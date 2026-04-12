<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';

// 检查管理员登录状态
checkAdminAuth();

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => '仅支持 POST 请求']);
        exit;
    }

    // 图片状态切换
    if (isset($_POST['toggle_expression_status'])) {
        $id = intval($_POST['expression_id'] ?? 0);
        $status = intval($_POST['expression_status'] ?? 0);
        $stmt = $conn->prepare("UPDATE expression_images SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // 语音状态切换
    if (isset($_POST['toggle_audio_status'])) {
        $id = intval($_POST['audio_id'] ?? 0);
        $status = intval($_POST['audio_status'] ?? 0);
        $stmt = $conn->prepare("UPDATE expression_audios SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // 商品状态切换（仅当表存在时）
    $shopcarTableExists = false;
    try {
        $t = $conn->prepare("SHOW TABLES LIKE 'shopcar_products'");
        $t->execute();
        $shopcarTableExists = ($t->rowCount() > 0);
    } catch (Exception $e) {
        // ignore
    }

    if (isset($_POST['toggle_product_status']) && $shopcarTableExists) {
        $id = intval($_POST['product_id'] ?? 0);
        $status = intval($_POST['product_status'] ?? 0);
        $stmt = $conn->prepare("UPDATE shopcar_products SET active = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => '未识别的操作']);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}


