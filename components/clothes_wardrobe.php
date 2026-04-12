<?php
// 可以在这里添加一些逻辑，例如获取衣装数量
$clothes_count = 0;

try {
    // 从数据库获取衣装数量（示例，如果数据库中有相关表）
    require_once __DIR__ . '/../includes/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // 假设有一个clothes表，统计衣装数量
    // 这里只是示例，您可能需要调整查询
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM site_config WHERE config_key LIKE 'clothes_%'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && isset($result['count'])) {
        $clothes_count = (int)$result['count'];
    } else {
        // 如果没有数据，设置一个默认值
        $clothes_count = 13; // 设置默认展示的衣装数量
    }
} catch (Exception $e) {
    // 出错时使用默认值
    $clothes_count = 13; // 默认展示的衣装数量
}
?>

<!-- 衣柜浮窗容器 -->
<div class="section1-left-button-wrapper" style="top: 50%; left: 7.5%;">
  <a href="/SecWeb/clothes.php" class="section1-left-button-link" target="_blank">
    <div class="section1-left-button-container" id="wardrobe-button">
      <svg class="section1-left-button-stripes" width="100%" height="100%" preserveAspectRatio="none" viewBox="0 0 180 60" xmlns="http://www.w3.org/2000/svg">
        <!-- 条纹3 - 浅灰色 (最底层) -->
        <path class="stripe-3" d="M0,44 L180,60 L180,76 L0,60 Z" fill="#CAC8C7" />
        <!-- 条纹2 - 橙棕色 (中间层) -->
        <path class="stripe-2" d="M180,0 L0,16 L0,0 L180,16 Z" fill="#D79568" />
        <!-- 条纹1 - 深蓝灰色 (最上层) -->
        <path class="stripe-1" d="M180,-16 L0,0 L0,16 L180,0 Z" fill="#3D4255" />
      </svg>
      <div class="section1-left-button-text">衣柜</div>
    </div>
  </a>
</div>

<!-- 引入样式和脚本 -->
<link rel="stylesheet" href="/assets/css/section1_left_button.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="/assets/css/button_override.css?v=<?php echo time(); ?>">
<script src="/assets/js/section1_left_button.js"></script> 