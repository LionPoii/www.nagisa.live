<?php
/**
 * 居中 Toast（PHP 输出，fixed 视口中心）。
 * 与「Nagisa Admin Toast」右上角轻提示（Top-right light toast）不是同一套 UI；命名与全站约定见 ../../docs/NAGISA_TOP_RIGHT_LIGHT_TOAST.md。
 */
function showToast($message, $type = 'success') {
    $bgColor = $type === 'success' ? 'bg-green-500' : 'bg-red-500';
    $icon = $type === 'success' ? 'check-circle' : 'exclamation-circle';
    ?>
    <div id="toast" class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-50">
        <div class="<?php echo $bgColor; ?> text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 min-w-[300px] relative">
            <i class="fas fa-<?php echo $icon; ?> text-xl"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
            <?php if ($type === 'error'): ?>
            <button onclick="closeToast()" class="absolute top-2 right-2 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <script>
        // 显示提示
        const toast = document.getElementById('toast');
        toast.style.opacity = '1';
        
        function closeToast() {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }

        <?php if ($type === 'success'): ?>
        // 成功提示3秒后自动隐藏
        setTimeout(() => {
            closeToast();
        }, 3000);
        <?php endif; ?>
    </script>
    <style>
        #toast {
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }
        #toast button {
            transition: all 0.2s ease-in-out;
        }
        #toast button:hover {
            transform: scale(1.1);
        }
    </style>
    <?php
} 

/**
 * 显示成功消息
 * @param string $message 成功消息
 */
function showSuccess($message) {
    showToast($message, 'success');
}

/**
 * 显示错误消息
 * @param string $message 错误消息
 */
function showError($message) {
    showToast($message, 'error');
} 