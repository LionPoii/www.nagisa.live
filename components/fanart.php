<?php
/**
 * Fanart线索墙组件
 * 显示来自B站API的话题内容
 */
?>
<style>
    /* 线索墙容器样式 - 修改为水平布局 */
    .cluewall-container {
        position: absolute; /* 绝对定位 */
        left: 5%; /* 左侧距离 */
        right: 5%; /* 右侧距离 */
        bottom: 50vh; /* 距离底部50vh */
        height: 25vh; /* 固定高度 */
        min-height: 275px; /* 最小高度 */
        width: 90%; /* 宽度为90% */
        max-width: 90%; /* 最大宽度90% */
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

    /* 添加标题样式 */
    .cluewall-title {
        position: absolute;
        top: -45px;
        left: 0;
        color: #3D515F;
        font-size: 1.6em;
        font-family: "QIANTUHOUHEI", sans-serif;
        letter-spacing: 3px;
        z-index: 11;
        text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
    }
    
    /* 移除标题相关样式 */
    
    /* 修改为包含导航按钮的布局 */
    .cluewall-wrapper {
        flex-grow: 1; /* 填充剩余空间 */
        overflow: hidden; /* 防止内容溢出 */
        position: relative;
        display: flex; /* 启用弹性布局 */
        align-items: center; /* 垂直居中 */
        justify-content: center; /* 水平居中 */
    }

    .cluewall-gallery {
        position: relative;
        width: 100%;
        height: 100%;
        overflow-x: auto; /* 允许水平滚动 */
        overflow-y: hidden; /* 禁止垂直溢出 */
        display: flex; /* 水平排列 */
        flex-direction: row; /* 确保水平方向 */
        gap: 0.25%; /* 修改卡片之间的间距为0.25% */
        padding-bottom: 10px; /* 为滚动条留空间 */
        padding-left: 0; /* 确保没有左侧内边距 */
        margin-left: 0; /* 确保没有左侧外边距 */
        scrollbar-width: none; /* Firefox 隐藏滚动条 */
        -ms-overflow-style: none; /* IE/Edge 隐藏滚动条 */
        scroll-behavior: auto; /* 移除平滑滚动以提高响应速度 */
        transition: none; /* 移除过渡效果提高响应速度 */
        cursor: grab; /* 恢复抓取光标 */
        justify-content: flex-start; /* 改为从左侧开始布局 */
        user-select: none; /* 防止选择文本 */
        will-change: transform, scroll-position; /* 提示浏览器优化滚动和变换 */
    }
    
    /* 恢复抓取相关的光标样式 */
    .cluewall-gallery.grabbing {
        cursor: grabbing; /* 恢复抓取时的光标 */
    }
    
    /* 隐藏WebKit浏览器的滚动条 */
    .cluewall-gallery::-webkit-scrollbar {
        display: none;
    }

    /* 确保卡片适应容器大小 */
    .cluewall-dynamic-card {
        flex: 0 0 auto; /* 不伸缩，保持原始尺寸 */
        width: 15%; /* 修改宽度为容器的15% */
        min-width: 200px; /* 最小高度 */
        box-sizing: border-box;
        overflow: hidden; /* 防止卡片内容溢出 */
        background-color: #ffffff; /* 纯白色背景 */
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease; /* 添加背景色过渡 */
        padding: 10px; /* 减少内边距 */
        height: 90%; /* 修改高度为容器的90% */
        min-height: 200px; /* 最小高度 */
        max-height: 90%; /* 最大高度限制为容器的90% */
        display: flex;
        flex-direction: column;
        cursor: pointer; /* 改为指针光标表示可点击 */
        user-select: none; /* 防止选择文本 */
        z-index: 1; /* 确保卡片在画廊之上 */
    }
    
    .cluewall-dynamic-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        background-color: rgba(255, 255, 255, 0.7); /* 悬停时变为70%不透明度 */
    }
    
    /* 移除拖动相关样式 */

    /* 卡片头部样式 */
    .card-header {
        display: flex;
        align-items: center;
        margin-bottom: 6px; /* 减少底部间距 */
        flex: 0 0 auto; /* 不伸缩 */
    }
    
    .card-username {
        font-weight: bold;
        font-size: 1em; /* 增大用户名字体 */
        color: #333;
    }
    
    /* 卡片主体样式 */
    .card-body {
        margin-bottom: 6px; /* 减少底部间距 */
        display: flex;
        flex-direction: column;
        flex: 1 1 auto; /* 填充剩余空间 */
        overflow: hidden;
        min-height: 0; /* 允许flex子元素适当收缩 */
    }
    
    /* 隐藏图片容器 */
    .card-image-container {
        display: none;
    }
    
    /* 确保卡片内的文本不会溢出 */
    .card-text {
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
        overflow-y: auto; /* 允许垂直滚动 */
        font-size: 1em; /* 增大文本字体 */
        line-height: 1.4; /* 略微增加行高 */
        color: #333;
        flex: 1 1 auto; /* 填充剩余空间 */
        min-height: 0; /* 允许flex子元素适当收缩 */
        max-height: 15vh; /* 增加最大高度，大约占总高度(25vh)的60% */
        padding-right: 5px; /* 为滚动条留出空间 */
    }
    
    /* 优化文本区域滚动条 */
    .card-text::-webkit-scrollbar {
        width: 3px;
    }
    
    .card-text::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.03);
    }
    
    .card-text::-webkit-scrollbar-thumb {
        background: rgba(204, 148, 113, 0.2);
        border-radius: 2px;
    }
    
    .card-text::-webkit-scrollbar-thumb:hover {
        background: rgba(204, 148, 113, 0.3);
    }
    
    /* 卡片底部样式 */
    .card-footer {
        display: flex;
        justify-content: space-between;
        font-size: 0.85em; /* 增大底部字体 */
        color: #666;
        margin-top: auto; /* 将底部推到卡片底端 */
        flex: 0 0 auto; /* 不伸缩 */
        padding-top: 4px; /* 减少顶部间距 */
    }
    
    /* 占位样式，用于表示拖动卡片的目标位置 */
    .card-placeholder {
        flex: 0 0 auto;
        width: 20%;
        height: 95%;
        border: 2px dashed rgba(204, 148, 113, 0.5);
        border-radius: 8px;
        margin: 0;
        box-sizing: border-box;
        background-color: rgba(204, 148, 113, 0.1);
    }

    /* 优化滚动条样式 */
    .cluewall-gallery::-webkit-scrollbar {
        height: 6px; /* 水平滚动条高度 */
        width: 0px; /* 隐藏垂直滚动条 */
    }
    
    .cluewall-gallery::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.1);
        border-radius: 3px;
    }
    
    .cluewall-gallery::-webkit-scrollbar-thumb {
        background: rgba(204, 148, 113, 0.5);
        border-radius: 3px;
    }
    
    .cluewall-gallery::-webkit-scrollbar-thumb:hover {
        background: rgba(204, 148, 113, 0.7);
    }
    
    /* 卡片文本的滚动条 */
    .card-text::-webkit-scrollbar {
        width: 4px;
    }
    
    .card-text::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
    }
    
    .card-text::-webkit-scrollbar-thumb {
        background: rgba(204, 148, 113, 0.3);
        border-radius: 2px;
    }
    
    /* scrollable类的样式 */
    .cluewall-gallery.scrollable {
        padding-bottom: 6px;
    }

    /* 修改导航按钮位置，防止影响卡片位置 */
    .nav-button.prev {
        left: 0px;
        z-index: 20;
    }

    /* 回到顶部按钮样式 */
    .cluewall-top-button {
        opacity: 0.85;
        background: rgba(255, 255, 255, 0.95);
        box-shadow: none; /* 移除阴影效果 */
    }
    
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

<!-- 注意：需要确保父容器.section-content设置为position:relative -->

<div class="cluewall-container">
    <div class="cluewall-title">#米汀的线索墙</div>
    <div class="cluewall-wrapper">
        <div class="cluewall-gallery" id="dynamicGallery">
            <!-- 卡片内容将通过JavaScript动态插入 -->
            <!-- 卡片模板示例 -->
            <div class="cluewall-dynamic-card template-card" style="display:none;">
                <div class="card-header">
                    <span class="card-username"></span>
                </div>
                <div class="card-body">
                    <div class="card-image-container">
                        <img class="card-image" src="" alt="图片内容">
                    </div>
                    <div class="card-text"></div>
                </div>
                <div class="card-footer">
                    <div class="card-date"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="cluewall-loading" id="loading">
    <div class="cluewall-spinner"></div>
</div>

<script>
    // 在DOMContentLoaded事件中初始化线索墙
    document.addEventListener('DOMContentLoaded', function() {
        // 定义ClueWall对象
        window.ClueWall = window.ClueWall || {};
        
        // 存储数据源URL
        let dataSource = '';
        
        // 存储已加载的数据
        let loadedItems = [];
        let currentPage = 1;
        let isLoading = false;
        
        // 设置数据源
        window.ClueWall.setDataSource = function(url) {
            dataSource = url;
            loadInitialContent();
        };
        
        // 清理现有卡片
        function clearExistingCards() {
            const gallery = document.getElementById('dynamicGallery');
            if (gallery) {
                // 移除所有非模板卡片
                const existingCards = gallery.querySelectorAll('.cluewall-dynamic-card:not(.template-card)');
                existingCards.forEach(card => card.remove());
                console.log(`清理了 ${existingCards.length} 个现有卡片`);
            }
        }
        
        // 加载初始内容
        function loadInitialContent() {
            if (!dataSource) return;
            
            isLoading = true;
            showLoading();
            
            // 清理现有卡片
            clearExistingCards();
            
            // 立即初始化滚动功能，不等待数据加载
            initScrollGallery();
            
            // 从API获取数据
            fetch(`${dataSource}?page=${currentPage}`)
                .then(response => {
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
                    if (data.code !== 0) {
                        throw new Error(`API错误: ${data.message || '未知错误'}`);
                    }
                    
                    // 验证数据项
                    const items = data.data?.items || [];
                    if (!Array.isArray(items)) {
                        throw new Error('API返回的items不是数组格式');
                    }
                    
                    console.log(`API返回 ${items.length} 个数据项`);
                    
                    // 调试：显示前几个数据项的结构
                    if (items.length > 0) {
                        console.log('第一个数据项结构:', JSON.stringify(items[0], null, 2));
                        if (items.length > 1) {
                            console.log('第二个数据项结构:', JSON.stringify(items[1], null, 2));
                        }
                    }
                    
                    // 过滤有效数据项
                    const validItems = items.filter(item => {
                        return item && 
                               typeof item === 'object' && 
                               (item.content && item.content.trim() !== '' || 
                                (item.images && Array.isArray(item.images) && item.images.length > 0)) &&
                               item.user && 
                               item.user.name && 
                               item.user.name.trim() !== '';
                    });
                    
                    console.log(`有效数据项: ${validItems.length} 个`);
                    
                    if (validItems.length > 0) {
                        loadedItems = loadedItems.concat(validItems);
                        renderCards(validItems);
                        currentPage++;
                    } else {
                        console.warn('没有找到有效的数据项');
                    }
                    
                    isLoading = false;
                    hideLoading();
                })
                .catch(error => {
                    console.error('获取线索墙数据失败:', error);
                    isLoading = false;
                    hideLoading();
                    
                    // 显示错误信息给用户
                    const gallery = document.getElementById('dynamicGallery');
                    if (gallery) {
                        const errorCard = document.createElement('div');
                        errorCard.className = 'cluewall-dynamic-card';
                        errorCard.style.display = 'flex';
                        errorCard.style.alignItems = 'center';
                        errorCard.style.justifyContent = 'center';
                        errorCard.style.color = '#666';
                        errorCard.style.fontSize = '0.9em';
                        errorCard.innerHTML = `
                            <div style="text-align: center;">
                                <div>数据加载失败</div>
                                <div style="font-size: 0.8em; margin-top: 5px;">请稍后重试</div>
                            </div>
                        `;
                        gallery.appendChild(errorCard);
                    }
                });
        }
        
        // 渲染卡片
        function renderCards(items) {
            const gallery = document.getElementById('dynamicGallery');
            const templateCard = document.querySelector('.template-card');
            
            if (!gallery || !templateCard) return;
            
            // 数据已经在loadInitialContent中过滤过了，这里直接使用
            items.forEach((item, index) => {
                // 创建卡片元素
                const card = createCardElement(item, templateCard, index);
                
                // 添加到画廊
                if (card) {
                    gallery.appendChild(card);
                }
            });
            
            // 检查是否可以水平滚动
            checkScrollable();
        }
        
        // 创建卡片元素
        function createCardElement(item, templateCard, index) {
            // 验证数据项
            if (!item || typeof item !== 'object') {
                console.warn('跳过无效数据项:', item);
                return null;
            }
            
            // 检查是否有有效内容
            const hasValidContent = item.content && item.content.trim() !== '';
            const hasValidImages = item.images && Array.isArray(item.images) && item.images.length > 0;
            
            if (!hasValidContent && !hasValidImages) {
                console.warn('跳过无内容的数据项:', item);
                return null;
            }
            
            // 克隆模板
            const card = templateCard.cloneNode(true);
            card.classList.remove('template-card');
            card.style.display = '';
            card.dataset.index = index; // 存储索引以便排序
            
            // 设置用户信息
            if (item.user && item.user.name) {
                const username = card.querySelector('.card-username');
                if (username) {
                    username.textContent = item.user.name.trim();
                }
            } else {
                // 如果没有用户名，隐藏整个卡片
                console.warn('跳过无用户名的数据项:', item);
                return null;
            }
            
            // 设置图片(如果有)
            if (hasValidImages) {
                const imageContainer = card.querySelector('.card-image-container');
                const image = card.querySelector('.card-image');
                
                if (image && imageContainer) {
                    image.src = item.images[0].url;
                    image.alt = item.images[0].description || '图片';
                    imageContainer.style.display = 'block'; // 显示图片容器
                }
            } else {
                // 如果没有图片则隐藏图片容器
                const imageContainer = card.querySelector('.card-image-container');
                if (imageContainer) imageContainer.style.display = 'none';
            }
            
            // 设置文本内容
            const textElement = card.querySelector('.card-text');
            if (textElement) {
                if (hasValidContent) {
                    textElement.textContent = item.content.trim();
                } else {
                    textElement.textContent = '图片内容'; // 如果只有图片没有文字
                }
            }
            
            // 设置日期
            const dateElement = card.querySelector('.card-date');
            if (dateElement && item.create_time) {
                try {
                    const date = new Date(parseInt(item.create_time) * 1000);
                    if (!isNaN(date.getTime())) {
                        // 直接使用月日格式
                        const month = date.getMonth() + 1;
                        const day = date.getDate();
                        dateElement.textContent = month + '月' + day + '日';
                    } else {
                        dateElement.textContent = '';
                    }
                } catch(e) {
                    console.error('日期处理错误', e);
                    dateElement.textContent = '';
                }
            }
            
            return card;
        }
        
        // 格式化数字(超过1000显示为1k)
        function formatNumber(num) {
            return num > 999 ? (num/1000).toFixed(1) + 'k' : num;
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
        
        // 检查是否可以水平滚动
        function checkScrollable() {
            const gallery = document.getElementById('dynamicGallery');
            if (gallery) {
                if (gallery.scrollWidth > gallery.clientWidth) {
                    gallery.classList.add('scrollable');
                } else {
                    gallery.classList.remove('scrollable');
                }
            }
        }

        // 全新的交互控制逻辑
        function initScrollGallery() {
            const gallery = document.getElementById('dynamicGallery');
            if (!gallery) return;
            
            console.log('初始化滚动画廊');
            
            // 状态变量
            let interactionState = {
                isMouseDown: false,
                isDragging: false,
                preventClick: false,
                startX: 0,
                startY: 0,
                scrollLeft: 0,
                startTime: 0,
                rafId: null
            };
            
            // 常量定义
            const DRAG_THRESHOLD = 5; // 拖动阈值（像素）
            const DRAG_COOLDOWN = 500; // 拖动冷却期（毫秒）
            
            // 添加手势锁定机制
            function lockGestures() {
                interactionState.preventClick = true;
                // 拖动后设置一段时间的点击冷却期
                setTimeout(() => {
                    interactionState.preventClick = false;
                }, DRAG_COOLDOWN);
            }
            
            // 鼠标按下处理
            function handlePointerDown(clientX, clientY) {
                interactionState.isMouseDown = true;
                interactionState.isDragging = false;
                interactionState.startX = clientX;
                interactionState.startY = clientY;
                interactionState.scrollLeft = gallery.scrollLeft;
                interactionState.startTime = Date.now();
                gallery.classList.add('grabbing');
                cancelAnimationFrame(interactionState.rafId);
            }
            
            // 拖动处理
            function handleDrag(clientX) {
                if (!interactionState.isMouseDown) return false;
                
                const x = clientX;
                const walk = interactionState.startX - x;
                
                // 使用requestAnimationFrame优化滚动性能
                cancelAnimationFrame(interactionState.rafId);
                interactionState.rafId = requestAnimationFrame(() => {
                    gallery.scrollLeft = interactionState.scrollLeft + walk;
                });
                
                return true;
            }
            
            // 鼠标移动距离检测
            function checkDragThreshold(clientX, clientY) {
                if (!interactionState.isMouseDown) return false;
                
                const deltaX = Math.abs(clientX - interactionState.startX);
                const deltaY = Math.abs(clientY - interactionState.startY);
                
                if (deltaX > DRAG_THRESHOLD || deltaY > DRAG_THRESHOLD) {
                    if (!interactionState.isDragging) {
                        interactionState.isDragging = true;
                        lockGestures(); // 一旦确认拖动，立即锁定点击
                    }
                    return true;
                }
                return false;
            }
            
            // 鼠标按下事件
            gallery.addEventListener('mousedown', function(e) {
                e.preventDefault();
                handlePointerDown(e.pageX, e.pageY);
            });
            
            // 鼠标移动事件
            gallery.addEventListener('mousemove', function(e) {
                if (!interactionState.isMouseDown) return;
                e.preventDefault();
                
                // 检查是否超过拖动阈值
                const isDragging = checkDragThreshold(e.pageX, e.pageY);
                if (isDragging) {
                    handleDrag(e.pageX);
                }
            });
            
            // 鼠标松开事件
            gallery.addEventListener('mouseup', function(e) {
                if (!interactionState.isMouseDown) return;
                
                interactionState.isMouseDown = false;
                gallery.classList.remove('grabbing');
            });
            
            // 鼠标离开区域
            gallery.addEventListener('mouseleave', function() {
                if (interactionState.isMouseDown) {
                    interactionState.isMouseDown = false;
                    gallery.classList.remove('grabbing');
                }
            });
            
            // 单击事件 - 使用click事件而非在mouseup中处理
            // click事件会在mousedown和mouseup之后触发
            gallery.addEventListener('click', function(e) {
                // 如果处于锁定状态或刚刚发生了拖动，阻止点击
                if (interactionState.preventClick || interactionState.isDragging) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
                
                // 处理卡片点击
                const card = e.target.closest('.cluewall-dynamic-card');
                if (card) {
                    const index = parseInt(card.dataset.index);
                    if (!isNaN(index) && loadedItems[index]) {
                        console.log('卡片被点击:', loadedItems[index]);
                        alert('卡片内容: ' + loadedItems[index].content);
                    }
                }
            }, true); // 使用捕获阶段，确保最先处理
            
            // 触摸设备支持 - 使用相同的逻辑
            gallery.addEventListener('touchstart', function(e) {
                e.preventDefault();
                const touch = e.touches[0];
                handlePointerDown(touch.pageX, touch.pageY);
            }, { passive: false });
            
            gallery.addEventListener('touchmove', function(e) {
                if (!interactionState.isMouseDown) return;
                
                const touch = e.touches[0];
                // 检查是否超过拖动阈值
                const isDragging = checkDragThreshold(touch.pageX, touch.pageY);
                
                if (isDragging) {
                    handleDrag(touch.pageX);
                    e.preventDefault();
                }
            }, { passive: false });
            
            gallery.addEventListener('touchend', function(e) {
                if (!interactionState.isMouseDown) return;
                
                interactionState.isMouseDown = false;
                gallery.classList.remove('grabbing');
                
                // 触摸结束时，如果已经拖动，不进行点击处理
                if (interactionState.isDragging) {
                    e.preventDefault();
                    return;
                }
                
                // 如果是简单点击且不在锁定状态，处理触摸点击
                if (!interactionState.preventClick) {
                    const touch = e.changedTouches[0];
                    const element = document.elementFromPoint(touch.clientX, touch.clientY);
                    const card = element?.closest('.cluewall-dynamic-card');
                    
                    if (card) {
                        const index = parseInt(card.dataset.index);
                        if (!isNaN(index) && loadedItems[index]) {
                            console.log('卡片被触摸点击:', loadedItems[index]);
                            alert('卡片内容: ' + loadedItems[index].content);
                        }
                    }
                }
            });
            
            // 为画廊添加阻止默认click事件的处理器，防止拖动后触发
            gallery.addEventListener('click', function(e) {
                if (interactionState.preventClick) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            }, true); // 使用捕获阶段确保最先处理
        }
        
        // 重新加载数据
        window.ClueWall.reload = function() {
            console.log('重新加载线索墙数据...');
            currentPage = 1;
            loadedItems = [];
            loadInitialContent();
        };
        
        // 确保线索墙保持在底部
        function ensureClueWallPosition() {
            const clueWall = document.querySelector('.cluewall-container');
            if (clueWall) {
                clueWall.style.bottom = '30vh';  // 修改为35vh以匹配CSS设置
            }
        }
        
        // 页面加载和窗口大小变化时执行
        window.addEventListener('resize', function() {
            ensureClueWallPosition();
            checkScrollable();
        });
        
        ensureClueWallPosition();
        
        // 定期检查位置（以防其他脚本修改）
        setInterval(ensureClueWallPosition, 1000);
        
        // 如果已有数据源，初始化加载
        if (window.ClueWall.dataSource) {
            window.ClueWall.setDataSource(window.ClueWall.dataSource);
        } else {
            // 默认数据源
            window.ClueWall.setDataSource('api/fanart_api.php');
        }
    });
</script> 