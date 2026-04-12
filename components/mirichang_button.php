<?php
// 日常按钮组件
?>
<!-- 日常按钮容器 -->
<div class="section1-left-button-wrapper" style="top: calc(50% + 70px); left: 7.5%;">
  <a href="/SecWeb/expression/expression_base.php" class="section1-left-button-link" target="_blank">
    <div class="section1-left-button-container" id="daily-button">
      <svg class="section1-left-button-stripes" width="100%" height="100%" preserveAspectRatio="none" viewBox="0 0 180 60" xmlns="http://www.w3.org/2000/svg">
        <!-- 条纹3 - 浅灰色 (最底层) -->
        <path class="stripe-3" d="M0,44 L180,60 L180,76 L0,60 Z" fill="#CAC8C7" />
        <!-- 条纹2 - 橙棕色 (中间层) -->
        <path class="stripe-2" d="M180,0 L0,16 L0,0 L180,16 Z" fill="#D79568" />
        <!-- 条纹1 - 深蓝灰色 (最上层) -->
        <path class="stripe-1" d="M180,-16 L0,0 L0,16 L180,0 Z" fill="#3D4255" />
      </svg>
      <div class="section1-left-button-text">日常</div>
    </div>
  </a>
</div>

<!-- 引入样式和脚本 -->
<link rel="stylesheet" href="/assets/css/section1_left_button.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="/assets/css/button_override.css?v=<?php echo time(); ?>">
<script src="/assets/js/section1_left_button.js"></script> 