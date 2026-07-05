<?php
require_once __DIR__ . '/../includes/api_no_cache_headers.php';
/**
 * 直播状态 API（精简版）
 *
 * GET /api/check_live_status.php
 * 文档：同目录 check_live_status.md
 *
 * 成功：{ success, room_id, is_living, title, cover_url, timestamp }
 * 失败：{ success: false, error, message, timestamp }
 */
// 设置安全响应头
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json');

// 关闭错误显示，避免错误信息混入JSON输出
ini_set('display_errors', 0);
error_reporting(E_ERROR);

// 定义系统标记
define('IN_SYSTEM', true);

// 引入必要的文件
require_once __DIR__ . '/../includes/bilibili_live.php';

try {
    // 从数据库获取配置的直播间ID
    $roomId = 31368705; // 默认直播间ID
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
    
    // 获取直播状态
    $biliLive = new BilibiliLive($roomId);
    $isLiving = $biliLive->isLiving(); // 直播状态：在线/离线
    $title = $biliLive->getTitle();    // 直播标题
    $cover = $biliLive->getCover();    // 直播封面
    $keyframe = $biliLive->getKeyframe(); // 直播间实时截图
    
    // 处理封面图片，优先使用封面，其次使用实时截图
    $coverUrl = '';
    if (!empty($cover)) {
        $coverUrl = strpos($cover, '//') === 0 ? 'https:' . $cover : $cover;
    } elseif (!empty($keyframe)) {
        $coverUrl = strpos($keyframe, '//') === 0 ? 'https:' . $keyframe : $keyframe;
    }
    
    $response = [
        'success' => true,
        'room_id' => $roomId,
        'is_living' => $isLiving,
        'title' => isset($title) ? $title : '直播中',
        'cover_url' => $coverUrl,
        'timestamp' => time()
    ];
    
    echo json_encode($response);
} catch (Exception $e) {
    // 处理错误
    echo json_encode([
        'success' => false,
        'error' => '获取直播状态失败',
        'message' => $e->getMessage(),
        'timestamp' => time()
    ]);
} 