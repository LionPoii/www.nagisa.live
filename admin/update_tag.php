<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';

// 检查管理员登录状态
checkAdminAuth();

// 设置响应头
header('Content-Type: application/json');

// 初始化响应数据
$response = [
    'success' => false,
    'message' => '未知错误'
];

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = '无效的请求方法';
    echo json_encode($response);
    exit;
}

// 获取请求参数
$action = isset($_POST['action']) ? $_POST['action'] : '';
$oldName = isset($_POST['old_name']) ? $_POST['old_name'] : '';
$newName = isset($_POST['new_name']) ? $_POST['new_name'] : '';
$type = isset($_POST['type']) ? $_POST['type'] : '';
$description = isset($_POST['description']) ? $_POST['description'] : '';
$sort_order = isset($_POST['position']) ? intval($_POST['position']) : 0;

// 验证参数
if (empty($action) || empty($type)) {
    $response['message'] = '操作类型和标签类型不能为空';
    echo json_encode($response);
    exit;
}

// 根据操作类型验证必要参数
if ($action === 'update' && (empty($oldName) || empty($newName))) {
    $response['message'] = '更新操作需要提供原标签名称和新标签名称';
    echo json_encode($response);
    exit;
}

if ($action === 'delete' && empty($oldName)) {
    $response['message'] = '删除操作需要提供标签名称';
    echo json_encode($response);
    exit;
}

if ($action === 'create' && empty($newName)) {
    $response['message'] = '创建操作需要提供标签名称';
    echo json_encode($response);
    exit;
}

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

try {
    // 根据操作类型执行不同的操作
    switch ($action) {
        case 'create':
            // 根据类型选择不同的处理逻辑
            if ($type === 'expression') {
                // 检查标签是否已存在
                $stmt = $conn->prepare("SELECT id, category FROM expression_images WHERE category LIKE ? OR category LIKE ? OR category LIKE ? OR category = ?");
                $stmt->execute([
                    $newName . ',%',       // 以newName开头
                    '%,' . $newName . ',%', // 中间包含newName
                    '%,' . $newName,       // 以newName结尾
                    $newName               // 完全匹配newName
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $response['message'] = '标签已存在';
                    break;
                }
                
                // 创建标签（将其添加到至少一个图片上）
                // 这里我们找到一个图片并添加此标签，如果没有图片，则标签仍然会在前端显示
                $stmt = $conn->prepare("SELECT id, category FROM expression_images LIMIT 1");
                $stmt->execute();
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($item) {
                    $categories = explode(',', $item['category']);
                    $categories[] = $newName;
                    $updatedCategoryString = implode(',', $categories);
                    
                    $updateStmt = $conn->prepare("UPDATE expression_images SET category = ? WHERE id = ?");
                    $updateStmt->execute([$updatedCategoryString, $item['id']]);
                }
                
                $response['success'] = true;
                $response['message'] = '图片标签创建成功';
            } elseif ($type === 'audio') {
                // 检查标签是否已存在
                $stmt = $conn->prepare("SELECT COUNT(*) FROM expression_audios WHERE category = ?");
                $stmt->execute([$newName]);
                
                if ($stmt->fetchColumn() > 0) {
                    $response['message'] = '标签已存在';
                    break;
                }
                
                // 创建标签（将其添加到至少一个语音上）
                // 这里我们找到一个语音并设置此标签，如果没有语音，则标签仍然会在前端显示
                $stmt = $conn->prepare("SELECT id FROM expression_audios LIMIT 1");
                $stmt->execute();
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($item) {
                    $updateStmt = $conn->prepare("UPDATE expression_audios SET category = ? WHERE id = ?");
                    $updateStmt->execute([$newName, $item['id']]);
                }
                
                // 在audio_categories表中创建记录
                try {
                    $tableExistsStmt = $conn->prepare("SHOW TABLES LIKE 'audio_categories'");
                    $tableExistsStmt->execute();
                    
                    if ($tableExistsStmt->rowCount() > 0) {
                        // 检查分类是否已存在
                        $checkStmt = $conn->prepare("SELECT id FROM audio_categories WHERE name = ?");
                        $checkStmt->execute([$newName]);
                        
                        if ($checkStmt->rowCount() == 0) {
                            // 创建新分类
                                                          $insertStmt = $conn->prepare("INSERT INTO audio_categories (name, sort_order) VALUES (?, ?)");
                            $insertStmt->execute([$newName, $sort_order]);
                        }
                    }
                } catch (PDOException $e) {
                    // 忽略错误，继续执行
                }
                
                $response['success'] = true;
                $response['message'] = '语音标签创建成功';
            } else {
                $response['message'] = '无效的标签类型';
            }
            break;
            
        case 'update':
            if (empty($newName)) {
                $response['message'] = '新标签名称不能为空';
                break;
            }
            
            // 根据类型选择不同的表和字段
            if ($type === 'expression') {
                // 更新图片分类
                $stmt = $conn->prepare("SELECT id, category FROM expression_images WHERE category LIKE ? OR category LIKE ? OR category LIKE ? OR category = ?");
                $stmt->execute([
                    $oldName . ',%',       // 以oldName开头
                    '%,' . $oldName . ',%', // 中间包含oldName
                    '%,' . $oldName,       // 以oldName结尾
                    $oldName               // 完全匹配oldName
                ]);
                
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($items as $item) {
                    $categories = explode(',', $item['category']);
                    $updatedCategories = [];
                    
                    foreach ($categories as $category) {
                        $category = trim($category);
                        if ($category === $oldName) {
                            $updatedCategories[] = $newName;
                        } else {
                            $updatedCategories[] = $category;
                        }
                    }
                    
                    $updatedCategoryString = implode(',', $updatedCategories);
                    
                    $updateStmt = $conn->prepare("UPDATE expression_images SET category = ? WHERE id = ?");
                    $updateStmt->execute([$updatedCategoryString, $item['id']]);
                }
                
                $response['success'] = true;
                $response['message'] = '图片标签更新成功';
            } elseif ($type === 'audio') {
                // 更新语音分类
                $stmt = $conn->prepare("UPDATE expression_audios SET category = ? WHERE category = ?");
                $stmt->execute([$newName, $oldName]);

                // 更新或创建音频分类记录
                try {
                    // 检查audio_categories表是否存在
                    $tableExistsStmt = $conn->prepare("SHOW TABLES LIKE 'audio_categories'");
                    $tableExistsStmt->execute();
                    
                    if ($tableExistsStmt->rowCount() > 0) {
                        // 检查分类是否已存在
                        $checkStmt = $conn->prepare("SELECT id FROM audio_categories WHERE name = ?");
                        $checkStmt->execute([$newName]);
                        
                        if ($checkStmt->rowCount() > 0) {
                            // 更新现有分类
                                                          $updateStmt = $conn->prepare("UPDATE audio_categories SET sort_order = ? WHERE name = ?");
                            $updateStmt->execute([$sort_order, $newName]);
                        } else {
                            // 检查旧分类是否存在
                            $checkOldStmt = $conn->prepare("SELECT id FROM audio_categories WHERE name = ?");
                            $checkOldStmt->execute([$oldName]);
                            
                            if ($checkOldStmt->rowCount() > 0) {
                                // 更新旧分类名称和排序
                                                                  $updateOldStmt = $conn->prepare("UPDATE audio_categories SET name = ?, sort_order = ? WHERE name = ?");
                                $updateOldStmt->execute([$newName, $sort_order, $oldName]);
                            } else {
                                // 创建新分类
                                $insertStmt = $conn->prepare("INSERT INTO audio_categories (name, sort_order) VALUES (?, ?)");
                                $insertStmt->execute([$newName, $sort_order]);
                            }
                        }
                    }
                } catch (PDOException $e) {
                    // 忽略错误，继续执行
                }
                
                $response['success'] = true;
                $response['message'] = '语音标签更新成功';
            } else {
                $response['message'] = '无效的标签类型';
            }
            break;
            
        case 'delete':
            // 根据类型选择不同的表和字段
            if ($type === 'expression') {
                // 删除图片分类（将该分类从所有图片中移除）
                $stmt = $conn->prepare("SELECT id, category FROM expression_images WHERE category LIKE ? OR category LIKE ? OR category LIKE ? OR category = ?");
                $stmt->execute([
                    $oldName . ',%',       // 以oldName开头
                    '%,' . $oldName . ',%', // 中间包含oldName
                    '%,' . $oldName,       // 以oldName结尾
                    $oldName               // 完全匹配oldName
                ]);
                
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($items as $item) {
                    $categories = explode(',', $item['category']);
                    $updatedCategories = [];
                    
                    foreach ($categories as $category) {
                        $category = trim($category);
                        if ($category !== $oldName) {
                            $updatedCategories[] = $category;
                        }
                    }
                    
                    // 如果移除标签后没有分类，则设置为默认分类
                    if (empty($updatedCategories)) {
                        $updatedCategories[] = 'emotion';
                    }
                    
                    $updatedCategoryString = implode(',', $updatedCategories);
                    
                    $updateStmt = $conn->prepare("UPDATE expression_images SET category = ? WHERE id = ?");
                    $updateStmt->execute([$updatedCategoryString, $item['id']]);
                }
                
                $response['success'] = true;
                $response['message'] = '图片标签删除成功';
            } elseif ($type === 'audio') {
                // 删除语音分类（将使用该分类的语音设置为默认分类）
                $stmt = $conn->prepare("UPDATE expression_audios SET category = 'waiting' WHERE category = ?");
                $stmt->execute([$oldName]);
                
                // 从audio_categories表中删除分类
                try {
                    $tableExistsStmt = $conn->prepare("SHOW TABLES LIKE 'audio_categories'");
                    $tableExistsStmt->execute();
                    
                    if ($tableExistsStmt->rowCount() > 0) {
                        $deleteStmt = $conn->prepare("DELETE FROM audio_categories WHERE name = ?");
                        $deleteStmt->execute([$oldName]);
                    }
                } catch (PDOException $e) {
                    // 忽略错误，继续执行
                }
                
                $response['success'] = true;
                $response['message'] = '语音标签删除成功';
            } else {
                $response['message'] = '无效的标签类型';
            }
            break;
            
        default:
            $response['message'] = '无效的操作类型';
            break;
    }
} catch (PDOException $e) {
    $response['message'] = '数据库错误: ' . $e->getMessage();
}

echo json_encode($response); 