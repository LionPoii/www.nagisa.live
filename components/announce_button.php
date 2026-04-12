<?php
/**
 * 公告按钮组件
 */

// 引入数据库连接
require_once __DIR__ . '/../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

// 获取激活状态的公告列表，按优先级和开始日期排序
$announcements = [];
$has_announcements = false;

try {
    // 检查表是否存在
    $tableExists = false;
    $checkTable = $conn->query("SHOW TABLES LIKE 'announcements'");
    $tableExists = ($checkTable && $checkTable->rowCount() > 0);
    
    // 如果表存在则获取当前有效的公告
    if ($tableExists) {
        $today = date('Y-m-d');
        $stmt = $conn->prepare("SELECT * FROM announcements 
                              WHERE status = 'active' 
                              AND (is_permanent = 1 OR (start_date <= ? AND end_date >= ?))
                              ORDER BY priority DESC, start_date DESC");
        $stmt->execute([$today, $today]);
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $has_announcements = (count($announcements) > 0);
    }
} catch (PDOException $e) {
    // 出错时不显示公告
    error_log("公告获取错误: " . $e->getMessage(), 0);
}

// 获取最新公告的ID，用于检测是否有新公告
$latest_announcement_id = 0;
if ($has_announcements && count($announcements) > 0) {
    $latest_announcement_id = $announcements[0]['id'];
}

// 检查是否有未读公告
$has_unread_announcements = false;
if ($has_announcements) {
    foreach ($announcements as $announcement) {
        if (!isset($_COOKIE['viewed_announcement_' . $announcement['id']])) {
            $has_unread_announcements = true;
            break;
        }
    }
}

// 按需切换图片
$announceImage = $has_unread_announcements ? "elements/announce/Announce_new.png" : "elements/announce/Announce.png";
?>

<!-- 公告跳转按钮 -->
<a href="#" id="announce-btn" onclick="showAnnounceModal(); return false;" style="text-decoration: none;">
    <div class="announce-container" 
         style="position: absolute; 
                right: 5%; 
                bottom: 5%; 
                height: 5vh; 
                width: auto;
                cursor: pointer;
                transition: transform 0.3s ease;">
        <img src="<?php echo $announceImage; ?>" 
             alt="Announcement Icon" 
             id="announce-image"
             class="announce-image" 
             style="height: 100%; 
                    width: auto;
                    transition: transform 0.3s ease;">
        <div class="announce-text" style="position: absolute; 
                                     top: 120%; 
                                     left: 50%; 
                                     transform: translate(-50%, 0); 
                                     color: #4c526b; 
                                     font-weight: bold; 
                                     text-align: center;
                                     font-size: 1.5rem;
                                     letter-spacing: 0.1em;
                                     user-select: none;
                                     transition: color 0.3s ease;
                                     font-family: 'QiantuHouhei';">
        </div>
    </div>
</a>

<!-- 公告模态窗口 -->
<div id="announce-modal" class="custom-modal">
    <div class="modal-content announce-modal-content">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; position: sticky; top: 0; background-color: #fff; padding-top: 10px; z-index: 2;">
            <h2 class="modal-title" style="font-family: 'KingHwaOldSongv3.0'; color: #4c526b; margin: 0; font-size: 24px; font-weight: bold;">公告</h2>
            <span class="close-button" onclick="closeAnnounceModal()" style="cursor: pointer; font-size: 24px; color: #4c526b;">&times;</span>
        </div>
        <div class="modal-body">
            <div class="announcement-content" id="announcement-content">
                <?php if ($has_announcements): ?>
                    <?php foreach ($announcements as $index => $announcement): ?>
                        <div class="announcement-item <?php echo !isset($_COOKIE['viewed_announcement_' . $announcement['id']]) ? 'unread' : ''; ?>" 
                             data-id="<?php echo $announcement['id']; ?>"
                             data-content="<?php echo htmlspecialchars(str_replace("\r\n", "\n", $announcement['content'])); ?>"
                             onclick="showAnnouncementDetail(this)"
                             style="margin-bottom: 20px; padding: 15px 5px 15px 10px; background-color: <?php echo $index % 2 === 0 ? '#f9f9f9' : '#fff'; ?>; border-radius: 8px; border-left: 4px solid <?php echo !isset($_COOKIE['viewed_announcement_' . $announcement['id']]) ? '#ff5252' : '#cc9471'; ?>; position: relative; text-align: left; cursor: pointer;">
                            <div style="position: absolute; top: 12px; right: 15px; font-size: 14px; color: #999;">
                                <span><?php echo date('Y-m-d', strtotime($announcement['start_date'])); ?></span>
                            </div>
                            <h3 style="margin-top: 0; margin-bottom: 15px; font-family: 'KingHwaOldSongv3.0'; color: #4c526b; font-size: 20px; padding-right: 90px; font-weight: bold;"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <?php if (!empty($announcement['subtitle'])): ?>
                            <div style="margin: -10px 0 15px 0; font-family: 'KingHwaOldSongv3.0'; color: #888; font-size: 16px; font-style: italic; display: flex; align-items: center; padding-left: 10px; font-weight: normal;"><?php echo htmlspecialchars($announcement['subtitle']); ?></div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #4c526b; font-size: 18px; text-align: center; padding: 20px 0;">目前暂无公告</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 添加公告详情模态窗口 -->
<div id="announcement-detail-modal" class="custom-modal">
    <div class="modal-content announcement-detail-modal-content">
        <div style="position: relative; margin-bottom: 15px;">
            <span class="close-button" onclick="closeAnnouncementDetail()" style="position: absolute; top: 0; right: 0; cursor: pointer; font-size: 24px; color: #4c526b; z-index: 3;">&times;</span>
        </div>
        <div class="modal-body">
            <div id="detail-subtitle" style="margin: 0px 0 20px 0; font-family: 'KingHwaOldSongv3.0'; color: #4c526b; font-size: 20px; font-weight: bold; text-align: center; padding: 10px 0; border-bottom: 1px solid #cc9471;"></div>
            <div id="detail-content" class="announcement-content-view"></div>
            <input type="hidden" id="current-announcement-id" value="">
            <div style="text-align: center; margin-top: 20px;">
                <button onclick="confirmAnnouncement()" style="background-color: #cc9471; color: white; border: none; padding: 10px 20px; border-radius: 5px; font-family: 'QiantuHouhei'; font-size: 16px; cursor: pointer; transition: background-color 0.3s ease;">确认</button>
            </div>
        </div>
    </div>
</div>

<style>
/* 公告按钮特定样式 */
.announce-container:hover {
    transform: scale(1.05);
}

.announce-container:hover .announce-text {
    color: #cc9471 !important;
}

.announce-container:hover .announce-image {
    transform: translateY(-10px);
}

/* 添加浮动动画 */
.announce-container {
    animation: floating-announce 3s ease-in-out infinite;
}

@keyframes floating-announce {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}

/* 公告模态窗口样式 */
.announce-modal-content {
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    border: 2px solid #cc9471;
    transform: scale(0.9);
    transition: transform 0.3s ease;
    overflow-y: auto;
    max-height: 80vh;
    width: 50%;
    min-width: 300px;
    max-width: 600px;
    padding: 20px;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: #cc9471 #f0f0f0;
}

/* 自定义滚动条样式 */
.announce-modal-content::-webkit-scrollbar {
    width: 8px;
}

.announce-modal-content::-webkit-scrollbar-track {
    background: #f0f0f0;
    border-radius: 4px;
}

.announce-modal-content::-webkit-scrollbar-thumb {
    background-color: #cc9471;
    border-radius: 4px;
}

/* 添加全局模态窗口样式 */
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

.custom-modal.show {
    opacity: 1;
}

.custom-modal.show .modal-content,
.custom-modal.show .announce-modal-content,
.custom-modal.show .announcement-detail-modal-content {
    transform: scale(1);
}

/* 公告内容动画 */
.announcement-item {
    animation: fadeIn 0.5s ease;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    text-align: left;
}

.announcement-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.announcement-item h3 {
    font-size: 20px !important;
    line-height: 1.3;
    font-weight: 600;
    color: #4c526b !important;
}

/* 控制空格显示 */
#detail-content {
    white-space: pre-wrap !important;
    word-spacing: normal !important;
    letter-spacing: normal !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
}

/* 公告内容视图样式 */
.announcement-content-view {
    margin: 0;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 8px;
    color: #666;
    font-size: 16px;
    text-align: left;
    line-height: 1.6;
    white-space: pre-wrap !important;
    word-spacing: normal !important;
    letter-spacing: normal !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* 公告详情模态窗口样式 */
.announcement-detail-modal-content {
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    border: 2px solid #cc9471;
    transform: scale(0.9);
    transition: transform 0.3s ease;
    overflow-y: auto;
    max-height: 80vh;
    width: 60%;
    min-width: 320px;
    max-width: 700px;
    padding: 20px;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: #cc9471 #f0f0f0;
}

/* 添加悬停效果 */
.announcement-item:hover {
    box-shadow: 0 4px 12px rgba(204, 148, 113, 0.2);
    transform: translateY(-3px);
}

/* 添加确认按钮悬停效果 */
button:hover {
    background-color: #d9a88a !important;
}

/* 未读公告样式 */
.announcement-item.unread {
    box-shadow: 0 2px 8px rgba(255, 82, 82, 0.2);
}
</style>

<script>
// 组件加载完成后调整字体大小
document.addEventListener('DOMContentLoaded', function() {
    // 调整公告按钮字体大小的函数
    function adjustAnnounceFontSize() {
        const announceImage = document.querySelector('.announce-image');
        const announceText = document.querySelector('.announce-text');
        if (announceImage && announceText) {
            const height = announceImage.offsetHeight;
            // 确保有最小值，防止字体消失
            announceText.style.fontSize = height > 0 ? `${Math.max(height * 0.2, 16)}px` : '16px';
        }
    }
    
    // 初始调整
    adjustAnnounceFontSize();
    
    // 监听窗口大小变化
    window.addEventListener('resize', function() {
        adjustAnnounceFontSize();
    });
    
    // 确保在图片加载后调整大小
    const announceImage = document.querySelector('.announce-image');
    if (announceImage) {
        announceImage.onload = adjustAnnounceFontSize;
    }
});

// 显示公告模态窗口
function showAnnounceModal() {
    const modal = document.getElementById('announce-modal');
    
    // 显示模态窗口
    modal.style.display = 'flex';
    
    // 使用setTimeout让CSS过渡效果生效
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    // 防止页面滚动
    document.body.style.overflow = 'hidden';
}

// 关闭公告模态窗口
function closeAnnounceModal() {
    const modal = document.getElementById('announce-modal');
    modal.classList.remove('show');
    
    // 等待过渡效果完成后隐藏模态窗口
    setTimeout(() => {
        modal.style.display = 'none';
        // 恢复页面滚动
        document.body.style.overflow = '';
    }, 300);
}

// 点击窗口外部关闭模态窗口
window.addEventListener('click', function(event) {
    const modal = document.getElementById('announce-modal');
    if (event.target === modal) {
        closeAnnounceModal();
    }
});

// 显示公告详情
function showAnnouncementDetail(element) {
    // 获取公告内容
    const title = element.querySelector('h3').textContent;
    const content = element.getAttribute('data-content');
    const announcementId = element.getAttribute('data-id');
    
    // 获取副标题（如果有）
    let subtitle = '';
    const subtitleElement = element.querySelector('div[style*="font-style: italic"]');
    if (subtitleElement) {
        subtitle = subtitleElement.textContent;
    }
    
    // 设置详情模态窗口内容
    const detailContent = document.getElementById('detail-content');
    // 直接设置文本内容，让CSS控制显示
    detailContent.textContent = content;
    
    // 设置副标题（使用标题作为副标题）
    const detailSubtitle = document.getElementById('detail-subtitle');
    detailSubtitle.textContent = title;
    detailSubtitle.style.display = 'block';
    
    // 存储当前公告ID，用于确认按钮
    document.getElementById('current-announcement-id').value = announcementId;
    
    // 显示详情模态窗口
    const modal = document.getElementById('announcement-detail-modal');
    modal.style.display = 'flex';
    
    // 使用setTimeout让CSS过渡效果生效
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    // 防止事件冒泡到公告模态窗口
    event && event.stopPropagation();
}

// 关闭公告详情模态窗口
function closeAnnouncementDetail() {
    const modal = document.getElementById('announcement-detail-modal');
    modal.classList.remove('show');
    
    // 等待过渡效果完成后隐藏模态窗口
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

// 确认已读公告
function confirmAnnouncement() {
    const announcementId = document.getElementById('current-announcement-id').value;
    if (announcementId) {
        // 设置cookie，有效期30天
        const expiryDate = new Date();
        expiryDate.setDate(expiryDate.getDate() + 30);
        document.cookie = `viewed_announcement_${announcementId}=1; expires=${expiryDate.toUTCString()}; path=/`;
        
        // 更新UI，将红色边框变为正常颜色
        const element = document.querySelector(`.announcement-item[data-id="${announcementId}"]`);
        if (element) {
            element.classList.remove('unread');
            element.style.borderLeftColor = '#cc9471';
        }
        
        // 检查是否所有公告都已读，如果是则更新图标
        checkAllAnnouncementsRead();
    }
    
    // 关闭详情模态窗口
    closeAnnouncementDetail();
}

// 检查是否所有公告都已读
function checkAllAnnouncementsRead() {
    const unreadAnnouncements = document.querySelectorAll('.announcement-item.unread');
    const announceImage = document.getElementById('announce-image');
    
    if (unreadAnnouncements.length === 0 && announceImage) {
        announceImage.src = "elements/announce/Announce.png";
    }
}

// 点击窗口外部关闭详情模态窗口
window.addEventListener('click', function(event) {
    const modal = document.getElementById('announcement-detail-modal');
    if (event.target === modal) {
        closeAnnouncementDetail();
    }
});
</script> 