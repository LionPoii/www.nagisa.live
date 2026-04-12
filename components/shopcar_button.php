<?php
/**
 * 购物车按钮组件
 */

// 检查是否有商品
$hasProducts = false;

// 引入数据库连接
require_once 'includes/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // 检查表是否存在
    $tableExists = false;
    $checkTable = $conn->query("SHOW TABLES LIKE 'shopcar_products'");
    $tableExists = ($checkTable && $checkTable->rowCount() > 0);
    
    // 如果表存在则检查是否有激活的商品
    if ($tableExists) {
        $stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM shopcar_products WHERE active = 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $hasProducts = ($result && $result['product_count'] > 0);
    }
} catch (PDOException $e) {
    // 出错时默认显示空购物车图标
    error_log("购物车商品检查错误: " . $e->getMessage(), 0);
}

// 根据是否有商品选择对应的图片
$cartImage = $hasProducts ? "elements/shopcar/shopcar-full.png" : "elements/shopcar/shopcar-empty.png";
?>
<!-- 购物车跳转按钮 -->
<a href="#" id="shopcar-btn" onclick="showShopcarModal(); return false;" style="text-decoration: none;">
    <div class="shopcar-container" 
         style="position: absolute; 
                right: 12.5%; 
                bottom: 5%; 
                height: 5vh; 
                width: auto;
                cursor: pointer;
                transition: transform 0.3s ease;">
        <img src="<?php echo $cartImage; ?>" 
             alt="Shopping Cart Icon" 
             id="shopcar-image"
             class="shopcar-image" 
             style="height: 100%; 
                    width: auto;
                    transition: transform 0.3s ease;">
        <div class="shopcar-text" style="position: absolute; 
                                     top: 120%; 
                                     left: 50%; 
                                     transform: translate(-50%, 0); 
                                     color: #4c526b; 
                                     font-weight: bold; 
                                     text-align: center;
                                     font-size: 1.5rem;
                                     letter-spacing: 0.1em;
                                     user-select: none;
                                     transition: color 0.3s ease;
                                     font-family: 'QiantuHouhei';">
        </div>
    </div>
</a>

<!-- 购物车模态窗口 -->
<div id="shopcar-modal" class="custom-modal">
    <div class="modal-content shopcar-modal-content">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; position: sticky; top: 0; background-color: #fff; padding-top: 10px; z-index: 2;">
            <h2 class="modal-title" style="font-family: 'QiantuHouhei'; color: #4c526b; margin: 0; font-size: 24px;">商品列表</h2>
            <span class="close-button" onclick="closeShopcarModal()" style="cursor: pointer; font-size: 24px; color: #4c526b;">&times;</span>
        </div>
        <div class="modal-body">
            <div class="product-cards" id="product-cards-container" style="display: flex; flex-wrap: wrap; justify-content: flex-start; gap: 20px;">
                <!-- 商品卡片将通过PHP动态加载 -->
                <?php require_once 'components/shopcar_products.php'; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* 购物车按钮特定样式 */
.shopcar-container:hover {
    transform: scale(1.05);
}

.shopcar-container:hover .shopcar-text {
    color: #cc9471 !important;
}

.shopcar-container:hover .shopcar-image {
    transform: translateY(-10px);
}

/* 添加浮动动画 */
.shopcar-container {
    animation: floating-shopcar 3s ease-in-out infinite;
}

@keyframes floating-shopcar {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}

/* 购物车模态窗口样式 */
.custom-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1050;
    justify-content: center;
    align-items: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.custom-modal.show {
    opacity: 1;
}

.shopcar-modal-content {
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    border: 2px solid #cc9471;
    transform: scale(0.9);
    transition: transform 0.3s ease;
    overflow-y: auto;
    max-height: 80vh;
    max-width: 80%;
    width: auto;
    padding: 20px;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: #cc9471 #f0f0f0;
}

/* 自定义滚动条样式（Chrome、Edge、Safari） */
.shopcar-modal-content::-webkit-scrollbar {
    width: 8px;
}

.shopcar-modal-content::-webkit-scrollbar-track {
    background: #f0f0f0;
    border-radius: 4px;
}

.shopcar-modal-content::-webkit-scrollbar-thumb {
    background-color: #cc9471;
    border-radius: 4px;
}

.custom-modal.show .shopcar-modal-content {
    transform: scale(1);
}

.product-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    background-color: #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.product-image {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 5px;
    margin-bottom: 10px;
    transition: opacity 0.3s ease;
}

/* 懒加载图片的过渡效果 */
.lazy-image {
    opacity: 0;
    transition: opacity 0.5s ease;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 2;
}

.loaded-image {
    opacity: 1 !important;
}

.error-image {
    opacity: 1 !important;
    object-fit: contain;
    background-color: #f8f8f8;
}

.image-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
    background-color: #f5f5f5;
}

.image-container {
    position: relative;
    height: 150px;
    overflow: hidden;
    border-radius: 5px;
    margin-bottom: 10px;
    background-color: #f5f5f5;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.product-title {
    font-family: 'QiantuHouhei';
    font-size: 18px; /* 增大商品标题字体 */
    font-weight: bold;
    color: #4c526b;
    margin-bottom: 8px;
}

.product-description {
    font-size: 16px; /* 增大描述字体 */
    color: #666;
    flex-grow: 1;
    margin-bottom: 10px;
}

.product-price {
    font-weight: bold;
    color: #cc9471;
    font-size: 20px; /* 增大价格字体 */
    margin-bottom: 10px;
}

.product-link {
    display: inline-block;
    background-color: #4c526b;
    color: white;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    text-align: center;
    transition: background-color 0.3s ease;
    font-size: 16px; /* 增大链接字体 */
}

.product-link:hover {
    background-color: #cc9471;
}
</style>

<script>
// 组件加载完成后调整字体大小
document.addEventListener('DOMContentLoaded', function() {
    // 存储购物车状态
    window.shopcarState = {
        hasProducts: <?php echo $hasProducts ? 'true' : 'false'; ?>
    };
    
    // 调整购物车按钮字体大小的函数
    function adjustShopcarFontSize() {
        const shopcarImage = document.querySelector('.shopcar-image');
        const shopcarText = document.querySelector('.shopcar-text');
        if (shopcarImage && shopcarText) {
            const height = shopcarImage.offsetHeight;
            // 确保有最小值，防止字体消失
            shopcarText.style.fontSize = height > 0 ? `${Math.max(height * 0.2, 16)}px` : '16px';
        }
    }
    
    // 调整商品卡片宽度的函数
    function adjustProductCardWidth() {
        const container = document.getElementById('product-cards-container');
        const cards = document.querySelectorAll('.product-card');
        
        if (!container || cards.length === 0) return;
        
        // 获取容器宽度和浏览器宽度
        const containerWidth = container.clientWidth;
        const windowWidth = window.innerWidth * 0.8; // 80%浏览器宽度
        
        // 计算每个卡片应有的宽度
        const totalCards = cards.length;
        let cardWidth;
        
        if (containerWidth * totalCards / (totalCards - 0.5) < windowWidth) {
            // 如果所有卡片加起来不超过80%浏览器宽度，就平均分配
            cardWidth = `calc(${100 / totalCards}% - ${20 * (totalCards - 1) / totalCards}px)`;
            
            cards.forEach(card => {
                card.style.width = cardWidth;
                card.style.minWidth = '200px';
                card.style.maxWidth = '100%';
            });
        } else {
            // 如果超过80%浏览器宽度，设置固定宽度自动换行
            cards.forEach(card => {
                card.style.width = 'calc(250px - 20px)';
                card.style.minWidth = '200px';
                card.style.maxWidth = 'calc(33% - 20px)';
            });
        }
    }
    
    // 初始调整
    adjustShopcarFontSize();
    
    // 更新购物车图标状态
    function updateShopcarIcon() {
        const shopcarImage = document.getElementById('shopcar-image');
        if (shopcarImage) {
            if (window.shopcarState.hasProducts) {
                shopcarImage.src = 'elements/shopcar/shopcar-full.png';
            } else {
                shopcarImage.src = 'elements/shopcar/shopcar-empty.png';
            }
        }
    }
    
    // 处理模态窗口内的滚动事件
    const modalContent = document.querySelector('.shopcar-modal-content');
    if (modalContent) {
        // 防止滚动事件冒泡到父元素
        modalContent.addEventListener('wheel', function(e) {
            // 检查是否已经到达滚动边界
            const atTop = this.scrollTop === 0 && e.deltaY < 0;
            const atBottom = this.scrollTop + this.offsetHeight >= this.scrollHeight && e.deltaY > 0;
            
            // 只有当不在边界时才阻止事件传播
            if (!atTop && !atBottom) {
                e.stopPropagation();
            }
        }, { passive: true });
        
        // 触摸设备滚动处理
        modalContent.addEventListener('touchmove', function(e) {
            e.stopPropagation();
        }, { passive: true });
    }
    
    // 显示模态窗口时调整卡片布局
    window.showShopcarModal = function() {
        const modal = document.getElementById('shopcar-modal');
        
        // 显示模态窗口
        modal.style.display = 'flex';
        
        // 立即开始加载图片，不等待过渡效果
        loadProductImages();
        
        // 使用setTimeout让CSS过渡效果生效
        setTimeout(() => {
            modal.classList.add('show');
            // 调整卡片布局
            setTimeout(() => {
                adjustProductCardWidth();
                
                // 检查窗口中是否有商品卡片
                const cards = document.querySelectorAll('.product-card');
                const noProducts = document.querySelector('.no-products');
                
                // 更新购物车状态
                window.shopcarState.hasProducts = (cards.length > 0 && !noProducts);
                
                // 更新购物车图标
                updateShopcarIcon();
                
                // 再次尝试懒加载商品图片，确保所有图片都能加载
                loadProductImages();
            }, 50);
        }, 10);
        
        // 防止页面滚动
        document.body.style.overflow = 'hidden';
    };
    
    // 监听窗口大小变化
    window.addEventListener('resize', function() {
        adjustShopcarFontSize();
        if (document.getElementById('shopcar-modal').style.display === 'flex') {
            adjustProductCardWidth();
        }
    });
    
    // 确保在图片加载后调整大小
    const shopcarImage = document.querySelector('.shopcar-image');
    if (shopcarImage) {
        shopcarImage.onload = adjustShopcarFontSize;
    }
});

// 懒加载商品图片的函数
function loadProductImages() {
    const lazyImages = document.querySelectorAll('.lazy-image');
    
    // 检查是否有需要懒加载的图片
    if (lazyImages.length === 0) return;
    
    console.log(`开始加载${lazyImages.length}张商品图片`);
    
    lazyImages.forEach((img, index) => {
        if (img.dataset.src) {
            // 创建新的Image对象来预加载
            const tempImg = new Image();
            
            // 设置加载超时
            const loadTimeout = setTimeout(() => {
                console.log(`商品图片 ${index + 1} 加载超时，使用默认图片`);
                // 检查默认图片是否存在
                const defaultImg = new Image();
                defaultImg.onload = function() {
                    img.src = 'assets/images/default-product.jpg';
                    img.classList.remove('lazy-image');
                    img.classList.add('error-image');
                    
                    // 找到并隐藏对应的加载占位符
                    const container = img.closest('.image-container');
                    if (container) {
                        const placeholder = container.querySelector('.image-placeholder');
                        if (placeholder) {
                            placeholder.style.display = 'none';
                        }
                    }
                };
                defaultImg.onerror = function() {
                    // 默认图片也不存在，使用内联SVG
                    img.src = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150" viewBox="0 0 200 150"><rect width="200" height="150" fill="#f8f8f8"/><text x="50%" y="50%" font-family="Arial" font-size="14" text-anchor="middle" fill="#999">图片加载失败</text></svg>');
                    img.classList.remove('lazy-image');
                    img.classList.add('error-image');
                    
                    // 找到并隐藏对应的加载占位符
                    const container = img.closest('.image-container');
                    if (container) {
                        const placeholder = container.querySelector('.image-placeholder');
                        if (placeholder) {
                            placeholder.style.display = 'none';
                        }
                    }
                };
                defaultImg.src = 'assets/images/default-product.jpg';
            }, 5000); // 5秒超时
            
            // 添加加载事件
            tempImg.onload = function() {
                clearTimeout(loadTimeout); // 清除超时
                console.log(`商品图片 ${index + 1} 加载成功: ${img.dataset.src}`);
                img.src = img.dataset.src;
                img.classList.remove('lazy-image');
                img.classList.add('loaded-image');
                
                // 找到并隐藏对应的加载占位符
                const container = img.closest('.image-container');
                if (container) {
                    const placeholder = container.querySelector('.image-placeholder');
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                }
            };
            
            tempImg.onerror = function() {
                clearTimeout(loadTimeout); // 清除超时
                // 如果加载失败，使用默认图片
                console.log(`商品图片 ${index + 1} 加载失败，使用默认图片`);
                // 检查默认图片是否存在
                const defaultImg = new Image();
                defaultImg.onload = function() {
                    img.src = 'assets/images/default-product.jpg';
                    img.classList.remove('lazy-image');
                    img.classList.add('error-image');
                    
                    // 找到并隐藏对应的加载占位符
                    const container = img.closest('.image-container');
                    if (container) {
                        const placeholder = container.querySelector('.image-placeholder');
                        if (placeholder) {
                            placeholder.style.display = 'none';
                        }
                    }
                };
                defaultImg.onerror = function() {
                    // 默认图片也不存在，使用内联SVG
                    img.src = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150" viewBox="0 0 200 150"><rect width="200" height="150" fill="#f8f8f8"/><text x="50%" y="50%" font-family="Arial" font-size="14" text-anchor="middle" fill="#999">图片加载失败</text></svg>');
                    img.classList.remove('lazy-image');
                    img.classList.add('error-image');
                    
                    // 找到并隐藏对应的加载占位符
                    const container = img.closest('.image-container');
                    if (container) {
                        const placeholder = container.querySelector('.image-placeholder');
                        if (placeholder) {
                            placeholder.style.display = 'none';
                        }
                    }
                };
                defaultImg.src = 'assets/images/default-product.jpg';
            };
            
            // 开始加载图片
            console.log(`开始加载商品图片 ${index + 1}: ${img.dataset.src}`);
            tempImg.src = img.dataset.src;
        }
    });
}

// 关闭购物车模态窗口
function closeShopcarModal() {
    const modal = document.getElementById('shopcar-modal');
    modal.classList.remove('show');
    
    // 等待过渡效果完成后隐藏模态窗口
    setTimeout(() => {
        modal.style.display = 'none';
        // 恢复页面滚动
        document.body.style.overflow = '';
    }, 300);
}

// 点击窗口外部关闭模态窗口
window.addEventListener('click', function(event) {
    const modal = document.getElementById('shopcar-modal');
    if (event.target === modal) {
        closeShopcarModal();
    }
});
</script> 