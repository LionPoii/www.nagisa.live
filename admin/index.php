<?php
session_start();
require_once '../includes/auth.php';

// 检查管理员登录状态
checkAdminAuth();
// 连接数据库以检查未处理反馈数量
require_once '../includes/database.php';
$pendingFeedbackCount = 0;
try {
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        $stmt = $conn->query('SELECT COUNT(*) FROM feedback WHERE status=0');
        $pendingFeedbackCount = intval($stmt->fetchColumn());
    }
} catch (Exception $e) {
    $pendingFeedbackCount = 0;
}

// 获取管理员信息
$admin_username = 'admin'; // 这里可以从数据库获取

// 设置缓存控制头
header('Cache-Control: private, max-age=3600');
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>管理后台</title>
    <!-- 预加载关键资源 -->
    <link rel="preload" href="/assets/css/admin-tailwind.min.css" as="style">
    <link rel="preload" href="/assets/css/fontawesome.min.css" as="style">
    <link rel="preload" href="admin_style_enhanced.css" as="style">
    
    <!-- 加载CSS -->
    <link href="/assets/css/admin-tailwind.min.css" rel="stylesheet">
    <link href="/assets/css/fontawesome.min.css" rel="stylesheet">
    <link href="admin_style_enhanced.css" rel="stylesheet">
    <style>
        .admin-layout {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 30px;
        }
        
        .admin-column {
            flex: 1;
            min-width: 320px;
        }
        
        .admin-column-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(204, 148, 113, 0.2);
        }
        
        .admin-column-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        /* 确保所有按钮和链接没有下划线 */
        a, .admin-button, .admin-feature-card {
            text-decoration: none !important;
        }
        /* 未处理反馈提示（边缘变色）——不改变元素尺寸，使用外部阴影模拟边框 */
        .admin-feature-card.has-pending {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05), 0 0 0 2px #cc9471;
        }
    </style>
</head>
<body>
    <!-- 页面加载动画 -->
    <div class="page-loader">
        <div class="loader-spinner"></div>
    </div>

    <div class="admin-container">
        <h1 class="admin-title">管理功能列表</h1>
        
        <div class="admin-layout">
            <!-- 第一列 - 账号管理 -->
            <div class="admin-column">
                <h2 class="admin-column-title"></h2>
                <div class="admin-column-content">
                    <!-- 账号管理 -->
                    <a href="manage_accounts.php" class="admin-feature-card">
                        <div class="admin-feature-card-content">
                            <h3>账号管理</h3>
                            <p>管理系统账号、密码和权限</p>
                        </div>
                        <i class="fas fa-user-shield"></i>
                    </a>
                </div>
            </div>
            
            <!-- 第二列 - 布局管理、B站管理、发布管理、用户反馈 -->
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] !== 'viewer'): ?>
            <div class="admin-column">
                <h2 class="admin-column-title"></h2>
                <div class="admin-column-content">
                    <!-- 布局统一管理 -->
                    <a href="manage_layout_unified.php" class="admin-feature-card">
                        <div class="admin-feature-card-content">
                            <h3>布局管理</h3>
                            <p>页眉设置、背景图片、页脚内容统一管理</p>
                        </div>
                        <i class="fas fa-th-large"></i>
                    </a>
                    
                    <!-- B站统一管理 -->
                    <a href="manage_bilibili_unified.php" class="admin-feature-card">
                        <div class="admin-feature-card-content">
                            <h3>B站管理</h3>
                            <p>直播检测、用户信息、空间管理</p>
                        </div>
                        <i class="fab fa-bilibili"></i>
                    </a>
                    
                    <!-- 内容发布管理 -->
                    <a href="manage_releases_unified.php" class="admin-feature-card">
                        <div class="admin-feature-card-content">
                            <h3>发布管理</h3>
                            <p>管理网站公告和更新日志</p>
                        </div>
                        <i class="fas fa-bullhorn"></i>
                    </a>
                    
                    <!-- 用户反馈 -->
                    <a href="manage_feedback.php" class="admin-feature-card<?php echo $pendingFeedbackCount>0 ? ' has-pending' : ''; ?>">
                        <div class="admin-feature-card-content">
                            <h3>用户反馈</h3>
                            <p>管理用户提交的建议和反馈信息</p>
                        </div>
                        <i class="fas fa-comment-dots"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 第三列 - 内容管理、上传管理 -->
            <div class="admin-column">
                <h2 class="admin-column-title"></h2>
                <div class="admin-column-content">
                    <!-- 内容统一管理 -->
                    <a href="manage_content_unified.php" class="admin-feature-card">
                        <div class="admin-feature-card-content">
                            <h3>内容管理</h3>
                            <p>文件袋文本、个人描述、周表管理、衣装管理</p>
                        </div>
                        <i class="fas fa-file-alt"></i>
                    </a>
                    
                    <!-- 表情包和商品管理 -->
                    <a href="manage_shop_expressions_unified.php" class="admin-feature-card">
                        <div class="admin-feature-card-content">
                            <h3>上传管理</h3>
                            <p>管理表情包、语音素材和购物车商品</p>
                        </div>
                        <i class="fas fa-cloud-upload-alt"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- 退出按钮 -->
        <div class="admin-footer-buttons">
            <a href="../index.php" target="_blank" class="admin-button admin-button-primary">
                <i class="fas fa-external-link-alt"></i> 访问前台
            </a>
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin'): ?>
            <a href="http://8.134.238.238:3136/login" target="_blank" class="admin-button admin-button-secondary">
                <i class="fas fa-server"></i> 后台服务器
            </a>
            <?php endif; ?>
            <a href="logout.php" class="admin-button admin-button-secondary">
                <i class="fas fa-sign-out-alt"></i> 退出登录
            </a>
        </div>
    </div>

    <script>
        // 使用更高效的页面加载处理
        document.addEventListener('DOMContentLoaded', function() {
            const loader = document.querySelector('.page-loader');
            
            // 页面内容已加载，隐藏加载动画
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 300);
            }
        });
    </script>
</body>
</html> 