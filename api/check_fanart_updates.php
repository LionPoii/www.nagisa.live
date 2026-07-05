<?php
require_once __DIR__ . '/../includes/api_no_cache_headers.php';
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

// 记录请求日志
$log_file = __DIR__ . '/../logs/fanart_check.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - API检查Fanart更新请求\n", FILE_APPEND);

// 初始化响应数据
$response = [
    'success' => true,
    'latest_fanart' => null,
    'timestamp' => time()
];

try {
    require_once __DIR__ . '/../includes/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // 获取最新的一条fanart记录
    $stmt = $conn->prepare("SELECT id, title, created_at FROM fanarts ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $fanart = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($fanart) {
        $response['latest_fanart'] = [
            'id' => $fanart['id'],
            'title' => isset($fanart['title']) ? $fanart['title'] : '新Fanart',
            'timestamp' => strtotime($fanart['created_at'])
        ];
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - 获取到最新Fanart ID: {$fanart['id']}\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - 未获取到Fanart数据\n", FILE_APPEND);
    }
} catch (Exception $e) {
    // 处理错误
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - 获取Fanart数据出错: " . $e->getMessage() . "\n", FILE_APPEND);
    
    $response = [
        'success' => false,
        'error' => '获取Fanart数据失败',
        'message' => $e->getMessage(),
        'timestamp' => time()
    ];
}

// 输出JSON响应
echo json_encode($response); 