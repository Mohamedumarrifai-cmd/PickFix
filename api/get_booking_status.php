<?php
/**
 * PickFix API - Get Booking Status Endpoint (Live Polling)
 * 
 * Used by AJAX polling loops on Customer & Provider dashboards
 * to update job progress, ETA, payment state, reviews, and unread chat badges in real time.
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
    $bookingId = !empty($_GET['id']) ? intval($_GET['id']) : null;

    if ($bookingId) {
        // Query specific booking
        $sql = "
            SELECT 
                b.*,
                u_cust.name AS customer_name,
                u_cust.phone AS customer_phone,
                u_prov.name AS provider_name,
                u_prov.phone AS provider_phone,
                p.workshop_name,
                p.category AS provider_category,
                p.sub_category AS provider_specialty,
                p.current_lat AS provider_lat,
                p.current_lng AS provider_lng,
                p.rating AS provider_rating,
                r.rating AS review_rating,
                r.comment AS review_comment,
                (SELECT COUNT(*) FROM messages m WHERE m.booking_id = b.id AND m.receiver_id = :uid AND m.is_read = 0) AS unread_messages
            FROM bookings b
            LEFT JOIN users u_cust ON b.customer_id = u_cust.id
            LEFT JOIN providers p ON b.provider_id = p.id
            LEFT JOIN users u_prov ON p.user_id = u_prov.id
            LEFT JOIN reviews r ON b.id = r.booking_id
            WHERE b.id = :id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $bookingId, ':uid' => $currentUserId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            json_response(false, null, "Booking not found.", 404);
        }

        // Security check
        if ($currentUser['role'] === 'customer' && (int)$booking['customer_id'] !== $currentUserId) {
            json_response(false, null, "Access denied.", 403);
        }

        $booking['status_badge'] = render_status_badge($booking['status']);
        $booking['payment_badge'] = render_payment_badge($booking['payment_status']);
        $booking['formatted_budget'] = $booking['budget'] ? format_lkr((float)$booking['budget']) : 'Negotiable';

        json_response(true, [
            'booking' => $booking,
            'badge'   => render_status_badge($booking['status'])
        ]);

    } else {
        // Query user's recent bookings list
        if ($currentUser['role'] === 'customer') {
            $sql = "
                SELECT 
                    b.*,
                    u_prov.name AS provider_name,
                    u_prov.phone AS provider_phone,
                    p.workshop_name,
                    p.current_lat AS provider_lat,
                    p.current_lng AS provider_lng,
                    p.rating AS provider_rating,
                    p.category AS provider_category,
                    p.sub_category AS provider_specialty,
                    r.rating AS review_rating,
                    r.comment AS review_comment,
                    (SELECT COUNT(*) FROM messages m WHERE m.booking_id = b.id AND m.receiver_id = :msg_rcv_id AND m.is_read = 0) AS unread_messages
                FROM bookings b
                LEFT JOIN providers p ON b.provider_id = p.id
                LEFT JOIN users u_prov ON p.user_id = u_prov.id
                LEFT JOIN reviews r ON b.id = r.booking_id
                WHERE b.customer_id = :cid
                ORDER BY b.created_at DESC
                LIMIT 20
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':msg_rcv_id' => $currentUserId,
                ':cid'        => $currentUserId
            ]);
        } else {
            // Provider: view assigned or open jobs
            $provId = $currentUser['provider_id'] ?? 0;
            $provCat = $currentUser['category'] ?? '';

            $sql = "
                SELECT 
                    b.*,
                    u_cust.name AS customer_name,
                    u_cust.phone AS customer_phone,
                    r.rating AS review_rating,
                    r.comment AS review_comment,
                    (SELECT COUNT(*) FROM messages m WHERE m.booking_id = b.id AND m.receiver_id = :uid AND m.is_read = 0) AS unread_messages
                FROM bookings b
                JOIN users u_cust ON b.customer_id = u_cust.id
                LEFT JOIN reviews r ON b.id = r.booking_id
                WHERE b.provider_id = :pid 
                   OR (b.status = 'pending' AND (b.service_type = :pcat OR b.is_emergency = 1 OR :is_mechanic = 'mechanic'))
                ORDER BY b.is_emergency DESC, b.created_at DESC
                LIMIT 25
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':pid'         => $provId,
                ':pcat'        => $provCat,
                ':uid'         => $currentUserId,
                ':is_mechanic' => $provCat
            ]);
        }

        $bookings = $stmt->fetchAll();

        // Attach HTML badges and formatted rates
        $list = array_map(function($b) {
            $b['badge_html'] = render_status_badge($b['status']);
            $b['payment_badge_html'] = render_payment_badge($b['payment_status'] ?? 'unpaid');
            $b['formatted_budget'] = $b['budget'] ? format_lkr((float)$b['budget']) : 'Pending Quote';
            $b['service_label'] = get_service_label($b['service_type']);
            return $b;
        }, $bookings);

        json_response(true, [
            'count'    => count($list),
            'bookings' => $list
        ]);
    }

} catch (Exception $e) {
    error_log("[PickFix Get Booking Status Error] " . $e->getMessage());
    json_response(false, null, "Failed to retrieve status: " . $e->getMessage(), 500);
}
