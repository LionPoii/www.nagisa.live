<?php
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/toast.php';

// 检查管理员登录状态
checkAdminAuth();

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

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
        
        $stmt->execute(['v1.2.0', '2024-03-01', '✨ 用户体验升级与内容更新']);
        $release1_id = $conn->lastInsertId();
        
        $stmt->execute(['v1.1.0', '2024-01-15', '🚀 性能优化与功能增强']);
        $release2_id = $conn->lastInsertId();
        
        $stmt->execute(['v1.0.0', '2023-12-25', '🎉 初始版本发布！这是长濑未央粉丝站的第一个正式版本。']);
        $release3_id = $conn->lastInsertId();
        
        // 添加示例提交
        $stmt = $conn->prepare("INSERT INTO changelog_commits (release_id, commit_type, message, commit_sha, detail) VALUES (?, ?, ?, ?, ?)");
        
        // 版本1.2.0的提交
        $stmt->execute([$release1_id, 'feature', '增加黑暗模式支持', 'y1z2a3b', '添加网站暗色主题，并根据系统设置自动切换。']);
        $stmt->execute([$release1_id, 'docs', '更新作品资料', 'c4d5e6f', '更新并补充了作品列表和详情信息。']);
        $stmt->execute([$release1_id, 'improve', '优化页脚交互体验', 'g7h8i9j', '改进页脚弹出窗口，使友站链接显示在按钮正上方并随位置变动。']);
        $stmt->execute([$release1_id, 'other', '添加更新日志页面', 'k0l1m2n', '创建GitHub风格的更新日志页面，记录网站所有变更。']);
        
        // 版本1.1.0的提交
        $stmt->execute([$release2_id, 'improve', '优化图片加载速度', 'm2n3o4p', '实现图片懒加载和压缩，提高页面加载速度。']);
        $stmt->execute([$release2_id, 'fix', '修复移动端菜单显示问题', 'q5r6s7t', '解决了移动设备上菜单展开后遮挡内容的问题。']);
        $stmt->execute([$release2_id, 'feature', '添加图片画廊', 'u8v9w0x', '新增图片画廊功能，支持图片预览和轮播。']);
        
        // 版本1.0.0的提交
        $stmt->execute([$release3_id, 'feature', '添加首页基本结构和布局', 'a1b2c3d', '创建了网站的主页布局，包括导航栏、轮播图和内容区域。']);
        $stmt->execute([$release3_id, 'feature', '添加资料库初始内容', 'e4f5g6h', '添加了角色档案、作品列表和基本介绍页面。']);
        $stmt->execute([$release3_id, 'feature', '实现响应式设计', 'i7j8k9l', '网站现在可以适配不同设备屏幕大小，包括桌面、平板和手机。']);
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        showToast('初始化更新日志数据失败：' . $e->getMessage(), 'error');
    }
}

// 处理版本操作
if (isset($_POST['action'])) {
    try {
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
            header('Location: ' . $_SERVER['PHP_SELF']);
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
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        // 删除版本
        if ($_POST['action'] === 'delete_release' && isset($_POST['release_id'])) {
            $release_id = (int)$_POST['release_id'];
            
            $stmt = $conn->prepare("DELETE FROM changelog_releases WHERE id = ?");
            $stmt->execute([$release_id]);
            
            showToast('版本已删除！');
            header('Location: ' . $_SERVER['PHP_SELF']);
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
            header('Location: ' . $_SERVER['PHP_SELF'] . '?release_id=' . $release_id);
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
            header('Location: ' . $_SERVER['PHP_SELF'] . '?release_id=' . $release_id);
            exit;
        }
        
        // 删除提交
        if ($_POST['action'] === 'delete_commit' && isset($_POST['commit_id'], $_POST['release_id'])) {
            $commit_id = (int)$_POST['commit_id'];
            $release_id = (int)$_POST['release_id'];
            
            $stmt = $conn->prepare("DELETE FROM changelog_commits WHERE id = ?");
            $stmt->execute([$commit_id]);
            
            showToast('提交记录已删除！');
            header('Location: ' . $_SERVER['PHP_SELF'] . '?release_id=' . $release_id);
            exit;
        }
        
    } catch (Exception $e) {
        showToast($e->getMessage(), 'error');
    }
}

// 获取所有版本
$releases = [];
$stmt = $conn->prepare("SELECT * FROM changelog_releases ORDER BY release_date DESC");
$stmt->execute();
$releases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 如果指定了版本ID，获取该版本的所有提交
$commits = [];
$current_release = null;
if (isset($_GET['release_id']) && !empty($_GET['release_id'])) {
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
$page_title = '更新日志管理';
include 'admin_header.php';
?>

<div class="changelog-container">
    <div class="admin-content">
        <!-- 顶部按钮和标题 -->
        <div class="admin-content-header">
            <h2><?php echo isset($current_release) ? '管理版本：' . htmlspecialchars($current_release['version']) : '版本列表'; ?></h2>
            <?php if (isset($current_release)): ?>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="admin-button admin-button-manage">
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
                                            <a href="<?php echo $_SERVER['PHP_SELF'] . '?release_id=' . $release['id']; ?>" class="admin-button admin-button-small admin-button-manage">
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
    </div>
</div>

<!-- 添加版本模态窗口 -->
<div id="add-release-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>添加新版本</h3>
            <button type="button" class="admin-modal-close" onclick="hideModal('add-release-modal')">×</button>
        </div>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" style="width: 100%; box-sizing: border-box;">
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
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" style="width: 100%; box-sizing: border-box;">
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
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
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
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
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
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
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
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
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

<style>
    /* 整体容器样式 */
    .changelog-container {
        padding: 0 20px;
        max-width: 1400px;
        margin: 0 auto;
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
    
    /* 提交列表图标颜色 */
    .admin-button-manage .fa-list-ul {
        color: #fff;
    }
    
    /* 返回按钮图标颜色 */
    .admin-button-manage .fa-arrow-left {
        color: #fff;
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
        min-width: 95px;
        text-align: center;
        justify-content: center;
        white-space: nowrap;
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
</script>

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

include 'admin_footer.php';
?> 