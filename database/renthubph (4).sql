-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 17, 2025 at 05:58 AM
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
-- Database: `renthubph`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `BookingID` int(11) NOT NULL,
  `ProductID` int(11) DEFAULT NULL,
  `RenterID` int(11) DEFAULT NULL,
  `OwnerID` int(11) DEFAULT NULL,
  `Book_StartDate` datetime NOT NULL,
  `Book_EndDate` datetime NOT NULL,
  `Book_TotalAmount` decimal(10,2) NOT NULL,
  `Book_SecurityDeposit` decimal(10,2) DEFAULT NULL,
  `Book_DeliveryFee` decimal(8,2) DEFAULT NULL,
  `Book_Status` varchar(50) DEFAULT 'Pending',
  `Book_PickupType` varchar(20) DEFAULT NULL,
  `Book_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Book_UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Book_CancelReason` text DEFAULT NULL,
  `Book_Notes` text DEFAULT NULL,
  `Book_Damaged` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`BookingID`, `ProductID`, `RenterID`, `OwnerID`, `Book_StartDate`, `Book_EndDate`, `Book_TotalAmount`, `Book_SecurityDeposit`, `Book_DeliveryFee`, `Book_Status`, `Book_PickupType`, `Book_CreatedAt`, `Book_UpdatedAt`, `Book_CancelReason`, `Book_Notes`, `Book_Damaged`) VALUES
(1, 2, 4, 3, '2025-09-28 08:31:00', '2025-09-29 08:31:00', 2700.00, 2500.00, 0.00, 'Completed', 'Pickup', '2025-09-25 00:31:55', '2025-09-25 09:17:01', NULL, '{\"payment_method\":\"Bank Transfer\",\"renter_name\":\"Gabrielle Reyes\",\"renter_phone\":\"+639992223324\",\"renter_email\":\"gab@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(2, 2, 4, 3, '2025-09-27 18:56:00', '2025-09-28 18:56:00', 2705.00, 2500.00, 5.00, 'Completed', 'Delivery', '2025-09-25 10:57:31', '2025-09-25 11:58:37', NULL, '{\"payment_method\":\"Bank Transfer\",\"renter_name\":\"Gabrielle Reyes\",\"renter_phone\":\"+639992223324\",\"renter_email\":\"gab@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"delivery\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(3, 2, 4, 3, '2025-09-30 20:04:00', '2025-10-01 20:04:00', 2705.00, 2500.00, 5.00, 'Completed', 'Delivery', '2025-09-25 12:04:39', '2025-09-25 12:08:35', NULL, '{\"payment_method\":\"GCash\",\"renter_name\":\"Gabrielle Reyes\",\"renter_phone\":\"+639992223324\",\"renter_email\":\"gab@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"delivery\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(4, 2, 4, 3, '2025-09-25 20:08:00', '2025-09-26 20:10:00', 2700.00, 2500.00, 0.00, 'Completed', 'Pickup', '2025-09-25 12:10:32', '2025-09-25 12:11:51', NULL, '{\"payment_method\":\"Maya\",\"renter_name\":\"Gabrielle Reyes\",\"renter_phone\":\"+639992223324\",\"renter_email\":\"gab@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(5, 2, 4, 3, '2025-09-27 02:06:00', '2025-09-28 02:06:00', 2700.00, 2500.00, 0.00, 'Completed', 'Pickup', '2025-09-25 18:06:43', '2025-09-25 18:51:40', NULL, '{\"payment_method\":\"Maya\",\"renter_name\":\"Gabrielle Reyes\",\"renter_phone\":\"+639992223324\",\"renter_email\":\"gab@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"hahahahahahah pakiingatan po ngani\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(6, 2, 4, 3, '2025-09-27 03:03:00', '2025-09-28 03:03:00', 2700.00, 2500.00, 0.00, 'Completed', 'Pickup', '2025-09-25 19:03:49', '2025-09-25 19:06:28', NULL, '{\"payment_method\":\"GCash\",\"renter_name\":\"Gabrielle Reyes\",\"renter_phone\":\"+639992223324\",\"renter_email\":\"gab@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(7, 2, 4, 3, '2025-09-27 11:57:00', '2025-09-30 11:57:00', 2900.00, 2500.00, 0.00, 'Completed', 'Pickup', '2025-09-26 03:59:45', '2025-09-26 04:04:00', NULL, '{\"payment_method\":\"Maya\",\"renter_name\":\"Gabrielle Reyes\",\"renter_phone\":\"+639992223324\",\"renter_email\":\"gab@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"sa may kanto lang po kami, thanks\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(8, 10, 4, 3, '2025-09-27 20:44:00', '2025-10-03 20:44:00', 8900.00, 4000.00, 0.00, 'Completed', 'Pickup', '2025-09-26 12:45:03', '2025-09-26 15:24:16', NULL, '{\"payment_method\":\"Maya\",\"renter_name\":\"Gabrielle Reyes\",\"renter_phone\":\"+639992223324\",\"renter_email\":\"gab@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"ingatan mo sya tol\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(9, 10, 4, 3, '2025-09-28 11:11:00', '2025-10-02 11:11:00', 7500.00, 4000.00, 0.00, 'Cancelled', 'Pickup', '2025-09-27 03:11:41', '2025-09-27 05:32:32', 'ayaw ko na', '{\"payment_method\":\"GCash\",\"renter_name\":\"Gabrielle Reyes\",\"renter_phone\":\"+639992223324\",\"renter_email\":\"gab@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(10, 9, 4, 3, '2025-09-29 13:35:00', '2025-09-30 13:35:00', 1150.00, 1000.00, 0.00, 'Completed', 'Pickup', '2025-09-27 05:36:18', '2025-09-27 11:29:40', NULL, '{\"payment_method\":\"Maya\",\"renter_name\":\"Gabrielle Reyes\",\"renter_phone\":\"+639992223324\",\"renter_email\":\"gab@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 1),
(11, 2, 6, 3, '2025-09-29 19:45:00', '2025-09-30 19:45:00', 2700.00, 2500.00, 0.00, 'Completed', 'Pickup', '2025-09-27 11:46:15', '2025-09-27 11:47:44', NULL, '{\"payment_method\":\"Bank Transfer\",\"renter_name\":\"Iya Atayde\",\"renter_phone\":\"+639622360874\",\"renter_email\":\"iya@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 1),
(12, 9, 6, 3, '2025-09-29 22:48:00', '2025-09-30 22:48:00', 1150.00, 1000.00, 0.00, 'Completed', 'Pickup', '2025-09-27 14:50:15', '2025-09-27 14:53:43', NULL, '{\"payment_method\":\"Bank Transfer\",\"renter_name\":\"Iya Atayde\",\"renter_phone\":\"+639622360874\",\"renter_email\":\"iya@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(13, 6, 6, 3, '2025-09-29 14:47:00', '2025-10-01 14:47:00', 6176.00, 2000.00, 0.00, 'Completed', 'Pickup', '2025-09-28 06:49:11', '2025-09-28 07:04:29', NULL, '{\"payment_method\":\"Maya\",\"renter_name\":\"Iya Atayde\",\"renter_phone\":\"+639622360874\",\"renter_email\":\"iya@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"ingatan mo sya\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(14, 12, 6, 5, '2025-09-29 18:41:00', '2025-09-30 18:41:00', 6000.00, 5000.00, 0.00, 'Pending', 'Pickup', '2025-09-28 10:42:07', '2025-09-28 10:42:07', NULL, '{\"payment_method\":\"GCash\",\"renter_name\":\"Iya Atayde\",\"renter_phone\":\"+639622360874\",\"renter_email\":\"iya@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(15, 8, 6, 3, '2025-10-07 17:45:00', '2025-10-08 17:45:00', 1500.00, 500.00, 0.00, 'Completed', 'Pickup', '2025-10-05 09:45:45', '2025-10-15 17:31:07', NULL, '{\"payment_method\":\"Bank Transfer\",\"renter_name\":\"Iya Atayde\",\"renter_phone\":\"+639622360874\",\"renter_email\":\"iya@gmail.com\",\"emergency_contact\":\"88888888888\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"wala potanginamo\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(16, 6, 6, 3, '2025-10-11 18:42:00', '2025-10-12 18:42:00', 4088.00, 2000.00, 0.00, 'Completed', 'Pickup', '2025-10-05 10:43:01', '2025-10-15 17:30:22', NULL, '{\"payment_method\":\"Maya\",\"renter_name\":\"Iya Atayde\",\"renter_phone\":\"+639622360874\",\"renter_email\":\"iya@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 1),
(17, 4, 6, 3, '2025-10-16 20:08:00', '2025-10-18 20:08:00', 7160.00, 5000.00, 0.00, 'Completed', 'Pickup', '2025-10-05 12:08:45', '2025-10-15 17:29:33', NULL, '{\"payment_method\":\"Cash\",\"renter_name\":\"Iya Atayde\",\"renter_phone\":\"+639622360874\",\"renter_email\":\"iya@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(18, 13, 6, 3, '2025-10-18 01:56:00', '2025-10-19 01:56:00', 1600.00, 200.00, 0.00, 'Cancelled', 'Pickup', '2025-10-15 17:57:05', '2025-10-15 18:10:14', 'ayoko na', '{\"payment_method\":\"Maya\",\"renter_name\":\"Iya Atayde\",\"renter_phone\":\"+639622360874\",\"renter_email\":\"iya@gmail.com\",\"emergency_contact\":\"88888888888\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"ghghghgh\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(19, 10, 6, 3, '2025-10-17 02:16:00', '2025-10-19 02:16:00', 6100.00, 4000.00, 0.00, 'Cancelled', 'Pickup', '2025-10-15 18:17:13', '2025-10-15 18:28:02', 'la pira', '{\"payment_method\":\"GCash\",\"renter_name\":\"Iya Atayde\",\"renter_phone\":\"+639622360874\",\"renter_email\":\"iya@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(20, 11, 6, 3, '2025-10-18 10:10:00', '2025-10-20 10:10:00', 4820.00, 500.00, 0.00, 'Pending', 'Pickup', '2025-10-17 02:10:44', '2025-10-17 02:10:44', NULL, '{\"payment_method\":\"GCash\",\"renter_name\":\"Iya Atayde\",\"renter_phone\":\"+639622360874\",\"renter_email\":\"iya@gmail.com\",\"emergency_contact\":\"\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Lipa, Batangas, 4217\",\"pickup_delivery\":\"pickup\",\"special_instructions\":\"\",\"payment_account_name\":\"\",\"payment_account_number\":\"\",\"terms_agreement\":\"on\"}', 0),
(21, 12, 3, 5, '2025-10-18 10:43:00', '2025-10-19 10:43:00', 6000.00, 5000.00, 0.00, 'Cancelled', 'Pickup', '2025-10-17 02:44:00', '2025-10-17 02:46:57', 'dsfsdfsdf', '{\"payment_method\":\"GCash\",\"renter_name\":\"Theabels Reyes\",\"renter_phone\":\"+639992223332\",\"renter_email\":\"raltheagabrielle@gmail.com\",\"renter_address\":\"0278, Rosas St, Muntingpulo, Banay-Banay, Lipa, Batangas, 4217\",\"emergency_contact\":\"\",\"special_instructions\":\"\"}', 0);

-- --------------------------------------------------------

--
-- Table structure for table `booking_status_history`
--

CREATE TABLE `booking_status_history` (
  `StatusHistoryID` int(11) NOT NULL,
  `BookingID` int(11) DEFAULT NULL,
  `BSH_OldStatus` varchar(50) DEFAULT NULL,
  `BSH_NewStatus` varchar(50) NOT NULL,
  `BSH_ChangedBy` int(11) DEFAULT NULL,
  `BSH_ChangeReason` text DEFAULT NULL,
  `BSH_ChangedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_status_history`
--

INSERT INTO `booking_status_history` (`StatusHistoryID`, `BookingID`, `BSH_OldStatus`, `BSH_NewStatus`, `BSH_ChangedBy`, `BSH_ChangeReason`, `BSH_ChangedAt`) VALUES
(1, 1, 'Pending', 'Confirmed', 3, '', '2025-09-25 09:14:36'),
(2, 1, 'Active', 'In Progress', 3, '', '2025-09-25 09:16:46'),
(3, 1, 'In Progress', 'Completed', 3, '', '2025-09-25 09:17:01'),
(4, 2, 'Pending', 'Confirmed', 3, '', '2025-09-25 11:24:11'),
(5, 2, 'Active', 'In Progress', 3, '', '2025-09-25 11:58:21'),
(6, 2, 'In Progress', 'Completed', 3, '', '2025-09-25 11:58:37'),
(7, 3, 'Pending', 'Confirmed', 3, '', '2025-09-25 12:04:55'),
(8, 3, 'Active', 'In Progress', 3, '', '2025-09-25 12:06:15'),
(9, 3, 'In Progress', 'Completed', 3, '', '2025-09-25 12:08:35'),
(10, 4, 'Pending', 'Confirmed', 3, '', '2025-09-25 12:11:04'),
(11, 4, 'Active', 'In Progress', 3, '', '2025-09-25 12:11:46'),
(12, 4, 'In Progress', 'Completed', 3, '', '2025-09-25 12:11:51'),
(13, 5, 'Pending', 'Confirmed', 3, '', '2025-09-25 18:28:27'),
(14, 5, 'Active', 'In Progress', 3, '', '2025-09-25 18:41:12'),
(15, 5, 'In Progress', 'Completed', 3, '', '2025-09-25 18:51:40'),
(16, 6, 'Pending', 'Confirmed', 3, '', '2025-09-25 19:04:05'),
(17, 6, 'Active', 'In Progress', 3, '', '2025-09-25 19:06:23'),
(18, 6, 'In Progress', 'Completed', 3, '', '2025-09-25 19:06:28'),
(19, 7, 'Pending', 'Confirmed', 3, '', '2025-09-26 04:02:26'),
(20, 7, 'Active', 'In Progress', 3, '', '2025-09-26 04:03:42'),
(21, 7, 'In Progress', 'Completed', 3, '', '2025-09-26 04:04:00'),
(22, 8, 'Pending', 'Confirmed', 3, '', '2025-09-26 15:21:18'),
(23, 8, 'Active', 'In Progress', 3, '', '2025-09-26 15:24:13'),
(24, 8, 'In Progress', 'Completed', 3, '', '2025-09-26 15:24:16'),
(25, 10, 'Pending', 'Confirmed', 3, '', '2025-09-27 06:58:06'),
(26, 10, 'Active', 'In Progress', 3, '', '2025-09-27 11:18:13'),
(27, 10, 'In Progress', 'Completed', 3, '', '2025-09-27 11:29:40'),
(28, 11, 'Pending', 'Confirmed', 3, '', '2025-09-27 11:46:44'),
(29, 11, 'Active', 'In Progress', 3, '', '2025-09-27 11:47:22'),
(30, 11, 'In Progress', 'Completed', 3, '', '2025-09-27 11:47:44'),
(31, 12, 'Pending', 'Confirmed', 3, '', '2025-09-27 14:50:52'),
(32, 12, 'Active', 'In Progress', 3, '', '2025-09-27 14:53:31'),
(33, 12, 'In Progress', 'Completed', 3, '', '2025-09-27 14:53:43'),
(34, 13, 'Pending', 'Confirmed', 3, '', '2025-09-28 06:54:23'),
(35, 13, 'Active', 'In Progress', 3, '', '2025-09-28 06:56:28'),
(36, 13, 'In Progress', 'Completed', 3, '', '2025-09-28 07:04:29'),
(37, 15, 'Pending', 'Confirmed', 3, '', '2025-10-05 10:41:11'),
(38, 17, 'Pending', 'Confirmed', 3, '', '2025-10-15 17:28:15'),
(39, 17, 'Active', 'In Progress', 3, '', '2025-10-15 17:29:24'),
(40, 17, 'In Progress', 'Completed', 3, '', '2025-10-15 17:29:33'),
(41, 16, 'Pending', 'Confirmed', 3, '', '2025-10-15 17:29:44'),
(42, 16, 'Active', 'In Progress', 3, '', '2025-10-15 17:30:17'),
(43, 16, 'In Progress', 'Completed', 3, '', '2025-10-15 17:30:22'),
(44, 15, 'Active', 'In Progress', 3, '', '2025-10-15 17:31:04'),
(45, 15, 'In Progress', 'Completed', 3, '', '2025-10-15 17:31:07'),
(46, 19, 'Pending', 'Confirmed', 3, '', '2025-10-15 18:19:22');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `CategoryID` int(11) NOT NULL,
  `Cat_Name` varchar(100) NOT NULL,
  `Cat_Description` text DEFAULT NULL,
  `Cat_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `ParentCategoryID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`CategoryID`, `Cat_Name`, `Cat_Description`, `Cat_CreatedAt`, `ParentCategoryID`) VALUES
(1, 'Camera', 'yeahhhh', '2025-09-14 04:52:28', 1),
(2, 'Action Camera', 'ngek ngok', '2025-09-14 04:52:50', 1),
(3, 'Printing Cam', 'ahsgdjsdyti', '2025-09-14 04:53:12', 1),
(4, 'Back Drop', 'Magnda', '2025-09-26 13:00:08', 2);

-- --------------------------------------------------------

--
-- Table structure for table `chats`
--

CREATE TABLE `chats` (
  `ChatID` int(11) NOT NULL,
  `Chat_ParticipantOne` int(11) DEFAULT NULL,
  `Chat_ParticipantTwo` int(11) DEFAULT NULL,
  `ProductID` int(11) DEFAULT NULL,
  `Chat_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Chat_LastMessageAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `MessageID` int(11) NOT NULL,
  `ChatID` int(11) DEFAULT NULL,
  `SenderID` int(11) DEFAULT NULL,
  `CM_MessageContent` text NOT NULL,
  `CM_MessageType` varchar(20) DEFAULT 'Text',
  `CM_FilePath` varchar(255) DEFAULT NULL,
  `CM_IsRead` tinyint(1) DEFAULT 0,
  `CM_SentAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_payments`
--

CREATE TABLE `commission_payments` (
  `CommissionID` int(11) NOT NULL,
  `BookingID` int(11) DEFAULT NULL,
  `OwnerID` int(11) DEFAULT NULL,
  `Comm_GrossAmount` decimal(10,2) NOT NULL,
  `Comm_Rate` decimal(5,2) NOT NULL,
  `Comm_Amount` decimal(10,2) NOT NULL,
  `Comm_NetAmount` decimal(10,2) NOT NULL,
  `Comm_Status` varchar(50) DEFAULT 'Pending',
  `Comm_PaymentMethod` varchar(50) DEFAULT NULL,
  `Comm_PayoutDate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Comm_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commission_payments`
--

INSERT INTO `commission_payments` (`CommissionID`, `BookingID`, `OwnerID`, `Comm_GrossAmount`, `Comm_Rate`, `Comm_Amount`, `Comm_NetAmount`, `Comm_Status`, `Comm_PaymentMethod`, `Comm_PayoutDate`, `Comm_CreatedAt`) VALUES
(1, 17, 3, 7160.00, 2.50, 179.00, 6981.00, 'Completed', NULL, '2025-10-15 17:29:33', '2025-10-15 17:29:33'),
(2, 16, 3, 4088.00, 2.50, 102.20, 3985.80, 'Completed', NULL, '2025-10-15 17:30:22', '2025-10-15 17:30:22'),
(3, 15, 3, 1500.00, 2.50, 37.50, 1462.50, 'Completed', NULL, '2025-10-15 17:31:07', '2025-10-15 17:31:07');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `ConversationID` int(11) NOT NULL,
  `User1ID` int(11) NOT NULL,
  `User2ID` int(11) NOT NULL,
  `ProductID` int(11) DEFAULT NULL,
  `Conv_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Conv_LastMessageAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`ConversationID`, `User1ID`, `User2ID`, `ProductID`, `Conv_CreatedAt`, `Conv_LastMessageAt`) VALUES
(1, 4, 3, 2, '2025-09-25 00:31:55', '2025-09-27 10:49:57'),
(2, 4, 3, 10, '2025-09-26 12:45:03', '2025-09-26 12:45:03'),
(3, 4, 3, 9, '2025-09-27 05:36:18', '2025-09-27 05:36:18'),
(4, 6, 3, 2, '2025-09-27 11:46:15', '2025-09-27 11:46:15'),
(5, 6, 3, 9, '2025-09-27 14:50:15', '2025-09-28 06:10:06'),
(6, 6, 3, 6, '2025-09-28 06:49:11', '2025-09-28 06:49:11'),
(7, 6, 5, 12, '2025-09-28 10:42:07', '2025-09-28 10:42:07'),
(8, 6, 3, 8, '2025-10-05 09:45:45', '2025-10-05 09:45:45'),
(9, 6, 3, 4, '2025-10-05 12:08:45', '2025-10-05 12:08:45'),
(10, 6, 3, 13, '2025-10-15 17:57:05', '2025-10-16 04:36:29'),
(11, 6, 3, 10, '2025-10-15 18:17:13', '2025-10-16 04:34:33'),
(12, 6, 3, 11, '2025-10-17 02:10:44', '2025-10-17 02:10:44');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `FavoriteID` int(11) NOT NULL,
  `UserID` int(11) DEFAULT NULL,
  `ProductID` int(11) DEFAULT NULL,
  `Fav_AddedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`FavoriteID`, `UserID`, `ProductID`, `Fav_AddedAt`) VALUES
(1, 4, 2, '2025-09-25 09:34:42'),
(4, 3, 12, '2025-10-17 02:28:23');

-- --------------------------------------------------------

--
-- Table structure for table `flag_reports`
--

CREATE TABLE `flag_reports` (
  `FlagID` int(11) NOT NULL,
  `ReporterID` int(11) NOT NULL,
  `ProductID` int(11) DEFAULT NULL,
  `OwnerID` int(11) DEFAULT NULL,
  `FlagType` enum('product','owner') NOT NULL,
  `Reason` text NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `flag_reports`
--

INSERT INTO `flag_reports` (`FlagID`, `ReporterID`, `ProductID`, `OwnerID`, `FlagType`, `Reason`, `CreatedAt`) VALUES
(1, 6, 9, 3, 'owner', 'masyadong maganda', '2025-09-28 10:50:12'),
(2, 6, 8, 3, 'product', 'masarap kasi', '2025-09-28 10:53:25'),
(3, 6, 8, 3, 'owner', 'masarap', '2025-09-28 10:57:13'),
(4, 6, 9, 3, 'product', 'masarap', '2025-09-28 11:00:12'),
(5, 6, 9, 3, 'product', 'masarap', '2025-09-28 11:00:18'),
(6, 6, 9, 3, 'owner', 'mjshdkuwefkuawyrf', '2025-09-28 11:02:02'),
(7, 6, 8, 3, 'owner', 'jhytuyt', '2025-09-28 11:03:37'),
(8, 6, 8, 3, 'owner', 'jasdhkqukquy', '2025-09-28 11:05:26'),
(9, 6, 8, 3, 'product', 'masarap masarap', '2025-09-28 11:09:00'),
(10, 6, 5, 3, 'product', 'jxdfakshdfkuewf', '2025-09-28 11:14:54'),
(11, 6, 9, 3, 'product', 'jshdukwduwye', '2025-09-28 11:16:07'),
(12, 6, 8, 3, 'owner', 'jgjy', '2025-09-28 11:17:02'),
(13, 6, 8, 3, 'product', 'dili maaare', '2025-10-05 09:15:24'),
(14, 6, 8, 3, 'owner', 'pogi masyado', '2025-10-05 09:15:48'),
(15, 6, 9, 3, 'product', 'hahahaha maganda', '2025-10-05 09:24:06'),
(16, 6, 2, 3, 'product', 'hahaha', '2025-10-05 09:38:13'),
(17, 6, 5, 3, 'product', 'hahahahahaha', '2025-10-05 09:59:20');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `MessageID` int(11) NOT NULL,
  `ConversationID` int(11) NOT NULL,
  `SenderID` int(11) NOT NULL,
  `Msg_Content` text NOT NULL,
  `Msg_IsRead` tinyint(1) DEFAULT 0,
  `Msg_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`MessageID`, `ConversationID`, `SenderID`, `Msg_Content`, `Msg_IsRead`, `Msg_CreatedAt`) VALUES
(1, 1, 3, 'hello', 1, '2025-09-27 03:18:41'),
(2, 1, 4, 'kamusta ka po?', 1, '2025-09-27 03:19:15'),
(3, 1, 3, 'hi', 1, '2025-09-27 10:49:57'),
(4, 5, 3, 'hello', 1, '2025-09-28 06:09:32'),
(5, 5, 6, 'ano po concern nila?', 1, '2025-09-28 06:09:51'),
(6, 5, 6, 'hala nag double na naman', 1, '2025-09-28 06:10:06'),
(7, 11, 3, 'hello', 1, '2025-10-16 04:34:33'),
(8, 11, 3, 'hello', 1, '2025-10-16 04:34:33'),
(9, 10, 3, 'hi', 1, '2025-10-16 04:35:43'),
(10, 10, 6, 'kamusta ka po?', 1, '2025-10-16 04:36:06'),
(11, 10, 6, 'owemji bat ganan', 1, '2025-10-16 04:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `NotificationID` int(11) NOT NULL,
  `UserID` int(11) DEFAULT NULL,
  `Not_Type` varchar(50) DEFAULT NULL,
  `Not_Title` varchar(255) NOT NULL,
  `Not_Message` text NOT NULL,
  `Not_RelatedID` int(11) DEFAULT NULL,
  `Not_IsRead` tinyint(1) DEFAULT 0,
  `Not_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`NotificationID`, `UserID`, `Not_Type`, `Not_Title`, `Not_Message`, `Not_RelatedID`, `Not_IsRead`, `Not_CreatedAt`) VALUES
(1, 3, 'booking', 'New Booking Request', 'You have a new booking request for your product: boogsh.', 10, 1, '2025-09-27 03:11:41'),
(2, 3, 'booking_cancelled', 'Booking Cancelled', 'A booking for your product: boogsh has been cancelled by the renter.', 9, 1, '2025-09-27 05:32:32'),
(3, 3, 'booking', 'New Booking Request', 'You have a new booking request for your product: ball.', 9, 1, '2025-09-27 05:36:18'),
(4, 4, 'booking', 'Booking Accepted', 'Your booking for product: ball has been accepted.', 10, 0, '2025-09-27 06:58:06'),
(5, 3, 'payment_confirmed', 'Payment Confirmed', 'A renter has confirmed payment for your product: ball.', 10, 1, '2025-09-27 11:15:09'),
(6, 4, 'rental_started', 'Rental Started', 'Your rental for product: ball has started.', 10, 0, '2025-09-27 11:18:13'),
(7, 4, 'booking_completed', 'Booking Completed (With Damage)', 'Your booking for product: ball has been marked as completed by the owner. \nPlease note: The owner reported damage to the product. \nThe security deposit will NOT be refunded. \nThank you for using RentHub!', 10, 0, '2025-09-27 11:29:40'),
(8, 3, 'booking', 'New Booking Request', 'You have a new booking request for your product: Instax.', 2, 1, '2025-09-27 11:46:15'),
(9, 6, 'booking', 'Booking Accepted', 'Your booking for product: Instax has been accepted.', 11, 1, '2025-09-27 11:46:44'),
(10, 3, 'payment_confirmed', 'Payment Confirmed', 'A renter has confirmed payment for your product: Instax.', 11, 1, '2025-09-27 11:47:08'),
(11, 6, 'rental_started', 'Rental Started', 'Your rental for product: Instax has started.', 11, 1, '2025-09-27 11:47:23'),
(12, 6, 'booking_completed', 'Booking Completed (With Damage)', 'Your booking for product: Instax has been marked as completed by the owner.<br>Please note: The owner reported damage to the product.<br>The security deposit will NOT be refunded.<br>Thank you for using RentHub!', 11, 1, '2025-09-27 11:47:44'),
(13, 3, 'review', 'New Review Received', 'Your product \"ball\" received a new review: \"ihhh ang angas\" (Rating: 3/5)', 10, 1, '2025-09-27 13:04:43'),
(14, 4, 'review_response', 'Owner Responded to Your Review', 'The owner responded to your review for product \"ball\": \"thank you so much po\"', 10, 0, '2025-09-27 13:11:35'),
(15, 6, 'review_response', 'Owner Responded to Your Review', 'The owner responded to your review for product \"Instax\".', 9, 1, '2025-09-27 13:25:21'),
(16, 3, 'booking', 'New Booking Request', 'You have a new booking request for your product: ball.', 9, 1, '2025-09-27 14:50:15'),
(17, 6, 'booking', 'Booking Accepted', 'Your booking for product: ball has been accepted.', 12, 1, '2025-09-27 14:50:52'),
(18, 3, 'payment_confirmed', 'Payment Confirmed', 'A renter has confirmed payment for your product: ball.', 12, 1, '2025-09-27 14:53:02'),
(19, 6, 'rental_started', 'Rental Started', 'Your rental for product: ball has started.', 12, 1, '2025-09-27 14:53:31'),
(20, 6, 'booking_completed', 'Booking Completed', 'Your booking for product: ball has been marked as completed by the owner.<br>Your security deposit will be refunded within 2-3 working days.<br>Thank you for using RentHub!', 12, 1, '2025-09-27 14:53:43'),
(21, 3, 'review', 'New Review Received', 'Your product \"ball\" received a new review: \"galing angas lopit\" (Rating: 5/5)', 12, 1, '2025-09-27 15:02:30'),
(22, 6, 'review_response', 'Owner Responded to Your Review', 'The owner responded to your review for product \"ball\".', 11, 1, '2025-09-27 15:02:46'),
(23, 4, 'review_response', 'Owner Responded to Your Review', 'The owner responded to your review for product \"Instax\".', 8, 0, '2025-09-27 15:30:00'),
(24, 4, 'review_response', 'Owner Responded to Your Review', 'The owner responded to your review for product \"boogsh\".', 7, 0, '2025-09-27 15:30:11'),
(25, 3, 'Refund Request', 'Refund Requested', 'Iya Atayde has requested a refund for product: ILY', 11, 1, '2025-09-28 06:07:57'),
(26, 3, 'booking', 'New Booking Request', 'You have a new booking request for your product: ganda.', 6, 1, '2025-09-28 06:49:11'),
(27, 6, 'booking', 'Booking Accepted', 'Your booking for product: ganda has been accepted.', 13, 1, '2025-09-28 06:54:23'),
(28, 3, 'payment_confirmed', 'Payment Confirmed', 'A renter has confirmed payment for your product: ganda.', 13, 1, '2025-09-28 06:56:13'),
(29, 6, 'rental_started', 'Rental Started', 'Your rental for product: ganda has started.', 13, 1, '2025-09-28 06:56:28'),
(30, 6, 'booking_completed', 'Booking Completed', 'Your booking for product: ganda has been marked as completed by the owner.<br>Your security deposit will be refunded within 2-3 working days.<br>Thank you for using RentHub!', 13, 1, '2025-09-28 07:04:29'),
(31, 3, 'review', 'New Review Received', 'Your product \"ganda\" received a new review: \"bangis lods\" (Rating: 4/5)', 13, 1, '2025-09-28 09:30:00'),
(32, 3, 'review', 'Review Updated', 'Your product \"ganda\" received an updated review: \"eh eh naman\" (Rating: 1/5)', 13, 1, '2025-09-28 10:05:05'),
(33, 5, 'booking', 'New Booking Request', 'You have a new booking request for your product: Bambagushkalabab.', 12, 1, '2025-09-28 10:42:07'),
(34, 3, 'booking', 'New Booking Request', 'You have a new booking request for your product: ako ay isang model.', 8, 1, '2025-10-05 09:45:45'),
(35, 6, 'booking', 'Booking Accepted', 'Your booking for product: ako ay isang model has been accepted.', 15, 1, '2025-10-05 10:41:11'),
(36, 3, 'booking', 'New Booking Request', 'You have a new booking request for your product: ganda.', 6, 1, '2025-10-05 10:43:01'),
(37, 3, 'booking', 'New Booking Request', 'You have a new booking request for your product: thea.', 4, 1, '2025-10-05 12:08:45'),
(38, 6, 'booking', 'Booking Accepted', 'Your booking for product: thea has been accepted.', 17, 1, '2025-10-15 17:28:15'),
(39, 3, 'payment_confirmed', 'Payment Confirmed', 'A renter has confirmed payment for your product: thea.', 17, 1, '2025-10-15 17:28:47'),
(40, 6, 'rental_started', 'Rental Started', 'Your rental for product: thea has started.', 17, 1, '2025-10-15 17:29:24'),
(41, 6, 'booking_completed', 'Booking Completed', 'Your booking for product: thea has been marked as completed by the owner.<br>Your security deposit will be refunded within 2-3 working days.<br>Thank you for using RentHub!', 17, 1, '2025-10-15 17:29:33'),
(42, 6, 'booking', 'Booking Accepted', 'Your booking for product: ganda has been accepted.', 16, 1, '2025-10-15 17:29:44'),
(43, 3, 'payment_confirmed', 'Payment Confirmed', 'A renter has confirmed payment for your product: ganda.', 16, 1, '2025-10-15 17:30:04'),
(44, 6, 'rental_started', 'Rental Started', 'Your rental for product: ganda has started.', 16, 1, '2025-10-15 17:30:17'),
(45, 6, 'booking_completed', 'Booking Completed (With Damage)', 'Your booking for product: ganda has been marked as completed by the owner.<br>Please note: The owner reported damage to the product.<br>The security deposit will NOT be refunded.<br>Thank you for using RentHub!', 16, 1, '2025-10-15 17:30:22'),
(46, 3, 'payment_confirmed', 'Payment Confirmed', 'A renter has confirmed payment for your product: ako ay isang model.', 15, 1, '2025-10-15 17:30:51'),
(47, 6, 'rental_started', 'Rental Started', 'Your rental for product: ako ay isang model has started.', 15, 1, '2025-10-15 17:31:04'),
(48, 6, 'booking_completed', 'Booking Completed', 'Your booking for product: ako ay isang model has been marked as completed by the owner.<br>Your security deposit will be refunded within 2-3 working days.<br>Thank you for using RentHub!', 15, 1, '2025-10-15 17:31:07'),
(49, 3, 'booking', 'New Booking Request', 'You have a new booking request for your product: awdgKYWETD.', 13, 1, '2025-10-15 17:57:05'),
(50, 3, 'booking_cancelled', 'Booking Cancelled', 'A booking for your product: awdgKYWETD has been cancelled by the renter. Reason: ayoko na', 18, 1, '2025-10-15 18:10:14'),
(51, 3, 'booking', 'New Booking Request', 'You have a new booking request for your product: boogsh.', 10, 1, '2025-10-15 18:17:13'),
(52, 6, 'booking', 'Booking Accepted', 'Your booking for product: boogsh has been accepted.', 19, 1, '2025-10-15 18:19:22'),
(53, 3, 'payment_pending', 'Payment Pending', 'A renter has initiated payment for your product: boogsh. Payment is now pending verification.', 19, 1, '2025-10-15 18:19:36'),
(54, 3, 'payment_completed', 'Payment Completed', 'Payment has been completed for your product: boogsh. The rental is now active.', 19, 1, '2025-10-15 18:19:39'),
(55, 3, 'booking_cancelled', 'Booking Cancelled', 'A booking for your product: boogsh has been cancelled by the renter. Reason: la pira', 19, 1, '2025-10-15 18:28:02'),
(56, 6, 'refund', 'Refund Processed', 'Your refund of ₱3,050.00 for booking \'boogsh\' has been processed via Bank Transfer. Transaction ID: 6779900887', NULL, 1, '2025-10-15 18:53:21'),
(57, 6, 'upgrade', 'Account Upgraded Successfully!', 'Congratulations! You can now rent and list products on RentHub PH.', NULL, 1, '2025-10-15 19:47:58'),
(58, 6, 'review_response', 'Owner Responded to Your Review', 'The owner responded to your review for product \"ganda\".', 12, 1, '2025-10-16 05:08:26'),
(59, 3, 'booking', 'New Booking Request', 'You have a new booking request for your product: bata batuta.', 11, 1, '2025-10-17 02:10:44'),
(60, 5, 'booking_cancelled', 'Booking Cancelled', 'A booking for your product: Bambagushkalabab has been cancelled by the renter. Reason: dsfsdfsdf', 21, 0, '2025-10-17 02:46:57');

-- --------------------------------------------------------

--
-- Table structure for table `parent_categories`
--

CREATE TABLE `parent_categories` (
  `ParentCategoryID` int(11) NOT NULL,
  `Parent_Name` varchar(100) NOT NULL,
  `Parent_Description` text DEFAULT NULL,
  `Parent_Icon` varchar(50) DEFAULT 'fas fa-folder',
  `Parent_Color` varchar(20) DEFAULT '#007bff',
  `Parent_IsActive` tinyint(1) DEFAULT 1,
  `Parent_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Parent_UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parent_categories`
--

INSERT INTO `parent_categories` (`ParentCategoryID`, `Parent_Name`, `Parent_Description`, `Parent_Icon`, `Parent_Color`, `Parent_IsActive`, `Parent_CreatedAt`, `Parent_UpdatedAt`) VALUES
(1, 'Photography', 'click click', 'fas fa-laptop', '#f89bf0', 1, '2025-09-14 04:52:13', '2025-09-14 04:52:13'),
(2, 'Furniture', 'Pang bdays', 'fas fa-couch', '#ee9b9b', 1, '2025-09-26 12:59:41', '2025-09-26 12:59:41');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `PaymentID` int(11) NOT NULL,
  `BookingID` int(11) DEFAULT NULL,
  `Pay_Amount` decimal(10,2) NOT NULL,
  `Pay_Type` varchar(50) DEFAULT NULL,
  `Pay_Method` varchar(50) DEFAULT NULL,
  `Pay_Status` varchar(50) DEFAULT 'Pending',
  `Pay_TransactionID` varchar(255) DEFAULT NULL,
  `Pay_ProcessedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Pay_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`PaymentID`, `BookingID`, `Pay_Amount`, `Pay_Type`, `Pay_Method`, `Pay_Status`, `Pay_TransactionID`, `Pay_ProcessedAt`, `Pay_CreatedAt`) VALUES
(1, 1, 2700.00, 'Rental Payment', 'Bank Transfer', 'Completed', 'TXN20250925111513-USER4-3453', '2025-09-25 09:15:35', '2025-09-25 09:15:13'),
(2, 2, 2705.00, 'Rental Payment', 'Bank Transfer', 'Completed', 'TXN20250925133957-USER4-6508', '2025-09-25 11:54:35', '2025-09-25 11:39:57'),
(3, 3, 2705.00, 'Rental Payment', 'GCash', 'Completed', 'TXN20250925140509-USER4-8460', '2025-09-25 12:05:19', '2025-09-25 12:05:09'),
(4, 4, 2700.00, 'Rental Payment', 'Maya', 'Completed', 'TXN20250925141113-USER4-1550', '2025-09-25 12:11:13', '2025-09-25 12:11:13'),
(5, 5, 2700.00, 'Rental Payment', 'Maya', 'Completed', 'TXN20250925202934-USER4-4052', '2025-09-25 18:29:34', '2025-09-25 18:29:34'),
(6, 6, 2700.00, 'Rental Payment', 'GCash', 'Completed', 'TXN20250925210425-USER4-4773', '2025-09-25 19:04:25', '2025-09-25 19:04:25'),
(7, 7, 2900.00, 'Rental Payment', 'Maya', 'Completed', 'TXN20250926060333-USER4-4985', '2025-09-26 04:03:33', '2025-09-26 04:03:33'),
(8, 8, 8900.00, 'Rental Payment', 'Maya', 'Completed', 'TXN20250926172351-USER4-4040', '2025-09-26 15:23:51', '2025-09-26 15:23:51'),
(9, 10, 1150.00, 'Rental Payment', 'Maya', 'Completed', 'TXN20250927131509-USER4-7418', '2025-09-27 11:15:09', '2025-09-27 11:15:09'),
(10, 11, 2700.00, 'Rental Payment', 'Bank Transfer', 'Completed', 'TXN20250927134708-USER6-3335', '2025-09-27 11:47:08', '2025-09-27 11:47:08'),
(11, 12, 1150.00, 'Rental Payment', 'Bank Transfer', 'Completed', 'TXN20250927165302-USER6-2247', '2025-09-27 14:53:02', '2025-09-27 14:53:02'),
(12, 13, 6176.00, 'Rental Payment', 'Maya', 'Completed', 'TXN20250928085613-USER6-2121', '2025-09-28 06:56:13', '2025-09-28 06:56:13'),
(13, 17, 7160.00, 'Rental Payment', 'Cash', 'Completed', 'TXN20251015192847-USER6-9897', '2025-10-15 17:28:47', '2025-10-15 17:28:47'),
(14, 16, 4088.00, 'Rental Payment', 'Maya', 'Completed', 'TXN20251015193004-USER6-2243', '2025-10-15 17:30:04', '2025-10-15 17:30:04'),
(15, 15, 1500.00, 'Rental Payment', 'Bank Transfer', 'Completed', 'TXN20251015193051-USER6-2293', '2025-10-15 17:30:51', '2025-10-15 17:30:51'),
(16, 19, 6100.00, 'Rental Payment', 'GCash', 'Completed', 'TXN20251015201936-USER6-1855', '2025-10-15 18:19:39', '2025-10-15 18:19:36');

-- --------------------------------------------------------

--
-- Table structure for table `payment_history`
--

CREATE TABLE `payment_history` (
  `PaymentHistoryID` int(11) NOT NULL,
  `UserID` int(11) DEFAULT NULL,
  `PaymentID` int(11) DEFAULT NULL,
  `SubPaymentID` int(11) DEFAULT NULL,
  `PH_Amount` decimal(10,2) NOT NULL,
  `PH_PaymentType` varchar(50) NOT NULL,
  `PH_PaymentMethod` varchar(50) DEFAULT NULL,
  `PH_Status` varchar(50) NOT NULL,
  `PH_TransactionID` varchar(255) DEFAULT NULL,
  `PH_ReferenceNumber` varchar(100) DEFAULT NULL,
  `PH_Description` text DEFAULT NULL,
  `PH_ProcessedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `PH_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `PH_UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `ProductID` int(11) NOT NULL,
  `OwnerID` int(11) DEFAULT NULL,
  `CategoryID` int(11) DEFAULT NULL,
  `Prod_Name` varchar(255) NOT NULL,
  `Prod_Description` text DEFAULT NULL,
  `Prod_Brand` varchar(100) DEFAULT NULL,
  `Prod_Model` varchar(100) DEFAULT NULL,
  `Prod_Condition` varchar(50) DEFAULT NULL,
  `Prod_RentalPrice` decimal(10,2) NOT NULL,
  `Prod_PriceType` varchar(20) DEFAULT NULL,
  `Prod_SecurityDeposit` decimal(10,2) DEFAULT NULL,
  `Prod_MinRentalDuration` int(11) DEFAULT NULL,
  `Prod_MaxRentalDuration` int(11) DEFAULT NULL,
  `Prod_Availability` tinyint(1) DEFAULT 1,
  `Prod_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Prod_UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Prod_Status` varchar(50) DEFAULT 'Active',
  `Prod_IsFeatured` tinyint(1) DEFAULT 0,
  `Prod_FeaturedUntil` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`ProductID`, `OwnerID`, `CategoryID`, `Prod_Name`, `Prod_Description`, `Prod_Brand`, `Prod_Model`, `Prod_Condition`, `Prod_RentalPrice`, `Prod_PriceType`, `Prod_SecurityDeposit`, `Prod_MinRentalDuration`, `Prod_MaxRentalDuration`, `Prod_Availability`, `Prod_CreatedAt`, `Prod_UpdatedAt`, `Prod_Status`, `Prod_IsFeatured`, `Prod_FeaturedUntil`) VALUES
(1, 2, 1, 'Canon EOS 90D DSLR Camera', 'A professional DSLR camera perfect for events, vlogging, and photography. Comes with battery and charger.', 'Canon', 'EOS 90D', 'Like New', 1200.00, 'Per Day', 5000.00, 1, 7, 1, '2025-09-14 05:40:13', '2025-09-25 00:23:50', 'Inactive', 1, '2025-12-31 23:59:59'),
(2, 3, 3, 'Instax', 'Maganda Magaling Maayos', NULL, NULL, 'Good', 100.00, 'Per Day', 2500.00, 7, 10, 1, '2025-09-24 18:20:59', '2025-10-06 13:12:43', 'Active', 1, '2025-10-25 02:29:18'),
(3, 3, 2, 'Go pro', 'Action camera is the best among the rest.', 'maganda', 'erqwer', 'New', 150.00, 'Per Day', 4000.00, 7, 10, 1, '2025-09-25 22:06:25', '2025-10-06 13:12:43', 'Active', 0, NULL),
(4, 3, 1, 'thea', 'ahhahahaha', NULL, NULL, 'Like New', 720.00, 'Per Week', 5000.00, 12, 20, 1, '2025-09-25 22:09:45', '2025-10-06 13:12:43', 'Active', 0, '2025-10-26 07:43:22'),
(5, 3, 2, 'yow yow', 'ahgsdjhsgdkgueyiuq', NULL, NULL, 'Good', 70.00, 'Per Day', 1200.00, 7, 10, 1, '2025-09-25 22:37:36', '2025-10-06 13:12:43', 'Active', 1, '2025-10-26 07:43:31'),
(6, 3, 2, 'ganda', 'hahksjdhKQUERY', NULL, NULL, 'New', 87.00, 'Per Hour', 2000.00, 5, 10, 1, '2025-09-25 22:39:52', '2025-10-06 13:12:43', 'Active', 0, NULL),
(7, 3, 1, 'galit sya', 'huhu sorry baby', NULL, NULL, 'New', 50.00, 'Per Day', 1000.00, 7, 15, 1, '2025-09-25 23:14:41', '2025-10-06 13:12:43', 'Active', 0, NULL),
(8, 3, 1, 'ako ay isang model', 'galit ang aking bebe', NULL, NULL, 'New', 500.00, 'Per Day', 500.00, 7, 10, 1, '2025-09-25 23:32:37', '2025-10-06 13:12:43', 'Active', 0, NULL),
(9, 3, 4, 'ILY', 'i love youuuu', 'Mahal', 'Kita', 'New', 750.00, 'Per Day', 1000.00, 7, 10, 1, '2025-09-25 23:38:11', '2025-10-06 13:12:43', 'Active', 1, '2025-10-27 20:30:35'),
(10, 3, 1, 'boogsh', 'jahdkudyq', NULL, NULL, 'Fair', 700.00, 'Per Week', 4000.00, 14, 24, 1, '2025-09-25 23:39:18', '2025-10-06 13:12:43', 'Active', 0, NULL),
(11, 3, 3, 'bata batuta', 'sdQTkueKDGHASGDKASDGAS', NULL, NULL, 'Good', 90.00, 'Per Hour', 500.00, 7, 10, 1, '2025-09-25 23:42:43', '2025-10-06 13:12:43', 'Active', 0, NULL),
(12, 5, 4, 'Bambagushkalabab', 'maganda si theabels', NULL, NULL, 'New', 500.00, 'Per Day', 5000.00, 7, 10, 1, '2025-09-26 11:56:51', '2025-10-05 13:08:55', 'Active', 0, NULL),
(13, 3, 2, 'awdgKYWETD', 'sadqwdeqwe', 'eqwe', 'tttthgfhg', 'Like New', 700.00, 'Per Month', 200.00, 32, 40, 1, '2025-10-15 10:58:32', '2025-10-16 05:07:44', 'Active', 0, NULL),
(14, 3, 4, 'trauma', 'hahahahhaha', '', '', 'Like New', 80.00, 'Per Day', 20.00, 8, 30, 1, '2025-10-15 11:01:49', '2025-10-16 05:04:35', 'Active', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_availability`
--

CREATE TABLE `product_availability` (
  `AvailabilityID` int(11) NOT NULL,
  `ProductID` int(11) DEFAULT NULL,
  `PA_DateFrom` date NOT NULL,
  `PA_DateTo` date NOT NULL,
  `PA_IsAvailable` tinyint(1) DEFAULT 1,
  `PA_Reason` varchar(255) DEFAULT NULL,
  `PA_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_availability`
--

INSERT INTO `product_availability` (`AvailabilityID`, `ProductID`, `PA_DateFrom`, `PA_DateTo`, `PA_IsAvailable`, `PA_Reason`, `PA_CreatedAt`) VALUES
(1, 2, '2025-09-25', '2026-09-25', 1, NULL, '2025-09-24 18:20:59'),
(2, 3, '2025-09-26', '2026-09-26', 1, NULL, '2025-09-25 22:06:25'),
(3, 4, '2025-09-26', '2026-09-26', 1, NULL, '2025-09-25 22:09:45'),
(4, 5, '2025-09-26', '2026-09-26', 1, NULL, '2025-09-25 22:37:36'),
(5, 6, '2025-09-26', '2026-09-26', 1, NULL, '2025-09-25 22:39:52'),
(6, 7, '2025-09-26', '2026-09-26', 1, NULL, '2025-09-25 23:14:41'),
(7, 8, '2025-09-26', '2026-09-26', 1, NULL, '2025-09-25 23:32:37'),
(8, 9, '2025-09-26', '2026-09-26', 1, NULL, '2025-09-25 23:38:11'),
(9, 10, '2025-09-26', '2026-09-26', 1, NULL, '2025-09-25 23:39:18'),
(10, 11, '2025-09-26', '2026-09-26', 1, NULL, '2025-09-25 23:42:43'),
(11, 12, '2025-09-26', '2026-09-26', 1, NULL, '2025-09-26 11:56:51'),
(12, 13, '2025-10-15', '2026-10-15', 1, NULL, '2025-10-15 10:58:32'),
(13, 14, '2025-10-15', '2026-10-15', 1, NULL, '2025-10-15 11:01:49');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `ImageID` int(11) NOT NULL,
  `ProductID` int(11) DEFAULT NULL,
  `PI_ImagePath` varchar(255) NOT NULL,
  `PI_ImageOrder` int(11) DEFAULT NULL,
  `PI_IsMain` tinyint(1) DEFAULT 0,
  `PI_UploadedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`ImageID`, `ProductID`, `PI_ImagePath`, `PI_ImageOrder`, `PI_IsMain`, `PI_UploadedAt`) VALUES
(1, 2, 'uploads/products/product_2_1_1758759659.png', 1, 1, '2025-09-24 18:20:59'),
(2, 3, 'uploads/products/product_3_1_1758859585.jpg', 1, 1, '2025-09-25 22:06:25'),
(3, 3, 'uploads/products/product_3_2_1758859585.jpg', 2, 0, '2025-09-25 22:06:25'),
(4, 3, 'uploads/products/product_3_3_1758859585.jpg', 3, 0, '2025-09-25 22:06:25'),
(5, 4, 'uploads/products/product_4_1_1758859785.jpg', 1, 1, '2025-09-25 22:09:45'),
(6, 4, 'uploads/products/product_4_2_1758859785.jpg', 2, 0, '2025-09-25 22:09:45'),
(7, 4, 'uploads/products/product_4_3_1758859785.jpg', 3, 0, '2025-09-25 22:09:45'),
(8, 5, 'uploads/products/product_5_1_1758861456.png', 1, 1, '2025-09-25 22:37:36'),
(9, 5, 'uploads/products/product_5_2_1758861456.png', 2, 0, '2025-09-25 22:37:36'),
(10, 6, 'uploads/products/product_6_1_1758861592.jfif', 1, 1, '2025-09-25 22:39:52'),
(11, 6, 'uploads/products/product_6_2_1758861592.jfif', 2, 0, '2025-09-25 22:39:52'),
(12, 7, 'uploads/products/product_7_1_1758863681.jpg', 1, 1, '2025-09-25 23:14:41'),
(13, 8, 'uploads/products/product_8_1_1758864757.jfif', 1, 1, '2025-09-25 23:32:37'),
(15, 10, 'uploads/products/product_10_1_1758865158.jfif', 1, 1, '2025-09-25 23:39:18'),
(16, 11, 'uploads/products/product_11_1_1758865363.png', 1, 1, '2025-09-25 23:42:43'),
(17, 12, 'uploads/products/product_12_1_1758909411.jpg', 1, 1, '2025-09-26 11:56:51'),
(18, 10, 'uploads/products/product_10_8148_1758989347.jpg', 0, 0, '2025-09-27 16:09:07'),
(19, 10, 'uploads/products/product_10_4930_1758989347.jpg', 0, 0, '2025-09-27 16:09:07'),
(20, 10, 'uploads/products/product_10_2053_1758989347.jpg', 0, 0, '2025-09-27 16:09:07'),
(21, 9, 'uploads/products/product_9_5917_1758996824.jpg', 0, 1, '2025-09-27 18:13:44'),
(22, 9, 'uploads/products/product_9_4441_1758997081.jpg', 2, 0, '2025-09-27 18:18:01'),
(23, 9, 'uploads/products/product_9_3637_1758997081.jpg', 3, 0, '2025-09-27 18:18:01'),
(24, 9, 'uploads/products/product_9_2435_1758997081.jpg', 4, 0, '2025-09-27 18:18:01'),
(25, 13, 'uploads/products/product_13_1_1760547512.jfif', 1, 1, '2025-10-15 10:58:32'),
(27, 14, 'uploads/products/product_14_2319_1760591251.jpg', 1, 1, '2025-10-16 05:07:31');

-- --------------------------------------------------------

--
-- Table structure for table `product_locations`
--

CREATE TABLE `product_locations` (
  `LocationID` int(11) NOT NULL,
  `ProductID` int(11) DEFAULT NULL,
  `AddressID` int(11) DEFAULT NULL,
  `PL_PickupAvailable` tinyint(1) DEFAULT 1,
  `PL_DeliveryAvailable` tinyint(1) DEFAULT 0,
  `PL_DeliveryRadius` decimal(5,2) DEFAULT NULL,
  `PL_DeliveryFee` decimal(8,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_locations`
--

INSERT INTO `product_locations` (`LocationID`, `ProductID`, `AddressID`, `PL_PickupAvailable`, `PL_DeliveryAvailable`, `PL_DeliveryRadius`, `PL_DeliveryFee`) VALUES
(1, 2, 2, 1, 1, 10.00, 5.00),
(2, 3, 2, 1, 0, 0.00, 0.00),
(3, 4, 2, 1, 1, 80.00, 20.00),
(4, 5, 2, 1, 0, 0.00, 0.00),
(5, 6, 2, 1, 0, 0.00, 0.00),
(6, 7, 2, 1, 1, 15.00, 90.00),
(7, 8, 2, 1, 0, 0.00, 0.00),
(8, 9, 2, 1, 1, 20.00, 110.00),
(9, 10, 2, 1, 1, 10.00, 100.00),
(10, 11, 2, 1, 0, 0.00, 0.00),
(11, 12, 6, 1, 1, 6.00, 50.00),
(12, 13, 2, 1, 1, 50.00, 20.00),
(13, 14, 2, 1, 0, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `RefundID` int(11) NOT NULL,
  `BookingID` int(11) NOT NULL,
  `Refund_Amount` decimal(10,2) NOT NULL,
  `Refund_Status` varchar(50) DEFAULT 'Pending',
  `Refund_Reason` text DEFAULT NULL,
  `Refund_Method` varchar(50) DEFAULT NULL,
  `Refund_TransactionID` varchar(255) DEFAULT NULL,
  `Refund_ProcessedBy` int(11) DEFAULT NULL,
  `Refund_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Refund_ProcessedAt` timestamp NULL DEFAULT NULL,
  `Refund_Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `refunds`
--

INSERT INTO `refunds` (`RefundID`, `BookingID`, `Refund_Amount`, `Refund_Status`, `Refund_Reason`, `Refund_Method`, `Refund_TransactionID`, `Refund_ProcessedBy`, `Refund_CreatedAt`, `Refund_ProcessedAt`, `Refund_Notes`) VALUES
(1, 19, 3050.00, 'Completed', 'Booking cancelled - 50% refund based on cancellation timing', 'Bank Transfer', '6779900887', 1, '2025-10-15 18:28:02', '2025-10-15 18:53:21', 'eto na po');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `ReviewID` int(11) NOT NULL,
  `BookingID` int(11) DEFAULT NULL,
  `ReviewerID` int(11) DEFAULT NULL,
  `RevieweeID` int(11) DEFAULT NULL,
  `Rev_Rating` int(11) NOT NULL,
  `Rev_Comment` text DEFAULT NULL,
  `Rev_Type` varchar(20) DEFAULT NULL,
  `Rev_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Rev_UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Rev_OwnerResponse` text DEFAULT NULL,
  `Rev_ResponseDate` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`ReviewID`, `BookingID`, `ReviewerID`, `RevieweeID`, `Rev_Rating`, `Rev_Comment`, `Rev_Type`, `Rev_CreatedAt`, `Rev_UpdatedAt`, `Rev_OwnerResponse`, `Rev_ResponseDate`) VALUES
(1, 1, 4, 3, 5, 'amazing sah', NULL, '2025-09-25 09:17:48', '2025-09-27 04:38:56', 'amazing', '2025-09-27 04:38:56'),
(2, 3, 4, 3, 2, 'ok lang naman', NULL, '2025-09-25 18:54:57', '2025-09-27 04:38:46', 'galing', '2025-09-27 04:38:46'),
(3, 2, 4, 3, 3, 'amazing ba', NULL, '2025-09-25 18:55:07', '2025-09-27 04:38:39', 'hahahahahaha', '2025-09-27 04:38:39'),
(4, 5, 4, 3, 2, 'saks lang sya', NULL, '2025-09-25 18:55:29', '2025-09-27 04:38:32', 'okurrrrr', '2025-09-27 04:38:32'),
(5, 4, 4, 3, 2, 'pede na pede', NULL, '2025-09-25 18:55:39', '2025-09-27 04:38:17', 'salamuch', '2025-09-27 04:38:17'),
(6, 6, 4, 3, 5, 'angas mo sah grabe', NULL, '2025-09-25 19:07:20', '2025-09-27 04:38:02', 'thank youuuu', '2025-09-27 04:38:02'),
(7, 8, 4, 3, 2, 'wow amasing', NULL, '2025-09-27 10:50:51', '2025-09-27 15:30:11', 'omg', '2025-09-27 15:30:11'),
(8, 7, 4, 3, 2, 'saks lang aahahhaha', NULL, '2025-09-27 10:51:16', '2025-09-27 15:30:00', 'hahahahah yis taina galing', '2025-09-27 15:30:00'),
(9, 11, 6, 3, 4, 'angas lods', NULL, '2025-09-27 12:58:50', '2025-09-27 13:25:21', 'thank you po', '2025-09-27 13:25:21'),
(10, 10, 4, 3, 3, 'ihhh ang angas', NULL, '2025-09-27 13:04:43', '2025-09-27 13:11:35', 'thank you so much po', '2025-09-27 13:11:35'),
(11, 12, 6, 3, 5, 'galing angas lopit', NULL, '2025-09-27 15:02:30', '2025-09-27 15:02:46', 'hala thank you po', '2025-09-27 15:02:46'),
(12, 13, 6, 3, 1, 'eh eh naman', NULL, '2025-09-28 09:30:00', '2025-10-16 05:08:26', 'wowwww', '2025-10-16 05:08:26');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_description`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'RentHub PH', 'Website name displayed across the platform', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(2, 'site_description', 'Your trusted rental marketplace in the Philippines', 'Website description for SEO', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(3, 'site_email', 'admin@renthub.ph', 'Main contact email for the platform', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(4, 'site_phone', '+63-XXX-XXX-XXXX', 'Main contact phone number', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(5, 'maintenance_mode', '0', 'Enable/disable maintenance mode (0=disabled, 1=enabled)', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(6, 'registration_enabled', '1', 'Allow new user registrations (0=disabled, 1=enabled)', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(7, 'auto_approve_products', '0', 'Automatically approve new products (0=manual, 1=auto)', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(8, 'featured_product_price', '500.00', 'Price for featuring a product (in PHP)', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(9, 'commission_rate', '5.00', 'Platform commission rate (percentage)', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(10, 'max_upload_size', '5', 'Maximum file upload size in MB', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(11, 'currency_symbol', '₱', 'Currency symbol to display', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(12, 'timezone', 'Asia/Manila', 'Default timezone for the platform', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(13, 'date_format', 'Y-m-d', 'Default date format', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(14, 'items_per_page', '12', 'Number of items to show per page', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(15, 'allow_guest_browsing', '1', 'Allow guests to browse products (0=no, 1=yes)', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(16, 'min_rental_duration', '1', 'Minimum rental duration in days', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(17, 'max_rental_duration', '365', 'Maximum rental duration in days', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(18, 'backup_frequency', 'weekly', 'Database backup frequency (daily, weekly, monthly)', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(19, 'email_notifications', '1', 'Enable email notifications (0=disabled, 1=enabled)', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(20, 'sms_notifications', '0', 'Enable SMS notifications (0=disabled, 1=enabled)', '2025-10-05 14:23:03', '2025-10-05 14:23:03'),
(21, 'flag_email_notifications', '1', 'Send email alerts when content is flagged (0=disabled, 1=enabled)', '2025-10-05 22:49:14', '2025-10-05 22:49:14'),
(22, 'smtp_host', 'smtp.gmail.com', 'SMTP server host for sending emails', '2025-10-05 22:49:14', '2025-10-05 22:49:14'),
(23, 'smtp_port', '587', 'SMTP server port', '2025-10-05 22:49:14', '2025-10-05 22:49:14'),
(24, 'smtp_username', '', 'SMTP username (your email address)', '2025-10-05 22:49:14', '2025-10-05 22:49:14'),
(25, 'smtp_password', '', 'SMTP password (use App Password for Gmail)', '2025-10-05 22:49:14', '2025-10-05 22:49:14'),
(26, 'smtp_encryption', 'tls', 'SMTP encryption type (tls or ssl)', '2025-10-05 22:49:14', '2025-10-05 22:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_payments`
--

CREATE TABLE `subscription_payments` (
  `SubPaymentID` int(11) NOT NULL,
  `SubscriptionID` int(11) DEFAULT NULL,
  `SubPay_Amount` decimal(10,2) NOT NULL,
  `SubPay_PaymentMethod` varchar(50) DEFAULT NULL,
  `SubPay_Status` varchar(50) DEFAULT 'Pending',
  `SubPay_TransactionID` varchar(255) DEFAULT NULL,
  `SubPay_ProcessedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `SubPay_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `SubPay_DueDate` date NOT NULL,
  `SubPay_Type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_payments`
--

INSERT INTO `subscription_payments` (`SubPaymentID`, `SubscriptionID`, `SubPay_Amount`, `SubPay_PaymentMethod`, `SubPay_Status`, `SubPay_TransactionID`, `SubPay_ProcessedAt`, `SubPay_CreatedAt`, `SubPay_DueDate`, `SubPay_Type`) VALUES
(1, 1, 499.00, 'Credit Card', 'Completed', 'TXN17578248903437', '2025-09-14 04:41:38', '2025-09-14 04:41:30', '2025-09-21', 'Subscription'),
(2, 2, 499.00, 'GCash', 'Completed', 'TXN17587576622916', '2025-09-24 23:56:12', '2025-09-24 23:47:42', '2025-10-02', 'Subscription'),
(3, 3, 499.00, 'Credit Card', 'Completed', 'TXN17587583988135', '2025-09-26 12:13:17', '2025-09-24 23:59:58', '2025-10-02', 'Subscription'),
(4, 4, 499.00, 'Credit Card', 'Completed', 'TXN17588991925275', '2025-09-26 15:59:43', '2025-09-26 15:06:32', '2025-10-03', 'Subscription'),
(5, 5, 699.00, 'GCash', 'Completed', 'TXN17589003208042', '2025-09-26 15:45:28', '2025-09-26 15:25:20', '2025-10-03', 'Subscription'),
(6, 6, 699.00, 'GCash', 'Completed', 'TXN17589016373132', '2025-09-26 15:47:22', '2025-09-26 15:47:17', '2025-10-03', 'Subscription'),
(7, 7, 699.00, 'GCash', 'Completed', 'TXN17589017312351', '2025-09-26 15:49:52', '2025-09-26 15:48:51', '2025-10-03', 'Subscription'),
(8, 8, 699.00, 'GCash', 'Completed', 'TXN17589020288868', '2025-09-26 15:54:21', '2025-09-26 15:53:48', '2025-10-03', 'Subscription'),
(9, 9, 699.00, 'Credit Card', 'Completed', 'TXN17589021499682', '2025-09-26 15:56:00', '2025-09-26 15:55:49', '2025-10-03', 'Subscription'),
(10, 10, 499.00, 'GCash', 'Completed', 'TXN17605582463851', '2025-10-15 19:57:35', '2025-10-15 19:57:26', '2025-10-22', 'Subscription'),
(11, 11, 499.00, 'GCash', 'Completed', 'TXN17605584092041', '2025-10-15 20:08:15', '2025-10-15 20:00:09', '2025-10-22', 'Subscription'),
(12, 12, 699.00, 'Bank Transfer', 'Completed', 'TXN17605589969717', '2025-10-15 20:10:21', '2025-10-15 20:09:56', '2025-10-22', 'Subscription'),
(13, 13, 699.00, 'Credit Card', 'Completed', 'TXN17605590361004', '2025-10-15 20:10:56', '2025-10-15 20:10:36', '2025-10-22', 'Subscription'),
(14, 14, 499.00, 'Maya', 'Completed', 'TXN17605590882431', '2025-10-15 20:11:38', '2025-10-15 20:11:28', '2025-10-22', 'Subscription'),
(15, 15, 499.00, 'Bank Transfer', 'Completed', 'TXN17605593534352', '2025-10-15 20:16:16', '2025-10-15 20:15:53', '2025-10-22', 'Subscription'),
(16, 16, 499.00, 'GCash', 'Completed', 'TXN17605597222521', '2025-10-15 20:22:07', '2025-10-15 20:22:02', '2025-10-22', 'Subscription'),
(17, 17, 499.00, 'GCash', 'Completed', 'TXN17605901589202', '2025-10-16 04:49:25', '2025-10-16 04:49:18', '2025-10-23', 'Subscription'),
(18, 18, 499.00, 'Maya', 'Completed', 'TXN17606652668440', '2025-10-17 01:41:13', '2025-10-17 01:41:06', '2025-10-24', 'Subscription'),
(19, 19, 699.00, 'GCash', 'Completed', 'TXN17606652966180', '2025-10-17 01:41:43', '2025-10-17 01:41:36', '2025-10-24', 'Subscription'),
(20, 20, 699.00, 'GCash', 'Completed', 'TXN17606671026691', '2025-10-17 02:11:47', '2025-10-17 02:11:42', '2025-10-24', 'Subscription');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `PlanID` int(11) NOT NULL,
  `Plan_Name` varchar(100) NOT NULL,
  `Plan_Description` text DEFAULT NULL,
  `Plan_Price` decimal(10,2) NOT NULL,
  `Plan_Duration` int(11) NOT NULL,
  `Plan_MaxListings` int(11) DEFAULT NULL,
  `Plan_FeaturedListings` int(11) DEFAULT NULL,
  `Plan_CommissionRate` decimal(5,2) DEFAULT NULL,
  `Plan_IsActive` tinyint(1) DEFAULT 1,
  `Plan_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`PlanID`, `Plan_Name`, `Plan_Description`, `Plan_Price`, `Plan_Duration`, `Plan_MaxListings`, `Plan_FeaturedListings`, `Plan_CommissionRate`, `Plan_IsActive`, `Plan_CreatedAt`) VALUES
(1, 'Premium Owner Plan', 'preumium to beh', 499.00, 30, 10, 3, 2.50, 1, '2025-09-14 04:40:48'),
(2, 'Magandang Owners', 'Basta dadami chix mo dito', 699.00, 90, 25, 5, 2.50, 1, '2025-09-26 12:56:26');

-- --------------------------------------------------------

--
-- Table structure for table `user_accounts`
--

CREATE TABLE `user_accounts` (
  `UserID` int(11) NOT NULL,
  `User_Name` varchar(255) NOT NULL,
  `User_Email` varchar(255) NOT NULL,
  `User_Password` varchar(255) NOT NULL,
  `User_Phone` varchar(20) DEFAULT NULL,
  `User_Role` int(11) DEFAULT NULL,
  `User_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `User_UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `User_Photo` varchar(255) DEFAULT NULL,
  `User_IsVerified` tinyint(1) DEFAULT 0,
  `User_Status` varchar(50) DEFAULT 'Active',
  `User_Birthdate` date DEFAULT NULL,
  `User_Gender` varchar(20) DEFAULT NULL,
  `User_Bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_accounts`
--

INSERT INTO `user_accounts` (`UserID`, `User_Name`, `User_Email`, `User_Password`, `User_Phone`, `User_Role`, `User_CreatedAt`, `User_UpdatedAt`, `User_Photo`, `User_IsVerified`, `User_Status`, `User_Birthdate`, `User_Gender`, `User_Bio`) VALUES
(1, 'Admin', 'admin@gmail.com', '$2y$10$Fz.yOY4bSa.gDTDqwv2/1.bxAslwbfuxOnuZy4vKhFcXriBztWQBW', '+639992223332', 1, '2025-09-14 04:28:45', '2025-09-14 04:29:18', NULL, 0, 'Active', NULL, NULL, NULL),
(2, 'Sepp Bernard Consulta', 'sepp@gmail.com', '$2y$10$Z5AtEBy/MJWJUkcVvDimZOYTlBvxQf/R5cJcKCncWHH7I7mU1Iume', '+639123456789', 3, '2025-09-14 04:38:43', '2025-09-25 00:23:50', NULL, 0, 'Inactive', '2005-04-16', 'Male', NULL),
(3, 'Theabels Reyes', 'raltheagabrielle@gmail.com', '$2y$10$EyeBT5RRnQMq5aDFqCNKpeK3WIw15CG76F3TKyMbec/vIbHHcpr56', '+639992223332', 3, '2025-09-24 23:47:17', '2025-10-16 04:50:46', 'user_3_1758793798.jpg', 1, 'Active', '2004-11-11', 'Female', 'Maganda Ako'),
(4, 'Gabrielle Reyes', 'gab@gmail.com', '$2y$10$nh.1XkO42NyT4yyLVrT0a.lb3k/kxiBhNI8yaqGkpHx9aV/InaU2.', '+639992223324', 2, '2025-09-25 00:22:25', '2025-09-25 10:02:50', 'user_4_1758794570.jpg', 1, 'Active', '2004-11-11', 'Female', 'Maganda ako'),
(5, 'Althea Reyes', 'thea@gmail.com', '$2y$10$npTk2hBgbzLYXTlNzCJ.H.ESARw.uuG6ohbc.nTZrnOH/6LGAkwj6', '+639992223332', 3, '2025-09-26 14:37:23', '2025-09-26 16:40:47', 'user_1758897443_7496.jpg', 1, 'Active', '2004-11-11', 'Female', 'Maganda at Mabait'),
(6, 'Iya Atayde', 'iya@gmail.com', '$2y$10$t5JYxYWVqJgjwAPM1bCi5.gI98qfo3cKWNzKQEx19dvaZGM9anTiu', '+639622360874', 3, '2025-09-27 11:38:50', '2025-10-15 19:47:58', 'user_1758973130_7852.jpg', 1, 'Active', '2004-11-11', 'Female', NULL);

--
-- Triggers `user_accounts`
--
DELIMITER $$
CREATE TRIGGER `sync_products_status_after_user_update` AFTER UPDATE ON `user_accounts` FOR EACH ROW BEGIN
    IF NEW.User_Status = 'Inactive' AND OLD.User_Status != 'Inactive' THEN
        UPDATE products SET Prod_Status = 'Inactive' WHERE OwnerID = NEW.UserID;
    ELSEIF NEW.User_Status = 'Active' AND OLD.User_Status != 'Active' THEN
        UPDATE products SET Prod_Status = 'Active' WHERE OwnerID = NEW.UserID;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `AddressID` int(11) NOT NULL,
  `UserID` int(11) DEFAULT NULL,
  `UA_Street` varchar(255) NOT NULL,
  `UA_Barangay` varchar(100) NOT NULL,
  `UA_City` varchar(100) NOT NULL,
  `UA_Province` varchar(100) NOT NULL,
  `UA_ZipCode` varchar(10) DEFAULT NULL,
  `UA_Latitude` decimal(10,8) DEFAULT NULL,
  `UA_Longitude` decimal(11,8) DEFAULT NULL,
  `UA_AddressType` varchar(50) DEFAULT NULL,
  `UA_IsDefault` tinyint(1) DEFAULT 0,
  `UA_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` (`AddressID`, `UserID`, `UA_Street`, `UA_Barangay`, `UA_City`, `UA_Province`, `UA_ZipCode`, `UA_Latitude`, `UA_Longitude`, `UA_AddressType`, `UA_IsDefault`, `UA_CreatedAt`) VALUES
(2, 3, '0278, Rosas St, Muntingpulo', 'Banay-Banay', 'Lipa', 'Batangas', '4217', 0.00000000, 0.00000000, 'Home', 1, '2025-09-24 23:47:17'),
(3, 4, '0278, Rosas St', 'Muntingpulo', 'Lipa', 'Batangas', '4217', NULL, NULL, 'Home', 1, '2025-09-25 00:31:05'),
(4, 5, '0278, Cadena St', '', 'Lipa City', 'Bataan', '4217', NULL, NULL, 'Work', 0, '2025-09-26 16:48:24'),
(5, 5, '0278, Rosas St', '', 'Lipa City', 'Bataan', '4217', NULL, NULL, 'Home', 0, '2025-09-26 17:02:54'),
(6, 5, '2301 Sampaguita', 'Latag', 'Lipa City', 'Ilocos Sur', '4217', NULL, NULL, 'Home', 1, '2025-09-26 17:17:32'),
(7, 6, '0278, Rosas St', 'Muntingpulo', 'Lipa', 'Batangas', '4217', 0.00000000, 0.00000000, 'Home', 1, '2025-09-27 11:38:50'),
(8, 3, '4516 Mabundok', 'Iniwan', 'Lipa', 'Cagayan', '4217', NULL, NULL, 'Work', 0, '2025-10-15 17:14:01');

-- --------------------------------------------------------

--
-- Table structure for table `user_favorites`
--

CREATE TABLE `user_favorites` (
  `FavoriteID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `ProductID` int(11) NOT NULL,
  `UF_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_subscriptions`
--

CREATE TABLE `user_subscriptions` (
  `SubscriptionID` int(11) NOT NULL,
  `UserID` int(11) DEFAULT NULL,
  `PlanID` int(11) DEFAULT NULL,
  `Sub_StartDate` datetime NOT NULL,
  `Sub_EndDate` datetime NOT NULL,
  `Sub_Status` varchar(50) DEFAULT 'Active',
  `Sub_AutoRenew` tinyint(1) DEFAULT 1,
  `Sub_PaymentMethod` varchar(50) DEFAULT NULL,
  `Sub_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Sub_UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_subscriptions`
--

INSERT INTO `user_subscriptions` (`SubscriptionID`, `UserID`, `PlanID`, `Sub_StartDate`, `Sub_EndDate`, `Sub_Status`, `Sub_AutoRenew`, `Sub_PaymentMethod`, `Sub_CreatedAt`, `Sub_UpdatedAt`) VALUES
(1, 2, 1, '2025-09-14 00:00:00', '2025-10-14 00:00:00', 'Active', 0, 'Credit Card', '2025-09-14 04:41:30', '2025-09-14 04:41:38'),
(2, 3, 1, '2025-09-25 00:00:00', '2025-10-25 00:00:00', 'Cancelled', 0, 'GCash', '2025-09-24 23:47:42', '2025-09-24 23:59:52'),
(3, 3, 1, '2025-09-25 00:00:00', '2025-10-25 00:00:00', 'Cancelled', 0, 'Credit Card', '2025-09-24 23:59:58', '2025-09-26 15:25:13'),
(4, 5, 1, '2025-09-26 00:00:00', '2025-10-26 00:00:00', 'Active', 0, 'Credit Card', '2025-09-26 15:06:32', '2025-09-26 15:59:43'),
(5, 3, 2, '2025-09-26 00:00:00', '2025-12-25 00:00:00', 'Pending', 0, 'Pending', '2025-09-26 15:25:20', '2025-09-26 15:25:20'),
(6, 3, 2, '2025-09-26 00:00:00', '2025-12-25 00:00:00', 'Cancelled', 0, 'GCash', '2025-09-26 15:47:17', '2025-09-26 15:48:46'),
(7, 3, 2, '2025-09-26 00:00:00', '2025-12-25 00:00:00', 'Cancelled', 0, 'GCash', '2025-09-26 15:48:51', '2025-09-26 15:53:44'),
(8, 3, 2, '2025-09-26 00:00:00', '2025-12-25 00:00:00', 'Cancelled', 0, 'GCash', '2025-09-26 15:53:48', '2025-09-26 15:54:50'),
(9, 3, 2, '2025-09-26 00:00:00', '2025-12-25 00:00:00', 'Cancelled', 1, 'Credit Card', '2025-09-26 15:55:49', '2025-10-16 04:45:14'),
(10, 6, 1, '2025-10-15 00:00:00', '2025-11-14 00:00:00', 'Cancelled', 0, 'GCash', '2025-10-15 19:57:26', '2025-10-15 20:00:04'),
(11, 6, 1, '2025-10-15 00:00:00', '2025-11-14 00:00:00', 'Cancelled', 0, 'GCash', '2025-10-15 20:00:09', '2025-10-15 20:09:48'),
(12, 6, 2, '2025-10-15 00:00:00', '2026-01-13 00:00:00', 'Cancelled', 0, 'Bank Transfer', '2025-10-15 20:09:56', '2025-10-15 20:10:31'),
(13, 6, 2, '2025-10-15 00:00:00', '2026-01-13 00:00:00', 'Cancelled', 0, 'Credit Card', '2025-10-15 20:10:36', '2025-10-15 20:11:15'),
(14, 6, 1, '2025-10-15 00:00:00', '2025-11-14 00:00:00', 'Cancelled', 0, 'Maya', '2025-10-15 20:11:28', '2025-10-15 20:15:49'),
(15, 6, 1, '2025-10-15 00:00:00', '2025-11-14 00:00:00', 'Cancelled', 0, 'Bank Transfer', '2025-10-15 20:15:53', '2025-10-15 20:21:54'),
(16, 6, 1, '2025-10-15 00:00:00', '2025-11-14 00:00:00', 'Active', 0, 'GCash', '2025-10-15 20:22:02', '2025-10-15 20:22:07'),
(17, 3, 1, '2025-10-16 00:00:00', '2025-11-15 00:00:00', 'Cancelled', 0, 'GCash', '2025-10-16 04:49:18', '2025-10-16 04:50:35'),
(18, 3, 1, '2025-10-17 00:00:00', '2025-11-16 00:00:00', 'Cancelled', 0, 'Maya', '2025-10-17 01:41:06', '2025-10-17 01:41:32'),
(19, 3, 2, '2025-10-17 00:00:00', '2026-01-15 00:00:00', 'Cancelled', 0, 'GCash', '2025-10-17 01:41:36', '2025-10-17 02:04:43'),
(20, 3, 2, '2025-10-17 00:00:00', '2026-01-15 00:00:00', 'Active', 0, 'GCash', '2025-10-17 02:11:42', '2025-10-17 02:11:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`BookingID`),
  ADD KEY `ProductID` (`ProductID`),
  ADD KEY `RenterID` (`RenterID`),
  ADD KEY `OwnerID` (`OwnerID`);

--
-- Indexes for table `booking_status_history`
--
ALTER TABLE `booking_status_history`
  ADD PRIMARY KEY (`StatusHistoryID`),
  ADD KEY `BookingID` (`BookingID`),
  ADD KEY `BSH_ChangedBy` (`BSH_ChangedBy`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`CategoryID`),
  ADD KEY `ParentCategoryID` (`ParentCategoryID`);

--
-- Indexes for table `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`ChatID`),
  ADD KEY `Chat_ParticipantOne` (`Chat_ParticipantOne`),
  ADD KEY `Chat_ParticipantTwo` (`Chat_ParticipantTwo`),
  ADD KEY `ProductID` (`ProductID`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`MessageID`),
  ADD KEY `ChatID` (`ChatID`),
  ADD KEY `SenderID` (`SenderID`);

--
-- Indexes for table `commission_payments`
--
ALTER TABLE `commission_payments`
  ADD PRIMARY KEY (`CommissionID`),
  ADD KEY `BookingID` (`BookingID`),
  ADD KEY `OwnerID` (`OwnerID`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`ConversationID`),
  ADD UNIQUE KEY `unique_conversation` (`User1ID`,`User2ID`,`ProductID`),
  ADD KEY `User2ID` (`User2ID`),
  ADD KEY `ProductID` (`ProductID`),
  ADD KEY `idx_conversations_users` (`User1ID`,`User2ID`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`FavoriteID`),
  ADD UNIQUE KEY `unique_user_product` (`UserID`,`ProductID`),
  ADD KEY `ProductID` (`ProductID`);

--
-- Indexes for table `flag_reports`
--
ALTER TABLE `flag_reports`
  ADD PRIMARY KEY (`FlagID`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`MessageID`),
  ADD KEY `idx_messages_conversation` (`ConversationID`),
  ADD KEY `idx_messages_sender` (`SenderID`),
  ADD KEY `idx_messages_read` (`Msg_IsRead`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`NotificationID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `parent_categories`
--
ALTER TABLE `parent_categories`
  ADD PRIMARY KEY (`ParentCategoryID`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`PaymentID`),
  ADD KEY `BookingID` (`BookingID`);

--
-- Indexes for table `payment_history`
--
ALTER TABLE `payment_history`
  ADD PRIMARY KEY (`PaymentHistoryID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `PaymentID` (`PaymentID`),
  ADD KEY `SubPaymentID` (`SubPaymentID`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`ProductID`),
  ADD KEY `OwnerID` (`OwnerID`),
  ADD KEY `CategoryID` (`CategoryID`);

--
-- Indexes for table `product_availability`
--
ALTER TABLE `product_availability`
  ADD PRIMARY KEY (`AvailabilityID`),
  ADD KEY `ProductID` (`ProductID`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`ImageID`),
  ADD KEY `ProductID` (`ProductID`);

--
-- Indexes for table `product_locations`
--
ALTER TABLE `product_locations`
  ADD PRIMARY KEY (`LocationID`),
  ADD KEY `ProductID` (`ProductID`),
  ADD KEY `AddressID` (`AddressID`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`RefundID`),
  ADD KEY `idx_refunds_booking` (`BookingID`),
  ADD KEY `idx_refunds_status` (`Refund_Status`),
  ADD KEY `fk_refunds_processed_by` (`Refund_ProcessedBy`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`ReviewID`),
  ADD KEY `ReviewerID` (`ReviewerID`),
  ADD KEY `RevieweeID` (`RevieweeID`),
  ADD KEY `idx_reviews_booking` (`BookingID`),
  ADD KEY `idx_reviews_rating` (`Rev_Rating`),
  ADD KEY `idx_reviews_created` (`Rev_CreatedAt`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD PRIMARY KEY (`SubPaymentID`),
  ADD KEY `SubscriptionID` (`SubscriptionID`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`PlanID`);

--
-- Indexes for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `User_Email` (`User_Email`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`AddressID`),
  ADD KEY `idx_user_addresses_user` (`UserID`),
  ADD KEY `idx_user_addresses_default` (`UA_IsDefault`);

--
-- Indexes for table `user_favorites`
--
ALTER TABLE `user_favorites`
  ADD PRIMARY KEY (`FavoriteID`),
  ADD UNIQUE KEY `unique_user_product` (`UserID`,`ProductID`),
  ADD KEY `idx_user_favorites_user` (`UserID`),
  ADD KEY `idx_user_favorites_product` (`ProductID`),
  ADD KEY `idx_user_favorites_created` (`UF_CreatedAt`);

--
-- Indexes for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD PRIMARY KEY (`SubscriptionID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `PlanID` (`PlanID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `BookingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `booking_status_history`
--
ALTER TABLE `booking_status_history`
  MODIFY `StatusHistoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `CategoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chats`
--
ALTER TABLE `chats`
  MODIFY `ChatID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `MessageID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commission_payments`
--
ALTER TABLE `commission_payments`
  MODIFY `CommissionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `ConversationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `FavoriteID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `flag_reports`
--
ALTER TABLE `flag_reports`
  MODIFY `FlagID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `MessageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `NotificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `parent_categories`
--
ALTER TABLE `parent_categories`
  MODIFY `ParentCategoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `PaymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `PaymentHistoryID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `ProductID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `product_availability`
--
ALTER TABLE `product_availability`
  MODIFY `AvailabilityID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `ImageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `product_locations`
--
ALTER TABLE `product_locations`
  MODIFY `LocationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `RefundID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `ReviewID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `SubPaymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `PlanID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_accounts`
--
ALTER TABLE `user_accounts`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `AddressID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_favorites`
--
ALTER TABLE `user_favorites`
  MODIFY `FavoriteID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  MODIFY `SubscriptionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`RenterID`) REFERENCES `user_accounts` (`UserID`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`OwnerID`) REFERENCES `user_accounts` (`UserID`);

--
-- Constraints for table `booking_status_history`
--
ALTER TABLE `booking_status_history`
  ADD CONSTRAINT `booking_status_history_ibfk_1` FOREIGN KEY (`BookingID`) REFERENCES `bookings` (`BookingID`),
  ADD CONSTRAINT `booking_status_history_ibfk_2` FOREIGN KEY (`BSH_ChangedBy`) REFERENCES `user_accounts` (`UserID`);

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`ParentCategoryID`) REFERENCES `parent_categories` (`ParentCategoryID`) ON DELETE CASCADE;

--
-- Constraints for table `chats`
--
ALTER TABLE `chats`
  ADD CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`Chat_ParticipantOne`) REFERENCES `user_accounts` (`UserID`),
  ADD CONSTRAINT `chats_ibfk_2` FOREIGN KEY (`Chat_ParticipantTwo`) REFERENCES `user_accounts` (`UserID`),
  ADD CONSTRAINT `chats_ibfk_3` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`);

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`ChatID`) REFERENCES `chats` (`ChatID`),
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`SenderID`) REFERENCES `user_accounts` (`UserID`);

--
-- Constraints for table `commission_payments`
--
ALTER TABLE `commission_payments`
  ADD CONSTRAINT `commission_payments_ibfk_1` FOREIGN KEY (`BookingID`) REFERENCES `bookings` (`BookingID`),
  ADD CONSTRAINT `commission_payments_ibfk_2` FOREIGN KEY (`OwnerID`) REFERENCES `user_accounts` (`UserID`);

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`User1ID`) REFERENCES `user_accounts` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`User2ID`) REFERENCES `user_accounts` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_3` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`) ON DELETE SET NULL;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user_accounts` (`UserID`),
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`ConversationID`) REFERENCES `conversations` (`ConversationID`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`SenderID`) REFERENCES `user_accounts` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user_accounts` (`UserID`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`BookingID`) REFERENCES `bookings` (`BookingID`);

--
-- Constraints for table `payment_history`
--
ALTER TABLE `payment_history`
  ADD CONSTRAINT `payment_history_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user_accounts` (`UserID`),
  ADD CONSTRAINT `payment_history_ibfk_2` FOREIGN KEY (`PaymentID`) REFERENCES `payments` (`PaymentID`),
  ADD CONSTRAINT `payment_history_ibfk_3` FOREIGN KEY (`SubPaymentID`) REFERENCES `subscription_payments` (`SubPaymentID`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`OwnerID`) REFERENCES `user_accounts` (`UserID`),
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`CategoryID`) REFERENCES `categories` (`CategoryID`);

--
-- Constraints for table `product_availability`
--
ALTER TABLE `product_availability`
  ADD CONSTRAINT `product_availability_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`);

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`);

--
-- Constraints for table `product_locations`
--
ALTER TABLE `product_locations`
  ADD CONSTRAINT `product_locations_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`),
  ADD CONSTRAINT `product_locations_ibfk_2` FOREIGN KEY (`AddressID`) REFERENCES `user_addresses` (`AddressID`);

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `fk_refunds_booking` FOREIGN KEY (`BookingID`) REFERENCES `bookings` (`BookingID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_refunds_processed_by` FOREIGN KEY (`Refund_ProcessedBy`) REFERENCES `user_accounts` (`UserID`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`BookingID`) REFERENCES `bookings` (`BookingID`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`ReviewerID`) REFERENCES `user_accounts` (`UserID`),
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`RevieweeID`) REFERENCES `user_accounts` (`UserID`);

--
-- Constraints for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD CONSTRAINT `subscription_payments_ibfk_1` FOREIGN KEY (`SubscriptionID`) REFERENCES `user_subscriptions` (`SubscriptionID`);

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user_accounts` (`UserID`);

--
-- Constraints for table `user_favorites`
--
ALTER TABLE `user_favorites`
  ADD CONSTRAINT `user_favorites_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user_accounts` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_favorites_ibfk_2` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`) ON DELETE CASCADE;

--
-- Constraints for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD CONSTRAINT `user_subscriptions_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `user_accounts` (`UserID`),
  ADD CONSTRAINT `user_subscriptions_ibfk_2` FOREIGN KEY (`PlanID`) REFERENCES `subscription_plans` (`PlanID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
