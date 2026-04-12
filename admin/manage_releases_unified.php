<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/toast.php';

// 检查管理员登录状态
checkAdminAuth();

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

// 当前活动标签
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'announcements';

// ==================== 公告管理部分 ====================
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

// ==================== 更新日志部分 ====================
// 检查更新日志表是否存在，如果不存在则创建
$stmt = $conn->prepare("SHOW TABLES LIKE 'changelog_releases'");
$stmt->execute();
if ($stmt->rowCount() == 0) {
    // 创建版本表
    $conn->exec("CREATE TABLE changelog_releases (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        version VARCHAR(20) NOT NULL,
        release_date DATE NOT NULL,
        description TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // 创建提交表
    $conn->exec("CREATE TABLE changelog_commits (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        release_id INT(11) NOT NULL,
        commit_type VARCHAR(20) NOT NULL,
        message VARCHAR(255) NOT NULL,
        commit_sha VARCHAR(10) NOT NULL,
        detail TEXT NOT NULL,
        FOREIGN KEY (release_id) REFERENCES changelog_releases(id) ON DELETE CASCADE
    )");
    
    // 添加一些示例数据
    $conn->beginTransaction();
    
    try {
        // 添加示例版本
        $stmt = $conn->prepare("INSERT INTO changelog_releases (version, release_date, description) VALUES (?, ?, ?)");
        
        $stmt->execute(['v1.0.0', date('Y-m-d'), '🎉 初始版本发布']);
        $release_id = $conn->lastInsertId();
        
        // 添加示例提交
        $stmt = $conn->prepare("INSERT INTO changelog_commits (release_id, commit_type, message, commit_sha, detail) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$release_id, 'feature', '创建网站基本结构', 'a1b2c3d', '完成网站基本框架和功能设计']);
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        showToast('初始化更新日志数据失败：' . $e->getMessage(), 'error');
    }
}

// ==================== 处理表单提交 ====================
if (isset($_POST['action'])) {
    try {
        // ========== 公告操作 ==========
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
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=announcements');
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
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=announcements');
            exit;
        }
        
        // 删除公告
        if ($_POST['action'] === 'delete_announcement' && isset($_POST['announcement_id'])) {
            $announcement_id = (int)$_POST['announcement_id'];
            
            $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$announcement_id]);
            
            showToast('公告已删除！');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=announcements');
            exit;
        }
        
        // 更改公告状态
        if ($_POST['action'] === 'toggle_status' && isset($_POST['announcement_id'], $_POST['status'])) {
            $announcement_id = (int)$_POST['announcement_id'];
            $status = $_POST['status'] === 'active' ? 'inactive' : 'active';
            
            $stmt = $conn->prepare("UPDATE announcements SET status = ? WHERE id = ?");
            $stmt->execute([$status, $announcement_id]);
            
            showToast('公告状态已更新！');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=announcements');
            exit;
        }
        
        // ========== 更新日志操作 ==========
        // 添加新版本
        if ($_POST['action'] === 'add_release' && isset($_POST['version'], $_POST['release_date'], $_POST['description'])) {
            $version = trim($_POST['version']);
            $release_date = trim($_POST['release_date']);
            $description = trim($_POST['description']);
            
            if (empty($version) || empty($release_date) || empty($description)) {
                throw new Exception('所有字段都是必填的！');
            }
            
            $stmt = $conn->prepare("INSERT INTO changelog_releases (version, release_date, description) VALUES (?, ?, ?)");
            $stmt->execute([$version, $release_date, $description]);
            
            showToast('新版本添加成功！');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=changelog');
            exit;
        }
        
        // 编辑版本
        if ($_POST['action'] === 'edit_release' && isset($_POST['release_id'], $_POST['version'], $_POST['release_date'], $_POST['description'])) {
            $release_id = (int)$_POST['release_id'];
            $version = trim($_POST['version']);
            $release_date = trim($_POST['release_date']);
            $description = trim($_POST['description']);
            
            if (empty($version) || empty($release_date) || empty($description)) {
                throw new Exception('所有字段都是必填的！');
            }
            
            $stmt = $conn->prepare("UPDATE changelog_releases SET version = ?, release_date = ?, description = ? WHERE id = ?");
            $stmt->execute([$version, $release_date, $description, $release_id]);
            
            showToast('版本更新成功！');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=changelog');
            exit;
        }
        
        // 删除版本
        if ($_POST['action'] === 'delete_release' && isset($_POST['release_id'])) {
            $release_id = (int)$_POST['release_id'];
            
            $stmt = $conn->prepare("DELETE FROM changelog_releases WHERE id = ?");
            $stmt->execute([$release_id]);
            
            showToast('版本已删除！');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=changelog');
            exit;
        }
        
        // 添加提交
        if ($_POST['action'] === 'add_commit' && isset($_POST['release_id'], $_POST['commit_type'], $_POST['message'], $_POST['commit_sha'], $_POST['detail'])) {
            $release_id = (int)$_POST['release_id'];
            $commit_type = trim($_POST['commit_type']);
            $message = trim($_POST['message']);
            $commit_sha = trim($_POST['commit_sha']);
            $detail = trim($_POST['detail']);
            
            if (empty($commit_type) || empty($message) || empty($commit_sha) || empty($detail)) {
                throw new Exception('所有字段都是必填的！');
            }
            
            $stmt = $conn->prepare("INSERT INTO changelog_commits (release_id, commit_type, message, commit_sha, detail) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$release_id, $commit_type, $message, $commit_sha, $detail]);
            
            showToast('提交记录添加成功！');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=changelog&release_id=' . $release_id);
            exit;
        }
        
        // 编辑提交
        if ($_POST['action'] === 'edit_commit' && isset($_POST['commit_id'], $_POST['release_id'], $_POST['commit_type'], $_POST['message'], $_POST['commit_sha'], $_POST['detail'])) {
            $commit_id = (int)$_POST['commit_id'];
            $release_id = (int)$_POST['release_id'];
            $commit_type = trim($_POST['commit_type']);
            $message = trim($_POST['message']);
            $commit_sha = trim($_POST['commit_sha']);
            $detail = trim($_POST['detail']);
            
            if (empty($commit_type) || empty($message) || empty($commit_sha) || empty($detail)) {
                throw new Exception('所有字段都是必填的！');
            }
            
            $stmt = $conn->prepare("UPDATE changelog_commits SET commit_type = ?, message = ?, commit_sha = ?, detail = ? WHERE id = ?");
            $stmt->execute([$commit_type, $message, $commit_sha, $detail, $commit_id]);
            
            showToast('提交记录更新成功！');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=changelog&release_id=' . $release_id);
            exit;
        }
        
        // 删除提交
        if ($_POST['action'] === 'delete_commit' && isset($_POST['commit_id'], $_POST['release_id'])) {
            $commit_id = (int)$_POST['commit_id'];
            $release_id = (int)$_POST['release_id'];
            
            $stmt = $conn->prepare("DELETE FROM changelog_commits WHERE id = ?");
            $stmt->execute([$commit_id]);
            
            showToast('提交记录已删除！');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=changelog&release_id=' . $release_id);
            exit;
        }
        
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// ==================== 获取数据 ====================
// 获取所有公告
$announcements = [];
$stmt = $conn->prepare("SELECT * FROM announcements ORDER BY priority DESC, start_date DESC");
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取所有版本
$releases = [];
$stmt = $conn->prepare("SELECT * FROM changelog_releases ORDER BY release_date DESC");
$stmt->execute();
$releases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 如果指定了版本ID，获取该版本的所有提交
$commits = [];
$current_release = null;
if (isset($_GET['release_id']) && !empty($_GET['release_id']) && $active_tab == 'changelog') {
    $release_id = (int)$_GET['release_id'];
    
    // 获取当前版本信息
    $stmt = $conn->prepare("SELECT * FROM changelog_releases WHERE id = ?");
    $stmt->execute([$release_id]);
    $current_release = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_release) {
        // 获取该版本的所有提交
        $stmt = $conn->prepare("SELECT * FROM changelog_commits WHERE release_id = ?");
        $stmt->execute([$release_id]);
        $commits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// 设置页面标题
$page_title = '发布管理';
include 'admin_header.php';
?>

<div class="unified-container">
    <!-- 标签切换栏 -->
    <div class="admin-tabs">
        <a href="?tab=announcements" class="admin-tab <?php echo $active_tab == 'announcements' ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn"></i> 公告列表
        </a>
        <a href="?tab=changelog" class="admin-tab <?php echo $active_tab == 'changelog' ? 'active' : ''; ?>">
            <i class="fas fa-code-branch"></i> 更新日志
        </a>
    </div>

    <div class="admin-content">
        <?php if ($active_tab == 'announcements'): ?>
            <!-- 公告管理内容 -->
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
                                <tr data-id="<?php echo $announcement['id']; ?>" data-subtitle="<?php echo htmlspecialchars($announcement['subtitle'] ?? ''); ?>">
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
                                                    data-id="<?php echo $announcement['id']; ?>"
                                                    data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                                                    data-content="<?php echo htmlspecialchars(str_replace("\r\n", "\n", $announcement['content']), ENT_QUOTES); ?>"
                                                    onclick="viewAnnouncementById(this)">
                                                <i class="fas fa-eye"></i> 查看
                                            </button>
                                            <button type="button" class="admin-button admin-button-small admin-button-secondary" 
                                                    data-id="<?php echo $announcement['id']; ?>"
                                                    data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                                                    data-content="<?php echo htmlspecialchars(str_replace("\r\n", "\n", $announcement['content']), ENT_QUOTES); ?>"
                                                    data-status="<?php echo $announcement['status']; ?>"
                                                    data-priority="<?php echo $announcement['priority']; ?>"
                                                    data-is-permanent="<?php echo $announcement['is_permanent'] ?? 0; ?>"
                                                    data-start-date="<?php echo date('Y-m-d', strtotime($announcement['start_date'])); ?>"
                                                    data-end-date="<?php echo date('Y-m-d', strtotime($announcement['end_date'])); ?>"
                                                    data-subtitle="<?php echo htmlspecialchars($announcement['subtitle'] ?? ''); ?>"
                                                    onclick="editAnnouncementById(this)">
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

        <?php elseif ($active_tab == 'changelog'): ?>
            <!-- 更新日志管理内容 -->
            <div class="admin-content-header">
                <h2><?php echo isset($current_release) ? '管理版本：' . htmlspecialchars($current_release['version']) : '版本列表'; ?></h2>
                <?php if (isset($current_release)): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?tab=changelog" class="admin-button admin-button-manage">
                        <i class="fas fa-arrow-left"></i> 返回版本列表
                    </a>
                <?php else: ?>
                    <button type="button" class="admin-button admin-button-primary" onclick="showModal('add-release-modal')">
                        <i class="fas fa-plus"></i> 添加新版本
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!isset($current_release)): ?>
                <!-- 版本列表 -->
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>版本号</th>
                                <th>发布日期</th>
                                <th>描述</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($releases)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">暂无版本记录</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($releases as $release): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($release['version']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($release['release_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($release['description']); ?></td>
                                        <td>
                                            <div class="admin-table-actions">
                                                <a href="<?php echo $_SERVER['PHP_SELF'] . '?tab=changelog&release_id=' . $release['id']; ?>" class="admin-button admin-button-small admin-button-manage">
                                                    <i class="fas fa-list-ul"></i> 管理提交
                                                </a>
                                                <button type="button" class="admin-button admin-button-small admin-button-secondary" 
                                                        onclick="editRelease(<?php echo $release['id']; ?>, '<?php echo addslashes($release['version']); ?>', '<?php echo date('Y-m-d', strtotime($release['release_date'])); ?>', '<?php echo addslashes($release['description']); ?>')">
                                                    <i class="fas fa-edit"></i> 编辑
                                                </button>
                                                <button type="button" class="admin-button admin-button-small admin-button-danger" 
                                                        onclick="confirmDeleteRelease(<?php echo $release['id']; ?>, '<?php echo addslashes($release['version']); ?>')">
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
            <?php else: ?>
                <!-- 提交列表 -->
                <div class="admin-card mb-4">
                    <h3>版本信息</h3>
                    <p><strong>版本号:</strong> <?php echo htmlspecialchars($current_release['version']); ?></p>
                    <p><strong>发布日期:</strong> <?php echo date('Y-m-d', strtotime($current_release['release_date'])); ?></p>
                    <p><strong>描述:</strong> <?php echo htmlspecialchars($current_release['description']); ?></p>
                    <div class="mt-4">
                        <button type="button" class="admin-button admin-button-secondary" 
                                onclick="editRelease(<?php echo $current_release['id']; ?>, '<?php echo addslashes($current_release['version']); ?>', '<?php echo date('Y-m-d', strtotime($current_release['release_date'])); ?>', '<?php echo addslashes($current_release['description']); ?>')">
                            <i class="fas fa-edit"></i> 编辑版本信息
                        </button>
                    </div>
                </div>

                <div class="admin-content-header">
                    <h3>提交记录</h3>
                    <button type="button" class="admin-button admin-button-add-commit" onclick="showAddCommitModal(<?php echo $current_release['id']; ?>)">
                        <i class="fas fa-plus"></i> 添加提交记录
                    </button>
                </div>

                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>类型</th>
                                <th>消息</th>
                                <th>提交哈希</th>
                                <th>详情</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($commits)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">该版本暂无提交记录</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($commits as $commit): ?>
                                    <tr>
                                        <td>
                                            <span class="commit-type-badge <?php echo htmlspecialchars($commit['commit_type']); ?>">
                                                <?php echo getCommitTypeText($commit['commit_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($commit['message']); ?></td>
                                        <td><code><?php echo htmlspecialchars($commit['commit_sha']); ?></code></td>
                                        <td><?php echo htmlspecialchars($commit['detail']); ?></td>
                                        <td>
                                            <div class="admin-table-actions">
                                                <button type="button" class="admin-button admin-button-small admin-button-secondary" 
                                                        onclick="editCommit(<?php echo $commit['id']; ?>, <?php echo $current_release['id']; ?>, '<?php echo $commit['commit_type']; ?>', '<?php echo addslashes($commit['message']); ?>', '<?php echo addslashes($commit['commit_sha']); ?>', '<?php echo addslashes($commit['detail']); ?>')">
                                                    <i class="fas fa-edit"></i> 编辑
                                                </button>
                                                <button type="button" class="admin-button admin-button-small admin-button-danger" 
                                                        onclick="confirmDeleteCommit(<?php echo $commit['id']; ?>, <?php echo $current_release['id']; ?>, '<?php echo addslashes($commit['message']); ?>')">
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
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
// 辅助函数：根据类型返回对应的中文文本
function getCommitTypeText($type) {
    $typeTexts = [
        'feature' => '新功能',
        'fix' => '修复',
        'improve' => '改进',
        'docs' => '文档',
        'other' => '其他'
    ];
    return isset($typeTexts[$type]) ? $typeTexts[$type] : '其他';
}
?>

<!-- ==================== 公告模态窗口 ==================== -->
<!-- 添加公告模态窗口 -->
<div id="add-announcement-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>添加新公告</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('add-announcement-modal')">×</button>
        </div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?tab=announcements" method="post" style="width: 100%; box-sizing: border-box;">
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
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?tab=announcements" method="post" style="width: 100%; box-sizing: border-box;">
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
                <textarea id="edit_content" name="content" required rows="10" style="min-height: 200px; white-space: pre-wrap;"></textarea>
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
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?tab=announcements" method="post">
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
<form id="toggle-status-form" action="<?php echo $_SERVER['PHP_SELF']; ?>?tab=announcements" method="post" style="display:none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" id="toggle_announcement_id" name="announcement_id" value="">
    <input type="hidden" id="toggle_status" name="status" value="">
</form>

<!-- ==================== 更新日志模态窗口 ==================== -->
<!-- 添加版本模态窗口 -->
<div id="add-release-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>添加新版本</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('add-release-modal')">×</button>
        </div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?tab=changelog" method="post" style="width: 100%; box-sizing: border-box;">
            <input type="hidden" name="action" value="add_release">
            <div class="admin-form-group">
                <label for="version">版本号</label>
                <input type="text" id="version" name="version" placeholder="例如: v1.0.0" required>
            </div>
            <div class="admin-form-group">
                <label for="release_date">发布日期</label>
                <input type="date" id="release_date" name="release_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="admin-form-group">
                <label for="description">描述</label>
                <input type="text" id="description" name="description" placeholder="简要描述这个版本的主要内容" required>
            </div>
            <div class="admin-form-actions">
                <button type="button" class="admin-button admin-button-secondary" onclick="hideModal('add-release-modal')">取消</button>
                <button type="submit" class="admin-button admin-button-primary">添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑版本模态窗口 -->
<div id="edit-release-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>编辑版本</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('edit-release-modal')">×</button>
        </div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?tab=changelog" method="post" style="width: 100%; box-sizing: border-box;">
            <input type="hidden" name="action" value="edit_release">
            <input type="hidden" id="edit_release_id" name="release_id" value="">
            <div class="admin-form-group">
                <label for="edit_version">版本号</label>
                <input type="text" id="edit_version" name="version" required>
            </div>
            <div class="admin-form-group">
                <label for="edit_release_date">发布日期</label>
                <input type="date" id="edit_release_date" name="release_date" required>
            </div>
            <div class="admin-form-group">
                <label for="edit_description">描述</label>
                <input type="text" id="edit_description" name="description" required>
            </div>
            <div class="admin-form-actions">
                <button type="button" class="admin-button admin-button-secondary" onclick="hideModal('edit-release-modal')">取消</button>
                <button type="submit" class="admin-button admin-button-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 删除版本确认模态窗口 -->
<div id="delete-release-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>确认删除</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('delete-release-modal')">×</button>
        </div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?tab=changelog" method="post">
            <input type="hidden" name="action" value="delete_release">
            <input type="hidden" id="delete_release_id" name="release_id" value="">
            <p style="text-align: left; margin: 20px 0; padding: 0 20px;">确定要删除版本 <strong id="delete_release_version"></strong> 吗？此操作将删除该版本下的所有提交记录，且无法恢复。</p>
            <div class="admin-form-actions">
                <button type="button" class="admin-button admin-button-secondary" onclick="hideModal('delete-release-modal')">取消</button>
                <button type="submit" class="admin-button admin-button-danger">确认删除</button>
            </div>
        </form>
    </div>
</div>

<!-- 添加提交模态窗口 -->
<div id="add-commit-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>添加提交记录</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('add-commit-modal')">×</button>
        </div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?tab=changelog" method="post">
            <input type="hidden" name="action" value="add_commit">
            <input type="hidden" id="add_commit_release_id" name="release_id" value="">
            <div class="admin-form-group">
                <label for="commit_type">提交类型</label>
                <select id="commit_type" name="commit_type" required>
                    <option value="feature">新功能</option>
                    <option value="fix">修复</option>
                    <option value="improve">改进</option>
                    <option value="docs">文档</option>
                    <option value="other">其他</option>
                </select>
            </div>
            <div class="admin-form-group">
                <label for="message">提交消息</label>
                <input type="text" id="message" name="message" placeholder="简要描述本次提交内容" required>
            </div>
            <div class="admin-form-group">
                <label for="commit_sha">提交哈希</label>
                <input type="text" id="commit_sha" name="commit_sha" placeholder="例如: a1b2c3d" required>
            </div>
            <div class="admin-form-group">
                <label for="detail">详细信息</label>
                <textarea id="detail" name="detail" placeholder="详细描述本次提交的内容和目的" required></textarea>
            </div>
            <div class="admin-form-actions">
                <button type="button" class="admin-button admin-button-secondary" onclick="hideModal('add-commit-modal')">取消</button>
                <button type="submit" class="admin-button admin-button-primary">添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑提交模态窗口 -->
<div id="edit-commit-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>编辑提交记录</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('edit-commit-modal')">×</button>
        </div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?tab=changelog" method="post">
            <input type="hidden" name="action" value="edit_commit">
            <input type="hidden" id="edit_commit_id" name="commit_id" value="">
            <input type="hidden" id="edit_commit_release_id" name="release_id" value="">
            <div class="admin-form-group">
                <label for="edit_commit_type">提交类型</label>
                <select id="edit_commit_type" name="commit_type" required>
                    <option value="feature">新功能</option>
                    <option value="fix">修复</option>
                    <option value="improve">改进</option>
                    <option value="docs">文档</option>
                    <option value="other">其他</option>
                </select>
            </div>
            <div class="admin-form-group">
                <label for="edit_message">提交消息</label>
                <input type="text" id="edit_message" name="message" required>
            </div>
            <div class="admin-form-group">
                <label for="edit_commit_sha">提交哈希</label>
                <input type="text" id="edit_commit_sha" name="commit_sha" required>
            </div>
            <div class="admin-form-group">
                <label for="edit_detail">详细信息</label>
                <textarea id="edit_detail" name="detail" required></textarea>
            </div>
            <div class="admin-form-actions">
                <button type="button" class="admin-button admin-button-secondary" onclick="hideModal('edit-commit-modal')">取消</button>
                <button type="submit" class="admin-button admin-button-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 删除提交确认模态窗口 -->
<div id="delete-commit-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>确认删除</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('delete-commit-modal')">×</button>
        </div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?tab=changelog" method="post">
            <input type="hidden" name="action" value="delete_commit">
            <input type="hidden" id="delete_commit_id" name="commit_id" value="">
            <input type="hidden" id="delete_commit_release_id" name="release_id" value="">
            <p style="text-align: left; margin: 20px 0; padding: 0 20px;">确定要删除提交记录 <strong id="delete_commit_message"></strong> 吗？此操作无法恢复。</p>
            <div class="admin-form-actions">
                <button type="button" class="admin-button admin-button-secondary" onclick="hideModal('delete-commit-modal')">取消</button>
                <button type="submit" class="admin-button admin-button-danger">确认删除</button>
            </div>
        </form>
    </div>
</div>

<?php include 'admin_footer.php'; ?>

<style>
    /* 整体容器样式 */
    .unified-container {
        padding: 0 20px;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    /* 标签切换栏样式 */
    .admin-tabs {
        display: flex;
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        overflow: hidden;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .admin-tab {
        padding: 16px 24px;
        font-size: 16px;
        font-weight: 500;
        color: #4D4030;
        text-decoration: none;
        transition: all 0.3s ease;
        border-bottom: 3px solid transparent;
        flex: 1;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .admin-tab i {
        margin-right: 8px;
        font-size: 18px;
    }
    
    .admin-tab:hover {
        background-color: rgba(204, 148, 113, 0.1);
        color: #cc9471;
    }
    
    .admin-tab.active {
        background-color: rgba(204, 148, 113, 0.15);
        color: #cc9471;
        border-bottom-color: #cc9471;
        font-weight: 600;
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
    
    /* 提交类型标签样式 */
    .commit-type-badge {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: white;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .commit-type-badge.feature {
        background-color: #2da44e;
        background-image: linear-gradient(to bottom, #3ebd60, #2da44e);
    }
    
    .commit-type-badge.fix {
        background-color: #f85149;
        background-image: linear-gradient(to bottom, #ff6b64, #f85149);
    }
    
    .commit-type-badge.improve {
        background-color: #4D4030; /* 使用网站的次要主题色 */
        background-image: linear-gradient(to bottom, #5e4f3c, #4D4030);
    }
    
    .commit-type-badge.docs {
        background-color: #8250df;
        background-image: linear-gradient(to bottom, #9668e2, #8250df);
    }
    
    .commit-type-badge.other {
        background-color: #cc9471; /* 使用网站的主题色 */
        background-image: linear-gradient(to bottom, #e8a274, #cc9471);
    }
    
    /* 版本卡片样式 */
    .admin-card {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        padding: 24px;
        margin-bottom: 20px;
        border-left: 4px solid #4D4030;
        max-width: 1100px;
        margin-left: auto;
        margin-right: auto;
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
    
    .admin-content-header h3 {
        margin: 0;
        color: #cc9471;
        font-size: 1.25rem;
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
    
    /* 提交哈希样式 */
    code {
        background-color: rgba(77, 64, 48, 0.1);
        color: #4D4030;
        padding: 0.1rem 0.3rem;
        border-radius: 3px;
        font-size: 0.85rem;
        font-family: monospace;
    }
    
    /* 动画效果 */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .admin-table-container {
        animation: fadeIn 0.5s ease;
    }
    
    .admin-card {
        animation: fadeIn 0.5s ease;
    }
    
    /* 工具样式 */
    .mb-4 {
        margin-bottom: 1rem;
    }
    
    .mt-4 {
        margin-top: 1rem;
    }
    
    .text-center {
        text-align: center;
    }
    
    /* 版本列表项背景色交替 */
    .admin-table tbody tr:nth-child(odd) {
        background-color: rgba(77, 64, 48, 0.05);
    }
    
    /* 固定样式 */
    
    /* 管理提交按钮特殊样式 */
    .admin-button-manage {
        background-color: #3498db;
        background-image: linear-gradient(to bottom, #3498db, #2980b9);
        border-color: #2980b9;
        color: white;
        font-weight: 600;
        position: relative;
        overflow: hidden;
        z-index: 1;
        transition: all 0.3s ease;
        text-decoration: none;
        padding: 6px 12px;
        display: inline-flex;
        align-items: center;
    }
    
    .admin-button-manage:hover {
        background-color: #2980b9;
        background-image: linear-gradient(to bottom, #2980b9, #216a9c);
        border-color: #216a9c;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(41, 128, 185, 0.4);
    }
    
    .admin-button-manage:active {
        transform: translateY(0);
        box-shadow: 0 2px 5px rgba(41, 128, 185, 0.3);
    }
    
    .admin-button-manage i {
        font-size: 1.1em;
        margin-right: 8px;
        color: rgba(255, 255, 255, 0.9);
        transition: all 0.3s ease;
    }
    
    .admin-button-manage:hover i {
        color: white;
        transform: scale(1.1);
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
    white-space: pre-wrap !important;
    word-spacing: normal !important;
    letter-spacing: normal !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
    line-height: 1.5;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 8px;
    margin-bottom: 15px;
    max-height: 400px;
    overflow-y: auto;
    font-size: 16px;
    color: #666;
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
    
    /* 固定表单布局 */
        
        .admin-form-group.half {
            width: 100%;
        }
    }
    
    /* 添加提交记录按钮样式 */
    .admin-button-add-commit {
        background-color: #1abc9c;
        background-image: linear-gradient(to bottom, #1abc9c, #16a085);
        border-color: #16a085;
        color: white;
    }
    
    .admin-button-add-commit:hover {
        background-color: #16a085;
        background-image: linear-gradient(to bottom, #16a085, #13866f);
        border-color: #13866f;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(22, 160, 133, 0.3);
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
    
    // ==================== 公告相关函数 ====================
    // 查看公告
    function viewAnnouncement(title, content) {
        document.getElementById('view_announcement_title').textContent = title;
        const contentElement = document.getElementById('view_announcement_content');
        // 直接设置文本内容，让CSS控制显示，与编辑的公告内容样式保持一致
        contentElement.textContent = content;
        showModal('view-announcement-modal');
    }
    
    // 通过数据属性查看公告
    function viewAnnouncementById(button) {
        const title = button.getAttribute('data-title');
        const content = button.getAttribute('data-content');
        viewAnnouncement(title, content);
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
        
        toggleEditEndDateVisibility();
        showModal('edit-announcement-modal');
    }
    
    // 通过数据属性编辑公告
    function editAnnouncementById(button) {
        const id = button.getAttribute('data-id');
        const title = button.getAttribute('data-title');
        const content = button.getAttribute('data-content');
        const status = button.getAttribute('data-status');
        const priority = button.getAttribute('data-priority');
        const isPermanent = button.getAttribute('data-is-permanent');
        const startDate = button.getAttribute('data-start-date');
        const endDate = button.getAttribute('data-end-date');
        const subtitle = button.getAttribute('data-subtitle');
        
        editAnnouncement(id, title, content, status, priority, isPermanent, startDate, endDate, subtitle);
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
    
    // ==================== 更新日志相关函数 ====================
    // 编辑版本
    function editRelease(id, version, date, description) {
        document.getElementById('edit_release_id').value = id;
        document.getElementById('edit_version').value = version;
        document.getElementById('edit_release_date').value = date;
        document.getElementById('edit_description').value = description;
        showModal('edit-release-modal');
    }
    
    // 确认删除版本
    function confirmDeleteRelease(id, version) {
        document.getElementById('delete_release_id').value = id;
        document.getElementById('delete_release_version').textContent = version;
        showModal('delete-release-modal');
    }
    
    // 显示添加提交模态窗口
    function showAddCommitModal(releaseId) {
        document.getElementById('add_commit_release_id').value = releaseId;
        showModal('add-commit-modal');
    }
    
    // 编辑提交
    function editCommit(id, releaseId, type, message, sha, detail) {
        document.getElementById('edit_commit_id').value = id;
        document.getElementById('edit_commit_release_id').value = releaseId;
        document.getElementById('edit_commit_type').value = type;
        document.getElementById('edit_message').value = message;
        document.getElementById('edit_commit_sha').value = sha;
        document.getElementById('edit_detail').value = detail;
        showModal('edit-commit-modal');
    }
    
    // 确认删除提交
    function confirmDeleteCommit(id, releaseId, message) {
        document.getElementById('delete_commit_id').value = id;
        document.getElementById('delete_commit_release_id').value = releaseId;
        document.getElementById('delete_commit_message').textContent = message;
        showModal('delete-commit-modal');
    }
    
    // 页面加载完成后，初始化
    document.addEventListener('DOMContentLoaded', function() {
        // 初始化添加公告表单
        if (document.getElementById('is_permanent')) {
            document.getElementById('is_permanent').addEventListener('change', toggleEndDateVisibility);
        }
        
        // 在编辑公告模态窗口打开时，初始化编辑表单
        const editAnnouncementModal = document.getElementById('edit-announcement-modal');
        if (editAnnouncementModal) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'class' && editAnnouncementModal.classList.contains('show')) {
                        toggleEditEndDateVisibility();
                    }
                });
            });
            
            observer.observe(editAnnouncementModal, { attributes: true });
        }
    });
</script> 