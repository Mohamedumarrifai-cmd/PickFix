<?php
/**
 * PickFix API - Update Booking Status Endpoint
 * 
 * Allows service providers to claim open requests on their radar,
 * transition jobs to 'accepted', 'in_progress', 'completed', or 'cancelled'.
 */

require_once __DIR__ . '/../config/database.php';
pickfix_session_start();

if (!is_logged_in()) {
    json_response(false, null, "Authentication required.", 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, "Method not allowed. Use POST.", 405);
}

try {
    $db = get_db();
    $currentUser = current_user();

    $bookingId = !empty($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $newStatus = trim($_POST['status'] ?? '');

    $allowedStatuses = ['pending', 'accepted', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        json_response(false, null, "Invalid booking status provided: " . $newStatus, 400);
    }

    if ($bookingId <= 0) {
        json_response(false, null, "Valid booking ID required.", 400);
    }

    // Retrieve current booking state
    $stmt = $db->prepare("SELECT * FROM bookings WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        json_response(false, null, "Booking not found.", 404);
    }

    if ($currentUser['role'] === 'provider') {
        $providerId = (int)$currentUser['provider_id'];

        // If accepting a pending job, assign to this provider
        if ($newStatus === 'accepted') {
            if ($booking['status'] !== 'pending') {
                json_response(false, null, "This job has already been claimed by another pro.", 409);
            }

            $update = $db->prepare("
                UPDATE bookings 
                SET provider_id = :pid, status = 'accepted', updated_at = NOW() 
                WHERE id = :id
            ");
            $update->execute([':pid' => $providerId, ':id' => $bookingId]);

            json_response(true, [
                'booking_id' => $bookingId,
                'status'     => 'accepted',
                'badge'      => render_status_badge('accepted')
            ], "Job accepted! Customer has been notified.");

        } else {
            // Only the assigned provider can advance the job
            if ((int)$booking['provider_id'] !== $providerId) {
                json_response(false, null, "You are not assigned to this booking.", 403);
            }

            $update = $db->prepare("
                UPDATE bookings 
                SET status = :status, updated_at = NOW() 
                WHERE id = :id
            ");
            $update->execute([':status' => $newStatus, ':id' => $bookingId]);

            // If completing, increment provider counter
            if ($newStatus === 'completed' && $booking['status'] !== 'completed') {
                $db->prepare("UPDATE providers SET total_jobs = total_jobs + 1 WHERE id = :pid")
                   ->execute([':pid' => $providerId]);
            }

            json_response(true, [
                'booking_id' => $bookingId,
                'status'     => $newStatus,
                'badge'      => render_status_badge($newStatus)
            ], "Status updated to " . ucfirst($newStatus) . ".");
        }

    } elseif ($currentUser['role'] === 'customer') {
        // Customer can only cancel their own pending booking
        if ((int)$booking['customer_id'] !== (int)$currentUser['id']) {
            json_response(false, null, "Unauthorized.", 403);
        }

        if ($newStatus === 'cancelled') {
            if ($booking['status'] === 'completed') {
                json_response(false, null, "Cannot cancel a completed service.", 400);
            }

            $update = $db->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
            $update->execute([':id' => $bookingId]);

            json_response(true, [
                'booking_id' => $bookingId,
                'status'     => 'cancelled',
                'badge'      => render_status_badge('cancelled')
            ], "Booking cancelled.");
        } else {
            json_response(false, null, "Customers can only cancel bookings.", 403);
        }
    }

} catch (Exception $e) {
    error_log("[PickFix Update Booking Status Error] " . $e->getMessage());
    json_response(false, null, "Failed to update status: " . $e->getMessage(), 500);
}

