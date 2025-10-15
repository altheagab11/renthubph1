-- Add refunds table for handling refund processing
-- This table will track refunds for cancelled bookings

CREATE TABLE `refunds` (
  `RefundID` int(11) NOT NULL AUTO_INCREMENT,
  `BookingID` int(11) NOT NULL,
  `Refund_Amount` decimal(10,2) NOT NULL,
  `Refund_Status` varchar(50) DEFAULT 'Pending',
  `Refund_Reason` text DEFAULT NULL,
  `Refund_Method` varchar(50) DEFAULT NULL,
  `Refund_TransactionID` varchar(255) DEFAULT NULL,
  `Refund_ProcessedBy` int(11) DEFAULT NULL,
  `Refund_CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Refund_ProcessedAt` timestamp NULL DEFAULT NULL,
  `Refund_Notes` text DEFAULT NULL,
  PRIMARY KEY (`RefundID`),
  KEY `idx_refunds_booking` (`BookingID`),
  KEY `idx_refunds_status` (`Refund_Status`),
  CONSTRAINT `fk_refunds_booking` FOREIGN KEY (`BookingID`) REFERENCES `bookings` (`BookingID`) ON DELETE CASCADE,
  CONSTRAINT `fk_refunds_processed_by` FOREIGN KEY (`Refund_ProcessedBy`) REFERENCES `user_accounts` (`UserID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;