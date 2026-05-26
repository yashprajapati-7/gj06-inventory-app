-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql110.infinityfree.com
-- Generation Time: May 25, 2026 at 08:03 PM
-- Server version: 11.4.11-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_41656960_inventory_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'kg',
  `soh` int(11) NOT NULL DEFAULT 0,
  `csoh` int(11) NOT NULL DEFAULT 0,
  `op` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `supplier_id`, `product_name`, `unit`, `soh`, `csoh`, `op`) VALUES
(52, 2, 'Handvo Mix (GITS)', 'pcs', 30, 30, 0),
(53, 2, 'Roasted Dadiya', 'kg', 3, 3, 0),
(51, 2, 'Jaggery Powder', 'kg', 8, 32, 24),
(143, 4, 'Schwepss', 'pcs', 0, 5, 5),
(166, 2, 'Frozen green chilli ( Vadilal )', 'ctn', 0, 1, 1),
(8, 7, 'Frozen Fries', 'carton', 16, 45, 29),
(9, 1, 'Chaat Puri', 'ctn', 1, 1, 0),
(10, 1, 'Tamarind Seedless', 'ctn', 0, 2, 2),
(11, 1, 'Special Mix', 'ctn', 2, 2, 0),
(12, 1, 'Naylon Sev', 'ctn', 1, 1, 0),
(13, 1, 'Thick Sev', 'ctn', 2, 2, 0),
(14, 1, 'Masala Boondi', 'ctn', 1, 1, 0),
(15, 1, 'Black Salt', 'kg', 2, 2, 0),
(16, 1, 'Cardamom Green', 'kg', 3, 3, 0),
(17, 1, 'Mustard', 'kg', 1, 1, 0),
(18, 1, 'Hing', 'kg', 1, 1, 0),
(19, 1, 'Turmeric Powder', 'kg', 2, 2, 0),
(20, 1, 'Kashmiri Chilli Powder', 'ctn', 2, 2, 0),
(21, 1, 'Hot Chilli Powder', 'kg', 3, 3, 0),
(22, 1, 'Sesame Seed', 'kg', 1, 1, 0),
(23, 1, 'Pav Bhaji Masala', 'ctn', 1, 1, 0),
(24, 1, 'Poha Thick Medium', 'ctn', 0, 2, 2),
(25, 1, 'Mamra', 'ctn', 1, 1, 0),
(26, 1, 'Poha Masala', 'kg', 2, 2, 0),
(27, 1, 'Maggi', 'ctn', 0, 3, 3),
(28, 1, 'Maggi Masala', 'ctn', 1, 1, 0),
(146, 4, 'Black paper powder', 'pcs', 0, 1, 1),
(30, 1, 'Ginger Powder', 'kg', 2, 2, 0),
(31, 1, 'Sunflower Oil', 'kg', 0, 90, 90),
(32, 1, 'Peanut Oil', 'kg', 10, 10, 0),
(33, 1, 'Garam Masala', 'ctn', 1, 1, 0),
(34, 1, 'Coriander Powder', 'kg', 1, 1, 0),
(35, 1, 'Mango Pulp', 'ctn', 1, 1, 0),
(36, 1, 'Wagh Bakri Tea', 'ctn', 1, 2, 1),
(37, 1, 'Besan', 'ctn', 0, 2, 2),
(38, 1, 'Thumbs Up', 'ctn', 0, 2, 2),
(39, 1, 'Limca', 'ctn', 1, 1, 0),
(40, 1, 'Jeeru Soda', 'ctn', 0, 2, 2),
(41, 1, 'Chaat Masala', 'ctn', 1, 1, 0),
(42, 1, 'Nimbooz', 'ctn', 1, 1, 0),
(43, 1, 'Cumin Powder', 'kg', 3, 3, 0),
(44, 1, 'Fennel Seeds', 'kg', 1, 1, 0),
(45, 1, 'Tata Salt', 'kg', 0, 50, 50),
(46, 1, 'Black Chana', 'kg', 0, 10, 10),
(47, 1, 'White Peas', 'kg', 10, 15, 5),
(145, 2, 'Chuundo (Deep)', 'kg', 12, 12, 0),
(49, 1, 'Jaljeera Powder', 'kg', 1, 1, 0),
(50, 1, 'Schezwan Chutney 1kg bottles', 'kg', 0, 15, 15),
(54, 2, 'Spark Bhungda (Extra Long)', 'ctn', 6, 6, 0),
(55, 2, 'Bru Coffee', 'ctn', 5, 5, 0),
(56, 4, 'Cheese', 'kg', 0, 18, 18),
(57, 4, 'Butter', 'kg', 0, 12, 12),
(58, 4, 'Frankie Bread', 'pcs', 0, 15, 15),
(59, 4, 'Feta Cheese', 'pcs', 1, 2, 1),
(60, 4, 'Avocado', 'pcs', 2, 4, 2),
(61, 4, 'Water Bottles', 'ctn', 0, 1, 1),
(63, 4, 'Sugar', 'kg', 12, 20, 8),
(64, 4, 'Baking Spray', 'pcs', 15, 15, 0),
(65, 4, 'Choc Drop', 'ctn', 1, 3, 2),
(66, 4, 'Sweet Corn', 'kg', 8, 10, 2),
(67, 4, 'Green Peas', 'kg', 2, 5, 3),
(69, 4, 'Lime Juice', 'pcs', 6, 12, 6),
(71, 4, 'Choc Syrup', 'pcs', 2, 5, 3),
(73, 4, 'Cream Cheese', 'ctn', 1, 3, 2),
(74, 4, 'Pineapple Ring', 'pcs', 0, 10, 10),
(75, 4, 'Pineapple Diced', 'pcs', 0, 10, 10),
(76, 4, 'Nutella', 'kg', 0, 1, 1),
(77, 4, 'Rice Flour', 'kg', 1, 2, 1),
(78, 6, 'Coffee Beans (Elements)', 'kg', 2, 10, 8),
(79, 6, 'Chocolate Powder', 'kg', 0, 2, 2),
(80, 6, 'Chai Powder', 'kg', 0, 1, 1),
(81, 6, 'Vanilla Syrup', 'pcs', 1, 1, 0),
(82, 6, 'Caramel Syrup', 'pcs', 0, 1, 1),
(83, 6, 'Hazelnut Syrup', 'pcs', 1, 1, 0),
(84, 6, 'Decafe', 'kg', 0, 1, 1),
(85, 5, 'Brush Potatoes', 'ctn', 2, 10, 8),
(86, 5, 'Tomatoes', 'kg', 2, 9, 7),
(87, 5, 'Red Onions', 'kg', 0, 20, 20),
(88, 5, 'Cucumber', 'kg', 0, 3, 3),
(89, 5, 'Capsicums', 'kg', 0, 4, 4),
(90, 5, 'Long Melon', 'kg', 1, 2, 1),
(91, 5, 'Ginger', 'kg', 0, 5, 5),
(92, 5, 'Coriander', 'ctn', 10, 20, 10),
(93, 5, 'Mint', 'ctn', 0, 1, 1),
(94, 5, 'Spring Onions', 'ctn', 1, 3, 2),
(95, 5, 'Green Chilli', 'kg', 0, 1, 1),
(96, 5, 'Carrot', 'kg', 2, 5, 3),
(97, 5, 'Lime', 'kg', 5, 5, 0),
(98, 5, 'Beetroot', 'kg', 1, 1, 0),
(99, 5, 'Curry Leaves', 'kg', 0, 0, 0),
(100, 5, 'Peeled Garlic', 'ctn', 1, 1, 0),
(101, 5, 'Cabbage', 'pcs', 1, 1, 0),
(102, 5, 'Green Apple', 'kg', 1, 1, 0),
(103, 5, 'Red Apple', 'kg', 2, 3, 1),
(104, 5, 'Orange', 'kg', 3, 4, 1),
(105, 5, 'Pear', 'kg', 0, 1, 1),
(106, 3, 'Snack Box Large', 'ctn', 2, 2, 0),
(107, 3, 'Snack Box Regular', 'ctn', 2, 2, 0),
(108, 3, 'Burger Box', 'ctn', 2, 3, 1),
(109, 3, 'Hotdog Box', 'ctn', 1, 2, 1),
(110, 3, 'VP2 attach lid round', 'ctn', 0, 2, 2),
(111, 3, 'VP8 Round contanier', 'ctn', 1, 2, 1),
(112, 3, 'VP16 Round container', 'ctn', 1, 2, 1),
(113, 3, 'VP Lid', 'ctn', 0, 2, 2),
(114, 3, '4oz Black Coffee cup', 'ctn', 1, 1, 0),
(115, 3, '4oz White Lid', 'ctn', 2, 1, 0),
(116, 3, '6oz Black coffee cup', 'ctn', 0, 2, 2),
(117, 3, '6oz White Lid', 'ctn', 1, 1, 0),
(119, 3, 'Long Wooden Spoons', 'ctn', 0, 1, 1),
(120, 3, 'Thin Straws', 'ctn', 0, 1, 1),
(121, 3, 'Thick Straws', 'ctn', 0, 1, 1),
(122, 3, 'Pizza Box 12 Inch', 'pcs', 200, 300, 100),
(123, 3, 'Stirrer', 'ctn', 1, 1, 0),
(124, 3, '2 Cup egg Trays', 'ctn', 0, 1, 1),
(125, 3, '4 Cup egg Trays', 'ctn', 1, 1, 0),
(126, 3, 'Cup Sleeves', 'ctn', 0, 1, 1),
(127, 3, 'Clear Plastic 12oz Cup', 'ctn', 0, 1, 1),
(128, 3, 'Clear Plastic 12oz dome Lid', 'ctn', 0, 1, 1),
(129, 3, 'Clear Plastic 16oz Cup', 'ctn', 0, 1, 1),
(130, 3, 'Clear 16oz cup round dome lid', 'ctn', 1, 1, 0),
(131, 3, 'Black Small Gloves', 'ctn', 0, 1, 1),
(132, 3, 'Black Medium Gloves', 'ctn', 2, 1, 0),
(133, 3, '25 UM LDPE BAGS (45.5cmx25.5cm)', 'ctn', 12, 12, 0),
(134, 3, 'Lunch Napkins 1ply', 'ctn', 0, 2, 2),
(135, 3, 'Foil Paper roll (300mm Width )', 'ctn', 1, 1, 0),
(136, 3, 'Cling Wrap 60cm x 33cm', 'ctn', 1, 1, 0),
(137, 3, 'White Paper Bags Small', 'pcs', 0, 150, 150),
(138, 3, 'Frankie B&W paper', 'pcs', 800, 200, 0),
(139, 3, 'Uber bags (XTRA SMALL) with handle', 'ctn', 0, 1, 1),
(141, 3, 'Poly Carry Bags Small', 'ctn', 0, 1, 1),
(142, 3, 'Poly Bags Medium', 'ctn', 0, 1, 1),
(162, 3, 'Burger sticks ( 12cm Bamboo Oars )', 'ctn', 2, 1, 0),
(144, 4, 'Sprite 250ml can', 'pcs', 12, 12, 0),
(147, 3, 'Dishwashing Liquid', 'kg', 1, 10, 9),
(149, 3, 'Multipurpose spray and wipe', 'kg', 0, 5, 5),
(150, 3, 'Floor cleaner liquid', 'kg', 0, 10, 10),
(151, 3, 'Degreaser oven grill liquid', 'kg', 0, 5, 5),
(152, 3, 'Bleach liquid', 'kg', 0, 5, 5),
(153, 3, 'Sanitizer gel liquid', 'kg', 0, 5, 5),
(154, 3, 'Garbage bags Black (240L)', 'ctn', 1, 1, 0),
(155, 3, 'Garbage bag Black (60L)', 'ctn', 1, 1, 0),
(156, 3, 'Heavy duty chucks', 'ctn', 0, 1, 1),
(157, 3, 'Tax invoice book ( auto double copy )', 'pcs', 5, 5, 0),
(158, 3, 'Supa bowl 500ml', 'ctn', 1, 1, 0),
(159, 3, 'Supa bowl transparent lid 500ml', 'ctn', 0, 1, 1),
(160, 3, 'White reusable dinner spoon', 'ctn', 0, 1, 1),
(161, 3, 'White resuable dinner forks', 'ctn', 16, 1, 0),
(163, 3, 'Mocktail sticks (12cm Bamboo looped screwers)', 'ctn', 2, 1, 0),
(164, 3, 'Mocktails sticks ( 8cm Bamboo looped skewers)', 'ctn', 1, 1, 0),
(165, 3, 'Plastic ziploack ( 10cmx7.5cm )', 'ctn', 0, 1, 1),
(168, 2, 'Black Chana', 'kg', 10, 10, 0),
(169, 2, 'Malabar Mixture ( Extra hot )', 'kg', 20, 20, 0),
(167, 2, 'Gala broom ( Savrani )', 'pcs', 2, 2, 0),
(170, 2, 'Cinnamon powder', 'kg', 1, 1, 0),
(171, 5, 'Thick Green chilli', 'kg', 1, 1, 0),
(172, 3, 'Uber bags (MEDUIM) with handle', 'ctn', 0, 1, 1),
(173, 3, 'Uber Bags (LARGE) with handle', 'ctn', 0, 1, 1),
(174, 3, '12oz Black coffee cup', 'ctn', 0, 1, 1),
(175, 3, '12oz LID (coffee cup)', 'ctn', 1, 1, 0),
(176, 3, 'Cash register (Thermal paper roll) 80mmx80mm', 'ctn', 0, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_name` varchar(120) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_name`) VALUES
(1, 'Indian grocery'),
(2, 'Local Indian Grocery'),
(3, 'Disposal'),
(4, 'Supermarket'),
(5, 'Fruit & Veggies'),
(6, 'Coffee'),
(7, 'Frozen goods');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'staff'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`) VALUES
(1, 'GJ06', 'GJ06', '$2y$10$VoDq3Ngp39WiK5a6VrWuRO57nQ/eqeNN9.2PIvB4v6ewOqMjp0Aeu', 'staff'),
(2, 'Yashp', 'Yashp', '$2y$10$fE.C.WqI7HTPJRWhundVsOBeWakqSte5ini80nyHt9sy7Sapww9Cm', 'staff'),
(3, 'Khushi12', 'Khushi12', '$2y$10$7gHqNV/f8tGwivqqoiY6W.hA.9Sxg5LEBF0hDxqyuIzvedVyuBNOu', 'staff'),
(4, 'Him', 'Him', '$2y$10$ybyXE.VXYGNAMamkxcOwfu7dfZjEJKAt5TwCEDQuVDVEnmjozbbza', 'staff'),
(5, 'Main Admin', 'admin', '$2y$10$jNSF/3uDdPVx88ZwNTffnOs/cXH8VM/lrLv1UYH/9tsqc9GY8HVnG', 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `user_supplier_access`
--

CREATE TABLE `user_supplier_access` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `user_supplier_access`
--

INSERT INTO `user_supplier_access` (`id`, `user_id`, `supplier_id`) VALUES
(28, 2, 2),
(2, 3, 6),
(3, 3, 3),
(4, 3, 7),
(5, 3, 5),
(6, 3, 1),
(7, 3, 2),
(8, 3, 4),
(24, 4, 6),
(10, 1, 6),
(11, 1, 3),
(12, 1, 7),
(13, 1, 5),
(14, 1, 1),
(15, 1, 2),
(16, 1, 4),
(27, 2, 5),
(26, 2, 6),
(25, 4, 3);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_supplier_access`
--
ALTER TABLE `user_supplier_access`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=177;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_supplier_access`
--
ALTER TABLE `user_supplier_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
