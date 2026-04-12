<?php
// 设置页面标题
$page_title = "语音 - 推理社日常";
// 设置活动标签
$active_tab = 'audios';

// 优化内存使用
gc_enable(); // 启用垃圾回收
ini_set('memory_limit', '128M'); // 增加内存限制以避免内存溢出问题

// 启用输出缓冲
ob_start();

// 获取数据库连接
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/database.php';
$db = new Database();
$conn = $db->getConnection();

// 从数据库获取音频数据
$audios = [];

try {
    // 检查音频表是否存在
    $stmt = $conn->query("SHOW TABLES LIKE 'expression_audios'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // 获取所有音频，按显示顺序排序，只选择必要的字段
        // 限制加载的音频数量，避免内存溢出
        $stmt = $conn->prepare("SELECT id, title, audio_path, category, display_order FROM expression_audios WHERE status = 1 ORDER BY display_order ASC, created_at DESC LIMIT 500");
        $stmt->execute();
        $audios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // 出错时不显示错误，只在日志中记录
    error_log("音频页面数据库错误: " . $e->getMessage());
}



// 按分类组织音频数据，优化内存使用
$audio_by_category = [];
foreach ($audios as $key => $audio) {
    $category = $audio['category'] ?: '未分类';
    
    // 跳过未分类的音频
    if ($category === '未分类') {
        continue;
    }
    
    if (!isset($audio_by_category[$category])) {
        $audio_by_category[$category] = [];
    }
    
    // 只保存必要的信息
    $audio_by_category[$category][] = [
        'id' => $audio['id'],
        'title' => $audio['title'],
        'audio_path' => $audio['audio_path'],
        'display_order' => $audio['display_order']
    ];
}



// 从数据库获取分类，按sort_order升序排序
try {
    // 释放之前的查询资源
    $stmt = null;
    $conn = null;
    
    // 重新获取数据库连接
    $db = new Database();
    $conn = $db->getConnection();
    
    // 查询所有分类及其排序值
    $stmt = $conn->prepare("SELECT name, sort_order FROM audio_categories ORDER BY sort_order ASC, name ASC");
    $stmt->execute();
    $ordered_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 创建一个有序的分类数组
    $audio_categories_ordered = [];
    foreach ($ordered_categories as $cat) {
        if (isset($audio_by_category[$cat['name']])) {
            $audio_categories_ordered[] = $cat['name'];
        }
    }
    
    // 添加任何没有在数据库中的分类（放在最后），但排除"未分类"
    $audio_categories = array_keys($audio_by_category);
    foreach ($audio_categories as $category) {
        if (!in_array($category, $audio_categories_ordered) && $category !== '未分类') {
            $audio_categories_ordered[] = $category;
        }
    }
    
    // 使用排序后的分类替换原来的分类数组
    $audio_categories = $audio_categories_ordered;
    
    // 释放不再需要的变量以节省内存
    $ordered_categories = null;
    $audio_categories_ordered = null;
} catch (PDOException $e) {
    // 出错时不显示错误，只在日志中记录
    error_log("音频分类排序错误: " . $e->getMessage());
    // 如果出错，使用默认的未排序分类
    $audio_categories = array_keys($audio_by_category);
}
?>

<!-- 顶部控制区域 -->
<div class="top-control-section">
    <!-- 正在播放显示区 -->
    <div class="now-playing-section" id="now-playing-section">
        <div class="now-playing-card">
            <div class="now-playing-content">
                <div class="now-playing-info">
                    <span class="now-playing-label">正在播放：</span>
                    <span class="now-playing-name" id="now-playing-name"></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 随机按钮区域 -->
    <div class="random-section">
        <button class="showcase-button" id="random-all-btn">
            <img src="/elements/express/reflash.png" alt="随机切换" class="refresh-icon">
        </button>
    </div>
</div>

<!-- 分类展示区 -->
<div class="category-grid single-page-container">
    <?php foreach ($audio_categories as $category): ?>
    <div class="category-card">
        <div class="category-header">
            <h3><?php echo htmlspecialchars($category); ?></h3>
        </div>
        <div class="category-content">
            <?php if (isset($audio_by_category[$category])): ?>
                <?php foreach ($audio_by_category[$category] as $audio): ?>
                <div class="content-item audio-item" data-type="audio" data-id="<?php echo $audio['id']; ?>">
                    <div class="item-text"><?php echo htmlspecialchars($audio['title']); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            

        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- 底部控制区域 -->
<div class="bottom-control-section">
    <!-- 贴士提示 -->
    <div class="tips-container">
        <div class="tips-card">
            <div class="tips-content">
                <p id="tips-text">请注意音量大小，部分语音会有少量修改。以及由于技术原因，部分语音出现失真，感谢谅解。</p>
            </div>
        </div>
    </div>
    
    <!-- 音量控制区域 -->
    <div class="volume-control-section">
        <div class="volume-control-container">
            <div class="volume-slider-container">
                <input type="range" min="0" max="100" value="80" class="volume-slider" id="volume-slider">
            </div>
            <div class="volume-value">
                <span id="volume-value">80%</span>
            </div>
        </div>
    </div>
</div>

<!-- 音频播放器（隐藏） -->
<audio id="audio-player" style="display: none;"></audio>

<?php
// 新布局的CSS样式
$additional_styles = '
<style>
    /* 隐藏链接悬停时的URL预览 */
    a {
        pointer-events: auto;
    }
    a[href] {
        cursor: pointer;
    }
    a[href]:hover::after {
        display: none !important;
    }
    
    /* 音频播放器容器 */
    .audio-player-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 2rem;
        position: relative;
    }
    
    /* 底部控制区域样式 */
    .bottom-control-section {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 20px;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        z-index: 100;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        min-height: 60px; /* 最小高度，确保内容显示 */
    }
    
    /* 贴士提示样式 */
    .tips-container {
        flex: 1;
        margin-right: 20px;
    }
    
    .tips-card {
        background: linear-gradient(45deg, #f6e6cb, #f9f2e3);
        border-left: 4px solid #D4C38D;
        border-radius: 8px;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        height: 40px; /* 确保固定高度 */
    }
    
    .tips-content {
        flex: 1;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
    }
    
    .tips-content p {
        margin: 0;
        font-size: 0.9rem;
        color: #494236;
        line-height: 1.5;
        font-family: "SimHei", "黑体", sans-serif;
        white-space: nowrap;
        overflow: hidden;
        height: 1.5em; /* 确保文本高度 */
        position: relative;
        width: 100%;
    }
    
    /* 底部滚动文本样式 - 优化性能 */
    .tips-scrolling-text {
        animation: textScroll 15s linear infinite;
        display: inline-block;
        white-space: nowrap;
        width: max-content;
        padding: 0 15px;
        line-height: 1.5;
        height: 1.5em;
        overflow: hidden;
        position: absolute;
        right: 0; /* 从右侧开始 */
        top: 0; /* 确保垂直定位 */
        will-change: transform; /* 优化动画性能 */
        transform: translateZ(0); /* 启用GPU加速 */
    }
    
    /* 顶部控制区域样式 */
    .top-control-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        gap: 1rem;
        opacity: 0;
        transform: translateY(-20px);
        animation: slideDown 0.6s ease forwards;
        animation-delay: 0.2s;
    }
    
    /* 音量控制样式 */
    .volume-control-section {
        flex: 0 0 auto;
        width: 200px;
        display: flex;
        align-items: center; /* 垂直居中 */
    }
    
    .volume-control-container {
        display: flex;
        align-items: center;
        background: white;
        border-radius: 30px;
        padding: 8px 15px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        width: 100%; /* 确保容器填满父元素 */
    }
    
    .volume-slider-container {
        width: 140px;
        display: flex;
        align-items: center; /* 垂直居中 */
    }
    
    .volume-slider {
        -webkit-appearance: none;
        width: 100%;
        height: 5px;
        border-radius: 5px;
        background: #e0e0e0;
        outline: none;
        vertical-align: middle; /* 垂直居中 */
        margin: 0; /* 移除默认边距 */
    }
    
    /* 根据音量大小改变滑块颜色 */
    .volume-slider.low-volume::-webkit-slider-thumb {
        background: #8ECAE6;
    }
    
    .volume-slider.medium-volume::-webkit-slider-thumb {
        background: #FFB703;
    }
    
    .volume-slider.high-volume::-webkit-slider-thumb {
        background: #FB8500;
    }
    
    .volume-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 15px;
        height: 15px;
        border-radius: 50%;
        background: #D4C38D;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .volume-slider::-webkit-slider-thumb:hover {
        transform: scale(1.2);
    }
    
    /* 根据音量大小改变滑块颜色 (Firefox) */
    .volume-slider.low-volume::-moz-range-thumb {
        background: #8ECAE6;
    }
    
    .volume-slider.medium-volume::-moz-range-thumb {
        background: #FFB703;
    }
    
    .volume-slider.high-volume::-moz-range-thumb {
        background: #FB8500;
    }
    
    .volume-slider::-moz-range-thumb {
        width: 15px;
        height: 15px;
        border-radius: 50%;
        background: #D4C38D;
        cursor: pointer;
        border: none;
        transition: all 0.2s ease;
    }
    
    .volume-slider::-moz-range-thumb:hover {
        transform: scale(1.2);
    }
    
    .volume-value {
        min-width: 40px;
        text-align: right;
        margin-left: 10px;
        font-size: 0.85rem;
        color: #494236;
        font-family: "QiantuHouhei", sans-serif;
        font-weight: normal;
        display: flex;
        align-items: center; /* 垂直居中 */
    }
    
    /* 随机按钮样式 */
    .random-section {
        flex-shrink: 0;
    }
    
    .showcase-button {
        width: 60px;
        height: 60px;
        background-color: var(--color-primary);
        color: white;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        font-family: "QiantuHouhei", sans-serif;
        font-size: 1rem;
        font-weight: 500;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .showcase-button:hover {
        background-color: var(--color-accent);
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(204, 148, 113, 0.4);
    }
    
    /* 刷新按钮样式 */
    .refresh-icon {
        width: 24px;
        height: 24px;
        transition: transform 0.3s ease;
    }
    
    .showcase-button:hover .refresh-icon {
        transform: scale(1.2);
    }
    
    .showcase-button.rotating .refresh-icon {
        animation: rotate360 1s linear;
    }
    
    /* 旋转动画 */
    @keyframes rotate360 {
        0% { transform: scale(1) rotate(0deg); }
        50% { transform: scale(1.2) rotate(180deg); }
        100% { transform: scale(1) rotate(360deg); }
    }
    
    /* 正在播放区域样式 */
    .now-playing-section {
        flex: 1;
        display: flex;
        justify-content: center;
        text-align: center;
    }
    
    .now-playing-card {
        display: inline-block;
        background: white;
        color: #333;
        border-radius: 12px;
        padding: 0 24px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        min-width: 300px;
        width: calc(100% - 500px);
        height: 60px;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .now-playing-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(45deg, #4D4030, #CC9471);
    }
    
    .now-playing-content {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
    }
    
    .now-playing-info {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        width: 100%;
    }
    
    .now-playing-label {
        color: #666;
        font-size: 1.1rem;
        font-weight: normal;
        white-space: nowrap;
        font-family: "QiantuHouhei", sans-serif;
        flex-shrink: 0;
        text-align: center;
    }
    
    /* 添加京华宋体字体 */
    @font-face {
        font-family: "KingHwaOldSong";
        src: url("/assets/webfonts/KingHwaOldSongv3.0.ttf") format("truetype");
        font-display: swap;
    }
    
    .now-playing-name {
        font-size: 1.1rem;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        flex: 1;
        text-align: center;
        font-family: "KingHwaOldSong", "SimHei", "黑体", sans-serif;
        position: relative;
        max-width: 100%;
        height: 1.2em;
        line-height: 1.2;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* 超长文本滚动样式 - 优化性能 */
    .scrolling-text {
        animation: textScroll 10s linear infinite;
        display: inline-block;
        position: absolute;
        right: 0; /* 从右侧开始 */
        width: max-content;
        white-space: nowrap;
        padding: 0 15px;
        line-height: 1.2;
        height: 1.2em;
        overflow: hidden;
        will-change: transform; /* 优化动画性能 */
        transform: translateZ(0); /* 启用GPU加速 */
    }
    
    /* 确保父容器隐藏溢出内容 */
    .now-playing-name {
        overflow: hidden !important;
    }
    
    /* 内容项样式，确保文本完整显示在容器内 */
    .content-item {
        height: auto;
        overflow: hidden;
        position: relative;
    }
    
    /* 待播放状态样式 */
    .waiting-status {
        color: #888;
        font-style: italic;
    }
    
    /* 正在播放状态样式 */
    .playing-status {
        color: #CC9471;
        animation: textPulse 2s infinite alternate;
    }
    
    @keyframes textPulse {
        from { opacity: 0.8; }
        to { opacity: 1; }
    }
    
    /* 文本滚动动画 - 从右往左连续滚动 */
    @keyframes textScroll {
        0% { transform: translateX(100%); }
        100% { transform: translateX(-100%); }
    }
    
    /* 确保所有容器都隐藏溢出内容 */
    .now-playing-card, .now-playing-content, .now-playing-info {
        overflow: hidden;
    }
    

    

    
    /* 分类网格布局 */
    .category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* 保持原有最小宽度 */
        gap: 1rem; /* 保持原有间距 */
        width: 100%; /* 使用全屏宽度 */
        max-width: 2600px; /* 增加最大宽度 */
        margin: 0 auto 20px; /* 减小底部边距 */
        opacity: 0;
        transform: translateY(30px);
        animation: fadeInUp 0.8s ease forwards;
        overflow-y: auto;
        overflow-x: hidden; /* 防止水平滚动 */
        padding: 0 10px 5px; /* 减小内边距 */
        height: auto;
        flex: 1;
    }
    
    /* 分类卡片样式 */
    .category-card {
        background: #f8f9fa;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
        /* 移除最大高度限制 */
        display: flex;
        flex-direction: column;
        opacity: 0;
        transform: scale(0.8) translateY(20px);
        animation: cardExpand 0.6s ease forwards;
        min-width: 220px; /* 确保最小宽度 */
        width: 100%; /* 填充整个网格单元 */
        position: relative; /* 确保定位上下文 */
    }
    
    /* 为每个卡片设置不同的延迟时间，创造波浪效果 */
    .category-card:nth-child(1) { animation-delay: 0.1s; }
    .category-card:nth-child(2) { animation-delay: 0.2s; }
    .category-card:nth-child(3) { animation-delay: 0.3s; }
    .category-card:nth-child(4) { animation-delay: 0.4s; }
    .category-card:nth-child(5) { animation-delay: 0.5s; }
    .category-card:nth-child(6) { animation-delay: 0.6s; }
    .category-card:nth-child(7) { animation-delay: 0.7s; }
    .category-card:nth-child(8) { animation-delay: 0.8s; }
    .category-card:nth-child(9) { animation-delay: 0.9s; }
    .category-card:nth-child(10) { animation-delay: 1.0s; }
    .category-card:nth-child(11) { animation-delay: 1.1s; }
    .category-card:nth-child(12) { animation-delay: 1.2s; }
    .category-card:nth-child(13) { animation-delay: 1.3s; }
    .category-card:nth-child(14) { animation-delay: 1.4s; }
    .category-card:nth-child(15) { animation-delay: 1.5s; }
    .category-card:nth-child(16) { animation-delay: 1.6s; }
    .category-card:nth-child(17) { animation-delay: 1.7s; }
    .category-card:nth-child(18) { animation-delay: 1.8s; }
    .category-card:nth-child(19) { animation-delay: 1.9s; }
    .category-card:nth-child(20) { animation-delay: 2.0s; }
    
    /* 为超过20个的卡片设置通用延迟 */
    .category-card:nth-child(n+21) { animation-delay: 2.1s; }
    

    
    /* 动画关键帧 */
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
    
    @keyframes cardExpand {
        from {
            opacity: 0;
            transform: scale(0.8) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideInTop {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .category-card:hover {
        transform: translateY(-5px);
    }
    
    /* 分类标题 */
    .category-header {
        background: #1A1E1D;
        color: white;
        padding: 12px 16px;
        text-align: center;
    }
    
    .category-header h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: normal;
        font-family: "QiantuHouhei", sans-serif;
    }
    
    /* 分类内容区 */
    .category-content {
        padding: 12px;
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden; /* 确保水平方向超出内容不显示 */
        /* 移除最大高度限制，允许内容自然扩展 */
    }
    
    /* 内容项样式 */
    .content-item {
        background: #e3f2fd;
        border-radius: 8px;
        padding: 10px 8px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        justify-content: center;
        align-items: center;
        border: 1px solid #bbdefb;
        opacity: 0;
        transform: translateY(-20px);
        animation: slideInTop 0.5s ease forwards;
        min-height: 42px; /* 确保有足够高度显示两行文本 */
        overflow: hidden; /* 确保超出部分隐藏 */
    }
    
    /* 为内容项设置延迟动画 */
    .content-item:nth-child(1) { animation-delay: 0.3s; }
    .content-item:nth-child(2) { animation-delay: 0.4s; }
    .content-item:nth-child(3) { animation-delay: 0.5s; }
    .content-item:nth-child(4) { animation-delay: 0.6s; }
    .content-item:nth-child(5) { animation-delay: 0.7s; }
    .content-item:nth-child(6) { animation-delay: 0.8s; }
    .content-item:nth-child(7) { animation-delay: 0.9s; }
    .content-item:nth-child(8) { animation-delay: 1.0s; }
    .content-item:nth-child(9) { animation-delay: 1.1s; }
    .content-item:nth-child(10) { animation-delay: 1.2s; }
    .content-item:nth-child(11) { animation-delay: 1.3s; }
    .content-item:nth-child(12) { animation-delay: 1.4s; }
    .content-item:nth-child(13) { animation-delay: 1.5s; }
    .content-item:nth-child(14) { animation-delay: 1.6s; }
    .content-item:nth-child(15) { animation-delay: 1.7s; }
    .content-item:nth-child(16) { animation-delay: 1.8s; }
    .content-item:nth-child(17) { animation-delay: 1.9s; }
    .content-item:nth-child(20) { animation-delay: 2.2s; }
    
    /* 为超过20个的内容项设置通用延迟 */
    .content-item:nth-child(n+21) { animation-delay: 2.3s; }
    
    .content-item:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .content-item:hover .item-text {
        color: #494236;
        font-weight: bold;
    }
    
    .content-item.active {
        background: #494236;
        border-color: #494236;
    }
    
    .content-item.active .item-text {
        color: white;
        font-weight: bold;
    }
    
    .content-item.active:hover {
        background: #494236;
        border-color: #494236;
    }
    
    .content-item.active:hover .item-text {
        color: white;
        font-weight: bold;
    }
    
    .content-item:last-child {
        margin-bottom: 0;
    }
    
    .item-text {
        flex: 1;
        font-size: 0.95rem;
        color: #1976d2;
        font-weight: 500;
        line-height: 1.4;
        text-align: center;
        font-family: "SimHei", "黑体", sans-serif;
        width: 100%;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
        white-space: nowrap;
        padding: 0 5px;
        box-sizing: border-box;
    }
    
    /* 文本超出时的滚动动画 */
    .item-text.scrolling {
        animation: textScroll 8s linear infinite;
        animation-delay: 1s;
    }
    
    /* 鼠标悬停时暂停动画 */
    .content-item:hover .item-text.scrolling {
        animation-play-state: paused;
    }
    
    .item-icon {
        font-size: 1.2rem;
        margin-left: 8px;
        opacity: 0.7;
    }
    
    /* 音频项特殊样式 */
    .audio-item {
        background: #D4C38D;
        border-color: #D4C38D;
    }
    
    .audio-item:hover {
        background: #c4b37d;
    }
    
    .audio-item:hover .item-text {
        color: #494236;
        font-weight: bold;
    }
    
    .audio-item.active {
        background: #494236;
        border-color: #494236;
    }
    
    .audio-item.active .item-text {
        color: white;
        font-weight: bold;
    }
    
    .audio-item .item-text {
        color: white;
        text-align: center;
        font-family: "SimHei", "黑体", sans-serif;
    }
    

    
    /* 响应式设计 */
    @media (max-width: 1200px) {
        .category-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    @media (max-width: 480px) {
        .category-grid {
            grid-template-columns: 1fr; /* 超小屏幕只显示1列 */
            width: 100%;
        }
        
        .category-card {
            min-width: 0; /* 移除最小宽度限制 */
        }
        
        .content-item {
            padding: 8px 10px;
        }
        
        .item-text {
            font-size: 0.9rem;
        }
    }
    
    /* 单页面布局设置 */
    body {
        overflow: hidden;
        height: 100vh;
        display: flex;
        flex-direction: column;
    }
    
    .main-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    /* 单页面布局的容器高度计算 */
    .single-page-container {
        height: calc(100vh - 110px); /* 减少减去的高度，使容器更高 */
    }
    
    @media (max-width: 768px) {
        .top-control-section {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .now-playing-section {
            text-align: center;
        }
        
        .random-section {
            text-align: center;
        }
        
        .category-grid {
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); /* 移动端自适应布局 */
            gap: 0.8rem; /* 减小间距 */
            margin-bottom: 90px;
            max-height: calc(100vh - 180px); /* 移动端调整高度，增加容器高度 */
            width: 100%; /* 全宽 */
            padding: 0 5px; /* 减小内边距 */
        }
        
        /* 底部控制区域响应式 */
        .bottom-control-section {
            flex-direction: column;
            padding: 10px;
            height: 80px; /* 移动端增加高度 */
        }
        
        .tips-container {
            margin-right: 0;
            margin-bottom: 10px;
            width: 100%;
        }
        
        .tips-card {
            text-align: center;
        }
        
        .volume-control-section {
            width: 100%;
        }
        
        .volume-control-container {
            justify-content: center;
        }
        
        .volume-slider-container {
            width: 80%;
        }
        
        /* 单页面布局移动端调整 */
        .single-page-container {
            height: calc(100vh - 170px);
        }
    }
    
    /* 针对当前图片中显示的浏览器尺寸的特定样式 */
    @media (min-width: 1024px) and (max-height: 768px) {
        .category-grid {
            margin-bottom: 10px; /* 进一步减小底部边距 */
            max-height: calc(100vh - 100px); /* 调整高度计算，增加容器高度 */
            width: 98%; /* 使用更大的宽度 */
        }
        
        .bottom-control-section {
            height: 40px; /* 减小底部控制区域高度 */
            padding: 5px 20px; /* 减小内边距 */
        }
        
        /* 减小底部控制区域内部元素的大小 */
        .tips-card {
            padding: 5px 10px;
        }
    }
    
    @media (max-width: 480px) {
        .category-grid {
            grid-template-columns: 1fr;
        }
        
        .content-item {
            padding: 8px 10px;
        }
        
        .item-text {
            font-size: 0.9rem;
        }
    }
    
    /* 滚动条样式 */
    .category-content::-webkit-scrollbar {
        width: 6px;
    }
    
    .category-content::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    
    .category-content::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }
    
    .category-content::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
</style>
';

// 优化音频数据，只保留必要字段，减少内存占用
$optimized_audios = [];
foreach ($audios as $audio) {
    $optimized_audios[] = [
        'id' => $audio['id'],
        'title' => $audio['title'],
        'audio_path' => $audio['audio_path']
    ];
}
$audios = null; // 释放原始数据

// JavaScript功能
$additional_scripts = '
<script>
    // 存储数据 - 使用优化后的数据集
    const audios = ' . json_encode($optimized_audios, JSON_UNESCAPED_UNICODE) . ';
    
    // 音频播放器
    const audioPlayer = document.getElementById("audio-player");
    let isPlaying = false;
    let currentActiveItem = null; // 跟踪当前激活的按钮
    let playedAudios = []; // 记录已播放过的音频ID
    let currentAudio = null; // 当前播放的音频
    let isShortAudio = false; // 是否是短音频（2秒以内）
    
    // 随机选择功能
    function randomSelect() {
        const audioItems = document.querySelectorAll(".content-item.audio-item");
        if (audioItems.length === 0) {
            return;
        }
        
        // 添加旋转效果
        const btn = document.getElementById("random-all-btn");
        if (btn) {
            btn.classList.add("rotating");
            // 动画结束后移除类
            setTimeout(() => {
                btn.classList.remove("rotating");
            }, 1000);
        }
        
        // 移除所有其他项的active类
        document.querySelectorAll(".content-item").forEach(otherItem => {
            otherItem.classList.remove("active");
        });
        
        // 检查是否所有音频都已播放过
        if (playedAudios.length >= audios.length) {
            // 如果所有音频都播放过了，重置记录
            playedAudios = [];
        }
        
        // 直接从音频数组中随机选择
        if (audios.length === 0) {
            console.error("没有可用的音频");
            return;
        }
        
        // 筛选出未播放过的音频
        const unplayedAudios = audios.filter(audio => 
            !playedAudios.includes(parseInt(audio.id))
        );
        
        // 如果有未播放的音频，优先从中选择，否则随机选择任意音频
        let audioToPlay;
        if (unplayedAudios.length > 0) {
            const randomIndex = Math.floor(Math.random() * unplayedAudios.length);
            audioToPlay = unplayedAudios[randomIndex];
        } else {
            const randomIndex = Math.floor(Math.random() * audios.length);
            audioToPlay = audios[randomIndex];
        }
        
        if (!audioToPlay) {
            console.error("未能选择有效的音频");
            return;
        }
        
        const audioId = parseInt(audioToPlay.id);
        
        // 查找对应的DOM元素并添加active类
        const matchingItem = document.querySelector(`.content-item.audio-item[data-id="${audioId}"]`);
        if (matchingItem) {
            matchingItem.classList.add("active");
            currentActiveItem = matchingItem;
            
            // 如果元素不在视图中，滚动到该元素
            matchingItem.scrollIntoView({ behavior: "smooth", block: "center" });
        }
        
        // 记录已播放的音频
        if (!playedAudios.includes(audioId)) {
            playedAudios.push(audioId);
        }
        
        // 播放音频
        console.log("随机播放音频:", audioToPlay.title);
        playAudio(audioToPlay);
    }
    
    // 处理音频播放
    function playAudio(audio) {
        // 停止当前播放
        audioPlayer.pause();
        
        // 保存当前音频
        currentAudio = audio;
        
        // 设置音频源并播放
        audioPlayer.src = audio.audio_path;
        
        // 播放音频
        audioPlayer.play().then(() => {
            isPlaying = true;
            showNowPlaying(audio);
            
            // 检查音频长度，加载完成后执行
            audioPlayer.addEventListener("loadedmetadata", function onLoadedMetadata() {
                // 移除事件监听器，避免重复执行
                audioPlayer.removeEventListener("loadedmetadata", onLoadedMetadata);
                
                // 判断是否是短音频（1秒以内）
                isShortAudio = audioPlayer.duration <= 1;
            });
        }).catch(error => {
            console.error("播放失败:", error, "音频路径:", audio.audio_path);
            // 尝试使用原始路径
            audioPlayer.src = audio.audio_path;
            audioPlayer.play().catch(err => {
                console.error("使用原始路径播放也失败:", err);
            });
        });
    }
    
    // 显示正在播放信息 - 优化性能
    function showNowPlaying(audio) {
        const nowPlayingName = document.getElementById("now-playing-name");
        const nowPlayingLabel = document.querySelector(".now-playing-label");
        
        // 更新显示内容
        nowPlayingLabel.textContent = "正在播放：";
        
        // 清除之前的内容
        while (nowPlayingName.firstChild) {
            nowPlayingName.removeChild(nowPlayingName.firstChild);
        }
        
        // 直接显示标题，不使用滚动效果
        const title = audio.title;
        nowPlayingName.textContent = title;
        
        nowPlayingName.classList.remove("waiting-status");
        nowPlayingName.classList.add("playing-status");
        
        // 确保文本居中显示
        nowPlayingName.style.textAlign = "center";
    }
    
    // 重置播放信息为默认状态
    function resetNowPlaying() {
        const nowPlayingName = document.getElementById("now-playing-name");
        const nowPlayingLabel = document.querySelector(".now-playing-label");
        
        // 重置显示内容为待播放
        nowPlayingLabel.textContent = "待播放：";
        
        // 清除之前的内容
        while (nowPlayingName.firstChild) {
            nowPlayingName.removeChild(nowPlayingName.firstChild);
        }
        nowPlayingName.textContent = "";
        nowPlayingName.classList.add("waiting-status");
        
        // 移除所有按钮的active状态
        document.querySelectorAll(".content-item").forEach(item => {
            item.classList.remove("active");
        });
        currentActiveItem = null;
    }
    
    // 重置播放信息但保留按钮状态
    function resetNowPlayingText() {
        const nowPlayingName = document.getElementById("now-playing-name");
        const nowPlayingLabel = document.querySelector(".now-playing-label");
        
        // 只重置显示内容，不重置按钮状态
        nowPlayingLabel.textContent = "待播放：";
        
        // 清除之前的内容
        while (nowPlayingName.firstChild) {
            nowPlayingName.removeChild(nowPlayingName.firstChild);
        }
        nowPlayingName.textContent = "";
        nowPlayingName.classList.add("waiting-status");
    }
    

    

    

    
    // 音量控制功能
    function setupVolumeControl() {
        const volumeSlider = document.getElementById("volume-slider");
        const volumeValue = document.getElementById("volume-value");
        const audioPlayer = document.getElementById("audio-player");
        
        // 初始化音量
        if (audioPlayer && volumeSlider) {
            audioPlayer.volume = volumeSlider.value / 100;
            updateVolumeSliderColor(volumeSlider.value);
        }
        
        // 更新音量滑块颜色
        function updateVolumeSliderColor(value) {
            if (!volumeSlider) return;
            
            // 移除所有音量相关的类
            volumeSlider.classList.remove("low-volume", "medium-volume", "high-volume");
            
            // 根据音量大小添加对应的类
            if (value === 0) {
                // 静音状态不添加类
            } else if (value < 30) {
                volumeSlider.classList.add("low-volume");
            } else if (value < 70) {
                volumeSlider.classList.add("medium-volume");
            } else {
                volumeSlider.classList.add("high-volume");
            }
        }
        
        // 音量滑块事件
        if (volumeSlider) {
            volumeSlider.addEventListener("input", function() {
                const value = this.value;
                if (volumeValue) {
                    volumeValue.textContent = value + "%";
                }
                
                if (audioPlayer) {
                    audioPlayer.volume = value / 100;
                }
                
                updateVolumeSliderColor(value);
            });
        }
    }
    
    // 检查文本是否需要滚动效果 - 优化性能
    function checkTextOverflow() {
        const textElements = document.querySelectorAll(".item-text");
        if (!textElements.length) return;
        
        // 创建一个临时元素，重用它来测量所有文本宽度
        const temp = document.createElement("span");
        temp.style.visibility = "hidden";
        temp.style.position = "absolute";
        temp.style.whiteSpace = "nowrap";
        document.body.appendChild(temp);
        
        // 批量处理DOM操作，减少重排
        const updates = [];
        
        textElements.forEach(element => {
            // 设置临时元素的样式以匹配当前元素
            temp.style.fontSize = window.getComputedStyle(element).fontSize;
            temp.style.fontFamily = window.getComputedStyle(element).fontFamily;
            temp.textContent = element.textContent;
            
            // 获取文本和容器的宽度
            const textWidth = temp.offsetWidth;
            const containerWidth = element.offsetWidth;
            
            // 记录需要更新的元素和状态
            const needsScrolling = textWidth > containerWidth * 0.9;
            const hasScrolling = element.classList.contains("scrolling");
            
            if (needsScrolling !== hasScrolling) {
                updates.push({
                    element: element,
                    add: needsScrolling
                });
            }
        });
        
        // 移除临时元素
        document.body.removeChild(temp);
        
        // 批量应用DOM更新
        updates.forEach(update => {
            if (update.add) {
                update.element.classList.add("scrolling");
            } else {
                update.element.classList.remove("scrolling");
            }
        });
    }
    
    // 优化事件处理函数，减少内存使用
    function handleItemClick(event) {
        const item = event.currentTarget;
        console.log("内容项被点击", item);
        
        // 移除所有其他项的active类
        document.querySelectorAll(".content-item").forEach(otherItem => {
            otherItem.classList.remove("active");
        });
        
        // 为当前点击项添加active类
        item.classList.add("active");
        currentActiveItem = item;
        
        const type = item.getAttribute("data-type");
        const audioId = parseInt(item.getAttribute("data-id"));
        
        console.log("类型:", type, "ID:", audioId);
        
        if (type === "audio") {
            // 根据ID查找对应的音频
            const audioToPlay = audios.find(audio => parseInt(audio.id) === audioId);
            if (audioToPlay) {
                console.log("找到音频:", audioToPlay.title);
                playAudio(audioToPlay);
            } else {
                console.error("未找到ID为", audioId, "的音频", "所有音频:", audios);
                // 尝试直接通过索引查找
                if (audios[audioId - 1]) {
                    console.log("通过索引找到音频:", audios[audioId - 1].title);
                    playAudio(audios[audioId - 1]);
                }
            }
        }
    }
    
    // 初始化
    document.addEventListener("DOMContentLoaded", function() {
        // 设置播放区域默认状态
        resetNowPlaying();
        
        // 初始化音量控制
        setupVolumeControl();
        
        // 重置已播放音频记录
        playedAudios = [];
        
        // 检查文本是否需要滚动
        setTimeout(checkTextOverflow, 500); // 延迟执行以确保DOM完全加载
        
        // 窗口大小改变时添加节流处理，避免频繁触发
        let resizeTimeout;
        window.addEventListener("resize", function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(checkTextOverflow, 200);
        });
        
        // 绑定随机按钮事件
        const randomBtn = document.getElementById("random-all-btn");
        if (randomBtn) {
            randomBtn.addEventListener("click", randomSelect);
        }
        
                          // 直接绑定点击事件到每个音频项
         document.querySelectorAll(".content-item.audio-item").forEach(item => {
             item.addEventListener("click", function() {
                 console.log("音频项点击事件触发");
                 const audioId = parseInt(this.getAttribute("data-id"));
                 if (!isNaN(audioId)) {
                     // 移除所有其他项的active类
                     document.querySelectorAll(".content-item").forEach(otherItem => {
                         otherItem.classList.remove("active");
                     });
                     
                     // 为当前点击项添加active类
                     this.classList.add("active");
                     currentActiveItem = this;
                     
                     // 查找对应的音频
                     const audioToPlay = audios.find(audio => parseInt(audio.id) === audioId);
                     if (audioToPlay) {
                         console.log("找到音频:", audioToPlay.title);
                         playAudio(audioToPlay);
                     } else {
                         console.error("未找到ID为", audioId, "的音频");
                     }
                 }
             });
             
             // 鼠标移出事件
             item.addEventListener("mouseleave", function() {
                 if (!isPlaying) {
                     this.classList.remove("active");
                     if (currentActiveItem === this) {
                         currentActiveItem = null;
                         resetNowPlayingText();
                     }
                 }
             });
         });
        
        // 当DOM内容变化时，重新检查文本溢出 - 优化观察范围
        const observer = new MutationObserver(function(mutations) {
            // 使用节流函数避免频繁调用
            let needsCheck = false;
            mutations.forEach(function(mutation) {
                if (mutation.type === "childList" && mutation.addedNodes.length > 0) {
                    needsCheck = true;
                }
            });
            
            if (needsCheck) {
                setTimeout(checkTextOverflow, 100);
            }
        });
        
        // 只观察必要的部分，而不是整个文档
        const categoryGrid = document.querySelector(".category-grid");
        if (categoryGrid) {
            observer.observe(categoryGrid, { childList: true, subtree: true });
        }
        
        // 音频播放结束事件
        audioPlayer.addEventListener("ended", () => {
            // 如果是短音频（1秒以内）且是第一次播放，则再播放一次
            if (isShortAudio && currentAudio) {
                // 重置音频
                audioPlayer.currentTime = 0;
                // 再次播放
                audioPlayer.play().then(() => {
                    // 第二次播放后，重置短音频标志
                    isShortAudio = false;
                }).catch(error => {
                    console.error("重复播放失败:", error);
                });
            } else {
                // 正常结束播放
                isPlaying = false;
                resetNowPlaying();
                
                // 移除所有项的active类
                document.querySelectorAll(".content-item").forEach(item => {
                    item.classList.remove("active");
                });
                currentActiveItem = null;
            }
        });
        
        // 标签同步更新
        updateTabStatus();
        
        // 初始化底部提示文本滚动效果
        setupTipsScrolling();
    });
    
    // 设置底部提示文本滚动效果 - 优化性能
    function setupTipsScrolling() {
        const tipsText = document.getElementById("tips-text");
        const tipsContent = document.querySelector(".tips-content");
        
        if (tipsText && tipsContent) {
            const text = tipsText.textContent;
            
            // 清除原有内容
            tipsText.textContent = "";
            
            // 创建滚动文本元素
            const scrollingText = document.createElement("span");
            scrollingText.textContent = text;
            scrollingText.className = "tips-scrolling-text";
            tipsText.appendChild(scrollingText);
            
            // 使用requestAnimationFrame优化性能
            requestAnimationFrame(() => {
                const textWidth = scrollingText.offsetWidth;
                const containerWidth = tipsContent.offsetWidth;
                
                // 根据文本长度设置合适的滚动速度
                // 确保不会发生除以零错误
                let animationDuration = 15; // 默认值
                if (textWidth > 0) {
                    animationDuration = Math.max(10, Math.min(25, textWidth / 40));
                }
                scrollingText.style.animationDuration = animationDuration + "s";
                scrollingText.style.animationTimingFunction = "linear";
                
                // 确保底部区域高度足够
                const bottomSection = document.querySelector(".bottom-control-section");
                if (bottomSection) {
                    // 确保底部区域高度至少为60px
                    const currentHeight = bottomSection.offsetHeight;
                    if (currentHeight < 60) {
                        bottomSection.style.height = "60px";
                    }
                }
            });
        }
    }
    
    // 标签状态同步函数
    function updateTabStatus() {
        // 移除所有标签的active类
        document.querySelectorAll(\'.tab-button\').forEach(tab => {
            tab.classList.remove(\'active\');
        });
        
        // 为当前页面对应的标签添加active类
        const currentTab = document.querySelector(\'.tab-button[data-tab="audios"]\');
        if (currentTab) {
            currentTab.classList.add(\'active\');
        }
    }
</script>
';

// 获取缓冲区内容
$content = ob_get_clean();

// 包含基础模板
require_once 'expression_base.php';
?> 