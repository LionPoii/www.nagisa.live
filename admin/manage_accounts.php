<?php
// 定义系统标记，防止直接访问限制
define('IN_SYSTEM', true);

require_once '../includes/auth.php';
require_once '../includes/account_service.php';

// 检查管理员登录状态
checkAdminAuth();

// 页面标题
$page_title = "账号管理";

// 处理表单提交
$message = '';
$message_type = 'success';

// 处理修改密码
if (isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $username = $_SESSION['admin_username'] ?? 'admin'; // 获取当前登录用户名
    
    // 验证当前密码
    if (validateAdminPassword($username, $current_password)) {
        // 验证新密码
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 8) {
                if (updateAdminPassword($username, $new_password)) {
                    $message = '密码已成功更新';
                    $message_type = 'success';
                } else {
                    $message = '密码更新失败，请稍后重试';
                    $message_type = 'error';
                }
            } else {
                $message = '新密码长度必须至少为8个字符';
                $message_type = 'error';
            }
        } else {
            $message = '新密码和确认密码不匹配';
            $message_type = 'error';
        }
    } else {
        $message = '当前密码不正确';
        $message_type = 'error';
    }
}

// 处理添加新账号
if (isset($_POST['add_account'])) {
    // 检查权限，只有admin角色才能添加账号
    if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
        $message = '您没有权限执行此操作';
        $message_type = 'error';
    } else {
        $new_username = $_POST['new_username'] ?? '';
        $new_user_password = $_POST['new_user_password'] ?? '';
        $new_user_role = $_POST['new_user_role'] ?? '';
        
        if (!empty($new_username) && !empty($new_user_password) && !empty($new_user_role)) {
        // 检查用户名是否已存在
        if (!checkUsernameExists($new_username)) {
            $new_ar = adminSiteFlagToInt($_POST['new_archive_ar_editor'] ?? null);
            $new_so = adminSiteFlagToInt($_POST['new_archive_so_editor'] ?? null);
            if (addNewAdmin($new_username, $new_user_password, $new_user_role, $new_ar, $new_so)) {
                $message = '新账号已成功创建';
                $message_type = 'success';
            } else {
                $message = '账号创建失败，请稍后重试';
                $message_type = 'error';
            }
        } else {
            $message = '用户名已存在';
            $message_type = 'error';
        }
            } else {
            $message = '请填写所有必填字段';
            $message_type = 'error';
        }
    }
}

// 处理删除账号
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // 检查权限，只有admin角色才能删除账号
    if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
        $message = '您没有权限执行此操作';
        $message_type = 'error';
    } else {
        $id = (int)$_GET['delete'];
        if (deleteAdmin($id)) {
            $message = '账号已成功删除';
            $message_type = 'success';
        } else {
            $message = '无法删除该账号';
            $message_type = 'error';
        }
    }
}

// 处理重置密码
if (isset($_GET['reset_password']) && is_numeric($_GET['reset_password'])) {
    // 检查权限，只有admin角色才能重置密码
    if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
        $message = '您没有权限执行此操作';
        $message_type = 'error';
    } else {
        $id = (int)$_GET['reset_password'];
        
        // 获取用户信息
        $user_to_reset = null;
        foreach ($admin_list as $admin) {
            if ($admin['id'] == $id) {
                $user_to_reset = $admin;
                break;
            }
        }
        
        // 不允许重置admin账号密码
        if ($user_to_reset && $user_to_reset['username'] !== 'admin') {
            // 生成随机密码
            $new_password = generateRandomPassword();
            
            // 更新密码
            if (updateAdminPassword($user_to_reset['username'], $new_password)) {
                $message = '密码已重置为: ' . $new_password;
                $message_type = 'success';
            } else {
                $message = '密码重置失败';
                $message_type = 'error';
            }
        } else {
            $message = '无法重置此账号的密码';
            $message_type = 'error';
        }
    }
}

// 生成随机密码
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// 获取账号列表
$admin_list = getAdminList();

// 获取当前登录账号信息
$current_username = $_SESSION['admin_username'] ?? 'admin';
$current_account = null;
foreach ($admin_list as $admin) {
    if ($admin['username'] === $current_username) {
        $current_account = $admin;
        // 将用户角色保存到会话中，用于权限检查
        $_SESSION['admin_role'] = $admin['role'];
        break;
    }
}

// 不再需要获取密码
// $current_password = getAdminPassword($current_username);

// 处理编辑账号
$edit_account = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    foreach ($admin_list as $a) {
        if ($a['id'] == $edit_id) {
            $edit_account = $a;
            break;
        }
    }
}

// 处理账号更新
if (isset($_POST['update_account'])) {
    $edit_id = (int)($_POST['edit_id'] ?? 0);
    $edit_username = trim($_POST['edit_username'] ?? '');
    $edit_role = $_POST['edit_role'] ?? '';
    if ($edit_id && $edit_username && $edit_role) {
        if (updateAdmin($edit_id, [
            'username' => $edit_username,
            'role' => $edit_role,
            'archive_ar_editor' => adminSiteFlagToInt($_POST['edit_archive_ar_editor'] ?? null),
            'archive_so_editor' => adminSiteFlagToInt($_POST['edit_archive_so_editor'] ?? null),
        ])) {
            $message = '账号信息已更新';
            $message_type = 'success';
            // 刷新列表
            $admin_list = getAdminList();
            $edit_account = null;
        } else {
            $message = '账号信息更新失败';
            $message_type = 'error';
        }
    } else {
        $message = '请填写完整信息';
        $message_type = 'error';
    }
}

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

/* 隐藏浏览器默认的密码显示/隐藏图标 */
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear,
input[type="password"]::-webkit-contacts-auto-fill-button,
input[type="password"]::-webkit-credentials-auto-fill-button {
    display: none !important;
    visibility: hidden;
    pointer-events: none;
}

/* 移除浏览器默认的密码图标 */
input[type="password"] {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

/* 禁用Chrome浏览器自动填充时的背景色 */
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus,
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 30px white inset !important;
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

.nagisa-input {
    border: 2px solid rgba(204, 148, 113, 0.3);
    transition: all 0.3s ease;
    width: 100%;
    padding: 8px 12px;
    border-radius: 8px;
}

.nagisa-input:focus {
    border-color: #cc9471;
    box-shadow: 0 0 0 3px rgba(204, 148, 113, 0.2);
    outline: none;
}

.nagisa-select {
    border: 2px solid rgba(204, 148, 113, 0.3);
    transition: all 0.3s ease;
    width: 100%;
    padding: 8px 12px;
    border-radius: 8px;
}

.nagisa-select:focus {
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

.nagisa-btn-small {
    padding: 6px 12px;
    font-size: 0.875rem;
}

.nagisa-btn-warning {
    background: linear-gradient(45deg, #e8a87c, #ffcca7);
}

.nagisa-btn-warning:hover {
    background: linear-gradient(45deg, #f0b185, #ffd8b8);
}

.nagisa-btn-danger {
    background: linear-gradient(45deg, #e57373, #ffcdd2);
}

.nagisa-btn-danger:hover {
    background: linear-gradient(45deg, #ef5350, #ffb3b3);
}

.nagisa-btn-primary {
    background: linear-gradient(45deg, #5c6bc0, #8e99f3);
}

.nagisa-btn-primary:hover {
    background: linear-gradient(45deg, #3f51b5, #7986cb);
}

.nagisa-section-title {
    color: #cc9471;
    font-weight: 600;
    padding-bottom: 8px;
    border-bottom: 2px solid rgba(204, 148, 113, 0.2);
    margin-bottom: 16px;
}

.nagisa-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}

.nagisa-badge-primary {
    background: linear-gradient(45deg, #cc9471, #f3b4a4);
    color: white;
}

.nagisa-badge-secondary {
    background: linear-gradient(45deg, #7c9ce8, #a7c2ff);
    color: white;
}

.nagisa-badge-default {
    background: linear-gradient(45deg, #a5a5a5, #d0d0d0);
    color: white;
}

.nagisa-form-group {
    margin-bottom: 16px;
}

.nagisa-form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 500;
    color: #4b5563;
    margin-bottom: 8px;
}

.nagisa-form-hint {
    display: block;
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 4px;
}

.nagisa-table {
    width: 100%;
    border-collapse: collapse;
}

.nagisa-table th {
    background-color: #f9f1ee;
    color: #cc9471;
    font-weight: 600;
    text-align: left;
    padding: 12px 16px;
    border-bottom: 2px solid rgba(204, 148, 113, 0.2);
}

.nagisa-table td {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(204, 148, 113, 0.1);
}

.nagisa-table tr:hover {
    background-color: #f9f5f2;
}

.pwd-toggle-btn {
  position: absolute;
  right: 0;
  top: 0;
  bottom: 0;
  background: none;
  border: none;
  outline: none;
  cursor: pointer;
  padding: 0 12px;
  color: #cc9471;
  font-size: 1.2rem;
  z-index: 2;
  transition: color 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
}
.pwd-toggle-btn:hover {
  color: #a86b3c;
}
.password-wrapper {
  position: relative;
  display: block;
  width: 100%;
}
.password-wrapper .nagisa-input {
  padding-right: 38px !important;
  width: 100%;
  height: 42px;
}

/* 导航链接样式 */
.nagisa-nav-link {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    border-radius: 8px;
    color: #4b5563;
    font-weight: 500;
    transition: all 0.2s ease;
}

.nagisa-nav-link:hover {
    background-color: #f9f1ee;
    color: #cc9471;
}

.nagisa-nav-link.active {
    background-color: #f9f1ee;
    color: #cc9471;
    border-left: 3px solid #cc9471;
}

/* 内容区域样式 */
.section-content {
    display: none;
}

.section-content.active {
    display: block;
}
';

// 包含管理后台页眉
include 'admin_header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'error' ? 'red' : 'green'; ?>-100 border border-<?php echo $message_type === 'error' ? 'red' : 'green'; ?>-400 text-<?php echo $message_type === 'error' ? 'red' : 'green'; ?>-700 px-4 py-3 rounded mb-4">
        <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
        <!-- 侧边导航 -->
        <div class="md:col-span-3">
            <div class="nagisa-card">
                <h2 class="nagisa-card-header">账号管理</h2>
                <div class="p-4">
                    <ul class="space-y-1">
                        <li>
                            <a href="#" class="nagisa-nav-link active" onclick="showSection('my-account'); return false;">
                                <i class="fas fa-user-circle mr-2"></i>我的账号
                            </a>
                        </li>
                        <?php if ($current_account && $current_account['role'] === 'admin'): ?>
                        <li>
                            <a href="#" class="nagisa-nav-link" onclick="showSection('view-accounts'); return false;">
                                <i class="fas fa-users mr-2"></i>查看账号
                            </a>
                        </li>
                        <li>
                            <a href="#" class="nagisa-nav-link" onclick="showSection('create-account'); return false;">
                                <i class="fas fa-user-plus mr-2"></i>创建账号
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a href="#" class="nagisa-nav-link" onclick="showSection('change-password'); return false;">
                                <i class="fas fa-key mr-2"></i>修改密码
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
                            <span>我的账号：查看当前登录账号信息</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>查看账号：管理系统中的所有账号</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>创建账号：添加新的管理员账号</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>修改密码：更新当前账号的密码</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                            <span>关联站点权限：账号关联权限</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- 主要内容区域 -->
        <div class="md:col-span-9">
            <!-- 查看账号部分 -->
            <div id="view-accounts" class="nagisa-card section-content">
                <h2 class="nagisa-card-header">现有账号列表</h2>
                <?php if ($current_account && $current_account['role'] === 'admin'): ?>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="nagisa-table">
                            <thead>
                                <tr>
                                    <th>用户名</th>
                                    <th>权限</th>
                                    <th>扩展站点权限</th>
                                    <th>上次登录</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admin_list as $admin): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                    <td>
                                        <?php if ($admin['role'] === 'admin'): ?>
                                        <span class="nagisa-badge nagisa-badge-primary">I</span>
                                        <?php elseif ($admin['role'] === 'editor'): ?>
                                        <span class="nagisa-badge nagisa-badge-secondary">II</span>
                                        <?php else: ?>
                                        <span class="nagisa-badge nagisa-badge-default">III</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $hasAr = !empty($admin['archive_ar_editor']);
                                        $hasSo = !empty($admin['archive_so_editor']);
                                        if (!$hasAr && !$hasSo): ?>
                                        <span class="text-gray-400 text-sm">—</span>
                                        <?php else: ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php if ($hasAr): ?>
                                            <span class="nagisa-badge nagisa-badge-primary" title="archive.nagisa.live /admin"><?php echo htmlspecialchars(ARCHIVE_PERMISSION_AR_EDITOR); ?></span>
                                            <?php endif; ?>
                                            <?php if ($hasSo): ?>
                                            <span class="nagisa-badge nagisa-badge-default" title="储备站点（并列）"><?php echo htmlspecialchars(ARCHIVE_PERMISSION_SO_EDITOR); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($admin['last_login']); ?></td>
                                    <td>
                                        <?php if ($admin['username'] === 'admin'): ?>
                                        <button class="nagisa-btn nagisa-btn-small nagisa-btn-warning" disabled>
                                            <i class="fas fa-edit"></i> 编辑
                                        </button>
                                        <button class="nagisa-btn nagisa-btn-small nagisa-btn-danger" disabled>
                                            <i class="fas fa-trash"></i> 删除
                                        </button>
                                        <?php else: ?>
                                        <a href="?edit=<?php echo $admin['id']; ?>" class="nagisa-btn nagisa-btn-small nagisa-btn-warning">
                                            <i class="fas fa-edit"></i> 编辑
                                        </a>
                                        <a href="?reset_password=<?php echo $admin['id']; ?>" class="nagisa-btn nagisa-btn-small nagisa-btn-primary" onclick="return confirm('确定要重置该账号的密码吗？重置后将生成一个随机密码。');">
                                            <i class="fas fa-key"></i> 重置密码
                                        </a>
                                        <a href="?delete=<?php echo $admin['id']; ?>" class="nagisa-btn nagisa-btn-small nagisa-btn-danger" onclick="return confirm('确定要删除该账号吗？');">
                                            <i class="fas fa-trash"></i> 删除
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="p-6">
                    <div class="bg-yellow-50 p-4 rounded-md text-yellow-700">
                        <p><i class="fas fa-lock mr-2"></i>您没有权限查看此页面内容。</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 我的账号部分 -->
            <div id="my-account" class="nagisa-card section-content active">
                <h2 class="nagisa-card-header">我的账号信息</h2>
                <div class="p-6">
                    <?php if ($current_account): ?>
                    <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($current_account['username']); ?></h3>
                            <div class="mt-1">
                                <?php if ($current_account['role'] === 'admin'): ?>
                                <span class="nagisa-badge nagisa-badge-primary">I</span>
                                <?php elseif ($current_account['role'] === 'editor'): ?>
                                <span class="nagisa-badge nagisa-badge-secondary">II</span>
                                <?php else: ?>
                                <span class="nagisa-badge nagisa-badge-default">III</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">账号详情</h4>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">用户名</label>
                                        <div class="mt-1 p-3 bg-white border border-gray-300 rounded-md">
                                            <?php echo htmlspecialchars($current_account['username']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">权限</label>
                                        <div class="mt-1 p-3 bg-white border border-gray-300 rounded-md">
                                            <?php 
                                            if ($current_account['role'] === 'admin') echo 'I';
                                            elseif ($current_account['role'] === 'editor') echo 'II';
                                            else echo 'III';
                                            ?>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">扩展站点权限</label>
                                        <div class="mt-1 p-3 bg-white border border-gray-300 rounded-md">
                                            <?php
                                            $cAr = !empty($current_account['archive_ar_editor']);
                                            $cSo = !empty($current_account['archive_so_editor']);
                                            if (!$cAr && !$cSo): ?>
                                            <span class="text-gray-500">未授予</span>
                                            <?php else: ?>
                                            <div class="flex flex-wrap gap-2 items-center">
                                                <?php if ($cAr): ?>
                                                <span><span class="nagisa-badge nagisa-badge-primary"><?php echo htmlspecialchars(ARCHIVE_PERMISSION_AR_EDITOR); ?></span><span class="text-gray-600 text-sm ml-1">archive.nagisa.live /admin</span></span>
                                                <?php endif; ?>
                                                <?php if ($cSo): ?>
                                                <span><span class="nagisa-badge nagisa-badge-default"><?php echo htmlspecialchars(ARCHIVE_PERMISSION_SO_EDITOR); ?></span><span class="text-gray-600 text-sm ml-1">储备站点</span></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">登录信息</h4>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">上次登录时间</label>
                                        <div class="mt-1 p-3 bg-white border border-gray-300 rounded-md">
                                            <?php echo htmlspecialchars($current_account['last_login']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        

                        
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-3">权限说明</h4>
                            <div class="bg-blue-50 p-4 rounded-md text-blue-700 text-sm">
                                <?php if ($current_account['role'] === 'admin'): ?>
                                <p><i class="fas fa-info-circle mr-2"></i><strong>I权限：</strong>可以管理所有账号、添加新账号、修改系统设置等全部功能。</p>
                                <?php elseif ($current_account['role'] === 'editor'): ?>
                                <p><i class="fas fa-info-circle mr-2"></i><strong>II权限：</strong>可以编辑内容、上传文件。</p>
                                <?php else: ?>
                                <p><i class="fas fa-info-circle mr-2"></i><strong>III权限：</strong>查看内容。</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-yellow-50 p-4 rounded-md text-yellow-700">
                        <p><i class="fas fa-exclamation-triangle mr-2"></i>无法获取当前账号信息，请尝试重新登录。</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 创建账号部分 -->
            <div id="create-account" class="nagisa-card section-content">
                <h2 class="nagisa-card-header">添加新账号</h2>
                <?php if ($current_account && $current_account['role'] === 'admin'): ?>
                <div class="p-6">
                    <form method="POST">
                        <div class="nagisa-form-group">
                            <label for="new_username" class="nagisa-form-label">用户名</label>
                            <input type="text" id="new_username" name="new_username" required class="nagisa-input">
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label for="new_user_password" class="nagisa-form-label">密码</label>
                            <div class="password-wrapper">
                                <input type="password" id="new_user_password" name="new_user_password" required class="nagisa-input" minlength="8">
                                <button type="button" class="pwd-toggle-btn" onclick="togglePwd('new_user_password', this)" tabindex="-1" aria-label="显示/隐藏密码"><i class="fas fa-eye-slash"></i></button>
                            </div>
                            <small class="nagisa-form-hint">密码长度至少8个字符</small>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label for="new_user_role" class="nagisa-form-label">权限</label>
                            <select id="new_user_role" name="new_user_role" required class="nagisa-select">
                                <option value="">选择权限</option>
                                <option value="admin">I</option>
                                <option value="editor">II</option>
                                <option value="viewer">III</option>
                            </select>
                        </div>
                        <div class="nagisa-form-group">
                            <span class="nagisa-form-label">扩展站点权限 <span class="text-xs font-normal text-gray-500">（AI_REF: NAGISA_ADMINS_ARCHIVE_PERMISSION）</span></span>
                            <div class="mt-2 space-y-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="new_archive_ar_editor" value="1" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                    <span class="text-sm text-gray-700"><code class="text-xs bg-gray-100 px-1 rounded"><?php echo htmlspecialchars(ARCHIVE_PERMISSION_AR_EDITOR); ?></code> — archive.nagisa.live 管理后台</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="new_archive_so_editor" value="1" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                    <span class="text-sm text-gray-700"><code class="text-xs bg-gray-100 px-1 rounded"><?php echo htmlspecialchars(ARCHIVE_PERMISSION_SO_EDITOR); ?></code> — 储备站点（与上并列，独立管理）</span>
                                </label>
                            </div>
                            <small class="nagisa-form-hint">可同时勾选；与主站 I / II / III 权限独立。</small>
                        </div>
                        
                        <div class="flex justify-end pt-3">
                            <button type="submit" name="add_account" class="nagisa-btn">
                                <i class="fas fa-user-plus mr-2"></i> 创建账号
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="p-6">
                    <div class="bg-yellow-50 p-4 rounded-md text-yellow-700">
                        <p><i class="fas fa-lock mr-2"></i>您没有权限查看此页面内容。</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 修改密码部分 -->
            <div id="change-password" class="nagisa-card section-content">
                <h2 class="nagisa-card-header">修改密码</h2>
                <div class="p-6">
                    <form method="POST">
                        <div class="nagisa-form-group">
                            <label for="current_password" class="nagisa-form-label">当前密码</label>
                            <div class="password-wrapper">
                                <input type="password" id="current_password" name="current_password" required class="nagisa-input">
                                <button type="button" class="pwd-toggle-btn" onclick="togglePwd('current_password', this)" tabindex="-1" aria-label="显示/隐藏密码"><i class="fas fa-eye-slash"></i></button>
                            </div>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label for="new_password" class="nagisa-form-label">新密码</label>
                            <div class="password-wrapper">
                                <input type="password" id="new_password" name="new_password" required class="nagisa-input" minlength="8">
                                <button type="button" class="pwd-toggle-btn" onclick="togglePwd('new_password', this)" tabindex="-1" aria-label="显示/隐藏密码"><i class="fas fa-eye-slash"></i></button>
                            </div>
                            <small class="nagisa-form-hint">密码长度至少8个字符</small>
                        </div>
                        
                        <div class="nagisa-form-group">
                            <label for="confirm_password" class="nagisa-form-label">确认新密码</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" required class="nagisa-input" minlength="8">
                                <button type="button" class="pwd-toggle-btn" onclick="togglePwd('confirm_password', this)" tabindex="-1" aria-label="显示/隐藏密码"><i class="fas fa-eye-slash"></i></button>
                            </div>
                        </div>
                        
                        <div class="flex justify-end pt-3">
                            <button type="submit" name="update_password" class="nagisa-btn">
                                <i class="fas fa-save mr-2"></i> 更新密码
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($edit_account): ?>
<!-- 编辑账号弹窗 -->
<div style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:9999;display:flex;align-items:center;justify-content:center;">
  <div class="nagisa-card" style="min-width:320px;max-width:90vw;">
    <h2 class="nagisa-card-header">编辑账号</h2>
    <div class="p-6">
      <form method="POST">
        <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($edit_account['id']); ?>">
        <div class="nagisa-form-group">
          <label class="nagisa-form-label">用户名</label>
          <input type="text" name="edit_username" class="nagisa-input" required value="<?php echo htmlspecialchars($edit_account['username']); ?>">
        </div>
        <div class="nagisa-form-group">
          <label class="nagisa-form-label">权限</label>
          <select name="edit_role" class="nagisa-select" required>
                                        <option value="admin" <?php if($edit_account['role']==='admin')echo'selected';?>>I</option>
                            <option value="editor" <?php if($edit_account['role']==='editor')echo'selected';?>>II</option>
                            <option value="viewer" <?php if($edit_account['role']==='viewer')echo'selected';?>>III</option>
          </select>
        </div>
        <div class="nagisa-form-group">
          <span class="nagisa-form-label">扩展站点权限</span>
          <input type="hidden" name="edit_archive_ar_editor" value="0">
          <input type="hidden" name="edit_archive_so_editor" value="0">
          <div class="mt-2 space-y-2">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="edit_archive_ar_editor" value="1" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500" <?php echo !empty($edit_account['archive_ar_editor']) ? 'checked' : ''; ?>>
              <span class="text-sm text-gray-700"><code class="text-xs bg-gray-100 px-1 rounded"><?php echo htmlspecialchars(ARCHIVE_PERMISSION_AR_EDITOR); ?></code> — archive.nagisa.live /admin</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="edit_archive_so_editor" value="1" class="rounded border-gray-300 text-amber-600 focus:ring-amber-500" <?php echo !empty($edit_account['archive_so_editor']) ? 'checked' : ''; ?>>
              <span class="text-sm text-gray-700"><code class="text-xs bg-gray-100 px-1 rounded"><?php echo htmlspecialchars(ARCHIVE_PERMISSION_SO_EDITOR); ?></code> — 储备站点（并列）</span>
            </label>
          </div>
          <small class="nagisa-form-hint">AI_REF: NAGISA_ADMINS_ARCHIVE_PERMISSION — 两列独立，可同时勾选。</small>
        </div>
        <div class="flex justify-end pt-3">
          <button type="submit" name="update_account" class="nagisa-btn"><i class="fas fa-save mr-2"></i>保存</button>
          <a href="manage_accounts.php" class="nagisa-btn nagisa-btn-warning ml-2">取消</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
    // 页面加载完成后隐藏加载动画
    window.addEventListener('load', function() {
        const loader = document.querySelector('.page-loader');
        if (loader) {
            loader.classList.add('fade-out');
            setTimeout(() => {
                loader.style.display = 'none';
            }, 300);
        }
    });
    
    // 密码确认验证
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    function validatePasswordMatch() {
        if (newPasswordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.setCustomValidity('密码不匹配');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    }
    
    if (newPasswordInput && confirmPasswordInput) {
        newPasswordInput.addEventListener('change', validatePasswordMatch);
        confirmPasswordInput.addEventListener('keyup', validatePasswordMatch);
    }

    // 密码显示/隐藏功能
    function togglePwd(id, btn) {
        var input = document.getElementById(id);
        if (input.type === 'password') {
            input.type = 'text';
            btn.querySelector('i').classList.remove('fa-eye-slash');
            btn.querySelector('i').classList.add('fa-eye');
        } else {
            input.type = 'password';
            btn.querySelector('i').classList.remove('fa-eye');
            btn.querySelector('i').classList.add('fa-eye-slash');
        }
    }
    
    // 切换内容区域
    function showSection(sectionId) {
        // 隐藏所有内容区域
        document.querySelectorAll('.section-content').forEach(section => {
            section.classList.remove('active');
        });
        
        // 移除所有导航链接的活动状态
        document.querySelectorAll('.nagisa-nav-link').forEach(link => {
            link.classList.remove('active');
        });
        
        // 显示选定的内容区域
        document.getElementById(sectionId).classList.add('active');
        
        // 设置选定的导航链接为活动状态
        const activeLink = document.querySelector(`.nagisa-nav-link[onclick="showSection('${sectionId}'); return false;"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
        
        // 不再保存状态到本地存储，确保每次加载页面时都显示"我的账号"
        // localStorage.setItem('accounts_active_section', sectionId);
    }
    
    // 页面加载时，根据URL参数决定显示哪个区域，默认总是显示"我的账号"
    document.addEventListener('DOMContentLoaded', function() {
        // 如果URL中有edit参数，显示账号列表区域
        if (window.location.search.includes('edit=') || window.location.search.includes('delete=')) {
            showSection('view-accounts');
            return;
        }
        
        // 默认总是显示"我的账号"
        showSection('my-account');
    });
</script>

<?php include 'admin_footer.php'; ?> 