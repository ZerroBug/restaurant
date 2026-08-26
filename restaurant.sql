-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 26, 2026 at 02:33 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `restaurant`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Proteins', 'Only proteins', 'Active', '2026-08-17 05:26:43', '2026-08-17 05:26:43'),
(2, 'Main meal', NULL, 'Active', '2026-08-17 05:28:28', '2026-08-17 05:28:28');

-- --------------------------------------------------------

--
-- Table structure for table `food_menu`
--

CREATE TABLE `food_menu` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('Available','Unavailable') NOT NULL DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_menu`
--

INSERT INTO `food_menu` (`id`, `category_id`, `name`, `description`, `price`, `image`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 'Fried Rice & Chicken', '', 50.00, 'food_73ff09aee69876c9b6c8818fd05afdc4.jpg', 'Available', '2026-08-17 13:09:06', '2026-08-23 15:48:40'),
(7, 2, 'Fried Rice & Chicken', NULL, 60.00, 'food_3e38725a3121173b02b09bb42f9b9c3a.jpg', 'Available', '2026-08-23 15:58:20', '2026-08-23 15:58:20'),
(8, 2, 'Fried Rice & Chicken', NULL, 70.00, 'food_235b86e0039e1f816f6896b1a7217769.jpg', 'Available', '2026-08-23 15:59:38', '2026-08-23 15:59:38'),
(9, 2, 'Jollof Rice @ Chicken', NULL, 50.00, 'food_91aace72e9d7419c083409e34a1ae0bb.png', 'Available', '2026-08-23 16:20:19', '2026-08-23 16:20:19'),
(10, 2, 'Jollof Rice @ Chicken', NULL, 60.00, 'food_c2be6f6aa34b066319ceadf5fdbd40d0.png', 'Available', '2026-08-23 16:20:58', '2026-08-23 16:20:58'),
(11, 2, 'Jollof Rice @ Chicken', NULL, 70.00, 'food_5c2bee380c53bb8ded4b67dc6ce0cad9.png', 'Available', '2026-08-23 16:23:02', '2026-08-23 16:23:02'),
(12, 2, 'Plain Rice &Chicken', NULL, 50.00, 'food_d8779a357f9ba4ed5c9f118483dd0c4c.jpg', 'Available', '2026-08-23 16:51:51', '2026-08-23 16:51:51'),
(13, 2, 'Plain Rice &Chicken', NULL, 60.00, 'food_1a9922791146ab4367319987b3c39b8e.jpg', 'Available', '2026-08-23 16:53:34', '2026-08-23 16:53:34'),
(14, 2, 'Assorted Fried Rice', NULL, 80.00, 'food_e9dafb69179188b11bc16b94d76e2696.png', 'Available', '2026-08-23 17:07:07', '2026-08-23 17:07:07'),
(15, 2, 'Assorted Fried Rice', NULL, 100.00, 'food_3fa1dfd0431743714372922371968397.png', 'Available', '2026-08-23 17:07:41', '2026-08-23 17:07:41'),
(16, 2, 'Assorted Jollof Rice', '', 80.00, 'food_16_214f62d6fb57988b.png', 'Available', '2026-08-23 17:57:57', '2026-08-23 21:49:35'),
(17, 2, 'Assorted Jollof Rice', '', 100.00, 'food_17_2b84fb836daf4756.png', 'Available', '2026-08-23 17:58:22', '2026-08-23 21:49:19'),
(18, 2, 'Assorted Indomie', '', 50.00, 'food_18_d82e6700cb6379a8.png', 'Available', '2026-08-23 18:00:00', '2026-08-23 21:47:35'),
(19, 2, 'Assorted Indomie', '', 70.00, 'food_19_a1ffb03f4f053d36.png', 'Available', '2026-08-23 18:00:19', '2026-08-23 21:45:02'),
(20, 2, 'Fried Rice & Fish', '', 80.00, 'food_20_7728f730c352178d.png', 'Available', '2026-08-23 18:01:54', '2026-08-23 21:47:05'),
(21, 2, 'Fried Rice & Fish', '', 100.00, 'food_21_cbaf75501794f99e.png', 'Available', '2026-08-23 20:03:31', '2026-08-23 21:46:49'),
(22, 2, 'Jollof Rice & Fish', '', 100.00, 'food_22_9410042b13e51611.png', 'Available', '2026-08-23 20:04:16', '2026-08-23 21:43:42'),
(23, 2, 'Jollof Rice & Fish', '', 120.00, 'food_23_ffdf0a2f6db0394b.png', 'Available', '2026-08-23 20:04:38', '2026-08-23 21:43:26'),
(24, 2, 'Loaded Rice', '', 100.00, 'food_24_4333b04ab7202695.png', 'Available', '2026-08-23 20:05:44', '2026-08-23 21:39:06'),
(25, 2, 'Potato Chips & Chicken', '', 100.00, 'food_25_a47ef7ca03090589.png', 'Available', '2026-08-23 20:33:13', '2026-08-23 21:37:37'),
(26, 2, 'Fried Yam & Chicken Wings', '', 80.00, 'food_26_4a5ac1d5502f1c03.png', 'Available', '2026-08-23 20:34:11', '2026-08-23 21:37:16'),
(27, 2, 'Fried Yam & Chicken Wings', '', 100.00, 'food_27_64ab4ebf7db94366.png', 'Available', '2026-08-23 20:34:36', '2026-08-23 21:36:38'),
(28, 2, 'Banku &Tilapia', '', 100.00, 'food_28_a9c63e73dbb41209.png', 'Available', '2026-08-23 20:35:52', '2026-08-23 21:36:08'),
(29, 2, 'Banku & Fish', NULL, 80.00, 'food_504902df6ef71ba6b9891c4c51af3712.png', 'Available', '2026-08-23 20:48:43', '2026-08-23 20:48:43'),
(30, 2, 'Banku & Fish', NULL, 100.00, 'food_8b619c139f0183b5acb170c503930a29.png', 'Available', '2026-08-23 20:49:14', '2026-08-23 20:49:14'),
(31, 2, 'Banko & Okro & Beef', NULL, 50.00, 'food_23b6142c7fe64ca64b25f99590c34a3b.png', 'Available', '2026-08-23 21:01:56', '2026-08-23 21:01:56'),
(32, 2, 'Banko & Okro & Beef', NULL, 60.00, 'food_9e81324d1b6c210f1a69e39d48d9a51e.png', 'Available', '2026-08-23 21:02:40', '2026-08-23 21:02:40'),
(33, 2, 'Banko & Okro & Beef', NULL, 80.00, 'food_80e961bd448028c77c80ee00be3ec1c6.png', 'Available', '2026-08-23 21:03:11', '2026-08-23 21:03:11'),
(34, 2, 'Banko & Okro & Fish', NULL, 80.00, 'food_9d798d25b32b218ce403e380c60f6e90.png', 'Available', '2026-08-23 21:04:22', '2026-08-23 21:04:22'),
(35, 2, 'Banko & Okro & Fish', NULL, 100.00, 'food_c38c8b27e5a865e24bd222a8e03befef.png', 'Available', '2026-08-23 21:04:49', '2026-08-23 21:04:49'),
(36, 2, 'Banko & Okro & Chicken', NULL, 50.00, 'food_5840d4c56f3b67505d29766404bbae08.png', 'Available', '2026-08-23 21:05:46', '2026-08-23 21:05:46'),
(37, 2, 'Banko & Okro & Chicken', NULL, 60.00, 'food_d4f56e941d97bb71750396edd7cbc82f.png', 'Available', '2026-08-23 21:06:39', '2026-08-23 21:06:39'),
(38, 2, 'Banko & Okro & Chicken', NULL, 80.00, 'food_50ac4203b727c354901f6049cba8d993.png', 'Available', '2026-08-23 21:07:11', '2026-08-23 21:07:11');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `minimum_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('In Stock','Low Stock','Out of Stock') NOT NULL DEFAULT 'In Stock',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `order_type` enum('Dine In','Takeaway') NOT NULL DEFAULT 'Dine In',
  `status` enum('Pending','Preparing','Ready','Completed','Cancelled') NOT NULL DEFAULT 'Completed',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Unpaid','Partially Paid','Paid') NOT NULL DEFAULT 'Paid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `order_type`, `status`, `subtotal`, `discount`, `tax`, `total`, `payment_status`, `notes`, `created_at`, `updated_at`) VALUES
(42, 'ORD-20260824052539-412', 4, 'Dine In', 'Completed', 130.00, 0.00, 0.00, 130.00, 'Paid', NULL, '2026-08-24 03:25:39', '2026-08-24 03:25:39'),
(43, 'ORD-20260824060124-752', 4, 'Dine In', 'Completed', 100.00, 0.00, 0.00, 100.00, 'Paid', NULL, '2026-08-24 04:01:24', '2026-08-24 04:01:24'),
(44, 'ORD-20260824153717-449', 5, 'Dine In', 'Completed', 80.00, 0.00, 0.00, 80.00, 'Paid', NULL, '2026-08-24 13:37:17', '2026-08-24 13:37:17'),
(45, 'ORD-20260824153827-690', 5, 'Dine In', 'Completed', 100.00, 0.00, 0.00, 100.00, 'Paid', NULL, '2026-08-24 13:38:27', '2026-08-24 13:38:27'),
(46, 'ORD-20260824163805-189', 4, 'Dine In', 'Completed', 180.00, 0.00, 0.00, 180.00, 'Paid', NULL, '2026-08-24 14:38:05', '2026-08-24 14:38:05'),
(47, 'ORD-20260825013335-166', 4, 'Dine In', 'Completed', 210.00, 0.00, 0.00, 210.00, 'Paid', NULL, '2026-08-24 23:33:35', '2026-08-24 23:33:35'),
(48, 'ORD-20260826014831-918', 4, 'Takeaway', 'Completed', 140.00, 0.00, 0.00, 140.00, 'Paid', NULL, '2026-08-25 23:48:31', '2026-08-25 23:48:31'),
(49, 'ORD-20260826022944-706', 4, 'Dine In', 'Completed', 280.00, 0.00, 0.00, 280.00, 'Paid', NULL, '2026-08-26 00:29:44', '2026-08-26 00:29:44');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `food_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `food_id`, `quantity`, `unit_price`, `subtotal`, `created_at`) VALUES
(75, 42, 16, 1, 80.00, 80.00, '2026-08-24 03:25:39'),
(76, 42, 31, 1, 50.00, 50.00, '2026-08-24 03:25:39'),
(77, 43, 15, 1, 100.00, 100.00, '2026-08-24 04:01:24'),
(78, 44, 14, 1, 80.00, 80.00, '2026-08-24 13:37:17'),
(79, 45, 15, 1, 100.00, 100.00, '2026-08-24 13:38:27'),
(80, 46, 14, 1, 80.00, 80.00, '2026-08-24 14:38:05'),
(81, 46, 15, 1, 100.00, 100.00, '2026-08-24 14:38:05'),
(82, 47, 14, 1, 80.00, 80.00, '2026-08-24 23:33:35'),
(83, 47, 19, 1, 70.00, 70.00, '2026-08-24 23:33:35'),
(84, 47, 32, 1, 60.00, 60.00, '2026-08-24 23:33:35'),
(85, 48, 14, 1, 80.00, 80.00, '2026-08-25 23:48:31'),
(86, 48, 37, 1, 60.00, 60.00, '2026-08-25 23:48:31'),
(87, 49, 15, 1, 100.00, 100.00, '2026-08-26 00:29:44'),
(88, 49, 14, 1, 80.00, 80.00, '2026-08-26 00:29:44'),
(89, 49, 25, 1, 100.00, 100.00, '2026-08-26 00:29:44');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('Cash','Card','Mobile Money') NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Completed','Failed','Refunded') NOT NULL DEFAULT 'Completed',
  `paid_at` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `amount`, `payment_method`, `reference`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES
(39, 42, 130.00, 'Cash', 'PAY-20260824052539-6493', 'Completed', '2026-08-24 03:25:39', '2026-08-24 03:25:39', '2026-08-24 03:25:39'),
(40, 43, 100.00, 'Cash', 'PAY-20260824060124-5198', 'Completed', '2026-08-24 04:01:24', '2026-08-24 04:01:24', '2026-08-24 04:01:24'),
(41, 44, 80.00, 'Cash', 'PAY-20260824153717-8676', 'Completed', '2026-08-24 13:37:17', '2026-08-24 13:37:17', '2026-08-24 13:37:17'),
(42, 45, 100.00, 'Mobile Money', 'PAY-20260824153827-8985', 'Completed', '2026-08-24 13:38:27', '2026-08-24 13:38:27', '2026-08-24 13:38:27'),
(43, 46, 180.00, 'Cash', 'PAY-20260824163805-6610', 'Completed', '2026-08-24 14:38:05', '2026-08-24 14:38:05', '2026-08-24 14:38:05'),
(44, 47, 210.00, 'Cash', 'PAY-20260825013335-1532', 'Completed', '2026-08-24 23:33:35', '2026-08-24 23:33:35', '2026-08-24 23:33:35'),
(45, 48, 140.00, 'Mobile Money', 'PAY-20260826014831-6994', 'Completed', '2026-08-25 23:48:31', '2026-08-25 23:48:31', '2026-08-25 23:48:31'),
(46, 49, 280.00, 'Mobile Money', 'PAY-20260826022944-7679', 'Completed', '2026-08-26 00:29:44', '2026-08-26 00:29:44', '2026-08-26 00:29:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Administrator','Salesperson') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `phone`, `password`, `role`, `created_at`) VALUES
(4, 'ERIC ANANE', 'ERIC', 'anane2020@gmail.com', '0541574526', '$2y$10$8DbHOePXQ5Dbk3rvtYv2z.bo4o9GMAUAOzYYuopx5nV8twljbnax2', 'Administrator', '2026-08-17 02:18:52'),
(5, 'KOFI ANANE', 'KOFI', 'kofianane@gmail.com', '0244172090', '$2y$10$gh5bvT2dEVm09WSBNTLlmu5X7rZqjUpY0ORVUwhHkYf3KZhgAlxSW', 'Salesperson', '2026-08-19 06:42:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_category_name` (`name`);

--
-- Indexes for table `food_menu`
--
ALTER TABLE `food_menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_food_menu_category` (`category_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_name` (`name`),
  ADD KEY `idx_inventory_status` (`status`),
  ADD KEY `idx_inventory_updated_at` (`updated_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_order_number` (`order_number`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD KEY `idx_orders_payment_status` (`payment_status`),
  ADD KEY `idx_orders_created_at` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_food` (`food_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_order` (`order_id`),
  ADD KEY `idx_payments_method` (`payment_method`),
  ADD KEY `idx_payments_status` (`status`),
  ADD KEY `idx_payments_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `food_menu`
--
ALTER TABLE `food_menu`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `food_menu`
--
ALTER TABLE `food_menu`
  ADD CONSTRAINT `fk_food_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_food` FOREIGN KEY (`food_id`) REFERENCES `food_menu` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
