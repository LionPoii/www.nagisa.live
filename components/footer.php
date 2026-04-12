<?php
// 设置页脚文本，可以从数据库加载或使用默认值
$footer_text = '© ' . date('Y') . '非官方粉丝网站 Design By LionPoii';

// 获取页脚内容数据
$footer_thanks = '加载中，请稍等...';
$footer_links = '加载中，请稍等...';
$footer_updates = '加载中，请稍等...';
$footer_feedback = '加载中，请稍等...';

// 直接设置备案信息，格式：显示文本|链接地址
$beian_text = '粤ICP备2025466395号|https://beian.miit.gov.cn';

// 检查是否是管理员
$isAdmin = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin';

// 如果数据库连接存在，从数据库读取内容
if (isset($conn)) {
    // 读取页脚文本
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'footer_text'");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($config && !empty($config['config_value'])) {
        $footer_text = $config['config_value'];
    }
    
    // 读取鸣谢内容
    $stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = 'footer_thanks'");
    $stmt->execute();
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($content && !empty($content['content_value'])) {
        $footer_thanks = $content['content_value'];
    }
    
    // 读取友站连接 (修改: 支持"站点名称|链接地址"格式)
    $stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = 'footer_links'");
    $stmt->execute();
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($content && !empty($content['content_value'])) {
        $footer_links = $content['content_value'];
    }
    
    // 读取更新日志
    $stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = 'footer_updates'");
    $stmt->execute();
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($content && !empty($content['content_value'])) {
        $footer_updates = $content['content_value'];
    }
    
    // 读取反馈内容
    $stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = 'footer_feedback'");
    $stmt->execute();
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($content && !empty($content['content_value'])) {
        $footer_feedback = $content['content_value'];
    }
}

// 将内容转为JavaScript安全字符串，禁用Unicode转义，保持中文原样
$footer_thanks_js = json_encode($footer_thanks, JSON_UNESCAPED_UNICODE);
$footer_links_js = json_encode($footer_links, JSON_UNESCAPED_UNICODE);
$footer_updates_js = json_encode($footer_updates, JSON_UNESCAPED_UNICODE);
$footer_feedback_js = json_encode($footer_feedback, JSON_UNESCAPED_UNICODE);
?>

<?php require_once __DIR__ . '/feedback_form.php'; ?>

<!-- 固定页脚 -->
<div id="site-footer" class="fixed-footer">
    <div class="footer-container">
        <!-- 添加备案号区域 -->
        <div class="beian-container">
            <?php if (!empty($beian_text)): ?>
                <?php
                    // 解析备案号文本，格式：显示文本|链接地址
                    $beian_parts = explode('|', $beian_text, 2);
                    $beian_display = trim($beian_parts[0]);
                    $beian_url = isset($beian_parts[1]) ? trim($beian_parts[1]) : '';
                ?>
                <?php if (!empty($beian_url)): ?>
                    <a href="<?php echo htmlspecialchars($beian_url); ?>" target="_blank" class="beian-link"><?php echo htmlspecialchars($beian_display); ?></a>
                <?php else: ?>
                    <span class="beian-text"><?php echo htmlspecialchars($beian_display); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="footer-nav">
            <div class="footer-nav-item" onclick="showFooterContent('thanks', <?php echo htmlspecialchars($footer_thanks_js, ENT_QUOTES, 'UTF-8'); ?>, '/assets/uploads/thanks/aimi.png')"> 
                <span class="footer-nav-icon">
                    <img src="/elements/Footer/icon/thanks.png" alt="特别鸣谢" class="footer-icon-img">
                </span>
                <span class="footer-nav-text">特别鸣谢</span>
            </div>
            <div class="footer-nav-item" onclick="showFooterContent('links', <?php echo htmlspecialchars($footer_links_js, ENT_QUOTES, 'UTF-8'); ?>)">
                <span class="footer-nav-icon">
                    <img src="/elements/Footer/icon/link.png" alt="友站连接" class="footer-icon-img">
                </span>
                <span class="footer-nav-text">友站连接</span>
            </div>
            <div class="footer-nav-item" onclick="window.open('/SecWeb/changelog.php', '_blank')">
                <span class="footer-nav-icon">
                    <img src="/elements/Footer/icon/notes.png" alt="更新日志" class="footer-icon-img">
                </span>
                <span class="footer-nav-text">更新日志</span>
            </div>
            <div class="footer-nav-item" onclick="showFooterContent('feedback', <?php echo htmlspecialchars($footer_feedback_js, ENT_QUOTES, 'UTF-8'); ?>)">
                <span class="footer-nav-icon">
                    <img src="/elements/Footer/icon/feedback.png" alt="改进反馈" class="footer-icon-img">
                </span>
                <span class="footer-nav-text">改进反馈</span>
            </div>
            <div class="footer-nav-item" onclick="window.open('admin/', '_blank')">
                <span class="footer-nav-icon">
                    <img src="/elements/Footer/icon/system.png" alt="后台设置" class="footer-icon-img">
                </span>
                <span class="footer-nav-text">后台设置</span>
            </div>
        </div>
        <div class="footer-nav-item copyright-item" onclick="showCustomModal('© <?php echo date('Y'); ?> 米汀Nagisa-非官方粉丝网站 ')">
            <span class="copyright-symbol">©</span><!-- 修改: 简化版权显示为仅©符号 -->
        </div>
    </div>
</div>

<!-- 页脚内容模态窗口 -->
<div id="footerContentModal" class="footer-modal">
    <div class="footer-modal-content">
        <span class="footer-modal-close" onclick="closeFooterModal()">&times;</span>
        <div class="footer-modal-title" id="footerModalTitle">内容标题</div>
        <div class="footer-modal-body" id="footerModalBody"></div>
    </div>
</div>

<!-- 友站链接弹出窗口 (新增: 独立的友站链接浮窗) -->
<div id="friendlinkPopup" class="friendlink-popup">
    <div class="popup-close" onclick="closeFriendlinkPopup()"></div>
    <div class="popup-title" style="display:none;">友情连接</div>
    <div class="popup-content" id="friendlinkContent"></div>
</div>
<div id="friendlinkOverlay" class="popup-overlay" onclick="closeFriendlinkPopup()"></div>

<style>
/* 页脚样式 */
.fixed-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 5vh; /* 明确设置高度为5vh */
    padding: 0 10px; /* 减少垂直内边距以适应固定高度 */
    background: rgba(77, 64, 48, 0.8); /* 与header颜色匹配 */
    color: #ffffff;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 -4px 30px rgba(0, 0, 0, 0.3);
    transition: all 0.8s cubic-bezier(0.19, 1, 0.22, 1); /* 更平滑的动画曲线 */
    z-index: 1000; /* 与header相同的z-index */
    backdrop-filter: blur(5px); /* 背景模糊效果 */
    opacity: 0;
    transform: translateY(100%);
}

.footer-container {
    display: flex;
    justify-content: space-between; /* 保持两端对齐，左侧放备案号，右侧放导航 */
    align-items: center;
    width: 100%;
    height: 100%; /* 确保容器高度填满页脚 */
    padding: 0 5%; /* 移除垂直内边距，只保留水平内边距 */
}

/* 备案号容器样式 */
.beian-container {
    display: flex;
    align-items: center;
    height: 100%;
    flex: 1; /* 让备案号容器占据剩余空间 */
    padding-left: 20px; /* 增加左侧内边距 */
}

.beian-link, .beian-text {
    font-size: 1rem; /* 增大字体大小 */
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-family: 'QiantuHouhei', sans-serif;
    transition: all 0.3s ease;
    letter-spacing: 1px; /* 增加字间距 */
    padding: 0 10px; /* 增加内边距 */
}

.beian-link:hover {
    color: #cc9471;
    text-decoration: none; /* 移除下划线 */
}

.footer-nav {
    display: flex;
    justify-content: flex-end; /* 确保导航项靠右对齐 */
    gap: 60px; /* 增加项目之间的间距 */
    height: 100%; /* 确保导航栏高度填满页脚容器 */
    align-items: center; /* 确保导航项垂直居中 */
    flex-shrink: 0; /* 防止导航栏被压缩 */
}

.footer-nav-item {
    display: flex;
    flex-direction: row;
    align-items: center;
    cursor: pointer;
    padding: 0 12px; /* 只保留水平内边距 */
    border-radius: 8px;
    transition: all 0.3s ease;
    position: relative;
    user-select: none;
    height: 80%; /* 设置导航项高度以适应页脚高度 */
}

.footer-nav-item:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-3px);
}

.footer-nav-item:active {
    transform: translateY(-1px);
}

.footer-nav-icon {
    font-size: 1.2rem;
    transition: all 0.3s ease;
    margin-right: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.footer-icon-img {
    width: 20px;
    height: 20px;
    object-fit: contain;
    transition: all 0.3s ease;
    filter: brightness(1.8); /* 提高亮度使图标在深色背景上更清晰 */
}

.footer-nav-item:hover .footer-icon-img {
    transform: scale(1.2);
    filter: brightness(2.2) drop-shadow(0 0 3px rgba(204, 148, 113, 0.8));
}

.footer-nav-text {
    font-size: 0.9rem;
    font-family: 'QiantuHouhei', sans-serif;
    letter-spacing: 2px;
}

/* 版权图标特殊样式 */
.copyright-item {
    padding: 8px;
    opacity: 0.7;
    margin-left: 0; /* 移除左侧自动间距 */
    margin-right: 10px; /* 增加右侧间距 */
}

.copyright-symbol {
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.8);
}

.copyright-item:hover {
    opacity: 1;
}

.copyright-item:hover .copyright-symbol {
    color: #cc9471;
}

/* 添加鼠标悬停时的发光效果 */
.footer-nav-item::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 8px;
    box-shadow: 0 0 15px rgba(204, 148, 113, 0); /* 初始无阴影 */
    transition: box-shadow 0.3s ease;
    pointer-events: none;
}

.footer-nav-item:hover::after {
    box-shadow: 0 0 15px rgba(204, 148, 113, 0.6); /* 悬停时添加发光效果 */
}

.footer-nav-item:hover .footer-nav-icon {
    transform: scale(1.2);
    color: #cc9471; /* 与网站强调色匹配 */
}

/* 添加鼠标悬浮效果 */
.fixed-footer:hover {
    background: rgba(77, 64, 48, 0.9);
    box-shadow: 0 -5px 15px rgba(77, 64, 48, 0.5);
}

/* 页脚内容模态窗口样式 */
.footer-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 2000;
    overflow: auto;
    animation: fadeIn 0.3s ease; /* 添加淡入动画 */
    backdrop-filter: blur(5px); /* 添加背景模糊效果 */
    user-select: none !important; /* 禁止文本选择 */
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.footer-modal-content {
    position: relative;
    background-color: #ffffff; /* 从深色背景改为白色背景 */
    margin: 0 auto; /* 移除顶部间距 */
    margin-top: 0; /* 移除顶部边距 */
    top: 50%; /* 垂直居中定位 */
    transform: translateY(-50%); /* 垂直居中定位 */
    padding: 20px;
    width: auto; /* 从固定宽度改为自动宽度 */
    min-width: 300px; /* 设置最小宽度 */
    max-width: 500px; /* 减小最大宽度从800px到500px */
    border-radius: 10px;
    box-shadow: 0 5px 30px rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s ease; /* 改为淡入动画 */
    border: 1px solid rgba(204, 148, 113, 0.3); /* 淡化边框颜色 */
    backdrop-filter: blur(10px);
    color: #333333; /* 文本颜色从白色改为深灰色以适应白色背景 */
    user-select: none !important; /* 禁止文本选择 */
}



/* 移除调试用边框 */

@keyframes fadeIn { /* 修改: 淡入动画 */
    from { opacity: 0; }
    to { opacity: 1; }
}

.footer-modal-close {
    position: absolute;
    top: 10px;
    right: 20px;
    font-size: 28px;
    font-weight: bold;
    color: #333333; /* 从白色改为深灰色 */
    cursor: pointer;
    transition: all 0.3s ease;
}

.footer-modal-close:hover {
    color: #cc9471; /* 保持原来的悬停颜色 */
}

.footer-modal-title {
    margin-bottom: 15px;
    padding-bottom: 10px;
    font-size: 22px;
    font-weight: normal; /* 从bold改为normal，移除粗体 */
    border-bottom: 1px solid rgba(0, 0, 0, 0.1); /* 边框颜色适应白色背景 */
    text-align: center;
    font-family: 'QiantuHouhei', sans-serif;
    letter-spacing: 2px;
    color: #4D4030; /* 标题颜色改为棕色主题色 */
    user-select: none !important; /* 新增: 禁止文本选择 */
}

.footer-modal-body {
    max-height: 60vh;
    overflow-y: auto;
    padding: 10px;
    line-height: 1.6;
    font-size: 16px;
    word-wrap: break-word; /* 确保长文本自动换行 */
    white-space: pre-wrap; /* 保留空格和换行符并自动换行 */
    user-select: none !important; /* 禁止文本选择 */
    -webkit-user-select: none !important; /* Safari 支持 */
    -moz-user-select: none !important; /* Firefox 支持 */
    -ms-user-select: none !important; /* IE/Edge 支持 */
}

/* 自定义滚动条样式 */
.footer-modal-body::-webkit-scrollbar {
    width: 8px;
}

.footer-modal-body::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05); /* 滚动条轨道颜色适应白色背景 */
    border-radius: 10px;
}

.footer-modal-body::-webkit-scrollbar-thumb {
    background: rgba(204, 148, 113, 0.5); /* 滚动条颜色调淡 */
    border-radius: 10px;
}

.footer-modal-body::-webkit-scrollbar-thumb:hover {
    background: rgba(204, 148, 113, 0.7); /* 滚动条悬停颜色调整 */
}

/* 友站链接表格样式 */
.friendlinks-container {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    width: auto;
    max-width: 250px; /* 最大宽度从400px缩小到250px */
    min-width: 150px; /* 最小宽度从200px缩小到150px */
    margin: 20px auto 0;
    background-color: #ffffff; /* 背景颜色改回纯白色 */
    border-radius: 10px;
    padding: 20px 18px; /* 内边距从25px减小到18px，使容器更紧凑 */
    position: relative;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); /* 添加轻微阴影提升层次感 */
}

/* 删除友情链接标题 */
.friendlinks-container::before {
    display: none; /* 隐藏友情链接标题文本 */
}

.friendlink-item {
    margin: 8px 0;
    text-align: center; /* 文本对齐从左对齐改为居中 */
    border-bottom: 1px dashed rgba(0, 0, 0, 0.1); /* 分隔线颜色调整为适合白色背景 */
    padding-bottom: 8px;
    transition: all 0.3s ease;
}

.friendlink-item:last-child {
    border-bottom: none;
}

.friendlink-name {
    color: #333333; /* 文本颜色从白色改为深灰色，适合白色背景 */
    font-size: 16px;
    text-decoration: none;
    padding: 5px 0;
    transition: all 0.3s ease;
    display: block;
    text-align: center; /* 确保文本居中显示 */
}

.friendlink-name:hover {
    color: #e8a274; /* 保持悬停颜色不变 */
    transform: translateY(-3px); /* 鼠标悬停时从向右移动改为向上移动 */
}

/* 友站浮窗样式 */
.friendlink-popup {
    position: fixed;
    bottom: auto; /* 移除固定底部位置 */
    left: auto; /* 移除固定左侧位置 */
    right: auto;
    transform: translateX(-50%); /* 保留水平居中转换 */
    background-color: #ffffff;
    border-radius: 10px;
    padding: 15px 20px;
    padding-top: 30px;
    max-width: 85%;
    width: auto;
    max-height: 80vh;
    z-index: 2001;
    box-shadow: 0 5px 30px rgba(0, 0, 0, 0.2);
    display: none;
    animation: popupSlideUp 0.3s ease;
    border: 1px solid rgba(204, 148, 113, 0.2);
}

@keyframes popupSlideUp {
    from { opacity: 0; transform: translate(-50%, 20px); }
    to { opacity: 1; transform: translate(-50%, 0); }
}

.popup-title {
    text-align: center;
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
    font-family: 'QiantuHouhei', sans-serif;
    letter-spacing: 2px;
}

.popup-content {
    max-height: 60vh;
    overflow-y: auto;
    padding-right: 5px;
    background-color: #ffffff; /* 内容区域背景颜色设为白色 */
    border-radius: 8px; /* 添加圆角 */
    padding: 10px; /* 添加内边距 */
}

.popup-close {
    position: absolute;
    top: 10px; /* 位置调整 */
    left: 50%; /* 水平居中 */
    transform: translateX(-50%); /* 水平居中 */
    width: 20px;
    height: 20px; /* 调整高度 */
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.popup-close:hover {
    opacity: 0.7; /* 简化悬停效果 */
}

.popup-close::before {
    content: '';
    position: absolute;
    width: 0;
    height: 0;
    border-left: 8px solid transparent; /* 创建三角形左边 */
    border-right: 8px solid transparent; /* 创建三角形右边 */
    border-top: 8px solid rgba(77, 64, 48, 0.8); /* 创建三角形底边并设置颜色 */
}

.popup-close::after {
    display: none; /* 隐藏第二条线，只用before创建向下箭头 */
}

.popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 2000;
    display: none;
}

@media (max-width: 1200px) {
    .footer-nav {
        gap: 30px;
    }
    
    /* 确保保持5vh高度 */
    .fixed-footer {
        height: 5vh;
    }
    
    .beian-link, .beian-text {
        font-size: 0.9rem; /* 稍微减小字体大小，但仍然比原来大 */
    }
}

@media (max-width: 768px) {
    .footer-container {
        padding: 0 3%; /* 在平板尺寸下减少内边距 */
    }
    
    .footer-nav {
        gap: 15px;
    }
    
    .footer-nav-item {
        padding: 0 10px; /* 移除垂直内边距 */
        height: 70%; /* 调整高度比例 */
    }
    
    .footer-nav-icon {
        font-size: 1rem;
    }
    
    .footer-icon-img {
        width: 18px;
        height: 18px;
    }
    
    .footer-nav-text {
        font-size: 0.8rem;
        letter-spacing: 1px;
    }
    
    .copyright-symbol {
        font-size: 1rem;
    }
    
    .beian-link, .beian-text {
        font-size: 0.8rem; /* 平板上的字体大小 */
        letter-spacing: 0.5px; /* 减小字间距但仍保持一定间隔 */
    }
    
    /* 确保保持5vh高度 */
    .fixed-footer {
        height: 5vh;
    }
    
    .footer-modal-content {
        width: auto; /* 从固定90%改为自动宽度 */
        min-width: 250px; /* 设置最小宽度 */
        max-width: 90%; /* 设置最大宽度为屏幕的90% */
        margin: 0 auto; /* 移除顶部边距 */
        top: 50%; /* 保持垂直居中 */
        transform: translateY(-50%); /* 保持垂直居中 */
    }
    
    .footer-modal-title {
        font-size: 18px;
    }
    
    .footer-modal-body {
        font-size: 14px;
    }
    
    .friendlink-name {
        font-size: 14px;
    }
}

@media (max-width: 480px) {
    .footer-nav {
        gap: 5px;
    }
    
    .footer-nav-item {
        padding: 0 8px; /* 移除垂直内边距 */
        height: 65%; /* 调整高度比例 */
    }
    
    .footer-nav-icon {
        font-size: 0.9rem;
    }
    
    .footer-nav-text {
        font-size: 0.7rem;
        letter-spacing: 0px;
    }
    
    .copyright-symbol {
        font-size: 0.8rem;
    }
    
    .beian-link, .beian-text {
        font-size: 0.75rem; /* 移动设备上的字体大小 */
        letter-spacing: 0.3px; /* 移动设备上的字间距 */
        padding: 0 5px; /* 减少内边距但仍保持一定间隔 */
    }
    
    /* 确保保持5vh高度 */
    .fixed-footer {
        height: 5vh;
    }
    
    .friendlink-name {
        font-size: 12px;
        padding: 3px 6px;
    }
    
    .friendlink-item {
        margin: 6px 8px;
    }
    
    /* 小屏幕下的模态窗口调整 */
    .footer-modal-content {
        min-width: 200px; /* 减小最小宽度 */
        max-width: 95%; /* 增加最大宽度比例 */
        margin: 0 auto; /* 移除顶部边距 */
        top: 50%; /* 保持垂直居中 */
        transform: translateY(-50%); /* 保持垂直居中 */
        padding: 15px; /* 减小内边距 */
    }
    
    .footer-modal-body {
        padding: 5px; /* 减小内边距 */
        font-size: 13px; /* 减小字体大小 */
    }
}
</style>

<script>
// 显示页脚内容模态窗口
function showFooterContent(type, content) {
    // 确保内容是字符串格式
    if (typeof content === 'string') {
        try {
            // 尝试解析JSON字符串
            content = JSON.parse(content);
        } catch (e) {
            // 如果不是有效的JSON，保持原样
            console.log('内容不是有效的JSON格式');
        }
    }
    

    
    // 调整模态窗口宽度以适应内容
    if (type === 'thanks') {
        // 创建临时元素测量内容宽度
        const tempDiv = document.createElement('div');
        tempDiv.style.position = 'absolute';
        tempDiv.style.visibility = 'hidden';
        tempDiv.style.whiteSpace = 'pre-wrap';
        tempDiv.style.width = 'auto';
        tempDiv.style.maxWidth = 'none';
        tempDiv.innerHTML = content;
        document.body.appendChild(tempDiv);
        
        // 获取内容宽度并设置模态窗口宽度
        const contentWidth = tempDiv.offsetWidth;
        document.body.removeChild(tempDiv);
        
        // 设置模态窗口宽度，但不超过屏幕宽度的80%
        const modalContent = document.querySelector('.footer-modal-content');
        const screenWidth = window.innerWidth;
        const maxWidth = Math.min(contentWidth + 40, screenWidth * 0.8); // 加上内边距
        modalContent.style.width = maxWidth + 'px';
        
        // 确保模态窗口在屏幕中央
        modalContent.style.top = '50%';
        modalContent.style.transform = 'translateY(-50%)';
    }
    
    // 友站链接使用单独的浮窗 (修改: 为友站链接添加单独的浮窗显示)
    if (type === 'links') {
        const popup = document.getElementById('friendlinkPopup');
        const linkButton = document.querySelector('.footer-nav-item[onclick*="links"]');
        document.getElementById('friendlinkContent').innerHTML = content;
        
        // 计算弹出窗口位置，使其显示在按钮正上方
        const buttonRect = linkButton.getBoundingClientRect();
        const buttonCenter = buttonRect.left + buttonRect.width / 2;
        
        // 设置弹出窗口位置
        popup.style.left = buttonCenter + 'px';
        popup.style.bottom = (window.innerHeight - buttonRect.top + 10) + 'px';
        
        popup.style.display = 'block';
        document.getElementById('friendlinkOverlay').style.display = 'block';
        document.body.style.overflow = 'hidden';
        return;
    }
    
    const modal = document.getElementById('footerContentModal');
    const modalTitle = document.getElementById('footerModalTitle');
    const modalBody = document.getElementById('footerModalBody');
    
    // 设置标题和内容
    switch (type) {
        case 'thanks':
            modalTitle.textContent = '特别鸣谢';
            break;
        case 'updates':
            modalTitle.textContent = '更新日志';
            break;
        case 'feedback':
            modalTitle.textContent = '改进反馈';
            break;
        default:
            modalTitle.textContent = '信息';
    }
    
    // 设置内容(支持HTML)并添加禁止选择属性
    modalBody.innerHTML = content;
    modalBody.style.userSelect = 'none';
    modalBody.style.webkitUserSelect = 'none';
    modalBody.style.mozUserSelect = 'none';
    modalBody.style.msUserSelect = 'none';
    

    
    // 显示模态窗口
    modal.style.display = 'block';
    
    // 阻止背景滚动
    document.body.style.overflow = 'hidden';
    
    // 添加ESC键关闭功能
    document.addEventListener('keydown', closeModalOnEsc);
    
    // 表情图片已经改为在内容区域内显示，不需要额外调整位置
}

// ESC键关闭模态窗口
function closeModalOnEsc(e) {
    if (e.key === 'Escape') {
        closeFooterModal();
        closeFriendlinkPopup();
    }
}

// 关闭模态窗口
function closeFooterModal() {
    const modal = document.getElementById('footerContentModal');
    modal.style.display = 'none';
    
    // 恢复背景滚动
    document.body.style.overflow = '';
    
    // 移除ESC键监听
    document.removeEventListener('keydown', closeModalOnEsc);
    
    // 重置模态窗口宽度为自动
    const modalContent = document.querySelector('.footer-modal-content');
    if (modalContent) {
        modalContent.style.width = 'auto';
    }
    

}

// 关闭友站链接浮窗 (新增: 单独的友站链接浮窗关闭函数)
function closeFriendlinkPopup() {
    document.getElementById('friendlinkPopup').style.display = 'none';
    document.getElementById('friendlinkOverlay').style.display = 'none';
    document.body.style.overflow = '';
    document.removeEventListener('keydown', closeModalOnEsc);
}

// 点击模态窗口外部关闭
window.addEventListener('click', function(event) {
    const modal = document.getElementById('footerContentModal');
    if (event.target === modal) {
        closeFooterModal();
    }
});

// 显示自定义简单信息的模态窗口 (新增: 用于显示版权信息的简易模态窗口)
function showCustomModal(text) {
    const modal = document.getElementById('footerContentModal');
    const modalTitle = document.getElementById('footerModalTitle');
    const modalBody = document.getElementById('footerModalBody');
    
    modalTitle.textContent = '关于';
    modalBody.innerHTML = text;
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', closeModalOnEsc);
}

// 确保页脚高度始终保持5vh
function ensureFooterHeight() {
    const footer = document.querySelector('.fixed-footer');
    if (footer) {
        footer.style.height = '5vh';
    }
}

// 页面加载和窗口大小变化时执行
window.addEventListener('load', ensureFooterHeight);
window.addEventListener('resize', function() {
    ensureFooterHeight();
    
    // 窗口大小变化时，如果友站链接弹窗显示，则更新其位置
    const popup = document.getElementById('friendlinkPopup');
    if (popup.style.display === 'block') {
        const linkButton = document.querySelector('.footer-nav-item[onclick*="links"]');
        const buttonRect = linkButton.getBoundingClientRect();
        const buttonCenter = buttonRect.left + buttonRect.width / 2;
        
        popup.style.left = buttonCenter + 'px';
        popup.style.bottom = (window.innerHeight - buttonRect.top + 10) + 'px';
    }
});

// 定期检查页脚高度（以防其他脚本修改）
setInterval(ensureFooterHeight, 1000);

// 绑定"改进反馈"按钮点击事件，弹出反馈表单
window.addEventListener('DOMContentLoaded', function() {
    var feedbackBtns = document.querySelectorAll('.footer-nav-item .footer-nav-text');
    feedbackBtns.forEach(function(item) {
        if(item.innerText === '改进反馈') {
            item.parentElement.onclick = function(e) {
                e.stopPropagation();
                showFeedbackModal();
                
                // 确保反馈窗口在浏览器中央显示
                setTimeout(function() {
                    const feedbackModal = document.getElementById('feedbackModal');
                    if (feedbackModal) {
                        const modalContent = feedbackModal.querySelector('.feedback-modal-content');
                        if (modalContent) {
                            modalContent.style.top = '50%';
                            modalContent.style.transform = 'translateY(-50%)';
                            modalContent.style.margin = '0 auto';
                        }
                    }
                }, 10);
            }
        }
    });
});
</script> 