<?php
/**
 * 哔哩哔哩动态API处理类
 * 获取用户动态列表
 * 参考文档: https://github.com/SocialSisterYi/bilibili-API-collect/blob/34f5c70174e0f31e6b4575f813705e9de6ba9633/docs/dynamic/space.md?plain=1#L1
 */

require_once __DIR__ . '/bilibili_rich_text.php';

class BilibiliDynamic {
    public const DISPLAY_COUNT = 18;

    private $headers;
    private $cache_dir;
    private $log_dir;
    private $cache_time;
    private $img_key = '';
    private $sub_key = '';
    private $last_wbi_fetch_time = 0;
    private $mixinKeyEncTab = [
        46, 47, 18, 2, 53, 8, 23, 32, 15, 50, 10, 31, 58, 3, 45, 35, 27, 43, 5, 49,
        33, 9, 42, 19, 29, 28, 14, 39, 12, 38, 41, 13, 37, 48, 7, 16, 24, 55, 40,
        61, 26, 17, 0, 1, 60, 51, 30, 4, 22, 25, 54, 21, 56, 59, 6, 63, 57, 62, 11,
        36, 20, 34, 44, 52
    ];
    
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
        
        $this->cache_time = 300;
        
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        
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
            $headers[] = 'Cookie: ' . $this->ensureCookieWithBuvid($cookie);
        }
        
        $this->headers = $headers;
    }

    /**
     * 补全 Cookie 中的 buvid3（B 站接口风控需要）
     */
    private function ensureCookieWithBuvid($cookie) {
        $cookie = trim((string) $cookie);
        if ($cookie === '') {
            return $cookie;
        }
        if (stripos($cookie, 'buvid3=') !== false) {
            return $cookie;
        }

        $ch = curl_init('https://api.bilibili.com/x/frontend/finger/spi');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data) || ($data['code'] ?? -1) !== 0) {
            return $cookie;
        }

        $buvid3 = $data['data']['b_3'] ?? '';
        $buvid4 = $data['data']['b_4'] ?? '';
        if ($buvid3 !== '') {
            $cookie .= '; buvid3=' . $buvid3;
        }
        if ($buvid4 !== '') {
            $cookie .= '; buvid4=' . $buvid4;
        }
        return $cookie;
    }

    private function getMixinKey($orig) {
        $result = '';
        foreach ($this->mixinKeyEncTab as $i) {
            $result .= isset($orig[$i]) ? $orig[$i] : '';
        }
        return substr($result, 0, 32);
    }

    /**
     * 获取 WBI 签名密钥（使用当前 Cookie）
     */
    private function getWbiKeys() {
        if (time() - $this->last_wbi_fetch_time < 3600 && $this->img_key !== '' && $this->sub_key !== '') {
            return [$this->img_key, $this->sub_key];
        }

        $savedHeaders = $this->headers;
        $cookie = $this->getBilibiliCookie();
        if ($cookie) {
            $this->headers = [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Referer: https://www.bilibili.com/',
                'Cookie: ' . $this->ensureCookieWithBuvid($cookie),
            ];
        }

        $result = $this->httpGet('https://api.bilibili.com/x/web-interface/nav');
        $this->headers = $savedHeaders;

        if (!is_array($result) || ($result['code'] ?? -1) !== 0 || empty($result['data']['wbi_img'])) {
            return ['', ''];
        }

        $img_url = $result['data']['wbi_img']['img_url'];
        $sub_url = $result['data']['wbi_img']['sub_url'];
        $this->img_key = explode('.', basename($img_url))[0];
        $this->sub_key = explode('.', basename($sub_url))[0];
        $this->last_wbi_fetch_time = time();

        return [$this->img_key, $this->sub_key];
    }

    /**
     * WBI 参数签名
     */
    private function signWbiParams(array $params) {
        list($img_key, $sub_key) = $this->getWbiKeys();
        if ($img_key === '' || $sub_key === '') {
            return $params;
        }

        $mixin_key = $this->getMixinKey($img_key . $sub_key);
        $params['wts'] = time();
        ksort($params);
        foreach ($params as $key => $value) {
            $params[$key] = preg_replace('/[!\'()*]/', '', (string) $value);
        }
        $params['w_rid'] = md5(http_build_query($params) . $mixin_key);
        return $params;
    }

    /**
     * B 站 -352 风控所需参数
     */
    private function getAntiRiskParams() {
        return [
            'platform' => 'web',
            'web_location' => '333.1387',
            'dm_img_list' => '[{"x":6289,"y":-1428,"z":0,"timestamp":40,"type":0}]',
            'dm_img_str' => 'V2ViR0wgMS',
            'dm_cover_img_str' => 'QU1ESV9GeF9IRTU1MngDTUVD',
        ];
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
            && is_array($result['data']['items'])
            && count($result['data']['items']) > 0;
    }
    
    /**
     * 构建 polymer feed 请求参数（仅用 offset 翻页，不传 page/page_size）
     *
     * @param int $mid 用户ID
     * @param string $offset 分页游标，首页传空字符串
     * @return array
     */
    private function buildFeedRequestParams($mid, $offset = '') {
        $params = array_merge([
            'host_mid' => $mid,
            'features' => 'itemOpusStyle,listOnlyfans,opusBigCover,onlyfansVote,forwardListHidden,decorationCard,commentsNewVersion,onlyfansAssetsV2,ugcDelete,onlyfansQaCard',
            'timezone_offset' => -480,
        ], $this->getAntiRiskParams());

        if ($offset !== '' && $offset !== null) {
            $params['offset'] = (string) $offset;
        }

        return $this->signWbiParams($params);
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
            $params = $this->buildFeedRequestParams($mid, $offset);
            
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
            
            $next_offset = $result['data']['offset'] ?? '';
            if ($next_offset === '' || $next_offset === $offset) {
                return null;
            }
            $offset = $next_offset;
            $current_page++;
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
     * 分页拉取 feed/all，直到凑够目标条数或无更多数据
     *
     * @param int $mid 用户ID
     * @param int $target_count 目标条数
     * @param array $seed_items 已有动态（如 feed/space 首批结果）
     * @return array|null
     */
    private function fetchFeedAllAccumulated($mid, $target_count, $seed_items = []) {
        return $this->fetchFeedAccumulated(
            'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/all',
            $mid,
            $target_count,
            $seed_items
        );
    }

    /**
     * 分页拉取 feed/space，直到凑够目标条数或无更多数据
     *
     * @param int $mid 用户ID
     * @param int $target_count 目标条数
     * @return array|null
     */
    private function fetchFeedSpaceAccumulated($mid, $target_count) {
        $desktop_url = 'https://api.bilibili.com/x/polymer/web-dynamic/desktop/v1/feed/space';
        $legacy_url = 'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/space';

        $result = $this->fetchFeedAccumulated($desktop_url, $mid, $target_count);
        if ($this->isValidDynamicResponse($result) && count($result['data']['items']) >= min($target_count, 1)) {
            return $result;
        }

        return $this->fetchFeedAccumulated($legacy_url, $mid, $target_count);
    }

    /**
     * 分页拉取动态 feed 接口
     *
     * @param string $api_url feed 接口地址
     * @param int $mid 用户ID
     * @param int $target_count 目标条数
     * @param array $seed_items 已有动态
     * @return array|null
     */
    private function fetchFeedAccumulated($api_url, $mid, $target_count, $seed_items = []) {
        $all_items = [];
        $seen = [];

        foreach ($seed_items as $item) {
            $id = $item['id_str'] ?? '';
            if ($id !== '' && !isset($seen[$id])) {
                $seen[$id] = true;
                $all_items[] = $item;
            }
        }

        $offset = '';
        $base_result = null;
        $has_more = false;
        $max_pages = 10;

        for ($i = 0; $i < $max_pages && count($all_items) < $target_count; $i++) {
            if ($i > 0) {
                usleep(250000);
            }

            $params = $this->buildFeedRequestParams($mid, $offset);

            $this->applyRequestHeaders($mid);
            $result = $this->httpGet($api_url, $params);

            if (!is_array($result) || ($result['code'] ?? -1) !== 0 || !isset($result['data']['items']) || !is_array($result['data']['items'])) {
                if ($i === 0 && empty($all_items)) {
                    break;
                }
                usleep(300000);
                $this->applyRequestHeaders($mid);
                $result = $this->httpGet($api_url, $params);
                if (!is_array($result) || ($result['code'] ?? -1) !== 0 || !isset($result['data']['items']) || !is_array($result['data']['items'])) {
                    break;
                }
            }

            if (empty($result['data']['items'])) {
                break;
            }

            if ($base_result === null) {
                $base_result = $result;
            }

            $added = 0;
            foreach ($result['data']['items'] as $item) {
                $id = $item['id_str'] ?? '';
                if ($id !== '' && !isset($seen[$id])) {
                    $seen[$id] = true;
                    $all_items[] = $item;
                    $added++;
                }
            }

            $has_more = !empty($result['data']['has_more']);
            if (count($all_items) >= $target_count || !$has_more) {
                break;
            }

            $next_offset = $result['data']['offset'] ?? '';
            if ($next_offset === '' || $next_offset === $offset) {
                break;
            }
            $offset = $next_offset;
        }

        if (empty($all_items)) {
            return null;
        }

        if ($base_result === null) {
            $base_result = [
                'code' => 0,
                'data' => [
                    'items' => [],
                    'has_more' => false,
                ],
            ];
        }

        $base_result['data']['items'] = array_slice($all_items, 0, $target_count);
        $base_result['data']['has_more'] = $has_more;
        return $base_result;
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
        $api_urls = [
            'https://api.bilibili.com/x/polymer/web-dynamic/desktop/v1/feed/space',
            'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/space',
        ];
        $offset = '';

        if ($page > 1) {
            for ($current = 1; $current < $page; $current++) {
                $step = null;
                foreach ($api_urls as $api_url) {
                    $params = $this->buildFeedRequestParams($mid, $offset);
                    $this->applyRequestHeaders($mid);
                    $step = $this->httpGet($api_url, $params);
                    if ($this->isValidDynamicResponse($step) && !empty($step['data']['has_more'])) {
                        break;
                    }
                    $step = null;
                }

                if (!$this->isValidDynamicResponse($step) || empty($step['data']['has_more'])) {
                    return null;
                }

                $next_offset = $step['data']['offset'] ?? '';
                if ($next_offset === '' || $next_offset === $offset) {
                    return null;
                }
                $offset = $next_offset;
            }
        }

        $result = null;
        foreach ($api_urls as $api_url) {
            $params = $this->buildFeedRequestParams($mid, $offset);
            $this->applyRequestHeaders($mid);
            $result = $this->httpGet($api_url, $params);
            if ($this->isValidDynamicResponse($result)) {
                break;
            }
            $result = null;
        }

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
        $item = $this->normalizeDynamicItem($item);
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
            $item = $this->normalizeDynamicItem($item);
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
        if (empty($data) || empty($data['data']['items']) || !is_array($data['data']['items'])) {
            return;
        }
        
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
                $cached_count = count($cached['data']['items']);
                if (!$force_refresh && (time() - filemtime($latest_file)) <= $this->cache_time && $cached_count >= $page_size) {
                    $this->logApiCall("[CACHE HIT]", $mid, $page);
                    return $cached;
                }
                
                $expired_cache = $cached;
                $this->logApiCall($force_refresh ? "[FORCE REFRESH]" : "[CACHE EXPIRED]", $mid, $page);
            }
        } else {
            $this->logApiCall("[CACHE MISS]", $mid, $page);
        }
        
        // 有 Cookie 时优先 feed/space（含置顶与图文原图）；不足时用 feed/all 补齐；否则回退 feed/all
        try {
            $result = null;

            if ($this->hasBilibiliCookie()) {
                $spaceResult = $this->fetchFeedSpaceAccumulated($mid, $page_size);
                if ($this->isValidDynamicResponse($spaceResult)) {
                    $result = $spaceResult;
                    $spaceCount = count($spaceResult['data']['items']);

                    if ($spaceCount < $page_size) {
                        $merged = $this->fetchFeedAllAccumulated(
                            $mid,
                            $page_size,
                            $spaceResult['data']['items']
                        );
                        if ($this->isValidDynamicResponse($merged) && count($merged['data']['items']) > $spaceCount) {
                            $result = $merged;
                            $this->logApiCall("[MERGED space+all] count:" . count($merged['data']['items']), $mid, $page);
                        }
                    }

                    $this->saveApiResponse($result, $mid, $page);
                    $this->logApiCall("[CACHE WRITE space]", $mid, $page);
                    return $result;
                }
                $this->logApiCall("[SPACE FEED FAILED, fallback all]", $mid, $page);
            }

            $result = $this->fetchFeedAllAccumulated($mid, $page_size);
            
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
     * 是否为 desktop feed 的 modules 数组格式
     */
    private function isDesktopModulesFormat(array $modules): bool {
        if (empty($modules)) {
            return false;
        }
        if (isset($modules['module_author']) || isset($modules['module_dynamic'])) {
            return false;
        }
        return isset($modules[0]['module_type']);
    }

    /**
     * 将 desktop/v1/feed 的动态项转为 legacy modules 对象结构
     */
    private function normalizeDynamicItem(array $item): array {
        if (!isset($item['modules']) || !is_array($item['modules']) || !$this->isDesktopModulesFormat($item['modules'])) {
            return $item;
        }

        $modules = [];
        foreach ($item['modules'] as $mod) {
            $type = $mod['module_type'] ?? '';
            switch ($type) {
                case 'MODULE_TYPE_AUTHOR':
                    $modules['module_author'] = $mod['module_author'] ?? [];
                    break;
                case 'MODULE_TYPE_TAG':
                    $modules['module_tag'] = $mod['module_tag'] ?? [];
                    break;
                case 'MODULE_TYPE_DESC':
                    if (!isset($modules['module_dynamic'])) {
                        $modules['module_dynamic'] = [];
                    }
                    $modules['module_dynamic']['desc'] = [
                        'rich_text_nodes' => $mod['module_desc']['rich_text_nodes'] ?? [],
                        'text' => $mod['module_desc']['text'] ?? '',
                    ];
                    break;
                case 'MODULE_TYPE_DYNAMIC':
                    $major = $this->convertDesktopMajor($mod['module_dynamic'] ?? []);
                    if (!isset($modules['module_dynamic'])) {
                        $modules['module_dynamic'] = [];
                    }
                    if (!empty($major)) {
                        $modules['module_dynamic']['major'] = $major;
                    }
                    break;
            }
        }

        $item['modules'] = $modules;
        return $item;
    }

    /**
     * desktop module_dynamic 转为 legacy major 结构
     */
    private function convertDesktopMajor(array $dyn): array {
        $major = [];

        if (!empty($dyn['dyn_draw'])) {
            $draw = $dyn['dyn_draw'];
            $major['type'] = 'MAJOR_TYPE_DRAW';
            $major['draw'] = [
                'id' => $draw['id'] ?? '',
                'items' => $draw['items'] ?? [],
            ];
            if (!empty($draw['pics'])) {
                $major['draw']['pics'] = $draw['pics'];
            }
        }

        if (!empty($dyn['dyn_archive'])) {
            $arch = $dyn['dyn_archive'];
            $major['type'] = 'MAJOR_TYPE_ARCHIVE';
            $major['archive'] = [
                'aid' => $arch['aid'] ?? '',
                'bvid' => $arch['bvid'] ?? '',
                'title' => $arch['title'] ?? '',
                'cover' => $arch['cover'] ?? '',
                'duration_text' => $arch['duration_text'] ?? '',
                'desc' => $arch['desc'] ?? '',
                'stat' => $arch['stat'] ?? [],
            ];
        }

        if (!empty($dyn['dyn_forward']['item'])) {
            $major['type'] = 'MAJOR_TYPE_FORWARD';
            $major['forward'] = [
                'item' => $this->normalizeDynamicItem($dyn['dyn_forward']['item']),
            ];
        }

        if (!empty($dyn['dyn_opus'])) {
            $opus = $dyn['dyn_opus'];
            $major['type'] = 'MAJOR_TYPE_OPUS';
            $major['opus'] = $opus;
            if (!empty($opus['summary']['rich_text_nodes'])) {
                $major['opus']['summary'] = $opus['summary'];
            }
            if (!empty($opus['pics'])) {
                $major['opus']['pics'] = $opus['pics'];
            }
        }

        return $major;
    }

    /**
     * 处理动态内容
     * 
     * @param array $dynamic 动态数据
     * @return array 处理后的动态数据
     */
    public function processDynamic($dynamic) {
        $dynamic = $this->normalizeDynamicItem($dynamic);

        $processed = [
            'id' => $dynamic['id_str'] ?? '',
            'type' => $dynamic['type'] ?? '',
            'timestamp' => $dynamic['modules']['module_author']['pub_ts'] ?? 0,
            'time' => '',
            'content' => '',
            'images' => [],
            'video' => null,
            'card' => null,
            'forward_origin' => null,
            'is_pinned' => false,
            'is_forward' => false
        ];
        
        // 检查是否为置顶动态
        if (isset($dynamic['modules']['module_tag']['text']) && $dynamic['modules']['module_tag']['text'] === '置顶') {
            $processed['is_pinned'] = true;
        }
        if (!empty($dynamic['modules']['module_author']['is_top'])) {
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

        // 5. 纯文本 desc 兜底
        if (empty($content) && !empty($dynamic['modules']['module_dynamic']['desc']['text'])) {
            $content = nl2br(htmlspecialchars($dynamic['modules']['module_dynamic']['desc']['text'], ENT_QUOTES, 'UTF-8'));
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
        
        // 处理卡片（图文 draw 仅有 items 时不生成空 card，避免渲染空白条）
        if (isset($dynamic['modules']['module_dynamic']['major']['draw'])) {
            $draw = $dynamic['modules']['module_dynamic']['major']['draw'];
            $card = [
                'id' => $draw['id'] ?? '',
                'title' => $draw['title'] ?? '',
                'desc' => $draw['desc'] ?? '',
                'pics' => $this->extractImages($draw['pics'] ?? [])
            ];
            if ($card['title'] !== '' || $card['desc'] !== '' || !empty($card['pics'])) {
                $processed['card'] = $card;
            }
        }

        // 转发动态：外层 content 为转发语，原文与媒体放入 forward_origin
        if ($processed['is_forward'] && !empty($dynamic['modules']['module_dynamic']['major']['forward']['item'])) {
            $inner = $this->processDynamic($dynamic['modules']['module_dynamic']['major']['forward']['item']);
            $processed['forward_origin'] = [
                'content' => $inner['content'] ?? '',
                'images' => $inner['images'] ?? [],
                'video' => $inner['video'] ?? null,
                'card' => $inner['card'] ?? null,
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
     * 读取上次成功抓到的置顶周表图片 URL（API 失败时回退）
     */
    private function getCachedPinnedScheduleImageUrl(): string {
        try {
            require_once __DIR__ . '/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'pinned_schedule_image_url'");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return ($row && $row['config_value'] !== '') ? (string) $row['config_value'] : '';
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * 缓存最新置顶周表图片 URL
     */
    private function saveCachedPinnedScheduleImageUrl(string $url): void {
        if ($url === '') {
            return;
        }

        try {
            require_once __DIR__ . '/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare(
                "INSERT INTO site_config (config_key, config_value) VALUES ('pinned_schedule_image_url', ?)
                 ON DUPLICATE KEY UPDATE config_value = ?"
            );
            $stmt->execute([$url, $url]);
        } catch (Exception $e) {
            // 忽略缓存写入失败
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
            return $this->getCachedPinnedScheduleImageUrl();
        }

        $result = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($attempt > 0) {
                usleep(300000);
            }

            $result = $this->fetchFeedSpacePage($mid, 1, 20);
            if ($this->isValidDynamicResponse($result)) {
                break;
            }
            $result = null;
        }

        if (!$this->isValidDynamicResponse($result)) {
            return $this->getCachedPinnedScheduleImageUrl();
        }

        foreach ($result['data']['items'] as $item) {
            $normalized = $this->normalizeDynamicItem($item);
            $tag = $normalized['modules']['module_tag']['text'] ?? '';
            $is_top = !empty($normalized['modules']['module_author']['is_top']);
            if ($tag !== '置顶' && !$is_top) {
                continue;
            }

            $processed = $this->processDynamic($item);
            if (!empty($processed['images'][0])) {
                $this->saveCachedPinnedScheduleImageUrl($processed['images'][0]);
                return $processed['images'][0];
            }
        }

        return $this->getCachedPinnedScheduleImageUrl();
    }

    /**
     * 获取处理后的动态列表
     * 
     * @param int $mid 用户ID
     * @param int $page 页码
     * @param int $page_size 每页大小
     * @param bool $force_refresh 是否强制刷新，不使用缓存
     * @param bool $exclude_pinned 是否排除置顶动态
     * @return array 处理后的动态列表
     */
    public function getProcessedDynamics($mid, $page = 1, $page_size = 30, $force_refresh = false, $exclude_pinned = false) {
        $start_time = microtime(true);
        $dynamics = [];
        $raw_fetch_size = $page_size + ($exclude_pinned ? 10 : 0);

        for ($attempt = 0; $attempt < 4 && count($dynamics) < $page_size; $attempt++) {
            $current_fetch = min($raw_fetch_size + ($attempt * 15), 80);
            $should_force = $force_refresh || $attempt > 0;
            $result = $this->getUserDynamics($mid, $page, $current_fetch, $should_force);

            if (empty($result) || !isset($result['data']['items']) || !is_array($result['data']['items'])) {
                break;
            }

            $dynamics = [];
            foreach ($result['data']['items'] as $dynamic) {
                if (isset($dynamic['type']) && $dynamic['type'] === 'DYNAMIC_TYPE_LIVE_RCMD') {
                    continue;
                }

                $processed = $this->processDynamic($dynamic);
                if ($exclude_pinned && !empty($processed['is_pinned'])) {
                    continue;
                }

                $dynamics[] = $processed;
                if (count($dynamics) >= $page_size) {
                    break;
                }
            }
        }

        $api_time = microtime(true) - $start_time;
        $this->logApiCall("[API REQUEST TIME] " . round($api_time * 1000) . "ms", $mid, $page);
        $this->logApiCall("[PROCESSED DYNAMICS] Count:" . count($dynamics) . " Total Time:" . round($api_time * 1000) . "ms", $mid, $page);
        $this->logApiCall("[CACHE TIME] " . $this->cache_time . "s", $mid, $page);

        if ($page_size > 0 && count($dynamics) > $page_size) {
            $dynamics = array_slice($dynamics, 0, $page_size);
        }

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