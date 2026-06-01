<?php
/**
 * 商品系列 AJAX 接口（仿标签管理）
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/shopcar_helpers.php';

checkAdminAuth();

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => '未知错误'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = '无效的请求方法';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? '';
$db = new Database();
$conn = $db->getConnection();

if (!$conn || !shopcarTableExists($conn, 'shopcar_products')) {
    $response['message'] = '商品数据表不存在';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

shopcarEnsureSchema($conn);

if (!shopcarTableExists($conn, 'shopcar_series')) {
    $response['message'] = '商品系列表不存在';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $position = intval($_POST['position'] ?? 0);
            $active = isset($_POST['active']) && $_POST['active'] !== '0' ? 1 : 0;

            if ($title === '') {
                throw new Exception('商品系列名称不能为空');
            }

            $check = $conn->prepare('SELECT id FROM shopcar_series WHERE title = ? LIMIT 1');
            $check->execute([$title]);
            if ($check->fetch()) {
                throw new Exception('商品系列名称已存在');
            }

            $stmt = $conn->prepare('INSERT INTO shopcar_series (parent_id, title, description, position, active) VALUES (NULL, ?, ?, ?, ?)');
            $stmt->execute([$title, $description, $position, $active]);

            $response['success'] = true;
            $response['message'] = '商品系列添加成功';
            $response['series'] = [
                'id' => (int)$conn->lastInsertId(),
                'title' => $title,
                'description' => $description,
                'position' => $position,
                'active' => $active,
            ];
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $position = intval($_POST['position'] ?? 0);
            $active = isset($_POST['active']) && $_POST['active'] !== '0' ? 1 : 0;

            if ($id <= 0) {
                throw new Exception('无效的商品系列');
            }
            if ($title === '') {
                throw new Exception('商品系列名称不能为空');
            }

            $check = $conn->prepare('SELECT id FROM shopcar_series WHERE title = ? AND id != ? LIMIT 1');
            $check->execute([$title, $id]);
            if ($check->fetch()) {
                throw new Exception('商品系列名称已存在');
            }

            $stmt = $conn->prepare('UPDATE shopcar_series SET parent_id = NULL, title = ?, description = ?, position = ?, active = ? WHERE id = ?');
            $stmt->execute([$title, $description, $position, $active, $id]);

            $response['success'] = true;
            $response['message'] = '商品系列已更新';
            $response['series'] = [
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'position' => $position,
                'active' => $active,
            ];
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('无效的商品系列');
            }

            if (shopcarColumnExists($conn, 'shopcar_products', 'series_id')) {
                $prodStmt = $conn->prepare('SELECT COUNT(*) FROM shopcar_products WHERE series_id = ?');
                $prodStmt->execute([$id]);
                if ($prodStmt->fetchColumn() > 0) {
                    throw new Exception('该商品系列下仍有商品，请先移动或删除商品');
                }
            }

            $stmt = $conn->prepare('DELETE FROM shopcar_series WHERE id = ?');
            $stmt->execute([$id]);

            $response['success'] = true;
            $response['message'] = '商品系列已删除';
            break;

        default:
            throw new Exception('未识别的操作');
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
