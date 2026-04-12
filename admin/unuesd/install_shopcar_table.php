<?php
// 启用错误显示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "开始安装购物车商品表...<br>";

// 引入数据库连接
require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("数据库连接失败");
}
echo "数据库连接成功<br>";

// 读取SQL文件
$sql_file = __DIR__ . '/../sql/create_shopcar_products_table.sql';
echo "SQL文件路径: " . $sql_file . "<br>";

if (!file_exists($sql_file)) {
    die("SQL文件不存在: " . $sql_file);
}

$sql = file_get_contents($sql_file);
echo "已读取SQL文件<br>";

// 执行SQL
try {
    $statements = explode(';', $sql);
    foreach($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            echo "执行SQL: " . substr($statement, 0, 50) . "...<br>";
            $conn->exec($statement);
        }
    }
    echo "购物车商品表已成功创建/更新！<br>";
    echo "<a href='manage_shopcar.php'>返回商品管理</a>";
} catch (PDOException $e) {
    die("执行SQL失败: " . $e->getMessage());
}
?> 