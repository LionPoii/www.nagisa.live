<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/toast.php';

// 设置页面标题
$page_title = "用户反馈";

require_once 'admin_header.php';

// 检查管理员登录状态
checkAdminAuth();

$db = new Database();
$conn = $db->getConnection();

// 处理操作
$message = '';
// 处理 POST 回复（管理员回复）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply']) && isset($_POST['id'])) {
    $reply_id = intval($_POST['id']);
    $reply_text = trim($_POST['reply']);
    $stmt = $conn->prepare('UPDATE feedback SET reply=?, reply_at=NOW(), status=1 WHERE id=?');
    $stmt->execute([$reply_text, $reply_id]);
    showToast('回复已保存', 'success');
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'delete') {
        // 先删除关联的图片文件
        $stmtImgs = $conn->prepare('SELECT image_paths FROM feedback WHERE id=?');
        $stmtImgs->execute([$id]);
        $imgRow = $stmtImgs->fetch(PDO::FETCH_ASSOC);
        if ($imgRow) {
            $imgs = json_decode($imgRow['image_paths'], true) ?: [];
            foreach ($imgs as $img) {
                $filePath = __DIR__ . '/../' . $img;
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }

        $stmt = $conn->prepare('DELETE FROM feedback WHERE id=?');
        $stmt->execute([$id]);
        // prepare right-up toast message for client-side display
        $rightup_toast = ['msg' => '已删除', 'type' => 'error'];
    } elseif ($_GET['action'] === 'mark') {
        $stmt = $conn->prepare('UPDATE feedback SET status=1 WHERE id=?');
        $stmt->execute([$id]);
        showToast('已标记为已处理', 'success');
    }
}

// 查询反馈
$stmt = $conn->query('SELECT * FROM feedback ORDER BY created_at DESC LIMIT 100');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="feedback-container">
    <?php if (!empty($rightup_toast)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof showRightUpToast === 'function') {
                showRightUpToast(<?php echo json_encode($rightup_toast['msg'], JSON_UNESCAPED_UNICODE); ?>, 'error', 2500);
            } else {
                alert(<?php echo json_encode($rightup_toast['msg'], JSON_UNESCAPED_UNICODE); ?>);
            }
        });
    </script>
    <?php unset($rightup_toast); endif; ?>
    <div class="feedback-content">
        <!-- 顶部标题 -->
        <div class="feedback-header">
            <h2>反馈列表</h2>
        </div>
        
        <p class="feedback-description">查看和管理用户提交的反馈信息。</p>
            
        <div class="feedback-table-container">
            <table class="feedback-table">
                <thead>
                    <tr>
                        <th width="10%">单号</th>
                        <th width="10%">类型</th>
                        <th width="30%">内容</th>
                        <th width="10%">ID</th>
                        <th width="15%">图片</th>
                        <th width="10%">状态</th>
                        <th width="10%">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rows) === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">暂无反馈数据</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $count = 1;
                        foreach($rows as $row): 
                        ?>
                        <tr data-reply="<?php echo htmlspecialchars($row['reply']); ?>">
                            <td><?php echo htmlspecialchars($row['ticket_number']); ?></td>
                            <td>
                                <?php 
                                $typeClasses = [
                                    '建议' => 'feature',
                                    'BUG' => 'bug',
                                    '内容补充' => 'content',
                                    '其他' => 'other'
                                ];
                                $typeClass = $typeClasses[$row['type']] ?? 'other';
                                ?>
                                <span class="type-badge <?php echo $typeClass; ?>">
                                    <?php echo htmlspecialchars($row['type']); ?>
                                </span>
                            </td>
                            <td class="message-cell"><?php echo nl2br(htmlspecialchars($row['message'])); ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td>
                                <div class="image-gallery" data-feedback-id="<?php echo $row['id']; ?>">
                                <?php 
                                $imgs = json_decode($row['image_paths'], true) ?: [];
                                foreach($imgs as $key => $img): ?>
                                    <a href="javascript:void(0);" class="image-thumbnail" data-image-path="<?php echo htmlspecialchars($img); ?>" onclick="openLightbox('<?php echo htmlspecialchars($img); ?>', <?php echo $row['id']; ?>, <?php echo $key; ?>)">
                                        <img src="/<?php echo htmlspecialchars($img); ?>" class="thumbnail-img" alt="反馈图片">
                                    </a>
                                <?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <?php if($row['status']==1): ?>
                                    <span class="status-badge processed">已处理</span>
                                <?php else: ?>
                                    <span class="status-badge pending">未处理</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    
                                    <a href="javascript:void(0);" 
                                        class="btn btn-secondary"
                                        onclick="openReplyModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['ticket_number']); ?>')">
                                        <i class="fas fa-reply"></i> 回复
                                    </a>
                                    <a href="javascript:void(0);" 
                                        class="btn btn-danger"
                                        onclick="showConfirmModal(<?php echo $row['id']; ?>, 'delete', '确认删除此反馈？此操作不可撤销')">
                                        <i class="fas fa-trash"></i> 删除
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="info-card">
            <h3>反馈组件信息</h3>
            <div class="card-body">
                <p class="info-text">
                    反馈组件自动收集用户反馈并存储在数据库中。用户可以通过页面底部的"改进反馈"按钮提交反馈。
                </p>
                <ul class="feature-list">
                    <li class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>支持多种反馈类型：建议、BUG报告、内容补充等</span>
                    </li>
                    <li class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>用户可以上传图片附件</span>
                    </li>
                    <li class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>系统自动记录提交时间和用户信息</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- 灯箱模态窗口 -->
<div id="image-lightbox" class="lightbox">
    <div class="lightbox-content">
        <span class="lightbox-close">&times;</span>
        <img id="lightbox-img" class="lightbox-img">
        <div class="lightbox-nav">
            <button id="prev-btn" class="lightbox-btn"><i class="fas fa-chevron-left"></i></button>
            <button id="next-btn" class="lightbox-btn"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</div>

<!-- 确认操作模态窗口 -->
<div id="confirm-modal" class="confirm-modal">
    <div class="confirm-content">
        <h3 class="confirm-title">确认操作</h3>
        <p id="confirm-message"></p>
        <div class="confirm-buttons">
            <button id="confirm-cancel" class="btn btn-secondary">取消</button>
            <button id="confirm-ok" class="btn btn-primary">确认</button>
        </div>
    </div>
</div>

<!-- 管理员回复模态窗口 -->
<div id="reply-modal" class="confirm-modal">
    <div class="confirm-content">
        <h3 class="confirm-title">回复反馈 - 单号 <span id="reply-ticket"></span></h3>
        <div style="padding:20px;">
            <textarea id="reply-text" style="width:100%;height:120px;padding:8px;" placeholder="在此输入回复内容"></textarea>
            <div style="margin-top:12px;text-align:right;">
                <button id="reply-cancel" class="btn btn-secondary">取消</button>
                <button id="reply-send" class="btn btn-primary">回复</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* 全局样式 */
    .feedback-container {
        padding: 0 20px;
        max-width: 1400px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    
    .feedback-content {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
        margin: 20px 0;
        padding: 24px;
    }
    
    .feedback-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e1e4e8;
    }
    
    .feedback-header h2 {
        margin: 0;
        color: #cc9471;
        font-size: 1.5rem;
        font-weight: 600;
    }
    
    .feedback-description {
        margin-bottom: 20px;
        color: #586069;
        font-size: 0.95rem;
    }
    
    /* 表格样式 */
    .feedback-table-container {
        background-color: #fff;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 24px;
    }
    
    .feedback-table {
        width: 100%;
        border-collapse: collapse;
        border-spacing: 0;
    }
    
    .feedback-table th {
        background-color: #cc9471;
        color: #fff;
        font-weight: 500;
        text-align: left;
        padding: 12px 16px;
    }
    
    .feedback-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #eaecef;
        vertical-align: top;
    }
    
    .feedback-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .feedback-table tbody tr:hover {
        background-color: rgba(204, 148, 113, 0.05);
    }
    
    /* 交替行背景色 */
    .feedback-table tbody tr:nth-child(odd) {
        background-color: #f6f8fa;
    }
    
    .feedback-table tbody tr:nth-child(odd):hover {
        background-color: rgba(204, 148, 113, 0.08);
    }
    
    /* 消息单元格样式 */
    .message-cell {
        max-width: 300px;
        word-break: break-all;
        line-height: 1.5;
    }
    
    /* 类型标签 */
    .type-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: white;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
    }
    
    .type-badge.feature {
        background-color: #2da44e;
        background-image: linear-gradient(to bottom, #3ebd60, #2da44e);
    }
    
    .type-badge.bug {
        background-color: #f85149;
        background-image: linear-gradient(to bottom, #ff6b64, #f85149);
    }
    
    .type-badge.content {
        background-color: #8250df;
        background-image: linear-gradient(to bottom, #9668e2, #8250df);
    }
    
    .type-badge.other {
        background-color: #cc9471;
        background-image: linear-gradient(to bottom, #e8a274, #cc9471);
    }
    
    /* 状态标签 */
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }
    
    .status-badge.processed {
        background-color: #e6f4ea;
        color: #137333;
    }
    
    .status-badge.pending {
        background-color: #fff8e1;
        color: #b06000;
    }
    
    /* 按钮样式 */
    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    
    .btn i {
        margin-right: 4px;
        font-size: 11px;
    }
    
    .btn-secondary {
        background-color: #f1f2f4;
        color: #24292e;
        border-color: #d1d5db;
    }
    
    .btn-secondary:hover {
        background-color: #e9ecef;
        border-color: #c9cdd3;
    }
    
    .btn-danger {
        background-color: #fef2f2;
        color: #ef4444;
        border-color: #fecaca;
    }
    
    .btn-danger:hover {
        background-color: #fee2e2;
        color: #dc2626;
    }
    
    /* 信息卡片 */
    .info-card {
        background-color: #fff;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-top: 20px;
        border-left: 4px solid #4D4030;
    }
    
    .info-card h3 {
        padding: 16px 20px;
        margin: 0;
        border-bottom: 1px solid #e1e4e8;
        font-size: 1.1rem;
        font-weight: 600;
        color: #24292e;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .info-text {
        margin-bottom: 16px;
        color: #586069;
        font-size: 14px;
        line-height: 1.5;
    }
    
    /* 功能列表 */
    .feature-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }
    
    .feature-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 12px;
        font-size: 14px;
        color: #586069;
    }
    
    .feature-item:last-child {
        margin-bottom: 0;
    }
    
    .feature-item i {
        color: #2da44e;
        margin-right: 8px;
        margin-top: 3px;
    }
    
    /* 图片样式 */
    .image-gallery {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .image-thumbnail {
        width: 48px;
        height: 48px;
        display: block;
    }
    
    .thumbnail-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #e1e4e8;
        transition: transform 0.2s, border-color 0.3s;
    }
    
    .thumbnail-img:hover {
        transform: scale(1.05);
        border-color: #cc9471;
    }
    
    /* 灯箱样式 */
    .lightbox {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        justify-content: center;
        align-items: center;
    }
    
    .lightbox-content {
        position: relative;
        max-width: 80%;
        max-height: 80%;
        margin: auto;
    }
    
    .lightbox-img {
        display: block;
        max-width: 100%;
        max-height: 80vh;
        margin: auto;
        box-shadow: 0 0 20px rgba(0,0,0,0.2);
        border-radius: 2px;
    }
    
    .lightbox-close {
        position: absolute;
        top: -40px;
        right: -5px;
        color: white;
        font-size: 30px;
        font-weight: bold;
        cursor: pointer;
        z-index: 100;
    }
    
    .lightbox-nav {
        position: absolute;
        bottom: -40px;
        left: 0;
        width: 100%;
        display: flex;
        justify-content: center;
        gap: 20px;
    }
    
    .lightbox-btn {
        background-color: rgba(255,255,255,0.2);
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        font-size: 16px;
        cursor: pointer;
        transition: background-color 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .lightbox-btn:hover {
        background-color: rgba(255,255,255,0.4);
    }
    
    /* 响应式调整 */
    
    /* 工具类 */
    .text-center {
        text-align: center;
    }

    /* 确认模态窗口样式 */
    .confirm-modal {
        display: none;
        position: fixed;
        z-index: 20000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
    }
    .confirm-modal.show {
        display: flex !important;
    }
    
    .confirm-content {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        width: 90%;
        max-width: 400px;
        padding: 0;
        animation: modalFadeIn 0.3s ease;
        overflow: hidden;
    }
    
    .confirm-title {
        background-color: #cc9471;
        color: white;
        margin: 0;
        padding: 16px 20px;
        font-size: 18px;
        font-weight: 500;
    }
    
    #confirm-message {
        padding: 20px;
        color: #4a5568;
        margin: 0;
        min-height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 16px;
    }
    
    .confirm-buttons {
        display: flex;
        padding: 16px 20px;
        background-color: #f8f9fa;
        border-top: 1px solid #e9ecef;
        justify-content: flex-end;
        gap: 12px;
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .btn-primary {
        background-color: #cc9471;
        color: white;
        border-color: #cc9471;
    }
    
    .btn-primary:hover {
        background-color: #b88365;
        border-color: #b88365;
    }
</style>

<script>
    // 当前灯箱图片集和索引
    let currentFeedbackImages = {};
    let currentImageIndex = 0;
    let currentFeedbackId = 0;
    
    // 打开灯箱
    function openLightbox(imagePath, feedbackId, imageIndex) {
        // 获取当前反馈的所有图片
        if (!currentFeedbackImages[feedbackId]) {
            currentFeedbackImages[feedbackId] = [];
            const imageGallery = document.querySelector(`[data-feedback-id="${feedbackId}"]`);
            if (imageGallery) {
                const thumbnails = imageGallery.querySelectorAll('.image-thumbnail');
                thumbnails.forEach(thumbnail => {
                    currentFeedbackImages[feedbackId].push(thumbnail.getAttribute('data-image-path'));
                });
            }
        }
        
        const lightbox = document.getElementById('image-lightbox');
        const lightboxImg = document.getElementById('lightbox-img');
        
        // 设置当前图片索引和反馈ID
        currentFeedbackId = feedbackId;
        currentImageIndex = imageIndex;
        
        // 显示灯箱和图片
        lightboxImg.src = '/' + imagePath;
        lightbox.style.display = 'flex';
        
        // 更新导航按钮状态
        updateNavButtons();
        
        // 防止滚动
        document.body.style.overflow = 'hidden';
    }
    
    // 关闭灯箱
    function closeLightbox() {
        const lightbox = document.getElementById('image-lightbox');
        lightbox.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    // 显示下一张图片
    function showNextImage() {
        const images = currentFeedbackImages[currentFeedbackId];
        if (images && currentImageIndex < images.length - 1) {
            currentImageIndex++;
            document.getElementById('lightbox-img').src = '/' + images[currentImageIndex];
            updateNavButtons();
        }
    }
    
    // 显示上一张图片
    function showPrevImage() {
        const images = currentFeedbackImages[currentFeedbackId];
        if (images && currentImageIndex > 0) {
            currentImageIndex--;
            document.getElementById('lightbox-img').src = '/' + images[currentImageIndex];
            updateNavButtons();
        }
    }
    
    // 更新导航按钮状态
    function updateNavButtons() {
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const images = currentFeedbackImages[currentFeedbackId] || [];
        
        prevBtn.disabled = currentImageIndex === 0;
        prevBtn.style.opacity = currentImageIndex === 0 ? '0.5' : '1';
        
        nextBtn.disabled = currentImageIndex === images.length - 1;
        nextBtn.style.opacity = currentImageIndex === images.length - 1 ? '0.5' : '1';
    }
    
    // 当前确认操作的详情
    let currentActionId = null;
    let currentActionType = null;
    
    // 显示确认模态框
    function showConfirmModal(id, actionType, message) {
        currentActionId = id;
        currentActionType = actionType;
        
        const confirmModal = document.getElementById('confirm-modal');
        const confirmMessage = document.getElementById('confirm-message');
        
        confirmMessage.textContent = message;
        confirmModal.style.display = 'flex';
        confirmModal.classList.add('show');
        
        // 防止滚动
        document.body.style.overflow = 'hidden';
    }
    
    // 关闭确认模态框
    function closeConfirmModal() {
        const confirmModal = document.getElementById('confirm-modal');
        confirmModal.style.display = 'none';
        confirmModal.classList.remove('show');
        document.body.style.overflow = '';
        
        currentActionId = null;
        currentActionType = null;
    }
    
    // 执行确认的操作
    function executeConfirmedAction() {
        if (currentActionId && currentActionType) {
            window.location.href = `?action=${currentActionType}&id=${currentActionId}`;
        }
        closeConfirmModal();
    }
    
    // 初始化灯箱事件
    document.addEventListener('DOMContentLoaded', function() {
        // 关闭按钮点击事件
        document.querySelector('.lightbox-close').addEventListener('click', closeLightbox);
        
        // 点击灯箱背景关闭
        document.getElementById('image-lightbox').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLightbox();
            }
        });
        
        // 上一张/下一张按钮点击事件
        document.getElementById('prev-btn').addEventListener('click', showPrevImage);
        document.getElementById('next-btn').addEventListener('click', showNextImage);
        
        // 键盘导航
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('image-lightbox').style.display === 'flex') {
                if (e.key === 'Escape') {
                    closeLightbox();
                } else if (e.key === 'ArrowLeft') {
                    showPrevImage();
                } else if (e.key === 'ArrowRight') {
                    showNextImage();
                }
            }
        });
        
        // 确认模态框事件处理
        document.getElementById('confirm-cancel').addEventListener('click', closeConfirmModal);
        document.getElementById('confirm-ok').addEventListener('click', executeConfirmedAction);
        
        // 点击模态框背景关闭
        document.getElementById('confirm-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmModal();
            }
        });
        
        // 同时为确认模态框添加键盘事件
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('confirm-modal').style.display === 'flex') {
                if (e.key === 'Escape') {
                    closeConfirmModal();
                } else if (e.key === 'Enter') {
                    executeConfirmedAction();
                }
            }
        });
        
        // 回复模态窗口事件绑定
        const replyModal = document.getElementById('reply-modal');
        const replyCancel = document.getElementById('reply-cancel');
        const replySend = document.getElementById('reply-send');
        replyCancel.addEventListener('click', closeReplyModal);
        replySend.addEventListener('click', submitReply);
        document.getElementById('reply-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReplyModal();
            }
        });
    });

    // 打开回复模态
    let currentReplyId = null;
    function openReplyModal(id, ticket) {
        currentReplyId = id;
        document.getElementById('reply-ticket').textContent = ticket;
        // 尝试从表格行的 data-reply 属性中读取已有回复并预填充
        const replyField = document.getElementById('reply-text');
        let existingReply = '';
        const gallery = document.querySelector(`[data-feedback-id="${id}"]`);
        if (gallery) {
            const row = gallery.closest('tr');
            if (row) {
                existingReply = row.getAttribute('data-reply') || '';
            }
        }
        replyField.value = existingReply;
        const replyModal = document.getElementById('reply-modal');
        replyModal.style.display = 'flex';
        replyModal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeReplyModal() {
        const replyModal = document.getElementById('reply-modal');
        replyModal.style.display = 'none';
        replyModal.classList.remove('show');
        document.body.style.overflow = '';
        currentReplyId = null;
    }

    // 提交回复（通过 POST 提交到当前页面）
    function submitReply() {
        if (!currentReplyId) return;
        const text = document.getElementById('reply-text').value.trim();
        if (text === '') return alert('回复内容不能为空');
        const params = new URLSearchParams();
        params.append('id', currentReplyId);
        params.append('reply', text);
        // 使用 API 异步保存回复，允许多次编辑
        fetch('/api/feedback_reply.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params.toString()
        }).then(r => r.json()).then(res => {
            if (!res.success) {
                alert(res.msg || '回复保存失败');
                return;
            }
            // 更新表格行的状态与 data-reply
            const gallery = document.querySelector(`[data-feedback-id="${currentReplyId}"]`);
            if (gallery) {
                const row = gallery.closest('tr');
                if (row) {
                    row.setAttribute('data-reply', text);
                    const statusBadge = row.querySelector('.status-badge');
                    if (statusBadge) {
                        statusBadge.classList.remove('pending');
                        statusBadge.classList.add('processed');
                        statusBadge.textContent = '已处理';
                    }
                }
            }
            // 使用右上角全局提示显示已保存
            if (typeof showRightUpToast === 'function') {
                showRightUpToast('回复已保存', 'success', 2500);
                // 自动关闭模态（短延迟以便用户看到提示）
                setTimeout(closeReplyModal, 300);
            } else {
                // 作为降级处理，使用 alert 或在模态内短暂显示
                const content = document.querySelector('#reply-modal .confirm-content > div');
                if (content) {
                    const okDiv = document.createElement('div');
                    okDiv.style.marginTop = '8px';
                    okDiv.style.color = '#2F855A';
                    okDiv.textContent = '回复已保存';
                    okDiv.className = 'save-note';
                    // 移除既有提示并加入新提示
                    const prev = content.querySelector('.save-note');
                    if (prev) prev.remove();
                    content.appendChild(okDiv);
                    setTimeout(()=> { if (okDiv) okDiv.remove(); }, 3000);
                    // 关闭模态
                    setTimeout(closeReplyModal, 300);
                } else {
                    alert('回复已保存');
                    setTimeout(closeReplyModal, 300);
                }
            }
        }).catch(()=> {
            alert('回复保存失败，请稍后再试');
        });
    }
</script>

<?php require_once 'admin_footer.php'; ?> 