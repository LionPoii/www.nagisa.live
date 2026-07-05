<?php
/**
 * 哔哩哔哩(B站)API PHP实现
 * 获取用户投稿视频列表
 * 参考文档:
 * - 用户空间API: https://github.com/SocialSisterYi/bilibili-API-collect/blob/34f5c70174e0f31e6b4575f813705e9de6ba9633/docs/user/space.md?plain=1#L2552
 * - WBI签名: https://github.com/SocialSisterYi/bilibili-API-collect/blob/34f5c70174e0f31e6b4575f813705e9de6ba9633/docs/misc/sign/wbi.md?plain=1
 */

class BilibiliAPI {
    // HTTP请求头
    private $headers;
    
    // WBI签名的映射表
    private $mixinKeyEncTab = [
        46, 47, 18, 2, 53, 8, 23, 32, 15, 50, 10, 31, 58, 3, 45, 35, 27, 43, 5, 49,
        33, 9, 42, 19, 29, 28, 14, 39, 12, 38, 41, 13, 37, 48, 7, 16, 24, 55, 40,
        61, 26, 17, 0, 1, 60, 51, 30, 4, 22, 25, 54, 21, 56, 59, 6, 63, 57, 62, 11,
        36, 20, 34, 44, 52
    ];
    
    // 缓存WBI签名密钥
    private $img_key = "";
    private $sub_key = "";
    private $last_wbi_fetch_time = 0;
    
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
     * 对 imgKey 和 subKey 进行字符顺序打乱编码
     * 
     * @param string $orig 原始字符串
     * @return string 打乱后的字符串
     */
    private function getMixinKey($orig) {
        $result = '';
        foreach ($this->mixinKeyEncTab as $i) {
            $result .= isset($orig[$i]) ? $orig[$i] : '';
        }
        return substr($result, 0, 32);
    }
    
    /**
     * 获取最新的 img_key 和 sub_key
     * 
     * @return array [img_key, sub_key]
     */
    private function getWbiKeys() {
        // 检查缓存是否过期（1小时更新一次）
        $current_time = time();
        if ($current_time - $this->last_wbi_fetch_time < 3600 && !empty($this->img_key) && !empty($this->sub_key)) {
            return [$this->img_key, $this->sub_key];
        }
        
        // 支持通过header传cookie
        $custom_cookie = '';
        if (isset($_SERVER['HTTP_X_BILICOOKIE']) && $_SERVER['HTTP_X_BILICOOKIE']) {
            $custom_cookie = $_SERVER['HTTP_X_BILICOOKIE'];
        }
        if ($custom_cookie) {
            $this->headers[] = 'Cookie: ' . $custom_cookie;
        } else {
            $this->headers[] = 'Cookie: buvid3=71945689-CD8D-8CC9-BD60-006A9409189517629infoc; b_nut=1734897617; _uuid=2911326D-54C2-910AB-4610E-C8ED54935BD617921infoc; buvid4=FEA504ED-6175-2E0A-E04B-D5862940B52217993-024122220-wS7p99u5gVg7WI1iY8qDLw%3D%3D; enable_web_push=DISABLE; DedeUserID=1979338810; DedeUserID__ckMd5=24d52e954875ae86; rpdid=|(kRm|J)Jm|0J\'u~JR)m~Yml; header_theme_version=CLOSE; enable_feed_channel=ENABLE; hit-dyn-v2=1; opus-goback=1; buvid_fp_plain=undefined; CURRENT_QUALITY=80; LIVE_BUVID=AUTO9017482642133905; dy_spec_agreed=1; fingerprint=e43d7756432416907bed9e705405e85f; buvid_fp=e43d7756432416907bed9e705405e85f; PVID=2; SESSDATA=2f23e51c%2C1765115864%2C46bb4%2A61CjB2yBCEUStfQ87400P2iCYijU34o8QJjVfUQGgCN0FF1zwp-MH0YhCmGC5Z8SDAXAoSVmZHTXRWNUxyMTFjSnJzLXUwbUgtN01CWWV2cWZ6TkxtN3R3QUVtTGJleFVaVFhZUkVsTU5lQ2lZd2RLQmpuRDBmY3V5eTFoOXJvNm51UEtjSGJ2enJnIIEC; bili_jct=0903e603c2759b75b727f104e2df6e59; b_lsid=E8B7AF107_1975FEAEB57; bili_ticket=eyJhbGciOiJIUzI1NiIsImtpZCI6InMwMyIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NDk5MjAxMjEsImlhdCI6MTc0OTY2MDg2MSwicGx0IjotMX0.Li-kr-evJcqaz1dFV5z6nxa87q9kNDOR4HQFXQqQqHg; bili_ticket_expires=1749920061; home_feed_column=5; browser_resolution=2005-1317; CURRENT_FNVAL=2000; sid=g43ekb6v; bp_t_offset_1979338810=1077276680868855808';
        }
        
        try {
            // 请求获取WBI密钥
            $result = $this->httpGet('https://api.bilibili.com/x/web-interface/nav');
            
            if (empty($result)) {
                log_api_response("获取WBI密钥失败: 请求返回为空");
                return ['', '']; // 返回空值避免异常
            }
            
            if ($result['code'] !== 0) {
                $message = "获取WBI密钥失败: {$result['message']}";
                log_api_response($message);
                return ['', '']; // 返回空值避免异常
            }
            
            if (empty($result['data']['wbi_img'])) {
                $message = "获取WBI密钥失败: 返回数据中缺少wbi_img字段";
                log_api_response($message);
                return ['', '']; // 返回空值避免异常
            }
            
            $img_url = $result['data']['wbi_img']['img_url'];
            $sub_url = $result['data']['wbi_img']['sub_url'];
            
            // 提取img_key和sub_key
            $this->img_key = explode('.', basename($img_url))[0];
            $this->sub_key = explode('.', basename($sub_url))[0];
            $this->last_wbi_fetch_time = $current_time;
            
            return [$this->img_key, $this->sub_key];
        } catch (Exception $e) {
            log_api_response("获取WBI密钥异常: " . $e->getMessage());
            return ['', '']; // 返回空值避免异常
        }
    }
    
    /**
     * 为请求参数进行 wbi 签名
     * 
     * @param array $params 请求参数
     * @return array 带签名的请求参数
     */
    private function encWbi($params) {
        try {
            // 获取WBI密钥
            list($img_key, $sub_key) = $this->getWbiKeys();
            
            // 如果密钥为空，不进行签名直接返回原始参数
            if (empty($img_key) || empty($sub_key)) {
                log_api_response("WBI密钥为空，跳过签名");
                return $params;
            }
            
            $mixin_key = $this->getMixinKey($img_key . $sub_key);
            
            // 添加时间戳
            $params['wts'] = time();
            
            // 按键名排序
            ksort($params);
            
            // 过滤特殊字符
            foreach ($params as $key => $value) {
                $params[$key] = preg_replace('/[!\'()*]/', '', strval($value));
            }
            
            // 构建查询字符串
            $query = http_build_query($params);
            
            // 计算w_rid
            $wbi_sign = md5($query . $mixin_key);
            $params['w_rid'] = $wbi_sign;
        } catch (Exception $e) {
            log_api_response("WBI签名异常: " . $e->getMessage());
        }
        
        return $params;
    }
    
    /**
     * 获取用户投稿的视频列表
     * 
     * @param int $mid 用户ID
     * @param int $page 页码，从1开始
     * @param int $page_size 每页大小，最大30
     * @param string $keyword 搜索关键词，默认为空
     * @return array|null 用户视频列表数据
     */
    public function getUserVideos($mid, $page = 1, $page_size = 30, $keyword = "") {
        $api_url = "https://api.bilibili.com/x/space/wbi/arc/search";
        
        // 构建请求参数
        $params = [
            'mid' => $mid,
            'ps' => $page_size,
            'pn' => $page,
            'order' => 'pubdate', // 按发布日期排序
            'platform' => 'web',
            'web_location' => 1550101,
        ];
        
        // 添加可选参数
        if (!empty($keyword)) {
            $params['keyword'] = $keyword;
        }
        
        // 进行WBI签名
        $signed_params = $this->encWbi($params);
        
        // 发送请求
        $result = $this->httpGet($api_url, $signed_params);
        
        if (empty($result) || $result['code'] !== 0) {
            log_api_response("获取用户视频失败: " . ($result['message'] ?? '未知错误'));
            return null;
        }
        
        // 记录API响应并缓存数据
        log_api_response("成功获取用户 $mid 第 $page 页视频数据");
        cache_api_data("user_{$mid}_videos_page_{$page}", $result['data']);
        
        return $result['data'];
    }
    
    /**
     * 获取用户所有投稿视频
     * 
     * @param int $mid 用户ID
     * @param string $keyword 搜索关键词，默认为空
     * @return array|null 用户所有视频的列表
     */
    public function getAllUserVideos($mid, $keyword = "") {
        $page = 1;
        $page_size = 30;
        $all_videos = [];
        
        log_api_response("开始获取用户 $mid 的所有视频");
        
        while (true) {
            // 获取一页视频数据
            $data = $this->getUserVideos($mid, $page, $page_size, $keyword);
            
            // 检查是否获取成功
            if (empty($data) || empty($data['list']['vlist'])) {
                break;
            }
            
            $videos = $data['list']['vlist'];
            $all_videos = array_merge($all_videos, $videos);
            
            // 检查是否还有更多视频
            if (count($videos) < $page_size) {
                break;
            }
            
            // 请求间隔，避免频率过高
            sleep(1);
            $page++;
        }
        
        log_api_response("成功获取用户 $mid 的所有视频，共 " . count($all_videos) . " 个");
        cache_api_data("user_{$mid}_all_videos", $all_videos);
        
        return $all_videos;
    }

    /**
     * 处理图片URL
     * 
     * @param string $url 原始图片URL
     * @return string 处理后的URL
     */
    private function processImageUrl($url) {
        // 如果URL为空，返回默认图片
        if (empty($url)) {
            return 'https://i0.hdslb.com/bfs/archive/1c471796343c26a6c7688b2e2e2d50e0a8b0c42c.jpg';
        }
        
        // 如果已经是完整的URL，直接返回
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }
        
        // 如果是相对路径，添加域名
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        } else if (strpos($url, '/') === 0) {
            // 检查是否是B站的图片路径
            if (strpos($url, '/bfs/') !== false) {
                $url = 'https://i0.hdslb.com' . $url;
            } else {
                $url = 'https://i1.hdslb.com' . $url;
            }
        }
        
        // 确保URL是HTTPS
        $url = str_replace('http://', 'https://', $url);
        
        return $url;
    }

    /**
     * 生成视频列表的HTML
     * 
     * @param array $videos 视频数据数组
     * @param int $current_page 当前页码
     * @param int $total_pages 总页数
     * @param int $mid 用户ID
     * @return string HTML字符串
     */
    public function generateVideoListHTML($videos, $current_page, $total_pages, $mid) {
        $html = '<div class="video-grid">';
        
        foreach ($videos as $video) {
            // 处理图片URL，直接使用B站直链
            $pic_url = $this->processImageUrl($video['pic']);
            
            $html .= sprintf(
                '<div class="video-card">
                    <a href="https://www.bilibili.com/video/%s" target="_blank">
                        <img src="%s" alt="%s" class="video-thumbnail" loading="lazy" referrerpolicy="no-referrer" onerror="this.onerror=null;this.src=\'https://i0.hdslb.com/bfs/archive/1c471796343c26a6c7688b2e2e2d50e0a8b0c42c.jpg\';">
                        <div class="video-info">
                            <h3 class="video-title">%s</h3>
                            <div class="video-meta">
                                <span>播放: %s</span> • 
                                <span>弹幕: %s</span> • 
                                <span>%s</span>
                            </div>
                        </div>
                    </a>
                </div>',
                htmlspecialchars($video['bvid']),
                htmlspecialchars($pic_url),
                htmlspecialchars($video['title']),
                htmlspecialchars($video['title']),
                number_format($video['play']),
                number_format($video['video_review']),
                date('Y-m-d', $video['created'])
            );
        }
        
        $html .= '</div>';
        
        // 添加分页
        if ($total_pages > 1) {
            $html .= '<div class="pagination">';
            
            // 上一页
            if ($current_page > 1) {
                $html .= sprintf(
                    '<a href="?page=%d">&laquo; 上一页</a>',
                    $current_page - 1
                );
            }
            
            // 页码
            for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
                if ($i == $current_page) {
                    $html .= sprintf('<span class="current">%d</span>', $i);
                } else {
                    $html .= sprintf('<a href="?page=%d">%d</a>', $i, $i);
                }
            }
            
            // 下一页
            if ($current_page < $total_pages) {
                $html .= sprintf(
                    '<a href="?page=%d">下一页 &raquo;</a>',
                    $current_page + 1
                );
            }
            
            $html .= '</div>';
        }
        
        return $html;
    }

    /**
     * 获取用户动态列表
     * 
     * @param int $mid 用户ID
     * @param int $page 页码，从1开始
     * @param int $page_size 每页大小，最大30
     * @return array|null 用户动态列表数据
     */
    public function getUserDynamics($mid, $page = 1, $page_size = 30) {
        $api_url = "https://api.bilibili.com/x/dynamic/feed/draw/doc_list";
        
        // 构建请求参数
        $params = [
            'uid' => $mid,
            'page_num' => $page,
            'page_size' => $page_size,
            'biz' => 'all'
        ];
        
        // 发送请求
        $result = $this->httpGet($api_url, $params);
        
        if (empty($result) || $result['code'] !== 0) {
            return null;
        }
        
        // 记录API响应
        log_api_response($result);
        
        return $result['data'];
    }
}

/**
 * 记录API响应日志
 * @param string $content 日志内容
 */
function log_api_response($content) {
    $log_dir = __DIR__ . '/../logs/spacevideos_api';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/spacevideos_api_' . date('Y-m-d') . '.log';
    file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ' . $content . "\n", FILE_APPEND);
}

/**
 * 缓存API返回数据
 * @param string $key 缓存键名
 * @param mixed $data 要缓存的数据
 */
function cache_api_data($key, $data) {
    $cache_dir = __DIR__ . '/api_cache/spacevideos';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $cache_file = $cache_dir . '/' . $key . '_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($cache_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    // 清理旧文件，只保留最新的文件
    $pattern = $cache_dir . '/' . $key . '_*.json';
    $files = glob($pattern);
    
    if (count($files) > 1) {
        // 按修改时间排序
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // 保留最新的1个文件，删除其余文件
        for ($i = 1; $i < count($files); $i++) {
            if (file_exists($files[$i]) && $files[$i] !== $cache_file) {
                unlink($files[$i]);
            }
        }
    }
    
    // 记录缓存操作
    log_api_response("已缓存数据到文件: " . basename($cache_file));
}

// 使用示例
if (isset($_GET['mid']) && is_numeric($_GET['mid'])) {
    require_once __DIR__ . '/../includes/api_no_cache_headers.php';
    $mid = intval($_GET['mid']);
    $action = isset($_GET['action']) ? $_GET['action'] : 'videos';
    
    $bili = new BilibiliAPI();
    
    // 设置JSON响应头
    header('Content-Type: application/json; charset=utf-8');
    
    // 根据action执行不同操作
    switch ($action) {
        case 'videos':
            // 获取视频列表参数
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 30;
            $keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
            
            // 获取单页视频
            $video_data = $bili->getUserVideos($mid, $page, $page_size, $keyword);
            
            if ($video_data) {
                $response = [
                    'code' => 0,
                    'message' => 'success',
                    'data' => $video_data
                ];
                
                // 缓存API响应
                cache_api_data("api_videos_response_{$mid}_page_{$page}", $response);
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
            } else {
                $response = [
                    'code' => -1,
                    'message' => '获取视频列表失败',
                    'data' => null
                ];
                
                // 记录并缓存错误响应
                log_api_response("获取视频列表失败，用户ID: $mid");
                cache_api_data("api_videos_error_{$mid}_page_{$page}", $response);
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'dynamics':
            // 获取动态列表参数
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 30;
            
            // 获取动态列表
            $dynamics = $bili->getUserDynamics($mid, $page, $page_size);
            
            if ($dynamics) {
                $response = [
                    'code' => 0,
                    'message' => 'success',
                    'data' => $dynamics
                ];
                
                // 缓存API响应
                cache_api_data("api_dynamics_response_{$mid}_page_{$page}", $response);
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
            } else {
                $response = [
                    'code' => -1,
                    'message' => '获取动态列表失败',
                    'data' => null
                ];
                
                // 记录并缓存错误响应
                log_api_response("获取动态列表失败，用户ID: $mid");
                cache_api_data("api_dynamics_error_{$mid}_page_{$page}", $response);
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
            }
            break;
            
        default:
            $response = [
                'code' => -1,
                'message' => '未知操作: ' . $action,
                'data' => null
            ];
            
            log_api_response("未知操作: $action, 用户ID: $mid");
            cache_api_data("api_unknown_action_{$action}_{$mid}", $response);
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
} else {
    // 返回错误信息
    echo json_encode([
        'code' => -1,
        'message' => '缺少必要的参数mid',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?> 