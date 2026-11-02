<?php
/**
 * PickFix API - Upload Design Deliverable Endpoint
 * 
 * Allows 3D CAD designers and service providers to submit finalized deliverables
 * (.stl 3D prints, .step/.dwg engineering files, .zip archives, or high-res 4K renders)
 * and marks the project/booking as 'completed'.
 */

require_once __DIR__ . '/../config/database.php';
pickfix_session_start();

// Enforce Provider Authentication
if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'provider') {
    json_response(false, null, "Unauthorized. Only verified service providers and 3D designers can upload deliverables.", 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, "Method not allowed. Use POST.", 405);
}

try {
    $db = get_db();
    $currentUser = current_user();
    $providerId = $currentUser['provider_id'] ?? null;

    if (!$providerId) {
        json_response(false, null, "Provider profile not found for this account.", 400);
    }

    $bookingId = !empty($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $deliveryNotes = trim($_POST['delivery_notes'] ?? '');

    if ($bookingId <= 0) {
        json_response(false, null, "Invalid booking reference provided.", 400);
    }

    // Verify booking exists
    $stmt = $db->prepare("SELECT * FROM bookings WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        json_response(false, null, "Booking project record not found.", 404);
    }

    // Ensure booking belongs to this provider or assign it to them if open
    if (!empty($booking['provider_id']) && (int)$booking['provider_id'] !== (int)$providerId) {
        json_response(false, null, "You are not the designated provider assigned to this project.", 403);
    }

    // Validate uploaded file
    if (!isset($_FILES['deliverable_file']) || $_FILES['deliverable_file']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['deliverable_file']['error'] ?? 'missing';
        json_response(false, null, "File upload missing or failed with error code: " . $errorCode, 400);
    }

    $file = $_FILES['deliverable_file'];
    $maxSizeBytes = 50 * 1024 * 1024; // 50 MB

    if ($file['size'] > $maxSizeBytes) {
        json_response(false, null, "Deliverable file exceeds the maximum 50MB allowance.", 400);
    }

    $fileInfo = pathinfo($file['name']);
    $originalName = $file['name'];
    $extension = strtolower($fileInfo['extension'] ?? '');

    // Allowed extensions for 3D meshes, CAD assemblies, renders & archives
    $allowedExtensions = [
        'stl', 'obj', 'step', 'stp', '3mf', 'blend', 'dwg', 'dxf',
        'zip', 'rar', '7z', 'jpg', 'jpeg', 'png', 'webp', 'pdf'
    ];

    if (!in_array($extension, $allowedExtensions, true)) {
        json_response(false, null, "Unsupported deliverable extension: ." . $extension . ". Permitted formats: " . implode(', ', $allowedExtensions), 400);
    }

    // Ensure deliverable storage directory exists
    if (!is_dir(UPLOAD_DIR_DELIVERABLES)) {
        mkdir(UPLOAD_DIR_DELIVERABLES, 0777, true);
    }

    $cleanBaseName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fileInfo['filename']);
    $newFileName = sprintf('deliv_%d_%s_%s.%s', $bookingId, date('Ymd_His'), substr(bin2hex(random_bytes(3)), 0, 6), $extension);
    $destination = UPLOAD_DIR_DELIVERABLES . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        json_response(false, null, "Failed to persist uploaded deliverable to storage.", 500);
    }

    $relativeFilePath = 'uploads/deliverables/' . $newFileName;

    // Begin Transaction: Update booking record and provider stats
    $db->beginTransaction();

    $updateBooking = $db->prepare("
        UPDATE bookings 
        SET 
            provider_id = COALESCE(provider_id, :pid),
            delivered_file_path = :path,
            delivery_notes = :notes,
            status = 'completed',
            updated_at = NOW()
        WHERE id = :id
    ");

    $updateBooking->execute([
        ':pid'   => $providerId,
        ':path'  => $relativeFilePath,
        ':notes' => $deliveryNotes,
        ':id'    => $bookingId
    ]);

    // Increment completed jobs count for this provider
    $updateProvider = $db->prepare("
        UPDATE providers 
        SET total_jobs = total_jobs + 1 
        WHERE id = :pid
    ");
    $updateProvider->execute([':pid' => $providerId]);

    $db->commit();

    json_response(true, [
        'booking_id'    => $bookingId,
        'booking_code'  => $booking['booking_code'],
        'status'        => 'completed',
        'file_path'     => $relativeFilePath,
        'file_name'     => $originalName,
        'file_size_str' => round($file['size'] / (1024 * 1024), 2) . ' MB',
        'notes'         => $deliveryNotes
    ], "Deliverable file uploaded successfully! Project is marked as Completed.");

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("[PickFix Deliverable Upload Error] " . $e->getMessage());
    json_response(false, null, "Failed to upload deliverable: " . $e->getMessage(), 500);
}

