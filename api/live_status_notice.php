<?php
require_once __DIR__ . '/../includes/api_no_cache_headers.php';
/**
 * 直播状态API
 * 返回当前直播状态信息
 */

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// 引入直播状态检测类
require_once __DIR__ . '/../includes/bilibili_live.php';

// 初始化B站直播检测类
$roomId = 31368705; // 默认直播间ID

// 从数据库获取配置的直播间ID
try {
    require_once __DIR__ . '/../includes/database.php';
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

$biliLive = new BilibiliLive($roomId);

// 获取直播信息
$liveInfo = $biliLive->getLiveInfo();

// 确保room_id字段存在
$liveInfo['room_id'] = $roomId;

// 输出JSON结果
echo json_encode($liveInfo); 