<?php
// 确保只能通过系统访问
if (!defined('IN_SYSTEM')) {
    header('HTTP/1.1 403 Forbidden');
    exit('禁止访问');
}

// 根据状态选择图标
$icon_path = '/assets/icon/notice-off.png';
$status_text = '通知已关闭';
?>

<!-- 通知状态按钮 -->
<div class="notification-toggle-container">
    <div id="notification-toggle" class="notification-toggle" title="<?php echo $status_text; ?>">
        <img src="<?php echo $icon_path; ?>" alt="通知状态" id="notification-icon">
    </div>
</div>

<style>
.notification-toggle {
    cursor: pointer;
    width: 40px;
    height: 40px;
    margin-left: 15px;
    margin-right: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    user-select: none;
}

.notification-toggle img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.notification-toggle:hover img {
    transform: scale(1.2);
}

/* 添加图标动画效果的样式 */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.3); }
    100% { transform: scale(1); }
}

@keyframes shake {
    0% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    50% { transform: translateX(0); }
    75% { transform: translateX(5px); }
    100% { transform: translateX(0); }
}

@keyframes rotate {
    0% { transform: rotate(0deg); }
    25% { transform: rotate(-15deg); }
    50% { transform: rotate(0deg); }
    75% { transform: rotate(15deg); }
    100% { transform: rotate(0deg); }
}

.notification-icon-pulse {
    animation: pulse 1s ease-in-out;
}

.notification-icon-shake {
    animation: shake 0.5s ease-in-out;
}

.notification-icon-rotate {
    animation: rotate 0.8s ease-in-out;
}

/* 添加通知徽标样式 */
.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: red;
    color: white;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.notification-toggle-container {
    position: relative;
    display: inline-block;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const notificationIcon = document.getElementById('notification-icon');
    const notificationToggle = document.getElementById('notification-toggle');
    let notificationCheckInterval = null;
    
    // 添加通知徽标
    const notificationBadge = document.createElement('div');
    notificationBadge.className = 'notification-badge';
    notificationBadge.textContent = '!';
    
    // 获取已有的容器
    const toggleContainer = document.querySelector('.notification-toggle-container');
    if (toggleContainer) {
        toggleContainer.appendChild(notificationBadge);
    }
    
    // 尝试从localStorage获取上次记录的动态ID
    let lastDynamicId = null;
    try {
        const storedDynamicId = localStorage.getItem('lastDynamicId');
        if (storedDynamicId) {
            lastDynamicId = storedDynamicId;
        }
    } catch (e) {
        // localStorage错误处理
    }
    
    let lastLiveStatus = false;
    
    // 添加点击事件
    notificationToggle.addEventListener('click', function() {
        // 检查当前通知权限状态
        if ('Notification' in window) {
            if (Notification.permission === 'granted') {
                // 如果已授权，显示如何关闭通知的提示
                showNotificationSettingsHelp();
            } else {
                // 如果未授权或被拒绝，请求通知权限
                requestNotificationPermission();
            }
        } else {
            // 浏览器不支持通知
            showCustomToast('您的浏览器不支持通知功能');
        }
    });
    
    // 显示通知菜单功能已移除
    
    // 检查浏览器通知权限并更新图标
    function checkNotificationPermission() {
        // 检查浏览器是否支持通知
        if ('Notification' in window) {
            const wasGranted = notificationIcon.src.includes('notice-on.png');
            const isGranted = Notification.permission === 'granted';
            
            // 根据通知权限状态设置图标
            if (isGranted) {
                notificationIcon.src = '/assets/icon/notice-on.png';
                notificationToggle.title = '通知已开启';
                
                // 仅当状态从未授权变为已授权时，启动检查
                if (!wasGranted) {
                    startUpdateCheck();
                }
            } else {
                notificationIcon.src = '/assets/icon/notice-off.png';
                notificationToggle.title = '通知已关闭';
                
                // 如果通知权限未授予，停止检查更新
                stopUpdateCheck();
            }
        } else {
            // 浏览器不支持通知，显示关闭图标
            notificationIcon.src = '/assets/icon/notice-off.png';
            notificationToggle.title = '您的浏览器不支持通知功能';
            
            // 停止检查更新
            stopUpdateCheck();
        }
    }
    
    // 开始检查更新
    function startUpdateCheck() {
        // 如果已经有定时器，先清除
        stopUpdateCheck();
        
        // 立即检查一次
        checkForUpdates();
        
        // 设置定期检查（每30秒检查一次）
        notificationCheckInterval = setInterval(checkForUpdates, 30000);
    }
    
    // 停止检查更新
    function stopUpdateCheck() {
        if (notificationCheckInterval) {
            clearInterval(notificationCheckInterval);
            notificationCheckInterval = null;
        }
    }
    
    // 检查动态和直播状态
    function checkForUpdates() {
        // 如果通知权限不是已授予，不进行检查
        if (Notification.permission !== 'granted') {
            return;
        }
        
        // 检查动态更新
        checkDynamicUpdates();
        
        // 检查直播状态
        checkLiveStatus();
    }
    
    // 播放图标动画效果
    function playIconAnimation(type) {
        if (!notificationIcon) return;
        
        // 移除之前的动画类
        notificationIcon.classList.remove('notification-icon-pulse', 'notification-icon-shake', 'notification-icon-rotate');
        
        // 根据类型添加不同的动画效果
        switch(type) {
            case 'dynamic':
                notificationIcon.classList.add('notification-icon-pulse');
                break;
            case 'live':
                notificationIcon.classList.add('notification-icon-shake');
                break;
            default:
                notificationIcon.classList.add('notification-icon-rotate');
        }
        
        // 显示通知徽标
        if (notificationBadge) {
            notificationBadge.style.opacity = '1';
            
            // 5秒后隐藏徽标
            setTimeout(() => {
                notificationBadge.style.opacity = '0';
            }, 5000);
        }
        
        // 动画结束后移除类
        setTimeout(() => {
            notificationIcon.classList.remove('notification-icon-pulse', 'notification-icon-shake', 'notification-icon-rotate');
        }, 1000);
    }
    
    // 检查动态更新
    function checkDynamicUpdates() {
        // 直接从B站API获取最新动态
        const timestamp = new Date().getTime();
        
        // 首先尝试从我们的API获取
        fetch(`https://www.nagisa.live/api/check_dynamic_updates.php?t=${timestamp}&force=1`)
            .then(response => {
                return response.json();
            })
            .then(data => {
                if (data && data.latest_dynamic && data.latest_dynamic.id) {
                    // 如果是首次检查，只记录ID不显示通知
                    if (lastDynamicId === null) {
                        lastDynamicId = data.latest_dynamic.id;
                    } 
                    // 如果有新动态且不是首次检查，播放图标动画和显示系统弹窗通知
                    else if (data.latest_dynamic.id !== lastDynamicId) {
                        // 播放图标动画
                        playIconAnimation('dynamic');
                        
                        // 同时显示系统弹窗通知
                        showNotification('动态更新', data.latest_dynamic.text || '有新动态发布', 'dynamic', {
                            url: data.latest_dynamic.url || 'https://t.bilibili.com/' + data.latest_dynamic.id,
                            target: '_blank' // 在新标签页中打开
                        });
                        
                        // 更新记录的ID
                        const oldId = lastDynamicId;
                        lastDynamicId = data.latest_dynamic.id;
                        
                        // 将最新的动态ID存储到localStorage中
                        try {
                            localStorage.setItem('lastDynamicId', lastDynamicId);
                        } catch (e) {
                            // localStorage错误处理
                        }
                    }
                } else {
                    // 未获取到有效的动态数据
                }
            })
            .catch(error => {
                // 处理错误
            });
    }
    
    // Fanart检测功能已移除
    
    // 检查直播状态
    function checkLiveStatus() {
        fetch('https://www.nagisa.live/api/check_live_status.php?_=' + new Date().getTime())
            .then(response => {
                return response.json();
            })
            .then(data => {
                if (data && typeof data.is_living !== 'undefined') {
                    // 如果是首次检查，只记录状态不显示通知
                    if (lastLiveStatus === null) {
                        lastLiveStatus = data.is_living;
                    } 
                    // 如果直播状态从离线变为在线，播放图标动画和显示系统弹窗通知
                    else if (data.is_living && !lastLiveStatus) {
                        // 播放图标动画
                        playIconAnimation('live');
                        
                        // 同时显示系统弹窗通知
                        showNotification('直播开始', data.title || '直播已开始', 'live', {
                            url: 'https://live.bilibili.com/' + data.room_id,
                            image: data.cover_url || null, // 只设置大图，显示完整封面
                            target: '_blank' // 在新标签页中打开
                        });
                        
                        lastLiveStatus = data.is_living;
                    } else if (!data.is_living && lastLiveStatus) {
                        lastLiveStatus = data.is_living;
                    }
                    
                    // 触发自定义事件，通知直播状态组件
                    const event = new CustomEvent('liveStatusChanged', { 
                        detail: { 
                            is_living: data.is_living,
                            title: data.title,
                            room_id: data.room_id
                        } 
                    });
                    document.dispatchEvent(event);
                    
                    // 如果存在直播状态组件的更新函数，直接调用
                    if (typeof window.updateLiveStatusUI === 'function') {
                        window.updateLiveStatusUI(data.is_living);
                    }
                }
            })
            .catch(error => {
                // 处理错误
            });
    }
    
    // 暴露方法给全局，以便直播状态组件可以检查是否存在
    window.checkLiveStatus = checkLiveStatus;
    
    // 存储已显示通知的ID，防止重复显示
    let shownNotifications = {};
    
    /**
     * 系统通知拉取 image 时不会像 <img referrerpolicy="no-referrer"> 那样带防盗链策略，
     * B 站 CDN 直链在通知里会间歇性空白。改为走本站同源代理（与 includes/bili_img_proxy.php 一致）。
     */
    function resolveNotificationImageUrl(rawUrl) {
        if (!rawUrl || typeof rawUrl !== 'string') return null;
        var u = rawUrl.trim();
        if (u.indexOf('//') === 0) {
            u = (typeof location !== 'undefined' && location.protocol ? location.protocol : 'https:') + u;
        }
        if (!/^https?:\/\//i.test(u)) return null;
        var host;
        try {
            host = new URL(u).hostname;
        } catch (e) {
            return null;
        }
        var needProxy = /\.(?:bilibili\.com|hdslb\.com|bilivideo\.com)$/i.test(host);
        if (!needProxy) return u;
        try {
            return new URL('/includes/bili_img_proxy.php?url=' + encodeURIComponent(u), location.origin).href;
        } catch (e2) {
            return null;
        }
    }
    
    // 显示浏览器通知
    function showNotification(title, message, type, options) {
        // 检查浏览器是否支持通知
        if (!('Notification' in window)) {
            return;
        }
        
        if (Notification.permission !== 'granted') {
            return;
        }
        
        // 创建通知唯一ID
        const notificationId = type + '-' + (options && options.url ? options.url : Date.now());
        
        // 检查是否已经显示过相同的通知
        if (shownNotifications[notificationId]) {
            // 如果相同的通知在30秒内已经显示过，则不再显示
            const timeSinceLastShown = Date.now() - shownNotifications[notificationId];
            if (timeSinceLastShown < 30000) {
                return;
            }
        }
        
        // 记录此通知已显示
        shownNotifications[notificationId] = Date.now();
        
        // 格式化标题和内容，使其更加区分
        const formattedTitle = `【${title}】`;
        const formattedMessage = message;
        
        let notificationOptions = {
            body: formattedMessage,
            requireInteraction: true, // 在Windows上保持通知显示，直到用户交互
            tag: notificationId, // 使用唯一ID作为tag，可以防止某些浏览器重复显示
            silent: false, // 允许通知声音
            badge: '/assets/icon/notice-on.png', // 添加通知徽章
            image: null // 用于显示大图片
        };
        
        // 根据通知类型设置不同图标和大图
        if (type === 'live') {
            // 优先使用大图（经同源代理，避免 B 站直链在系统通知中无法加载）
            if (options && options.image) {
                var liveImg = resolveNotificationImageUrl(options.image);
                if (liveImg) {
                    notificationOptions.image = liveImg;
                    notificationOptions.icon = null;
                } else if (options.icon) {
                    notificationOptions.icon = options.icon;
                } else {
                    notificationOptions.icon = '/assets/icon/notice-on.png';
                }
            } else if (options && options.icon) {
                // 如果没有大图但有小图标
                notificationOptions.icon = options.icon;
            } else {
                // 默认图标
                notificationOptions.icon = '/assets/icon/notice-on.png';
            }
        } else if (type === 'dynamic' && options && options.image) {
            var dynImg = resolveNotificationImageUrl(options.image);
            if (dynImg) {
                notificationOptions.image = dynImg;
                notificationOptions.icon = null;
            } else {
                notificationOptions.icon = '/assets/icon/notice-on.png';
            }
        }
        
        try {
            const notification = new Notification(formattedTitle, notificationOptions);
            
            // 点击通知时的行为
            notification.onclick = function() {
                if (options && options.url) {
                    // 根据通知类型处理跳转
                    if (type === 'dynamic') {
                        // 动态通知 - 打开B站动态页面
                        window.open(options.url, '_blank');
                    } else if (type === 'live') {
                        // 直播通知 - 打开B站直播间
                        window.open(options.url, '_blank');
                    } else {
                        // 其他类型通知
                        if (options.url.startsWith('http')) {
                            window.open(options.url, options.target || '_blank');
                        } else {
                            window.location.href = options.url;
                        }
                    }
                }
                this.close();
            };
            
            // 30秒后自动关闭通知
            setTimeout(function() {
                notification.close();
            }, 30000);
        } catch (error) {
            // 忽略错误
        }
    }
    
    // 请求通知权限
    function requestNotificationPermission() {
        // 检查浏览器是否支持通知
        if (!('Notification' in window)) {
            showCustomToast('您的浏览器不支持通知功能');
            return;
        }
        
        // 确保在用户交互的上下文中请求权限
        try {
            // 使用新的Promise API
            Notification.requestPermission().then(function(permission) {
                // 更新图标状态
                checkNotificationPermission();
                
                if (permission === 'granted') {
                    showCustomToast('通知权限已开启');
                    // 显示一个测试通知
                    showTestNotification();
                } else if (permission === 'denied') {
                    // 显示提示，使用模态窗口而不是Toast
                    showNotificationSettingsHelp();
                } else {
                    // 默认状态，用户未做出选择
                    showCustomToast('请点击允许以开启通知功能');
                }
            }).catch(function(error) {
                // 使用模态窗口显示错误信息
                showNotificationSettingsHelp();
            });
        } catch (error) {
            // 兼容旧版浏览器的回调API
            try {
                Notification.requestPermission(function(permission) {
                    // 更新图标状态
                    checkNotificationPermission();
                    
                    if (permission === 'granted') {
                        showCustomToast('通知权限已开启');
                        // 显示一个测试通知
                        showTestNotification();
                    } else if (permission === 'denied') {
                        showNotificationSettingsHelp();
                    }
                });
            } catch (fallbackError) {
                // 使用模态窗口显示错误信息
                showNotificationSettingsHelp();
            }
        }
    }
    
    // 显示测试通知
    function showTestNotification() {
        if (Notification.permission === 'granted') {
            const formattedTitle = `【通知测试】`;
            const options = {
                body: '通知功能已启用，您将收到动态、直播的更新提醒',
                icon: '/assets/icon/notice-on.png',
                tag: 'test-notification',
                badge: '/assets/icon/notice-on.png'
            };
            
            const notification = new Notification(formattedTitle, options);
            
            // 10秒后自动关闭
            setTimeout(function() {
                notification.close();
            }, 10000);
        }
    }
    
    // 显示通知设置帮助
    function showNotificationSettingsHelp() {
        // 检测浏览器类型
        const isChrome = navigator.userAgent.indexOf('Chrome') > -1;
        const isFirefox = navigator.userAgent.indexOf('Firefox') > -1;
        const isEdge = navigator.userAgent.indexOf('Edg') > -1;
        const isSafari = navigator.userAgent.indexOf('Safari') > -1 && navigator.userAgent.indexOf('Chrome') === -1;
        
        let message = '';
        
        // 根据通知权限状态显示不同的消息
        if (Notification.permission === 'denied') {
            message = ' 通知权限已被拒绝，请按照以下步骤启用：\n\n';
            
            if (isChrome || isEdge) {
                message += ' 1. 点击地址栏左侧的锁定图标\n 2. 点击"网站设置"\n 3. 在通知选项中选择"允许"';
            } else if (isFirefox) {
                message += ' 1. 点击地址栏左侧的信息图标\n 2. 点击"权限"\n 3. 在通知选项中选择"允许"';
            } else if (isSafari) {
                message += ' 1. 打开Safari偏好设置\n 2. 点击"网站"\n 3. 点击"通知"\n 4. 找到本网站并选择"允许"';
            } else {
                message += ' 请在浏览器设置中找到网站权限或通知设置，然后启用本网站的通知';
            }
            
            message += '\n\n 通知接收功能需要网站处于开启状态下使用';
        } else {
            message = ' 要关闭通知权限，请：\n\n';
            
            if (isChrome || isEdge) {
                message += ' 1. 点击地址栏左侧的锁定图标\n 2. 点击"网站设置"\n 3. 在通知选项中选择"阻止"';
            } else if (isFirefox) {
                message += ' 1. 点击地址栏左侧的信息图标\n 2. 点击"权限"\n 3. 在通知选项中选择"阻止"';
            } else if (isSafari) {
                message += ' 1. 打开Safari偏好设置\n 2. 点击"网站"\n 3. 点击"通知"\n 4. 找到本网站并选择"阻止"';
            } else {
                message += ' 请在浏览器设置中找到网站权限或通知设置，然后禁用本网站的通知';
            }
        }
        
        showCustomModal(message);
    }
    
    // 自定义模态框功能已移除
    
    // 显示自定义模态框
    function showCustomModal(message) {
        // 检查是否已存在customModal元素
        let modal = document.getElementById('customModal');
        
        // 如果不存在，创建一个新的模态框
        if (!modal) {
            // 创建模态框元素
            modal = document.createElement('div');
            modal.id = 'customModal';
            modal.className = 'custom-modal';
            
            // 创建内容容器
            const content = document.createElement('div');
            content.className = 'modal-content';
            
            // 创建消息文本容器
            const messageDiv = document.createElement('div');
            messageDiv.className = 'modal-message';
            messageDiv.id = 'modalMessage';
            
            // 组装模态框
            content.appendChild(messageDiv);
            modal.appendChild(content);
            
            // 直接在modal上绑定点击事件，用于关闭
            modal.onclick = function(event) {
                // 如果点击的是模态框背景，而不是内容
                if (event.target === modal) {
                    closeModal();
                }
            };
            
            // 阻止内容区域的点击事件冒泡
            content.onclick = function(event) {
                event.stopPropagation();
            };
            
            // 添加样式
            const style = document.createElement('style');
            style.textContent = `
                .custom-modal {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0,0,0,0.5);
                    z-index: 9999;
                    justify-content: center;
                    align-items: center;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                    cursor: pointer; /* 指示整个模态区域可点击 */
                    -webkit-tap-highlight-color: transparent; /* 移除移动设备上的点击高亮 */
                }
                
                .modal-content {
                    background-color: white;
                    padding: 30px;
                    border-radius: 10px;
                    max-width: 400px;
                    text-align: left !important;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                    position: relative;
                    transform: translateY(-20px);
                    transition: transform 0.3s ease;
                    border: 2px solid rgb(204, 148, 113);
                    cursor: default; /* 重置内容区域的鼠标样式 */
                }
                
                .modal-message {
                    font-size: 1.2rem;
                    color: #4d4030;
                    font-family: 'QiantuHouhei', sans-serif;
                    letter-spacing: 1px;
                    white-space: pre-line; /* 保留换行符 */
                    margin: 0;
                    padding: 10px;
                    text-align: left !important;
                }
                
                .custom-modal.show {
                    opacity: 1;
                }
                
                .custom-modal.show .modal-content {
                    transform: translateY(0);
                }
            `;
            
            // 添加到页面
            document.head.appendChild(style);
            document.body.appendChild(modal);
        } else {
            // 如果已存在，确保事件绑定正确
            modal.onclick = function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            };
            
            const content = modal.querySelector('.modal-content');
            if (content) {
                content.onclick = function(event) {
                    event.stopPropagation();
                };
            }
        }
        
        // 设置消息内容
        const modalMessage = document.getElementById('modalMessage');
        modalMessage.innerText = message;
        modalMessage.style.textAlign = 'left';
        
        // 显示模态框
        modal.style.display = 'flex';
        
        // 使用setTimeout让CSS过渡效果生效
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
        
        // 10秒后自动关闭
        const autoCloseTimer = setTimeout(() => {
            closeModal();
        }, 10000);
        
        // 保存定时器ID，以便在手动关闭时清除
        modal.dataset.autoCloseTimer = autoCloseTimer;
    }
    
    // 关闭模态框
    function closeModal() {
        const modal = document.getElementById('customModal');
        if (modal) {
            // 清除自动关闭定时器
            if (modal.dataset.autoCloseTimer) {
                clearTimeout(parseInt(modal.dataset.autoCloseTimer));
                delete modal.dataset.autoCloseTimer;
            }
            
            modal.classList.remove('show');
            
            // 等待过渡效果完成后隐藏模态框
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
    }
    
    // 显示自定义Toast提示
    function showCustomToast(message) {
        // 检查是否已存在toast元素
        let toast = document.getElementById('customToast');
        
        // 如果已存在，先移除
        if (toast) {
            document.body.removeChild(toast);
        }
        
        // 创建toast元素
        toast = document.createElement('div');
        toast.id = 'customToast';
        toast.className = 'custom-toast';
        toast.textContent = message;
        
        // 添加样式
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.left = '50%';
        toast.style.transform = 'translateX(-50%)';
        toast.style.backgroundColor = 'rgba(204, 148, 113, 0.9)';
        toast.style.color = 'white';
        toast.style.padding = '10px 20px';
        toast.style.borderRadius = '8px';
        toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';
        toast.style.zIndex = '2000';
        toast.style.transition = 'opacity 0.3s ease';
        toast.style.opacity = '0';
        
        // 添加到页面
        document.body.appendChild(toast);
        
        // 触发重排以应用过渡效果
        void toast.offsetWidth;
        toast.style.opacity = '1';
        
        // 3秒后自动消失
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentNode) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }
    
    // 页面加载时检查通知权限
    checkNotificationPermission();
    
    // 定期检查通知权限状态（每30秒）
    setInterval(checkNotificationPermission, 30000);
});
</script> 