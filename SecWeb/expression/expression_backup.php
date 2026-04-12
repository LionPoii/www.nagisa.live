<?php
// 保留最基本的PHP开头
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/database.php';

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

// 从数据库获取表情包和音频数据
$expressions = [];
$audios = [];

try {
    // 检查表情包表是否存在
    $stmt = $conn->query("SHOW TABLES LIKE 'expression_images'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // 获取所有表情包
        $stmt = $conn->prepare("SELECT * FROM expression_images WHERE status = 1");
        $stmt->execute();
        $expressions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 检查音频表是否存在
    $stmt = $conn->query("SHOW TABLES LIKE 'expression_audios'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // 获取所有音频
        $stmt = $conn->prepare("SELECT * FROM expression_audios WHERE status = 1");
        $stmt->execute();
        $audios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // 出错时不显示错误，只在日志中记录
    error_log("表情页面数据库错误: " . $e->getMessage());
}

// 获取可用的表情包分类
$expression_categories = [];
foreach ($expressions as $expression) {
    if (!empty($expression['category']) && !in_array($expression['category'], $expression_categories)) {
        $expression_categories[] = $expression['category'];
    }
}

// 获取可用的音频分类
$audio_categories = [];
foreach ($audios as $audio) {
    if (!empty($audio['category']) && !in_array($audio['category'], $audio_categories)) {
        $audio_categories[] = $audio['category'];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'QiantuHouhei';
            src: url('/assets/webfonts/QIANTUHOUHEI.TTF') format('truetype');
            font-display: swap;
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
        
        @media (max-width: 768px) {
            .header-text {
                font-size: 1.5rem;
                letter-spacing: 3px;
            }
        }
        
        /* 内容区域调整，为固定头部留出空间 */
        .main-content {
            padding-top: 90px !important;
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
            width: 75vw;
            max-width: 1200px;
            min-width: 320px;
            margin: 0 auto;
            padding: 0 20px;
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
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--color-text);
            opacity: 0.6;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
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
            transition: all 0.3s ease;
        }

        .tab-button.active {
            color: white;
            opacity: 1;
        }

        .tab-button.active:before {
            opacity: 1;
            transform: translateY(0);
        }

        .tab-button:hover {
            opacity: 1;
        }

        /* 内容区域 */
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease forwards;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* 卡片网格 */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        /* 过滤器 */
        .filters {
            margin-bottom: 2rem;
            text-align: left;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .filter-right {
            margin-left: auto;
        }
        
        .filter-label {
            display: inline-block;
            margin-right: 0.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--color-primary);
        }

        .filter-container {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: flex-start;
            margin: 0.5rem 0;
        }

        .filter-button {
            padding: 0.4rem 1rem;
            background-color: transparent;
            border: 1px solid var(--color-border);
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .filter-button:hover {
            background-color: rgba(204, 148, 113, 0.1);
        }

        .filter-button.active {
            background-color: var(--color-accent);
            color: white;
            border-color: var(--color-accent);
        }

        /* 表情卡片 */
        .expression-card {
            perspective: 1000px;
            height: 200px;
            cursor: pointer;
            position: relative;
        }

        .expression-card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform 0.8s;
            transform-style: preserve-3d;
            box-shadow: 0 4px 15px var(--color-shadow);
            border-radius: 15px;
        }

        .expression-card:hover .expression-card-inner {
            transform: rotateY(180deg);
        }

        .expression-card-front, .expression-card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            -webkit-backface-visibility: hidden; /* Safari */
            backface-visibility: hidden;
            border-radius: 15px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        
        .expression-card-back {
            padding: 0.5rem;
        }

        .expression-card-front {
            background-color: var(--color-card);
        }

        .expression-card-back {
            background-color: var(--color-primary);
            color: white;
            transform: rotateY(180deg);
            transition: background-color 0.3s ease;
        }
        


        .expression-image {
            max-width: 100%;
            max-height: 95%;
            object-fit: contain;
        }

        .expression-title {
            margin-top: 1rem;
            text-align: center;
            font-weight: 500;
            font-family: 'QiantuHouhei', sans-serif;
        }

        .use-button {
            margin-top: 1rem;
            padding: 0.5rem 1.5rem;
            background-color: var(--color-accent);
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-family: 'QiantuHouhei', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .use-button:hover {
            background-color: var(--color-secondary);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(204, 148, 113, 0.4);
        }

        /* 音频卡片 */
        .audio-card {
            background-color: var(--color-card);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px var(--color-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .audio-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px var(--color-shadow);
        }

        .audio-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background-color: var(--color-accent);
        }

        .audio-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            font-family: 'QiantuHouhei', sans-serif;
            color: var(--color-primary);
        }

        .audio-player {
            width: 100%;
            margin-bottom: 1rem;
            border-radius: 30px;
            overflow: hidden;
        }

        .audio-category {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background-color: rgba(204, 148, 113, 0.1);
            color: var(--color-primary);
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: auto;
        }

        /* 大展示区 */
        .showcase {
            background-color: var(--color-card);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px var(--color-shadow);
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .showcase::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--color-primary), var(--color-accent));
        }

        .showcase-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--color-primary);
            font-family: 'QiantuHouhei', sans-serif;
        }

        .showcase-content {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 300px;
            margin-bottom: 1.5rem;
        }

        .showcase-image {
            max-width: 90%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 5px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.5s ease;
        }

        .showcase-image.animate {
            animation: pulse 1s;
        }

        .showcase-audio-player {
            width: 80%;
            max-width: 500px;
            margin: 0 auto 1.5rem;
        }

        .showcase-info {
            font-size: 1.2rem;
            color: var(--color-accent);
            font-weight: 500;
            margin-bottom: 1.5rem;
            font-family: 'QiantuHouhei', sans-serif;
        }

        .showcase-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .showcase-button {
            padding: 0.75rem 2rem;
            background-color: var(--color-primary);
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-family: 'QiantuHouhei', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        /* 过滤区域中的按钮样式调整 */
        .filter-right .showcase-button {
            padding: 0.5rem;
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .showcase-button:hover {
            background-color: var(--color-accent);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(204, 148, 113, 0.4);
        }

        .showcase-button::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: -100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.6s;
        }

        .showcase-button:hover::after {
            left: 100%;
        }
        
        /* 刷新按钮样式 */
        .refresh-icon {
            width: 30px;
            height: 30px;
            transition: transform 0.3s ease;
        }
        
        .showcase-button:hover .refresh-icon {
            transform: scale(1.2);
        }
        
        .showcase-button.rotating .refresh-icon {
            animation: rotate360 1s linear;
        }

        /* 动画效果 */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes rotate360 {
            0% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.2) rotate(180deg); }
            100% { transform: scale(1) rotate(360deg); }
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
        @media (max-width: 768px) {
            .card-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1.5rem;
            }
            
            .title {
                font-size: 1.6rem;
            }
            
            .showcase {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .card-grid {
                grid-template-columns: 1fr;
                max-width: 240px;
                margin: 0 auto 2rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .title {
                font-size: 1.4rem;
            }
            
            .showcase-buttons {
                flex-direction: column;
                gap: 0.7rem;
            }
            
            .tabs {
                margin-bottom: 1.5rem;
            }
            
            .tab-button {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }
            
            .expression-card {
                height: 180px;
            }
        }
    </style>
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
                <button class="tab-button active" data-tab="expressions">表情</button>
                <button class="tab-button" data-tab="audios">语音</button>
            </div>

            <!-- 表情内容区 -->
            <div class="tab-content active" id="expressions-content">
                <!-- 大展示区 -->
                <div class="showcase">
                    <?php if(!empty($expressions)): ?>
                    <div class="showcase-title" id="showcase-expression-info">
                        <?php echo !empty($expressions) ? htmlspecialchars($expressions[0]['title']) : '暂无表情'; ?>
                    </div>
                    <div class="showcase-content">
                        <img src="<?php echo htmlspecialchars($expressions[0]['image_path']); ?>" alt="表情展示" class="showcase-image" id="showcase-expression">
                    </div>
                    <div class="showcase-info" id="showcase-expression-desc">
                        <?php echo !empty($expressions) && !empty($expressions[0]['description']) ? htmlspecialchars($expressions[0]['description']) : '&nbsp;'; ?>
                    </div>
                    <?php else: ?>
                    <div style="color:var(--color-text);opacity:0.7;">暂无表情数据</div>
                    <?php endif; ?>

                </div>

                <!-- 过滤器 -->
                <div class="filters">
                    <div class="filter-header">
                        <div class="filter-label">按分类筛选：</div>
                        <div class="filter-right">
                            <button class="showcase-button" id="random-expression-btn" <?php echo empty($expressions) ? 'disabled' : ''; ?>>
                                <img src="/elements/express/reflash.png" alt="随机切换" class="refresh-icon">
                            </button>
                        </div>
                    </div>
                    <div class="filter-container" id="expression-filters">
                        <button class="filter-button active" data-category="all">全部</button>
                        <?php foreach($expression_categories as $category): ?>
                        <button class="filter-button" data-category="<?php echo htmlspecialchars($category); ?>">
                            <?php echo htmlspecialchars($category); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 卡片网格 -->
                <div class="card-grid" id="expression-grid">
                    <?php if(!empty($expressions)): ?>
                    <?php foreach($expressions as $index => $expression): ?>
                    <div class="expression-card" data-category="<?php echo htmlspecialchars($expression['category']); ?>" data-index="<?php echo $index; ?>">
                        <div class="expression-card-inner">
                            <div class="expression-card-front">
                                <div class="expression-title" style="font-size:1.2rem;line-height:1.6;word-break:break-all;"><?php echo htmlspecialchars($expression['title']); ?></div>
                            </div>
                            <div class="expression-card-back">
                                <img src="<?php echo htmlspecialchars($expression['image_path']); ?>" alt="<?php echo htmlspecialchars($expression['title']); ?>" class="expression-image">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-message" style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--color-text); opacity: 0.7;">
                        暂无表情包数据
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 音频内容区 -->
            <div class="tab-content" id="audios-content">
                <!-- 大展示区 -->
                <div class="showcase">
                    <h2 class="showcase-title">语音播放</h2>
                    <div class="showcase-content">
                        <?php if(!empty($audios)): ?>
                        <audio controls id="showcase-audio-player" class="showcase-audio-player">
                            <source src="<?php echo htmlspecialchars($audios[0]['audio_path']); ?>" type="audio/mpeg">
                            您的浏览器不支持音频播放
                        </audio>
                        <?php else: ?>
                        <div style="color:var(--color-text);opacity:0.7;">暂无语音数据</div>
                        <?php endif; ?>
                    </div>
                    <div class="showcase-info" id="showcase-audio-info">
                        <?php echo !empty($audios) ? htmlspecialchars($audios[0]['title']) : '暂无语音'; ?>
                    </div>
                    <div class="showcase-buttons">
                        <button class="showcase-button" id="random-audio-btn" <?php echo empty($audios) ? 'disabled' : ''; ?>>随机切换语音</button>
                        <button class="showcase-button" id="play-audio-btn" <?php echo empty($audios) ? 'disabled' : ''; ?>>播放此语音</button>
                    </div>
                </div>

                <!-- 过滤器 -->
                <div class="filters">
                    <div class="filter-label">按分类筛选：</div>
                    <div class="filter-container" id="audio-filters">
                        <button class="filter-button active" data-category="all">全部</button>
                        <?php foreach($audio_categories as $category): ?>
                        <button class="filter-button" data-category="<?php echo htmlspecialchars($category); ?>">
                            <?php echo htmlspecialchars($category); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 音频卡片网格 -->
                <div class="card-grid" id="audio-grid">
                    <?php if(!empty($audios)): ?>
                    <?php foreach($audios as $index => $audio): ?>
                    <div class="audio-card" data-category="<?php echo htmlspecialchars($audio['category']); ?>" data-index="<?php echo $index; ?>">
                        <h3 class="audio-title"><?php echo htmlspecialchars($audio['title']); ?></h3>
                        <audio controls class="audio-player">
                            <source src="<?php echo htmlspecialchars($audio['audio_path']); ?>" type="audio/mpeg">
                            您的浏览器不支持音频播放
                        </audio>
                        <span class="audio-category"><?php echo htmlspecialchars($audio['category']); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-message" style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--color-text); opacity: 0.7;">
                        暂无语音数据
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- 页脚 -->
        <footer class="footer">
            <p>© <?php echo date('Y'); ?> Nagisa Live</p>
        </footer>
    </div>

    <script>
        // 存储数据
        const expressions = <?php echo json_encode($expressions, JSON_UNESCAPED_UNICODE); ?>;
        const audios = <?php echo json_encode($audios, JSON_UNESCAPED_UNICODE); ?>;
        
        // 当前过滤器状态
        let currentExpressionFilter = 'all';
        let currentAudioFilter = 'all';
        
        // DOM元素引用
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        const expressionFilters = document.querySelectorAll('#expression-filters .filter-button');
        const audioFilters = document.querySelectorAll('#audio-filters .filter-button');
        const expressionCards = document.querySelectorAll('.expression-card');
        const audioCards = document.querySelectorAll('.audio-card');
        const showcaseExpression = document.getElementById('showcase-expression');
        const showcaseExpressionInfo = document.getElementById('showcase-expression-info');
        const showcaseAudioPlayer = document.getElementById('showcase-audio-player');
        const showcaseAudioInfo = document.getElementById('showcase-audio-info');
        const randomExpressionBtn = document.getElementById('random-expression-btn');
        const randomAudioBtn = document.getElementById('random-audio-btn');
        const playAudioBtn = document.getElementById('play-audio-btn');
        
        // 标签切换功能
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // 移除所有活动状态
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // 设置当前活动标签
                button.classList.add('active');
                const tabId = button.getAttribute('data-tab');
                document.getElementById(`${tabId}-content`).classList.add('active');
            });
        });
        
        // 过滤功能
        function setupFilters(filters, items, filterProperty) {
            filters.forEach(filter => {
                filter.addEventListener('click', () => {
                    const category = filter.getAttribute('data-category');
                    
                    // 更新过滤按钮状态
                    filters.forEach(btn => btn.classList.remove('active'));
                    filter.classList.add('active');
                    
                    // 更新当前过滤器
                    if (filterProperty === 'currentExpressionFilter') {
                        currentExpressionFilter = category;
                    } else {
                        currentAudioFilter = category;
                    }
                    
                    // 过滤项目
                    items.forEach(item => {
                        if (category === 'all' || item.getAttribute('data-category') === category) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
        }
        
        // 设置过滤器
        setupFilters(expressionFilters, expressionCards, 'currentExpressionFilter');
        setupFilters(audioFilters, audioCards, 'currentAudioFilter');
        
        // 表情选择功能
        function selectExpression(index) {
            const expression = expressions[index];
            if (!expression || !showcaseExpression) return;
            
            // 添加动画类
            showcaseExpression.classList.add('animate');
            
            // 更新展示区
            showcaseExpression.src = expression.image_path;
            
            // 更新标题和描述
            document.getElementById('showcase-expression-info').textContent = expression.title || '未命名表情';
            document.getElementById('showcase-expression-desc').innerHTML = expression.description || '&nbsp;';
            
            // 移除动画类
            setTimeout(() => {
                showcaseExpression.classList.remove('animate');
            }, 1000);
            
            // 滚动到展示区
            document.querySelector('.showcase').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // 随机表情功能
        function randomExpression() {
            // 添加旋转效果
            const btn = document.getElementById('random-expression-btn');
            btn.classList.add('rotating');
            
            // 动画结束后移除类
            setTimeout(() => {
                btn.classList.remove('rotating');
            }, 1000);
            
            // 获取当前过滤后的表情
            const filteredExpressions = expressions.filter(expr => 
                currentExpressionFilter === 'all' || expr.category === currentExpressionFilter);
            
            if (filteredExpressions.length === 0) return;
            
            // 如果只有一个表情，则直接返回
            if (filteredExpressions.length === 1) {
                const originalIndex = expressions.findIndex(expr => expr.id === filteredExpressions[0].id);
                selectExpression(originalIndex);
                return;
            }
            
            // 获取当前显示的表情ID
            const currentExpressionSrc = document.getElementById('showcase-expression').src;
            const currentExpressionName = document.getElementById('showcase-expression-info').textContent;
            
            // 排除当前表情后再随机选择
            const availableExpressions = filteredExpressions.filter(expr => 
                expr.image_path !== currentExpressionSrc && expr.title !== currentExpressionName);
                
            // 如果过滤后没有表情了（理论上不应该，因为前面检查了至少有2个），则使用原来的方法
            if (availableExpressions.length === 0) {
                const randomIndex = Math.floor(Math.random() * filteredExpressions.length);
                const randomExpr = filteredExpressions[randomIndex];
                const originalIndex = expressions.findIndex(expr => expr.id === randomExpr.id);
                selectExpression(originalIndex);
                return;
            }
            
            // 从可用表情中随机选择一个
            const randomIndex = Math.floor(Math.random() * availableExpressions.length);
            const randomExpr = availableExpressions[randomIndex];
            
            // 找到对应原始索引
            const originalIndex = expressions.findIndex(expr => expr.id === randomExpr.id);
            
            // 选择该表情
            selectExpression(originalIndex);
        }
        
        // 音频选择功能
        function selectAudio(index) {
            const audio = audios[index];
            if (!audio || !showcaseAudioPlayer) return;
            
            // 更新展示区
            showcaseAudioPlayer.src = audio.audio_path;
            showcaseAudioInfo.textContent = audio.title || '未命名语音';
            
            // 滚动到展示区
            document.querySelector('.showcase').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // 随机音频功能
        function randomAudio() {
            // 获取当前过滤后的音频
            const filteredAudios = audios.filter(audio => 
                currentAudioFilter === 'all' || audio.category === currentAudioFilter);
            
            if (filteredAudios.length === 0) return;
            
            // 随机选择一个音频
            const randomIndex = Math.floor(Math.random() * filteredAudios.length);
            const randomAud = filteredAudios[randomIndex];
            
            // 找到对应原始索引
            const originalIndex = audios.findIndex(audio => audio.id === randomAud.id);
            
            // 选择该音频
            selectAudio(originalIndex);
            
            // 播放音频
            setTimeout(() => {
                if (showcaseAudioPlayer) showcaseAudioPlayer.play();
            }, 300);
        }
        
        // 为每个表情卡片添加点击事件
        expressionCards.forEach(card => {
            // 卡片背面元素
            const cardBack = card.querySelector('.expression-card-back');
            
            // 点击事件
            card.addEventListener('click', () => {
                const index = parseInt(card.getAttribute('data-index'));
                
                // 背景色变为浅色
                if(cardBack) {
                    cardBack.style.backgroundColor = 'var(--color-accent)';
                }
                
                // 选择表情
                selectExpression(index);
            });
            
            // 鼠标离开事件
            card.addEventListener('mouseleave', () => {
                // 恢复背景色
                if(cardBack) {
                    cardBack.style.backgroundColor = 'var(--color-primary)';
                }
            });
        });
        
        // 为每个音频卡片添加点击事件
        audioCards.forEach(card => {
            card.addEventListener('click', () => {
                const index = parseInt(card.getAttribute('data-index'));
                selectAudio(index);
            });
        });
        
        // 绑定按钮事件
        if (randomExpressionBtn) randomExpressionBtn.addEventListener('click', randomExpression);
        if (randomAudioBtn) randomAudioBtn.addEventListener('click', randomAudio);
        if (playAudioBtn) playAudioBtn.addEventListener('click', () => {
            if (showcaseAudioPlayer) showcaseAudioPlayer.play();
        });
    </script>
</body>
</html> 