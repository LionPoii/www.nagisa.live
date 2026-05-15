<?php
// 设置安全响应头
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// 设置缓存控制头 - 修改为允许缓存
header('Cache-Control: public, max-age=86400'); // 缓存1天
header('Pragma: public');

// 定义系统标记
define('IN_SYSTEM', true);

require_once 'includes/database.php';
$db = new Database();
$conn = $db->getConnection();

// 检查数据库连接是否成功
if (!$conn) {
    die("数据库连接失败，请检查数据库配置或联系管理员。");
}

// 获取section背景图片
$stmt = $conn->prepare("SELECT section_id, background_image FROM section_backgrounds WHERE section_id IN (1, 2, 3)");
$stmt->execute();
$backgrounds = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $backgrounds[$row['section_id']] = $row['background_image'];
}

// 预加载schedule数据
$stmt = $conn->prepare("SELECT * FROM schedule_image ORDER BY id DESC LIMIT 1");
$stmt->execute();
$schedule_image = $stmt->fetch(PDO::FETCH_ASSOC);
$schedule_image_path = $schedule_image['image_path'] ?? '';

// 预加载header组件所需的数据，而不是直接引入
$stmt = $conn->prepare("SELECT header_text, header_image, header_style FROM header_settings WHERE id = 1");
$stmt->execute();
$header = $stmt->fetch(PDO::FETCH_ASSOC);
$header_text = $header['header_text'] ?? 'Nagisa Live';
$header_image = $header['header_image'] ?? '';
$style = json_decode($header['header_style'] ?? '{}', true);
$default_style = [
    'background_color' => 'rgba(0, 0, 0, 0.8)',
    'text_color' => '#ffffff',
    'border_color' => 'rgba(255, 255, 255, 0.1)',
    'shadow_color' => 'rgba(0, 0, 0, 0.3)',
    'text_size' => '1.2',
    'image_size' => '50'
];
$style = array_merge($default_style, $style);

// 现在开始输出HTML内容
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>古利高校推理社</title>
    <link rel="icon" href="/Webicon/Nagisa-fans.ico" type="image/x-icon">
    <link rel="shortcut icon" href="/Webicon/Nagisa-fans.ico" type="image/x-icon">
    <link href="assets/css/tailwind.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/header.css" rel="stylesheet">
    <link href="assets/css/splash.css" rel="stylesheet">
    <link href="assets/css/fanart.css" rel="stylesheet">
    
    <!-- 异步字体加载，不阻塞页面显示 -->
    <script>
        // 记录页面加载开始时间
        window.pageLoadStartTime = Date.now();
        
        // 异步加载自定义字体，不阻塞页面渲染
        function loadCustomFonts() {
            const fontFace = new FontFace('QiantuHouhei', 'url(/assets/webfonts/QIANTUHOUHEI.TTF)');
            
            // 添加字体加载超时
            const fontTimeout = setTimeout(() => {
                console.log('字体加载超时，使用系统字体');
                // 即使字体加载失败也添加fonts-loaded类，确保UI一致性
                document.body.classList.add('fonts-loaded');
            }, 2000); // 2秒超时
            
            fontFace.load().then(function(loadedFace) {
                clearTimeout(fontTimeout); // 清除超时
                document.fonts.add(loadedFace);
                // 字体加载完成后，逐步应用到页面元素
                document.body.classList.add('fonts-loaded');
            }).catch(function(error) {
                clearTimeout(fontTimeout); // 清除超时
                console.log('字体加载失败，使用系统字体:', error);
                // 即使字体加载失败也添加fonts-loaded类，确保UI一致性
                document.body.classList.add('fonts-loaded');
            });
        }
        
        // 页面加载完成后异步加载字体
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadCustomFonts);
        } else {
            loadCustomFonts();
        }
    </script>
    
    <!-- 添加自动刷新脚本 -->
    <script>
        // 自动刷新功能
        function setupAutoRefresh() {
            // 设置刷新间隔为1小时（单位：毫秒）
            const refreshInterval = 1 * 60 * 60 * 1000;
            
            // 保存当前滚动位置和视图状态
            function savePageState() {
                const currentSection = window.currentSection || 0;
                localStorage.setItem('nagisa_current_section', currentSection);
                localStorage.setItem('nagisa_last_refresh', Date.now());
            }
            
            // 检查是否需要刷新
            function checkRefresh() {
                // 首先检查直播状态
                checkLiveStatus().then(isLiving => {
                    // 如果正在直播，不进行自动刷新
                    if (isLiving) {
                        console.log('检测到正在直播中，暂停自动刷新');
                        return;
                    }
                    
                    // 检查上次直播状态，如果从直播状态变为非直播状态，立即刷新一次
                    const wasLiving = localStorage.getItem('nagisa_is_living') === 'true';
                    if (wasLiving) {
                        console.log('检测到直播已结束，执行一次刷新');
                        localStorage.setItem('nagisa_is_living', 'false');
                        // 保存当前状态
                        savePageState();
                        // 刷新页面，保留URL参数
                        window.location.href = window.location.href;
                        return;
                    }
                    
                    // 正常的定时刷新检查
                    const lastRefresh = localStorage.getItem('nagisa_last_refresh');
                    if (!lastRefresh || (Date.now() - lastRefresh) > refreshInterval) {
                        // 保存当前状态
                        savePageState();
                        // 刷新页面，保留URL参数
                        window.location.href = window.location.href;
                    }
                }).catch(error => {
                    console.error('检查直播状态时出错:', error);
                    // 出错时执行正常的定时刷新检查，不中断刷新机制
                    const lastRefresh = localStorage.getItem('nagisa_last_refresh');
                    if (!lastRefresh || (Date.now() - lastRefresh) > refreshInterval) {
                        savePageState();
                        window.location.href = window.location.href;
                    }
                });
            }
            
            // 检查直播状态的函数
            async function checkLiveStatus() {
                try {
                    // 添加超时处理
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 5000); // 5秒超时
                    
                    // 使用fetch API请求直播状态，使用现有的API端点
                    const response = await fetch('/api/check_live_status.php?_=' + new Date().getTime(), {
                        signal: controller.signal
                    });
                    
                    clearTimeout(timeoutId); // 清除超时
                    
                    if (!response.ok) {
                        throw new Error(`网络请求失败: ${response.status} ${response.statusText}`);
                    }
                    
                    const data = await response.json();
                    
                    // 检查API返回的数据格式是否正确
                    if (!data || typeof data !== 'object') {
                        throw new Error('API返回数据格式错误');
                    }
                    
                    // 更新本地存储中的直播状态
                    const isLiving = data.is_living === true;
                    localStorage.setItem('nagisa_is_living', isLiving.toString());
                    
                    return isLiving;
                } catch (error) {
                    console.error('获取直播状态失败:', error);
                    // 如果请求失败，返回false以允许正常刷新
                    return false;
                }
            }
            
            // 设置定期检查是否需要刷新
            setInterval(checkRefresh, 60000); // 每分钟检查一次
            
            // 页面载入时立即检查一次直播状态
            checkLiveStatus().then(isLiving => {
                console.log('初始直播状态检查:', isLiving ? '直播中' : '未直播');
            });
            
            // 页面载入时恢复状态
            window.addEventListener('load', function() {
                const savedSection = localStorage.getItem('nagisa_current_section');
                if (savedSection !== null && typeof scrollToSection === 'function') {
                    setTimeout(() => {
                        scrollToSection(parseInt(savedSection));
                    }, 1500);
                }
            });
        }
        
        // DOM加载完成后初始化自动刷新功能
        document.addEventListener('DOMContentLoaded', setupAutoRefresh);
    </script>
    
    <script>
        // 自动刷新：每次新开标签页/窗口时刷新一次
        if (!sessionStorage.getItem('reloaded')) {
            sessionStorage.setItem('reloaded', 'true');
            // 使用当前URL刷新，保留参数
            window.location.href = window.location.href;
        }
    </script>
    
    <script>
        // 判断是否新标签页/窗口进入
        if (!sessionStorage.getItem('visited')) {
            sessionStorage.setItem('visited', 'true');
            // 新标签页/窗口，强制回到第一页
            localStorage.setItem('nagisa_current_section', 0);
        }
    </script>
    
    <style>
        .board1-container:hover .board1-text, 
        .board2-container:hover .board2-text, 
        .board3-container:hover .board3-text {
            color: #cc9471 !important; /* 鼠标悬停时文字变为粉色 */
        }
        
        /* 添加clutter按钮悬停效果 */
        .clutter-container:hover {
            transform: scale(1.05);
        }
        
        .clutter-container:hover .clutter-text {
            color: #cc9471 !important;
        }
        
        .clutter-container:hover .clutter-image {
            transform: translateY(-10px);
        }
        
        /* 添加浮动动画 */
        .clutter-container {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
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
            border: 2px solid rgb(204, 148, 113); /* 添加边框颜色 */
        }
        
        .modal-message {
            font-size: 1.2rem;
            color: #4d4030; /* 与页脚颜色匹配 */
            font-family: 'QiantuHouhei', sans-serif; /* 使用网站字体 */
            letter-spacing: 1px;
        }
        
        .custom-modal.show {
            opacity: 1;
        }
        
        .custom-modal.show .modal-content {
            transform: translateY(0);
        }
        
        /* 所有Board emoji动画样式 */
        .board1-emoji,
        .board2-emoji,
        .board3-emoji {
            position: absolute;
            left: 50%;
            top: 62.5%; /* 初始位置在文字附近 */
            transform: translateX(-50%);
            opacity: 0;
            height: 0;
            transition: all 0.3s ease;
            pointer-events: none; /* 确保图片不会影响鼠标事件 */
            z-index: 10; /* 确保emoji在文字上方 */
        }
        
        .board1-container:hover .board1-emoji,
        .board2-container:hover .board2-emoji,
        .board3-container:hover .board3-emoji {
            opacity: 1;
            top: 0%; /* 减少向上移动的距离 */
            height: 50%; /* 缩小图片大小 */
        }
    </style>
</head>
<body>
    <!-- 全屏Loading遮罩 -->
    <div id="global-loading" style="position:fixed;z-index:9999;top:0;left:0;width:100vw;height:100vh;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;transition:opacity 0.5s ease;">
      <div class="loader-spinner" style="width:48px;height:48px;border:5px solid #eee;border-top:5px solid #ECA97B;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:20px;"></div>
      
      <!-- 进度条容器 -->
      <div class="progress-container" style="width:300px;max-width:80vw;margin-bottom:15px;">
        <div class="progress-bar" id="progress-bar" style="width:0%;height:6px;background:linear-gradient(90deg,#ECA97B,#cc9471);border-radius:3px;transition:width 0.3s ease;box-shadow:0 2px 8px rgba(236,169,123,0.3);"></div>
      </div>
      
      <!-- 进度文本 -->
      <div class="progress-text" id="progress-text" style="font-family:'Microsoft YaHei','PingFang SC','Helvetica Neue',sans-serif;color:#4d4030;font-size:16px;text-align:center;letter-spacing:1px;">
        寻找钥匙中...
      </div>
      
      <!-- 加载状态文本 -->
      <div class="loading-status" id="loading-status" style="font-family:'Microsoft YaHei','PingFang SC','Helvetica Neue',sans-serif;color:#666;font-size:14px;text-align:center;margin-top:10px;opacity:0.8;">
        准备开门...
      </div>
    </div>
    <style>
        @keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
        
        /* 进度条动画效果 */
        .progress-bar {
            position: relative;
            overflow: hidden;
        }
        
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        /* 加载状态文本的淡入淡出效果 */
        .loading-status {
            transition: opacity 0.5s ease;
        }
        
        /* 进度条完成时的庆祝效果 */
        .progress-bar.completed {
            animation: celebrate 0.6s ease-in-out;
        }
        
        @keyframes celebrate {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* 加载完成时的淡出效果 */
        .global-loading-completed {
            animation: fadeOutUp 0.8s ease-out forwards;
        }
        
        @keyframes fadeOutUp {
            0% { 
                opacity: 1;
                transform: translateY(0);
            }
            100% { 
                opacity: 0;
                transform: translateY(-50px);
            }
        }
        
        /* 字体加载完成后的平滑过渡 */
        .fonts-loaded .progress-text,
        .fonts-loaded .loading-status {
            font-family: 'QiantuHouhei', 'Microsoft YaHei', 'PingFang SC', 'Helvetica Neue', sans-serif;
            transition: font-family 0.3s ease;
        }
        
        /* 确保字体加载不影响页面显示 */
        @font-face {
            font-family: 'QiantuHouhei';
            src: url('/assets/webfonts/QIANTUHOUHEI.TTF') format('truetype');
            font-display: swap; /* 关键：不阻塞页面渲染 */
            font-weight: normal;
            font-style: normal;
            font-synthesis: none; /* 防止合成粗体/斜体 */
            text-rendering: optimizeSpeed; /* 优化速度 */
        }
    </style>
    <!-- 主内容容器，初始隐藏 -->
    <div id="main-content" style="display:block;opacity:0;transition:opacity 0.5s ease;">
        <!-- 固定页眉 -->
        <div class="fixed-header" style="
            background: <?php echo $style['background_color']; ?>;
            color: <?php echo $style['text_color']; ?>;
            border-bottom: 1px solid <?php echo $style['border_color']; ?>;
            box-shadow: 0 4px 30px <?php echo $style['shadow_color']; ?>;
        ">
            <div class="header-circle" style="width: <?php echo $style['image_size']; ?>px; height: <?php echo $style['image_size']; ?>px; margin-right: 15px;">
                <?php if ($header_image): ?>
                    <img src="<?php echo htmlspecialchars($header_image); ?>" alt="Header Image" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block;">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="header-text" style="font-size: <?php echo $style['text_size']; ?>rem; font-family: 'QiantuHouhei', sans-serif; letter-spacing: 5px;">
                <?php echo htmlspecialchars($header_text); ?>
            </div>
            <div style="flex-grow: 1;"></div>
            <?php require_once 'components/notification_button.php'; ?>
        </div>
    
    <!-- 首屏图片 -->
    <?php
    // 检查是否需要显示开幕遮罩
    $show_splash = true;
    if (isset($_GET['no_splash']) && $_GET['no_splash'] == '1') {
        $show_splash = false;
    }
    // 检查HTTP_REFERER是否来自SecWeb
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/SecWeb/') !== false) {
        $show_splash = false;
    }
    if ($show_splash):
    ?>
    <div class="splash-image" id="splashImage"></div>
    <?php endif; ?>
    
    <div class="scroll-container">
        <!-- Section 1 -->
        <div class="fullscreen-section" style="background-image: url('<?php echo isset($backgrounds[1]) ? htmlspecialchars($backgrounds[1]) : 'assets/uploads/backgrounds/default/default-bg-1.jpg'; ?>'); background-size:cover; background-position:center;">
            <!-- 添加置物架组件（包含B站直播状态和怀表收集计数器） -->
            <?php require_once 'components/section1_shelf.php'; ?>
            
            <!-- 添加吊架组件（包含衣柜和日常按钮） -->
            <?php require_once 'components/section1_hanger.php'; ?>
            
            <!-- 添加左侧跳转按钮 -->
            <?php require_once 'components/clutter_button.php'; ?>
            
            <!-- 添加购物车按钮 -->
            <?php require_once 'components/shopcar_button.php'; ?>
    
            <!-- 添加公告按钮 -->
            <?php require_once 'components/announce_button.php'; ?>
    

    <style>
        .expression-button:hover {
            background-color: #4d4030;
            transform: scale(1.28);
            box-shadow: 0 5px 15px #4d4030;
        }
        .expression-button:active {
            transform: scale(1.15);
            box-shadow: 0 2px 8px #4d4030;
        }
    </style>
            
            <div class="section-content container mx-auto px-4">
                <!-- 添加Filebag和Card信息组件 -->
                <div class="information-container" style="position: absolute; left: 50%; top: 55%; transform: translate(-50%, -50%); width: 70%; text-align: center;">
                    <?php require_once 'components/information.php'; ?>
                </div>

                 <!-- Board1图片代码 -->
                 <a href="#" onclick="showCustomModal('档案柜整理中，敬请期待！'); return false;" style="text-decoration: none;">
                 <div class="board1-container" 
                      style="position: absolute; 
                             right: 12%; 
                             top: 25%; 
                             height: 15vh; 
                             width: auto;
                             cursor: pointer;">
                    <img src="elements/links/emoji/link_emoji_1.png" 
                         alt="Emoji" 
                         class="board1-emoji" 
                         style="width: auto;">
                    <img src="elements/links/Board1.png" 
                         alt="Board" 
                         class="board1-image" 
                         style="height: 100%; 
                                width: auto;">
                    <div class="board1-text" style="position: absolute; 
                                                   top: 62.5%; 
                                                    left: 50%; 
                                                    transform: translate(-50%, -50%); 
                                                   color: #4c526b; 
                                                    text-align: center;
                                                    font-size: 4.5rem;
                                                    width: 90%;
                                                    letter-spacing: 0.2em;
                                                    user-select: none;
                                                    transition: color 0.3s ease;
                                                    font-family: 'KingHwaOldSongv3.0', 'QiantuHouhei', sans-serif;
                                                    font-weight: bold; /* 添加加粗效果 */
                                                    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);">
                         档案柜
                      </div>
                  </div>
                  </a>
                  
                  <!-- 添加Board2图片 -->
                  <a href="https://song.nagisa.live" target="_blank" style="text-decoration: none;">
                  <div class="board2-container" 
                        style="position: absolute; 
                               right: 11.5%; 
                               top: 45%; 
                               height: 15vh; 
                               width: auto;
                               cursor: pointer;">
                      <img src="elements/links/emoji/link_emoji_2.png" 
                           alt="Emoji" 
                           class="board2-emoji" 
                           style="width: auto;">
                      <img src="elements/links/Board2.png" 
                           alt="Board2" 
                           class="board2-image" 
                           style="height: 100%; 
                                  width: auto;">
                      <div class="board2-text" style="position: absolute; 
                                                    top: 62.5%; 
                                                     left: 50%; 
                                                     transform: translate(-50%, -50%); 
                                                    color: #4c526b; 
                                                     text-align: center;
                                                     font-size: 4.5rem;
                                                     width: 90%;
                                                     letter-spacing: 0.2em;
                                                     user-select: none;
                                                     transition: color 0.3s ease;
                                                     font-family: 'KingHwaOldSongv3.0', 'QiantuHouhei', sans-serif;
                                                     font-weight: bold; /* 添加加粗效果 */
                                                     text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);">
                         音乐箱
                      </div>
                  </div>
                  </a>
                  
                  <!-- 添加Board3图片 -->
                  <a href="#" onclick="showCustomModal('正在整理中，敬请期待！'); return false;" style="text-decoration: none;">
                  <div class="board3-container" 
                        style="position: absolute; 
                               right: 12%; 
                               top: 65%; 
                               height: 15vh; 
                               width: auto;
                               cursor: pointer;">
                      <img src="elements/links/emoji/link_emoji_3.png" 
                           alt="Emoji" 
                           class="board3-emoji" 
                           style="width: auto;">
                      <img src="elements/links/Board3.png" 
                           alt="Board3" 
                           class="board3-image" 
                           style="height: 100%; 
                                  width: auto;">
                      <div class="board3-text" style="position: absolute; 
                                                    top: 62.5%; 
                                                     left: 50%; 
                                                     transform: translate(-50%, -50%); 
                                                    color: #4c526b; 
                                                     text-align: center;
                                                     font-size: 4.5rem;
                                                     width: 90%;
                                                     letter-spacing: 0.2em;
                                                     user-select: none;
                                                     transition: color 0.3s ease;
                                                     font-family: 'KingHwaOldSongv3.0', 'QiantuHouhei', sans-serif;
                                                     font-weight: bold; /* 添加加粗效果 */
                                                     text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);">
                          (整理中)
                      </div>
                  </div>
                  </a>
            </div>
        </div>

            <!-- Section 2 -->
            <div class="fullscreen-section" style="background-image: url('<?php echo isset($backgrounds[2]) ? htmlspecialchars($backgrounds[2]) : 'assets/uploads/backgrounds/default/default-bg-2.jpg'; ?>'); background-size:cover; background-position:center;">
                <div class="section-content container mx-auto px-4">
                    <?php require_once 'components/schedule.php'; ?>
                    <?php 
                    // 检查动态功能是否启用
                    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_dynamic_enabled'");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $dynamic_enabled = $result ? $result['config_value'] : '1';
                    if ($dynamic_enabled == '1') {
                        require_once 'components/bilibili_dynamic.php';
                    }
                    ?>
                </div>
            </div>

            <!-- Section 3 - 引入Fanart线索墙组件 -->
            <div class="fullscreen-section" style="background-image: url('<?php echo isset($backgrounds[3]) ? htmlspecialchars($backgrounds[3]) : 'assets/uploads/backgrounds/default/default-bg-3.jpg'; ?>'); background-size:cover; background-position:center;">
                <div class="section-content container mx-auto px-4">
                    <?php require_once 'components/bilibili_spacevideos.php'; ?>
                </div>
                <div style="position: relative; width: 100%; height: 75vh; overflow: visible;">
                <?php require_once 'components/fanart_enhanced.php'; ?>
                </div>
            </div>
        </div>

        <!-- 导航点 -->
        <div class="nav-dots">
            <div class="nav-dot active" data-section="0"></div>
            <div class="nav-dot" data-section="1"></div>
            <div class="nav-dot" data-section="2"></div>
        </div>

        <!-- 添加页脚组件 -->
        <?php require_once 'components/footer.php'; ?>

        <script src="assets/js/splash.js"></script>
        <script>
            // 全局变量
            window.currentSection = 0;
            let isScrolling = false;

            // 更新导航点状态和页脚显示
            function updateDots() {
                const dots = document.querySelectorAll('.nav-dot');
                const footer = document.getElementById('site-footer');
                
                dots.forEach((dot, index) => {
                    dot.classList.toggle('active', index === window.currentSection);
                });
                
                // 仅当第3部分显示时显示页脚
                if (window.currentSection === 2) { // 第3部分索引为2
                    footer.style.opacity = '1';
                    footer.style.transform = 'translateY(0)';
                } else {
                    footer.style.opacity = '0';
                    footer.style.transform = 'translateY(100%)';
                }
            }

            // 字体大小与图片高度绑定函数
            function adjustBoardFontSize() {
                // 调整档案柜字体大小
                const board1Image = document.querySelector('.board1-image');
                const board1Text = document.querySelector('.board1-text');
                if (board1Image && board1Text) {
                    const height = board1Image.offsetHeight;
                    // 确保有最小值，防止字体消失
                    board1Text.style.fontSize = height > 0 ? `${Math.max(height * 0.2, 16)}px` : '16px';
                }
                
                // 调整音乐柜字体大小
                const board2Image = document.querySelector('.board2-image');
                const board2Text = document.querySelector('.board2-text');
                if (board2Image && board2Text) {
                    const height = board2Image.offsetHeight;
                    // 确保有最小值，防止字体消失
                    board2Text.style.fontSize = height > 0 ? `${Math.max(height * 0.2, 16)}px` : '16px';
                }
                
                // 调整整理中字体大小
                const board3Image = document.querySelector('.board3-image');
                const board3Text = document.querySelector('.board3-text');
                if (board3Image && board3Text) {
                    const height = board3Image.offsetHeight;
                    // 确保有最小值，防止字体消失
                    board3Text.style.fontSize = height > 0 ? `${Math.max(height * 0.2, 16)}px` : '16px';
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                const container = document.querySelector('.scroll-container');
                const sections = document.querySelectorAll('.fullscreen-section');
                const dots = document.querySelectorAll('.nav-dot');
                const footer = document.getElementById('site-footer');

                // 恢复section位置
                const savedSection = localStorage.getItem('nagisa_current_section');
                if (savedSection !== null && typeof scrollToSection === 'function') {
                    setTimeout(() => {
                        scrollToSection(parseInt(savedSection));
                    }, 100);
                }

                // 滚动到指定section - 修改为全局函数
                window.scrollToSection = function(index) {
                    if (index < 0 || index >= sections.length) return;
                    window.currentSection = index;
                    sections[index].scrollIntoView({ behavior: 'smooth' });
                    updateDots();
                    localStorage.setItem('nagisa_current_section', index);
                }

                // 处理鼠标滚轮事件
                container.addEventListener('wheel', function(e) {
                    e.preventDefault();
                    if (isScrolling) return;
                    
                    isScrolling = true;
                    if (e.deltaY > 0 && window.currentSection < sections.length - 1) {
                        scrollToSection(window.currentSection + 1);
                    } else if (e.deltaY < 0 && window.currentSection > 0) {
                        scrollToSection(window.currentSection - 1);
                    }
                    
                    setTimeout(() => {
                        isScrolling = false;
                    }, 1000);
                });

                // 处理导航点点击
                dots.forEach((dot, index) => {
                    dot.addEventListener('click', () => {
                        scrollToSection(index);
                    });
                });

                // 处理键盘事件
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'ArrowDown' && window.currentSection < sections.length - 1) {
                        scrollToSection(window.currentSection + 1);
                    } else if (e.key === 'ArrowUp' && window.currentSection > 0) {
                        scrollToSection(window.currentSection - 1);
                    }
                });

                // 处理触摸事件
                let touchStartY = 0;
                container.addEventListener('touchstart', function(e) {
                    touchStartY = e.touches[0].clientY;
                });

                container.addEventListener('touchend', function(e) {
                    const touchEndY = e.changedTouches[0].clientY;
                    const diff = touchStartY - touchEndY;

                    if (Math.abs(diff) > 50) {
                        if (diff > 0 && window.currentSection < sections.length - 1) {
                            scrollToSection(window.currentSection + 1);
                        } else if (diff < 0 && window.currentSection > 0) {
                            scrollToSection(window.currentSection - 1);
                        }
                    }
                });

                // 监听滚动事件，确保页脚状态正确
                container.addEventListener('scroll', function() {
                    if (isScrolling) return;

                    // 使用节流函数避免频繁触发
                    if (!container.scrollThrottle) {
                        container.scrollThrottle = setTimeout(() => {
                            // 查找当前可见section
                            let newSection = window.currentSection;
                            const windowCenter = window.innerHeight / 2;
                            
                            sections.forEach((section, index) => {
                                const rect = section.getBoundingClientRect();
                                if (rect.top <= windowCenter && rect.bottom >= windowCenter) {
                                    newSection = index;
                                }
                            });

                            if (newSection !== window.currentSection) {
                                window.currentSection = newSection;
                                updateDots(); // 更新导航点和页脚状态
                            }
                            
                            container.scrollThrottle = null;
                        }, 100);
                    }
                });

                // 初始化时更新一次状态
                updateDots();

                // 初始调整字体大小
                adjustBoardFontSize();
                
                // 监听窗口大小变化，重新调整字体大小
                window.addEventListener('resize', adjustBoardFontSize);
            });
        </script>
    </div>

    <!-- 自定义模态窗口 -->
    <div id="customModal" class="custom-modal">
        <div class="modal-content">
            <div class="modal-message" id="modalMessage"></div>
        </div>
    </div>

    <script>
    // 监听所有资源和关键数据加载完毕后再显示主内容
    window.addEventListener('load', function() {
        // 记录页面加载开始时间
        window.pageLoadStartTime = window.pageLoadStartTime || Date.now();
        
        // 确保图片加载完成后调整字体大小
        adjustBoardFontSize();
        
        // 添加延迟检查，确保在图片完全加载后再次调整字体大小
        setTimeout(adjustBoardFontSize, 500);
        setTimeout(adjustBoardFontSize, 1000);
        
        // 加载周表图片
        const scheduleDataElement = document.getElementById('schedule-image-data');
        if (scheduleDataElement) {
            const imagePath = scheduleDataElement.getAttribute('data-image');
            const scheduleOnBlackboard = document.getElementById('schedule-on-blackboard');
            
            if (imagePath && scheduleOnBlackboard) {
                const img = document.createElement('img');
                img.src = imagePath;
                img.alt = '';
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'contain';
                img.style.borderRadius = '5px';
                img.style.boxShadow = '0 0 15px #b7a1a3';
                
                scheduleOnBlackboard.innerHTML = '';
                scheduleOnBlackboard.appendChild(img);
                
                // 监听黑板大小变化，调整日程表图片大小
                const blackboardContainer = document.querySelector('.blackboard-container');
                const resizeObserver = new ResizeObserver(entries => {
                    for (let entry of entries) {
                        const { width, height } = entry.contentRect;
                        // 保持日程表图片大小与黑板容器成比例
                        scheduleOnBlackboard.style.width = `${width * 0.88}px`;
                        scheduleOnBlackboard.style.height = `${height * 0.90}px`;
                    }
                });
                
                // 开始观察黑板容器大小变化
                resizeObserver.observe(blackboardContainer);
            }
        }

        // 检查fanart线索墙和schedule图片是否加载完
        function checkReady() {
            // 检查fanart是否准备好
            let fanartReady = true;
            if (window.ClueWall && typeof window.ClueWall.refresh === 'function') {
                // 确保ClueWall已初始化
                fanartReady = window.ClueWall.isInitialized || true;
            }
            
            // 检查schedule图片是否加载完成
            const scheduleImg = document.querySelector('#schedule-on-blackboard img');
            const scheduleReady = !scheduleImg || scheduleImg.complete;
            
            // 检查背景图片是否加载完成
            let bgImagesReady = true;
            const sections = document.querySelectorAll('.fullscreen-section');
            sections.forEach(section => {
                if (section.style.backgroundImage && !section.dataset.bgLoaded) {
                    bgImagesReady = false;
                }
            });
            
            // 检查组件计算是否完成 - 如果超过500ms还未完成，则强制认为已完成
            const componentsReady = window.componentsCalculated || 
                                   (Date.now() - window.pageLoadStartTime > 500);
            
            // 注意：购物车组件的商品图片不包含在此检查中
            // 这些图片只在用户点击购物车时才加载，不影响页面初始加载速度
            
            // 注意：字体加载不包含在此检查中，字体会异步加载，不影响页面显示
            
            return fanartReady && scheduleReady && (bgImagesReady || Date.now() - window.pageLoadStartTime > 800) && componentsReady;
        }
        
        // 进度条管理
        let totalLoadingItems = 0;
        let loadedItems = 0;
        let currentLoadingPhase = 0;
        
        // 更新进度条
        function updateProgress(phase, item = null) {
            if (item) {
                loadedItems++;
            }
            
            const progress = Math.min((loadedItems / totalLoadingItems) * 100, 100);
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const loadingStatus = document.getElementById('loading-status');
 
            if (progressText) progressText.textContent = `正在加载页面... ${Math.round(progress)}%`;
            
            // 根据加载阶段更新状态文本
            const phaseTexts = [
                '准备中...',
                '加载背景图片...',
                '加载组件...',
                '计算布局...',
                '即将完成...'
            ];
            
            // 注意：字体加载不包含在进度条检查中，字体会异步加载
            // 初始遮罩不等待字体，页面会更快显示
            // 字体加载完全独立于页面加载流程，不会阻塞进度条
            
            if (loadingStatus && phase < phaseTexts.length) {
                loadingStatus.textContent = phaseTexts[phase];
            }
            
            currentLoadingPhase = phase;
        }
        
        // 预加载背景图片
        // 注意：这里只预加载核心背景图片，购物车等组件的图片使用懒加载
        const sections = document.querySelectorAll('.fullscreen-section');
        let loadingCount = sections.length;
        
        // 检查是否有需要加载的背景图片
        let hasBackgroundImages = false;
        sections.forEach(section => {
            if (section.style.backgroundImage && section.style.backgroundImage !== 'none') {
                hasBackgroundImages = true;
            }
        });
        
        // 如果没有背景图片需要加载，减少等待时间
        if (!hasBackgroundImages) {
            loadingCount = 0;
        }
        
        // 计算总加载项数
        totalLoadingItems = loadingCount + 3; // 背景图片 + 3个阶段
        
        sections.forEach(section => {
            if (section.style.backgroundImage) {
                const bgUrl = section.style.backgroundImage.match(/url\(['"]?(.*?)['"]?\)/);
                if (bgUrl && bgUrl[1]) {
                    const img = new Image();
                    img.onload = img.onerror = () => {
                        section.dataset.bgLoaded = 'true';
                        loadingCount--;
                        updateProgress(1, true); // 背景图片加载完成
                        if (loadingCount <= 0) {
                            updateProgress(2); // 进入组件加载阶段
                            calculateComponentPositions();
                        }
                    };
                    img.src = bgUrl[1];
                } else {
                    section.dataset.bgLoaded = 'true';
                    loadingCount--;
                    updateProgress(1, true);
                }
            } else {
                section.dataset.bgLoaded = 'true';
                loadingCount--;
                updateProgress(1, true);
            }
        });
        
        // 计算所有组件位置的函数
        function calculateComponentPositions() {
            updateProgress(3); // 进入计算布局阶段
            
            // 计算所有需要精确定位的组件位置
            
            // 如有B站动态组件，计算其位置
            if (typeof calculateDynamicPosition === 'function') {
                calculateDynamicPosition();
            }
            
            // 如有信息组件，计算其位置
            if (typeof calculateInformationPosition === 'function') {
                calculateInformationPosition();
            }
            
            // 如有直播状态组件，计算其位置
            if (typeof calculateLiveStatusPosition === 'function') {
                calculateLiveStatusPosition();
            }
            
            // 如有其他需要计算位置的组件，在此处添加
            
            // 标记组件位置计算完成
            window.componentsCalculated = true;
            
            updateProgress(4); // 即将完成阶段
            
            // 检查是否可以显示内容
            showContentWhenReady();
        }
        
        function showContentWhenReady() {
            // 添加强制超时，无论字体是否加载完成都显示内容
            const forceTimeout = setTimeout(() => {
                showContentNow();
            }, 800); // 最多等待800ms
            
            if (checkReady()) {
                clearTimeout(forceTimeout);
                showContentNow();
            } else {
                setTimeout(() => {
                    clearTimeout(forceTimeout); // 清除强制超时
                    showContentWhenReady();
                }, 100); // 减少检查间隔以更快响应
            }
        }
        
        function showContentNow() {
            // 确保进度条达到100%
            updateProgress(4, true);
            
            // 添加完成动画
            const globalLoading = document.getElementById('global-loading');
            const mainContent = document.getElementById('main-content');
            
            if (globalLoading) {
                // 添加完成动画类
                globalLoading.classList.add('global-loading-completed');
                globalLoading.style.pointerEvents = 'none'; // 确保即使遮罩可见也不阻止鼠标事件
            }
            
            if (mainContent) {
                mainContent.style.opacity = '1';
                // 确保主内容可以接收鼠标事件
                mainContent.style.visibility = 'visible';
                mainContent.style.pointerEvents = 'auto';
                
                // 内容显示后再次触发位置计算，确保所有组件位置正确
                setTimeout(() => {
                    if (typeof calculateDynamicPosition === 'function') {
                        calculateDynamicPosition();
                    }
                    if (typeof calculateInformationPosition === 'function') {
                        calculateInformationPosition();
                    }
                    if (typeof calculateLiveStatusPosition === 'function') {
                        calculateLiveStatusPosition();
                    }
                    if (typeof adjustBoardFontSize === 'function') {
                        adjustBoardFontSize();
                    }
                    // 如有其他需要重新计算的函数，在此处调用
                }, 100);
            }
            
            // 彻底移除加载遮罩
            setTimeout(() => {
                if (globalLoading) {
                    globalLoading.style.display = 'none';
                    // 如果有父元素，从DOM中移除
                    if (globalLoading.parentNode) {
                        globalLoading.parentNode.removeChild(globalLoading);
                    }
                }
                
                // 页面加载完成后，检查当前部分并显示页脚
                const currentScrollPos = window.scrollY;
                const sections = document.querySelectorAll('.fullscreen-section');
                const windowHeight = window.innerHeight;

                sections.forEach((section, index) => {
                    const rect = section.getBoundingClientRect();
                    if (rect.top <= windowHeight/2 && rect.bottom >= windowHeight/2) {
                        // 这是当前可见的部分
                        window.currentSection = index;
                        updateDots(); // 更新导航点和页脚状态
                    }
                });
            }, 500);
            
            // 清除超时定时器
            if (window.loadingTimeout) {
                clearTimeout(window.loadingTimeout);
            }
        }
        
        // 智能检测是否需要显示资源加载遮罩
        let needsResourceLoading = false;
        
        // 检查是否有需要加载的资源
        if (loadingCount > 0) {
            needsResourceLoading = true;
        }
        
        // 检查是否有需要计算的组件
        if (typeof calculateDynamicPosition === 'function' || 
            typeof calculateInformationPosition === 'function' || 
            typeof calculateLiveStatusPosition === 'function') {
            needsResourceLoading = true;
        }
        
        if (needsResourceLoading) {
            // 初始化进度条
            updateProgress(0);
            
            // 如果背景图片全部加载完成，开始计算组件位置
            if (loadingCount <= 0) {
                calculateComponentPositions();
            }
            
            // 最多等待1.5秒后显示内容（减少等待时间）
            window.loadingTimeout = setTimeout(() => {
                // 强制标记组件计算完成
                window.componentsCalculated = true;
                showContentNow();
            }, 800); // 从1500ms减少到800ms
        } else {
            // 如果不需要加载资源，直接显示内容
            setTimeout(() => {
                showContentNow();
            }, 100);
        }
    });
    </script>

    <script>
    // 显示自定义模态窗口的函数
    function showCustomModal(message, duration = 1500) {
        const modal = document.getElementById('customModal');
        document.getElementById('modalMessage').innerText = message;
        
        // 显示模态窗口
        modal.style.display = 'flex';
        
        // 使用setTimeout让CSS过渡效果生效
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
        
        // 设置自动关闭
        setTimeout(() => {
            closeModal();
        }, duration);
    }
    
    // 关闭模态窗口
    function closeModal() {
        const modal = document.getElementById('customModal');
        modal.classList.remove('show');
        
        // 等待过渡效果完成后隐藏模态窗口
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }
    </script>
    
    <!-- 设备适配样式 -->
    <style>
        /* 桌面设备适配样式 */
        body {
            /* 桌面端默认样式 */
        }
    </style>
</body>