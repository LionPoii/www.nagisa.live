<?php
/**
 * 动态API
 * 返回用户的B站动态列表
 */

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

// 引入动态获取类
require_once __DIR__ . '/../includes/bilibili_dynamic.php';

// 获取请求参数
$mid = isset($_GET['mid']) ? intval($_GET['mid']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
$force_refresh = isset($_GET['refresh']) && $_GET['refresh'] == '1';

// 限制最大获取数量
if ($limit > 20) {
    $limit = 20;
}

// 如果没有指定用户ID，从数据库获取配置
if ($mid <= 0) {
    try {
        require_once __DIR__ . '/../includes/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_mid'");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($config && !empty($config['config_value'])) {
            $mid = intval($config['config_value']);
        } else {
            $mid = 2124647716; // 默认用户ID
        }
    } catch (Exception $e) {
        $mid = 2124647716; // 出错时使用默认值
    }
}

// 初始化B站动态类
$biliDynamic = new BilibiliDynamic();

// 获取动态列表
$dynamics = $biliDynamic->getProcessedDynamics($mid, $page, $limit, $force_refresh);

// 代理图片处理函数
function proxyBiliImage($url) {
    if (!$url) return '';
    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }
    return $url;
}

// 处理所有动态中的图片URL
foreach ($dynamics as &$dynamic) {
    // 处理动态中的图片
    if (!empty($dynamic['images'])) {
        foreach ($dynamic['images'] as &$image) {
            $image = proxyBiliImage($image);
        }
    }
    
    // 处理视频封面
    if (!empty($dynamic['video']) && !empty($dynamic['video']['cover'])) {
        $dynamic['video']['cover'] = proxyBiliImage($dynamic['video']['cover']);
    }
    
    // 处理卡片中的图片
    if (!empty($dynamic['card']) && !empty($dynamic['card']['pics'])) {
        foreach ($dynamic['card']['pics'] as &$pic) {
            $pic = proxyBiliImage($pic);
        }
    }
}
unset($dynamic);

// 输出JSON结果
echo json_encode($dynamics); 