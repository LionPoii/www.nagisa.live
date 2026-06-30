<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/toast.php';
require_once '../includes/shopcar_helpers.php';

// 检查管理员登录状态
checkAdminAuth();

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

// 初始化变量
$error = '';
$success = '';
$shopProductFlash = null;
if (!empty($_SESSION['shop_product_flash'])) {
    $shopProductFlash = $_SESSION['shop_product_flash'];
    unset($_SESSION['shop_product_flash']);
}

// 检查图片表是否存在，如果不存在则创建
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
        category VARCHAR(50) DEFAULT 'waiting',
        status TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

// 检查商品表是否存在
$stmt = $conn->prepare("SHOW TABLES LIKE 'shopcar_products'");
$stmt->execute();
$shopcarTableExists = ($stmt->rowCount() > 0);
$shopcarSeriesTableExists = false;
$shopcarSeries = [];

if ($shopcarTableExists && $conn) {
    shopcarEnsureSchema($conn);
    $shopcarSeriesTableExists = shopcarTableExists($conn, 'shopcar_series');
    if ($shopcarSeriesTableExists) {
        try {
            $shopcarSeries = shopcarGetAllSeriesFlat($conn);
        } catch (PDOException $e) {
            error_log('获取商品系列失败: ' . $e->getMessage());
        }
    }
}

// 处理图片上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expression'])) {
    try {
        // 获取表单数据
        $title = trim($_POST['expression_title']);
        $description = trim($_POST['expression_description']);
        $categories = isset($_POST['expression_categories']) ? $_POST['expression_categories'] : [];
        $category = !empty($categories) ? implode(',', $categories) : 'emotion';
        $status = isset($_POST['expression_status']) ? 1 : 0;
        
        // 验证表单数据
        if (empty($title)) {
            throw new Exception('图片标题不能为空');
        }
        
        // 处理文件上传
        if (isset($_FILES['expression_file']) && $_FILES['expression_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['expression_file']['tmp_name'];
            $file_name = $_FILES['expression_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // 验证文件类型
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('不支持的文件类型，请上传jpg、jpeg、png、gif或webp格式的图片');
            }
            
            // 构建上传路径
            $upload_dir = '../assets/expressions/';
            
            // 确保目录存在
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('无法创建目录: ' . $upload_dir);
                }
            }
            
            // 生成唯一文件名
            $original_name = pathinfo($file_name, PATHINFO_FILENAME);
            // 清理文件名，移除特殊字符
            $original_name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $original_name);
            // 如果清理后文件名为空，使用默认名称
            if (empty($original_name)) {
                $original_name = 'expression';
            }
            // 生成唯一文件名：原始名称_随机数.扩展名
            $random_suffix = substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $new_file_name = $original_name . '_' . $random_suffix . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            // 移动上传的文件
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                throw new Exception('无法上传文件');
            }
            
            // 保存到数据库 - 确保路径格式一致，以/开头
            $relative_path = '/assets/expressions/' . $new_file_name;
            $stmt = $conn->prepare("INSERT INTO expression_images (title, description, image_path, category, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $relative_path, $category, $status]);
            
            showToast('图片添加成功！');
        } else {
            throw new Exception('请选择要上传的图片');
        }
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
} 

// 处理语音上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_audio'])) {
    try {
        // 获取表单数据
        $title = trim($_POST['audio_title']);
        $description = trim($_POST['audio_description']);
        $categories = isset($_POST['audio_categories']) ? $_POST['audio_categories'] : [];
        $category = !empty($categories) ? $categories[0] : 'waiting';
        $position = isset($_POST['audio_position']) ? intval($_POST['audio_position']) : 0;
        $status = isset($_POST['audio_status']) ? 1 : 0;
        
        // 验证表单数据
        if (empty($title)) {
            throw new Exception('语音标题不能为空');
        }
        
        // 处理文件上传
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['audio_file']['tmp_name'];
            $file_name = $_FILES['audio_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // 验证文件类型
            $allowed_extensions = ['mp3', 'wav', 'ogg'];
            
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('不支持的文件类型，请上传mp3、wav或ogg格式的音频');
            }
            
            // 构建上传路径
            $upload_dir = '../assets/audios/';
            
            // 确保目录存在
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('无法创建目录: ' . $upload_dir);
                }
            }
            
            // 生成唯一文件名
            $original_name = pathinfo($file_name, PATHINFO_FILENAME);
            // 清理文件名，移除特殊字符
            $original_name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $original_name);
            // 如果清理后文件名为空，使用默认名称
            if (empty($original_name)) {
                $original_name = 'audio';
            }
            // 生成唯一文件名：原始名称_随机数.扩展名
            $random_suffix = substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $new_file_name = $original_name . '_' . $random_suffix . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            // 移动上传的文件
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                throw new Exception('无法上传文件');
            }
            
            // 保存到数据库 - 确保路径格式一致，以/开头
            $relative_path = '/assets/audios/' . $new_file_name;
            $stmt = $conn->prepare("INSERT INTO expression_audios (title, description, audio_path, category, display_order, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $relative_path, $category, $position, $status]);
            
            showToast('语音添加成功！');
        } else {
            throw new Exception('请选择要上传的语音文件');
        }
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
} 

// 处理添加商品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product']) && $shopcarTableExists) {
    try {
        $title = trim($_POST['product_title'] ?? $_POST['edit_product_title'] ?? '');
        $description = trim($_POST['product_description'] ?? $_POST['edit_product_description'] ?? '');
        $price = trim($_POST['product_price'] ?? $_POST['edit_product_price'] ?? '');
        $link = trim($_POST['product_link'] ?? $_POST['edit_product_link'] ?? '');
        $position = isset($_POST['product_position']) ? intval($_POST['product_position']) : (isset($_POST['edit_product_position']) ? intval($_POST['edit_product_position']) : 0);
        $active = isset($_POST['product_active']) || isset($_POST['edit_product_active']) ? 1 : 0;
        $seriesId = shopcarParseSeriesIdFromPost();
        
        if (empty($title)) {
            throw new Exception('商品标题不能为空');
        }
        
        // 确定文件上传字段名
        // 优先检查 edit_product_image（编辑模式），然后检查 product_image（添加模式）
        $fileField = null;
        $fileInfo = null;
        
        if (isset($_FILES['edit_product_image']) && $_FILES['edit_product_image']['error'] === UPLOAD_ERR_OK) {
            $fileField = 'edit_product_image';
            $fileInfo = $_FILES['edit_product_image'];
        } elseif (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $fileField = 'product_image';
            $fileInfo = $_FILES['product_image'];
        }
        
        // 处理图片上传
        if ($fileField && $fileInfo) {
            $targetDir = "../assets/uploads/products/";
            
            // 如果目录不存在则创建
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            // 获取文件扩展名和原始文件名
            $fileExt = pathinfo($fileInfo["name"], PATHINFO_EXTENSION);
            $originalName = pathinfo($fileInfo["name"], PATHINFO_FILENAME);
            // 清理文件名，移除特殊字符
            $originalName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $originalName);
            // 如果清理后文件名为空，使用默认名称
            if (empty($originalName)) {
                $originalName = 'product';
            }
            // 生成唯一文件名：原始名称_随机数.扩展名
            $randomSuffix = substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $newFileName = $originalName . '_' . $randomSuffix . "." . $fileExt;
            $targetFile = $targetDir . $newFileName;
            
            // 检查文件类型
            $allowedTypes = ["jpg", "jpeg", "png", "gif", "webp"];
            if (!in_array(strtolower($fileExt), $allowedTypes)) {
                throw new Exception("只允许上传JPG、JPEG、PNG、GIF和WEBP格式的图片");
            }
            
            // 上传文件
            if (move_uploaded_file($fileInfo["tmp_name"], $targetFile)) {
                $imagePath = "assets/uploads/products/" . $newFileName;
                
                // 插入数据库
                $stmt = $conn->prepare(shopcarProductInsertSql($conn));
                $stmt->execute(shopcarProductInsertParams($conn, $title, $description, $price, $imagePath, $link, $seriesId, $position, $active));
                
                showToast('商品添加成功！');
            } else {
                throw new Exception("文件上传失败");
            }
        } else {
            // 提供详细的错误信息
            $errorMsg = '请选择要上传的商品图片';
            if ($fileField && isset($_FILES[$fileField])) {
                $errorCode = $_FILES[$fileField]['error'];
                $errorMsg .= ' (错误代码: ' . $errorCode . ')';
                if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
                    $errorMsg .= ' - 文件大小超过限制';
                } elseif ($errorCode === UPLOAD_ERR_PARTIAL) {
                    $errorMsg .= ' - 文件只有部分被上传';
                } elseif ($errorCode === UPLOAD_ERR_NO_FILE) {
                    $errorMsg .= ' - 没有文件被上传';
                }
            }
            throw new Exception($errorMsg);
        }
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 处理更新商品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_product']) || isset($_POST['edit'])) && $shopcarTableExists) {
    try {
        // 处理来自两种不同表单的数据
        if (isset($_POST['update_product'])) {
            // 来自全屏编辑表单
            $id = intval($_POST['edit_product_id']);
            $title = isset($_POST['edit_product_title']) ? trim($_POST['edit_product_title']) : '';
            $description = isset($_POST['edit_product_description']) ? trim($_POST['edit_product_description']) : '';
            $price = isset($_POST['edit_product_price']) ? trim($_POST['edit_product_price']) : '';
            $link = isset($_POST['edit_product_link']) ? trim($_POST['edit_product_link']) : '';
            $position = isset($_POST['edit_product_position']) ? intval($_POST['edit_product_position']) : 0;
            $active = isset($_POST['edit_product_active']) ? 1 : 0;
            $oldImage = isset($_POST['edit_product_image_old']) ? $_POST['edit_product_image_old'] : '';
            $imageField = 'edit_product_image';
            $seriesId = shopcarParseSeriesIdFromPost();
        } else {
            // 来自内嵌编辑表单
            $id = intval($_POST['product_id']);
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $price = trim($_POST['price']);
            $link = trim($_POST['link']);
            $position = isset($_POST['position']) ? intval($_POST['position']) : 0;
            $active = isset($_POST['active']) ? 1 : 0;
            $oldImage = isset($_POST['current_image']) ? '../' . $_POST['current_image'] : '';
            $imageField = 'image';
            $seriesId = isset($_POST['series_id']) && $_POST['series_id'] !== '' ? intval($_POST['series_id']) : null;
        }
        
        if (empty($title)) {
            throw new Exception('商品标题不能为空');
        }
        
        $imagePath = $oldImage;
        
        // 处理新图片上传（如果有的话）
        if (isset($_FILES[$imageField]) && $_FILES[$imageField]['error'] === UPLOAD_ERR_OK) {
            $targetDir = "../assets/uploads/products/";
            
            // 如果目录不存在则创建
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            // 获取文件扩展名和原始文件名
            $fileExt = pathinfo($_FILES[$imageField]["name"], PATHINFO_EXTENSION);
            $originalName = pathinfo($_FILES[$imageField]["name"], PATHINFO_FILENAME);
            // 清理文件名，移除特殊字符
            $originalName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $originalName);
            // 如果清理后文件名为空，使用默认名称
            if (empty($originalName)) {
                $originalName = 'product';
            }
            // 生成唯一文件名：原始名称_随机数.扩展名
            $randomSuffix = substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $newFileName = $originalName . '_' . $randomSuffix . "." . $fileExt;
            $targetFile = $targetDir . $newFileName;
            
            // 检查文件类型
            $allowedTypes = ["jpg", "jpeg", "png", "gif", "webp"];
            if (!in_array(strtolower($fileExt), $allowedTypes)) {
                throw new Exception("只允许上传JPG、JPEG、PNG、GIF和WEBP格式的图片");
            }
            
            // 上传新文件
            if (move_uploaded_file($_FILES[$imageField]["tmp_name"], $targetFile)) {
                $imagePath = "assets/uploads/products/" . $newFileName;
                
                // 删除旧图片（如果不是默认图片）
                if ($oldImage && strpos($oldImage, 'assets/uploads/products/') !== false) {
                    $oldImagePath = $oldImage;
                    if (file_exists($oldImagePath)) {
                        @unlink($oldImagePath);
                    }
                }
            } else {
                throw new Exception("新图片上传失败");
            }
        } else {
            // 如果没有上传新图片，确保路径格式正确（移除可能的../前缀）
            if (strpos($imagePath, '../') === 0) {
                $imagePath = substr($imagePath, 3); // 移除开头的../
            } else if (strpos($imagePath, '/') === 0) {
                $imagePath = substr($imagePath, 1); // 移除开头的/
            }
        }
        
        // 更新数据库
        $stmt = $conn->prepare(shopcarProductUpdateSql($conn));
        $result = $stmt->execute(shopcarProductUpdateParams($conn, $title, $description, $price, $imagePath, $link, $seriesId, $position, $active, $id));
        
        if ($result) {
            $_SESSION['shop_product_flash'] = ['message' => '商品更新成功！', 'type' => 'success'];
            header('Location: ' . $_SERVER['PHP_SELF'] . '?section=products');
            exit;
        } else {
            throw new Exception('商品更新失败，可能是数据没有变化或商品不存在');
        }
        
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 处理删除图片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_expression'])) {
    try {
        $id = intval($_POST['expression_id']);
        
        // 获取图片信息
        $stmt = $conn->prepare("SELECT image_path FROM expression_images WHERE id = ?");
        $stmt->execute([$id]);
        $expression = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($expression) {
            // 删除文件
            $file_path = '../' . $expression['image_path'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            
            // 从数据库中删除记录
            $stmt = $conn->prepare("DELETE FROM expression_images WHERE id = ?");
            $stmt->execute([$id]);
            
            showToast('图片已成功删除！');
        } else {
            throw new Exception('未找到图片！');
        }
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 处理删除语音
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_audio'])) {
    try {
        $id = intval($_POST['audio_id']);
        
        // 获取语音信息
        $stmt = $conn->prepare("SELECT audio_path FROM expression_audios WHERE id = ?");
        $stmt->execute([$id]);
        $audio = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($audio) {
            // 删除文件
            $file_path = '../' . $audio['audio_path'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            
            // 从数据库中删除记录
            $stmt = $conn->prepare("DELETE FROM expression_audios WHERE id = ?");
            $stmt->execute([$id]);
            
            showToast('语音已成功删除！');
        } else {
            throw new Exception('未找到语音！');
        }
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 处理删除商品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product']) && $shopcarTableExists) {
    try {
        $id = intval($_POST['product_id']);
        
        // 获取商品信息
        $stmt = $conn->prepare("SELECT image FROM shopcar_products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // 删除文件
            $file_path = '../' . $product['image'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
            
            // 从数据库中删除记录
            $stmt = $conn->prepare("DELETE FROM shopcar_products WHERE id = ?");
            $stmt->execute([$id]);
            
            showToast('商品已成功删除！');
        } else {
            throw new Exception('未找到商品！');
        }
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 处理更新图片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_expression'])) {
    try {
        // 获取表单数据
        $id = intval($_POST['expression_id']);
        $title = trim($_POST['expression_title']);
        $description = trim($_POST['expression_description']);
        $categories = isset($_POST['expression_categories']) ? $_POST['expression_categories'] : [];
        $category = !empty($categories) ? implode(',', $categories) : 'emotion';
        $status = isset($_POST['expression_status']) ? 1 : 0;
        
        // 验证表单数据
        if (empty($title)) {
            throw new Exception('图片标题不能为空');
        }
        
        // 检查是否有文件上传
        if (isset($_FILES['expression_file']) && $_FILES['expression_file']['error'] === UPLOAD_ERR_OK) {
            // 处理文件上传
            $file_tmp = $_FILES['expression_file']['tmp_name'];
            $file_name = $_FILES['expression_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // 验证文件类型
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('不支持的文件类型，请上传jpg、jpeg、png、gif或webp格式的图片');
            }
            
            // 构建上传路径
            $upload_dir = '../assets/expressions/';
            
            // 确保目录存在
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('无法创建目录: ' . $upload_dir);
                }
            }
            
            // 获取原有图片路径
            $stmt = $conn->prepare("SELECT image_path FROM expression_images WHERE id = ?");
            $stmt->execute([$id]);
            $expression = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($expression) {
                // 删除原有文件
                $old_file_path = '../' . $expression['image_path'];
                if (file_exists($old_file_path)) {
                    @unlink($old_file_path);
                }
            }
            
            // 生成唯一文件名
            $original_name = pathinfo($file_name, PATHINFO_FILENAME);
            // 清理文件名，移除特殊字符
            $original_name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $original_name);
            // 如果清理后文件名为空，使用默认名称
            if (empty($original_name)) {
                $original_name = 'expression';
            }
            // 生成唯一文件名：原始名称_随机数.扩展名
            $random_suffix = substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $new_file_name = $original_name . '_' . $random_suffix . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            // 移动上传的文件
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                throw new Exception('无法上传文件');
            }
            
            // 更新数据库
            $relative_path = '/assets/expressions/' . $new_file_name;
            $stmt = $conn->prepare("UPDATE expression_images SET title = ?, description = ?, image_path = ?, category = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $description, $relative_path, $category, $status, $id]);
        } else {
            // 仅更新文本信息
            $stmt = $conn->prepare("UPDATE expression_images SET title = ?, description = ?, category = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $description, $category, $status, $id]);
        }
        
        showToast('图片更新成功！');
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 处理更新语音
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_audio'])) {
    try {
        // 获取表单数据
        $id = intval($_POST['audio_id']);
        $title = trim($_POST['audio_title']);
        $description = trim($_POST['audio_description']);
        $categories = isset($_POST['audio_categories']) ? $_POST['audio_categories'] : [];
        $category = !empty($categories) ? $categories[0] : 'waiting';
        $position = isset($_POST['audio_position']) ? intval($_POST['audio_position']) : 0;
        $status = isset($_POST['audio_status']) ? 1 : 0;
        
        // 验证表单数据
        if (empty($title)) {
            throw new Exception('语音标题不能为空');
        }
        
        // 检查是否有文件上传
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            // 处理文件上传
            $file_tmp = $_FILES['audio_file']['tmp_name'];
            $file_name = $_FILES['audio_file']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // 验证文件类型
            $allowed_extensions = ['mp3', 'wav', 'ogg'];
            
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('不支持的文件类型，请上传mp3、wav或ogg格式的音频');
            }
            
            // 构建上传路径
            $upload_dir = '../assets/audios/';
            
            // 确保目录存在
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception('无法创建目录: ' . $upload_dir);
                }
            }
            
            // 获取原有音频路径
            $stmt = $conn->prepare("SELECT audio_path FROM expression_audios WHERE id = ?");
            $stmt->execute([$id]);
            $audio = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($audio) {
                // 删除原有文件
                $old_file_path = '../' . $audio['audio_path'];
                if (file_exists($old_file_path)) {
                    @unlink($old_file_path);
                }
            }
            
            // 生成唯一文件名
            $original_name = pathinfo($file_name, PATHINFO_FILENAME);
            // 清理文件名，移除特殊字符
            $original_name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $original_name);
            // 如果清理后文件名为空，使用默认名称
            if (empty($original_name)) {
                $original_name = 'audio';
            }
            // 生成唯一文件名：原始名称_随机数.扩展名
            $random_suffix = substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $new_file_name = $original_name . '_' . $random_suffix . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            // 移动上传的文件
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                throw new Exception('无法上传文件');
            }
            
            // 更新数据库
            $relative_path = '/assets/audios/' . $new_file_name;
            $stmt = $conn->prepare("UPDATE expression_audios SET title = ?, description = ?, audio_path = ?, category = ?, display_order = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $description, $relative_path, $category, $position, $status, $id]);
        } else {
            // 仅更新文本信息
            $stmt = $conn->prepare("UPDATE expression_audios SET title = ?, description = ?, category = ?, display_order = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $description, $category, $position, $status, $id]);
        }
        
        showToast('语音更新成功！');
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 获取所有图片
$expressions = [];
try {
    $stmt = $conn->prepare("SELECT * FROM expression_images ORDER BY category ASC, created_at DESC");
    $stmt->execute();
    $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = '获取图片失败: ' . $e->getMessage();
}

// 获取所有语音
$audios = [];
try {
    $stmt = $conn->prepare("SELECT * FROM expression_audios ORDER BY display_order ASC, category ASC, created_at DESC");
    $stmt->execute();
    $audios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = '获取语音失败: ' . $e->getMessage();
}

// 获取所有商品
$products = [];
if ($shopcarTableExists) {
    try {
        $stmt = $conn->prepare("SELECT * FROM shopcar_products ORDER BY position ASC, id DESC");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 确保所有字段都有值，避免undefined
        foreach ($products as &$product) {
            $product['title'] = $product['title'] ?? '';
            $product['price'] = $product['price'] ?? '';
            $product['description'] = $product['description'] ?? '';
            $product['link'] = $product['link'] ?? '';
            $product['position'] = $product['position'] ?? 0;
            $product['active'] = $product['active'] ?? 1;
            $product['image'] = $product['image'] ?? '';
            $product['series_id'] = $product['series_id'] ?? null;
            $product['series_label'] = shopcarGetSeriesDisplayName($product['series_id'], $shopcarSeries);
        }
        unset($product);

        $productGroups = shopcarGroupProductsBySeries($products, $shopcarSeries);
        
    } catch (PDOException $e) {
        $error = '获取商品失败: ' . $e->getMessage();
        error_log('获取商品失败: ' . $e->getMessage());
    }
}
$productGroups = $productGroups ?? [];

// 设置页面标题
$page_title = "上传管理"; 

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

.nagisa-input, .nagisa-textarea, .nagisa-select {
    border: 2px solid rgba(204, 148, 113, 0.3);
    transition: all 0.3s ease;
    width: 100%;
    padding: 8px 12px;
    border-radius: 8px;
}

.nagisa-input:focus, .nagisa-textarea:focus, .nagisa-select:focus {
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

/* 图片管理样式 */
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

.expression-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

.expression-item {
    background-color: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.expression-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
}

.expression-image {
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background-color: #f8fafc;
    position: relative;
}

.expression-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.expression-info {
    padding: 15px;
}

.expression-title {
    font-weight: 600;
    color: #334155;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.expression-category {
    margin-bottom: 10px;
    min-height: 28px;
}

.product-card-series-line {
    font-size: 0.875rem;
    color: #475569;
    line-height: 1.4;
}

.product-card-title-line {
    font-weight: 600;
    font-size: 1rem;
    color: #1e293b;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-card-meta-line {
    font-size: 0.75rem;
    color: #64748b;
    line-height: 1.4;
}

.product-grid-by-series {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.product-series-group {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
}

.product-series-group-header-bar {
    display: flex;
    align-items: center;
    background: linear-gradient(45deg, #f8fafc, #f1f5f9);
}

.product-series-group-header {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border: none;
    cursor: pointer;
    text-align: left;
    background: transparent;
    color: #334155;
    font-size: 0.95rem;
    font-weight: 600;
    transition: background 0.2s ease;
}

.product-series-group-toggle {
    flex-shrink: 0;
    padding: 0 16px;
    display: flex;
    align-items: center;
}

.product-series-group-header-bar:hover {
    background: linear-gradient(45deg, #f1f5f9, #e2e8f0);
}

.product-series-group.is-inactive .product-series-group-title {
    text-decoration: line-through;
    text-decoration-color: #94a3b8;
    color: #94a3b8;
}

.product-series-group-chevron {
    color: #cc9471;
    font-size: 0.85rem;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

.product-series-group.is-collapsed .product-series-group-chevron {
    transform: rotate(-90deg);
}

.product-series-group-title {
    flex: 1;
    min-width: 0;
}

.product-series-group-count {
    font-size: 0.75rem;
    font-weight: 500;
    color: #64748b;
    background: #e2e8f0;
    padding: 2px 8px;
    border-radius: 9999px;
}

.product-series-group.is-filter-empty {
    display: none !important;
}

.product-series-group-body {
    padding: 16px;
    border-top: 1px solid #e2e8f0;
}

.product-series-group.is-collapsed .product-series-group-body {
    display: none;
}

.series-list-item.is-inactive .series-item-title {
    text-decoration: line-through;
    text-decoration-color: #94a3b8;
    color: #94a3b8;
}

.series-list-item.is-inactive .series-item-desc,
.series-list-item.is-inactive .series-item-position {
    color: #94a3b8;
}

.expression-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
}

.expression-toggle {
    display: flex;
    align-items: center;
}

/* 开关样式 */
.switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 20px;
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
    border-radius: 20px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 2px;
    bottom: 2px;
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
    transform: translateX(20px);
}

/* 标签管理样式 */
#category-checkboxes {
    max-height: 150px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

#category-checkboxes::-webkit-scrollbar {
    width: 6px;
}

#category-checkboxes::-webkit-scrollbar-track {
    background: #e5e7eb;
    border-radius: 3px;
}

#category-checkboxes::-webkit-scrollbar-thumb {
    background: #10b981;
    border-radius: 3px;
}

#category-checkboxes::-webkit-scrollbar-thumb:hover {
    background: #059669;
}

/* 图片和商品网格样式 */
.item-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.item-card {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    position: relative;
}

.item-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

/* 过滤后的项目样式 */
.expression-item.filtered-out,
.audio-item.filtered-out {
    display: none !important;
}

/* 确保网格布局正常工作 */
.expression-grid,
.audio-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

.item-image-container {
    height: 200px;
    width: 100%;
    overflow: hidden;
    position: relative;
    background-color: #f9f9f9;
    display: flex;
    align-items: center;
    justify-content: center;
}

.item-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    display: block;
}

.item-audio-container {
    height: 60px;
    width: 100%;
    background-color: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
}

.item-info {
    padding: 15px;
}

.item-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.5);
    color: #fff;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 0.8rem;
}

.item-title {
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
    font-size: 1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.item-description {
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 10px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.item-price {
    font-weight: bold;
    color: #cc9471;
    margin-bottom: 10px;
}

.item-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
}

.status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 5px;
}

.status-active {
    background-color: rgba(52, 211, 153, 0.2);
    color: #059669;
}

.status-inactive {
    background-color: rgba(239, 68, 68, 0.2);
    color: #dc2626;
}

/* 音频播放器样式 */
.audio-player {
    width: 100%;
    height: 40px;
}

/* 语音管理样式 */
.audio-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

.audio-item {
    background-color: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.audio-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
}

.audio-player-container {
    padding: 15px;
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.audio-info {
    padding: 15px;
}

.audio-title {
    font-weight: 600;
    color: #334155;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.audio-category {
    margin-bottom: 10px;
    min-height: 28px;
}

.audio-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
}

.audio-toggle {
    display: flex;
    align-items: center;
}

#audio-category-checkboxes {
    max-height: 150px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

#audio-category-checkboxes::-webkit-scrollbar {
    width: 6px;
}

#audio-category-checkboxes::-webkit-scrollbar-track {
    background: #e5e7eb;
    border-radius: 3px;
}

#audio-category-checkboxes::-webkit-scrollbar-thumb {
    background: #10b981;
    border-radius: 3px;
}

#audio-category-checkboxes::-webkit-scrollbar-thumb:hover {
    background: #059669;
}

/* 预览样式 */
.preview-container {
    background-color: #f9f9f9;
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
    border: 1px solid #e2e8f0;
}

.preview-title {
    font-weight: 600;
    margin-bottom: 10px;
    color: #333;
}

.preview-image {
    max-width: 100%;
    max-height: 200px;
    display: block;
    margin: 0 auto;
}

/* 多选框样式 */
.checkbox-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 5px;
}

.checkbox-item {
    display: flex;
    align-items: center;
}

.checkbox-item input[type="checkbox"],
.checkbox-item input[type="radio"] {
    margin-right: 5px;
}

/* 分页样式 */
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}

.pagination-item {
    margin: 0 5px;
    padding: 5px 10px;
    border-radius: 4px;
    background-color: #fff;
    border: 1px solid #e2e8f0;
    color: #333;
    text-decoration: none;
    transition: all 0.3s ease;
}

.pagination-item:hover {
    background-color: #f3f4f6;
}

.pagination-item.active {
    background-color: #cc9471;
    color: white;
    border-color: #cc9471;
}

.pagination-item.disabled {
    color: #cbd5e0;
    cursor: not-allowed;
}

/* 分类复选框样式 */
.category-checkbox-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    border-radius: 8px;
    background-color: white;
    border: 2px solid #e2e8f0;
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    height: 40px;
}

.category-checkbox-item:hover {
    border-color: #10b981;
    background-color: #f8fafc;
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
    font-weight: 700;
    background: #10b981;
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

.category-label {
    font-size: 0.9rem;
    color: #334155;
    transition: all 0.2s ease;
    position: relative;
    text-align: center;
    width: 100%;
}

/* 预览容器样式 */
.nagisa-preview-container {
    margin-top: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.nagisa-preview-title {
    background-color: #f1f5f9;
    padding: 10px 15px;
    font-weight: 600;
    color: #334155;
    border-bottom: 1px solid #e2e8f0;
}

/* 旧组件样式兼容 */
.old-style-form {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.old-style-form .form-header {
    background-color: #e9967a;
    color: white;
    padding: 12px 16px;
    font-size: 18px;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

.old-style-form .form-body {
    padding: 16px;
}

.old-style-form .form-group {
    margin-bottom: 16px;
}

.old-style-form .form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.old-style-form .form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #fff;
}

.old-style-form .form-check {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #fff;
    margin-bottom: 8px;
    cursor: pointer;
}

.old-style-form .form-check:hover {
    background-color: #f9f9f9;
}

.old-style-form .form-check-input {
    margin-right: 8px;
}

.old-style-form .btn-group {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
}

.old-style-form .btn {
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    border: none;
}

.old-style-form .btn-primary {
    background-color: #e9967a;
    color: white;
}

.old-style-form .btn-primary:hover {
    background-color: #cc9471;
}

.old-style-form .btn-secondary {
    background-color: #e2e8f0;
    color: #333;
}

.old-style-form .btn-secondary:hover {
    background-color: #cbd5e0;
}

.old-style-form .form-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 24px;
}

.old-style-form .form-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.old-style-form .form-switch-slider {
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

.old-style-form .form-switch-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

.old-style-form input:checked + .form-switch-slider {
    background-color: #e9967a;
}

.old-style-form input:checked + .form-switch-slider:before {
    transform: translateX(24px);
}

/* 新的标签按钮样式 */
.tag-button-group {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    align-items: stretch;
}

.tag-button {
    display: block;
    width: 100%;
    padding: 8px 12px;
    text-align: center;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    background-color: white;
    color: #334155;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
    min-height: 40px;
    box-sizing: border-box;
}

.tag-button:hover {
    border-color: #10b981;
    background-color: #f8fafc;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.1);
}

.tag-button input[type="checkbox"] {
    display: none;
}

.tag-button input[type="checkbox"]:checked + span {
    color: #ffffff;
    font-weight: 700;
    background: #10b981;
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
    width: 100%;
    height: 100%;
    box-sizing: border-box;
}

.tag-button-selected {
    background-color: #10b981;
    border-color: #10b981;
    color: #ffffff;
}

/* 确保按钮尺寸一致 */
.tag-button span {
    display: block;
    width: 100%;
    height: 100%;
    line-height: 1.2;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* 防止按钮变形 */
.tag-button:has(input[type="checkbox"]:checked) {
    background-color: transparent;
    border-color: transparent;
}

/* 确保网格布局稳定 */
.tag-button-group > * {
    min-height: 40px;
}
'; 

// 包含统一页眉
include 'admin_header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 sm:grid-cols-12 gap-6">
        <!-- 侧边导航 -->
        <div class="sm:col-span-3 col-span-12">
            <div class="nagisa-card">
                <h2 class="nagisa-card-header">上传管理</h2>
                <div class="p-4">
                    <ul class="space-y-1">
                        <li>
                            <a href="?section=expressions" class="nagisa-nav-link" onclick="showSection('expressions'); return false;">
                                <i class="fas fa-image mr-2"></i>图片管理
                            </a>
                        </li>
                        <li>
                            <a href="?section=audios" class="nagisa-nav-link" onclick="showSection('audios'); return false;">
                                <i class="fas fa-volume-up mr-2"></i>语音管理
                            </a>
                        </li>
                        <li>
                            <a href="?section=products" class="nagisa-nav-link" onclick="showSection('products'); return false;">
                                <i class="fas fa-shopping-cart mr-2"></i>商品管理
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
                            <span>图片管理：上传和管理网站图片</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>语音管理：上传和管理网站语音素材</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>商品管理：添加和管理购物车商品</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>支持多种文件格式和分类管理</span>
                        </li>
                    </ul>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <a href="fix_expression_paths.php" class="flex items-center text-blue-600 hover:text-blue-800">
                            <i class="fas fa-tools mr-2"></i>
                            <span>修复图片和语音路径格式</span>
                        </a>
                        <p class="text-xs text-gray-500 mt-1">统一所有文件路径格式，确保前台正确显示</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 主要内容区域 -->
        <div class="sm:col-span-9 col-span-12">
            <!-- 图片管理部分 -->
            <div id="expressions" class="nagisa-card section-content">
                <h2 class="nagisa-card-header" style="background: linear-gradient(45deg, #e9967a, #cc9471);">图片管理</h2>
                <div class="p-6">
                    <!-- 搜索和过滤栏 -->
                    <div class="search-filter-bar">
                        <div class="flex gap-2" style="min-width:300px;">
                            <input type="text" id="expression-search" placeholder="搜索图片..." class="search-input">
                            <select id="expression-category-filter" class="filter-select">
                                <option value="all">所有分类</option>
                                <?php 
                                // 获取所有图片分类
                                $expr_categories = [];
                                foreach ($expressions as $expr) {
                                    $cats = explode(',', $expr['category']);
                                    foreach ($cats as $cat) {
                                        $cat = trim($cat);
                                        if (!empty($cat) && !in_array($cat, $expr_categories)) {
                                            $expr_categories[] = $cat;
                                        }
                                    }
                                }
                                foreach($expr_categories as $category): 
                                ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="showTagManager()" class="nagisa-btn" style="background: linear-gradient(45deg, #8b5cf6, #a855f7);">
                                <i class="fas fa-tags mr-2"></i>标签管理
                            </button>
                            <button type="button" onclick="showAddExpressionForm()" class="nagisa-btn">
                                <i class="fas fa-plus mr-2"></i>添加图片
                            </button>
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
                                    <h4 class="text-xl font-medium text-gray-700">图片分类标签管理</h4>
                                    <p class="text-sm text-gray-500 mt-1">管理图片的分类标签，方便用户筛选和查找</p>
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
                                            <p class="text-xs text-gray-500 mt-1">为图片设置分类标签</p>
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
                                            <?php if (empty($expr_categories)): ?>
                                            <div class="text-center py-8 text-gray-500">
                                                <i class="fas fa-tags text-4xl mb-3"></i>
                                                <p>暂无标签，请添加新标签</p>
                                            </div>
                                            <?php else: ?>
                                            <?php foreach($expr_categories as $category): ?>
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
                                                    <button type="button" onclick="openDeleteTagModal('<?php echo htmlspecialchars($category); ?>', 'expression')" class="nagisa-btn-danger nagisa-btn-mini">
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
                            
                            <div class="mt-6 p-4 bg-blue-50 text-blue-700 rounded-lg border border-blue-200">
                                <h6 class="font-medium mb-2">
                                    <i class="fas fa-info-circle mr-2"></i>标签使用说明
                                </h6>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li>• 标签用于对图片进行分类，方便用户筛选</li>
                                    <li>• 添加标签后，可以在图片编辑时选择使用</li>
                                    <li>• 删除标签不会影响已存在的图片</li>
                                    <li>• 建议使用简洁明了的标签名称</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 添加图片表单 -->
                    <div id="add-expression-form" class="nagisa-card" style="display: none; margin-bottom: 20px;">
                        <h3 class="nagisa-card-header" style="background: linear-gradient(45deg, #e9967a, #cc9471);">
                            <i class="fas fa-plus mr-2"></i>图片管理
                        </h3>
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-xl font-medium text-gray-700" id="expression-form-title">添加新图片</h3>
                                <button type="button" onclick="hideAddExpressionForm()" class="nagisa-btn-secondary">
                                    <i class="fas fa-arrow-left mr-2"></i>返回列表
                                </button>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="expression_id" id="expression_id" value="">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label">图片标题 <span class="text-red-500">*</span></label>
                                            <input type="text" class="nagisa-input" name="expression_title" required>
                                            <p class="text-xs text-gray-500 mt-1">给图片起一个简短明了的名称</p>
                                        </div>
                                        
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label">分类标签</label>
                                            <div class="flex gap-3">
                                                <div class="flex-1">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="text-sm font-medium text-gray-700">选择分类</span>
                                                    </div>
                                                    <div id="category-checkboxes" class="border-2 border-gray-200 rounded-xl p-4 max-h-40 overflow-y-auto bg-gray-50 hover:border-gray-300 transition-colors relative">
                                                        <?php if (empty($expr_categories)): ?>
                                                        <div class="text-center py-4 text-gray-500">
                                                            <i class="fas fa-tags text-2xl mb-2"></i>
                                                            <p class="text-sm">暂无分类，请先创建分类</p>
                                                        </div>
                                                        <?php else: ?>
                                                        <div class="tag-button-group">
                                                            <?php foreach($expr_categories as $category): ?>
                                                            <label class="tag-button <?php echo ($category === 'emotion') ? 'tag-button-selected' : ''; ?>">
                                                                <input type="checkbox" name="expression_categories[]" value="<?php echo htmlspecialchars($category); ?>" <?php echo ($category === 'emotion') ? 'checked' : ''; ?> onchange="toggleTagButton(this)">
                                                                <span><?php echo htmlspecialchars($category); ?></span>
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
                                                为图片设置一个或多个分类，方便用户筛选和查找
                                            </p>
                                        </div>
                                        
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label">描述</label>
                                            <textarea class="nagisa-textarea" name="expression_description" rows="4"></textarea>
                                            <p class="text-xs text-gray-500 mt-1">可选，添加图片的详细描述</p>
                                        </div>
                                        
                                        <div class="nagisa-form-group">
                                            <label class="flex items-center">
                                                <span class="mr-2">启用图片</span>
                                                <label class="switch">
                                                    <input type="checkbox" name="expression_status" checked>
                                                    <span class="slider"></span>
                                                </label>
                                            </label>
                                            <p class="text-xs text-gray-500 mt-1">未启用的图片不会在前台页面显示</p>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label" id="expression-file-label">上传图片 <span class="text-red-500">*</span></label>
                                            <input type="file" name="expression_file" id="expression_file" accept="image/*" class="nagisa-input" required onchange="previewExpressionImage(this)">
                                            <p class="text-xs text-gray-500 mt-1">支持JPG、PNG、GIF等常见图片格式</p>
                                        </div>
                                        
                                        <div id="expression-preview-container" class="nagisa-preview-container" style="display:none;">
                                            <h3 class="nagisa-preview-title">图片预览</h3>
                                            <div class="p-4 bg-gray-50 rounded-lg">
                                                <img id="expression-preview-image" class="max-w-full max-h-80 mx-auto" src="" alt="预览">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end mt-6 space-x-2">
                                    <button type="button" onclick="hideAddExpressionForm()" class="nagisa-btn-secondary">
                                        <i class="fas fa-times mr-2"></i>取消
                                    </button>
                                    <button type="submit" id="expression-submit-btn" name="add_expression" class="nagisa-btn">
                                        <i class="fas fa-plus mr-2"></i>添加图片
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- 表情列表 -->
                    <?php if (empty($expressions)): ?>
                    <div class="flex flex-col items-center justify-center py-10">
                        <div class="text-gray-400 mb-4 text-7xl">
                            <i class="fas fa-images"></i>
                        </div>
                        <p class="text-gray-500 mb-6">暂无图片，点击添加按钮上传新图片</p>
                        <button type="button" onclick="showAddExpressionForm()" class="nagisa-btn">
                            <i class="fas fa-plus mr-2"></i>添加图片
                        </button>
                    </div>
                    <?php else: ?>
                    <!-- 表情网格视图 -->
                    <div class="expression-grid" id="expression-grid">
                        <?php foreach ($expressions as $expr): ?>
                        <div class="expression-item" data-id="<?php echo $expr['id']; ?>" data-category="<?php echo htmlspecialchars($expr['category']); ?>" data-title="<?php echo htmlspecialchars($expr['title']); ?>" data-description="<?php echo htmlspecialchars($expr['description']); ?>">
                            <div class="expression-image">
                                <img data-src="../<?php echo htmlspecialchars($expr['image_path']); ?>" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24' fill='%23cccccc'%3E%3Cpath d='M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2zm0 2v14h14V5H5zm11.5 9c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5.67-1.5 1.5-1.5 1.5.67 1.5 1.5zm-8-3h5V9h-5v2zm0-3h8V6h-8v2z'/%3E%3C/svg%3E" alt="<?php echo htmlspecialchars($expr['title']); ?>">
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
                                <?php if (!empty($expr['description'])): ?>
                                <div class="expression-description hidden"><?php echo htmlspecialchars($expr['description']); ?></div>
                                <?php endif; ?>
                                <div class="expression-category">
                                    <?php 
                                    // 显示分类标签
                                    $categories = explode(',', $expr['category']);
                                    foreach ($categories as $cat) {
                                        $cat = trim($cat);
                                        if (!empty($cat)) {
                                            echo '<span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-1 mb-1">' . htmlspecialchars($cat) . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="expression-actions">
                                    <button type="button" onclick="editExpression(<?php echo $expr['id']; ?>)" class="nagisa-btn nagisa-btn-mini"><i class="fas fa-edit mr-1"></i>编辑</button>
                                    <form id="delete-expression-form-<?php echo $expr['id']; ?>" method="POST" style="display:inline">
                                        <input type="hidden" name="expression_id" value="<?php echo $expr['id']; ?>">
                                        <button type="button" onclick="showDeleteConfirmModal('delete-expression-form-<?php echo $expr['id']; ?>', 'expression', '<?php echo htmlspecialchars($expr['title']); ?>')" class="nagisa-btn-danger nagisa-btn-mini"><i class="fas fa-trash mr-1"></i>删除</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 语音管理部分 -->
            <div id="audios" class="nagisa-card section-content" style="display: none;">
                <h2 class="nagisa-card-header" style="background: linear-gradient(45deg, #e9967a, #cc9471);">语音管理</h2>
                <div class="p-6">
                    <!-- 搜索和过滤栏 -->
                    <div class="search-filter-bar">
                        <div class="flex gap-2" style="min-width:300px;">
                            <input type="text" id="audio-search" placeholder="搜索语音..." class="search-input">
                            <select id="audio-category-filter" class="filter-select">
                                <option value="all">所有分类</option>
                                <?php 
                                // 获取所有语音分类
                                $audio_categories = [];
                                try {
                                    $stmt = $conn->prepare("SELECT * FROM audio_categories ORDER BY sort_order ASC, name ASC");
                                    $stmt->execute();
                                    $audio_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (PDOException $e) {
                                    // 如果表不存在，使用旧方法获取分类
                                    foreach ($audios as $audio) {
                                        $cat = trim($audio['category']);
                                        if (!empty($cat) && !in_array($cat, $audio_categories)) {
                                            $audio_categories[] = ['name' => $cat, 'position' => 0];
                                        }
                                    }
                                }
                                foreach($audio_categories as $category): 
                                ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="showAudioTagManager()" class="nagisa-btn" style="background: linear-gradient(45deg, #8b5cf6, #a855f7);">
                                <i class="fas fa-tags mr-2"></i>标签管理
                            </button>
                            <button type="button" onclick="showAddAudioForm()" class="nagisa-btn">
                                <i class="fas fa-plus mr-2"></i>添加语音
                            </button>
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
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label">显示顺序</label>
                                            <input type="number" id="new-audio-tag-position" class="nagisa-input" value="0">
                                            <p class="text-xs text-gray-500 mt-1">数字越小排序越靠前</p>
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
                                                        <div class="font-medium text-gray-700"><?php echo htmlspecialchars($category['name']); ?></div>
                                                    </div>
                                                </div>
                                                <div class="flex gap-2 items-center">
                                                    <div class="inline-block bg-gray-100 text-gray-500 text-xs px-2 py-1 rounded mr-2"><?php echo $category['sort_order']; ?></div>
                                                    <button type="button" onclick="editAudioTag('<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['sort_order']; ?>)" class="nagisa-btn nagisa-btn-mini" style="background: linear-gradient(45deg, #f59e0b, #d97706);">
                                                        <i class="fas fa-edit mr-1"></i>编辑
                                                    </button>
                                                    <button type="button" onclick="openDeleteTagModal('<?php echo htmlspecialchars($category['name']); ?>', 'audio')" class="nagisa-btn-danger nagisa-btn-mini">
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
                            
                            <div class="mt-6 p-4 bg-blue-50 text-blue-700 rounded-lg border border-blue-200">
                                <h6 class="font-medium mb-2">
                                    <i class="fas fa-info-circle mr-2"></i>标签使用说明
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
                    
                    <!-- 添加语音表单 -->
                    <div id="add-audio-form" class="nagisa-card" style="display: none; margin-bottom: 20px;">
                        <h3 class="nagisa-card-header" style="background: linear-gradient(45deg, #e9967a, #cc9471);">
                            <i class="fas fa-plus mr-2"></i>语音管理
                        </h3>
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-xl font-medium text-gray-700" id="audio-form-title">添加新语音</h3>
                                <button type="button" onclick="hideAddAudioForm()" class="nagisa-btn-secondary">
                                    <i class="fas fa-arrow-left mr-2"></i>返回列表
                                </button>
                            </div>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="audio_id" id="audio_id" value="">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label">语音标题 <span class="text-red-500">*</span></label>
                                            <input type="text" class="nagisa-input" name="audio_title" required>
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
                                                            <?php foreach($audio_categories as $category): ?>
                                                            <label class="category-checkbox-item">
                                                                <input type="radio" name="audio_categories[]" value="<?php echo htmlspecialchars($category['name']); ?>" class="category-checkbox" 
                                                                    <?php echo ($category['name'] === 'waiting' || $category['name'] === '未分类') ? 'checked' : ''; ?>>
                                                                <span class="category-label"><?php echo htmlspecialchars($category['name']); ?></span>
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
                                                为语音设置一个分类，方便用户筛选和查找
                                            </p>
                                        </div>
                                        
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label">描述</label>
                                            <textarea class="nagisa-textarea" name="audio_description" rows="4"></textarea>
                                            <p class="text-xs text-gray-500 mt-1">可选，添加语音的详细描述</p>
                                        </div>
                                        
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label">显示顺序</label>
                                            <input type="number" class="nagisa-input" name="audio_position" id="audio_position" value="0">
                                            <p class="text-xs text-gray-500 mt-1">数字越小排序越靠前</p>
                                        </div>
                                        
                                        <div class="nagisa-form-group">
                                            <label class="flex items-center">
                                                <span class="mr-2">启用语音</span>
                                                <label class="switch">
                                                    <input type="checkbox" name="audio_status" checked>
                                                    <span class="slider"></span>
                                                </label>
                                            </label>
                                            <p class="text-xs text-gray-500 mt-1">未启用的语音不会在前台页面显示</p>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <div class="nagisa-form-group">
                                            <label class="nagisa-label" id="audio-file-label">语音文件 <span class="text-red-500">*</span></label>
                                            <input type="file" name="audio_file" id="audio_file" accept="audio/*" class="nagisa-input" required onchange="previewAudio(this)">
                                            <p class="text-xs text-gray-500 mt-1">支持MP3、WAV和OGG格式</p>
                                        </div>
                                        
                                        <div id="audio-preview-container" class="nagisa-preview-container" style="display:none;">
                                            <h3 class="nagisa-preview-title">音频预览</h3>
                                            <div class="p-4 bg-gray-50 rounded-lg">
                                                <audio id="audio-preview" controls class="w-full"></audio>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end mt-6 space-x-2">
                                    <button type="button" onclick="hideAddAudioForm()" class="nagisa-btn-secondary">
                                        <i class="fas fa-times mr-2"></i>取消
                                    </button>
                                    <button type="submit" id="audio-submit-btn" name="add_audio" class="nagisa-btn">
                                        <i class="fas fa-plus mr-2"></i>添加语音
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- 语音列表 -->
                    <?php if (empty($audios)): ?>
                    <div class="flex flex-col items-center justify-center py-10">
                        <div class="text-gray-400 mb-4 text-7xl">
                            <i class="fas fa-music"></i>
                        </div>
                        <p class="text-gray-500 mb-6">暂无语音，点击添加按钮上传新语音</p>
                        <button type="button" onclick="showAddAudioForm()" class="nagisa-btn">
                            <i class="fas fa-plus mr-2"></i>添加语音
                        </button>
                    </div>
                    <?php else: ?>
                    <!-- 语音网格视图 -->
                    <div class="audio-grid" id="audio-grid">
                        <?php foreach ($audios as $audio): ?>
                        <div class="audio-item" data-id="<?php echo $audio['id']; ?>" data-category="<?php echo htmlspecialchars($audio['category']); ?>" data-title="<?php echo htmlspecialchars($audio['title']); ?>" data-description="<?php echo htmlspecialchars($audio['description']); ?>" data-position="<?php echo htmlspecialchars($audio['display_order']); ?>">
                            <div class="audio-player-container">
                                <audio controls class="audio-player">
                                    <source src="../<?php echo htmlspecialchars($audio['audio_path']); ?>" type="audio/mpeg">
                                    您的浏览器不支持音频播放
                                </audio>
                            </div>
                            <div class="audio-info">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="audio-title" title="<?php echo htmlspecialchars($audio['title']); ?>"><?php echo htmlspecialchars($audio['title']); ?></div>
                                    <div class="audio-toggle">
                                        <label class="switch">
                                            <input type="checkbox" class="status-toggle" data-id="<?php echo $audio['id']; ?>" data-type="audio" <?php echo $audio['status'] ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                                <?php if (!empty($audio['description'])): ?>
                                <div class="audio-description hidden"><?php echo htmlspecialchars($audio['description']); ?></div>
                                <?php endif; ?>
                                <div class="flex justify-between items-center">
                                    <div class="audio-category">
                                        <span class="inline-block bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded"><?php echo htmlspecialchars($audio['category']); ?></span>
                                    </div>
                                    <div>
                                        <span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded"><?php echo htmlspecialchars($audio['display_order']); ?></span>
                                    </div>
                                </div>
                                <div class="audio-actions flex justify-between items-center mt-2">
                                    <button type="button" onclick="editAudio(<?php echo $audio['id']; ?>)" class="nagisa-btn nagisa-btn-mini"><i class="fas fa-edit mr-1"></i>编辑</button>
                                    <form id="delete-audio-form-<?php echo $audio['id']; ?>" method="POST" style="display:inline">
                                        <input type="hidden" name="audio_id" value="<?php echo $audio['id']; ?>">
                                        <button type="button" onclick="showDeleteConfirmModal('delete-audio-form-<?php echo $audio['id']; ?>', 'audio', '<?php echo htmlspecialchars($audio['title']); ?>')" class="nagisa-btn-danger nagisa-btn-mini"><i class="fas fa-trash mr-1"></i>删除</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

                                    <!-- 商品管理部分 -->
                        <div id="products" class="nagisa-card section-content" style="display: none;">
                            <h2 class="nagisa-card-header" style="background: linear-gradient(45deg, #e9967a, #cc9471);">商品管理</h2>
                            <div class="p-6">

                                
                                <?php if (!$shopcarTableExists): ?>
                                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-yellow-700">
                                                    购物车商品表不存在。请先 <a href="install_shopcar_table.php" class="font-medium underline text-yellow-700 hover:text-yellow-600">安装数据表</a>。
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                        <!-- 搜索和过滤栏 -->
                        <div class="search-filter-bar">
                            <div class="flex gap-2" style="min-width:300px;">
                                <input type="text" id="product-search" placeholder="搜索商品..." class="search-input">
                                <select id="product-status-filter" class="filter-select">
                                    <option value="all">所有状态</option>
                                    <option value="1">显示中</option>
                                    <option value="0">已隐藏</option>
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <?php if ($shopcarSeriesTableExists): ?>
                                <button type="button" onclick="showSeriesManager()" class="nagisa-btn" style="background: linear-gradient(45deg, #8b5cf6, #a855f7);">
                                    <i class="fas fa-tags mr-2"></i>系列管理
                                </button>
                                <?php endif; ?>
                                <button type="button" onclick="showAddProductForm()" class="nagisa-btn">
                                    <i class="fas fa-plus mr-2"></i>添加商品
                                </button>
                            </div>
                        </div>

                        <!-- 商品系列管理界面（仿标签管理） -->
                        <?php if ($shopcarSeriesTableExists): ?>
                        <div id="series-manager-section" class="nagisa-card" style="display: none; margin-bottom: 20px;">
                            <h3 class="nagisa-card-header" style="background: linear-gradient(45deg, #8b5cf6, #a855f7);">
                                <i class="fas fa-tags mr-2"></i>系列管理
                            </h3>
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <div>
                                        <h4 class="text-xl font-medium text-gray-700">商品系列管理</h4>
                                        <p class="text-sm text-gray-500 mt-1">管理商品系列，方便分类展示与绑定商品</p>
                                    </div>
                                    <button type="button" onclick="hideSeriesManager()" class="nagisa-btn-secondary">
                                        <i class="fas fa-times mr-2"></i>关闭管理
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <div class="nagisa-card">
                                        <h5 class="nagisa-card-header" style="background: linear-gradient(45deg, #10b981, #059669);">
                                            <i class="fas fa-plus mr-2"></i>添加商品系列
                                        </h5>
                                        <div class="p-4">
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">系列名称 <span class="text-red-500">*</span></label>
                                                <input type="text" id="new-series-name" class="nagisa-input">
                                            </div>
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">系列说明</label>
                                                <textarea id="new-series-description" class="nagisa-textarea" rows="3"></textarea>
                                            </div>
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">显示顺序</label>
                                                <input type="number" id="new-series-position" class="nagisa-input" value="0">
                                            </div>
                                            <div class="nagisa-form-group">
                                                <label class="flex items-center">
                                                    <span class="mr-2">前台显示</span>
                                                    <label class="switch">
                                                        <input type="checkbox" id="new-series-active" checked>
                                                        <span class="slider"></span>
                                                    </label>
                                                </label>
                                            </div>
                                            <button type="button" onclick="addNewSeries()" class="nagisa-btn w-full" style="background: linear-gradient(45deg, #10b981, #059669);">
                                                <i class="fas fa-plus mr-2"></i>添加商品系列
                                            </button>
                                        </div>
                                    </div>

                                    <div class="nagisa-card">
                                        <h5 class="nagisa-card-header" style="background: linear-gradient(45deg, #3b82f6, #2563eb);">
                                            <i class="fas fa-list mr-2"></i>现有系列
                                        </h5>
                                        <div class="p-4">
                                            <div id="existing-series-list" class="space-y-3">
                                                <?php if (empty($shopcarSeries)): ?>
                                                <div class="text-center py-8 text-gray-500 series-empty-placeholder">
                                                    <i class="fas fa-tags text-4xl mb-3"></i>
                                                    <p>暂无商品系列，请添加</p>
                                                </div>
                                                <?php else: ?>
                                                <?php foreach ($shopcarSeries as $s): ?>
                                                <?php
                                                    $seriesJson = json_encode([
                                                        'id' => (int)$s['id'],
                                                        'title' => $s['title'],
                                                        'description' => $s['description'] ?? '',
                                                        'position' => (int)$s['position'],
                                                        'active' => (int)$s['active'],
                                                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                                                ?>
                                                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200 series-list-item<?php echo $s['active'] ? '' : ' is-inactive'; ?>" data-series-id="<?php echo (int)$s['id']; ?>" data-series="<?php echo rawurlencode($seriesJson); ?>">
                                                    <div class="flex items-center min-w-0 flex-1">
                                                        <div class="w-3 h-3 rounded-full mr-3 flex-shrink-0" style="background: linear-gradient(45deg, #e9967a, #cc9471);"></div>
                                                        <div class="min-w-0">
                                                            <p class="font-semibold text-gray-800 text-base series-item-title m-0"><?php echo htmlspecialchars($s['title']); ?></p>
                                                            <?php if (!empty($s['description'])): ?>
                                                            <p class="text-xs text-gray-500 mt-1 truncate series-item-desc"><?php echo htmlspecialchars($s['description']); ?></p>
                                                            <?php endif; ?>
                                                            <p class="text-xs text-gray-500 mt-1 m-0">
                                                                顺序: <span class="series-item-position font-medium text-gray-600"><?php echo (int)$s['position']; ?></span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="flex gap-2 flex-shrink-0 ml-3">
                                                        <button type="button" class="nagisa-btn nagisa-btn-mini series-edit-btn" style="background: linear-gradient(45deg, #f59e0b, #d97706);">
                                                            <i class="fas fa-edit mr-1"></i>编辑
                                                        </button>
                                                        <button type="button" onclick="openDeleteSeriesModal(<?php echo (int)$s['id']; ?>, this.closest('.series-list-item').querySelector('.series-item-title').textContent.trim())" class="nagisa-btn-danger nagisa-btn-mini">
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

                                <div class="mt-6 p-4 bg-blue-50 text-blue-700 rounded-lg border border-blue-200">
                                    <h6 class="font-medium mb-2">
                                        <i class="fas fa-info-circle mr-2"></i>使用说明
                                    </h6>
                                    <ul class="text-sm text-blue-700 space-y-1">
                                        <li>· 商品系列用于前台左侧分类导航</li>
                                        <li>· 添加系列后，可在商品编辑时选择绑定</li>
                                        <li>· 删除系列不会影响已存在商品，但需先解除绑定</li>
                                        <li>· 关闭「前台显示」后，该系列在前台隐藏；也可在商品列表的系列标题旁直接切换</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- 添加/编辑商品表单 -->
                        <div id="add-product-form" class="nagisa-card" style="display: none; margin-bottom: 20px;">
                            <h3 class="nagisa-card-header" style="background: linear-gradient(45deg, #e9967a, #cc9471);">
                                <i class="fas fa-plus mr-2"></i>商品管理
                            </h3>
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-6">
                                    <h3 class="text-xl font-medium text-gray-700" id="product-form-title">添加新商品</h3>
                                    <button type="button" onclick="hideAddProductForm()" class="nagisa-btn-secondary">
                                        <i class="fas fa-arrow-left mr-2"></i>返回列表
                                    </button>
                                </div>
                                
                                <form method="POST" enctype="multipart/form-data" id="add-product-form-content" onsubmit="return validateProductForm()">
                                    <!-- 隐藏字段，用于编辑模式 -->
                                    <input type="hidden" name="edit_product_id" id="product_id">
                                    <input type="hidden" name="edit_product_image_old" id="product_image_old">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <?php if ($shopcarSeriesTableExists): ?>
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">商品系列</label>
                                                <select class="nagisa-input" name="edit_product_series_id" id="product_series_id">
                                                    <?php echo shopcarSeriesSelectOptions($shopcarSeries); ?>
                                                </select>
                                            </div>
                                            <?php endif; ?>

                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">商品名称 <span class="text-red-500">*</span></label>
                                                <input type="text" class="nagisa-input" name="edit_product_title" id="product_title" required>
                                                <p class="text-xs text-gray-500 mt-1">给商品起一个简短明了的名称</p>
                                            </div>
                                            
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">价格</label>
                                                <input type="text" class="nagisa-input" name="edit_product_price" id="product_price">
                                                <p class="text-xs text-gray-500 mt-1">商品的价格信息</p>
                                            </div>
                                            
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">购买链接</label>
                                                <input type="text" class="nagisa-input" name="edit_product_link" id="product_link">
                                                <p class="text-xs text-gray-500 mt-1">商品的购买链接（可选）</p>
                                            </div>

                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">显示顺序</label>
                                                <input type="number" class="nagisa-input" name="edit_product_position" id="product_position" value="0">
                                                <p class="text-xs text-gray-500 mt-1">数字越小排序越靠前</p>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label">商品描述</label>
                                                <textarea class="nagisa-textarea" name="edit_product_description" id="product_description" rows="4"></textarea>
                                                <p class="text-xs text-gray-500 mt-1">可选，添加商品的详细描述</p>
                                            </div>
                                            
                                            <div class="nagisa-form-group">
                                                <label class="nagisa-label" id="product-image-label">商品图片 <span class="text-red-500">*</span></label>
                                                <input type="file" name="edit_product_image" id="product_image" accept="image/*" class="nagisa-input" required onchange="previewProductImage(this)">
                                                <p class="text-xs text-gray-500 mt-1">支持JPG、PNG、GIF和WEBP格式</p>
                                            </div>
                                            
                                            <div class="nagisa-form-group">
                                                <label class="flex items-center">
                                                    <span class="mr-2">显示此商品</span>
                                                    <label class="switch">
                                                        <input type="checkbox" name="edit_product_active" id="product_active" checked>
                                                        <span class="slider"></span>
                                                    </label>
                                                </label>
                                                <p class="text-xs text-gray-500 mt-1">未启用的商品不会在前台页面显示</p>
                                            </div>
                                            
                                            <!-- 当前图片预览 -->
                                            <div id="product-current-image" class="nagisa-preview-container" style="display:none;">
                                                <h3 class="nagisa-preview-title">当前图片</h3>
                                                <div class="p-4 bg-gray-50 rounded-lg">
                                                    <img id="product-current-image-src" class="max-w-full max-h-80 mx-auto" src="" alt="当前图片">
                                                </div>
                                            </div>
                                            
                                            <!-- 新图片预览区域 -->
                                            <div id="product-preview-container" class="nagisa-preview-container" style="display:none;">
                                                <h3 class="nagisa-preview-title">新图片预览</h3>
                                                <div class="p-4 bg-gray-50 rounded-lg">
                                                    <img id="product-preview-image" class="max-w-full max-h-80 mx-auto" src="" alt="预览">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-end mt-6 space-x-2">
                                        <button type="button" onclick="hideAddProductForm()" class="nagisa-btn-secondary">
                                            <i class="fas fa-times mr-2"></i>取消
                                        </button>
                                        <button type="submit" name="add_product" id="product-submit-btn" class="nagisa-btn">
                                            <i class="fas fa-plus mr-2"></i>添加商品
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        

                        
                        <!-- 商品列表 -->
                        <?php if (empty($products)): ?>
                        <div class="flex flex-col items-center justify-center py-10">
                            <div class="text-gray-400 mb-4 text-7xl">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <p class="text-gray-500 mb-6">暂无商品，点击添加按钮上传新商品</p>
                            <button type="button" onclick="showAddProductForm()" class="nagisa-btn">
                                <i class="fas fa-plus mr-2"></i>添加商品
                            </button>
                        </div>
                        <?php else: ?>
                        <!-- 商品按系列分组（可折叠） -->
                        <div class="product-grid-by-series" id="product-grid">
                            <?php foreach ($productGroups as $group): ?>
                            <?php
                                $groupKey = $group['id'] > 0 ? 'series-' . (int)$group['id'] : 'uncategorized';
                                $groupTotal = count($group['products']);
                                $groupInactive = empty($group['active']);
                            ?>
                            <div class="product-series-group<?php echo $groupInactive ? ' is-inactive' : ''; ?>" data-series-key="<?php echo htmlspecialchars($groupKey); ?>" data-series-id="<?php echo (int)$group['id']; ?>" data-series-active="<?php echo $groupInactive ? '0' : '1'; ?>">
                                <div class="product-series-group-header-bar">
                                    <button type="button" class="product-series-group-header" onclick="toggleProductSeriesGroup(this)" aria-expanded="true">
                                        <i class="fas fa-chevron-down product-series-group-chevron"></i>
                                        <span class="product-series-group-title"><?php echo htmlspecialchars($group['title']); ?></span>
                                        <span class="product-series-group-count" data-total="<?php echo $groupTotal; ?>"><?php echo $groupTotal; ?> 件</span>
                                    </button>
                                    <?php if ((int)$group['id'] > 0): ?>
                                    <div class="product-series-group-toggle" onclick="event.stopPropagation()">
                                        <label class="switch" title="前台显示">
                                            <input type="checkbox" class="series-active-toggle" data-series-id="<?php echo (int)$group['id']; ?>" <?php echo $groupInactive ? '' : 'checked'; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-series-group-body expression-grid">
                                    <?php foreach ($group['products'] as $product): ?>
                                    <div class="expression-item"
                                         data-id="<?php echo (int)$product['id']; ?>"
                                         data-status="<?php echo (int)$product['active']; ?>"
                                         data-title="<?php echo htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                         data-category="product"
                                         data-price="<?php echo htmlspecialchars($product['price'], ENT_QUOTES, 'UTF-8'); ?>"
                                         data-description="<?php echo htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8'); ?>"
                                         data-link="<?php echo htmlspecialchars($product['link'], ENT_QUOTES, 'UTF-8'); ?>"
                                         data-position="<?php echo (int)$product['position']; ?>"
                                         data-image="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                         data-series-id="<?php echo (int)($product['series_id'] ?? 0); ?>">
                                        <div class="expression-image">
                                            <img data-src="../<?php echo htmlspecialchars($product['image']); ?>" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' width='24' height='24' fill='%23cccccc'%3E%3Cpath d='M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2zm0 2v14h14V5H5zm11.5 9c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5.67-1.5 1.5-1.5 1.5.67 1.5 1.5zm-8-3h5V9h-5v2zm0-3h8V6h-8v2z'/%3E%3C/svg%3E" alt="<?php echo htmlspecialchars($product['title']); ?>" onerror="this.src='../assets/images/placeholder.png'">
                                        </div>
                                        <div class="expression-info">
                                            <div class="flex items-start justify-between mb-2">
                                                <div class="flex-1 min-w-0 pr-2">
                                                    <p class="product-card-title-line m-0 mb-1.5" title="<?php echo htmlspecialchars($product['title']); ?>"><?php echo htmlspecialchars($product['title']); ?></p>
                                                    <p class="product-card-meta-line m-0">
                                                        价格: <span class="font-medium text-gray-600"><?php echo $product['price'] !== '' ? htmlspecialchars($product['price']) : '—'; ?></span>
                                                        <span class="mx-1 text-gray-300">·</span>
                                                        顺序: <span class="font-medium text-gray-600"><?php echo (int)$product['position']; ?></span>
                                                    </p>
                                                </div>
                                                <div class="expression-toggle flex-shrink-0">
                                                    <label class="switch">
                                                        <input type="checkbox" class="status-toggle" data-id="<?php echo $product['id']; ?>" data-type="product" <?php echo $product['active'] ? 'checked' : ''; ?>>
                                                        <span class="slider"></span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="expression-actions">
                                                <button type="button" class="nagisa-btn nagisa-btn-mini product-edit-btn"><i class="fas fa-edit mr-1"></i>编辑</button>
                                                <form id="delete-product-form-<?php echo $product['id']; ?>" method="POST" style="display:inline">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <button type="button" onclick="showDeleteConfirmModal('delete-product-form-<?php echo $product['id']; ?>', 'product', '<?php echo htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8'); ?>')" class="nagisa-btn-danger nagisa-btn-mini"><i class="fas fa-trash mr-1"></i>删除</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
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

<!-- 自定义编辑弹窗 -->
<div id="edit-tag-modal" class="fixed inset-0 flex items-center justify-center z-50" style="display: none;">
    <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeEditTagModal()"></div>
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 z-10 overflow-hidden border-2 border-[#cc9471]">
        <div class="bg-gradient-to-r from-[#cc9471] to-[#f3b4a4] text-white px-6 py-4 flex justify-between items-center">
            <h3 class="text-lg font-semibold" id="edit-tag-modal-title">编辑标签</h3>
            <button type="button" onclick="closeEditTagModal()" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-3">
            <input type="hidden" id="edit-tag-type" value="">
            <input type="hidden" id="edit-tag-original-name" value="">
            <input type="hidden" id="edit-tag-position" value="0">
            
            <div class="mb-3">
                <label class="nagisa-label mb-1" for="edit-tag-name">
                    标签名称 <span class="text-red-500">*</span>
                </label>
                <input id="edit-tag-name" type="text" class="nagisa-input" placeholder="请输入新的标签名称">
                <p class="text-xs text-gray-500 mt-1">标签名称将用于分类和筛选</p>
            </div>
            
            <div class="mb-3">
                <label class="nagisa-label mb-1" for="edit-tag-position-input">
                    显示顺序
                </label>
                <input id="edit-tag-position-input" type="number" class="nagisa-input" value="0">
                <p class="text-xs text-gray-500 mt-1">数字越小排序越靠前</p>
            </div>
            
            <div class="flex justify-end space-x-3 mt-4">
                <button type="button" onclick="closeEditTagModal()" class="nagisa-btn-secondary">
                    <i class="fas fa-times mr-2"></i>取消
                </button>
                <button type="button" onclick="saveTagEdit()" class="nagisa-btn" style="background: linear-gradient(45deg, #cc9471, #f3b4a4);">
                    <i class="fas fa-save mr-2"></i>保存
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 编辑商品系列弹窗 -->
<div id="edit-series-modal" class="fixed inset-0 flex items-center justify-center z-50" style="display: none;">
    <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeEditSeriesModal()"></div>
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 z-10 overflow-hidden border-2 border-[#cc9471]">
        <div class="bg-gradient-to-r from-[#cc9471] to-[#f3b4a4] text-white px-6 py-4 flex justify-between items-center">
            <h3 class="text-lg font-semibold">编辑商品系列</h3>
            <button type="button" onclick="closeEditSeriesModal()" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <input type="hidden" id="edit-series-id" value="">
            <div class="nagisa-form-group">
                <label class="nagisa-label">系列名称 <span class="text-red-500">*</span></label>
                <input type="text" id="edit-series-name" class="nagisa-input">
            </div>
            <div class="nagisa-form-group">
                <label class="nagisa-label">系列说明</label>
                <textarea id="edit-series-description" class="nagisa-textarea" rows="3"></textarea>
            </div>
            <div class="nagisa-form-group">
                <label class="nagisa-label">显示顺序</label>
                <input type="number" id="edit-series-position" class="nagisa-input" value="0">
            </div>
            <div class="nagisa-form-group">
                <label class="flex items-center">
                    <span class="mr-2">前台显示</span>
                    <label class="switch">
                        <input type="checkbox" id="edit-series-active" checked>
                        <span class="slider"></span>
                    </label>
                </label>
            </div>
            <div class="flex justify-end space-x-3 mt-4">
                <button type="button" onclick="closeEditSeriesModal()" class="nagisa-btn-secondary">
                    <i class="fas fa-times mr-2"></i>取消
                </button>
                <button type="button" onclick="saveSeriesEdit()" class="nagisa-btn" style="background: linear-gradient(45deg, #cc9471, #f3b4a4);">
                    <i class="fas fa-save mr-2"></i>保存
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 删除商品系列确认弹窗 -->
<div id="delete-series-modal" class="fixed inset-0 flex items-center justify-center z-50" style="display: none;">
    <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeDeleteSeriesModal()"></div>
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 z-10 overflow-hidden border-2 border-[#cc9471]">
        <div class="bg-gradient-to-r from-[#cc9471] to-[#f3b4a4] px-6 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <h3 class="text-lg font-semibold" style="color: #cc9471;">删除商品系列</h3>
                <div class="text-white text-xl ml-2"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
            <button type="button" onclick="closeDeleteSeriesModal()" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <input type="hidden" id="delete-series-id" value="">
            <div class="mb-6">
                <div class="bg-red-50 px-4 py-3 rounded-lg border-l-4 border-red-300">
                    <p class="text-[#cc9471] font-medium">
                        确定要删除商品系列「<span id="delete-series-display-name" class="font-bold" style="color: #cc9471;"></span>」吗？此操作不可恢复。
                    </p>
                </div>
            </div>
            <div class="flex justify-between">
                <button type="button" onclick="closeDeleteSeriesModal()" class="nagisa-btn-secondary">取消</button>
                <button type="button" onclick="confirmDeleteSeries()" class="nagisa-btn-danger">确认删除</button>
            </div>
        </div>
    </div>
</div>

<!-- 自定义删除确认弹窗 -->
<div id="delete-tag-modal" class="fixed inset-0 flex items-center justify-center z-50" style="display: none;">
    <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeDeleteTagModal()"></div>
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 z-10 overflow-hidden border-2 border-[#cc9471]">
        <div class="bg-gradient-to-r from-[#cc9471] to-[#f3b4a4] px-6 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <h3 class="text-lg font-semibold" id="delete-tag-modal-title" style="color: #cc9471;">删除标签</h3>
                <div class="text-white text-xl ml-2">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <button type="button" onclick="closeDeleteTagModal()" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <input type="hidden" id="delete-tag-type" value="">
            <input type="hidden" id="delete-tag-name" value="">
            
            <div class="mb-6">
                <div class="bg-red-50 px-4 py-3 rounded-lg border-l-4 border-red-300">
                    <p class="text-[#cc9471] font-medium" id="delete-tag-message">
                        确定要删除标签"<span id="delete-tag-display-name" class="font-bold" style="color: #cc9471;"></span>"吗？此操作不可恢复。
                    </p>
                </div>
            </div>
            
            <div class="flex justify-between mt-8">
                <button type="button" onclick="confirmDeleteTag()" class="nagisa-btn-danger">
                    <i class="fas fa-trash mr-2"></i>确认删除
                </button>
                <button type="button" onclick="closeDeleteTagModal()" class="nagisa-btn-secondary">
                    <i class="fas fa-times mr-2"></i>取消
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 自定义删除确认弹窗位于文件底部 -->

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
        
        // 重置子区域的显示状态
        if (sectionId === 'expressions') {
            document.getElementById('tag-manager-section').style.display = 'none';
            document.getElementById('add-expression-form').style.display = 'none';
            
            // 显示表情列表或空表情提示
            const expressionGrid = document.getElementById('expression-grid');
            if (expressionGrid) {
                expressionGrid.style.display = 'grid';
            }
            
            const emptyMessage = document.querySelector('#expressions .flex.flex-col.items-center.justify-center');
            if (emptyMessage) {
                emptyMessage.style.display = 'flex';
            }
        } else if (sectionId === 'audios') {
            document.getElementById('audio-tag-manager-section').style.display = 'none';
            document.getElementById('add-audio-form').style.display = 'none';
            
            // 显示语音列表或空语音提示
            const audioGrid = document.getElementById('audio-grid');
            if (audioGrid) {
                audioGrid.style.display = 'grid';
            }
            
            const emptyMessage = document.querySelector('#audios .flex.flex-col.items-center.justify-center');
            if (emptyMessage) {
                emptyMessage.style.display = 'flex';
            }
        } else if (sectionId === 'products') {
            // 隐藏所有商品相关表单
            const addForm = document.getElementById('add-product-form');
            if (addForm) addForm.style.display = 'none';
            
            // 显示商品列表或空商品提示
            const productGrid = document.getElementById('product-grid');
            if (productGrid) {
                productGrid.style.display = 'block';
                applyProductSeriesCollapseState();
            }
            
            const emptyMessage = document.querySelector('#products .flex.flex-col.items-center.justify-center');
            if (emptyMessage) {
                emptyMessage.style.display = 'flex';
            }
        }
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
    localStorage.setItem('shop_expressions_active_section', sectionId);
    
    // 更新URL参数（不刷新页面）
    const url = new URL(window.location);
    url.searchParams.set('section', sectionId);
    window.history.replaceState({}, '', url);
}

// 获取当前应该显示的模块
function getCurrentSection() {
    // 首先检查URL参数
    const urlParams = new URLSearchParams(window.location.search);
    const urlSection = urlParams.get('section');
    
    // 然后检查本地存储
    const storedSection = localStorage.getItem('shop_expressions_active_section');
    
    // 最后使用默认值
    const defaultSection = 'expressions';
    
    // 验证section是否有效
    const validSections = ['expressions', 'audios', 'products'];
    const section = urlSection || storedSection || defaultSection;
    
    return validSections.includes(section) ? section : defaultSection;
}

// 图片预览
function previewExpressionImage(input) {
    const previewContainer = document.getElementById('expression-preview-container');
    const previewImage = document.getElementById('expression-preview-image');
    
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

// 切换标签按钮样式
function toggleTagButton(checkbox) {
    const label = checkbox.closest('.tag-button');
    if (checkbox.checked) {
        label.classList.add('tag-button-selected');
    } else {
        label.classList.remove('tag-button-selected');
    }
}

// 切换语音标签按钮样式（单选）
function toggleAudioTagButton(radio) {
    // 先移除所有标签的选中样式
    document.querySelectorAll('#audio-category-checkboxes .tag-button').forEach(btn => {
        btn.classList.remove('tag-button-selected');
    });
    
    // 为当前选中的标签添加样式
    if (radio.checked) {
        const label = radio.closest('.tag-button');
        label.classList.add('tag-button-selected');
    }
}

// 音频预览
function previewAudio(input) {
    const previewContainer = document.getElementById('audio-preview-container');
    const previewAudio = document.getElementById('audio-preview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewContainer.style.display = 'block';
            previewAudio.src = e.target.result;
            previewAudio.load(); // 加载新的音频源
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        previewContainer.style.display = 'none';
        previewAudio.src = '';
    }
}

// 商品图片预览
function previewProductImage(input) {
    const previewContainer = document.getElementById('product-preview-container');
    const previewImage = document.getElementById('product-preview-image');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewContainer.classList.remove('hidden');
            previewImage.src = e.target.result;
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        previewContainer.classList.add('hidden');
        previewImage.src = '';
    }
}

// 显示标签管理界面
function showTagManager() {
    document.getElementById('tag-manager-section').style.display = 'block';
    document.getElementById('add-expression-form').style.display = 'none';
    
    // 隐藏表情列表，但保持主容器可见
    const expressionGrid = document.getElementById('expression-grid');
    if (expressionGrid) {
        expressionGrid.style.display = 'none';
    }
    
    // 隐藏空表情提示
    const emptyMessage = document.querySelector('#expressions .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'none';
    }
}

// 隐藏标签管理界面
function hideTagManager() {
    document.getElementById('tag-manager-section').style.display = 'none';
    
    // 显示表情列表或空表情提示
    const expressionGrid = document.getElementById('expression-grid');
    if (expressionGrid) {
        expressionGrid.style.display = 'grid';
    }
    
    const emptyMessage = document.querySelector('#expressions .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'flex';
    }
}

// 显示添加表情表单
function showAddExpressionForm() {
    document.getElementById('add-expression-form').style.display = 'block';
    document.getElementById('tag-manager-section').style.display = 'none';
    
    // 重置表单
    document.querySelector('#add-expression-form form').reset();
    
    // 设置表单标题和按钮为"添加"模式
    document.getElementById('expression-form-title').textContent = '添加新表情';
    document.getElementById('expression-submit-btn').innerHTML = '<i class="fas fa-plus mr-2"></i>添加表情';
    document.getElementById('expression-submit-btn').name = 'add_expression';
    document.getElementById('expression_id').value = '';
    
    // 设置文件输入为必填
    const fileInput = document.getElementById('expression_file');
    if (fileInput) {
        fileInput.setAttribute('required', '');
    }
    
    // 恢复文件输入标签
    const fileLabel = document.getElementById('expression-file-label');
    if (fileLabel) {
        fileLabel.innerHTML = '表情图片 <span class="text-red-500">*</span>';
    }
    
    // 隐藏预览
    document.getElementById('expression-preview-container').style.display = 'none';
    document.getElementById('expression-preview-image').src = '';
    
    // 隐藏表情列表，但保持主容器可见
    const expressionGrid = document.getElementById('expression-grid');
    if (expressionGrid) {
        expressionGrid.style.display = 'none';
    }
    
    // 隐藏空表情提示
    const emptyMessage = document.querySelector('#expressions .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'none';
    }
}

// 隐藏添加表情表单
function hideAddExpressionForm() {
    document.getElementById('add-expression-form').style.display = 'none';
    
    // 显示表情列表或空表情提示
    const expressionGrid = document.getElementById('expression-grid');
    if (expressionGrid) {
        expressionGrid.style.display = 'grid';
    }
    
    const emptyMessage = document.querySelector('#expressions .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'flex';
    }
}

// 添加新标签
function addNewTag() {
    const tagName = document.getElementById('new-tag-name').value.trim();
    const tagDescription = document.getElementById('new-tag-description').value.trim();

    if (!tagName) {
        showToast('标签名称不能为空！', 'error');
        return;
    }

    // 检查标签是否已存在
    const existingTags = document.querySelectorAll('#existing-tags-list .flex.items-center.justify-between');
    for (let i = 0; i < existingTags.length; i++) {
        const existingNameElement = existingTags[i].querySelector('.font-medium');
        if (existingNameElement && existingNameElement.textContent.trim().toLowerCase() === tagName.toLowerCase()) {
            showToast('标签名称已存在！', 'error');
            return;
        }
    }

    // 先通过AJAX将标签添加到后台数据库
    fetch('update_tag.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=create&new_name=${encodeURIComponent(tagName)}&description=${encodeURIComponent(tagDescription)}&type=expression`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 创建与现有标签结构一致的新标签元素
            const newTagElement = document.createElement('div');
            newTagElement.className = 'flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200';
            newTagElement.innerHTML = `
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
            `;
            
            // 检查是否有"暂无标签"的提示，如果有则移除
            const emptyMessage = document.querySelector('#existing-tags-list .text-center.py-8');
            if (emptyMessage) {
                emptyMessage.remove();
            }
            
            // 添加新标签到列表
            document.getElementById('existing-tags-list').appendChild(newTagElement);
            
            // 清空输入框
            document.getElementById('new-tag-name').value = '';
            document.getElementById('new-tag-description').value = '';
            
            // 显示成功消息
            showToast('标签添加成功！');
            
            // 刷新分类选择框中的选项
            refreshCategoryCheckboxes();
        } else {
            showToast(data.message || '标签添加失败！', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('标签添加失败！请检查网络连接。', 'error');
    });
}

// 刷新分类选择框中的选项
function refreshCategoryCheckboxes() {
    // 获取所有标签名称
    const tagElements = document.querySelectorAll('#existing-tags-list .flex.items-center.justify-between');
    const tagNames = Array.from(tagElements).map(el => {
        const nameElement = el.querySelector('.font-medium');
        return nameElement ? nameElement.textContent.trim() : '';
    }).filter(name => name);
    
    // 获取分类选择框
    const categoryCheckboxes = document.getElementById('category-checkboxes');
    if (!categoryCheckboxes) return;
    
    // 清空现有选项
    const tagButtonGroup = categoryCheckboxes.querySelector('.tag-button-group');
    if (tagButtonGroup) {
        // 如果已经有标签按钮组，则更新它
        tagButtonGroup.innerHTML = '';
        
        // 添加所有标签
        tagNames.forEach(tagName => {
            const label = document.createElement('label');
            label.className = 'tag-button';
            label.innerHTML = `
                <input type="checkbox" name="expression_categories[]" value="${tagName}" onchange="toggleTagButton(this)">
                <span>${tagName}</span>
            `;
            tagButtonGroup.appendChild(label);
        });
    } else if (tagNames.length > 0) {
        // 如果没有标签按钮组但有标签，则创建一个新的
        const emptyMessage = categoryCheckboxes.querySelector('.text-center');
        if (emptyMessage) {
            emptyMessage.remove();
        }
        
        const newTagButtonGroup = document.createElement('div');
        newTagButtonGroup.className = 'tag-button-group';
        
        // 添加所有标签
        tagNames.forEach(tagName => {
            const label = document.createElement('label');
            label.className = 'tag-button';
            label.innerHTML = `
                <input type="checkbox" name="expression_categories[]" value="${tagName}" onchange="toggleTagButton(this)">
                <span>${tagName}</span>
            `;
            newTagButtonGroup.appendChild(label);
        });
        
        categoryCheckboxes.appendChild(newTagButtonGroup);
    }
}

// 编辑标签
function editTag(tagName) {
    // 打开编辑弹窗
    openEditTagModal(tagName, 'expression');
}

// 删除标签
function deleteTag(tagName) {
    // 打开删除确认弹窗
    openDeleteTagModal(tagName, 'expression');
}

// 编辑图片
function editExpression(id) {
    const expressionItem = document.querySelector(`.expression-item[data-id="${id}"]`);
    if (!expressionItem) return;

    const title = expressionItem.querySelector('.expression-title').textContent;
    // 优先从data属性获取描述，如果没有则尝试从DOM元素获取
    const description = expressionItem.getAttribute('data-description') || 
                      (expressionItem.querySelector('.expression-description') ? 
                       expressionItem.querySelector('.expression-description').textContent : '');
    const currentCategories = Array.from(expressionItem.querySelectorAll('.expression-category span'))
                             .map(span => span.textContent.trim());
    const status = expressionItem.querySelector('.status-toggle').checked;
    const imagePath = expressionItem.querySelector('img').src;
    
    // 提取文件名（去除随机数）
    let fileName = "";
    if (imagePath) {
        // 从路径中获取完整文件名
        const fullFileName = imagePath.split('/').pop();
        if (fullFileName) {
            // 尝试移除随机数部分（格式通常为：name_randomhash.ext）
            const fileNameParts = fullFileName.split('_');
            if (fileNameParts.length > 1) {
                // 保留文件名部分，移除随机哈希部分
                fileName = fileNameParts[0];
            } else {
                // 如果没有下划线，则使用完整文件名（不含扩展名）
                fileName = fullFileName.split('.')[0];
            }
        }
    }

    // 显示表单
    showAddExpressionForm();
    
    // 设置表单标题和按钮文本
    document.getElementById('expression-form-title').textContent = '编辑表情';
    document.getElementById('expression-submit-btn').innerHTML = '<i class="fas fa-save mr-2"></i>更新表情';
    document.getElementById('expression-submit-btn').name = 'update_expression';
    
    // 填充表单数据
    const titleInput = document.querySelector('input[name="expression_title"]');
    if (titleInput) {
        // 如果标题为空或未定义，尝试使用文件名作为标题
        titleInput.value = title || fileName || '';
    }
    
    const descriptionInput = document.querySelector('textarea[name="expression_description"]');
    if (descriptionInput) {
        // 确保描述字段被正确填充
        descriptionInput.value = description || '';
    }
    
    // 设置文件名提示（如果有的话）
    const fileNameInfo = document.createElement('div');
    fileNameInfo.className = 'mt-2 text-sm text-gray-600';
    fileNameInfo.innerHTML = `<i class="fas fa-info-circle mr-1"></i>原始文件名: <span class="font-medium">${fileName || '未知'}</span>`;
    
    // 添加到文件输入区域
    const fileInputContainer = document.getElementById('expression-file-label').parentNode;
    // 移除之前的文件名提示（如果有）
    const oldFileNameInfo = fileInputContainer.querySelector('.mt-2.text-sm.text-gray-600');
    if (oldFileNameInfo) {
        fileInputContainer.removeChild(oldFileNameInfo);
    }
    fileInputContainer.appendChild(fileNameInfo);
    
    // 设置分类复选框
    document.querySelectorAll('input[name="expression_categories[]"]').forEach(checkbox => {
        const isChecked = currentCategories.includes(checkbox.value);
        checkbox.checked = isChecked;
        
        // 更新按钮样式
        const label = checkbox.closest('.tag-button');
        if (label) {
            if (isChecked) {
                label.classList.add('tag-button-selected');
            } else {
                label.classList.remove('tag-button-selected');
            }
        }
    });
    
    // 设置状态开关
    const statusToggle = document.querySelector('input[name="expression_status"]');
    if (statusToggle) statusToggle.checked = status;
    
    // 设置文件输入为可选
    const fileInput = document.getElementById('expression_file');
    if (fileInput) {
        fileInput.removeAttribute('required');
    }
    
    // 修改文件输入标签
    const fileLabel = document.getElementById('expression-file-label');
    if (fileLabel) {
        fileLabel.innerHTML = '表情图片（可选，不上传则保持原图片）';
    }
    
    // 显示当前图片预览
    const previewContainer = document.getElementById('expression-preview-container');
    const previewImage = document.getElementById('expression-preview-image');
    if (previewContainer && previewImage) {
        previewContainer.style.display = 'block';
        previewImage.src = imagePath;
    }
    
    // 添加隐藏的ID字段
    document.getElementById('expression_id').value = id;
}

// 显示语音标签管理界面
function showAudioTagManager() {
    document.getElementById('audio-tag-manager-section').style.display = 'block';
    document.getElementById('add-audio-form').style.display = 'none';
    
    // 隐藏语音列表，但保持主容器可见
    const audioGrid = document.getElementById('audio-grid');
    if (audioGrid) {
        audioGrid.style.display = 'none';
    }
    
    // 隐藏空语音提示
    const emptyMessage = document.querySelector('#audios .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'none';
    }
}

// 隐藏语音标签管理界面
function hideAudioTagManager() {
    document.getElementById('audio-tag-manager-section').style.display = 'none';
    
    // 显示语音列表或空语音提示
    const audioGrid = document.getElementById('audio-grid');
    if (audioGrid) {
        audioGrid.style.display = 'grid';
    }
    
    const emptyMessage = document.querySelector('#audios .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'flex';
    }
}

// 显示添加语音表单
function showAddAudioForm() {
    document.getElementById('add-audio-form').style.display = 'block';
    document.getElementById('audio-tag-manager-section').style.display = 'none';
    
    // 隐藏语音列表，但保持主容器可见
    const audioGrid = document.getElementById('audio-grid');
    if (audioGrid) {
        audioGrid.style.display = 'none';
    }
    
    // 隐藏空语音提示
    const emptyMessage = document.querySelector('#audios .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'none';
    }
}

// 隐藏添加语音表单
function hideAddAudioForm() {
    document.getElementById('add-audio-form').style.display = 'none';
    
    // 显示语音列表或空语音提示
    const audioGrid = document.getElementById('audio-grid');
    if (audioGrid) {
        audioGrid.style.display = 'grid';
    }
    
    const emptyMessage = document.querySelector('#audios .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'flex';
    }
}

// 添加新语音标签
function addNewAudioTag() {
    const tagName = document.getElementById('new-audio-tag-name').value.trim();
    const tagDescription = document.getElementById('new-audio-tag-description').value.trim();

    if (!tagName) {
        showToast('标签名称不能为空！', 'error');
        return;
    }

    // 检查标签是否已存在
    const existingTags = document.querySelectorAll('#existing-audio-tags-list .flex.items-center.justify-between');
    for (let i = 0; i < existingTags.length; i++) {
        const existingNameElement = existingTags[i].querySelector('.font-medium');
        if (existingNameElement && existingNameElement.textContent.trim().toLowerCase() === tagName.toLowerCase()) {
            showToast('标签名称已存在！', 'error');
            return;
        }
    }

    // 先通过AJAX将标签添加到后台数据库
    fetch('update_tag.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=create&new_name=${encodeURIComponent(tagName)}&description=${encodeURIComponent(tagDescription)}&type=audio`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 创建与现有标签结构一致的新标签元素
            const newTagElement = document.createElement('div');
            newTagElement.className = 'flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200';
            newTagElement.innerHTML = `
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
            `;
            
            // 检查是否有"暂无标签"的提示，如果有则移除
            const emptyMessage = document.querySelector('#existing-audio-tags-list .text-center.py-8');
            if (emptyMessage) {
                emptyMessage.remove();
            }
            
            // 添加新标签到列表
            document.getElementById('existing-audio-tags-list').appendChild(newTagElement);
            
            // 清空输入框
            document.getElementById('new-audio-tag-name').value = '';
            document.getElementById('new-audio-tag-description').value = '';
            
            // 显示成功消息
            showToast('标签添加成功！');
            
            // 刷新分类选择框中的选项
            refreshAudioCategoryRadios();
        } else {
            showToast(data.message || '标签添加失败！', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('标签添加失败！请检查网络连接。', 'error');
    });
}

// 刷新语音分类单选框中的选项
function refreshAudioCategoryRadios() {
    // 获取所有标签名称
    const tagElements = document.querySelectorAll('#existing-audio-tags-list .flex.items-center.justify-between');
    const tagNames = Array.from(tagElements).map(el => {
        const nameElement = el.querySelector('.font-medium');
        return nameElement ? nameElement.textContent.trim() : '';
    }).filter(name => name);
    
    // 获取分类选择框
    const categoryCheckboxes = document.getElementById('audio-category-checkboxes');
    if (!categoryCheckboxes) return;
    
    // 清空现有选项
    const radioGroup = categoryCheckboxes.querySelector('.grid');
    if (radioGroup) {
        // 如果已经有单选按钮组，则更新它
        radioGroup.innerHTML = '';
        
        // 添加所有标签
        tagNames.forEach(tagName => {
            const label = document.createElement('label');
            label.className = 'category-checkbox-item';
            label.innerHTML = `
                <input type="radio" name="audio_categories[]" value="${tagName}" class="category-checkbox">
                <span class="category-label">${tagName}</span>
            `;
            radioGroup.appendChild(label);
        });
    } else if (tagNames.length > 0) {
        // 如果没有单选按钮组但有标签，则创建一个新的
        const emptyMessage = categoryCheckboxes.querySelector('.text-center');
        if (emptyMessage) {
            emptyMessage.remove();
        }
        
        const newRadioGroup = document.createElement('div');
        newRadioGroup.className = 'grid grid-cols-2 gap-3';
        
        // 添加所有标签
        tagNames.forEach(tagName => {
            const label = document.createElement('label');
            label.className = 'category-checkbox-item';
            label.innerHTML = `
                <input type="radio" name="audio_categories[]" value="${tagName}" class="category-checkbox">
                <span class="category-label">${tagName}</span>
            `;
            newRadioGroup.appendChild(label);
        });
        
        categoryCheckboxes.appendChild(newRadioGroup);
    }
}

// 编辑语音标签
function editAudioTag(tagName, position = 0) {
    // 打开编辑弹窗
    openEditTagModal(tagName, 'audio', position);
}

// 删除语音标签
function deleteAudioTag(tagName) {
    // 打开删除确认弹窗
    openDeleteTagModal(tagName, 'audio');
}

// 编辑语音
function editAudio(id) {
    const audioItem = document.querySelector(`.audio-item[data-id="${id}"]`);
    if (!audioItem) return;

    const title = audioItem.querySelector('.audio-title').textContent;
    // 优先从data属性获取描述，如果没有则尝试从DOM元素获取
    const description = audioItem.getAttribute('data-description') || 
                      (audioItem.querySelector('.audio-description') ? 
                       audioItem.querySelector('.audio-description').textContent : '');
    const currentCategory = audioItem.querySelector('.audio-category span') ? 
                          audioItem.querySelector('.audio-category span').textContent.trim() : '';
    const position = audioItem.getAttribute('data-position') || '0';
    const status = audioItem.querySelector('.status-toggle').checked;
    const audioPath = audioItem.querySelector('audio source').src;
    
    // 提取文件名（去除随机数）
    let fileName = "";
    if (audioPath) {
        // 从路径中获取完整文件名
        const fullFileName = audioPath.split('/').pop();
        if (fullFileName) {
            // 尝试移除随机数部分（格式通常为：name_randomhash.ext）
            const fileNameParts = fullFileName.split('_');
            if (fileNameParts.length > 1) {
                // 保留文件名部分，移除随机哈希部分
                fileName = fileNameParts[0];
            } else {
                // 如果没有下划线，则使用完整文件名（不含扩展名）
                fileName = fullFileName.split('.')[0];
            }
        }
    }

    // 显示表单
    showAddAudioForm();
    
    // 设置表单标题和按钮文本
    document.getElementById('audio-form-title').textContent = '编辑语音';
    document.getElementById('audio-submit-btn').innerHTML = '<i class="fas fa-save mr-2"></i>更新语音';
    document.getElementById('audio-submit-btn').name = 'update_audio';
    
    // 填充表单数据
    const titleInput = document.querySelector('input[name="audio_title"]');
    if (titleInput) {
        // 如果标题为空或未定义，尝试使用文件名作为标题
        titleInput.value = title || fileName || '';
    }
    
    const descriptionInput = document.querySelector('textarea[name="audio_description"]');
    if (descriptionInput) {
        // 确保描述字段被正确填充
        descriptionInput.value = description || '';
    }
    
    // 设置文件名提示（如果有的话）
    const fileNameInfo = document.createElement('div');
    fileNameInfo.className = 'mt-2 text-sm text-gray-600';
    fileNameInfo.innerHTML = `<i class="fas fa-info-circle mr-1"></i>原始文件名: <span class="font-medium">${fileName || '未知'}</span>`;
    
    // 添加到文件输入区域
    const fileInputContainer = document.getElementById('audio-file-label').parentNode;
    // 移除之前的文件名提示（如果有）
    const oldFileNameInfo = fileInputContainer.querySelector('.mt-2.text-sm.text-gray-600');
    if (oldFileNameInfo) {
        fileInputContainer.removeChild(oldFileNameInfo);
    }
    fileInputContainer.appendChild(fileNameInfo);
    
    // 设置分类单选按钮
    document.querySelectorAll('input[name="audio_categories[]"]').forEach(radio => {
        const isChecked = radio.value === currentCategory;
        radio.checked = isChecked;
        
        // 更新按钮样式
        const label = radio.closest('.category-checkbox-item');
        if (label) {
            if (isChecked) {
                label.classList.add('category-checkbox-item-selected');
            } else {
                label.classList.remove('category-checkbox-item-selected');
            }
        }
    });
    
    // 设置排序值
    const positionInput = document.querySelector('input[name="audio_position"]');
    if (positionInput) positionInput.value = position;
    
    // 设置状态开关
    const statusToggle = document.querySelector('input[name="audio_status"]');
    if (statusToggle) statusToggle.checked = status;
    
    // 设置文件输入为可选
    const fileInput = document.getElementById('audio_file');
    if (fileInput) {
        fileInput.removeAttribute('required');
    }
    
    // 修改文件输入标签
    const fileLabel = document.getElementById('audio-file-label');
    if (fileLabel) {
        fileLabel.innerHTML = '语音文件（可选，不上传则保持原文件）';
    }
    
    // 显示当前音频预览
    const previewContainer = document.getElementById('audio-preview-container');
    const previewAudio = document.getElementById('audio-preview');
    if (previewContainer && previewAudio) {
        previewContainer.style.display = 'block';
        previewAudio.src = audioPath;
    }
    
    // 添加隐藏的ID字段
    document.getElementById('audio_id').value = id;
}

// 预览音频
function previewAudio(input) {
    const previewContainer = document.getElementById('audio-preview-container');
    const previewAudio = document.getElementById('audio-preview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewContainer.style.display = 'block';
            previewAudio.src = e.target.result;
            previewAudio.load(); // 加载新的音频源
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        previewContainer.style.display = 'none';
        previewAudio.src = '';
    }
}

// 显示自定义删除确认弹窗
function showDeleteConfirmModal(formId, type, itemName, itemId) {
    const modal = document.getElementById('custom-delete-confirm-modal');
    const message = document.getElementById('delete-confirm-message');
    const formIdInput = document.getElementById('delete-confirm-form-id');
    const typeInput = document.getElementById('delete-confirm-type');
    const itemIdInput = document.getElementById('delete-confirm-item-id');
    
    // 设置标题和消息
    let title = '删除确认';
    let messageText = '确定要删除这个项目吗？此操作不可恢复。';
    
    if (type === 'expression') {
        title = '删除图片';
        messageText = `确定要删除这个图片"${itemName}"吗？此操作不可恢复。`;
    } else if (type === 'audio') {
        title = '删除语音';
        messageText = `确定要删除这个语音"${itemName}"吗？此操作不可恢复。`;
    } else if (type === 'product') {
        title = '删除商品';
        messageText = `确定要删除这个商品"${itemName}"吗？此操作不可恢复。`;
    }
    
    document.getElementById('delete-confirm-title').textContent = title;
    message.textContent = messageText;
    formIdInput.value = formId;
    typeInput.value = type;
    itemIdInput.value = itemId || '';
    
    modal.style.display = 'flex';
}

// 关闭自定义删除确认弹窗
function closeDeleteConfirmModal() {
    document.getElementById('custom-delete-confirm-modal').style.display = 'none';
}

// 确认删除操作
function confirmDelete() {
    const formId = document.getElementById('delete-confirm-form-id').value;
    const type = document.getElementById('delete-confirm-type').value;
    const itemId = document.getElementById('delete-confirm-item-id').value;
    
    if (formId) {
        // 如果有表单ID，添加提交按钮并提交表单
        const form = document.getElementById(formId);
        if (form) {
            // 添加对应类型的提交按钮
            if (type === 'expression') {
                const submitBtn = document.createElement('input');
                submitBtn.type = 'hidden';
                submitBtn.name = 'delete_expression';
                submitBtn.value = '1';
                form.appendChild(submitBtn);
            } else if (type === 'audio') {
                const submitBtn = document.createElement('input');
                submitBtn.type = 'hidden';
                submitBtn.name = 'delete_audio';
                submitBtn.value = '1';
                form.appendChild(submitBtn);
            } else if (type === 'product') {
                const submitBtn = document.createElement('input');
                submitBtn.type = 'hidden';
                submitBtn.name = 'delete_product';
                submitBtn.value = '1';
                form.appendChild(submitBtn);
            }
            form.submit();
        }
    } else if (type && itemId) {
        // 如果有类型和ID，创建并提交表单
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        
        if (type === 'expression') {
            idInput.name = 'expression_id';
            form.appendChild(idInput);
            
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'delete_expression';
            submitInput.value = '1';
            form.appendChild(submitInput);
        } else if (type === 'audio') {
            idInput.name = 'audio_id';
            form.appendChild(idInput);
            
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'delete_audio';
            submitInput.value = '1';
            form.appendChild(submitInput);
        } else if (type === 'product') {
            idInput.name = 'product_id';
            form.appendChild(idInput);
            
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'delete_product';
            submitInput.value = '1';
            form.appendChild(submitInput);
        }
        
        idInput.value = itemId;
        document.body.appendChild(form);
        form.submit();
    }
    
    closeDeleteConfirmModal();
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 获取当前应该显示的模块
    const currentSection = getCurrentSection();
    
    // 显示对应的模块
    showSection(currentSection);
    
    // 初始化标签管理和表单的显示状态
    document.getElementById('tag-manager-section').style.display = 'none';
    document.getElementById('add-expression-form').style.display = 'none';
    document.getElementById('audio-tag-manager-section').style.display = 'none';
    document.getElementById('add-audio-form').style.display = 'none';
    const seriesManager = document.getElementById('series-manager-section');
    if (seriesManager) seriesManager.style.display = 'none';
    
    // 添加表单提交后的处理
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            // 保存当前模块状态
            const currentSection = getCurrentSection();
            localStorage.setItem('shop_expressions_active_section', currentSection);
        });
    });
    
    // 搜索和过滤功能 - 表情
    const expressionSearch = document.getElementById('expression-search');
    const expressionCategoryFilter = document.getElementById('expression-category-filter');
    
    if (expressionSearch) {
        expressionSearch.addEventListener('input', function() {
            filterItems('expression');
        });
    }
    
    if (expressionCategoryFilter) {
        expressionCategoryFilter.addEventListener('change', function() {
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
    
    // 搜索和过滤功能 - 商品
    const productSearch = document.getElementById('product-search');
    const productStatusFilter = document.getElementById('product-status-filter');
    
    if (productSearch) {
        productSearch.addEventListener('input', function() {
            filterItems('product');
        });
    }
    
    if (productStatusFilter) {
        productStatusFilter.addEventListener('change', function() {
            filterItems('product');
        });
    }

    const productGrid = document.getElementById('product-grid');
    if (productGrid) {
        productGrid.addEventListener('click', function(e) {
            const btn = e.target.closest('.product-edit-btn');
            if (!btn) return;
            e.preventDefault();
            const item = btn.closest('.expression-item');
            if (!item || !item.dataset.id) return;
            editProduct(
                item.dataset.id,
                item.dataset.title || '',
                item.dataset.price || '',
                item.dataset.description || '',
                item.dataset.link || '',
                item.dataset.position || '0',
                item.dataset.status || '1',
                item.dataset.image || '',
                item.dataset.seriesId || item.getAttribute('data-series-id') || '0'
            );
        });
        applyProductSeriesCollapseState();
    }
    
    // 添加toast消息显示功能
    window.showToast = function(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 500);
        }, 3000);
    };
    
    // 为标签编辑弹窗添加键盘事件
    const tagNameInput = document.getElementById('edit-tag-name');
    if (tagNameInput) {
        tagNameInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                saveTagEdit();
            } else if (event.key === 'Escape') {
                closeEditTagModal();
            }
        });
    }
    
    // 测试搜索和筛选功能
    console.log('Testing search and filter functionality...');
    setTimeout(() => {
        const expressionSearch = document.getElementById('expression-search');
        const audioSearch = document.getElementById('audio-search');
        const expressionFilter = document.getElementById('expression-category-filter');
        const audioFilter = document.getElementById('audio-category-filter');
        
        console.log('Expression search element:', expressionSearch);
        console.log('Audio search element:', audioSearch);
        console.log('Expression filter element:', expressionFilter);
        console.log('Audio filter element:', audioFilter);
        
        if (expressionSearch) {
            console.log('Expression search event listener added');
        }
        if (audioSearch) {
            console.log('Audio search event listener added');
        }
        if (expressionFilter) {
            console.log('Expression filter event listener added');
        }
        if (audioFilter) {
            console.log('Audio filter event listener added');
        }
    }, 1000);
});

// 过滤项目的函数
function filterItems(type) {
    let searchInput, filterSelect, grid;
    
    if (type === 'expression') {
        searchInput = document.getElementById('expression-search');
        filterSelect = document.getElementById('expression-category-filter');
        grid = document.getElementById('expression-grid');
    } else if (type === 'audio') {
        searchInput = document.getElementById('audio-search');
        filterSelect = document.getElementById('audio-category-filter');
        grid = document.getElementById('audio-grid');
    } else if (type === 'product') {
        searchInput = document.getElementById('product-search');
        filterSelect = document.getElementById('product-status-filter');
        grid = document.getElementById('product-grid');
    }
    
    if (!grid) {
        console.log(`Grid not found for type: ${type}`);
        return;
    }
    
    const items = grid.querySelectorAll('.expression-item, .audio-item');
    const searchValue = searchInput ? searchInput.value.toLowerCase() : '';
    const filterValue = filterSelect ? filterSelect.value : 'all';
    
    console.log(`Filtering ${type}: search="${searchValue}", filter="${filterValue}", items found: ${items.length}`);
    
    items.forEach(item => {
        const title = item.getAttribute('data-title').toLowerCase();
        let matchesFilter = true;
        
        if (type === 'product') {
            // 商品状态筛选
            const status = item.getAttribute('data-status');
            if (filterValue !== 'all') {
                matchesFilter = status === filterValue;
            }
        } else if (type === 'expression' || type === 'audio') {
            // 表情和语音分类筛选
            const category = item.getAttribute('data-category');
            if (filterValue !== 'all') {
                const itemCategories = category.split(',').map(cat => cat.trim());
                matchesFilter = itemCategories.includes(filterValue);
            }
        }
        
        const matchesSearch = title.includes(searchValue);
        const shouldShow = matchesSearch && matchesFilter;
        
        if (shouldShow) {
            item.style.display = '';
            item.classList.remove('filtered-out');
        } else {
            item.style.display = 'none';
            item.classList.add('filtered-out');
        }
        
        console.log(`Item "${title}": search=${matchesSearch}, filter=${matchesFilter}, show=${shouldShow}`);
    });

    if (type === 'product') {
        grid.querySelectorAll('.product-series-group').forEach(group => {
            const items = group.querySelectorAll('.expression-item');
            let visibleCount = 0;
            items.forEach(item => {
                if (!item.classList.contains('filtered-out') && item.style.display !== 'none') {
                    visibleCount++;
                }
            });
            const countEl = group.querySelector('.product-series-group-count');
            const total = countEl ? parseInt(countEl.getAttribute('data-total'), 10) || items.length : items.length;
            if (countEl) {
                countEl.textContent = visibleCount === total
                    ? `${total} 件`
                    : `${visibleCount} / ${total} 件`;
            }
            if (visibleCount === 0) {
                group.classList.add('is-filter-empty');
            } else {
                group.classList.remove('is-filter-empty');
            }
        });
    }
}

const PRODUCT_SERIES_COLLAPSE_KEY = 'shop_products_series_collapsed';

function getProductSeriesCollapseState() {
    try {
        const raw = localStorage.getItem(PRODUCT_SERIES_COLLAPSE_KEY);
        return raw ? JSON.parse(raw) : {};
    } catch (e) {
        return {};
    }
}

function saveProductSeriesCollapseState() {
    const state = {};
    document.querySelectorAll('#product-grid .product-series-group').forEach(function(group) {
        const key = group.getAttribute('data-series-key');
        if (key) {
            state[key] = group.classList.contains('is-collapsed');
        }
    });
    try {
        localStorage.setItem(PRODUCT_SERIES_COLLAPSE_KEY, JSON.stringify(state));
    } catch (e) { /* ignore */ }
}

function applyProductSeriesCollapseState() {
    const grid = document.getElementById('product-grid');
    if (!grid) return;
    const state = getProductSeriesCollapseState();
    if (!Object.keys(state).length) return;
    grid.querySelectorAll('.product-series-group').forEach(function(group) {
        const key = group.getAttribute('data-series-key');
        if (!key || !Object.prototype.hasOwnProperty.call(state, key)) return;
        const header = group.querySelector('.product-series-group-header');
        const collapsed = !!state[key];
        group.classList.toggle('is-collapsed', collapsed);
        if (header) header.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });
}

function toggleProductSeriesGroup(headerBtn) {
    const group = headerBtn.closest('.product-series-group');
    if (!group) return;
    const collapsed = group.classList.toggle('is-collapsed');
    headerBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    saveProductSeriesCollapseState();
}

function updateProductSeriesGroupActive(seriesId, active) {
    const group = document.querySelector(`#product-grid .product-series-group[data-series-id="${seriesId}"]`);
    if (!group) return;
    group.classList.toggle('is-inactive', !active);
    group.setAttribute('data-series-active', active ? '1' : '0');
    const toggle = group.querySelector('.series-active-toggle');
    if (toggle) toggle.checked = !!active;
}

function syncSeriesListItemActive(seriesId, active) {
    const card = document.querySelector(`#existing-series-list .series-list-item[data-series-id="${seriesId}"]`);
    if (!card) return;
    try {
        const raw = card.getAttribute('data-series');
        const series = JSON.parse(decodeURIComponent(raw));
        series.active = active ? 1 : 0;
        card.outerHTML = buildSeriesListItem(series);
    } catch (e) { /* ignore */ }
}

function toggleSeriesActiveFromGrid(toggleEl) {
    const seriesId = toggleEl.getAttribute('data-series-id');
    const active = toggleEl.checked ? 1 : 0;

    fetch('ajax_shopcar_series.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_active&id=${encodeURIComponent(seriesId)}&active=${active}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateProductSeriesGroupActive(seriesId, active);
            syncSeriesListItemActive(seriesId, active);
            showToast(data.message || (active ? '系列已开启前台显示' : '系列已关闭前台显示'));
        } else {
            showToast(data.message || '更新失败', 'error');
            toggleEl.checked = !toggleEl.checked;
        }
    })
    .catch(() => {
        showToast('更新失败，请检查网络连接', 'error');
        toggleEl.checked = !toggleEl.checked;
    });
}

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('series-active-toggle')) {
        toggleSeriesActiveFromGrid(e.target);
    }
});

// 监听浏览器前进后退按钮
window.addEventListener('popstate', function() {
    const currentSection = getCurrentSection();
    showSection(currentSection);
});

// 显示新建分类输入框
function showNewCategoryInput() {
    document.getElementById('new-category-input').style.display = 'block';
}

// 隐藏新建分类输入框
function hideNewCategoryInput() {
    document.getElementById('new-category-input').style.display = 'none';
    document.getElementById('new-category-name').value = '';
}

// 添加新分类
function addNewCategory() {
    const categoryName = document.getElementById('new-category-name').value.trim();
    if (!categoryName) {
        showToast('请输入分类名称', 'error');
        return;
    }
    
    // 检查分类是否已存在
    const existingCategories = document.querySelectorAll('#category-checkboxes input[name="expression_categories[]"]');
    for (let i = 0; i < existingCategories.length; i++) {
        if (existingCategories[i].value.toLowerCase() === categoryName.toLowerCase()) {
            showToast('该分类已存在', 'error');
            return;
        }
    }
    
    // 添加新分类到界面
    const categoriesContainer = document.querySelector('#category-checkboxes .tag-button-group');
    if (!categoriesContainer) {
        // 如果没有分类列表，创建一个
        const emptyMessage = document.querySelector('#category-checkboxes .text-center');
        if (emptyMessage) {
            emptyMessage.remove();
        }
        const newContainer = document.createElement('div');
        newContainer.className = 'tag-button-group';
        document.getElementById('category-checkboxes').appendChild(newContainer);
        
        const newCategoryItem = document.createElement('label');
        newCategoryItem.className = 'tag-button tag-button-selected';
        newCategoryItem.innerHTML = `
            <input type="checkbox" name="expression_categories[]" value="${categoryName}" checked onchange="toggleTagButton(this)">
            <span>${categoryName}</span>
        `;
        newContainer.appendChild(newCategoryItem);
    } else {
        const newCategoryItem = document.createElement('label');
        newCategoryItem.className = 'tag-button tag-button-selected';
        newCategoryItem.innerHTML = `
            <input type="checkbox" name="expression_categories[]" value="${categoryName}" checked onchange="toggleTagButton(this)">
            <span>${categoryName}</span>
        `;
        categoriesContainer.appendChild(newCategoryItem);
    }
    
    // 清空输入框并隐藏
    hideNewCategoryInput();
    showToast('分类添加成功');
}

// 显示新建语音分类输入框
function showNewAudioCategoryInput() {
    document.getElementById('new-audio-category-input').style.display = 'block';
}

// 隐藏新建语音分类输入框
function hideNewAudioCategoryInput() {
    document.getElementById('new-audio-category-input').style.display = 'none';
    document.getElementById('new-audio-category-name').value = '';
}

// 添加新语音分类
function addNewAudioCategory() {
    const categoryName = document.getElementById('new-audio-category-name').value.trim();
    if (!categoryName) {
        showToast('请输入分类名称', 'error');
        return;
    }
    
    // 检查分类是否已存在
    const existingCategories = document.querySelectorAll('#audio-category-checkboxes input[name="audio_categories[]"]');
    for (let i = 0; i < existingCategories.length; i++) {
        if (existingCategories[i].value.toLowerCase() === categoryName.toLowerCase()) {
            showToast('该分类已存在', 'error');
            return;
        }
    }
    
    // 添加新分类到界面
    const categoriesContainer = document.querySelector('#audio-category-checkboxes .grid');
    if (!categoriesContainer) {
        // 如果没有分类列表，创建一个
        const emptyMessage = document.querySelector('#audio-category-checkboxes .text-center');
        if (emptyMessage) {
            emptyMessage.remove();
        }
        const newContainer = document.createElement('div');
        newContainer.className = 'grid grid-cols-2 gap-3';
        document.getElementById('audio-category-checkboxes').appendChild(newContainer);
        
        const newCategoryItem = document.createElement('label');
        newCategoryItem.className = 'category-checkbox-item';
        newCategoryItem.innerHTML = `
            <input type="radio" name="audio_categories[]" value="${categoryName}" class="category-checkbox" checked>
            <span class="category-label">${categoryName}</span>
        `;
        newContainer.appendChild(newCategoryItem);
    } else {
        // 创建新标签
        const newCategoryItem = document.createElement('label');
        newCategoryItem.className = 'category-checkbox-item';
        newCategoryItem.innerHTML = `
            <input type="radio" name="audio_categories[]" value="${categoryName}" class="category-checkbox">
            <span class="category-label">${categoryName}</span>
        `;
        categoriesContainer.appendChild(newCategoryItem);
    }
    
    // 清空输入框并隐藏
    hideNewAudioCategoryInput();
    showToast('分类添加成功');
}

// 商品管理相关函数
function showAddProductForm() {
    document.getElementById('add-product-form').style.display = 'block';
    
    // 隐藏编辑表单
    document.getElementById('edit-product-form').style.display = 'none';
    
    // 隐藏商品列表
    const productGrid = document.getElementById('product-grid');
    if (productGrid) {
        productGrid.style.display = 'none';
    }
    
    // 隐藏空商品提示
    const emptyMessage = document.querySelector('#products .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'none';
    }
}

function hideAddProductForm() {
    document.getElementById('add-product-form').style.display = 'none';
    
    // 隐藏编辑表单
    const editForm = document.getElementById('edit-product-form');
    if (editForm) editForm.style.display = 'none';
    
    // 显示商品列表或空商品提示
    const productGrid = document.getElementById('product-grid');
    if (productGrid) {
        productGrid.style.display = 'block';
        applyProductSeriesCollapseState();
    }
    
    const emptyMessage = document.querySelector('#products .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'flex';
    }
    
    // 重置表单
    document.getElementById('add-product-form-content').reset();
    
    // 重置表单标题和按钮
    document.getElementById('product-form-title').textContent = '添加新商品';
    document.getElementById('product-submit-btn').innerHTML = '<i class="fas fa-plus mr-2"></i>添加商品';
    document.getElementById('product-submit-btn').name = 'add_product';
    
    // 重置隐藏字段
    document.getElementById('product_id').value = '';
    document.getElementById('product_image_old').value = '';
    
    // 隐藏当前图片预览
    document.getElementById('product-current-image').style.display = 'none';
    
    // 设置文件输入为必填
    const fileInput = document.getElementById('product_image');
    if (fileInput) {
        fileInput.setAttribute('required', 'required');
    }
    
    // 重置文件输入标签
    const fileLabel = document.getElementById('product-image-label');
    if (fileLabel) {
        fileLabel.innerHTML = '商品图片 <span class="text-red-500">*</span>';
    }
    
    // 修改表单字段名称，从编辑模式改回添加模式
    const titleInput = document.getElementById('product_title');
    if (titleInput) titleInput.name = 'product_title';
    
    const priceInput = document.getElementById('product_price');
    if (priceInput) priceInput.name = 'product_price';
    
    const descInput = document.getElementById('product_description');
    if (descInput) descInput.name = 'product_description';
    
    const linkInput = document.getElementById('product_link');
    if (linkInput) linkInput.name = 'product_link';
    
    const positionInput = document.getElementById('product_position');
    if (positionInput) positionInput.name = 'product_position';
    
    const activeInput = document.getElementById('product_active');
    if (activeInput) activeInput.name = 'product_active';
    
    if (fileInput) fileInput.name = 'product_image';
}



// 商品表单验证
function validateProductForm() {
    const title = document.getElementById('product_title').value.trim();
    const price = document.getElementById('product_price').value.trim();
    
    if (!title) {
        showToast('商品名称不能为空', 'error');
        document.getElementById('product_title').focus();
        return false;
    }
    
    if (price && isNaN(parseFloat(price))) {
        showToast('价格必须是有效的数字', 'error');
        document.getElementById('product_price').focus();
        return false;
    }
    
    return true;
}

// 显示添加商品表单
function showAddProductForm() {
    document.getElementById('add-product-form').style.display = 'block';
    const seriesSection = document.getElementById('series-manager-section');
    if (seriesSection) seriesSection.style.display = 'none';
    
    // 隐藏商品列表，但保持主容器可见
    const productGrid = document.getElementById('product-grid');
    if (productGrid) {
        productGrid.style.display = 'none';
    }
    
    // 隐藏空商品提示
    const emptyMessage = document.querySelector('#products .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'none';
    }
}

// 隐藏添加商品表单
function hideAddProductForm() {
    document.getElementById('add-product-form').style.display = 'none';
    
    // 显示商品列表或空商品提示
    const productGrid = document.getElementById('product-grid');
    if (productGrid) {
        productGrid.style.display = 'block';
        applyProductSeriesCollapseState();
    }
    
    const emptyMessage = document.querySelector('#products .flex.flex-col.items-center.justify-center');
    if (emptyMessage) {
        emptyMessage.style.display = 'flex';
    }
    
    // 重置表单
    document.getElementById('add-product-form-content').reset();
    
    // 重置表单标题和按钮
    document.getElementById('product-form-title').textContent = '添加新商品';
    document.getElementById('product-submit-btn').innerHTML = '<i class="fas fa-plus mr-2"></i>添加商品';
    document.getElementById('product-submit-btn').name = 'add_product';
    
    // 重置隐藏字段
    document.getElementById('product_id').value = '';
    document.getElementById('product_image_old').value = '';
    const seriesSelect = document.getElementById('product_series_id');
    if (seriesSelect) seriesSelect.value = '';
    
    // 隐藏当前图片预览
    document.getElementById('product-current-image').style.display = 'none';
    
    // 设置文件输入为必填
    const fileInput = document.getElementById('product_image');
    if (fileInput) {
        fileInput.setAttribute('required', 'required');
    }
    
    // 重置文件输入标签
    const fileLabel = document.getElementById('product-image-label');
    if (fileLabel) {
        fileLabel.innerHTML = '商品图片 <span class="text-red-500">*</span>';
    }
}

// ========== 商品系列管理（仿标签管理） ==========
function showSeriesManager() {
    const section = document.getElementById('series-manager-section');
    if (!section) return;
    section.style.display = 'block';
    document.getElementById('add-product-form').style.display = 'none';
    const productGrid = document.getElementById('product-grid');
    if (productGrid) productGrid.style.display = 'none';
    const emptyMessage = document.querySelector('#products .flex.flex-col.items-center.justify-center');
    if (emptyMessage) emptyMessage.style.display = 'none';
}

function hideSeriesManager() {
    const section = document.getElementById('series-manager-section');
    if (section) section.style.display = 'none';
    const productGrid = document.getElementById('product-grid');
    if (productGrid) {
        productGrid.style.display = 'block';
        applyProductSeriesCollapseState();
    }
    const emptyMessage = document.querySelector('#products .flex.flex-col.items-center.justify-center');
    if (emptyMessage) emptyMessage.style.display = 'flex';
}

function escapeHtmlSeries(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function getSeriesPositionFromItem(item) {
    try {
        const series = JSON.parse(decodeURIComponent(item.getAttribute('data-series') || ''));
        return parseInt(series.position, 10) || 0;
    } catch (e) {
        return 0;
    }
}

function sortSeriesListByPosition() {
    const list = document.getElementById('existing-series-list');
    if (!list) return;
    const items = Array.from(list.querySelectorAll('.series-list-item'));
    if (items.length < 2) return;
    items.sort((a, b) => {
        const posDiff = getSeriesPositionFromItem(a) - getSeriesPositionFromItem(b);
        if (posDiff !== 0) return posDiff;
        return (parseInt(a.getAttribute('data-series-id'), 10) || 0) - (parseInt(b.getAttribute('data-series-id'), 10) || 0);
    });
    items.forEach(item => list.appendChild(item));
}

function buildSeriesListItem(series) {
    const descHtml = series.description
        ? `<p class="text-xs text-gray-500 mt-1 truncate series-item-desc">${escapeHtmlSeries(series.description)}</p>`
        : '';
    const dataSeries = encodeURIComponent(JSON.stringify(series));
    const inactiveClass = series.active ? '' : ' is-inactive';
    return `
        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200 series-list-item${inactiveClass}" data-series-id="${series.id}" data-series="${dataSeries}">
            <div class="flex items-center min-w-0 flex-1">
                <div class="w-3 h-3 rounded-full mr-3 flex-shrink-0" style="background: linear-gradient(45deg, #e9967a, #cc9471);"></div>
                <div class="min-w-0">
                    <p class="font-semibold text-gray-800 text-base series-item-title m-0">${escapeHtmlSeries(series.title)}</p>
                    ${descHtml}
                    <p class="text-xs text-gray-500 mt-1 m-0">
                        顺序: <span class="series-item-position font-medium text-gray-600">${series.position}</span>
                    </p>
                </div>
            </div>
            <div class="flex gap-2 flex-shrink-0 ml-3">
                <button type="button" class="nagisa-btn nagisa-btn-mini series-edit-btn" style="background: linear-gradient(45deg, #f59e0b, #d97706);">
                    <i class="fas fa-edit mr-1"></i>编辑
                </button>
                <button type="button" onclick="openDeleteSeriesModal(${series.id}, this.closest('.series-list-item').querySelector('.series-item-title').textContent.trim())" class="nagisa-btn-danger nagisa-btn-mini">
                    <i class="fas fa-trash mr-1"></i>删除
                </button>
            </div>
        </div>`;
}

function editSeriesFromBtn(btn) {
    try {
        const item = btn.closest('.series-list-item');
        const raw = item.getAttribute('data-series');
        const series = JSON.parse(decodeURIComponent(raw));
        editSeries(series);
    } catch (e) {
        showToast('无法打开编辑', 'error');
    }
}

document.addEventListener('click', function(e) {
    const editBtn = e.target.closest('.series-edit-btn');
    if (editBtn) {
        e.preventDefault();
        editSeriesFromBtn(editBtn);
    }
});

function removeSeriesEmptyPlaceholder() {
    const placeholder = document.querySelector('#existing-series-list .series-empty-placeholder');
    if (placeholder) placeholder.remove();
}

function refreshProductSeriesSelect() {
    const select = document.getElementById('product_series_id');
    const list = document.getElementById('existing-series-list');
    if (!select || !list) return;

    sortSeriesListByPosition();
    const current = select.value;
    const items = Array.from(list.querySelectorAll('.series-list-item')).sort((a, b) => {
        const posDiff = getSeriesPositionFromItem(a) - getSeriesPositionFromItem(b);
        if (posDiff !== 0) return posDiff;
        return (parseInt(a.getAttribute('data-series-id'), 10) || 0) - (parseInt(b.getAttribute('data-series-id'), 10) || 0);
    });
    let html = '<option value="">— 未分类 —</option>';
    items.forEach(item => {
        const id = item.getAttribute('data-series-id');
        const title = item.querySelector('.series-item-title')?.textContent.trim() || '';
        if (id && title) {
            const sel = current === id ? ' selected' : '';
            html += `<option value="${id}"${sel}>${escapeHtmlSeries(title)}</option>`;
        }
    });
    select.innerHTML = html;
}

function addNewSeries() {
    const title = document.getElementById('new-series-name').value.trim();
    const description = document.getElementById('new-series-description').value.trim();
    const position = parseInt(document.getElementById('new-series-position').value, 10) || 0;
    const active = document.getElementById('new-series-active').checked ? 1 : 0;

    if (!title) {
        showToast('系列名称不能为空！', 'error');
        return;
    }

    const existing = document.querySelectorAll('#existing-series-list .series-item-title');
    for (let i = 0; i < existing.length; i++) {
        if (existing[i].textContent.trim().toLowerCase() === title.toLowerCase()) {
            showToast('商品系列名称已存在！', 'error');
            return;
        }
    }

    fetch('ajax_shopcar_series.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=create&title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}&position=${position}&active=${active}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.series) {
            removeSeriesEmptyPlaceholder();
            document.getElementById('existing-series-list').insertAdjacentHTML('beforeend', buildSeriesListItem(data.series));
            sortSeriesListByPosition();
            document.getElementById('new-series-name').value = '';
            document.getElementById('new-series-description').value = '';
            document.getElementById('new-series-position').value = '0';
            document.getElementById('new-series-active').checked = true;
            showToast('商品系列添加成功！');
            refreshProductSeriesSelect();
        } else {
            showToast(data.message || '添加失败', 'error');
        }
    })
    .catch(() => showToast('添加失败，请检查网络连接', 'error'));
}

function editSeries(series) {
    document.getElementById('edit-series-id').value = series.id;
    document.getElementById('edit-series-name').value = series.title || '';
    document.getElementById('edit-series-description').value = series.description || '';
    document.getElementById('edit-series-position').value = series.position ?? 0;
    document.getElementById('edit-series-active').checked = series.active == 1;
    document.getElementById('edit-series-modal').style.display = 'flex';
    setTimeout(() => {
        document.getElementById('edit-series-name').focus();
        document.getElementById('edit-series-name').select();
    }, 100);
}

function closeEditSeriesModal() {
    document.getElementById('edit-series-modal').style.display = 'none';
}

function saveSeriesEdit() {
    const id = document.getElementById('edit-series-id').value;
    const title = document.getElementById('edit-series-name').value.trim();
    const description = document.getElementById('edit-series-description').value.trim();
    const position = parseInt(document.getElementById('edit-series-position').value, 10) || 0;
    const active = document.getElementById('edit-series-active').checked ? 1 : 0;

    if (!title) {
        showToast('系列名称不能为空！', 'error');
        return;
    }

    fetch('ajax_shopcar_series.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update&id=${encodeURIComponent(id)}&title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}&position=${position}&active=${active}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.series) {
            const card = document.querySelector(`#existing-series-list .series-list-item[data-series-id="${id}"]`);
            if (card) {
                card.outerHTML = buildSeriesListItem(data.series);
            }
            sortSeriesListByPosition();
            showToast('商品系列已更新！');
            refreshProductSeriesSelect();
            updateProductSeriesGroupActive(id, active);
            closeEditSeriesModal();
        } else {
            showToast(data.message || '更新失败', 'error');
        }
    })
    .catch(() => showToast('更新失败，请检查网络连接', 'error'));
}

function openDeleteSeriesModal(id, title) {
    document.getElementById('delete-series-id').value = id;
    document.getElementById('delete-series-display-name').textContent = title;
    document.getElementById('delete-series-modal').style.display = 'flex';
}

function closeDeleteSeriesModal() {
    document.getElementById('delete-series-modal').style.display = 'none';
}

function confirmDeleteSeries() {
    const id = document.getElementById('delete-series-id').value;
    const card = document.querySelector(`#existing-series-list .series-list-item[data-series-id="${id}"]`);

    fetch('ajax_shopcar_series.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&id=${encodeURIComponent(id)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (card) card.remove();
            const list = document.getElementById('existing-series-list');
            if (list && !list.querySelector('.series-list-item')) {
                list.innerHTML = `<div class="text-center py-8 text-gray-500 series-empty-placeholder">
                    <i class="fas fa-tags text-4xl mb-3"></i><p>暂无商品系列，请添加</p></div>`;
            }
            showToast('商品系列已删除！');
            refreshProductSeriesSelect();
            closeDeleteSeriesModal();
        } else {
            showToast(data.message || '删除失败', 'error');
        }
    })
    .catch(() => showToast('删除失败，请检查网络连接', 'error'));
}

// 编辑商品
function editProduct(id, title, price, description, link, position, status, image, seriesId) {
    // 验证数据
    if (!title) {
        showToast('商品标题获取失败', 'error');
        return;
    }
    
    // 构建完整的图片路径
    const imageSrc = image ? '../' + image : '';
    
    // 提取文件名（去除随机数）
    let fileName = "";
    if (image) {
        // 从路径中获取完整文件名
        const fullFileName = image.split('/').pop();
        if (fullFileName) {
            // 尝试移除随机数部分（格式通常为：name_randomhash.ext）
            const fileNameParts = fullFileName.split('_');
            if (fileNameParts.length > 1) {
                // 保留文件名部分，移除随机哈希部分
                fileName = fileNameParts[0];
            } else {
                // 如果没有下划线，则使用完整文件名（不含扩展名）
                fileName = fullFileName.split('.')[0];
            }
        }
    }
    
    // 显示添加商品表单（复用为编辑表单）
    showAddProductForm();
    
    // 设置表单标题和按钮文本
    document.getElementById('product-form-title').textContent = '编辑商品';
    document.getElementById('product-submit-btn').innerHTML = '<i class="fas fa-save mr-2"></i>更新商品';
    document.getElementById('product-submit-btn').name = 'update_product';
    
    // 填充表单数据
    document.getElementById('product_id').value = id;
    document.getElementById('product_title').value = title || fileName || '';
    document.getElementById('product_price').value = price || '';
    document.getElementById('product_description').value = description || '';
    document.getElementById('product_link').value = link || '';
    document.getElementById('product_position').value = position || '0';
    document.getElementById('product_active').checked = status === '1';
    const seriesSelect = document.getElementById('product_series_id');
    if (seriesSelect) {
        seriesSelect.value = seriesId && seriesId !== '0' ? String(seriesId) : '';
    }
    document.getElementById('product_image_old').value = image; // 存储原始图片路径，不带../前缀
    
    // 设置文件名提示（如果有的话）
    const fileNameInfo = document.createElement('div');
    fileNameInfo.className = 'mt-2 text-sm text-gray-600';
    fileNameInfo.innerHTML = `<i class="fas fa-info-circle mr-1"></i>原始文件名: <span class="font-medium">${fileName || '未知'}</span>`;
    
    // 添加到文件输入区域
    const fileInputContainer = document.getElementById('product-image-label').parentNode;
    // 移除之前的文件名提示（如果有）
    const oldFileNameInfo = fileInputContainer.querySelector('.mt-2.text-sm.text-gray-600');
    if (oldFileNameInfo) {
        fileInputContainer.removeChild(oldFileNameInfo);
    }
    fileInputContainer.appendChild(fileNameInfo);
    
    // 设置文件输入为可选
    const fileInput = document.getElementById('product_image');
    if (fileInput) {
        fileInput.removeAttribute('required');
    }
    
    // 修改文件输入标签
    const fileLabel = document.getElementById('product-image-label');
    if (fileLabel) {
        fileLabel.innerHTML = '商品图片（可选，不上传则保持原图片）';
    }
    
    // 修改表单字段名称为编辑模式
    const titleInput = document.getElementById('product_title');
    if (titleInput) titleInput.name = 'edit_product_title';
    
    const priceInput = document.getElementById('product_price');
    if (priceInput) priceInput.name = 'edit_product_price';
    
    const descInput = document.getElementById('product_description');
    if (descInput) descInput.name = 'edit_product_description';
    
    const linkInput = document.getElementById('product_link');
    if (linkInput) linkInput.name = 'edit_product_link';
    
    const positionInput = document.getElementById('product_position');
    if (positionInput) positionInput.name = 'edit_product_position';
    
    const activeInput = document.getElementById('product_active');
    if (activeInput) activeInput.name = 'edit_product_active';

    const seriesSelectEdit = document.getElementById('product_series_id');
    if (seriesSelectEdit) seriesSelectEdit.name = 'edit_product_series_id';
    
    if (fileInput) fileInput.name = 'edit_product_image';
    
    // 显示当前图片预览
    if (imageSrc) {
        document.getElementById('product-current-image-src').src = imageSrc;
        document.getElementById('product-current-image').style.display = 'block';
    } else {
        document.getElementById('product-current-image').style.display = 'none';
    }
    
    // 隐藏新图片预览
    document.getElementById('product-preview-container').style.display = 'none';
    
    // 滚动到编辑表单
    document.getElementById('add-product-form').scrollIntoView({ behavior: 'smooth' });
}

// 商品表单验证

// 打开编辑标签弹窗
function openEditTagModal(tagName, type, position = 0) {
    // 设置弹窗标题
    if (type === 'expression') {
        document.getElementById('edit-tag-modal-title').textContent = '编辑图片标签';
    } else if (type === 'audio') {
        document.getElementById('edit-tag-modal-title').textContent = '编辑语音标签';
    }
    
    // 设置隐藏字段
    document.getElementById('edit-tag-type').value = type;
    document.getElementById('edit-tag-original-name').value = tagName;
    document.getElementById('edit-tag-position').value = position;
    
    // 设置输入框的值和占位符
    const tagInput = document.getElementById('edit-tag-name');
    tagInput.value = tagName;
    tagInput.placeholder = `请输入新的标签名称（当前：${tagName}）`;
    
    // 设置排序值
    const positionInput = document.getElementById('edit-tag-position-input');
    if (positionInput) {
        positionInput.value = position;
    }
    
    // 显示弹窗
    document.getElementById('edit-tag-modal').style.display = 'flex';
    
    // 聚焦输入框
    setTimeout(() => {
        tagInput.focus();
        tagInput.select();
    }, 100);
}

// 关闭编辑标签弹窗
function closeEditTagModal() {
    document.getElementById('edit-tag-modal').style.display = 'none';
}

// 保存标签编辑
function saveTagEdit() {
    const type = document.getElementById('edit-tag-type').value;
    const oldTagName = document.getElementById('edit-tag-original-name').value;
    const newTagName = document.getElementById('edit-tag-name').value.trim();
    const position = parseInt(document.getElementById('edit-tag-position-input').value) || 0;
    
    // 验证新标签名称
    if (!newTagName) {
        showToast('标签名称不能为空！', 'error');
        return;
    }
    
    // 如果只有排序变化，也需要更新
    const oldPosition = parseInt(document.getElementById('edit-tag-position').value) || 0;
    const hasChanges = oldTagName !== newTagName || oldPosition !== position;
    
    if (!hasChanges) {
        closeEditTagModal();
        return; // 没有变化，直接关闭
    }
    
    // 根据类型调用不同的处理函数
    if (type === 'expression') {
        updateExpressionTag(oldTagName, newTagName);
    } else if (type === 'audio') {
        updateAudioTag(oldTagName, newTagName);
    }
    
    // 关闭弹窗
    closeEditTagModal();
}

// 更新图片标签
function updateExpressionTag(oldTagName, newTagName) {
    // 查找标签卡片
    const tagCards = document.querySelectorAll('#existing-tags-list .flex.items-center.justify-between');
    let tagCard = null;
    
    // 遍历找到匹配的标签卡片
    for (let i = 0; i < tagCards.length; i++) {
        const nameElement = tagCards[i].querySelector('.font-medium');
        if (nameElement && nameElement.textContent.trim() === oldTagName) {
            tagCard = tagCards[i];
            break;
        }
    }
    
    if (!tagCard) return;
    
    // 检查新名称是否已存在
    const existingTags = document.querySelectorAll('#existing-tags-list .flex.items-center.justify-between');
    for (let i = 0; i < existingTags.length; i++) {
        const existingNameElement = existingTags[i].querySelector('.font-medium');
        if (existingNameElement) {
            const existingTagName = existingNameElement.textContent.trim();
            if (existingTagName.toLowerCase() === newTagName.toLowerCase() && existingTagName !== oldTagName) {
                showToast('标签名称已存在！', 'error');
                return;
            }
        }
    }
    
    // 更新标签显示
    const nameElement = tagCard.querySelector('.font-medium');
    if (nameElement) {
        nameElement.textContent = newTagName;
    }
    
    // 更新后台数据（通过AJAX）
    fetch('update_tag.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update&old_name=${encodeURIComponent(oldTagName)}&new_name=${encodeURIComponent(newTagName)}&type=expression`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('标签已更新！');
            
            // 更新编辑按钮的onclick事件
            const editButton = tagCard.querySelector('button[onclick^="editTag"]');
            if (editButton) {
                editButton.setAttribute('onclick', `editTag('${newTagName}')`);
            }
            
            // 更新删除按钮的onclick事件
            const deleteButton = tagCard.querySelector('button[onclick^="deleteTag"]');
            if (deleteButton) {
                deleteButton.setAttribute('onclick', `deleteTag('${newTagName}')`);
            }
        } else {
            showToast(data.message || '标签更新失败！', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('标签更新失败！请检查网络连接。', 'error');
    });
}

// 更新语音标签
function updateAudioTag(oldTagName, newTagName) {
    // 获取排序值
    const position = parseInt(document.getElementById('edit-tag-position-input').value) || 0;
    // 查找标签卡片
    const tagCards = document.querySelectorAll('#existing-audio-tags-list .flex.items-center.justify-between');
    let tagCard = null;
    
    // 遍历找到匹配的标签卡片
    for (let i = 0; i < tagCards.length; i++) {
        const nameElement = tagCards[i].querySelector('.font-medium');
        if (nameElement && nameElement.textContent.trim() === oldTagName) {
            tagCard = tagCards[i];
            break;
        }
    }
    
    if (!tagCard) return;
    
    // 检查新名称是否已存在
    const existingTags = document.querySelectorAll('#existing-audio-tags-list .flex.items-center.justify-between');
    for (let i = 0; i < existingTags.length; i++) {
        const existingNameElement = existingTags[i].querySelector('.font-medium');
        if (existingNameElement) {
            const existingTagName = existingNameElement.textContent.trim();
            if (existingTagName.toLowerCase() === newTagName.toLowerCase() && existingTagName !== oldTagName) {
                showToast('标签名称已存在！', 'error');
                return;
            }
        }
    }
    
    // 更新标签显示
    const nameElement = tagCard.querySelector('.font-medium');
    if (nameElement) {
        nameElement.textContent = newTagName;
    }
    
    // 更新后台数据（通过AJAX）
    fetch('update_tag.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update&old_name=${encodeURIComponent(oldTagName)}&new_name=${encodeURIComponent(newTagName)}&type=audio&position=${position}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('标签已更新！');
            
            // 更新编辑按钮的onclick事件
            const editButton = tagCard.querySelector('button[onclick^="editAudioTag"]');
            if (editButton) {
                editButton.setAttribute('onclick', `editAudioTag('${newTagName}', ${position})`);
            }
            
            // 更新排序显示
            const positionSpan = tagCard.querySelector('span.inline-block.bg-gray-100');
            if (positionSpan) {
                positionSpan.textContent = `顺序: ${position}`;
            }
            
            // 更新删除按钮的onclick事件
            const deleteButton = tagCard.querySelector('button[onclick^="deleteAudioTag"]');
            if (deleteButton) {
                deleteButton.setAttribute('onclick', `deleteAudioTag('${newTagName}')`);
            }
        } else {
            showToast(data.message || '标签更新失败！', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('标签更新失败！请检查网络连接。', 'error');
    });
}

// 打开删除标签确认弹窗
function openDeleteTagModal(tagName, type) {
    // 设置消息和标题
    let title = '删除标签';
    
    if (type === 'expression') {
        title = '删除图片标签';
    } else if (type === 'audio') {
        title = '删除语音标签';
    }
    
    // 统一设置标题颜色为 #cc9471
    document.getElementById('delete-tag-modal-title').style.color = '#cc9471';
    // 设置标签名称颜色
    document.getElementById('delete-tag-display-name').style.color = '#cc9471';
    
    // 设置标题
    document.getElementById('delete-tag-modal-title').textContent = title;
    
    // 设置标签名称
    document.getElementById('delete-tag-display-name').textContent = tagName;
    
    // 设置隐藏字段
    document.getElementById('delete-tag-type').value = type;
    document.getElementById('delete-tag-name').value = tagName;
    
    // 设置确认按钮样式
    const confirmButton = document.querySelector('#delete-tag-modal button[onclick="confirmDeleteTag()"]');
    if (type === 'audio') {
        // 为语音标签删除按钮添加红色样式
        confirmButton.style.background = 'linear-gradient(45deg, #ef4444, #f87171)';
    } else {
        // 重置按钮样式
        confirmButton.style.background = '';
    }
    
    // 显示弹窗
    document.getElementById('delete-tag-modal').style.display = 'flex';
}

// 关闭删除标签确认弹窗
function closeDeleteTagModal() {
    document.getElementById('delete-tag-modal').style.display = 'none';
}

// 确认删除标签
function confirmDeleteTag() {
    const tagName = document.getElementById('delete-tag-name').value;
    const type = document.getElementById('delete-tag-type').value;
    
    if (!tagName || !type) {
        showToast('标签信息不完整', 'error');
        return;
    }
    
    // 查找包含该标签名称的卡片
    const tagListSelector = type === 'expression' ? '#existing-tags-list' : '#existing-audio-tags-list';
    const tagCards = document.querySelectorAll(`${tagListSelector} .flex.items-center.justify-between`);
    let tagCard = null;
    
    // 遍历找到匹配的标签卡片
    for (let i = 0; i < tagCards.length; i++) {
        const nameElement = tagCards[i].querySelector('.font-medium');
        if (nameElement && nameElement.textContent.trim() === tagName) {
            tagCard = tagCards[i];
            break;
        }
    }
    
    if (!tagCard) {
        showToast('未找到要删除的标签', 'error');
        closeDeleteTagModal();
        return;
    }
    
    // 更新后台数据（通过AJAX）
    fetch('update_tag.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete&old_name=${encodeURIComponent(tagName)}&type=${type}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 从DOM中移除标签卡片
            tagCard.parentNode.removeChild(tagCard);
            showToast('标签已删除！');
            
            // 刷新分类选择框
            if (type === 'expression') {
                refreshCategoryCheckboxes();
            } else if (type === 'audio') {
                refreshAudioCategoryRadios();
            }
        } else {
            showToast(data.message || '标签删除失败！', 'error');
        }
        
        // 关闭弹窗
        closeDeleteTagModal();
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('标签删除失败！请检查网络连接。', 'error');
        closeDeleteTagModal();
    });
}

// 显示自定义删除确认弹窗
function showDeleteConfirmModal(formId, type, itemName) {
    const modal = document.getElementById('custom-delete-confirm-modal');
    const message = document.getElementById('delete-confirm-message');
    const formIdInput = document.getElementById('delete-confirm-form-id');
    const typeInput = document.getElementById('delete-confirm-type');
    
    // 设置标题和消息
    let title = '删除确认';
    let messageText = '确定要删除这个项目吗？此操作不可恢复。';
    
    if (type === 'expression') {
        title = '删除图片';
        messageText = `确定要删除这个图片"<span style="color: #cc9471; font-weight: bold;">${itemName}</span>"吗？此操作不可恢复。`;
    } else if (type === 'audio') {
        title = '删除语音';
        messageText = `确定要删除这个语音"<span style="color: #cc9471; font-weight: bold;">${itemName}</span>"吗？此操作不可恢复。`;
    } else if (type === 'product') {
        title = '删除商品';
        messageText = `确定要删除这个商品"<span style="color: #cc9471; font-weight: bold;">${itemName}</span>"吗？此操作不可恢复。`;
    }
    
    document.getElementById('delete-confirm-title').innerHTML = title;
    // 统一设置标题颜色为 #cc9471
    document.getElementById('delete-confirm-title').style.color = '#cc9471';
    
    message.innerHTML = messageText;
    formIdInput.value = formId;
    typeInput.value = type;
    
    modal.style.display = 'flex';
}

// 关闭自定义删除确认弹窗
function closeDeleteConfirmModal() {
    document.getElementById('custom-delete-confirm-modal').style.display = 'none';
}

// 确认删除操作
function confirmDelete() {
    const formId = document.getElementById('delete-confirm-form-id').value;
    const form = document.getElementById(formId);
    if (form) {
        form.submit();
    }
    closeDeleteConfirmModal();
}
</script>

<!-- 自定义删除确认弹窗 -->
<div id="custom-delete-confirm-modal" class="fixed inset-0 flex items-center justify-center z-50" style="display: none;">
    <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeDeleteConfirmModal()"></div>
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 z-10 overflow-hidden border-2 border-[#cc9471]">
        <div class="bg-gradient-to-r from-[#cc9471] to-[#f3b4a4] px-6 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <h3 class="text-lg font-semibold" id="delete-confirm-title" style="color: white;">删除确认</h3>
                <div class="text-white text-xl ml-2">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <button type="button" onclick="closeDeleteConfirmModal()" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <input type="hidden" id="delete-confirm-form-id" value="">
            <input type="hidden" id="delete-confirm-type" value="">
            <input type="hidden" id="delete-confirm-item-id" value="">
            
            <div class="mb-6">
                <div class="bg-red-50 px-4 py-3 rounded-lg border-l-4 border-red-300">
                    <p class="text-[#cc9471] font-medium" id="delete-confirm-message">
                        确定要删除这个项目吗？此操作不可恢复。
                    </p>
                </div>
            </div>
            
            <div class="flex justify-between mt-8">
                <button type="button" onclick="confirmDelete()" class="nagisa-btn-danger">
                    <i class="fas fa-trash mr-2"></i>确认删除
                </button>
                <button type="button" onclick="closeDeleteConfirmModal()" class="nagisa-btn-secondary">
                    <i class="fas fa-times mr-2"></i>取消
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.status-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const id = this.dataset.id;
        const type = this.dataset.type;
        const status = this.checked ? 1 : 0;
        const formData = new URLSearchParams();

        if (type === 'expression') {
            formData.append('toggle_expression_status', '1');
            formData.append('expression_id', id);
            formData.append('expression_status', status);
        } else if (type === 'audio') {
            formData.append('toggle_audio_status', '1');
            formData.append('audio_id', id);
            formData.append('audio_status', status);
        } else if (type === 'product') {
            formData.append('toggle_product_status', '1');
            formData.append('product_id', id);
            formData.append('product_status', status);
        } else {
            return;
        }

        fetch('/admin/includes/toggle_status.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: formData.toString()
        })
        .then(res => {
            const ct = res.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                return res.text().then(txt => { throw new Error('服务器返回非 JSON 响应: ' + (txt.substr(0,200) || '')); });
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                try {
                    if (typeof showRightUpToast === 'function') showRightUpToast('已更改', 'success');
                } catch (e) {
                    // ignore
                }
            } else {
                alert('更新失败: ' + (data.message || '未知错误'));
                toggle.checked = !toggle.checked;
            }
        })
        .catch(err => {
            alert('请求失败: ' + err.message);
            toggle.checked = !toggle.checked;
        });
    });
});
</script>

<?php // 处理状态切换 - 图片
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_expression_status'])) {
    try {
        $id = intval($_POST['expression_id']);
        $status = intval($_POST['expression_status']);
        
        $stmt = $conn->prepare("UPDATE expression_images SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 处理状态切换 - 语音
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_audio_status'])) {
    try {
        $id = intval($_POST['audio_id']);
        $status = intval($_POST['audio_status']);
        
        $stmt = $conn->prepare("UPDATE expression_audios SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 处理状态切换 - 商品
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_product_status']) && $shopcarTableExists) {
    try {
        $id = intval($_POST['product_id']);
        $status = intval($_POST['product_status']);
        
        $stmt = $conn->prepare("UPDATE shopcar_products SET active = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if ($shopProductFlash) {
    showToast($shopProductFlash['message'], $shopProductFlash['type'] ?? 'success');
}

// 引入管理后台页脚
require_once 'admin_footer.php';