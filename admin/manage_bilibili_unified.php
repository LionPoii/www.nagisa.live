<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/toast.php';
require_once '../includes/bilibili_live.php';
require_once '../includes/bilibili_dynamic.php';

// 检查管理员登录状态
checkAdminAuth();

// 图片URL处理函数
function getBiliImageProxyUrl($imageUrl) {
    if (empty($imageUrl)) {
        return '/assets/images/default-avatar.png';
    }
    
    // 如果是本地图片，直接返回
    if (strpos($imageUrl, 'http') !== 0) {
        return $imageUrl;
    }
    
    // 如果是B站图片直链且为协议相对地址，补全为HTTPS
    if (strpos($imageUrl, 'bilibili.com') !== false || strpos($imageUrl, 'hdslb.com') !== false) {
        return strpos($imageUrl, '//') === 0 ? 'https:' . $imageUrl : $imageUrl;
    }
    
    // 其他图片直接返回原URL
    return $imageUrl;
}

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

// 初始化变量
$avatar = '';
$username = '';
$description = '';
$followers = 0;
$roomId = '31368705';
$current_uid = '';
$current_cookie = '';
$current_mid = '2124647716'; // 动态用户ID
$dynamic_enabled = '1'; // 动态功能启用状态
$show_pinned = true;
$show_spacevideos = true; // 默认显示视频组件

// 延迟加载标志
$lazy_load = true;

// 获取已保存的配置信息
try {
    // 获取B站直播间ID
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_room_id'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $roomId = $result ? $result['config_value'] : '31368705';

    // 获取头像
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_avatar'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $avatar = $result ? $result['config_value'] : '';

    // 获取用户名
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_username'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $username = $result ? $result['config_value'] : '';

    // 获取简介
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_description'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $description = $result ? $result['config_value'] : '';

    // 获取B站UID
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_uid'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_uid = $result ? $result['config_value'] : '';

    // 获取B站Cookie
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_cookie'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_cookie = $result ? $result['config_value'] : '';
    
    // 获取动态用户ID
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_mid'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_mid = $result ? $result['config_value'] : '2124647716';
    
    // 获取动态功能启用状态
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_dynamic_enabled'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $dynamic_enabled = $result ? $result['config_value'] : '1';
    
    // 获取显示置顶动态状态
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'show_pinned'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $show_pinned = $result ? (bool)$result['config_value'] : true;
    
    // 获取显示视频组件状态
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'show_spacevideos'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $show_spacevideos = $result !== false ? (bool)$result['config_value'] : true;
    
    // 尝试从B站API获取粉丝数
    if ($roomId) {
        $biliLive = new BilibiliLive($roomId);
        $liveInfo = $biliLive->getLiveInfo();
        if (isset($liveInfo['attention'])) {
            $api_followers = (int)$liveInfo['attention'];
            
            // 获取保存的粉丝数
            $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_followers'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $followers = $result ? (int)$result['config_value'] : 0;
            
            // 如果API粉丝数与保存的不同，自动更新
            if ($api_followers != $followers) {
                $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_followers', ?) 
                                      ON DUPLICATE KEY UPDATE config_value = ?");
                $stmt->execute([$api_followers, $api_followers]);
                $followers = $api_followers;
            }
        }
    }
} catch (PDOException $e) {
    showToast("数据库查询错误: " . $e->getMessage(), "error");
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 处理直播间配置
        if (isset($_POST['update_room'])) {
            $newRoomId = trim($_POST['room_id'] ?? '');
            
            // 验证房间ID
            if (!$newRoomId || !is_numeric($newRoomId)) {
                throw new Exception('请输入有效的直播间ID');
            }
            
            // 尝试获取直播间信息
            $biliLive = new BilibiliLive($newRoomId);
            $liveInfo = $biliLive->getLiveInfo();
            
            if (empty($liveInfo) || !isset($liveInfo['room_id'])) {
                throw new Exception('无法获取该直播间信息，请确认ID正确');
            }
            
            // 保存配置
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_room_id', ?) 
                                  ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$newRoomId, $newRoomId]);
            
            // 清除缓存
            $cacheFile = __DIR__ . "/../cache/bilibili_live_{$roomId}.json";
            if (file_exists($cacheFile)) {
                @unlink($cacheFile);
            }
            
            // 更新当前值
            $roomId = $newRoomId;
            
            showToast('直播间配置已更新！');
        }
        
        // 处理主播信息更新
        if (isset($_POST['update_profile'])) {
            // 处理头像上传
            if (!empty($_FILES['avatar_file']['name'])) {
                $upload_dir = '../assets/uploads/liverface/';
                $file_extension = strtolower(pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION));
                
                // 检查文件类型
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($file_extension, $allowed_types)) {
                    // 生成唯一文件名
                    $new_filename = 'liver_avatar_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $upload_path)) {
                        // 更新头像URL为本地路径
                        $new_avatar = '/assets/uploads/liverface/' . $new_filename;
                        
                        $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_avatar', ?) 
                                              ON DUPLICATE KEY UPDATE config_value = ?");
                        $stmt->execute([$new_avatar, $new_avatar]);
                        
                        $avatar = $new_avatar;
                    } else {
                        throw new Exception("头像上传失败");
                    }
                } else {
                    throw new Exception("只允许上传JPG、JPEG、PNG或GIF格式的图片");
                }
            } else if (!empty($_POST['avatar'])) {
                // 如果没有上传文件但提供了URL
                $new_avatar = trim($_POST['avatar']);
                $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_avatar', ?) 
                                      ON DUPLICATE KEY UPDATE config_value = ?");
                $stmt->execute([$new_avatar, $new_avatar]);
                $avatar = $new_avatar;
            }
            
            // 更新用户名
            $new_username = trim($_POST['username']);
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_username', ?) 
                                    ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$new_username, $new_username]);
            
            // 更新简介
            $new_description = trim($_POST['description']);
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_description', ?) 
                                    ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$new_description, $new_description]);
            
            // 更新本地变量以显示新值
            $username = $new_username;
            $description = $new_description;
            
            showToast("主播信息已成功更新！");
        }
        
        // 处理视频空间配置更新
        if (isset($_POST['update_space'])) {
            $newUID = trim($_POST['bilibili_uid'] ?? '');
            
            // 保存UID
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_uid', ?) 
                                  ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$newUID, $newUID]);
            
            // 更新视频显示设置
            $show_videos = isset($_POST['show_spacevideos']) ? '1' : '0';
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('show_spacevideos', ?) 
                                  ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$show_videos, $show_videos]);
            
            // 清除视频缓存
            $cache_dir = __DIR__ . '/../api/api_cache/spacevideos/';
            if (is_dir($cache_dir)) {
                // 删除包含该UID的缓存文件
                if (!empty($newUID)) {
                    $cache_pattern = $cache_dir . "*_{$newUID}_*.json";
                    foreach (glob($cache_pattern) as $cache_file) {
                        @unlink($cache_file);
                    }
                }
            }
            
            // 更新当前值
            $current_uid = $newUID;
            $show_spacevideos = (bool)$show_videos;
            
            showToast('视频空间配置已更新！');
        }
        
        // 处理动态配置
        if (isset($_POST['update_dynamic'])) {
            $new_mid = trim($_POST['bilibili_mid'] ?? '');
            $new_enabled = $_POST['dynamic_enabled'] ?? '0';
            $new_show_pinned = isset($_POST['show_pinned']) ? 1 : 0;
            
            // 验证用户ID
            if (!empty($new_mid) && !is_numeric($new_mid)) {
                throw new Exception('用户ID必须为数字');
            }
            
            // 保存用户ID配置
            if (!empty($new_mid)) {
                $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_mid', ?) 
                                      ON DUPLICATE KEY UPDATE config_value = ?");
                $stmt->execute([$new_mid, $new_mid]);
                $current_mid = $new_mid;
            }
            
            // 保存启用状态配置
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_dynamic_enabled', ?) 
                                  ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$new_enabled, $new_enabled]);
            $dynamic_enabled = $new_enabled;
            
            // 保存显示置顶动态状态配置
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('show_pinned', ?) 
                                  ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$new_show_pinned, $new_show_pinned]);
            $show_pinned = $new_show_pinned;
            
            // 清除动态缓存
            $cache_dir = __DIR__ . '/../cache/';
            $files = glob($cache_dir . 'bili_dynamic_*.json');
            foreach ($files as $file) {
                @unlink($file);
            }
            
            showToast('动态配置已更新！');
        }
        
        // 处理Cookie配置
        if (isset($_POST['update_cookie'])) {
            $new_cookie = trim($_POST['bilibili_cookie'] ?? '');
            
            // 保存Cookie配置
            if (!empty($new_cookie)) {
                $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('bilibili_cookie', ?) 
                                      ON DUPLICATE KEY UPDATE config_value = ?");
                $stmt->execute([$new_cookie, $new_cookie]);
                $current_cookie = $new_cookie;
            }
            
            showToast('Cookie配置已更新！');
        }
        
        // 处理同人图墙配置
        if (isset($_POST['action']) && $_POST['action'] === 'update_fanart') {
            // 获取表单数据
            $fanart_enabled = isset($_POST['fanart_enabled']) ? $_POST['fanart_enabled'] : '1';
            $topic_id = trim($_POST['topic_id'] ?? '1134905');
            $show_i0 = isset($_POST['show_i0']) ? '1' : '0';
            $show_i1 = isset($_POST['show_i1']) ? '1' : '0';
            $cache_time = isset($_POST['cache_time']) ? (int)$_POST['cache_time'] : 7200;
            
            // 验证数据
            if (!is_numeric($topic_id)) {
                throw new Exception('话题ID必须为数字');
            }
            
            if ($cache_time < 300) {
                $cache_time = 300; // 最小缓存时间5分钟
            }
            
            // 保存同人图墙启用状态
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('fanart_enabled', ?) 
                                  ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$fanart_enabled, $fanart_enabled]);
            
            // 保存话题ID
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('fanart_topic_id', ?) 
                                  ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$topic_id, $topic_id]);
            
            // 保存i0显示状态
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('fanart_show_i0', ?) 
                                  ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$show_i0, $show_i0]);
            
            // 保存i1显示状态
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('fanart_show_i1', ?) 
                                  ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$show_i1, $show_i1]);
            
            // 保存缓存时间
            $stmt = $conn->prepare("INSERT INTO site_config (config_key, config_value) VALUES ('fanart_cache_time', ?) 
                                  ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$cache_time, $cache_time]);
            
            // 如果过滤模式变更，更新JavaScript配置
            $js_file_path = __DIR__ . '/../assets/fanart/cluewall.js';
            if (file_exists($js_file_path)) {
                $js_content = file_get_contents($js_file_path);
                
                // 根据选择的过滤模式更新JavaScript代码
                if ($show_i0 == '1' && $show_i1 == '1') {
                    // 显示所有hdslb.com图片
                    $js_content = preg_replace('/\/\/ 检查是否包含.*?图片的卡片/', '// 检查是否包含hdslb.com的图片（包括所有子域名）', $js_content);
                    $js_content = preg_replace('/hasHdslbImage = content\.major\.opus\.pics\.some\(pic => pic\.url && pic\.url\.includes\([\'"]i0\.hdslb\.com[\'"]\)\);/', 
                                              'hasHdslbImage = content.major.opus.pics.some(pic => pic.url && pic.url.includes(\'.hdslb.com\'));', $js_content);
                    $js_content = preg_replace('/hasHdslbImage = content\.major\.draw\.pics\.some\(pic => pic\.url && pic\.url\.includes\([\'"]i0\.hdslb\.com[\'"]\)\);/', 
                                              'hasHdslbImage = content.major.draw.pics.some(pic => pic.url && pic.url.includes(\'.hdslb.com\'));', $js_content);
                    $js_content = preg_replace('/hasHdslbImage = content\.major\.opus\.pics\.some\(pic => pic\.url && pic\.url\.includes\([\'"]i1\.hdslb\.com[\'"]\)\);/', 
                                              'hasHdslbImage = content.major.opus.pics.some(pic => pic.url && pic.url.includes(\'.hdslb.com\'));', $js_content);
                    $js_content = preg_replace('/hasHdslbImage = content\.major\.draw\.pics\.some\(pic => pic\.url && pic\.url\.includes\([\'"]i1\.hdslb\.com[\'"]\)\);/', 
                                              'hasHdslbImage = content.major.draw.pics.some(pic => pic.url && pic.url.includes(\'.hdslb.com\'));', $js_content);
                    $js_content = preg_replace('/if \(cover\.src && cover\.src\.includes\([\'"]i0\.hdslb\.com[\'"]\)\)/', 
                                              'if (cover.src && cover.src.includes(\'.hdslb.com\'))', $js_content);
                    $js_content = preg_replace('/if \(cover\.src && cover\.src\.includes\([\'"]i1\.hdslb\.com[\'"]\)\)/', 
                                              'if (cover.src && cover.src.includes(\'.hdslb.com\'))', $js_content);
                    // 更新日志消息
                    $js_content = preg_replace('/console\.log\([\'"]跳过非i0\.hdslb\.com图片的卡片[\'"]\);/', 
                                              'console.log(\'跳过非hdslb.com图片的卡片\');', $js_content);
                    $js_content = preg_replace('/console\.log\([\'"]跳过非i1\.hdslb\.com图片的卡片[\'"]\);/', 
                                              'console.log(\'跳过非hdslb.com图片的卡片\');', $js_content);
                } else {
                    // 创建过滤条件
                    $filterConditions = [];
                    $logMessage = '跳过非';
                    
                    if ($show_i0 == '1') {
                        $filterConditions[] = 'pic.url && pic.url.includes(\'i0.hdslb.com\')';
                        $filterConditions[] = 'cover.src && cover.src.includes(\'i0.hdslb.com\')';
                        $logMessage .= 'i0';
                    }
                    
                    if ($show_i1 == '1') {
                        if (!empty($filterConditions)) {
                            $filterConditions[0] = '(' . $filterConditions[0] . ' || pic.url && pic.url.includes(\'i1.hdslb.com\'))';
                            $filterConditions[1] = '(' . $filterConditions[1] . ' || cover.src && cover.src.includes(\'i1.hdslb.com\'))';
                            $logMessage .= '/i1';
                        } else {
                            $filterConditions[] = 'pic.url && pic.url.includes(\'i1.hdslb.com\')';
                            $filterConditions[] = 'cover.src && cover.src.includes(\'i1.hdslb.com\')';
                            $logMessage .= 'i1';
                        }
                    }
                    
                    $logMessage .= '.hdslb.com图片的卡片';
                    
                    // 如果没有选择任何过滤条件，默认显示所有
                    if (empty($filterConditions)) {
                        $filterConditions[] = 'pic.url && pic.url.includes(\'.hdslb.com\')';
                        $filterConditions[] = 'cover.src && cover.src.includes(\'.hdslb.com\')';
                        $logMessage = '跳过非hdslb.com图片的卡片';
                    }
                    
                    // 更新opus类型图片检查
                    $js_content = preg_replace('/hasHdslbImage = content\.major\.opus\.pics\.some\(pic => .*?\);/', 
                                              'hasHdslbImage = content.major.opus.pics.some(pic => ' . $filterConditions[0] . ');', $js_content);
                    
                    // 更新draw类型图片检查
                    $js_content = preg_replace('/hasHdslbImage = content\.major\.draw\.pics\.some\(pic => .*?\);/', 
                                              'hasHdslbImage = content.major.draw.pics.some(pic => ' . $filterConditions[0] . ');', $js_content);
                    
                    // 更新common封面检查
                    $js_content = preg_replace('/if \(cover\.src && cover\.src\.includes\(.*?\)\)/', 
                                              'if (' . $filterConditions[1] . ')', $js_content);
                    
                    // 更新日志消息
                    $js_content = preg_replace('/console\.log\([\'"]跳过非.*?图片的卡片[\'"]\);/', 
                                              'console.log(\'' . $logMessage . '\');', $js_content);
                }
                
                // 写入文件
                file_put_contents($js_file_path, $js_content);
            }
            
            // 更新API文件中的缓存时间
            $api_file_path = __DIR__ . '/../api/fanart_api.php';
            if (file_exists($api_file_path)) {
                $api_content = file_get_contents($api_file_path);
                
                // 更新缓存过期时间
                $api_content = preg_replace('/\$cache_expired = !\$cache_file \|\| \(time\(\) - filemtime\(\$cache_file\) > \d+\);/', 
                                           '$cache_expired = !$cache_file || (time() - filemtime($cache_file) > ' . $cache_time . ');', 
                                           $api_content);
                
                // 更新话题ID
                $api_content = preg_replace('/\$api_url = \'https:\/\/api\.bilibili\.com\/x\/polymer\/web-dynamic\/v1\/feed\/topic\?topic_id=\d+&sort_by=3&offset=&page_size=24\';/', 
                                           '$api_url = \'https://api.bilibili.com/x/polymer/web-dynamic/v1/feed/topic?topic_id=' . $topic_id . '&sort_by=3&offset=&page_size=24\';', 
                                           $api_content);
                
                // 写入文件
                file_put_contents($api_file_path, $api_content);
            }
            
            // 清除缓存
            $cache_dir = __DIR__ . '/../api/api_cache/fanart_api';
            if (is_dir($cache_dir)) {
                $files = glob($cache_dir . '/*.json');
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            
            showToast('同人图墙配置已更新！');
        }
        
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 获取当前直播间信息
$biliLive = new BilibiliLive($roomId);
$isLiving = $biliLive->isLiving();
$title = $biliLive->getTitle();
$online = $biliLive->getOnline();
$areaName = $biliLive->getAreaName();
$keyframe = $biliLive->getKeyframe();
$cover = $biliLive->getCover();

// 获取当前配置
$stmt = $conn->prepare("SELECT config_key, config_value FROM site_config WHERE config_key IN ('bilibili_mid', 'show_pinned')");
$stmt->execute();
$configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$current_mid = $configs['bilibili_mid'] ?? '';
$show_pinned = isset($configs['show_pinned']) ? (bool)$configs['show_pinned'] : true;

// 设置页面标题
$page_title = "B站管理";

// 设置页面特定样式
$extra_styles = '
/* Nagisa主题样式 */
.nagisa-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(204, 148, 113, 0.1);
    overflow: hidden;
    border: 1px solid rgba(204, 148, 113, 0.2);
    transition: all 0.3s ease;
    margin-bottom: 20px;
}

.nagisa-card:hover {
    box-shadow: 0 6px 20px rgba(204, 148, 113, 0.2);
    transform: translateY(-2px);
}

.nagisa-card-header {
    background: linear-gradient(45deg, #cc9471, #f3b4a4);
    color: white;
    padding: 15px 20px;
    font-weight: 600;
    font-size: 1.1rem;
    border-bottom: 1px solid rgba(204, 148, 113, 0.2);
}

.nagisa-input, .nagisa-textarea {
    border: 2px solid rgba(204, 148, 113, 0.3);
    transition: all 0.3s ease;
    width: 100%;
    padding: 8px 12px;
    border-radius: 8px;
}

.nagisa-input:focus, .nagisa-textarea:focus {
    border-color: #cc9471;
    box-shadow: 0 0 0 3px rgba(204, 148, 113, 0.2);
    outline: none;
}

.nagisa-btn {
    background: linear-gradient(45deg, #cc9471, #f3b4a4);
    border: none;
    color: white;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(204, 148, 113, 0.2);
    cursor: pointer;
}

.nagisa-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(204, 148, 113, 0.3);
    background: linear-gradient(45deg, #d49c78, #f8c1b1);
}

.nagisa-btn-secondary {
    background: #e2e8f0;
    color: #64748b;
    border: none;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    cursor: pointer;
}

.nagisa-btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    background: #f1f5f9;
}

.nagisa-section-title {
    color: #cc9471;
    font-weight: 600;
    padding-bottom: 8px;
    border-bottom: 2px solid rgba(204, 148, 113, 0.2);
    margin-bottom: 16px;
}

.nagisa-form-group {
    margin-bottom: 20px;
}

.nagisa-label {
    display: block;
    font-weight: 500;
    color: #704c38;
    margin-bottom: 8px;
}

.nagisa-nav-link {
    display: block;
    padding: 10px 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
    margin-bottom: 4px;
    font-weight: 500;
    text-decoration: none;
    color: #64748b;
}

.nagisa-nav-link.active {
    background: rgba(204, 148, 113, 0.1);
    color: #cc9471;
    border-left: 3px solid #cc9471;
}

.nagisa-nav-link:hover {
    background: rgba(204, 148, 113, 0.05);
    color: #cc9471;
}

.avatar-preview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto;
    border: 3px solid rgba(204, 148, 113, 0.3);
    box-shadow: 0 4px 10px rgba(204, 148, 113, 0.2);
    transition: all 0.3s;
}

.avatar-preview:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 15px rgba(204, 148, 113, 0.3);
}

.avatar-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.file-upload-container {
    position: relative;
    overflow: hidden;
    display: inline-block;
    width: 100%;
}

.file-upload-container input[type=file] {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.live-status-badge {
    border-radius: 20px;
    padding: 6px 12px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-block;
}

.live-status-on {
    background: linear-gradient(45deg, #6ed69a, #41c7af);
    color: white;
}

.live-status-off {
    background: linear-gradient(45deg, #d1d1d1, #a1a1a1);
    color: white;
}

.live-preview {
    border-radius: 8px;
    overflow: hidden;
    margin-top: 16px;
    border: 2px solid rgba(204, 148, 113, 0.2);
}

.live-preview-img {
    width: 100%;
    height: auto;
    display: block;
}

.live-info-item {
    padding: 8px 0;
    border-bottom: 1px solid rgba(204, 148, 113, 0.1);
}

.live-info-item:last-child {
    border-bottom: none;
}

.live-info-label {
    color: #704c38;
    font-size: 0.9rem;
    margin-bottom: 4px;
}

.live-info-value {
    font-weight: 500;
    color: #333;
    word-break: break-word;
}

.api-code-block {
    background: rgba(204, 148, 113, 0.05);
    border: 1px solid rgba(204, 148, 113, 0.2);
    border-radius: 8px;
    padding: 12px;
    font-family: monospace;
    font-size: 0.9rem;
    overflow-x: auto;
    color: #704c38;
}

.nagisa-preview-container {
    background: rgba(204, 148, 113, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(204, 148, 113, 0.2);
    padding: 16px;
    margin-top: 20px;
}

.nagisa-preview-title {
    color: #cc9471;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 10px;
}

.nagisa-preview-display {
    background: white;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid rgba(204, 148, 113, 0.1);
    min-height: 60px;
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.nagisa-preview-display.dynamic-preview-display {
    display: block;
    align-items: stretch;
    width: 100%;
    max-height: min(70vh, 720px);
    overflow-y: auto;
    overflow-x: hidden;
    padding: 12px;
    box-sizing: border-box;
}

.dynamic-preview-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
}

.dynamic-preview-item {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid rgba(204, 148, 113, 0.18);
    border-radius: 10px;
    padding: 12px 14px;
    background: #faf8f6;
}

.dynamic-preview-link {
    display: block;
    text-decoration: none;
    color: inherit;
}

.dynamic-preview-text {
    font-size: 14px;
    line-height: 1.65;
    color: #4a4035;
    word-break: break-word;
    white-space: pre-line;
    margin-bottom: 8px;
}

.dynamic-preview-text .bili-emote {
    display: inline-block;
    vertical-align: middle;
    max-height: 40px;
    max-width: 120px;
    margin: 0 2px;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
}

.dynamic-preview-images {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 8px;
    margin-bottom: 8px;
    width: 100%;
}

.dynamic-preview-images img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 8px;
}

.dynamic-preview-more {
    aspect-ratio: 1;
    border-radius: 8px;
    background: rgba(204, 148, 113, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #704c38;
}

.dynamic-preview-video {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    background: rgba(204, 148, 113, 0.08);
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 8px;
    width: 100%;
    box-sizing: border-box;
}

.dynamic-preview-video-cover {
    width: clamp(100px, 32%, 168px);
    flex-shrink: 0;
    aspect-ratio: 16 / 10;
    object-fit: cover;
    border-radius: 6px;
}

.dynamic-preview-video-info {
    flex: 1;
    min-width: 0;
}

.dynamic-preview-video-title {
    font-size: 14px;
    font-weight: 600;
    color: #4a4035;
    line-height: 1.45;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.dynamic-preview-video-meta {
    font-size: 12px;
    color: #8a7a6a;
    margin-top: 4px;
}

.dynamic-preview-card {
    background: rgba(204, 148, 113, 0.08);
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 8px;
    width: 100%;
    box-sizing: border-box;
}

.dynamic-preview-card-title {
    font-size: 14px;
    font-weight: 600;
    color: #4a4035;
    margin-bottom: 4px;
}

.dynamic-preview-card-desc {
    font-size: 12px;
    color: #8a7a6a;
    margin-bottom: 8px;
    word-break: break-word;
}

.dynamic-preview-card-images {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(64px, 1fr));
    gap: 8px;
    width: 100%;
}

.dynamic-preview-card-images img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 6px;
}

.dynamic-preview-time {
    font-size: 12px;
    color: #b0a090;
    margin-top: 6px;
}

.dynamic-preview-pinned {
    display: inline-block;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 999px;
    background: linear-gradient(135deg, #fb7299, #ff9eb5);
    color: #fff;
    margin-bottom: 6px;
}

.dynamic-preview-empty {
    text-align: center;
    color: #8a7a6a;
    padding: 24px 12px;
}

@media (max-width: 640px) {
    .dynamic-preview-video {
        flex-direction: column;
    }

    .dynamic-preview-video-cover {
        width: 100%;
    }
}

.bili-marquee-box { position: relative; overflow: hidden; max-width: 100%; }
.bili-marquee-content {
    display: inline-block;
    white-space: nowrap;
    animation: bili-marquee-scroll 8s linear infinite;
}
@keyframes bili-marquee-scroll {
    0% { transform: translateX(0); }
    90% { transform: translateX(calc(-100% + 100%)); }
    100% { transform: translateX(0); }
}

/* 加载动画 */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-content {
    text-align: center;
}

.loading-spinner {
    border: 4px solid rgba(204, 148, 113, 0.2);
    border-top: 4px solid #cc9471;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 成功提示样式 */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast {
    padding: 12px 20px;
    margin-bottom: 10px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    min-width: 250px;
    max-width: 350px;
    transform: translateX(120%);
    transition: transform 0.3s ease;
}

.toast.show {
    transform: translateX(0);
}

.toast-success {
    background-color: #d1e7dd;
    border-left: 4px solid #198754;
    color: #0f5132;
}

.toast-error {
    background-color: #f8d7da;
    border-left: 4px solid #dc3545;
    color: #842029;
}

.toast-info {
    background-color: #cff4fc;
    border-left: 4px solid #0dcaf0;
    color: #055160;
}

.toast-icon {
    margin-right: 12px;
    font-size: 1.2rem;
}

.toast-message {
    flex: 1;
}

.toast-close {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    opacity: 0.7;
    font-size: 1.2rem;
    padding: 0;
    margin-left: 8px;
}

.toast-close:hover {
    opacity: 1;
}
';

// 包含统一页眉
include 'admin_header.php';
?>

<!-- 使用原生拖拽API，不需要外部库 -->



<!-- 加载动画 -->
<div id="loading-overlay" class="loading-overlay" style="display: none;">
    <div class="loading-content">
        <div class="loading-spinner"></div>
        <p class="text-gray-700">正在处理，请稍候...</p>
    </div>
</div>

<!-- 成功提示容器 -->
<div id="toast-container" class="toast-container"></div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
        <!-- 侧边导航 -->
        <div class="md:col-span-3">
            <div class="nagisa-card">
                <h2 class="nagisa-card-header">B站功能</h2>
                <div class="p-4">
                    <ul class="space-y-1">
                        <li>
                            <a href="?section=live" class="nagisa-nav-link" onclick="showSection('live'); return false;">
                                直播检测
                            </a>
                        </li>
                        <li>
                            <a href="?section=profile" class="nagisa-nav-link" onclick="showSection('profile'); return false;">
                                主播信息
                            </a>
                        </li>
                        <li>
                            <a href="?section=space" class="nagisa-nav-link" onclick="showSection('space'); return false;">
                                视频管理
                            </a>
                        </li>
                        <li>
                            <a href="?section=dynamic" class="nagisa-nav-link" onclick="showSection('dynamic'); return false;">
                                动态管理
                            </a>
                        </li>
                        <li>
                            <a href="?section=fanart" class="nagisa-nav-link" onclick="showSection('fanart'); return false;">
                                同人图墙
                            </a>
                        </li>
                        <li>
                            <a href="?section=cookie" class="nagisa-nav-link" onclick="showSection('cookie'); return false;">
                                Cookie管理
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="nagisa-card">
                <h2 class="nagisa-card-header">使用说明</h2>
                <div class="p-4">
                    <ul class="space-y-2 text-gray-600 text-sm">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>直播检测：配置直播间ID和查看直播状态</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>主播信息：管理头像、用户名和简介</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>视频管理：配置B站空间视频展示</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>动态管理：配置B站动态展示和缓存管理</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>同人图墙：配置B站同人图墙展示</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>Cookie管理：配置B站Cookie和访问凭证</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- 主要内容区域 -->
        <div class="md:col-span-9">
            <!-- 直播检测部分 -->
            <div id="live" class="nagisa-card section-content">
                <h2 class="nagisa-card-header">直播检测管理</h2>
                <div class="p-6">
                    <p class="mb-6 text-gray-600">
                        配置B站直播间ID并查看当前直播状态。
                    </p>
                    
                    <form method="POST">
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">B站直播间ID</label>
                            <input type="text" 
                                   name="room_id" 
                                   value="<?php echo htmlspecialchars($roomId); ?>"
                                   class="nagisa-input">
                            <p class="mt-1 text-sm text-gray-500">
                                直播间ID可以从B站直播URL中获取，例如：https://live.bilibili.com/31368705 中的数字部分
                            </p>
                        </div>
                        
                        <button type="submit" name="update_room" class="nagisa-btn">
                            <i class="fas fa-save mr-2"></i>保存配置
                        </button>
                    </form>
                    
                    <!-- 直播状态预览 -->
                    <div class="nagisa-preview-container">
                        <h3 class="nagisa-preview-title">直播状态</h3>
                        <div class="nagisa-preview-display">
                            <div class="w-full">
                                <div class="live-info-item">
                                    <div class="live-info-label">直播状态</div>
                                    <div class="live-info-value">
                                        <span class="live-status-badge <?php echo $isLiving ? 'live-status-on' : 'live-status-off'; ?>">
                                            <?php echo $isLiving ? '直播中' : '未开播'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($title): ?>
                                <div class="live-info-item">
                                    <div class="live-info-label">直播标题</div>
                                    <div class="live-info-value"><?php echo htmlspecialchars($title); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($online): ?>
                                <div class="live-info-item">
                                    <div class="live-info-label">在线人数</div>
                                    <div class="live-info-value"><?php echo number_format($online); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($areaName): ?>
                                <div class="live-info-item">
                                    <div class="live-info-label">分区</div>
                                    <div class="live-info-value"><?php echo htmlspecialchars($areaName); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($cover): ?>
                                <div class="live-preview">
                                    <img data-src="<?php echo getBiliImageProxyUrl($cover); ?>" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24' fill='%23cccccc'%3E%3Cpath d='M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2zm0 2v14h14V5H5zm11.5 9c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5.67-1.5 1.5-1.5 1.5.67 1.5 1.5zm-8-3h5V9h-5v2zm0-3h8V6h-8v2z'/%3E%3C/svg%3E" alt="直播封面" class="live-preview-img" referrerpolicy="no-referrer">
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 主播信息部分 -->
            <div id="profile" class="nagisa-card section-content" style="display: none;">
                <h2 class="nagisa-card-header">主播信息管理</h2>
                <div class="p-6">
                    <p class="mb-6 text-gray-600">
                        管理主播的头像、用户名和简介信息。
                    </p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <!-- 头像管理 -->
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">主播头像</label>
                            <div class="text-center mb-4">
                                <div class="avatar-preview">
                                    <img data-src="<?php echo getBiliImageProxyUrl($avatar); ?>" 
                                         src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24' fill='%23cccccc'%3E%3Cpath d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z'/%3E%3C/svg%3E" 
                                         alt="主播头像" 
                                         referrerpolicy="no-referrer"
                                         onerror="this.src='/assets/images/default-avatar.png'">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">上传新头像</label>
                                <div class="file-upload-container">
                                    <input type="file" name="avatar_file" accept="image/*" class="nagisa-input">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">支持 JPG、JPEG、PNG、GIF 格式，建议尺寸 200x200 像素</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">或输入头像URL</label>
                                <input type="url" name="avatar" value="<?php echo htmlspecialchars($avatar); ?>" 
                                       class="nagisa-input" placeholder="https://example.com/avatar.jpg">
                            </div>
                        </div>
                        
                        <!-- 用户名 -->
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">主播用户名</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" 
                                   class="nagisa-input" placeholder="输入主播用户名">
                        </div>
                        
                        <!-- 简介 -->
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">主播简介</label>
                            <textarea name="description" rows="4" class="nagisa-textarea" 
                                      placeholder="输入主播简介"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                        
                        <!-- 粉丝数显示 -->
                        <?php if ($followers > 0): ?>
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">粉丝数</label>
                            <div class="text-lg font-semibold text-gray-700">
                                <?php echo number_format($followers); ?>
                                <span class="text-sm text-gray-500 ml-2">（自动同步）</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" name="update_profile" class="nagisa-btn">
                            <i class="fas fa-save mr-2"></i>保存信息
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- B站视频管理部分 -->
            <div id="space" class="nagisa-card section-content" style="display: none;">
                <h2 class="nagisa-card-header">B站空间视频管理</h2>
                <div class="p-6">
                    <p class="mb-6 text-gray-600">
                        配置B站空间视频展示功能。
                    </p>
                    
                    <form method="POST">
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">B站用户UID</label>
                            <input type="text" 
                                   name="bilibili_uid" 
                                   value="<?php echo htmlspecialchars($current_uid); ?>"
                                   class="nagisa-input" 
                                   placeholder="输入B站用户UID">
                            <p class="mt-1 text-sm text-gray-500">
                                输入需要展示的B站用户的UID（数字ID），可以从用户空间URL中获取
                            </p>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">显示视频组件</label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="show_spacevideos" <?php echo $show_spacevideos ? 'checked' : ''; ?> class="w-4 h-4 text-cc9471 border-gray-300 rounded focus:ring-cc9471">
                                    <span class="ml-2 text-gray-700">在首页显示主播作品视频卡片组件</span>
                                </label>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                取消勾选将在首页隐藏主播作品视频组件
                            </p>
                        </div>
                        
                        <button type="submit" name="update_space" class="nagisa-btn">
                            <i class="fas fa-save mr-2"></i>保存配置
                        </button>
                    </form>
                    
                    <!-- 视频预览 -->
                    <?php if (!empty($current_uid)): ?>
                    <div class="nagisa-preview-container">
                        <h3 class="nagisa-preview-title">视频预览</h3>
                        <div class="nagisa-preview-display">
                            <?php
                            // 使用PHP获取视频列表（JSON方式）
                            $ch = curl_init();
                            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                            $url = $base_url . "/api/bili_spacevideos_api.php?mid=" . urlencode($current_uid) . "&action=videos&page=1&page_size=12";
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'X-BiliCookie: ' . $current_cookie,
                                'Accept: application/json'
                            ]);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                            
                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $error = curl_error($ch);
                            curl_close($ch);
                            
                            if ($response) {
                                $data = json_decode($response, true);
                                if ($data && $data['code'] === 0 && isset($data['data']['list']['vlist']) && is_array($data['data']['list']['vlist'])) {
                                    $videos = $data['data']['list']['vlist'];
                                    if (count($videos) > 0) {
                                        // 在视频卡片输出前插入一次性样式（只插入一次）
                                        static $bili_marquee_style = false;
                                        if (!$bili_marquee_style) {
                                            echo '<style>
                                            .bili-marquee-box { position: relative; overflow: hidden; max-width: 100%; }
                                            .bili-marquee-content {
                                                display: inline-block;
                                                white-space: nowrap;
                                                animation: bili-marquee-scroll 8s linear infinite;
                                            }
                                            @keyframes bili-marquee-scroll {
                                                0% { transform: translateX(0); }
                                                90% { transform: translateX(calc(-100% + 100%)); }
                                                100% { transform: translateX(0); }
                                            }
                                            </style>';
                                            $bili_marquee_style = true;
                                        }
                                        echo '<div style="display:flex;flex-direction:column;gap:18px;width:100%;">';
                                        foreach ($videos as $video) {
                                            echo '<div style="width:100%;max-width:480px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(204,148,113,0.10);padding:10px 10px 16px 10px;display:flex;flex-direction:row;align-items:center;">';
                                            echo '<a href="https://www.bilibili.com/video/' . htmlspecialchars($video['bvid']) . '" target="_blank" style="display:flex;align-items:center;text-decoration:none;width:100%;">';
                                            $videoPic = strpos($video['pic'], '//') === 0 ? 'https:' . $video['pic'] : $video['pic'];
                                            echo '<img src="' . htmlspecialchars($videoPic) . '" alt="cover" referrerpolicy="no-referrer" style="width:110px;height:70px;object-fit:cover;border-radius:8px;flex-shrink:0;">';
                                            echo '<div style="margin-left:16px;flex:1;min-width:0;">';
                                            echo '<div class="bili-marquee-box" style="font-weight:bold;font-size:1rem;color:#222;margin-bottom:6px;max-width:100%;">'
                                                . '<span class="bili-marquee-content">' . htmlspecialchars($video['title']) . '</span>'
                                                . '</div>';
                                            echo '<div class="bili-marquee-box" style="font-size:0.95rem;color:#4c526b;max-width:100%;">'
                                                . '<span class="bili-marquee-content">' . number_format($video['play']) . '播放 · ' . number_format($video['video_review']) . '弹幕</span>'
                                                . '</div>';
                                            echo '</div>';
                                            echo '</a>';
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<div class="text-gray-500">暂无视频数据</div>';
                                    }
                                } else {
                                    echo '<div class="text-red-500">API返回数据异常</div>';
                                }
                            } else {
                                echo '<div class="text-red-500">';
                                echo '<p>加载失败</p>';
                                echo '<p>HTTP状态码: ' . $httpCode . '</p>';
                                if ($error) {
                                    echo '<p>CURL错误: ' . htmlspecialchars($error) . '</p>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="nagisa-preview-container">
                        <h3 class="nagisa-preview-title">视频预览</h3>
                        <div class="nagisa-preview-display">
                            <p class="text-gray-500">请先设置B站用户UID</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- B站动态管理部分 -->
            <div id="dynamic" class="nagisa-card section-content" style="display: none;">
                <h2 class="nagisa-card-header">B站动态管理</h2>
                <div class="p-6">
                    <p class="mb-6 text-gray-600">
                        配置B站动态展示功能和缓存管理。
                    </p>
                    
                    <form method="POST">
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">B站用户ID (UID)</label>
                            <input type="text" 
                                   name="bilibili_mid" 
                                   value="<?php echo htmlspecialchars($current_mid); ?>"
                                   class="nagisa-input" 
                                   placeholder="输入B站用户ID">
                            <p class="mt-1 text-sm text-gray-500">
                                输入需要展示动态的B站用户ID（数字ID），可以从用户空间URL中获取
                            </p>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">动态功能状态</label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="radio" 
                                           name="dynamic_enabled" 
                                           value="1" 
                                           <?php echo $dynamic_enabled == '1' ? 'checked' : ''; ?>
                                           class="mr-2">
                                    <span class="text-sm">启用</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" 
                                           name="dynamic_enabled" 
                                           value="0" 
                                           <?php echo $dynamic_enabled == '0' ? 'checked' : ''; ?>
                                           class="mr-2">
                                    <span class="text-sm">禁用</span>
                                </label>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                控制是否在前台显示动态组件
                            </p>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">显示置顶动态</label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="show_pinned" <?php echo $show_pinned ? 'checked' : ''; ?>>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_dynamic" class="nagisa-btn">
                            <i class="fas fa-save mr-2"></i>保存配置
                        </button>
                    </form>
                    
                    <!-- 动态预览 -->
                    <?php if (!empty($current_mid) && $dynamic_enabled == '1'): ?>
                    <div class="nagisa-preview-container">
                        <h3 class="nagisa-preview-title">动态预览</h3>
                        <div class="nagisa-preview-display dynamic-preview-display">
                            <?php
                            // 使用PHP获取动态列表
                            $ch = curl_init();
                            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                            $timestamp = time();
                            $url = $base_url . "/api/dynamic_api.php?action=get_dynamics&mid=" . urlencode($current_mid) . "&page=1&page_size=" . BilibiliDynamic::DISPLAY_COUNT . "&t=" . $timestamp . "&force=1";
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                            
                            $response = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $error = curl_error($ch);
                            curl_close($ch);
                            
                            if ($response) {
                                $data = json_decode($response, true);
                                if ($data && $data['code'] === 0 && !empty($data['data']['dynamics'])) {
                                    $preview_dynamics = $data['data']['dynamics'];
                                    echo '<div class="dynamic-preview-list">';
                                    foreach ($preview_dynamics as $dynamic) {
                                        $jump_url = '';
                                        if (!empty($dynamic['video']['bvid'])) {
                                            $jump_url = 'https://www.bilibili.com/video/' . htmlspecialchars($dynamic['video']['bvid']);
                                        } elseif (!empty($dynamic['id'])) {
                                            $jump_url = 'https://t.bilibili.com/' . htmlspecialchars($dynamic['id']);
                                        }

                                        echo '<article class="dynamic-preview-item">';
                                        if (!empty($dynamic['is_pinned'])) {
                                            echo '<span class="dynamic-preview-pinned">置顶</span>';
                                        }
                                        if ($jump_url) {
                                            echo '<a href="' . $jump_url . '" target="_blank" rel="noopener" class="dynamic-preview-link">';
                                        }

                                        if (!empty($dynamic['content'])) {
                                            echo '<div class="dynamic-preview-text">' . html_entity_decode($dynamic['content']) . '</div>';
                                        }

                                        if (!empty($dynamic['images'])) {
                                            echo '<div class="dynamic-preview-images">';
                                            foreach (array_slice($dynamic['images'], 0, 9) as $image) {
                                                echo '<img src="' . getBiliImageProxyUrl($image) . '" alt="动态图片" referrerpolicy="no-referrer" loading="lazy">';
                                            }
                                            if (count($dynamic['images']) > 9) {
                                                echo '<div class="dynamic-preview-more">+' . (count($dynamic['images']) - 9) . '</div>';
                                            }
                                            echo '</div>';
                                        }

                                        if (!empty($dynamic['video'])) {
                                            echo '<div class="dynamic-preview-video">';
                                            echo '<img src="' . getBiliImageProxyUrl($dynamic['video']['cover']) . '" alt="视频封面" class="dynamic-preview-video-cover" referrerpolicy="no-referrer" loading="lazy">';
                                            echo '<div class="dynamic-preview-video-info">';
                                            echo '<div class="dynamic-preview-video-title">' . html_entity_decode($dynamic['video']['title']) . '</div>';
                                            echo '<div class="dynamic-preview-video-meta">';
                                            echo (!empty($dynamic['video']['play']) ? htmlspecialchars($dynamic['video']['play']) : (isset($dynamic['video']['view']) ? (is_numeric($dynamic['video']['view']) ? number_format($dynamic['video']['view']) : htmlspecialchars($dynamic['video']['view'])) : ''));
                                            echo '播放 · ';
                                            echo (isset($dynamic['video']['danmaku']) ? htmlspecialchars($dynamic['video']['danmaku']) : '0');
                                            echo '弹幕 · ';
                                            echo htmlspecialchars($dynamic['video']['duration'] ?? '');
                                            echo '</div></div></div>';
                                        }

                                        if (!empty($dynamic['card'])) {
                                            echo '<div class="dynamic-preview-card">';
                                            if (!empty($dynamic['card']['title'])) {
                                                echo '<div class="dynamic-preview-card-title">' . html_entity_decode($dynamic['card']['title']) . '</div>';
                                            }
                                            if (!empty($dynamic['card']['desc'])) {
                                                echo '<div class="dynamic-preview-card-desc">' . html_entity_decode($dynamic['card']['desc']) . '</div>';
                                            }
                                            if (!empty($dynamic['card']['pics'])) {
                                                echo '<div class="dynamic-preview-card-images">';
                                                foreach (array_slice($dynamic['card']['pics'], 0, 6) as $image) {
                                                    echo '<img src="' . getBiliImageProxyUrl($image) . '" alt="卡片图片" referrerpolicy="no-referrer" loading="lazy">';
                                                }
                                                echo '</div>';
                                            }
                                            echo '</div>';
                                        }

                                        if ($jump_url) {
                                            echo '</a>';
                                        }

                                        if (!empty($dynamic['time'])) {
                                            echo '<div class="dynamic-preview-time">' . htmlspecialchars($dynamic['time']) . '</div>';
                                        }
                                        echo '</article>';
                                    }
                                    echo '</div>';
                                } else {
                                    echo '<div class="dynamic-preview-empty">暂无动态数据</div>';
                                }
                            } else {
                                echo '<div class="dynamic-preview-empty" style="color:#b45454;">';
                                echo '<p>加载失败</p>';
                                echo '<p>HTTP状态码: ' . (int)$httpCode . '</p>';
                                if ($error) {
                                    echo '<p>CURL错误: ' . htmlspecialchars($error) . '</p>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="nagisa-preview-container">
                        <h3 class="nagisa-preview-title">动态预览</h3>
                        <div class="nagisa-preview-display">
                            <p class="text-gray-500">
                                <?php if (empty($current_mid)): ?>
                                    请先设置B站用户ID
                                <?php else: ?>
                                    动态功能已禁用
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 缓存管理 -->
                    <div class="nagisa-preview-container">
                        <h3 class="nagisa-preview-title">缓存管理</h3>
                        <div class="p-4">
                            <p class="text-sm text-gray-600 mb-4">
                                动态数据会缓存5分钟以提高加载速度，如需立即更新可手动清除缓存。
                            </p>
                            <button type="button" onclick="clearDynamicCache()" class="nagisa-btn bg-orange-500 hover:bg-orange-600">
                                <i class="fas fa-trash mr-2"></i>清除动态缓存
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 同人图墙管理部分 -->
            <div id="fanart" class="nagisa-card section-content" style="display: none;">
                <h2 class="nagisa-card-header">同人图墙管理</h2>
                <div class="p-6">
                    <p class="mb-6 text-gray-600">
                        配置B站同人图墙展示功能、过滤规则和缓存管理。
                    </p>
                    
                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        <input type="hidden" name="action" value="update_fanart">
                        
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">同人图墙状态</label>
                            <div class="flex items-center space-x-4">
                                <?php
                                // 获取同人图墙启用状态
                                $fanart_enabled = '1'; // 默认启用
                                try {
                                    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'fanart_enabled'");
                                    $stmt->execute();
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($result) {
                                        $fanart_enabled = $result['config_value'];
                                    }
                                } catch (PDOException $e) {
                                    // 出错时使用默认值
                                }
                                ?>
                                <label class="flex items-center">
                                    <input type="radio" 
                                           name="fanart_enabled" 
                                           value="1" 
                                           <?php echo $fanart_enabled == '1' ? 'checked' : ''; ?>
                                           class="mr-2">
                                    <span class="text-sm">启用</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" 
                                           name="fanart_enabled" 
                                           value="0" 
                                           <?php echo $fanart_enabled == '0' ? 'checked' : ''; ?>
                                           class="mr-2">
                                    <span class="text-sm">禁用</span>
                                </label>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                控制是否在前台显示同人图墙组件
                            </p>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">B站话题ID</label>
                            <?php
                            // 获取B站话题ID
                            $topic_id = '1134905'; // 默认值
                            try {
                                $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'fanart_topic_id'");
                                $stmt->execute();
                                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($result) {
                                    $topic_id = $result['config_value'];
                                }
                            } catch (PDOException $e) {
                                // 出错时使用默认值
                            }
                            ?>
                            <input type="text" 
                                   name="topic_id" 
                                   value="<?php echo htmlspecialchars($topic_id); ?>"
                                   class="nagisa-input" 
                                   placeholder="输入B站话题ID">
                            <p class="mt-1 text-sm text-gray-500">
                                输入需要展示的B站话题ID，可以从话题页面URL中获取
                            </p>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">图片过滤规则</label>
                            <div class="flex flex-col space-y-2">
                                <?php
                                // 获取图片过滤规则
                                $filter_mode = 'all'; // 默认值：显示所有hdslb.com图片
                                $show_i0 = true; // 默认显示i0
                                $show_i1 = true; // 默认显示i1
                                
                                try {
                                    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'fanart_filter_mode'");
                                    $stmt->execute();
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($result) {
                                        $filter_mode = $result['config_value'];
                                    }
                                    
                                    // 获取i0显示状态
                                    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'fanart_show_i0'");
                                    $stmt->execute();
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($result !== false) {
                                        $show_i0 = (bool)$result['config_value'];
                                    }
                                    
                                    // 获取i1显示状态
                                    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'fanart_show_i1'");
                                    $stmt->execute();
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($result !== false) {
                                        $show_i1 = (bool)$result['config_value'];
                                    }
                                } catch (PDOException $e) {
                                    // 出错时使用默认值
                                }
                                ?>
                                <label class="flex items-center">
                                    <input type="checkbox" 
                                           name="show_i0" 
                                           value="1" 
                                           <?php echo $show_i0 ? 'checked' : ''; ?>
                                           class="mr-2">
                                    <span class="text-sm">显示i0.hdslb.com图片</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" 
                                           name="show_i1" 
                                           value="1" 
                                           <?php echo $show_i1 ? 'checked' : ''; ?>
                                           class="mr-2">
                                    <span class="text-sm">显示i1.hdslb.com图片</span>
                                </label>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                选择要在同人图墙中显示的图片来源，可以单独选择或同时选择多个
                            </p>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">缓存刷新时间</label>
                            <?php
                            // 获取缓存时间
                            $cache_time = '7200'; // 默认2小时
                            try {
                                $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'fanart_cache_time'");
                                $stmt->execute();
                                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($result) {
                                    $cache_time = $result['config_value'];
                                }
                            } catch (PDOException $e) {
                                // 出错时使用默认值
                            }
                            ?>
                            <div class="flex items-center">
                                <input type="number" 
                                       name="cache_time" 
                                       value="<?php echo htmlspecialchars($cache_time); ?>"
                                       min="300"
                                       step="300"
                                       class="nagisa-input w-32">
                                <span class="ml-2">秒</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                设置同人图墙数据的缓存时间，默认7200秒（2小时）
                            </p>
                        </div>
                        
                        <button type="submit" class="nagisa-btn">
                            <i class="fas fa-save mr-2"></i>保存配置
                        </button>
                    </form>
                    
                    <div class="mt-8">
                        <h3 class="nagisa-section-title">缓存管理</h3>
                        <p class="mb-4 text-sm text-gray-600">
                            同人图数据会根据上面设置的时间进行缓存以提高加载速度，如需立即更新可手动清除缓存。
                        </p>
                        <div class="flex space-x-4">
                            <button type="button" onclick="clearFanartCache()" class="nagisa-btn bg-orange-500 hover:bg-orange-600">
                                <i class="fas fa-trash mr-2"></i>清除同人图缓存
                            </button>
                            <button type="button" onclick="updateFanartCache()" class="nagisa-btn bg-green-500 hover:bg-green-600">
                                <i class="fas fa-sync-alt mr-2"></i>手动更新缓存
                            </button>
                        </div>
                        <div class="mt-4 p-3 bg-blue-50 border-l-4 border-blue-400 text-sm">
                            <p class="text-blue-700">
                                <strong>说明：</strong> 清除缓存后，下次访问同人图墙页面将会从B站API重新获取最新数据。手动更新缓存则会立即重新获取最新数据。
                            </p>
                        </div>
                    </div>
                    
                    <div class="mt-8">
                        <h3 class="nagisa-section-title">当前缓存状态</h3>
                        <div id="fanart-cache-status" class="p-4 bg-gray-50 rounded">
                            <p>正在加载缓存信息...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- B站Cookie管理部分 -->
            <div id="cookie" class="nagisa-card section-content" style="display: none;">
                <h2 class="nagisa-card-header">B站Cookie管理</h2>
                <div class="p-6">
                    <p class="mb-6 text-gray-600">
                        配置B站Cookie用于API访问，提高API的访问权限和稳定性。定期更新Cookie可以保证数据获取正常。
                    </p>
                    
                    <form method="POST">
                        <div class="nagisa-form-group">
                            <label class="nagisa-label">B站 Cookie</label>
                            <textarea name="bilibili_cookie" 
                                      rows="4" 
                                      class="nagisa-textarea" 
                                      id="bilibili-cookie-input"
                                      placeholder="粘贴你的B站登录Cookie（含SESSDATA等）"><?php echo htmlspecialchars($current_cookie); ?></textarea>
                            <p class="mt-1 text-sm text-gray-500">
                                请粘贴完整的B站登录Cookie，建议定期更新以确保API访问正常
                            </p>
                            <div class="mt-3 flex space-x-2">
                                <button type="button" id="test-cookie-btn" class="nagisa-btn-secondary">
                                    <i class="fas fa-vial mr-1"></i>测试Cookie有效性
                                </button>
                                <div id="cookie-test-result" class="hidden px-3 py-1 rounded text-sm"></div>
                            </div>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <h3 class="text-lg font-medium text-gray-700 mb-2">如何获取Cookie</h3>
                            <ol class="list-decimal list-inside text-sm text-gray-600 space-y-2">
                                <li>使用Chrome或Edge浏览器登录B站 (bilibili.com)</li>
                                <li>按F12打开开发者工具，切换到"网络/Network"选项卡</li>
                                <li>刷新页面，在网络请求列表中找到任意bilibili.com请求</li>
                                <li>点击该请求，找到"请求标头/Headers"中的"Cookie"字段</li>
                                <li>复制整个Cookie值并粘贴到上面的输入框中</li>
                            </ol>
                            <div class="mt-3 p-3 bg-yellow-50 border-l-4 border-yellow-400 text-sm">
                                <p class="font-medium text-yellow-800">安全提示</p>
                                <p class="text-yellow-700">Cookie中包含您的登录凭证，请不要分享给他人，管理员应当定期更换Cookie。</p>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_cookie" class="nagisa-btn">
                            <i class="fas fa-save mr-2"></i>保存Cookie
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 切换显示不同的部分
function showSection(sectionId) {
    // 隐藏所有部分
    const sections = document.querySelectorAll('.section-content');
    sections.forEach(section => {
        section.style.display = 'none';
    });
    
    // 显示选中的部分
    const selectedSection = document.getElementById(sectionId);
    if (selectedSection) {
        selectedSection.style.display = 'block';
    }
    
    // 更新导航链接状态
    const navLinks = document.querySelectorAll('.nagisa-nav-link');
    navLinks.forEach(link => {
        link.classList.remove('active');
    });
    
    // 激活当前链接
    const activeLink = document.querySelector(`a[href="?section=${sectionId}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
    
    // 保存当前选中的模块到本地存储
    localStorage.setItem('bilibili_admin_active_section', sectionId);
    
    // 更新URL参数（不刷新页面）
    const url = new URL(window.location);
    url.searchParams.set('section', sectionId);
    window.history.replaceState({}, '', url);
    
    // 如果是同人图墙部分，加载缓存状态
    if (sectionId === 'fanart') {
        loadFanartCacheStatus();
    }
    
    // 确保模态框隐藏
    const editCategoryModal = document.getElementById('edit-category-modal');
    if (editCategoryModal) {
        editCategoryModal.classList.add('hidden');
    }
}

// 获取当前应该显示的模块
function getCurrentSection() {
    // 首先检查URL参数
    const urlParams = new URLSearchParams(window.location.search);
    const urlSection = urlParams.get('section');
    
    // 然后检查本地存储
    const storedSection = localStorage.getItem('bilibili_admin_active_section');
    
    // 最后使用默认值
    const defaultSection = 'live';
    
    // 验证section是否有效
    const validSections = ['live', 'profile', 'space', 'dynamic', 'fanart', 'cookie'];
    const section = urlSection || storedSection || defaultSection;
    
    return validSections.includes(section) ? section : defaultSection;
}

// 清除同人图缓存
function clearFanartCache() {
    if (confirm('确定要清除同人图墙缓存吗？这将立即刷新同人图数据。')) {
        fetch('/api/fanart_api.php?action=clear_cache', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 0) {
                alert('同人图缓存清除成功！');
                // 刷新缓存状态
                loadFanartCacheStatus();
            } else {
                alert('缓存清除失败：' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('缓存清除失败，请稍后重试');
        });
    }
}

// 手动更新同人图缓存
function updateFanartCache() {
    if (confirm('确定要手动更新同人图墙缓存吗？这将立即从B站API获取最新数据。')) {
        // 显示加载中提示
        document.getElementById('fanart-cache-status').innerHTML = '<p class="text-blue-500"><i class="fas fa-spinner fa-spin mr-2"></i>正在更新缓存，请稍候...</p>';
        
        fetch('/api/fanart_api.php?action=update_cache', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 0) {
                alert('同人图缓存更新成功！');
                // 刷新缓存状态
                loadFanartCacheStatus();
            } else {
                alert('缓存更新失败：' + data.message);
                // 刷新缓存状态以显示当前情况
                loadFanartCacheStatus();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('缓存更新失败，请稍后重试');
            // 刷新缓存状态以显示当前情况
            loadFanartCacheStatus();
        });
    }
}

// 加载同人图墙缓存状态
function loadFanartCacheStatus() {
    const cacheStatusDiv = document.getElementById('fanart-cache-status');
    if (!cacheStatusDiv) return;
    
    cacheStatusDiv.innerHTML = '<p>正在加载缓存信息...</p>';
    
    fetch('/api/fanart_api.php?action=list_cache', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.code === 0 && data.data && data.data.files) {
            const files = data.data.files;
            let html = '';
            
            if (files.length > 0) {
                html += '<table class="w-full text-sm">';
                html += '<thead><tr class="bg-gray-100">';
                html += '<th class="px-4 py-2 text-left">缓存文件</th>';
                html += '<th class="px-4 py-2 text-left">创建时间</th>';
                html += '<th class="px-4 py-2 text-left">大小</th>';
                html += '</tr></thead>';
                html += '<tbody>';
                
                for (let i = 0; i < Math.min(files.length, 5); i++) {
                    const file = files[i];
                    html += '<tr class="border-t">';
                    html += `<td class="px-4 py-2">${file.filename}</td>`;
                    html += `<td class="px-4 py-2">${file.time}</td>`;
                    html += `<td class="px-4 py-2">${formatFileSize(file.size)}</td>`;
                    html += '</tr>';
                }
                
                html += '</tbody></table>';
                
                if (files.length > 5) {
                    html += `<p class="mt-2 text-xs text-gray-500">显示最新的5个缓存文件（共${files.length}个）</p>`;
                }
            } else {
                html = '<p class="text-gray-500">没有找到缓存文件，同人图数据将在下次访问时重新获取。</p>';
            }
            
            cacheStatusDiv.innerHTML = html;
        } else {
            cacheStatusDiv.innerHTML = '<p class="text-red-500">加载缓存信息失败</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        cacheStatusDiv.innerHTML = '<p class="text-red-500">加载缓存信息失败，请稍后重试</p>';
    });
}

// 格式化文件大小
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    else if (bytes < 1048576) return (bytes / 1024).toFixed(2) + ' KB';
    else return (bytes / 1048576).toFixed(2) + ' MB';
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 原有初始化代码
    const currentSection = getCurrentSection();
    showSection(currentSection);
    
    const forms = document.querySelectorAll('form');
        forms.forEach(form => {
        form.addEventListener('submit', function() {
            const currentSection = getCurrentSection();
            localStorage.setItem('bilibili_admin_active_section', currentSection);
        });
    });
    
    // 强制隐藏编辑分类模态框
    const editCategoryModal = document.getElementById('edit-category-modal');
    if (editCategoryModal) {
        editCategoryModal.style.display = 'none';
        editCategoryModal.classList.add('hidden');
        setTimeout(() => {
            editCategoryModal.classList.add('hidden');
            editCategoryModal.style.display = 'none';
        }, 100);
    }
});

// 监听浏览器前进后退按钮
window.addEventListener('popstate', function() {
    const currentSection = getCurrentSection();
    showSection(currentSection);
});

// 清除动态缓存
function clearDynamicCache() {
    if (confirm('确定要清除动态缓存吗？这将立即刷新动态数据。')) {
        fetch('/api/dynamic_api.php?action=clear_cache', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 0) {
                alert('缓存清除成功！');
                // 刷新页面以显示最新数据
                location.reload();
            } else {
                alert('缓存清除失败：' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('缓存清除失败，请稍后重试');
        });
    }
}



// 测试Cookie有效性
document.addEventListener('DOMContentLoaded', function() {
    const testCookieBtn = document.getElementById('test-cookie-btn');
    if (testCookieBtn) {
        testCookieBtn.addEventListener('click', function() {
            const cookieInput = document.getElementById('bilibili-cookie-input');
            const resultDiv = document.getElementById('cookie-test-result');
            
            if (!cookieInput || !cookieInput.value.trim()) {
                alert('请先输入Cookie再进行测试');
                return;
            }
            
            // 显示加载状态
            resultDiv.textContent = '正在测试...';
            resultDiv.classList.remove('hidden', 'bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800');
            resultDiv.classList.add('inline-flex', 'items-center', 'bg-blue-100', 'text-blue-800');
            
            // 发送测试请求
            fetch('../api/test_bilibili_cookie.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cookie: cookieInput.value
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP错误：${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                resultDiv.classList.remove('bg-blue-100', 'text-blue-800');
                if (data.code === 0) {
                    // Cookie有效
                    resultDiv.textContent = '✅ Cookie有效 - ' + (data.message || '可正常使用');
                    resultDiv.classList.add('bg-green-100', 'text-green-800');
                } else {
                    // Cookie无效
                    resultDiv.textContent = '❌ Cookie无效 - ' + (data.message || '请检查或更新');
                    resultDiv.classList.add('bg-red-100', 'text-red-800');
                }
            })
            .catch(error => {
                resultDiv.classList.remove('bg-blue-100', 'text-blue-800');
                resultDiv.textContent = '❌ 测试失败 - ' + (error.message || '服务器错误');
                resultDiv.classList.add('bg-red-100', 'text-red-800');
                console.error('测试Cookie时出错:', error);
            });
        });
    }
});

// 页面通用脚本（加载、Toast、表单、懒加载）
document.addEventListener('DOMContentLoaded', function() {
    // 显示加载动画
    function showLoading() {
        document.getElementById('loading-overlay').style.display = 'flex';
    }
    
    // 隐藏加载动画
    function hideLoading() {
        document.getElementById('loading-overlay').style.display = 'none';
    }
    
    // 显示提示信息
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toast-container');
        
        // 创建toast元素
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        // 设置图标
        let icon = 'check-circle';
        if (type === 'error') icon = 'exclamation-circle';
        if (type === 'info') icon = 'info-circle';
        
        // 设置内容
        toast.innerHTML = `
            <div class="toast-icon"><i class="fas fa-${icon}"></i></div>
            <div class="toast-message">${message}</div>
            <button class="toast-close"><i class="fas fa-times"></i></button>
        `;
        
        // 添加到容器
        toastContainer.appendChild(toast);
        
        // 显示toast
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        // 添加关闭按钮事件
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        });
        
        // 自动关闭
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 5000);
    }
    
    // 表单提交时显示加载动画
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            showLoading();
        });
    });

    // 延迟加载图片和媒体资源
    function lazyLoadMedia() {
    // 延迟加载图片
    const lazyImages = document.querySelectorAll('img[data-src]');
    lazyImages.forEach(img => {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.getAttribute('data-src');
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        });
        observer.observe(img);
    });
    
    // 延迟加载iframe
    const lazyIframes = document.querySelectorAll('iframe[data-src]');
    lazyIframes.forEach(iframe => {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const iframe = entry.target;
                    iframe.src = iframe.getAttribute('data-src');
                    iframe.removeAttribute('data-src');
                    observer.unobserve(iframe);
                }
            });
        });
        observer.observe(iframe);
    });
    }
    
    // 检查URL参数是否有成功消息
    const urlParams = new URLSearchParams(window.location.search);
    const successMsg = urlParams.get('success');
    const errorMsg = urlParams.get('error');
    
    if (successMsg) {
        showToast(decodeURIComponent(successMsg), 'success');
        // 清除URL参数
        history.replaceState({}, document.title, window.location.pathname);
    }
    
    if (errorMsg) {
        showToast(decodeURIComponent(errorMsg), 'error');
        // 清除URL参数
        history.replaceState({}, document.title, window.location.pathname);
    }
    
    // 延迟加载媒体资源
    setTimeout(() => {
        lazyLoadMedia();
    }, 300);
});
</script>

<?php
// 引入管理后台页脚
require_once 'admin_footer.php';
?> 