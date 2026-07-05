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

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_notification') {
    // 处理逻辑已经在includes/toggle_notification.php中实现
} else {
    // 如果不是POST请求或缺少必要参数，返回错误
    echo json_encode([
        'success' => false,
        'message' => '无效请求'
    ]);
} 