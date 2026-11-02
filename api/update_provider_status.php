<?php
/**
 * PickFix API - Update Provider Status & GPS Telemetry Endpoint
 * 
 * Allows active providers and 3D studios to toggle Online/Offline availability
 * and broadcast updated GPS coordinates to the PickFix dispatch radar.
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

    if (!$providerId) {
        json_response(false, null, "Provider record not found.", 400);
    }

    $isOnline = isset($_POST['is_online']) ? (intval($_POST['is_online']) ? 1 : 0) : null;
    $lat = !empty($_POST['lat']) ? floatval($_POST['lat']) : null;
    $lng = !empty($_POST['lng']) ? floatval($_POST['lng']) : null;

    $fields = [];
    $params = [':pid' => $providerId];

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
        json_response(false, null, "No status attributes provided for update.", 400);
    }

    $sql = "UPDATE providers SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :pid";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // Fetch updated record
    $check = $db->prepare("SELECT is_online, current_lat, current_lng FROM providers WHERE id = :pid");
    $check->execute([':pid' => $providerId]);
    $updated = $check->fetch();

    json_response(true, [
        'is_online' => (bool)$updated['is_online'],
        'lat'       => (float)$updated['current_lat'],
        'lng'       => (float)$updated['current_lng']
    ], $updated['is_online'] ? "You are now ONLINE. You will receive real-time job radar requests." : "You are now OFFLINE. Dispatch broadcast paused.");

} catch (Exception $e) {
    error_log("[PickFix Update Provider Status Error] " . $e->getMessage());
    json_response(false, null, "Failed to update pro telemetry: " . $e->getMessage(), 500);
}

