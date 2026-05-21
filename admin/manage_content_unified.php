<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/toast.php';

// 检查管理员登录状态
checkAdminAuth();

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

// 获取当前section和action
$section = isset($_GET['section']) ? $_GET['section'] : 'schedule';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 编辑衣装时获取数据
$edit_clothes_data = null;
if ($section === 'clothes' && $action === 'edit' && $item_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM clothes_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $edit_clothes_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_clothes_data) {
        // 如果找不到数据，重定向回列表
        header('Location: ?section=clothes');
        exit;
    }
}

// 检查表是否存在，如果不存在则创建
$stmt = $conn->prepare("SHOW TABLES LIKE 'site_content'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    $conn->exec("CREATE TABLE site_content (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        content_key VARCHAR(50) NOT NULL UNIQUE,
        content_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // 初始化默认值
    $defaultFilebagText = '文件资料袋';
    $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('filebag_text', :filebag_text)");
    $stmt->bindParam(':filebag_text', $defaultFilebagText);
    $stmt->execute();
    
    // 初始化默认描述文本
    $defaultDescriptions = json_encode([
        '「认为思考有趣的问题比真正去做事更轻松很正常吧。」',
        '「我叫米汀，推理社社员，不是侦探。」',
        '「小朋友们大家好，我是你们的小汀姐姐」'
    ], JSON_UNESCAPED_UNICODE);
    
    $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) VALUES ('information_description', :description)");
    $stmt->bindParam(':description', $defaultDescriptions);
    $stmt->execute();
}

// 检查周表图片表是否存在
$stmt = $conn->prepare("SHOW TABLES LIKE 'schedule_image'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    $conn->exec("CREATE TABLE schedule_image (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_visible BOOLEAN DEFAULT 1
    )");
}

// 检查衣装表是否存在
$stmt = $conn->prepare("SHOW TABLES LIKE 'clothes_items'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    $conn->exec("CREATE TABLE clothes_items (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        display_year VARCHAR(10) NOT NULL DEFAULT '2024',
        title VARCHAR(100) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        display_order INT(5) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // 处理文件袋文本更新
        if (isset($_POST['update_filebag_text'])) {
            $filebagText = trim($_POST['filebag_text']);
            
            if (!empty($filebagText)) {
                // 允许部分安全的HTML标签
                $allowedTags = '<b><i><u><strong><em><span><div><font><h1><h2><h3><h4><h5><h6><p><br><hr>';
                $filebagText = strip_tags($filebagText, $allowedTags);
                
                // 尝试更新，如果记录不存在则插入
                $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) 
                                        VALUES ('filebag_text', :filebag_text)
                                        ON DUPLICATE KEY UPDATE content_value = :filebag_text");
                $stmt->bindParam(':filebag_text', $filebagText);
                
                if ($stmt->execute()) {
                    showToast('文件袋文本更新成功！');
                } else {
                    throw new Exception('更新失败，请稍后重试！');
                }
            } else {
                throw new Exception('文件袋文本不能为空！');
            }
        }
        
        // 处理个人描述文本更新
        if (isset($_POST['update_descriptions'])) {
            // 获取所有非空描述文本
            $descriptions = array_filter($_POST['descriptions'], function($value) {
                return !empty(trim($value));
            });
            
            if (count($descriptions) > 0) {
                $descriptionsJson = json_encode(array_values($descriptions), JSON_UNESCAPED_UNICODE);
                
                // 尝试更新，如果记录不存在则插入
                $stmt = $conn->prepare("INSERT INTO site_content (content_key, content_value) 
                                        VALUES ('information_description', :description)
                                        ON DUPLICATE KEY UPDATE content_value = :description_update");
                $stmt->bindParam(':description', $descriptionsJson);
                $stmt->bindParam(':description_update', $descriptionsJson);
                
                if ($stmt->execute()) {
                    showToast('个人描述更新成功！');
                } else {
                    throw new Exception('更新失败，请稍后重试！');
                }
            } else {
                throw new Exception('请至少添加一条描述文本！');
            }
        }
        
        // 处理周表图片上传
        if (isset($_POST['update_schedule'])) {
            if (isset($_FILES['schedule_image']) && $_FILES['schedule_image']['error'] === 0) {
                // 上传前，获取旧图片路径
                $stmt = $conn->prepare("SELECT image_path FROM schedule_image ORDER BY id DESC LIMIT 1");
                $stmt->execute();
                $old = $stmt->fetch(PDO::FETCH_ASSOC);
                $old_image = $old['image_path'] ?? null;

                $file = $_FILES['schedule_image'];
                
                // 检查文件类型 - 使用扩展名检查替代fileinfo
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $filename = $file['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('只允许上传 JPG、PNG 或 WebP 格式的图片');
                }
                
                // 尝试使用mime_content_type函数（如果可用）
                if (function_exists('mime_content_type')) {
                    $mime_type = mime_content_type($file['tmp_name']);
                $allowed_types = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];
                
                if (!in_array($mime_type, $allowed_types)) {
                    throw new Exception('只允许上传 JPG、PNG 或 WebP 格式的图片');
                }
                }
                
                $upload_dir = '../assets/uploads/schedule/';
                
                // 删除旧图片（如果存在）
                if ($old_image && file_exists('../' . $old_image)) {
                    if (!@unlink('../' . $old_image)) {
                        error_log("Failed to delete old image: " . $old_image);
                    }
                }
                
                // 清理上传目录中的其他文件
                $files = glob($upload_dir . 'schedule_*');
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
                
                // 使用年月日格式命名文件
                $date_format = date('Ymd');
                $new_filename = 'schedule_' . $date_format . '.' . $ext;
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
                
                $relative_path = 'assets/uploads/schedule/' . $new_filename;
                
                // 保存图片路径到数据库
                $stmt = $conn->prepare("INSERT INTO schedule_image (image_path) VALUES (?)");
                $stmt->execute([$relative_path]);
                
                showToast('周表已更新！');
            } else {
                throw new Exception('请选择要上传的周表图片！');
            }
        }
        
        // 处理删除周表图片
        if (isset($_POST['delete_schedule_image'])) {
            // 获取当前图片路径
            $stmt = $conn->prepare("SELECT image_path FROM schedule_image ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            $image_path = $image['image_path'] ?? null;
            
            if ($image_path && file_exists('../' . $image_path)) {
                // 删除文件
                if (@unlink('../' . $image_path)) {
                    // 从数据库中删除记录
                    $stmt = $conn->prepare("DELETE FROM schedule_image WHERE image_path = ?");
                    $stmt->execute([$image_path]);
                    
                    showToast('周表图片已成功删除！');
                } else {
                    throw new Exception('删除图片文件失败！请检查文件权限。');
                }
            } else {
                throw new Exception('未找到图片文件或图片已被删除！');
            }
        }
        
        // 处理衣装图片上传和信息添加
        if (isset($_POST['add_clothes_item'])) {
            if (isset($_FILES['clothes_image']) && $_FILES['clothes_image']['error'] === 0) {
                $file = $_FILES['clothes_image'];
                $display_year = isset($_POST['clothes_year']) ? trim($_POST['clothes_year']) : '2024';
                $title = isset($_POST['clothes_title']) ? trim($_POST['clothes_title']) : '';
                $description = isset($_POST['clothes_description']) ? trim($_POST['clothes_description']) : '';
                $display_order = isset($_POST['clothes_order']) ? (int)$_POST['clothes_order'] : 0;
                
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
                
                $upload_dir = '../elements/clothes/';
                
                // 确保目录存在且可写
                if (!file_exists($upload_dir)) {
                    if (!@mkdir($upload_dir, 0777, true)) {
                        throw new Exception('无法创建上传目录，请检查目录权限');
                    }
                }
                
                if (!is_writable($upload_dir)) {
                    throw new Exception('上传目录没有写入权限，请检查目录权限');
                }
                
                // 使用简单命名，如果文件已存在则添加序号
                $base_filename = pathinfo($filename, PATHINFO_FILENAME); // 获取不带扩展名的原始文件名
                $base_filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $base_filename); // 替换不安全字符
                
                $new_filename = 'clothes_' . $base_filename . '.' . $ext;
                $counter = 1;
                
                // 如果文件已存在，添加序号
                while (file_exists($upload_dir . $new_filename)) {
                    $new_filename = 'clothes_' . $base_filename . '_' . $counter . '.' . $ext;
                    $counter++;
                }
                $upload_path = $upload_dir . $new_filename;
                
                if (!@move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $error = error_get_last();
                    throw new Exception('图片上传失败：' . ($error ? $error['message'] : '未知错误'));
                }
                
                // 验证文件是否成功上传
                if (!file_exists($upload_path)) {
                    throw new Exception('文件上传后未找到，请检查目录权限');
                }
                
                $relative_path = '/elements/clothes/' . $new_filename;
                
                // 保存衣装信息到数据库
                $stmt = $conn->prepare("INSERT INTO clothes_items (image_path, display_year, title, description, display_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$relative_path, $display_year, $title, $description, $display_order]);
                
                showToast('衣装已成功添加！');
            } else {
                throw new Exception('请选择要上传的衣装图片！');
            }
        }
        
        // 处理删除衣装项目
        if (isset($_POST['delete_clothes_item']) && isset($_POST['clothes_id'])) {
            $clothes_id = (int)$_POST['clothes_id'];
            
            // 获取衣装图片路径
            $stmt = $conn->prepare("SELECT image_path FROM clothes_items WHERE id = ?");
            $stmt->execute([$clothes_id]);
            $clothes = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($clothes) {
                $image_path = $clothes['image_path'];
                $full_path = $_SERVER['DOCUMENT_ROOT'] . $image_path;
                
                // 从数据库中删除记录
                $stmt = $conn->prepare("DELETE FROM clothes_items WHERE id = ?");
                $stmt->execute([$clothes_id]);
                
                // 尝试删除文件（如果存在）
                if (file_exists($full_path)) {
                    @unlink($full_path);
                }
                
                showToast('衣装项目已成功删除！');
            } else {
                throw new Exception('未找到指定的衣装项目！');
            }
        }
        
        // 处理更新衣装信息
        if (isset($_POST['update_clothes_item']) && isset($_POST['clothes_id'])) {
            $clothes_id = (int)$_POST['clothes_id'];
            $display_year = isset($_POST['edit_clothes_year']) ? trim($_POST['edit_clothes_year']) : '2024';
            $title = isset($_POST['edit_clothes_title']) ? trim($_POST['edit_clothes_title']) : '';
            // 确保描述字段被正确处理，即使为空
            $description = isset($_POST['edit_clothes_description']) ? $_POST['edit_clothes_description'] : '';
            $display_order = isset($_POST['edit_clothes_order']) ? (int)$_POST['edit_clothes_order'] : 0;
            
            $stmt = $conn->prepare("UPDATE clothes_items SET display_year = ?, title = ?, description = ?, display_order = ? WHERE id = ?");
            $stmt->execute([$display_year, $title, $description, $display_order, $clothes_id]);
            
            showToast('衣装信息已成功更新！');
            
            // 重定向到衣装列表页面
            header('Location: ?section=clothes');
            exit;
        }
        
        // 处理周表可见性更新
        if (isset($_POST['update_schedule_visibility'])) {
            $visibility = (isset($_POST['visibility']) && $_POST['visibility'] === '1') ? 1 : 0;
            $schedule_id = (int)$_POST['schedule_id'];
            
            $stmt = $conn->prepare("UPDATE schedule_image SET is_visible = ? WHERE id = ?");
            $stmt->execute([$visibility, $schedule_id]);
            
            showToast('周表显示状态已更新！');
            
            // 更新当前页面的状态
            $schedule_visible = $visibility;
        }
        
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 获取当前文件袋文本
$filebagText = '文件资料袋'; // 默认值
$stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = 'filebag_text'");
$stmt->execute();
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $filebagText = $row['content_value'];
}

// 获取当前描述文本
$descriptions = ['「认为思考有趣的问题比真正去做事更轻松很正常吧。」']; // 默认值
$stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = 'information_description'");
$stmt->execute();
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $descriptions = json_decode($row['content_value'], true);
    if (!is_array($descriptions)) {
        // 兼容旧数据 - 如果不是JSON数组，则转换为数组
        $descriptions = [$row['content_value']];
        // 更新数据库为新格式
        $descriptionsJson = json_encode($descriptions, JSON_UNESCAPED_UNICODE);
        $updateStmt = $conn->prepare("UPDATE site_content SET content_value = :description WHERE content_key = 'information_description'");
        $updateStmt->bindParam(':description', $descriptionsJson);
        $updateStmt->execute();
    }
}

// 获取当前周表图片
$stmt = $conn->prepare("SELECT id, image_path, is_visible FROM schedule_image ORDER BY id DESC LIMIT 1");
$stmt->execute();
$schedule_image = $stmt->fetch(PDO::FETCH_ASSOC);
$current_schedule_image = $schedule_image['image_path'] ?? '';
$schedule_visible = $schedule_image['is_visible'] ?? 1;
$schedule_id = $schedule_image['id'] ?? 0;

// 处理周表可见性更新
if (isset($_POST['update_schedule_visibility'])) {
    $visibility = (isset($_POST['visibility']) && $_POST['visibility'] === '1') ? 1 : 0;
    $schedule_id = (int)$_POST['schedule_id'];
    
    $stmt = $conn->prepare("UPDATE schedule_image SET is_visible = ? WHERE id = ?");
    $stmt->execute([$visibility, $schedule_id]);
    
    showToast('周表显示状态已更新！');
    
    // 更新当前页面的状态
    $schedule_visible = $visibility;
}

// 获取所有衣装项目
$clothes_items = [];
try {
    $stmt = $conn->prepare("SELECT * FROM clothes_items ORDER BY display_order ASC, id DESC");
    $stmt->execute();
    $clothes_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching clothes items: ' . $e->getMessage());
}

// 获取置顶动态的第一张图片（feed/space + draw.items，与 feed/all 列表结构不同）
$pinned_dynamic_image = '';
$pinned_dynamic_image_bust = '';
try {
    require_once '../includes/bilibili_dynamic.php';
    
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_mid'");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    $mid = $config && !empty($config['config_value']) ? $config['config_value'] : '2124647716';
    
    $biliDynamic = new BilibiliDynamic();
    $pinned_dynamic_image = $biliDynamic->getPinnedDynamicFirstImageUrl($mid);
    if ($pinned_dynamic_image !== '') {
        $pinned_dynamic_image_bust = substr(md5($pinned_dynamic_image), 0, 12);
    }
} catch (Exception $e) {
    $log_file = __DIR__ . '/../logs/admin_pinned_dynamic.log';
    @file_put_contents($log_file, date('Y-m-d H:i:s') . " 获取置顶动态失败: " . $e->getMessage() . "\n", FILE_APPEND);
}

// 设置页面标题
$page_title = "内容管理";

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

.nagisa-btn-danger {
    background: linear-gradient(45deg, #f87171, #ef4444);
    border: none;
    color: white;
    padding: 8px 12px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2);
    cursor: pointer;
}

.nagisa-btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(239, 68, 68, 0.3);
    background: linear-gradient(45deg, #fca5a5, #f87171);
}

.nagisa-btn-mini {
    padding: 5px 10px;
    font-size: 0.85rem;
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

.nagisa-toolbar {
    background: rgba(204, 148, 113, 0.05);
    border: 1px solid rgba(204, 148, 113, 0.2);
    border-radius: 8px;
    padding: 10px;
    margin-bottom: 16px;
}

.nagisa-toolbar-btn {
    padding: 5px 10px;
    border: 1px solid rgba(204, 148, 113, 0.3);
    border-radius: 4px;
    background: white;
    cursor: pointer;
    margin-right: 4px;
    margin-bottom: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    transition: all 0.2s ease;
}

.nagisa-toolbar-btn:hover {
    background: rgba(204, 148, 113, 0.1);
    border-color: rgba(204, 148, 113, 0.5);
}

.nagisa-toolbar-select {
    padding: 4px 8px;
    border: 1px solid rgba(204, 148, 113, 0.3);
    border-radius: 4px;
    background: white;
    cursor: pointer;
    margin-right: 4px;
    font-size: 12px;
}

.nagisa-toolbar-input {
    padding: 4px 8px;
    border: 1px solid rgba(204, 148, 113, 0.3);
    border-radius: 4px;
    background: white;
    margin-right: 4px;
    font-size: 12px;
}

.description-container {
    margin-bottom: 12px;
    position: relative;
}

.schedule-image-container {
    min-height: 200px;
    background-color: rgba(204, 148, 113, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(204, 148, 113, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-bottom: 20px;
}

.schedule-image-container img {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
}

.schedule-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #cc9471;
    opacity: 0.7;
    padding: 40px 20px;
}

.schedule-placeholder i {
    font-size: 3rem;
    margin-bottom: 10px;
}

@font-face {
    font-family: "STXINWEI";
    src: url("/assets/webfonts/STXINWEI.TTF") format("truetype");
    font-display: swap;
}

/* 衣装管理样式 */
.clothes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.clothes-item {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    position: relative;
}

.clothes-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.clothes-image-container {
    height: 250px;
    width: 100%;
    overflow: hidden;
    position: relative;
}

.clothes-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}

.clothes-info {
    padding: 15px;
}

.clothes-year {
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.5);
    color: #fff;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: bold;
}

.clothes-title {
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
}

.clothes-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
}

.clothes-preview-image {
    max-width: 100%;
    height: auto;
    max-height: 300px;
    margin: 10px 0;
    display: block;
}

/* 修改衣装卡片样式，使其更小且每行显示3个 */
.expression-grid#clothes-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 20px;
}

/* 固定布局 */
.expression-grid#clothes-grid {
    grid-template-columns: repeat(3, 1fr);
}

.expression-grid#clothes-grid .expression-item {
    max-width: 100%;
    position: relative;
    min-height: 300px;
}

.expression-grid#clothes-grid .expression-image {
    height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

.expression-grid#clothes-grid .expression-image img.thumbnail {
    max-height: 180px;
    width: auto;
    max-width: 100%;
    object-fit: contain;
}

.expression-grid#clothes-grid .clothes-year {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background: rgba(0, 0, 0, 0.6);
    color: #fff;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: bold;
}

.expression-grid#clothes-grid .expression-info {
    padding: 10px;
    display: flex;
    flex-direction: column;
    height: calc(100% - 180px);
}

.expression-grid#clothes-grid .expression-title {
    font-size: 0.95rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.expression-grid#clothes-grid .expression-actions {
    margin-top: 8px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    position: absolute;
    bottom: 10px;
    left: 10px;
    right: 10px;
}

/* 确保删除按钮位于右下角 */
.expression-grid#clothes-grid .expression-actions form {
    align-self: flex-end;
}

/* 搜索和过滤栏样式 */
.search-filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.search-input {
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    min-width: 200px;
    transition: all 0.3s ease;
}

.search-input:focus {
    border-color: #cc9471;
    box-shadow: 0 0 0 3px rgba(204, 148, 113, 0.1);
    outline: none;
}

.filter-select {
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background-color: white;
    min-width: 120px;
    transition: all 0.3s ease;
}

.filter-select:focus {
    border-color: #cc9471;
    box-shadow: 0 0 0 3px rgba(204, 148, 113, 0.1);
    outline: none;
}

/* 编辑模态框样式 */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-container {
    background-color: white;
    padding: 20px;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.modal-title {
    font-size: 1.2rem;
    font-weight: bold;
    color: #333;
}

.modal-close {
    cursor: pointer;
    font-size: 1.5rem;
}

/* 滑动开关样式 */
.switch {
  position: relative;
  display: inline-block;
  width: 60px;
  height: 30px;
}

.switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #ccc;
  transition: .4s;
}

.slider:before {
  position: absolute;
  content: "";
  height: 22px;
  width: 22px;
  left: 4px;
  bottom: 4px;
  background-color: white;
  transition: .4s;
}

input:checked + .slider {
  background-color: #4CAF50;  /* 绿色 */
}

input:focus + .slider {
  box-shadow: 0 0 1px #4CAF50;  /* 绿色 */
}

input:checked + .slider:before {
  transform: translateX(30px);
}

.slider.round {
  border-radius: 34px;
}

.slider.round:before {
  border-radius: 50%;
}

.expression-grid#clothes-grid .expression-content {
    margin-bottom: 40px; /* 为底部的操作按钮留出空间 */
}

.expression-grid#clothes-grid .expression-actions .nagisa-btn-mini,
.expression-grid#clothes-grid .expression-actions .nagisa-btn-danger {
    padding: 4px 8px;
    font-size: 0.8rem;
}

.expression-grid#clothes-grid .expression-actions .inline-block {
    font-size: 0.75rem;
    padding: 2px 5px;
}
';

// 包含统一页眉
include 'admin_header.php';

// AJAX请求处理代码已移除，改为直接页面跳转

// AJAX处理代码已移除，改为直接表单提交
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
        <!-- 侧边导航 -->
        <div class="md:col-span-3">
            <div class="nagisa-card">
                <h2 class="nagisa-card-header">内容管理</h2>
                <div class="p-4">
                    <ul class="space-y-1">
                        <li>
                            <a href="?section=schedule" class="nagisa-nav-link" onclick="showSection('schedule'); return false;">
                                <i class="fas fa-calendar-alt mr-2"></i>周表管理
                            </a>
                        </li>
                        <li>
                            <a href="?section=filebag" class="nagisa-nav-link" onclick="showSection('filebag'); return false;">
                                <i class="fas fa-folder-open mr-2"></i>文件袋文本
                            </a>
                        </li>
                        <li>
                            <a href="?section=cardword" class="nagisa-nav-link" onclick="showSection('cardword'); return false;">
                                <i class="fas fa-comment-alt mr-2"></i>卡片描述
                            </a>
                        </li>
                        <li>
                            <a href="?section=clothes" class="nagisa-nav-link" onclick="showSection('clothes'); return false;">
                                <i class="fas fa-tshirt mr-2"></i>衣装管理
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
                            <span>周表管理：上传和管理直播周表图片</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>文件袋文本：修改信息页面上文件袋显示的文本</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>个人卡片描述：管理首页个人卡片中随机显示的描述文本</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>衣装管理：管理衣装展示页面中的衣装项目</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>支持HTML格式和多种字体样式</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- 主要内容区域 -->
        <div class="md:col-span-9">
            <!-- 周表管理部分 -->
            <div id="schedule" class="nagisa-card section-content">
                <h2 class="nagisa-card-header">周表管理</h2>
                <div class="p-6">
                    <p class="mb-6 text-gray-600">
                        上传和管理直播周表图片，将显示在网站的黑板位置上。
                    </p>
                    
                    <!-- 当前周表图片 -->
                    <div class="nagisa-form-group">
                        <h3 class="nagisa-section-title">当前周表图片</h3>
                        <div class="schedule-image-container">
                            <?php if ($current_schedule_image): ?>
                                <img src="../<?php echo htmlspecialchars($current_schedule_image); ?>" alt="当前周表">
                            <?php else: ?>
                                <div class="schedule-placeholder">
                                    <i class="fas fa-calendar-alt"></i>
                                    <p>暂无周表图片</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($current_schedule_image): ?>
                        <div class="flex justify-between items-center mt-2">
                            <div>
                                <label class="switch">
                                    <input type="checkbox" id="schedule_visibility" <?php echo $schedule_visible ? 'checked' : ''; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <form method="POST" onsubmit="return confirm('确定要删除当前周表图片吗？此操作不可撤销！');">
                                <button type="submit" name="delete_schedule_image" class="nagisa-btn-danger">
                                    <i class="fas fa-trash mr-1"></i>删除当前图片
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 置顶动态图片预览 -->
                    <?php if ($pinned_dynamic_image): ?>
                    <div class="nagisa-form-group">
                        <h3 class="nagisa-section-title">置顶动态周表</h3>
                        <div class="schedule-image-container">
                            <img src="<?php echo htmlspecialchars($pinned_dynamic_image . ($pinned_dynamic_image_bust ? '?v=' . $pinned_dynamic_image_bust : '')); ?>" alt="置顶动态图片" referrerpolicy="no-referrer">
                        </div>
                        <div class="flex justify-between mt-2">
                            <p class="text-sm text-gray-500">可以将此图片用作周表</p>
                            <div>
                                <button type="button" onclick="saveToArchive()" class="nagisa-btn-secondary mr-2">
                                    <i class="fas fa-archive mr-1"></i>档案馆储存
                                </button>
                                <button type="button" onclick="downloadDynamicImage()" class="nagisa-btn-secondary mr-2">
                                    <i class="fas fa-download mr-1"></i>下载此图片
                                </button>
                                <button type="button" onclick="copyDynamicImage()" class="nagisa-btn-secondary">
                                    <i class="fas fa-copy mr-1"></i>使用此图片
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="nagisa-form-group">
                        <h3 class="nagisa-section-title">置顶动态周表</h3>
                        <div class="schedule-image-container">
                            <div class="schedule-placeholder">
                                <i class="fas fa-image"></i>
                                <p>未找到置顶动态图片</p>
                                <p class="text-xs mt-2">请确保有设置置顶动态且包含图片</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- 上传新周表 -->
                    <form method="POST" enctype="multipart/form-data">
                        <div class="nagisa-form-group">
                            <h3 class="nagisa-section-title">上传新周表</h3>
                            <label class="nagisa-label">
                                周表图片
                            </label>
                            <input type="file" 
                                   name="schedule_image" 
                                   id="schedule_image_upload"
                                   accept="image/*"
                                   class="nagisa-input">
                            <p class="mt-1 text-sm text-gray-500">
                                建议上传清晰的周表图片，将显示在网站的黑板位置上
                            </p>
                        </div>
                        
                        <div class="nagisa-preview-container hidden" id="schedule-preview-container">
                            <h3 class="nagisa-preview-title">预览</h3>
                            <div id="schedule-preview-image" class="w-full"></div>
                        </div>
                        
                        <div class="flex justify-end mt-4">
                            <button type="submit" name="update_schedule" class="nagisa-btn">
                                <i class="fas fa-save mr-2"></i>保存修改
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 文件袋文本部分 -->
            <div id="filebag" class="nagisa-card section-content" style="display: none;">
                <h2 class="nagisa-card-header">文件袋文本管理</h2>
                <div class="p-6">
                    <p class="mb-6 text-gray-600">
                        修改信息页面中文件袋上显示的文本内容。
                    </p>
                    
                    <form method="POST">
                        <!-- 文本编辑工具栏 -->
                        <div class="nagisa-toolbar">
                            <div class="flex flex-wrap items-center">
                                <button type="button" class="nagisa-toolbar-btn" onclick="insertTag('b')"><i class="fas fa-bold"></i></button>
                                <button type="button" class="nagisa-toolbar-btn" onclick="insertTag('i')"><i class="fas fa-italic"></i></button>
                                <button type="button" class="nagisa-toolbar-btn" onclick="insertTag('u')"><i class="fas fa-underline"></i></button>
                                <span class="mx-1 text-gray-300">|</span>
                                
                                <div class="flex items-center mr-2">
                                    <span class="text-xs mr-1">字号:</span>
                                    <input type="text" id="custom-size" class="nagisa-toolbar-input w-12" placeholder="1-7">
                                    <button type="button" class="nagisa-toolbar-btn ml-1" onclick="insertCustomFontSize()">应用</button>
                                </div>
                                
                                <div class="flex items-center mr-2">
                                    <span class="text-xs mr-1">颜色:</span>
                                    <input type="text" id="custom-color" class="nagisa-toolbar-input w-20" placeholder="#RRGGBB">
                                    <input type="color" id="color-picker" class="h-6 w-6 p-0 mx-1 cursor-pointer" onchange="document.getElementById('custom-color').value = this.value">
                                    <button type="button" class="nagisa-toolbar-btn ml-1" onclick="insertCustomFontColor()">应用</button>
                                </div>
                                
                                <span class="mx-1 text-gray-300">|</span>
                                
                                <div class="flex items-center">
                                    <span class="text-xs mr-1">字体:</span>
                                    <select id="font-family" class="nagisa-toolbar-select" onchange="insertFontFamily(this.value); this.selectedIndex = 0;">
                                        <option value="" selected disabled>选择字体</option>
                                        <option value="STXINWEI">华文新魏</option>
                                        <option value="SimSun">宋体</option>
                                        <option value="KaiTi">楷体</option>
                                        <option value="Microsoft YaHei">微软雅黑</option>
                                        <option value="SimHei">黑体</option>
                                        <option value="Arial">Arial</option>
                                        <option value="Times New Roman">Times New Roman</option>
                                        <option value="Courier New">Courier New</option>
                                    </select>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">选择文本后点击按钮来应用格式，或直接在文本中插入HTML标签</div>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label class="nagisa-label" for="filebag_text">
                                文件袋文本:
                            </label>
                            <textarea 
                                name="filebag_text" 
                                id="filebag_text"
                                rows="12"
                                class="nagisa-textarea"
                            ><?php echo htmlspecialchars($filebagText); ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">支持多行输入和HTML标签</p>
                        </div>

                        <div class="nagisa-preview-container">
                            <h3 class="nagisa-preview-title">文本预览</h3>
                            <div class="nagisa-preview-display">
                                <div class="font-medium text-lg" id="filebag-preview" style="font-family: 'STXINWEI', serif;"><?php echo $filebagText; ?></div>
                            </div>
                            <button type="button" class="nagisa-btn-secondary nagisa-btn-mini" onclick="updateFilebagPreview()">
                                <i class="fas fa-sync-alt mr-1"></i>刷新预览
                            </button>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button 
                                type="submit" 
                                name="update_filebag_text" 
                                class="nagisa-btn"
                            >
                                <i class="fas fa-save mr-2"></i>保存文本
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 个人描述部分 -->
            <div id="cardword" class="nagisa-card section-content" style="display: none;">
                <h2 class="nagisa-card-header">个人描述文本管理</h2>
                <div class="p-6">
                    <p class="mb-6 text-gray-600">
                        添加或修改首页个人卡片中显示的描述文本，每次刷新页面时将随机显示其中一条。
                    </p>
                    
                    <form method="POST">
                        <div id="descriptions-container">
                            <?php foreach ($descriptions as $index => $desc): ?>
                            <div class="description-container">
                                <div class="flex">
                                    <textarea 
                                        name="descriptions[]"
                                        rows="2"
                                        class="nagisa-textarea"
                                    ><?php echo htmlspecialchars($desc); ?></textarea>
                                    <button 
                                        type="button" 
                                        class="ml-2 nagisa-btn-danger"
                                        onclick="removeDescription(this)"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <button 
                                type="button" 
                                id="add-description" 
                                class="nagisa-btn-secondary"
                            >
                                <i class="fas fa-plus mr-1"></i> 添加新语句
                            </button>
                        </div>
                        
                        <div class="nagisa-preview-container">
                            <h3 class="nagisa-preview-title">随机预览</h3>
                            <div class="nagisa-preview-display">
                                <div class="font-medium text-lg" id="cardword-preview"></div>
                            </div>
                            <button 
                                type="button" 
                                id="random-preview" 
                                class="nagisa-btn-secondary"
                            >
                                <i class="fas fa-random mr-1"></i> 随机预览
                            </button>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button 
                                type="submit" 
                                name="update_descriptions" 
                                class="nagisa-btn"
                            >
                                <i class="fas fa-save mr-2"></i>保存所有语句
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- 衣装管理部分 -->
            <div id="clothes" class="nagisa-card section-content" style="display: none;">
                <h2 class="nagisa-card-header" style="background: linear-gradient(45deg, #e9967a, #cc9471);">衣装管理</h2>
                <div class="p-6">
                    <?php if ($section === 'clothes' && $action === 'edit' && $edit_clothes_data): ?>
                    <!-- 编辑衣装表单 -->
                    <div class="nagisa-form">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-medium text-gray-700">编辑衣装</h3>
                            <a href="?section=clothes" class="nagisa-btn-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>返回列表
                            </a>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="clothes_id" value="<?php echo $edit_clothes_data['id']; ?>">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">显示:</label>
                                        <input 
                                            type="text" 
                                            name="edit_clothes_year" 
                                            value="<?php echo htmlspecialchars($edit_clothes_data['display_year']); ?>" 
                                            class="nagisa-input"
                                        >
                                        <p class="text-xs text-gray-500 mt-1">显示在衣装下方的标记</p>
                                    </div>
                                    
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">衣装标题:</label>
                                        <input 
                                            type="text" 
                                            name="edit_clothes_title" 
                                            value="<?php echo htmlspecialchars($edit_clothes_data['title'] ?? ''); ?>" 
                                            class="nagisa-input"
                                        >
                                        <p class="text-xs text-gray-500 mt-1">可选，衣装的名称或标题</p>
                                    </div>
                                    
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">显示顺序:</label>
                                        <input 
                                            type="number" 
                                            name="edit_clothes_order" 
                                            value="<?php echo (int)$edit_clothes_data['display_order']; ?>" 
                                            class="nagisa-input"
                                        >
                                        <p class="text-xs text-gray-500 mt-1">数字越小排序越靠前</p>
                                    </div>
                                    
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">衣装描述:</label>
                                        <textarea 
                                            name="edit_clothes_description" 
                                            rows="4"
                                            class="nagisa-textarea"
                                        ><?php echo htmlspecialchars($edit_clothes_data['description'] ?? ''); ?></textarea>
                                        <p class="text-xs text-gray-500 mt-1">可选，对该衣装的简要描述</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">当前衣装图片</label>
                                        <div class="nagisa-preview-container">
                                            <div class="flex justify-center items-center bg-gray-50 rounded-lg p-4 min-h-[200px]">
                                                <img src="<?php echo htmlspecialchars($edit_clothes_data['image_path']); ?>" alt="当前图片" class="max-h-[250px] object-contain">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end mt-6 space-x-2">
                                <a href="?section=clothes" class="nagisa-btn-secondary">
                                    <i class="fas fa-times mr-2"></i>取消
                                </a>
                                <button type="submit" name="update_clothes_item" class="nagisa-btn">
                                    <i class="fas fa-save mr-2"></i>保存更改
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php else: ?>
                    <p class="mb-6 text-gray-600">
                        管理衣装展示页面中的衣装项目。上传新衣装图片、编辑衣装信息或删除不需要的衣装。
                    </p>
                    
                    <!-- 搜索和过滤栏 -->
                    <div class="search-filter-bar mb-6">
                        <div class="flex gap-2" style="min-width:300px;">
                            <input type="text" id="clothes-search" placeholder="搜索衣装..." class="search-input">
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="showAddClothesForm()" class="nagisa-btn">
                                <i class="fas fa-plus mr-2"></i>添加衣装
                            </button>
                        </div>
                    </div>
                    
                    <!-- 添加衣装表单 -->
                    <div id="add-clothes-form" class="nagisa-card" style="display: none; margin-bottom: 20px;">
                        <h3 class="nagisa-card-header" style="background: linear-gradient(45deg, #e9967a, #cc9471);">
                            <i class="fas fa-plus mr-2"></i>衣装管理
                        </h3>
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-xl font-medium text-gray-700" id="clothes-form-title">添加新衣装</h3>
                                <button type="button" onclick="hideAddClothesForm()" class="nagisa-btn-secondary">
                                    <i class="fas fa-arrow-left mr-2"></i>返回列表
                                </button>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label" for="clothes_year">显示:</label>
                                            <input 
                                                type="text" 
                                                name="clothes_year" 
                                                id="clothes_year" 
                                                value="2024" 
                                                class="nagisa-input"
                                            >
                                            <p class="text-xs text-gray-500 mt-1">显示在衣装下方的标记</p>
                                        </div>
                                        
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label" for="clothes_title">衣装标题:</label>
                                            <input 
                                                type="text" 
                                                name="clothes_title" 
                                                id="clothes_title" 
                                                class="nagisa-input"
                                            >
                                            <p class="text-xs text-gray-500 mt-1">可选，衣装的名称或标题</p>
                                        </div>
                                        
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label" for="clothes_order">显示顺序:</label>
                                            <input 
                                                type="number" 
                                                name="clothes_order" 
                                                id="clothes_order" 
                                                value="0" 
                                                class="nagisa-input"
                                            >
                                            <p class="text-xs text-gray-500 mt-1">数字越小排序越靠前</p>
                                        </div>
                                        
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label" for="clothes_description">衣装描述:</label>
                                            <textarea 
                                                name="clothes_description" 
                                                id="clothes_description" 
                                                rows="3"
                                                class="nagisa-textarea"
                                            ></textarea>
                                            <p class="text-xs text-gray-500 mt-1">可选，对该衣装的简要描述</p>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label" for="clothes_image">衣装图片:</label>
                                            <input 
                                                type="file" 
                                                name="clothes_image" 
                                                id="clothes_image_upload" 
                                                accept="image/*" 
                                                class="nagisa-input"
                                                required
                                                onchange="previewClothesImage(this)"
                                            >
                                            <p class="text-xs text-gray-500 mt-1">建议PNG格式，PS中画布6000x3600</p>
                                        </div>
                                        
                                        <div id="clothes-preview-container" class="nagisa-preview-container" style="display:none;">
                                            <h3 class="nagisa-preview-title">图片预览</h3>
                                            <div class="p-4 bg-gray-50 rounded-lg">
                                                <img id="clothes-preview-image" class="max-w-full max-h-80 mx-auto" src="" alt="预览">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end mt-6 space-x-2">
                                    <button type="button" onclick="hideAddClothesForm()" class="nagisa-btn-secondary">
                                        <i class="fas fa-times mr-2"></i>取消
                                    </button>
                                    <button type="submit" name="add_clothes_item" class="nagisa-btn">
                                        <i class="fas fa-plus mr-2"></i>添加衣装
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- 衣装列表 -->
                    <?php if (empty($clothes_items)): ?>
                    <div class="flex flex-col items-center justify-center py-10">
                        <div class="text-gray-400 mb-4 text-7xl">
                            <i class="fas fa-tshirt"></i>
                        </div>
                        <p class="text-gray-500 mb-6">暂无衣装，点击添加按钮上传新衣装</p>
                        <button type="button" onclick="showAddClothesForm()" class="nagisa-btn">
                            <i class="fas fa-plus mr-2"></i>添加衣装
                        </button>
                    </div>
                    <?php else: ?>
                    <!-- 衣装网格视图 -->
                    <div class="expression-grid" id="clothes-grid">
                        <?php foreach ($clothes_items as $clothes): ?>
                        <div class="expression-item clothes-item" 
                             data-id="<?php echo $clothes['id']; ?>" 
                             data-year="<?php echo htmlspecialchars($clothes['display_year']); ?>" 
                             data-title="<?php echo htmlspecialchars($clothes['title'] ?? ''); ?>"
                             data-order="<?php echo (int)$clothes['display_order']; ?>"
                             data-description="<?php echo htmlspecialchars($clothes['description'] ?? ''); ?>">
                            <div class="expression-image">
                                <img src="<?php echo htmlspecialchars($clothes['image_path']); ?>" alt="<?php echo htmlspecialchars($clothes['title'] ?? '衣装'); ?>" class="thumbnail">
                                <div class="clothes-year"><?php echo htmlspecialchars($clothes['display_year']); ?></div>
                            </div>
                            <div class="expression-info">
                                <div class="expression-content">
                                    <div class="expression-title" title="<?php echo htmlspecialchars($clothes['title'] ?? '无标题'); ?>">
                                        <?php echo htmlspecialchars($clothes['title'] ?? '无标题'); ?>
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1 mb-2 truncate">
                                        <?php echo htmlspecialchars(substr($clothes['description'] ?? '', 0, 30)); ?>
                                        <?php echo (strlen($clothes['description'] ?? '') > 30) ? '...' : ''; ?>
                                    </div>
                                </div>
                                
                                <div class="expression-actions">
                                    <div class="flex items-center">
                                        <button type="button" onclick="editClothes(<?php echo $clothes['id']; ?>)" class="nagisa-btn nagisa-btn-mini">
                                            <i class="fas fa-edit mr-1"></i>编辑
                                        </button>
                                        <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded ml-2">
                                            顺序: <?php echo (int)$clothes['display_order']; ?>
                                        </span>
                                    </div>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('确定要删除这个衣装吗？此操作不可恢复。')">
                                        <input type="hidden" name="clothes_id" value="<?php echo $clothes['id']; ?>">
                                        <button type="submit" name="delete_clothes_item" class="nagisa-btn-danger nagisa-btn-mini">
                                            <i class="fas fa-trash mr-1"></i>删除
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
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
    const activeLink = document.querySelector(`a[href="#${sectionId}"], a[href="?section=${sectionId}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }
    
    // 保存当前选中的模块到本地存储
    localStorage.setItem('content_admin_active_section', sectionId);
    
    // 更新URL参数（不刷新页面）
    const url = new URL(window.location);
    
    // 保留action和id参数
    const action = url.searchParams.get('action');
    const id = url.searchParams.get('id');
    
    // 设置section参数
    url.searchParams.set('section', sectionId);
    
    // 如果没有特定action，则移除action和id参数
    if (!action || action === 'list') {
        url.searchParams.delete('action');
        url.searchParams.delete('id');
    }
    
    window.history.replaceState({}, '', url);
}

// 获取当前应该显示的模块
function getCurrentSection() {
    // 首先检查URL参数
    const urlParams = new URLSearchParams(window.location.search);
    const urlSection = urlParams.get('section');
    
    // 然后检查本地存储
    const storedSection = localStorage.getItem('content_admin_active_section');
    
    // 最后使用默认值
    const defaultSection = 'schedule';
    
    // 验证section是否有效
    const validSections = ['schedule', 'filebag', 'cardword', 'clothes'];
    const section = urlSection || storedSection || defaultSection;
    
    return validSections.includes(section) ? section : defaultSection;
}

// 文件袋文本编辑功能
const filebagTextarea = document.getElementById('filebag_text');
const filebagPreview = document.getElementById('filebag-preview');

// 在文本区域中插入标签
function insertTag(tag) {
    const start = filebagTextarea.selectionStart;
    const end = filebagTextarea.selectionEnd;
    const selectedText = filebagTextarea.value.substring(start, end);
    const replacement = '<' + tag + '>' + selectedText + '</' + tag + '>';
    
    filebagTextarea.value = filebagTextarea.value.substring(0, start) + replacement + filebagTextarea.value.substring(end);
    updateFilebagPreview();
    
    // 重新定位光标
    filebagTextarea.focus();
    filebagTextarea.setSelectionRange(start + 2 + tag.length + selectedText.length, start + 2 + tag.length + selectedText.length);
}

// 插入自定义字体大小
function insertCustomFontSize() {
    const size = document.getElementById('custom-size').value.trim();
    if (!size || isNaN(size) || size < 1 || size > 7) {
        alert('请输入1-7之间的数字');
        return;
    }
    
    const start = filebagTextarea.selectionStart;
    const end = filebagTextarea.selectionEnd;
    const selectedText = filebagTextarea.value.substring(start, end);
    const replacement = '<font size="' + size + '">' + selectedText + '</font>';
    
    filebagTextarea.value = filebagTextarea.value.substring(0, start) + replacement + filebagTextarea.value.substring(end);
    updateFilebagPreview();
    
    filebagTextarea.focus();
}

// 插入自定义字体颜色
function insertCustomFontColor() {
    const color = document.getElementById('custom-color').value.trim();
    if (!color) {
        alert('请输入有效的颜色值');
        return;
    }
    
    const start = filebagTextarea.selectionStart;
    const end = filebagTextarea.selectionEnd;
    const selectedText = filebagTextarea.value.substring(start, end);
    const replacement = '<font color="' + color + '">' + selectedText + '</font>';
    
    filebagTextarea.value = filebagTextarea.value.substring(0, start) + replacement + filebagTextarea.value.substring(end);
    updateFilebagPreview();
    
    filebagTextarea.focus();
}

// 插入字体族
function insertFontFamily(family) {
    if (!family) return;
    
    const start = filebagTextarea.selectionStart;
    const end = filebagTextarea.selectionEnd;
    const selectedText = filebagTextarea.value.substring(start, end);
    const replacement = '<span style="font-family: \'' + family + '\';">' + selectedText + '</span>';
    
    filebagTextarea.value = filebagTextarea.value.substring(0, start) + replacement + filebagTextarea.value.substring(end);
    updateFilebagPreview();
    
    filebagTextarea.focus();
}

// 更新文件袋预览区域
function updateFilebagPreview() {
    filebagPreview.innerHTML = filebagTextarea.value;
}

// 个人描述文本功能
// 添加新描述文本输入框
document.getElementById('add-description').addEventListener('click', function() {
    const container = document.getElementById('descriptions-container');
    const newItem = document.createElement('div');
    newItem.className = 'description-container';
    newItem.innerHTML = `
        <div class="flex">
            <textarea 
                name="descriptions[]"
                rows="2"
                class="nagisa-textarea"
            ></textarea>
            <button 
                type="button" 
                class="ml-2 nagisa-btn-danger"
                onclick="removeDescription(this)"
            >
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(newItem);
});

// 删除描述文本输入框
function removeDescription(button) {
    const itemToRemove = button.closest('.description-container');
    if (document.querySelectorAll('.description-container').length > 1) {
        itemToRemove.remove();
    } else {
        alert('至少需要保留一条描述文本！');
    }
}

// 随机预览功能
document.getElementById('random-preview').addEventListener('click', function() {
    const inputs = document.querySelectorAll('textarea[name="descriptions[]"]');
    const validInputs = Array.from(inputs).filter(input => input.value.trim() !== '');
    
    if (validInputs.length > 0) {
        const randomIndex = Math.floor(Math.random() * validInputs.length);
        document.getElementById('cardword-preview').innerText = validInputs[randomIndex].value;
    } else {
        document.getElementById('cardword-preview').innerText = '请至少添加一条描述文本';
    }
});

// 周表图片上传预览
document.getElementById('schedule_image_upload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const previewContainer = document.getElementById('schedule-preview-container');
    const previewImage = document.getElementById('schedule-preview-image');
    
    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewContainer.classList.remove('hidden');
            previewImage.innerHTML = `<img src="${e.target.result}" alt="预览" class="max-w-full mx-auto">`;
        }
        
        reader.readAsDataURL(file);
    } else {
        previewContainer.classList.add('hidden');
        previewImage.innerHTML = '';
    }
});

// 文件袋文本处理Enter键
filebagTextarea.addEventListener('keydown', function(e) {
    // 检查是否按下了Enter键
    if (e.key === 'Enter') {
        // 阻止默认行为（插入换行符）
        e.preventDefault();
        
        // 在当前光标位置插入<br>标签
        const start = this.selectionStart;
        const end = this.selectionEnd;
        
        // 插入<br>标签
        this.value = this.value.substring(0, start) + '<br>' + this.value.substring(end);
        
        // 更新预览
        updateFilebagPreview();
        
        // 将光标移到<br>后面
        this.selectionStart = this.selectionEnd = start + 4;
    }
});

// 监听粘贴事件，处理粘贴的文本中的换行符
filebagTextarea.addEventListener('paste', function(e) {
    // 让粘贴操作先完成
    setTimeout(() => {
        // 自动转换文本中的换行符为<br>标签
        convertNewlinesToBr();
    }, 0);
});

// 监听输入事件，处理可能输入的换行符
filebagTextarea.addEventListener('input', function(e) {
    // 自动转换文本中的换行符为<br>标签
    convertNewlinesToBr();
});

// 将文本中的\n换行符转换为<br>标签
function convertNewlinesToBr() {
    const currentText = filebagTextarea.value;
    if (currentText.includes('\n')) {
        // 保存当前光标位置
        const currentPos = filebagTextarea.selectionStart;
        
        // 计算新增的<br>标签数量（用于调整光标位置）
        const newlines = currentText.split('\n').length - 1;
        const brTagLength = 4; // <br> 的长度
        const additionalLength = newlines * (brTagLength - 1); // 每个\n被替换为<br>后增加的长度
        
        // 替换\n为<br>
        filebagTextarea.value = currentText.replace(/\n/g, '<br>');
        
        // 调整光标位置
        filebagTextarea.selectionStart = filebagTextarea.selectionEnd = currentPos + additionalLength;
        
        // 更新预览
        updateFilebagPreview();
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 获取当前应该显示的模块
    const currentSection = getCurrentSection();
    
    // 显示对应的模块
    showSection(currentSection);
    
    // 初始化文件袋文本功能
    convertNewlinesToBr();
    updateFilebagPreview();
    
    // 初始化个人描述预览
    document.getElementById('random-preview').click();
    
    // 添加表单提交后的处理
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            // 保存当前模块状态
            const currentSection = getCurrentSection();
            localStorage.setItem('content_admin_active_section', currentSection);
        });
    });
    
    // 搜索功能 - 衣装
    const clothesSearch = document.getElementById('clothes-search');
    
    if (clothesSearch) {
        clothesSearch.addEventListener('input', filterClothes);
    }
});

// 监听浏览器前进后退按钮
window.addEventListener('popstate', function() {
    const currentSection = getCurrentSection();
    showSection(currentSection);
});

<?php if ($pinned_dynamic_image): ?>
// 置顶动态图为 B 站等外链时，浏览器直接 fetch 原 URL 会因 CORS 失败；经本站后台同源脚本代理拉取字节。
const PINNED_SCHEDULE_IMAGE_URL = <?php echo json_encode($pinned_dynamic_image, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

function fetchPinnedScheduleImageBlob() {
    const fd = new FormData();
    fd.append('image_url', PINNED_SCHEDULE_IMAGE_URL);
    return fetch('fetch_remote_schedule_image.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    }).then(function (response) {
        if (!response.ok) {
            return response.text().then(function (text) {
                throw new Error(text || ('HTTP ' + response.status));
            });
        }
        return response.blob();
    });
}

function pinnedScheduleFileName(blob) {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    const dateStr = y + m + d;
    let ext = 'jpg';
    const t = (blob && blob.type) ? blob.type.toLowerCase() : '';
    if (t.indexOf('png') !== -1) {
        ext = 'png';
    } else if (t.indexOf('webp') !== -1) {
        ext = 'webp';
    } else if (t.indexOf('gif') !== -1) {
        ext = 'gif';
    }
    return 'Schedule_' + dateStr + '.' + ext;
}
<?php endif; ?>

// 复制动态图片到周表上传组件
function copyDynamicImage() {
    <?php if ($pinned_dynamic_image): ?>
    fetchPinnedScheduleImageBlob()
        .then(function (blob) {
            const input = document.getElementById('schedule_image_upload');
            if (!input) {
                showRightUpToast('未找到周表上传控件', 'error');
                return;
            }
            const fileName = pinnedScheduleFileName(blob);
            const file = new File([blob], fileName, { type: blob.type || 'image/jpeg' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            input.files = dataTransfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            const prevBox = document.getElementById('schedule-preview-container');
            if (prevBox) {
                prevBox.scrollIntoView({ behavior: 'smooth' });
            }
            showRightUpToast('已加载到上传区域，请点击保存修改', 'success');
        })
        .catch(function (error) {
            console.error('复制动态图片失败:', error);
            showRightUpToast('加载失败，请稍后重试或手动下载上传', 'error');
        });
    <?php endif; ?>
}

// 下载动态图片（仅下载到本地，不上传服务器）
function downloadDynamicImage() {
    <?php if ($pinned_dynamic_image): ?>
    fetchPinnedScheduleImageBlob()
        .then(function (blob) {
            const fileName = pinnedScheduleFileName(blob);
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
            showRightUpToast('图片已下载', 'success');
        })
        .catch(function () {
            showRightUpToast('下载失败，请稍后再试', 'error');
        });
    <?php endif; ?>
}

// 档案馆储存功能（保留图片原始格式，不强制转换为jpg）
function saveToArchive() {
    <?php if ($pinned_dynamic_image): ?>
    try {
        // 通过 fetch 获取图片 Blob 以获取正确的 MIME 类型和扩展名
        fetchPinnedScheduleImageBlob()
            .then(function (blob) {
                const ext = pinnedScheduleFileName(blob).replace(/^.*\./, ''); // 从 blob 推断扩展名
                const today = new Date();
                const y = today.getFullYear();
                const m = String(today.getMonth() + 1).padStart(2, '0');
                const d = String(today.getDate()).padStart(2, '0');
                const fileName = 'Schedule_' + y + m + d + '.' + ext;

                showRightUpToast('正在保存到档案馆...', 'info');

                const formData = new FormData();
                formData.append('image_url', PINNED_SCHEDULE_IMAGE_URL);
                formData.append('file_name', fileName);
                formData.append('action', 'save_schedule_image');

                return fetch('save_schedule_image.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.text().then(function (text) {
                        var data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            showRightUpToast('保存失败: 服务器返回异常（请检查 PHP 错误日志）', 'error');
                            return;
                        }
                        if (!response.ok) {
                            showRightUpToast('保存失败: ' + (data.message || ('HTTP ' + response.status)), 'error');
                            return;
                        }
                        if (data.success) {
                            showRightUpToast('已成功保存到档案馆', 'success');
                        } else {
                            showRightUpToast('保存失败: ' + (data.message || '未知错误'), 'error');
                        }
                    });
                });
            })
            .catch(function () {
                showRightUpToast('保存失败: 网络异常', 'error');
            });
    } catch (error) {
        showRightUpToast('操作失败，请稍后再试', 'error');
    }
    <?php endif; ?>
}

// 衣装图片上传预览
document.getElementById('clothes_image_upload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const previewContainer = document.getElementById('clothes-preview-container');
    const previewImage = document.getElementById('clothes-preview-image');
    
    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewContainer.classList.remove('hidden');
            previewImage.innerHTML = `<img src="${e.target.result}" alt="预览" class="clothes-preview-image mx-auto">`;
        }
        
        reader.readAsDataURL(file);
    } else {
        previewContainer.classList.add('hidden');
        previewImage.innerHTML = '';
    }
});

// 编辑衣装功能已改为直接表单提交方式

// 处理周表可见性开关
const visibilitySwitch = document.getElementById('schedule_visibility');
if (visibilitySwitch) {
    visibilitySwitch.addEventListener('change', function() {
        // 创建表单并提交
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const scheduleId = document.createElement('input');
        scheduleId.name = 'schedule_id';
        scheduleId.value = '<?php echo $schedule_id; ?>';
        
        const visibility = document.createElement('input');
        visibility.name = 'visibility';
        visibility.value = this.checked ? '1' : '0';
        
        const action = document.createElement('input');
        action.name = 'update_schedule_visibility';
        action.value = '1';
        
        form.appendChild(scheduleId);
        form.appendChild(visibility);
        form.appendChild(action);
        
        document.body.appendChild(form);
        form.submit();
    });
}

// 使用AJAX加载衣装编辑表单
function loadClothesEditForm(id) {
    // 直接跳转到编辑页面
    location.href = `?section=clothes&action=edit&id=${id}`;
}

// 隐藏衣装编辑表单，返回列表
function hideClothesEditForm() {
    // 刷新当前页面，但不跳转
    location.href = '?section=clothes';
}

// 保存衣装编辑
function saveClothesEdit(event) {
    // 此函数已不再使用，改为直接表单提交
    event.preventDefault();
    console.log('此函数已不再使用，改为直接表单提交');
    // 提交表单
    event.target.submit();
}

// Nagisa Admin Toast（Top-right light toast / 右上角轻提示，与 song_all.php 的 showToast 对齐）；规格与命名见 ../../docs/NAGISA_TOP_RIGHT_LIGHT_TOAST.md
function showToastJS(message, type = 'success') {
    // 创建Toast元素
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg transform transition-all duration-300 ease-in-out translate-y-0 opacity-0`;
    
    // 设置Toast样式
    if (type === 'success') {
        toast.classList.add('bg-green-500', 'text-white');
        toast.innerHTML = `<i class="fas fa-check-circle mr-2"></i>${message}`;
    } else if (type === 'error') {
        toast.classList.add('bg-red-500', 'text-white');
        toast.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${message}`;
    } else if (type === 'warning') {
        toast.classList.add('bg-yellow-500', 'text-white');
        toast.innerHTML = `<i class="fas fa-exclamation-triangle mr-2"></i>${message}`;
    }
    
    // 添加到页面
    document.body.appendChild(toast);
    
    // 显示Toast
    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-0');
        toast.classList.add('translate-y-4', 'opacity-100');
    }, 10);
    
    // 3秒后隐藏Toast
    setTimeout(() => {
        toast.classList.remove('translate-y-4', 'opacity-100');
        toast.classList.add('translate-y-0', 'opacity-0');
        
        // 动画结束后移除元素
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// 处理周表可见性开关
// ... existing code ...

// 衣装管理相关函数
// 预览衣装图片
function previewClothesImage(input) {
    const previewContainer = document.getElementById('clothes-preview-container');
    const previewImage = document.getElementById('clothes-preview-image');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewContainer.style.display = 'block';
            previewImage.src = e.target.result;
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        previewContainer.style.display = 'none';
        previewImage.src = '';
    }
}

// 显示添加衣装表单
function showAddClothesForm() {
    document.getElementById('add-clothes-form').style.display = 'block';
    
    // 隐藏衣装列表，但保持主容器可见
    const clothesGrid = document.getElementById('clothes-grid');
    if (clothesGrid) {
        clothesGrid.style.display = 'none';
    }
    
    // 隐藏空衣装提示
    const emptyMessage = document.querySelector('#clothes .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'none';
    }
    
    // 重置表单
    const form = document.querySelector('#add-clothes-form form');
    if (form) {
        form.reset();
    }
    
    // 设置表单标题和按钮为"添加"模式
    document.getElementById('clothes-form-title').textContent = '添加新衣装';
    const submitBtn = document.querySelector('#add-clothes-form button[type="submit"]');
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>添加衣装';
        submitBtn.name = 'add_clothes_item';
    }
    
    // 隐藏预览
    document.getElementById('clothes-preview-container').style.display = 'none';
    document.getElementById('clothes-preview-image').src = '';
}

// 隐藏添加衣装表单
function hideAddClothesForm() {
    document.getElementById('add-clothes-form').style.display = 'none';
    
    // 显示衣装列表或空衣装提示
    const clothesGrid = document.getElementById('clothes-grid');
    if (clothesGrid) {
        clothesGrid.style.display = 'grid';
    }
    
    const emptyMessage = document.querySelector('#clothes .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'flex';
    }
}

// 编辑衣装
function editClothes(id) {
    // 直接跳转到编辑页面
    location.href = `?section=clothes&action=edit&id=${id}`;
}

// 过滤衣装
function filterClothes() {
    const searchInput = document.getElementById('clothes-search');
    const grid = document.getElementById('clothes-grid');
    
    if (!grid) return;
    
    const items = grid.querySelectorAll('.clothes-item');
    const searchValue = searchInput ? searchInput.value.toLowerCase() : '';
    
    items.forEach(item => {
        const title = item.getAttribute('data-title').toLowerCase();
        
        // 检查是否匹配搜索文本
        const matchesSearch = title.includes(searchValue);
        
        if (matchesSearch) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>

<?php
// 引入管理后台页脚
require_once 'admin_footer.php';
?> 