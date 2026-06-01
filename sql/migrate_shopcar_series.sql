-- 商品系列迁移（在已有 shopcar_products 表上执行）

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

-- 若 MySQL 版本不支持 IF NOT EXISTS，请由 shopcarEnsureSchema() 自动添加
-- ALTER TABLE `shopcar_products` ADD COLUMN `series_id` int(11) DEFAULT NULL COMMENT '所属系列' AFTER `link`;
