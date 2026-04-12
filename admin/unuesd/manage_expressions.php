<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/toast.php';

// 检查管理员登录状态
checkAdminAuth();

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

// 初始化变量
$error = '';
$success = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$type = isset($_GET['type']) ? $_GET['type'] : 'expression';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 检查表情包表是否存在，如果不存在则创建
$stmt = $conn->prepare("SHOW TABLES LIKE 'expression_images'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    $conn->exec("CREATE TABLE expression_images (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        image_path VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT 'emotion',
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

// 检查语音表是否存在，如果不存在则创建
$stmt = $conn->prepare("SHOW TABLES LIKE 'expression_audios'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    $conn->exec("CREATE TABLE expression_audios (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        audio_path VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT 'greeting',
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

// 处理表情包/音频上传
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 处理表单逻辑
        if (isset($_POST['add_expression']) || isset($_POST['update_expression'])) {
            // 获取表单数据
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
            // 对于语音，只取第一个选中的分类（单选）
            if ($type === 'audio') {
                $category = !empty($categories) ? $categories[0] : '';
            } else {
                // 对于表情，保持多选逻辑
                $category = !empty($categories) ? implode(',', $categories) : '';
            }
            $status = isset($_POST['status']) ? 1 : 0;
            $isUpdate = isset($_POST['update_expression']);
            $itemId = $isUpdate ? intval($_POST['item_id']) : 0;
            
            // 验证表单数据
            if (empty($title)) {
                throw new Exception('标题不能为空');
            } else {
                // 处理文件上传
                $file_uploaded = false;
                $file_path = '';
                
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['file']['tmp_name'];
                    $file_name = $_FILES['file']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // 验证文件类型
                    $allowed_extensions = $type === 'expression' ? 
                        ['jpg', 'jpeg', 'png', 'gif', 'webp'] : 
                        ['mp3', 'wav', 'ogg'];
                    
                    if (!in_array($file_ext, $allowed_extensions)) {
                        throw new Exception('不支持的文件类型');
                    } else {
                        // 构建上传路径
                        $upload_dir = $type === 'expression' ? '../assets/expressions/' : '../assets/audios/';
                        
                        // 确保目录存在
                        if (!file_exists($upload_dir)) {
                            if (!mkdir($upload_dir, 0777, true)) {
                                throw new Exception('无法创建目录: ' . $upload_dir);
                            }
                            // 确保目录权限正确
                            chmod($upload_dir, 0777);
                        } else if (!is_writable($upload_dir)) {
                            // 如果目录存在但不可写，尝试修改权限
                            chmod($upload_dir, 0777);
                            if (!is_writable($upload_dir)) {
                                throw new Exception('上传目录没有写入权限，请联系管理员修改目录权限: ' . $upload_dir);
                            }
                        }
                        
                        // 生成唯一文件名
                        $unique_name = time() . '_' . md5($file_name) . '.' . $file_ext;
                        $upload_path = $upload_dir . $unique_name;
                        
                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            $file_uploaded = true;
                            $file_path = str_replace('../', '/', $upload_path); // 存储相对路径
                        } else {
                            $error = error_get_last();
                            $error_message = $error ? $error['message'] : '未知错误';
                            $log_message = date('Y-m-d H:i:s') . " 上传失败: {$error_message}, 路径: {$upload_path}\n";
                            error_log($log_message, 3, "../logs/upload_errors.log");
                            throw new Exception("文件上传失败: {$error_message}。请联系管理员查看日志。");
                        }
                    }
                }
                
                // 更新数据库
                $table = $type === 'expression' ? 'expression_images' : 'expression_audios';
                $path_field = $type === 'expression' ? 'image_path' : 'audio_path';
                
                if ($isUpdate) {
                    // 更新现有记录
                    if ($file_uploaded) {
                        // 如果上传了新文件，先获取旧文件路径
                        $stmt = $conn->prepare("SELECT $path_field FROM $table WHERE id = ?");
                        $stmt->execute([$itemId]);
                        $old_path = $stmt->fetchColumn();
                        
                        // 删除旧文件
                        if ($old_path && file_exists('../' . $old_path)) {
                            unlink('../' . $old_path);
                        }
                        
                        // 更新记录包括新文件路径
                        $stmt = $conn->prepare("UPDATE $table SET title = ?, description = ?, $path_field = ?, category = ?, status = ? WHERE id = ?");
                        $stmt->execute([$title, $description, $file_path, $category, $status, $itemId]);
                    } else {
                        // 不更新文件路径
                        $stmt = $conn->prepare("UPDATE $table SET title = ?, description = ?, category = ?, status = ? WHERE id = ?");
                        $stmt->execute([$title, $description, $category, $status, $itemId]);
                    }
                    showToast('更新成功！');
                } else {
                    // 添加新记录
                    if (!$file_uploaded) {
                        throw new Exception('请上传文件');
                    } else {
                        $stmt = $conn->prepare("INSERT INTO $table (title, description, $path_field, category, status) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$title, $description, $file_path, $category, $status]);
                        showToast('添加成功！');
                    }
                }
            }
        }
        
        // 处理标签管理请求
        if (isset($_POST['action']) && in_array($_POST['action'], ['add_tag', 'edit_tag', 'delete_tag'])) {
            $action = $_POST['action'];
            $type = $_POST['type'] ?? '';
            $response = ['success' => false, 'message' => ''];
            
            try {
                if (empty($type)) {
                    throw new Exception('缺少类型参数');
                }
                
                $table = $type === 'expression' ? 'expression_images' : 'expression_audios';
                
                switch ($action) {
                    case 'add_tag':
                        $tagName = trim($_POST['tag_name'] ?? '');
                        $tagDescription = trim($_POST['tag_description'] ?? '');
                        
                        if (empty($tagName)) {
                            throw new Exception('标签名称不能为空');
                        }
                        
                        // 检查标签是否已存在（通过创建一个虚拟记录来验证）
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE category = ?");
                        $stmt->execute([$tagName]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception('标签已存在');
                        }
                        
                        // 由于当前系统没有独立的标签表，我们通过创建一个临时记录来"保存"标签
                        // 这个记录会在后续被删除，但标签名称会被保留在分类中
                        if ($type === 'expression') {
                            $stmt = $conn->prepare("INSERT INTO $table (title, description, category, status, image_path) VALUES (?, ?, ?, 0, '/assets/expressions/temp.png')");
                        } else {
                            $stmt = $conn->prepare("INSERT INTO $table (title, description, category, status, audio_path) VALUES (?, ?, ?, 0, '/assets/audios/temp.mp3')");
                        }
                        $stmt->execute(['TEMP_TAG_' . $tagName, $tagDescription, $tagName]);
                        
                        $response = ['success' => true, 'message' => '标签添加成功', 'tag_name' => $tagName];
                        break;
                        
                    case 'edit_tag':
                        $oldName = trim($_POST['old_name'] ?? '');
                        $newName = trim($_POST['new_name'] ?? '');
                        
                        if (empty($oldName) || empty($newName)) {
                            throw new Exception('标签名称不能为空');
                        }
                        
                        if ($oldName === $newName) {
                            throw new Exception('新名称与原名称相同');
                        }
                        
                        // 更新所有使用该分类的记录
                        $stmt = $conn->prepare("UPDATE $table SET category = ? WHERE category = ?");
                        $stmt->execute([$newName, $oldName]);
                        
                        $response = ['success' => true, 'message' => '标签更新成功'];
                        break;
                        
                    case 'delete_tag':
                        $tagName = trim($_POST['tag_name'] ?? '');
                        
                        if (empty($tagName)) {
                            throw new Exception('标签名称不能为空');
                        }
                        
                        // 检查是否有记录使用该标签
                        $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE category = ? AND status = 1");
                        $stmt->execute([$tagName]);
                        $count = $stmt->fetchColumn();
                        
                        if ($count > 0) {
                            // 将使用该标签的记录分类改为默认分类
                            $defaultCategory = $type === 'expression' ? 'emotion' : 'greeting';
                            $stmt = $conn->prepare("UPDATE $table SET category = ? WHERE category = ? AND status = 1");
                            $stmt->execute([$defaultCategory, $tagName]);
                            $response = ['success' => true, 'message' => "标签已删除，{$count}个记录已移至默认分类"];
                        } else {
                            $response = ['success' => true, 'message' => '标签删除成功'];
                        }
                        
                        // 删除临时标签记录
                        $stmt = $conn->prepare("DELETE FROM $table WHERE category = ? AND status = 0 AND title LIKE 'TEMP_TAG_%'");
                        $stmt->execute([$tagName]);
                        break;
                        
                    default:
                        throw new Exception('未知操作');
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => $e->getMessage()];
            }
            
            // 返回JSON响应
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        // 处理删除请求
        if (isset($_POST['delete_item'])) {
            $itemId = intval($_POST['item_id']);
            $itemType = $_POST['item_type'];
            
            $table = $itemType === 'expression' ? 'expression_images' : 'expression_audios';
            $path_field = $itemType === 'expression' ? 'image_path' : 'audio_path';
            
            // 获取文件路径
            $stmt = $conn->prepare("SELECT $path_field FROM $table WHERE id = ?");
            $stmt->execute([$itemId]);
            $file_path = $stmt->fetchColumn();
            
            // 删除文件
            if ($file_path && file_exists('../' . $file_path)) {
                unlink('../' . $file_path);
            }
            
            // 删除数据库记录
            $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$itemId]);
            
            showToast('删除成功！');
        }
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 获取表情包数据
$expressions = [];
try {
    $stmt = $conn->query("SELECT * FROM expression_images WHERE title NOT LIKE 'TEMP_TAG_%' ORDER BY id DESC");
    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    showToast('获取表情包数据失败: ' . $e->getMessage(), 'error');
}

// 获取音频数据
$audios = [];
try {
    $stmt = $conn->query("SELECT * FROM expression_audios WHERE title NOT LIKE 'TEMP_TAG_%' ORDER BY id DESC");
    $audios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    showToast('获取音频数据失败: ' . $e->getMessage(), 'error');
}

// 获取现有分类
$expression_categories = [];
$audio_categories = [];

// 从表情数据中获取分类
foreach ($expressions as $expr) {
    if (!empty($expr['category'])) {
        $cats = explode(',', $expr['category']);
        foreach ($cats as $cat) {
            $cat = trim($cat);
            if (!empty($cat) && !in_array($cat, $expression_categories)) {
                $expression_categories[] = $cat;
            }
        }
    }
}

// 从音频数据中获取分类
foreach ($audios as $audio) {
    if (!empty($audio['category']) && !in_array($audio['category'], $audio_categories)) {
        $audio_categories[] = $audio['category'];
    }
}

// 从临时标签记录中获取分类（这些是用户添加的标签）
try {
    $stmt = $conn->query("SELECT DISTINCT category FROM expression_images WHERE title LIKE 'TEMP_TAG_%' AND status = 0");
    $temp_expression_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($temp_expression_categories as $cat) {
        if (!in_array($cat, $expression_categories)) {
            $expression_categories[] = $cat;
        }
    }
} catch (PDOException $e) {
    // 忽略错误
}

try {
    $stmt = $conn->query("SELECT DISTINCT category FROM expression_audios WHERE title LIKE 'TEMP_TAG_%' AND status = 0");
    $temp_audio_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($temp_audio_categories as $cat) {
        if (!in_array($cat, $audio_categories)) {
            $audio_categories[] = $cat;
        }
    }
} catch (PDOException $e) {
    // 忽略错误
}

// 确保分类数组不为空，至少包含默认分类
if (empty($expression_categories)) {
    $expression_categories[] = 'emotion';
}
if (empty($audio_categories)) {
    $audio_categories[] = 'greeting';
}



// 如果是编辑模式，获取对应的记录
$edit_data = null;
if ($action === 'edit' && $id > 0) {
    try {
        $table = $type === 'expression' ? 'expression_images' : 'expression_audios';
        $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        showToast('获取编辑数据失败: ' . $e->getMessage(), 'error');
    }
}

// 设置页面标题
$page_title = "管理";

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

.nagisa-card .nagisa-nav-link {
    display: block;
    padding: 10px 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
    margin-bottom: 4px;
    font-weight: 500;
    color: #64748b;
}

.nagisa-card .nagisa-nav-link.active {
    background: rgba(204, 148, 113, 0.1);
    color: #cc9471;
    border-left: 3px solid #cc9471;
    padding-left: 12px;
    font-weight: 500;
}

.nagisa-card .nagisa-nav-link:hover {
    background: rgba(204, 148, 113, 0.05);
    color: #cc9471;
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

.admin-upload-preview {
    width: 100px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 8px;
    border: 1px solid rgba(204, 148, 113, 0.2);
    background-color: #f9f3ee;
}

.admin-upload-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.expression-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.expression-item {
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(204, 148, 113, 0.1);
    transition: all 0.3s ease;
    position: relative;
    border: 1px solid rgba(204, 148, 113, 0.2);
}

.expression-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(204, 148, 113, 0.2);
}

.expression-image {
    height: 160px;
    width: 100%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9f3ee;
}

.expression-image img {
    max-height: 140px;
    max-width: 90%;
    object-fit: contain;
}

.expression-audio {
    padding: 15px;
    background: #f9f3ee;
}

.expression-info {
    padding: 15px;
}

.expression-title {
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
    font-size: 1.1rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.expression-category {
    margin-bottom: 10px;
}

.expression-status {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: #10b981;
}

.expression-status.disabled {
    background-color: #ef4444;
}

.expression-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
}

.search-filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.search-input {
    flex: 1;
    max-width: 400px;
    padding: 8px 12px;
    border: 2px solid rgba(204, 148, 113, 0.3);
    border-radius: 8px;
    outline: none;
    transition: all 0.3s ease;
}

.search-input:focus {
    border-color: #cc9471;
    box-shadow: 0 0 0 3px rgba(204, 148, 113, 0.2);
}

.filter-select {
    padding: 8px 12px;
    border: 2px solid rgba(204, 148, 113, 0.3);
    border-radius: 8px;
    outline: none;
    transition: all 0.3s ease;
    background-color: white;
}

.filter-select:focus {
    border-color: #cc9471;
    box-shadow: 0 0 0 3px rgba(204, 148, 113, 0.2);
}

.badge {
    display: inline-block;
    padding: 0.25em 0.6em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 10px;
}

.badge-success {
    color: #fff;
    background-color: #10b981;
}

.badge-danger {
    color: #fff;
    background-color: #ef4444;
}

.sort-buttons {
    display: flex;
    gap: 10px;
}

.sort-button {
    padding: 5px 10px;
    border: 1px solid rgba(204, 148, 113, 0.3);
    border-radius: 4px;
    background: white;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s ease;
}

.sort-button:hover, .sort-button.active {
    background: rgba(204, 148, 113, 0.1);
    border-color: rgba(204, 148, 113, 0.5);
}

/* 滑动开关样式 */
.switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 24px;
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
  border-radius: 24px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 16px;
  width: 16px;
  left: 4px;
  bottom: 4px;
  background-color: white;
  transition: .4s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: #10b981;
}

input:focus + .slider {
  box-shadow: 0 0 1px #10b981;
}

input:checked + .slider:before {
  transform: translateX(26px);
}

/* 表情项中的滑动开关 */
.expression-toggle {
  /* 移除绝对定位，现在在flex布局中 */
}

.expression-toggle .switch {
  width: 40px;
  height: 20px;
}

.expression-toggle .slider:before {
  height: 14px;
  width: 14px;
  left: 3px;
  bottom: 3px;
}

.expression-toggle input:checked + .slider:before {
  transform: translateX(20px);
}

/* 修改开关颜色为绿色 */
.expression-toggle input:checked + .slider {
  background-color: #10b981;
}

.expression-toggle input:focus + .slider {
  box-shadow: 0 0 1px #10b981;
}

/* 分类复选框样式 */
.category-checkbox-item {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  background: white;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
  overflow: hidden;
  height: 40px;
}

.category-checkbox-item:hover {
  border-color: #10b981;
  background: #f8fafc;
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(16, 185, 129, 0.1);
}

.category-checkbox-item input[type="checkbox"],
.category-checkbox-item input[type="radio"] {
  position: absolute;
  opacity: 0;
  cursor: pointer;
}

.category-checkbox-item input[type="checkbox"]:checked ~ .category-label,
.category-checkbox-item input[type="radio"]:checked ~ .category-label {
  color: #ffffff;
  font-weight: 600;
  background: linear-gradient(45deg, #10b981, #059669);
  border-radius: 6px;
  padding: 8px 12px;
  text-align: center;
  position: absolute;
  left: 0;
  top: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.category-checkbox-item input[type="checkbox"]:checked ~ .category-label::before,
.category-checkbox-item input[type="radio"]:checked ~ .category-label::before {
  content: "✓";
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #ffffff;
  font-weight: bold;
  font-size: 14px;
}

.category-label {
  display: block;
  width: 100%;
  padding-left: 0;
  color: #374151;
  font-size: 14px;
  transition: all 0.2s ease;
  position: relative;
  text-align: center;
}

.category-checkbox-item input[type="checkbox"]:checked ~ .category-label::before,
.category-checkbox-item input[type="radio"]:checked ~ .category-label::before {
  content: "";
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #ffffff;
  font-weight: bold;
  font-size: 14px;
}

/* 分类容器样式 */
#category-checkboxes, #audio-category-checkboxes {
  scrollbar-width: thin;
  scrollbar-color: #10b981 #e5e7eb;
}

#category-checkboxes::-webkit-scrollbar, #audio-category-checkboxes::-webkit-scrollbar {
  width: 6px;
}

#category-checkboxes::-webkit-scrollbar-track, #audio-category-checkboxes::-webkit-scrollbar-track {
  background: #e5e7eb;
  border-radius: 3px;
}

#category-checkboxes::-webkit-scrollbar-thumb, #audio-category-checkboxes::-webkit-scrollbar-thumb {
  background: #10b981;
  border-radius: 3px;
}

#category-checkboxes::-webkit-scrollbar-thumb:hover, #audio-category-checkboxes::-webkit-scrollbar-thumb:hover {
  background: #059669;
}
    
    /* 前台页面链接样式 */
    .frontend-link {
      background-color: #7d7068 !important;
      color: white !important;
      border: none !important;
    }
    
    .frontend-link:hover {
      background-color: #8e7f76 !important;
    }
    
    a.frontend-link:hover {
      background-color: #8e7f76 !important;
      transform: translateY(-2px);
    }
';

include 'admin_header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
        <!-- 侧边导航 -->
        <div class="md:col-span-3">
            <div class="nagisa-card">
                <h2 class="nagisa-card-header">管理</h2>
                <div class="p-4">
                    <ul class="space-y-1">
                        <li>
                            <a href="?type=expression&action=list" class="nagisa-nav-link<?php if($type==='expression'){echo ' active';} ?>" onclick="changeType('expression'); return false;">
                                <i class="fas fa-smile mr-2"></i>表情管理
                            </a>
                        </li>
                        <li>
                            <a href="?type=audio&action=list" class="nagisa-nav-link<?php if($type==='audio'){echo ' active';} ?>" onclick="changeType('audio'); return false;">
                                <i class="fas fa-music mr-2"></i>语音管理
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="nagisa-card mt-6">
                <h2 class="nagisa-card-header">使用说明</h2>
                <div class="p-4">
                    <ul class="space-y-2 text-gray-600 text-sm">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>表情管理：上传和管理表情包图片，支持JPG、PNG、GIF等格式</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>语音管理：上传和管理语音文件，支持MP3、WAV、OGG等格式</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>分类管理：可为表情和音频设置不同分类，便于在展示页面筛选</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>状态控制：可以单独设置每个资源的启用/禁用状态</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- 前台页面链接 -->
            <div class="nagisa-card mt-6">
                <h2 class="nagisa-card-header">前台页面</h2>
                <div class="p-4">
                    <a href="javascript:void(0)" onclick="viewFrontend()" class="w-full text-center block py-2" style="background-color: #a0a0a0; color: white; border-radius: 8px; padding: 10px 16px; font-weight: 600; box-shadow: 0 4px 6px rgba(160, 160, 160, 0.2); border: none;">
                        <i class="fas fa-external-link-alt mr-2"></i>查看当前页面
                    </a>
                </div>
            </div>
        </div>
        
        <!-- 主要内容区域 -->
        <div class="md:col-span-9">
            <!-- 表情管理部分 -->
            <div id="expression-section" class="nagisa-card section-content" style="<?php if($type!=='expression'){echo 'display:none;';} ?>">
                <h2 class="nagisa-card-header">表情包管理</h2>
                <div class="p-6">
                    <?php if ($action === 'list'): ?>
                        <!-- 搜索和过滤栏 -->
                        <div class="search-filter-bar">
                            <div class="flex gap-2" style="min-width:300px;">
                                <input type="text" id="expression-search" placeholder="搜索表情..." class="search-input">
                                                                                <select id="expression-category-filter" class="filter-select">
                                                    <option value="all">所有分类</option>
                                                    <?php foreach($expression_categories as $category): ?>
                                                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="showTagManager()" class="nagisa-btn" style="background: linear-gradient(45deg, #8b5cf6, #a855f7);">
                                    <i class="fas fa-tags mr-2"></i>标签管理
                                </button>
                                <a href="?type=expression&action=add" class="nagisa-btn">
                                    <i class="fas fa-plus mr-2"></i>添加表情
                                </a>
                            </div>
                        </div>

                        <!-- 标签管理界面 -->
                        <div id="tag-manager-section" class="nagisa-card" style="display: none; margin-bottom: 20px;">
                            <h3 class="nagisa-card-header" style="background: linear-gradient(45deg, #8b5cf6, #a855f7);">
                                <i class="fas fa-tags mr-2"></i>标签管理
                            </h3>
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <div>
                                        <h4 class="text-xl font-medium text-gray-700">表情分类标签管理</h4>
                                        <p class="text-sm text-gray-500 mt-1">管理表情包的分类标签，方便用户筛选和查找</p>
                                    </div>
                                    <button type="button" onclick="hideTagManager()" class="nagisa-btn-secondary">
                                        <i class="fas fa-times mr-2"></i>关闭管理
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- 添加新标签 -->
                                    <div class="nagisa-card">
                                        <h5 class="nagisa-card-header" style="background: linear-gradient(45deg, #10b981, #059669);">
                                            <i class="fas fa-plus mr-2"></i>添加新标签
                                        </h5>
                                        <div class="p-4">
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">标签名称 <span class="text-red-500">*</span></label>
                                                <input type="text" id="new-tag-name" class="nagisa-input" placeholder="例如：可爱、搞笑、悲伤...">
                                                <p class="text-xs text-gray-500 mt-1">为表情包设置分类标签</p>
                                            </div>
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">标签描述</label>
                                                <textarea id="new-tag-description" class="nagisa-textarea" rows="3" placeholder="标签的详细描述（可选）"></textarea>
                                                <p class="text-xs text-gray-500 mt-1">可选，用于说明标签的用途</p>
                                            </div>
                                            <button type="button" onclick="addNewTag()" class="nagisa-btn w-full" style="background: linear-gradient(45deg, #10b981, #059669);">
                                                <i class="fas fa-plus mr-2"></i>添加标签
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- 现有标签列表 -->
                                    <div class="nagisa-card">
                                        <h5 class="nagisa-card-header" style="background: linear-gradient(45deg, #3b82f6, #2563eb);">
                                            <i class="fas fa-list mr-2"></i>现有标签
                                        </h5>
                                        <div class="p-4">
                                            <div id="existing-tags-list" class="space-y-3">
                                                <?php if (empty($expression_categories)): ?>
                                                <div class="text-center py-8 text-gray-500">
                                                    <i class="fas fa-tags text-4xl mb-3"></i>
                                                    <p>暂无标签，请添加新标签</p>
                                                </div>
                                                <?php else: ?>
                                                <?php foreach($expression_categories as $category): ?>
                                                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200">
                                                    <div class="flex items-center">
                                                        <div class="w-3 h-3 bg-purple-500 rounded-full mr-3"></div>
                                                        <div>
                                                            <span class="font-medium text-gray-700"><?php echo htmlspecialchars($category); ?></span>
                                                            <span class="text-xs text-gray-500 ml-2 bg-gray-200 px-2 py-1 rounded-full">分类标签</span>
                                                        </div>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <button type="button" onclick="editTag('<?php echo htmlspecialchars($category); ?>')" class="nagisa-btn nagisa-btn-mini" style="background: linear-gradient(45deg, #f59e0b, #d97706);">
                                                            <i class="fas fa-edit mr-1"></i>编辑
                                                        </button>
                                                        <button type="button" onclick="deleteTag('<?php echo htmlspecialchars($category); ?>')" class="nagisa-btn-danger nagisa-btn-mini">
                                                            <i class="fas fa-trash mr-1"></i>删除
                                                        </button>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 使用说明 -->
                                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <h6 class="font-medium text-blue-800 mb-2">
                                        <i class="fas fa-info-circle mr-2"></i>使用说明
                                    </h6>
                                    <ul class="text-sm text-blue-700 space-y-1">
                                        <li>• 标签用于对表情包进行分类，方便用户筛选</li>
                                        <li>• 添加标签后，可以在表情编辑时选择使用</li>
                                        <li>• 删除标签不会影响已存在的表情包</li>
                                        <li>• 建议使用简洁明了的标签名称</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($expressions)): ?>
                        <div class="flex flex-col items-center justify-center py-10">
                            <div class="text-gray-400 mb-4 text-7xl">
                                <i class="fas fa-images"></i>
                            </div>
                            <p class="text-gray-500 mb-6">暂无表情包，点击添加按钮上传新表情</p>
                            <a href="?type=expression&action=add" class="nagisa-btn">
                                <i class="fas fa-plus mr-2"></i>添加表情
                            </a>
                        </div>
                        <?php else: ?>
                        <!-- 表情网格视图 -->
                        <div class="expression-grid" id="expression-grid">
                            <?php foreach ($expressions as $expr): ?>
                            <div class="expression-item" data-id="<?php echo $expr['id']; ?>" data-category="<?php echo htmlspecialchars($expr['category']); ?>" data-title="<?php echo htmlspecialchars($expr['title']); ?>">
                                <div class="expression-image">
                                    <img src="../<?php echo htmlspecialchars($expr['image_path']); ?>" alt="<?php echo htmlspecialchars($expr['title']); ?>">
                                </div>
                                <div class="expression-info">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="expression-title" title="<?php echo htmlspecialchars($expr['title']); ?>"><?php echo htmlspecialchars($expr['title']); ?></div>
                                        <div class="expression-toggle">
                                            <label class="switch">
                                                <input type="checkbox" class="status-toggle" data-id="<?php echo $expr['id']; ?>" data-type="expression" <?php echo $expr['status'] ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                                                                        <div class="expression-category">
                                                        <?php 
                                                        // 只显示基本标签（GIF、ANI、LIVE等），不显示复合标签（如GIF,ANI）
                                                        $basicTags = ['GIF', 'ANI', 'LIVE'];
                                                        $categories = explode(',', $expr['category']);
                                                        foreach ($categories as $cat) {
                                                            $cat = trim($cat);
                                                            if (in_array(strtoupper($cat), $basicTags)) {
                                                                echo '<span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-1 mb-1">' . htmlspecialchars($cat) . '</span>';
                                                            }
                                                        }
                                                        ?>
                                    </div>
                                    <div class="expression-actions">
                                        <a href="?type=expression&action=edit&id=<?php echo $expr['id']; ?>" class="nagisa-btn nagisa-btn-mini"><i class="fas fa-edit mr-1"></i>编辑</a>
                                        <form method="post" style="display:inline" onsubmit="return confirm('确定要删除这个表情包吗？此操作不可恢复。')">
                                            <input type="hidden" name="item_id" value="<?php echo $expr['id']; ?>">
                                            <input type="hidden" name="item_type" value="expression">
                                            <button type="submit" name="delete_item" class="nagisa-btn-danger nagisa-btn-mini"><i class="fas fa-trash mr-1"></i>删除</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    
                    <?php elseif ($action === 'add' || $action === 'edit'): ?>
                    <!-- 添加/编辑表情表单 -->
                    <div class="nagisa-form">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-medium text-gray-700"><?php echo $action === 'add' ? '添加新表情' : '编辑表情'; ?></h3>
                            <a href="?type=expression&action=list" class="nagisa-btn-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>返回列表
                            </a>
                        </div>
                        
                        <form method="post" enctype="multipart/form-data">
                            <?php if ($action === 'edit' && $edit_data): ?>
                            <input type="hidden" name="item_id" value="<?php echo $edit_data['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">表情标题 <span class="text-red-500">*</span></label>
                                        <input type="text" class="nagisa-input" name="title" required value="<?php echo $edit_data ? htmlspecialchars($edit_data['title']) : ''; ?>">
                                        <p class="text-xs text-gray-500 mt-1">给表情起一个简短明了的名称</p>
                                    </div>
                                    
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">分类标签</label>
                                        <div class="flex gap-3">
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-sm font-medium text-gray-700">选择分类</span>
                                                </div>
                                                <div id="category-checkboxes" class="border-2 border-gray-200 rounded-xl p-4 max-h-40 overflow-y-auto bg-gray-50 hover:border-gray-300 transition-colors relative">
                                                    <?php if (empty($expression_categories)): ?>
                                                    <div class="text-center py-4 text-gray-500">
                                                        <i class="fas fa-tags text-2xl mb-2"></i>
                                                        <p class="text-sm">暂无分类，请先创建分类</p>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="grid grid-cols-2 gap-3">
                                                        <?php foreach ($expression_categories as $cat): ?>
                                                        <label class="category-checkbox-item">
                                                            <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($cat); ?>" class="category-checkbox" 
                                                                <?php 
                                                                if ($edit_data) {
                                                                    $edit_categories = explode(',', $edit_data['category']);
                                                                    echo in_array($cat, $edit_categories) ? 'checked' : '';
                                                                } else {
                                                                    echo ($cat === 'emotion') ? 'checked' : '';
                                                                }
                                                                ?>>
                                                            <span class="category-label"><?php echo htmlspecialchars($cat); ?></span>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                    <div class="flex justify-center mt-4">
                                                        <button type="button" onclick="showNewCategoryInput()" class="nagisa-btn px-4 py-2" style="background: linear-gradient(45deg, #8b5cf6, #a855f7); white-space: nowrap;">
                                                            <i class="fas fa-plus mr-2"></i>新建分类
                                                        </button>
                                                    </div>
                                            </div>
                                        </div>
                                        <div id="new-category-input" class="mt-3 p-4 bg-gradient-to-r from-purple-50 to-blue-50 border border-purple-200 rounded-xl" style="display: none;">
                                            <div class="flex items-center gap-3">
                                                <div class="flex-1">
                                                    <input type="text" id="new-category-name" class="nagisa-input" placeholder="输入新分类名称">
                                                </div>
                                                <button type="button" onclick="addNewCategory()" class="nagisa-btn px-4 py-2" style="background: linear-gradient(45deg, #10b981, #059669);">
                                                    <i class="fas fa-check mr-2"></i>添加
                                                </button>
                                                <button type="button" onclick="hideNewCategoryInput()" class="nagisa-btn-secondary px-4 py-2">
                                                    <i class="fas fa-times mr-2"></i>取消
                                                </button>
                                            </div>
                                        </div>

                                        <p class="text-xs text-gray-500 mt-2">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            为表情设置一个或多个分类，方便用户筛选和查找
                                        </p>
                                    </div>
                                    
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">描述</label>
                                        <textarea class="nagisa-textarea" name="description" rows="4"><?php echo $edit_data ? htmlspecialchars($edit_data['description']) : ''; ?></textarea>
                                        <p class="text-xs text-gray-500 mt-1">可选，添加表情的详细描述</p>
                                    </div>
                                    
                                    <div class="nagisa-form-group">
                                        <label class="flex items-center">
                                            <span class="mr-2">启用表情</span>
                                            <label class="switch">
                                                <input type="checkbox" id="status" name="status" <?php echo ($edit_data && $edit_data['status'] == 1) || !$edit_data ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                        </label>
                                        <p class="text-xs text-gray-500 mt-1">未启用的表情不会在前台页面显示</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">表情图片<?php echo $action === 'add' ? ' <span class="text-red-500">*</span>' : '（可选，不上传则保持原图片）'; ?></label>
                                        <input type="file" class="nagisa-input" id="expression_file" name="file" <?php echo $action === 'add' ? 'required' : ''; ?> accept="image/*">
                                        <p class="text-xs text-gray-500 mt-1">支持JPG、PNG、GIF等常见图片格式</p>
                                    </div>
                                    
                                    <div class="nagisa-preview-container" id="expression-preview-container" style="<?php echo $action === 'edit' && $edit_data ? '' : 'display:none;'; ?>">
                                        <h3 class="nagisa-preview-title">图片预览</h3>
                                        <div id="expression-preview-image" class="flex justify-center items-center bg-gray-50 rounded-lg p-4 min-h-[200px]">
                                            <?php if ($action === 'edit' && $edit_data): ?>
                                            <img src="../<?php echo htmlspecialchars($edit_data['image_path']); ?>" alt="当前图片" class="max-h-[250px] object-contain">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end mt-6 space-x-2">
                                <a href="?type=expression&action=list" class="nagisa-btn-secondary">
                                    <i class="fas fa-times mr-2"></i>取消
                                </a>
                                <button type="submit" name="<?php echo $action === 'add' ? 'add_expression' : 'update_expression'; ?>" class="nagisa-btn">
                                    <i class="fas <?php echo $action === 'add' ? 'fa-plus' : 'fa-save'; ?> mr-2"></i><?php echo $action === 'add' ? '添加' : '更新'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 语音管理部分 -->
            <div id="audio-section" class="nagisa-card section-content" style="<?php if($type!=='audio'){echo 'display:none;';} ?>">
                <h2 class="nagisa-card-header">语音管理</h2>
                <div class="p-6">
                    <?php if ($action === 'list'): ?>
                        <!-- 搜索和过滤栏 -->
                        <div class="search-filter-bar">
                            <div class="flex gap-2" style="min-width:300px;">
                                <input type="text" id="audio-search" placeholder="搜索语音..." class="search-input">
                                <select id="audio-category-filter" class="filter-select">
                                    <option value="all">所有分类</option>
                                    <?php foreach($audio_categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="showAudioTagManager()" class="nagisa-btn" style="background: linear-gradient(45deg, #8b5cf6, #a855f7);">
                                    <i class="fas fa-tags mr-2"></i>标签管理
                                </button>
                                <a href="?type=audio&action=add" class="nagisa-btn">
                                    <i class="fas fa-plus mr-2"></i>添加语音
                                </a>
                            </div>
                        </div>

                        <!-- 语音标签管理界面 -->
                        <div id="audio-tag-manager-section" class="nagisa-card" style="display: none; margin-bottom: 20px;">
                            <h3 class="nagisa-card-header" style="background: linear-gradient(45deg, #8b5cf6, #a855f7);">
                                <i class="fas fa-tags mr-2"></i>语音标签管理
                            </h3>
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <div>
                                        <h4 class="text-xl font-medium text-gray-700">语音分类标签管理</h4>
                                        <p class="text-sm text-gray-500 mt-1">管理语音文件的分类标签，方便用户筛选和查找</p>
                                    </div>
                                    <button type="button" onclick="hideAudioTagManager()" class="nagisa-btn-secondary">
                                        <i class="fas fa-times mr-2"></i>关闭管理
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- 添加新标签 -->
                                    <div class="nagisa-card">
                                        <h5 class="nagisa-card-header" style="background: linear-gradient(45deg, #10b981, #059669);">
                                            <i class="fas fa-plus mr-2"></i>添加新标签
                                        </h5>
                                        <div class="p-4">
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">标签名称 <span class="text-red-500">*</span></label>
                                                <input type="text" id="new-audio-tag-name" class="nagisa-input" placeholder="例如：问候、祝福、搞笑...">
                                                <p class="text-xs text-gray-500 mt-1">为语音文件设置分类标签</p>
                                            </div>
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">标签描述</label>
                                                <textarea id="new-audio-tag-description" class="nagisa-textarea" rows="3" placeholder="标签的详细描述（可选）"></textarea>
                                                <p class="text-xs text-gray-500 mt-1">可选，用于说明标签的用途</p>
                                            </div>
                                            <button type="button" onclick="addNewAudioTag()" class="nagisa-btn w-full" style="background: linear-gradient(45deg, #10b981, #059669);">
                                                <i class="fas fa-plus mr-2"></i>添加标签
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- 现有标签列表 -->
                                    <div class="nagisa-card">
                                        <h5 class="nagisa-card-header" style="background: linear-gradient(45deg, #3b82f6, #2563eb);">
                                            <i class="fas fa-list mr-2"></i>现有标签
                                        </h5>
                                        <div class="p-4">
                                            <div id="existing-audio-tags-list" class="space-y-3">
                                                <?php if (empty($audio_categories)): ?>
                                                <div class="text-center py-8 text-gray-500">
                                                    <i class="fas fa-tags text-4xl mb-3"></i>
                                                    <p>暂无标签，请添加新标签</p>
                                                </div>
                                                <?php else: ?>
                                                <?php foreach($audio_categories as $category): ?>
                                                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200">
                                                    <div class="flex items-center">
                                                        <div class="w-3 h-3 bg-purple-500 rounded-full mr-3"></div>
                                                        <div>
                                                            <span class="font-medium text-gray-700"><?php echo htmlspecialchars($category); ?></span>
                                                            <span class="text-xs text-gray-500 ml-2 bg-gray-200 px-2 py-1 rounded-full">分类标签</span>
                                                        </div>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <button type="button" onclick="editAudioTag('<?php echo htmlspecialchars($category); ?>')" class="nagisa-btn nagisa-btn-mini" style="background: linear-gradient(45deg, #f59e0b, #d97706);">
                                                            <i class="fas fa-edit mr-1"></i>编辑
                                                        </button>
                                                        <button type="button" onclick="deleteAudioTag('<?php echo htmlspecialchars($category); ?>')" class="nagisa-btn-danger nagisa-btn-mini">
                                                            <i class="fas fa-trash mr-1"></i>删除
                                                        </button>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 使用说明 -->
                                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <h6 class="font-medium text-blue-800 mb-2">
                                        <i class="fas fa-info-circle mr-2"></i>使用说明
                                    </h6>
                                    <ul class="text-sm text-blue-700 space-y-1">
                                        <li>• 标签用于对语音文件进行分类，方便用户筛选</li>
                                        <li>• 添加标签后，可以在语音编辑时选择使用</li>
                                        <li>• 删除标签不会影响已存在的语音文件</li>
                                        <li>• 建议使用简洁明了的标签名称</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($audios)): ?>
                        <div class="flex flex-col items-center justify-center py-10">
                            <div class="text-gray-400 mb-4 text-7xl">
                                <i class="fas fa-music"></i>
                            </div>
                            <p class="text-gray-500 mb-6">暂无语音文件，点击添加按钮上传新语音</p>
                            <a href="?type=audio&action=add" class="nagisa-btn">
                                <i class="fas fa-plus mr-2"></i>添加语音
                            </a>
                        </div>
                        <?php else: ?>
                        <!-- 语音网格视图 -->
                        <div class="expression-grid" id="audio-grid">
                            <?php foreach ($audios as $audio): ?>
                            <div class="expression-item" data-id="<?php echo $audio['id']; ?>" data-category="<?php echo htmlspecialchars($audio['category']); ?>" data-title="<?php echo htmlspecialchars($audio['title']); ?>">
                                <div class="expression-audio">
                                    <audio controls class="w-full h-10">
                                        <source src="../<?php echo htmlspecialchars($audio['audio_path']); ?>" type="audio/mpeg">
                                        您的浏览器不支持音频播放
                                    </audio>
                                </div>
                                <div class="expression-info">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="expression-title" title="<?php echo htmlspecialchars($audio['title']); ?>"><?php echo htmlspecialchars($audio['title']); ?></div>
                                        <div class="expression-toggle">
                                            <label class="switch">
                                                <input type="checkbox" class="status-toggle" data-id="<?php echo $audio['id']; ?>" data-type="audio" <?php echo $audio['status'] ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="expression-category">
                                        <?php 
                                        $categories = explode(',', $audio['category']);
                                        foreach ($categories as $cat) {
                                            echo '<span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-1 mb-1">' . htmlspecialchars(trim($cat)) . '</span>';
                                        }
                                        ?>
                                    </div>
                                    <div class="expression-actions">
                                        <a href="?type=audio&action=edit&id=<?php echo $audio['id']; ?>" class="nagisa-btn nagisa-btn-mini"><i class="fas fa-edit mr-1"></i>编辑</a>
                                        <form method="post" style="display:inline" onsubmit="return confirm('确定要删除这个语音吗？此操作不可恢复。')">
                                            <input type="hidden" name="item_id" value="<?php echo $audio['id']; ?>">
                                            <input type="hidden" name="item_type" value="audio">
                                            <button type="submit" name="delete_item" class="nagisa-btn-danger nagisa-btn-mini"><i class="fas fa-trash mr-1"></i>删除</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    
                    <?php elseif ($action === 'add' || $action === 'edit'): ?>
                    <!-- 添加/编辑语音表单 -->
                    <div class="nagisa-form">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-medium text-gray-700"><?php echo $action === 'add' ? '添加新语音' : '编辑语音'; ?></h3>
                            <a href="?type=audio&action=list" class="nagisa-btn-secondary">
                                <i class="fas fa-arrow-left mr-2"></i>返回列表
                            </a>
                        </div>
                        
                        <form method="post" enctype="multipart/form-data">
                            <?php if ($action === 'edit' && $edit_data): ?>
                            <input type="hidden" name="item_id" value="<?php echo $edit_data['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">语音标题 <span class="text-red-500">*</span></label>
                                        <input type="text" class="nagisa-input" name="title" required value="<?php echo $edit_data ? htmlspecialchars($edit_data['title']) : ''; ?>">
                                        <p class="text-xs text-gray-500 mt-1">给语音起一个简短明了的名称</p>
                                    </div>
                                    
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">分类标签</label>
                                        <div class="flex gap-3">
                                            <div class="flex-1">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-sm font-medium text-gray-700">选择分类</span>
                                                </div>
                                                <div id="audio-category-checkboxes" class="border-2 border-gray-200 rounded-xl p-4 max-h-40 overflow-y-auto bg-gray-50 hover:border-gray-300 transition-colors relative">
                                                    <?php if (empty($audio_categories)): ?>
                                                    <div class="text-center py-4 text-gray-500">
                                                        <i class="fas fa-tags text-2xl mb-2"></i>
                                                        <p class="text-sm">暂无分类，请先创建分类</p>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="grid grid-cols-2 gap-3">
                                                        <?php foreach ($audio_categories as $cat): ?>
                                                        <label class="category-checkbox-item">
                                                            <input type="radio" name="categories[]" value="<?php echo htmlspecialchars($cat); ?>" class="category-checkbox" 
                                                                <?php 
                                                                if ($edit_data) {
                                                                    // 对于语音，直接比较分类名称（单选）
                                                                    echo ($cat === $edit_data['category']) ? 'checked' : '';
                                                                } else {
                                                                    echo ($cat === 'greeting') ? 'checked' : '';
                                                                }
                                                                ?>>
                                                            <span class="category-label"><?php echo htmlspecialchars($cat); ?></span>
                                                        </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex justify-center mt-4">
                                                    <button type="button" onclick="showNewAudioCategoryInput()" class="nagisa-btn px-4 py-2" style="background: linear-gradient(45deg, #8b5cf6, #a855f7); white-space: nowrap;">
                                                        <i class="fas fa-plus mr-2"></i>新建分类
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="new-audio-category-input" class="mt-3 p-4 bg-gradient-to-r from-purple-50 to-blue-50 border border-purple-200 rounded-xl" style="display: none;">
                                            <div class="flex items-center gap-3">
                                                <div class="flex-1">
                                                    <input type="text" id="new-audio-category-name" class="nagisa-input" placeholder="输入新分类名称">
                                                </div>
                                                <button type="button" onclick="addNewAudioCategory()" class="nagisa-btn px-4 py-2" style="background: linear-gradient(45deg, #10b981, #059669);">
                                                    <i class="fas fa-check mr-2"></i>添加
                                                </button>
                                                <button type="button" onclick="hideNewAudioCategoryInput()" class="nagisa-btn-secondary px-4 py-2">
                                                    <i class="fas fa-times mr-2"></i>取消
                                                </button>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            为语音设置一个或多个分类，方便用户筛选和查找
                                        </p>
                                    </div>
                                    
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">描述</label>
                                        <textarea class="nagisa-textarea" name="description" rows="4"><?php echo $edit_data ? htmlspecialchars($edit_data['description']) : ''; ?></textarea>
                                        <p class="text-xs text-gray-500 mt-1">可选，添加语音的详细描述</p>
                                    </div>
                                    
                                    <div class="nagisa-form-group">
                                        <label class="flex items-center">
                                            <span class="mr-2">启用语音</span>
                                            <label class="switch">
                                                <input type="checkbox" id="status" name="status" <?php echo ($edit_data && $edit_data['status'] == 1) || !$edit_data ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                        </label>
                                        <p class="text-xs text-gray-500 mt-1">未启用的语音不会在前台页面显示</p>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="nagisa-form-group">
                                        <label class="nagisa-label">语音文件<?php echo $action === 'add' ? ' <span class="text-red-500">*</span>' : '（可选，不上传则保持原文件）'; ?></label>
                                        <input type="file" class="nagisa-input" id="audio_file" name="file" <?php echo $action === 'add' ? 'required' : ''; ?> accept="audio/*">
                                        <p class="text-xs text-gray-500 mt-1">支持MP3、WAV、OGG等常见音频格式</p>
                                    </div>
                                    
                                    <?php if ($action === 'edit' && $edit_data): ?>
                                    <div class="nagisa-preview-container">
                                        <h3 class="nagisa-preview-title">当前音频</h3>
                                        <div class="p-4 bg-gray-50 rounded-lg">
                                            <audio controls class="w-full">
                                                <source src="../<?php echo htmlspecialchars($edit_data['audio_path']); ?>" type="audio/mpeg">
                                                您的浏览器不支持音频播放
                                            </audio>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div id="audio-preview-container" class="nagisa-preview-container" style="display:none;">
                                        <h3 class="nagisa-preview-title">音频预览</h3>
                                        <div id="audio-preview" class="p-4 bg-gray-50 rounded-lg"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end mt-6 space-x-2">
                                <a href="?type=audio&action=list" class="nagisa-btn-secondary">
                                    <i class="fas fa-times mr-2"></i>取消
                                </a>
                                <button type="submit" name="<?php echo $action === 'add' ? 'add_expression' : 'update_expression'; ?>" class="nagisa-btn">
                                    <i class="fas <?php echo $action === 'add' ? 'fa-plus' : 'fa-save'; ?> mr-2"></i><?php echo $action === 'add' ? '添加' : '更新'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showToast(message, type = 'success') {
    // 创建toast元素
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 px-4 py-2 rounded-md shadow-lg z-50 ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white`;
    toast.innerHTML = message;
    
    // 添加到页面
    document.body.appendChild(toast);
    
    // 淡入效果
    setTimeout(() => {
        toast.style.opacity = '1';
    }, 10);
    
    // 淡出并移除
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s';
        
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 500);
    }, 3000);
}

// 前台页面查看功能
function viewFrontend() {
    // 获取当前选中的类型
    const currentType = document.getElementById('expression-section').style.display === 'none' ? 'audio' : 'expression';
    
    // 根据类型打开对应的前台页面
    if (currentType === 'expression') {
        window.open('/SecWeb/expression/expression_emotes.php', '_blank');
    } else if (currentType === 'audio') {
        window.open('/SecWeb/expression/expression_audios.php', '_blank');
    } else {
        // 默认打开表情页面
        window.open('/SecWeb/expression/expression_emotes.php', '_blank');
    }
}

// 标签管理功能
function showTagManager() {
    const tagManagerSection = document.getElementById('tag-manager-section');
    const expressionGrid = document.getElementById('expression-grid');
    const emptyState = document.querySelector('.flex.flex-col.items-center.justify-center.py-10');
    
    if (tagManagerSection) {
        tagManagerSection.style.display = 'block';
    }
    
    // 隐藏表情网格和空状态
    if (expressionGrid) {
        expressionGrid.style.display = 'none';
    }
    if (emptyState) {
        emptyState.style.display = 'none';
    }
}

function hideTagManager() {
    const tagManagerSection = document.getElementById('tag-manager-section');
    const expressionGrid = document.getElementById('expression-grid');
    const emptyState = document.querySelector('.flex.flex-col.items-center.justify-center.py-10');
    
    if (tagManagerSection) {
        tagManagerSection.style.display = 'none';
    }
    
    // 显示表情网格或空状态
    if (expressionGrid) {
        expressionGrid.style.display = 'grid';
    }
    if (emptyState) {
        emptyState.style.display = 'flex';
    }
}

function addNewTag() {
    const tagName = document.getElementById('new-tag-name').value.trim();
    const tagDescription = document.getElementById('new-tag-description').value.trim();
    
    if (!tagName) {
        showToast('请输入标签名称', 'error');
        return;
    }
    
    // 检查标签是否已存在
    const existingTags = document.querySelectorAll('#existing-tags-list .font-medium');
    for (let tag of existingTags) {
        if (tag.textContent.trim() === tagName) {
            showToast('标签已存在', 'error');
            return;
        }
    }
    
    // 发送AJAX请求保存标签到数据库
    fetch('manage_expressions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add_tag&tag_name=${encodeURIComponent(tagName)}&tag_description=${encodeURIComponent(tagDescription)}&type=expression`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 添加新标签到列表
            const tagsList = document.getElementById('existing-tags-list');
            
            // 如果列表为空，清除空状态提示
            const emptyState = tagsList.querySelector('.text-center');
            if (emptyState) {
                emptyState.remove();
            }
            
            const newTagHtml = `
                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-purple-500 rounded-full mr-3"></div>
                        <div>
                            <span class="font-medium text-gray-700">${tagName}</span>
                            <span class="text-xs text-gray-500 ml-2 bg-gray-200 px-2 py-1 rounded-full">分类标签</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="editTag('${tagName}')" class="nagisa-btn nagisa-btn-mini" style="background: linear-gradient(45deg, #f59e0b, #d97706);">
                            <i class="fas fa-edit mr-1"></i>编辑
                        </button>
                        <button type="button" onclick="deleteTag('${tagName}')" class="nagisa-btn-danger nagisa-btn-mini">
                            <i class="fas fa-trash mr-1"></i>删除
                        </button>
                    </div>
                </div>
            `;
            tagsList.insertAdjacentHTML('afterbegin', newTagHtml);
            
            // 清空输入框
            document.getElementById('new-tag-name').value = '';
            document.getElementById('new-tag-description').value = '';
            
            showToast('标签添加成功！');
        } else {
            showToast(`添加标签失败: ${data.message}`, 'error');
        }
    })
    .catch(error => {
        showToast(`添加标签失败: ${error.message}`, 'error');
    });
}

function editTag(tagName) {
    const newName = prompt('请输入新的标签名称:', tagName);
    if (newName && newName.trim() && newName !== tagName) {
        // 检查新名称是否已存在
        const existingTags = document.querySelectorAll('#existing-tags-list .font-medium');
        for (let tag of existingTags) {
            if (tag.textContent.trim() === newName.trim()) {
                showToast('标签名称已存在', 'error');
                return;
            }
        }
        
        // 发送AJAX请求更新标签
        fetch('manage_expressions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=edit_tag&old_name=${encodeURIComponent(tagName)}&new_name=${encodeURIComponent(newName.trim())}&type=expression`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 更新标签显示
                const tagElements = document.querySelectorAll('#existing-tags-list .flex');
                for (let element of tagElements) {
                    const tagNameElement = element.querySelector('.font-medium');
                    if (tagNameElement && tagNameElement.textContent.trim() === tagName) {
                        tagNameElement.textContent = newName.trim();
                        // 更新onclick事件中的标签名称
                        const editBtn = element.querySelector('button[onclick^="editTag"]');
                        const deleteBtn = element.querySelector('button[onclick^="deleteTag"]');
                        if (editBtn) {
                            editBtn.setAttribute('onclick', `editTag('${newName.trim()}')`);
                        }
                        if (deleteBtn) {
                            deleteBtn.setAttribute('onclick', `deleteTag('${newName.trim()}')`);
                        }
                        break;
                    }
                }
                
                showToast('标签更新成功！');
            } else {
                showToast(`更新标签失败: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            showToast(`更新标签失败: ${error.message}`, 'error');
        });
    }
}

function deleteTag(tagName) {
    if (confirm(`确定要删除标签 "${tagName}" 吗？此操作不可恢复。`)) {
        // 发送AJAX请求删除标签
        fetch('manage_expressions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_tag&tag_name=${encodeURIComponent(tagName)}&type=expression`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 从DOM中移除标签元素
                const tagElements = document.querySelectorAll('#existing-tags-list .flex');
                for (let element of tagElements) {
                    const tagNameElement = element.querySelector('.font-medium');
                    if (tagNameElement && tagNameElement.textContent.trim() === tagName) {
                        element.remove();
                        break;
                    }
                }
                
                // 检查是否还有其他标签，如果没有则显示空状态
                const remainingTags = document.querySelectorAll('#existing-tags-list .flex');
                if (remainingTags.length === 0) {
                    const tagsList = document.getElementById('existing-tags-list');
                    tagsList.innerHTML = `
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-tags text-4xl mb-3"></i>
                            <p>暂无标签，请添加新标签</p>
                        </div>
                    `;
                }
                
                showToast('标签删除成功！');
            } else {
                showToast(`删除标签失败: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            showToast(`删除标签失败: ${error.message}`, 'error');
        });
    }
}

// 分类管理功能
function showNewCategoryInput() {
    const newCategoryInput = document.getElementById('new-category-input');
    const newCategoryName = document.getElementById('new-category-name');
    
    if (newCategoryInput) {
        newCategoryInput.style.display = 'block';
        if (newCategoryName) {
            newCategoryName.focus();
        }
    }
}

function hideNewCategoryInput() {
    const newCategoryInput = document.getElementById('new-category-input');
    const newCategoryName = document.getElementById('new-category-name');
    
    if (newCategoryInput) {
        newCategoryInput.style.display = 'none';
    }
    if (newCategoryName) {
        newCategoryName.value = '';
    }
}

function addNewCategory() {
    const newCategoryName = document.getElementById('new-category-name');
    const categoryCheckboxes = document.getElementById('category-checkboxes');
    
    if (!newCategoryName || !categoryCheckboxes) return;
    
    const categoryName = newCategoryName.value.trim();
    
    if (!categoryName) {
        showToast('请输入分类名称', 'error');
        return;
    }
    
    // 检查分类是否已存在
    const existingCheckboxes = categoryCheckboxes.querySelectorAll('input[type="checkbox"]');
    for (let checkbox of existingCheckboxes) {
        if (checkbox.value === categoryName) {
            showToast('分类已存在', 'error');
            return;
        }
    }
    
    // 发送AJAX请求保存分类到数据库
    fetch('manage_expressions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add_tag&tag_name=${encodeURIComponent(categoryName)}&tag_description=&type=expression`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 添加新复选框到容器
            const newLabel = document.createElement('label');
            newLabel.className = 'category-checkbox-item';
            newLabel.innerHTML = `
                <input type="checkbox" name="categories[]" value="${categoryName}" class="category-checkbox" checked>
                <span class="category-label">${categoryName}</span>
            `;
            
            categoryCheckboxes.appendChild(newLabel);
            
            // 隐藏输入框并清空
            hideNewCategoryInput();
            
            showToast('新分类添加成功！');
        } else {
            showToast(`添加分类失败: ${data.message}`, 'error');
        }
    })
    .catch(error => {
        showToast(`添加分类失败: ${error.message}`, 'error');
    });
}

// 语音分类管理功能
function showNewAudioCategoryInput() {
    const newCategoryInput = document.getElementById('new-audio-category-input');
    const newCategoryName = document.getElementById('new-audio-category-name');
    
    if (newCategoryInput) {
        newCategoryInput.style.display = 'block';
        if (newCategoryName) {
            newCategoryName.focus();
        }
    }
}

function hideNewAudioCategoryInput() {
    const newCategoryInput = document.getElementById('new-audio-category-input');
    const newCategoryName = document.getElementById('new-audio-category-name');
    
    if (newCategoryInput) {
        newCategoryInput.style.display = 'none';
    }
    if (newCategoryName) {
        newCategoryName.value = '';
    }
}

function addNewAudioCategory() {
    const newCategoryName = document.getElementById('new-audio-category-name');
    const categoryCheckboxes = document.getElementById('audio-category-checkboxes');
    
    if (!newCategoryName || !categoryCheckboxes) return;
    
    const categoryName = newCategoryName.value.trim();
    
    if (!categoryName) {
        showToast('请输入分类名称', 'error');
        return;
    }
    
    // 检查分类是否已存在
    const existingCheckboxes = categoryCheckboxes.querySelectorAll('input[type="checkbox"]');
    for (let checkbox of existingCheckboxes) {
        if (checkbox.value === categoryName) {
            showToast('分类已存在', 'error');
            return;
        }
    }
    
    // 发送AJAX请求保存分类到数据库
    fetch('manage_expressions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add_tag&tag_name=${encodeURIComponent(categoryName)}&tag_description=&type=audio`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 添加新复选框到容器
            const newLabel = document.createElement('label');
            newLabel.className = 'category-checkbox-item';
            newLabel.innerHTML = `
                <input type="checkbox" name="categories[]" value="${categoryName}" class="category-checkbox" checked>
                <span class="category-label">${categoryName}</span>
            `;
            
            categoryCheckboxes.appendChild(newLabel);
            
            // 隐藏输入框并清空
            hideNewAudioCategoryInput();
            
            showToast('新分类添加成功！');
        } else {
            showToast(`添加分类失败: ${data.message}`, 'error');
        }
    })
    .catch(error => {
        showToast(`添加分类失败: ${error.message}`, 'error');
    });
}

// 语音标签管理功能
function showAudioTagManager() {
    const tagManagerSection = document.getElementById('audio-tag-manager-section');
    const audioGrid = document.getElementById('audio-grid');
    const emptyState = document.querySelector('#audio-section .flex.flex-col.items-center.justify-center.py-10');
    
    if (tagManagerSection) {
        tagManagerSection.style.display = 'block';
    }
    
    // 隐藏语音网格和空状态
    if (audioGrid) {
        audioGrid.style.display = 'none';
    }
    if (emptyState) {
        emptyState.style.display = 'none';
    }
}

function hideAudioTagManager() {
    const tagManagerSection = document.getElementById('audio-tag-manager-section');
    const audioGrid = document.getElementById('audio-grid');
    const emptyState = document.querySelector('#audio-section .flex.flex-col.items-center.justify-center.py-10');
    
    if (tagManagerSection) {
        tagManagerSection.style.display = 'none';
    }
    
    // 显示语音网格或空状态
    if (audioGrid) {
        audioGrid.style.display = 'grid';
    }
    if (emptyState) {
        emptyState.style.display = 'flex';
    }
}

function addNewAudioTag() {
    const tagName = document.getElementById('new-audio-tag-name').value.trim();
    const tagDescription = document.getElementById('new-audio-tag-description').value.trim();
    
    if (!tagName) {
        showToast('请输入标签名称', 'error');
        return;
    }
    
    // 检查标签是否已存在
    const existingTags = document.querySelectorAll('#existing-audio-tags-list .font-medium');
    for (let tag of existingTags) {
        if (tag.textContent.trim() === tagName) {
            showToast('标签已存在', 'error');
            return;
        }
    }
    
    // 发送AJAX请求保存标签到数据库
    fetch('manage_expressions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add_tag&tag_name=${encodeURIComponent(tagName)}&tag_description=${encodeURIComponent(tagDescription)}&type=audio`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 添加新标签到列表
            const tagsList = document.getElementById('existing-audio-tags-list');
            
            // 如果列表为空，清除空状态提示
            const emptyState = tagsList.querySelector('.text-center');
            if (emptyState) {
                emptyState.remove();
            }
            
            const newTagHtml = `
                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-purple-500 rounded-full mr-3"></div>
                        <div>
                            <span class="font-medium text-gray-700">${tagName}</span>
                            <span class="text-xs text-gray-500 ml-2 bg-gray-200 px-2 py-1 rounded-full">分类标签</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="editAudioTag('${tagName}')" class="nagisa-btn nagisa-btn-mini" style="background: linear-gradient(45deg, #f59e0b, #d97706);">
                            <i class="fas fa-edit mr-1"></i>编辑
                        </button>
                        <button type="button" onclick="deleteAudioTag('${tagName}')" class="nagisa-btn-danger nagisa-btn-mini">
                            <i class="fas fa-trash mr-1"></i>删除
                        </button>
                    </div>
                </div>
            `;
            tagsList.insertAdjacentHTML('afterbegin', newTagHtml);
            
            // 清空输入框
            document.getElementById('new-audio-tag-name').value = '';
            document.getElementById('new-audio-tag-description').value = '';
            
            showToast('标签添加成功！');
        } else {
            showToast(`添加标签失败: ${data.message}`, 'error');
        }
    })
    .catch(error => {
        showToast(`添加标签失败: ${error.message}`, 'error');
    });
}

function editAudioTag(tagName) {
    const newName = prompt('请输入新的标签名称:', tagName);
    if (newName && newName.trim() && newName !== tagName) {
        // 检查新名称是否已存在
        const existingTags = document.querySelectorAll('#existing-audio-tags-list .font-medium');
        for (let tag of existingTags) {
            if (tag.textContent.trim() === newName.trim()) {
                showToast('标签名称已存在', 'error');
                return;
            }
        }
        
        // 发送AJAX请求更新标签
        fetch('manage_expressions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=edit_tag&old_name=${encodeURIComponent(tagName)}&new_name=${encodeURIComponent(newName.trim())}&type=audio`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 更新标签显示
                const tagElements = document.querySelectorAll('#existing-audio-tags-list .flex');
                for (let element of tagElements) {
                    const tagNameElement = element.querySelector('.font-medium');
                    if (tagNameElement && tagNameElement.textContent.trim() === tagName) {
                        tagNameElement.textContent = newName.trim();
                        // 更新onclick事件中的标签名称
                        const editBtn = element.querySelector('button[onclick^="editAudioTag"]');
                        const deleteBtn = element.querySelector('button[onclick^="deleteAudioTag"]');
                        if (editBtn) {
                            editBtn.setAttribute('onclick', `editAudioTag('${newName.trim()}')`);
                        }
                        if (deleteBtn) {
                            deleteBtn.setAttribute('onclick', `deleteAudioTag('${newName.trim()}')`);
                        }
                        break;
                    }
                }
                
                showToast('标签更新成功！');
            } else {
                showToast(`更新标签失败: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            showToast(`更新标签失败: ${error.message}`, 'error');
        });
    }
}

function deleteAudioTag(tagName) {
    if (confirm(`确定要删除标签 "${tagName}" 吗？此操作不可恢复。`)) {
        // 发送AJAX请求删除标签
        fetch('manage_expressions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_tag&tag_name=${encodeURIComponent(tagName)}&type=audio`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 从DOM中移除标签元素
                const tagElements = document.querySelectorAll('#existing-audio-tags-list .flex');
                for (let element of tagElements) {
                    const tagNameElement = element.querySelector('.font-medium');
                    if (tagNameElement && tagNameElement.textContent.trim() === tagName) {
                        element.remove();
                        break;
                    }
                }
                
                // 检查是否还有其他标签，如果没有则显示空状态
                const remainingTags = document.querySelectorAll('#existing-audio-tags-list .flex');
                if (remainingTags.length === 0) {
                    const tagsList = document.getElementById('existing-audio-tags-list');
                    tagsList.innerHTML = `
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-tags text-4xl mb-3"></i>
                            <p>暂无标签，请添加新标签</p>
                        </div>
                    `;
                }
                
                showToast('标签删除成功！');
            } else {
                showToast(`删除标签失败: ${data.message}`, 'error');
            }
        })
        .catch(error => {
            showToast(`删除标签失败: ${error.message}`, 'error');
        });
    }
}

// 切换显示不同的部分
function changeType(type) {
    const expressionSection = document.getElementById('expression-section');
    const audioSection = document.getElementById('audio-section');
    const expressionNavLink = document.querySelector('.nagisa-nav-link[onclick="changeType(\'expression\')"]');
    const audioNavLink = document.querySelector('.nagisa-nav-link[onclick="changeType(\'audio\')"]');

    expressionSection.style.display = type === 'expression' ? '' : 'none';
    audioSection.style.display = type === 'audio' ? '' : 'none';

    expressionNavLink.classList.toggle('active', type === 'expression');
    audioNavLink.classList.toggle('active', type === 'audio');

    const url = new URL(window.location);
    url.searchParams.set('type', type);
    window.history.replaceState({}, '', url);

    localStorage.setItem('expression_admin_active_section', type);
}

// 初始化当前显示的部分
document.addEventListener('DOMContentLoaded', function() {
    // 获取当前应该显示的部分
    const urlParams = new URLSearchParams(window.location.search);
    const urlType = urlParams.get('type');
    const storedType = localStorage.getItem('expression_admin_active_section');
    const type = urlType || storedType || 'expression';
    
    // 显示对应的部分
    changeType(type);
    
    // 表情包图片上传预览
    const expressionFileInput = document.getElementById('expression_file');
    if (expressionFileInput) {
        expressionFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewContainer = document.getElementById('expression-preview-container');
            const previewImage = document.getElementById('expression-preview-image');
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewContainer.style.display = 'block';
                    previewImage.innerHTML = `<img src="${e.target.result}" alt="预览" class="max-w-full mx-auto" style="max-height:300px;">`;
                }
                
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
                previewImage.innerHTML = '';
            }
        });
    }
    
    // 监听音频文件上传并添加预览
    const audioFileInput = document.getElementById('audio_file');
    if (audioFileInput) {
        audioFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const audioPreviewContainer = document.getElementById('audio-preview-container');
                if (audioPreviewContainer) {
                    audioPreviewContainer.style.display = 'block';
                    
                    const audioPreview = document.getElementById('audio-preview');
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        audioPreview.innerHTML = `
                            <audio controls style="width:100%;">
                                <source src="${e.target.result}" type="audio/${file.name.split('.').pop().toLowerCase()}">
                                您的浏览器不支持音频预览
                            </audio>
                        `;
                    }
                    
                    reader.readAsDataURL(file);
                }
            }
        });
    }
    
    // 搜索和过滤功能 - 表情
    const expressionSearch = document.getElementById('expression-search');
    const categoryFilter = document.getElementById('expression-category-filter');
    
    if (expressionSearch) {
        expressionSearch.addEventListener('input', function() {
            filterItems('expression');
        });
    }
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            filterItems('expression');
        });
    }
    
    // 搜索和过滤功能 - 语音
    const audioSearch = document.getElementById('audio-search');
    const audioCategoryFilter = document.getElementById('audio-category-filter');
    
    if (audioSearch) {
        audioSearch.addEventListener('input', function() {
            filterItems('audio');
        });
    }
    
    if (audioCategoryFilter) {
        audioCategoryFilter.addEventListener('change', function() {
            filterItems('audio');
        });
    }
    
    // 排序功能已移除
    
    // 过滤项目的函数
    function filterItems(type) {
        const searchInput = document.getElementById(`${type}-search`);
        const categorySelect = document.getElementById(`${type}-category-filter`);
        const grid = document.getElementById(`${type}-grid`);
        
        if (!grid) return;
        
        const items = grid.querySelectorAll('.expression-item');
        const searchValue = searchInput ? searchInput.value.toLowerCase() : '';
        const categoryValue = categorySelect ? categorySelect.value : 'all';
        
        items.forEach(item => {
            const title = item.getAttribute('data-title').toLowerCase();
            const category = item.getAttribute('data-category');
            
            const matchesSearch = title.includes(searchValue);
            let matchesCategory = true;
            
            if (categoryValue !== 'all') {
                // 支持多选分类过滤
                const itemCategories = category.split(',').map(cat => cat.trim());
                matchesCategory = itemCategories.includes(categoryValue);
            }
            
            item.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
        });
    }
    
    // 排序项目的函数已移除

    // 处理表情/语音状态切换
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const itemId = this.dataset.id;
            const itemType = this.dataset.type;
            const newStatus = this.checked ? 1 : 0;

            fetch(`../includes/toggle_status.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `item_id=${itemId}&item_type=${itemType}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`${itemType}状态已更新为 ${newStatus ? '启用' : '禁用'}`);
                } else {
                    showToast(`更新${itemType}状态失败: ${data.message}`, 'error');
                    // 恢复切换状态
                    this.checked = !newStatus;
                }
            })
            .catch(error => {
                showToast(`更新${itemType}状态失败: ${error.message}`, 'error');
                // 恢复切换状态
                this.checked = !newStatus;
            });
        });
    });
    
    // 全选分类
    function selectAllCategories(type) {
        const container = document.getElementById(`${type}-category-checkboxes`);
        if (!container) return;
        
        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        
        showToast('已全选所有分类');
    }
    
    // 取消全选分类
    function deselectAllCategories(type) {
        const container = document.getElementById(`${type}-category-checkboxes`);
        if (!container) return;
        
        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        
        showToast('已取消全选');
    }
    
    // 显示分类管理器
    function showCategoryManager() {
        showTagManager();
    }
    
    // 显示语音分类管理器
    function showAudioCategoryManager() {
        showAudioTagManager();
    }
});
</script>
<?php include 'admin_footer.php'; ?> 