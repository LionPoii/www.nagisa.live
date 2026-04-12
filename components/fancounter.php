<?php
// 从API中获取B站粉丝数数据
$apiUrl = "/api/fans_count_api.php";
$fans_count = 0;

try {
    // 尝试从数据库直接获取
    require_once __DIR__ . '/../includes/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // 获取保存的粉丝数
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_followers'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && !empty($result['config_value'])) {
        $fans_count = (int)$result['config_value'];
    }
} catch (Exception $e) {
    // 出错时使用默认值
    $fans_count = 0;
}
?>

<!-- 怀表收集浮窗容器 -->
<div class="fancounter-wrapper">
  <div class="fancounter-counter">
    <div class="fancounter-container">
      <div class="fancounter-text-container">
        <div class="counter-header">已收集怀表:</div>
        <div class="counter-value-container">
            <span class="counter-number" data-target="<?php echo $fans_count; ?>">0</span>
        </div>
      </div>
    </div>
    <div class="clock-image">
      <img src="/assets/fancounter/clock.png" alt="怀表">
    </div>
  </div>
</div>

<style>
/* 自定义字体定义 */
@font-face {
    font-family: 'QiantuHouhei';
    src: url('/assets/webfonts/QIANTUHOUHEI.TTF') format('truetype');
    font-weight: normal;
    font-style: normal;
}

@font-face {
    font-family: 'KingHwaOldSongv3.0';
    src: url('/assets/webfonts/KingHwaOldSongv3.0.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
}

/* 怀表收集包装器样式 */
.fancounter-wrapper {
    position: absolute;
    top: 30%; /* 垂直位置 */
    left: 7.5%; /* 初始值，确保在页面加载时就有一个合适的位置 */
    z-index: 10;
    pointer-events: none; /* 让鼠标事件穿透包装器 */
    height: auto;
    width: auto;
}

/* 怀表收集浮窗容器样式 */
.fancounter-counter {
    position: relative;
    pointer-events: auto; /* 恢复组件的鼠标交互 */
    width: 405px; /* 原450px的90% */
    overflow: hidden; /* 隐藏超出部分 */
    transform: scale(0.9); /* 整体缩小到90% */
    transform-origin: left center; /* 从左侧中心点缩放 */
}

/* 怀表容器样式 */
.fancounter-container {
    display: flex;
    flex-direction: column;
    align-items: flex-start; /* 左对齐 */
    justify-content: center;
    background-color:rgb(43, 46, 53);
    border-radius: 15px; /* 增加弧度 */
    padding: 2px; /* 统一内边距 */
    color: white;
    text-decoration: none;
    backdrop-filter: blur(5px);
    position: relative; /* 为伪元素定位 */
    box-sizing: border-box;
    width: 360px; /* 原400px的90% */
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3), 0 5px 10px rgba(0, 0, 0, 0.2); /* 添加阴影增强立体感 */
}

/* 文本容器样式 */
.fancounter-text-container {
    display: flex;
    flex-direction: column;
    align-items: flex-start; /* 左对齐 */
    background-color:rgb(96, 103, 114);
    background-image: 
        linear-gradient(135deg, rgba(255, 255, 255, 0.05) 25%, transparent 25%),
        linear-gradient(225deg, rgba(255, 255, 255, 0.05) 25%, transparent 25%),
        linear-gradient(45deg, rgba(255, 255, 255, 0.05) 25%, transparent 25%),
        linear-gradient(315deg, rgba(255, 255, 255, 0.05) 25%, transparent 25%); /* 添加细微纹理 */
    background-size: 20px 20px; /* 纹理大小 */
    border-radius: 15px; /* 增加弧度 */
    padding: 20px 25px; /* 内边距 */
    width: calc(100% - 10px); /* 恢复原来的宽度计算 */
    box-sizing: border-box;
    margin: 5px; /* 与父容器内边距相等 */
    border: 4px dashed rgba(255, 255, 255, 0.6); /* 简单的虚线边框 */
    user-select: none; /* 防止文本被选中 */
    -webkit-user-select: none; /* Safari */
    -moz-user-select: none; /* Firefox */
    -ms-user-select: none; /* IE/Edge */
    box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.1); /* 内部阴影增强质感 */
}

/* 移除悬浮效果 */
/* .fancounter-container:hover {
    background-color: #545a68;
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
} */

/* 计数器标题样式 */
.counter-header {
    font-family: 'KingHwaOldSongv3.0', sans-serif; /* 描述文本使用KingHwaOldSongv3.0字体 */
    font-size: calc(21.6px + 0.9vh); /* 原(24px + 1vh)的90% */
    line-height: 1.5;
    text-align: left; /* 左对齐 */
    letter-spacing: 2px;
    font-weight: bold; /* 设置为加粗 */
    color: white;
    width: 100%;
    margin-bottom: 12px; /* 增加底部间距 */
    user-select: none; /* 防止文本被选中 */
}

/* 数字显示区域样式 */
.counter-value-container {
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: white;
    border-radius: 8px; /* 增加圆角 */
    padding: 10px 30px; /* 增加左右内边距以适应更大的字符间距 */
    width: 100%; /* 占满容器宽度 */
    margin-top: 10px; /* 增加顶部间距 */
    height: 60px; /* 增加高度 */
}

/* 数字样式 - 调整颜色 */
.counter-number {
    font-size: calc(25.2px + 1.08vh); /* 原(28px + 1.2vh)的90% */
    font-weight: normal; /* 设置为正常字重，取消加粗 */
    font-family: 'QiantuHouhei', sans-serif; /* 数字显示仅使用千图后黑字体 */
    color: #e09c4b; /* 调暗数字颜色 */
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
    display: inline-block;
    text-align: center;
    transition: color 0.3s ease, transform 0.3s ease;
    user-select: none; /* 防止文本被选中 */
    letter-spacing: 3.6px; /* 原4px的90% */
}

/* 添加数字变化时的效果类 */
.counter-number.changing {
    color: #ffffff;
    text-shadow: 0 0 8px rgba(255, 255, 255, 0.7);
    transform: scale(1.1);
}

/* 怀表图片样式 */
.clock-image {
    position: absolute;
    right: -30px;
    top: 60%;
    transform: translateY(-50%) rotate(-30deg); /* 向左旋转30度 */
    width: 216px; /* 原240px的90% */
    height: 216px; /* 原240px的90% */
    z-index: 2;
}

.clock-image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

/* 适配移动设备 */
@media (max-width: 768px) {
    .fancounter-counter {
        width: 324px; /* 原360px的90% */
        transform: scale(0.9);
    }
    
    .fancounter-container {
        width: 288px; /* 原320px的90% */
        padding: 2px; /* 统一内边距 */
        border-radius: 12px; /* 移动设备上稍小的弧度 */
    }
    
    .fancounter-text-container {
        padding: 15px 20px;
        border-width: 3px; /* 移动设备上稍微细一点的边框 */
        width: calc(100% - 4px); /* 恢复原来的宽度计算 */
        margin: 2px; /* 与父容器内边距相等 */
        border-radius: 12px; /* 移动设备上稍小的弧度 */
    }
    
    .clock-image {
        width: 120px; /* 放大图片但适应移动设备 */
        height: 120px; /* 放大图片但适应移动设备 */
        right: -30px;
        top: 40%; /* 调整位置，确保底部不超出 */
        transform: translateY(-50%) rotate(-45deg); /* 向左旋转45度 */
    }
    
    .counter-header {
        font-size: calc(19.8px + 0.72vh); /* 原(22px + 0.8vh)的90% */
    }
    
    .counter-number {
        font-size: calc(21.6px + 0.72vh); /* 原(24px + 0.8vh)的90% */
    }
    
    .counter-value-container {
        height: 45px; /* 原50px的90% */
    }
}
</style>

<script>
// 页面加载后从API获取最新数据并执行数字滚动动画
document.addEventListener('DOMContentLoaded', function() {
    // 获取计数器元素
    const counterElement = document.querySelector('.counter-number');
    if (!counterElement) return;
    
    // 初始值
    let startValue = 0;
    // 目标值从data-target属性获取
    const targetValue = parseInt(counterElement.getAttribute('data-target'));
    // 动画持续时间(毫秒) - 进一步缩短动画时间
    const duration = 600;
    // 动画计时器
    let animationTimer = null;
    
    // 格式化数字的函数
    const formatNumber = (num) => {
        return new Intl.NumberFormat().format(Math.floor(num));
    };
    
    // 执行滚动动画
    const animateCounter = (start, end, duration) => {
        // 开始时间
        const startTime = performance.now();
        
        // 如果有正在进行的动画，先清除
        if (animationTimer) {
            cancelAnimationFrame(animationTimer);
        }
        
        // 更新显示为起始值
        counterElement.textContent = formatNumber(start);
        
        // 计算动画需要的总步数 - 减少步数以加快速度
        const difference = Math.abs(end - start);
        // 减少步数范围以加快变化速度
        const totalSteps = Math.min(Math.max(20, Math.floor(difference / 20)), 80);
        
        // 跟踪当前步数
        let currentStep = 0;
        
        // 生成一个非线性步长序列 - 先快后慢
        const generateStepSizes = (steps, total) => {
            const stepSizes = [];
            let remaining = total;
            
            for (let i = 0; i < steps; i++) {
                // 计算此步应该移动的比例 - 先大后小
                const progress = i / (steps - 1);
                const factor = 1 - Math.pow(progress, 2); // 非线性比例，先大后小
                
                // 确保最后一步精确到达目标值
                if (i === steps - 1) {
                    stepSizes.push(remaining);
                } else {
                    const thisStep = Math.ceil(factor * remaining / (1 + factor * (steps - i - 1)));
                    stepSizes.push(thisStep);
                    remaining -= thisStep;
                }
            }
            
            return stepSizes;
        };
        
        // 生成步长序列
        const direction = end > start ? 1 : -1;
        const stepSizes = generateStepSizes(totalSteps, Math.abs(end - start));
        
        // 计算帧之间的延迟时间 - 整体加快
        const getFrameDelay = (step, totalSteps) => {
            const progress = step / totalSteps;
            
            // 设计三阶段变化曲线：快-慢-快，但整体更快
            if (progress < 0.3) {
                // 前30%更快速递增(2-8ms)
                return 2 + 6 * (progress / 0.3);
            } else if (progress < 0.7) {
                // 中间40%适中速度(8-20ms)
                const midProgress = (progress - 0.3) / 0.4;
                return 8 + 12 * midProgress;
            } else {
                // 最后30%快速完成(2ms)
                return 2;
            }
        };
        
        // 记录上一帧的时间
        let lastFrameTime = 0;
        
        // 动画函数
        const updateCounter = (currentTime) => {
            // 计算自上一帧以来经过的时间
            const deltaTime = lastFrameTime === 0 ? 0 : currentTime - lastFrameTime;
            
            // 如果还有步数要执行
            if (currentStep < totalSteps) {
                // 计算当前步的延迟时间
                const frameDelay = getFrameDelay(currentStep, totalSteps);
                
                // 如果经过的时间超过了当前帧的延迟时间，或者是第一帧
                if (deltaTime >= frameDelay || lastFrameTime === 0) {
                    // 应用当前步的变化
                    const stepSize = direction * stepSizes[currentStep];
                    const newValue = start + direction * stepSizes.slice(0, currentStep + 1).reduce((a, b) => a + b, 0);
                    
                    // 更新显示
                    counterElement.textContent = formatNumber(newValue);
                    
                    // 添加变化效果类
                    counterElement.classList.add('changing');
                    
                    // 短暂延迟后移除效果类 - 与bilibili组件保持一致的动画持续时间
                    setTimeout(() => {
                        counterElement.classList.remove('changing');
                    }, 100);
                    
                    // 更新步数和时间
                    currentStep++;
                    lastFrameTime = currentTime;
                }
                
                // 请求下一帧
                animationTimer = requestAnimationFrame(updateCounter);
            } else {
                // 确保最终值精确
                counterElement.textContent = formatNumber(end);
                
                // 添加一个最终的变化效果
                counterElement.classList.add('changing');
                setTimeout(() => {
                    counterElement.classList.remove('changing');
                }, 300);  // 延长最终效果时间与bilibili组件保持一致
            }
        };
        
        // 开始动画
        animationTimer = requestAnimationFrame(updateCounter);
    };
    
    // 计算怀表计数器位置的函数
    function calculateFancounterPosition() {
        // 获取information组件的位置信息
        const infoContainer = document.querySelector('.information-container');
        if (!infoContainer) return;
        
        const infoRect = infoContainer.getBoundingClientRect();
        const infoLeftEdge = infoRect.left;
        
        // 计算左侧位置 - information组件左边缘与浏览器左边缘之间的中点
        const leftPosition = infoLeftEdge / 2;
        
        // 转换为百分比
        const viewportWidth = window.innerWidth;
        const leftPercent = (leftPosition / viewportWidth) * 100;
        
        // 应用新的位置
        const watchWrapper = document.querySelector('.fancounter-wrapper');
        if (watchWrapper) {
            watchWrapper.style.left = leftPercent + '%';
            // 移除transform属性，与bilibili_live_status保持一致
            watchWrapper.style.transform = '';
        }
    }
    
    // 立即调整位置 - 不需要等待，直接计算
    calculateFancounterPosition();
    
    // 等待页面加载遮罩消失后再开始动画
    const waitForLoadingMaskRemoval = () => {
        const globalLoading = document.getElementById('global-loading');
        const mainContent = document.getElementById('main-content');
        
        // 检查加载状态
        if (globalLoading && globalLoading.style.opacity === '0' && 
            mainContent && mainContent.style.display === 'block') {
            // 加载遮罩已消失，开始动画
            setTimeout(() => {
                animateCounter(startValue, targetValue, duration);
            }, 300); // 减少等待时间
        } else {
            // 继续等待
            setTimeout(waitForLoadingMaskRemoval, 100);
        }
    };
    
    // 开始等待加载遮罩消失
    waitForLoadingMaskRemoval();

    // 从API获取最新数据
    setTimeout(() => {
        fetch('/api/fans_count_api.php')
            .then(response => response.json())
            .then(data => {
                if (data && data.fans_count) {
                    const newTargetValue = data.fans_count;
                    const currentValue = parseInt(counterElement.textContent.replace(/,/g, ''));
                    
                    // 如果API返回的值与当前显示值不同，开始新动画
                    if (newTargetValue !== currentValue) {
                        // 更新data-target属性
                        counterElement.setAttribute('data-target', newTargetValue);
                        // 启动新动画，从当前值到新目标值
                        animateCounter(currentValue, newTargetValue, duration);
                    }
                }
            })
            .catch(error => console.error('获取粉丝数据失败:', error));
    }, 2000); // 减少API获取等待时间

    // 移除下面的代码，避免干扰整体缩放
    /*
    // 监听窗口大小变化事件
    window.addEventListener('resize', calculateFancounterPosition);
    
    // 监听滚动事件
    document.addEventListener('scroll', calculateFancounterPosition);
    
    // 监听DOM变化，在关键元素位置变化时重新计算位置
    const observer = new MutationObserver(function(mutations) {
        for (let mutation of mutations) {
            if (mutation.type === 'attributes' && 
                mutation.attributeName === 'style' && 
                mutation.target.classList.contains('information-container')) {
                calculateFancounterPosition();
                break;
            }
        }
    });
    
    // 开始观察information组件的样式变化
    const infoContainer = document.querySelector('.information-container');
    if (infoContainer) {
        observer.observe(infoContainer, { attributes: true });
    }
    */
});
</script> 