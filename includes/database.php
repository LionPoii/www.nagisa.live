<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        // 检查是否已定义配置常量
        if (!defined('DB_HOST')) {
            // 如果没有定义，则加载配置文件
            $config_file = __DIR__ . '/../config.php';
            if (file_exists($config_file)) {
                // 定义标记，防止config.php中的访问检查阻止加载
                define('IN_SYSTEM', true);
                require_once $config_file;
            }
        }
        
        // 使用配置文件中的常量
        $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $this->db_name = defined('DB_NAME') ? DB_NAME : 'www_nagisa_live';
        $this->username = defined('DB_USER') ? DB_USER : 'www_nagisa_live';
        $this->password = defined('DB_PASS') ? DB_PASS : '31368705';
    }

    public function getConnection() {
        $this->conn = null;

        try {
            // 添加连接参数：超时设置和持久连接
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true, // 使用持久连接
                PDO::ATTR_TIMEOUT => 5 // 设置5秒连接超时
            ];
            
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";connect_timeout=5",
                $this->username,
                $this->password,
                $options
            );
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $e) {
            // 记录错误但不显示给用户，防止信息泄露
            error_log("数据库连接错误: " . $e->getMessage());
            // 返回一个可用的空连接，防止页面崩溃
            $this->conn = null;
        }

        return $this->conn;
    }
} 