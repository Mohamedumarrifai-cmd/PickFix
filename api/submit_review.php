<?php
/**
 * PickFix API - Submit Review & 5-Star Rating Endpoint
 * 
 * Allows customers to submit post-service ratings (1 to 5 stars) and feedback.
 * Dynamically recomputes the provider's cumulative rating in the providers table.
 */

require_once __DIR__ . '/../config/database.php';
pickfix_session_start();

if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'customer') {
    json_response(false, null, "Only clients can submit provider reviews.", 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, "Method not allowed. Use POST.", 405);
}

try {
    $db = get_db();
    $currentUser = current_user();
    $customerId = (int)$currentUser['id'];

    $bookingId = !empty($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $rating = !empty($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = trim($_POST['comment'] ?? '');

    if ($bookingId <= 0) {
        json_response(false, null, "Invalid booking reference.", 400);
    }

    if ($rating < 1 || $rating > 5) {
        json_response(false, null, "Rating must be an integer between 1 and 5 stars.", 400);
    }

    // Verify booking
    $stmt = $db->prepare("SELECT * FROM bookings WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        json_response(false, null, "Booking not found.", 404);
    }

    if ((int)$booking['customer_id'] !== $customerId) {
        json_response(false, null, "You can only review services booked under your account.", 403);
    }

    $providerId = $booking['provider_id'];
    if (!$providerId) {
        json_response(false, null, "No provider was assigned to this booking.", 400);
    }

    // Insert or update review record (ON DUPLICATE KEY UPDATE)
    $db->beginTransaction();

    $reviewStmt = $db->prepare("
        INSERT INTO reviews (booking_id, customer_id, provider_id, rating, comment, created_at)
        VALUES (:bid, :cid, :pid, :rating, :comment, NOW())
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = NOW()
    ");
    $reviewStmt->execute([
        ':bid'     => $bookingId,
        ':cid'     => $customerId,
        ':pid'     => $providerId,
        ':rating'  => $rating,
        ':comment' => $comment
    ]);

    // Recalculate average provider rating dynamically
    $avgStmt = $db->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM reviews WHERE provider_id = :pid");
    $avgStmt->execute([':pid' => $providerId]);
    $stats = $avgStmt->fetch();
    $newAvgRating = round((float)($stats['avg_rating'] ?? 5.0), 2);

    // Update provider profile
    $updateProv = $db->prepare("UPDATE providers SET rating = :rating WHERE id = :pid");
    $updateProv->execute([':rating' => $newAvgRating, ':pid' => $providerId]);

    $db->commit();

    json_response(true, [
        'booking_id'    => $bookingId,
        'provider_id'   => $providerId,
        'rating'        => $rating,
        'new_avg_rating'=> $newAvgRating,
        'stars_html'    => render_stars($newAvgRating),
        'comment'       => sanitize($comment)
    ], "Thank you! Your " . $rating . "-star review has been published.");

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("[PickFix Submit Review Error] " . $e->getMessage());
    json_response(false, null, "Failed to submit rating: " . $e->getMessage(), 500);
}

