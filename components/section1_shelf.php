<?php
// 置物架组件 - 包含水平木条和两个竖立木条
// 可以在这里添加一些逻辑，例如获取物品数量
$items_count = 0;

try {
    // 从数据库获取物品数量（示例，如果数据库中有相关表）
    require_once __DIR__ . '/../includes/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    // 假设有一个items表，统计物品数量
    // 这里只是示例，您可能需要调整查询
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM site_config WHERE config_key LIKE 'item_%'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && isset($result['count'])) {
        $items_count = (int)$result['count'];
    } else {
        // 如果没有数据，设置一个默认值
        $items_count = 8; // 设置默认展示的物品数量
    }
} catch (Exception $e) {
    // 出错时使用默认值
    $items_count = 8; // 默认展示的物品数量
}
?>

<!-- 整体系统容器，包含所有相关组件 -->
<div class="section1-shelf-complete-system">
    <!-- 引入怀表收集计数器组件 -->
    <div class="fancounter-wrapper shelf-fancounter">
        <?php require_once __DIR__ . '/fancounter.php'; ?>
    </div>

    <!-- B站直播状态组件 -->
    <div class="bilibili-status-wrapper-under-shelf">
        <?php require_once __DIR__ . '/bilibili_live_status.php'; ?>
    </div>

    <!-- 置物架容器，控制置物架的位置 -->
    <div class="section1-shelf-system">
        <!-- 置物架容器 -->
        <div class="section1-shelf">
            <!-- 水平杆 -->
            <div class="section1-shelf-horizontal">
                <!-- 水平杆的高光 -->
                <div class="section1-shelf-highlight"></div>
                <!-- 水平杆的纹理 -->
                <div class="section1-shelf-texture"></div>
            </div>
            
            <!-- 左侧垂直杆 -->
            <div class="section1-shelf-vertical left">
                <!-- 垂直杆的高光 -->
                <div class="section1-shelf-highlight vertical"></div>
                <!-- 垂直杆的纹理 -->
                <div class="section1-shelf-texture vertical"></div>
                <!-- 装饰点 -->
                <div class="section1-shelf-dot section1-shelf-dot-left">
                    <div class="section1-shelf-dot-highlight"></div>
                </div>
            </div>
            
            <!-- 右侧垂直杆 -->
            <div class="section1-shelf-vertical right">
                <!-- 垂直杆的高光 -->
                <div class="section1-shelf-highlight vertical"></div>
                <!-- 垂直杆的纹理 -->
                <div class="section1-shelf-texture vertical"></div>
                <!-- 装饰点 -->
                <div class="section1-shelf-dot section1-shelf-dot-right">
                    <div class="section1-shelf-dot-highlight"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* 整体系统容器样式 */
.section1-shelf-complete-system {
    position: absolute;
    top: 65%;
    left: 19%;
    transform: translate(-50%, -50%);
    z-index: 10;
    width: 500px; /* 设置一个合适的宽度 */
    height: 300px; /* 设置一个合适的高度 */
    pointer-events: none; /* 不接收鼠标事件，点击可以穿透到下面的元素 */
}

/* 修改fancounter组件的位置，使其位于置物架上方 */
.shelf-fancounter {
    position: absolute;
    top: 15.5%; /* 相对于整体系统容器的位置 */
    left: 50%; /* 水平居中 */
    transform: translateX(-50%); /* 居中对齐 */
    z-index: 19; /* 确保在水平杆下方 */
    pointer-events: auto; /* 恢复鼠标交互 */
}

/* B站直播状态组件在置物架下方的样式 */
.bilibili-status-wrapper-under-shelf {
    position: absolute;
    top: 87%; /* 相对于整体系统容器的位置 */
    left: 35%; /* 相对于整体系统容器的位置 */
    transform: translateX(-50%); /* 居中对齐 */
    z-index: 5; /* 降低z-index，使其低于置物架 */
    width: auto;
    height: 60px; /* 增加高度 */
    padding-top: 20px; /* 增加顶部内边距 */
    pointer-events: auto; /* 恢复鼠标交互 */
}

/* 修改section1_shelf的z-index，确保它在直播状态组件上方 */
.section1-shelf-system {
    position: absolute;
    top: 75.5%; /* 相对于整体系统容器的位置 */
    left: 4%; /* 水平居中 */
    z-index: 20; /* 保持较高的z-index */
    transform: translateX(-50%); /* 使中点位于50%处 */
    pointer-events: auto; /* 恢复鼠标交互 */
}

.section1-shelf {
    position: relative;
    pointer-events: none; /* 不接收鼠标事件，点击可以穿透到下面的元素 */
    z-index: 20;
}

/* 水平杆 */
.section1-shelf-horizontal {
    position: absolute;
    width: 380px; /* 缩短水平杆长度 */
    height: 20px;
    background-color: #4d4030;
    top: 50%; /* 水平杆高度位于top 50%处 */
    left: 0; /* 相对于系统容器 */
    border-radius: 3px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.2);
    overflow: hidden;
}

/* 水平杆的高光 */
.section1-shelf-highlight {
    position: absolute;
    width: 100%;
    height: 4px;
    background: linear-gradient(to bottom, rgba(255,255,255,0.3), rgba(255,255,255,0.1));
    top: 0;
    left: 0;
}

/* 水平杆的纹理 */
.section1-shelf-texture {
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
.section1-shelf-vertical {
    position: absolute;
    width: 20px;
    height: 120px; /* 缩短高度从160px到120px */
    background-color: #4d4030;
    top: -5px; /* 向上偏移，使其延伸到上方 */
    border-radius: 3px;
    box-shadow: 4px 0 6px rgba(0,0,0,0.1);
    overflow: hidden;
}

/* 左侧垂直杆 */
.section1-shelf-vertical.left {
    left: calc(380px * 0.10); /* 位于水平杆的15%处，原来是25% */
    top: -5px; /* 向上偏移，使其延伸到上方 */
}

/* 右侧垂直杆 */
.section1-shelf-vertical.right {
    left: calc(380px * 0.85); /* 位于水平杆的85%处，原来是75% */
    top: -5px; /* 向上偏移，使其延伸到上方 */
}

/* 垂直杆的高光 */
.section1-shelf-highlight.vertical {
    width: 4px;
    height: 100%;
    background: linear-gradient(to right, rgba(255,255,255,0.3), rgba(255,255,255,0.1));
}

/* 垂直杆的纹理 */
.section1-shelf-texture.vertical {
    background-image: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 10px,
        rgba(0,0,0,0.03) 10px,
        rgba(0,0,0,0.03) 12px
    );
}

/* 装饰点 */
.section1-shelf-dot {
    position: absolute;
    width: 12px;
    height: 12px;
    background: radial-gradient(circle, #3d3326 60%, #2a231a);
    border-radius: 50%;
    top: 10px; /* 调整位置，确保装饰点位于水平杆交接处 */
    box-shadow: inset 0 0 2px rgba(0,0,0,0.2), 0 1px 2px rgba(0,0,0,0.3);
    overflow: hidden;
}

/* 中部装饰点 - 添加在竖直条的60px处 */
.section1-shelf-dot-middle {
    top: 100px; /* 位于竖直条的60px处 */
    left: 4px;
}

.section1-shelf-dot-left {
    left: 4px;
}

.section1-shelf-dot-right {
    left: 4px;
}

/* 装饰点的高光 */
.section1-shelf-dot-highlight {
    position: absolute;
    width: 4px;
    height: 4px;
    background-color: rgba(255,255,255,0.4);
    border-radius: 50%;
    top: 2px;
    left: 2px;
}

/* 垂直杆的挂钩 */
.section1-shelf-vertical::after {
    content: '';
    position: absolute;
    width: 14px;
    height: 10px;
    background-color: #3d3326;
    border-radius: 0 0 7px 7px;
    bottom: -10px;
    left: 3px;
    box-shadow: 0 2px 3px rgba(0,0,0,0.3);
}

/* 在左侧竖直条添加中部装饰点 */
.section1-shelf-vertical.left::before {
    content: '';
    position: absolute;
    width: 12px;
    height: 12px;
    background: radial-gradient(circle, #3d3326 60%, #2a231a);
    border-radius: 50%;
    top: 95px; /* 位于竖直条的80px处 */
    left: 4px;
    box-shadow: inset 0 0 2px rgba(0,0,0,0.2), 0 1px 2px rgba(0,0,0,0.3);
    z-index: 21;
}

/* 在右侧竖直条添加中部装饰点 */
.section1-shelf-vertical.right::before {
    content: '';
    position: absolute;
    width: 12px;
    height: 12px;
    background: radial-gradient(circle, #3d3326 60%, #2a231a);
    border-radius: 50%;
    top: 95px; /* 位于竖直条的80px处 */
    left: 4px;
    box-shadow: inset 0 0 2px rgba(0,0,0,0.2), 0 1px 2px rgba(0,0,0,0.3);
    z-index: 21;
}

/* 左侧竖直条中部装饰点的高光 */
.section1-shelf-vertical.left::after {
    content: '';
    position: absolute;
    width: 4px;
    height: 4px;
    background-color: rgba(255,255,255,0.4);
    border-radius: 50%;
    top: 97px; /* 位于装饰点中心 */
    left: 6px;
    z-index: 22;
}

/* 右侧竖直条中部装饰点的高光 */
.section1-shelf-vertical.right::after {
    content: '';
    position: absolute;
    width: 4px;
    height: 4px;
    background-color: rgba(255,255,255,0.4);
    border-radius: 50%;
    top: 97px; /* 位于装饰点中心 */
    left: 6px;
    z-index: 22;
}

/* 适配移动设备 */
@media (max-width: 768px) {
    .shelf-fancounter {
        top: 30% !important; /* 移动设备上直接设置一个固定的top值 */
        left: 10% !important; /* 移动设备上调整位置，与水平杆一致 */
        transform: translateX(-50%) !important; /* 确保居中对齐 */
        z-index: 19 !important; /* 确保在水平杆下方 */
    }
    
    /* 移动设备上B站直播状态组件的样式 */
    .bilibili-status-wrapper-under-shelf {
        top: 60% !important; /* 移动设备上的固定位置 */
        left: 30% !important; /* 移动设备上的固定位置 */
        margin-top: 0; /* 移除间距 */
        transform: translateX(-50%) scale(0.8) !important; /* 稍微缩小组件 */
    }
    
    .section1-shelf-horizontal {
        width: 260px; /* 缩短水平杆长度 */
        height: 15px;
        top: 50%; /* 水平杆高度位于top 50%处 */
    }
    
    .section1-shelf-vertical {
        width: 15px;
        height: 60px; /* 缩短高度从110px到80px */
        top: -10px; /* 向上偏移，使其延伸到上方 */
    }
    
    .section1-shelf-vertical.left {
        left: calc(260px * 0.15); /* 位于水平杆的15%处，与中点对称 */
        top: -30px; /* 向上偏移，使其延伸到上方 */
    }
    
    .section1-shelf-vertical.right {
        left: calc(260px * 0.85); /* 位于水平杆的85%处，与中点对称 */
        top: -30px; /* 向上偏移，使其延伸到上方 */
        right: auto; /* 移除右侧定位 */
    }
    
    .section1-shelf-vertical::after {
        width: 11px;
        height: 8px;
        bottom: -8px;
        left: 2px;
        border-radius: 0 0 5px 5px;
    }
    
    .section1-shelf-dot {
        width: 9px;
        height: 9px;
        top: 40px; /* 调整位置，确保装饰点位于水平杆交接处 */
    }
    
    .section1-shelf-dot-left,
    .section1-shelf-dot-right {
        left: 3px;
    }
    
    .section1-shelf-dot-middle {
        left: 3px;
    }
    
    .section1-shelf-dot-highlight {
        width: 3px;
        height: 3px;
    }
    
    /* 移动设备上的位置调整 */
    .section1-shelf-complete-system {
        width: 400px; /* 移动设备上调整宽度 */
        height: 250px; /* 移动设备上调整高度 */
    }
}
</style>

<!-- 添加缩放脚本 -->
<script>
// 计算并设置整体系统的缩放比例
function calculateCompleteShelfScale() {
    const completeSystem = document.querySelector('.section1-shelf-complete-system');
    if (!completeSystem) return;
    
    // 获取窗口宽度和高度
    const windowWidth = window.innerWidth;
    const windowHeight = window.innerHeight;
    
    // 基准尺寸（设计时的窗口尺寸）
    const baseWidth = 1920; // 假设设计时的宽度为1920px
    const baseHeight = 1080; // 假设设计时的高度为1080px
    
    // 计算宽度和高度的缩放比例
    const scaleX = windowWidth / baseWidth;
    const scaleY = windowHeight / baseHeight;
    
    // 使用较小的缩放比例，确保组件完全显示且保持固定比例
    const scale = Math.min(scaleX, scaleY);
    
    // 应用缩放，保持位置不变
    completeSystem.style.transform = `translate(-50%, -50%) scale(${scale})`;
    
    // 设置transform-origin确保从中心点缩放
    completeSystem.style.transformOrigin = 'center center';
}

// 注册窗口大小变化事件
window.addEventListener('resize', calculateCompleteShelfScale);

// 页面加载后初始化缩放
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(calculateCompleteShelfScale, 500);
});
</script> 