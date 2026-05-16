<?php
// 设置安全响应头
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// 关闭错误显示，避免错误信息混入JSON输出
ini_set('display_errors', 0);
error_reporting(E_ERROR);

// 定义系统标记
define('IN_SYSTEM', true);

// 引入必要的文件
require_once __DIR__ . '/../includes/bilibili_dynamic.php';

// 初始化B站动态检测类
$mid = 2124647716; // 默认用户ID

// 从数据库获取配置
try {
    require_once __DIR__ . '/../includes/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // 获取用户ID配置
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_mid'");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($config && !empty($config['config_value'])) {
        $mid = $config['config_value'];
    }
} catch (Exception $e) {
    // 出错时使用默认值
}

// 获取动态数据
try {
    // 记录请求时间戳
    $timestamp = isset($_GET['t']) ? $_GET['t'] : time();
    
    $biliDynamic = new BilibiliDynamic();
    // 获取更多动态，以便找到非置顶的最新动态
    $dynamics = $biliDynamic->getProcessedDynamics($mid, 1, 10, true); // 强制刷新获取最新的10条动态
    
    $response = [
        'success' => true,
        'latest_dynamic' => null,
        'timestamp' => time()
    ];
    
    if (!empty($dynamics)) {
        // 查找第一个非置顶动态
        $latest = null;
        foreach ($dynamics as $dynamic) {
            if (!$dynamic['is_pinned']) {
                $latest = $dynamic;
                break;
            }
        }
        
        // 如果没有找到非置顶动态，则使用第一条动态（可能是置顶的）
        if (!$latest && count($dynamics) > 0) {
            $latest = $dynamics[0];
        }
        
        if ($latest) {
            $text = isset($latest['content']) ? $latest['content'] : '';
            
            $response['latest_dynamic'] = [
                'id' => $latest['id'],
                'timestamp' => $latest['timestamp'],
                'text' => mb_substr(strip_tags($text), 0, 50) . '...', // 截取前50个字符
                'url' => 'https://t.bilibili.com/' . $latest['id'] // 添加直接访问动态的URL
            ];
        }
    }
    
    echo json_encode($response);
} catch (Exception $e) {
    // 处理错误
    echo json_encode([
        'success' => false,
        'error' => '获取动态数据失败',
        'message' => $e->getMessage(),
        'timestamp' => time()
    ]);
} 