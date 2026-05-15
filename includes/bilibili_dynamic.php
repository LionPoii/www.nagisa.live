<?php
/**
 * 哔哩哔哩动态API处理类
 * 获取用户动态列表
 * 参考文档: https://github.com/SocialSisterYi/bilibili-API-collect/blob/34f5c70174e0f31e6b4575f813705e9de6ba9633/docs/dynamic/space.md?plain=1#L1
 */

require_once __DIR__ . '/bilibili_rich_text.php';

class BilibiliDynamic {
    private $headers;
    private $cache_dir;
    private $log_dir;
    private $cache_time;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Referer: https://space.bilibili.com/',
            'Accept: application/json, text/plain, */*',
            'Connection: keep-alive',
            'Accept-Language: zh-CN,zh;q=0.9'
        ];
        
        // 默认缓存时间设置为很短
        $this->cache_time = 10; // 缓存10秒，确保能及时获取新动态
        
        // 检查是否有强制刷新参数
        if (isset($_GET['refresh_dynamic']) && $_GET['refresh_dynamic'] == '1' || 
            isset($_GET['t']) || // API请求中包含时间戳参数
            isset($_GET['force']) // 强制刷新参数
        ) {
            $this->cache_time = 0; // 强制刷新缓存
        }
        
        // 设置API日志目录
        $this->log_dir = __DIR__ . '/../logs';
        if (!is_dir($this->log_dir)) {
            @mkdir($this->log_dir, 0777, true); // 添加错误抑制符和更宽松的权限
        }
        
        // 创建dynamic_api目录
        $dynamic_log_dir = $this->log_dir . '/dynamic_api/';
        if (!is_dir($dynamic_log_dir)) {
            @mkdir($dynamic_log_dir, 0777, true);
        }
        
        // 使用当前用户可写的临时目录作为备用选项
        if (!is_dir($this->log_dir) || !is_writable($this->log_dir)) {
            // 使用系统临时目录
            $this->log_dir = sys_get_temp_dir() . '/nagisa_bili_logs';
            if (!is_dir($this->log_dir)) {
                @mkdir($this->log_dir, 0777, true);
            }
            
            // 创建dynamic_api目录
            $dynamic_log_dir = $this->log_dir . '/dynamic_api/';
            if (!is_dir($dynamic_log_dir)) {
                @mkdir($dynamic_log_dir, 0777, true);
            }
        }
    }
    
    /**
     * 发送HTTP GET请求
     * 
     * @param string $url 请求URL
     * @param array $params 请求参数
     * @return array|null 返回解析后的JSON数据，失败返回null
     */
    private function httpGet($url, $params = []) {
        // 构建带参数的URL
        if (!empty($params)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }
        
        // 初始化CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        // 执行请求
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 检查HTTP状态码
        if ($httpCode != 200) {
            return null;
        }
        
        // 解析JSON
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $data;
    }
    
    /**
     * 记录日志
     * 
     * @param string $message 日志消息
     * @param int $mid 用户ID
     * @param int $page 页码
     * @return void
     */
    private function logApiCall($message, $mid, $page) {
        // 日志功能已禁用
    }
    
    /**
     * 保存API响应数据
     * 
     * @param array $data API响应数据
     * @param int $mid 用户ID
     * @param int $page 页码
     * @return void
     */
    private function saveApiResponse($data, $mid, $page) {
        if (empty($data)) return;
        
        // 更新为新的缓存位置
        $save_dir = __DIR__ . '/../api/api_cache/dynamic_api/';
        if (!is_dir($save_dir)) {
            @mkdir($save_dir, 0777, true);
        }
        
        // 检查目录是否可写
        if (!is_writable($save_dir)) {
            return; // 如果目录不可写，放弃保存
        }
        
        // 获取当前时间
        $current_time = date('Y-m-d_H-i-s');
        // 格式化文件名
        $filename = $save_dir . "dynamic_api_mid{$mid}_p{$page}_" . $current_time . '.json';
        @file_put_contents($filename, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        try {
            // 清理旧文件，只保留最新的1个文件
            $files = glob($save_dir . "dynamic_api_mid{$mid}_p{$page}_*.json");
            
            // 按修改时间排序
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            // 保留第一个(最新的)文件，删除其余文件
            if (count($files) > 1) {
                for ($i = 1; $i < count($files); $i++) {
                    if (file_exists($files[$i])) {
                        @unlink($files[$i]);
                    }
                }
            }
        } catch (Exception $e) {
            // 忽略清理文件错误
        }
    }
    
    /**
     * 获取用户动态列表
     * 
     * @param int $mid 用户ID
     * @param int $page 页码，从1开始
     * @param int $page_size 每页大小，最大30
     * @param bool $force_refresh 是否强制刷新，不使用缓存
     * @return array|null 动态列表数据
     */
    public function getUserDynamics($mid, $page = 1, $page_size = 30, $force_refresh = false) {
        // 构建请求参数
        $params = [
            'host_mid' => $mid,
            'page_num' => $page,
            'page_size' => $page_size,
            'timezone_offset' => -480, // 东八区
            'features' => 'itemOpusStyle',
            'version' => '5.73'
        ];
        
        // API URL
        $api_url = "https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/space";
        
        // 检查是否有现有最新数据
        $save_dir = __DIR__ . '/../api/api_cache/dynamic_api/';
        $files = glob($save_dir . "dynamic_api_mid{$mid}_p{$page}_*.json");
        $expired_cache = null;
        
        // 如果强制刷新，则不使用缓存
        if (!$force_refresh) {
            // 按修改时间排序
            if (!empty($files)) {
                usort($files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                
                // 检查最新的文件是否在缓存时间内
                if (file_exists($files[0])) {
                    if (time() - filemtime($files[0]) <= $this->cache_time) {
                        $this->logApiCall("[CACHE HIT]", $mid, $page);
                        return json_decode(file_get_contents($files[0]), true);
                    } else {
                        $this->logApiCall("[CACHE EXPIRED]", $mid, $page);
                        $expired_cache = json_decode(file_get_contents($files[0]), true);
                    }
                }
            } else {
                $this->logApiCall("[CACHE MISS]", $mid, $page);
            }
        } else {
            $this->logApiCall("[FORCE REFRESH] Skipping cache", $mid, $page);
        }
        
        // 尝试从API获取数据
        try {
            // 获取数据库中的Cookie
            $cookie = $this->getBilibiliCookie();
            
            // 如果有Cookie，添加到请求头中
            if (!empty($cookie)) {
                // 移除旧的Cookie头（如果存在）
                foreach ($this->headers as $key => $header) {
                    if (strpos($header, 'Cookie:') === 0) {
                        unset($this->headers[$key]);
                    }
                }
                // 添加新的Cookie头
                $this->headers[] = 'Cookie: ' . $cookie;
                $this->logApiCall("[USING COOKIE]", $mid, $page);
            }
            
            // 支持通过header传cookie（优先级更高）
            if (isset($_SERVER['HTTP_X_BILICOOKIE']) && $_SERVER['HTTP_X_BILICOOKIE']) {
                foreach ($this->headers as $key => $header) {
                    if (strpos($header, 'Cookie:') === 0) {
                        unset($this->headers[$key]);
                    }
                }
                $this->headers[] = 'Cookie: ' . $_SERVER['HTTP_X_BILICOOKIE'];
                $this->logApiCall("[USING HEADER COOKIE]", $mid, $page);
            }
            
            // 发送请求
            $result = $this->httpGet($api_url, $params);
            
            // 记录API返回
            $this->logApiCall("[API RESP] CODE:" . ($result['code'] ?? 'unknown'), $mid, $page);
            
            // 处理API返回结果
            if (is_array($result) && isset($result['code'])) {
                if ($result['code'] === 0) {
                    // API请求成功
                    $this->saveApiResponse($result, $mid, $page);
                    $this->logApiCall("[CACHE WRITE]", $mid, $page);
                    return $result;
                } else {
                    // API返回错误代码
                    $this->logApiCall("[API ERROR] " . $result['code'], $mid, $page);
                }
            }
        } catch (Exception $e) {
            // 捕获可能的异常
            $this->logApiCall("[API EXCEPTION] " . $e->getMessage(), $mid, $page);
        }
        
        // 如果API请求失败或出错，使用过期缓存
        if ($expired_cache) {
            $this->logApiCall("[USING EXPIRED CACHE]", $mid, $page);
            return $expired_cache;
        }
        
        return null;
    }
    
    /**
     * 从数据库获取B站Cookie
     * 
     * @return string|null 返回Cookie字符串或null
     */
    private function getBilibiliCookie() {
        try {
            require_once __DIR__ . '/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_cookie'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['config_value'] : null;
        } catch (Exception $e) {
            $this->logApiCall("[DB ERROR] " . $e->getMessage(), 0, 0);
            return null;
        }
    }
    
    /**
     * 处理动态内容
     * 
     * @param array $dynamic 动态数据
     * @return array 处理后的动态数据
     */
    public function processDynamic($dynamic) {
        $processed = [
            'id' => $dynamic['id_str'] ?? '',
            'type' => $dynamic['type'] ?? '',
            'timestamp' => $dynamic['modules']['module_author']['pub_ts'] ?? 0,
            'time' => '',
            'content' => '',
            'images' => [],
            'video' => null,
            'card' => null,
            'is_pinned' => false,
            'is_forward' => false
        ];
        
        // 检查是否为置顶动态
        if (isset($dynamic['modules']['module_tag']['text']) && $dynamic['modules']['module_tag']['text'] === '置顶') {
            $processed['is_pinned'] = true;
        }
        
        // 检查是否为转发动态
        if (isset($dynamic['type']) && ($dynamic['type'] === 'DYNAMIC_TYPE_FORWARD' || 
            (isset($dynamic['modules']['module_dynamic']['major']['type']) && 
             $dynamic['modules']['module_dynamic']['major']['type'] === 'MAJOR_TYPE_FORWARD'))) {
            $processed['is_forward'] = true;
        }
        
        // 处理时间
        if ($processed['timestamp']) {
            $processed['time'] = $this->formatTime($processed['timestamp']);
        }
        
        // 处理文本内容
        $content = '';
        
        // 1. 检查普通动态文本
        if (isset($dynamic['modules']['module_dynamic']['desc']['rich_text_nodes'])) {
            $content = $this->extractText($dynamic['modules']['module_dynamic']['desc']['rich_text_nodes']);
        }
        
        // 2. 检查opus动态文本
        if (empty($content) && isset($dynamic['modules']['module_dynamic']['major']['opus']['summary']['rich_text_nodes'])) {
            $content = $this->extractText($dynamic['modules']['module_dynamic']['major']['opus']['summary']['rich_text_nodes']);
        }
        
        // 3. 检查其他可能的文本位置
        if (empty($content) && isset($dynamic['modules']['module_dynamic']['major']['opus']['title']['rich_text_nodes'])) {
            $content = $this->extractText($dynamic['modules']['module_dynamic']['major']['opus']['title']['rich_text_nodes']);
        }
        
        // 4. 检查卡片描述
        if (empty($content) && isset($dynamic['modules']['module_dynamic']['major']['draw']['desc'])) {
            $content = $dynamic['modules']['module_dynamic']['major']['draw']['desc'];
        }
        
        $processed['content'] = $content;
        
        // 处理图片
        $images = [];
        
        // 1. 检查opus动态图片
        if (isset($dynamic['modules']['module_dynamic']['major']['opus']['pics'])) {
            foreach ($dynamic['modules']['module_dynamic']['major']['opus']['pics'] as $pic) {
                if (isset($pic['url'])) {
                    $images[] = BilibiliRichText::normalizeMediaUrl($pic['url']);
                }
            }
        }
        
        // 2. 检查draw动态图片
        if (isset($dynamic['modules']['module_dynamic']['major']['draw']['pics'])) {
            foreach ($dynamic['modules']['module_dynamic']['major']['draw']['pics'] as $pic) {
                if (isset($pic['url'])) {
                    $images[] = BilibiliRichText::normalizeMediaUrl($pic['url']);
                }
            }
        }
        
        // 3. 检查其他可能的图片位置
        if (isset($dynamic['modules']['module_dynamic']['major']['common']['cover'])) {
            $cover = $dynamic['modules']['module_dynamic']['major']['common']['cover'];
            if (isset($cover['src'])) {
                $images[] = BilibiliRichText::normalizeMediaUrl($cover['src']);
            }
        }
        
        $processed['images'] = array_unique($images);
        
        // 处理视频
        if (isset($dynamic['modules']['module_dynamic']['major']['archive'])) {
            $archive = $dynamic['modules']['module_dynamic']['major']['archive'];
            $processed['video'] = [
                'bvid' => $archive['bvid'] ?? '',
                'title' => $archive['title'] ?? '',
                'cover' => BilibiliRichText::normalizeMediaUrl($archive['cover'] ?? ''),
                'duration' => $archive['duration_text'] ?? '',
                'view' => $archive['stat']['view'] ?? 0,
                'danmaku' => $archive['stat']['danmaku'] ?? 0,
                'play' => $archive['stat']['play'] ?? ''
            ];
        }
        
        // 处理卡片
        if (isset($dynamic['modules']['module_dynamic']['major']['draw'])) {
            $draw = $dynamic['modules']['module_dynamic']['major']['draw'];
            $processed['card'] = [
                'id' => $draw['id'] ?? '',
                'title' => $draw['title'] ?? '',
                'desc' => $draw['desc'] ?? '',
                'pics' => $this->extractImages($draw['pics'] ?? [])
            ];
        }
        
        return $processed;
    }
    
    /**
     * 提取富文本节点中的 HTML 片段（含表情 img/picture，逻辑见 BilibiliRichText）
     *
     * @param array $rich_text_nodes 富文本节点数组
     * @return string
     */
    private function extractText($rich_text_nodes) {
        return BilibiliRichText::richTextNodesToHtml($rich_text_nodes);
    }
    
    /**
     * 提取图片
     * 
     * @param array $pics 图片数组
     * @return array 图片URL数组
     */
    private function extractImages($pics) {
        $images = [];
        foreach ($pics as $pic) {
            $raw = null;
            if (isset($pic['url'])) {
                $raw = $pic['url'];
            } elseif (isset($pic['src'])) {
                $raw = $pic['src'];
            } elseif (isset($pic['image_src']['src'])) {
                $raw = $pic['image_src']['src'];
            } elseif (isset($pic['image_src']['remote']['url'])) {
                $raw = $pic['image_src']['remote']['url'];
            }
            if ($raw !== null) {
                $images[] = BilibiliRichText::normalizeMediaUrl($raw);
            }
        }
        return $images;
    }
    
    /**
     * 格式化时间
     * 
     * @param int $timestamp 时间戳
     * @return string 格式化后的时间
     */
    private function formatTime($timestamp) {
        $now = time();
        $diff = $now - $timestamp;
        $today_start = strtotime('today 00:00:00');
        
        if ($timestamp >= $today_start) {
            // 今天发布的，显示时:分
            return date('H:i', $timestamp);
        } else {
            // 其他时间都显示月/日格式
            return date('m/d', $timestamp);
        }
    }
    
    /**
     * 获取处理后的动态列表
     * 
     * @param int $mid 用户ID
     * @param int $page 页码
     * @param int $page_size 每页大小
     * @param bool $force_refresh 是否强制刷新，不使用缓存
     * @return array 处理后的动态列表
     */
    public function getProcessedDynamics($mid, $page = 1, $page_size = 30, $force_refresh = false) {
        // 记录请求开始时间
        $start_time = microtime(true);
        
        // 获取API数据
        $result = $this->getUserDynamics($mid, $page, $page_size, $force_refresh);
        
        // 记录API请求耗时
        $api_time = microtime(true) - $start_time;
        $this->logApiCall("[API REQUEST TIME] " . round($api_time * 1000) . "ms", $mid, $page);
        
        if (empty($result) || !isset($result['data']['items']) || !is_array($result['data']['items'])) {
            $this->logApiCall("[PROCESS DYNAMICS FAILED] Empty or invalid result", $mid, $page);
            return [];
        }
        
        // 记录原始动态数量
        $original_count = count($result['data']['items']);
        $this->logApiCall("[ORIGINAL DYNAMICS] Count:" . $original_count, $mid, $page);
        
        $dynamics = [];
        foreach ($result['data']['items'] as $dynamic) {
            // 过滤掉DYNAMIC_TYPE_LIVE_RCMD类型的动态
            if (isset($dynamic['type']) && $dynamic['type'] === 'DYNAMIC_TYPE_LIVE_RCMD') {
                continue;
            }
            
            $dynamics[] = $this->processDynamic($dynamic);
        }
        
        // 记录处理后动态数量和总处理时间
        $total_time = microtime(true) - $start_time;
        $this->logApiCall("[PROCESSED DYNAMICS] Count:" . count($dynamics) . " Total Time:" . round($total_time * 1000) . "ms", $mid, $page);
        
        // 记录缓存时间设置
        $this->logApiCall("[CACHE TIME] " . $this->cache_time . "s", $mid, $page);
        
        return $dynamics;
    }
    
    /**
     * 获取动态总数
     * 
     * @param int $mid 用户ID
     * @return int 动态总数
     */
    public function getDynamicCount($mid) {
        $result = $this->getUserDynamics($mid, 1, 1);
        
        if (empty($result) || !isset($result['data']['page']['total'])) {
            return 0;
        }
        
        return $result['data']['page']['total'];
    }
} 