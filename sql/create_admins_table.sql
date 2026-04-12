-- 检查并创建管理员表
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
  `archive_ar_editor` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'AI_REF NAGISA: ar-editor archive.nagisa.live',
  `archive_so_editor` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'AI_REF NAGISA: so-editor reserved site',
  `created_at` datetime NOT NULL,
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 如果没有 admin 用户，则创建默认主管理员（密码：admin123；扩展站点权限全开）
INSERT INTO `admins` (`username`, `password_hash`, `role`, `archive_ar_editor`, `archive_so_editor`, `created_at`, `last_login`)
SELECT 'admin', '$2y$10$dO.r5gfRzZXCfM0T8QIj5OJuPJ8V5L5bvWMnl7O6E4FtPjD0QCrRe', 'admin', 1, 1, NOW(), NOW()
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `admins` WHERE `username` = 'admin'); 