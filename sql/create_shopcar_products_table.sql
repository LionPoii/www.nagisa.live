-- 创建购物车商品表
CREATE TABLE IF NOT EXISTS `shopcar_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `position` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 添加示例数据
INSERT INTO `shopcar_products` 
(`title`, `description`, `price`, `image`, `link`, `active`, `position`) 
VALUES 
('示例商品1', '这是一个示例商品，您可以添加描述和更多细节。', 99.90, 'assets/images/default-product.jpg', 'https://example.com/product1', 1, 1),
('示例商品2', '这是另一个示例商品，展示多个商品的排列方式。', 159.00, 'assets/images/default-product.jpg', 'https://example.com/product2', 1, 2); 