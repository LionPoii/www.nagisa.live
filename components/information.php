<?php
// 获取个人描述文本
$description = '「认为思考有趣的问题比真正去做事更轻松很正常吧。」'; // 默认值
$filebagText = '文件资料袋'; // 默认文件袋文本

// 从数据库中读取文本
if (isset($conn)) {
    // 读取个人描述
    $stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = 'information_description'");
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $descriptions = json_decode($row['content_value'], true);
        
        // 如果是数组（多条语句），则随机选择一条；否则直接使用
        if (is_array($descriptions) && !empty($descriptions)) {
            $randomIndex = array_rand($descriptions);
            $description = $descriptions[$randomIndex];
        } elseif (!empty($row['content_value'])) {
            $description = $row['content_value'];
        }
    }
    
    // 读取文件袋文本
    $stmt = $conn->prepare("SELECT content_value FROM site_content WHERE content_key = 'filebag_text'");
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['content_value'])) {
            $filebagText = $row['content_value'];
        }
    }
}
?>
<!-- Filebag和Card信息组件 -->
<style>
@font-face {
  font-family: 'STXINWEI';
  src: url('/assets/webfonts/STXINWEI.TTF') format('truetype');
  font-display: swap;
}
@font-face {
  font-family: 'SEGOEPRB';
  src: url('/assets/webfonts/SEGOEPRB.TTF') format('truetype');
  font-display: swap;
}
/* 将字体大小设为固定值，然后通过容器缩放整体调整大小 */
.information-title-cn, .information-title-en {
  font-size: 28px; /* 从24px增加到28px */
  font-family: 'STXINWEI', serif;
  user-select: none;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
}
.information-description {
  font-size: 28px; /* 从24px增加到28px */
  color: #ffffff;
  margin-top: 14px; /* 从12px增加到14px */
  font-weight: normal;
  font-family: 'QIANTUHOUHEI', sans-serif;
  user-select: none;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
}
.information-text-container {
  user-select: none;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  transform-origin: top left; /* 确保从左上角开始缩放 */
}
/* 文件袋文本容器 */
.filebag-text {
  font-size: 22px; /* 从18px增加到22px */
  transform-origin: bottom left; /* 确保从左下角开始缩放 */
}
@keyframes fillAndChangeColor {
  0% {
    transform: scale(0);
    opacity: 0;
    background: rgba(255,255,255,0.2);
  }
  50% {
    transform: scale(1);
    opacity: 1;
    background: rgba(255,255,255,0.2);
  }
  100% {
    transform: scale(1);
    opacity: 1;
    background: rgba(232,162,116,0.3); /* 填充完毕后变为#E8A274 */
  }
}
@keyframes changeBorderColor {
  0% {
    border-color: #fff;
  }
  50% {
    border-color: #fff;
  }
  100% {
    border-color: #E8A274; /* 边框变为#E8A274 */
  }
}
.avatar-link {
  display: block;
  position: absolute;
  left: 12.5%; /* 从15%改为12.5% */
  top: 50%;
  width: 27%;
  height: 0;
  padding-bottom: 27%;
  transform: translateY(-50%);
  z-index: 15;
  border-radius: 50%;

}
.avatar-container {
  position: relative;
  width: 100%;
  height: 0;
  padding-bottom: 100%;
  border-radius: 50%;
  overflow: hidden;
  border: 3px solid #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  transition: transform 0.3s ease;
  cursor: pointer;
  background-color: #252930;
}
.avatar-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.2);
  border-radius: 50%;
  transform: scale(0);
  opacity: 0;
  transform-origin: center;
  z-index: 1;
}
.avatar-container:hover {
  transform: scale(1.05);
  animation: changeBorderColor 1.5s forwards;
}
.avatar-container:hover::before {
  animation: fillAndChangeColor 1.5s forwards;
}
.avatar-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
  position: absolute;
  top: 0;
  left: 0;
  z-index: 2;
}
</style>
<div class="main-container" style="position: absolute; left: 50%; top: 60%; transform: translate(-50%, -50%); width: 60vw; max-width: 90vw;">
  <div style="position: relative; display: inline-block;">
    <!-- 文件袋背景 -->
    <img src="elements/Information/Filebag.png" draggable="false"
         class="filebag-bg"
         style="display: block; max-width: 100%; max-height: 85vh; object-fit: contain; position: relative; z-index: 1; transform: rotate(-2deg);">
    
    <!-- 文件袋上的文本 -->
    <div class="filebag-text" style="position: absolute; left: 6.5%; width: 655px; bottom: 8.5%; z-index: 2; color: #333; transform: rotate(-2deg) scale(calc(1vw/10)); font-family: 'STXINWEI', serif; text-align: left;user-select: none; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none;">
      <?php echo $filebagText; ?>
    </div>
    
    <!-- 卡片作为容器 -->
    <div class="card-container" style="position: absolute; right: 7.5%; bottom: 51%; width: 90%; height: auto; z-index: 10; transform: rotate(2deg);">
      <img src="elements/Information/Card.png" draggable="false" style="width: 100%; height: auto;">
      
      <!-- 头像链接区域 - 现在作为卡片的子元素 -->
      <a href="https://space.bilibili.com/2124647716" target="_blank" class="avatar-link">
        <div class="avatar-container">
          <img src="elements/Information/informhead.png" draggable="false" class="avatar-image">
        </div>
      </a>
      
      <!-- 文本框 - 现在作为卡片的子元素 -->
      <div class="information-text-container" style="position: absolute; left: 47.5%; width: 325px; top: 35%; text-align: center; z-index: 12; color: rgb(255, 255, 255); font-weight: 600; letter-spacing: 0em; overflow: hidden; text-overflow: ellipsis; transform: scale(calc(1vw/10));">
        <div class="information-description" style="text-align: left; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($description); ?></div>
      </div>
    </div>
  </div>
</div>

<script>
// 计算并设置信息组件的位置和缩放比例
function calculateInformationPosition() {
  const infoContainer = document.querySelector('.information-container');
  if (!infoContainer) return;
  
  // 确保信息组件位置正确 - 水平居中，垂直位于55%
  infoContainer.style.left = '50%';
  infoContainer.style.top = '55%';
  infoContainer.style.transform = 'translate(-50%, -50%)';
  infoContainer.style.width = '70%';
  
  // 获取文件袋背景图片的实际尺寸
  const filebagBg = document.querySelector('.filebag-bg');
  const cardContainer = document.querySelector('.card-container');
  
  if (filebagBg && cardContainer) {
    // 获取文件袋背景的实际宽度
    const filebagWidth = filebagBg.offsetWidth;
    // 获取卡片容器的实际宽度
    const cardWidth = cardContainer.offsetWidth;
    
    // 基于设计时的参考尺寸计算比例
    const referenceFilebagWidth = 800; // 假设设计时文件袋宽度为800px
    const referenceCardWidth = 720;    // 假设设计时卡片宽度为720px
    
    // 计算实际缩放比例
    const filebagScale = filebagWidth / referenceFilebagWidth;
    const cardScale = cardWidth / referenceCardWidth;
    
    // 处理文件袋文本 - 保留旋转效果，使用实际比例缩放
    const filebagText = document.querySelector('.filebag-text');
    if (filebagText) {
      // 使用文件袋的实际缩放比例
      filebagText.style.transform = `rotate(-2deg) scale(${filebagScale})`;
    }
    
    // 处理信息文本容器 - 使用卡片的实际缩放比例
    const infoTextContainer = document.querySelector('.information-text-container');
    if (infoTextContainer) {
      infoTextContainer.style.transform = `scale(${cardScale})`;
    }
  } else {
    // 如果无法获取实际尺寸，则使用视口宽度作为备选方案
    const viewportWidth = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
    const scale = Math.min(Math.max(viewportWidth / 900, 1.0), 1.8);
    
    const filebagText = document.querySelector('.filebag-text');
    if (filebagText) {
      filebagText.style.transform = `rotate(-2deg) scale(${scale})`;
    }
    
    const infoTextContainer = document.querySelector('.information-text-container');
    if (infoTextContainer) {
      infoTextContainer.style.transform = `scale(${scale})`;
    }
  }
}

// 注册窗口大小变化事件
window.addEventListener('resize', calculateInformationPosition);

// 页面加载后初始化位置
document.addEventListener('DOMContentLoaded', function() {
  // 等待图片加载完成后再计算位置和缩放
  window.addEventListener('load', calculateInformationPosition);
  
  // 仍然保留延时调用，以防图片加载事件不触发
  setTimeout(calculateInformationPosition, 500);
});
</script> 

	