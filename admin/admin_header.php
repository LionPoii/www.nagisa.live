<?php
/**
 * 管理后台统一页眉组件
 * 用法：在每个管理页面顶部包含此文件并传递页面标题参数
 * 示例：
 * $page_title = "页眉管理";
 * include 'admin_header.php';
 */

// 确保页面标题已设置
$page_title = $page_title ?? '管理后台';

// 设置缓存控制头
header('Cache-Control: private, max-age=3600');
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page_title); ?> - Nagisa Live</title>
    <!-- 引入Tailwind CSS本地文件，避免CDN超时 -->
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <!-- 预加载关键资源 -->
    <link rel="preload" href="../assets/css/admin-tailwind.min.css" as="style">
    <link rel="preload" href="../assets/css/admin-fontawesome.min.css" as="style">
    <link rel="preload" href="admin_style.css" as="style">
    
    <!-- 加载CSS -->
    <link href="../assets/css/admin-tailwind.min.css" rel="stylesheet">
    <link href="../assets/css/admin-fontawesome.min.css" rel="stylesheet">
    <link href="admin_style.css" rel="stylesheet">
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- 右上角弹窗提示样式 -->
    <style>
        .RightUp-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            pointer-events: none;
        }
        
        .RightUp-toast {
            background-color: rgba(33, 33, 33, 0.9);
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            font-size: 14px;
            max-width: 300px;
            min-width: 120px;
            opacity: 0;
            transform: translateX(30px);
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .RightUp-toast.show {
            opacity: 1;
            transform: translateX(0);
        }
        
        .RightUp-toast.success {
            background-color: rgba(36, 170, 111, 0.9);
        }
        
        .RightUp-toast.error {
            background-color: rgba(229, 77, 66, 0.9);
        }
        
        .RightUp-toast.info {
            background-color: rgba(64, 158, 255, 0.9);
        }
        
        .RightUp-toast.warning {
            background-color: rgba(230, 162, 60, 0.9);
        }
        
        .RightUp-toast-icon {
            margin-right: 8px;
            font-size: 16px;
            flex-shrink: 0;
        }
        
        .RightUp-toast-content {
            flex-grow: 1;
            word-break: break-word;
        }
        
        @keyframes RightUp-toast-in {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes RightUp-toast-out {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(30px);
            }
        }
    </style>
    
    <!-- 页面可能需要的额外样式 -->
    <?php if (isset($extra_styles)): ?>
    <style>
        <?php echo $extra_styles; ?>
    </style>
    <?php endif; ?>
</head>
<body>
    <!-- 右上角弹窗容器 -->
    <div id="RightUp-container" class="RightUp-container"></div>
    
    <!-- 页面加载动画 -->
    <div class="page-loader">
        <div class="loader-spinner"></div>
    </div>

    <!-- 顶部导航栏 -->
    <nav class="admin-nav">
        <div class="admin-nav-container">
            <div class="flex items-center">
                <a href="../index.php" target="_blank" class="admin-nav-link ml-4">
                    <i class="fas fa-external-link-alt"></i>
                    预览网站
                </a>
            </div>
            <div>
                <a href="index.php" class="admin-nav-link mr-4">
                    <i class="fas fa-arrow-left"></i>
                    返回管理面板
                </a>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <h1 class="admin-title"><?php echo htmlspecialchars($page_title); ?></h1>
        
        <?php if (isset($_SESSION['toast_message'])): ?>
        <div class="admin-alert admin-alert-<?php echo $_SESSION['toast_type'] ?? 'success'; ?>">
            <i class="fas fa-<?php echo $_SESSION['toast_type'] === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
            <?php echo htmlspecialchars($_SESSION['toast_message']); ?>
        </div>
        <?php 
            // 清除提示消息，避免重复显示
            unset($_SESSION['toast_message']);
            unset($_SESSION['toast_type']);
        endif; 
        ?>
        
    <script>
        // 使用更高效的页面加载处理
        document.addEventListener('DOMContentLoaded', function() {
            const loader = document.querySelector('.page-loader');
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 300);
            }
            
            // 延迟加载图片和其他媒体资源
            setTimeout(() => {
                const lazyImages = document.querySelectorAll('img[data-src]');
                lazyImages.forEach(img => {
                    img.setAttribute('src', img.getAttribute('data-src'));
                    img.removeAttribute('data-src');
                });
                
                const lazyAudios = document.querySelectorAll('audio[data-src]');
                lazyAudios.forEach(audio => {
                    audio.setAttribute('src', audio.getAttribute('data-src'));
                    audio.removeAttribute('data-src');
                });
                
                const lazyVideos = document.querySelectorAll('video[data-src]');
                lazyVideos.forEach(video => {
                    video.setAttribute('src', video.getAttribute('data-src'));
                    video.removeAttribute('data-src');
                });
                
                const lazyIframes = document.querySelectorAll('iframe[data-src]');
                lazyIframes.forEach(iframe => {
                    iframe.setAttribute('src', iframe.getAttribute('data-src'));
                    iframe.removeAttribute('data-src');
                });
            }, 500);
        });
        
        // 右上角弹窗提示函数
        function showRightUpToast(message, type = 'success', duration = 3000) {
            // 获取或创建弹窗容器
            let container = document.getElementById('RightUp-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'RightUp-container';
                container.className = 'RightUp-container';
                document.body.appendChild(container);
            }
            
            // 创建弹窗元素
            const toast = document.createElement('div');
            toast.className = `RightUp-toast ${type}`;
            
            // 设置图标
            let iconClass = 'check-circle';
            if (type === 'error') iconClass = 'times-circle';
            else if (type === 'info') iconClass = 'info-circle';
            else if (type === 'warning') iconClass = 'exclamation-triangle';
            
            // 设置内容
            toast.innerHTML = `
                <div class="RightUp-toast-icon">
                    <i class="fas fa-${iconClass}"></i>
                </div>
                <div class="RightUp-toast-content">${message}</div>
            `;
            
            // 添加到容器
            container.appendChild(toast);
            
            // 显示弹窗
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // 自动关闭
            setTimeout(() => {
                toast.style.animation = 'RightUp-toast-out 0.3s forwards';
                setTimeout(() => {
                    if (container.contains(toast)) {
                        container.removeChild(toast);
                    }
                }, 300);
            }, duration);
            
            return toast;
        }
    </script>
        <!-- 在此处继续页面内容 -->
    </div>
</body>
</html> 