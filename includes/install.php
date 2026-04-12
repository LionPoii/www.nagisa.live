<?php
require_once 'config.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // 读取 SQL 文件内容
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    // 执行 SQL 语句
    $conn->exec($sql);
    
    echo "数据库表创建成功！<br>";
    echo "现在您可以：<br>";
    echo "1. <a href='/index.php'>访问主页</a><br>";
    echo "2. <a href='/admin/login.php'>访问管理后台</a>（用户名：admin，密码：admin123）<br>";
    
} catch(PDOException $e) {
    echo "数据库错误: " . $e->getMessage();
}
?> 
require_once 'config.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // 读取 SQL 文件内容
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    // 执行 SQL 语句
    $conn->exec($sql);
    
    echo "数据库表创建成功！<br>";
    echo "现在您可以：<br>";
    echo "1. <a href='/index.php'>访问主页</a><br>";
    echo "2. <a href='/admin/login.php'>访问管理后台</a>（用户名：admin，密码：admin123）<br>";
    
} catch(PDOException $e) {
    echo "数据库错误: " . $e->getMessage();
}
?> 
 
 
 
require_once 'config.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // 读取 SQL 文件内容
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    // 执行 SQL 语句
    $conn->exec($sql);
    
    echo "数据库表创建成功！<br>";
    echo "现在您可以：<br>";
    echo "1. <a href='/index.php'>访问主页</a><br>";
    echo "2. <a href='/admin/login.php'>访问管理后台</a>（用户名：admin，密码：admin123）<br>";
    
} catch(PDOException $e) {
    echo "数据库错误: " . $e->getMessage();
}
?> 
require_once 'config.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // 读取 SQL 文件内容
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    // 执行 SQL 语句
    $conn->exec($sql);
    
    echo "数据库表创建成功！<br>";
    echo "现在您可以：<br>";
    echo "1. <a href='/index.php'>访问主页</a><br>";
    echo "2. <a href='/admin/login.php'>访问管理后台</a>（用户名：admin，密码：admin123）<br>";
    
} catch(PDOException $e) {
    echo "数据库错误: " . $e->getMessage();
}
?> 
 
 
 
 
 