<?php
require_once __DIR__ . '/../includes/api_no_cache_headers.php';
define('IN_SYSTEM', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * 记录API日志
 * @param string $content 日志内容
 */
function log_live_status_api_response($content) {
    $log_dir = __DIR__ . '/../logs/live_status_api';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/live_status_api_' . date('Y-m-d') . '.log';
    file_put_contents($log_file, date('Y-m-d H:i:s') . "\n" . $content . "\n\n", FILE_APPEND);
}

/**
 * 缓存API响应数据
 */
function cache_live_status_data($data) {
    $cache_dir = __DIR__ . '/api_cache/live_status_api';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    $cache_file = $cache_dir . '/live_status_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($cache_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    // 清理旧文件，只保留最新的文件
    $files = glob($cache_dir . '/live_status_*.json');
    
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
    
    log_live_status_api_response("已缓存API响应到文件: " . basename($cache_file));
}

// 记录请求信息
$request_info = [
    'time' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'method' => $_SERVER['REQUEST_METHOD'],
    'bilibili_uid' => BILIBILI_UID ?? 'not_set'
];

// 记录请求开始
log_live_status_api_response("=== Live Status API 请求开始 ===\n请求信息: " . json_encode($request_info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $error_response = ['error' => '方法不允许'];
    log_live_status_api_response("请求方法错误: " . json_encode($error_response, JSON_UNESCAPED_UNICODE));
    send_json_response($error_response, 405);
}

// 检查B站UID是否配置
if (empty(BILIBILI_UID)) {
    $error_response = ['error' => 'B站UID未配置'];
    log_live_status_api_response("B站UID未配置: " . json_encode($error_response, JSON_UNESCAPED_UNICODE));
    send_json_response($error_response, 500);
}

try {
    log_live_status_api_response("正在获取B站UID " . BILIBILI_UID . " 的直播状态");
    
    $live_status = get_bilibili_live_status(BILIBILI_UID);
    
    if ($live_status === null) {
        $error_response = ['error' => '获取直播状态失败'];
        log_live_status_api_response("获取直播状态失败: " . json_encode($error_response, JSON_UNESCAPED_UNICODE));
        cache_live_status_data($error_response);
        send_json_response($error_response, 500);
    }
    
    // 记录成功响应
    $response_summary = [
        'status' => 'success',
        'live_status' => $live_status,
        'response_length' => strlen(json_encode($live_status))
    ];
    log_live_status_api_response("获取直播状态成功:\n" . json_encode($response_summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    // 缓存API响应数据
    cache_live_status_data($live_status);
    
    send_json_response($live_status);
} catch (Exception $e) {
    $error_response = ['error' => '服务器内部错误'];
    log_live_status_api_response("服务器内部错误: " . $e->getMessage() . "\n" . json_encode($error_response, JSON_UNESCAPED_UNICODE));
    
    // 缓存错误响应
    $error_data = [
        'error' => '服务器内部错误',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    cache_live_status_data($error_data);
    
    send_json_response($error_response, 500);
}

// 记录请求结束
log_live_status_api_response("=== Live Status API 请求结束 ===\n");
?> 