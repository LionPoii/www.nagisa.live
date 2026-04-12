<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/toast.php';

// 检查管理员登录状态
checkAdminAuth();

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

// 检查公告表是否存在，如果不存在则创建
$stmt = $conn->prepare("SHOW TABLES LIKE 'announcements'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    // 创建公告表
    $conn->exec("CREATE TABLE announcements (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        subtitle VARCHAR(200) DEFAULT NULL,
        content TEXT NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        priority INT(11) DEFAULT 0,
        is_permanent TINYINT(1) DEFAULT 0,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // 创建空表，不添加示例数据
    $conn->beginTransaction();
    
    try {
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        showToast('初始化公告数据表失败：' . $e->getMessage(), 'error');
    }
} else {
    // 检查是否存在subtitle字段，如果不存在则添加
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM announcements LIKE 'subtitle'");
        if ($checkColumn->rowCount() == 0) {
            // 添加subtitle字段
            $conn->exec("ALTER TABLE announcements ADD COLUMN subtitle VARCHAR(200) DEFAULT NULL AFTER title");
            showToast('公告表结构已更新，添加了副标题字段', 'success');
        }
    } catch (Exception $e) {
        error_log("检查或添加subtitle字段失败: " . $e->getMessage());
    }
}

// 处理公告操作
if (isset($_POST['action'])) {
    try {
        // 添加新公告
        if ($_POST['action'] === 'add_announcement' && isset($_POST['title'], $_POST['content'], $_POST['status'], $_POST['priority'], $_POST['start_date'], $_POST['end_date'])) {
            $title = trim($_POST['title']);
            $subtitle = isset($_POST['subtitle']) ? trim($_POST['subtitle']) : null;
            $content = trim($_POST['content']);
            $status = trim($_POST['status']);
            $priority = (int)$_POST['priority'];
            $is_permanent = isset($_POST['is_permanent']) ? 1 : 0;
            $start_date = trim($_POST['start_date']);
            $end_date = trim($_POST['end_date']);
            
            if (empty($title) || empty($content) || empty($start_date) || empty($end_date)) {
                throw new Exception('标题、内容、开始日期和结束日期都是必填的！');
            }
            
            if (!$is_permanent && strtotime($end_date) < strtotime($start_date)) {
                throw new Exception('结束日期不能早于开始日期！');
            }
            
            $stmt = $conn->prepare("INSERT INTO announcements (title, subtitle, content, status, priority, is_permanent, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $subtitle, $content, $status, $priority, $is_permanent, $start_date, $end_date]);
            
            showToast('新公告添加成功！');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // 编辑公告
        if ($_POST['action'] === 'edit_announcement' && isset($_POST['announcement_id'], $_POST['title'], $_POST['content'], $_POST['status'], $_POST['priority'], $_POST['start_date'], $_POST['end_date'])) {
            $announcement_id = (int)$_POST['announcement_id'];
            $title = trim($_POST['title']);
            $subtitle = isset($_POST['subtitle']) ? trim($_POST['subtitle']) : null;
            $content = trim($_POST['content']);
            $status = trim($_POST['status']);
            $priority = (int)$_POST['priority'];
            $is_permanent = isset($_POST['is_permanent']) ? 1 : 0;
            $start_date = trim($_POST['start_date']);
            $end_date = trim($_POST['end_date']);
            
            if (empty($title) || empty($content) || empty($start_date) || empty($end_date)) {
                throw new Exception('标题、内容、开始日期和结束日期都是必填的！');
            }
            
            if (!$is_permanent && strtotime($end_date) < strtotime($start_date)) {
                throw new Exception('结束日期不能早于开始日期！');
            }
            
            $stmt = $conn->prepare("UPDATE announcements SET title = ?, subtitle = ?, content = ?, status = ?, priority = ?, is_permanent = ?, start_date = ?, end_date = ? WHERE id = ?");
            $stmt->execute([$title, $subtitle, $content, $status, $priority, $is_permanent, $start_date, $end_date, $announcement_id]);
            
            showToast('公告更新成功！');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // 删除公告
        if ($_POST['action'] === 'delete_announcement' && isset($_POST['announcement_id'])) {
            $announcement_id = (int)$_POST['announcement_id'];
            
            $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$announcement_id]);
            
            showToast('公告已删除！');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // 更改公告状态
        if ($_POST['action'] === 'toggle_status' && isset($_POST['announcement_id'], $_POST['status'])) {
            $announcement_id = (int)$_POST['announcement_id'];
            $status = $_POST['status'] === 'active' ? 'inactive' : 'active';
            
            $stmt = $conn->prepare("UPDATE announcements SET status = ? WHERE id = ?");
            $stmt->execute([$status, $announcement_id]);
            
            showToast('公告状态已更新！');
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 获取所有公告
$announcements = [];
$stmt = $conn->prepare("SELECT * FROM announcements ORDER BY priority DESC, start_date DESC");
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 设置页面标题
$page_title = '公告管理';
include 'admin_header.php';
?>

<div class="announcement-container">
    <div class="admin-content">
        <!-- 顶部按钮和标题 -->
        <div class="admin-content-header">
            <h2>公告列表</h2>
            <button type="button" class="admin-button admin-button-primary" onclick="showModal('add-announcement-modal')">
                <i class="fas fa-plus"></i> 添加新公告
            </button>
        </div>

        <!-- 公告列表 -->
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                                            <tr>
                            <th>状态</th>
                            <th>标题</th>
                            <th>优先级</th>
                            <th>永久</th>
                            <th>开始日期</th>
                            <th>结束日期</th>
                            <th>操作</th>
                        </tr>
                </thead>
                <tbody>
                    <?php if (empty($announcements)): ?>
                        <tr>
                            <td colspan="7" class="text-center">暂无公告</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <tr data-id="<?php echo $announcement['id']; ?>" data-subtitle="<?php echo htmlspecialchars($announcement['subtitle']); ?>">
                                <td>
                                    <span class="status-badge <?php echo $announcement['status']; ?>">
                                        <?php echo $announcement['status'] === 'active' ? '激活' : '未激活'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                <td><?php echo $announcement['priority']; ?></td>
                                <td>
                                    <?php if ($announcement['is_permanent'] == 1): ?>
                                        <span class="status-badge active">是</span>
                                    <?php else: ?>
                                        <span class="status-badge inactive">否</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($announcement['start_date'])); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($announcement['end_date'])); ?></td>
                                <td>
                                    <div class="admin-table-actions">
                                        <button type="button" class="admin-button admin-button-small admin-button-view" 
                                                onclick="viewAnnouncement('<?php echo htmlspecialchars($announcement['title']); ?>', '<?php echo htmlspecialchars($announcement['content'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-eye"></i> 查看
                                        </button>
                                        <button type="button" class="admin-button admin-button-small admin-button-secondary" 
                                                onclick="editAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo addslashes($announcement['title']); ?>', '<?php echo addslashes($announcement['content']); ?>', '<?php echo $announcement['status']; ?>', <?php echo $announcement['priority']; ?>, <?php echo $announcement['is_permanent'] ?? 0; ?>, '<?php echo date('Y-m-d', strtotime($announcement['start_date'])); ?>', '<?php echo date('Y-m-d', strtotime($announcement['end_date'])); ?>', '<?php echo addslashes($announcement['subtitle'] ?? ''); ?>')">
                                            <i class="fas fa-edit"></i> 编辑
                                        </button>
                                        <button type="button" class="admin-button admin-button-small admin-button-toggle <?php echo $announcement['status'] === 'active' ? 'admin-button-deactivate' : 'admin-button-activate'; ?>" 
                                                onclick="toggleStatus(<?php echo $announcement['id']; ?>, '<?php echo $announcement['status']; ?>')">
                                            <i class="fas <?php echo $announcement['status'] === 'active' ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i> 
                                            <?php echo $announcement['status'] === 'active' ? '停用' : '启用'; ?>
                                        </button>
                                        <button type="button" class="admin-button admin-button-small admin-button-danger" 
                                                onclick="confirmDeleteAnnouncement(<?php echo $announcement['id']; ?>, '<?php echo addslashes($announcement['title']); ?>')">
                                            <i class="fas fa-trash"></i> 删除
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 添加公告模态窗口 -->
<div id="add-announcement-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>添加新公告</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('add-announcement-modal')">×</button>
        </div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" style="width: 100%; box-sizing: border-box;">
            <input type="hidden" name="action" value="add_announcement">
            <div class="admin-form-group">
                <label for="title">公告标题</label>
                <input type="text" id="title" name="title" placeholder="输入公告标题" required maxlength="100">
            </div>
            <div class="admin-form-group">
                <label for="subtitle">公告副标题</label>
                <input type="text" id="subtitle" name="subtitle" placeholder="输入公告副标题（可选）" maxlength="200">
                <small class="form-text">副标题将显示在标题下方，可以用于简短说明</small>
            </div>
            <div class="admin-form-group">
                <label for="content">公告内容</label>
                <textarea id="content" name="content" placeholder="输入公告详细内容" required rows="6"></textarea>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-group half">
                    <label for="status">状态</label>
                    <select id="status" name="status" required>
                        <option value="active">激活</option>
                        <option value="inactive">未激活</option>
                    </select>
                </div>
                <div class="admin-form-group half">
                    <label for="priority">优先级</label>
                    <input type="number" id="priority" name="priority" value="0" min="0" max="10" required>
                    <small class="form-text">数字越大优先级越高，默认为0</small>
                </div>
            </div>
            <div class="admin-form-row date-controls-container">
                <div class="admin-form-group half">
                    <label for="start_date">开始日期</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="admin-form-group half end-date-group">
                    <label for="end_date">结束日期</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                </div>
            </div>
            <div class="permanent-notice-container" style="display: flex; align-items: center; margin-bottom: 20px; background: #f9f9f9; padding: 10px; border-radius: 4px;">
                <label class="checkbox-label" style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                    <input type="checkbox" id="is_permanent" name="is_permanent" value="1" style="margin-right: 8px;" onchange="toggleEndDateVisibility()">
                    <span style="font-weight: 600; color: #4d4030;">设为永久公告</span>
                </label>
                <small class="form-text" style="margin-left: 10px; flex: 1; color: #666;">永久公告不会自动过期，始终保持显示</small>
            </div>
            <div class="admin-form-actions">
                <button type="button" class="admin-button admin-button-secondary" onclick="hideModal('add-announcement-modal')">取消</button>
                <button type="submit" class="admin-button admin-button-primary">添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑公告模态窗口 -->
<div id="edit-announcement-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>编辑公告</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('edit-announcement-modal')">×</button>
        </div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" style="width: 100%; box-sizing: border-box;">
            <input type="hidden" name="action" value="edit_announcement">
            <input type="hidden" id="edit_announcement_id" name="announcement_id" value="">
            <div class="admin-form-group">
                <label for="edit_title">公告标题</label>
                <input type="text" id="edit_title" name="title" required maxlength="100">
            </div>
            <div class="admin-form-group">
                <label for="edit_subtitle">公告副标题</label>
                <input type="text" id="edit_subtitle" name="subtitle" placeholder="输入公告副标题（可选）" maxlength="200">
                <small class="form-text">副标题将显示在标题下方，可以用于简短说明</small>
            </div>
            <div class="admin-form-group">
                <label for="edit_content">公告内容</label>
                <textarea id="edit_content" name="content" required rows="6"></textarea>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-group half">
                    <label for="edit_status">状态</label>
                    <select id="edit_status" name="status" required>
                        <option value="active">激活</option>
                        <option value="inactive">未激活</option>
                    </select>
                </div>
                <div class="admin-form-group half">
                    <label for="edit_priority">优先级</label>
                    <input type="number" id="edit_priority" name="priority" min="0" max="10" required>
                    <small class="form-text">数字越大优先级越高，默认为0</small>
                </div>
            </div>
            <div class="admin-form-row edit-date-controls-container">
                <div class="admin-form-group half">
                    <label for="edit_start_date">开始日期</label>
                    <input type="date" id="edit_start_date" name="start_date" required>
                </div>
                <div class="admin-form-group half edit-end-date-group">
                    <label for="edit_end_date">结束日期</label>
                    <input type="date" id="edit_end_date" name="end_date" required>
                </div>
            </div>
            <div class="permanent-notice-container" style="display: flex; align-items: center; margin-bottom: 20px; background: #f9f9f9; padding: 10px; border-radius: 4px;">
                <label class="checkbox-label" style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                    <input type="checkbox" id="edit_is_permanent" name="is_permanent" value="1" style="margin-right: 8px;" onchange="toggleEditEndDateVisibility()">
                    <span style="font-weight: 600; color: #4d4030;">设为永久公告</span>
                </label>
                <small class="form-text" style="margin-left: 10px; flex: 1; color: #666;">永久公告不会自动过期，始终保持显示</small>
            </div>
            <div class="admin-form-actions">
                <button type="button" class="admin-button admin-button-secondary" onclick="hideModal('edit-announcement-modal')">取消</button>
                <button type="submit" class="admin-button admin-button-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 查看公告模态窗口 -->
<div id="view-announcement-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 id="view_announcement_title">公告详情</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('view-announcement-modal')">×</button>
        </div>
        <div class="admin-modal-body">
            <div class="admin-form-group">
                <div id="view_announcement_content" class="announcement-content-view"></div>
            </div>
            <div class="admin-form-actions">
                <button type="button" class="admin-button admin-button-primary" onclick="hideModal('view-announcement-modal')">关闭</button>
            </div>
        </div>
    </div>
</div>

<!-- 删除公告确认模态窗口 -->
<div id="delete-announcement-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>确认删除</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('delete-announcement-modal')">×</button>
        </div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
            <input type="hidden" name="action" value="delete_announcement">
            <input type="hidden" id="delete_announcement_id" name="announcement_id" value="">
            <p style="text-align: left; margin: 20px 0; padding: 0 20px;">确定要删除公告 <strong id="delete_announcement_title"></strong> 吗？此操作无法恢复。</p>
            <div class="admin-form-actions">
                <button type="button" class="admin-button admin-button-secondary" onclick="hideModal('delete-announcement-modal')">取消</button>
                <button type="submit" class="admin-button admin-button-danger">确认删除</button>
            </div>
        </form>
    </div>
</div>

<!-- 切换状态表单 -->
<form id="toggle-status-form" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" style="display:none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" id="toggle_announcement_id" name="announcement_id" value="">
    <input type="hidden" id="toggle_status" name="status" value="">
</form>

<style>
    /* 整体容器样式 */
    .announcement-container {
        padding: 0 20px;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    /* 状态标签样式 */
    .status-badge {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: white;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .status-badge.active {
        background-color: #2da44e;
        background-image: linear-gradient(to bottom, #3ebd60, #2da44e);
    }
    
    .status-badge.inactive {
        background-color: #f85149;
        background-image: linear-gradient(to bottom, #ff6b64, #f85149);
    }

    /* 表格样式优化 */
    .admin-table th {
        background-color: #cc9471;
        color: white;
        padding: 12px 16px;
        font-weight: 500;
        text-align: left;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
    }
    
    .admin-table td {
        padding: 12px 16px;
        border-bottom: 1px solid rgba(77, 64, 48, 0.1);
    }
    
    .admin-table tr:hover {
        background-color: rgba(77, 64, 48, 0.05);
    }
    
    .admin-table tr:last-child td {
        border-bottom: none;
    }
    
    /* 按钮样式 */
    .admin-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 1px solid transparent;
        text-decoration: none;
    }
    
    .admin-button-primary {
        background-color: #4D4030;
        border-color: #4D4030;
        color: white;
    }
    
    .admin-button-primary:hover {
        background-color: #5e4f3c;
        border-color: #5e4f3c;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(77, 64, 48, 0.3);
    }
    
    .admin-button-secondary {
        background-color: #f8f5f2;
        border-color: #4D4030;
        color: #4D4030;
    }
    
    .admin-button-secondary:hover {
        background-color: rgba(77, 64, 48, 0.1);
        color: #3a3022;
        transform: translateY(-2px);
    }
    
    .admin-button-small {
        padding: 5px 10px;
        font-size: 13px;
        letter-spacing: 0.3px;
    }
    
    .admin-button-danger {
        background-color: #f8f5f2;
        border-color: #f85149;
        color: #f85149;
    }
    
    .admin-button-danger:hover {
        background-color: rgba(248, 81, 73, 0.1);
        color: #e03c35;
        transform: translateY(-2px);
    }
    
    .admin-button-view {
        background-color: #f8f5f2;
        border-color: #8250df;
        color: #8250df;
    }
    
    .admin-button-view:hover {
        background-color: rgba(130, 80, 223, 0.1);
        color: #7240cf;
        transform: translateY(-2px);
    }
    
    .admin-button-toggle {
        background-color: #f8f5f2;
        border-color: #f59f00;
        color: #f59f00;
    }
    
    .admin-button-toggle:hover {
        background-color: rgba(245, 159, 0, 0.1);
        color: #e59400;
        transform: translateY(-2px);
    }
    
    .admin-button-deactivate {
        border-color: #f59f00;
        color: #f59f00;
    }
    
    .admin-button-activate {
        border-color: #2da44e;
        color: #2da44e;
    }
    
    .admin-button i {
        margin-right: 6px;
    }
    
    /* 模态窗口样式 */
    .admin-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .admin-modal.show {
        display: flex;
    }
    
    .admin-modal-content {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        border: none;
        animation: modalFadeIn 0.3s ease;
        margin: 0 auto;
        box-sizing: border-box;
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .admin-modal-header {
        background-color: #cc9471;
        color: white;
        padding: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: none;
    }
    
    .admin-modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 500;
    }
    
    .admin-modal-close {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        line-height: 1;
    }
    
    .admin-modal-body {
        padding: 16px;
    }
    
    .admin-form-group {
        margin-bottom: 20px;
        padding: 0 16px;
        box-sizing: border-box;
        width: 100%;
    }
    
    .admin-form-group label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        color: #4a5568;
        margin-bottom: 8px;
    }
    
    .admin-form-group input, 
    .admin-form-group select, 
    .admin-form-group textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        font-size: 14px;
        transition: border-color 0.3s, box-shadow 0.3s;
    }
    
    .admin-form-group input:focus, 
    .admin-form-group select:focus, 
    .admin-form-group textarea:focus {
        outline: none;
        border-color: #4D4030;
        box-shadow: 0 0 0 3px rgba(77, 64, 48, 0.2);
    }
    
    .admin-form-group textarea {
        min-height: 120px;
        resize: vertical;
    }
    
    .admin-form-actions {
        padding: 16px;
        background: #f8f8f8;
        display: flex;
        justify-content: center;
        gap: 8px;
    }
    
    /* 内容区域样式 */
    .admin-content {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        padding: 24px;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .admin-content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid rgba(77, 64, 48, 0.2);
    }
    
    .admin-content-header h2 {
        margin: 0;
        color: #cc9471;
        font-size: 1.5rem;
        font-weight: 600;
    }
    
    /* 表格容器 */
    .admin-table-container {
        background-color: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 24px;
        max-width: 1100px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .admin-table-actions {
        display: flex;
        gap: 10px;
    }
    
    /* 动画效果 */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .admin-table-container {
        animation: fadeIn 0.5s ease;
    }
    
    /* 工具样式 */
    .text-center {
        text-align: center;
    }
    
    /* 版本列表项背景色交替 */
    .admin-table tbody tr:nth-child(odd) {
        background-color: rgba(77, 64, 48, 0.05);
    }
    
    /* 响应式样式 */
    @media (max-width: 768px) {
        .admin-table-actions {
            flex-direction: column;
            gap: 4px;
        }
        
        .admin-content-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .admin-button {
            width: 100%;
        }
    }
    
    /* 表格操作列样式 */
    .admin-table-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    /* 操作按钮样式 */
    .admin-table-actions .admin-button-small {
        padding: 6px 10px;
        min-width: 80px;
        text-align: center;
        justify-content: center;
        white-space: nowrap;
    }
    
    /* 公告内容查看样式 */
    .announcement-content-view {
        padding: 15px;
        background-color: #f9f9f9;
        border-radius: 5px;
        white-space: pre-wrap;
        line-height: 1.6;
    }
    
    /* 表单布局 */
    .admin-form-row {
        display: flex;
        gap: 20px;
        padding: 0 16px;
    }
    
    .admin-form-group.half {
        width: 50%;
        padding: 0;
    }
    
    .form-text {
        font-size: 12px;
        color: #666;
        margin-top: 4px;
        display: block;
    }
    
    @media (max-width: 576px) {
        .admin-form-row {
            flex-direction: column;
            gap: 10px;
        }
        
        .admin-form-group.half {
            width: 100%;
        }
    }
</style>

<script>
    // 显示模态窗口
    function showModal(modalId) {
        document.getElementById(modalId).classList.add('show');
    }
    
    // 隐藏模态窗口
    function hideModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
    }
    
    // 编辑公告
    function editAnnouncement(id, title, content, status, priority, isPermanent, startDate, endDate, subtitle) {
        document.getElementById('edit_announcement_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_content').value = content;
        document.getElementById('edit_status').value = status;
        document.getElementById('edit_priority').value = priority;
        document.getElementById('edit_is_permanent').checked = isPermanent == 1;
        document.getElementById('edit_start_date').value = startDate;
        document.getElementById('edit_end_date').value = endDate;
        document.getElementById('edit_subtitle').value = subtitle || '';
        
        showModal('edit-announcement-modal');
    }
    
    // 查看公告
    function viewAnnouncement(title, content) {
        document.getElementById('view_announcement_title').textContent = title;
        document.getElementById('view_announcement_content').textContent = content;
        showModal('view-announcement-modal');
    }
    
    // 确认删除公告
    function confirmDeleteAnnouncement(id, title) {
        document.getElementById('delete_announcement_id').value = id;
        document.getElementById('delete_announcement_title').textContent = title;
        showModal('delete-announcement-modal');
    }
    
    // 切换公告状态
    function toggleStatus(id, status) {
        document.getElementById('toggle_announcement_id').value = id;
        document.getElementById('toggle_status').value = status;
        document.getElementById('toggle-status-form').submit();
    }
    
    // 处理永久公告复选框切换时显示/隐藏结束日期（添加表单）
    function toggleEndDateVisibility() {
        const isPermanent = document.getElementById('is_permanent').checked;
        const endDateGroup = document.querySelector('.end-date-group');
        const endDateInput = document.getElementById('end_date');
        
        if (isPermanent) {
            endDateGroup.style.opacity = '0.5';
            endDateGroup.style.pointerEvents = 'none';
            // 设置一个远期日期作为默认值，但用户看不到这个输入框
            const farFutureDate = new Date();
            farFutureDate.setFullYear(farFutureDate.getFullYear() + 10);
            endDateInput.value = farFutureDate.toISOString().split('T')[0];
        } else {
            endDateGroup.style.opacity = '1';
            endDateGroup.style.pointerEvents = 'auto';
            // 恢复默认值（一周后）
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);
            endDateInput.value = nextWeek.toISOString().split('T')[0];
        }
    }
    
    // 处理永久公告复选框切换时显示/隐藏结束日期（编辑表单）
    function toggleEditEndDateVisibility() {
        const isPermanent = document.getElementById('edit_is_permanent').checked;
        const endDateGroup = document.querySelector('.edit-end-date-group');
        const endDateInput = document.getElementById('edit_end_date');
        
        if (isPermanent) {
            endDateGroup.style.opacity = '0.5';
            endDateGroup.style.pointerEvents = 'none';
            // 设置一个远期日期作为默认值，但用户看不到这个输入框
            const farFutureDate = new Date();
            farFutureDate.setFullYear(farFutureDate.getFullYear() + 10);
            endDateInput.value = farFutureDate.toISOString().split('T')[0];
        } else {
            endDateGroup.style.opacity = '1';
            endDateGroup.style.pointerEvents = 'auto';
        }
    }
    
    // 页面加载完成后，检查永久公告复选框状态
    document.addEventListener('DOMContentLoaded', function() {
        // 初始化添加表单
        document.getElementById('is_permanent').addEventListener('change', toggleEndDateVisibility);
        
        // 在编辑模态窗口打开时，初始化编辑表单
        const editModal = document.getElementById('edit-announcement-modal');
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class' && editModal.classList.contains('show')) {
                    toggleEditEndDateVisibility();
                }
            });
        });
        
        observer.observe(editModal, { attributes: true });
    });
</script>

<?php include 'admin_footer.php'; ?> 