<?php
/**
 * PickFix API - Send Message Endpoint (Customer-Provider Real-Time Chat)
 * 
 * Inserts messages between customers and assigned service providers / mechanics / 3D designers.
 */

require_once __DIR__ . '/../config/database.php';
pickfix_session_start();

if (!is_logged_in()) {
    json_response(false, null, "Authentication required to chat.", 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, "Method not allowed. Use POST.", 405);
}

try {
    $db = get_db();
    $currentUser = current_user();
    $senderId = (int)$currentUser['id'];

    $bookingId = !empty($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $messageText = trim($_POST['message_text'] ?? '');

    if ($bookingId <= 0 || empty($messageText)) {
        json_response(false, null, "Valid booking ID and message text required.", 400);
    }

    // Retrieve booking and identify receiver
    $stmt = $db->prepare("
        SELECT b.*, p.user_id AS provider_user_id 
        FROM bookings b
        LEFT JOIN providers p ON b.provider_id = p.id
        WHERE b.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        json_response(false, null, "Booking not found.", 404);
    }

    $customerId = (int)$booking['customer_id'];
    $providerUserId = $booking['provider_user_id'] ? (int)$booking['provider_user_id'] : null;

    if ($senderId === $customerId) {
        // Sender is Customer; Receiver must be Provider
        if (!$providerUserId) {
            json_response(false, null, "Cannot message provider until a provider claims or is assigned to this job.", 400);
        }
        $receiverId = $providerUserId;
    } elseif ($providerUserId && $senderId === $providerUserId) {
        // Sender is Provider; Receiver is Customer
        $receiverId = $customerId;
    } else {
        json_response(false, null, "You are not an authorized party in this booking conversation.", 403);
    }

    // Insert Message
    $insert = $db->prepare("
        INSERT INTO messages (booking_id, sender_id, receiver_id, message_text)
        VALUES (:bid, :sid, :rid, :txt)
    ");
    $insert->execute([
        ':bid' => $bookingId,
        ':sid' => $senderId,
        ':rid' => $receiverId,
        ':txt' => $messageText
    ]);
    $newMsgId = (int)$db->lastInsertId();

    json_response(true, [
        'id'           => $newMsgId,
        'booking_id'   => $bookingId,
        'sender_id'    => $senderId,
        'sender_name'  => $currentUser['name'],
        'sender_role'  => $currentUser['role'],
        'message_text' => sanitize($messageText),
        'created_at'   => date('Y-m-d H:i:s'),
        'time_display' => date('g:i A')
    ], "Message sent successfully.");

} catch (Exception $e) {
    error_log("[PickFix Send Message Error] " . $e->getMessage());
    json_response(false, null, "Failed to deliver message: " . $e->getMessage(), 500);
}

