<?php
// 设置缓存头
header('Cache-Control: public, max-age=3600'); // 缓存1小时

if (!isset($_GET['url'])) {
    http_response_code(400);
    exit('Missing url');
}

$url = $_GET['url'];

// 验证URL格式
if (strpos($url, 'http') !== 0) {
    http_response_code(400);
    exit('Invalid url');
}

// 只允许B站相关域名
$allowed_domains = [
    'bilibili.com',
    'hdslb.com',
    'bilivideo.com'
];

$url_parts = parse_url($url);
$host = $url_parts['host'] ?? '';

$is_allowed = false;
foreach ($allowed_domains as $domain) {
    if (strpos($host, $domain) !== false) {
        $is_allowed = true;
        break;
    }
}

if (!$is_allowed) {
    http_response_code(403);
    exit('Domain not allowed');
}

// 初始化CURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Referer: https://www.bilibili.com/',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
]);

$data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
curl_close($ch);

// 检查是否有错误
if ($error) {
    http_response_code(500);
    exit('CURL Error: ' . $error);
}

if ($http_code !== 200) {
    http_response_code($http_code);
    exit('HTTP Error: ' . $http_code);
}

if (empty($data)) {
    http_response_code(404);
    exit('Empty response');
}

// 设置内容类型
if ($content_type) {
    header('Content-Type: ' . $content_type);
}

// 输出图片数据
echo $data;
?> 