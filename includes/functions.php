<?php
if (!defined('IN_SYSTEM')) {
    die('Access Denied');
}

/**
 * 安全过滤输入
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * 生成CSRF令牌
 */
function generate_csrf_token() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * 验证CSRF令牌
 */
function verify_csrf_token($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * 获取B站直播间状态
 */
function get_bilibili_live_status($uid) {
    $api_url = "https://api.live.bilibili.com/room/v1/Room/get_status_info_by_uids";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['uids' => [$uid]]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['data']['by_uids'][$uid] ?? null;
    }
    return null;
}

/**
 * 安全的文件上传处理
 */
function handle_file_upload($file, $allowed_types = ALLOWED_IMAGE_TYPES, $max_size = MAX_FILE_SIZE) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('无效的文件参数');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('文件大小超过限制');
        case UPLOAD_ERR_PARTIAL:
            throw new RuntimeException('文件上传不完整');
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('没有文件被上传');
        default:
            throw new RuntimeException('未知错误');
    }

    if ($file['size'] > $max_size) {
        throw new RuntimeException('文件大小超过限制');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    if (!in_array($mime_type, $allowed_types)) {
        throw new RuntimeException('不支持的文件类型');
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = sprintf(
        '%s.%s',
        bin2hex(random_bytes(16)),
        $extension
    );

    $upload_path = UPLOAD_PATH . '/' . $new_filename;
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new RuntimeException('文件上传失败');
    }

    return $new_filename;
}

/**
 * 记录错误日志
 */
function log_error($message, $context = []) {
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $log_message .= ' - Context: ' . json_encode($context);
    }
    error_log($log_message . PHP_EOL, 3, __DIR__ . '/../logs/error.log');
}

/**
 * 发送JSON响应
 */
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * 检查是否是AJAX请求
 */
function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
} 