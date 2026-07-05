<?php
require_once __DIR__ . '/../includes/api_no_cache_headers.php';
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/database.php';

function response($success, $msg = '', $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'msg' => $msg
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket = trim($_GET['ticket'] ?? $_POST['ticket'] ?? '');
if ($ticket === '') {
    response(false, '请输入单号');
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT id, ticket_number, type, message, name, image_paths, status, reply, reply_at, created_at FROM feedback WHERE ticket_number = ? LIMIT 1");
    $stmt->execute([$ticket]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        response(false, '未找到对应单号');
    }

    response(true, '查询成功', [
        'ticket' => $row['ticket_number'],
        'type' => $row['type'],
        'message' => $row['message'],
        'name' => $row['name'],
        'image_paths' => json_decode($row['image_paths'], true) ?: [],
        'status' => intval($row['status']),
        'reply' => $row['reply'],
        'reply_at' => $row['reply_at'],
        'created_at' => $row['created_at']
    ]);
} catch (Exception $e) {
    response(false, '数据库错误: ' . $e->getMessage());
}





