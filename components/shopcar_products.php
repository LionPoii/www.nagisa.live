<?php
/**
 * 购物车商品管理组件
 * 该组件负责从数据库加载商品数据并以卡片形式显示
 */

// 引入数据库连接
require_once 'includes/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // 检查表是否存在
    $tableExists = false;
    $checkTable = $conn->query("SHOW TABLES LIKE 'shopcar_products'");
    $tableExists = ($checkTable && $checkTable->rowCount() > 0);
    
    // 如果表存在则获取商品数据
    $products = [];
    if ($tableExists) {
        $stmt = $conn->prepare("SELECT * FROM shopcar_products WHERE active = 1 ORDER BY position ASC");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 检查是否存在商品
    if ($tableExists && count($products) > 0) {
        // 遍历并显示每个商品
        foreach ($products as $product) {
            // 转义所有输出以防XSS攻击
            $id = htmlspecialchars($product['id']);
            $title = htmlspecialchars($product['title']);
            $description = htmlspecialchars($product['description']);
            $price = htmlspecialchars($product['price']);
            $image = htmlspecialchars($product['image']);
            $link = htmlspecialchars($product['link']);
            
            // 确保图片路径正确
            if (!empty($image)) {
                // 检查是否为绝对URL
                if (filter_var($image, FILTER_VALIDATE_URL)) {
                    $imageUrl = $image;
                } 
                // 检查是否为相对路径且文件存在
                else if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($image, '/'))) {
                    $imageUrl = $image;
                } else {
                    // 检查默认图片是否存在
                    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/images/default-product.jpg')) {
                        $imageUrl = 'assets/images/default-product.jpg';
                    } else {
                        // 使用内联SVG作为默认图片
                        $imageUrl = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150" viewBox="0 0 200 150"><rect width="200" height="150" fill="#f8f8f8"/><text x="50%" y="50%" font-family="Arial" font-size="14" text-anchor="middle" fill="#999">图片加载失败</text></svg>');
                    }
                }
            } else {
                // 检查默认图片是否存在
                if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/images/default-product.jpg')) {
                    $imageUrl = 'assets/images/default-product.jpg';
                } else {
                    // 使用内联SVG作为默认图片
                    $imageUrl = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150" viewBox="0 0 200 150"><rect width="200" height="150" fill="#f8f8f8"/><text x="50%" y="50%" font-family="Arial" font-size="14" text-anchor="middle" fill="#999">图片加载失败</text></svg>');
                }
            }
            
            // 输出商品卡片HTML，默认宽度适配横向布局
            // 使用懒加载，图片src设为空白占位符，data-src保存实际图片地址
            echo <<<HTML
            <div class="product-card" data-product-id="$id" style="flex: 1 0 auto; min-width: 220px; max-width: calc(33% - 20px);">
                <div class="image-container" style="position: relative; height: 150px; overflow: hidden; border-radius: 5px; margin-bottom: 10px; background-color: #f5f5f5;">
                    <div class="image-placeholder" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; z-index: 1;">
                        <div style="width: 30px; height: 30px; border: 3px solid #eee; border-top: 3px solid #cc9471; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    </div>
                    <img data-src="$imageUrl" alt="$title" class="product-image lazy-image" style="opacity: 0; transition: opacity 0.5s ease; z-index: 2;">
                </div>
                <h3 class="product-title">$title</h3>
                <p class="product-description">$description</p>
                <div class="product-price">¥ $price</div>
                <a href="$link" class="product-link" target="_blank">查看详情</a>
            </div>
HTML;
        }
    } else {
        // 如果没有商品或表不存在，显示提示信息
        echo '<div class="no-products" style="flex: 1 0 100%; text-align: center; padding: 30px; color: #666;">
                <img src="elements/shopcar/shopcar-empty.png" style="width: 80px; margin-bottom: 15px;">
                <p style="font-family: \'QiantuHouhei\'; font-size: 18px;">暂无商品</p>
                <p style="font-size: 14px;">请稍后再来查看</p>
              </div>';
    }
} catch (PDOException $e) {
    // 捕获异常并显示友好的错误信息
    echo '<div class="no-products" style="flex: 1 0 100%; text-align: center; padding: 30px; color: #666;">
            <img src="elements/shopcar/shopcar-empty.png" style="width: 80px; margin-bottom: 15px;">
            <p style="font-family: \'QiantuHouhei\'; font-size: 18px;">暂无商品</p>
            <p style="font-size: 14px;">请稍后再来查看</p>
          </div>';
    
    // 如果启用了调试模式，可以记录错误日志
    error_log("购物车商品加载错误: " . $e->getMessage(), 0);
}

?>

<!-- 添加一些基本的响应式调整 -->
<style>
@media (max-width: 768px) {
    .product-card {
        min-width: 220px !important;
        max-width: calc(50% - 20px) !important;
    }
}

@media (max-width: 480px) {
    .product-card {
        min-width: 100% !important;
        max-width: 100% !important;
    }
}
</style> 