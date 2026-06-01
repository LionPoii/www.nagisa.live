-- 商品系列表
CREATE TABLE IF NOT EXISTS `shopcar_series` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL COMMENT '上级商品系列ID，NULL为顶级',
  `title` varchar(255) NOT NULL COMMENT '系列名称',
  `description` text COMMENT '系列说明',
  `position` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品系列';

-- 购物车商品表
CREATE TABLE IF NOT EXISTS `shopcar_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT '商品名称',
  `description` text COMMENT '商品描述',
  `price` varchar(50) NOT NULL COMMENT '商品价格',
  `image` varchar(255) DEFAULT NULL COMMENT '商品图片路径',
  `link` varchar(255) DEFAULT NULL COMMENT '购买链接',
  `series_id` int(11) DEFAULT NULL COMMENT '商品系列ID',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示',
  `position` int(11) NOT NULL DEFAULT 0 COMMENT '显示顺序',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `series_id` (`series_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='购物车商品表';
