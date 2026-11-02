<?php
/**
 * PickFix API - Get Messages Endpoint (Live Chat Polling)
 * 
 * Fetches real-time conversation messages for an active booking.
 */

require_once __DIR__ . '/../config/database.php';
pickfix_session_start();

if (!is_logged_in()) {
    json_response(false, null, "Authentication required.", 401);
}

try {
    $db = get_db();
    $currentUser = current_user();
    $currentUserId = (int)$currentUser['id'];

    $bookingId = !empty($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
    $lastId = !empty($_GET['last_id']) ? intval($_GET['last_id']) : 0;

    if ($bookingId <= 0) {
        json_response(false, null, "Booking ID required.", 400);
    }

    // Verify booking authorization
    $stmt = $db->prepare("
        SELECT b.*, p.user_id AS provider_user_id, u_cust.name AS customer_name, u_prov.name AS provider_name
        FROM bookings b
        LEFT JOIN users u_cust ON b.customer_id = u_cust.id
        LEFT JOIN providers p ON b.provider_id = p.id
        LEFT JOIN users u_prov ON p.user_id = u_prov.id
        WHERE b.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        json_response(false, null, "Booking not found.", 404);
    }

    $customerId = (int)$booking['customer_id'];
    $providerUserId = $booking['provider_user_id'] ? (int)$booking['provider_user_id'] : null;

    if ($currentUserId !== $customerId && $currentUserId !== $providerUserId) {
        json_response(false, null, "Unauthorized access to this chat.", 403);
    }

    // Mark unread messages delivered to this user as read
    $markRead = $db->prepare("UPDATE messages SET is_read = 1 WHERE booking_id = :bid AND receiver_id = :uid AND is_read = 0");
    $markRead->execute([':bid' => $bookingId, ':uid' => $currentUserId]);

    // Query messages
    $query = "
        SELECT m.*, u.name AS sender_name, u.role AS sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.booking_id = :bid
    ";
    $params = [':bid' => $bookingId];

    if ($lastId > 0) {
        $query .= " AND m.id > :last_id";
        $params[':last_id'] = $lastId;
    }

    $query .= " ORDER BY m.created_at ASC";
    $msgStmt = $db->prepare($query);
    $msgStmt->execute($params);
    $messages = $msgStmt->fetchAll();

    $formatted = array_map(function($m) use ($currentUserId) {
        return [
            'id'           => (int)$m['id'],
            'booking_id'   => (int)$m['booking_id'],
            'sender_id'    => (int)$m['sender_id'],
            'sender_name'  => $m['sender_name'],
            'sender_role'  => $m['sender_role'],
            'message_text' => sanitize($m['message_text']),
            'is_mine'      => (int)$m['sender_id'] === $currentUserId,
            'created_at'   => $m['created_at'],
            'time_display' => date('g:i A', strtotime($m['created_at']))
        ];
    }, $messages);

    json_response(true, [
        'booking_id'     => $bookingId,
        'booking_code'   => $booking['booking_code'],
        'partner_name'   => $currentUserId === $customerId ? ($booking['provider_name'] ?? 'Provider') : $booking['customer_name'],
        'messages_count' => count($formatted),
        'messages'       => $formatted
    ]);

} catch (Exception $e) {
    error_log("[PickFix Get Messages Error] " . $e->getMessage());
    json_response(false, null, "Failed to retrieve chat messages: " . $e->getMessage(), 500);
}

