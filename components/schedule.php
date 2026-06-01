<?php

// 获取数据库连接
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';
$db = new Database();
$conn = $db->getConnection();

// 每周一首次访问时自动关闭旧周表（手动开关可在本周内强制重新显示）
schedule_run_weekly_auto_close($conn);

// 获取最新周表图片（仅获取设置为可见的）
$stmt = $conn->prepare("SELECT * FROM schedule_image WHERE is_visible = 1 ORDER BY id DESC LIMIT 1");
$stmt->execute();
$schedule_image = $stmt->fetch(PDO::FETCH_ASSOC);

$schedule_image_path = $schedule_image['image_path'] ?? '';

// 只有在有路径且服务器上实际存在该文件时才让前端尝试加载图片（避免出现损坏图标）
$should_render_schedule = false;
if (!empty($schedule_image_path)) {
    // 兼容以/开头或不以/开头的存储格式，定位到项目根目录来检查文件是否存在
    $check_path = __DIR__ . '/../' . ltrim($schedule_image_path, '/');
    if (file_exists($check_path)) {
        $should_render_schedule = true;
    }
}
?>
<div class="blackboard-container" style="position: absolute; left: 2.5%; top: 52.5%; transform: translateY(-50%); width: 70%; max-width: 90%; z-index: 3;">
    <img src="elements/Blackboard.png" alt="Blackboard" class="blackboard-image" style="width: 100%; height: auto; display: block;">
    <?php if ($should_render_schedule): ?>
        <div id="schedule-on-blackboard" style="position: absolute; top: 50%; left: 52.5%; transform: translate(-50%, -50%); width: 88%; height: 90%; display: flex; align-items: center; justify-content: center;">
            <!-- 周表图片将通过JavaScript加载到这里 -->
        </div>
        <div id="schedule-image-data" data-image="<?php echo htmlspecialchars($schedule_image_path); ?>" style="display:none;"></div>
    <?php endif; ?>
</div>
