/**
 * section1左侧按钮交互效果
 * 用于实现条纹动画和其他交互效果
 */

document.addEventListener('DOMContentLoaded', function() {
    /**
     * 初始化左侧按钮
     * @param {string} buttonId - 按钮容器的ID
     */
    function initLeftButton(buttonId) {
        const container = document.getElementById(buttonId);
        if (!container) return;
        
        const stripe1 = container.querySelector('.stripe-1');
        const stripe2 = container.querySelector('.stripe-2');
        const stripe3 = container.querySelector('.stripe-3');
        
        if (!stripe1 || !stripe2 || !stripe3) return;
        
        // 保存原始路径数据
        const originalPaths = {
            stripe1: stripe1.getAttribute('d'),
            stripe2: stripe2.getAttribute('d'),
            stripe3: stripe3.getAttribute('d')
        };
        
        // 水平路径数据 - 条纹2与条纹1、3方向相反
        const horizontalPaths = {
            stripe1: "M180,-16 L0,-16 L0,0 L180,0 Z", // 从右到左
            stripe2: "M180,0 L0,0 L0,16 L180,16 Z",   // 从左到右（与原始方向相反）
            stripe3: "M0,60 L180,60 L180,76 L0,76 Z"  // 从右到左
        };
        
        // 鼠标悬停时改变路径
        container.addEventListener('mouseenter', function() {
            stripe1.setAttribute('d', horizontalPaths.stripe1);
            stripe2.setAttribute('d', horizontalPaths.stripe2);
            stripe3.setAttribute('d', horizontalPaths.stripe3);
        });
        
        // 鼠标离开时恢复原始路径
        container.addEventListener('mouseleave', function() {
            stripe1.setAttribute('d', originalPaths.stripe1);
            stripe2.setAttribute('d', originalPaths.stripe2);
            stripe3.setAttribute('d', originalPaths.stripe3);
        });
    }
    
    /**
     * 创建左侧按钮
     * @param {Object} options - 按钮配置选项
     * @param {string} options.containerId - 容器ID
     * @param {string} options.text - 按钮文本
     * @param {string} options.link - 链接URL
     * @param {string} options.position - 位置，格式为 "top: 50%; left: 7.5%;"
     * @param {Object} options.colors - 条纹颜色
     */
    window.createLeftButton = function(options) {
        const defaults = {
            containerId: 'section1-left-button-' + Math.random().toString(36).substr(2, 9),
            text: '按钮',
            link: '#',
            position: 'top: 50%; left: 7.5%;',
            colors: {
                stripe1: '#3D4255', // 深蓝灰色
                stripe2: '#D79568', // 橙棕色
                stripe3: '#CAC8C7'  // 浅灰色
            }
        };
        
        const config = Object.assign({}, defaults, options);
        
        // 创建按钮HTML
        const buttonHtml = `
            <div class="section1-left-button-wrapper" id="${config.containerId}-wrapper" style="${config.position}">
                <a href="${config.link}" class="section1-left-button-link" id="${config.containerId}-link">
                    <div class="section1-left-button-container" id="${config.containerId}">
                        <svg class="section1-left-button-stripes" width="100%" height="100%" preserveAspectRatio="none" viewBox="0 0 180 60" xmlns="http://www.w3.org/2000/svg">
                            <!-- 条纹3 - 浅灰色 (最底层) -->
                            <path class="stripe-3" d="M0,44 L180,60 L180,76 L0,60 Z" fill="${config.colors.stripe3}" />
                            <!-- 条纹2 - 橙棕色 (中间层) -->
                            <path class="stripe-2" d="M180,0 L0,16 L0,0 L180,16 Z" fill="${config.colors.stripe2}" />
                            <!-- 条纹1 - 深蓝灰色 (最上层) -->
                            <path class="stripe-1" d="M180,-16 L0,0 L0,16 L180,0 Z" fill="${config.colors.stripe1}" />
                        </svg>
                        <div class="section1-left-button-text">${config.text}</div>
                    </div>
                </a>
            </div>
        `;
        
        // 将按钮添加到DOM
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = buttonHtml;
        document.body.appendChild(tempDiv.firstElementChild);
        
        // 初始化按钮交互
        initLeftButton(config.containerId);
        
        return config.containerId;
    };
    
    // 查找并初始化页面上已有的左侧按钮
    const existingButtons = document.querySelectorAll('.section1-left-button-container');
    existingButtons.forEach(button => {
        if (button.id) {
            initLeftButton(button.id);
        }
    });
}); 