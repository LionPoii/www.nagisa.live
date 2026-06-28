<?php
/**
 * 哔哩哔哩动态API接口
 * 提供动态数据获取和配置管理功能
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/bilibili_dynamic.php';
require_once __DIR__ . '/../includes/database.php';

// 记录API调用日志
function log_api_call($endpoint, $params = []) {
    $log_dir = __DIR__ . '/../logs/dynamic_api';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'params' => $params,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    $log_file = $log_dir . '/dynamic_api_' . date('Y-m-d') . '.log';
    file_put_contents($log_file, json_encode($log_data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

// 缓存API返回数据
function cache_api_data($action, $data) {
    $cache_dir = __DIR__ . '/api_cache/dynamic_api';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    // 提取action的基本部分（不包含时间戳等动态部分）
    $action_base = preg_replace('/_\d+_page_\d+$/', '', $action); // 处理类似get_dynamics_{mid}_page_{page}格式
    $action_base = preg_replace('/_[0-9a-f]{32}$/', '', $action_base); // 处理类似error_{md5}格式
    
    // 数据未变化时仅刷新已有缓存文件的修改时间，不生成新文件
    $pattern = $cache_dir . '/' . $action_base . '*' . '.json';
    $existing_files = glob($pattern);
    if (!empty($existing_files)) {
        usort($existing_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $latest = $existing_files[0];
        $existing_data = json_decode(file_get_contents($latest), true);
        if (json_encode($existing_data) === json_encode($data)) {
            touch($latest);
            log_api_call('cache_touch', ['file' => basename($latest), 'action' => $action]);
            return $latest;
        }
    }
    
    $cache_file = $cache_dir . '/' . $action . '_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($cache_file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    // 清理旧文件，只保留最新的文件
    $pattern = $cache_dir . '/' . $action_base . '*' . '.json'; // 使用通配符匹配相同类型的缓存文件
    $files = glob($pattern);
    
    if (count($files) > 1) {
        // 按修改时间排序（从新到旧）
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // 记录文件清理情况
        log_api_call('cache_cleanup', [
            'action' => $action, 
            'total_files' => count($files), 
            'keeping' => basename($files[0])
        ]);
        
        // 删除除了最新文件之外的所有文件
        for ($i = 1; $i < count($files); $i++) {
            if (file_exists($files[$i])) {
                if (unlink($files[$i])) {
                    log_api_call('cache_delete_success', ['file' => basename($files[$i])]);
                } else {
                    log_api_call('cache_delete_fail', ['file' => basename($files[$i])]);
                }
            }
        }
    }
    
    log_api_call('cache', ['file' => basename($cache_file), 'action' => $action]);
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // 添加缓存控制头
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    
    switch ($action) {
        case 'get_dynamics':
            // 获取动态列表
            $mid = intval($_GET['mid'] ?? 2124647716);
            $page = intval($_GET['page'] ?? 1);
            $page_size = intval($_GET['page_size'] ?? BilibiliDynamic::DISPLAY_COUNT);
            
            // 限制参数范围
            $page = max(1, min($page, 10));
            $page_size = max(1, min($page_size, 30));
            
            log_api_call('get_dynamics', ['mid' => $mid, 'page' => $page, 'page_size' => $page_size, 'force' => isset($_GET['force']) ? $_GET['force'] : '0']);
            
            $biliDynamic = new BilibiliDynamic();
            // 检查是否需要强制刷新
            $force_refresh = isset($_GET['force']) && $_GET['force'] == '1';

            $exclude_pinned = false;
            try {
                $db = new Database();
                $conn = $db->getConnection();
                $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'show_pinned'");
                $stmt->execute();
                $config = $stmt->fetch(PDO::FETCH_ASSOC);
                $exclude_pinned = $config ? !(bool)$config['config_value'] : false;
            } catch (Exception $e) {
                // 使用默认值
            }

            $dynamics = $biliDynamic->getProcessedDynamics($mid, $page, $page_size, $force_refresh, $exclude_pinned);

            $total = $biliDynamic->getDynamicCount($mid);
            
            echo json_encode([
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'dynamics' => $dynamics,
                    'total' => $total,
                    'page' => $page,
                    'page_size' => $page_size
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            $response = [
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'dynamics' => $dynamics,
                    'total' => $total,
                    'page' => $page,
                    'page_size' => $page_size
                ]
            ];
            
            // 缓存API响应
            cache_api_data("get_dynamics_{$mid}_page_{$page}", $response);
            
            break;
            
        case 'get_config':
            // 获取动态配置
            log_api_call('get_config');
            
            $db = new Database();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT config_key, config_value FROM site_config WHERE config_key LIKE 'bilibili_%'");
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $config_data = [];
            foreach ($configs as $config) {
                $config_data[$config['config_key']] = $config['config_value'];
            }
            
            $response = [
                'code' => 0,
                'message' => 'success',
                'data' => $config_data
            ];
            
            // 缓存API响应
            cache_api_data("get_config", $response);
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'update_config':
            // 更新动态配置
            $mid = $_POST['mid'] ?? '';
            $enabled = $_POST['enabled'] ?? '1';
            
            if (empty($mid) || !is_numeric($mid)) {
                throw new Exception('用户ID不能为空且必须为数字');
            }
            
            log_api_call('update_config', ['mid' => $mid, 'enabled' => $enabled]);
            
            $db = new Database();
            $conn = $db->getConnection();
            
            // 更新或插入用户ID配置
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_mid', ?) ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$mid, $mid]);
            
            // 更新或插入启用状态配置
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_dynamic_enabled', ?) ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$enabled, $enabled]);
            
            $response = [
                'code' => 0,
                'message' => '配置更新成功',
                'data' => [
                    'mid' => $mid,
                    'enabled' => $enabled
                ]
            ];
            
            // 缓存API响应
            cache_api_data("update_config_{$mid}", $response);
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'clear_cache':
            // 清除动态缓存
            log_api_call('clear_cache');
            
            // 清除缓存目录
            $cache_dir_old = __DIR__ . '/../cache/';
            $cache_dir_new = __DIR__ . '/api_cache/dynamic_api/';
            
            $files_old = glob($cache_dir_old . 'bili_dynamic_*.json');
            $files_new = glob($cache_dir_new . '*.json');
            
            $cleared_count = 0;
            // 清除旧缓存位置的文件
            foreach ($files_old as $file) {
                if (file_exists($file) && unlink($file)) {
                    $cleared_count++;
                }
            }
            
            // 清除新缓存位置的文件
            foreach ($files_new as $file) {
                if (file_exists($file) && unlink($file)) {
                    $cleared_count++;
                }
            }
            
            $response = [
                'code' => 0,
                'message' => "成功清除 {$cleared_count} 个缓存文件",
                'data' => [
                    'cleared_count' => $cleared_count
                ]
            ];
            
            // 缓存API响应
            cache_api_data("clear_cache", $response);
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'test_api':
            // 测试API连接
            $mid = intval($_GET['mid'] ?? 2124647716);
            
            log_api_call('test_api', ['mid' => $mid]);
            
            $biliDynamic = new BilibiliDynamic();
            $result = $biliDynamic->getUserDynamics($mid, 1, 1);
            
            if ($result && isset($result['code']) && $result['code'] === 0) {
                $response = [
                    'code' => 0,
                    'message' => 'API连接正常',
                    'data' => [
                        'api_status' => 'success',
                        'total_dynamics' => $result['data']['page']['total'] ?? 0
                    ]
                ];
            } else {
                $response = [
                    'code' => 1,
                    'message' => 'API连接失败',
                    'data' => [
                        'api_status' => 'failed',
                        'error' => $result['message'] ?? '未知错误'
                    ]
                ];
            }
            
            // 缓存API响应
            cache_api_data("test_api_{$mid}", $response);
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'manual_clean_cache':
            // 手动强制清理所有缓存文件
            log_api_call('manual_clean_cache');
            
            $cache_dir = __DIR__ . '/api_cache/dynamic_api';
            if (!is_dir($cache_dir)) {
                mkdir($cache_dir, 0755, true);
            }
            
            // 获取所有缓存文件
            $files = glob($cache_dir . '/*.json');
            $total_files = count($files);
            $deleted_files = 0;
            
            // 遍历并删除所有文件
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $filename = basename($file);
                    if (unlink($file)) {
                        log_api_call('manual_delete_success', ['file' => $filename]);
                        $deleted_files++;
                    } else {
                        log_api_call('manual_delete_fail', ['file' => $filename]);
                    }
                }
            }
            
            $response = [
                'code' => 0,
                'message' => "手动清理缓存完成",
                'data' => [
                    'total_files' => $total_files,
                    'deleted_files' => $deleted_files,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            throw new Exception('未知的操作类型');
    }
    
} catch (Exception $e) {
    log_api_call('error', ['error' => $e->getMessage()]);
    
    $response = [
        'code' => 1,
        'message' => $e->getMessage(),
        'data' => null
    ];
    
    // 缓存错误响应
    cache_api_data("error_" . md5($e->getMessage()), $response);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} 