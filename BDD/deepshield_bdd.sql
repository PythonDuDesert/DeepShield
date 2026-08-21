-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : ven. 21 août 2026 à 13:07
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `deepshield_bdd`
--

-- --------------------------------------------------------

--
-- Structure de la table `account_deletion_logs`
--

DROP TABLE IF EXISTS `account_deletion_logs`;
CREATE TABLE IF NOT EXISTS `account_deletion_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` int DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `deleted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `account_deletion_logs`
--

INSERT INTO `account_deletion_logs` (`id`, `user_id`, `first_name`, `last_name`, `email`, `role`, `reason`, `deleted_at`) VALUES
(2, 6, 'Jean', 'Dupont', 'jean.dupont@gmail.com', 2, 'Suppression volontaire par le titulaire du compte.', '2026-08-21 15:05:45');

-- --------------------------------------------------------

--
-- Structure de la table `assistance`
--

DROP TABLE IF EXISTS `assistance`;
CREATE TABLE IF NOT EXISTS `assistance` (
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

--
-- Déchargement des données de la table `assistance`
--

INSERT INTO `assistance` (`id`, `user_id`, `first_name`, `last_name`, `email`, `role`, `subject`, `message`, `date_submission`, `status`, `admin_notes`, `date_response`) VALUES
(1, 4, 'Test', 'TESTEUR', 'test@gmail.com', 2, 'Bonjour Bonjour', 'TEST TEST', '2026-08-10 11:11:40', 'fermé', 'TEST OK', '2026-08-10 11:12:08');

-- --------------------------------------------------------

--
-- Structure de la table `audios`
--

DROP TABLE IF EXISTS `audios`;
CREATE TABLE IF NOT EXISTS `audios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `sender_email` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `audio_name` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `score` tinyint DEFAULT NULL COMMENT 'Score in %',
  `explinations` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `audios`
--

INSERT INTO `audios` (`id`, `user_id`, `sender_email`, `audio_name`, `file_size`, `uploaded_at`, `score`, `explinations`) VALUES
(1, 3, 'lucas.duhoo@gmail.com', '31.valle_.wav', 322194, '2026-08-17 16:56:13', 21, 'DEEPFAKE — score réel 20.7%'),
(2, 3, 'lucas.duhoo@gmail.com', '908_31957_000003_000001.wav', 619724, '2026-08-17 16:56:32', 62, 'RÉEL — score réel 61.6%'),
(3, 2, 'omar.tanaradje@gmail.com', '908_31957_000007_000000.wav', 748844, '2026-08-17 16:57:16', 47, 'SUSPECT — score réel 47.1%'),
(4, 2, 'omar.tanaradje@gmail.com', '61.valle_.wav', 906726, '2026-08-17 16:57:32', 31, 'DEEPFAKE — score réel 31.1%'),
(5, 1, 'olivier.yammine@gmail.com', '62.valle_.wav', 748858, '2026-08-17 16:58:08', 39, 'DEEPFAKE — score réel 38.6%'),
(6, 1, 'olivier.yammine@gmail.com', '908_31957_000007_000001.wav', 521324, '2026-08-17 16:58:23', 56, 'SUSPECT — score réel 56.3%'),
(7, 1, 'olivier.yammine@gmail.com', '908_31957_000013_000000.wav', 240524, '2026-08-17 16:58:55', 64, 'RÉEL — score réel 63.8%');

-- --------------------------------------------------------

--
-- Structure de la table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `attempt_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_time` (`attempt_time`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip_address`, `email`, `attempt_time`) VALUES
(1, '::1', 'test@gmail.com', '2026-08-10 11:10:54'),
(2, '::1', 'olivier.yammine@gmail.com', '2026-08-14 12:49:01'),
(3, '::1', 'olivier.yammine@gmail.com', '2026-08-14 17:36:03'),
(4, '::1', 'test@gmail.com', '2026-08-17 11:48:09'),
(5, '::1', 'olivier.yammine@gmail.com', '2026-08-17 15:56:32'),
(6, '::1', 'olivier.yammine@gmail.com', '2026-08-17 16:36:23'),
(7, '::1', 'lucas.duhoo@gmail.com', '2026-08-17 16:40:44');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `profile_photo` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'default-avatar.png',
  `role` tinyint UNSIGNED NOT NULL DEFAULT '2' COMMENT '0=''admin''\r\n1=''premium user''\r\n2=''user''',
  `is_active` tinyint DEFAULT '1' COMMENT '0=inactif, 1=actif, 2=bloqué (trop de tentatives)',
  `failed_login_attempts` tinyint UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Nombre de tentatives de connexion échouées',
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `profile_photo`, `role`, `is_active`, `failed_login_attempts`, `created_at`, `updated_at`, `last_login`, `last_try_login`, `email_token`, `email_token_expires`, `reset_token`, `reset_token_expires`) VALUES
(1, 'olivier.yammine@gmail.com', '$2y$10$eehhFsCdUDkGO68QLBrQ0ux8E17MOsJozXRzf/9jQYTN5ibYe0Neq', 'Olivier', 'YAMMINE', 'default-avatar.png', 0, 1, 0, '2026-07-13 10:25:28', '2026-08-21 13:05:53', '2026-08-21 13:05:53', '2026-08-17 16:36:23', NULL, NULL, 'c23da4eb36bf84b75c5e26f825de110ee71afcb02c1ca983f57f9f1be7ebaf26', '2026-08-10 15:01:26'),
(2, 'omar.tanaradje@gmail.com', '$2y$10$m9U9YXcQze4UWJ2CdRbWj.PkadgYkJv/1o85uG1yZDmwYY46Yqx8K', 'Omar', 'TANARADJE', 'default-avatar.png', 0, 1, 0, '2026-07-13 12:21:30', '2026-08-21 13:07:05', '2026-08-21 13:07:05', NULL, NULL, NULL, NULL, NULL),
(3, 'lucas.duhoo@gmail.com', '$2y$10$K3qZRkKXY5X4d4lEM4dI4uZntfu/CzGEJK3gmaruE/3d8xaM2KNu.', 'Lucas', 'DUHOO', 'default-avatar.png', 0, 1, 0, '2026-07-13 12:21:52', '2026-08-20 14:49:45', '2026-08-17 16:53:45', '2026-08-17 16:40:44', NULL, NULL, NULL, NULL),
(4, 'test@gmail.com', '$2y$10$sSqMm/rLVZBL/5sTS5NIueC8i06Oj0/hAKCMYOT9M73x/4LdgpBpq', 'Test', 'TESTEUR', 'default-avatar.png', 2, 1, 0, '2026-08-03 11:44:34', '2026-08-18 20:55:52', '2026-08-18 20:55:52', '2026-08-17 11:48:09', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `videos`
--

DROP TABLE IF EXISTS `videos`;
CREATE TABLE IF NOT EXISTS `videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `sender_email` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `video_name` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `score` tinyint DEFAULT NULL COMMENT 'Score in %',
  `explinations` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `videos`
--

INSERT INTO `videos` (`id`, `user_id`, `sender_email`, `video_name`, `file_size`, `uploaded_at`, `score`, `explinations`) VALUES
(1, 1, 'olivier.yammine@gmail.com', 'test_video1.mp4', 7247213, '2026-08-17 16:37:44', 46, 'SUSPECT — score réel 46.1%, 16 frame(s) suspecte(s) sur 30 analysée(s)'),
(2, 1, 'olivier.yammine@gmail.com', 'test_video2.mp4', 7173830, '2026-08-17 16:38:11', 62, 'RÉEL — score réel 62.0%, 8 frame(s) suspecte(s) sur 30 analysée(s)'),
(3, 1, 'olivier.yammine@gmail.com', 'test_video3.mp4', 9371449, '2026-08-17 16:38:37', 59, 'RÉEL — score réel 59.2%, 13 frame(s) suspecte(s) sur 39 analysée(s)'),
(4, 1, 'olivier.yammine@gmail.com', 'test_video4.mp4', 9696686, '2026-08-17 16:39:01', 66, 'RÉEL — score réel 66.2%, 8 frame(s) suspecte(s) sur 30 analysée(s)'),
(5, 1, 'olivier.yammine@gmail.com', 'test_video5.mp4', 7816449, '2026-08-17 16:39:37', 53, 'SUSPECT — score réel 52.7%, 10 frame(s) suspecte(s) sur 30 analysée(s)'),
(6, 3, 'lucas.duhoo@gmail.com', 'test_video6.mp4', 8535324, '2026-08-17 16:41:31', 19, 'DEEPFAKE — score réel 18.8%, 33 frame(s) suspecte(s) sur 40 analysée(s)'),
(7, 3, 'lucas.duhoo@gmail.com', 'test_video7.mp4', 3879286, '2026-08-17 16:42:08', 92, 'RÉEL — score réel 91.8%, 0 frame(s) suspecte(s) sur 30 analysée(s)'),
(8, 3, 'lucas.duhoo@gmail.com', 'test_video8.mp4', 1931241, '2026-08-17 16:42:35', 78, 'RÉEL — score réel 78.5%, 2 frame(s) suspecte(s) sur 40 analysée(s)'),
(9, 3, 'lucas.duhoo@gmail.com', 'test_video9.mp4', 5904409, '2026-08-17 16:43:01', 65, 'RÉEL — score réel 65.2%, 8 frame(s) suspecte(s) sur 30 analysée(s)'),
(10, 3, 'lucas.duhoo@gmail.com', 'test_video10.mp4', 6732438, '2026-08-17 16:43:22', 16, 'DEEPFAKE — score réel 15.9%, 29 frame(s) suspecte(s) sur 30 analysée(s)'),
(11, 2, 'omar.tanaradje@gmail.com', 'test_video11.mp4', 6108742, '2026-08-17 16:44:02', 64, 'RÉEL — score réel 64.0%, 11 frame(s) suspecte(s) sur 30 analysée(s)'),
(12, 2, 'omar.tanaradje@gmail.com', 'test_video12.mp4', 4678516, '2026-08-17 16:44:28', 69, 'RÉEL — score réel 69.0%, 5 frame(s) suspecte(s) sur 29 analysée(s)'),
(13, 2, 'omar.tanaradje@gmail.com', 'test_video13.mp4', 4051299, '2026-08-17 16:45:22', 89, 'RÉEL — score réel 89.0%, 0 frame(s) suspecte(s) sur 29 analysée(s)'),
(14, 2, 'omar.tanaradje@gmail.com', 'test_video14.mp4', 2014111, '2026-08-17 16:46:06', 35, 'DEEPFAKE — score réel 34.5%, 40 frame(s) suspecte(s) sur 60 analysée(s)'),
(15, 2, 'omar.tanaradje@gmail.com', 'test_video15.mp4', 5198976, '2026-08-17 16:46:28', 76, 'RÉEL — score réel 75.5%, 3 frame(s) suspecte(s) sur 30 analysée(s)'),
(16, 2, 'omar.tanaradje@gmail.com', 'test_video16.mp4', 2609536, '2026-08-17 16:46:57', 66, 'RÉEL — score réel 66.2%, 10 frame(s) suspecte(s) sur 30 analysée(s)'),
(17, 4, 'test@gmail.com', 'test_video17.mp4', 4433982, '2026-08-17 16:47:39', 72, 'RÉEL — score réel 71.9%, 3 frame(s) suspecte(s) sur 40 analysée(s)'),
(18, 4, 'test@gmail.com', 'test_video18.mp4', 6460225, '2026-08-17 16:48:01', 54, 'SUSPECT — score réel 54.0%, 10 frame(s) suspecte(s) sur 30 analysée(s)'),
(19, 4, 'test@gmail.com', 'test_video19.mp4', 4589358, '2026-08-17 16:48:31', 62, 'RÉEL — score réel 61.5%, 2 frame(s) suspecte(s) sur 30 analysée(s)'),
(20, 4, 'test@gmail.com', 'test_video20.mp4', 4361724, '2026-08-17 16:49:43', 83, 'RÉEL — score réel 83.2%, 0 frame(s) suspecte(s) sur 40 analysée(s)'),
(21, 1, 'olivier.yammine@gmail.com', '51.mp4', 285123, '2026-08-17 16:50:26', 8, 'DEEPFAKE — score réel 8.4%, 30 frame(s) suspecte(s) sur 30 analysée(s)'),
(22, 1, 'olivier.yammine@gmail.com', '52.mp4', 315485, '2026-08-17 16:50:50', 10, 'DEEPFAKE — score réel 9.8%, 30 frame(s) suspecte(s) sur 30 analysée(s)'),
(23, 1, 'olivier.yammine@gmail.com', '53.mp4', 430066, '2026-08-17 16:51:09', 38, 'DEEPFAKE — score réel 37.6%, 21 frame(s) suspecte(s) sur 30 analysée(s)'),
(24, 2, 'omar.tanaradje@gmail.com', '54.mp4', 1359840, '2026-08-17 16:52:38', 24, 'DEEPFAKE — score réel 24.3%, 25 frame(s) suspecte(s) sur 30 analysée(s)'),
(25, 2, 'omar.tanaradje@gmail.com', '55.mp4', 1032573, '2026-08-17 16:53:00', 13, 'DEEPFAKE — score réel 13.0%, 28 frame(s) suspecte(s) sur 30 analysée(s)'),
(26, 2, 'omar.tanaradje@gmail.com', '56.mp4', 1284170, '2026-08-17 16:53:35', 9, 'DEEPFAKE — score réel 8.8%, 28 frame(s) suspecte(s) sur 30 analysée(s)'),
(27, 3, 'lucas.duhoo@gmail.com', '57.mp4', 243721, '2026-08-17 16:54:01', 19, 'DEEPFAKE — score réel 19.4%, 26 frame(s) suspecte(s) sur 30 analysée(s)'),
(28, 3, 'lucas.duhoo@gmail.com', '58.mp4', 631435, '2026-08-17 16:54:25', 3, 'DEEPFAKE — score réel 3.0%, 30 frame(s) suspecte(s) sur 30 analysée(s)'),
(29, 3, 'lucas.duhoo@gmail.com', '59.mp4', 2053167, '2026-08-17 16:54:53', 26, 'DEEPFAKE — score réel 26.4%, 34 frame(s) suspecte(s) sur 40 analysée(s)'),
(30, 3, 'lucas.duhoo@gmail.com', '60.mp4', 884519, '2026-08-17 16:55:46', 47, 'SUSPECT — score réel 47.0%, 15 frame(s) suspecte(s) sur 30 analysée(s)');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
