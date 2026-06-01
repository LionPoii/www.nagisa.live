-- 商品系列表（parent_id=NULL 为顶级系列）
CREATE TABLE IF NOT EXISTS `shopcar_series` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL COMMENT '上级商品系列ID，NULL为顶级',
  `title` varchar(255) NOT NULL,
  `description` text,
  `position` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `active_position` (`active`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品系列';

-- 购物车商品表
CREATE TABLE IF NOT EXISTS `shopcar_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `price` varchar(50) NOT NULL DEFAULT '',
  `image` varchar(255) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `series_id` int(11) DEFAULT NULL COMMENT '商品系列ID',
  `active` tinyint(1) DEFAULT '1',
  `position` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `series_id` (`series_id`),
  KEY `active_position` (`active`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
