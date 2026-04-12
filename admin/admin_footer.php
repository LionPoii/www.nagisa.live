<?php
/**
 * 管理后台统一页脚组件
 * 用法：在每个管理页面底部包含此文件
 */
?>

    <!-- 页面结束标记 -->
    <footer class="admin-footer">
        <div class="text-center">
            © <?php echo date('Y'); ?> Nagisa Live
        </div>
    </footer>
    
    <script>
        // 确保页面加载动画已正确处理
        if (document.readyState === 'complete') {
            const loader = document.querySelector('.page-loader');
            if (loader) {
                loader.style.display = 'none';
            }
        }
    </script>
</body>
</html> 