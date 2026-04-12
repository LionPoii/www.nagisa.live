<?php
// 设置允许跨域请求的头部
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

// 设置缓存控制头
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// 引入数据库类
require_once __DIR__ . '/../includes/database.php';

// 创建日志目录（如果不存在）
$logDir = __DIR__ . '/../logs/fans_count_api';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// 日志文件路径
$logFile = $logDir . '/fans_count_api_' . date('Y-m-d') . '.log';

/**
 * 记录API日志
 */
function logApiRequest($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * 缓存API响应数据
 */
function cacheApiResponse($data) {
    $cacheDir = __DIR__ . '/api_cache/fans_count_api';
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    $cacheFile = $cacheDir . '/fans_count_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    // 清理旧文件，只保留最新的文件
    $files = glob($cacheDir . '/fans_count_*.json');
    
    if (count($files) > 1) {
        // 按修改时间排序
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // 保留最新的1个文件，删除其余文件
        for ($i = 1; $i < count($files); $i++) {
            if (file_exists($files[$i]) && $files[$i] !== $cacheFile) {
                unlink($files[$i]);
            }
        }
    }
    
    logApiRequest("已缓存API响应到文件: " . basename($cacheFile));
}

/**
 * 获取B站粉丝数
 */
function getBilibiliFansCount() {
    try {
        // 从数据库获取B站UID
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_uid'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || empty($result['config_value'])) {
            logApiRequest("Error: No Bilibili UID configured in database");
            return ["error" => "No Bilibili UID configured", "fans_count" => 0];
        }
        
        $uid = $result['config_value'];
        
        // 请求B站API获取粉丝数
        $apiUrl = "https://api.bilibili.com/x/relation/stat?vmid={$uid}";
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
            ]
        ];
        
        $context = stream_context_create($options);
        $response = file_get_contents($apiUrl, false, $context);
        
        if ($response === FALSE) {
            logApiRequest("Error: Failed to get response from Bilibili API");
            return ["error" => "Failed to get data from Bilibili API", "fans_count" => 0];
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['data']['follower'])) {
            $fansCount = $data['data']['follower'];
            logApiRequest("Success: Got fans count: $fansCount");
            return ["fans_count" => $fansCount];
        } else {
            logApiRequest("Error: Invalid response format from Bilibili API: " . json_encode($data));
            return ["error" => "Invalid response format", "fans_count" => 0];
        }
    } catch (Exception $e) {
        logApiRequest("Exception: " . $e->getMessage());
        return ["error" => $e->getMessage(), "fans_count" => 0];
    }
}

// 执行API请求
$result = getBilibiliFansCount();

// 缓存响应结果
cacheApiResponse($result);

// 输出结果
echo json_encode($result); 