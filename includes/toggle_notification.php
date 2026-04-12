<?php
// 确保只能通过系统访问
if (!defined('IN_SYSTEM')) {
    header('HTTP/1.1 403 Forbidden');
    exit('禁止访问');
}

// 处理通知状态切换
if (isset($_POST['action']) && $_POST['action'] === 'toggle_notification') {
    // 设置响应头
    header('Content-Type: application/json');
    
    // 获取当前状态
    $status_file = __DIR__ . '/../cache/notification_status.txt';
    
    // 如果文件不存在，创建它并设置默认值为开启(1)
    if (!file_exists($status_file)) {
        file_put_contents($status_file, '1');
        $current_status = 1;
    } else {
        $current_status = (int)file_get_contents($status_file);
    }
    
    // 切换状态 (1 -> 0 或 0 -> 1)
    $new_status = $current_status ? 0 : 1;
    
    // 保存新状态
    if (file_put_contents($status_file, (string)$new_status) !== false) {
        echo json_encode([
            'success' => true,
            'status' => $new_status,
            'message' => $new_status ? '通知已开启' : '通知已关闭'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '无法保存状态'
        ]);
    }
    exit;
}

// 获取当前通知状态
function get_notification_status() {
    $status_file = __DIR__ . '/../cache/notification_status.txt';
    
    // 如果文件不存在，创建它并设置默认值为开启(1)
    if (!file_exists($status_file)) {
        file_put_contents($status_file, '1');
        return 1;
    }
    
    return (int)file_get_contents($status_file);
} 