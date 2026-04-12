<?php
$path = $_GET['path'] ?? '';

// 安全检查：确保路径不包含 ".." 以防止目录遍历
if (strpos($path, '..') !== false) {
    http_response_code(403);
    exit('Access denied');
}

// 处理路径
if (strpos($path, '/') === 0) {
    $real_path = $_SERVER['DOCUMENT_ROOT'] . $path;
} else {
    $real_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
}

// 检查文件是否存在
if (!file_exists($real_path)) {
    http_response_code(404);
    exit("Image not found");
}

// 使用扩展名判断MIME类型
$extension = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml'
];

$mime = $mime_types[$extension] ?? 'application/octet-stream';

// 确保是图片类型
if (!isset($mime_types[$extension])) {
    http_response_code(403);
    exit("Not a supported image type");
}

// 设置防下载HTTP头
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="image.' . $extension . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// 输出图片内容
readfile($real_path);
exit; 