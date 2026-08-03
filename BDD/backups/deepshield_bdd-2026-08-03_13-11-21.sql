-- Dump deepshield_bdd - 2026-08-03 13:11:21
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+01:00";

DROP TABLE IF EXISTS `account_deletion_logs`;
CREATE TABLE `account_deletion_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` int DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `deleted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `assistance`;
CREATE TABLE `assistance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` tinyint NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` longtext NOT NULL,
  `date_submission` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('nouveau','lu','resolu','fermé') DEFAULT 'nouveau',
  `admin_notes` longtext,
  `date_response` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `attempt_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `profile_photo` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'default-avatar.png',
  `role` tinyint unsigned NOT NULL DEFAULT '2' COMMENT '0=''admin''\r\n1=''premium user''\r\n2=''user''',
  `is_active` tinyint DEFAULT '1' COMMENT '0=inactif, 1=actif, 2=bloqué (trop de tentatives)',
  `failed_login_attempts` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Nombre de tentatives de connexion échouées',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `last_try_login` timestamp NULL DEFAULT NULL,
  `email_token` varchar(64) DEFAULT NULL,
  `email_token_expires` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

INSERT INTO `users` (`id`,`email`,`password_hash`,`first_name`,`last_name`,`profile_photo`,`role`,`is_active`,`failed_login_attempts`,`created_at`,`updated_at`,`last_login`,`last_try_login`,`email_token`,`email_token_expires`,`reset_token`,`reset_token_expires`) VALUES ('1','olivier.yammine@gmail.com','$2y$10$bDerZejFmniCRLFq8DGthu3UDKI69LjsteZcfgiAgwQfX3PYENv1.','Olivier','YAMMINE','default-avatar.png','0','1','0','2026-07-13 11:25:28','2026-08-03 12:59:42','2026-08-03 12:59:42',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`email`,`password_hash`,`first_name`,`last_name`,`profile_photo`,`role`,`is_active`,`failed_login_attempts`,`created_at`,`updated_at`,`last_login`,`last_try_login`,`email_token`,`email_token_expires`,`reset_token`,`reset_token_expires`) VALUES ('2','omar.tanaradje@gmail.com','$2y$10$qiZsHIr4t5a6.5Tcuv46MO7sQ2ZivfhICUs2Sl5kRQDjTKksDFyQy','Omar','TANARADJE','default-avatar.png','0','1','0','2026-07-13 13:21:30','2026-07-13 13:22:00',NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`email`,`password_hash`,`first_name`,`last_name`,`profile_photo`,`role`,`is_active`,`failed_login_attempts`,`created_at`,`updated_at`,`last_login`,`last_try_login`,`email_token`,`email_token_expires`,`reset_token`,`reset_token_expires`) VALUES ('3','lucas.duhoo@gmail.com','$2y$10$bPkFpvBy27EOi5B2osTC.enabmYQ8KXTWtqc8cyZ2iZZlBm5.rnmS','Lucas','DUHOO','default-avatar.png','0','1','0','2026-07-13 13:21:52','2026-07-13 13:22:05',NULL,NULL,NULL,NULL,NULL,NULL);

DROP TABLE IF EXISTS `videos`;
CREATE TABLE `videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `sender_email` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `video_name` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `score` tinyint DEFAULT NULL COMMENT 'Score in %',
  `explinations` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
SET FOREIGN_KEY_CHECKS=1;
