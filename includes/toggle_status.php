<?php
/**
 * 表情和语音状态切换处理器
 * 通过AJAX请求切换表情包和语音的启用状态
 */

// 设置响应头为JSON
header('Content-Type: application/json');

// 引入必要的文件
require_once 'auth.php';
require_once 'database.php';

// 检查管理员权限
if (!isAdmin()) {
    echo json_encode([
        'success' => false,
        'message' => '权限不足'
    ]);
    exit;
}

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => '请求方法不允许'
    ]);
    exit;
}

// 获取并验证请求参数
$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
$item_type = isset($_POST['item_type']) ? $_POST['item_type'] : '';
$status = isset($_POST['status']) ? intval($_POST['status']) : 0;

// 验证请求参数的合法性
if (empty($item_id) || !in_array($item_type, ['expression', 'audio']) || !in_array($status, [0, 1])) {
    echo json_encode([
        'success' => false,
        'message' => '参数错误'
    ]);
    exit;
}

try {
    // 获取数据库连接
    $db = new Database();
    $conn = $db->getConnection();
    
    // 根据项目类型确定表名
    $table = $item_type === 'expression' ? 'expression_images' : 'expression_audios';
    
    // 准备并执行更新语句
    $stmt = $conn->prepare("UPDATE $table SET status = ? WHERE id = ?");
    $stmt->execute([$status, $item_id]);
    
    // 检查是否成功更新
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => '状态已更新',
            'status' => $status
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '未找到要更新的项目'
        ]);
    }
} catch (PDOException $e) {
    // 记录错误日志
    error_log("表情状态切换错误：" . $e->getMessage(), 0);
    
    echo json_encode([
        'success' => false,
        'message' => '数据库错误，请查看日志'
    ]);
} 