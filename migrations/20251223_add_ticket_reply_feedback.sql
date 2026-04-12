-- Migration: 添加 ticket_number, reply, reply_at 到 feedback 表
-- 请在执行此 SQL 前备份数据库

ALTER TABLE feedback
  ADD COLUMN ticket_number VARCHAR(32) NOT NULL UNIQUE AFTER id,
  ADD COLUMN reply TEXT NULL AFTER image_paths,
  ADD COLUMN reply_at DATETIME NULL AFTER reply;





