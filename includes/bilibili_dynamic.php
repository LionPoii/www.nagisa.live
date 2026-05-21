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
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer: https://space.bilibili.com/',
            'Origin: https://space.bilibili.com',
            'Accept: application/json, text/plain, */*',
            'Connection: keep-alive',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8'
        ];
        
        $this->cache_time = 10;
        
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
     * 设置请求头（Cookie、Referer）
     *
     * @param int $mid 用户ID
     * @return void
     */
    private function applyRequestHeaders($mid) {
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer: https://space.bilibili.com/' . $mid . '/dynamic',
            'Origin: https://space.bilibili.com',
            'Accept: application/json, text/plain, */*',
            'Connection: keep-alive',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8'
        ];
        
        $cookie = null;
        if (isset($_SERVER['HTTP_X_BILICOOKIE']) && $_SERVER['HTTP_X_BILICOOKIE']) {
            $cookie = $_SERVER['HTTP_X_BILICOOKIE'];
        } else {
            $cookie = $this->getBilibiliCookie();
        }
        
        if (!empty($cookie)) {
            $headers[] = 'Cookie: ' . $cookie;
        }
        
        $this->headers = $headers;
    }
    
    /**
     * 校验动态 API 响应是否有效
     *
     * @param array|null $result API 响应
     * @return bool
     */
    private function isValidDynamicResponse($result) {
        return is_array($result)
            && isset($result['code'])
            && $result['code'] === 0
            && isset($result['data']['items'])
            && is_array($result['data']['items']);
    }
    
    /**
     * 通过 feed/all 接口获取用户动态（feed/space 已被风控拦截）
     *
     * @param int $mid 用户ID
     * @param int $page 页码
     * @param int $page_size 每页大小
     * @return array|null
     */
    private function fetchFeedAllPage($mid, $page, $page_size) {
        $api_url = 'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/all';
        $offset = '';
        $current_page = 1;
        $result = null;
        
        while ($current_page <= $page) {
            $params = [
                'host_mid' => $mid,
                'offset' => $offset,
                'page' => $current_page,
                'features' => 'itemOpusStyle',
                'timezone_offset' => -480,
            ];
            
            if ($page_size > 0) {
                $params['page_size'] = min($page_size, 30);
            }
            
            $this->applyRequestHeaders($mid);
            $result = $this->httpGet($api_url, $params);
            
            if (!$this->isValidDynamicResponse($result)) {
                return null;
            }
            
            if ($current_page === $page) {
                break;
            }
            
            if (empty($result['data']['has_more'])) {
                return null;
            }
            
            $offset = $result['data']['offset'] ?? '';
            $current_page++;
        }
        
        if ($page_size > 0 && !empty($result['data']['items'])) {
            $result['data']['items'] = array_slice($result['data']['items'], 0, $page_size);
        }
        
        $item_count = count($result['data']['items']);
        $result['data']['page'] = [
            'total' => empty($result['data']['has_more']) ? $item_count : max($item_count, $page * $page_size)
        ];
        
        return $result;
    }

    /**
     * 通过 feed/space 获取用户动态（含置顶标签与图文 draw.items，需有效 Cookie）
     *
     * @param int $mid 用户ID
     * @param int $page 页码
     * @param int $page_size 每页大小
     * @return array|null
     */
    private function fetchFeedSpacePage($mid, $page, $page_size) {
        $api_url = 'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/space';
        $params = [
            'host_mid' => $mid,
            'offset' => '',
            'page' => max(1, (int) $page),
            'features' => 'itemOpusStyle',
            'timezone_offset' => -480,
        ];

        if ($page_size > 0) {
            $params['page_size'] = min($page_size, 30);
        }

        $this->applyRequestHeaders($mid);
        $result = $this->httpGet($api_url, $params);

        if (!$this->isValidDynamicResponse($result)) {
            return null;
        }

        if ($page_size > 0 && !empty($result['data']['items'])) {
            $result['data']['items'] = array_slice($result['data']['items'], 0, $page_size);
        }

        $item_count = count($result['data']['items']);
        $result['data']['page'] = [
            'total' => empty($result['data']['has_more']) ? $item_count : max($item_count, $page * max($page_size, 1))
        ];

        return $result;
    }

    /**
     * 是否已配置可用于 feed/space 的 B 站 Cookie
     */
    private function hasBilibiliCookie() {
        $cookie = $this->getBilibiliCookie();
        return is_string($cookie) && trim($cookie) !== '';
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
     * 获取指定用户/页码的最新缓存文件路径
     *
     * @param int $mid 用户ID
     * @param int $page 页码
     * @return string|null
     */
    private function getLatestCacheFile($mid, $page) {
        $save_dir = __DIR__ . '/../api/api_cache/dynamic_api/';
        $files = glob($save_dir . "dynamic_api_mid{$mid}_p{$page}_*.json");
        if (empty($files)) {
            return null;
        }
        
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        return $files[0];
    }
    
    /**
     * 从 API 原始动态项提取图片 URL（用于缓存指纹，检测换图但未改发布时间的情况）
     *
     * @param array $item 单条动态
     * @return string 排序后的 URL 列表，逗号分隔
     */
    private function extractRawItemImageUrls($item) {
        $urls = [];
        $major = $item['modules']['module_dynamic']['major'] ?? [];

        if (!empty($major['opus']['pics']) && is_array($major['opus']['pics'])) {
            foreach ($major['opus']['pics'] as $pic) {
                if (!empty($pic['url'])) {
                    $urls[] = (string) $pic['url'];
                }
            }
        }

        if (!empty($major['draw']['pics']) && is_array($major['draw']['pics'])) {
            foreach ($major['draw']['pics'] as $pic) {
                if (!empty($pic['url'])) {
                    $urls[] = (string) $pic['url'];
                } elseif (!empty($pic['src'])) {
                    $urls[] = (string) $pic['src'];
                }
            }
        }

        if (!empty($major['draw']['items']) && is_array($major['draw']['items'])) {
            foreach ($major['draw']['items'] as $pic) {
                if (!empty($pic['src'])) {
                    $urls[] = (string) $pic['src'];
                }
            }
        }

        if (!empty($major['common']['cover']['src'])) {
            $urls[] = (string) $major['common']['cover']['src'];
        }

        $urls = array_values(array_unique($urls));
        sort($urls);

        return implode(',', $urls);
    }

    /**
     * 提取动态数据的对比指纹（ID + 发布时间 + 图片 URL）
     *
     * @param array|null $data API 响应数据
     * @return string
     */
    private function getDynamicDataFingerprint($data) {
        if (empty($data['data']['items']) || !is_array($data['data']['items'])) {
            return '';
        }
        
        $parts = [];
        foreach ($data['data']['items'] as $item) {
            $parts[] = ($item['id_str'] ?? '')
                . ':'
                . ($item['modules']['module_author']['pub_ts'] ?? 0)
                . ':'
                . $this->extractRawItemImageUrls($item);
        }
        
        return md5(implode('|', $parts));
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
        
        $save_dir = __DIR__ . '/../api/api_cache/dynamic_api/';
        if (!is_dir($save_dir)) {
            @mkdir($save_dir, 0777, true);
        }
        
        if (!is_writable($save_dir)) {
            return;
        }
        
        $latest_file = $this->getLatestCacheFile($mid, $page);
        if ($latest_file && file_exists($latest_file)) {
            $existing = json_decode(file_get_contents($latest_file), true);
            if ($this->getDynamicDataFingerprint($existing) === $this->getDynamicDataFingerprint($data)) {
                @touch($latest_file);
                return;
            }
        }
        
        $current_time = date('Y-m-d_H-i-s');
        $filename = $save_dir . "dynamic_api_mid{$mid}_p{$page}_" . $current_time . '.json';
        @file_put_contents($filename, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        try {
            $files = glob($save_dir . "dynamic_api_mid{$mid}_p{$page}_*.json");
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
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
        $expired_cache = null;
        $latest_file = $this->getLatestCacheFile($mid, $page);
        
        if ($latest_file && file_exists($latest_file)) {
            $cached = json_decode(file_get_contents($latest_file), true);
            
            if ($this->isValidDynamicResponse($cached)) {
                if (!$force_refresh && (time() - filemtime($latest_file)) <= $this->cache_time) {
                    $this->logApiCall("[CACHE HIT]", $mid, $page);
                    return $cached;
                }
                
                $expired_cache = $cached;
                $this->logApiCall($force_refresh ? "[FORCE REFRESH]" : "[CACHE EXPIRED]", $mid, $page);
            }
        } else {
            $this->logApiCall("[CACHE MISS]", $mid, $page);
        }
        
        // 有 Cookie 时优先 feed/space（含置顶与图文原图）；否则或失败时回退 feed/all
        try {
            $result = null;

            if ($this->hasBilibiliCookie()) {
                $result = $this->fetchFeedSpacePage($mid, $page, $page_size);
                if ($this->isValidDynamicResponse($result)) {
                    $this->saveApiResponse($result, $mid, $page);
                    $this->logApiCall("[CACHE WRITE space]", $mid, $page);
                    return $result;
                }
                $this->logApiCall("[SPACE FEED FAILED, fallback all]", $mid, $page);
            }

            $result = $this->fetchFeedAllPage($mid, $page, $page_size);
            
            $this->logApiCall("[API RESP] CODE:" . ($result['code'] ?? 'unknown'), $mid, $page);
            
            if ($this->isValidDynamicResponse($result)) {
                $this->saveApiResponse($result, $mid, $page);
                $this->logApiCall("[CACHE WRITE all]", $mid, $page);
                return $result;
            }
            
            if (is_array($result) && isset($result['code'])) {
                $this->logApiCall("[API ERROR] " . $result['code'], $mid, $page);
            }
        } catch (Exception $e) {
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
        
        // 2. 检查 draw 动态图片（pics 或 feed/space 的 items）
        if (isset($dynamic['modules']['module_dynamic']['major']['draw']['pics'])) {
            foreach ($dynamic['modules']['module_dynamic']['major']['draw']['pics'] as $pic) {
                if (isset($pic['url'])) {
                    $images[] = BilibiliRichText::normalizeMediaUrl($pic['url']);
                } elseif (isset($pic['src'])) {
                    $images[] = BilibiliRichText::normalizeMediaUrl($pic['src']);
                }
            }
        }
        if (isset($dynamic['modules']['module_dynamic']['major']['draw']['items'])) {
            foreach ($dynamic['modules']['module_dynamic']['major']['draw']['items'] as $pic) {
                if (isset($pic['src'])) {
                    $images[] = BilibiliRichText::normalizeMediaUrl($pic['src']);
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
     * 获取置顶动态的第一张图片 URL（周表用，始终走 feed/space 拉最新）
     *
     * @param int|string $mid B 站用户 mid
     * @return string https 图片地址，未找到时返回空字符串
     */
    public function getPinnedDynamicFirstImageUrl($mid) {
        if (!$this->hasBilibiliCookie()) {
            return '';
        }

        $result = $this->fetchFeedSpacePage($mid, 1, 20);
        if (!$this->isValidDynamicResponse($result)) {
            return '';
        }

        foreach ($result['data']['items'] as $item) {
            $tag = $item['modules']['module_tag']['text'] ?? '';
            if ($tag !== '置顶') {
                continue;
            }

            $processed = $this->processDynamic($item);
            if (!empty($processed['images'][0])) {
                return $processed['images'][0];
            }
        }

        return '';
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
        $result = $this->getUserDynamics($mid, 1, 12);
        
        if (empty($result) || !isset($result['data']['items'])) {
            return 0;
        }
        
        $count = count($result['data']['items']);
        if (!empty($result['data']['has_more'])) {
            return max($count, 12);
        }
        
        return $count;
    }
} 