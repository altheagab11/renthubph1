-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 12, 2025 at 03:08 PM
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
  `Book_Notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  MODIFY `BookingID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_status_history`
--
ALTER TABLE `booking_status_history`
  MODIFY `StatusHistoryID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `CategoryID` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `CommissionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `ConversationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `FavoriteID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `MessageID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `NotificationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parent_categories`
--
ALTER TABLE `parent_categories`
  MODIFY `ParentCategoryID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `PaymentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `PaymentHistoryID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `ProductID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_availability`
--
ALTER TABLE `product_availability`
  MODIFY `AvailabilityID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `ImageID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_locations`
--
ALTER TABLE `product_locations`
  MODIFY `LocationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `ReviewID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `SubPaymentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `PlanID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_accounts`
--
ALTER TABLE `user_accounts`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `AddressID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_favorites`
--
ALTER TABLE `user_favorites`
  MODIFY `FavoriteID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  MODIFY `SubscriptionID` int(11) NOT NULL AUTO_INCREMENT;

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
