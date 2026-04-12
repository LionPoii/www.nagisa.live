<?php
// 防止直接访问
if (!defined('IN_SYSTEM')) {
    die('Access Denied');
}

// 基础配置
define('SITE_NAME', '虚拟主播名称');
define('SITE_URL', 'https://www.nagisa.live');
define('TIMEZONE', 'Asia/Shanghai');

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'www_nagisa_live');
define('DB_USER', 'www_nagisa_live');
define('DB_PASS', '31368705');

// API配置
define('BILIBILI_API_KEY', '');  // B站API密钥
define('BILIBILI_UID', '53051788');      // B站UID

// 文件上传配置
define('UPLOAD_PATH', __DIR__ . '/uploads');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// 安全配置
define('SESSION_LIFETIME', 3600); // 1小时
define('CSRF_TOKEN_NAME', 'csrf_token');

// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// 设置时区
date_default_timezone_set(TIMEZONE);

// 会话配置（只在 session 未启动时设置）
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1);
    session_start();
}