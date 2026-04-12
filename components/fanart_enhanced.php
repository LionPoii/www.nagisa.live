<?php
/**
 * Fanart线索墙组件 - 增强版
 * 显示来自B站API的话题内容，采用线索墙风格设计
 */

// 如果是检查更新请求，返回最新Fanart的信息
if (isset($_GET['check_update']) && $_GET['check_update'] == '1') {
    header('Content-Type: application/json');
    
    $response = [
        'success' => true,
        'latest_fanart' => null
    ];
    
    try {
        require_once __DIR__ . '/../includes/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        // 获取最新的一条fanart记录
        $stmt = $conn->prepare("SELECT id, title, created_at FROM fanarts ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $fanart = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fanart) {
            $response['latest_fanart'] = [
                'id' => $fanart['id'],
                'title' => $fanart['title'],
                'timestamp' => strtotime($fanart['created_at'])
            ];
        }
    } catch (Exception $e) {
        $response['success'] = false;
        $response['error'] = '获取Fanart数据失败';
    }
    
    echo json_encode($response);
    exit;
}

?>
<style>
    /* 引入KingHwaOldSongv3.0字体 */
    @font-face {
        font-family: 'KingHwaOldSongv3.0';
        src: url('/assets/webfonts/KingHwaOldSongv3.0.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
        font-display: swap;
    }
    
    /* 线索墙容器样式 - 修改为墙面布局 */
    .cluewall-container {
        position: absolute; /* 绝对定位 */
        left: 10%; /* 左侧距离改为10%，因为宽度是80% */
        right: 10%; /* 右侧距离改为10% */
        top: 45%; /* 从顶部40%开始，整体往上移动 */
        transform: translateY(-50%); /* 垂直居中 */
        height: 75vh; /* 占浏览器窗口高度的75% */
        min-height: 600px; /* 增加最小高度 */
        width: 80%; /* 宽度为80% */
        max-width: 80%; /* 最大宽度80% */
        margin: 0 auto; /* 水平居中 */
        box-sizing: border-box;
        z-index: 10; /* 确保显示在其他元素上面 */
        display: flex;
        flex-direction: column;
        overflow: visible; /* 允许内容溢出容器 */
        padding: 0; /* 移除内部填充，因为已经有了外部边距 */
        font-size: calc(1rem + 0.3vw); /* 增大基础字体大小 */
        background: transparent; /* 设置容器背景为透明 */
        box-shadow: none; /* 移除阴影效果 */
    }

    /* 添加卡片重新布局动画类 */
    .relayout-animation {
        animation: relayout-pulse 1.2s cubic-bezier(0.215, 0.61, 0.355, 1); /* 使用更长的时间和更平滑的贝塞尔曲线 */
    }

    @keyframes relayout-pulse {
        0% {
            transform: scale(1) rotate(var(--card-rotation, 0deg));
            opacity: 1;
        }
        20% {
            transform: scale(1.03) rotate(var(--card-rotation, 0deg));
            opacity: 1;
        }
        40% {
            transform: scale(1.05) rotate(var(--card-rotation, 0deg));
            opacity: 1;
        }
        60% {
            transform: scale(1.04) rotate(var(--card-rotation, 0deg));
            opacity: 1;
        }
        80% {
            transform: scale(1.02) rotate(var(--card-rotation, 0deg));
            opacity: 1;
        }
        100% {
            transform: scale(1) rotate(var(--card-rotation, 0deg));
            opacity: 1;
        }
    }

    /* 添加标题样式 - 修改为左上角竖排 */
    .cluewall-title {
        position: absolute;
        top: 20px;
        left: -50px;
        color: #3D515F;
        font-size: 1.6em;
        font-family: "QIANTUHOUHEI", sans-serif;
        letter-spacing: 3px;
        z-index: 11;
        text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8), 0 0 5px rgba(255, 255, 255, 0.5);
        margin-bottom: 0;
        writing-mode: vertical-lr;
        /* 不旋转，保持原文本方向 */
        text-orientation: mixed;
    }
    
    /* 修改为墙面布局 */
    .cluewall-wrapper {
        flex-grow: 1; /* 填充剩余空间 */
        overflow: visible; /* 允许内容溢出，防止文本截断 */
        position: relative;
        display: block; /* 改为块级布局 */
        width: 100%; /* 确保宽度100% */
        height: 100%; /* 确保高度100% */
        box-sizing: border-box; /* 确保边框和padding包含在尺寸内 */
        /* 保持原有的墙面背景纹理 */
        background: 
            /* 主要墙面纹理 - 增强对比度和细节 */
            radial-gradient(circle at 20% 30%, rgba(39, 71, 78, 0.25) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%, rgba(44, 80, 87, 0.22) 0%, transparent 50%),
            radial-gradient(circle at 50% 50%, rgba(39, 71, 78, 0.18) 0%, transparent 70%),
            /* 墙面细节纹理 - 增加更多细节 */
            repeating-linear-gradient(45deg, rgba(39, 71, 78, 0.12) 0px, rgba(39, 71, 78, 0.12) 2px, transparent 2px, transparent 4px),
            repeating-linear-gradient(-45deg, rgba(39, 71, 78, 0.08) 0px, rgba(39, 71, 78, 0.08) 1px, transparent 1px, transparent 3px),
            /* 木纹效果 - 模拟墙板 */
            repeating-linear-gradient(90deg, rgba(31, 57, 62, 0.05) 0px, rgba(31, 57, 62, 0.05) 1px, transparent 1px, transparent 15px),
            /* 添加一些随机的"污渍"和阴影效果 */
            radial-gradient(circle at 15% 25%, rgba(31, 57, 62, 0.2) 0%, transparent 30%),
            radial-gradient(circle at 85% 75%, rgba(31, 57, 62, 0.15) 0%, transparent 25%),
            radial-gradient(ellipse at 40% 60%, rgba(0, 0, 0, 0.1) 0%, transparent 40%),
            /* 基础墙面颜色 - 墨青色，增加深浅变化 */
            linear-gradient(135deg, #1A3238 0%, #27474E 40%, #2C5057 70%, #345F68 100%);
        background-size: 100px 100px, 150px 150px, 200px 200px, 10px 10px, 8px 8px, 30px 30px, 60px 60px, 80px 80px, 120px 120px, 100% 100%;
        background-position: 0 0, 0 0, 0 0, 0 0, 4px 4px, 0 0, 20px 20px, 40px 40px, 0 0, 0 0;
        background-blend-mode: normal, normal, normal, multiply, multiply, overlay, soft-light, soft-light, overlay, normal;
        border-radius: 8px;
        border: 3px solid rgba(39, 71, 78, 0.6);
        box-shadow: 
            inset 0 2px 6px rgba(0, 0, 0, 0.3),
            inset 0 0 15px rgba(0, 0, 0, 0.1),
            0 4px 8px rgba(0, 0, 0, 0.3),
            0 8px 16px rgba(0, 0, 0, 0.2);
        position: relative;
    }
    
    /* 添加一些装饰性的"图钉"在墙面上 */
    .cluewall-wrapper::before {
        content: '';
        position: absolute;
        top: 10px;
        left: 10px;
        width: 8px;
        height: 8px;
        background: radial-gradient(circle, #2A4A50 0%, #1F393E 40%, #15292D 100%);
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.5), inset 0 1px 2px rgba(255, 255, 255, 0.2);
        z-index: 1; /* 保持较低的z-index */
    }
    
    .cluewall-wrapper::after {
        content: '';
        position: absolute;
        top: 30px;
        right: 20px;
        width: 6px;
        height: 6px;
        background: radial-gradient(circle, #2A4A50 0%, #1F393E 40%, #15292D 100%);
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.5), inset 0 1px 2px rgba(255, 255, 255, 0.2);
        z-index: 1; /* 保持较低的z-index */
    }

    .cluewall-gallery {
        position: relative;
        width: 100%;
        height: 100%;
        overflow: auto; /* 修改为auto，允许滚动但只在需要时显示滚动条 */
        display: block; /* 块级布局 */
        padding: 40px; /* 内边距 */
        user-select: none; /* 防止选择文本 */
        /* 添加一些随机的装饰线条 */
        background-image: 
            linear-gradient(90deg, transparent 95%, rgba(39, 71, 78, 0.15) 95%),
            linear-gradient(0deg, transparent 90%, rgba(39, 71, 78, 0.15) 90%),
            repeating-linear-gradient(90deg, rgba(255, 255, 255, 0.03) 0px, rgba(255, 255, 255, 0.03) 1px, transparent 1px, transparent 50px),
            repeating-linear-gradient(0deg, rgba(255, 255, 255, 0.03) 0px, rgba(255, 255, 255, 0.03) 1px, transparent 1px, transparent 50px);
        background-size: 50px 50px, 50px 50px, 50px 50px, 50px 50px;
        background-position: 0 0, 0 0, 0 0, 0 0;
        background-blend-mode: normal, normal, overlay, overlay;
        min-height: 600px; /* 确保画廊有足够的高度 */
        border: 2px dashed rgba(255, 255, 255, 0.4); /* 添加虚线边框，标识画廊边界，增强可见度 */
        box-sizing: border-box; /* 确保padding包含在宽高计算中 */
        /* 修复响应式布局问题 */
        margin: 0;
        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
        /* 确保背景墙和画廊无缝连接 */
        background-color: transparent;
        background-clip: padding-box;
        /* 添加内部定位上下文 */
        transform-style: preserve-3d; /* 创建3D空间，增强立体感 */
        perspective: 1000px; /* 添加透视效果 */
        backdrop-filter: blur(0.5px); /* 轻微模糊效果，增强深度感 */
        z-index: 2; /* 设置z-index，确保在装饰小点之上，但在卡片之下 */
        
        /* 设置滚动条样式为透明不可见 */
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE and Edge */
    }

    /* 为Webkit浏览器（Chrome、Safari等）设置滚动条样式 */
.cluewall-gallery::-webkit-scrollbar {
    width: 0;
    height: 0;
    background: transparent; /* 透明背景 */
    display: none; /* 隐藏滚动条 */
}

/* 为整个页面设置滚动条样式 */
body::-webkit-scrollbar {
    width: 0;
    height: 0;
    background: transparent; /* 透明背景 */
    display: none; /* 隐藏滚动条 */
}

body {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}

/* 为所有可能出现滚动条的容器设置样式 */
.cluewall-container::-webkit-scrollbar,
.cluewall-wrapper::-webkit-scrollbar,
.section-content::-webkit-scrollbar,
div::-webkit-scrollbar {
    width: 0 !important;
    height: 0 !important;
    background: transparent !important;
    display: none !important;
}

.cluewall-container,
.cluewall-wrapper,
.section-content,
div {
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
}
    
    /* 确保背景墙和画廊之间没有间隙 */
    .cluewall-wrapper .cluewall-gallery {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        height: 100%;
    }
    
    /* 响应式设计 - 墙面布局 */
    @media (max-width: 768px) {
        .cluewall-container {
            left: 5%;
            right: 5%;
            width: 90%;
            max-width: 90%;
            height: 70vh;
            min-height: 500px;
            top: 45%;
            transform: translateY(-50%);
        }
        
        .cluewall-wrapper {
            width: 100%;
            height: 100%;
            border-radius: 6px;
        }
        
        .cluewall-gallery {
            padding: 20px;
            min-width: 100%;
            min-height: 100%;
        }
    }

    @media (max-width: 480px) {
        .cluewall-container {
            left: 2%;
            right: 2%;
            width: 96%;
            max-width: 96%;
            height: 65vh;
            min-height: 450px;
            top: 45%;
            transform: translateY(-50%);
        }
        
        .cluewall-wrapper {
            width: 100%;
            height: 100%;
            border-radius: 4px;
        }
        
        .cluewall-title {
            font-size: 1.2em;
            top: 20px;
            left: -40px;
        }
        
        .cluewall-gallery {
            padding: 15px;
            min-width: 100%;
            min-height: 100%;
        }
    }

    /* 新卡片样式  */
    .new-card {
        min-width: calc(220px * 0.85); /* 缩小到85%，减小最小宽度 */
        max-width: calc(450px * 0.85); /* 缩小到85%，减小最大宽度 */
        width: auto; /* 宽度根据内容自动调整 */
        background-color: white;
        border-radius: 0 calc(16px * 0.85) calc(16px * 0.85) calc(16px * 0.85); /* 缩小到85% */
        overflow: visible; /* 修改为visible，允许内容完整显示 */
        /* 增强立体感的阴影 */
        box-shadow: 
            /* 内部阴影增加纸张质感 */
            inset 0 1px 2px rgba(255, 255, 255, 0.8),
            /* 多层阴影增加立体感 */
            0 1px 2px rgba(0, 0, 0, 0.05),
            0 4px 8px rgba(0, 0, 0, 0.1),
            0 8px 16px rgba(0, 0, 0, 0.08);
        position: absolute; /* 绝对定位 */
        margin: calc(15px * 0.85); /* 减小边距 */
        padding-top: calc(44px * 0.85); /* 缩小到85% */
        /* 添加微妙的边框增强纸张感 */
        border: 1px solid rgba(0, 0, 0, 0.05);
        /* 添加过渡效果 */
        transition: transform 0.3s ease, 
                    box-shadow 0.3s ease, 
                    left 0.8s cubic-bezier(0.25, 0.1, 0.25, 1), 
                    top 0.8s cubic-bezier(0.25, 0.1, 0.25, 1); /* 使用更快的过渡时间和简单的ease函数 */
        /* 确保卡片高度能够适应内容 */
        min-height: calc(180px * 0.85); /* 适当减小最小高度 */
        height: auto !important; /* 强制自动高度 */
        /* 确保卡片在画廊内 */
        max-height: none; /* 移除最大高度限制，允许卡片根据内容调整高度 */
        z-index: 5; /* 设置更高的z-index，确保卡片显示在背景墙和装饰小点之上 */
    }
    
    /* 添加真正透明的圆形孔洞装饰 */
    .new-card::after {
        content: '';
        position: absolute;
        right: 15px;
        bottom: 15px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background-color: transparent;
        /* 使用径向渐变创建孔洞边缘效果 */
        background-image: radial-gradient(
            circle at center,
            transparent 40%, 
            rgba(0, 0, 0, 0.2) 70%, 
            rgba(0, 0, 0, 0.5) 80%, 
            rgba(0, 0, 0, 0.4) 90%,
            rgba(0, 0, 0, 0.1) 100%
        );
        /* 创建实际的透明孔洞 */
        -webkit-mask-image: radial-gradient(circle at center, transparent 0%, transparent 40%, black 50%);
        mask-image: radial-gradient(circle at center, transparent 0%, transparent 40%, black 50%);
        /* 添加阴影效果 */
        box-shadow: 0 0 3px rgba(0, 0, 0, 0.3);
        z-index: 6;
        pointer-events: none; /* 确保不会干扰鼠标事件 */
    }
    
    /* 使用伪元素创建真正的透明孔 */
    .new-card::before {
        content: '';
        position: absolute;
        right: 17px;
        bottom: 17px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        /* 使用clip-path创建真正的透明孔 */
        background: transparent;
        box-shadow: 0 0 5px 2px rgba(0, 0, 0, 0.2);
        /* 创建实际的透明孔洞 */
        clip-path: circle(5px at center);
        z-index: 1; /* 确保在卡片内容之下 */
        pointer-events: none;
    }
    
    /* 新卡片悬停效果 */
    .new-card:hover {
        transform: translateY(-5px) rotate(0.5deg) scale(1.05); /* 轻微放大效果，减小到5% */
        box-shadow: 
            inset 0 1px 2px rgba(255, 255, 255, 0.8),
            0 10px 20px rgba(0, 0, 0, 0.1),
            0 15px 30px rgba(0, 0, 0, 0.08);
        z-index: 10;
        /* 悬停时不需要重复定义transition，已在基础样式中定义 */
    }
    
    .new-card-header {
        display: block; /* 改为块级元素 */
        width: 100%; /* 确保header占满卡片宽度 */
        font-size: 0; /* 消除元素间的空白 */
        position: absolute; /* 绝对定位 */
        top: 0;
        left: 0;
        white-space: nowrap; /* 防止换行 */
        overflow: visible; /* 确保内容可见 */
    }
    
    .new-card-username {
        background-color: #3b3d4e;
        color: white;
        padding: calc(12px * 0.85) calc(16px * 0.85); /* 减小水平padding */
        font-size: calc(20px * 0.85); /* 缩小到85% */
        font-family: "KingHwaOldSongv3.0", sans-serif; /* 更改字体为KingHwaOldSongv3.0 */
        font-weight: normal; /* 修改为不加粗 */
        border-top-left-radius: 0; /* 移除左上角弧度 */
        border-bottom-left-radius: 0; /* 移除左下角弧度 */
        border-top-right-radius: calc(50px * 0.85); /* 缩小到85% */
        border-bottom-right-radius: calc(50px * 0.85); /* 缩小到85% */
        position: relative;
        z-index: 1; /* 确保用户名区域在日期区域之上 */
        margin-right: calc(-25px * 0.85); /* 缩小到85% */
        height: calc(44px * 0.85); /* 缩小到85% */
        display: inline-flex; /* 改为inline-flex以便更好地控制内容居中 */
        align-items: center; /* 垂直居中 */
        justify-content: center; /* 水平居中 */
        width: auto; /* 根据内容自动调整宽度 */
        min-width: calc(100px * 0.85); /* 缩小到85% */
        max-width: none; /* 移除最大宽度限制，完全根据内容调整 */
        box-sizing: border-box;
        white-space: nowrap; /* 防止用户名换行 */
        overflow: visible; /* 确保内容不会被裁剪 */
        text-overflow: initial; /* 不使用省略号 */
        padding-right: calc(25px * 0.85); /* 进一步减小右侧padding */
        line-height: calc(20px * 0.85); /* 缩小到85% */
    }
    
    .new-card-date {
        /* 使用CSS变量存储颜色，便于JavaScript动态修改 */
        --date-bg-color: #e9a87c;
        background-color: var(--date-bg-color);
        color:rgb(255, 255, 255); /* 白色文字 */
        display: inline-block; /* 改为inline-block */
        text-align: right; /* 文本右对齐 */
        padding-right: calc(20px * 0.85); /* 缩小到85% */
        padding-left: calc(30px * 0.85); /* 缩小到85% */
        font-size: calc(16px * 0.85); /* 缩小到85% */
        font-weight: normal; /* 修改为不加粗 */
        font-family: "KingHwaOldSongv3.0", sans-serif; /* 使用与用户ID相同的字体 */
        border-top-right-radius: calc(16px * 0.85); /* 缩小到85% */
        height: calc(22px * 0.85); /* 缩小到85% */
        line-height: calc(22px * 0.85); /* 缩小到85% */
        min-width: calc(80px * 0.85); /* 缩小到85% */
        z-index: 0; /* 确保在用户名区域之下 */
        position: absolute; /* 绝对定位 */
        top: 0;
        right: 0; /* 固定在右侧 */
        width:100%; /* 宽度为卡片宽度减去用户名区域的大致宽度 */
    }
    
    .new-card-pin {
        width: 12px;
        height: 12px;
        background-color: #e74c3c;
        border-radius: 50%;
        position: absolute;
        top: 50%;
        right: -6px;
        transform: translateY(-50%);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        z-index: 2; /* 确保图钉显示在最上层 */
    }
    
    .new-card-title {
        padding: 0;
        font-size: calc(22px * 0.85); /* 缩小到85% */
        font-weight: bold;
        font-family: "KingHwaOldSongv3.0", sans-serif; /* 使用与用户ID相同的字体 */
        text-align: right; /* 右对齐 */
        position: absolute;
        top: calc(22px * 0.85); /* 缩小到85% */
        width: auto; /* 自动调整宽度 */
        right: calc(20px * 0.85); /* 缩小到85% */
        left: auto; /* 不固定左侧位置 */
        margin-left: calc(20px * 0.85); /* 缩小到85% */
        box-sizing: border-box;
        display: flex;
        justify-content: flex-end; /* 右侧对齐 */
        height: calc(44px * 0.85); /* 缩小到85% */
        align-items: center; /* 垂直居中文本 */
    }
    
    /* 当标题不存在时隐藏 */
    .new-card-title:empty {
        display: none;
    }
    
    .new-card-content {
        padding: calc(15px * 0.85) calc(20px * 0.85); /* 缩小到85% */
        font-size: calc(18px * 0.85); /* 缩小到85% */
        text-align: left; /* 改为左对齐，更容易阅读 */
        margin-top: calc(20px * 0.85); /* 缩小到85% */
        /* 增加纸张质感 */
        color: #333;
        line-height: 1.6; /* 增加行高 */
        /* 微妙的文本阴影增强立体感 */
        text-shadow: 0 1px 0 rgba(255, 255, 255, 0.8);
        /* 增加行间距 */
        letter-spacing: 0.02em;
        /* 确保文本可以完整显示 */
        overflow: visible;
        min-height: calc(100px * 0.85); /* 设置最小高度 */
        height: auto; /* 自动调整高度 */
        max-height: none; /* 移除最大高度限制，允许内容完全展示 */
    }
    
    .new-card-divider {
        border-top: calc(1px * 0.85) dashed #ccc; /* 缩小到85% */
        margin: 0 calc(20px * 0.85); /* 缩小到85% */
        position: absolute;
        top: calc(66px * 0.85); /* 缩小到85% */
        width: calc(100% - (40px * 0.85)); /* 缩小到85% */
    }
</style>

<!-- 注意：需要确保父容器.section-content设置为position:relative -->

<!-- 线索墙容器 -->
<div class="cluewall-container" style="scrollbar-width: none; -ms-overflow-style: none; margin-top: 30px;">
    <h2 class="cluewall-title">#线索墙</h2>
    <div class="cluewall-wrapper" style="scrollbar-width: none; -ms-overflow-style: none;">
        <div class="cluewall-gallery" id="fanartGallery" style="scrollbar-width: none; -ms-overflow-style: none;">
            <!-- 便签卡片将通过JavaScript动态生成 -->
        </div>
    </div>
</div>

<style>
/* 强制所有滚动条隐藏 */
::-webkit-scrollbar {
    width: 0 !important;
    height: 0 !important;
    background: transparent !important;
    display: none !important;
    z-index: 1 !important; /* 确保滚动条位于较低层级 */
}
* {
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
}

/* 确保滚动条相关元素都位于较低层级 */
::-webkit-scrollbar-track,
::-webkit-scrollbar-thumb,
::-webkit-scrollbar-corner {
    background: transparent !important;
    z-index: 1 !important;
}
</style>

<!-- 加载动画 -->
<div class="cluewall-loading" id="loading">
    <div class="cluewall-spinner"></div>
</div>

<style>
    /* 添加加载动画的响应式样式 */
    .cluewall-loading {
        display: none;
        justify-content: center;
        align-items: center;
        margin: 1rem 0;
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        bottom: -2rem;
    }
    
    .cluewall-spinner {
        width: calc(25px + 1vw);
        height: calc(25px + 1vw);
        border: calc(2px + 0.2vw) solid #f3f3f3;
        border-top: calc(2px + 0.2vw) solid #00a1d6;
        border-radius: 50%;
        animation: cluewall-spin 1s linear infinite;
    }
    
    @keyframes cluewall-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 定义ClueWall对象
    window.ClueWall = window.ClueWall || {};
    
    // 存储已加载的数据
    let loadedItems = [];
    let currentPage = 1;
    let isLoading = false;
    
    // 重置所有卡片状态的函数
    function resetAllCards() {
        const cards = document.querySelectorAll('.new-card');
        cards.forEach(card => {
            card.dataset.preventClick = 'false';
            card.dataset.isDragging = 'false';
            card.style.cursor = 'pointer';
            card.style.boxShadow = '';
            card.style.zIndex = '5';
        });
        isDraggingAny = false;
        currentDragCard = null;
    }
    
    // 在窗口大小改变时重置卡片状态并重新布局
    window.addEventListener('resize', debounce(function() {
        resetAllCards();
        // 如果有加载的数据，重新布局卡片
        if (loadedItems && loadedItems.length > 0) {
            console.log('窗口大小变化，重新布局卡片...');
            relayoutCards();
        }
    }, 250)); // 添加250ms的防抖，避免频繁触发
    
    // 重新布局所有卡片
    function relayoutCards() {
        if (!loadedItems || loadedItems.length === 0) return;
        
        // 首先调整画廊容器大小
        adjustGallerySize();
        
        // 获取当前所有卡片
        const cards = document.querySelectorAll('.new-card');
        if (cards.length === 0) {
            // 如果没有卡片，直接创建
            createNoteCards(loadedItems);
            return;
        }
        
        // 获取画廊实际尺寸，考虑内边距
        const gallery = document.getElementById('fanartGallery');
        const galleryRect = gallery.getBoundingClientRect();
        const galleryWidth = galleryRect.width - 80; // 减去内边距
        const galleryHeight = galleryRect.height - 80; // 减去内边距
        
        console.log(`重新布局 - 画廊尺寸: ${galleryWidth}x${galleryHeight}`);
        
        // 创建网格系统，用于均匀分布卡片
        const gridSize = 120; // 增加网格大小，使卡片分布更均匀
        const gridCols = Math.max(2, Math.floor(galleryWidth / gridSize)); // 确保至少有2列
        const gridRows = Math.max(2, Math.floor(galleryHeight / gridSize)); // 确保至少有2行
        
        // 创建已占用位置的跟踪数组
        const occupiedPositions = [];
        
        // 保存当前卡片的状态并重新定位
        const cardStates = {};
        
        // 将卡片转换为数组以便随机排序
        const cardsArray = Array.from(cards);
        
        // 随机打乱卡片顺序，使动画看起来更自然
        shuffleArray(cardsArray);
        
        // 延迟执行每张卡片的动画，创造错落有致的效果
        cardsArray.forEach((card, arrayIndex) => {
            const index = parseInt(card.dataset.index);
            if (!isNaN(index) && index >= 0 && index < loadedItems.length) {
                // 保存卡片状态
                cardStates[index] = {
                    isDragging: card.dataset.isDragging === 'true',
                    preventClick: card.dataset.preventClick === 'true',
                    element: card
                };
                
                // 获取卡片尺寸
                const cardWidth = card.offsetWidth;
                const cardHeight = card.offsetHeight;
                
                // 找到一个新位置
                const position = findAvailablePosition(
                    galleryWidth, 
                    galleryHeight, 
                    cardWidth, 
                    cardHeight, 
                    occupiedPositions,
                    gridCols,
                    gridRows
                );
                
                // 记录已占用的位置
                occupiedPositions.push({
                    x: position.x,
                    y: position.y,
                    width: cardWidth,
                    height: cardHeight
                });
                
                // 延迟执行，创造错落有致的动画效果
                setTimeout(() => {
                    // 应用新位置，触发过渡动画
                    card.style.left = `${position.x}px`;
                    card.style.top = `${position.y}px`;
                    
                    // 添加随机旋转
                    const rotation = Math.random() * 30 - 15; // -15度到15度之间的随机旋转
                    card.style.setProperty('--card-rotation', `${rotation}deg`); // 设置CSS变量用于动画
                    card.style.transform = `rotate(${rotation}deg)`;
                    
                    // 应用动画类
                    card.classList.remove('relayout-animation'); // 移除可能存在的动画类
                    void card.offsetWidth; // 触发重绘
                    card.classList.add('relayout-animation'); // 添加动画类
                    
                    // 动画结束后移除类
                    card.addEventListener('animationend', function animEndHandler() {
                        card.classList.remove('relayout-animation');
                        card.removeEventListener('animationend', animEndHandler);
                    });
                }, arrayIndex * 50); // 每张卡片延迟50ms
            }
        });
        
        console.log('卡片重新布局完成，应用了平滑动画');
    }
    
    // 调整画廊容器大小
    function adjustGallerySize() {
        const container = document.querySelector('.cluewall-container');
        const wrapper = document.querySelector('.cluewall-wrapper');
        const gallery = document.getElementById('fanartGallery');
        
        if (!container || !wrapper || !gallery) return;
        
        // 获取窗口尺寸
        const windowHeight = window.innerHeight;
        const windowWidth = window.innerWidth;
        
        // 根据窗口大小调整容器尺寸
        if (windowWidth <= 480) {
            // 小屏幕设备
            container.style.height = '65vh';
            container.style.width = '96%';
            container.style.left = '2%';
            container.style.right = '2%';
        } else if (windowWidth <= 768) {
            // 中等屏幕设备
            container.style.height = '70vh';
            container.style.width = '90%';
            container.style.left = '5%';
            container.style.right = '5%';
        } else {
            // 大屏幕设备
            container.style.height = '75vh';
            container.style.width = '80%';
            container.style.left = '10%';
            container.style.right = '10%';
        }
        
        console.log(`调整画廊容器大小完成，窗口尺寸: ${windowWidth}x${windowHeight}`);
    }
    
    // 在页面加载时调整画廊大小
    window.addEventListener('load', adjustGallerySize);
    
    // 防抖函数，避免频繁触发事件
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                func.apply(context, args);
            }, wait);
        };
    }
    
    // 设置数据源
    window.ClueWall.setDataSource = function(url) {
        dataSource = url;
        loadInitialContent();
    };
    
    // 存储数据源URL
    let dataSource = '';
    
    // 加载初始内容
    function loadInitialContent() {
        if (!dataSource) return;
        
        isLoading = true;
        showLoading();
        
        // 清理现有卡片
        const gallery = document.getElementById('fanartGallery');
        if (gallery) {
            gallery.innerHTML = '';
        }
        
        console.log('开始请求API:', dataSource);
        
        // 从API获取数据 - 设置每页12条数据
        fetch(`${dataSource}?page=${currentPage}&page_size=12`)
        .then(response => {
            console.log('API响应状态:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('API返回数据:', data);
            
            // 验证API响应结构
            if (!data || typeof data !== 'object') {
                throw new Error('API返回的数据格式无效');
            }
            
                // 检查API响应状态
                if (data.code !== 0 && data.code !== undefined) {
                    throw new Error(`API错误: ${data.message || '未知错误'}`);
                }
                
                // 详细打印数据结构
                console.log('API返回数据结构:', JSON.stringify(data).substring(0, 500) + '...');
            
                // 验证数据项
            let items = [];
            if (data.data && data.data.items) {
                items = data.data.items;
                    console.log('从data.data.items获取数据');
                } else if (data.data && data.data.topic_card_list && data.data.topic_card_list.items) {
                    // B站话题API格式
                    console.log('从data.data.topic_card_list.items获取数据');
                    items = data.data.topic_card_list.items.map(item => {
                        if (item.dynamic_card_item) {
                            const cardItem = item.dynamic_card_item;
                            const modules = cardItem.modules || {};
                            const author = modules.module_author || {};
                            const dynamic = modules.module_dynamic || {};
                            const major = dynamic.major || {};
                            const opus = major.opus || {};
                                
                                // 提取图片
                            let images = [];
                            if (opus.pics && Array.isArray(opus.pics)) {
                                images = opus.pics.map(pic => ({
                                    url: pic.url,
                                    description: ''
                                }));
                            }
                            
                            // 提取内容（服务端 fanart_api 已注入 _fanart_content_html，与动态页同一套表情 HTML）
                            let content = '';
                            let content_html = '';
                            if (opus.summary) {
                                if (opus.summary.text) {
                                    content = opus.summary.text;
                                } else if (opus.summary._fanart_content_plain) {
                                    content = opus.summary._fanart_content_plain;
                                }
                                if (opus.summary._fanart_content_html) {
                                    content_html = opus.summary._fanart_content_html;
                                }
                            }
                            
                            // 提取标题
                            let title = '';
                            if (opus.title) {
                                title = opus.title;
                                console.log('从opus中提取到标题:', title);
                            }
                                
                            return {
                                user: {
                                    name: author.name || '匿名用户'
                                },
                                content: content,
                                content_html: content_html,
                                title: title, // 添加标题字段
                                create_time: author.pub_ts || Math.floor(Date.now() / 1000),
                                images: images,
                                // 保留原始的dynamic_card_item对象以便后续跳转使用
                                dynamic_card_item: {
                                    id_str: cardItem.id_str || '',
                                    basic: {
                                        jump_url: cardItem.basic?.jump_url || cardItem.modules?.module_dynamic?.major?.opus?.jump_url || ''
                                    }
                                }
                            };
                        }
                        return item;
                    });
                }
                
                if (!Array.isArray(items)) {
                    console.error('API返回的items不是数组格式', items);
                    throw new Error('API返回的items不是数组格式');
                }
                
                console.log(`API返回 ${items.length} 个数据项`);
                            
                // 调试：显示前几个数据项的结构
                if (items.length > 0) {
                    console.log('第一个数据项结构:', JSON.stringify(items[0], null, 2));
                    
                    // 特别检查标题相关字段
                    const item = items[0];
                    if (item.dynamic_card_item && item.dynamic_card_item.modules) {
                        const modules = item.dynamic_card_item.modules;
                        console.log('检查动态卡片标题相关字段:',
                            JSON.stringify({
                                hasModuleDynamic: !!modules.module_dynamic,
                                hasMajor: modules.module_dynamic ? !!modules.module_dynamic.major : false,
                                hasOpus: modules.module_dynamic && modules.module_dynamic.major ? 
                                    !!modules.module_dynamic.major.opus : false,
                                hasTitle: modules.module_dynamic && modules.module_dynamic.major && 
                                    modules.module_dynamic.major.opus ? 
                                    !!modules.module_dynamic.major.opus.title : false,
                                titleValue: modules.module_dynamic && modules.module_dynamic.major && 
                                    modules.module_dynamic.major.opus && modules.module_dynamic.major.opus.title ?
                                    modules.module_dynamic.major.opus.title : 'undefined'
                            }, null, 2)
                        );
                    }
                    
                    if (items.length > 1) {
                        console.log('第二个数据项结构:', JSON.stringify(items[1], null, 2));
                    }
                }
                
                            // 过滤有效数据项
            const validItems = items.filter(item => {
                // 检查是否有内容（纯文本或带表情的 HTML）
                const hasContent = (item.content && item.content.trim() !== '') ||
                    (item.content_html && item.content_html.trim() !== '');
                
                // 检查是否有图片
                const hasImages = item.images && Array.isArray(item.images) && item.images.length > 0;
                
                // 检查是否有用户名
                const hasUser = (item.user && item.user.name && item.user.name.trim() !== '') ||
                               (item.module_author && item.module_author.name && item.module_author.name.trim() !== '');
                
                // 检查B站动态格式
                const sum = item.dynamic_card_item && item.dynamic_card_item.modules &&
                    item.dynamic_card_item.modules.module_dynamic &&
                    item.dynamic_card_item.modules.module_dynamic.major &&
                    item.dynamic_card_item.modules.module_dynamic.major.opus &&
                    item.dynamic_card_item.modules.module_dynamic.major.opus.summary
                    ? item.dynamic_card_item.modules.module_dynamic.major.opus.summary : null;
                const hasDynamicContent = sum && (
                    (sum.text && sum.text.trim() !== '') ||
                    (sum.rich_text_nodes && sum.rich_text_nodes.length > 0) ||
                    (sum._fanart_content_html && sum._fanart_content_html.trim() !== '')
                );
                
                const isValid = item && 
                       typeof item === 'object' && 
                       (hasContent || hasImages || hasDynamicContent) &&
                       hasUser;
                
                if (!isValid) {
                    console.log('无效数据项:', JSON.stringify(item).substring(0, 200));
            }
            
                return isValid;
            });
            
            console.log(`有效数据项: ${validItems.length} 个`);
            
            if (validItems.length > 0) {
                // 根据时间戳排序，使最旧的在前面
                validItems.sort((a, b) => {
                    // 获取A的时间戳
                    let timeA = 0;
                    if (a.create_time) {
                        timeA = parseInt(a.create_time);
                    } else if (a.dynamic_card_item && a.dynamic_card_item.modules && 
                              a.dynamic_card_item.modules.module_author && 
                              a.dynamic_card_item.modules.module_author.pub_ts) {
                        timeA = parseInt(a.dynamic_card_item.modules.module_author.pub_ts);
                    }
                    
                    // 获取B的时间戳
                    let timeB = 0;
                    if (b.create_time) {
                        timeB = parseInt(b.create_time);
                    } else if (b.dynamic_card_item && b.dynamic_card_item.modules && 
                              b.dynamic_card_item.modules.module_author && 
                              b.dynamic_card_item.modules.module_author.pub_ts) {
                        timeB = parseInt(b.dynamic_card_item.modules.module_author.pub_ts);
                    }
                    
                    return timeA - timeB; // 升序排列，最旧的在前
                });
                
                loadedItems = validItems;
                createNoteCards(validItems);
                currentPage++;
            } else {
                console.warn('没有找到有效的数据项');
                document.getElementById('fanartGallery').innerHTML = 
                    '<div style="color: #856404; background-color: #fff3cd; padding: 15px; border-radius: 5px; text-align: center;">' +
                    '暂无数据</div>';
            }
                
                isLoading = false;
                hideLoading();
        })
        .catch(error => {
            // 隐藏加载动画
            hideLoading();
            isLoading = false;
            
            // 显示错误信息
            console.error('获取数据失败:', error);
            document.getElementById('fanartGallery').innerHTML = 
                '<div style="color: #721c24; background-color: #f8d7da; padding: 15px; border-radius: 5px; text-align: center;">' +
                '获取数据失败，请稍后再试。</div>';
        });
    }

// 显示加载中
function showLoading() {
    const loading = document.getElementById('loading');
    if (loading) loading.style.display = 'flex';
}

// 隐藏加载中
function hideLoading() {
    const loading = document.getElementById('loading');
    if (loading) loading.style.display = 'none';
}

// 创建便签卡片并均匀分布在线索墙上
function createNoteCards(data) {
    const gallery = document.getElementById('fanartGallery');
    
    // 获取画廊实际尺寸，考虑内边距
    const galleryRect = gallery.getBoundingClientRect();
    const galleryWidth = galleryRect.width - 80; // 减去内边距
    const galleryHeight = galleryRect.height - 80; // 减去内边距
    
    console.log(`画廊尺寸: ${galleryWidth}x${galleryHeight}`);
    
    // 清空画廊
    gallery.innerHTML = '';
    
    // 如果没有数据
    if (!data || data.length === 0) {
        console.error('没有有效数据可显示');
        gallery.innerHTML = '<div style="color: #856404; background-color: #fff3cd; padding: 15px; border-radius: 5px; text-align: center;">' +
            '暂无数据</div>';
        return;
    }
    
    console.log(`准备创建 ${data.length} 个便签卡片`);
    
    // 创建网格系统，用于均匀分布卡片
    const gridSize = 120; // 增加网格大小，使卡片分布更均匀
    const gridCols = Math.max(2, Math.floor(galleryWidth / gridSize)); // 确保至少有2列
    const gridRows = Math.max(2, Math.floor(galleryHeight / gridSize)); // 确保至少有2行
    
    // 创建已占用位置的跟踪数组
    const occupiedPositions = [];
    
    // 添加全局拖动事件处理
    let currentDragCard = null;
    let isDraggingAny = false;
    let longPressTimer = null;
    
    // 全局鼠标移动事件
    document.addEventListener('mousemove', function(e) {
        if (!isDraggingAny || !currentDragCard) return;
        
        // 获取画廊元素的位置信息，确保相对于画廊定位
        const gallery = document.getElementById('fanartGallery');
        const galleryRect = gallery.getBoundingClientRect();
        
        // 计算相对于画廊的位置
        let relativeX = e.clientX - galleryRect.left - currentDragCard.offsetX;
        let relativeY = e.clientY - galleryRect.top - currentDragCard.offsetY;
        
        // 获取卡片尺寸
        const cardWidth = currentDragCard.element.offsetWidth;
        const cardHeight = currentDragCard.element.offsetHeight;
        
        // 限制卡片在画廊内
        // 左边界限制
        relativeX = Math.max(0, relativeX);
        // 右边界限制，留一点边距确保卡片不完全超出
        relativeX = Math.min(galleryRect.width - cardWidth * 0.3, relativeX);
        // 上边界限制
        relativeY = Math.max(0, relativeY);
        // 下边界限制，留一点边距确保卡片不完全超出
        relativeY = Math.min(galleryRect.height - cardHeight * 0.3, relativeY);
        
        // 应用新位置，使用绝对像素而不是百分比
        currentDragCard.element.style.left = relativeX + 'px';
        currentDragCard.element.style.top = relativeY + 'px';
    });
    
    // 全局鼠标松开事件
    document.addEventListener('mouseup', function(e) {
        if (isDraggingAny && currentDragCard) {
            isDraggingAny = false;
            currentDragCard.element.style.cursor = 'pointer';
            currentDragCard.element.style.boxShadow = '';
            currentDragCard.element.style.zIndex = '5'; // 恢复到卡片的正常z-index值
            
            // 重置拖动状态标记，但保持点击阻止状态
            currentDragCard.element.dataset.isDragging = 'false';
            currentDragCard.element.dataset.preventClick = 'true'; // 保持为true，阻止立即点击
            
            e.preventDefault(); // 阻止点击事件
            e.stopPropagation(); // 阻止事件冒泡
            
            // 延迟一段时间后，再允许点击
            const element = currentDragCard.element;
            setTimeout(() => {
                if (element) {
                    element.dataset.preventClick = 'false'; // 延迟后允许点击
                }
            }, 300); // 300毫秒延迟，防止拖动后立即触发点击
            
            currentDragCard = null;
        } else if (currentDragCard) {
            // 短点击情况下，不阻止点击
            if (currentDragCard.element) {
                currentDragCard.element.dataset.isDragging = 'false';
                // 不设置preventClick，允许正常点击
            }
            clearTimeout(currentDragCard.timeout);
            currentDragCard = null;
        }
    });
    
    // 触摸事件支持
    document.addEventListener('touchmove', function(e) {
        if (!isDraggingAny || !currentDragCard) return;
        
        const touch = e.touches[0];
        
        // 获取画廊元素的位置信息，确保相对于画廊定位
        const gallery = document.getElementById('fanartGallery');
        const galleryRect = gallery.getBoundingClientRect();
        
        // 计算相对于画廊的位置
        let relativeX = touch.clientX - galleryRect.left - currentDragCard.offsetX;
        let relativeY = touch.clientY - galleryRect.top - currentDragCard.offsetY;
        
        // 获取卡片尺寸
        const cardWidth = currentDragCard.element.offsetWidth;
        const cardHeight = currentDragCard.element.offsetHeight;
        
        // 限制卡片在画廊内
        // 左边界限制
        relativeX = Math.max(0, relativeX);
        // 右边界限制，留一点边距确保卡片不完全超出
        relativeX = Math.min(galleryRect.width - cardWidth * 0.3, relativeX);
        // 上边界限制
        relativeY = Math.max(0, relativeY);
        // 下边界限制，留一点边距确保卡片不完全超出
        relativeY = Math.min(galleryRect.height - cardHeight * 0.3, relativeY);
        
        // 应用新位置，使用绝对像素而不是百分比
        currentDragCard.element.style.left = relativeX + 'px';
        currentDragCard.element.style.top = relativeY + 'px';
        e.preventDefault(); // 阻止页面滚动
    }, { passive: false });
    
    document.addEventListener('touchend', function(e) {
        if (isDraggingAny && currentDragCard) {
            isDraggingAny = false;
            currentDragCard.element.style.boxShadow = '';
            currentDragCard.element.style.zIndex = '5';
            
            // 重置拖动状态标记，但保持点击阻止状态
            currentDragCard.element.dataset.isDragging = 'false';
            currentDragCard.element.dataset.preventClick = 'true'; // 保持为true，阻止立即点击
            
            e.preventDefault(); // 阻止默认行为
            
            // 延迟一段时间后，再允许点击
            const element = currentDragCard.element;
            setTimeout(() => {
                if (element) {
                    element.dataset.preventClick = 'false'; // 延迟后允许点击
                }
            }, 300); // 300毫秒延迟，防止拖动后立即触发点击
            
            currentDragCard = null;
        } else if (currentDragCard) {
            // 短点击情况下，不阻止点击
            if (currentDragCard.element) {
                currentDragCard.element.dataset.isDragging = 'false';
                // 不设置preventClick，允许正常点击
            }
            clearTimeout(currentDragCard.timeout);
            currentDragCard = null;
        }
        clearTimeout(longPressTimer);
    });
    
    // 为每条数据创建一个便签卡片
    data.forEach((item, index) => {
        // 验证数据项
        if (!item || typeof item !== 'object') {
            console.warn('跳过无效数据项:', item);
            return;
        }
        
        // 检查是否有有效内容
        const hasValidContent = (item.content && item.content.trim() !== '') ||
            (item.content_html && item.content_html.trim() !== '');
        const hasValidImages = item.images && Array.isArray(item.images) && item.images.length > 0;
        const sumDyn = item.dynamic_card_item && item.dynamic_card_item.modules &&
            item.dynamic_card_item.modules.module_dynamic &&
            item.dynamic_card_item.modules.module_dynamic.major &&
            item.dynamic_card_item.modules.module_dynamic.major.opus &&
            item.dynamic_card_item.modules.module_dynamic.major.opus.summary
            ? item.dynamic_card_item.modules.module_dynamic.major.opus.summary : null;
        const hasDynamicContent = sumDyn && (
            (sumDyn.text && sumDyn.text.trim() !== '') ||
            (sumDyn.rich_text_nodes && sumDyn.rich_text_nodes.length > 0) ||
            (sumDyn._fanart_content_html && sumDyn._fanart_content_html.trim() !== '')
        );
        
        if (!hasValidContent && !hasValidImages && !hasDynamicContent) {
            console.warn('跳过无内容的数据项:', item);
            return;
        }
        
        // 创建新样式卡片元素
        const noteCard = document.createElement('div');
        noteCard.className = 'new-card';
        noteCard.dataset.preventClick = 'false'; // 初始化防止点击标记为false
        noteCard.dataset.isDragging = 'false'; // 添加拖动状态标记
        noteCard.style.scrollbarWidth = 'none'; // 隐藏滚动条
        noteCard.style.msOverflowStyle = 'none'; // IE和Edge隐藏滚动条
        
        // 提取用户名
        let username = '匿名用户';
        if (item.user && item.user.name) {
            username = item.user.name.trim();
        } else if (item.module_author && item.module_author.name) {
            username = item.module_author.name.trim();
        } else if (item.dynamic_card_item && item.dynamic_card_item.modules && 
                  item.dynamic_card_item.modules.module_author && 
                  item.dynamic_card_item.modules.module_author.name) {
            username = item.dynamic_card_item.modules.module_author.name.trim();
        }
        
        // 确保用户名不会太长，但不要截断
        console.log('用户名长度:', username.length, '用户名:', username);
        
        // 提取日期
        let dateText = '';
        if (item.create_time) {
            try {
                const date = new Date(parseInt(item.create_time) * 1000);
                if (!isNaN(date.getTime())) {
                    const month = date.getMonth() + 1;
                    const day = date.getDate();
                    dateText = month + '月' + day + '日';
                }
            } catch(e) {
                console.error('日期处理错误', e);
            }
        } else if (item.dynamic_card_item && item.dynamic_card_item.modules && 
                  item.dynamic_card_item.modules.module_author && 
                  item.dynamic_card_item.modules.module_author.pub_ts) {
            try {
                const date = new Date(parseInt(item.dynamic_card_item.modules.module_author.pub_ts) * 1000);
                if (!isNaN(date.getTime())) {
                    const month = date.getMonth() + 1;
                    const day = date.getDate();
                    dateText = month + '月' + day + '日';
                }
            } catch(e) {
                console.error('日期处理错误', e);
            }
        }
        
        // 提取标题 (如果有)
        let title = '';
        console.log('开始提取标题，数据项:', JSON.stringify({
            hasTitle: !!item.title,
            hasDynamicCardItem: !!item.dynamic_card_item,
            dynamicCardItemModules: item.dynamic_card_item ? !!item.dynamic_card_item.modules : false
        }));
        
        if (item.title) {
            title = item.title.trim();
            console.log('从item.title提取标题:', title);
        } else if (item.dynamic_card_item && item.dynamic_card_item.modules && 
                  item.dynamic_card_item.modules.module_dynamic && 
                  item.dynamic_card_item.modules.module_dynamic.major && 
                  item.dynamic_card_item.modules.module_dynamic.major.opus && 
                  item.dynamic_card_item.modules.module_dynamic.major.opus.title) {
            title = item.dynamic_card_item.modules.module_dynamic.major.opus.title.trim();
            console.log('从dynamic_card_item.modules.module_dynamic.major.opus.title提取标题:', title);
        }
        
        // 确保空格字符串被视为空标题
        if (title === '' || !title.replace(/\s/g, '').length) {
            title = '';
            console.log('标题为空或只包含空格，不显示标题');
        } else {
            console.log('最终提取到的标题:', title);
        }
        
        // 提取内容（content_html 来自接口，与动态页表情一致）
        let content = '';
        let contentHtml = (item.content_html && item.content_html.trim()) ? item.content_html.trim() : '';
        if (item.content && item.content.trim()) {
            content = item.content.trim();
        } else if (sumDyn) {
            const t = (sumDyn.text || sumDyn._fanart_content_plain || '').trim();
            content = t;
            if (!contentHtml && sumDyn._fanart_content_html) {
                contentHtml = sumDyn._fanart_content_html.trim();
            }
        } else if (hasValidImages) {
            content = '图片内容';
        }
        
        // 构建卡片HTML结构
        // 先创建DOM元素而不是使用innerHTML，这样可以更好地控制元素
        const cardHeader = document.createElement('div');
        cardHeader.className = 'new-card-header';
        
        // 创建用户名元素
        const usernameElement = document.createElement('div');
        usernameElement.className = 'new-card-username';
        usernameElement.textContent = username;
        
        // 创建日期元素
        const dateElement = document.createElement('div');
        dateElement.className = 'new-card-date';
        dateElement.textContent = dateText;
        
        // 根据卡片在数组中的位置设置颜色层次
        // 数据已经按时间排序，索引越小越新
        
        // 计算颜色渐变
        let bgColor = '#e9a87c'; // 默认颜色
        
        try {
            // 获取卡片总数
            const totalCards = data.length;
            
            // 只有当总卡片数大于1时才进行渐变计算
            if (totalCards > 1) {
                // 计算当前卡片在总体中的位置比例（0-1之间，0表示最新，1表示最旧）
                const position = index / (totalCards - 1);
                
                // 根据位置比例设置颜色
                // 设置基础颜色：#e9a87c
                // 颜色顺序颠倒：越新的越暗淡，越旧的越鲜艳
                
                if (position <= 0.2) { // 最新的20%
                    bgColor = '#957463'; // 最暗
                } else if (position <= 0.4) { // 20%-40%
                    bgColor = '#bf8e70'; // 更暗
                } else if (position <= 0.6) { // 40%-60%
                    bgColor = '#d49b76'; // 稍暗
                } else if (position <= 0.8) { // 60%-80%
                    bgColor = '#e9a87c'; // 原始颜色
                } else { // 80%-100%（最旧）
                    bgColor = '#ff9966'; // 鲜亮的橙色
                }
            }
        } catch (e) {
            console.error('设置卡片颜色时出错:', e);
            // 出错时使用默认颜色
            bgColor = '#e9a87c';
        }
        
        // 设置背景颜色
        dateElement.style.setProperty('--date-bg-color', bgColor);
        
        // 添加到header
        cardHeader.appendChild(usernameElement);
        cardHeader.appendChild(dateElement);
        
        // 添加到卡片
        noteCard.appendChild(cardHeader);
        
        // 如果有标题且不为空，添加标题
        if (title && title.length > 0) {
            const titleContainer = document.createElement('div');
            titleContainer.className = 'new-card-title';
            const titleSpan = document.createElement('span');
            titleSpan.textContent = title;
            titleContainer.appendChild(titleSpan);
            noteCard.appendChild(titleContainer);
            console.log('添加标题:', title);
        } else {
            console.log('标题为空，不添加标题元素');
        }
        
        // 添加分隔线
        const divider = document.createElement('div');
        divider.className = 'new-card-divider';
        noteCard.appendChild(divider);
        
        // 添加内容
        const contentElement = document.createElement('div');
        contentElement.className = 'new-card-content';
        contentElement.style.textAlign = 'left';
        contentElement.style.wordBreak = 'break-word'; // 确保长文本能够换行
        contentElement.style.whiteSpace = 'pre-wrap'; // 保留空格和换行
        contentElement.style.overflow = 'visible'; // 确保内容可见
        contentElement.style.height = 'auto'; // 确保高度自动适应内容
        contentElement.style.maxHeight = 'none'; // 确保没有最大高度限制
        contentElement.style.scrollbarWidth = 'none'; // 隐藏滚动条
        contentElement.style.msOverflowStyle = 'none'; // IE和Edge隐藏滚动条
        
        (function setCardContent() {
            if (contentHtml) {
                contentElement.innerHTML = contentHtml;
            } else {
                const esc = document.createElement('div');
                esc.textContent = content;
                contentElement.innerHTML = esc.innerHTML.replace(/\n/g, '<br>');
            }
        })();
        
        noteCard.appendChild(contentElement);
        
        // 动态调整用户名区域宽度
        // 使用MutationObserver确保在DOM完全渲染后测量
        const observer = new MutationObserver(() => {
            // 获取用户名元素的实际内容宽度
            // 使用更精确的方法测量文本宽度
            const computedStyle = window.getComputedStyle(usernameElement);
            const paddingLeft = parseFloat(computedStyle.paddingLeft) || 0;
            const paddingRight = parseFloat(computedStyle.paddingRight) || 0;
            
            // 创建一个临时span来精确测量文本宽度
            const tempSpan = document.createElement('span');
            tempSpan.style.font = computedStyle.font;
            tempSpan.style.visibility = 'hidden';
            tempSpan.style.position = 'absolute';
            tempSpan.style.whiteSpace = 'nowrap';
            tempSpan.textContent = username;
            document.body.appendChild(tempSpan);
            
            // 获取精确的文本宽度
            const exactTextWidth = tempSpan.getBoundingClientRect().width;
            document.body.removeChild(tempSpan);
            
            console.log('用户名精确宽度:', exactTextWidth);
            
            // 确保用户名区域足够宽，加上padding的宽度但不添加过多额外空间
            if (exactTextWidth > 0) {
                // 计算最终宽度：文本宽度 + padding + 极小的额外空间(2px)
                const finalWidth = exactTextWidth + paddingLeft + paddingRight + 2;
                usernameElement.style.width = finalWidth + 'px';
                console.log('设置用户名区域宽度:', finalWidth, 
                           '(文本宽度:', exactTextWidth, 
                           '+ paddingLeft:', paddingLeft, 
                           '+ paddingRight:', paddingRight, 
                           '+ 额外:', 2, ')');
                
                // 同时调整日期区域的宽度，使其与卡片等长
                // 计算日期区域的宽度 = 卡片宽度 - 用户名宽度
                let cardWidth = noteCard.offsetWidth;
                
                // 添加规则：如果卡片宽度小于用户名区域宽度的1.5倍，则将卡片宽度设置为用户名区域宽度的1.5倍
                const minCardWidth = finalWidth * 1.5; // 用户名区域宽度的1.5倍
                if (cardWidth < minCardWidth) {
                    // 调整卡片宽度
                    noteCard.style.width = `${minCardWidth}px`;
                    cardWidth = minCardWidth;
                    console.log('卡片宽度过小，调整为:', minCardWidth);
                }
                
                const dateWidth = cardWidth - finalWidth + 25; // +25是为了考虑重叠部分
                if (dateWidth > 0) {
                    dateElement.style.width = dateWidth + 'px';
                    console.log('设置日期区域宽度:', dateWidth);
                }
            }
            
            // 只执行一次
            observer.disconnect();
        });
        
        // 开始观察DOM变化
        observer.observe(noteCard, { childList: true, subtree: true });
        
        // 添加到画廊
        gallery.appendChild(noteCard);
        
        // 卡片宽度根据内容自动调整，不需要设置固定宽度
        // 但设置最小宽度和最大宽度
        noteCard.style.minWidth = '250px';
        noteCard.style.maxWidth = '500px';
        
        // 高度自动适应内容
        noteCard.style.height = 'auto';
        
                         // 计算文本行数和每行最大字符数
        const layoutText = content || (contentHtml ? contentHtml.replace(/<[^>]+>/g, '') : '');
        const lines = layoutText.split('\n');
        const lineCount = lines.length;
        let maxLineLength = 0;
        
        // 找出最长的行
        for (const line of lines) {
            if (line.length > maxLineLength) {
                maxLineLength = line.length;
            }
        }
        
        // 计算用户名长度，确保卡片宽度足够容纳用户名
        const usernameLength = username.length;
        console.log('用户名长度计算:', usernameLength);
        
                 // 根据内容和用户名计算合适的卡片尺寸
         // 每个汉字约20px宽，每行约24px高
                 const baseWidth = 150 * 0.85; // 基础宽度，缩小到85%
        const baseHeight = 100 * 0.85; // 基础高度，缩小到85%
        
                                 // 计算宽度和高度，考虑用户名长度和内容长度
        // 如果用户名很长，增加卡片宽度
        const usernameFactor = Math.max(0, (usernameLength - 10) * 15 * 0.85); // 用户名超过10个字符时，每个字符增加15px宽度，缩小到85%
        const estimatedUsernameWidth = baseWidth + usernameFactor + 100 * 0.85; // 估计的用户名区域宽度
        
        // 计算基于内容的卡片宽度
        let contentBasedWidth = baseWidth + maxLineLength * 12 * 0.85 + Math.random() * 20 * 0.85; // 内容宽度
        
        // 确保卡片宽度不小于用户名区域宽度的1.5倍
        const cardWidth = Math.max(
            contentBasedWidth,
            estimatedUsernameWidth,
            estimatedUsernameWidth * 1.5 // 确保卡片宽度至少是用户名区域宽度的1.5倍
        );
       // 增加卡片高度计算，确保足够显示内容
       const minContentHeight = 150 * 0.85; // 最小内容高度
       const contentBasedHeight = baseHeight + lineCount * 30 * 0.85; // 增加每行高度从24px到30px，确保足够空间
       const cardHeight = Math.max(contentBasedHeight, minContentHeight) + 80 * 0.85; // 增加额外空间从50px到80px
        
        noteCard.style.width = `${cardWidth}px`;
        noteCard.style.height = `${cardHeight}px`;
        
        // 找到一个不重叠的位置
        let position = findAvailablePosition(
            galleryWidth, 
            galleryHeight, 
            cardWidth, 
            cardHeight, 
            occupiedPositions,
            gridCols,
            gridRows
        );
        
        // 设置便签卡片位置 - 确保相对于画廊定位
        noteCard.style.position = 'absolute';
        noteCard.style.left = `${position.x}px`;
        noteCard.style.top = `${position.y}px`;
        
        // 添加随机旋转
        const rotation = Math.random() * 30 - 15; // -15度到15度之间的随机旋转
        noteCard.style.transform = `rotate(${rotation}deg)`;
        
        // 记录已占用的位置
        occupiedPositions.push({
            x: position.x,
            y: position.y,
            width: cardWidth,
            height: cardHeight
        });
        
        // 添加点击事件 - 直接跳转到B站动态界面
        noteCard.dataset.index = index; // 存储索引以便点击时获取
        noteCard.style.cursor = 'pointer'; // 添加指针样式，表示可点击
        
        // 鼠标按下事件
        noteCard.addEventListener('mousedown', function(e) {
            // 如果是右键，不处理
            if (e.button === 2) return;
            
            // 如果卡片已经掉落，不执行任何操作
            if (this.classList.contains('falling')) {
                return;
            }
            
            // 获取画廊和卡片的位置信息
            const gallery = document.getElementById('fanartGallery');
            const galleryRect = gallery.getBoundingClientRect();
            const cardRect = this.getBoundingClientRect();
            
            // 记录鼠标位置
            const startX = e.clientX;
            const startY = e.clientY;
            
            // 计算鼠标在卡片上的相对位置
            const offsetX = startX - cardRect.left;
            const offsetY = startY - cardRect.top;
            
            // 计算卡片相对于画廊的位置
            const cardLeft = cardRect.left - galleryRect.left;
            const cardTop = cardRect.top - galleryRect.top;
            
            // 设置当前拖动卡片
            currentDragCard = {
                element: this,
                startX: startX,
                startY: startY,
                galleryRect: galleryRect,
                cardLeft: cardLeft,
                cardTop: cardTop,
                offsetX: offsetX,
                offsetY: offsetY
            };
            
            // 设置长按检测
            currentDragCard.timeout = setTimeout(() => {
                // 获取当前鼠标位置和画廊位置，重新计算偏移量
                const gallery = document.getElementById('fanartGallery');
                const galleryRect = gallery.getBoundingClientRect();
                const currentRect = this.getBoundingClientRect();
                const currentMouseX = e.clientX;
                const currentMouseY = e.clientY;
                
                // 更新偏移量，确保拖动开始时不会跳跃
                currentDragCard.offsetX = currentMouseX - currentRect.left;
                currentDragCard.offsetY = currentMouseY - currentRect.top;
                currentDragCard.galleryRect = galleryRect;
                currentDragCard.cardLeft = currentRect.left - galleryRect.left;
                currentDragCard.cardTop = currentRect.top - galleryRect.top;
                
                isDraggingAny = true;
                // 增加z-index使当前卡片显示在最上层
                this.style.zIndex = '100'; // 确保拖动时卡片在最上层
                // 添加拖动时的样式
                this.style.cursor = 'grabbing';
                this.style.boxShadow = '0 10px 20px rgba(0, 0, 0, 0.2)';
                this.style.transition = 'box-shadow 0.3s ease';
                // 设置拖动状态标记
                this.dataset.isDragging = 'true';
            }, 100); // 100ms长按触发拖动
        });
        
        // 触摸开始事件 - 移动设备支持
        noteCard.addEventListener('touchstart', function(e) {
            // 如果卡片已经掉落，不执行任何操作
            if (this.classList.contains('falling')) {
                return;
            }
            
            // 获取画廊和卡片的位置信息
            const gallery = document.getElementById('fanartGallery');
            const galleryRect = gallery.getBoundingClientRect();
            const cardRect = this.getBoundingClientRect();
            
            // 记录触摸点位置
            const touch = e.touches[0];
            const startX = touch.clientX;
            const startY = touch.clientY;
            
            // 计算触摸点在卡片上的相对位置
            const offsetX = startX - cardRect.left;
            const offsetY = startY - cardRect.top;
            
            // 计算卡片相对于画廊的位置
            const cardLeft = cardRect.left - galleryRect.left;
            const cardTop = cardRect.top - galleryRect.top;
            
            // 设置当前拖动卡片
            currentDragCard = {
                element: this,
                startX: startX,
                startY: startY,
                galleryRect: galleryRect,
                cardLeft: cardLeft,
                cardTop: cardTop,
                offsetX: offsetX,
                offsetY: offsetY
            };
            
            // 设置长按检测 - 移动设备上长按时间稍长
            currentDragCard.timeout = setTimeout(() => {
                // 获取当前触摸位置和画廊位置，重新计算偏移量
                if (e.touches && e.touches[0]) {
                    const gallery = document.getElementById('fanartGallery');
                    const galleryRect = gallery.getBoundingClientRect();
                    const currentRect = this.getBoundingClientRect();
                    const currentTouch = e.touches[0];
                    
                    // 更新偏移量，确保拖动开始时不会跳跃
                    currentDragCard.offsetX = currentTouch.clientX - currentRect.left;
                    currentDragCard.offsetY = currentTouch.clientY - currentRect.top;
                    currentDragCard.galleryRect = galleryRect;
                    currentDragCard.cardLeft = currentRect.left - galleryRect.left;
                    currentDragCard.cardTop = currentRect.top - galleryRect.top;
                }
                
                isDraggingAny = true;
                // 增加z-index使当前卡片显示在最上层
                this.style.zIndex = '100';
                // 添加拖动时的样式
                this.style.boxShadow = '0 10px 20px rgba(0, 0, 0, 0.2)';
                this.style.transition = 'box-shadow 0.3s ease';
                // 设置拖动状态标记
                this.dataset.isDragging = 'true';
            }, 300); // 300ms长按触发拖动
        });
        
        // 左键点击卡片打开链接
        noteCard.addEventListener('click', function(e) {
            e.preventDefault(); // 阻止默认行为
            
            // 如果正在拖动或卡片已经掉落，不执行任何操作
            if (isDraggingAny || this.classList.contains('falling')) {
                return false;
            }
            
            // 检查是否处于点击阻止状态
            // 同时检查isDragging和preventClick状态
            if (this.dataset.isDragging === 'true' || this.dataset.preventClick === 'true') {
                console.log('点击被阻止，卡片正在拖动或刚拖动完毕');
                return false;
            }
            
            // 获取卡片索引
            const index = parseInt(this.dataset.index);
            if (!isNaN(index) && loadedItems[index]) {
                const item = loadedItems[index];
                console.log('卡片被点击:', item);
                // 详细输出动态卡片项的结构，便于调试
                if (item.dynamic_card_item) {
                    console.log('动态卡片项结构:', JSON.stringify(item.dynamic_card_item, null, 2));
                }
                
                // 获取跳转链接
                let jumpUrl = '';
                let dynamicId = '';
                
                // 从不同位置尝试获取跳转链接和动态ID
                if (item.dynamic_card_item && item.dynamic_card_item.id_str) {
                    // 直接使用动态ID
                    dynamicId = item.dynamic_card_item.id_str;
                    jumpUrl = `https://t.bilibili.com/${dynamicId}`;
                    console.log('使用动态ID构建链接:', jumpUrl);
                } else if (item.dynamic_card_item && item.dynamic_card_item.basic && item.dynamic_card_item.basic.jump_url) {
                    // 使用jump_url
                    jumpUrl = item.dynamic_card_item.basic.jump_url;
                    // 去除可能的转义字符
                    jumpUrl = jumpUrl.replace(/\\/g, '');
                    console.log('处理后的jump_url:', jumpUrl);
                    
                    // 处理链接格式
                    if (jumpUrl.startsWith('//')) {
                        jumpUrl = 'https:' + jumpUrl;
                    } else if (!jumpUrl.startsWith('http')) {
                        jumpUrl = 'https://www.bilibili.com' + jumpUrl;
                    }
                } else if (item.modules && item.modules.module_dynamic && item.modules.module_dynamic.major && item.modules.module_dynamic.major.opus) {
                    // 尝试从opus中获取链接
                    const opus = item.modules.module_dynamic.major.opus;
                    if (opus.jump_url) {
                        jumpUrl = opus.jump_url.replace(/\\/g, '');
                        console.log('从opus获取jump_url:', jumpUrl);
                        
                        // 处理链接格式
                        if (jumpUrl.startsWith('//')) {
                            jumpUrl = 'https:' + jumpUrl;
                        } else if (!jumpUrl.startsWith('http')) {
                            jumpUrl = 'https://www.bilibili.com' + jumpUrl;
                        }
                    } else if (opus.id_str) {
                        // 使用opus的id_str构建链接
                        jumpUrl = `https://t.bilibili.com/${opus.id_str}`;
                        console.log('使用opus的id_str构建链接:', jumpUrl);
                    }
                }
                
                // 在新窗口打开链接
                if (jumpUrl) {
                    console.log('打开链接:', jumpUrl);
                    try {
                        window.open(jumpUrl, '_blank');
                    } catch (error) {
                        console.error('打开链接失败:', error);
                        window.location.href = jumpUrl;
                    }
                } else {
                    console.error('未找到有效的跳转链接');
                    // 如果没有找到跳转链接，尝试构建搜索链接
                    const searchTerm = encodeURIComponent(item.user?.name || '米汀');
                    const searchUrl = `https://search.bilibili.com/all?keyword=${searchTerm}`;
                    console.log('使用搜索链接:', searchUrl);
                    window.open(searchUrl, '_blank');
                }
            }
            
            return false; // 阻止事件冒泡
        });
        
        // 右键点击卡片使其掉落
        noteCard.addEventListener('contextmenu', function(e) {
            e.preventDefault(); // 阻止默认的右键菜单
            
            // 如果卡片已经掉落，不执行任何操作
            if (this.classList.contains('falling')) {
                return false;
            }
            
            // 添加掉落类
            this.classList.add('falling');
            
            // 获取卡片当前位置和旋转角度
            const currentTransform = window.getComputedStyle(this).transform;
            const currentRotation = this.style.transform || `rotate(${Math.random() * 30 - 15}deg)`;
            
            // 计算随机掉落方向和旋转
            const fallDirection = Math.random() > 0.5 ? 1 : -1;
            const fallRotation = Math.random() * 180 - 90; // -90到90度之间的随机旋转
            
            // 设置掉落动画，在画廊内向下移动一段距离并完全消失
            const randomDistance = 50 + Math.random() * 100; // 随机下落距离，100-250px之间
            this.style.transition = 'transform 1s ease-in-out, opacity 1s ease-in-out';
            this.style.transform = `${currentRotation} translateY(${randomDistance}px) translateX(${fallDirection * 30}px) rotate(${fallRotation * 0.5}deg)`;
            this.style.opacity = '0'; // 完全透明
            this.style.pointerEvents = 'none'; // 禁用鼠标事件
            
            // 动画结束后彻底移除元素
            setTimeout(() => {
                this.remove(); // 直接从DOM中移除元素
            }, 1000); // 等动画结束后移除元素
            
                         // 不记录掉落状态，刷新后卡片将恢复
            
            return false;
        });
    });
}

// 查找可用位置的函数
function findAvailablePosition(galleryWidth, galleryHeight, cardWidth, cardHeight, occupiedPositions, gridCols, gridRows) {
    // 添加边距，确保卡片不会触碰画廊边界
    const margin = 20;
    const effectiveWidth = galleryWidth - 2 * margin;
    const effectiveHeight = galleryHeight - 2 * margin;
    
    // 如果卡片尺寸超过有效区域，调整卡片尺寸
    const effectiveCardWidth = Math.min(cardWidth, effectiveWidth);
    const effectiveCardHeight = Math.min(cardHeight, effectiveHeight);
    
    // 尝试次数限制
    const maxAttempts = 150; // 增加尝试次数
    let attempts = 0;
    
    // 网格单元大小
    const cellWidth = effectiveWidth / gridCols;
    const cellHeight = effectiveHeight / gridRows;
    
    // 可用的网格单元
    const availableCells = [];
    for (let row = 0; row < gridRows; row++) {
        for (let col = 0; col < gridCols; col++) {
            availableCells.push({ row, col });
        }
    }
    
    // 随机打乱可用单元格顺序
    shuffleArray(availableCells);
    
    // 尝试找到不重叠的位置
    while (attempts < maxAttempts && availableCells.length > 0) {
        const cell = availableCells.pop();
        
        // 计算位置（加上边距）
        const x = margin + cell.col * cellWidth + Math.random() * (cellWidth - effectiveCardWidth);
        const y = margin + cell.row * cellHeight + Math.random() * (cellHeight - effectiveCardHeight);
        
        // 确保位置在有效范围内
        if (x < margin || x + effectiveCardWidth > galleryWidth - margin || 
            y < margin || y + effectiveCardHeight > galleryHeight - margin) {
            attempts++;
            continue;
        }
        
        // 检查是否与已有卡片重叠
        let overlaps = false;
        for (const pos of occupiedPositions) {
            if (
                x < pos.x + pos.width &&
                x + effectiveCardWidth > pos.x &&
                y < pos.y + pos.height &&
                y + effectiveCardHeight > pos.y
            ) {
                overlaps = true;
                break;
            }
        }
        
        // 如果不重叠，返回这个位置
        if (!overlaps) {
            return { x, y };
        }
        
        attempts++;
    }
    
    // 如果找不到不重叠的位置，返回一个安全的随机位置
    // 确保位置在画廊范围内
    return {
        x: margin + Math.random() * (effectiveWidth - effectiveCardWidth),
        y: margin + Math.random() * (effectiveHeight - effectiveCardHeight)
    };
}

// 打乱数组的函数
function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
}
    // 重新加载数据
    window.ClueWall.reload = function() {
        console.log('重新加载线索墙数据...');
        currentPage = 1;
        loadedItems = [];
        resetAllCards(); // 重置卡片状态
        loadInitialContent();
    };
    
    // 添加重新布局方法，允许外部调用
    window.ClueWall.relayout = function() {
        console.log('手动触发线索墙重新布局...');
        relayoutCards();
    };
    
    // 添加测试API的函数
    window.ClueWall.testAPI = function() {
        console.log('测试API连接...');
        fetch('api/fanart_api.php?page=1')
            .then(response => response.json())
            .then(data => {
                console.log('API测试结果:', data);
                alert('API测试完成，请查看控制台输出');
            })
            .catch(error => {
                console.error('API测试失败:', error);
                alert('API测试失败: ' + error.message);
            });
    };
    
    // 添加调试跳转链接的函数
    window.ClueWall.debugLinks = function() {
        console.log('调试跳转链接...');
        if (loadedItems && loadedItems.length > 0) {
            loadedItems.forEach((item, index) => {
                let jumpUrl = '';
                if (item.dynamic_card_item && item.dynamic_card_item.basic && item.dynamic_card_item.basic.jump_url) {
                    jumpUrl = item.dynamic_card_item.basic.jump_url;
                }
                console.log(`项目 ${index} 跳转链接:`, jumpUrl);
            });
            alert('跳转链接调试完成，请查看控制台输出');
        } else {
            alert('没有加载项目');
        }
    };
    
    // 如果已有数据源，初始化加载
    if (window.ClueWall.dataSource) {
        window.ClueWall.setDataSource(window.ClueWall.dataSource);
    } else {
        // 默认数据源
        window.ClueWall.setDataSource('api/fanart_api.php');
    }
});
</script>