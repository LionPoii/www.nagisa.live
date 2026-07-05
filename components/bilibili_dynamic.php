<?php
/**
 * 首页 B 站动态展示（原 dynamic_v02 样式与逻辑，合并为单一组件）
 */
// === PHP数据处理部分 ===
require_once __DIR__ . '/../includes/bilibili_dynamic.php';

// 修改检查更新API部分
// 如果是检查更新请求，返回最新动态的信息
if (isset($_GET['check_update']) && $_GET['check_update'] == '1') {
    header('Content-Type: application/json');
    
    // 记录请求日志
    $log_file = __DIR__ . '/../logs/dynamic_check.log';
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - 检查动态更新请求\n", FILE_APPEND);
    
    // 初始化B站动态检测类
    $mid = 2124647716; // 默认用户ID
    
    // 从数据库获取配置
    try {
        require_once __DIR__ . '/../includes/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        // 获取用户ID配置
        $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_mid'");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($config && !empty($config['config_value'])) {
            $mid = $config['config_value'];
        }
    } catch (Exception $e) {
        // 出错时使用默认值
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - 获取配置出错: " . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - 使用UID: {$mid}\n", FILE_APPEND);
    
    $biliDynamic = new BilibiliDynamic();
    $dynamics = $biliDynamic->getProcessedDynamics($mid, 1, 1, false); // 只获取最新的1条动态
    
    $response = [
        'success' => true,
        'latest_dynamic' => null,
        'timestamp' => time()
    ];
    
    if (!empty($dynamics)) {
        $latest = $dynamics[0];
        $plain = strip_tags($latest['content'] ?? '');
        $snippet = mb_substr($plain, 0, 50);
        if (mb_strlen($plain) > 50) {
            $snippet .= '...';
        }
        $response['latest_dynamic'] = [
            'id' => $latest['id'],
            'timestamp' => $latest['timestamp'],
            'text' => $snippet
        ];
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - 获取到最新动态ID: {$latest['id']}\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - 未获取到动态数据\n", FILE_APPEND);
    }
    
    echo json_encode($response);
    exit;
}

// 记录组件加载时间
$component_load_time = date('Y-m-d H:i:s');
$log_file = __DIR__ . '/../logs/dynamic_component.log';
file_put_contents($log_file, "{$component_load_time} - 动态组件加载\n", FILE_APPEND);

// 检查是否为刷新请求
$force_refresh = isset($_GET['refresh_dynamic']) && $_GET['refresh_dynamic'] == '1';
if ($force_refresh) {
    file_put_contents($log_file, "{$component_load_time} - 强制刷新请求\n", FILE_APPEND);
}

// 初始化B站动态检测类
$mid = 2124647716; // 默认用户ID

// 从数据库获取配置
try {
    require_once __DIR__ . '/../includes/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // 获取用户ID配置
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_mid'");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($config && !empty($config['config_value'])) {
        $mid = $config['config_value'];
    }
    
    // 获取是否显示置顶动态配置
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'show_pinned'");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    $show_pinned = $config ? (bool)$config['config_value'] : true;
} catch (Exception $e) {
    // 出错时使用默认值
    $show_pinned = true;
}

$biliDynamic = new BilibiliDynamic();

// 首页展示条数
$dynamic_display_count = BilibiliDynamic::DISPLAY_COUNT;

// 获取动态列表
$max_retries = 3;
$retry_count = 0;
$dynamics = [];

while ($retry_count < $max_retries && empty($dynamics)) {
    if ($retry_count > 0) {
        sleep(1); // 延迟1秒后重试
    }
    
    $dynamics = $biliDynamic->getProcessedDynamics(
        $mid,
        1,
        $dynamic_display_count,
        $force_refresh,
        !$show_pinned
    );
    $retry_count++;
    
    // 记录重试日志
    if (empty($dynamics) && $retry_count < $max_retries) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " [RETRY] 动态获取重试 #$retry_count\n", FILE_APPEND);
    }
}

// 代理图片处理函数
function proxyBiliImage($url) {
    return BilibiliRichText::normalizeMediaUrl($url);
}

/** 渲染动态附件（图片 / 视频 / 卡片） */
function nagisa_render_dynamic_attachments(array $dynamic): void {
    if (!empty($dynamic['images'])) {
        $imageCount = count($dynamic['images']);
        echo '<div class="dynamic-images ' . ($imageCount == 1 ? 'single-image' : '') . '">';
        foreach ($dynamic['images'] as $image) {
            echo '<div class="dynamic-image ' . ($imageCount == 1 ? 'full-width' : '') . '">';
            echo '<img src="' . htmlspecialchars($image) . '" alt="动态图片" loading="lazy" referrerpolicy="no-referrer">';
            echo '</div>';
        }
        echo '</div>';
    }

    if (!empty($dynamic['video'])) {
        $video = $dynamic['video'];
        echo '<div class="dynamic-video">';
        echo '<a href="https://www.bilibili.com/video/' . htmlspecialchars($video['bvid']) . '" target="_blank">';
        echo '<div class="video-cover">';
        echo '<img src="' . htmlspecialchars($video['cover']) . '" alt="视频封面" referrerpolicy="no-referrer">';
        echo '<div class="video-duration">' . htmlspecialchars($video['duration']) . '</div>';
        echo '<div class="video-play-icon">▶</div>';
        echo '</div>';
        echo '<div class="video-info">';
        echo '<div class="video-title">' . $video['title'] . '</div>';
        echo '<div class="video-stats">';
        echo '<span>播放: ';
        echo !empty($video['play'])
            ? htmlspecialchars($video['play'])
            : (isset($video['view'])
                ? (is_numeric($video['view']) ? number_format($video['view']) : htmlspecialchars($video['view']))
                : '');
        echo '</span>';
        echo '<span>弹幕: ' . (isset($video['danmaku']) ? htmlspecialchars($video['danmaku']) : '') . '</span>';
        echo '</div></div></a></div>';
    }

    if (!empty($dynamic['card'])) {
        $card = $dynamic['card'];
        $hasCardContent = !empty($card['title']) || !empty($card['desc']) || !empty($card['pics']);
        if ($hasCardContent) {
            echo '<div class="dynamic-card">';
            if (!empty($card['title'])) {
                echo '<div class="card-title">' . $card['title'] . '</div>';
            }
            if (!empty($card['desc'])) {
                echo '<div class="card-desc">' . $card['desc'] . '</div>';
            }
            if (!empty($card['pics'])) {
                $picCount = count($card['pics']);
                echo '<div class="card-images ' . ($picCount == 1 ? 'single-image' : '') . '">';
                foreach ($card['pics'] as $pic) {
                    echo '<div class="card-image ' . ($picCount == 1 ? 'full-width' : '') . '">';
                    echo '<img src="' . htmlspecialchars($pic) . '" alt="卡片图片" loading="lazy" referrerpolicy="no-referrer">';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
    }
}

// 处理所有动态中的图片URL
foreach ($dynamics as $index => &$dynamic) {
    // 处理动态中的图片
    if (!empty($dynamic['images'])) {
        $processed_images = array();
        foreach ($dynamic['images'] as $img_index => $image) {
            $processed_images[] = proxyBiliImage($image);
        }
        $dynamic['images'] = $processed_images;
    }
    
    // 处理视频封面
    if (!empty($dynamic['video']) && !empty($dynamic['video']['cover'])) {
        $dynamic['video']['cover'] = proxyBiliImage($dynamic['video']['cover']);
    }
    
    // 处理卡片中的图片
    if (!empty($dynamic['card']) && !empty($dynamic['card']['pics'])) {
        $processed_pics = array();
        foreach ($dynamic['card']['pics'] as $pic) {
            $processed_pics[] = proxyBiliImage($pic);
        }
        $dynamic['card']['pics'] = $processed_pics;
    }
}
unset($dynamic);

// 添加调试信息
echo "<!-- 动态组件加载时间: {$component_load_time} -->\n";
echo "<!-- 动态数据数量: " . count($dynamics) . " -->\n";
if (isset($_GET['refresh_dynamic']) && $_GET['refresh_dynamic'] == '1') {
    echo "<!-- 强制刷新模式 -->\n";
}
?>

<!-- === HTML结构部分 === -->
<!-- 整体容器 -->
<div class="bilibili-dynamic-container" style="position: absolute; transform: translateY(-50%); width: 22.5%; z-index: 1;"><!-- 整体容器位置将由JS动态计算 -->
    <!-- 最高图层的图片 -->
    <div class="dynamic-letter-image"></div>
    <!-- 标题栏 -->
    <div class="dynamic-header">
        <h2 class="dynamic-title">最新动态</h2>
    </div>

    <!-- 标题与卡片之间的间距和分割线 -->
    <div class="title-card-spacing">
        <div class="title-divider"></div>
    </div>
    
    <!-- 加载中动画 -->
    <div id="dynamic-loading" style="display: none; text-align: left; padding: 20px;">
        <div style="color: white;">加载中...</div>
    </div>
    
    <!-- 动态列表容器 -->
    <div class="dynamic-list">
        <?php if (empty($dynamics)): ?>
            <!-- 无动态时的提示 -->
            <div class="dynamic-empty">
                <div class="empty-text">整理中</div>
            </div>
        <?php else: ?>
            <?php foreach ($dynamics as $index => $dynamic): ?>
                <!-- 单个动态项 -->
                <div class="dynamic-item <?php echo $dynamic['is_forward'] ? 'is-forward' : ''; ?>" onclick="window.open('https://t.bilibili.com/<?php echo htmlspecialchars($dynamic['id']); ?>', '_blank')" style="cursor: pointer;">
                    <?php if ($dynamic['is_pinned']): ?>
                        <div class="dynamic-pinned">置顶</div>
                    <?php endif; ?>
                    
                    <!-- 转发标识已通过CSS色条区分，不再显示图标 -->
                    
                    <div class="dynamic-content">
                        <?php if (!empty($dynamic['content'])): ?>
                            <div class="dynamic-text"><?php echo $dynamic['content']; ?></div>
                        <?php endif; ?>

                        <?php nagisa_render_dynamic_attachments($dynamic); ?>

                        <?php if (!empty($dynamic['forward_origin'])): ?>
                            <div class="dynamic-forward-card">
                                <?php if (!empty($dynamic['forward_origin']['content'])): ?>
                                    <div class="dynamic-text dynamic-forward-text"><?php echo $dynamic['forward_origin']['content']; ?></div>
                                <?php endif; ?>
                                <?php nagisa_render_dynamic_attachments($dynamic['forward_origin']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="dynamic-time-wrapper">
        <div class="dynamic-time"><?php echo htmlspecialchars($dynamic['time']); ?></div>
    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- 底部空间 -->
    <div class="dynamic-footer"></div>
</div>

<!-- === CSS样式部分 === -->
<style>
/* 自定义字体定义 */
@font-face {
    font-family: 'QiantuHouhei';
    src: url('/assets/webfonts/QIANTUHOUHEI.TTF') format('truetype');
    font-weight: normal;
    font-style: normal;
}

/* 整体容器样式 */
.bilibili-dynamic {
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    border-radius: 12px;
    padding: 20px;
    margin: 20px;
    width: 20vw;
    position: absolute;
    right: 10%;
    bottom: 5%;
    z-index: 2;
    border: 1px solid rgba(255, 255, 255, 0.1);
    text-align: left;
}

/* 动态容器样式 */
.bilibili-dynamic-container {
    background-image: url('/assets/dynamic/new_dynamics_back.png');
    background-size: 100% 100%; /* 确保背景图完全覆盖容器 */
    background-position: center;
    background-repeat: no-repeat;
    border-radius: 16px;
    padding: 15px 15px 15px 15px; /* 恢复原来的内边距 */
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    height: 80vh; /* 设置整体高度为80vh */
    min-height: unset; /* 移除最小高度限制 */
    display: flex;
    flex-direction: column;
    width: 100%; /* 确保容器宽度适合背景图 */
    box-sizing: border-box; /* 确保内边距不会增加容器尺寸 */
    justify-content: space-between; /* 使内容在容器中均匀分布 */
}

/* 标题栏样式 */
.dynamic-header {
    display: flex;
    justify-content: flex-start; /* 保持左对齐 */
    align-items: flex-start; /* 改为顶部对齐 */
    margin: 0; /* 移除所有边距 */
    padding: 0 5px; /* 添加左右内边距 */
    text-align: left;
    position: relative;
    z-index: 5;
    width: 100%;
}

/* 标题与卡片之间的间距 */
.title-card-spacing {
    height: 2vh; /* 调整高度以容纳分割线 */
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* 标题下方分割线 */
.title-divider {
    height: 2px; /* 更粗的线条 */
    width: 90%; /* 更窄的宽度 */
    background-color: #a5a199;
    margin: 0 auto;
    margin-top: -1.25vh; /* 向上移动 */
}

/* 较高图层图片样式 */
.dynamic-letter-image {
    background-image: url('/assets/dynamic/new_dynamics_letter.png');
    background-size: 100%; /* 缩小一半 */
    background-repeat: no-repeat;
    background-position: top right; /* 位于右上角 */
    position: absolute;
    width: 50%; /* 只占用右半部分 */
    height: 50%; /* 只占用上半部分 */
    top: -2.8%; /* 向上移动 */
    right: -5%; /* 向右移动 */
    left: auto; /* 取消左侧定位 */
    z-index: 6; /* 位于卡片列表之上 */
    pointer-events: none; /* 允许点击穿透 */
}

.dynamic-title {
    color: #4D4030;
    font-size: 46px; /* 进一步增大字体大小 */
    margin: 0 0 0 0.5vh; /* 移除顶部边距，只保留左边距 */
    text-align: left;
    font-family: 'QiantuHouhei', sans-serif; /* 保留字体设置 */
    width: auto; /* 保持自动宽度 */
    display: block; /* 保持为块级元素 */
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3); /* 添加文字阴影增强可读性 */
    font-weight: normal; /* 移除加粗效果 */
    user-select: none; /* 防止文字被选中 */
    -webkit-user-select: none; /* Safari 兼容 */
    -moz-user-select: none; /* Firefox 兼容 */
    -ms-user-select: none; /* IE/Edge 兼容 */
}

.view-more {
    color: #FB7299;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s ease;
    position: relative;
    z-index: 10;
}

.view-more:hover {
    color: #ff9eb5;
}

/* 动态列表样式 */
.dynamic-list {
    max-height: calc(80vh - 50px - 2vh - 1vh); /* 调整最大高度，减去标题高度(约50px)、标题与卡片间距(2vh)和底部空间(1vh)的高度 */
    overflow-y: auto;
    padding: 0 10px 0 10px; /* 移除上下内边距 */
    scrollbar-width: none; /* 隐藏Firefox的滚动条 */
    -ms-overflow-style: none; /* 隐藏IE和Edge的滚动条 */
    margin: 0; /* 移除负边距 */
    flex: 0 1 auto; /* 不使用flex:1，避免自动填充剩余空间 */
    position: relative;
    z-index: 5; /* 确保低于图片层级 */
}

/* 隐藏Webkit浏览器的滚动条 */
.dynamic-list::-webkit-scrollbar {
    display: none;
}

/* 动态项样式 */
.dynamic-item {
    background: #373c52; /* 较浅的背景色 */
    border-radius: 16px; /* 大圆角 */
    padding: 12px 12px 12px 12px; /* 减小左右内边距 */
    margin-bottom: 10px; /* 减小卡片间距 */
    position: relative; /* 为左侧色条定位 */
    border: none; /* 移除原有边框 */
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); /* 轻微阴影 */
    transition: all 0.3s ease;
    overflow: hidden; /* 确保色条不超出圆角 */
    cursor: pointer;
    width: 95%; /* 恢复固定宽度 */
    margin-left: auto;
    margin-right: auto;
    height: auto; /* 高度由内容决定 */
    box-sizing: border-box; /* 确保内边距不影响总宽度 */
}

/* 左侧色条 - 添加转发动态的不同色条颜色 */
.dynamic-item::before {
    content: "";
    position: absolute;
    left: 6px; /* 减小左侧距离 */
    top: 12px; /* 减小顶部距离 */
    bottom: 12px; /* 减小底部距离 */
    width: 8px; /* 减小色条宽度 */
    background: #e9b384; /* 默认色条颜色 */
    border-radius: 4px; /* 调整圆角以匹配减小的宽度 */
}

/* 转发动态的色条颜色 */
.dynamic-item.is-forward::before {
    background: #C0C5C6; /* 转发动态使用灰色 */
}

.dynamic-item:hover {
    background: rgba(45, 49, 66, 0.9); /* 悬停时变得更深，但保持一定透明度 */
    transform: translateX(3px); /* 改为向右偏移 */
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

/* 确保链接不会影响整体点击 */
.dynamic-item a {
    pointer-events: none;
}

/* 视频和卡片内容需要单独处理点击事件 */
.dynamic-video a,
.dynamic-card a {
    pointer-events: auto;
}

.dynamic-item:last-child {
    margin-bottom: 0;
}

/* 动态内容样式 */
.dynamic-content {
    color: #e0e0e0; /* 浅色文字，适合深色背景 */
    line-height: 1.6;
    padding-left: 10px; /* 保持左侧内边距 */
    position: relative;
    z-index: 5; /* 高于时间文本的图层 */
    min-height: auto; /* 高度由内容决定 */
    padding-bottom: 0; /* 移除底部内边距 */
}

/* 动态文本样式 */
.dynamic-text {
    color: #ffffff; /* 白色文字，增加对比度 */
    font-size: 16px;
    line-height: 1.6;
    margin-bottom: 10px;
    word-wrap: break-word;
    text-align: left;
    white-space: pre-line; /* 保留换行符 */
}

/* 图片网格样式 */
.dynamic-images {
    display: grid;
    grid-template-columns: repeat(3, 1fr); /* 固定为3列 */
    gap: 8px;
    margin-bottom: 10px;
    max-height: 600px; /* 限制最大高度，避免过多图片占用过多空间 */
    overflow-y: auto; /* 超出部分可滚动 */
}

/* 当图片数量超过9张时添加滚动条样式 */
.dynamic-images::-webkit-scrollbar {
    width: 4px;
}

.dynamic-images::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 4px;
}

.dynamic-images::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
}

.dynamic-images::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* 单张图片的容器样式 */
.dynamic-images.single-image {
    grid-template-columns: 1fr; /* 单张图片时只有一列 */
    max-height: none; /* 单张图片不限制容器高度 */
    overflow-y: visible; /* 单张图片不需要滚动 */
}

.dynamic-image {
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
}

/* 单张图片时的样式 */
.dynamic-image.full-width {
    aspect-ratio: auto; /* 不限制宽高比 */
    max-height: 300px; /* 限制最大高度 */
}

.dynamic-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: top; /* 确保显示图片顶部 */
}

/* 单张图片时的图片样式 */
.dynamic-image.full-width img {
    object-fit: contain; /* 保持图片比例 */
    object-position: top; /* 从顶部开始显示 */
    max-height: 300px; /* 限制最大高度 */
}

.dynamic-image-more {
    aspect-ratio: 1;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    font-weight: bold;
}

/* 视频样式 */
.dynamic-video {
    margin-bottom: 10px;
}

.dynamic-video a {
    text-decoration: none;
    color: inherit;
    display: block;
}

.video-cover {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 8px;
}

.video-cover img {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

.video-duration {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}

.video-play-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.7);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.video-cover:hover .video-play-icon {
    opacity: 1;
}

.video-info {
    color: white;
    text-align: left;
}

.video-title {
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 5px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-align: left;
}

.video-stats {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
    text-align: left;
}

.video-stats span {
    margin-right: 10px;
}

/* 卡片样式 */
.dynamic-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
}

.card-title {
    color: white;
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 5px;
    text-align: left;
}

.card-desc {
    color: rgba(255, 255, 255, 0.8);
    font-size: 14px;
    line-height: 1.4;
    margin-bottom: 8px;
    text-align: left;
    white-space: pre-line; /* 保留换行符 */
}

/* 卡片图片样式 */
.card-images {
    display: grid;
    grid-template-columns: repeat(3, 1fr); /* 固定为3列 */
    gap: 6px;
}

/* 单张卡片图片的容器样式 */
.card-images.single-image {
    grid-template-columns: 1fr; /* 单张图片时只有一列 */
}

.card-image {
    aspect-ratio: 1;
    border-radius: 6px;
    overflow: hidden;
}

/* 单张卡片图片时的样式 */
.card-image.full-width {
    aspect-ratio: auto; /* 不限制宽高比 */
    max-height: 300px; /* 限制最大高度 */
}

.card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: top; /* 确保显示图片顶部 */
}

/* 单张卡片图片时的图片样式 */
.card-image.full-width img {
    object-fit: contain; /* 保持图片比例 */
    object-position: top; /* 从顶部开始显示 */
    max-height: 300px; /* 限制最大高度 */
}

/* 时间包装器样式 */
.dynamic-time-wrapper {
    position: absolute;
    z-index: 4; /* 低于动态文本的图层 */
    bottom: -20px; /* 向下移动，位于卡片底部偏下位置 */
    right: 10px; /* 右对齐 */
    max-width: 60%; /* 限制最大宽度 */
    overflow: hidden; /* 超出部分隐藏 */
    white-space: nowrap; /* 防止换行 */
    text-overflow: ellipsis; /* 超出部分显示省略号 */
}

/* 时间样式 */
.dynamic-time {
    color: rgba(255, 255, 255, 0.3); /* 更暗淡的颜色 */
    font-size: 36px; /* 巨大的字体大小 */
    text-align: right;
    font-weight: bold; /* 加粗显示 */
    position: relative;
    overflow: hidden; /* 超出部分隐藏 */
    background: transparent; /* 删除背景色 */
    text-shadow: none; /* 移除字体阴影 */
}

/* 空状态样式 */
.dynamic-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: calc(80vh - 50px - 3vh);
    width: 100%;
    text-align: center;
}

.empty-text {
    font-family: 'QiantuHouhei', sans-serif;
    font-size: 42px;
    color: #4D4030;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
    letter-spacing: 0.08em;
    user-select: none;
    -webkit-user-select: none;
}

/* 响应式设计 */
@media (max-width: 768px) {
    .bilibili-dynamic {
        margin: 10px;
        padding: 15px;
        width: 90vw;
        position: relative;
        right: auto;
        bottom: auto;
    }
    
    .dynamic-title {
        font-size: 30px; /* 进一步增大响应式设计中的字体大小 */
        text-align: left;
        color: #4D4030;
        text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.2);
        font-weight: normal;
    }

    .empty-text {
        font-size: 28px;
    }
    
    .dynamic-text {
        font-size: 14px;
        text-align: left;
    }
    
    /* 调整移动设备上的图片网格 */
    .dynamic-images {
        grid-template-columns: repeat(3, 1fr); /* 保持3列布局 */
        gap: 4px; /* 减小间距 */
        max-height: 400px; /* 减小最大高度 */
    }
    
    /* 单张图片在移动设备上的样式 */
    .dynamic-image.full-width, .card-image.full-width {
        max-height: 200px; /* 减小最大高度 */
    }
    
    .dynamic-image.full-width img, .card-image.full-width img {
        max-height: 200px; /* 减小最大高度 */
    }
    
    /* 调整卡片图片网格 */
    .card-images {
        grid-template-columns: repeat(3, 1fr); /* 保持3列布局 */
        gap: 4px; /* 减小间距 */
        max-height: 400px; /* 减小最大高度 */
    }
    
    .video-cover img {
        height: 100px;
    }
    
    .dynamic-time {
        font-size: 30px; /* 响应式设计中的更大字体 */
    }
}

.dynamic-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.dynamic-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

/* 置顶标签样式 */
.dynamic-pinned {
    background: linear-gradient(135deg, #fb7299, #ff9eb5);
    color: #fff;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    position: absolute;
    top: 16px;
    right: 16px;
    z-index: 2;
    font-weight: bold;
    box-shadow: 0 2px 4px rgba(251, 114, 153, 0.3);
}

/* 转发原文容器：上侧粗灰条 + 边框盒子 */
.dynamic-forward-card {
    margin-top: 12px;
    padding: 10px 12px;
    background: rgba(192, 197, 198, 0.08);
    border: 1px solid rgba(192, 197, 198, 0.35);
    border-top: 3px solid #C0C5C6;
    border-radius: 8px;
}

.dynamic-forward-card .dynamic-text:last-child,
.dynamic-forward-card .dynamic-video:last-child,
.dynamic-forward-card .dynamic-card:last-child,
.dynamic-forward-card .dynamic-images:last-child {
    margin-bottom: 0;
}

.dynamic-forward-card .dynamic-text:first-child,
.dynamic-forward-card .dynamic-images:first-child,
.dynamic-forward-card .dynamic-video:first-child,
.dynamic-forward-card .dynamic-card:first-child {
    margin-top: 0;
}

.dynamic-forward-text {
    color: rgba(255, 255, 255, 0.88);
}

.dynamic-forward-card .dynamic-video,
.dynamic-forward-card .dynamic-card {
    background: rgba(255, 255, 255, 0.03);
}

.dynamic-images img {
    width: 100%;
    height: 100%; /* 保持原来的高度设置 */
    object-fit: cover;
    border-radius: 4px;
}

.dynamic-video {
    margin-top: 10px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.05); /* 深色背景 */
    border-radius: 8px;
}

.dynamic-card {
    margin-top: 10px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.05); /* 深色背景 */
    border-radius: 8px;
}

.no-dynamics {
    text-align: left;
    color: rgba(255, 255, 255, 0.6); /* 浅色文字 */
    padding: 20px;
}

/* 添加底部空间 */
.dynamic-footer {
    height: calc(1vh); /* 设置为容器高度的1%，容器高度为75vh */
    margin-top: auto; /* 将其推到容器底部 */
}

/* 移除图片错误样式 */
/* .dynamic-image.image-error {
    background-color: rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.dynamic-image.image-error img {
    width: auto;
    height: auto;
    max-width: 50%;
    max-height: 50%;
} */
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 现有代码
    const dynamicList = document.querySelector('.dynamic-list');
    
    if (dynamicList) {
        // 设置滚动行为
        dynamicList.style.overflowY = 'auto';
        dynamicList.style.overflowX = 'hidden';
        
        // 添加滚轮事件处理
        dynamicList.addEventListener('wheel', function(e) {
            // 阻止事件冒泡，防止其他处理函数干扰
            e.stopPropagation();
            
            // 计算滚动量
            var delta = e.deltaY || -e.wheelDelta || e.detail;
            var scrollSpeed = 50; // 调整滚动速度
            
            // 根据滚轮方向滚动
            if (delta > 0) {
                // 向下滚动
                dynamicList.scrollTop += scrollSpeed;
            } else {
                // 向上滚动
                dynamicList.scrollTop -= scrollSpeed;
            }
            
            // 只有在dynamicList外部点击时才阻止默认行为
            if (e.target === dynamicList || dynamicList.contains(e.target)) {
                // 在dynamicList内部，不阻止默认行为
            } else {
                // 在dynamicList外部，阻止默认行为
                e.preventDefault();
            }
        }, { passive: false });
    }
    
    // 初始化位置
    calculateDynamicPosition();
    
    // 在窗口大小调整时重新计算位置
    window.addEventListener('resize', calculateDynamicPosition);
    
    // 在页面滚动时重新计算位置
    window.addEventListener('scroll', calculateDynamicPosition);
    
    // 添加自动刷新功能
    setupAutoRefresh();
});

// 添加自动刷新功能
function setupAutoRefresh() {
    // 每5分钟自动刷新一次动态数据
    const refreshInterval = 5 * 60 * 1000; // 5分钟
    
    // 定期刷新函数
    function refreshDynamics() {
        console.log('自动刷新动态数据...');
        
        // 创建一个新的XMLHttpRequest对象
        const xhr = new XMLHttpRequest();
        
        // 强制绕过服务端动态缓存
        const url = window.location.pathname + '?refresh_dynamic=1&_=' + new Date().getTime();
        
        // 配置请求
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        // 添加防缓存头
        xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        xhr.setRequestHeader('Pragma', 'no-cache');
        xhr.setRequestHeader('Expires', '0');
        
        // 处理响应
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(xhr.responseText, 'text/html');
                const newDynamicList = doc.querySelector('.dynamic-list');
                const currentDynamicList = document.querySelector('.dynamic-list');

                if (!newDynamicList || !currentDynamicList) {
                    return;
                }

                const hasNewItems = newDynamicList.querySelector('.dynamic-item') !== null;
                const hasCurrentItems = currentDynamicList.querySelector('.dynamic-item') !== null;

                // API 偶发返回空列表时，保留当前已展示的内容
                if (!hasNewItems && hasCurrentItems) {
                    console.warn('刷新未获取到新动态，保留当前内容');
                    return;
                }

                currentDynamicList.innerHTML = newDynamicList.innerHTML;
                console.log('动态数据已更新');
            } else {
                console.error('刷新动态数据失败:', xhr.statusText);
            }
        };
        
        // 处理错误
        xhr.onerror = function() {
            console.error('刷新动态数据请求失败');
        };
        
        // 发送请求
        xhr.send();
    }
    
    // 供通知组件或其他模块触发立即刷新
    window.refreshBilibiliDynamics = refreshDynamics;
    
    // 通知检测到新动态时立即刷新页面列表
    document.addEventListener('dynamicUpdated', function() {
        refreshDynamics();
    });
    
    // 设置定时器
    setInterval(refreshDynamics, refreshInterval);
    
    // 页面可见性变化时刷新
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            // 页面变为可见时刷新数据
            refreshDynamics();
        }
    });
    
}

// 全局函数：动态计算并设置B站动态组件位置
function calculateDynamicPosition() {
    const blackboardContainer = document.querySelector('.blackboard-container');
    const dynamicContainer = document.querySelector('.bilibili-dynamic-container');
    
    if (blackboardContainer && dynamicContainer) {
        // 获取黑板容器的位置和尺寸信息
        const blackboardRect = blackboardContainer.getBoundingClientRect();
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;
        
        // 计算黑板右侧到窗口右侧的距离
        const blackboardRightEdge = blackboardRect.right;
        const spaceToRight = windowWidth - blackboardRightEdge;
        
        // 考虑组件自身宽度进行水平居中计算
        const dynamicContainerWidth = dynamicContainer.offsetWidth;
        
        // 计算动态组件的left值 - 黑板右侧 + (剩余空间 - 动态组件宽度)/2
        const leftValue = blackboardRightEdge + (spaceToRight - dynamicContainerWidth)/2;
        
        // 应用位置设置
        dynamicContainer.style.left = `${leftValue}px`;
        dynamicContainer.style.right = ''; // 清除right值
        dynamicContainer.style.top = '52.5%';
        
        // 动态调整字体大小
        adjustFontSizes(dynamicContainer, dynamicContainerWidth);
    }
}

// 字体大小动态调整函数
function adjustFontSizes(container, containerWidth) {
    if (!container) return;
    
    // 基础尺寸系数 - 根据容器宽度计算基础字体大小
    const baseSize = Math.max(containerWidth / 35, 12);
    
    // 确保表情图片大小适合文本，并且更大一些
    const emoteImages = container.querySelectorAll('.bili-emote');
    emoteImages.forEach(img => {
        if (!img.referrerPolicy) {
            img.referrerPolicy = 'no-referrer';
        }
        img.style.maxHeight = `${Math.max(baseSize * 2, 40)}px`;
        img.style.maxWidth = `${Math.max(baseSize * 3, 120)}px`;
        img.style.verticalAlign = 'middle';
        img.style.display = 'inline-block';
        img.style.margin = '0 2px';
        // 移除title属性，避免悬停时显示表情名称
        img.removeAttribute('title');
    });
    
    // 调整标题字体大小
    const titleElements = container.querySelectorAll('.dynamic-title');
    titleElements.forEach(el => {
        el.style.fontSize = `${Math.max(baseSize * 2.2, 28)}px`; // 进一步增大字体大小系数
        el.style.color = '#4D4030';
        el.style.textShadow = '1px 1px 2px rgba(0, 0, 0, 0.3)';
        el.style.fontWeight = 'normal'; // 确保移除加粗效果
    });
    
    // 调整"查看更多"链接字体大小
    const viewMore = container.querySelector('.view-more');
    if (viewMore) {
        viewMore.style.fontSize = `${Math.max(baseSize * 0.9, 14)}px`;
    }
    
    // 调整动态内容字体大小
    const textElements = container.querySelectorAll('.dynamic-text');
    textElements.forEach(el => {
        el.style.fontSize = `${Math.max(baseSize * 1.1, 16)}px`;
    });
    
    // 调整视频标题字体大小
    const videoTitles = container.querySelectorAll('.video-title');
    videoTitles.forEach(el => {
        el.style.fontSize = `${Math.max(baseSize, 14)}px`;
    });
    
    // 调整视频统计信息字体大小
    const videoStats = container.querySelectorAll('.video-stats');
    videoStats.forEach(el => {
        el.style.fontSize = `${Math.max(baseSize * 0.8, 12)}px`;
    });
    
    // 调整时间戳字体大小
    const timeElements = container.querySelectorAll('.dynamic-time');
    timeElements.forEach(el => {
        el.style.fontSize = `${Math.max(baseSize * 2.5, 28)}px`; // 巨大的字体大小
        el.style.fontWeight = 'bold'; // 加粗显示
        el.style.textShadow = 'none'; // 移除字体阴影
        el.style.color = 'rgba(255, 255, 255, 0.3)'; // 更暗淡的颜色
    });
    
    // 调整卡片标题和描述字体大小
    const cardTitles = container.querySelectorAll('.card-title');
    cardTitles.forEach(el => {
        el.style.fontSize = `${Math.max(baseSize * 1.1, 16)}px`;
    });
    
    const cardDescs = container.querySelectorAll('.card-desc');
    cardDescs.forEach(el => {
        el.style.fontSize = `${Math.max(baseSize, 14)}px`;
    });
}
</script>