-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 10 Okt 2026 pada 04.34
-- Versi server: 8.4.3
-- Versi PHP: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `newapp`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `assets`
--

CREATE TABLE `assets` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unit',
  `source_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'AVAILABLE',
  `condition` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GOOD',
  `purchase_year` smallint UNSIGNED DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `request_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `request_letter_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_description` text COLLATE utf8mb4_unicode_ci,
  `requested_by` bigint UNSIGNED DEFAULT NULL,
  `request_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint UNSIGNED NOT NULL,
  `procurement_request_item_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `assets`
--

INSERT INTO `assets` (`id`, `code`, `name`, `category`, `brand`, `model`, `unit`, `source_type`, `status`, `condition`, `purchase_year`, `description`, `request_number`, `request_date`, `request_letter_file`, `request_description`, `requested_by`, `request_status`, `created_by`, `procurement_request_item_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'AST-0001', 'Laptop Dell Latitude 5450', 'IT Equipment', 'Dell', 'Latitude 5450', 'Unit', 'PURCHASE', 'INACTIVE', 'GOOD', NULL, 'Laptop engineering untuk site dan office.', 'SPP-2026-001', '2026-06-26', NULL, 'Permohonan pembelian laptop untuk tim engineering.', 1, 'PURCHASED', 1, NULL, '2026-09-25 21:51:43', '2026-10-04 22:41:03', NULL),
(2, 'AST-0002', 'Printer Laser', 'Office Equipment', 'HP', 'LaserJet Pro', 'Unit', 'EXISTING', 'AVAILABLE', 'GOOD', NULL, 'Printer dokumen project.', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, '2026-09-25 21:51:43', '2026-09-25 21:51:43', NULL),
(3, 'AST-0003', 'Portable Generator', 'Machinery', 'Honda', 'EU70is', 'Unit', 'PURCHASE', 'IN_USE', 'GOOD', NULL, 'Generator portable untuk kebutuhan site.', 'SPP-2026-003', '2026-09-19', NULL, 'Permohonan pembelian generator untuk site.', 1, 'PURCHASED', 1, NULL, '2026-09-25 21:51:43', '2026-10-04 22:19:23', NULL),
(4, 'AST-0004', 'Network Switch', 'IT Equipment', 'Cisco', 'Catalyst 2960', 'Unit', 'EXISTING', 'IN_USE', 'GOOD', NULL, 'Switch jaringan untuk temporary site network.', NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, '2026-09-25 21:51:43', '2026-10-04 19:17:37', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `boqs`
--

CREATE TABLE `boqs` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revision_number` int UNSIGNED NOT NULL DEFAULT '1',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `submitted_by` bigint UNSIGNED DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `boqs`
--

INSERT INTO `boqs` (`id`, `project_id`, `code`, `revision_number`, `status`, `submitted_by`, `submitted_at`, `reviewed_by`, `reviewed_at`, `review_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'BOQ-PRJ001', 1, 'DRAFT', NULL, NULL, NULL, NULL, NULL, '2026-09-25 21:51:41', '2026-09-25 21:51:41'),
(2, 2, 'BOQ-PRJ002', 1, 'APPROVED', 1, '2026-09-24 21:51:42', 1, '2026-09-26 02:26:40', 'Confirmed', '2026-09-25 21:51:42', '2026-09-26 02:26:40'),
(3, 3, 'BOQ-PRJ003', 1, 'REVISION_REQUIRED', 1, '2026-09-21 21:51:42', 1, '2026-09-23 21:51:42', 'Harga item cabling perlu disesuaikan.', '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(4, 4, 'BOQ-PRJ004', 1, 'APPROVED', 1, '2026-09-15 21:51:42', 1, '2026-09-17 21:51:42', 'Disetujui untuk eksekusi.', '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(5, 5, 'BOQ-PRJ005', 1, 'APPROVED', 1, '2026-08-26 21:51:43', 1, '2026-08-28 21:51:43', 'Approved for site execution.', '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(6, 8, 'BOQ-Test 002', 1, 'APPROVED', 1, '2026-10-04 22:12:41', 1, '2026-10-04 22:15:28', 'Confirmed', '2026-10-04 22:12:29', '2026-10-04 22:15:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `boqs_cogs`
--

CREATE TABLE `boqs_cogs` (
  `id` bigint UNSIGNED NOT NULL,
  `boq_id` bigint UNSIGNED NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `boqs_cogs`
--

INSERT INTO `boqs_cogs` (`id`, `boq_id`, `code`, `name`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'COGS-001', 'Engineering', 'Design and engineering works', 1, '2026-09-25 21:51:41', '2026-09-25 21:51:41'),
(2, 1, 'COGS-002', 'Procurement', 'Material and equipment supply', 2, '2026-09-25 21:51:41', '2026-09-25 21:51:41'),
(3, 1, 'COGS-003', 'Installation', 'On-site installation works', 3, '2026-09-25 21:51:41', '2026-09-25 21:51:41'),
(4, 1, 'COGS-004', 'Testing', 'Testing and commissioning', 4, '2026-09-25 21:51:41', '2026-09-25 21:51:41'),
(5, 2, 'COGS-DC-001', 'Engineering', 'Design and engineering works', 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(6, 2, 'COGS-DC-002', 'Procurement', 'Material and equipment supply', 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(7, 2, 'COGS-DC-003', 'Installation', 'On-site installation works', 3, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(8, 2, 'COGS-DC-004', 'Testing', 'Testing and commissioning', 4, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(9, 3, 'COGS-RT-001', 'Engineering', 'Design and engineering works', 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(10, 3, 'COGS-RT-002', 'Procurement', 'Material and equipment supply', 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(11, 3, 'COGS-RT-003', 'Installation', 'On-site installation works', 3, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(12, 3, 'COGS-RT-004', 'Testing', 'Testing and commissioning', 4, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(13, 4, 'COGS-UT-001', 'Engineering', 'Design and engineering works', 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(14, 4, 'COGS-UT-002', 'Procurement', 'Material and equipment supply', 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(15, 4, 'COGS-UT-003', 'Installation', 'On-site installation works', 3, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(16, 4, 'COGS-UT-004', 'Testing', 'Testing and commissioning', 4, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(17, 5, 'COGS-LV-001', 'Engineering', 'Design and engineering works', 1, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(18, 5, 'COGS-LV-002', 'Procurement', 'Material and equipment supply', 2, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(19, 5, 'COGS-LV-003', 'Installation', 'On-site installation works', 3, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(20, 5, 'COGS-LV-004', 'Testing', 'Testing and commissioning', 4, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(21, 6, 'COGS-001', 'Engineering', 'Design works', 1, '2026-10-04 22:12:29', '2026-10-04 22:12:29'),
(22, 6, 'COGS-002', 'Installation', 'Site works', 2, '2026-10-04 22:12:29', '2026-10-04 22:12:29');

-- --------------------------------------------------------

--
-- Struktur dari tabel `boq_items`
--

CREATE TABLE `boq_items` (
  `id` bigint UNSIGNED NOT NULL,
  `boq_cogs_id` bigint UNSIGNED NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `quantity` decimal(18,4) NOT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `material_unit_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `service_unit_price` decimal(18,2) NOT NULL DEFAULT '0.00',
  `unit_price` decimal(18,2) NOT NULL,
  `total_price` decimal(18,2) NOT NULL,
  `progress_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `boq_items`
--

INSERT INTO `boq_items` (`id`, `boq_cogs_id`, `code`, `name`, `description`, `quantity`, `unit`, `material_unit_price`, `service_unit_price`, `unit_price`, `total_price`, `progress_percentage`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'ITEM-STD-E01', 'Design Drawing', NULL, 1.0000, 'lot', 12000000.00, 0.00, 12000000.00, 12000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(2, 1, 'ITEM-STD-E02', 'Structural Calculation', NULL, 1.0000, 'lot', 8000000.00, 0.00, 8000000.00, 8000000.00, 0.00, 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(3, 1, 'ITEM-STD-E03', 'Shop Drawing', NULL, 1.0000, 'lot', 10000000.00, 0.00, 10000000.00, 10000000.00, 0.00, 3, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(4, 2, 'ITEM-STD-P01', 'Cable Tray Supply', NULL, 200.0000, 'm', 85000.00, 0.00, 85000.00, 17000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(5, 2, 'ITEM-STD-P02', 'Control Panel Supply', NULL, 4.0000, 'set', 7500000.00, 0.00, 7500000.00, 30000000.00, 0.00, 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(6, 3, 'ITEM-STD-I01', 'Cable Tray Installation', NULL, 200.0000, 'm', 45000.00, 0.00, 45000.00, 9000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(7, 3, 'ITEM-STD-I02', 'Panel Installation', NULL, 4.0000, 'set', 2500000.00, 0.00, 2500000.00, 10000000.00, 0.00, 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(8, 4, 'ITEM-STD-T01', 'Testing & Commissioning', NULL, 1.0000, 'lot', 15000000.00, 0.00, 15000000.00, 15000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(9, 5, 'ITEM-DC-E01', 'Design Drawing', NULL, 1.0000, 'lot', 12000000.00, 0.00, 12000000.00, 12000000.00, 100.00, 1, '2026-09-25 21:51:42', '2026-09-26 02:28:34'),
(10, 5, 'ITEM-DC-E02', 'Structural Calculation', NULL, 1.0000, 'lot', 8000000.00, 0.00, 8000000.00, 8000000.00, 100.00, 2, '2026-09-25 21:51:42', '2026-09-26 02:28:52'),
(11, 5, 'ITEM-DC-E03', 'Shop Drawing', NULL, 1.0000, 'lot', 10000000.00, 0.00, 10000000.00, 10000000.00, 0.00, 3, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(12, 6, 'ITEM-DC-P01', 'Cable Tray Supply', NULL, 200.0000, 'm', 85000.00, 0.00, 85000.00, 17000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(13, 6, 'ITEM-DC-P02', 'Control Panel Supply', NULL, 4.0000, 'set', 7500000.00, 0.00, 7500000.00, 30000000.00, 0.00, 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(14, 7, 'ITEM-DC-I01', 'Cable Tray Installation', NULL, 200.0000, 'm', 45000.00, 0.00, 45000.00, 9000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(15, 7, 'ITEM-DC-I02', 'Panel Installation', NULL, 4.0000, 'set', 2500000.00, 0.00, 2500000.00, 10000000.00, 0.00, 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(16, 8, 'ITEM-DC-T01', 'Testing & Commissioning', NULL, 1.0000, 'lot', 15000000.00, 0.00, 15000000.00, 15000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(17, 9, 'ITEM-RT-E01', 'Design Drawing', NULL, 1.0000, 'lot', 12000000.00, 0.00, 12000000.00, 12000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(18, 9, 'ITEM-RT-E02', 'Structural Calculation', NULL, 1.0000, 'lot', 8000000.00, 0.00, 8000000.00, 8000000.00, 0.00, 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(19, 9, 'ITEM-RT-E03', 'Shop Drawing', NULL, 1.0000, 'lot', 10000000.00, 0.00, 10000000.00, 10000000.00, 0.00, 3, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(20, 10, 'ITEM-RT-P01', 'Cable Tray Supply', NULL, 200.0000, 'm', 85000.00, 0.00, 85000.00, 17000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(21, 10, 'ITEM-RT-P02', 'Control Panel Supply', NULL, 4.0000, 'set', 7500000.00, 0.00, 7500000.00, 30000000.00, 0.00, 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(22, 11, 'ITEM-RT-I01', 'Cable Tray Installation', NULL, 200.0000, 'm', 45000.00, 0.00, 45000.00, 9000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(23, 11, 'ITEM-RT-I02', 'Panel Installation', NULL, 4.0000, 'set', 2500000.00, 0.00, 2500000.00, 10000000.00, 0.00, 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(24, 12, 'ITEM-RT-T01', 'Testing & Commissioning', NULL, 1.0000, 'lot', 15000000.00, 0.00, 15000000.00, 15000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(25, 13, 'ITEM-UT-E01', 'Design Drawing', NULL, 1.0000, 'lot', 12000000.00, 0.00, 12000000.00, 12000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(26, 13, 'ITEM-UT-E02', 'Structural Calculation', NULL, 1.0000, 'lot', 8000000.00, 0.00, 8000000.00, 8000000.00, 0.00, 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(27, 13, 'ITEM-UT-E03', 'Shop Drawing', NULL, 1.0000, 'lot', 10000000.00, 0.00, 10000000.00, 10000000.00, 0.00, 3, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(28, 14, 'ITEM-UT-P01', 'Cable Tray Supply', NULL, 200.0000, 'm', 85000.00, 0.00, 85000.00, 17000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(29, 14, 'ITEM-UT-P02', 'Control Panel Supply', NULL, 4.0000, 'set', 7500000.00, 0.00, 7500000.00, 30000000.00, 0.00, 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(30, 15, 'ITEM-UT-I01', 'Cable Tray Installation', NULL, 200.0000, 'm', 45000.00, 0.00, 45000.00, 9000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(31, 15, 'ITEM-UT-I02', 'Panel Installation', NULL, 4.0000, 'set', 2500000.00, 0.00, 2500000.00, 10000000.00, 0.00, 2, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(32, 16, 'ITEM-UT-T01', 'Testing & Commissioning', NULL, 1.0000, 'lot', 15000000.00, 0.00, 15000000.00, 15000000.00, 0.00, 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42'),
(33, 17, 'ITEM-LV-E01', 'Design Drawing', NULL, 1.0000, 'lot', 12000000.00, 0.00, 12000000.00, 12000000.00, 100.00, 1, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(34, 17, 'ITEM-LV-E02', 'Structural Calculation', NULL, 1.0000, 'lot', 8000000.00, 0.00, 8000000.00, 8000000.00, 75.00, 2, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(35, 17, 'ITEM-LV-E03', 'Shop Drawing', NULL, 1.0000, 'lot', 10000000.00, 0.00, 10000000.00, 10000000.00, 40.00, 3, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(36, 18, 'ITEM-LV-P01', 'Cable Tray Supply', NULL, 200.0000, 'm', 85000.00, 0.00, 85000.00, 17000000.00, 80.00, 1, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(37, 18, 'ITEM-LV-P02', 'Control Panel Supply', NULL, 4.0000, 'set', 7500000.00, 0.00, 7500000.00, 30000000.00, 100.00, 2, '2026-09-25 21:51:43', '2026-10-06 22:49:46'),
(38, 19, 'ITEM-LV-I01', 'Cable Tray Installation', NULL, 200.0000, 'm', 45000.00, 0.00, 45000.00, 9000000.00, 35.00, 1, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(39, 19, 'ITEM-LV-I02', 'Panel Installation', NULL, 4.0000, 'set', 2500000.00, 0.00, 2500000.00, 10000000.00, 20.00, 2, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(40, 20, 'ITEM-LV-T01', 'Testing & Commissioning', NULL, 1.0000, 'lot', 15000000.00, 0.00, 15000000.00, 15000000.00, 10.00, 1, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(41, 21, 'ITEM-E01', 'Design Drawing', NULL, 1.0000, 'ls', 2000000.00, 0.00, 2000000.00, 2000000.00, 100.00, 1, '2026-10-04 22:12:29', '2026-10-04 22:18:03'),
(42, 21, 'ITEM-E02', 'Structural Calculation', NULL, 1.0000, 'ls', 3000000.00, 0.00, 3000000.00, 3000000.00, 100.00, 2, '2026-10-04 22:12:29', '2026-10-04 22:18:27'),
(43, 22, 'ITEM-I01', 'Cable Tray', NULL, 10.0000, 'm', 100000.00, 0.00, 100000.00, 1000000.00, 0.00, 1, '2026-10-04 22:12:29', '2026-10-04 22:12:29');

-- --------------------------------------------------------

--
-- Struktur dari tabel `boq_revisions`
--

CREATE TABLE `boq_revisions` (
  `id` bigint UNSIGNED NOT NULL,
  `boq_id` bigint UNSIGNED NOT NULL,
  `revision_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revision_number` int UNSIGNED NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OPEN',
  `created_by` bigint UNSIGNED NOT NULL,
  `reviewed_by` bigint UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `boq_revisions`
--

INSERT INTO `boq_revisions` (`id`, `boq_id`, `revision_code`, `revision_number`, `reason`, `status`, `created_by`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(1, 3, 'BOQ-PRJ003-REV01', 1, 'Harga perlu disesuaikan.', 'OPEN', 1, NULL, NULL, '2026-09-25 21:51:42', '2026-09-25 21:51:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `boq_revision_items`
--

CREATE TABLE `boq_revision_items` (
  `id` bigint UNSIGNED NOT NULL,
  `boq_revision_id` bigint UNSIGNED NOT NULL,
  `boq_item_id` bigint UNSIGNED NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `boq_revision_items`
--

INSERT INTO `boq_revision_items` (`id`, `boq_revision_id`, `boq_item_id`, `reason`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 17, 'Sesuaikan harga sesuai quotation vendor terbaru.', 'PENDING', '2026-09-25 21:51:42', '2026-09-25 21:51:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `client_billings`
--

CREATE TABLE `client_billings` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `project_term_id` bigint UNSIGNED NOT NULL,
  `billing_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_date` date NOT NULL,
  `billing_amount` decimal(18,2) NOT NULL,
  `invoice_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `created_by` bigint UNSIGNED NOT NULL,
  `submitted_by` bigint UNSIGNED DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `sent_by` bigint UNSIGNED DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `cancelled_by` bigint UNSIGNED DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `client_billings`
--

INSERT INTO `client_billings` (`id`, `project_id`, `project_term_id`, `billing_number`, `billing_date`, `billing_amount`, `invoice_number`, `invoice_date`, `due_date`, `description`, `attachment`, `status`, `created_by`, `submitted_by`, `submitted_at`, `sent_by`, `sent_at`, `cancelled_by`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES
(1, 5, 1, 'BIL-0001', '2026-08-17', 620000000.00, 'INV/SBA/2026/001', '2026-08-17', '2026-09-16', 'Penagihan Termin 1 — Uang Muka.', NULL, 'SENT', 1, 1, '2026-08-17 00:47:15', 1, '2026-08-17 00:47:15', NULL, NULL, NULL, '2026-10-06 00:47:15', '2026-10-06 00:47:15'),
(2, 5, 2, 'BIL-0002', '2026-09-26', 465000000.00, 'INV/SBA/2026/002', '2026-09-26', '2026-10-26', 'Penagihan Termin 2 — Progress 50%.', NULL, 'SENT', 1, 1, '2026-09-26 00:47:15', 1, '2026-09-26 00:47:15', NULL, NULL, NULL, '2026-10-06 00:47:15', '2026-10-06 00:47:15'),
(3, 5, 2, 'BIL-0003', '2026-10-06', 465000000.00, NULL, NULL, NULL, 'Sisa termin progress 50%.', NULL, 'DRAFT', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-06 00:47:15', '2026-10-06 00:47:15'),
(4, 5, 3, 'BIL-0004', '2026-10-07', 930000000.00, 'INV-112-112-oop', '2026-10-07', NULL, NULL, NULL, 'SENT', 1, 1, '2026-10-06 22:44:11', 1, '2026-10-06 22:44:28', NULL, NULL, NULL, '2026-10-06 22:43:51', '2026-10-06 22:44:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `client_payments`
--

CREATE TABLE `client_payments` (
  `id` bigint UNSIGNED NOT NULL,
  `client_billing_id` bigint UNSIGNED NOT NULL,
  `payment_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `bank_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proof_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RECEIVED',
  `created_by` bigint UNSIGNED NOT NULL,
  `cancelled_by` bigint UNSIGNED DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `client_payments`
--

INSERT INTO `client_payments` (`id`, `client_billing_id`, `payment_number`, `payment_date`, `amount`, `bank_reference`, `proof_file`, `notes`, `status`, `created_by`, `cancelled_by`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES
(1, 1, 'RCV-0001', '2026-08-27', 620000000.00, 'TRF-BCA-0001', NULL, NULL, 'RECEIVED', 1, NULL, NULL, NULL, '2026-10-06 00:47:15', '2026-10-06 00:47:15'),
(2, 2, 'RCV-0002', '2026-10-03', 300000000.00, 'TRF-BCA-0002', NULL, NULL, 'RECEIVED', 1, NULL, NULL, NULL, '2026-10-06 00:47:15', '2026-10-06 00:47:15'),
(3, 2, 'RCV-0003', '2026-10-06', 165000000.00, NULL, NULL, NULL, 'RECEIVED', 1, NULL, NULL, NULL, '2026-10-06 04:23:12', '2026-10-06 04:23:12'),
(4, 4, 'RCV-0004', '2026-10-07', 930000000.00, NULL, NULL, NULL, 'RECEIVED', 1, NULL, NULL, NULL, '2026-10-06 22:44:53', '2026-10-06 22:44:53');

-- --------------------------------------------------------

--
-- Struktur dari tabel `document_types`
--

CREATE TABLE `document_types` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` smallint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `document_types`
--

INSERT INTO `document_types` (`id`, `code`, `name`, `reference_type`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'DOC-BILLING', 'Dokumen Penagihan', 'BILLING', 'Dokumen penagihan termin ke client.', 1, 1, '2026-10-06 02:00:19', '2026-10-06 02:00:19'),
(2, 'DOC-CONTRACT', 'Kontrak', NULL, 'Dokumen kontrak dan addendum.', 0, 2, '2026-10-06 02:00:19', '2026-10-06 02:00:19'),
(3, 'DOC-BA', 'Berita Acara', NULL, 'Berita acara progress, serah terima, dan sejenisnya.', 0, 3, '2026-10-06 02:00:19', '2026-10-06 02:00:19'),
(4, 'DOC-GENERAL', 'Dokumen Umum', NULL, 'Dokumen administrasi project lainnya.', 0, 4, '2026-10-06 02:00:19', '2026-10-06 02:00:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `job_batches`
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
-- Struktur dari tabel `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_09_23_135219_add_role_to_users_table', 1),
(5, '2026_09_23_135220_create_projects_table', 1),
(6, '2026_09_23_135221_create_boqs_table', 1),
(7, '2026_09_23_135222_create_boqs_cogs_table', 1),
(8, '2026_09_23_135223_create_boq_items_table', 1),
(9, '2026_09_23_135224_create_boq_revisions_table', 1),
(10, '2026_09_23_135225_create_boq_revision_items_table', 1),
(11, '2026_09_23_135226_create_payment_plans_table', 1),
(12, '2026_09_23_135227_create_payments_table', 1),
(13, '2026_09_24_010614_create_payment_plan_items_table', 1),
(14, '2026_09_25_063023_add_category_to_boq_items_table', 1),
(15, '2026_09_25_153206_create_assets_table', 1),
(16, '2026_09_25_153207_create_project_assets_table', 1),
(17, '2026_09_26_115101_add_condition_and_purchase_year_to_assets_table', 2),
(18, '2026_10_03_074043_add_contract_number_to_projects_table', 3),
(19, '2026_10_05_032051_add_contract_value_to_projects_table', 4),
(20, '2026_10_06_023121_create_master_bmhps_table', 5),
(21, '2026_10_06_023122_create_bmhp_purchases_table', 5),
(22, '2026_10_06_023124_create_bmhp_purchase_details_table', 5),
(23, '2026_10_06_023125_create_bmhp_allocations_table', 5),
(24, '2026_10_06_030034_convert_payments_to_actual_cost', 6),
(25, '2026_10_06_053323_create_project_terms_table', 7),
(26, '2026_10_06_053325_create_client_billings_table', 7),
(27, '2026_10_06_053326_create_client_payments_table', 7),
(28, '2026_10_06_095456_create_document_types_table', 8),
(29, '2026_10_06_095458_create_project_documents_table', 8),
(30, '2026_10_06_095500_create_project_document_versions_table', 9),
(31, '2026_10_06_095502_create_project_document_status_histories_table', 9),
(32, '2026_10_09_104417_add_material_and_service_prices_to_boq_items_table', 10),
(33, '2026_10_09_110008_drop_category_from_boq_items_table', 11),
(34, '2026_10_09_115152_replace_bmhp_purchases_with_procurement_requests', 12),
(35, '2026_10_10_035713_add_trigger_progress_to_project_terms_table', 13);

-- --------------------------------------------------------

--
-- Struktur dari tabel `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `payments`
--

CREATE TABLE `payments` (
  `id` bigint UNSIGNED NOT NULL,
  `payment_plan_id` bigint UNSIGNED NOT NULL,
  `payment_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `payment_method` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `overrun_reason` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PAID',
  `paid_by` bigint UNSIGNED NOT NULL,
  `voided_by` bigint UNSIGNED DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `void_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `payments`
--

INSERT INTO `payments` (`id`, `payment_plan_id`, `payment_code`, `payment_date`, `amount`, `payment_method`, `reference_number`, `notes`, `overrun_reason`, `status`, `paid_by`, `voided_by`, `voided_at`, `void_reason`, `created_at`, `updated_at`) VALUES
(1, 1, 'PAY-PRJ004-001', '2026-09-22', 40000000.00, 'bank_transfer', 'TRX-UT-44112', 'Pembayaran diterima', NULL, 'PAID', 1, NULL, NULL, NULL, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(2, 3, 'PAY-PRJ005-001', '2026-09-07', 57000000.00, 'bank_transfer', 'TRX-LV-99001', 'Pembayaran diterima', NULL, 'PAID', 1, NULL, NULL, NULL, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(3, 4, 'PAY-001', '2026-09-26', 30000000.00, 'bank_transfer', NULL, NULL, NULL, 'PAID', 1, NULL, NULL, NULL, '2026-09-26 02:28:12', '2026-09-26 02:28:12'),
(4, 5, 'PAY-002', '2026-10-05', 4000000.00, 'bank_transfer', NULL, NULL, NULL, 'PAID', 1, NULL, NULL, NULL, '2026-10-04 22:17:32', '2026-10-04 22:17:32'),
(5, 6, 'PAY-0034', '2026-10-06', 8500000.00, 'bank_transfer', NULL, NULL, NULL, 'PAID', 1, NULL, NULL, NULL, '2026-10-06 06:04:53', '2026-10-06 06:04:53');

-- --------------------------------------------------------

--
-- Struktur dari tabel `payment_allocations`
--

CREATE TABLE `payment_allocations` (
  `id` bigint UNSIGNED NOT NULL,
  `payment_id` bigint UNSIGNED NOT NULL,
  `boq_item_id` bigint UNSIGNED NOT NULL,
  `allocated_amount` decimal(18,2) NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `payment_allocations`
--

INSERT INTO `payment_allocations` (`id`, `payment_id`, `boq_item_id`, `allocated_amount`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 25, 12000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(2, 1, 26, 8000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(3, 1, 27, 10000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(4, 1, 31, 10000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(5, 2, 33, 12000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(6, 2, 34, 8000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(7, 2, 35, 10000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(8, 2, 36, 17000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(9, 2, 39, 10000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(10, 3, 9, 12000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(11, 3, 10, 8000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(12, 3, 11, 10000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(13, 4, 42, 3000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(14, 4, 43, 1000000.00, NULL, '2026-10-05 19:06:29', '2026-10-05 19:06:29'),
(15, 5, 38, 8500000.00, NULL, '2026-10-06 06:04:53', '2026-10-06 06:04:53');

-- --------------------------------------------------------

--
-- Struktur dari tabel `payment_plans`
--

CREATE TABLE `payment_plans` (
  `id` bigint UNSIGNED NOT NULL,
  `boq_id` bigint UNSIGNED NOT NULL,
  `reference_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLANNED',
  `planned_date` date DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLANNED',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `payment_plans`
--

INSERT INTO `payment_plans` (`id`, `boq_id`, `reference_code`, `type`, `planned_date`, `amount`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 4, 'PP-PRJ004-001', 'READY_TO_PAY', '2026-09-21', 40000000.00, 'PAID', 'Down payment', 1, '2026-09-25 21:51:42', '2026-09-25 21:51:43'),
(2, 4, 'PP-PRJ004-002', 'READY_TO_PAY', '2026-10-03', 24000000.00, 'READY_TO_PAY', 'Siap diproses', 1, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(3, 5, 'PP-PRJ005-001', 'READY_TO_PAY', '2026-09-06', 57000000.00, 'PAID', 'Initial payment', 1, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(4, 2, '001', 'READY_TO_PAY', '2026-12-12', 30000000.00, 'PAID', NULL, 1, '2026-09-26 02:27:54', '2026-09-26 02:28:12'),
(5, 6, '002', 'READY_TO_PAY', NULL, 4000000.00, 'PAID', NULL, 1, '2026-10-04 22:16:44', '2026-10-04 22:17:32'),
(6, 5, '0034', 'READY_TO_PAY', '2026-10-07', 8500000.00, 'PAID', NULL, 1, '2026-10-06 06:04:24', '2026-10-06 06:04:53');

-- --------------------------------------------------------

--
-- Struktur dari tabel `payment_plan_items`
--

CREATE TABLE `payment_plan_items` (
  `id` bigint UNSIGNED NOT NULL,
  `payment_plan_id` bigint UNSIGNED NOT NULL,
  `boq_item_id` bigint UNSIGNED NOT NULL,
  `planned_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `payment_plan_items`
--

INSERT INTO `payment_plan_items` (`id`, `payment_plan_id`, `boq_item_id`, `planned_amount`, `created_at`, `updated_at`) VALUES
(1, 1, 25, 12000000.00, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(2, 1, 26, 8000000.00, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(3, 1, 27, 10000000.00, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(4, 1, 31, 10000000.00, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(5, 2, 30, 9000000.00, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(6, 2, 32, 15000000.00, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(7, 3, 33, 12000000.00, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(8, 3, 34, 8000000.00, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(9, 3, 35, 10000000.00, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(10, 3, 36, 17000000.00, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(11, 3, 39, 10000000.00, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(12, 4, 9, 12000000.00, '2026-09-26 02:27:54', '2026-09-26 02:27:54'),
(13, 4, 10, 8000000.00, '2026-09-26 02:27:54', '2026-09-26 02:27:54'),
(14, 4, 11, 10000000.00, '2026-09-26 02:27:54', '2026-09-26 02:27:54'),
(15, 5, 42, 3000000.00, '2026-10-04 22:16:44', '2026-10-04 22:16:44'),
(16, 5, 43, 1000000.00, '2026-10-04 22:16:44', '2026-10-04 22:16:44'),
(17, 6, 38, 8500000.00, '2026-10-06 06:04:24', '2026-10-06 06:04:24');

-- --------------------------------------------------------

--
-- Struktur dari tabel `procurement_allocations`
--

CREATE TABLE `procurement_allocations` (
  `id` bigint UNSIGNED NOT NULL,
  `procurement_request_item_id` bigint UNSIGNED NOT NULL,
  `boq_item_id` bigint UNSIGNED NOT NULL,
  `allocated_quantity` decimal(18,4) DEFAULT NULL,
  `allocated_amount` decimal(18,2) NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `procurement_allocations`
--

INSERT INTO `procurement_allocations` (`id`, `procurement_request_item_id`, `boq_item_id`, `allocated_quantity`, `allocated_amount`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 33, NULL, 15000000.00, NULL, '2026-10-05 18:41:39', '2026-10-05 18:41:39'),
(2, 1, 34, NULL, 10000000.00, NULL, '2026-10-05 18:41:39', '2026-10-05 18:41:39'),
(3, 2, 33, NULL, 10000000.00, NULL, '2026-10-05 18:41:39', '2026-10-05 18:41:39'),
(4, 3, 35, NULL, 2000000.00, NULL, '2026-10-05 18:41:39', '2026-10-05 18:41:39'),
(5, 5, 33, NULL, 15000.00, NULL, '2026-10-05 18:53:23', '2026-10-05 18:53:23'),
(6, 6, 36, NULL, 9999999.90, NULL, '2026-10-06 04:16:13', '2026-10-06 04:16:13'),
(7, 7, 37, 9.9991, 10000000.00, NULL, '2026-10-06 22:41:12', '2026-10-06 22:41:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `procurement_requests`
--

CREATE TABLE `procurement_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `request_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_date` date NOT NULL,
  `supplier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `estimated_total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `created_by` bigint UNSIGNED NOT NULL,
  `submitted_by` bigint UNSIGNED DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `returned_by` bigint UNSIGNED DEFAULT NULL,
  `returned_at` timestamp NULL DEFAULT NULL,
  `return_note` text COLLATE utf8mb4_unicode_ci,
  `paid_by` bigint UNSIGNED DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `paid_total` decimal(18,2) DEFAULT NULL,
  `invoice_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_proof_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_by` bigint UNSIGNED DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `procurement_requests`
--

INSERT INTO `procurement_requests` (`id`, `project_id`, `request_number`, `type`, `request_date`, `supplier`, `notes`, `estimated_total`, `status`, `created_by`, `submitted_by`, `submitted_at`, `approved_by`, `approved_at`, `returned_by`, `returned_at`, `return_note`, `paid_by`, `paid_at`, `payment_date`, `paid_total`, `invoice_number`, `payment_proof_file`, `cancelled_by`, `cancelled_at`, `cancel_reason`, `created_at`, `updated_at`) VALUES
(1, 5, 'PB-0001', 'BMHP', '2026-09-26', 'PT ABC Material', NULL, 37500000.00, 'PAID', 1, 1, '2026-09-26 18:41:39', 1, '2026-09-27 18:41:39', NULL, NULL, NULL, 1, '2026-09-27 18:41:39', '2026-09-26', 37500000.00, 'INV-PB-0001', NULL, NULL, NULL, NULL, '2026-10-05 18:41:38', '2026-10-05 18:41:39'),
(2, 5, 'PB-0002', 'BMHP', '2026-10-05', 'CV Sumber Jaya', NULL, 3500000.00, 'DRAFT', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'INV-PB-0002', NULL, NULL, NULL, NULL, '2026-10-05 18:41:39', '2026-10-05 18:41:39'),
(3, 5, 'PB-0003', 'BMHP', '2026-10-06', 'PT ABCDE', NULL, 15000.00, 'PAID', 1, 1, '2026-10-05 18:53:37', 1, '2026-10-05 18:53:44', NULL, NULL, NULL, 1, '2026-10-05 18:53:44', '2026-10-06', 15000.00, 'INV-112-112', NULL, NULL, NULL, NULL, '2026-10-05 18:51:44', '2026-10-05 18:53:44'),
(4, 5, 'PB-0004', 'BMHP', '2026-10-06', 'PT ABCDE', NULL, 10000000.00, 'PAID', 1, 1, '2026-10-06 04:16:22', 1, '2026-10-06 04:16:29', NULL, NULL, NULL, 1, '2026-10-06 04:16:29', '2026-10-06', 10000000.00, 'INV-112-112', NULL, NULL, NULL, NULL, '2026-10-06 04:15:12', '2026-10-06 04:16:29'),
(5, 5, 'PB-0005', 'BMHP', '2026-10-07', 'PT ABCDE', NULL, 10000000.00, 'PAID', 1, 1, '2026-10-06 22:41:21', 1, '2026-10-06 22:41:27', NULL, NULL, NULL, 1, '2026-10-06 22:41:27', '2026-10-07', 10000000.00, 'inv-001ppp', NULL, NULL, NULL, NULL, '2026-10-06 22:38:52', '2026-10-06 22:41:27'),
(6, 8, 'PR-0001', 'BMHP', '2026-10-10', NULL, NULL, 1000000.00, 'PAID', 1, 1, '2026-10-09 18:50:33', 1, '2026-10-09 18:50:37', NULL, NULL, NULL, 1, '2026-10-09 18:52:07', '2026-10-10', 1000000.00, NULL, NULL, NULL, NULL, NULL, '2026-10-09 18:50:18', '2026-10-09 18:52:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `procurement_request_items`
--

CREATE TABLE `procurement_request_items` (
  `id` bigint UNSIGNED NOT NULL,
  `procurement_request_id` bigint UNSIGNED NOT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(18,4) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `total` decimal(18,2) NOT NULL,
  `paid_unit_price` decimal(18,2) DEFAULT NULL,
  `paid_total` decimal(18,2) DEFAULT NULL,
  `asset_category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rental_start` date DEFAULT NULL,
  `rental_end` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `procurement_request_items`
--

INSERT INTO `procurement_request_items` (`id`, `procurement_request_id`, `item_name`, `unit`, `quantity`, `unit_price`, `total`, `paid_unit_price`, `paid_total`, `asset_category`, `brand`, `model`, `rental_start`, `rental_end`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'Gypsum 9mm', 'Lembar', 100.0000, 250000.00, 25000000.00, 250000.00, 25000000.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-05 18:41:39', '2026-10-05 18:41:39'),
(2, 1, 'Hollow 4x4', 'Batang', 200.0000, 50000.00, 10000000.00, 50000.00, 10000000.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-05 18:41:39', '2026-10-05 18:41:39'),
(3, 1, 'Screw Gypsum', 'Box', 50.0000, 50000.00, 2500000.00, 50000.00, 2500000.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-05 18:41:39', '2026-10-05 18:41:39'),
(4, 2, 'Compound Gypsum', 'Sak', 70.0000, 50000.00, 3500000.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-05 18:41:39', '2026-10-05 18:41:39'),
(5, 3, 'Hollow 4x4', 'Batang', 1.0000, 15000.00, 15000.00, 15000.00, 15000.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-05 18:52:18', '2026-10-05 18:52:18'),
(6, 4, 'Compound Gypsum', 'Sak', 100.0000, 100000.00, 10000000.00, 100000.00, 10000000.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-06 04:15:32', '2026-10-06 04:15:32'),
(7, 5, 'Gypsum 9mm', 'Lembar', 10.0000, 1000000.00, 10000000.00, 1000000.00, 10000000.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-06 22:39:38', '2026-10-06 22:39:38'),
(8, 6, 'Semen', 'Sak', 10.0000, 100000.00, 1000000.00, 100000.00, 1000000.00, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-09 18:50:18', '2026-10-09 18:52:07');

-- --------------------------------------------------------

--
-- Struktur dari tabel `projects`
--

CREATE TABLE `projects` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_value` decimal(18,2) DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `start_date` date DEFAULT NULL,
  `target_end_date` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `projects`
--

INSERT INTO `projects` (`id`, `code`, `name`, `client_name`, `contract_number`, `contract_value`, `description`, `start_date`, `target_end_date`, `status`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'PRJ-001', 'Engineering & Installation Project', 'PT Nusantara Logistik', NULL, NULL, 'Perluasan gudang termasuk engineering design dan instalasi utilitas.', '2026-09-26', '2027-02-26', 'ACTIVE', 1, '2026-09-25 21:51:41', '2026-09-25 21:51:41', NULL),
(2, 'PRJ-002', 'Data Center Fit-Out Phase 2', 'PT Mega Data Indonesia', NULL, NULL, 'Fit-out ruang data center: rack layout, cabling, dan testing.', '2026-09-12', '2026-12-26', 'ACTIVE', 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42', NULL),
(3, 'PRJ-003', 'Retail Fit-Out — Structured Cabling', 'PT Retail Prima', NULL, NULL, 'BOQ dikembalikan untuk revisi harga item tertentu.', '2026-09-19', '2027-01-26', 'ACTIVE', 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42', NULL),
(4, 'PRJ-004', 'Plant Utility Upgrade — Cooling System', 'PT Industri Sejahtera', NULL, NULL, 'Upgrade sistem pendingin pabrik. BOQ sudah approved dan siap/ sebagian dibayar.', '2026-08-26', '2026-12-26', 'ACTIVE', 1, '2026-09-25 21:51:42', '2026-09-25 21:51:42', NULL),
(5, 'PRJ-005', 'Office Network Modernization — Phase Live', 'PT Sentral Bisnis Asia', NULL, 3100000000.00, 'Project berjalan: BOQ approved, payment paid, progress sedang diupdate.', '2026-07-26', '2026-11-26', 'ACTIVE', 1, '2026-09-25 21:51:43', '2026-10-06 00:47:14', NULL),
(6, 'TEST 001', 'Projek Maju Bersama', 'PT Nusantara Bersama', '001/KTR/Test/X/2026', NULL, NULL, '2026-10-05', '2026-11-30', 'DRAFT', 1, '2026-10-04 19:17:10', '2026-10-04 19:17:10', NULL),
(7, 'TEST 001 2', 'Pekerjaan Bersama', 'PT ABCDE', '001/KTR/ON/X/2026', 150000000.00, NULL, '2026-09-04', '2026-10-10', 'DRAFT', 1, '2026-10-04 21:20:58', '2026-10-04 21:20:58', NULL),
(8, 'Test 002', 'Pekerjaan Bersama', 'PT ABCDE', '001/KTR/Test/X/2026', 100000000.00, NULL, '2026-10-05', '2026-10-31', 'ACTIVE', 1, '2026-10-04 22:08:59', '2026-10-04 22:09:31', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `project_assets`
--

CREATE TABLE `project_assets` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `asset_id` bigint UNSIGNED NOT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IN_USE',
  `assigned_by` bigint UNSIGNED NOT NULL,
  `released_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `project_assets`
--

INSERT INTO `project_assets` (`id`, `project_id`, `asset_id`, `assigned_at`, `released_at`, `notes`, `status`, `assigned_by`, `released_by`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2026-05-25 21:51:43', '2026-08-25 21:51:43', 'Riwayat penggunaan sebelumnya.', 'RELEASED', 1, 1, '2026-09-25 21:51:43', '2026-09-25 21:51:43'),
(2, 1, 1, '2026-09-20 21:51:43', '2026-10-04 22:40:41', 'Digunakan tim engineering.', 'RELEASED', 1, 1, '2026-09-25 21:51:43', '2026-10-04 22:40:41'),
(3, 6, 4, '2026-10-04 19:17:37', NULL, NULL, 'IN_USE', 1, NULL, '2026-10-04 19:17:37', '2026-10-04 19:17:37'),
(4, 8, 3, '2026-10-04 22:19:23', NULL, NULL, 'IN_USE', 1, NULL, '2026-10-04 22:19:23', '2026-10-04 22:19:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `project_documents`
--

CREATE TABLE `project_documents` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `document_type_id` bigint UNSIGNED NOT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint UNSIGNED DEFAULT NULL,
  `document_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `current_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `current_version` int UNSIGNED NOT NULL DEFAULT '0',
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_notes` text COLLATE utf8mb4_unicode_ci,
  `approved_version` int UNSIGNED DEFAULT NULL,
  `cancelled_by` bigint UNSIGNED DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `project_documents`
--

INSERT INTO `project_documents` (`id`, `project_id`, `document_type_id`, `reference_type`, `reference_id`, `document_number`, `document_title`, `description`, `current_status`, `current_version`, `approved_by`, `approved_at`, `approval_notes`, `approved_version`, `cancelled_by`, `cancelled_at`, `cancel_reason`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 5, 1, 'BILLING', 1, 'DOC-0001', 'Dokumen Penagihan Termin 1 — Uang Muka', NULL, 'APPROVED', 2, 1, '2026-09-01 02:05:01', 'Disetujui client via email.', 2, NULL, NULL, NULL, 1, '2026-08-22 02:05:01', '2026-09-01 02:05:01'),
(2, 5, 1, 'BILLING', 2, 'DOC-0002', 'Dokumen Penagihan Termin 2 — Progress 50%', NULL, 'APPROVED', 1, 1, '2026-10-06 04:21:03', NULL, 1, NULL, NULL, NULL, 1, '2026-09-27 02:05:01', '2026-10-06 04:21:03'),
(3, 5, 1, 'BILLING', 4, 'DOC-0003', 'Dokumen Penagihan Termin 3 — Progress 100%', NULL, 'APPROVED', 1, 1, '2026-10-06 22:48:34', 'telahdiapprove oleh client', 1, NULL, NULL, NULL, 1, '2026-10-06 22:46:50', '2026-10-06 22:48:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `project_document_status_histories`
--

CREATE TABLE `project_document_status_histories` (
  `id` bigint UNSIGNED NOT NULL,
  `project_document_id` bigint UNSIGNED NOT NULL,
  `from_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version_number` int UNSIGNED DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `revision_reason` text COLLATE utf8mb4_unicode_ci,
  `actor_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INTERNAL',
  `changed_by` bigint UNSIGNED DEFAULT NULL,
  `changed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `project_document_status_histories`
--

INSERT INTO `project_document_status_histories` (`id`, `project_document_id`, `from_status`, `to_status`, `version_number`, `notes`, `revision_reason`, `actor_type`, `changed_by`, `changed_at`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'DRAFT', 1, 'Dokumen dibuat.', NULL, 'INTERNAL', 1, '2026-08-22 02:05:01', '2026-08-22 02:05:01', '2026-08-22 02:05:01'),
(2, 1, 'DRAFT', 'SUBMITTED', 1, 'Dikirim via email ke client.', NULL, 'INTERNAL', 1, '2026-08-23 02:05:01', '2026-08-23 02:05:01', '2026-08-23 02:05:01'),
(3, 1, 'SUBMITTED', 'UNDER_REVIEW', 1, NULL, NULL, 'INTERNAL', 1, '2026-08-25 02:05:01', '2026-08-25 02:05:01', '2026-08-25 02:05:01'),
(4, 1, 'UNDER_REVIEW', 'REVISION_REQUIRED', 1, NULL, 'Nomor kontrak pada invoice belum dicantumkan.', 'INTERNAL', 1, '2026-08-27 02:05:01', '2026-08-27 02:05:01', '2026-08-27 02:05:01'),
(5, 1, 'REVISION_REQUIRED', 'RESUBMITTED', 2, NULL, NULL, 'INTERNAL', 1, '2026-08-28 04:05:01', '2026-08-28 04:05:01', '2026-08-28 04:05:01'),
(6, 1, 'RESUBMITTED', 'UNDER_REVIEW', 2, NULL, NULL, 'INTERNAL', 1, '2026-08-30 02:05:01', '2026-08-30 02:05:01', '2026-08-30 02:05:01'),
(7, 1, 'UNDER_REVIEW', 'APPROVED', 2, 'Disetujui client via email.', NULL, 'INTERNAL', 1, '2026-09-01 02:05:01', '2026-09-01 02:05:01', '2026-09-01 02:05:01'),
(8, 2, NULL, 'DRAFT', 1, 'Dokumen dibuat.', NULL, 'INTERNAL', 1, '2026-09-27 02:05:01', '2026-09-27 02:05:01', '2026-09-27 02:05:01'),
(9, 2, 'DRAFT', 'SUBMITTED', 1, NULL, NULL, 'INTERNAL', 1, '2026-09-28 02:05:01', '2026-09-28 02:05:01', '2026-09-28 02:05:01'),
(10, 2, 'SUBMITTED', 'UNDER_REVIEW', 1, NULL, NULL, 'INTERNAL', 1, '2026-09-30 02:05:01', '2026-09-30 02:05:01', '2026-09-30 02:05:01'),
(11, 2, 'UNDER_REVIEW', 'APPROVED', 1, NULL, NULL, 'INTERNAL', 1, '2026-10-06 04:21:03', '2026-10-06 04:21:03', '2026-10-06 04:21:03'),
(12, 3, NULL, 'DRAFT', 1, 'Dokumen dibuat.', NULL, 'INTERNAL', 1, '2026-10-06 22:46:50', '2026-10-06 22:46:50', '2026-10-06 22:46:50'),
(13, 3, 'DRAFT', 'SUBMITTED', 1, NULL, NULL, 'INTERNAL', 1, '2026-10-06 22:47:44', '2026-10-06 22:47:44', '2026-10-06 22:47:44'),
(14, 3, 'SUBMITTED', 'UNDER_REVIEW', 1, NULL, NULL, 'INTERNAL', 1, '2026-10-06 22:48:08', '2026-10-06 22:48:08', '2026-10-06 22:48:08'),
(15, 3, 'UNDER_REVIEW', 'APPROVED', 1, 'telahdiapprove oleh client', NULL, 'INTERNAL', 1, '2026-10-06 22:48:34', '2026-10-06 22:48:34', '2026-10-06 22:48:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `project_document_versions`
--

CREATE TABLE `project_document_versions` (
  `id` bigint UNSIGNED NOT NULL,
  `project_document_id` bigint UNSIGNED NOT NULL,
  `version_number` int UNSIGNED NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint UNSIGNED NOT NULL,
  `checksum_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `uploaded_by` bigint UNSIGNED NOT NULL,
  `uploaded_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `project_document_versions`
--

INSERT INTO `project_document_versions` (`id`, `project_document_id`, `version_number`, `file_path`, `original_filename`, `mime_type`, `file_size`, `checksum_sha256`, `notes`, `uploaded_by`, `uploaded_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'project-documents/5/1/bec75422-5798-4aaa-96b7-6a1894d9ce91.pdf', 'Invoice_Termin_1.pdf', 'application/pdf', 77, '56c2ac043c28d3543275a6f50b593a6beb0b6888ed8159ac02e3e7f4a32ccb26', NULL, 1, '2026-08-22 02:05:01', '2026-08-22 02:05:01', '2026-08-22 02:05:01'),
(2, 1, 2, 'project-documents/5/1/54a78920-995c-4520-bfec-ec75e166d24d.pdf', 'Invoice_Termin_1_rev1.pdf', 'application/pdf', 77, '56c2ac043c28d3543275a6f50b593a6beb0b6888ed8159ac02e3e7f4a32ccb26', 'Menambahkan nomor kontrak pada invoice.', 1, '2026-08-28 02:05:01', '2026-08-28 02:05:01', '2026-08-28 02:05:01'),
(3, 2, 1, 'project-documents/5/2/b2c88649-bee5-491c-b465-bf4d943a4e66.pdf', 'Invoice_Termin_2.pdf', 'application/pdf', 77, '56c2ac043c28d3543275a6f50b593a6beb0b6888ed8159ac02e3e7f4a32ccb26', NULL, 1, '2026-09-27 02:05:01', '2026-09-27 02:05:01', '2026-09-27 02:05:01'),
(4, 3, 1, 'project-documents/5/3/701a3c73-5f5e-4bc8-856c-d34ec54ccf42.pdf', 'USER_GUIDE_PEMOHON.pdf', 'application/pdf', 2192987, '3091b45ab25e19aa48af240bb225c3a05ff0362ac8627490ca1665834fb8dc7e', NULL, 1, '2026-10-06 22:46:54', '2026-10-06 22:46:54', '2026-10-06 22:46:54');

-- --------------------------------------------------------

--
-- Struktur dari tabel `project_terms`
--

CREATE TABLE `project_terms` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `term_number` smallint UNSIGNED NOT NULL,
  `term_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculation_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `percentage` decimal(7,4) DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL,
  `trigger_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MANUAL',
  `trigger_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_progress` decimal(5,2) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `readiness` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NOT_READY',
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `project_terms`
--

INSERT INTO `project_terms` (`id`, `project_id`, `term_number`, `term_name`, `calculation_type`, `percentage`, `amount`, `trigger_type`, `trigger_value`, `trigger_progress`, `due_date`, `notes`, `readiness`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 5, 1, 'Uang Muka', 'PERCENTAGE', 20.0000, 620000000.00, 'MANUAL', 'Setelah kontrak ditandatangani', NULL, NULL, NULL, 'READY', 1, '2026-10-06 00:47:15', '2026-10-06 00:47:15'),
(2, 5, 2, 'Progress 50%', 'PERCENTAGE', 30.0000, 930000000.00, 'PROGRESS', 'Progress fisik 50%', 50.00, NULL, NULL, 'READY', 1, '2026-10-06 00:47:15', '2026-10-06 00:47:15'),
(3, 5, 3, 'Progress 100%', 'PERCENTAGE', 30.0000, 930000000.00, 'PROGRESS', 'Progress fisik 100%', 100.00, NULL, NULL, 'READY', 1, '2026-10-06 00:47:15', '2026-10-06 22:43:25'),
(4, 5, 4, 'Retensi', 'PERCENTAGE', 20.0000, 620000000.00, 'MILESTONE', 'BAST / masa pemeliharaan selesai', NULL, NULL, NULL, 'NOT_READY', 1, '2026-10-06 00:47:15', '2026-10-06 00:47:15');

-- --------------------------------------------------------

--
-- Struktur dari tabel `sessions`
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
-- Dumping data untuk tabel `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('coEjnH9hOl9RvtPIXzmyfNDSWJ4hD4jmiVI11cz6', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/3.23.12 Chrome/148.0.7778.280 Electron/42.10.0 Safari/537.36', 'eyJfdG9rZW4iOiJ0M005OFZCRG5UQ2VZV0FBY0VDcjRCQzR5ZTVlU1dGblJ5WmdsYmRpIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL3Byb2plY3RzXC84XC9wcm9jdXJlbWVudFwvY3JlYXRlP3R5cGU9Qk1IUCJ9LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1791600312),
('MEAyf1SPXdigjn7b1wCIavPCRpYAfom3YRvXpAf9', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/3.23.12 Chrome/148.0.7778.280 Electron/42.10.0 Safari/537.36', 'eyJfdG9rZW4iOiJjRjVYbWZYVjBiOFRRQzh1b3dPcElBR0NSYXlXZjR4aWVnb0dQSzIxIiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdDo4MDAwXC9wcm9qZWN0c1wvOFwvcmVwb3J0cz9zZWN0aW9uPWJpbGxpbmciLCJyb3V0ZSI6InByb2plY3RzLnJlcG9ydHMuaW5kZXgifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6MSwicGFzc3dvcmRfaGFzaF93ZWIiOiJjZTNmYmE4YmYwMmY2MjhhNTJkZjk4NDRjNzU3ZGUxOTE1YzQ4NzRhMDk3Yzg1YjE4ZGYxMjgzM2VlYmU4YTdhIn0=', 1791606854),
('Q22CaNGHEBEJnZBPfBCkkoYXAnRyXJXWtvCdAVwg', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiIwcXhNTEVBZU1Nb3lIb3czVThzblVwamdrdlQxOEFhckpzUTREZHNMIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL3Byb2plY3RzXC83In0sIl9wcmV2aW91cyI6eyJ1cmwiOiJodHRwOlwvXC8xMjcuMC4wLjE6ODAwMFwvcHJvamVjdHNcLzciLCJyb3V0ZSI6InByb2plY3RzLnNob3cifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1791600091),
('uBUsKnBg2OTNGAw9JI0WP7TCq5MkW70ctsH107VK', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJCWDQ2TmF3OFNCdlJXcjVtQ00yd0M0bkZaR2ViYXM2dVBwVXVlanNTIiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9wcm9qZWN0c1wvOFwvYmlsbGluZyIsInJvdXRlIjoicHJvamVjdHMuYmlsbGluZy5pbmRleCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjoxLCJwYXNzd29yZF9oYXNoX3dlYiI6ImNlM2ZiYThiZjAyZjYyOGE1MmRmOTg0NGM3NTdkZTE5MTVjNDg3NGEwOTdjODViMThkZjEyODMzZWViZThhN2EifQ==', 1791600859);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'superadmin',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'superadmin@example.com', '2026-09-25 21:51:41', '$2y$12$D24w2aB2s7xGwc5K2zTvzOL1XhTfOK767T1XM933JZjsjRlbXtQOi', 'superadmin', 'o49zz2NbzaYgkdQbOO4lbKa43YSB2FavAbLRoYzhGypDCxH9Nv1GM96Mig0r', '2026-09-25 21:51:38', '2026-09-25 21:51:41'),
(2, 'Project Admin', 'project.admin@example.com', '2026-09-25 21:51:38', '$2y$12$ZGNdBfEF11g9Qk9Y2xpo1eT/tybC11mpBfteOtC3Bbeq3BmIx1WZ2', 'project_admin', NULL, '2026-09-25 21:51:38', '2026-09-25 21:51:38'),
(3, 'BOQ Cost Control', 'boq.cost@example.com', '2026-09-25 21:51:39', '$2y$12$ST.vDZd.L48L9bir7ALrV.vS.vRoUCjoHznwSJGkonca1QnN36NSK', 'boq_cost_control', NULL, '2026-09-25 21:51:39', '2026-09-25 21:51:39'),
(4, 'Approver', 'approver@example.com', '2026-09-25 21:51:40', '$2y$12$EjRHrHcyhrB9zB3Dx5Tkr.idlLLUwL2iuTxQoNzkhs1a/SyPcM75K', 'approver', NULL, '2026-09-25 21:51:40', '2026-09-25 21:51:40'),
(5, 'Finance', 'finance@example.com', '2026-09-25 21:51:40', '$2y$12$kDiCDLZidueHv7izxYzw/.piMXcnWAJX/la/.udlh8JR.pzo32D.C', 'finance', NULL, '2026-09-25 21:51:40', '2026-09-25 21:51:40'),
(6, 'Project Site', 'project.site@example.com', '2026-09-25 21:51:41', '$2y$12$A/7m0extpgh7KsBXM.xKju658KlHjy2NbedTlQzWWa1Y6RN5MX7Yq', 'project_site', NULL, '2026-09-25 21:51:41', '2026-09-25 21:51:41');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `assets_code_unique` (`code`),
  ADD KEY `assets_requested_by_foreign` (`requested_by`),
  ADD KEY `assets_created_by_foreign` (`created_by`),
  ADD KEY `assets_status_source_type_index` (`status`,`source_type`),
  ADD KEY `assets_category_index` (`category`),
  ADD KEY `assets_procurement_request_item_id_foreign` (`procurement_request_item_id`);

--
-- Indeks untuk tabel `boqs`
--
ALTER TABLE `boqs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `boqs_project_id_code_revision_number_unique` (`project_id`,`code`,`revision_number`),
  ADD KEY `boqs_submitted_by_foreign` (`submitted_by`),
  ADD KEY `boqs_reviewed_by_foreign` (`reviewed_by`),
  ADD KEY `boqs_status_index` (`status`);

--
-- Indeks untuk tabel `boqs_cogs`
--
ALTER TABLE `boqs_cogs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `boqs_cogs_boq_id_code_unique` (`boq_id`,`code`);

--
-- Indeks untuk tabel `boq_items`
--
ALTER TABLE `boq_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `boq_items_boq_cogs_id_code_unique` (`boq_cogs_id`,`code`);

--
-- Indeks untuk tabel `boq_revisions`
--
ALTER TABLE `boq_revisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `boq_revisions_revision_code_unique` (`revision_code`),
  ADD UNIQUE KEY `boq_revisions_boq_id_revision_number_unique` (`boq_id`,`revision_number`),
  ADD KEY `boq_revisions_created_by_foreign` (`created_by`),
  ADD KEY `boq_revisions_reviewed_by_foreign` (`reviewed_by`);

--
-- Indeks untuk tabel `boq_revision_items`
--
ALTER TABLE `boq_revision_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `boq_revision_items_boq_revision_id_boq_item_id_unique` (`boq_revision_id`,`boq_item_id`),
  ADD KEY `boq_revision_items_boq_item_id_foreign` (`boq_item_id`);

--
-- Indeks untuk tabel `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indeks untuk tabel `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indeks untuk tabel `client_billings`
--
ALTER TABLE `client_billings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_billings_billing_number_unique` (`billing_number`),
  ADD UNIQUE KEY `client_billings_invoice_number_unique` (`invoice_number`),
  ADD KEY `client_billings_created_by_foreign` (`created_by`),
  ADD KEY `client_billings_submitted_by_foreign` (`submitted_by`),
  ADD KEY `client_billings_sent_by_foreign` (`sent_by`),
  ADD KEY `client_billings_cancelled_by_foreign` (`cancelled_by`),
  ADD KEY `client_billings_project_id_status_index` (`project_id`,`status`),
  ADD KEY `client_billings_project_term_id_status_index` (`project_term_id`,`status`);

--
-- Indeks untuk tabel `client_payments`
--
ALTER TABLE `client_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_payments_payment_number_unique` (`payment_number`),
  ADD KEY `client_payments_created_by_foreign` (`created_by`),
  ADD KEY `client_payments_cancelled_by_foreign` (`cancelled_by`),
  ADD KEY `client_payments_client_billing_id_status_index` (`client_billing_id`,`status`);

--
-- Indeks untuk tabel `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_types_code_unique` (`code`);

--
-- Indeks untuk tabel `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  ADD KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`);

--
-- Indeks untuk tabel `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indeks untuk tabel `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indeks untuk tabel `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payments_payment_code_unique` (`payment_code`),
  ADD KEY `payments_payment_plan_id_foreign` (`payment_plan_id`),
  ADD KEY `payments_paid_by_foreign` (`paid_by`),
  ADD KEY `payments_voided_by_foreign` (`voided_by`);

--
-- Indeks untuk tabel `payment_allocations`
--
ALTER TABLE `payment_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_allocations_payment_id_boq_item_id_unique` (`payment_id`,`boq_item_id`),
  ADD KEY `payment_allocations_boq_item_id_index` (`boq_item_id`);

--
-- Indeks untuk tabel `payment_plans`
--
ALTER TABLE `payment_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_plans_reference_code_unique` (`reference_code`),
  ADD KEY `payment_plans_created_by_foreign` (`created_by`),
  ADD KEY `payment_plans_boq_id_status_index` (`boq_id`,`status`);

--
-- Indeks untuk tabel `payment_plan_items`
--
ALTER TABLE `payment_plan_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_plan_items_payment_plan_id_boq_item_id_unique` (`payment_plan_id`,`boq_item_id`),
  ADD KEY `payment_plan_items_boq_item_id_index` (`boq_item_id`);

--
-- Indeks untuk tabel `procurement_allocations`
--
ALTER TABLE `procurement_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `procurement_allocations_item_boq_item_unique` (`procurement_request_item_id`,`boq_item_id`),
  ADD KEY `procurement_allocations_boq_item_id_index` (`boq_item_id`);

--
-- Indeks untuk tabel `procurement_requests`
--
ALTER TABLE `procurement_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `procurement_requests_request_number_unique` (`request_number`),
  ADD KEY `procurement_requests_created_by_foreign` (`created_by`),
  ADD KEY `procurement_requests_submitted_by_foreign` (`submitted_by`),
  ADD KEY `procurement_requests_approved_by_foreign` (`approved_by`),
  ADD KEY `procurement_requests_returned_by_foreign` (`returned_by`),
  ADD KEY `procurement_requests_paid_by_foreign` (`paid_by`),
  ADD KEY `procurement_requests_cancelled_by_foreign` (`cancelled_by`),
  ADD KEY `procurement_requests_project_id_type_status_index` (`project_id`,`type`,`status`);

--
-- Indeks untuk tabel `procurement_request_items`
--
ALTER TABLE `procurement_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `procurement_request_items_procurement_request_id_foreign` (`procurement_request_id`);

--
-- Indeks untuk tabel `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `projects_code_unique` (`code`),
  ADD KEY `projects_created_by_foreign` (`created_by`);

--
-- Indeks untuk tabel `project_assets`
--
ALTER TABLE `project_assets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_assets_assigned_by_foreign` (`assigned_by`),
  ADD KEY `project_assets_released_by_foreign` (`released_by`),
  ADD KEY `project_assets_asset_id_status_index` (`asset_id`,`status`),
  ADD KEY `project_assets_project_id_status_index` (`project_id`,`status`);

--
-- Indeks untuk tabel `project_documents`
--
ALTER TABLE `project_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_documents_document_number_unique` (`document_number`),
  ADD KEY `project_documents_document_type_id_foreign` (`document_type_id`),
  ADD KEY `project_documents_approved_by_foreign` (`approved_by`),
  ADD KEY `project_documents_cancelled_by_foreign` (`cancelled_by`),
  ADD KEY `project_documents_created_by_foreign` (`created_by`),
  ADD KEY `project_documents_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  ADD KEY `project_documents_project_id_current_status_index` (`project_id`,`current_status`);

--
-- Indeks untuk tabel `project_document_status_histories`
--
ALTER TABLE `project_document_status_histories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_document_status_histories_changed_by_foreign` (`changed_by`),
  ADD KEY `doc_status_histories_document_changed_at_index` (`project_document_id`,`changed_at`);

--
-- Indeks untuk tabel `project_document_versions`
--
ALTER TABLE `project_document_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `doc_versions_document_version_unique` (`project_document_id`,`version_number`),
  ADD KEY `project_document_versions_uploaded_by_foreign` (`uploaded_by`);

--
-- Indeks untuk tabel `project_terms`
--
ALTER TABLE `project_terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_terms_project_id_term_number_unique` (`project_id`,`term_number`),
  ADD KEY `project_terms_created_by_foreign` (`created_by`);

--
-- Indeks untuk tabel `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `assets`
--
ALTER TABLE `assets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `boqs`
--
ALTER TABLE `boqs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `boqs_cogs`
--
ALTER TABLE `boqs_cogs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `boq_items`
--
ALTER TABLE `boq_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT untuk tabel `boq_revisions`
--
ALTER TABLE `boq_revisions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `boq_revision_items`
--
ALTER TABLE `boq_revision_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `client_billings`
--
ALTER TABLE `client_billings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `client_payments`
--
ALTER TABLE `client_payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT untuk tabel `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `payment_allocations`
--
ALTER TABLE `payment_allocations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `payment_plans`
--
ALTER TABLE `payment_plans`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `payment_plan_items`
--
ALTER TABLE `payment_plan_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `procurement_allocations`
--
ALTER TABLE `procurement_allocations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `procurement_requests`
--
ALTER TABLE `procurement_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `procurement_request_items`
--
ALTER TABLE `procurement_request_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `projects`
--
ALTER TABLE `projects`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `project_assets`
--
ALTER TABLE `project_assets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `project_documents`
--
ALTER TABLE `project_documents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `project_document_status_histories`
--
ALTER TABLE `project_document_status_histories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `project_document_versions`
--
ALTER TABLE `project_document_versions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `project_terms`
--
ALTER TABLE `project_terms`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `assets_procurement_request_item_id_foreign` FOREIGN KEY (`procurement_request_item_id`) REFERENCES `procurement_request_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `assets_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `boqs`
--
ALTER TABLE `boqs`
  ADD CONSTRAINT `boqs_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `boqs_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `boqs_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `boqs_cogs`
--
ALTER TABLE `boqs_cogs`
  ADD CONSTRAINT `boqs_cogs_boq_id_foreign` FOREIGN KEY (`boq_id`) REFERENCES `boqs` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `boq_items`
--
ALTER TABLE `boq_items`
  ADD CONSTRAINT `boq_items_boq_cogs_id_foreign` FOREIGN KEY (`boq_cogs_id`) REFERENCES `boqs_cogs` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `boq_revisions`
--
ALTER TABLE `boq_revisions`
  ADD CONSTRAINT `boq_revisions_boq_id_foreign` FOREIGN KEY (`boq_id`) REFERENCES `boqs` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `boq_revisions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `boq_revisions_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `boq_revision_items`
--
ALTER TABLE `boq_revision_items`
  ADD CONSTRAINT `boq_revision_items_boq_item_id_foreign` FOREIGN KEY (`boq_item_id`) REFERENCES `boq_items` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `boq_revision_items_boq_revision_id_foreign` FOREIGN KEY (`boq_revision_id`) REFERENCES `boq_revisions` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `client_billings`
--
ALTER TABLE `client_billings`
  ADD CONSTRAINT `client_billings_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `client_billings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `client_billings_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `client_billings_project_term_id_foreign` FOREIGN KEY (`project_term_id`) REFERENCES `project_terms` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `client_billings_sent_by_foreign` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `client_billings_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `client_payments`
--
ALTER TABLE `client_payments`
  ADD CONSTRAINT `client_payments_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `client_payments_client_billing_id_foreign` FOREIGN KEY (`client_billing_id`) REFERENCES `client_billings` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `client_payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `payments_payment_plan_id_foreign` FOREIGN KEY (`payment_plan_id`) REFERENCES `payment_plans` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `payments_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `payment_allocations`
--
ALTER TABLE `payment_allocations`
  ADD CONSTRAINT `payment_allocations_boq_item_id_foreign` FOREIGN KEY (`boq_item_id`) REFERENCES `boq_items` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `payment_allocations_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `payment_plans`
--
ALTER TABLE `payment_plans`
  ADD CONSTRAINT `payment_plans_boq_id_foreign` FOREIGN KEY (`boq_id`) REFERENCES `boqs` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `payment_plans_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `payment_plan_items`
--
ALTER TABLE `payment_plan_items`
  ADD CONSTRAINT `payment_plan_items_boq_item_id_foreign` FOREIGN KEY (`boq_item_id`) REFERENCES `boq_items` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `payment_plan_items_payment_plan_id_foreign` FOREIGN KEY (`payment_plan_id`) REFERENCES `payment_plans` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `procurement_allocations`
--
ALTER TABLE `procurement_allocations`
  ADD CONSTRAINT `procurement_allocations_boq_item_id_foreign` FOREIGN KEY (`boq_item_id`) REFERENCES `boq_items` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `procurement_allocations_procurement_request_item_id_foreign` FOREIGN KEY (`procurement_request_item_id`) REFERENCES `procurement_request_items` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `procurement_requests`
--
ALTER TABLE `procurement_requests`
  ADD CONSTRAINT `procurement_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `procurement_requests_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `procurement_requests_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `procurement_requests_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `procurement_requests_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `procurement_requests_returned_by_foreign` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `procurement_requests_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `procurement_request_items`
--
ALTER TABLE `procurement_request_items`
  ADD CONSTRAINT `procurement_request_items_procurement_request_id_foreign` FOREIGN KEY (`procurement_request_id`) REFERENCES `procurement_requests` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `project_assets`
--
ALTER TABLE `project_assets`
  ADD CONSTRAINT `project_assets_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `project_assets_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `project_assets_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_assets_released_by_foreign` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `project_documents`
--
ALTER TABLE `project_documents`
  ADD CONSTRAINT `project_documents_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `project_documents_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `project_documents_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `project_documents_document_type_id_foreign` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `project_documents_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `project_document_status_histories`
--
ALTER TABLE `project_document_status_histories`
  ADD CONSTRAINT `project_document_status_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `project_document_status_histories_project_document_id_foreign` FOREIGN KEY (`project_document_id`) REFERENCES `project_documents` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `project_document_versions`
--
ALTER TABLE `project_document_versions`
  ADD CONSTRAINT `project_document_versions_project_document_id_foreign` FOREIGN KEY (`project_document_id`) REFERENCES `project_documents` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `project_document_versions_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Ketidakleluasaan untuk tabel `project_terms`
--
ALTER TABLE `project_terms`
  ADD CONSTRAINT `project_terms_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `project_terms_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
