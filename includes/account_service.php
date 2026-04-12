<?php
/**
 * 账号管理服务
 * 提供账号相关的功能，如更新密码、添加新账号、获取账号列表等
 */

/*
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 * AI_REF: NAGISA_ADMINS_ARCHIVE_PERMISSION
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 * 数据库字段（并列、可叠加）:
 *   - admins.archive_ar_editor  TINYINT(1) — 对应界面「ar-editor」：archive.nagisa.live /admin（见 archive 站 admin/_auth.php）
 *   - admins.archive_so_editor  TINYINT(1) — 对应界面「so-editor」：储备/未来独立站点后台，与 ar 并列管理
 *
 * 界面标签常量（仅用于文案 / badge，与库字段一一对应）:
 */
/** @see AI_REF: NAGISA_ADMINS_ARCHIVE_PERMISSION */
define('ARCHIVE_PERMISSION_AR_EDITOR', 'ar-editor');
/** @see AI_REF: NAGISA_ADMINS_ARCHIVE_PERMISSION */
define('ARCHIVE_PERMISSION_SO_EDITOR', 'so-editor');

/**
 * POST 勾选「1」转为 0/1
 */
function adminSiteFlagToInt($val) {
    return (!empty($val) && (string) $val === '1') ? 1 : 0;
}

// 数据库连接函数（假设config.php中已定义）
function getAccountDbConnection() {
    // 使用配置文件中的数据库连接
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/database.php';
    $database = new Database();
    return $database->getConnection();
}

/**
 * 验证管理员密码
 * 
 * @param string $username 用户名
 * @param string $password 密码
 * @return bool 验证是否成功
 */
function validateAdminPassword($username, $password) {
    try {
        $conn = getAccountDbConnection();
        if (!$conn) {
            return false;
        }
        
        $stmt = $conn->prepare("SELECT password_hash FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && password_verify($password, $user['password_hash']);
    } catch (PDOException $e) {
        error_log("验证密码失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 更新管理员密码
 * 
 * @param string $username 用户名
 * @param string $new_password 新密码
 * @return bool 更新是否成功
 */
function updateAdminPassword($username, $new_password) {
    try {
        $conn = getAccountDbConnection();
        if (!$conn) {
            return false;
        }
        
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admins SET password_hash = ? WHERE username = ?");
        return $stmt->execute([$password_hash, $username]);
    } catch (PDOException $e) {
        error_log("更新密码失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 检查用户名是否已存在
 * 
 * @param string $username 用户名
 * @return bool 用户名是否存在
 */
function checkUsernameExists($username) {
    try {
        $conn = getAccountDbConnection();
        if (!$conn) {
            return false;
        }
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("检查用户名失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 添加新管理员账号
 * 
 * @param string $username 用户名
 * @param string $password 密码
 * @param string $role 角色
 * @return bool 添加是否成功
 */
function addNewAdmin($username, $password, $role, $archive_ar_editor = false, $archive_so_editor = false) {
    try {
        $conn = getAccountDbConnection();
        if (!$conn) {
            return false;
        }
        
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $current_time = date('Y-m-d H:i:s');
        $ar = $archive_ar_editor ? 1 : 0;
        $so = $archive_so_editor ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO admins (username, password_hash, role, archive_ar_editor, archive_so_editor, created_at, last_login) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$username, $password_hash, $role, $ar, $so, $current_time, $current_time]);
    } catch (PDOException $e) {
        error_log("添加管理员失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 获取管理员账号列表
 * 
 * @return array 账号列表
 */
function getAdminList() {
    try {
        $conn = getAccountDbConnection();
        if (!$conn) {
            return [];
        }
        
        $stmt = $conn->query("SELECT id, username, role, archive_ar_editor, archive_so_editor, last_login FROM admins ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("获取管理员列表失败: " . $e->getMessage());
        return [];
    }
}

/**
 * 删除管理员账号
 * 
 * @param int $id 账号ID
 * @return bool 删除是否成功
 */
function deleteAdmin($id) {
    try {
        $conn = getAccountDbConnection();
        if (!$conn) {
            return false;
        }
        
        // 不允许删除admin账号（ID=1）
        if ($id == 1) {
            return false;
        }
        
        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("删除管理员失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 获取单个管理员账号信息
 * 
 * @param int $id 账号ID
 * @return array|null 账号信息
 */
function getAdminById($id) {
    try {
        $conn = getAccountDbConnection();
        if (!$conn) {
            return null;
        }
        $stmt = $conn->prepare("SELECT id, username, role, archive_ar_editor, archive_so_editor FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $e) {
        error_log("获取管理员失败: " . $e->getMessage());
        return null;
    }
}

/**
 * 更新管理员账号信息
 * 
 * @param int $id 账号ID
 * @param array $data 更新数据
 * @return bool 更新是否成功
 */
function updateAdmin($id, $data) {
    try {
        $conn = getAccountDbConnection();
        if (!$conn) return false;
        // 获取当前账号信息
        $current = getAdminById($id);
        if (!$current) return false;
        $username = isset($data['username']) ? $data['username'] : $current['username'];
        $role = isset($data['role']) ? $data['role'] : $current['role'];
        if ($id == 1 && $role !== 'admin') return false;
        $ar = (int) ($current['archive_ar_editor'] ?? 0);
        $so = (int) ($current['archive_so_editor'] ?? 0);
        if (array_key_exists('archive_ar_editor', $data)) {
            $ar = adminSiteFlagToInt($data['archive_ar_editor']);
        }
        if (array_key_exists('archive_so_editor', $data)) {
            $so = adminSiteFlagToInt($data['archive_so_editor']);
        }
        $stmt = $conn->prepare("UPDATE admins SET username = ?, role = ?, archive_ar_editor = ?, archive_so_editor = ? WHERE id = ?");
        return $stmt->execute([$username, $role, $ar, $so, $id]);
    } catch (PDOException $e) {
        error_log("更新账号信息失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 记录管理员登录时间
 * 
 * @param string $username 用户名
 * @return bool 更新是否成功
 */
function updateLastLogin($username) {
    try {
        $conn = getAccountDbConnection();
        if (!$conn) {
            return false;
        }
        
        $stmt = $conn->prepare("UPDATE admins SET last_login = NOW() WHERE username = ?");
        return $stmt->execute([$username]);
    } catch (PDOException $e) {
        error_log("更新登录时间失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 根据用户名获取管理员信息
 * 
 * @param string $username 用户名
 * @return array|null 管理员信息
 */
function getAdminByUsername($username) {
    try {
        $conn = getAccountDbConnection();
        if (!$conn) {
            return null;
        }
        
        $stmt = $conn->prepare("SELECT id, username, role, archive_ar_editor, archive_so_editor FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("获取管理员信息失败: " . $e->getMessage());
        return null;
    }
} 