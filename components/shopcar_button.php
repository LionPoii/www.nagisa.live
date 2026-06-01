<?php
/**
 * 购物车按钮组件 — 跳转至 SecWeb 二级商品页
 */

$hasProducts = false;

require_once 'includes/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $checkTable = $conn->query("SHOW TABLES LIKE 'shopcar_products'");
    if ($checkTable && $checkTable->rowCount() > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM shopcar_products WHERE active = 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $hasProducts = ($result && $result['product_count'] > 0);
    }
} catch (PDOException $e) {
    error_log("购物车商品检查错误: " . $e->getMessage(), 0);
}

$cartImage = $hasProducts ? "elements/shopcar/shopcar-full.png" : "elements/shopcar/shopcar-empty.png";
?>
<a href="/SecWeb/shopcar/shopcar.php" id="shopcar-btn" class="shopcar-btn-link" target="_blank" rel="noopener noreferrer" style="text-decoration: none;">
    <div class="shopcar-container"
         style="position: absolute;
                right: 12.5%;
                bottom: 5%;
                height: 5vh;
                width: auto;
                cursor: pointer;
                transition: transform 0.3s ease;">
        <img src="<?php echo $cartImage; ?>"
             alt="购物车"
             id="shopcar-image"
             class="shopcar-image"
             style="height: 100%;
                    width: auto;
                    transition: transform 0.3s ease;">
    </div>
</a>

<style>
.shopcar-container:hover {
    transform: scale(1.05);
}

.shopcar-container:hover .shopcar-image {
    transform: translateY(-10px);
}

.shopcar-container {
    animation: floating-shopcar 3s ease-in-out infinite;
}

@keyframes floating-shopcar {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}
</style>
