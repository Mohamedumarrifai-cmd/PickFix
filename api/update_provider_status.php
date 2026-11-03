<?php
/**
 * PickFix API - Update Provider Status & GPS Telemetry Endpoint
 * 
 * Allows active providers and garage mechanics to toggle Online/Offline availability
 * and continuously stream live GPS telemetry coordinates to the PickFix dispatch radar.
 */

require_once __DIR__ . '/../config/database.php';
pickfix_session_start();

if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'provider') {
    json_response(false, null, "Only service providers can update availability status.", 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, "Method not allowed. Use POST.", 405);
}

try {
    $db = get_db();
    $currentUser = current_user();
    $providerId = $currentUser['provider_id'] ?? null;

    // Resilient fallback to resolve provider ID by user_id if session was missing it
    if (!$providerId) {
        $checkProv = $db->prepare("SELECT id FROM providers WHERE user_id = :uid LIMIT 1");
        $checkProv->execute([':uid' => (int)$currentUser['id']]);
        $providerId = $checkProv->fetchColumn();
        if ($providerId) {
            $_SESSION['provider_id'] = (int)$providerId;
        }
    }

    if (!$providerId) {
        json_response(false, null, "Provider record not found.", 400);
    }

    // Parse input from either multipart/form-data or application/json
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);

    $isOnlineVal = $_POST['is_online'] ?? ($jsonData['is_online'] ?? null);
    $latVal = $_POST['lat'] ?? ($jsonData['lat'] ?? null);
    $lngVal = $_POST['lng'] ?? ($jsonData['lng'] ?? null);

    $isOnline = $isOnlineVal !== null ? (intval($isOnlineVal) ? 1 : 0) : null;
    $lat = ($latVal !== null && is_numeric($latVal)) ? floatval($latVal) : null;
    $lng = ($lngVal !== null && is_numeric($lngVal)) ? floatval($lngVal) : null;

    $fields = [];
    $params = [':pid' => (int)$providerId];

    if ($isOnline !== null) {
        $fields[] = "is_online = :online";
        $params[':online'] = $isOnline;
    }

    if ($lat !== null && $lng !== null) {
        $fields[] = "current_lat = :lat, current_lng = :lng";
        $params[':lat'] = $lat;
        $params[':lng'] = $lng;
    }

    if (empty($fields)) {
        json_response(false, null, "No status or GPS coordinates provided for update.", 400);
    }

    $sql = "UPDATE providers SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :pid";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // Fetch updated record
    $check = $db->prepare("SELECT is_online, current_lat, current_lng, workshop_name, category FROM providers WHERE id = :pid");
    $check->execute([':pid' => (int)$providerId]);
    $updated = $check->fetch();

    $isOnlineState = (bool)$updated['is_online'];

    json_response(true, [
        'provider_id' => (int)$providerId,
        'is_online'   => $isOnlineState,
        'lat'         => (float)$updated['current_lat'],
        'lng'         => (float)$updated['current_lng'],
        'updated_at'  => date('Y-m-d H:i:s')
    ], $isOnlineState ? "You are now ONLINE. You will receive real-time job radar requests." : "You are now OFFLINE. Dispatch broadcast paused.");

} catch (Exception $e) {
    error_log("[PickFix Update Provider Status Error] " . $e->getMessage());
    json_response(false, null, "Failed to update pro telemetry: " . $e->getMessage(), 500);
}

