<?php
/**
 * API 响应防缓存头（源站 + CDN）
 * 在 api/*.php 入口文件最顶部 require_once 即可。
 */
if (headers_sent()) {
    return;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');
// 阿里云 CDN / 部分边缘节点识别的私有头
header('CDN-Cache-Control: no-store');
header('X-CDN-Cache-Control: no-store');
