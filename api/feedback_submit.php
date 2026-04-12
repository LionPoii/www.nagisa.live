<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/database.php';

function response($success, $msg = '', $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'msg' => $msg
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    response(false, '仅支持POST请求');
}

$type = trim($_POST['type'] ?? '');
$message = trim($_POST['message'] ?? '');
$name = trim($_POST['name'] ?? '');

if ($type === '' || $message === '') {
    response(false, '反馈类型和内容不能为空');
}

// 图片上传处理
$image_paths = [];
if (!empty($_FILES['images']['name'][0])) {
    $upload_dir = '../assets/uploads/feedback/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    foreach ($_FILES['images']['tmp_name'] as $i => $tmp_name) {
        $name_file = $_FILES['images']['name'][$i];
        $ext = strtolower(pathinfo($name_file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) continue;
        if ($_FILES['images']['size'][$i] > 2 * 1024 * 1024) continue; // 2MB限制
        $new_name = uniqid('fb_', true) . '.' . $ext;
        $target = $upload_dir . $new_name;
        if (move_uploaded_file($tmp_name, $target)) {
            $image_paths[] = 'assets/uploads/feedback/' . $new_name;
        }
    }
}

// 获取用户IP和UA
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// 写入数据库
try {
    $db = new Database();
    $conn = $db->getConnection();
    // 生成日期+序号的单号，格式 YYYYMMDDNN (NN 两位序号，必要时会扩展)
    $prefix = date('Ymd');
    $seq = 1;
    try {
        $stmtSeq = $conn->prepare("SELECT ticket_number FROM feedback WHERE ticket_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmtSeq->execute([$prefix . '%']);
        $lastTicket = $stmtSeq->fetchColumn();
        if ($lastTicket) {
            $lastSeq = intval(substr($lastTicket, 8)); // 前8位是日期 YYYYMMDD
            $seq = $lastSeq + 1;
        }
    } catch (Exception $e) {
        // 忽略计数异常，使用默认序号1
        $seq = 1;
    }
    $ticket_number = $prefix . str_pad($seq, 2, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare("INSERT INTO feedback (ticket_number, type, message, name, image_paths, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $ticket_number,
        $type,
        $message,
        $name,
        json_encode($image_paths, JSON_UNESCAPED_UNICODE),
        $ip,
        $user_agent
    ]);
    response(true, '反馈提交成功！', ['ticket' => $ticket_number]);
} catch (Exception $e) {
    response(false, '数据库错误: ' . $e->getMessage());
} 