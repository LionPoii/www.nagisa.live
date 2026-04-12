<?php
session_start();

function checkAdminAuth($isLoginPage = false) {
    // 如果是登录页面，且用户已登录，则重定向到管理面板
    if ($isLoginPage && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        header('Location: /admin/index.php');
        exit;
    }

    // 如果不是登录页面，则检查认证
    if (!$isLoginPage) {
        // 检查会话是否过期（30分钟）
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            session_unset();
            session_destroy();
            header('Location: /admin/login.php');
            exit;
        }

        // 更新最后活动时间
        $_SESSION['last_activity'] = time();

        // 检查是否已登录
        if (!isset($_SESSION['admin_logged_in']) || 
            !isset($_SESSION['admin_id']) || 
            !isset($_SESSION['admin_token']) || 
            $_SESSION['admin_logged_in'] !== true) {
            header('Location: /admin/login.php');
            exit;
        }

        // 验证令牌
        $stored_token = $_SESSION['admin_token'];
        $current_token = hash('sha256', $_SESSION['admin_id'] . $_SERVER['HTTP_USER_AGENT']);
        
        if (!hash_equals($stored_token, $current_token)) {
            session_unset();
            session_destroy();
            header('Location: /admin/login.php');
            exit;
        }
    }
}

function loginAdmin($username, $password) {
    require_once 'account_service.php';
    
    // 使用account_service中的函数验证密码
    if (validateAdminPassword($username, $password)) {
        // 获取管理员信息
        $admin = getAdminByUsername($username);
        if ($admin) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_token'] = hash('sha256', $admin['id'] . $_SERVER['HTTP_USER_AGENT']); // 生成安全令牌
            $_SESSION['last_activity'] = time(); // 设置最后活动时间
            
            // 更新最后登录时间
            updateLastLogin($username);
            
            return true;
        }
    }
    return false;
}

function logoutAdmin() {
    session_unset();
    session_destroy();
} 