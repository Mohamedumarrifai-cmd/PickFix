<?php
/**
 * PickFix API - Process Payment & Platform Monetization Gateway
 * 
 * Simulates local Sri Lankan payment processing (PayHere, Dialog Genie, and Cash On Delivery).
 * Automatically calculates and records the 10% Platform Commission Fee and 90% Provider Payout.
 */

require_once __DIR__ . '/../config/database.php';
pickfix_session_start();

if (!is_logged_in()) {
    json_response(false, null, "Authentication required to complete payment.", 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, "Method not allowed. Use POST.", 405);
}

try {
    $db = get_db();
    $currentUser = current_user();

    $bookingId = !empty($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $amountLkr = !empty($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $gateway = strtolower(trim($_POST['payment_gateway'] ?? 'payhere'));
    $paymentDetails = trim($_POST['payment_details'] ?? '');

    $allowedGateways = ['payhere', 'genie', 'cash'];
    if (!in_array($gateway, $allowedGateways, true)) {
        $gateway = 'payhere';
    }

    if ($bookingId <= 0) {
        json_response(false, null, "Invalid booking reference.", 400);
    }

    // Retrieve booking details
    $stmt = $db->prepare("
        SELECT b.*, p.id AS provider_id, u.name AS provider_name 
        FROM bookings b
        LEFT JOIN providers p ON b.provider_id = p.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE b.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        json_response(false, null, "Booking not found.", 404);
    }

    // Verify user authorization (customer paying or provider collecting cash)
    if ($currentUser['role'] === 'customer' && (int)$booking['customer_id'] !== (int)$currentUser['id']) {
        json_response(false, null, "Unauthorized booking transaction.", 403);
    }

    if ($booking['payment_status'] === 'paid') {
        json_response(false, null, "This service request has already been paid.", 400);
    }

    // Default amount from budget or hourly rate if not explicitly supplied
    if ($amountLkr <= 0) {
        $amountLkr = !empty($booking['budget']) ? floatval($booking['budget']) : 3500.00;
    }

    // Ensure there is a provider assigned
    $providerId = $booking['provider_id'];
    if (!$providerId) {
        json_response(false, null, "Cannot process payment before a service provider has been assigned.", 400);
    }

    // Monetization: Calculate 10% platform commission & 90% provider payout
    $commission = calculate_commission($amountLkr, PLATFORM_COMMISSION_PERCENT);
    $platformFee = $commission['platform_fee'];
    $providerPayout = $commission['provider_payout'];

    // Generate unique transaction reference (e.g. TXN-PH-20261102-123456)
    $prefix = $gateway === 'payhere' ? 'TXN-PH' : ($gateway === 'genie' ? 'TXN-GEN' : 'TXN-CSH');
    $txnRef = sprintf('%s-%s-%s', $prefix, date('Ymd'), strtoupper(bin2hex(random_bytes(3))));

    if (empty($paymentDetails)) {
        if ($gateway === 'payhere') {
            $paymentDetails = "PayHere Visa/MasterCard (Approved Simulated Gateway)";
        } elseif ($gateway === 'genie') {
            $paymentDetails = "Dialog Genie Digital Wallet (Verified)";
        } else {
            $paymentDetails = "Cash on Delivery / In-Person Settlement";
        }
    }

    // Begin Transaction to persist payment and update booking status
    $db->beginTransaction();

    // 1. Insert into transactions table
    $insertTxn = $db->prepare("
        INSERT INTO transactions (
            booking_id, customer_id, provider_id, amount_lkr,
            platform_fee_lkr, provider_payout_lkr, payment_gateway,
            status, transaction_ref, payment_details
        ) VALUES (
            :bid, :cid, :pid, :amt,
            :fee, :payout, :gateway,
            'success', :ref, :details
        )
    ");
    $insertTxn->execute([
        ':bid'     => $bookingId,
        ':cid'     => $booking['customer_id'],
        ':pid'     => $providerId,
        ':amt'     => $amountLkr,
        ':fee'     => $platformFee,
        ':payout'  => $providerPayout,
        ':gateway' => $gateway,
        ':ref'     => $txnRef,
        ':details' => $paymentDetails
    ]);
    $txnId = (int)$db->lastInsertId();

    // 2. Update booking payment status
    $updateBooking = $db->prepare("
        UPDATE bookings 
        SET 
            payment_status = 'paid',
            paid_amount = :amt,
            payment_method = :gateway,
            status = CASE WHEN status = 'pending' THEN 'accepted' ELSE status END,
            updated_at = NOW()
        WHERE id = :id
    ");
    $updateBooking->execute([
        ':amt'     => $amountLkr,
        ':gateway' => $gateway,
        ':id'      => $bookingId
    ]);

    $db->commit();

    json_response(true, [
        'transaction_id'      => $txnId,
        'transaction_ref'     => $txnRef,
        'booking_id'          => $bookingId,
        'booking_code'        => $booking['booking_code'],
        'amount_paid'         => $amountLkr,
        'amount_paid_display' => format_lkr($amountLkr),
        'platform_fee'        => $platformFee,
        'platform_fee_disp'   => format_lkr($platformFee),
        'provider_payout'     => $providerPayout,
        'provider_payout_disp'=> format_lkr($providerPayout),
        'gateway'             => strtoupper($gateway),
        'payment_status'      => 'paid',
        'badge'               => render_payment_badge('paid')
    ], "Payment of " . format_lkr($amountLkr) . " via " . strtoupper($gateway) . " processed successfully! Ref: " . $txnRef);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("[PickFix Process Payment Error] " . $e->getMessage());
    json_response(false, null, "Payment processing failed: " . $e->getMessage(), 500);
}

