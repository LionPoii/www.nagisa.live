-- 旧版迁移：若仍存在 archive_permission(VARCHAR)，可执行本脚本转为并列布尔列后删除旧列。
-- 新库请直接使用 create_admins_table.sql 中的 archive_ar_editor / archive_so_editor。

ALTER TABLE `admins`
  ADD COLUMN `archive_ar_editor` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'AI_REF NAGISA: ar-editor' AFTER `role`,
  ADD COLUMN `archive_so_editor` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'AI_REF NAGISA: so-editor' AFTER `archive_ar_editor`;

UPDATE `admins` SET `archive_ar_editor` = 1 WHERE `archive_permission` = 'ar-editor';
UPDATE `admins` SET `archive_so_editor` = 1 WHERE `archive_permission` = 'so-editor';

ALTER TABLE `admins` DROP COLUMN `archive_permission`;
