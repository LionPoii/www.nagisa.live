<?php
// === PHP数据处理部分 ===
require_once __DIR__ . '/../includes/bilibili_live.php';

// 如果是检查直播状态请求，返回当前直播状态
if (isset($_GET['check_status']) && $_GET['check_status'] == '1') {
    header('Content-Type: application/json');
    
    // 从数据库获取配置的直播间ID
    $roomId = 31368705; // 默认直播间ID
    try {
        require_once __DIR__ . '/../includes/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_room_id'");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($config && !empty($config['config_value'])) {
            $roomId = $config['config_value'];
        }
    } catch (Exception $e) {
        // 出错时使用默认值
    }
    
    $biliLive = new BilibiliLive($roomId);
    $isLiving = $biliLive->isLiving(); // 直播状态：在线/离线
    $title = $biliLive->getTitle();    // 直播标题
    
    $response = [
        'success' => true,
        'room_id' => $roomId,
        'is_living' => $isLiving,
        'title' => $title
    ];
    
    echo json_encode($response);
    exit;
}

// 初始化B站直播检测类
$roomId = 31368705; // 默认直播间ID

// 从数据库获取配置的直播间ID
try {
    require_once __DIR__ . '/../includes/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT config_value FROM site_config WHERE config_key = 'bilibili_room_id'");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($config && !empty($config['config_value'])) {
        $roomId = $config['config_value'];
    }
} catch (Exception $e) {
    // 出错时使用默认值
}

$biliLive = new BilibiliLive($roomId);

// 获取直播状态
$isLiving = $biliLive->isLiving(); // 直播状态：在线/离线
$title = $biliLive->getTitle();    // 直播标题
?>

<!-- === HTML结构部分 === -->
<!-- 整体容器 -->
<div id="bilibiliLiveStatus" class="bilibili-live-status">
    <!-- 点击跳转到B站直播间的链接 -->
    <a href="https://live.bilibili.com/<?php echo htmlspecialchars($roomId); ?>" target="_blank" class="live-status-indicator <?php echo $isLiving ? 'online' : 'offline'; ?>">
        <!-- 状态指示灯容器 -->
        <div class="status-light-container">
            <!-- 状态指示灯：根据直播状态动态切换样式 -->
            <div class="status-light <?php echo $isLiving ? 'online' : 'offline'; ?>"></div>
        </div>
        <!-- 状态信息容器 -->
        <div class="status-info">
            <!-- 状态文本：在线显示"推理案件中"，离线显示"暂时离开" -->
            <div class="status-text">
                <?php echo $isLiving ? '推理案件中' : '暂时离开'; ?>
            </div>
        </div>
    </a>
</div>

<!-- === CSS样式部分 === -->
<style>
/* 自定义字体定义 */
@font-face {
    font-family: 'QiantuHouhei';
    src: url('/assets/webfonts/QIANTUHOUHEI.TTF') format('truetype');
    font-weight: normal;
    font-style: normal;
}

/* 右上角固定定位包装器 */
.bilibili-status-wrapper {
    position: absolute;
    top: 15%;
    /* left值将由JavaScript动态设置 */
    z-index: 10;
    pointer-events: none; /* 让鼠标事件穿透包装器 */
    height: 10vh; /* 减少高度从15vh到10vh */
    width: auto;
}

.bilibili-status-wrapper .bilibili-live-status {
    pointer-events: auto; /* 恢复组件的鼠标交互 */
    height: 100%; /* 填满父容器高度 */
}

/* 整体容器样式 */
.bilibili-live-status {
    display: inline-block;
    margin: 0 auto;
    text-align: center;
    position: relative;
    z-index: 10;
    height: 80px; /* 固定高度，放大一倍 */
    width: 400px; /* 固定宽度，放大一倍 */
}

/* 状态指示器样式：包含指示灯和文本的整体容器 - 吊牌风格 */
.live-status-indicator {
    display: flex;
    flex-direction: row;        /* 横向布局 */
    align-items: center;
    background-color: #5D4037; /* 紫檀木色调 */
    padding: 16px 40px;  /* 使用固定像素值替代vh单位，放大一倍 */
    border-radius: 16px;        /* 圆角边框，吊牌风格，放大一倍 */
    text-decoration: none;
    color: white;
    transition: all 0.3s ease;  /* 过渡动画效果 */
    border: none;
    transform: scale(1);      /* 修改为默认大小 */
    cursor: pointer; /* 确保鼠标指针变为手型 */
    position: relative; /* 为伪元素定位 */
    height: 80px;  /* 固定高度，放大一倍 */
    box-sizing: border-box;
    width: 400px; /* 固定宽度，放大一倍 */
    /* 增强木质纹理背景 */
    background-image: 
        /* 基础颜色渐变 */
        linear-gradient(90deg, 
            rgba(93, 64, 55, 0.8) 0%, 
            rgba(93, 64, 55, 0.9) 30%, 
            rgba(93, 64, 55, 0.8) 70%, 
            rgba(93, 64, 55, 0.9) 100%),
        /* 水平木纹 */
        repeating-linear-gradient(0deg, 
            transparent, 
            transparent 8px, 
            rgba(0,0,0,0.05) 8px, 
            rgba(0,0,0,0.05) 10px),
        /* 垂直细纹理 */
        repeating-linear-gradient(90deg,
            transparent,
            transparent 15px,
            rgba(0,0,0,0.02) 15px,
            rgba(0,0,0,0.02) 16px),
        /* 木节点效果 */
        radial-gradient(
            ellipse at 30% 40%,
            rgba(120, 80, 70, 0.2) 0%,
            transparent 50%
        ),
        radial-gradient(
            ellipse at 70% 60%,
            rgba(120, 80, 70, 0.15) 0%,
            transparent 50%
        ),
        /* 添加更多木纹细节 */
        repeating-linear-gradient(45deg,
            transparent,
            transparent 20px,
            rgba(0,0,0,0.01) 20px,
            rgba(0,0,0,0.01) 22px
        ),
        repeating-linear-gradient(-45deg,
            transparent,
            transparent 30px,
            rgba(0,0,0,0.01) 30px,
            rgba(0,0,0,0.01) 31px
        );
    border: 1px solid rgba(110, 80, 70, 0.7); /* 紫檀木边框 */
    /* 添加内部阴影增强立体感 */
    box-shadow: 
        0 4px 8px rgba(0,0,0,0.3),
        inset 0 1px 3px rgba(255,255,255,0.2),
        inset 0 -1px 3px rgba(0,0,0,0.2);
    /* 添加微妙的边缘高光 */
    position: relative;
    overflow: hidden;
}

/* 添加木质边缘高光效果 */
.live-status-indicator::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(to right, 
        rgba(255,255,255,0.05), 
        rgba(255,255,255,0.2) 50%, 
        rgba(255,255,255,0.05)
    );
    pointer-events: none;
}

/* 悬停效果 - 取消 */
.live-status-indicator:hover {
    background-color: #5D4037; /* 与正常状态相同，更新颜色 */
    transform: scale(1);     /* 取消放大效果 */
    box-shadow: 
        0 4px 8px rgba(0,0,0,0.3),
        inset 0 1px 3px rgba(255,255,255,0.2),
        inset 0 -1px 3px rgba(0,0,0,0.2);
}

/* 点击效果 */
.live-status-indicator:active {
    transform: scale(0.98);
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

/* 添加一个点击波纹效果 */
.live-status-indicator::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.2);
    opacity: 0;
    transform: scale(0.5);
    transition: all 0.3s ease;
    pointer-events: none; /* 不影响鼠标事件 */
}

.live-status-indicator:active::after {
    opacity: 1;
    transform: scale(1);
}

/* 状态指示灯容器 */
.status-light-container {
    position: relative;
    width: 40px; /* 增加宽度 */
    height: 40px; /* 增加高度 */
    margin-right: 40px; /* 保持右侧边距 */
    margin-left: -10px; /* 保持左侧边距 */
    border: 2px solid rgba(255, 255, 255, 0.4); /* 减小边框厚度 */
    border-radius: 50%;
    padding: 4px; /* 保持内边距 */
    box-shadow: 
        inset 0 0 8px rgba(0,0,0,0.3),
        0 0 4px rgba(255,255,255,0.2);
    background: rgba(0,0,0,0.1);
}

/* 状态指示灯基本样式 */
.status-light {
    position: absolute;
    top: 4px;
    left: 4px;
    right: 4px;
    bottom: 4px;
    border-radius: 50%;
}

/* 在线状态样式：绿色带脉冲动画 */
.status-light.online {
    background-color: #4CAF50; /* 绿色 */
    box-shadow: 0 0 10px #4CAF50; /* 发光效果 */
    animation: breathe 2s infinite; /* 呼吸灯动画 */
}

/* 离线状态样式：灰色无动画 */
.status-light.offline {
    background-color:rgb(151, 141, 140); /* 冷灰色 */
    box-shadow: none;
    animation: none;
}

/* 添加呼吸灯动画 */
@keyframes breathe {
    0% {
        box-shadow: 0 0 5px 2px rgba(76, 175, 80, 0.4);
        opacity: 0.8;
    }
    50% {
        box-shadow: 0 0 20px 5px rgba(76, 175, 80, 0.7);
        opacity: 1;
    }
    100% {
        box-shadow: 0 0 5px 2px rgba(76, 175, 80, 0.4);
        opacity: 0.8;
    }
}

/* 状态信息容器 */
.status-info {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center; /* 水平居中 */
    background-color: #f0f4f8;
    border-radius: 12px;
    padding: 16px 30px;
    white-space: nowrap;
    border: 2px solid rgba(0,0,0,0.1);
    box-shadow: 
        inset 0 0 10px rgba(0,0,0,0.05),
        0 0 4px rgba(255,255,255,0.5);
    background-image: 
        linear-gradient(45deg, rgba(0,0,0,0.03) 25%, transparent 25%, 
            transparent 50%, rgba(0,0,0,0.03) 50%, rgba(0,0,0,0.03) 75%, 
            transparent 75%, transparent);
    background-size: 20px 20px;
    width: 240px;
    height: 60px;
    margin-left: 5px; /* 向右移动文本容器 */
    position: relative;
    left: 0;
    overflow: hidden;
    transition: background-color 0.3s ease;
}

/* 添加流动效果的伪元素 */
.status-info::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%; /* 初始位置在容器外 */
    width: 100%;
    height: 100%;
    background: linear-gradient(to right, transparent, rgba(76, 175, 80, 0.2), transparent); /* 流动效果的渐变为绿色 */
    transition: left 0s ease; /* 初始无动画 */
    z-index: 1;
    pointer-events: none; /* 不影响鼠标事件 */
}

/* 离线状态下的流动效果伪元素 */
.live-status-indicator.offline .status-info::before {
    background: linear-gradient(to right, transparent, rgba(127, 127, 127, 0.2), transparent); /* 离线状态下为灰色 */
}

/* 鼠标悬停时触发流动效果 - 只在在线状态生效 */
.live-status-indicator:hover .status-info::before {
    left: 100%; /* 移动到容器右侧外 */
    transition: left 1.5s ease; /* 1.5秒完成动画 */
}

/* 状态文本样式 */
.status-text {
    font-size: 24px; /* 稍微减小字体大小 */
    font-weight: normal; /* 取消加粗 */
    letter-spacing: 3px; /* 稍微减小字间距 */
    line-height: 1.5;
    font-family: 'QiantuHouhei', sans-serif; /* 使用自定义字体 */
    color: #4C526B; /* 指定的文字颜色 */
    display: inline-block; /* 确保文本水平显示 */
    text-shadow: 0 2px 2px rgba(0,0,0,0.1); /* 文字阴影，放大一倍 */
    padding: 0 8px; /* 减小左右内边距 */
    position: relative; /* 相对定位，用于文字颜色动画 */
    z-index: 2; /* 确保文字在流动效果上方 */
    text-align: center; /* 文本居中 */
    width: 100%; /* 占满容器宽度 */
}

/* 添加文字颜色变化的动画 - 在线状态 */
@keyframes textColorFlow {
    0% {
        color: #4C526B; /* 初始颜色 */
    }
    50% {
        color: #4d4030; /* 中间颜色改为指定的棕色 */
    }
    100% {
        color: #CC9471; /* 最终颜色改为指定的浅棕色 */
    }
}

/* 鼠标悬停时触发文字颜色动画 - 只在在线状态生效 */
.live-status-indicator.online:hover .status-text {
    animation: textColorFlow 1.5s ease; /* 1.5秒完成动画 */
    color: #CC9471; /* 最终颜色为指定的浅棕色 */
}

/* 添加斜杠格子移动的动画 */
@keyframes diagonalMove {
    0% {
        background-position: 0 0;
    }
    100% {
        background-position: 20px 20px; /* 移动一个完整的格子周期 */
    }
}

/* 在线状态下鼠标悬停时触发背景格子移动动画 */
.live-status-indicator.online:hover .status-info {
    background-image: 
        linear-gradient(45deg, 
            rgba(76, 175, 80, 0.15) 25%, /* 绿色格子 */
            transparent 25%, 
            transparent 50%, 
            rgba(76, 175, 80, 0.15) 50%, 
            rgba(76, 175, 80, 0.15) 75%, 
            transparent 75%, 
            transparent
        ); /* 悬停时的斜杠格子颜色为淡绿色 */
    background-size: 20px 20px; /* 格子大小，放大一倍 */
    animation: diagonalMove 1s linear infinite; /* 无限循环动画 */
}

/* 离线状态下鼠标悬停时触发背景格子移动动画 */
.live-status-indicator.offline:hover .status-info {
    background-image: 
        linear-gradient(45deg, 
            rgba(127, 127, 127, 0.15) 25%, 
            transparent 25%, 
            transparent 50%, 
            rgba(127, 127, 127, 0.15) 50%, 
            rgba(127, 127, 127, 0.15) 75%, 
            transparent 75%, 
            transparent
        ); /* 离线状态下为灰色格子 */
    background-size: 10px 10px; /* 格子大小 */
    animation: diagonalMove 1s linear infinite; /* 无限循环动画 */
}

/* 为整个组件添加在线/离线状态类 */
.live-status-indicator {
    /* 现有样式保持不变 */
}

/* 脉冲动画定义：用于在线状态的指示灯 */
@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.2);
        opacity: 0.7;
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

/* 吊牌摇摆动画 */
@keyframes swing {
    0% { transform: rotate(-1deg); }
    50% { transform: rotate(1deg); }
    100% { transform: rotate(-1deg); }
}

/* 为置物架下的吊牌添加特殊样式 */
.bilibili-status-wrapper-under-shelf .live-status-indicator {
    /* 移除摇摆动画 */
    animation: none;
    transform-origin: top center; /* 保留旋转原点在顶部中心 */
}

/* 为置物架下的吊牌移除细绳 */
.bilibili-status-wrapper-under-shelf .live-status-indicator::before {
    display: none; /* 隐藏绳子 */
}
</style>

<!-- 添加自动检测直播状态的脚本，与通知组件集成 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 初始化直播状态检测
    setupLiveStatusChecker();
});

/**
 * 设置直播状态自动检测功能
 */
function setupLiveStatusChecker() {
    // 初始状态
    let currentStatus = {
        is_living: <?php echo $isLiving ? 'true' : 'false'; ?>,
        title: <?php echo json_encode($title); ?>
    };
    let isFirstLiveCheck = true;
    
    // 检测间隔（毫秒）- 每2秒检查一次（与通知权限无关，始终更新 UI）
    const checkInterval = 2000;
    
    /**
     * 检查直播状态
     */
    function checkLiveStatus() {
        // 创建一个新的XMLHttpRequest对象
        const xhr = new XMLHttpRequest();
        
        // 使用 live_status_notice（CDN 不缓存）；兼容 is_living 字段
        const url = '/api/live_status_notice.php?_=' + new Date().getTime();
        
        // 配置请求
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        
        // 处理响应
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const raw = JSON.parse(xhr.responseText);
                    const response = typeof raw.is_living !== 'undefined' ? raw : {
                        is_living: raw.live_status === 1,
                        title: raw.title || '直播中',
                        room_id: raw.room_id,
                        cover_url: raw.cover || raw.keyframe || raw.background || ''
                    };
                    const statusChanged = response.is_living !== currentStatus.is_living
                        || response.title !== currentStatus.title;
                    
                    // 首次轮询也要派发事件，便于「已在直播时首次进入」触发开播播报
                    if (statusChanged || isFirstLiveCheck) {
                        // 更新当前状态
                        currentStatus = {
                            is_living: response.is_living,
                            title: response.title
                        };
                        
                        // 状态变化时更新 UI（首次若与 SSR 一致则跳过 DOM 写入）
                        if (statusChanged) {
                            updateLiveStatusUI(response.is_living);
                            console.log('直播状态已更新:', response.is_living ? '在线' : '离线');
                        }
                        
                        // 触发自定义事件，通知其他组件直播状态已更新
                        const event = new CustomEvent('liveStatusChanged', { 
                            detail: { 
                                is_living: response.is_living,
                                title: response.title,
                                room_id: response.room_id,
                                cover_url: response.cover_url,
                                is_initial: isFirstLiveCheck
                            } 
                        });
                        document.dispatchEvent(event);
                    }
                    
                    isFirstLiveCheck = false;
                } catch (e) {
                    console.error('解析直播状态响应失败:', e);
                }
            }
        };
        
        // 处理错误
        xhr.onerror = function() {
            console.error('检查直播状态请求失败');
        };
        
        // 发送请求
        xhr.send();
    }
    
    /**
     * 更新直播状态UI
     * @param {boolean} isLiving - 是否正在直播
     */
    function updateLiveStatusUI(isLiving) {
        // 获取相关元素
        const statusIndicator = document.querySelector('.live-status-indicator');
        const statusLight = document.querySelector('.status-light');
        const statusText = document.querySelector('.status-text');
        
        if (!statusIndicator || !statusLight || !statusText) {
            console.error('找不到直播状态UI元素');
            return;
        }
        
        // 更新状态指示器类
        if (isLiving) {
            statusIndicator.classList.remove('offline');
            statusIndicator.classList.add('online');
            statusLight.classList.remove('offline');
            statusLight.classList.add('online');
            statusText.textContent = '推理案件中';
        } else {
            statusIndicator.classList.remove('online');
            statusIndicator.classList.add('offline');
            statusLight.classList.remove('online');
            statusLight.classList.add('offline');
            statusText.textContent = '暂时离开';
        }
    }
    
    // 暴露方法给全局，以便其他组件可以调用
    window.updateLiveStatusUI = updateLiveStatusUI;
    
    // 设置定期检查
    setInterval(checkLiveStatus, checkInterval);
    
    // 页面可见性变化时检查
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            checkLiveStatus();
        }
    });
    
    // 初次加载立即检查一次
    checkLiveStatus();
}
</script> 