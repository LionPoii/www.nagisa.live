-- 购物车商品表结构
CREATE TABLE IF NOT EXISTS `shopcar_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '商品名称',
  `description` text NOT NULL COMMENT '商品描述',
  `price` varchar(50) NOT NULL COMMENT '商品价格',
  `image` varchar(255) DEFAULT NULL COMMENT '商品图片路径',
  `link` varchar(255) NOT NULL COMMENT '购买链接',
  `active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示',
  `position` int(11) NOT NULL DEFAULT '1' COMMENT '显示顺序',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='购物车商品表';

-- 插入一些示例数据
INSERT INTO `shopcar_products` (`title`, `description`, `price`, `image`, `link`, `active`, `position`) VALUES
('Nagisa限定抱枕', '精美的Nagisa主题抱枕，采用高质量面料制作，手感舒适。', '128.00', NULL, 'https://example.com/product1', 1, 1),
('Nagisa周边徽章套装', '包含5个精美徽章的套装，展示Nagisa的不同形象。', '38.50', NULL, 'https://example.com/product2', 1, 2),
('Nagisa定制马克杯', '高质量陶瓷马克杯，印有Nagisa独特设计，日常使用和收藏两相宜。', '59.90', NULL, 'https://example.com/product3', 1, 3); 