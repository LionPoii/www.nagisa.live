<?php
// 确保数据库连接存在
if (!isset($conn)) {
    require_once __DIR__ . '/../includes/database.php';
    $db = new Database();
    $conn = $db->getConnection();
}

// 获取页眉文本和图像
$stmt = $conn->prepare("SELECT header_text, header_image, header_style FROM header_settings WHERE id = 1");
$stmt->execute();
$header = $stmt->fetch(PDO::FETCH_ASSOC);
$header_text = $header['header_text'] ?? 'Nagisa Live';
$header_image = $header['header_image'] ?? '';

// 解析样式设置
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
?>

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
</div>