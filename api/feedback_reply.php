<?php
require_once __DIR__ . '/../includes/api_no_cache_headers.php';
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/database.php';
require_once '../includes/auth.php';

function response($success, $msg = '', $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'msg' => $msg
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

// 仅允许已登录管理员调用
checkAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, '仅支持 POST 请求');
}

$id = intval($_POST['id'] ?? 0);
$reply = trim($_POST['reply'] ?? '');
if ($id <= 0) response(false, '无效的 id');

try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare('UPDATE feedback SET reply=?, reply_at=NOW(), status=1 WHERE id=?');
    $stmt->execute([$reply, $id]);
    response(true, '回复已保存', ['id' => $id, 'reply' => $reply, 'reply_at' => date('Y-m-d H:i:s')]);
} catch (Exception $e) {
    response(false, '数据库错误: ' . $e->getMessage());
}





