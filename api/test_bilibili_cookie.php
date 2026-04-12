<?php
// 确保所有错误都被捕获并以JSON格式返回
set_error_handler(function($severity, $message, $file, $line) {
    // 仅记录错误，但不中断执行
    try {
        $error_message = "PHP错误: $message (在 $file 第 $line 行)";
        @error_log($error_message);
    } catch (Exception $e) {
        // 忽略记录错误的错误
    }
    
    // 只有致命错误才返回错误响应并退出
    if ($severity & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) {
        header('Content-Type: application/json');
        echo json_encode([
            'code' => 500,
            'message' => 'PHP错误: ' . $message
        ]);
        exit;
    }
    
    // 非致命错误不会中断执行
    return true;
});

// 禁止PHP显示错误
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 设置响应头为JSON
header('Content-Type: application/json');

// 允许跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 安全的日志记录函数，忽略任何错误
function safe_log($message) {
    try {
        // 尝试记录到系统错误日志
        @error_log("[BILIBILI_COOKIE_TEST] " . $message);
        
        // 尝试写入自定义日志文件，但不强求成功
        $log_file = __DIR__ . '/../logs/cookie_test_errors.log';
        @error_log(date('Y-m-d H:i:s') . " $message\n", 3, $log_file);
    } catch (Exception $e) {
        // 完全忽略任何错误
    }
}

try {
    // 测试请求方法（不依赖日志）
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'code' => 400,
            'message' => '请使用POST方法'
        ]);
        exit;
    }

    // 检查 curl 扩展是否可用
    if (!function_exists('curl_init')) {
        safe_log('CURL扩展未安装');
        echo json_encode([
            'code' => 500,
            'message' => 'CURL扩展未安装，请联系管理员'
        ]);
        exit;
    }

    // 获取请求体内容
    $input = @file_get_contents('php://input');
    if (empty($input)) {
        echo json_encode([
            'code' => 400,
            'message' => '请求内容为空'
        ]);
        exit;
    }

    $data = @json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'code' => 400,
            'message' => 'JSON格式错误: ' . json_last_error_msg()
        ]);
        exit;
    }

    // 检查是否提供了Cookie
    if (empty($data['cookie'])) {
        echo json_encode([
            'code' => 400,
            'message' => '请提供Cookie'
        ]);
        exit;
    }

    // 使用提供的Cookie测试B站API
    $cookie = $data['cookie'];

    // 测试登录状态API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.bilibili.com/x/web-interface/nav');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10秒超时
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5秒连接超时

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);

    curl_close($ch);

    // 检查HTTP状态码
    if ($httpCode !== 200) {
        echo json_encode([
            'code' => 500,
            'message' => "API请求失败: HTTP $httpCode"
        ]);
        exit;
    }

    // 检查是否有curl错误
    if ($error) {
        echo json_encode([
            'code' => 500,
            'message' => "请求错误: $error (错误码: $errno)"
        ]);
        exit;
    }

    // 检查响应是否为空
    if (empty($response)) {
        echo json_encode([
            'code' => 500,
            'message' => "API返回空数据"
        ]);
        exit;
    }

    // 解析响应JSON
    $result = @json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'code' => 500,
            'message' => 'API返回数据格式错误: ' . json_last_error_msg()
        ]);
        exit;
    }

    // 检查登录状态
    if ($result['code'] === 0 && isset($result['data']['isLogin']) && $result['data']['isLogin'] === true) {
        $username = $result['data']['uname'] ?? '未知用户';
        $uid = $result['data']['mid'] ?? 0;
        
        echo json_encode([
            'code' => 0,
            'message' => "已登录: $username",
            'data' => [
                'uid' => $uid,
                'username' => $username
            ]
        ]);
    } else {
        $msg = $result['message'] ?? '未登录状态或Cookie已过期';
        echo json_encode([
            'code' => 401,
            'message' => $msg
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => "系统错误: " . $e->getMessage()
    ]);
} catch (Error $e) {
    echo json_encode([
        'code' => 500,
        'message' => "PHP错误: " . $e->getMessage()
    ]);
}
?> 