<?php
/**
 * Auto-cancel expired pending bookings
 * This function can be called from anywhere in the system to automatically
 * cancel bookings that have passed their start date without being accepted
 */

require_once __DIR__ . '/../config/database.php';

function autoCancelExpiredBookings() {
    $database = new Database();
    $conn = $database->getConnection();
    
    $current_date = date('Y-m-d H:i:s');
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Get expired pending bookings before cancelling them
        $expired_bookings_query = "SELECT b.BookingID, b.ProductID, b.RenterID, p.Prod_Name, p.OwnerID,
                                          u_renter.User_Name as Renter_Name, u_owner.User_Name as Owner_Name
                                   FROM bookings b 
                                   JOIN products p ON b.ProductID = p.ProductID 
                                   JOIN user_accounts u_renter ON b.RenterID = u_renter.UserID
                                   JOIN user_accounts u_owner ON p.OwnerID = u_owner.UserID
                                   WHERE b.Book_Status = 'Pending' 
                                   AND b.Book_StartDate < ?";
        $expired_stmt = $conn->prepare($expired_bookings_query);
        $expired_stmt->execute([$current_date]);
        $expired_bookings = $expired_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($expired_bookings) > 0) {
            // Cancel expired bookings
            $cancel_query = "UPDATE bookings 
                            SET Book_Status = 'Cancelled', 
                                Book_UpdatedAt = NOW() 
                            WHERE Book_Status = 'Pending' 
                            AND Book_StartDate < ?";
            $cancel_stmt = $conn->prepare($cancel_query);
            $cancel_stmt->execute([$current_date]);
            
            // Create notifications for each cancelled booking
            foreach ($expired_bookings as $booking) {
                // Notification for renter
                $renter_notif_query = "INSERT INTO notifications (UserID, Not_Type, Not_Title, Not_Message, Not_CreatedAt) 
                                       VALUES (?, 'booking_cancelled', ?, ?, NOW())";
                $renter_notif_stmt = $conn->prepare($renter_notif_query);
                $renter_notif_stmt->execute([
                    $booking['RenterID'],
                    'Booking Automatically Cancelled',
                    'Your booking for "' . $booking['Prod_Name'] . '" was automatically cancelled because the start date has passed without owner approval.'
                ]);
                
                // Notification for owner
                $owner_notif_query = "INSERT INTO notifications (UserID, Not_Type, Not_Title, Not_Message, Not_CreatedAt) 
                                      VALUES (?, 'booking_expired', ?, ?, NOW())";
                $owner_notif_stmt = $conn->prepare($owner_notif_query);
                $owner_notif_stmt->execute([
                    $booking['OwnerID'],
                    'Booking Request Expired',
                    'A booking request from ' . $booking['Renter_Name'] . ' for "' . $booking['Prod_Name'] . '" has been automatically cancelled due to expired start date.'
                ]);
            }
            
            // Commit transaction
            $conn->commit();
            
            return [
                'success' => true,
                'cancelled_count' => count($expired_bookings),
                'message' => count($expired_bookings) . ' expired booking(s) were automatically cancelled.'
            ];
        } else {
            $conn->commit();
            return [
                'success' => true,
                'cancelled_count' => 0,
                'message' => 'No expired bookings found.'
            ];
        }
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        return [
            'success' => false,
            'cancelled_count' => 0,
            'message' => 'Error auto-cancelling bookings: ' . $e->getMessage()
        ];
    }
}

// Auto-call this function if this file is accessed directly (for testing or cron jobs)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $result = autoCancelExpiredBookings();
    echo json_encode($result);
}
?>