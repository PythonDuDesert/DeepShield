-- Dump deepshield_bdd - 2026-08-10 20:47:16
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `account_deletion_logs` (`id`,`user_id`,`first_name`,`last_name`,`email`,`role`,`reason`,`deleted_at`) VALUES ('1','5','1','2','1@gmail.com','2','Suppression manuelle via la gestion des utilisateurs.','2026-08-03 13:58:23');

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

INSERT INTO `assistance` (`id`,`user_id`,`first_name`,`last_name`,`email`,`role`,`subject`,`message`,`date_submission`,`status`,`admin_notes`,`date_response`) VALUES ('1','4','Test','TESTEUR','test@gmail.com','2','Bonjour Bonjour','TEST TEST','2026-08-10 13:11:40','fermé','TEST OK','2026-08-10 13:12:08');

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `attempt_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_time` (`attempt_time`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

INSERT INTO `login_attempts` (`id`,`ip_address`,`email`,`attempt_time`) VALUES ('1','::1','test@gmail.com','2026-08-10 13:10:54');

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;

INSERT INTO `users` (`id`,`email`,`password_hash`,`first_name`,`last_name`,`profile_photo`,`role`,`is_active`,`failed_login_attempts`,`created_at`,`updated_at`,`last_login`,`last_try_login`,`email_token`,`email_token_expires`,`reset_token`,`reset_token_expires`) VALUES ('1','olivier.yammine@gmail.com','$2y$10$eehhFsCdUDkGO68QLBrQ0ux8E17MOsJozXRzf/9jQYTN5ibYe0Neq','Olivier','YAMMINE','default-avatar.png','0','1','0','2026-07-13 12:25:28','2026-08-10 20:42:20','2026-08-10 20:42:20','2026-08-06 01:38:36',NULL,NULL,'c23da4eb36bf84b75c5e26f825de110ee71afcb02c1ca983f57f9f1be7ebaf26','2026-08-10 15:01:26');
INSERT INTO `users` (`id`,`email`,`password_hash`,`first_name`,`last_name`,`profile_photo`,`role`,`is_active`,`failed_login_attempts`,`created_at`,`updated_at`,`last_login`,`last_try_login`,`email_token`,`email_token_expires`,`reset_token`,`reset_token_expires`) VALUES ('2','omar.tanaradje@gmail.com','$2y$10$m9U9YXcQze4UWJ2CdRbWj.PkadgYkJv/1o85uG1yZDmwYY46Yqx8K','Omar','TANARADJE','default-avatar.png','0','1','0','2026-07-13 14:21:30','2026-08-10 14:38:52','2026-08-10 14:38:37',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`email`,`password_hash`,`first_name`,`last_name`,`profile_photo`,`role`,`is_active`,`failed_login_attempts`,`created_at`,`updated_at`,`last_login`,`last_try_login`,`email_token`,`email_token_expires`,`reset_token`,`reset_token_expires`) VALUES ('3','lucas.duhoo@gmail.com','$2y$10$K3qZRkKXY5X4d4lEM4dI4uZntfu/CzGEJK3gmaruE/3d8xaM2KNu.','Lucas','DUHOO','default-avatar.png','0','1','0','2026-07-13 14:21:52','2026-08-10 14:38:29','2026-08-10 14:38:09',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`email`,`password_hash`,`first_name`,`last_name`,`profile_photo`,`role`,`is_active`,`failed_login_attempts`,`created_at`,`updated_at`,`last_login`,`last_try_login`,`email_token`,`email_token_expires`,`reset_token`,`reset_token_expires`) VALUES ('4','test@gmail.com','$2y$10$/Onn.KjNuqn7bJB2WSBLH.lMqn94LDRmkJ99aLMrrev1iyWXEDgui','Test','TESTEUR','default-avatar.png','2','1','0','2026-08-03 13:44:34','2026-08-10 13:55:42','2026-08-10 13:55:42','2026-08-10 13:10:54',NULL,NULL,NULL,NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `videos` (`id`,`user_id`,`sender_email`,`video_name`,`file_size`,`uploaded_at`,`score`,`explinations`) VALUES ('1','1','olivier.yammine@gmail.com','test_video7.mp4','3879286','2026-08-10 12:33:02','92','RÉEL — score réel 91.8%, 0 frame(s) suspecte(s) sur 30 analysée(s)');
INSERT INTO `videos` (`id`,`user_id`,`sender_email`,`video_name`,`file_size`,`uploaded_at`,`score`,`explinations`) VALUES ('2','1','olivier.yammine@gmail.com','test_video11.mp4','6108742','2026-08-10 12:47:19','64','RÉEL — score réel 64.0%, 11 frame(s) suspecte(s) sur 30 analysée(s)');
INSERT INTO `videos` (`id`,`user_id`,`sender_email`,`video_name`,`file_size`,`uploaded_at`,`score`,`explinations`) VALUES ('3','1','olivier.yammine@gmail.com','test_video7.mp4','3879286','2026-08-10 13:25:05','92','RÉEL — score réel 91.8%, 0 frame(s) suspecte(s) sur 30 analysée(s)');
INSERT INTO `videos` (`id`,`user_id`,`sender_email`,`video_name`,`file_size`,`uploaded_at`,`score`,`explinations`) VALUES ('4','1','olivier.yammine@gmail.com','test_video7.mp4','3879286','2026-08-10 13:34:13','92','RÉEL — score réel 91.8%, 0 frame(s) suspecte(s) sur 30 analysée(s)');
INSERT INTO `videos` (`id`,`user_id`,`sender_email`,`video_name`,`file_size`,`uploaded_at`,`score`,`explinations`) VALUES ('5','3','lucas.duhoo@gmail.com','test_video2.mp4','7173830','2026-08-10 13:43:32','62','RÉEL — score réel 62.0%, 8 frame(s) suspecte(s) sur 30 analysée(s)');
INSERT INTO `videos` (`id`,`user_id`,`sender_email`,`video_name`,`file_size`,`uploaded_at`,`score`,`explinations`) VALUES ('6','3','lucas.duhoo@gmail.com','test_video20.mp4','4361724','2026-08-10 13:46:16','83','RÉEL — score réel 82.5%, 0 frame(s) suspecte(s) sur 30 analysée(s)');
INSERT INTO `videos` (`id`,`user_id`,`sender_email`,`video_name`,`file_size`,`uploaded_at`,`score`,`explinations`) VALUES ('7','1','olivier.yammine@gmail.com','51.mp4','285123','2026-08-10 13:54:07','8','DEEPFAKE — score réel 8.4%, 30 frame(s) suspecte(s) sur 30 analysée(s)');
INSERT INTO `videos` (`id`,`user_id`,`sender_email`,`video_name`,`file_size`,`uploaded_at`,`score`,`explinations`) VALUES ('8','4','test@gmail.com','52.mp4','315485','2026-08-10 13:56:01','10','DEEPFAKE — score réel 9.8%, 30 frame(s) suspecte(s) sur 30 analysée(s)');
INSERT INTO `videos` (`id`,`user_id`,`sender_email`,`video_name`,`file_size`,`uploaded_at`,`score`,`explinations`) VALUES ('9','1','olivier.yammine@gmail.com','52.mp4','315485','2026-08-10 14:03:02','10','DEEPFAKE — score réel 9.8%, 30 frame(s) suspecte(s) sur 30 analysée(s)');
INSERT INTO `videos` (`id`,`user_id`,`sender_email`,`video_name`,`file_size`,`uploaded_at`,`score`,`explinations`) VALUES ('10','1','olivier.yammine@gmail.com','test_video17.mp4','4433982','2026-08-10 19:02:14','68','RÉEL — score réel 68.3%, 3 frame(s) suspecte(s) sur 30 analysée(s)');

COMMIT;
SET FOREIGN_KEY_CHECKS=1;
