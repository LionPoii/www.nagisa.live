<?php
/**
 * 同源代理：拉取置顶动态等外链图片字节，供后台 JS 生成 Blob（绕过浏览器对第三方图片的 CORS 限制）。
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['image_url'])) {
    http_response_code(400);
    echo 'Bad Request';
    exit;
}

$image_url = trim((string) $_POST['image_url']);
if (strlen($image_url) > 4096) {
    http_response_code(400);
    echo 'URL too long';
    exit;
}

if (!preg_match('#^https?://#i', $image_url)) {
    http_response_code(400);
    echo 'Invalid URL scheme';
    exit;
}

$parsed = parse_url($image_url);
if ($parsed === false || empty($parsed['host'])) {
    http_response_code(400);
    echo 'Invalid URL';
    exit;
}

$host = strtolower($parsed['host']);
$suffixes = ['.hdslb.com', '.bilibili.com', '.bilivideo.com', '.acg.tv', '.bcebos.com', '.sinaimg.cn', '.sinaimg.com'];
$allowed = false;
foreach ($suffixes as $suf) {
    $len = strlen($suf);
    if (strlen($host) >= $len && substr($host, -$len) === $suf) {
        $allowed = true;
        break;
    }
}
if (!$allowed && preg_match('/^i[0-3]\.hdslb\.com$/', $host)) {
    $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    echo 'Host not allowed';
    exit;
}

$maxBytes = 15 * 1024 * 1024;

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo 'cURL unavailable';
    exit;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $image_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 45);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
    'Referer: https://www.bilibili.com/',
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_ENCODING, '');

$body = curl_exec($ch);
$errno = curl_errno($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($errno !== 0 || $body === false || $code < 200 || $code >= 400) {
    http_response_code(502);
    echo 'Upstream fetch failed';
    exit;
}

if (strlen($body) > $maxBytes) {
    http_response_code(413);
    echo 'Image too large';
    exit;
}

$outType = 'application/octet-stream';
if (is_string($contentType) && $contentType !== '') {
    $semi = strpos($contentType, ';');
    $outType = $semi !== false ? trim(substr($contentType, 0, $semi)) : trim($contentType);
    if (stripos($outType, 'image/') !== 0) {
        $outType = 'application/octet-stream';
    }
}

header('Content-Type: ' . $outType);
header('Content-Length: ' . (string) strlen($body));
header('X-Content-Type-Options: nosniff');
echo $body;
