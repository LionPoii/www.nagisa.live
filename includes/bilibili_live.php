<?php
/**
 * B站直播状态检测
 * 使用API: https://api.live.bilibili.com/room/v1/Room/get_info?room_id=ROOM_ID
 */

class BilibiliLive {
    private $roomId;
    private $apiUrl;
    private $cacheFile;
    private $logFile;
    private $cacheTime = 15; // 缓存时间（秒）

    /**
     * 构造函数
     * @param int $roomId B站直播间ID
     */
    public function __construct($roomId = 31368705) {
        $this->roomId = $roomId;
        $this->apiUrl = "https://api.live.bilibili.com/room/v1/Room/get_info?room_id={$this->roomId}";
        $this->cacheFile = __DIR__ . "/../api/api_cache/live_status_api/bilibili_live_{$this->roomId}.json";
        $this->logFile = __DIR__ . "/../logs/live_status_api/bilibili_live_" . date('Y-m-d') . ".log";
        
        // 确保缓存目录存在
        if (!is_dir(dirname($this->cacheFile))) {
            mkdir(dirname($this->cacheFile), 0777, true);
        }
        
        // 确保日志目录存在
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
    }
    
    /**
     * 记录日志
     * @param string $message 日志消息
     */
    private function log($message) {
        $logEntry = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }

    /**
     * 获取直播信息
     * @return array 直播信息数组
     */
    public function getLiveInfo() {
        // 检查缓存是否存在且有效
        if ($this->isCacheValid()) {
            $this->log("使用缓存数据: {$this->cacheFile}");
            return $this->getCache();
        }

        // 请求API获取最新数据
        $this->log("正在从B站API获取直播间 {$this->roomId} 信息");
        $data = $this->fetchFromApi();
        if ($data) {
            $this->saveCache($data);
            $this->log("成功更新缓存: " . json_encode(['room_id' => $data['room_id'], 'live_status' => $data['live_status'], 'title' => $data['title']], JSON_UNESCAPED_UNICODE));
            return $data;
        }

        // 如果API请求失败但缓存存在，返回过期缓存
        if (file_exists($this->cacheFile)) {
            $this->log("API请求失败，使用过期缓存");
            return $this->getCache(true);
        }

        // 都失败则返回默认值
        $this->log("API请求失败，无可用缓存，返回默认值");
        return [
            'live_status' => 0,
            'title' => '暂无直播信息',
            'user_cover' => '',
            'keyframe' => '',
            'online' => 0,
            'area_name' => '',
            'live_time' => 0
        ];
    }

    /**
     * 检查是否正在直播
     * @return bool 是否正在直播
     */
    public function isLiving() {
        $info = $this->getLiveInfo();
        $status = isset($info['live_status']) && $info['live_status'] == 1;
        $this->log("直播状态检测: " . ($status ? "在线" : "离线"));
        return $status;
    }

    /**
     * 获取直播标题
     * @return string 直播标题
     */
    public function getTitle() {
        $info = $this->getLiveInfo();
        return $info['title'] ?? '暂无直播';
    }

    /**
     * 获取直播封面
     * @return string 直播封面URL
     */
    public function getCover() {
        $info = $this->getLiveInfo();
        return $info['user_cover'] ?? '';
    }

    /**
     * 获取直播间实时截图
     * @return string 直播间实时截图URL
     */
    public function getKeyframe() {
        $info = $this->getLiveInfo();
        return $info['keyframe'] ?? '';
    }

    /**
     * 获取观看人数
     * @return int 观看人数
     */
    public function getOnline() {
        $info = $this->getLiveInfo();
        return $info['online'] ?? 0;
    }

    /**
     * 获取直播分区
     * @return string 直播分区
     */
    public function getAreaName() {
        $info = $this->getLiveInfo();
        return $info['area_name'] ?? '';
    }
    
    /**
     * 获取直播开始时间
     * @return int 直播开始时间戳
     */
    public function getLiveStartTime() {
        $info = $this->getLiveInfo();
        return $info['live_time'] ?? 0;
    }
    
    /**
     * 获取直播持续时间（格式化为小时:分钟:秒）
     * @return string 格式化的直播持续时间
     */
    public function getLiveDuration() {
        if (!$this->isLiving()) {
            return '00:00:00';
        }
        
        $startTime = $this->getLiveStartTime();
        if (empty($startTime)) {
            return '00:00:00';
        }
        
        $duration = time() - $startTime;
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    /**
     * 从API获取数据
     * @return array|false 成功返回数组，失败返回false
     */
    private function fetchFromApi() {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode == 200 && $response) {
            $json = json_decode($response, true);
            if (isset($json['code']) && $json['code'] == 0 && isset($json['data'])) {
                return $json['data'];
            }
            $this->log("API响应解析失败: " . substr($response, 0, 200) . "...");
        } else {
            $this->log("API请求失败: HTTP状态码 $httpCode");
        }
        
        return false;
    }

    /**
     * 保存缓存
     * @param array $data 要缓存的数据
     */
    private function saveCache($data) {
        $cacheData = [
            'data' => $data,
            'timestamp' => time()
        ];
        
        // 确保只保留最新的缓存文件
        $pattern = dirname($this->cacheFile) . "/bilibili_live_{$this->roomId}*.json";
        $files = glob($pattern);
        foreach ($files as $file) {
            if ($file != $this->cacheFile) {
                unlink($file);
                $this->log("删除旧缓存文件: " . basename($file));
            }
        }
        
        file_put_contents($this->cacheFile, json_encode($cacheData));
        $this->log("创建新缓存文件: " . basename($this->cacheFile));
    }

    /**
     * 获取缓存
     * @param bool $ignoreExpiry 是否忽略过期时间
     * @return array|null 缓存数据
     */
    private function getCache($ignoreExpiry = false) {
        if (!file_exists($this->cacheFile)) {
            return null;
        }
        
        $cache = json_decode(file_get_contents($this->cacheFile), true);
        if (!$ignoreExpiry && (!isset($cache['timestamp']) || time() - $cache['timestamp'] > $this->cacheTime)) {
            $this->log("缓存已过期: " . basename($this->cacheFile));
            return null;
        }
        
        return $cache['data'] ?? null;
    }

    /**
     * 检查缓存是否有效
     * @return bool 缓存是否有效
     */
    private function isCacheValid() {
        if (!file_exists($this->cacheFile)) {
            return false;
        }
        
        $cache = json_decode(file_get_contents($this->cacheFile), true);
        return isset($cache['timestamp']) && time() - $cache['timestamp'] <= $this->cacheTime;
    }
} 