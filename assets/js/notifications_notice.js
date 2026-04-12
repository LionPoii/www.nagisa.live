/**
 * 浏览器通知系统
 * 用于显示动态更新和直播开始的通知
 */

// 通知系统类
class NotificationSystem {
    constructor() {
        this.notificationEnabled = false;
        this.lastLiveStatus = false; // 上次直播状态
        this.lastDynamicId = null;   // 上次最新动态ID
        this.checkInterval = 60000;  // 默认检查间隔（1分钟）
        this.initialized = false;    // 是否已初始化
    }

    // 初始化通知系统
    init() {
        if (this.initialized) return;
        
        // 检查浏览器是否支持通知
        if (!("Notification" in window)) {
            console.log("此浏览器不支持桌面通知");
            return;
        }
        
        // 添加通知权限按钮
        this.addNotificationButton();
        
        // 如果已经授权，直接启用通知
        if (Notification.permission === "granted") {
            this.notificationEnabled = true;
            this.startChecking();
        }
        
        this.initialized = true;
    }
    
    // 添加通知权限按钮
    addNotificationButton() {
        // 创建通知按钮
        const button = document.createElement('div');
        button.className = 'notification-button';
        button.innerHTML = `
            <div class="notification-icon">🔔</div>
            <div class="notification-text">开启通知</div>
        `;
        
        // 设置按钮样式
        button.style.position = 'fixed';
        button.style.bottom = '20px';
        button.style.right = '20px';
        button.style.backgroundColor = 'rgba(93, 64, 55, 0.8)';
        button.style.color = 'white';
        button.style.padding = '10px 15px';
        button.style.borderRadius = '8px';
        button.style.cursor = 'pointer';
        button.style.display = 'flex';
        button.style.alignItems = 'center';
        button.style.gap = '8px';
        button.style.zIndex = '9999';
        button.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
        button.style.transition = 'all 0.3s ease';
        
        // 添加悬停效果
        button.addEventListener('mouseover', () => {
            button.style.backgroundColor = 'rgba(93, 64, 55, 1)';
            button.style.transform = 'translateY(-2px)';
        });
        
        button.addEventListener('mouseout', () => {
            button.style.backgroundColor = 'rgba(93, 64, 55, 0.8)';
            button.style.transform = 'translateY(0)';
        });
        
        // 添加点击事件
        button.addEventListener('click', () => this.requestPermission());
        
        // 将按钮添加到页面
        document.body.appendChild(button);
        
        // 更新按钮状态
        this.updateButtonState(button);
    }
    
    // 更新按钮状态
    updateButtonState(button) {
        if (!button) {
            button = document.querySelector('.notification-button');
            if (!button) return;
        }
        
        const iconElement = button.querySelector('.notification-icon');
        const textElement = button.querySelector('.notification-text');
        
        if (Notification.permission === "granted") {
            iconElement.textContent = '🔔';
            textElement.textContent = '通知已启用';
            button.style.backgroundColor = 'rgba(76, 175, 80, 0.8)';
        } else if (Notification.permission === "denied") {
            iconElement.textContent = '🔕';
            textElement.textContent = '通知已禁用';
            button.style.backgroundColor = 'rgba(244, 67, 54, 0.8)';
        } else {
            iconElement.textContent = '🔔';
            textElement.textContent = '开启通知';
            button.style.backgroundColor = 'rgba(93, 64, 55, 0.8)';
        }
    }
    
    // 请求通知权限
    requestPermission() {
        Notification.requestPermission().then(permission => {
            if (permission === "granted") {
                this.notificationEnabled = true;
                this.showNotification("通知已启用", "您将收到动态更新和直播开始的通知");
                this.startChecking();
            }
            
            // 更新按钮状态
            this.updateButtonState();
        });
    }
    
    // 开始定期检查
    startChecking() {
        if (!this.notificationEnabled) return;
        
        // 立即执行一次检查
        this.checkLiveStatus();
        this.checkDynamicUpdates();
        
        // 设置定期检查
        setInterval(() => {
            this.checkLiveStatus();
            this.checkDynamicUpdates();
        }, this.checkInterval);
    }
    
    // 检查直播状态
    checkLiveStatus() {
        fetch('/api/live_status.php')
            .then(response => response.json())
            .then(data => {
                // 如果是首次检查，只记录状态不发送通知
                if (this.lastLiveStatus === false) {
                    this.lastLiveStatus = data.live_status === 1;
                    return;
                }
                
                // 检测直播状态变化
                const currentStatus = data.live_status === 1;
                if (!this.lastLiveStatus && currentStatus) {
                    // 从离线变为在线，发送开播通知
                    this.showNotification(
                        "直播开始啦！",
                        data.title || "点击前往直播间",
                        data.keyframe || data.user_cover || null,
                        `https://live.bilibili.com/${data.room_id}`
                    );
                }
                
                // 更新状态
                this.lastLiveStatus = currentStatus;
            })
            .catch(error => console.error('检查直播状态出错:', error));
    }
    
    // 检查动态更新
    checkDynamicUpdates() {
        fetch('/api/dynamic.php?limit=1')
            .then(response => response.json())
            .then(data => {
                if (!data || !data.length || !data[0].id) return;
                
                const latestDynamic = data[0];
                
                // 如果是首次检查，只记录ID不发送通知
                if (this.lastDynamicId === null) {
                    this.lastDynamicId = latestDynamic.id;
                    return;
                }
                
                // 检测是否有新动态
                if (latestDynamic.id !== this.lastDynamicId) {
                    // 获取动态内容摘要
                    let content = latestDynamic.content || '';
                    if (content.length > 50) {
                        content = content.substring(0, 50) + '...';
                    }
                    
                    // 获取图片（如果有）
                    let image = null;
                    if (latestDynamic.images && latestDynamic.images.length > 0) {
                        image = latestDynamic.images[0];
                    } else if (latestDynamic.video && latestDynamic.video.cover) {
                        image = latestDynamic.video.cover;
                    }
                    
                    // 发送新动态通知
                    this.showNotification(
                        "有新动态啦！",
                        content || "点击查看详情",
                        image,
                        `https://t.bilibili.com/${latestDynamic.id}`
                    );
                    
                    // 更新ID
                    this.lastDynamicId = latestDynamic.id;
                }
            })
            .catch(error => console.error('检查动态更新出错:', error));
    }
    
    // 显示通知
    showNotification(title, body, icon = null, url = null) {
        if (!this.notificationEnabled) return;
        
        const options = {
            body: body,
            icon: icon || '/assets/images/nagisa_icon.png', // 默认图标
            badge: '/assets/images/nagisa_badge.png',
            vibrate: [200, 100, 200],
            tag: 'nagisa-notification',
            renotify: true
        };
        
        const notification = new Notification(title, options);
        
        // 添加点击事件
        if (url) {
            notification.onclick = function() {
                window.open(url, '_blank');
                notification.close();
            };
        }
    }
}

// 创建通知系统实例并初始化
document.addEventListener('DOMContentLoaded', () => {
    window.nagisaNotifications = new NotificationSystem();
    window.nagisaNotifications.init();
}); 