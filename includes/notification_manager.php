<?php
// 确保只能通过系统访问
if (!defined('IN_SYSTEM')) {
    header('HTTP/1.1 403 Forbidden');
    exit('禁止访问');
}

/**
 * 通知管理器类
 * 用于检测动态、直播和fanart的变化，并显示通知
 */
class NotificationManager {
    private $cache_dir;
    private $dynamic_cache_file;
    private $live_cache_file;
    private $fanart_cache_file;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->cache_dir = __DIR__ . '/../cache/notifications/';
        
        // 确保缓存目录存在
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0777, true);
        }
        
        $this->dynamic_cache_file = $this->cache_dir . 'dynamic_last_update.json';
        $this->live_cache_file = $this->cache_dir . 'live_status.json';
        $this->fanart_cache_file = $this->cache_dir . 'fanart_last_update.json';
    }
    
    /**
     * 获取动态组件的最新数据
     */
    public function getDynamicData() {
        // 这里我们只获取动态ID和时间戳，用于比较是否有新动态
        $dynamic_data = [];
        
        // 引入必要的文件
        require_once __DIR__ . '/bilibili_dynamic.php';
        
        // 从数据库获取用户ID
        $mid = 2124647716; // 默认用户ID
        try {
            require_once __DIR__ . '/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_mid'");
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($config && !empty($config['config_value'])) {
                $mid = $config['config_value'];
            }
        } catch (Exception $e) {
            // 出错时使用默认值
        }
        
        // 获取动态列表
        $biliDynamic = new BilibiliDynamic();
        $dynamics = $biliDynamic->getProcessedDynamics($mid, 1, 5, false); // 只获取前5条动态
        
        if (!empty($dynamics)) {
            foreach ($dynamics as $dynamic) {
                $dynamic_data[] = [
                    'id' => $dynamic['id'],
                    'timestamp' => $dynamic['timestamp'],
                    'content' => mb_substr(strip_tags($dynamic['text']), 0, 50) . '...' // 截取前50个字符作为通知内容
                ];
            }
        }
        
        return $dynamic_data;
    }
    
    /**
     * 获取直播状态
     */
    public function getLiveStatus() {
        // 引入必要的文件
        require_once __DIR__ . '/bilibili_live.php';
        
        // 从数据库获取直播间ID
        $roomId = 31368705; // 默认直播间ID
        try {
            require_once __DIR__ . '/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_room_id'");
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($config && !empty($config['config_value'])) {
                $roomId = $config['config_value'];
            }
        } catch (Exception $e) {
            // 出错时使用默认值
        }
        
        // 获取直播状态
        $biliLive = new BilibiliLive($roomId);
        $isLiving = $biliLive->isLiving();
        $title = $biliLive->getTitle();
        
        return [
            'room_id' => $roomId,
            'is_living' => $isLiving,
            'title' => $title,
            'timestamp' => time()
        ];
    }
    
    /**
     * 获取Fanart数据
     */
    public function getFanartData() {
        // 这里我们只获取最新的fanart ID和时间戳
        $fanart_data = [];
        
        // 从数据库获取最新的fanart数据
        try {
            require_once __DIR__ . '/database.php';
            $db = new Database();
            $conn = $db->getConnection();
            
            // 获取最新的5条fanart记录
            $stmt = $conn->prepare("SELECT id, title, created_at FROM fanarts ORDER BY created_at DESC LIMIT 5");
            $stmt->execute();
            $fanarts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($fanarts)) {
                foreach ($fanarts as $fanart) {
                    $fanart_data[] = [
                        'id' => $fanart['id'],
                        'title' => $fanart['title'],
                        'timestamp' => strtotime($fanart['created_at'])
                    ];
                }
            }
        } catch (Exception $e) {
            // 出错时返回空数组
        }
        
        return $fanart_data;
    }
    
    /**
     * 检查动态是否有更新
     */
    public function checkDynamicUpdates() {
        $current_data = $this->getDynamicData();
        
        // 如果没有获取到数据，直接返回false
        if (empty($current_data)) {
            return false;
        }
        
        // 获取上次缓存的数据
        $last_data = [];
        if (file_exists($this->dynamic_cache_file)) {
            $last_data = json_decode(file_get_contents($this->dynamic_cache_file), true);
        }
        
        // 如果没有上次的数据，保存当前数据并返回false
        if (empty($last_data)) {
            file_put_contents($this->dynamic_cache_file, json_encode($current_data));
            return false;
        }
        
        // 比较是否有新动态
        $has_new = false;
        $new_dynamics = [];
        
        foreach ($current_data as $current) {
            $found = false;
            foreach ($last_data as $last) {
                if ($current['id'] === $last['id']) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $has_new = true;
                $new_dynamics[] = $current;
            }
        }
        
        // 更新缓存
        file_put_contents($this->dynamic_cache_file, json_encode($current_data));
        
        return $has_new ? $new_dynamics : false;
    }
    
    /**
     * 检查直播状态是否有变化
     */
    public function checkLiveStatusChange() {
        $current_status = $this->getLiveStatus();
        
        // 获取上次缓存的状态
        $last_status = [];
        if (file_exists($this->live_cache_file)) {
            $last_status = json_decode(file_get_contents($this->live_cache_file), true);
        }
        
        // 如果没有上次的状态，保存当前状态并返回false
        if (empty($last_status)) {
            file_put_contents($this->live_cache_file, json_encode($current_status));
            return false;
        }
        
        // 检查直播状态是否从离线变为在线
        $started_streaming = false;
        if (!$last_status['is_living'] && $current_status['is_living']) {
            $started_streaming = true;
        }
        
        // 更新缓存
        file_put_contents($this->live_cache_file, json_encode($current_status));
        
        return $started_streaming ? $current_status : false;
    }
    
    /**
     * 检查Fanart是否有更新
     */
    public function checkFanartUpdates() {
        $current_data = $this->getFanartData();
        
        // 如果没有获取到数据，直接返回false
        if (empty($current_data)) {
            return false;
        }
        
        // 获取上次缓存的数据
        $last_data = [];
        if (file_exists($this->fanart_cache_file)) {
            $last_data = json_decode(file_get_contents($this->fanart_cache_file), true);
        }
        
        // 如果没有上次的数据，保存当前数据并返回false
        if (empty($last_data)) {
            file_put_contents($this->fanart_cache_file, json_encode($current_data));
            return false;
        }
        
        // 比较是否有新fanart
        $has_new = false;
        $new_fanarts = [];
        
        foreach ($current_data as $current) {
            $found = false;
            foreach ($last_data as $last) {
                if ($current['id'] === $last['id']) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $has_new = true;
                $new_fanarts[] = $current;
            }
        }
        
        // 更新缓存
        file_put_contents($this->fanart_cache_file, json_encode($current_data));
        
        return $has_new ? $new_fanarts : false;
    }
} 