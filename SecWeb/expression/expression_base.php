<?php
// 保留最基本的PHP开头
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/database.php';

// 随机跳转逻辑
// 当用户直接访问expression_base.php时，随机跳转到表情、语音或视频页面
// 这样可以避免用户看到空白页面，同时增加访问的随机性和趣味性
// 检查是否是直接访问expression_base.php（而不是通过子页面包含）
if (basename($_SERVER['SCRIPT_NAME']) === 'expression_base.php' && !isset($content)) {
    // 定义可用的页面
    $available_pages = [
        'emotes' => '/SecWeb/expression/expression_emotes.php',
        'audios' => '/SecWeb/expression/expression_audios.php'
        // 未来可以添加视频页面
        // 'videos' => '/SecWeb/expression/expression_videos.php'
    ];
    
    // 如果视频页面存在，则添加到随机选择中
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/SecWeb/expression/expression_videos.php')) {
        $available_pages['videos'] = '/SecWeb/expression/expression_videos.php';
    }
    
    // 随机选择一个页面
    $random_page = array_rand($available_pages);
    $redirect_url = $available_pages[$random_page];
    
    // 执行跳转
    header('Location: ' . $redirect_url);
    exit;
}

// 设置更智能的缓存控制头
// 允许浏览器缓存页面，但每次访问前需要向服务器确认内容是否有更新
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
// 设置静态资源缓存时间
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT');

// 图片URL处理函数
function getBiliImageProxyUrl($imageUrl) {
    if (empty($imageUrl)) {
        return '/assets/images/default-avatar.png';
    }
    
    // 如果是本地图片，直接返回
    if (strpos($imageUrl, 'http') !== 0) {
        return $imageUrl;
    }
    
    // 如果是B站图片直链且为协议相对地址，补全为HTTPS
    if (strpos($imageUrl, 'bilibili.com') !== false || strpos($imageUrl, 'hdslb.com') !== false) {
        return strpos($imageUrl, '//') === 0 ? 'https:' . $imageUrl : $imageUrl;
    }
    
    // 其他图片直接返回原URL
    return $imageUrl;
}

// 获取数据库连接
$db = new Database();
$conn = $db->getConnection();

// 设置页面标题
$page_title = "日常";

// 获取主播头像
$avatar = '';
try {
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_avatar'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $avatar = $result ? $result['config_value'] : '';
    $avatar = getBiliImageProxyUrl($avatar);
} catch (PDOException $e) {
    $avatar = '/elements/logo.png'; // 出错时使用默认头像
}

// 如果头像为空，使用默认头像
if (empty($avatar)) {
    $avatar = '/elements/logo.png';
}

// 默认内容为空，将由各个组件填充
$content = isset($content) ? $content : '';
$active_tab = isset($active_tab) ? $active_tab : 'expressions';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="/assets/css/tailwind.min.css" rel="stylesheet">
    <!-- FontAwesome图标库 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- 添加Wavesurfer.js库用于音频波形显示 -->
    <script src="https://unpkg.com/wavesurfer.js@6/dist/wavesurfer.min.js"></script>
    
    <!-- 预加载其他页面以加速切换 -->
    <?php if ($active_tab == 'emotes'): ?>
    <link rel="prefetch" href="/SecWeb/expression/expression_audios.php">
    <?php elseif ($active_tab == 'audios'): ?>
    <link rel="prefetch" href="/SecWeb/expression/expression_emotes.php">
    <?php endif; ?>
    <style>
        @font-face {
            font-family: 'QiantuHouhei';
            src: url('/assets/webfonts/QIANTUHOUHEI.TTF') format('truetype');
            font-display: swap;
        }
        
        /* 全局设置千图后黑字体不加粗 */
        [style*="QiantuHouhei"], 
        [style*="QIANTUHOUHEI"], 
        [class*="tab-button"], 
        .header-text,
        *[class*="title"],
        *[class*="header"] h1, 
        *[class*="header"] h2, 
        *[class*="header"] h3,
        *[class*="header"] h4,
        *[class*="header"] h5,
        *[class*="header"] h6 {
            font-weight: normal !important;
        }
        
        /* 固定页眉样式 */
        .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            padding: 10px 20px;
            background: #303d4d;
            color: #ffffff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
        }
        
        .header-circle {
            width: 50px;
            height: 50px;
            margin-right: 15px;
            overflow: hidden;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .header-circle img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            display: block;
        }
        
        .header-text {
            font-size: 2.4rem;
            font-family: 'QiantuHouhei', sans-serif;
            letter-spacing: 5px;
        }
        
        .home-icon {
            margin-right: 6px;
            font-size: 1.3rem;
        }
        
        /* 内容区域调整，为固定头部留出空间 */
        .main-content {
            padding-top: 90px !important;
            height: calc(100vh - 90px); /* 减去头部高度 */
            display: flex;
            flex-direction: column;
        }
        
        :root {
            --color-primary: #4d4030;
            --color-accent: #cc9471;
            --color-secondary: #e8a274;
            --color-tertiary: #f2c9b5;
            --color-bg: #f9f3ee;
            --color-text: #333;
            --color-card: #ffffff;
            --color-border: rgba(204, 148, 113, 0.2);
            --color-shadow: rgba(0, 0, 0, 0.1);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --color-bg: #222;
                --color-text: #e0e0e0;
                --color-card: #333;
                --color-border: rgba(204, 148, 113, 0.4);
                --color-shadow: rgba(0, 0, 0, 0.4);
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'QiantuHouhei', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            line-height: 1.5;
            color: var(--color-text);
            background-color: var(--color-bg);
            padding: 0;
            margin: 0;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* 背景装饰 */
        .bg-decoration {
            position: fixed;
            pointer-events: none;
            z-index: -1;
        }
        
        .bg-decoration-1 {
            top: 10%;
            left: 5%;
            width: 250px;
            height: 250px;
            background-color: var(--color-tertiary);
            opacity: 0.3;
            border-radius: 50%;
            filter: blur(80px);
            animation: float 20s infinite alternate ease-in-out;
        }
        
        .bg-decoration-2 {
            bottom: 10%;
            right: 5%;
            width: 300px;
            height: 300px;
            background-color: var(--color-accent);
            opacity: 0.2;
            border-radius: 50%;
            filter: blur(100px);
            animation: float 15s infinite alternate-reverse ease-in-out;
        }
        
        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(30px, 30px) rotate(10deg); }
        }

        .container {
            width: 90vw;
            max-width: 1600px;
            min-width: 320px;
            margin: 0 auto;
            padding: 0;
        }

        /* 页眉样式 */
        .header {
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .title {
            display: inline-block;
            font-size: 2rem;
            color: var(--color-primary);
            font-family: 'QiantuHouhei', sans-serif;
            font-weight: 700;
            letter-spacing: 2px;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, var(--color-primary), transparent);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: var(--color-accent);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--color-primary);
            transform: translateX(-5px);
        }

        .back-link:before {
            content: '←';
            margin-right: 0.5rem;
            font-size: 1.2rem;
        }

        /* 主要内容区样式 */
        .main-content {
            padding: 2rem 0 4rem;
            min-height: calc(100vh - 200px);
        }

        /* 标签切换 */
        .tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            position: relative;
            z-index: 10;
        }

        .tab-button {
            padding: 0.75rem 2rem;
            background: transparent;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-family: 'QiantuHouhei', sans-serif;
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--color-text);
            opacity: 0.6;
            transition: transform 0.15s ease, opacity 0.15s ease; /* 优化过渡效果 */
            position: relative;
            overflow: hidden;
            text-decoration: none;
            will-change: transform, opacity; /* 提示浏览器优化变换 */
        }

        .tab-button:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--color-accent);
            opacity: 0;
            z-index: -1;
            transform: translateY(100%);
            transition: transform 0.15s ease, opacity 0.15s ease; /* 优化过渡效果 */
            will-change: transform, opacity; /* 提示浏览器优化变换 */
        }

        .tab-button.active {
            color: white;
            opacity: 1;
            transform: scale(1.02);
        }

        .tab-button.active:before {
            opacity: 1;
            transform: translateY(0);
        }

        .tab-button:hover {
            opacity: 1;
            transform: scale(1.01);
        }
        
        /* 简单的渐变效果 - 优化性能 */
        .tab-button {
            background: linear-gradient(45deg, transparent, rgba(204, 148, 113, 0.05), transparent);
            background-size: 200% 200%;
            background-position: -100% -100%;
            transition: transform 0.15s ease, opacity 0.15s ease, background-position 0.2s ease;
        }
        
        .tab-button.active {
            background-position: 100% 100%;
        }
        
        /* 页脚样式 */
        .footer {
            padding: 2rem 0;
            text-align: center;
            color: var(--color-text);
            opacity: 0.7;
            font-size: 0.9rem;
        }

        /* 响应式设计 */
        
        /* 炉石风格匹配动画 */
        .hearthstone-match {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .hearthstone-match.active {
            opacity: 1;
            pointer-events: auto;
        }
        
        .match-spinner {
            width: 120px;
            height: 120px;
            border: 5px solid rgba(204, 148, 113, 0.3);
            border-top-color: var(--color-accent);
            border-radius: 50%;
            animation: spin 1.5s linear infinite;
            margin-bottom: 20px;
        }
        
        .match-text {
            color: white;
            font-size: 1.5rem;
            font-family: 'QiantuHouhei', sans-serif;
            text-align: center;
            margin-top: 20px;
        }
        
        .match-versus {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 80%;
            max-width: 600px;
            margin-top: 30px;
        }
        
        .match-card {
            background-color: rgba(204, 148, 113, 0.2);
            border-radius: 10px;
            padding: 15px;
            width: 200px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .match-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: shine 2s infinite;
        }
        
        .match-vs {
            font-size: 2rem;
            font-weight: bold;
            color: var(--color-accent);
            margin: 0 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes shine {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        /* 子页面可能需要的共享样式这里定义 */
        
        /* 模态窗口样式 */
        .custom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal-content {
            background-color: white;
            padding: 25px 30px;
            border-radius: 10px;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
            border: 2px solid rgb(204, 148, 113);
        }
        
        .modal-message {
            font-size: 1.2rem;
            color: #4d4030;
            font-family: 'QiantuHouhei', sans-serif;
            letter-spacing: 1px;
        }
        
        .custom-modal.show {
            opacity: 1;
        }
        
        .custom-modal.show .modal-content {
            transform: translateY(0);
        }
        
    </style>
    
    <!-- 子页面可能需要的额外样式 -->
    <?php if (isset($additional_styles)) echo $additional_styles; ?>
</head>
<body>
    <!-- 背景装饰 -->
    <div class="bg-decoration bg-decoration-1"></div>
    <div class="bg-decoration bg-decoration-2"></div>

    <!-- 固定页眉 -->
    <header class="fixed-header">
        <div class="header-circle">
            <img src="<?php echo $avatar; ?>" alt="Nagisa" referrerpolicy="no-referrer">
        </div>
        <span class="header-text">日常</span>

    </header>

    <div class="container">
        <!-- 主要内容 -->
        <main class="main-content">
            <!-- 标签切换 -->
            <div class="tabs">
                <a href="/SecWeb/expression/expression_emotes.php" class="tab-button <?php echo $active_tab == 'emotes' ? 'active' : ''; ?>" data-tab="emotes" data-href="/SecWeb/expression/expression_emotes.php">表 情</a>
                <a href="/SecWeb/expression/expression_audios.php" class="tab-button <?php echo $active_tab == 'audios' ? 'active' : ''; ?>" data-tab="audios" data-href="/SecWeb/expression/expression_audios.php">语 音</a>
            </div>

            <!-- 内容区域 -->
            <?php echo $content; ?>
        </main>

        <!-- 页脚 -->
        <footer class="footer">
            <p></p>
        </footer>
    </div>
    
    <!-- 子页面可能需要的额外脚本 -->
    <?php if (isset($additional_scripts)) echo $additional_scripts; ?>
    
    <!-- 自定义模态窗口 -->
    <div id="customModal" class="custom-modal">
        <div class="modal-content">
            <div class="modal-message" id="modalMessage"></div>
        </div>
    </div>

    <script>
    // 显示自定义模态窗口的函数
    function showCustomModal(message, duration = 1500) {
        // 防止重复调用
        if (window.modalShowing) return;
        
        window.modalShowing = true;
        
        const modal = document.getElementById("customModal");
        document.getElementById("modalMessage").innerText = message;
        
        // 显示模态窗口
        modal.style.display = "flex";
        
        // 使用setTimeout让CSS过渡效果生效
        setTimeout(() => {
            modal.classList.add("show");
        }, 10);
        
        // 设置自动关闭
        setTimeout(() => {
            closeModal();
        }, duration);
        
        return false; // 确保返回false以阻止默认行为
    }
    
    // 关闭模态窗口
    function closeModal() {
        const modal = document.getElementById("customModal");
        modal.classList.remove("show");
        
        // 等待过渡效果完成后隐藏模态窗口
        setTimeout(() => {
            modal.style.display = "none";
            // 重置模态窗口状态
            window.modalShowing = false;
        }, 300);
    }
    
    // 标签切换渐变效果 - 优化性能
    document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.tab-button');
        
        // 预加载其他页面内容
        function preloadPages() {
            tabButtons.forEach(button => {
                const href = button.getAttribute('data-href');
                if (href && href !== '#' && !button.classList.contains('active')) {
                    const link = document.createElement('link');
                    link.rel = 'prefetch';
                    link.href = href;
                    document.head.appendChild(link);
                }
            });
        }
        
        // 页面加载后预加载其他页面
        setTimeout(preloadPages, 1000);
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                // 如果点击的是当前激活的标签，阻止默认行为
                if (this.classList.contains('active')) {
                    e.preventDefault();
                    return;
                }
                
                // 如果点击的是模态窗口链接，不执行后续代码
                if (this.getAttribute('onclick') && this.getAttribute('onclick').includes('showCustomModal')) {
                    return; // 直接返回，让onclick事件处理
                }
                
                // 阻止默认的链接跳转
                e.preventDefault();
                
                // 立即跳转，不添加延迟
                const targetUrl = this.getAttribute('data-href');
                if (targetUrl && targetUrl !== '#') {
                    // 添加当前标签的激活状态，提供即时反馈
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    // 立即跳转
                    window.location.href = targetUrl;
                }
            });
            
            // 简化的悬停效果
            button.addEventListener('mouseenter', function() {
                if (!this.classList.contains('active')) {
                    this.style.transform = 'scale(1.01)';
                }
            });
            
            button.addEventListener('mouseleave', function() {
                if (!this.classList.contains('active')) {
                    this.style.transform = 'scale(1)';
                }
            });
        });
    });
    </script>
</body>
</html> 