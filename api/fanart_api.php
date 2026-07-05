<?php
require_once __DIR__ . '/../includes/api_no_cache_headers.php';
/**
 * 哔哩哔哩同人图API接口
 * 提供同人图数据获取和缓存功能
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/bilibili_rich_text.php';

/**
 * 为话题卡片 opus.summary 注入与动态页一致的表情 HTML（供同人墙使用）
 *
 * @param array $payload 形如 { code, message, data } 的接口或缓存体
 */
function fanart_enrich_payload(&$payload) {
    if (!is_array($payload) || empty($payload['data']) || !is_array($payload['data'])) {
        return;
    }
    fanart_enrich_data_root($payload['data']);
}

function fanart_enrich_data_root(&$d) {
    if (!empty($d['topic_card_list']['items']) && is_array($d['topic_card_list']['items'])) {
        foreach ($d['topic_card_list']['items'] as &$topicItem) {
            fanart_enrich_item_opus_summary($topicItem);
        }
        unset($topicItem);
    }
    if (!empty($d['items']) && is_array($d['items'])) {
        foreach ($d['items'] as &$it) {
            fanart_enrich_item_opus_summary($it);
        }
        unset($it);
    }
}

function fanart_enrich_item_opus_summary(&$item) {
    if (!empty($item['dynamic_card_item']['modules']['module_dynamic']['major']['opus']['summary'])) {
        $summary = &$item['dynamic_card_item']['modules']['module_dynamic']['major']['opus']['summary'];
    } elseif (!empty($item['modules']['module_dynamic']['major']['opus']['summary'])) {
        $summary = &$item['modules']['module_dynamic']['major']['opus']['summary'];
    } else {
        return;
    }
    if (array_key_exists('_fanart_content_html', $summary)) {
        return;
    }
    if (!empty($summary['rich_text_nodes']) && is_array($summary['rich_text_nodes'])) {
        $summary['_fanart_content_html'] = BilibiliRichText::richTextNodesToHtml($summary['rich_text_nodes']);
        if (empty($summary['text'])) {
            $summary['_fanart_content_plain'] = BilibiliRichText::richTextNodesToPlain($summary['rich_text_nodes']);
        }
    } else {
        $summary['_fanart_content_html'] = '';
    }
}

// 记录API调用日志
function log_api_call($endpoint, $params = []) {
    $log_dir = __DIR__ . '/../logs/fanart_api';
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
    
    $log_file = $log_dir . '/fanart_api_' . date('Y-m-d') . '.log';
    file_put_contents($log_file, json_encode($log_data, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

// 更新为与live_status_api.php一致的日志格式
/**
 * 记录API日志
 * @param string $content 日志内容
 */
function log_fanart_api_response($content) {
    $log_dir = __DIR__ . '/../logs/fanart_api';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/fanart_api_' . date('Y-m-d') . '.log';
    file_put_contents($log_file, date('Y-m-d H:i:s') . "\n" . $content . "\n\n", FILE_APPEND);
}

// 缓存API返回数据
function cache_api_data($action, $data) {
    $cache_dir = __DIR__ . '/api_cache/fanart_api';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    // 提取action的基本部分（不包含时间戳等动态部分）
    $action_base = preg_replace('/_\d+$/', '', $action); // 处理类似get_fanart_{timestamp}格式
    $action_base = preg_replace('/_[0-9a-f]{32}$/', '', $action_base); // 处理类似error_{md5}格式
    
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
    
    return $cache_file;
}

/**
 * 获取最新的缓存文件
 * @param string $action 操作类型
 * @return string|null 缓存文件路径或null
 */
function get_latest_cache_file($action = 'get_fanart') {
    $cache_dir = __DIR__ . '/api_cache/fanart_api';
    if (!is_dir($cache_dir)) {
        return null;
    }
    
    $pattern = $cache_dir . '/' . $action . '*' . '.json';
    $files = glob($pattern);
    if (empty($files)) {
        return null;
    }
    
    // 按修改时间排序
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    return $files[0];
}

try {
    // 获取请求参数
    $action = $_GET['action'] ?? 'get_fanart';
    $refresh = isset($_GET['refresh']) && $_GET['refresh'] == 1;
    $page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 12; // 默认每页12条
    
    // 记录请求信息
    $request_info = [
        'time' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'],
        'action' => $action,
        'refresh' => $refresh ? 'true' : 'false',
        'query_params' => $_GET
    ];
    
    // 记录请求开始
    log_fanart_api_response("=== Fanart API 请求开始 ===\n请求信息: " . json_encode($request_info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    // 为向后兼容保留原有日志记录
    log_api_call('request', [
        'action' => $action,
        'refresh' => $refresh,
        'query_params' => $_GET
    ]);
    
    switch ($action) {
        case 'get_fanart':
        case 'get_fanart_data':
            // 获取缓存文件
            $cache_file = get_latest_cache_file('get_fanart');
            
            // 检查是否需要刷新（强制刷新或缓存过期2小时）
            $cache_expired = !$cache_file || (time() - filemtime($cache_file) > 7200);
            
            if ($refresh || $cache_expired) {
                log_fanart_api_response("准备获取最新同人图数据，原因: " . ($refresh ? "强制刷新" : ($cache_expired ? "缓存已过期" : "未知原因")));
                
                // 设置请求头
                $options = [
                    'http' => [
                        'method' => 'GET',
                        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n" .
                                "Referer: https://www.bilibili.com/\r\n" .
                                "Accept: application/json\r\n" .
                                "Origin: https://www.bilibili.com\r\n"
                    ]
                ];
                $context = stream_context_create($options);
                
                // 从B站获取数据
                $api_url = 'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/topic?topic_id=1134905&sort_by=3&offset=&page_size=' . $page_size;
                log_api_call('api_request', ['url' => $api_url]);
                log_fanart_api_response("正在从B站API获取数据: " . $api_url);
                
                $response = @file_get_contents($api_url, false, $context);
                
                if ($response !== false) {
                    // 解析响应数据
                    $response_data = json_decode($response, true);
                    
                    // 生成响应
                    $api_response = [
                        'code' => $response_data['code'] ?? 0,
                        'message' => $response_data['code'] == 0 ? 'success' : ($response_data['message'] ?? 'error'),
                        'data' => $response_data['data'] ?? null
                    ];
                    
                    fanart_enrich_payload($api_response);
                    
                    // 缓存API响应
                    $timestamp = time();
                    $cache_file = cache_api_data("get_fanart_{$timestamp}", $api_response);
                    
                    // 记录成功响应
                    log_api_call('api_response_success', [
                        'response_code' => $api_response['code'],
                        'items_count' => count($response_data['data']['items'] ?? [])
                    ]);
                    
                    // 使用新格式记录日志
                    $response_summary = [
                        'status' => 'success',
                        'code' => $api_response['code'],
                        'message' => $api_response['message'],
                        'items_count' => count($response_data['data']['items'] ?? []),
                        'cache_file' => basename($cache_file)
                    ];
                    log_fanart_api_response("获取同人图数据成功:\n" . json_encode($response_summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    
                    echo json_encode($api_response, JSON_UNESCAPED_UNICODE);
                } else {
                    // 获取数据失败，尝试使用缓存数据
                    $error = error_get_last();
                    log_api_call('api_request_failed', ['error' => $error]);
                    log_fanart_api_response("获取同人图数据失败: " . ($error ? json_encode($error, JSON_UNESCAPED_UNICODE) : "未知错误"));
                    
                    if ($cache_file && file_exists($cache_file)) {
                        $cache_content = file_get_contents($cache_file);
                        $cache_data = json_decode($cache_content, true);
                        
                        // 添加缓存标记
                        if (is_array($cache_data)) {
                            $cache_data['from_cache'] = true;
                            $cache_data['cache_time'] = date('Y-m-d H:i:s', filemtime($cache_file));
                            fanart_enrich_payload($cache_data);
                        }
                        
                        // 记录使用缓存
                        log_api_call('using_cache', [
                            'cache_file' => basename($cache_file),
                            'cache_size' => strlen($cache_content)
                        ]);
                        
                        log_fanart_api_response("使用缓存数据: " . basename($cache_file) . "，缓存时间: " . date('Y-m-d H:i:s', filemtime($cache_file)));
                        
                        echo json_encode($cache_data, JSON_UNESCAPED_UNICODE);
                    } else {
                        // 缓存也不存在，返回错误
                        $error_response = [
                            'code' => -1,
                            'message' => '获取数据失败，且缓存不存在',
                            'error' => error_get_last()
                        ];
                        
                        // 缓存错误响应
                        $error_id = md5(json_encode($error_response));
                        cache_api_data("error_{$error_id}", $error_response);
                        
                        log_api_call('api_error', $error_response);
                        log_fanart_api_response("严重错误: 获取数据失败且无可用缓存\n" . json_encode($error_response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                        
                        echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
                    }
                }
            } else {
                // 使用现有缓存
                $cache_content = file_get_contents($cache_file);
                $cache_data = json_decode($cache_content, true);
                
                // 添加缓存标记
                if (is_array($cache_data)) {
                    $cache_data['from_cache'] = true;
                    $cache_data['cache_time'] = date('Y-m-d H:i:s', filemtime($cache_file));
                    fanart_enrich_payload($cache_data);
                }
                
                log_api_call('using_valid_cache', [
                    'cache_file' => basename($cache_file),
                    'cache_age' => time() - filemtime($cache_file),
                    'cache_size' => strlen($cache_content)
                ]);
                
                log_fanart_api_response("使用有效缓存: " . basename($cache_file) . 
                    "\n缓存时间: " . date('Y-m-d H:i:s', filemtime($cache_file)) . 
                    "\n缓存年龄: " . (time() - filemtime($cache_file)) . " 秒");
                
                echo json_encode($cache_data, JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'clear_cache':
            // 清除缓存
            log_api_call('clear_cache');
            log_fanart_api_response("正在清理缓存文件");
            
            $cache_dir = __DIR__ . '/api_cache/fanart_api';
            $files = glob($cache_dir . '/*.json');
            $cleared_count = 0;
            
            foreach ($files as $file) {
                if (file_exists($file) && unlink($file)) {
                    $cleared_count++;
                }
            }
            
            log_fanart_api_response("缓存清理完成，共删除 {$cleared_count} 个文件");
            
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
            
        case 'list_cache':
            // 列出缓存文件
            log_api_call('list_cache');
            log_fanart_api_response("请求列出缓存文件");
            
            $cache_dir = __DIR__ . '/api_cache/fanart_api';
            if (!is_dir($cache_dir)) {
                mkdir($cache_dir, 0755, true);
            }
            
            $files = glob($cache_dir . '/get_fanart_*.json');
            $cache_files = [];
            
            foreach ($files as $file) {
                $file_info = [
                    'filename' => basename($file),
                    'mtime' => filemtime($file),
                    'size' => filesize($file),
                    'time' => date('Y-m-d H:i:s', filemtime($file))
                ];
                $cache_files[] = $file_info;
            }
            
            // 按修改时间从新到旧排序
            usort($cache_files, function($a, $b) {
                return $b['mtime'] - $a['mtime'];
            });
            
            $response = [
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'total' => count($cache_files),
                    'files' => $cache_files
                ]
            ];
            
            log_fanart_api_response("找到 " . count($cache_files) . " 个缓存文件");
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'update_cache':
            // 手动更新缓存 - 先清除现有缓存，然后获取最新数据
            log_api_call('update_cache');
            log_fanart_api_response("手动更新缓存 - 开始处理");
            
            // 清除现有缓存
            $cache_dir = __DIR__ . '/api_cache/fanart_api';
            $files = glob($cache_dir . '/get_fanart_*.json');
            $cleared_count = 0;
            
            foreach ($files as $file) {
                if (file_exists($file) && unlink($file)) {
                    $cleared_count++;
                }
            }
            
            log_fanart_api_response("清理了 {$cleared_count} 个缓存文件，正在获取最新数据");
            
            // 设置请求头
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n" .
                            "Referer: https://www.bilibili.com/\r\n" .
                            "Accept: application/json\r\n" .
                            "Origin: https://www.bilibili.com\r\n"
                ]
            ];
            $context = stream_context_create($options);
            
            // 从B站获取数据
            $topic_id = '1134905'; // 默认话题ID
            
            // 尝试从数据库中获取配置的话题ID
            try {
                require_once __DIR__ . '/../includes/database.php';
                $db = new Database();
                $conn = $db->getConnection();
                
                $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'fanart_topic_id'");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && !empty($result['config_value'])) {
                    $topic_id = $result['config_value'];
                }
            } catch (Exception $e) {
                log_fanart_api_response("获取话题ID出错，使用默认值: " . $e->getMessage());
            }
            
            $api_url = 'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/topic?topic_id=' . $topic_id . '&sort_by=3&offset=&page_size=' . $page_size;
            log_api_call('api_request', ['url' => $api_url]);
            log_fanart_api_response("正在从B站API获取数据: " . $api_url);
            
            $response = @file_get_contents($api_url, false, $context);
            
            if ($response !== false) {
                // 解析响应数据
                $response_data = json_decode($response, true);
                
                fanart_enrich_payload($response_data);
                
                // 生成响应
                $api_response = [
                    'code' => $response_data['code'] ?? 0,
                    'message' => $response_data['code'] == 0 ? '缓存更新成功' : ($response_data['message'] ?? 'error'),
                    'data' => [
                        'topic_id' => $topic_id,
                        'items_count' => count($response_data['data']['items'] ?? []),
                        'cleared_count' => $cleared_count,
                        'time' => date('Y-m-d H:i:s')
                    ]
                ];
                
                // 缓存API响应
                $timestamp = time();
                $cache_file = cache_api_data("get_fanart_{$timestamp}", $response_data);
                
                // 记录成功响应
                log_api_call('update_cache_success', [
                    'response_code' => $api_response['code'],
                    'items_count' => count($response_data['data']['items'] ?? [])
                ]);
                
                log_fanart_api_response("缓存更新成功:\n" . json_encode($api_response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                
                echo json_encode($api_response, JSON_UNESCAPED_UNICODE);
            } else {
                // 获取数据失败
                $error = error_get_last();
                $error_response = [
                    'code' => -1,
                    'message' => '更新缓存失败：无法获取最新数据',
                    'error' => $error
                ];
                
                log_api_call('update_cache_failed', ['error' => $error]);
                log_fanart_api_response("更新缓存失败: " . ($error ? json_encode($error, JSON_UNESCAPED_UNICODE) : "未知错误"));
                
                echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'get_cache':
            // 获取指定的缓存文件
            $filename = $_GET['file'] ?? '';
            
            if (empty($filename)) {
                log_api_call('get_cache_error', ['error' => '缺少文件名参数']);
                echo json_encode([
                    'code' => -1,
                    'message' => '缺少文件名参数'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // 安全检查：确保文件名只包含允许的字符
            if (false && !preg_match('/^get_fanart_\d+\.json$/', $filename)) {
                log_api_call('get_cache_error', ['error' => '无效的文件名']);
                echo json_encode([
                    'code' => -1,
                    'message' => '无效的文件名'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // 更宽松但安全的文件名检查，防止目录遍历攻击
            if (preg_match('/\.\.\/|\.\.\\\\|^\/|^\\\\|<|>|\*|\?|\"|\'|\||&|;/', $filename)) {
                log_api_call('get_cache_error', ['error' => '无效的文件名：存在危险字符']);
                echo json_encode([
                    'code' => -1,
                    'message' => '无效的文件名：存在危险字符'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $cache_file = __DIR__ . '/api_cache/fanart_api/' . $filename;
            
            if (!file_exists($cache_file)) {
                log_api_call('get_cache_error', ['error' => '文件不存在', 'file' => $filename]);
                echo json_encode([
                    'code' => -1,
                    'message' => '文件不存在: ' . $filename
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $content = file_get_contents($cache_file);
            $data = json_decode($content, true);
            
            if (is_array($data)) {
                fanart_enrich_payload($data);
                log_api_call('get_cache', ['file' => $filename]);
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                log_api_call('get_cache_error', ['error' => '缓存文件内容无效', 'file' => $filename]);
                
                // 尝试修复缓存：删除损坏的缓存文件并记录日志
                try {
                    $remove_result = unlink($cache_file);
                    log_api_call('auto_repair', [
                        'action' => 'remove_invalid_cache',
                        'file' => $filename,
                        'success' => $remove_result ? 'true' : 'false'
                    ]);
                    log_fanart_api_response("自动修复: 删除了损坏的缓存文件 {$filename}");
                } catch (Exception $e) {
                    log_api_call('auto_repair_error', [
                        'message' => $e->getMessage(),
                        'file' => $filename
                    ]);
                }
                
                echo json_encode([
                    'code' => -1,
                    'message' => '缓存文件内容无效，已尝试自动修复'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        default:
            // 未知操作
            $error_response = ['error' => '未知操作: ' . $action];
            log_api_call('unknown_action', ['action' => $action]);
            log_fanart_api_response("错误: 未知操作 " . $action);
            echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
            break;
    }
    
    // 记录请求结束
    log_fanart_api_response("=== Fanart API 请求结束 ===\n");
    
} catch (Exception $e) {
    // 记录异常信息
    $error_response = [
        'code' => -1,
        'message' => '服务器内部错误',
        'error' => $e->getMessage()
    ];
    
    log_api_call('exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    log_fanart_api_response("严重错误: 捕获到异常\n" . 
        "消息: " . $e->getMessage() . "\n" .
        "文件: " . $e->getFile() . "\n" .
        "行号: " . $e->getLine() . "\n" .
        "堆栈: " . $e->getTraceAsString());
    
    // 缓存错误响应
    $error_id = md5($e->getMessage());
    cache_api_data("error_{$error_id}", $error_response);
    
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
    
    // 记录请求结束（异常情况）
    log_fanart_api_response("=== Fanart API 请求异常结束 ===\n");
}
?> 