<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/toast.php';

// 检查管理员登录状态
checkAdminAuth();

// 设置缓存控制头
header('Cache-Control: private, max-age=3600');

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

// 定义可编辑的页脚项目
$footerItems = [
    'thanks' => [
        'name' => '特别鸣谢',
        'icon' => '/elements/Footer/icon/thanks.png',
        'key' => 'footer_thanks'
    ],
    'links' => [
        'name' => '友站连接',
        'icon' => '/elements/Footer/icon/link.png',
        'key' => 'footer_links',
        'help' => '<p>添加友站很简单，每行填入一个友站信息，格式为：<code>站点名称|链接地址</code></p>
                <p>例如：<code>E1粉丝站|https://example1.com</code></p>
                <p>系统会自动生成友好的表格展示样式，无需手动添加HTML标签。</p>'
    ]
];

// 检查周表图片表是否存在
$stmt = $conn->prepare("SHOW TABLES LIKE 'schedule_image'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    $conn->exec("CREATE TABLE schedule_image (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// 检查背景图片表是否存在
$stmt = $conn->prepare("SHOW TABLES LIKE 'section_backgrounds'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    $conn->exec("CREATE TABLE section_backgrounds (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        section_id INT(11) NOT NULL UNIQUE,
        background_image VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // 初始化默认记录
    $conn->exec("INSERT INTO section_backgrounds (section_id, background_image) VALUES (1, ''), (2, ''), (3, '')");
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 添加调试日志
    error_log("POST数据: " . json_encode($_POST));
    
    try {
        // 处理页眉相关更新
        if (isset($_POST['update_header'])) {
            // 处理文本更新
            if (isset($_POST['header_text'])) {
                $header_text = trim($_POST['header_text']);
                $stmt = $conn->prepare("UPDATE header_settings SET header_text = ? WHERE id = 1");
                $stmt->execute([$header_text]);
            }
            
            // 处理样式更新
            if (isset($_POST['background_color']) || isset($_POST['text_color']) || 
                isset($_POST['border_color']) || isset($_POST['shadow_color']) || 
                isset($_POST['text_size']) || isset($_POST['image_size'])) {
                
                // 获取当前样式
                $stmt = $conn->prepare("SELECT header_style FROM header_settings WHERE id = 1");
                $stmt->execute();
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                $current_style = json_decode($current['header_style'] ?? '{}', true);
                
                // 合并新样式
                $new_style = array_merge($current_style, [
                    'background_color' => $_POST['background_color'] ?? $current_style['background_color'] ?? 'rgba(0, 0, 0, 0.8)',
                    'text_color' => $_POST['text_color'] ?? $current_style['text_color'] ?? '#ffffff',
                    'border_color' => $_POST['border_color'] ?? $current_style['border_color'] ?? 'rgba(255, 255, 255, 0.1)',
                    'shadow_color' => $_POST['shadow_color'] ?? $current_style['shadow_color'] ?? 'rgba(0, 0, 0, 0.3)',
                    'text_size' => $_POST['text_size'] ?? $current_style['text_size'] ?? '1.2',
                    'image_size' => $_POST['image_size'] ?? $current_style['image_size'] ?? '50'
                ]);
                
                $header_style = json_encode($new_style);
                $stmt = $conn->prepare("UPDATE header_settings SET header_style = ? WHERE id = 1");
                $result = $stmt->execute([$header_style]);
                
                if (!$result) {
                    throw new Exception('样式更新失败');
                }
            }
            
            // 处理图片上传
            if (isset($_FILES['header_image']) && $_FILES['header_image']['error'] === 0) {
                // 上传前，获取旧图片路径
                $stmt = $conn->prepare("SELECT header_image FROM header_settings WHERE id = 1");
                $stmt->execute();
                $old = $stmt->fetch(PDO::FETCH_ASSOC);
                $old_image = $old['header_image'] ?? null;

                $file = $_FILES['header_image'];
                
                // 获取文件扩展名并检查类型
                $filename = $file['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('只允许上传 JPG、PNG 或 WebP 格式的图片');
                }
                
                // 设置对应的 MIME 类型
                $mime_types = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp'
                ];
                
                $mime_type = $mime_types[$ext] ?? 'application/octet-stream';
                
                $allowed_types = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];
                
                if (!in_array($mime_type, $allowed_types)) {
                    throw new Exception('只允许上传 JPG、PNG 或 WebP 格式的图片');
                }
                
                $upload_dir = '../assets/uploads/header/';
                
                // 删除旧图片（如果存在）
                if ($old_image && file_exists('../' . $old_image)) {
                    if (!@unlink('../' . $old_image)) {
                        error_log("Failed to delete old image: " . $old_image);
                    }
                }
                
                // 清理上传目录中的其他文件
                $files = glob($upload_dir . 'header_*');
                foreach ($files as $existing_file) {
                    if (is_file($existing_file)) {
                        if (!@unlink($existing_file)) {
                            error_log("Failed to delete existing file: " . $existing_file);
                        }
                    }
                }
                
                // 确保目录存在且可写
                if (!file_exists($upload_dir)) {
                    if (!@mkdir($upload_dir, 0777, true)) {
                        throw new Exception('无法创建上传目录，请检查目录权限');
                    }
                }
                
                if (!is_writable($upload_dir)) {
                    throw new Exception('上传目录没有写入权限，请检查目录权限');
                }
                
                $new_filename = 'header_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                // 如果目标文件已存在，先删除
                if (file_exists($upload_path)) {
                    @unlink($upload_path);
                }
                
                if (!@move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $error = error_get_last();
                    throw new Exception('图片上传失败：' . ($error ? $error['message'] : '未知错误'));
                }
                
                // 验证文件是否成功上传
                if (!file_exists($upload_path)) {
                    throw new Exception('文件上传后未找到，请检查目录权限');
                }
                
                $relative_path = 'assets/uploads/header/' . $new_filename;
                $stmt = $conn->prepare("UPDATE header_settings SET header_image = ? WHERE id = 1");
                $stmt->execute([$relative_path]);
            }
            
            showToast('页眉设置更新成功！');
        }
        
        // 处理页脚内容更新
        if (isset($_POST['update_footer'])) {
            // 调试信息
            error_log("处理页脚内容更新: " . json_encode($_POST));
            
            // 获取当前正在编辑的项目
            $currentItem = isset($_POST['current_item']) ? $_POST['current_item'] : '';
            
            if (array_key_exists($currentItem, $footerItems)) {
                $content = isset($_POST['content']) ? $_POST['content'] : '';
                
                // 删除内容字符串前后的单引号（如果存在）
                if (substr($content, 0, 1) === "'" && substr($content, -1) === "'") {
                    $content = substr($content, 1, -1);
                }
                
                $key = $footerItems[$currentItem]['key'];
                
                // 特殊处理友站链接内容
                if ($key === 'footer_links') {
                    // 将文本框内容转换为HTML格式
                    $content = processLinksContent($content);
                }
                
                // 检查记录是否存在
                $stmt = $conn->prepare("SELECT COUNT(*) FROM site_content WHERE content_key = ?");
                $stmt->execute([$key]);
                $exists = (int)$stmt->fetchColumn() > 0;
                
                if ($exists) {
                    // 更新现有记录
                    $stmt = $conn->prepare("UPDATE site_content SET content_value = ? WHERE content_key = ?");
                    $stmt->execute([$content, $key]);
                } else {
                    // 插入新记录
                    $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES (?, ?)");
                    $stmt->execute([$key, $content]);
                }
                
                // 显示成功提示并重定向回当前页面
                showToast("{$footerItems[$currentItem]['name']}内容已成功保存！");
                
                // 不再重定向，直接显示成功提示
                echo '<script>
                    window.onload = function() {
                        showCustomToast("' . $footerItems[$currentItem]['name'] . '内容已成功保存！", "success");
                    }
                </script>';
                // 不退出，继续执行后续代码
            }
        }
        
        // 处理背景图片上传
        if (isset($_POST['update_background'])) {
            if (isset($_FILES['background_image']) && $_FILES['background_image']['error'] === 0) {
                $section_id = $_POST['section_id'];
                
                // 上传前，获取旧图片路径
                $stmt = $conn->prepare("SELECT background_image FROM section_backgrounds WHERE section_id = ?");
                $stmt->execute([$section_id]);
                $old = $stmt->fetch(PDO::FETCH_ASSOC);
                $old_image = $old['background_image'] ?? null;

                $file = $_FILES['background_image'];
                
                // 获取文件扩展名并检查类型
                $filename = $file['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('只允许上传 JPG、PNG 或 WebP 格式的图片');
                }
                
                // 设置对应的 MIME 类型
                $mime_types = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp'
                ];
                
                $mime_type = $mime_types[$ext] ?? 'application/octet-stream';
                
                $allowed_types = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];
                
                if (!in_array($mime_type, $allowed_types)) {
                    throw new Exception('只允许上传 JPG、PNG 或 WebP 格式的图片');
                }
                
                $upload_dir = '../assets/uploads/backgrounds/';
                
                // 删除旧图片（如果存在）
                if ($old_image && file_exists('../' . $old_image)) {
                    if (!@unlink('../' . $old_image)) {
                        error_log("Failed to delete old image: " . $old_image);
                    }
                }
                
                // 清理上传目录中的其他文件
                $files = glob($upload_dir . 'background_' . $section_id . '_*');
                foreach ($files as $existing_file) {
                    if (is_file($existing_file)) {
                        if (!@unlink($existing_file)) {
                            error_log("Failed to delete existing file: " . $existing_file);
                        }
                    }
                }
                
                // 确保目录存在且可写
                if (!file_exists($upload_dir)) {
                    if (!@mkdir($upload_dir, 0777, true)) {
                        throw new Exception('无法创建上传目录，请检查目录权限');
                    }
                }
                
                if (!is_writable($upload_dir)) {
                    throw new Exception('上传目录没有写入权限，请检查目录权限');
                }
                
                $new_filename = 'background_' . $section_id . '_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                // 如果目标文件已存在，先删除
                if (file_exists($upload_path)) {
                    @unlink($upload_path);
                }
                
                if (!@move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $error = error_get_last();
                    throw new Exception('图片上传失败：' . ($error ? $error['message'] : '未知错误'));
                }
                
                // 验证文件是否成功上传
                if (!file_exists($upload_path)) {
                    throw new Exception('文件上传后未找到，请检查目录权限');
                }
                
                $relative_path = 'assets/uploads/backgrounds/' . $new_filename;
                $stmt = $conn->prepare("UPDATE section_backgrounds SET background_image = ? WHERE section_id = ?");
                $stmt->execute([$relative_path, $section_id]);
                
                showToast('背景图片更新成功！');
            } else {
                throw new Exception('请选择要上传的背景图片！');
            }
        }
        
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 获取当前页眉设置
$stmt = $conn->prepare("SELECT header_text, header_image, header_style FROM header_settings WHERE id = 1");
$stmt->execute();
$header = $stmt->fetch(PDO::FETCH_ASSOC);

// 解析样式设置
$style = json_decode($header['header_style'] ?? '{}', true);
$default_style = [
    'background_color' => 'rgba(0, 0, 0, 0.8)',
    'text_color' => '#ffffff',
    'border_color' => 'rgba(255, 255, 255, 0.1)',
    'shadow_color' => 'rgba(0, 0, 0, 0.3)',
    'text_size' => '1.2',
    'image_size' => '50'
];
$style = array_merge($default_style, $style);

// 获取当前页脚查看的项目
$viewItem = isset($_GET['item']) ? $_GET['item'] : 'thanks';
if (!array_key_exists($viewItem, $footerItems)) {
    $viewItem = 'thanks'; // 默认显示特别鸣谢
}

// 获取当前页脚项目的内容
$currentContent = '';
$rawLinksContent = ''; // 原始友站链接内容

// 检查是否刚刚保存过内容
$justSaved = isset($_GET['saved']) && $_GET['saved'] == '1';

try {
    // 获取所有类型的页脚内容
    $stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = ?");
    $stmt->execute([$footerItems[$viewItem]['key']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $currentContent = $row['content_value'];
        
        // 如果是友站链接，反向处理为简单格式
        if ($viewItem === 'links') {
            $rawLinksContent = reverseProcessLinksContent($currentContent);
        }
    }
} catch (Exception $e) {
    // 忽略错误，使用默认值
    error_log("获取页脚内容错误: " . $e->getMessage());
}

// 获取当前背景图片
$stmt = $conn->prepare("SELECT * FROM section_backgrounds ORDER BY section_id ASC");
$stmt->execute();
$backgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * 处理友站链接文本为HTML格式
 */
function processLinksContent($text) {
    $lines = explode("\n", trim($text));
    $html = '<div class="friendlinks-container">';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $parts = explode('|', $line, 2);
        $siteName = trim($parts[0]);
        $siteUrl = isset($parts[1]) ? trim($parts[1]) : '#';
        
        $html .= '<div class="friendlink-item">';
        $html .= '<a href="' . htmlspecialchars($siteUrl) . '" target="_blank" class="friendlink-name">' . htmlspecialchars($siteName) . '</a>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * 反向处理HTML友站链接为简单文本格式
 */
function reverseProcessLinksContent($html) {
    $text = '';
    
    // 使用正则表达式提取链接和站点名称
    preg_match_all('/<a href="([^"]*)" target="_blank" class="friendlink-name">([^<]*)<\/a>/i', $html, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $url = $match[1];
        $name = $match[2];
        $text .= $name . '|' . $url . "\n";
    }
    
    return $text;
}

// 设置页面标题
$page_title = "布局管理";

// 设置页面特定样式
$extra_styles = '
.style-preview {
    background: ' . $style['background_color'] . ';
    color: ' . $style['text_color'] . ';
    backdrop-filter: blur(' . ($style['blur_amount'] ?? '0') . 'px);
    -webkit-backdrop-filter: blur(' . ($style['blur_amount'] ?? '0') . 'px);
    border-bottom: 1px solid ' . $style['border_color'] . ';
    box-shadow: 0 4px 30px ' . $style['shadow_color'] . ';
    padding: 15px 20px;
    margin-bottom: 0;
    border-radius: 8px;
}
.style-preview .header-text {
    font-size: ' . $style['text_size'] . 'rem;
    font-weight: 600;
}
.style-preview .header-circle {
    width: ' . $style['image_size'] . 'px;
    height: ' . $style['image_size'] . 'px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.style-preview .header-circle img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

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

.color-input {
    border: 2px solid rgba(204, 148, 113, 0.3);
    overflow: hidden;
    border-radius: 8px;
}

.content-editor {
    min-height: 300px;
    font-family: "QiantuHouhei", sans-serif;
    border: 2px solid rgba(204, 148, 113, 0.3);
    transition: all 0.3s ease;
    border-radius: 8px;
    padding: 10px;
}

.content-editor:focus {
    border-color: #cc9471;
    box-shadow: 0 0 0 3px rgba(204, 148, 113, 0.2);
    outline: none;
}

.nagisa-tab {
    padding: 12px 16px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    border-bottom: 2px solid transparent;
}

.nagisa-tab-active {
    background: linear-gradient(45deg, #cc9471, #f3b4a4);
    color: white;
    border-bottom: 2px solid #cc9471;
}

.nagisa-tab:hover:not(.nagisa-tab-active) {
    background: rgba(204, 148, 113, 0.1);
    border-bottom: 2px solid rgba(204, 148, 113, 0.3);
}

.help-text {
    border-left: 4px solid #cc9471;
    padding-left: 10px;
    margin-bottom: 15px;
    background-color: rgba(204, 148, 113, 0.1);
    padding: 8px 12px;
    border-radius: 0 4px 4px 0;
    color: #704c38;
}

.help-text code {
    background-color: rgba(204, 148, 113, 0.2);
    padding: 2px 4px;
    border-radius: 4px;
    font-family: monospace;
}

.preview-container {
    background-color: rgba(204, 148, 113, 0.1);
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
    border: 1px solid rgba(204, 148, 113, 0.2);
}

.preview-title {
    font-weight: bold;
    margin-bottom: 10px;
    border-bottom: 1px solid rgba(204, 148, 113, 0.3);
    padding-bottom: 5px;
    color: #cc9471;
}

/* 友站链接样式预览 */
.preview-friendlinks-container {
    display: flex;
    flex-direction: column;
    padding: 15px 18px;
    background-color: rgba(77, 64, 48, 0.8);
    border-radius: 8px;
    width: auto;
    max-width: 200px;
    margin: 0 auto;
    border: 1px solid rgba(204, 148, 113, 0.3);
}

.preview-friendlink-item {
    margin: 8px 0;
    text-align: center;
    border-bottom: 1px dashed rgba(255, 255, 255, 0.2);
    padding-bottom: 8px;
}

.preview-friendlink-item:last-child {
    border-bottom: none;
}

.preview-friendlink-name {
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    display: block;
    padding: 5px 0;
    transition: all 0.3s ease;
    text-align: center;
}

.preview-friendlink-name:hover {
    color: #f3b4a4;
    transform: translateY(-3px);
}

/* 背景图片相关样式 */
.preview-image {
    width: 100%;
    height: 160px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid rgba(204, 148, 113, 0.2);
    transition: all 0.3s;
}

.preview-image:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(204, 148, 113, 0.3);
}

.nagisa-bg-card {
    background: rgba(204, 148, 113, 0.05);
    border-radius: 10px;
    padding: 15px;
    transition: all 0.3s;
    border: 1px solid rgba(204, 148, 113, 0.1);
}

.nagisa-bg-card:hover {
    background: rgba(204, 148, 113, 0.1);
    transform: translateY(-2px);
}
';

// 包含统一页眉
include 'admin_header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
        <!-- 左侧：布局管理导航和使用说明 -->
        <div class="md:col-span-3">
            <div class="nagisa-card">
                <h2 class="nagisa-card-header">布局管理</h2>
                <div class="p-4">
                    <ul class="space-y-1">
                        <li>
                            <a href="#header" class="nagisa-nav-link" onclick="showSection('header'); return false;">
                                <i class="fas fa-heading mr-2"></i>页眉设置
                            </a>
                        </li>
                        <li>
                            <a href="#background" class="nagisa-nav-link" onclick="showSection('background'); return false;">
                                <i class="fas fa-images mr-2"></i>背景图片
                            </a>
                        </li>
                        <li>
                            <a href="#footer" class="nagisa-nav-link" onclick="showSection('footer'); return false;">
                                <i class="fas fa-shoe-prints mr-2"></i>页脚内容
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
                            <span>页眉设置：管理网站页眉的文本、图片和样式</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>背景图片：管理网站各区域的背景图片</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>页脚内容：管理鸣谢名单和友站连接</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>支持实时预览和多种样式设置</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- 右侧：所有管理卡片 -->
        <div class="md:col-span-9">
            <!-- 页眉设置卡片 -->
            <div id="header" class="nagisa-card section-content mb-6">
                <h2 class="nagisa-card-header">页眉设置</h2>
                <div class="p-6">
                    <!-- 样式预览 -->
                    <div class="mb-6">
                        <h3 class="nagisa-section-title">样式预览</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="style-preview">
                                <div class="flex justify-between items-center">
                                    <div class="header-text"><?php echo htmlspecialchars($header['header_text'] ?? 'Nagisa Live'); ?></div>
                                    <div class="header-circle">
                                        <?php if (!empty($header['header_image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($header['header_image']); ?>" alt="当前页眉图片">
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 编辑表单 -->
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <!-- 文本编辑 -->
                        <div class="nagisa-form-group">
                            <label for="header_text" class="nagisa-label">页眉文本</label>
                            <input type="text" 
                                   id="header_text"
                                   name="header_text" 
                                   value="<?php echo htmlspecialchars($header['header_text'] ?? ''); ?>"
                                   class="nagisa-input">
                        </div>

                        <!-- 样式设置 - 颜色部分 -->
                        <div class="nagisa-form-group">
                            <h3 class="nagisa-section-title">颜色设置</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="nagisa-label">背景颜色</label>
                                    <div class="flex items-center gap-3">
                                        <input type="color" 
                                               name="background_color" 
                                               value="<?php echo $style['background_color'] ?? '#000000'; ?>"
                                               class="h-10 w-16 p-1 rounded color-input">
                                        <span class="text-sm text-gray-500"><?php echo $style['background_color'] ?? '#000000'; ?></span>
                                    </div>
                                </div>
                                <div>
                                    <label class="nagisa-label">文字颜色</label>
                                    <div class="flex items-center gap-3">
                                        <input type="color" 
                                               name="text_color" 
                                               value="<?php echo $style['text_color'] ?? '#ffffff'; ?>"
                                               class="h-10 w-16 p-1 rounded color-input">
                                        <span class="text-sm text-gray-500"><?php echo $style['text_color'] ?? '#ffffff'; ?></span>
                                    </div>
                                </div>
                                <div>
                                    <label class="nagisa-label">边框颜色</label>
                                    <div class="flex items-center gap-3">
                                        <input type="color" 
                                               name="border_color" 
                                               value="<?php echo $style['border_color'] ?? 'rgba(255, 255, 255, 0.1)'; ?>"
                                               class="h-10 w-16 p-1 rounded color-input">
                                        <span class="text-sm text-gray-500"><?php echo $style['border_color'] ?? 'rgba(255, 255, 255, 0.1)'; ?></span>
                                    </div>
                                </div>
                                <div>
                                    <label class="nagisa-label">阴影颜色</label>
                                    <div class="flex items-center gap-3">
                                        <input type="color" 
                                               name="shadow_color" 
                                               value="<?php echo $style['shadow_color'] ?? 'rgba(0, 0, 0, 0.3)'; ?>"
                                               class="h-10 w-16 p-1 rounded color-input">
                                        <span class="text-sm text-gray-500"><?php echo $style['shadow_color'] ?? 'rgba(0, 0, 0, 0.3)'; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 样式设置 - 尺寸部分 -->
                        <div class="nagisa-form-group">
                            <h3 class="nagisa-section-title">尺寸设置</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="nagisa-label">文字大小</label>
                                    <div class="flex items-center gap-3">
                                        <input type="range" 
                                               name="text_size" 
                                               min="0.8" 
                                               max="3.0" 
                                               step="0.1"
                                               value="<?php echo $style['text_size'] ?? '1.2'; ?>"
                                               oninput="document.getElementById('text_size_value').innerText = this.value"
                                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                        <span id="text_size_value" class="text-sm text-gray-500 w-10 text-right"><?php echo $style['text_size'] ?? '1.2'; ?></span>
                                        <span class="text-sm text-gray-400">rem</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="nagisa-label">图片大小</label>
                                    <div class="flex items-center gap-3">
                                        <input type="range" 
                                               name="image_size" 
                                               min="20" 
                                               max="100" 
                                               step="1"
                                               value="<?php echo $style['image_size'] ?? '50'; ?>"
                                               oninput="document.getElementById('image_size_value').innerText = this.value"
                                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                                        <span id="image_size_value" class="text-sm text-gray-500 w-10 text-right"><?php echo $style['image_size'] ?? '50'; ?></span>
                                        <span class="text-sm text-gray-400">px</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 图片上传 -->
                        <div class="nagisa-form-group">
                            <h3 class="nagisa-section-title">页眉图片</h3>
                            
                            <div class="flex flex-col items-center mb-4">
                                <div class="w-24 h-24 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center mb-3" style="border:3px solid #cc9471;">
                                    <?php if (!empty($header['header_image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($header['header_image']); ?>" alt="当前页眉图片" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fas fa-camera text-gray-400 text-2xl"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="w-full">
                                    <label class="nagisa-label">上传新图片</label>
                                    <input type="file" 
                                           name="header_image" 
                                           id="header_image_upload"
                                           accept=".jpg,.jpeg,.png,.webp"
                                           class="nagisa-input text-sm">
                                    <p class="mt-2 text-xs text-gray-500">支持的格式：JPG、PNG、WebP，建议尺寸：200x200像素</p>
                                </div>
                            </div>
                        </div>

                        <!-- 提交按钮 -->
                        <div class="flex justify-end pt-3">
                            <button type="submit" name="update_header" class="nagisa-btn">
                                <i class="fas fa-save mr-2"></i>保存页眉设置
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- 背景图片卡片 -->
            <div id="background" class="nagisa-card section-content mb-6">
                <h2 class="nagisa-card-header">背景图片</h2>
                <div class="p-6">
                    <p class="mb-6 text-gray-600">
                        管理网站各区域的背景图片，支持三个不同层级的背景设置。
                    </p>
                    
                    <!-- 上传背景图片 -->
                    <div class="nagisa-card">
                        <h3 class="nagisa-card-header">上传背景图片</h3>
                        <div class="p-6">
                            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                                <!-- 选择区域 -->
                                <div class="nagisa-form-group">
                                    <label class="nagisa-label">选择区域</label>
                                    <select name="section_id" class="nagisa-input">
                                        <option value="1">第一层</option>
                                        <option value="2">第二层</option>
                                        <option value="3">第三层</option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">每个区域只能设置一张背景图片，新上传的图片会替换旧图片</p>
                                </div>

                                <!-- 图片上传 -->
                                <div class="nagisa-form-group">
                                    <label class="nagisa-label">背景图片</label>
                                    <input type="file" 
                                           name="background_image" 
                                           id="background_image_upload"
                                           accept="image/*"
                                           class="nagisa-input">
                                    <p class="mt-1 text-xs text-gray-500">
                                        建议上传宽屏图片，将自动适应屏幕宽度
                                    </p>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" name="update_background" class="nagisa-btn">
                                        <i class="fas fa-upload mr-2"></i>
                                        上传图片
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 已上传的图片列表 -->
                    <div class="nagisa-card">
                        <h3 class="nagisa-card-header">已上传的图片</h3>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($backgrounds as $bg): ?>
                                <div class="nagisa-bg-card">
                                    <?php if (!empty($bg['background_image'])): ?>
                                    <img src="../<?php echo htmlspecialchars($bg['background_image']); ?>" 
                                         alt="Section <?php echo htmlspecialchars($bg['section_id']); ?> 背景"
                                         class="preview-image">
                                    <?php else: ?>
                                    <div class="preview-image bg-gray-100 flex items-center justify-center">
                                        <i class="fas fa-image text-gray-400 text-2xl"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div class="mt-3">
                                        <p class="text-sm font-medium text-gray-700">
                                            <?php 
                                                $section_names = [
                                                    1 => '第一层',
                                                    2 => '第二层',
                                                    3 => '第三层'
                                                ];
                                                echo htmlspecialchars($section_names[$bg['section_id']] ?? '未知区域');
                                            ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?php if (!empty($bg['background_image'])): ?>
                                            上传时间：<?php echo date('Y-m-d H:i:s', strtotime($bg['created_at'])); ?>
                                            <?php else: ?>
                                            暂无图片
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <?php if (count($backgrounds) === 0): ?>
                                <div class="col-span-3 text-center p-8 text-gray-500">
                                    <i class="fas fa-image text-2xl mb-2"></i>
                                    <p>暂无已上传的背景图片</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- 页脚内容卡片 -->
            <div id="footer" class="nagisa-card section-content mb-6">
                <h2 class="nagisa-card-header">页脚内容</h2>
                <div class="p-6">
                    <!-- 标签栏 -->
                    <div class="flex border-b overflow-x-auto mb-6">
                        <?php foreach ($footerItems as $key => $item): ?>
                        <a href="?section=footer&item=<?php echo $key; ?>" 
                           class="nagisa-tab <?php echo $viewItem === $key ? 'nagisa-tab-active' : ''; ?>"
                           onclick="switchFooterTab('<?php echo $key; ?>'); return false;">
                            <img src="<?php echo $item['icon']; ?>" alt="<?php echo $item['name']; ?>" class="inline-block w-5 h-5 mr-2" style="filter: <?php echo $viewItem === $key ? 'brightness(2)' : 'none'; ?>;">
                            <?php echo $item['name']; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- 当前项目内容编辑器 -->
                    <div>
                        <h3 class="nagisa-section-title text-lg font-semibold mb-4">
                            编辑<?php echo $footerItems[$viewItem]['name']; ?>内容
                        </h3>

                        <?php if (isset($footerItems[$viewItem]['help'])): ?>
                        <div class="help-text mb-4">
                            <?php echo $footerItems[$viewItem]['help']; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($viewItem === 'links'): ?>
                        <div class="preview-container">
                            <div class="preview-title">友站链接样式预览(单列排列)</div>
                            <div class="preview-friendlinks-container">
                                <div class="preview-friendlink-item">
                                    <a href="javascript:void(0)" class="preview-friendlink-name">AA粉丝站</a>
                                </div>
                                <div class="preview-friendlink-item">
                                    <a href="javascript:void(0)" class="preview-friendlink-name">BB粉丝站</a>
                                </div>
                                <div class="preview-friendlink-item">
                                    <a href="javascript:void(0)" class="preview-friendlink-name">CC粉丝站</a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?section=footer&item=<?php echo $viewItem; ?>" class="mt-6" enctype="multipart/form-data" name="footer-form">
                            <input type="hidden" name="current_item" value="<?php echo $viewItem; ?>">
                            
                            <div class="nagisa-form-group">
                                <?php if ($viewItem === 'links'): ?>
                                <label for="content" class="nagisa-label">
                                    友站列表 (每行一个，格式：站点名称|网址)
                                </label>
                                <textarea 
                                    id="content" 
                                    name="content" 
                                    class="content-editor w-full"
                                    rows="15"
                                    placeholder="E1粉丝站|https://example1.com&#10;E2粉丝站|https://example2.com&#10;E3粉丝站|https://example3.com"
                                ><?php echo htmlspecialchars($rawLinksContent); ?></textarea>
                                <p class="mt-2 text-sm text-gray-500">
                                    每行输入一个友站信息，系统会自动生成表格样式的布局。
                                </p>
                                
                                <?php elseif ($viewItem === 'thanks_emoji'): ?>
                                <div>
                                    <label class="nagisa-label">表情图片</label>
                                    
                                    <?php if (!empty($currentContent)): ?>
                                    <div class="mb-4">
                                        <p class="text-sm font-medium text-gray-700 mb-2">当前表情图片：</p>
                                        <div class="w-24 h-24 rounded overflow-hidden bg-gray-100 flex items-center justify-center mb-3" style="border:2px solid #cc9471;">
                                            <img src="<?php echo htmlspecialchars($currentContent); ?>" alt="表情图片" class="w-full h-full object-contain">
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-4">
                                        <p class="text-sm font-medium text-gray-700 mb-2">上传新表情图片：</p>
                                        <input type="file" 
                                               name="emoji_image" 
                                               id="emoji_image_upload"
                                               accept=".jpg,.jpeg,.png,.gif,.webp"
                                               class="nagisa-input text-sm">
                                        <p class="mt-2 text-xs text-gray-500">支持的格式：JPG、PNG、GIF、WebP，建议尺寸：100x100像素</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <p class="text-sm font-medium text-gray-700 mb-2">或者输入图片URL：</p>
                                        <input type="text" 
                                               name="content" 
                                               id="content" 
                                               value="<?php echo htmlspecialchars($currentContent); ?>"
                                               placeholder="https://example.com/emoji.png"
                                               class="nagisa-input">
                                        <p class="mt-2 text-xs text-gray-500">如果同时上传图片和输入URL，将优先使用上传的图片</p>
                                    </div>
                                </div>
                                
                                <?php else: ?>
                                <label for="content" class="nagisa-label">
                                    内容 (支持HTML)
                                </label>
                                <textarea 
                                    id="content" 
                                    name="content" 
                                    class="content-editor w-full"
                                    rows="15"
                                ><?php echo htmlspecialchars($currentContent); ?></textarea>
                                <p class="mt-2 text-sm text-gray-500">
                                    您可以使用HTML标签来格式化内容。点击页脚选项时，将显示此内容而不是默认提示。
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="update_footer" value="1" class="nagisa-btn">
                                    <i class="fas fa-save mr-2"></i>保存更改
                                </button>
                            </div>
                        </form>
                    </div>
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
    const activeLink = document.querySelector(`a[href="#${sectionId}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
    
    // 保存当前选中的模块到本地存储
    localStorage.setItem('layout_admin_active_section', sectionId);
    
    // 更新URL参数（不刷新页面）
    const url = new URL(window.location);
    url.searchParams.set('section', sectionId);
    window.history.replaceState({}, '', url);
}

// 自定义Toast提示函数
function showCustomToast(message, type = 'success') {
    // 创建toast元素
    const toast = document.createElement('div');
    toast.id = 'custom-toast';
    toast.style.position = 'fixed';
    toast.style.top = '50%';
    toast.style.left = '50%';
    toast.style.transform = 'translate(-50%, -50%)';
    toast.style.zIndex = '9999';
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s ease-in-out';
    
    // 设置toast内容
    const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    
    toast.innerHTML = `
        <div class="${bgColor} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 min-w-[300px] relative">
            <i class="fas fa-${icon} text-xl"></i>
            <span>${message}</span>
            ${type === 'error' ? '<button onclick="closeCustomToast()" class="absolute top-2 right-2 text-white hover:text-gray-200"><i class="fas fa-times"></i></button>' : ''}
        </div>
    `;
    
    // 添加到body
    document.body.appendChild(toast);
    
    // 显示toast
    setTimeout(() => {
        toast.style.opacity = '1';
    }, 10);
    
    // 如果是成功提示，3秒后自动隐藏
    if (type === 'success') {
        setTimeout(() => {
            closeCustomToast();
        }, 3000);
    }
}

// 关闭toast
function closeCustomToast() {
    const toast = document.getElementById('custom-toast');
    if (toast) {
        toast.style.opacity = '0';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }
}

// 处理页脚内容表单提交
document.addEventListener('DOMContentLoaded', function() {
    const footerForm = document.querySelector('form[name="footer-form"]');
    if (footerForm) {
        // 移除AJAX提交，使用传统表单提交
        // 不再阻止默认提交行为
    }
});

// 获取当前应该显示的模块
function getCurrentSection() {
    // 首先检查URL参数
    const urlParams = new URLSearchParams(window.location.search);
    const urlSection = urlParams.get('section');
    
    // 然后检查本地存储
    const storedSection = localStorage.getItem('layout_admin_active_section');
    
    // 最后使用默认值
    const defaultSection = 'header';
    
    // 验证section是否有效
    const validSections = ['header', 'background', 'footer'];
    const section = urlSection || storedSection || defaultSection;
    
    return validSections.includes(section) ? section : defaultSection;
}

// 切换页脚标签
function switchFooterTab(item) {
    // 更新URL参数
    const url = new URL(window.location);
    url.searchParams.set('item', item);
    window.history.replaceState({}, '', url);
    
    // 重新加载页面以获取新内容
    window.location.href = url.toString();
}

// 页眉实时预览
const headerInputs = document.querySelectorAll('input[name="background_color"], input[name="text_color"], input[name="border_color"], input[name="shadow_color"], input[name="text_size"], input[name="image_size"]');
headerInputs.forEach(input => {
    input.addEventListener('input', updateHeaderPreview);
});

function updateHeaderPreview() {
    const stylePreview = document.querySelector('.style-preview');
    const headerText = stylePreview.querySelector('.header-text');
    const headerCircle = stylePreview.querySelector('.header-circle');
    
    stylePreview.style.backgroundColor = document.querySelector('input[name="background_color"]').value;
    stylePreview.style.color = document.querySelector('input[name="text_color"]').value;
    stylePreview.style.borderBottomColor = document.querySelector('input[name="border_color"]').value;
    stylePreview.style.boxShadow = `0 4px 30px ${document.querySelector('input[name="shadow_color"]').value}`;
    
    headerText.style.fontSize = `${document.querySelector('input[name="text_size"]').value}rem`;
    
    const imageSize = document.querySelector('input[name="image_size"]').value;
    headerCircle.style.width = `${imageSize}px`;
    headerCircle.style.height = `${imageSize}px`;
}

// 图片上传预览
document.getElementById('header_image_upload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const previewContainer = document.querySelector('.w-24.h-24.rounded-full');
            
            // 清空预览容器
            previewContainer.innerHTML = '';
            
            // 创建预览图
            const img = document.createElement('img');
            img.src = event.target.result;
            img.alt = '页眉图片预览';
            img.className = 'w-full h-full object-cover';
            
            // 添加到预览容器
            previewContainer.appendChild(img);
            
            // 同时也更新样式预览中的图片
            const headerCircleImg = document.querySelector('.style-preview .header-circle');
            if (headerCircleImg) {
                if (headerCircleImg.querySelector('img')) {
                    headerCircleImg.querySelector('img').src = event.target.result;
                } else {
                    headerCircleImg.innerHTML = '';
                    const newImg = document.createElement('img');
                    newImg.src = event.target.result;
                    headerCircleImg.appendChild(newImg);
                }
            }
        };
        reader.readAsDataURL(file);
    }
});

// 表情图片上传预览
function setupEmojiImagePreview() {
    const emojiUpload = document.getElementById('emoji_image_upload');
    if (emojiUpload) {
        emojiUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    // 查找预览容器，如果不存在则创建一个
                    let previewContainer = document.querySelector('.emoji-preview-container');
                    if (!previewContainer) {
                        const parentDiv = emojiUpload.closest('div');
                        previewContainer = document.createElement('div');
                        previewContainer.className = 'emoji-preview-container mt-4';
                        previewContainer.innerHTML = `
                            <p class="text-sm font-medium text-gray-700 mb-2">预览：</p>
                            <div class="w-24 h-24 rounded overflow-hidden bg-gray-100 flex items-center justify-center" style="border:2px solid #cc9471;">
                                <img src="" alt="表情预览" class="w-full h-full object-contain">
                            </div>
                        `;
                        parentDiv.appendChild(previewContainer);
                    }
                    
                    // 更新预览图片
                    const previewImg = previewContainer.querySelector('img');
                    if (previewImg) {
                        previewImg.src = event.target.result;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 获取当前应该显示的模块
    const currentSection = getCurrentSection();
    
    // 显示对应的模块
    showSection(currentSection);
    
    // 设置表情图片上传预览
    setupEmojiImagePreview();
    
    // 添加表单提交后的处理
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            // 保存当前模块状态
            const currentSection = getCurrentSection();
            localStorage.setItem('layout_admin_active_section', currentSection);
        });
    });
});

// 监听浏览器前进后退按钮
window.addEventListener('popstate', function() {
    const currentSection = getCurrentSection();
    showSection(currentSection);
});
</script>

<?php
// 引入管理后台页脚
require_once 'admin_footer.php';
?> 