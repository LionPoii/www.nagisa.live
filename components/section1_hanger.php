<?php
// 吊架组件 - 包含T形装饰、衣柜按钮和日常按钮
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

<!-- 整体容器，控制整个组件的位置 -->
<div class="section1-hanger-system">
  <!-- 衣柜按钮 -->
  <div class="section1-left-button-wrapper" style="position: absolute; top: 85px; left: 0;">
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

  <!-- 日常按钮 -->
  <div class="section1-left-button-wrapper" style="position: absolute; top: 195px; left: 0;">
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
  
  <!-- 吊架容器 - 放在最后以确保最高图层 -->
  <div class="section1-hanger">
    <!-- 顶部挂钩 -->
    <div class="section1-hanger-hook"></div>
    <!-- 水平杆 -->
    <div class="section1-hanger-horizontal">
      <!-- 水平杆的高光 -->
      <div class="section1-hanger-highlight"></div>
      <!-- 水平杆的纹理 -->
      <div class="section1-hanger-texture"></div>
    </div>
    <!-- 垂直杆 -->
    <div class="section1-hanger-vertical">
      <!-- 垂直杆的高光 -->
      <div class="section1-hanger-highlight vertical"></div>
      <!-- 垂直杆的纹理 -->
      <div class="section1-hanger-texture vertical"></div>
      <!-- 装饰点 -->
      <div class="section1-hanger-dot section1-hanger-dot-1">
        <div class="section1-hanger-dot-highlight"></div>
      </div>
      <div class="section1-hanger-dot section1-hanger-dot-2">
        <div class="section1-hanger-dot-highlight"></div>
      </div>
      <!-- 底部装饰 -->
      <div class="section1-hanger-bottom"></div>
    </div>
  </div>
</div>

<style>
/* 吊架样式 - 直接嵌入组件中 */
.section1-hanger-system {
    position: absolute;
    top: 14.5%;
    left: 6%;
    z-index: 20;
    transform: translateY(-50%);
    /* 添加缩放变换的原点 */
    transform-origin: top left;
}

.section1-hanger {
    position: relative; /* 改为相对定位，因为现在在容器内 */
    pointer-events: none; /* 不接收鼠标事件，点击可以穿透到下面的元素 */
    z-index: 20; /* 确保吊架在按钮之上 */
}

/* 顶部挂钩 */
.section1-hanger-hook {
    position: absolute;
    width: 20px;
    height: 10px;
    background-color: #3d3326;
    border-radius: 10px 10px 0 0;
    top: -10px;
    left: 25px; /* 居中于垂直杆 */
    box-shadow: inset 0 2px 3px rgba(255,255,255,0.2), 0 -1px 2px rgba(0,0,0,0.3);
}

/* 水平杆 */
.section1-hanger-horizontal {
    position: absolute;
    width: 220px; /* 从180px增加到220px */
    height: 15px;
    background-color: #4d4030;
    top: 0;
    left: 0;
    border-radius: 3px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.2);
    overflow: hidden;
}

/* 水平杆的高光 */
.section1-hanger-highlight {
    position: absolute;
    width: 100%;
    height: 3px;
    background: linear-gradient(to bottom, rgba(255,255,255,0.3), rgba(255,255,255,0.1));
    top: 0;
    left: 0;
}

/* 水平杆的纹理 */
.section1-hanger-texture {
    position: absolute;
    width: 100%;
    height: 100%;
    background-image: repeating-linear-gradient(
        90deg,
        transparent,
        transparent 10px,
        rgba(0,0,0,0.03) 10px,
        rgba(0,0,0,0.03) 12px
    );
    opacity: 0.7;
}

/* 垂直杆 */
.section1-hanger-vertical {
    position: absolute;
    width: 15px;
    height: 280px; /* 从325px调整为280px */
    background-color: #4d4030;
    top: 0;
    left: 30px; /* 水平位置偏移 */
    border-radius: 3px;
    box-shadow: 4px 0 6px rgba(0,0,0,0.1);
    overflow: hidden;
}

/* 垂直杆的高光 */
.section1-hanger-highlight.vertical {
    width: 3px;
    height: 100%;
    background: linear-gradient(to right, rgba(255,255,255,0.3), rgba(255,255,255,0.1));
}

/* 垂直杆的纹理 */
.section1-hanger-texture.vertical {
    background-image: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 10px,
        rgba(0,0,0,0.03) 10px,
        rgba(0,0,0,0.03) 12px
    );
}

/* 装饰点 */
.section1-hanger-dot {
    position: absolute;
    width: 10px;
    height: 10px;
    background: radial-gradient(circle, #f0f0f0 60%, #d0d0d0);
    border-radius: 50%;
    left: 2.5px; /* 居中于垂直杆 */
    box-shadow: inset 0 0 2px rgba(0,0,0,0.2), 0 1px 2px rgba(0,0,0,0.3);
    overflow: hidden;
}

.section1-hanger-dot-1 {
    top: 85px; /* 从100px调整到85px */
}

.section1-hanger-dot-2 {
    top: 195px; /* 从225px调整到195px */
}

/* 装饰点的高光 */
.section1-hanger-dot-highlight {
    position: absolute;
    width: 4px;
    height: 4px;
    background-color: rgba(255,255,255,0.8);
    border-radius: 50%;
    top: 2px;
    left: 2px;
}

/* 底部装饰 */
.section1-hanger-bottom {
    position: absolute;
    width: 20px;
    height: 8px;
    background-color: #3d3326;
    border-radius: 0 0 5px 5px;
    bottom: -8px;
    left: -2.5px; /* 居中于垂直杆 */
    box-shadow: 0 2px 3px rgba(0,0,0,0.3);
}

/* 适配移动设备 */
@media (max-width: 768px) {
    .section1-hanger-hook {
        width: 15px;
        height: 8px;
        top: -8px;
        left: 17.5px;
    }
    
    .section1-hanger-horizontal {
        width: 130px; /* 从100px增加到130px */
        height: 10px;
    }
    
    .section1-hanger-vertical {
        width: 10px;
        height: 200px; /* 从240px调整到200px */
        left: 20px;
    }
    
    .section1-hanger-dot {
        width: 8px;
        height: 8px;
        left: 1px;
    }
    
    .section1-hanger-dot-highlight {
        width: 3px;
        height: 3px;
    }
    
    .section1-hanger-dot-1 {
        top: 65px; /* 从75px调整到65px */
    }
    
    .section1-hanger-dot-2 {
        top: 140px; /* 从165px调整到140px */
    }
    
    .section1-hanger-bottom {
        width: 15px;
        height: 6px;
        left: -2.5px;
    }
    
    /* 移动设备上的按钮位置调整 */
    .section1-hanger-system .section1-left-button-wrapper:nth-child(2) {
        top: 65px;
    }
    
    .section1-hanger-system .section1-left-button-wrapper:nth-child(3) {
        top: 140px;
    }
}
</style>

<!-- 引入按钮样式和脚本 -->
<link rel="stylesheet" href="/assets/css/section1_left_button.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="/assets/css/button_override.css?v=<?php echo time(); ?>">
<script src="/assets/js/section1_left_button.js"></script> 

<!-- 添加缩放脚本 -->
<script>
// 计算并设置吊架系统的缩放比例
function calculateHangerScale() {
    const hangerSystem = document.querySelector('.section1-hanger-system');
    if (!hangerSystem) return;
    
    // 获取窗口宽度和高度
    const windowWidth = window.innerWidth;
    const windowHeight = window.innerHeight;
    
    // 基准尺寸（设计时的窗口尺寸）
    const baseWidth = 1920; // 假设设计时的宽度为1920px
    const baseHeight = 1080; // 假设设计时的高度为1080px
    
    // 计算宽度和高度的缩放比例
    const scaleX = windowWidth / baseWidth;
    const scaleY = windowHeight / baseHeight;
    
    // 使用较小的缩放比例，确保组件完全显示
    const scale = Math.min(scaleX, scaleY);
    
    // 应用缩放
    hangerSystem.style.transform = `translateY(-50%) scale(${scale})`;
}

// 注册窗口大小变化事件
window.addEventListener('resize', calculateHangerScale);

// 页面加载后初始化缩放
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(calculateHangerScale, 500);
});
</script> 