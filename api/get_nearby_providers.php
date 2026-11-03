<?php
/**
 * PickFix API - Get Nearby Providers Endpoint
 * 
 * Retrieves active, online, and verified service providers, garage mechanics, and 3D CAD designers.
 * Calculates real-time distance using the Haversine formula based on client coordinates,
 * estimates transit arrival time (ETA), and supports specialized garage search filtering.
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();

    // Query parameters
    $category = !empty($_GET['category']) ? trim($_GET['category']) : null;
    $engineType = !empty($_GET['engine_type']) ? trim($_GET['engine_type']) : null;
    $specialty = !empty($_GET['specialty']) ? trim($_GET['specialty']) : null;
    $towingOnly = !empty($_GET['towing_only']) && $_GET['towing_only'] != '0';
    $customerLat = !empty($_GET['lat']) ? floatval($_GET['lat']) : 6.92710000;
    $customerLng = !empty($_GET['lng']) ? floatval($_GET['lng']) : 79.86120000;
    $radiusKm = !empty($_GET['radius']) ? floatval($_GET['radius']) : 35.0;
    $onlyOnline = !isset($_GET['online']) || $_GET['online'] != '0';

    // SQL query with Haversine distance calculation (Earth radius approx 6371 km)
    $sql = "
        SELECT 
            p.id AS provider_id,
            p.user_id,
            u.name,
            u.email,
            u.phone,
            p.workshop_name,
            p.category,
            p.sub_category,
            p.engine_types,
            p.specialties,
            p.towing_available,
            p.nic_number,
            p.is_verified,
            p.is_online,
            p.current_lat,
            p.current_lng,
            p.city,
            p.rating,
            p.total_jobs,
            p.hourly_rate,
            p.bio,
            (SELECT COUNT(*) FROM reviews r WHERE r.provider_id = p.id) AS reviews_count,
            (6371 * ACOS(
                COS(RADIANS(:cust_lat1)) * COS(RADIANS(p.current_lat)) *
                COS(RADIANS(p.current_lng) - RADIANS(:cust_lng)) +
                SIN(RADIANS(:cust_lat2)) * SIN(RADIANS(p.current_lat))
            )) AS distance_km
        FROM providers p
        INNER JOIN users u ON p.user_id = u.id
        WHERE p.is_verified = 1
    ";

    $params = [
        ':cust_lat1' => $customerLat,
        ':cust_lat2' => $customerLat,
        ':cust_lng'  => $customerLng
    ];

    if ($onlyOnline) {
        $sql .= " AND p.is_online = 1";
    }

    if ($category && $category !== 'all') {
        $sql .= " AND p.category = :category";
        $params[':category'] = $category;
    }

    if ($towingOnly) {
        $sql .= " AND p.towing_available = 1";
    }

    if ($engineType && $engineType !== 'all') {
        $sql .= " AND (p.engine_types LIKE :engine OR p.engine_types = 'N/A')";
        $params[':engine'] = '%' . $engineType . '%';
    }

    if ($specialty && $specialty !== 'all') {
        $sql .= " AND (p.specialties LIKE :specialty OR p.sub_category LIKE :sub_cat)";
        $params[':specialty'] = '%' . $specialty . '%';
        $params[':sub_cat'] = '%' . $specialty . '%';
    }

    // Sort by proximity and rating
    $sql .= " HAVING distance_km <= :radius ORDER BY distance_km ASC, p.rating DESC LIMIT 25";
    $params[':radius'] = $radiusKm;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $providers = $stmt->fetchAll();

    // Format output
    $formatted = array_map(function($p) {
        $dist = round((float)$p['distance_km'], 1);
        // Estimated transit arrival time in Sri Lankan urban traffic (average ~30km/h) + 3 min dispatch lag
        $etaMinutes = max(5, round(($dist / 30) * 60) + 3);

        return [
            'id'               => (int)$p['provider_id'],
            'user_id'          => (int)$p['user_id'],
            'name'             => $p['name'],
            'workshop_name'    => $p['workshop_name'] ?: $p['name'],
            'phone'            => $p['phone'],
            'category'         => $p['category'],
            'category_lbl'     => get_service_label($p['category']),
            'sub_category'     => $p['sub_category'],
            'engine_types'     => $p['engine_types'] ?: 'Petrol, Diesel, Hybrid/EV',
            'specialties'      => $p['specialties'] ?: '',
            'towing_available' => (bool)$p['towing_available'],
            'rating'           => (float)$p['rating'],
            'reviews_count'    => (int)$p['reviews_count'],
            'stars_html'       => render_stars((float)$p['rating']),
            'total_jobs'       => (int)$p['total_jobs'],
            'hourly_rate'      => (float)$p['hourly_rate'],
            'rate_display'     => format_lkr((float)$p['hourly_rate']),
            'city'             => $p['city'],
            'lat'              => (float)$p['current_lat'],
            'lng'              => (float)$p['current_lng'],
            'distance_km'      => $dist,
            'distance_str'     => $dist < 1 ? round($dist * 1000) . 'm' : $dist . ' km',
            'eta_minutes'      => $etaMinutes,
            'eta_str'          => $etaMinutes . ' mins ETA',
            'is_online'        => (bool)$p['is_online'],
            'bio'              => $p['bio']
        ];
    }, $providers);

    json_response(true, [
        'count'          => count($formatted),
        'center'         => ['lat' => $customerLat, 'lng' => $customerLng],
        'filter_cat'     => $category,
        'filter_engine'  => $engineType,
        'filter_specialty'=> $specialty,
        'providers'      => $formatted
    ], "Found " . count($formatted) . " matching verified providers near your location.");

} catch (Exception $e) {
    error_log("[PickFix Get Nearby Providers Error] " . $e->getMessage());
    json_response(false, null, "Unable to query nearby providers: " . $e->getMessage(), 500);
}
