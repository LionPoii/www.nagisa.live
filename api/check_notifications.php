<?php
require_once __DIR__ . '/../includes/api_no_cache_headers.php';
// 设置安全响应头
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json');

// 定义系统标记
define('IN_SYSTEM', true);

// 引入通知状态处理文件
require_once '../includes/toggle_notification.php';
require_once '../includes/notification_manager.php';

// 获取通知状态
$notification_enabled = get_notification_status();

// 初始化响应数据
$response = [
    'has_notifications' => false,
    'notifications' => [],
    'notification_enabled' => (bool)$notification_enabled
];

// 如果通知功能已启用，检查是否有新通知
if ($notification_enabled) {
    $notificationManager = new NotificationManager();
    
    // 检查动态更新
    $dynamic_updates = $notificationManager->checkDynamicUpdates();
    if ($dynamic_updates) {
        $response['has_notifications'] = true;
        foreach ($dynamic_updates as $update) {
            $response['notifications'][] = [
                'type' => 'dynamic',
                'title' => '新动态',
                'content' => $update['content'],
                'timestamp' => $update['timestamp']
            ];
        }
    }
    
    // 检查直播状态变化
    $live_change = $notificationManager->checkLiveStatusChange();
    if ($live_change) {
        $response['has_notifications'] = true;
        $response['notifications'][] = [
            'type' => 'live',
            'title' => '开始直播',
            'content' => $live_change['title'],
            'timestamp' => $live_change['timestamp'],
            'room_id' => $live_change['room_id']
        ];
    }
    
    // 检查Fanart更新
    $fanart_updates = $notificationManager->checkFanartUpdates();
    if ($fanart_updates) {
        $response['has_notifications'] = true;
        foreach ($fanart_updates as $update) {
            $response['notifications'][] = [
                'type' => 'fanart',
                'title' => '新Fanart',
                'content' => $update['title'],
                'timestamp' => $update['timestamp']
            ];
        }
    }
}

// 输出JSON响应
echo json_encode($response); 