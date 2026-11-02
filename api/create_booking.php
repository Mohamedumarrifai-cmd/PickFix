<?php
/**
 * PickFix API - Create Booking Endpoint
 * 
 * Handles incoming physical service requests, emergency SOS dispatches,
 * and 3D CAD modeling briefs with multipart reference file uploads.
 */

require_once __DIR__ . '/../config/database.php';
pickfix_session_start();

// Enforce Customer Authentication
if (!is_logged_in()) {
    json_response(false, null, "Authentication required. Please log in as a customer.", 401);
}

$currentUser = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, "Method not allowed. Use POST.", 405);
}

try {
    $db = get_db();

    // Collect Input parameters
    $serviceType = trim($_POST['service_type'] ?? 'plumber');
    $subCategory = trim($_POST['sub_category'] ?? '');
    $projectTitle = trim($_POST['project_title'] ?? '');
    $projectNotes = trim($_POST['project_notes'] ?? '');
    $isEmergency = !empty($_POST['is_emergency']) && $_POST['is_emergency'] != '0' ? 1 : 0;
    $customerLat = !empty($_POST['customer_lat']) ? floatval($_POST['customer_lat']) : 6.92710000;
    $customerLng = !empty($_POST['customer_lng']) ? floatval($_POST['customer_lng']) : 79.86120000;
    $customerAddress = trim($_POST['customer_address'] ?? 'Colombo 03, Western Province');
    $budget = !empty($_POST['budget']) ? floatval($_POST['budget']) : null;
    $assignedProviderId = !empty($_POST['provider_id']) ? intval($_POST['provider_id']) : null;

    // Validate service category
    $validServices = ['plumber', 'electrician', 'mechanic', 'ac_repair', '3d_design', 'emergency'];
    if (!in_array($serviceType, $validServices, true)) {
        $serviceType = 'plumber';
    }

    // Default title if not provided
    if (empty($projectTitle)) {
        if ($isEmergency) {
            $projectTitle = "EMERGENCY SOS: " . get_service_label($serviceType) . " Dispatch";
        } elseif ($serviceType === '3d_design') {
            $projectTitle = "3D CAD & Digital Modeling Project";
        } else {
            $projectTitle = get_service_label($serviceType) . " On-Demand Request";
        }
    }

    // Handle reference file upload (specs, photos, 2D sketches)
    $referenceFilePath = null;
    if (isset($_FILES['reference_file']) && $_FILES['reference_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['reference_file'];
        $maxSizeBytes = 30 * 1024 * 1024; // 30 MB

        if ($file['size'] > $maxSizeBytes) {
            json_response(false, null, "Uploaded reference file exceeds 30MB limit.", 400);
        }

        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension'] ?? '');
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'stl', 'obj', 'step', 'stp', 'dwg', 'zip', 'rar', 'txt'];

        if (!in_array($extension, $allowedExtensions, true)) {
            json_response(false, null, "Invalid file format. Allowed: " . implode(', ', $allowedExtensions), 400);
        }

        // Ensure directory exists
        if (!is_dir(UPLOAD_DIR_REFERENCES)) {
            mkdir(UPLOAD_DIR_REFERENCES, 0777, true);
        }

        $newFileName = 'ref_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = UPLOAD_DIR_REFERENCES . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $referenceFilePath = 'uploads/references/' . $newFileName;
        } else {
            json_response(false, null, "Failed to store uploaded reference file on server.", 500);
        }
    }

    // Generate unique human-readable booking code
    $prefix = $isEmergency ? 'PF-SOS' : ($serviceType === '3d_design' ? 'PF-CAD' : 'PF-TRD');
    $datePart = date('Ymd');
    $randomPart = strtoupper(bin2hex(random_bytes(2)));
    $bookingCode = sprintf('%s-%s-%s', $prefix, $datePart, $randomPart);

    // Initial booking status
    $status = 'pending';

    // Insert into MySQL Database
    $insertQuery = "
        INSERT INTO bookings (
            booking_code, customer_id, provider_id, service_type, sub_category,
            project_title, project_notes, reference_file_path, status,
            is_emergency, customer_lat, customer_lng, customer_address, budget
        ) VALUES (
            :booking_code, :customer_id, :provider_id, :service_type, :sub_category,
            :project_title, :project_notes, :reference_file_path, :status,
            :is_emergency, :customer_lat, :customer_lng, :customer_address, :budget
        )
    ";

    $stmt = $db->prepare($insertQuery);
    $stmt->execute([
        ':booking_code'        => $bookingCode,
        ':customer_id'         => $currentUser['id'],
        ':provider_id'         => $assignedProviderId,
        ':service_type'        => $serviceType,
        ':sub_category'        => $subCategory,
        ':project_title'       => $projectTitle,
        ':project_notes'       => $projectNotes,
        ':reference_file_path' => $referenceFilePath,
        ':status'              => $status,
        ':is_emergency'        => $isEmergency,
        ':customer_lat'        => $customerLat,
        ':customer_lng'        => $customerLng,
        ':customer_address'    => $customerAddress,
        ':budget'              => $budget
    ]);

    $newBookingId = (int)$db->lastInsertId();

    json_response(true, [
        'booking_id'    => $newBookingId,
        'booking_code'  => $bookingCode,
        'service_type'  => $serviceType,
        'status'        => $status,
        'is_emergency'  => (bool)$isEmergency,
        'reference_url' => $referenceFilePath
    ], "Booking request created successfully! Radar broadcast active.");

} catch (Exception $e) {
    error_log("[PickFix Create Booking Error] " . $e->getMessage());
    json_response(false, null, "Failed to submit booking: " . $e->getMessage(), 500);
}

