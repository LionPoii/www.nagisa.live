<?php
/**
 * 购物车商品后台管理
 * 该页面用于管理购物车中显示的商品
 */

// 检查管理员登录状态
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 设置页面标题（用于header.php）
$page_title = "购物车商品管理";

// 引入数据库连接
require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

// 处理添加/编辑/删除操作
$message = '';
$messageType = '';
$showAddSuccess = false; // 标记是否显示商品添加成功消息

// 检查表是否存在
$tableExists = false;
try {
    $checkTable = $conn->query("SHOW TABLES LIKE 'shopcar_products'");
    $tableExists = ($checkTable && $checkTable->rowCount() > 0);
    
    if (!$tableExists) {
        $message = "购物车商品表不存在。请点击<a href='install_shopcar_table.php' class='text-blue-600 underline'>此处</a>安装。";
        $messageType = "error";
    }
} catch (PDOException $e) {
    $message = "数据库错误: " . $e->getMessage();
    $messageType = "error";
}

// 如果表存在，继续处理其他操作
if ($tableExists) {
    // 处理商品显示状态切换
    if (isset($_POST['toggle_active']) && isset($_POST['product_id'])) {
        $productId = $_POST['product_id'];
        $active = $_POST['active_state'] == '1' ? 0 : 1; // 切换状态
        
        $stmt = $conn->prepare("UPDATE shopcar_products SET active = ? WHERE id = ?");
        $stmt->execute([$active, $productId]);
        
        // 设置提示消息
        $_SESSION['toast_message'] = $active ? "商品已设为显示" : "商品已设为隐藏";
        $_SESSION['toast_type'] = "success";
        
        // 如果是AJAX请求则返回JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => true, 'active' => $active]);
            exit;
        }
        
        // 普通表单提交则重定向以防止重复提交
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // 删除商品
    if (isset($_POST['delete']) && isset($_POST['product_id'])) {
        $stmt = $conn->prepare("DELETE FROM shopcar_products WHERE id = ?");
        $stmt->execute([$_POST['product_id']]);
        
        // 设置提示消息（用于显示在页面顶部）
        $_SESSION['toast_message'] = "商品已成功删除";
        $_SESSION['toast_type'] = "success";
    }
    
    // 编辑商品
    if (isset($_POST['edit']) && isset($_POST['product_id'])) {
        $productId = $_POST['product_id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $link = $_POST['link'];
        $active = isset($_POST['active']) ? 1 : 0;
        $position = $_POST['position'];
        
        // 处理图片上传
        $image = $_POST['current_image']; // 默认保留当前图片
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $targetDir = "../assets/uploads/products/";
            
            // 如果目录不存在则创建
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            // 获取文件扩展名
            $fileExt = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
            $newFileName = uniqid() . "." . $fileExt;
            $targetFilePath = $targetDir . $newFileName;
            
            // 上传文件
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $image = "assets/uploads/products/" . $newFileName; // 存储相对路径
            }
        }
        
        // 更新数据库
        $stmt = $conn->prepare("UPDATE shopcar_products SET title = ?, description = ?, price = ?, image = ?, link = ?, active = ?, position = ? WHERE id = ?");
        $stmt->execute([$title, $description, $price, $image, $link, $active, $position, $productId]);
        
        // 设置提示消息
        $_SESSION['toast_message'] = "商品已成功更新";
        $_SESSION['toast_type'] = "success";
    }
    
    // 添加新商品
    if (isset($_POST['add'])) {
        $title = $_POST['title'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $link = $_POST['link'];
        $active = isset($_POST['active']) ? 1 : 0;
        $position = $_POST['position'];
        $image = ''; // 默认空图片路径
        
        // 处理图片上传
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $targetDir = "../assets/uploads/products/";
            
            // 如果目录不存在则创建
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            // 获取文件扩展名
            $fileExt = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
            $newFileName = uniqid() . "." . $fileExt;
            $targetFilePath = $targetDir . $newFileName;
            
            // 上传文件
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $image = "assets/uploads/products/" . $newFileName; // 存储相对路径
            }
        }
        
        // 插入数据库
        $stmt = $conn->prepare("INSERT INTO shopcar_products (title, description, price, image, link, active, position) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $price, $image, $link, $active, $position]);
        
        // 设置添加成功标记和消息
        $showAddSuccess = true;
        $message = "商品 \"" . htmlspecialchars($title) . "\" 已成功添加";
        $messageType = "success";
        
        // 清空表单数据，避免重复提交
        $_POST = [];
    }
}

// 获取所有商品
$products = [];
$nextPosition = 1;

if ($tableExists) {
    try {
        $stmt = $conn->prepare("SELECT * FROM shopcar_products ORDER BY position ASC");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取下一个位置值
        if (count($products) > 0) {
            $maxPosition = max(array_column($products, 'position'));
            $nextPosition = $maxPosition + 1;
        }
    } catch (PDOException $e) {
        $message = "数据库查询错误: " . $e->getMessage();
        $messageType = "error";
    }
}

// 引入页头
include 'admin_header.php';
?>

<style>
    /* 限制内容区域宽度，不使用全屏显示 */
    .content-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 0 15px;
    }
    
    /* 调整网格布局，使产品卡片更紧凑 */
    .admin-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    
    /* 优化上传预览区域大小 */
    .admin-upload-preview {
        height: 180px;
    }
    
    /* 调整卡片内边距使其更紧凑 */
    .admin-card {
        padding: 15px;
    }
    
    /* 减小表单组间距 */
    .admin-form-group {
        margin-bottom: 15px;
    }
    
    /* AJAX提示框淡出效果 */
    .fade-out {
        opacity: 0;
        transition: opacity 0.3s ease-out;
    }
    
    /* 滑动开关样式 */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
        margin-right: 10px;
    }
    
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .toggle-slider {
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
    
    .toggle-slider:before {
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
    
    .toggle-switch input:checked + .toggle-slider {
        background-color: #4CAF50;
    }
    
    .toggle-switch input:focus + .toggle-slider {
        box-shadow: 0 0 1px #4CAF50;
    }
    
    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(26px);
    }
    
    /* 显示状态标签样式 */
    .toggle-status {
        font-size: 14px;
        font-weight: bold;
        padding: 4px 10px;
        border-radius: 12px;
        display: inline-block;
        text-align: center;
        min-width: 80px;
    }
    
    .status-active {
        background-color: #e6f7e6;
        color: #2e7d32;
        border: 1px solid #4CAF50;
    }
    
    .status-inactive {
        background-color: #fbe9e7;
        color: #c62828;
        border: 1px solid #ff5252;
    }
    
    /* 商品添加成功通知样式 */
    .add-success-notice {
        background-color: #e6f7e6;
        border-left: 4px solid #4CAF50;
        color: #2e7d32;
        padding: 15px;
        margin: 20px 0;
        border-radius: 4px;
        display: flex;
        align-items: center;
    }
    
    .add-success-notice i {
        font-size: 24px;
        margin-right: 10px;
    }
    
    /* 小屏幕响应式布局 */
    @media (max-width: 768px) {
        .admin-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-container">
    <?php if (!empty($message) && !isset($_SESSION['toast_message']) && $messageType === "error"): ?>
    <div class="admin-alert admin-alert-<?php echo $messageType; ?>">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <div class="admin-card">
        <div class="admin-card-title">添加新商品</div>
        <form method="post" enctype="multipart/form-data">
            <div class="admin-form-group">
                <label class="admin-label">商品名称</label>
                <input type="text" name="title" class="admin-input" required>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-label">商品描述</label>
                <textarea name="description" class="admin-textarea" rows="3" required></textarea>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-label">商品价格</label>
                <input type="text" name="price" class="admin-input" required>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-label">商品图片</label>
                <input type="file" name="image" class="admin-input">
            </div>
            
            <div class="admin-form-group">
                <label class="admin-label">购买链接</label>
                <input type="url" name="link" class="admin-input" required>
            </div>
            
            <div class="admin-form-group">
                <label class="admin-label">显示顺序</label>
                <input type="number" name="position" class="admin-input" value="<?php echo $nextPosition; ?>" required>
            </div>
            
            <div class="admin-form-group">
                <label>
                    <input type="checkbox" name="active" checked> 显示该商品
                </label>
            </div>
            
            <div class="admin-form-group">
                <button type="submit" name="add" class="admin-button admin-button-primary">
                    <i class="fas fa-plus"></i> 添加商品
                </button>
            </div>
        </form>
    </div>
    
    <?php if ($showAddSuccess): ?>
    <div class="add-success-notice">
        <i class="fas fa-check-circle"></i>
        <div><?php echo $message; ?></div>
    </div>
    <?php endif; ?>
    
    <h2 class="admin-title">现有商品</h2>
    
    <div class="admin-grid">
        <?php foreach ($products as $product): ?>
        <div class="admin-card">
            <?php if (!empty($product['image'])): ?>
            <div class="admin-upload-preview">
                <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
            </div>
            <?php else: ?>
            <div class="admin-upload-preview">
                <div class="preview-image" style="display: flex; align-items: center; justify-content: center;">无图片</div>
            </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <h3 class="font-bold text-lg"><?php echo htmlspecialchars($product['title']); ?></h3>
                <div class="text-red-600 font-bold">¥ <?php echo htmlspecialchars($product['price']); ?></div>
                <div class="text-gray-600 my-2"><?php echo htmlspecialchars($product['description']); ?></div>
                
                <div class="mb-3 flex justify-between items-center">
                    <div>
                        <span class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded">顺序: <?php echo $product['position']; ?></span>
                    </div>
                    
                    <!-- 显示状态切换开关 -->
                    <div class="flex items-center">
                        <form class="toggle-active-form flex items-center">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <input type="hidden" name="active_state" value="<?php echo $product['active']; ?>">
                            <label class="toggle-switch">
                                <input type="checkbox" <?php echo $product['active'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-status <?php echo $product['active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $product['active'] ? '显示中' : '已隐藏'; ?>
                            </span>
                        </form>
                    </div>
                </div>
                
                <div class="flex gap-2">
                    <button class="admin-button admin-button-secondary" onclick="toggleEditForm('<?php echo $product['id']; ?>')">
                        <i class="fas fa-edit"></i> 编辑
                    </button>
                    
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <button type="submit" name="delete" class="admin-button bg-red-500 text-white hover:bg-red-600" onclick="return confirm('确定要删除此商品吗?')">
                            <i class="fas fa-trash"></i> 删除
                        </button>
                    </form>
                </div>
                
                <!-- 编辑表单 -->
                <div id="edit-form-<?php echo $product['id']; ?>" style="display: none; margin-top: 15px; border-top: 1px solid #e2e8f0; padding-top: 15px;">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                        <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($product['image']); ?>">
                        
                        <div class="admin-form-group">
                            <label class="admin-label">商品名称</label>
                            <input type="text" name="title" class="admin-input" value="<?php echo htmlspecialchars($product['title']); ?>" required>
                        </div>
                        
                        <div class="admin-form-group">
                            <label class="admin-label">商品描述</label>
                            <textarea name="description" class="admin-textarea" rows="3" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                        
                        <div class="admin-form-group">
                            <label class="admin-label">商品价格</label>
                            <input type="text" name="price" class="admin-input" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                        </div>
                        
                        <div class="admin-form-group">
                            <label class="admin-label">商品图片</label>
                            <input type="file" name="image" class="admin-input">
                            <small>不上传则保留现有图片</small>
                        </div>
                        
                        <div class="admin-form-group">
                            <label class="admin-label">购买链接</label>
                            <input type="url" name="link" class="admin-input" value="<?php echo htmlspecialchars($product['link']); ?>" required>
                        </div>
                        
                        <div class="admin-form-group">
                            <label class="admin-label">显示顺序</label>
                            <input type="number" name="position" class="admin-input" value="<?php echo $product['position']; ?>" required>
                        </div>
                        
                        <div class="admin-form-group">
                            <label>
                                <input type="checkbox" name="active" <?php echo $product['active'] ? 'checked' : ''; ?>> 显示该商品
                            </label>
                        </div>
                        
                        <div class="admin-form-group">
                            <button type="submit" name="edit" class="admin-button admin-button-primary">
                                <i class="fas fa-save"></i> 保存修改
                            </button>
                            <button type="button" class="admin-button admin-button-secondary" onclick="toggleEditForm('<?php echo $product['id']; ?>')">
                                <i class="fas fa-times"></i> 取消
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($products)): ?>
        <div class="col-span-full text-center py-8 bg-white rounded-lg">
            <p>暂无商品</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleEditForm(productId) {
    const form = document.getElementById(`edit-form-${productId}`);
    if (form.style.display === 'none') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}

// 商品显示状态切换的AJAX处理
document.addEventListener('DOMContentLoaded', function() {
    // 获取所有切换表单
    const toggleForms = document.querySelectorAll('.toggle-active-form');
    
    toggleForms.forEach(form => {
        const toggleSwitch = form.querySelector('input[type="checkbox"]');
        const toggleStatus = form.querySelector('.toggle-status');
        
        // 监听复选框状态变化
        toggleSwitch.addEventListener('change', function() {
            const productId = form.querySelector('input[name="product_id"]').value;
            const activeState = form.querySelector('input[name="active_state"]').value;
            
            // 创建FormData对象
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('active_state', activeState);
            formData.append('toggle_active', '1');
            
            // 显示加载状态
            toggleStatus.textContent = '正在保存...';
            toggleSwitch.disabled = true;
            
            // 发送AJAX请求
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新表单中的active_state值
                    form.querySelector('input[name="active_state"]').value = data.active;
                    
                    // 更新开关状态和文本
                    toggleSwitch.checked = data.active;
                    toggleStatus.textContent = data.active ? '显示中' : '已隐藏';
                    toggleStatus.classList.remove('status-active', 'status-inactive');
                    toggleStatus.classList.add(data.active ? 'status-active' : 'status-inactive');
                    
                    // 显示临时成功提示
                    const message = data.active ? '商品已设为显示' : '商品已设为隐藏';
                    showToast(message, 'success');
                }
                
                // 恢复开关可用状态
                toggleSwitch.disabled = false;
            })
            .catch(error => {
                console.error('切换商品状态失败:', error);
                showToast('操作失败，请重试', 'error');
                
                // 恢复之前的状态
                toggleSwitch.checked = activeState === '1';
                toggleStatus.textContent = activeState === '1' ? '显示中' : '已隐藏';
                toggleStatus.classList.remove('status-active', 'status-inactive');
                toggleStatus.classList.add(activeState === '1' ? 'status-active' : 'status-inactive');
                toggleSwitch.disabled = false;
            });
        });
    });
    
    // 显示临时提示信息
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `admin-alert admin-alert-${type} fixed top-4 right-4 z-50 shadow-lg`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
        `;
        
        document.body.appendChild(toast);
        
        // 2秒后移除提示
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 2000);
    }
});
</script>

<?php
// 引入页脚
include 'admin_footer.php';
?> 