-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 01, 2026 at 01:08 AM
-- Server version: 8.0.30
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `starter_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('laravel-cache-pg_cached_tables', 'a:1:{i:0;s:26:\"powergrid_columns_in_roles\";}', 2090300216),
('laravel-cache-powergrid_columns_in_roles', 'a:6:{s:2:\"id\";s:15:\"bigint unsigned\";s:4:\"name\";s:12:\"varchar(255)\";s:10:\"guard_name\";s:12:\"varchar(255)\";s:12:\"descriptions\";s:12:\"varchar(255)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}', 1774951016),
('laravel-cache-spatie.permission.cache', 'a:3:{s:5:\"alias\";a:7:{s:1:\"a\";s:2:\"id\";s:1:\"b\";s:4:\"name\";s:1:\"c\";s:10:\"guard_name\";s:1:\"d\";s:7:\"menu_id\";s:1:\"e\";s:15:\"main_permission\";s:1:\"f\";s:12:\"descriptions\";s:1:\"r\";s:5:\"roles\";}s:11:\"permissions\";a:13:{i:0;a:7:{s:1:\"a\";i:5;s:1:\"b\";s:20:\"pengaturan-aplikasi:\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:10;s:1:\"e\";i:1;s:1:\"f\";s:25:\"akses Pengaturan Aplikasi\";s:1:\"r\";a:1:{i:0;i:1;}}i:1;a:7:{s:1:\"a\";i:6;s:1:\"b\";s:5:\"menu:\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:11;s:1:\"e\";i:1;s:1:\"f\";s:10:\"akses Menu\";s:1:\"r\";a:1:{i:0;i:1;}}i:2;a:7:{s:1:\"a\";i:7;s:1:\"b\";s:16:\"menu:tambah-menu\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:11;s:1:\"e\";i:0;s:1:\"f\";s:17:\"akses tambah menu\";s:1:\"r\";a:1:{i:0;i:1;}}i:3;a:7:{s:1:\"a\";i:8;s:1:\"b\";s:14:\"menu:edit-menu\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:11;s:1:\"e\";i:0;s:1:\"f\";s:15:\"akses edit menu\";s:1:\"r\";a:1:{i:0;i:1;}}i:4;a:7:{s:1:\"a\";i:10;s:1:\"b\";s:15:\"menu:hapus-data\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:11;s:1:\"e\";i:0;s:1:\"f\";s:16:\"akses hapus data\";s:1:\"r\";a:1:{i:0;i:1;}}i:5;a:7:{s:1:\"a\";i:11;s:1:\"b\";s:12:\"kelola-user:\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:12;s:1:\"e\";i:1;s:1:\"f\";s:17:\"akses Kelola User\";s:1:\"r\";a:1:{i:0;i:1;}}i:6;a:7:{s:1:\"a\";i:12;s:1:\"b\";s:23:\"kelola-user:tambah-user\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:12;s:1:\"e\";i:0;s:1:\"f\";s:22:\"akses menambahkan user\";s:1:\"r\";a:1:{i:0;i:1;}}i:7;a:7:{s:1:\"a\";i:13;s:1:\"b\";s:21:\"kelola-user:edit-user\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:12;s:1:\"e\";i:0;s:1:\"f\";s:15:\"akses edit user\";s:1:\"r\";a:1:{i:0;i:1;}}i:8;a:7:{s:1:\"a\";i:14;s:1:\"b\";s:22:\"kelola-user:hapus-user\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:12;s:1:\"e\";i:0;s:1:\"f\";s:16:\"akses hapus user\";s:1:\"r\";a:1:{i:0;i:1;}}i:9;a:7:{s:1:\"a\";i:15;s:1:\"b\";s:6:\"roles:\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:13;s:1:\"e\";i:1;s:1:\"f\";s:11:\"akses Roles\";s:1:\"r\";a:1:{i:0;i:1;}}i:10;a:7:{s:1:\"a\";i:16;s:1:\"b\";s:17:\"roles:tambah-role\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:13;s:1:\"e\";i:0;s:1:\"f\";s:17:\"akses tambah role\";s:1:\"r\";a:1:{i:0;i:1;}}i:11;a:7:{s:1:\"a\";i:17;s:1:\"b\";s:15:\"roles:edit-role\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:13;s:1:\"e\";i:0;s:1:\"f\";s:15:\"akses edit role\";s:1:\"r\";a:1:{i:0;i:1;}}i:12;a:7:{s:1:\"a\";i:18;s:1:\"b\";s:16:\"roles:hapus-role\";s:1:\"c\";s:3:\"web\";s:1:\"d\";i:13;s:1:\"e\";i:0;s:1:\"f\";s:16:\"akses hapus role\";s:1:\"r\";a:1:{i:0;i:1;}}}s:5:\"roles\";a:1:{i:0;a:4:{s:1:\"a\";i:1;s:1:\"b\";s:9:\"Developer\";s:1:\"c\";s:3:\"web\";s:1:\"f\";s:62:\"role untuk pengembang sistem, role ini mendapatkan semua akses\";}}}', 1775053827);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

CREATE TABLE `menus` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `is_child_menu` tinyint(1) NOT NULL DEFAULT '0',
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `position` int DEFAULT NULL,
  `descriptions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `name`, `icon`, `route`, `status`, `is_child_menu`, `parent_id`, `position`, `descriptions`, `created_at`, `updated_at`) VALUES
(10, 'Pengaturan Aplikasi', '<i class=\"ri-settings-5-line\"></i>', NULL, 1, 0, NULL, 1, 'parent menu developer setting aplikasi', '2026-03-30 21:14:59', '2026-03-30 21:14:59'),
(11, 'Menu', NULL, 'menu.index', 1, 1, 10, 2, 'kelola menu aplikasi', '2026-03-30 21:21:34', '2026-03-31 07:30:02'),
(12, 'Kelola User', NULL, 'users.index', 1, 1, 10, 1, 'kelola user dan hak akses aplikasi', '2026-03-31 07:26:07', '2026-03-31 07:30:27'),
(13, 'Roles', NULL, 'roles.index', 1, 1, 10, 3, 'kelola roles aplikasi', '2026-03-31 07:27:24', '2026-03-31 07:30:16');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2025_08_14_170933_add_two_factor_columns_to_users_table', 1),
(5, '2026_03_23_032641_create_table_menu', 2),
(6, '2026_03_23_033512_create_permission_tables', 2);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 1);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_id` bigint UNSIGNED DEFAULT NULL,
  `main_permission` tinyint(1) DEFAULT '0',
  `descriptions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `menu_id`, `main_permission`, `descriptions`, `created_at`, `updated_at`) VALUES
(5, 'pengaturan-aplikasi:', 'web', 10, 1, 'akses Pengaturan Aplikasi', '2026-03-30 21:14:59', '2026-03-30 21:14:59'),
(6, 'menu:', 'web', 11, 1, 'akses Menu', '2026-03-30 21:21:34', '2026-03-30 21:21:34'),
(7, 'menu:tambah-menu', 'web', 11, 0, 'akses tambah menu', '2026-03-30 21:21:34', '2026-03-30 21:21:34'),
(8, 'menu:edit-menu', 'web', 11, 0, 'akses edit menu', '2026-03-30 21:21:34', '2026-03-30 21:21:34'),
(10, 'menu:hapus-data', 'web', 11, 0, 'akses hapus data', '2026-03-30 21:45:39', '2026-03-30 21:45:39'),
(11, 'kelola-user:', 'web', 12, 1, 'akses Kelola User', '2026-03-31 07:26:07', '2026-03-31 07:26:07'),
(12, 'kelola-user:tambah-user', 'web', 12, 0, 'akses menambahkan user', '2026-03-31 07:26:07', '2026-03-31 07:26:07'),
(13, 'kelola-user:edit-user', 'web', 12, 0, 'akses edit user', '2026-03-31 07:26:07', '2026-03-31 07:26:07'),
(14, 'kelola-user:hapus-user', 'web', 12, 0, 'akses hapus user', '2026-03-31 07:26:07', '2026-03-31 07:26:07'),
(15, 'roles:', 'web', 13, 1, 'akses Roles', '2026-03-31 07:27:24', '2026-03-31 07:27:24'),
(16, 'roles:tambah-role', 'web', 13, 0, 'akses tambah role', '2026-03-31 07:27:24', '2026-03-31 07:27:24'),
(17, 'roles:edit-role', 'web', 13, 0, 'akses edit role', '2026-03-31 07:27:24', '2026-03-31 07:27:24'),
(18, 'roles:hapus-role', 'web', 13, 0, 'akses hapus role', '2026-03-31 07:27:24', '2026-03-31 07:27:24');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descriptions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `descriptions`, `created_at`, `updated_at`) VALUES
(1, 'Developer', 'web', 'role untuk pengembang sistem, role ini mendapatkan semua akses', '2026-03-31 01:43:44', '2026-03-31 01:56:00');

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(5, 1),
(6, 1),
(7, 1),
(8, 1),
(10, 1),
(11, 1),
(12, 1),
(13, 1),
(14, 1),
(15, 1),
(16, 1),
(17, 1),
(18, 1);

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('iBlxhGBt0qK8pbYd4P6VrZYG1eWSB95eh8Czp7y4', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:149.0) Gecko/20100101 Firefox/149.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiVGE4WUtmaHNqSEVpWFhWamVLZjB6SktpdFE3MUxvOFFuUkVKaGN5VCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzk6Imh0dHA6Ly92ZWx6b24tbGFyYXZlbC0xMi50ZXN0L2Rhc2hib2FyZCI7czo1OiJyb3V0ZSI7czo5OiJkYXNoYm9hcmQiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO30=', 1774973501);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Anam', 'khoirul.anam@unja.ac.id', NULL, '$2y$12$.Gbia6lr/TlXygbsx671/.MkrR3vyCGZPxAYzH29GB61FPDZON9ce', NULL, NULL, NULL, NULL, '2026-03-19 06:40:38', '2026-03-19 06:40:38'),
(2, 'Khoirul', 'khoirul.anam7@unja.ac.id', NULL, '$2y$12$sttNqf5DWs0t9TatlgBSq.UHWH5qZwR7Zt5yoCANWVKr6cFngubb.', NULL, NULL, NULL, NULL, '2026-03-20 01:28:48', '2026-03-20 01:28:48'),
(3, 'anam', 'anam@gmail.com', NULL, '$2y$12$gjtvtzCXq7a4Z2efInaqmuj1fV/XRZoS0251em83idhLTBso8/oTu', NULL, NULL, NULL, NULL, '2026-03-31 06:17:04', '2026-03-31 06:17:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `menus_parent_id_foreign` (`parent_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`),
  ADD KEY `permissions_menu_id_foreign` (`menu_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `menus`
--
ALTER TABLE `menus`
  ADD CONSTRAINT `menus_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_menu_id_foreign` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
