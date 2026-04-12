document.addEventListener('DOMContentLoaded', function() {
    const splashImage = document.getElementById('splashImage');
    if (!splashImage) return;
    
    // 检查URL参数是否包含no_splash=1
    const urlParams = new URLSearchParams(window.location.search);
    const noSplash = urlParams.get('no_splash');
    
    // 如果URL参数中有no_splash=1，则不显示开幕遮罩
    if (noSplash === '1') {
        splashImage.style.display = 'none';
        splashImage.style.opacity = '0';
        // 保存到会话存储，确保刷新时也不显示遮罩
        sessionStorage.setItem('no_splash', '1');
        return;
    }
    
    // 检查会话存储中是否有no_splash标记
    if (sessionStorage.getItem('no_splash') === '1') {
        splashImage.style.display = 'none';
        splashImage.style.opacity = '0';
        return;
    }
    
    // 显示并淡出遮罩
        splashImage.style.display = 'block';
        splashImage.style.opacity = '1';
        
        // 强制重绘
        void splashImage.offsetWidth;
        
    // 保持0.5秒后再开始淡出动画
        setTimeout(() => {
            splashImage.style.transition = 'opacity 1.2s ease-out';
            splashImage.style.opacity = '0';
        splashImage.classList.add('splash-hide');
        
        setTimeout(() => {
            splashImage.style.display = 'none';
        }, 1200); // 动画持续1.2s
    }, 500);
}); 