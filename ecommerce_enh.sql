-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 10, 2026 at 02:59 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ecommerce_enh`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Electronics', 'Electronic devices and gadgets', '2026-02-09 08:10:45', '2026-02-09 08:10:45'),
(2, 'Fashion', 'Clothing and accessories', '2026-02-09 08:10:45', '2026-02-09 08:10:45'),
(3, 'Home & Living', 'Home appliances and furniture', '2026-02-09 08:10:45', '2026-02-09 08:10:45'),
(4, 'Books', 'Books and stationery', '2026-02-09 08:10:45', '2026-02-09 08:10:45'),
(5, 'Food & Drink', 'Fullfil your craving for sweet foods or soft drink', '2026-02-09 14:42:21', '2026-02-09 14:42:21');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `category_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `stock`, `category_id`, `image`, `status`, `created_at`, `updated_at`) VALUES
(11, 'INFINIX HOT 50I', 'SMARTPHONE INFINIX HOT 50I TERSEDIA DENGAN VARIAN:\r\n- 8/256\r\n- 6/128\r\n\r\nDIJAMIN ORIGINAL BUKAN REFURBISH\r\n\r\nHAPPY SHOPPING :)', 2800000.00, 296, 1, '699e730e35b9c.jpg', 'active', '2026-02-25 03:57:02', '2026-04-10 12:56:11'),
(12, 'MOTHERBOARD ASUS B85M-E', 'MOTHERBOARD PC/KOMPUTER LGA 1150 ATAU SUPPORT SAMPAI INTEL GENERASI KE-EMPAT (HASWELL)', 380000.00, 188, 1, '69d67b3cdc063.jpg', 'active', '2026-04-08 15:58:52', '2026-04-10 12:18:51');

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('requested','approved','rejected') DEFAULT 'requested',
  `admin_note` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `transaction_code` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','shipped','delivered','cancelled') DEFAULT 'pending',
  `is_completed` tinyint(1) DEFAULT 0,
  `payment_method` varchar(50) DEFAULT NULL,
  `courier` varchar(50) DEFAULT NULL,
  `service_type` varchar(50) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `return_reason` text DEFAULT NULL,
  `return_processed_at` timestamp NULL DEFAULT NULL,
  `return_processed_by` int(11) DEFAULT NULL,
  `return_status` enum('none','requested','approved','rejected') DEFAULT 'none',
  `returned_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `transaction_code`, `total_amount`, `status`, `is_completed`, `payment_method`, `courier`, `service_type`, `shipping_address`, `notes`, `return_reason`, `return_processed_at`, `return_processed_by`, `return_status`, `returned_at`, `phone`, `created_at`, `updated_at`) VALUES
(17, 3, 'TRX-20260410-D7800A', 395000.00, 'paid', 0, 'bank_transfer_bni', NULL, NULL, 'Jl. Dadang Kutilang IV', 'Testing', 'barang rusak', '2026-04-10 10:27:22', 2, 'approved', NULL, '085812340000', '2026-04-10 09:11:25', '2026-04-10 10:27:22'),
(18, 3, 'TRX-20260410-F49614', 2815000.00, 'delivered', 1, 'bank_transfer_mandiri', NULL, NULL, 'Jl. Dadang Kutilang IV', '', NULL, NULL, NULL, 'none', NULL, '085812340000', '2026-04-10 10:41:03', '2026-04-10 12:16:36'),
(19, 3, 'TRX-20260410-B310D3', 3815000.00, 'delivered', 1, 'bank_transfer_bca', NULL, NULL, 'Jl. Dadang Kutilang IV', '', NULL, NULL, NULL, 'none', NULL, '085812340000', '2026-04-10 12:18:51', '2026-04-10 12:55:10'),
(20, 3, 'TRX-20260410-3DFE92', 2815000.00, 'cancelled', 0, 'bank_transfer_bca', NULL, NULL, 'Jl. Dadang Kutilang IV', '\n[Batal User: ga jadi beli]', NULL, NULL, NULL, 'none', NULL, '085812340000', '2026-04-10 12:55:31', '2026-04-10 12:55:49'),
(21, 3, 'TRX-20260410-B46A8E', 2815000.00, 'delivered', 0, 'bank_transfer_bca', NULL, NULL, 'Jl. Dadang Kutilang IV', '', NULL, NULL, NULL, 'none', NULL, '085812340000', '2026-04-10 12:56:11', '2026-04-10 12:56:44');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_details`
--

CREATE TABLE `transaction_details` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction_details`
--

INSERT INTO `transaction_details` (`id`, `transaction_id`, `product_id`, `quantity`, `price`, `subtotal`) VALUES
(16, 17, 12, 1, 380000.00, 380000.00),
(17, 18, 11, 1, 2800000.00, 2800000.00),
(18, 19, 12, 10, 380000.00, 3800000.00),
(19, 20, 11, 1, 2800000.00, 2800000.00),
(20, 21, 11, 1, 2800000.00, 2800000.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','petugas','admin') DEFAULT 'user',
  `balance` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `address`, `profile_picture`, `password`, `role`, `balance`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Azzam', 'admin@ecommerce.com', '', NULL, 'profile_1_1770647465.jpg', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 3815000.00, 'active', '2026-02-09 07:31:50', '2026-04-10 12:55:10'),
(2, 'Azzam', 'azzam@petugas.com', '085699776655', NULL, NULL, '$2y$10$yKtncBTpA8i654ImXpXMsOx3a6Wh4J/ijoUQPTknKgNQGIrlN6yxS', 'petugas', 0.00, 'active', '2026-02-09 08:48:11', '2026-04-08 16:38:14'),
(3, 'Azzam', 'azzam@user.com', '085812340000', 'Jl. Dadang Kutilang IV', NULL, '$2y$10$kNCSOV.UUGJm0ZzCDOblo.75nVdrXjQ7noCEuP7O6gwJmiDNAUpB6', 'user', 0.00, 'active', '2026-02-25 03:18:39', '2026-04-08 15:09:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_code` (`transaction_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transaction_details`
--
ALTER TABLE `transaction_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `transaction_details`
--
ALTER TABLE `transaction_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `returns`
--
ALTER TABLE `returns`
  ADD CONSTRAINT `returns_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `returns_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `returns_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transaction_details`
--
ALTER TABLE `transaction_details`
  ADD CONSTRAINT `transaction_details_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
