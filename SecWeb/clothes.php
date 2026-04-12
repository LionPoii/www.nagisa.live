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

// 获取所有衣装项目
$clothes_items = [];
try {
    $stmt = $conn->prepare("SELECT * FROM clothes_items ORDER BY display_order ASC, id DESC");
    $stmt->execute();
    $clothes_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // 发生错误时记录日志
    $log_file = $_SERVER['DOCUMENT_ROOT'] . '/logs/clothes_error.log';
    @file_put_contents($log_file, date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>衣柜</title>
    <style>
        @font-face {
            font-family: 'QIANTUHOUHEI';
            src: url('/assets/webfonts/QIANTUHOUHEI.TTF') format('truetype');
            font-display: swap;
        }
        
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow: hidden;
        }
        
        body {
            background-color: #ffffff;
            position: relative;
        }
        
        .background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .background img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: all 0.3s ease;
        }
        
        /* 页眉样式 */
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
            font-family: 'QIANTUHOUHEI', sans-serif;
            letter-spacing: 5px;
        }
        
        /* 添加图标样式 */
        .home-icon {
            margin-right: 6px;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .background img {
                object-position: center center;
            }
            
            .header-text {
                font-size: 1.5rem;
                letter-spacing: 3px;
            }
        }
        
        /* 内容区域 */
        .content-area {
            margin-top: 80px;
            height: calc(100vh - 80px);
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            gap: 20px; /* 添加容器之间的间距 */
        }
        
        /* 衣主容器 */
        .clothes-main-container {
            height: 90%;
            width: 80%; /* 占据从左侧15%到右侧5%的宽度 */
            margin-left: 20%; /* 左侧15%的边距 */
            margin-right: 3.5%; /* 右侧5%的边距 */
            display: flex;
            justify-content: center; /* 卡片居中显示 */
            align-items: center;
            gap: 20px;
            background-color: transparent; /* 移除半透明背景 */
            border-radius: 15px; /* 添加圆角 */
            padding: 20px; /* 添加内边距 */
            overflow: hidden; /* 超出部分隐藏，不显示滚动条 */
            flex-wrap: nowrap; /* 禁止内容换行，保持单行 */
        }
        
        /* 衣装容器 */
        .clothes-container {
            height: 100%;
            width: 15%; /* 默认宽度减半 */
            max-width: 80%;
            position: relative;
            background-color: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
            transition: width 0.4s cubic-bezier(0.25, 1, 0.5, 1), 
                        transform 0.3s cubic-bezier(0.25, 1, 0.5, 1), 
                        box-shadow 0.3s ease; /* 使用更平滑的过渡效果 */
            cursor: pointer; /* 添加指针光标表示可点击 */
            will-change: width, transform; /* 提示浏览器优化这些属性的变化 */
            margin: 0 5px; /* 添加左右间距 */
            flex-shrink: 0; /* 防止卡片被压缩 */
        }
        
        /* 添加悬停效果 */
        .clothes-container:hover:not(.active) {
            width: 35%; /* 悬停时宽度介于默认和活跃状态之间 */
            transform: translateY(-5px); /* 轻微上浮效果 */
            box-shadow: 0 15px 35px rgba(255, 153, 102, 0.3); /* 更明显的阴影 */
            border: 1px solid rgba(255, 153, 102, 0.5); /* 添加边框 */
        }
        
        /* 移除hover效果，改为active类控制 */
        .clothes-container.active {
            width: 35%; /* 活跃状态时宽度增加 */
            z-index: 10; /* 确保活跃时显示在其他元素之上 */
            border: 2px solid rgba(255, 153, 102, 0.85);
            box-shadow: 0 0 20px rgba(255, 153, 102, 0.5);
        }
        
        .clothes-image {
            height: 100%; /* 固定为容器高度的100% */
            width: auto; /* 宽度会根据高度和原始比例自动计算 */
            object-fit: contain; /* 保持原始比例，确保完整显示 */
            object-position: center center; /* 确保图片居中 */
            max-width: none; /* 移除最大宽度限制 */
            max-height: none; /* 移除最大高度限制 */
            transition: all 0.5s ease; /* 添加图片过渡效果 */
        }
        
        /* 标题文本效果 */
        .title-text {
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 4rem;
            font-family: 'QIANTUHOUHEI', sans-serif;
            font-weight: normal;
            color: rgba(255, 153, 102, 0.85);
            z-index: 2;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            pointer-events: none; /* 确保文字不影响鼠标事件 */
            writing-mode: vertical-rl; /* 默认竖向排列 */
            text-orientation: upright; /* 默认文字直立 */
            letter-spacing: 0.1em; /* 增加字间距 */
            white-space: nowrap; /* 防止文本换行 */
            overflow: hidden; /* 超出部分隐藏 */
            text-overflow: ellipsis; /* 超出部分显示省略号 */
            max-height: 80%; /* 限制最大高度 */
        }
        
        /* 英文文本使用侧向排列 */
        .title-text.english-text {
            writing-mode: vertical-lr; /* 侧向排列，顶部在左底部在右 */
            text-orientation: sideways; /* 文字侧向 */
        }

        /* 衣装信息容器样式 */
        .clothes-info-container {
            position: absolute;
            left: 5.5%;
            top: calc(80px + 0.25vh); /* 距离页眉底部的位置 */
            transform: none; /* 移除垂直居中变换 */
            height: auto; /* 从固定高度改为自动高度 */
            min-height: 200px; /* 设置最小高度 */
            max-height: calc(90vh - 80px - 5vh); /* 设置最大高度，防止超出可视区域 */
            width: 13.5%;
            background-color: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border-radius: 15px;
            padding: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            box-sizing: border-box;
            transition: box-shadow 0.4s ease, 
                        border 0.4s ease,
                        opacity 0.4s ease;
            z-index: 5;
            will-change: opacity, box-shadow; /* 提示浏览器优化这些属性的变化 */
        }
        
        /* 容器动画效果类 */
        .container-expanded {
            box-shadow: 0 15px 35px rgba(255, 153, 102, 0.25);
            border: 1px solid rgba(255, 153, 102, 0.3);
        }
        
        .container-collapsed {
            min-height: 150px; /* 增加初始高度，减少变化幅度 */
            opacity: 0.9;
            transform: scaleY(1); /* 移除translateY */
        }
        
        /* 展开状态重写transform，确保正确定位 */
        .container-expanded.clothes-info-container {
            transform: scaleY(1); /* 移除translateY */
        }
        
        .clothes-info-content {
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.4s ease, transform 0.4s ease;
            transition-delay: 0.1s; /* 减少延迟内容显示时间 */
        }
        
        .clothes-info-content.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .clothes-info-title {
            font-family: 'QIANTUHOUHEI', sans-serif;
            font-size: 2rem;
            color: #303d4d;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 153, 102, 0.85);
            text-align: center;
            white-space: nowrap; /* 防止文本换行 */
            overflow: hidden; /* 超出部分隐藏 */
            text-overflow: ellipsis; /* 超出部分显示省略号 */
            transition: opacity 0.2s ease, transform 0.2s ease;
            font-weight: normal; /* 取消加粗 */
        }
        
        .clothes-info-description {
            font-size: 1.2rem;
            color: #333;
            line-height: 1.6;
            flex-grow: 1;
            overflow-y: auto;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: 10px;
            white-space: pre-line; /* 保留换行符 */
            overflow-wrap: break-word; /* 允许长单词换行 */
            margin-bottom: 15px;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        
        .clothes-info-year {
            font-family: 'QIANTUHOUHEI', sans-serif;
            font-size: 3rem;
            color: rgba(255, 153, 102, 0.85);
            text-align: right;
            margin-top: auto; /* 将年份推到底部 */
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
            transition: opacity 0.3s ease, transform 0.3s ease;
            white-space: nowrap; /* 防止文本换行 */
            overflow: hidden; /* 超出部分隐藏 */
            text-overflow: ellipsis; /* 超出部分显示省略号 */
        }
        
        .no-clothes-selected {
            display: flex;
            height: 100%;
            justify-content: center;
            align-items: center;
            color: #666;
            font-size: 1.5rem;
            font-style: italic;
        }
        
        /* 文字动画效果类 */
        .fade-out {
            opacity: 0;
            transform: translateY(-10px);
        }
        
        .fade-in {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* 页面进入动画样式 */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* 页面加载时的初始状态 */
        .initial-hidden {
            opacity: 0;
        }
        
        /* 容器进入动画 */
        .clothes-main-container.animate-in {
            animation: fadeInUp 0.8s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
        }
        
        .clothes-info-container.animate-in {
            animation: fadeInRight 0.8s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
        }
        
        .clothes-container.animate-in {
            animation: scaleIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        .clothes-container .title-text {
            opacity: 1;
            transition: opacity 0.5s ease;
        }

        .clothes-container.active .title-text {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
    </style>
</head>
<body>
    <!-- 页眉 -->
    <div class="fixed-header">
        <div class="header-circle">
            <img src="<?php echo $avatar; ?>" alt="Logo" referrerpolicy="no-referrer">
        </div>
        <div class="header-text">
            衣柜
        </div>
        <div style="flex-grow: 1;"></div>
    </div>

    <div class="background">
        <img src="/elements/clothes/Clothes_Blackboard.png" alt="背景">
    </div>
    
    <!-- 内容区域 -->
    <div class="content-area">
        <!-- 衣主容器 -->
        <div class="clothes-main-container">
            <?php if (empty($clothes_items)): ?>
                <div style="color: #fff; text-align: center; width: 100%; padding: 20px;">
                    <h3>暂无衣装展示</h3>
                    <p>请在管理后台添加衣装</p>
                </div>
            <?php else: ?>
                <?php foreach ($clothes_items as $item): ?>
                    <div class="clothes-container" data-id="<?php echo $item['id']; ?>" 
                         data-title="<?php echo htmlspecialchars($item['title'] ?? '未命名衣装'); ?>" 
                         data-description="<?php echo htmlspecialchars($item['description'] ?? '暂无描述'); ?>" 
                         data-year="<?php echo htmlspecialchars($item['display_year']); ?>">
                        <img src="/SecWeb/image_proxy.php?path=<?php echo urlencode($item['image_path']); ?>"
                             alt="<?php echo htmlspecialchars($item['title'] ?? '衣装'); ?>"
                             class="clothes-image"
                             oncontextmenu="return false;"
                             draggable="false"
                             style="user-select: none; -webkit-user-drag: none;">
                        <div class="title-text"><?php echo htmlspecialchars($item['display_year']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- 衣装信息文本框容器 -->
        <div class="clothes-info-container">
            <div class="no-clothes-selected">
                
            </div>
            <div class="clothes-info-content" style="display: none;">
                <div class="clothes-info-title"></div>
                <div class="clothes-info-description"></div>
                <div class="clothes-info-year"></div>
            </div>
        </div>
    </div>
    
    <script>
    // 窗口调整时的背景适应
    window.addEventListener('resize', function() {
        // 背景已经通过CSS自动适应
    });
    
    // 衣装选择功能
    document.addEventListener('DOMContentLoaded', function() {
        const clothesContainers = document.querySelectorAll('.clothes-container');
        const infoContainer = document.querySelector('.clothes-info-container');
        const mainContainer = document.querySelector('.clothes-main-container');
        const noClothesSelected = document.querySelector('.no-clothes-selected');
        const clothesInfoContent = document.querySelector('.clothes-info-content');
        const titleElement = document.querySelector('.clothes-info-title');
        const descriptionElement = document.querySelector('.clothes-info-description');
        const yearElement = document.querySelector('.clothes-info-year');
        
        // 检测文本是否为英文的函数
        function isEnglishText(text) {
            // 使用正则表达式检测文本是否只包含英文字符、数字和标点
            return /^[A-Za-z0-9\s\p{P}]*$/u.test(text);
        }
        
        // 为所有标题文本应用适当的样式
        document.querySelectorAll('.title-text').forEach(element => {
            const text = element.textContent.trim();
            if (isEnglishText(text)) {
                element.classList.add('english-text');
            }
        });
        
        // 添加初始隐藏类
        mainContainer.classList.add('initial-hidden');
        infoContainer.classList.add('initial-hidden');
        clothesContainers.forEach(container => {
            container.classList.add('initial-hidden');
        });
        
        // 页面加载动画
        function animatePageEntry() {
            // 直接显示主容器
            setTimeout(() => {
                mainContainer.classList.add('animate-in');
                
                // 然后显示信息容器
                setTimeout(() => {
                    infoContainer.classList.add('animate-in');
                }, 200);
                
                // 最后依次显示各个衣装容器
                clothesContainers.forEach((container, index) => {
                    setTimeout(() => {
                        container.classList.add('animate-in');
                        
                        // 检查并应用英文文本样式
                        const year = container.getAttribute('data-year');
                        const yearText = container.querySelector('.title-text');
                        if (yearText && isEnglishText(year)) {
                            yearText.classList.add('english-text');
                        }
                    }, 400 + index * 100); // 每个容器错开100ms
                });
            }, 100);
        }
        
        // 调用页面入场动画
        animatePageEntry();
        
        let activeContainer = null; // 跟踪当前活跃的容器
        
        // 文本动画函数
        function animateTextChange(element, newText) {
            // 淡出效果
            element.classList.add('fade-out');
            
            // 等待淡出动画完成后更新文本并淡入
            setTimeout(() => {
                element.textContent = newText;
                element.classList.remove('fade-out');
                element.classList.add('fade-in');
                
                // 移除淡入类，以便下次动画
                setTimeout(() => {
                    element.classList.remove('fade-in');
                }, 300);
            }, 150); // 减少延迟时间
        }
        
        // 容器动画函数
        function animateContainer(expanded) {
            // 如果有活跃容器，先移除其活跃状态的过渡效果
            if (activeContainer) {
                activeContainer.style.transition = 'none';
                void activeContainer.offsetWidth; // 强制重排
                activeContainer.style.transition = ''; // 恢复默认过渡效果
            }
            
            // 动画持续时间
            const animationDuration = 400; // 毫秒
            
            if (expanded) {
                // 先从折叠状态开始
                if (!infoContainer.classList.contains('container-collapsed')) {
                    infoContainer.classList.add('container-collapsed');
                    // 强制重排，确保过渡效果生效
                    void infoContainer.offsetWidth;
                }
                
                // 应用展开效果
                infoContainer.classList.add('container-expanded');
                infoContainer.classList.remove('container-collapsed');
                
                // 延迟后显示内容
                setTimeout(() => {
                    clothesInfoContent.classList.add('visible');
                }, animationDuration);
            } else {
                // 先隐藏内容
                clothesInfoContent.classList.remove('visible');
                
                // 相同延迟后改变容器样式
                setTimeout(() => {
                    infoContainer.classList.remove('container-expanded');
                    infoContainer.classList.add('container-collapsed');
                }, animationDuration);
            }
        }
        
        // 初始化时添加折叠效果
        infoContainer.classList.add('container-collapsed');
        
        clothesContainers.forEach(container => {
            container.addEventListener('click', function() {
                // 如果点击的是当前活跃容器，则取消活跃状态
                if (activeContainer === this) {
                    this.classList.remove('active');
                    activeContainer = null;
                    
                    // 添加淡出效果后隐藏信息内容
                    titleElement.classList.add('fade-out');
                    descriptionElement.classList.add('fade-out');
                    yearElement.classList.add('fade-out');
                    
                    // 使用相同的延迟
                    setTimeout(() => {
                        // 折叠容器
                        animateContainer(false);
                        
                        setTimeout(() => {
                            noClothesSelected.style.display = 'flex';
                            clothesInfoContent.style.display = 'none';
                            
                            // 重置类，为下次显示准备
                            titleElement.classList.remove('fade-out');
                            descriptionElement.classList.remove('fade-out');
                            yearElement.classList.remove('fade-out');
                        }, 400); // 使用相同的延迟
                    }, 300);
                    
                    return;
                }
                
                // 暂时禁用过渡效果，防止闪缩
                clothesContainers.forEach(item => {
                    item.style.transition = 'none';
                    item.classList.remove('active');
                });
                
                // 强制重排
                void this.offsetWidth;
                
                // 恢复过渡效果
                clothesContainers.forEach(item => {
                    item.style.transition = '';
                });
                
                // 设置当前容器为活跃状态
                this.classList.add('active');
                activeContainer = this;
                
                // 获取衣装数据
                const title = this.getAttribute('data-title');
                const description = this.getAttribute('data-description');
                const year = this.getAttribute('data-year');
                
                // 检查年份是否为英文并更新样式
                const yearText = this.querySelector('.title-text');
                if (yearText && isEnglishText(year)) {
                    yearText.classList.add('english-text');
                } else if (yearText) {
                    yearText.classList.remove('english-text');
                }
                
                // 如果信息内容已显示，则使用动画过渡
                if (clothesInfoContent.style.display === 'block') {
                    // 先淡出当前内容
                    clothesInfoContent.classList.remove('visible');
                    
                    // 使用相同的延迟
                    setTimeout(() => {
                        animateTextChange(titleElement, title);
                        animateTextChange(descriptionElement, description);
                        animateTextChange(yearElement, year);
                        
                        // 淡入新内容
                        setTimeout(() => {
                            clothesInfoContent.classList.add('visible');
                        }, 400);
                    }, 400);
                } else {
                    // 首次显示时，先设置内容，然后显示
                    titleElement.textContent = title;
                    descriptionElement.textContent = description;
                    yearElement.textContent = year;
                    
                    // 隐藏提示文本，但不立即显示内容
                    noClothesSelected.style.display = 'none';
                    clothesInfoContent.style.display = 'block';
                    
                    // 展开容器
                    animateContainer(true);
                    
                    // 添加初次显示的动画效果
                    titleElement.classList.add('fade-out');
                    descriptionElement.classList.add('fade-out');
                    yearElement.classList.add('fade-out');
                    
                    // 使用相同的延迟淡入各个元素
                    const textDelay = 200; // 减少延迟
                    setTimeout(() => {
                        titleElement.classList.remove('fade-out');
                        setTimeout(() => {
                            descriptionElement.classList.remove('fade-out');
                            setTimeout(() => {
                                yearElement.classList.remove('fade-out');
                            }, 80);
                        }, 80);
                    }, textDelay);
                }
            });
        });
    });
    </script>
</body>
</html>