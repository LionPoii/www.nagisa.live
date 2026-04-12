<?php
session_start();

// 定义系统标记
define('IN_SYSTEM', true);

require_once '../includes/auth.php';
require_once '../includes/account_service.php';

// 使用新的认证检查，传入 true 表示这是登录页面
checkAdminAuth(true);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (validateAdminPassword($username, $password)) {
        $admin = getAdminByUsername($username);
        if ($admin) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id']; // 用真实ID
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_token'] = hash('sha256', $admin['id'] . $_SERVER['HTTP_USER_AGENT']); // 生成安全令牌
            $_SESSION['last_activity'] = time(); // 设置最后活动时间
            $_SESSION['admin_role'] = $admin['role']; // 赋值角色
            
            // 记录登录时间
            updateLastLogin($username);
            
            header('Location: index.php');
            exit;
        } else {
            $error = '用户信息获取失败';
        }
    } else {
        $error = '用户名或密码错误';
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台登录 - Nagisa Live</title>
    <!-- 使用本地资源 -->
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <link href="../assets/css/fontawesome.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f0f2f5, #e6e9ee);
            min-height: 100vh;
        }
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.3s ease-out;
        }
        .page-loader.fade-out {
            opacity: 0;
            pointer-events: none;
        }
        .loader-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #303D4D;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* 美化登录表单 */
        .login-container {
            background: linear-gradient(135deg, #303D4D, #445569);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            border-radius: 12px;
        }
        .login-title {
            color: #ffffff;
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        .login-label {
            color: rgba(255, 255, 255, 0.9);
        }
        .login-input {
            border: 1px solid #e5e7eb;
            transition: all 0.3s;
            background-color: rgba(255, 255, 255, 0.9);
        }
        .login-input:focus {
            border-color: #5D7599;
            box-shadow: 0 0 0 3px rgba(48, 61, 77, 0.2);
        }
        .login-button {
            background: linear-gradient(135deg,rgb(241, 170, 123),rgb(190, 122, 77));
            transition: all 0.3s;
            font-weight: 600;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }
        .login-button:hover {
            box-shadow: 0 4px 8px rgba(48, 61, 77, 0.4);
        }
        .login-button::before,
        .login-button::after {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            animation: none;
            transition: all 0.5s;
        }
        
        .login-button::before {
            background: linear-gradient(90deg, 
                rgba(255, 255, 255, 0) 0%, 
                rgba(255, 255, 255, 0.3) 50%, 
                rgba(255, 255, 255, 0) 100%);
            z-index: 1;
        }
        
        .login-button::after {
            background: linear-gradient(90deg, 
                rgba(139, 69, 19, 0) 0%, 
                rgba(139, 69, 19, 0.3) 50%, 
                rgba(139, 69, 19, 0) 100%);
            z-index: 2;
        }
        
        .login-button:hover::before {
            animation: pulse-gradient 1.5s infinite;
        }
        
        .login-button:hover::after {
            animation: pulse-gradient 1.5s infinite 0.75s;
        }
        
        @keyframes pulse-gradient {
            0% {
                left: -100%;
            }
            100% {
                left: 100%;
            }
        }
        .footer-text {
            color: rgba(255, 255, 255, 0.8);
        }
    </style>
</head>
<body>
    <!-- 页面加载动画 -->
    <div class="page-loader">
        <div class="loader-spinner"></div>
    </div>

    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full login-container p-8">
            <h1 class="text-2xl login-title text-center mb-8">后台登录</h1>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium login-label mb-2">
                        用户名
                    </label>
                    <input type="text" 
                           name="username" 
                           required
                           class="w-full px-3 py-2 login-input rounded-md focus:outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium login-label mb-2">
                        密码
                    </label>
                    <input type="password" 
                           name="password" 
                           required
                           class="w-full px-3 py-2 login-input rounded-md focus:outline-none">
                </div>

                <button type="submit" 
                        class="w-full login-button text-white px-4 py-2 rounded focus:outline-none">
                    登录
                </button>
            </form>
            
            <div class="mt-6 text-center text-sm footer-text">
                <p>粉丝站后台系统</p>
            </div>
        </div>
    </div>

    <script>
        // 页面加载完成后隐藏加载动画
        window.addEventListener('load', function() {
            const loader = document.querySelector('.page-loader');
            loader.classList.add('fade-out');
            setTimeout(() => {
                loader.style.display = 'none';
            }, 1000);
        });

        // 表单提交时显示加载动画
        document.querySelector('form').addEventListener('submit', function() {
            document.querySelector('.page-loader').style.display = 'flex';
        });
    </script>
</body>
</html> 