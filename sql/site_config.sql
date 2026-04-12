-- 创建site_config配置表
CREATE TABLE IF NOT EXISTS `site_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入B站UID配置（如果不存在）
INSERT INTO `site_config` (`config_key`, `config_value`)
VALUES ('bilibili_uid', '')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

-- 插入B站动态用户ID配置（如果不存在）
INSERT INTO `site_config` (`config_key`, `config_value`)
VALUES ('bilibili_mid', '2124647716')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;

-- 插入B站动态功能启用状态配置（如果不存在）
INSERT INTO `site_config` (`config_key`, `config_value`)
VALUES ('bilibili_dynamic_enabled', '1')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`; 