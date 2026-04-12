<?php
/**
 * 杂物堆跳转按钮组件
 */
?>
<!-- 杂物堆跳转按钮 -->
<a href="#" id="clutter-btn" onclick="showCustomModal('即将跳转到推理社杂物堆...'); setTimeout(() => window.open('https://docs.qq.com/sheet/DT1dUUWdKY29FZW5F?tab=ss_4pv7e0&viewId=v1wXeP', '_blank'), 1500); return false;" style="text-decoration: none;">
    <div class="clutter-container" 
         style="position: absolute; 
                right: 8.5%; 
                bottom: 5%; 
                height: 5vh; 
                width: auto;
                cursor: pointer;
                transition: transform 0.3s ease;">
        <img src="elements/clutter/clutter icon.png" 
             alt="Clutter Icon" 
             class="clutter-image" 
             style="height: 100%; 
                    width: auto;
                    transition: transform 0.3s ease;">
        <div class="clutter-text" style="position: absolute; 
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

<style>
/* 杂物堆按钮特定样式 */
.clutter-container:hover {
    transform: scale(1.05);
}

.clutter-container:hover .clutter-text {
    color: #cc9471 !important;
}

.clutter-container:hover .clutter-image {
    transform: translateY(-10px);
}

/* 添加浮动动画 */
.clutter-container {
    animation: floating 3s ease-in-out infinite;
}

@keyframes floating {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}
</style>

<script>
// 组件加载完成后调整字体大小
document.addEventListener('DOMContentLoaded', function() {
    // 调整clutter按钮字体大小的函数
    function adjustClutterFontSize() {
        const clutterImage = document.querySelector('.clutter-image');
        const clutterText = document.querySelector('.clutter-text');
        if (clutterImage && clutterText) {
            const height = clutterImage.offsetHeight;
            // 确保有最小值，防止字体消失
            clutterText.style.fontSize = height > 0 ? `${Math.max(height * 0.2, 16)}px` : '16px';
        }
    }
    
    // 初始调整
    adjustClutterFontSize();
    
    // 监听窗口大小变化
    window.addEventListener('resize', adjustClutterFontSize);
    
    // 确保在图片加载后调整大小
    const clutterImage = document.querySelector('.clutter-image');
    if (clutterImage) {
        clutterImage.onload = adjustClutterFontSize;
    }
});
</script> 