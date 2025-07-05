-- 设置 SQL 环境
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- 请在运行前确保你已连接到目标数据库
-- 例如：USE your_database_name;

-- 创建用户表
CREATE TABLE `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `verification_code` VARCHAR(255),
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `avatar_url` VARCHAR(255) NOT NULL DEFAULT 'img/default-avatar.png',
  `password_reset_token` VARCHAR(255),
  `password_reset_expires` DATETIME,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 创建弹幕表
CREATE TABLE `barrages` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `content` TEXT NOT NULL,
  `color` VARCHAR(7) NOT NULL,
  `bg_color` VARCHAR(7) NOT NULL DEFAULT '#000000',
  `display_mode` VARCHAR(10) NOT NULL,
  `speed` INT NOT NULL,
  `status` ENUM('visible', 'under_review', 'hidden') NOT NULL DEFAULT 'visible',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_barrages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 创建举报表
CREATE TABLE `barrage_reports` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `barrage_id` INT NOT NULL,
  `reporter_user_id` INT NOT NULL,
  `reported_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_barrage_report` (`barrage_id`, `reporter_user_id`),
  KEY `idx_reporter_user_id` (`reporter_user_id`),
  CONSTRAINT `fk_report_barrage` FOREIGN KEY (`barrage_id`) REFERENCES `barrages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_user` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;