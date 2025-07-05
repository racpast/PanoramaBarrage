SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 运行前请确保已经选择了目标数据库
-- 例如：USE your_database_name;

CREATE TABLE `barrages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `content` text NOT NULL,
  `color` varchar(7) NOT NULL,
  `bg_color` varchar(7) NOT NULL DEFAULT '#000000',
  `mode` varchar(10) NOT NULL,
  `speed` int NOT NULL,
  `status` enum('visible','under_review','hidden') NOT NULL DEFAULT 'visible',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `barrages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `barrage_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `barrage_id` int NOT NULL,
  `reporter_user_id` int NOT NULL,
  `report_timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_report` (`barrage_id`,`reporter_user_id`),
  KEY `reporter_user_id` (`reporter_user_id`),
  CONSTRAINT `barrage_reports_ibfk_1` FOREIGN KEY (`barrage_id`) REFERENCES `barrages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `barrage_reports_ibfk_2` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `verification_code` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `avatar_url` varchar(255) DEFAULT 'img/default-avatar.png',
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
