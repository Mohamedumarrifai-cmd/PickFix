<?php
/**
 * PickFix - Customer Hub, Garage Mechanics Radar & Live Tracker
 * 
 * Features:
 * 1. Dedicated Garage Mechanics Search with engine filters (Hybrid/EV, Diesel, Petrol) and towing.
 * 2. Leaflet.js with free OpenStreetMap tiles & Nominatim free geocoding API.
 * 3. Real-time Haversine distance and transit ETA in minutes.
 * 4. PayHere / Genie local payment gateway with 10% platform commission monetization.
 * 5. Real-time AJAX chat box between customer and assigned provider/mechanic.
 * 6. 5-Star rating & review submission updating provider ratings dynamically.
 */

// Enable comprehensive error reporting during debugging to immediately catch syntax or query issues
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require_once __DIR__ . '/config/database.php';
pickfix_session_start();
require_role('customer', 'login.php');

$currentUser = current_user();
if (!$currentUser || empty($currentUser['id'])) {
    if (!headers_sent()) {
        header("Location: login.php");
        exit;
    } else {
        echo "<script>window.location.href = 'login.php';</script>";
        exit;
    }
}

$pageTitle = "Customer Hub & Garage Mechanics Radar";
$customerBookings = [];
$dbErrorNotice = null;

// Query customer's existing bookings with reviews, payments & unread chat tallies using distinct PDO placeholders
try {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT 
            b.*,
            u.name AS provider_name,
            u.phone AS provider_phone,
            COALESCE(p.workshop_name, u.name) AS workshop_name,
            COALESCE(p.rating, 5.0) AS provider_rating,
            p.category AS provider_category,
            p.sub_category AS provider_specialty,
            r.rating AS customer_review_rating,
            r.comment AS customer_review_comment,
            (SELECT COUNT(*) FROM messages m WHERE m.booking_id = b.id AND m.receiver_id = :msg_rcv_id AND m.is_read = 0) AS unread_messages
        FROM bookings b
        LEFT JOIN providers p ON b.provider_id = p.id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN reviews r ON b.id = r.booking_id
        WHERE b.customer_id = :cid
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([
        ':msg_rcv_id' => (int)$currentUser['id'],
        ':cid'        => (int)$currentUser['id']
    ]);
    $customerBookings = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    error_log("[PickFix Customer Dashboard Error] " . $e->getMessage());
    $dbErrorNotice = $e->getMessage();
    
    // Resilient fallback query without messages or reviews joins
    try {
        if (isset($db)) {
            $fallback = $db->prepare("SELECT b.* FROM bookings b WHERE b.customer_id = :cid ORDER BY b.created_at DESC");
            $fallback->execute([':cid' => (int)$currentUser['id']]);
            $customerBookings = $fallback->fetchAll() ?: [];
        }
    } catch (Throwable $e2) {
        $customerBookings = [];
    }
}

// Handle URL query parameters for pre-filling
$preSelectedService = $_GET['service'] ?? 'plumber';
$isEmergencyMode = !empty($_GET['emergency']) && $_GET['emergency'] != '0';
$activeTab = $_GET['tab'] ?? ($isEmergencyMode ? 'physical' : ($preSelectedService === 'mechanic' ? 'mechanics' : ($preSelectedService === '3d_design' ? '3d_design' : 'physical')));

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
    
    <?php if (!empty($dbErrorNotice)): ?>
        <!-- Graceful Schema Sync Alert -->
        <div class="mb-6 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex flex-col sm:flex-row items-center justify-between gap-3 shadow-lg">
            <div class="flex items-center gap-3">
                <i class="fas fa-database text-lg text-amber-400"></i>
                <div>
                    <span class="font-bold block">Database Sync Mode Active:</span>
                    <span class="text-amber-300/80"><?= sanitize($dbErrorNotice) ?></span>
                </div>
            </div>
            <a href="setup_db.php?auto=1" class="px-3.5 py-1.5 rounded-xl bg-amber-500 text-slate-950 font-bold hover:bg-amber-400 transition whitespace-nowrap shadow-md">
                <i class="fas fa-wrench mr-1"></i> Auto-Migrate Database
            </a>
        </div>
    <?php endif; ?>
    
    <!-- Top Welcome & Emergency Quick Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 mb-8 border-b border-slate-800 gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <span class="text-xs font-bold uppercase tracking-wider text-brand-400 bg-brand-500/10 px-2.5 py-0.5 rounded-full border border-brand-500/20">Client Dispatch Hub</span>
                <span class="text-xs text-slate-500">•</span>
                <span class="text-xs text-slate-400">Colombo & Islandwide Network</span>
                <span class="text-xs text-slate-500">•</span>
                <span id="liveGpsStatusBadge" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-mono font-bold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>Initializing GPS...</span>
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white">
                Ayubowan, <?= sanitize($currentUser['name']) ?>!
            </h1>
        </div>

        <!-- Emergency SOS Instant Button -->
        <button type="button" onclick="triggerEmergencySOS()" class="inline-flex items-center justify-center gap-3 px-6 py-3.5 rounded-2xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-black text-sm shadow-xl shadow-red-600/30 transition transform hover:-translate-y-0.5 sos-pulse">
            <i class="fas fa-exclamation-triangle text-lg animate-bounce"></i>
            <span>INSTANT SOS DISPATCH (15-MIN)</span>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- PickMe-Style Real-Time Live Dispatch Radar & Transit Tracking Console     -->
    <!-- ========================================================================= -->
    <div id="liveDispatchConsole" class="mb-8 hidden">
        <!-- Rendered dynamically by renderActiveDispatchConsole() -->
    </div>

    <!-- Navigation Tabs: Physical Trades vs Garage Mechanics vs 3D Design Lab vs Live Job Tracker -->
    <div class="flex flex-wrap items-center gap-2 mb-8 bg-dark-900 p-2 rounded-2xl border border-slate-800 max-w-3xl">
        <button type="button" onclick="switchDashboardTab('physical')" id="tabBtn-physical" class="flex-1 min-w-[130px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 <?= $activeTab === 'physical' ? 'bg-brand-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white' ?>">
            <i class="fas fa-tools"></i>
            <span>Home Trades</span>
        </button>
        <button type="button" onclick="switchDashboardTab('mechanics')" id="tabBtn-mechanics" class="flex-1 min-w-[140px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 <?= $activeTab === 'mechanics' ? 'bg-amber-500 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white' ?>">
            <i class="fas fa-car-burst text-slate-950"></i>
            <span>Garage Mechanics</span>
        </button>
        <button type="button" onclick="switchDashboardTab('3d_design')" id="tabBtn-3d_design" class="flex-1 min-w-[130px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 <?= $activeTab === '3d_design' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white' ?>">
            <i class="fas fa-cube text-indigo-300"></i>
            <span>3D CAD Lab</span>
        </button>
        <button type="button" onclick="switchDashboardTab('my_bookings')" id="tabBtn-my_bookings" class="flex-1 min-w-[140px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 <?= $activeTab === 'my_bookings' ? 'bg-slate-800 text-white border border-slate-700' : 'text-slate-400 hover:text-white' ?>">
            <i class="fas fa-list-check"></i>
            <span>Live Tracker & Chat</span>
            <span id="bookingCountBadge" class="ml-1 px-1.5 py-0.2 rounded-full bg-slate-950 text-[10px] font-mono text-emerald-400 border border-slate-800"><?= count($customerBookings) ?></span>
        </button>
    </div>

    <!-- Free Nominatim Geocoding Search Bar (Zero-Cost Public API) -->
    <div class="mb-8 bg-dark-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
        <div class="flex flex-col sm:flex-row items-center gap-3">
            <div class="relative flex-1 w-full">
                <i class="fas fa-map-location-dot absolute left-4 top-3.5 text-brand-400 text-sm"></i>
                <input type="text" id="nominatimSearchInput" placeholder="Search any Sri Lankan city/street (e.g. Colombo 03, Kollupitiya, Nugegoda, Kandy, Galle)..." class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition" onkeydown="if(event.key === 'Enter'){ searchNominatimAddress(); }">
            </div>
            <button type="button" onclick="searchNominatimAddress()" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-brand-500 hover:text-slate-950 text-white font-bold text-xs transition border border-slate-700 flex items-center justify-center gap-2">
                <i class="fas fa-search"></i>
                <span>Locate via OpenStreetMap</span>
            </button>
            <button type="button" onclick="refreshGPSLocation()" class="w-full sm:w-auto px-4 py-2.5 rounded-xl bg-dark-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 flex items-center justify-center gap-1.5 transition">
                <i class="fas fa-crosshairs text-brand-400"></i> My GPS
            </button>
        </div>
        <div id="nominatimResultText" class="text-[11px] text-slate-400 mt-2 font-mono hidden"></div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1: General Physical Services (Plumber, Electrician, AC Repair)        -->
    <!-- ========================================================================= -->
    <div id="tabContent-physical" class="<?= $activeTab === 'physical' ? '' : 'hidden' ?> space-y-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div class="lg:col-span-7 space-y-4">
                <div class="bg-dark-900 border border-slate-800 rounded-3xl p-5 shadow-2xl relative overflow-hidden">
                    <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-bold text-white uppercase tracking-wider">Trades Dispatch Radar (OpenStreetMap)</span>
                        </div>
                        <span id="generalRadarStatus" class="text-xs font-mono text-emerald-400">Radar Active</span>
                    </div>
                    <div id="physicalLiveMap" class="w-full h-80 rounded-2xl border border-slate-800 z-10"></div>
                </div>

                <div class="bg-dark-900 border border-slate-800 rounded-3xl p-5 shadow-xl space-y-3">
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider flex items-center justify-between">
                        <span><i class="fas fa-wrench text-brand-500 mr-1.5"></i> Nearest Available Pros</span>
                        <span id="generalProsCount" class="text-slate-400 text-[11px] font-mono">Scanning...</span>
                    </h3>
                    <div id="generalProsList" class="space-y-2.5 max-h-72 overflow-y-auto pr-1"></div>
                </div>
            </div>

            <div class="lg:col-span-5">
                <div class="bg-dark-900 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-2xl">
                    <h2 class="text-lg font-bold text-white mb-1">Dispatch Local Technician</h2>
                    <p class="text-xs text-slate-400 mb-4">Fast-response licensed home trade professionals</p>

                    <form id="physicalBookingForm" onsubmit="handlePhysicalBooking(event)" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Select Trade</label>
                            <select id="physicalServiceType" name="service_type" onchange="fetchNearbyGeneralPros(this.value)" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-brand-500 transition">
                                <option value="plumber" <?= $preSelectedService === 'plumber' ? 'selected' : '' ?>>🔧 Plumber (Water works, leaks, pumps)</option>
                                <option value="electrician" <?= $preSelectedService === 'electrician' ? 'selected' : '' ?>>⚡ Electrician (Breakers, solar, rewiring)</option>
                                <option value="ac_repair" <?= $preSelectedService === 'ac_repair' ? 'selected' : '' ?>>❄️ Inverter AC Repair & Cleaning</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Issue Title</label>
                            <input type="text" id="physicalProjectTitle" name="project_title" placeholder="e.g. Bathroom main pipe leak" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition" required>
                        </div>

                        <div class="p-3.5 bg-red-950/20 border border-red-500/30 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <input type="checkbox" id="physicalIsEmergency" name="is_emergency" value="1" <?= $isEmergencyMode ? 'checked' : '' ?> class="w-4 h-4 rounded text-red-600 bg-slate-900 border-slate-700 focus:ring-red-500">
                                <div>
                                    <label for="physicalIsEmergency" class="text-xs text-red-300 font-bold block cursor-pointer">Emergency SOS Priority</label>
                                    <span class="text-[10px] text-slate-400">High-priority dispatch to nearest pro within 15 mins</span>
                                </div>
                            </div>
                            <span class="text-[11px] font-bold text-red-400 bg-red-500/20 px-2 py-0.5 rounded font-mono">&lt; 15m</span>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Service Address</label>
                            <input type="text" id="physicalAddress" name="customer_address" value="No. 42, Galle Road, Colombo 03" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition" required>
                            <input type="hidden" id="custLat" name="customer_lat" value="6.92710000">
                            <input type="hidden" id="custLng" name="customer_lng" value="79.86120000">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Notes for Pro</label>
                            <textarea id="physicalNotes" name="project_notes" rows="2" placeholder="e.g. Main stopcock jammed, water spreading" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition"></textarea>
                        </div>

                        <input type="hidden" id="selectedGeneralProviderId" name="provider_id" value="">
                        <div id="assignedGeneralProNotice" class="hidden p-2.5 rounded-xl bg-brand-500/10 border border-brand-500/30 text-xs text-brand-300 flex items-center justify-between">
                            <span id="assignedGeneralProText">Direct request</span>
                            <button type="button" onclick="clearSelectedGeneralPro()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
                        </div>

                        <button type="submit" id="btnDispatchPhysical" class="w-full py-4 rounded-xl bg-gradient-to-r from-brand-500 to-emerald-600 hover:from-brand-600 hover:to-emerald-700 text-slate-950 font-black text-sm shadow-lg shadow-brand-500/25 transition transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <i class="fas fa-satellite-dish text-base"></i>
                            <span>Confirm & Broadcast Trade Request</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: Enhanced Garage Mechanics & Roadside Rescue Hub (NEW)              -->
    <!-- ========================================================================= -->
    <div id="tabContent-mechanics" class="<?= $activeTab === 'mechanics' ? '' : 'hidden' ?> space-y-6">
        
        <!-- Filter Bar: Engine Type, Specialization & Towing -->
        <div class="bg-dark-900 border border-amber-500/30 rounded-3xl p-6 shadow-2xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-800">
                <div>
                    <h2 class="text-xl font-black text-white flex items-center gap-2">
                        <i class="fas fa-car-wrench text-amber-400"></i>
                        Garage Mechanics & Roadside Diagnostics
                        <span class="text-[10px] bg-amber-500/20 text-amber-300 font-bold px-2 py-0.5 rounded-full border border-amber-500/30 uppercase">Sri Lanka Network</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Filter by engine type (Hybrid/EV, Diesel, Petrol), workshop specialization, and emergency flatbed towing.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-400 font-mono" id="mechanicFilterTelemetry">Scanning 35km radius</span>
                </div>
            </div>

            <!-- Filters Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                <!-- Engine Type Filter -->
                <div>
                    <label class="block font-bold text-slate-300 mb-1.5 uppercase">Engine / Powertrain</label>
                    <select id="mechFilterEngine" onchange="fetchGarageMechanics()" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-amber-500 transition">
                        <option value="all">⚡ All Engine Types (Hybrid, Diesel, Petrol)</option>
                        <option value="Hybrid/EV">🔋 Hybrid & Electric (EV / Inverter / Battery)</option>
                        <option value="Diesel">🛢️ Common Rail Diesel & Turbo (CRDI)</option>
                        <option value="Petrol">⛽ Petrol & Electronic Fuel Injection (EFI)</option>
                    </select>
                </div>

                <!-- Specialization Filter -->
                <div>
                    <label class="block font-bold text-slate-300 mb-1.5 uppercase">Service Specialization</label>
                    <select id="mechFilterSpecialty" onchange="fetchGarageMechanics()" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-amber-500 transition">
                        <option value="all">All Garage Specialties</option>
                        <option value="Hybrid Diagnostics">Hybrid Battery Diagnostics & Cell Balancing</option>
                        <option value="Engine Repair">Engine Overhaul & Common Rail Diesel</option>
                        <option value="Bodywork">Bodywork, Denting & Accident Repair</option>
                        <option value="Emergency Towing">Emergency Flatbed & Heavy Towing</option>
                        <option value="Roadside">Roadside Battery Jump & Quick Repair</option>
                    </select>
                </div>

                <!-- Towing Truck Checkbox -->
                <div class="flex items-center">
                    <label class="p-3 bg-slate-950 rounded-xl border border-slate-800 flex items-center gap-2.5 cursor-pointer w-full hover:border-amber-500 transition">
                        <input type="checkbox" id="mechFilterTowing" onchange="fetchGarageMechanics()" class="w-4 h-4 rounded text-amber-500 bg-slate-900 border-slate-700 focus:ring-amber-500">
                        <span class="text-slate-300 font-semibold text-xs">
                            <i class="fas fa-truck-pickup text-amber-400 mr-1"></i> Towing Truck Required
                        </span>
                    </label>
                </div>

                <!-- Action Button -->
                <div class="flex items-end">
                    <button type="button" onclick="fetchGarageMechanics()" class="w-full py-2.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs transition shadow-lg shadow-amber-500/20 flex items-center justify-center gap-2">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Mechanics Map & Card Radar Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Left: Interactive Mechanics Map -->
            <div class="lg:col-span-7 space-y-4">
                <div class="bg-dark-900 border border-slate-800 rounded-3xl p-5 shadow-2xl relative overflow-hidden">
                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-map-marked-alt text-amber-400"></i>
                            <span class="text-xs font-bold text-white uppercase tracking-wider">Garages & Breakdown Units Map</span>
                        </div>
                        <span id="mechanicMapPinCount" class="text-xs font-mono text-amber-400">0 Garages Pinned</span>
                    </div>
                    <div id="mechanicsLiveMap" class="w-full h-96 rounded-2xl border border-slate-800 z-10"></div>
                    <div class="pt-3 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                        <span>Free OpenStreetMap Tiles + Nominatim Geocoding</span>
                        <span>Estimated Transit Speed: 30 km/h</span>
                    </div>
                </div>

                <!-- Garage List -->
                <div id="garageCardsContainer" class="space-y-3">
                    <div class="text-center py-8 text-slate-500 text-xs">Loading garage mechanics matching criteria...</div>
                </div>
            </div>

            <!-- Right: Direct Garage Booking Form -->
            <div class="lg:col-span-5">
                <div class="bg-dark-900 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-2xl">
                    <div class="pb-4 mb-4 border-b border-slate-800">
                        <h3 class="text-lg font-bold text-white">Book Garage Mechanic / Rescue</h3>
                        <p class="text-xs text-slate-400">Connect with licensed automotive engineers and recovery trucks</p>
                    </div>

                    <form id="mechanicBookingForm" onsubmit="handleMechanicBooking(event)" class="space-y-4">
                        <input type="hidden" name="service_type" value="mechanic">

                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Vehicle & Problem Summary</label>
                            <input type="text" id="mechProjectTitle" name="project_title" placeholder="e.g. Toyota Aqua Hybrid warning light / Flatbed tow needed" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition" required>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Vehicle Powertrain</label>
                                <select name="sub_category" id="mechSubCategoryForm" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-amber-500 transition">
                                    <option value="Hybrid & EV Diagnostics">Hybrid / EV</option>
                                    <option value="Common Rail Diesel Engine">Diesel Engine</option>
                                    <option value="Petrol EFI Engine">Petrol EFI</option>
                                    <option value="Emergency Roadside Recovery">Towing Rescue</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Estimated Budget (LKR)</label>
                                <input type="number" step="500" name="budget" id="mechBudget" value="4500" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-amber-500 transition" required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Breakdown / Vehicle Location</label>
                            <input type="text" id="mechAddress" name="customer_address" value="Galle Road, Colombo 03" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition" required>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Symptoms / Towing Requirements</label>
                            <textarea name="project_notes" id="mechNotes" rows="3" placeholder="e.g. Master warning light on dash. Car is stuck in neutral. Need flatbed towing from Thimbirigasyaya to workshop." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition"></textarea>
                        </div>

                        <input type="hidden" id="selectedMechanicProviderId" name="provider_id" value="">
                        <div id="assignedMechanicNotice" class="hidden p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-300 flex items-center justify-between">
                            <span id="assignedMechanicText">Assigned Garage:</span>
                            <button type="button" onclick="clearSelectedMechanic()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
                        </div>

                        <button type="submit" id="btnDispatchMechanic" class="w-full py-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 hover:from-amber-600 hover:to-orange-700 text-slate-950 font-black text-sm shadow-xl shadow-amber-500/25 transition transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <i class="fas fa-paper-plane text-base"></i>
                            <span>Confirm & Request Garage Service</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- TAB 3: 3D CAD & Digital Design Project Studio                            -->
    <!-- ========================================================================= -->
    <div id="tabContent-3d_design" class="<?= $activeTab === '3d_design' ? '' : 'hidden' ?> space-y-8">
        <div class="max-w-4xl mx-auto bg-dark-900 border border-slate-800 rounded-3xl p-6 sm:p-10 shadow-2xl">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 mb-8 border-b border-slate-800 gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/15 border border-indigo-500/30 flex items-center justify-center text-indigo-400 text-2xl">
                        <i class="fas fa-cube"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">3D Design & CAD Project Studio</h2>
                        <p class="text-xs text-slate-400">Order architectural renderings, 3D printable STL models, and parametric CAD drafts</p>
                    </div>
                </div>
                <span class="text-xs font-semibold px-3 py-1 rounded-full bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                    <i class="fas fa-shield-alt mr-1"></i> Escrow Guarantee
                </span>
            </div>

            <form id="cadBookingForm" onsubmit="handleCadBooking(event)" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="service_type" value="3d_design">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Project Deliverable Type</label>
                        <select id="cadSubCategory" name="sub_category" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="Interior 3D ArchViz & Renders">🛋️ Interior 3D ArchViz (4K Photorealistic Renderings)</option>
                            <option value="Product Prototyping (STL/3D Print)">🖨️ Product Prototyping (Watertight STL for 3D Printing)</option>
                            <option value="Mechanical Blueprints (SolidWorks/STEP)">⚙️ Mechanical CAD Blueprints (SolidWorks / STEP / CNC)</option>
                            <option value="Architectural BIM & Elevations">🏛️ Architectural Modeling (2D to 3D BIM / Revit)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Project Title</label>
                        <input type="text" id="cadProjectTitle" name="project_title" placeholder="e.g. Penthouse Living Room 3D Render / Drone Frame STL" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Estimated Budget (LKR)</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-xs text-indigo-400 font-bold">Rs.</span>
                            <input type="number" step="500" id="cadBudget" name="budget" value="15000" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-12 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Client Location / Zone</label>
                        <input type="text" name="customer_address" value="Colombo, Sri Lanka" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Design Specifications & Requirements</label>
                    <textarea id="cadNotes" name="project_notes" rows="4" placeholder="Detail dimensions, materials, color palettes, software preferences (Blender, SolidWorks, 3ds Max), or intended manufacturing method..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition" required></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Upload Reference Spec / Hand-Drawn Sketch / Dimensions</label>
                    <div class="border-2 border-dashed border-slate-700 hover:border-indigo-500/80 rounded-2xl p-6 text-center transition bg-slate-950/60 cursor-pointer" onclick="document.getElementById('cadReferenceFile').click()">
                        <i class="fas fa-cloud-upload-alt text-3xl text-indigo-400 mb-2"></i>
                        <p class="text-xs text-slate-300 font-semibold mb-1">Click to select reference image, sketch or CAD specification</p>
                        <p class="text-[11px] text-slate-500">Supports JPG, PNG, PDF, STL, OBJ, STEP, DWG, ZIP (Max: 30MB)</p>
                        <input type="file" id="cadReferenceFile" name="reference_file" class="hidden" onchange="previewSelectedFile(this, 'cadFileDisplay')">
                        <div id="cadFileDisplay" class="mt-3 text-xs font-mono text-indigo-300 font-bold"></div>
                    </div>
                </div>

                <button type="submit" id="btnSubmitCad" class="w-full py-4 rounded-xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-emerald-600 hover:from-indigo-500 hover:to-emerald-500 text-white font-extrabold text-sm shadow-xl shadow-indigo-600/30 transition transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane text-base"></i>
                    <span>Post 3D Project to Designer Radar</span>
                </button>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 4: Live Job Tracker, Real-Time Chat & PayHere Payments               -->
    <!-- ========================================================================= -->
    <div id="tabContent-my_bookings" class="<?= $activeTab === 'my_bookings' ? '' : 'hidden' ?> space-y-6">
        
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-satellite-dish text-brand-500"></i> Active Requests & Payments
                </h2>
                <p class="text-xs text-slate-400">Live chat with your assigned mechanic/designer, pay via PayHere/Genie, and submit ratings</p>
            </div>
            <button type="button" onclick="pollBookingsStatus(true)" class="px-3 py-1.5 rounded-xl bg-dark-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 flex items-center gap-1.5 transition">
                <i class="fas fa-sync-alt" id="refreshSpinner"></i> Refresh
            </button>
        </div>

        <div id="bookingsContainer" class="space-y-5">
            <?php if (empty($customerBookings)): ?>
                <div class="text-center py-16 bg-dark-900 border border-slate-800 rounded-3xl p-8 space-y-3">
                    <div class="w-16 h-16 rounded-2xl bg-slate-800/80 text-slate-500 mx-auto flex items-center justify-center text-2xl">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3 class="text-base font-bold text-white">No Service Requests Found</h3>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto">Choose a trade or garage mechanic above to place your first request.</p>
                </div>
            <?php else: ?>
                <?php foreach ($customerBookings as $b): ?>
                    <div class="bg-dark-900 border border-slate-800 hover:border-slate-700/80 rounded-2xl p-5 sm:p-6 shadow-xl space-y-4 transition">
                        
                        <!-- Header Row -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-800">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-xs font-bold text-slate-400 bg-slate-950 px-2.5 py-1 rounded-lg border border-slate-800">
                                    <?= sanitize($b['booking_code']) ?>
                                </span>
                                <?php if ($b['is_emergency']): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-red-400 bg-red-500/10 border border-red-500/30 px-2 py-0.5 rounded-full animate-pulse">
                                        <i class="fas fa-bolt"></i> SOS Emergency
                                    </span>
                                <?php endif; ?>
                                <span class="text-xs font-bold text-white">
                                    <?= sanitize(get_service_label($b['service_type'])) ?>
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <?= render_status_badge($b['status']) ?>
                                <?= render_payment_badge($b['payment_status'] ?? 'unpaid') ?>
                            </div>
                        </div>

                        <!-- Content Row -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-8 space-y-2">
                                <h3 class="text-sm font-bold text-white">
                                    <?= sanitize($b['project_title']) ?>
                                </h3>
                                <p class="text-xs text-slate-400 leading-relaxed">
                                    <?= nl2br(sanitize($b['project_notes'])) ?>
                                </p>

                                <?php if (!empty($b['reference_file_path'])): ?>
                                    <div class="pt-1">
                                        <a href="<?= sanitize($b['reference_file_path']) ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-indigo-400 hover:underline">
                                            <i class="fas fa-paperclip"></i> View Attached Reference Spec (<?= basename($b['reference_file_path']) ?>)
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="md:col-span-4 bg-slate-950 p-4 rounded-xl border border-slate-800/80 space-y-2 text-xs">
                                <?php if (!empty($b['provider_name'])): ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">Assigned Pro:</span>
                                        <span class="font-bold text-emerald-400"><?= sanitize(!empty($b['workshop_name']) ? $b['workshop_name'] : $b['provider_name']) ?></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">Contact:</span>
                                        <a href="tel:<?= sanitize($b['provider_phone'] ?? '') ?>" class="text-slate-300 font-mono hover:text-white"><?= sanitize($b['provider_phone'] ?? 'N/A') ?></a>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">Rating:</span>
                                        <?= render_stars((float)($b['provider_rating'] ?? 5.0)) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-slate-400 flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                                        <span>Broadcasting to radar...</span>
                                    </div>
                                <?php endif; ?>

                                <div class="pt-2 border-t border-slate-800 flex items-center justify-between">
                                    <span class="text-slate-500">Amount / Quote:</span>
                                    <span class="font-bold text-white"><?= $b['budget'] ? format_lkr((float)$b['budget']) : 'Negotiable' ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Deliverable Download Module (When 3D CAD/Service is Completed) -->
                        <?php if ($b['status'] === 'completed' && !empty($b['delivered_file_path'])): ?>
                            <div class="p-4 bg-emerald-950/20 border border-emerald-500/40 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-lg">
                                        <i class="fas fa-file-download"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-white flex items-center gap-2">
                                            <span>Deliverable Ready for Download</span>
                                            <span class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-500 text-slate-950 font-black">FINAL</span>
                                        </div>
                                        <div class="text-[11px] text-emerald-400 font-mono">
                                            <?= basename($b['delivered_file_path']) ?>
                                        </div>
                                        <?php if (!empty($b['delivery_notes'])): ?>
                                            <p class="text-[11px] text-slate-400 mt-0.5">"<?= sanitize($b['delivery_notes']) ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <a href="<?= sanitize($b['delivered_file_path']) ?>" download class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs shadow-lg transition">
                                    <i class="fas fa-download"></i>
                                    <span>Download File</span>
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- Interactive Action Controls: Chat Drawer, PayHere Gateway, Review Modal -->
                        <div class="pt-4 border-t border-slate-800 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <!-- Chat Button -->
                                <?php if (!empty($b['provider_id'])): ?>
                                    <?php $assignedDisplayName = !empty($b['workshop_name']) ? $b['workshop_name'] : (!empty($b['provider_name']) ? $b['provider_name'] : 'Service Pro'); ?>
                                    <button type="button" onclick="openChatModal(<?= (int)$b['id'] ?>, '<?= addslashes(sanitize(($b['booking_code'] ?? 'PF') . ' - ' . $assignedDisplayName)) ?>')" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs flex items-center gap-2 transition border border-slate-700">
                                        <i class="fas fa-comments text-brand-400"></i>
                                        <span>Message Pro</span>
                                        <?php if (!empty($b['unread_messages']) && $b['unread_messages'] > 0): ?>
                                            <span class="px-1.5 py-0.2 rounded-full bg-brand-500 text-slate-950 font-mono text-[10px] font-bold"><?= (int)$b['unread_messages'] ?></span>
                                        <?php endif; ?>
                                    </button>
                                <?php endif; ?>

                                <!-- PayHere / Payment Gateway Button -->
                                <?php if (($b['payment_status'] ?? 'unpaid') !== 'paid' && !empty($b['provider_id'])): ?>
                                    <button type="button" onclick="openPaymentModal(<?= (int)$b['id'] ?>, '<?= sanitize($b['booking_code'] ?? '') ?>', <?= floatval($b['budget'] ?? 3500) ?>)" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-black text-xs flex items-center gap-1.5 shadow-lg shadow-emerald-500/20 transition">
                                        <i class="fas fa-credit-card"></i> Pay via PayHere / Genie
                                    </button>
                                <?php endif; ?>

                                <!-- 5-Star Review Button -->
                                <?php if (($b['status'] ?? '') === 'completed'): ?>
                                    <?php if (!empty($b['customer_review_rating'])): ?>
                                        <div class="text-xs text-amber-400 flex items-center gap-1 font-semibold">
                                            <span>Your Rating:</span>
                                            <?= render_stars((float)$b['customer_review_rating']) ?>
                                        </div>
                                    <?php else: ?>
                                        <?php $revDisplayName = !empty($b['workshop_name']) ? $b['workshop_name'] : (!empty($b['provider_name']) ? $b['provider_name'] : 'Pro'); ?>
                                        <button type="button" onclick="openReviewModal(<?= (int)$b['id'] ?>, '<?= addslashes(sanitize($revDisplayName)) ?>')" class="px-3.5 py-2 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 font-bold text-xs flex items-center gap-1.5 border border-amber-500/30 transition">
                                            <i class="fas fa-star"></i> Rate & Review Pro
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($b['status'] === 'pending'): ?>
                                <button type="button" onclick="cancelBooking(<?= $b['id'] ?>)" class="text-xs text-rose-400 hover:text-rose-300 font-semibold transition">
                                    <i class="fas fa-times-circle mr-1"></i> Cancel Request
                                </button>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- ========================================================================= -->
<!-- MODAL 1: Real-Time Customer-Provider Chat Box                             -->
<!-- ========================================================================= -->
<div id="chatModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-dark-900 border border-slate-800 rounded-3xl max-w-lg w-full shadow-2xl flex flex-col h-[560px] overflow-hidden">
        
        <!-- Chat Header -->
        <div class="p-4 bg-slate-950 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-400 flex items-center justify-center font-bold">
                    <i class="fas fa-comments"></i>
                </div>
                <div>
                    <h3 id="chatModalTitle" class="text-sm font-bold text-white">Live Discussion</h3>
                    <p class="text-[11px] text-emerald-400 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Encrypted direct channel
                    </p>
                </div>
            </div>
            <button onclick="closeChatModal()" class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Chat Messages Container -->
        <div id="chatMessagesContainer" class="flex-1 p-4 overflow-y-auto space-y-3 bg-dark-950/50">
            <div class="text-center text-slate-500 text-xs py-10">Loading messages...</div>
        </div>

        <!-- Chat Input Form -->
        <form onsubmit="handleSendMessage(event)" class="p-3 bg-slate-950 border-t border-slate-800 flex items-center gap-2">
            <input type="hidden" id="chatActiveBookingId" value="">
            <input type="text" id="chatMessageInput" placeholder="Type instructions, directions, or ETA inquiries..." class="flex-1 bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-xs text-white focus:outline-none focus:border-brand-500 transition" required>
            <button type="submit" id="btnSendChatMessage" class="px-5 py-3 rounded-xl bg-brand-500 hover:bg-brand-600 text-slate-950 font-bold text-xs transition flex items-center gap-1.5">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>

    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: Simulated PayHere / Genie Monetization Gateway                   -->
<!-- ========================================================================= -->
<div id="paymentModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-dark-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold">
                    <i class="fas fa-wallet"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">PickFix Checkout</h3>
                    <p class="text-[11px] text-slate-400">Sri Lankan Secure Payment Flow</p>
                </div>
            </div>
            <button onclick="closePaymentModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <!-- Invoice Breakdown -->
        <div class="p-4 bg-slate-950 rounded-2xl border border-slate-800 space-y-2.5 text-xs">
            <div class="flex justify-between text-slate-400">
                <span>Booking Reference:</span>
                <span id="payModalRef" class="font-mono text-white font-bold"></span>
            </div>
            <div class="flex justify-between text-slate-400">
                <span>Agreed Service Fee:</span>
                <span id="payModalBase" class="text-white font-bold"></span>
            </div>
            <div class="flex justify-between text-slate-400">
                <span>PickFix Platform Fee (10%):</span>
                <span id="payModalFee" class="text-indigo-400 font-mono font-bold"></span>
            </div>
            <div class="pt-2 border-t border-slate-800 flex justify-between text-sm font-extrabold text-white">
                <span>Total Amount Due:</span>
                <span id="payModalTotal" class="text-emerald-400"></span>
            </div>
        </div>

        <form id="paymentForm" onsubmit="handleProcessPayment(event)" class="space-y-4">
            <input type="hidden" id="payBookingId" value="">
            <input type="hidden" id="payAmountVal" value="">

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Select Payment Gateway</label>
                <div class="space-y-2 text-xs">
                    <label class="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between cursor-pointer hover:border-emerald-500 transition">
                        <div class="flex items-center gap-2.5">
                            <input type="radio" name="payment_gateway" value="payhere" checked class="text-emerald-500 focus:ring-emerald-500">
                            <span class="font-bold text-white">PayHere Online Gateway</span>
                        </div>
                        <span class="text-[10px] text-emerald-400 font-mono">Visa / Master / FriMi</span>
                    </label>

                    <label class="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between cursor-pointer hover:border-emerald-500 transition">
                        <div class="flex items-center gap-2.5">
                            <input type="radio" name="payment_gateway" value="genie" class="text-emerald-500 focus:ring-emerald-500">
                            <span class="font-bold text-white">Dialog Genie Wallet</span>
                        </div>
                        <span class="text-[10px] text-amber-400 font-mono">Direct Wallet Debit</span>
                    </label>

                    <label class="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between cursor-pointer hover:border-emerald-500 transition">
                        <div class="flex items-center gap-2.5">
                            <input type="radio" name="payment_gateway" value="cash" class="text-emerald-500 focus:ring-emerald-500">
                            <span class="font-bold text-white">Cash on Delivery / On-Site</span>
                        </div>
                        <span class="text-[10px] text-slate-400 font-mono">In-Person Cash</span>
                    </label>
                </div>
            </div>

            <button type="submit" id="btnSubmitPayment" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-slate-950 font-black text-sm shadow-lg shadow-emerald-500/25 transition flex items-center justify-center gap-2">
                <i class="fas fa-lock"></i>
                <span id="btnPayLabel">Authorize Payment</span>
            </button>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 3: 5-Star Customer Rating & Review Form                             -->
<!-- ========================================================================= -->
<div id="reviewModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-dark-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fas fa-star text-amber-400"></i> Rate Service & Workshop
            </h3>
            <button onclick="closeReviewModal()" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form onsubmit="handleReviewSubmit(event)" class="space-y-4">
            <input type="hidden" id="reviewBookingId" value="">

            <div>
                <span class="block text-xs font-bold text-slate-300 uppercase mb-2" id="reviewProName">Service Provider</span>
                <div class="flex items-center justify-center gap-2 p-3 bg-slate-950 rounded-2xl border border-slate-800">
                    <input type="hidden" id="selectedStarRating" value="5">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <button type="button" onclick="setStarRating(<?= $s ?>)" id="starBtn_<?= $s ?>" class="text-2xl text-amber-400 hover:scale-125 transition transform">
                            <i class="fas fa-star"></i>
                        </button>
                    <?php endfor; ?>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1.5">Feedback / Comments</label>
                <textarea id="reviewCommentInput" rows="3" placeholder="Share your experience (e.g. fast turnaround, honest diagnostic, high quality CAD render)..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-amber-500 transition" required></textarea>
            </div>

            <button type="submit" id="btnSubmitReview" class="w-full py-3.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs uppercase tracking-wider transition shadow-lg shadow-amber-500/20">
                Submit Public Review
            </button>
        </form>
    </div>
</div>

<!-- PickMe-Style CSS Animations for Real-Time Radar & Transit Telemetry -->
<style>
.client-pulse-dot {
    position: relative;
    width: 24px;
    height: 24px;
    background: #0284c7;
    border: 3px solid #ffffff;
    border-radius: 50%;
    box-shadow: 0 0 16px rgba(2, 132, 199, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
}
.client-pulse-dot::after {
    content: '';
    position: absolute;
    top: -12px;
    left: -12px;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 2px solid rgba(2, 132, 199, 0.7);
    animation: client-pulse-wave 2s infinite ease-out;
}
@keyframes client-pulse-wave {
    0% { transform: scale(0.5); opacity: 1; }
    100% { transform: scale(2.4); opacity: 0; }
}

.radar-sweep-beam {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 220px;
    height: 220px;
    margin-top: -110px;
    margin-left: -110px;
    border-radius: 50%;
    background: conic-gradient(from 0deg, rgba(16, 185, 129, 0.45) 0deg, rgba(16, 185, 129, 0) 65deg);
    animation: radar-sweep-spin 3s linear infinite;
    pointer-events: none;
    z-index: 450;
}
@keyframes radar-sweep-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.provider-live-pin {
    background: #10b981;
    color: #020617;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 3px solid #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.6);
    font-size: 15px;
    transition: all 0.5s ease-in-out;
}
.provider-live-pin.mechanic-pin {
    background: #f59e0b;
}

.pulse-badge {
    animation: pulse-glow 1.5s infinite;
}
@keyframes pulse-glow {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.85; transform: scale(1.03); }
}
</style>

<!-- Leaflet, Free OpenStreetMap, Geolocation & Live Dispatch JavaScript -->
<script>
let physicalMap = null;
let mechanicsMap = null;
let transitMap = null;
let clientLat = 6.92710000;
let clientLng = 79.86120000;
let clientAccuracy = 15;
let watchPositionId = null;

let physicalMarkers = [];
let mechanicsMarkers = [];
let clientPins = [];

// Transit and simulation state
let activeTransitBooking = null;
let activeTransitPolyline = null;
let activeProviderMarker = null;
let transitSimulationInterval = null;
let lastKnownProStatus = null;
let activeChatPollInterval = null;

// Initial payload from server
let serverBookings = <?= json_encode($customerBookings) ?>;

document.addEventListener('DOMContentLoaded', function() {
    initMaps();
    startWatchingCustomerLocation();
    fetchNearbyGeneralPros('plumber');
    fetchGarageMechanics();

    // Check if user has an existing active booking to display on load
    checkInitialActiveBooking();

    // Background polling loop (every 4 seconds for real-time status & transit sync)
    setInterval(() => pollBookingsStatus(false), 4000);
});

// Calculate Haversine distance in kilometers
function calculateHaversineKm(lat1, lon1, lat2, lon2) {
    const R = 6371.0;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return parseFloat((R * c).toFixed(2));
}

// Calculate estimated transit arrival time (ETA) in minutes
function calculateTransitEta(distanceKm) {
    const avgSpeedKmh = 28.0;
    return Math.max(3, Math.round((distanceKm / avgSpeedKmh) * 60) + 3);
}

// Continuous HTML5 Geolocation Tracking with navigator.geolocation.watchPosition
function startWatchingCustomerLocation() {
    if (!('geolocation' in navigator)) {
        updateGpsBadge("GPS Unavailable (Using Colombo)", false);
        updateClientPins();
        return;
    }

    const options = {
        enableHighAccuracy: true,
        timeout: 20000,
        maximumAge: 5000
    };

    watchPositionId = navigator.geolocation.watchPosition(
        (pos) => {
            clientLat = pos.coords.latitude;
            clientLng = pos.coords.longitude;
            clientAccuracy = pos.coords.accuracy || 10;

            // Sync hidden form coordinates
            ['custLat', 'mechLat'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = clientLat;
            });
            ['custLng', 'mechLng'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = clientLng;
            });

            updateGpsBadge(`GPS Live (±${Math.round(clientAccuracy)}m)`, true);
            updateClientPins();
        },
        (err) => {
            console.warn("watchPosition notice:", err.message);
            updateGpsBadge("Default GPS (Colombo)", false);
            updateClientPins();
        },
        options
    );
}

function refreshGPSLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            clientLat = pos.coords.latitude;
            clientLng = pos.coords.longitude;
            updateClientPins();
            if (physicalMap) physicalMap.setView([clientLat, clientLng], 14);
            if (mechanicsMap) mechanicsMap.setView([clientLat, clientLng], 14);
            fetchNearbyGeneralPros(document.getElementById('physicalServiceType').value);
            fetchGarageMechanics();
        });
    }
}

function updateGpsBadge(label, isLive) {
    const badge = document.getElementById('liveGpsStatusBadge');
    if (!badge) return;
    if (isLive) {
        badge.className = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-mono font-bold";
        badge.innerHTML = `<span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span><span>${label}</span>`;
    } else {
        badge.className = "inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-mono font-bold";
        badge.innerHTML = `<span class="w-2 h-2 rounded-full bg-amber-400"></span><span>${label}</span>`;
    }
}

// Update customer pins across all visible Leaflet maps
function updateClientPins() {
    clientPins.forEach(p => {
        if (p && p._map) p._map.removeLayer(p);
    });
    clientPins = [];

    const createPin = () => L.divIcon({
        className: 'client-custom-marker',
        html: `<div class="client-pulse-dot"><i class="fas fa-street-view text-white text-[10px]"></i></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });

    if (physicalMap) {
        const pPin = L.marker([clientLat, clientLng], { icon: createPin() }).addTo(physicalMap).bindPopup("<strong>Your Location</strong>");
        clientPins.push(pPin);
    }
    if (mechanicsMap) {
        const mPin = L.marker([clientLat, clientLng], { icon: createPin() }).addTo(mechanicsMap).bindPopup("<strong>Your Location</strong>");
        clientPins.push(mPin);
    }
    if (transitMap) {
        const tPin = L.marker([clientLat, clientLng], { icon: createPin() }).addTo(transitMap).bindPopup("<strong>Service Destination</strong>");
        clientPins.push(tPin);
    }
}

function checkInitialActiveBooking() {
    if (!serverBookings || serverBookings.length === 0) return;
    const active = serverBookings.find(b => ['pending', 'accepted', 'in_progress'].includes(b.status));
    if (active) {
        renderActiveDispatchConsole(active);
    }
}

// Renders either Radar Scanning Console (if pending) OR Live Transit Map (if accepted/in_progress)
function renderActiveDispatchConsole(b) {
    const container = document.getElementById('liveDispatchConsole');
    if (!container) return;
    container.classList.remove('hidden');

    activeTransitBooking = b;

    const custLat = parseFloat(b.customer_lat) || clientLat;
    const custLng = parseFloat(b.customer_lng) || clientLng;
    const provLat = parseFloat(b.provider_lat) || (custLat + 0.022);
    const provLng = parseFloat(b.provider_lng) || (custLng + 0.018);

    const distanceKm = calculateHaversineKm(custLat, custLng, provLat, provLng);
    const etaMinutes = calculateTransitEta(distanceKm);

    // MODE 1: Pending Radar Broadcast State (Scanning Sector)
    if (b.status === 'pending') {
        if (transitSimulationInterval) {
            clearInterval(transitSimulationInterval);
            transitSimulationInterval = null;
        }

        container.innerHTML = `
            <div class="bg-dark-900 border-2 ${b.is_emergency ? 'border-red-500 shadow-2xl shadow-red-500/20' : 'border-brand-500 shadow-2xl shadow-brand-500/20'} rounded-3xl p-6 sm:p-8 space-y-6 relative overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
                    <div class="flex items-center gap-3">
                        <span class="w-4 h-4 rounded-full bg-emerald-400 animate-ping"></span>
                        <div>
                            <span class="text-xs font-black uppercase tracking-widest text-emerald-400">PICKFIX LIVE DISPATCH RADAR</span>
                            <h2 class="text-xl font-black text-white flex items-center gap-2">
                                <span>Scanning Sector for Nearest Pro...</span>
                                <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-950 text-slate-300 border border-slate-800">${b.booking_code}</span>
                            </h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="cancelBooking(${b.id})" class="px-4 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-xs font-bold border border-rose-500/30 transition">
                            <i class="fas fa-times-circle mr-1"></i> Cancel Request
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                    <div class="lg:col-span-7 relative h-72 rounded-2xl overflow-hidden border border-slate-800">
                        <div id="activeTransitMap" class="w-full h-full z-10"></div>
                        <div class="radar-sweep-beam"></div>
                    </div>

                    <div class="lg:col-span-5 space-y-4">
                        <div class="p-4 bg-slate-950 rounded-2xl border border-slate-800 space-y-3 text-xs">
                            <div class="flex items-center justify-between text-slate-400">
                                <span>Service Requested:</span>
                                <span class="font-bold text-white capitalize">${b.service_type}</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <span>Emergency Priority:</span>
                                <span class="font-bold text-red-400">${b.is_emergency ? '⚡ YES (15-Min Fast Dispatch)' : 'Standard'}</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <span>Broadcasting Range:</span>
                                <span class="font-bold text-emerald-400 font-mono">15.0 km Radial Zone</span>
                            </div>
                            <div class="flex items-center justify-between text-slate-400">
                                <span>Service Address:</span>
                                <span class="font-semibold text-slate-200 truncate max-w-[200px]">${b.customer_address || 'Colombo Area'}</span>
                            </div>
                        </div>

                        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs flex items-center gap-3">
                            <i class="fas fa-satellite-dish text-xl animate-spin"></i>
                            <div>
                                <span class="font-bold block">Sector Providers Alerted</span>
                                <span class="text-[11px] text-emerald-300/80">Awaiting provider acceptance. Estimated response: &lt; 45 seconds.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        setTimeout(() => {
            initTransitRadarMap(custLat, custLng, true);
        }, 150);

    // MODE 2: Pro Accepted & En Route (Live PickMe Transit Tracker)
    } else if (b.status === 'accepted' || b.status === 'in_progress') {
        const proName = b.workshop_name || b.provider_name || 'Assigned Pro';
        const proPhone = b.provider_phone || '+94 77 123 4567';

        container.innerHTML = `
            <div class="bg-dark-900 border-2 border-emerald-500/80 shadow-2xl shadow-emerald-500/20 rounded-3xl p-6 sm:p-8 space-y-6 relative overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
                    <div class="flex items-center gap-3">
                        <span class="w-3.5 h-3.5 rounded-full bg-emerald-400 animate-ping"></span>
                        <div>
                            <span class="text-xs font-black uppercase tracking-widest text-emerald-400">PRO EN ROUTE • LIVE GPS TRANSIT TRACKING</span>
                            <h2 class="text-xl font-black text-white flex items-center gap-2">
                                <span>${proName} is Heading to You</span>
                                <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-950 text-slate-300 border border-slate-800">${b.booking_code}</span>
                            </h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span id="liveTransitEtaBadge" class="px-3.5 py-1.5 rounded-xl bg-emerald-500 text-slate-950 font-black text-xs font-mono shadow-md pulse-badge">
                            ${etaMinutes} MINS ETA
                        </span>
                        <span id="liveTransitDistBadge" class="px-3 py-1.5 rounded-xl bg-slate-800 text-emerald-400 font-bold text-xs font-mono border border-slate-700">
                            ${distanceKm} km away
                        </span>
                    </div>
                </div>

                <!-- Live Transit Map View -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                    <div class="lg:col-span-8 relative h-80 rounded-2xl overflow-hidden border border-slate-800">
                        <div id="activeTransitMap" class="w-full h-full z-10"></div>
                    </div>

                    <div class="lg:col-span-4 space-y-4">
                        <div class="p-4 bg-slate-950 rounded-2xl border border-slate-800 space-y-3 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Assigned Pro:</span>
                                <span class="font-bold text-white">${proName}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Category:</span>
                                <span class="font-bold text-brand-400 capitalize">${b.service_type}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Direct Contact:</span>
                                <a href="tel:${proPhone}" class="text-emerald-400 font-mono font-bold hover:underline">${proPhone}</a>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Agreed Rate:</span>
                                <span class="font-bold text-white">${b.budget ? 'Rs. ' + Number(b.budget).toLocaleString() : 'Negotiable'}</span>
                            </div>
                        </div>

                        <!-- Direct Call and Chat Actions -->
                        <div class="flex items-center gap-3">
                            <a href="tel:${proPhone}" class="flex-1 py-3 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs flex items-center justify-center gap-2 border border-slate-700 transition">
                                <i class="fas fa-phone text-emerald-400"></i> Call Pro
                            </a>
                            <button type="button" onclick="openChatModal(${b.id}, '${b.booking_code} - ${proName}')" class="flex-1 py-3 rounded-xl bg-brand-500 hover:bg-brand-600 text-slate-950 font-black text-xs flex items-center justify-center gap-2 transition shadow-md">
                                <i class="fas fa-comment-dots"></i> Live Chat
                            </button>
                        </div>

                        <!-- PayHere Quick Trigger -->
                        ${(b.payment_status || 'unpaid') !== 'paid' ? `
                            <button type="button" onclick="openPaymentModal(${b.id}, '${b.booking_code}', ${Number(b.budget || 3500)})" class="w-full py-2.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 text-xs font-bold border border-emerald-500/30 flex items-center justify-center gap-2 transition">
                                <i class="fas fa-credit-card"></i> Pay via PayHere / Genie
                            </button>
                        ` : `
                            <div class="p-2.5 rounded-xl bg-emerald-500/10 text-emerald-400 text-xs text-center font-bold">
                                <i class="fas fa-check-circle mr-1"></i> Paid in Full
                            </div>
                        `}
                    </div>
                </div>
            </div>
        `;

        setTimeout(() => {
            initTransitLiveRouteMap(custLat, custLng, provLat, provLng, b);
        }, 150);

    // MODE 3: Completed Service
    } else if (b.status === 'completed') {
        container.innerHTML = `
            <div class="bg-dark-900 border border-emerald-500/50 rounded-3xl p-6 shadow-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                    <div>
                        <div class="text-xs font-mono text-emerald-400 font-bold uppercase">Service Completed</div>
                        <h3 class="text-base font-black text-white">${b.project_title || 'Work Finished'} (${b.booking_code})</h3>
                        <p class="text-xs text-slate-400">Provider has delivered the service. Please settle payment and leave a rating.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    ${(b.payment_status || 'unpaid') !== 'paid' ? `
                        <button type="button" onclick="openPaymentModal(${b.id}, '${b.booking_code}', ${Number(b.budget || 3500)})" class="px-4 py-2.5 rounded-xl bg-emerald-500 text-slate-950 font-black text-xs transition shadow-md">
                            Pay via PayHere
                        </button>
                    ` : ''}
                    <button type="button" onclick="openReviewModal(${b.id}, '${b.workshop_name || b.provider_name || 'Pro'}')" class="px-4 py-2.5 rounded-xl bg-amber-500 text-slate-950 font-black text-xs transition shadow-md">
                        Leave 5★ Review
                    </button>
                </div>
            </div>
        `;
    }
}

// Initialize Leaflet Map for Pending Radar Scanning
function initTransitRadarMap(lat, lng, isPending) {
    const el = document.getElementById('activeTransitMap');
    if (!el) return;

    if (transitMap) {
        transitMap.remove();
        transitMap = null;
    }

    const osmTileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    transitMap = L.map('activeTransitMap', { zoomControl: false }).setView([lat, lng], 13);
    L.tileLayer(osmTileUrl, { maxZoom: 19 }).addTo(transitMap);

    const clientIcon = L.divIcon({
        className: 'client-center-pin',
        html: `<div class="client-pulse-dot"><i class="fas fa-broadcast-tower text-white text-[10px]"></i></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });
    L.marker([lat, lng], { icon: clientIcon }).addTo(transitMap).bindPopup("Your Location");

    // Add surrounding radar blips
    const blips = [
        [lat + 0.012, lng + 0.015],
        [lat - 0.014, lng + 0.018],
        [lat + 0.020, lng - 0.010],
        [lat - 0.008, lng - 0.019]
    ];
    blips.forEach(pos => {
        const blipIcon = L.divIcon({
            className: 'radar-blip-pin',
            html: `<div style="background: rgba(16, 185, 129, 0.4); width: 14px; height: 14px; border-radius: 50%; border: 2px solid #10b981; animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;"></div>`,
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });
        L.marker(pos, { icon: blipIcon }).addTo(transitMap);
    });

    // Draw dispatch radius circle
    L.circle([lat, lng], {
        color: '#10b981',
        fillColor: '#10b981',
        fillOpacity: 0.08,
        radius: 2500
    }).addTo(transitMap);
}

// Initialize Leaflet Map for Live PickMe Route & Movement Simulation
function initTransitLiveRouteMap(custLat, custLng, provLat, provLng, booking) {
    const el = document.getElementById('activeTransitMap');
    if (!el) return;

    if (transitMap) {
        transitMap.remove();
        transitMap = null;
    }

    const osmTileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    transitMap = L.map('activeTransitMap', { zoomControl: true }).setView([(custLat + provLat) / 2, (custLng + provLng) / 2], 13);
    L.tileLayer(osmTileUrl, { maxZoom: 19 }).addTo(transitMap);

    // 1. Customer Marker (Blue pulsating)
    const custIcon = L.divIcon({
        className: 'client-center-pin',
        html: `<div class="client-pulse-dot"><i class="fas fa-home text-white text-[10px]"></i></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });
    L.marker([custLat, custLng], { icon: custIcon }).addTo(transitMap).bindPopup("Your Location");

    // 2. Provider Vehicle Marker
    const isMech = (booking.service_type === 'mechanic');
    const provIcon = L.divIcon({
        className: 'pro-moving-pin',
        html: `<div class="provider-live-pin ${isMech ? 'mechanic-pin' : ''}"><i class="fas ${isMech ? 'fa-car-side' : 'fa-van-shuttle'}"></i></div>`,
        iconSize: [38, 38],
        iconAnchor: [19, 19]
    });
    activeProviderMarker = L.marker([provLat, provLng], { icon: provIcon }).addTo(transitMap);

    // 3. Draw Route Polyline
    activeTransitPolyline = L.polyline([[provLat, provLng], [custLat, custLng]], {
        color: isMech ? '#f59e0b' : '#10b981',
        weight: 5,
        opacity: 0.85,
        dashArray: '8, 8'
    }).addTo(transitMap);

    transitMap.fitBounds(activeTransitPolyline.getBounds(), { padding: [40, 40] });

    // Start Real-Time Route Movement Simulation
    startRouteSimulation(booking, provLat, provLng, custLat, custLng);
}

// Route Movement Simulation Engine (Interpolates vehicle along waypoints with dynamic distance & countdown ETA)
function startRouteSimulation(booking, startLat, startLng, targetLat, targetLng) {
    if (transitSimulationInterval) {
        clearInterval(transitSimulationInterval);
    }

    const totalSteps = 24;
    let step = 0;

    // Generate waypoints with realistic street curvature
    const waypoints = [];
    for (let i = 0; i <= totalSteps; i++) {
        const ratio = i / totalSteps;
        // Minor sinusoidal offset for realistic curved transit path
        const curveOffset = Math.sin(ratio * Math.PI) * 0.003;
        const lat = startLat + (targetLat - startLat) * ratio + curveOffset;
        const lng = startLng + (targetLng - startLng) * ratio;
        waypoints.push([lat, lng]);
    }

    transitSimulationInterval = setInterval(() => {
        if (!activeProviderMarker || !transitMap) {
            clearInterval(transitSimulationInterval);
            return;
        }

        step++;
        if (step >= waypoints.length) {
            step = waypoints.length - 1;
            clearInterval(transitSimulationInterval);
            updateEtaDisplay(0.05, 1);
            return;
        }

        const currentPos = waypoints[step];
        activeProviderMarker.setLatLng(currentPos);

        // Update remaining route polyline
        if (activeTransitPolyline) {
            activeTransitPolyline.setLatLngs([currentPos, [targetLat, targetLng]]);
        }

        const remainingKm = calculateHaversineKm(currentPos[0], currentPos[1], targetLat, targetLng);
        const dynamicEta = calculateTransitEta(remainingKm);

        updateEtaDisplay(remainingKm, dynamicEta);
    }, 1800);
}

function updateEtaDisplay(km, mins) {
    const etaBadge = document.getElementById('liveTransitEtaBadge');
    const distBadge = document.getElementById('liveTransitDistBadge');

    if (km <= 0.1) {
        if (etaBadge) etaBadge.innerText = "ARRIVED ON SITE!";
        if (distBadge) distBadge.innerText = "At Location";
    } else {
        if (etaBadge) etaBadge.innerText = `${mins} MINS ETA`;
        if (distBadge) distBadge.innerText = `${km} km away`;
    }
}

function refreshGPSLocation() {
    detectCustomerLocation();
}

// Fetch Nearby General Trades
function fetchNearbyGeneralPros(category) {
    const list = document.getElementById('generalProsList');
    const count = document.getElementById('generalProsCount');

    fetch(`api/get_nearby_providers.php?category=${encodeURIComponent(category)}&lat=${clientLat}&lng=${clientLng}&radius=35`)
        .then(r => r.json())
        .then(data => {
            physicalMarkers.forEach(m => physicalMap.removeLayer(m));
            physicalMarkers = [];

            if (data.success && data.data.providers.length > 0) {
                count.innerText = `${data.data.providers.length} Verified Online`;
                list.innerHTML = '';

                data.data.providers.forEach(p => {
                    const marker = L.marker([p.lat, p.lng]).addTo(physicalMap).bindPopup(`<strong>${p.name}</strong><br>${p.distance_str} (${p.eta_str})<br>${p.rate_display}`);
                    physicalMarkers.push(marker);

                    const div = document.createElement('div');
                    div.className = "p-3 bg-slate-950/70 hover:bg-slate-950 rounded-2xl border border-slate-800 flex items-center justify-between gap-3 text-xs";
                    div.innerHTML = `
                        <div>
                            <div class="font-bold text-white">${p.name} <span class="text-amber-400 text-[10px]">★ ${p.rating.toFixed(1)}</span></div>
                            <div class="text-[11px] text-slate-400">${p.distance_str} • <span class="text-brand-400">${p.eta_str}</span></div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-white">${p.rate_display}</div>
                            <button type="button" onclick="selectGeneralPro(${p.id}, '${p.name}')" class="mt-1 px-3 py-1 rounded-lg bg-dark-800 hover:bg-brand-500 hover:text-slate-950 text-slate-300 text-[11px] font-bold border border-slate-700">Select</button>
                        </div>
                    `;
                    list.appendChild(div);
                });
            } else {
                count.innerText = "0 Available";
                list.innerHTML = `<div class="p-4 text-center text-xs text-slate-400 bg-slate-950/50 rounded-xl">No active pro in sector. Request will broadcast islandwide.</div>`;
            }
        });
}

function selectGeneralPro(id, name) {
    document.getElementById('selectedGeneralProviderId').value = id;
    document.getElementById('assignedGeneralProText').innerText = `Direct Request for: ${name}`;
    document.getElementById('assignedGeneralProNotice').classList.remove('hidden');
    document.getElementById('physicalBookingForm').scrollIntoView({ behavior: 'smooth' });
}

function clearSelectedGeneralPro() {
    document.getElementById('selectedGeneralProviderId').value = '';
    document.getElementById('assignedGeneralProNotice').classList.add('hidden');
}

// Fetch Garage Mechanics with Advanced Filters (Engine Type, Towing, Specialty)
function fetchGarageMechanics() {
    const engine = document.getElementById('mechFilterEngine').value;
    const specialty = document.getElementById('mechFilterSpecialty').value;
    const towing = document.getElementById('mechFilterTowing').checked ? '1' : '0';
    const container = document.getElementById('garageCardsContainer');
    const pinCount = document.getElementById('mechanicMapPinCount');

    container.innerHTML = `<div class="text-center py-6 text-slate-500 text-xs"><i class="fas fa-spinner fa-spin mr-1"></i> Querying nearby mechanics...</div>`;

    fetch(`api/get_nearby_providers.php?category=mechanic&engine_type=${encodeURIComponent(engine)}&specialty=${encodeURIComponent(specialty)}&towing_only=${towing}&lat=${clientLat}&lng=${clientLng}&radius=40`)
        .then(r => r.json())
        .then(data => {
            mechanicsMarkers.forEach(m => mechanicsMap.removeLayer(m));
            mechanicsMarkers = [];

            if (data.success && data.data.providers.length > 0) {
                pinCount.innerText = `${data.data.providers.length} Garages Located`;
                container.innerHTML = '';

                data.data.providers.forEach(g => {
                    // Custom pin for garage
                    const gIcon = L.divIcon({
                        className: 'garage-map-pin',
                        html: `<div style="background-color: #f59e0b; width: 34px; height: 34px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;"><i class="fas fa-car-wrench" style="color: #020617; font-size: 14px;"></i></div>`,
                        iconSize: [34, 34],
                        iconAnchor: [17, 17]
                    });

                    const marker = L.marker([g.lat, g.lng], { icon: gIcon })
                        .addTo(mechanicsMap)
                        .bindPopup(`
                            <div style="font-family: sans-serif; font-size: 12px; color: #0f172a;">
                                <strong>${g.workshop_name}</strong><br>
                                <span style="color: #d97706; font-weight: bold;">${g.sub_category}</span><br>
                                <span>${g.distance_str} (${g.eta_str})</span><br>
                                <button onclick="selectGarageForBooking(${g.id}, '${g.workshop_name}')" style="margin-top: 5px; background: #d97706; color: white; border: none; padding: 4px 10px; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: bold;">Book This Garage</button>
                            </div>
                        `);
                    mechanicsMarkers.push(marker);

                    // Add Card
                    const card = document.createElement('div');
                    card.className = "bg-dark-900 border border-slate-800 hover:border-amber-500/80 rounded-2xl p-4 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition";
                    card.innerHTML = `
                        <div class="space-y-1.5 flex-1">
                            <div class="flex items-center gap-2">
                                <h4 class="text-sm font-bold text-white">${g.workshop_name}</h4>
                                ${g.towing_available ? '<span class="px-2 py-0.5 rounded text-[10px] bg-red-500/15 text-red-400 font-bold border border-red-500/30"><i class="fas fa-truck-pickup mr-1"></i> Towing Truck</span>' : ''}
                            </div>
                            <div class="text-xs text-amber-400 font-medium">${g.sub_category}</div>
                            <div class="text-[11px] text-slate-400">
                                <span class="text-slate-300 font-semibold">${g.engine_types}</span> • ${g.distance_str} away (<span class="text-emerald-400 font-bold">${g.eta_str}</span>)
                            </div>
                            <div class="flex items-center gap-2 pt-1 text-[11px]">
                                ${g.stars_html}
                                <span class="text-slate-500 font-mono">(${g.reviews_count} reviews • ${g.total_jobs} jobs)</span>
                            </div>
                        </div>
                        <div class="text-right flex sm:flex-col items-center sm:items-end justify-between sm:justify-center gap-2">
                            <div class="text-xs font-black text-white">${g.rate_display}</div>
                            <button type="button" onclick="selectGarageForBooking(${g.id}, '${g.workshop_name}')" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-xs transition shadow-md">
                                Book Garage
                            </button>
                        </div>
                    `;
                    container.appendChild(card);
                });
            } else {
                pinCount.innerText = "0 Garages";
                container.innerHTML = `<div class="p-6 text-center text-xs text-slate-400 bg-slate-950/50 rounded-2xl">No garages found matching this specific powertrain or towing filter in a 40km radius. Try setting Engine to "All Engine Types".</div>`;
            }
        });
}

function selectGarageForBooking(id, workshopName) {
    document.getElementById('selectedMechanicProviderId').value = id;
    document.getElementById('assignedMechanicText').innerText = `Assigned Garage: ${workshopName}`;
    document.getElementById('assignedMechanicNotice').classList.remove('hidden');
    document.getElementById('mechanicBookingForm').scrollIntoView({ behavior: 'smooth' });
}

function clearSelectedMechanic() {
    document.getElementById('selectedMechanicProviderId').value = '';
    document.getElementById('assignedMechanicNotice').classList.add('hidden');
}

// Tab Switching
function switchDashboardTab(tabId) {
    ['physical', 'mechanics', '3d_design', 'my_bookings'].forEach(t => {
        document.getElementById('tabContent-' + t)?.classList.add('hidden');
        const btn = document.getElementById('tabBtn-' + t);
        if (btn) btn.className = "flex-1 min-w-[130px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-400 hover:text-white";
    });

    document.getElementById('tabContent-' + tabId)?.classList.remove('hidden');
    const activeBtn = document.getElementById('tabBtn-' + tabId);
    if (activeBtn) {
        if (tabId === 'physical') activeBtn.className = "flex-1 min-w-[130px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-brand-500 text-slate-950 shadow-md";
        else if (tabId === 'mechanics') activeBtn.className = "flex-1 min-w-[140px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-amber-500 text-slate-950 shadow-md";
        else if (tabId === '3d_design') activeBtn.className = "flex-1 min-w-[130px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-indigo-600 text-white shadow-md";
        else activeBtn.className = "flex-1 min-w-[140px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-slate-800 text-white border border-slate-700";
    }

    if (tabId === 'physical' && physicalMap) setTimeout(() => physicalMap.invalidateSize(), 200);
    if (tabId === 'mechanics' && mechanicsMap) setTimeout(() => mechanicsMap.invalidateSize(), 200);
}

// Emergency SOS Trigger
function triggerEmergencySOS() {
    switchDashboardTab('physical');
    document.getElementById('physicalIsEmergency').checked = true;
    document.getElementById('physicalProjectTitle').value = "URGENT EMERGENCY: Main Burst Pipe / Rapid Response";
    document.getElementById('btnDispatchPhysical').focus();
    alert('Emergency SOS Armed! Verify address and confirm broadcast.');
}

// Handle Submissions
function handlePhysicalBooking(e) {
    e.preventDefault();
    const btn = document.getElementById('btnDispatchPhysical');
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i> Transmitting Request to Radar...`;

    const form = document.getElementById('physicalBookingForm');
    const fd = new FormData(form);
    fd.append('customer_lat', clientLat);
    fd.append('customer_lng', clientLng);

    fetch('api/create_booking.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-satellite-dish text-base mr-2"></i> Confirm & Broadcast Trade Request`;
            if (res.success) {
                form.reset();
                renderActiveDispatchConsole(res.data.booking);
                document.getElementById('liveDispatchConsole').scrollIntoView({ behavior: 'smooth' });
                pollBookingsStatus(false);
            } else alert(res.message);
        });
}

function handleMechanicBooking(e) {
    e.preventDefault();
    const btn = document.getElementById('btnDispatchMechanic');
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i> Dispatching Garage Radar Request...`;

    const form = document.getElementById('mechanicBookingForm');
    const fd = new FormData(form);
    fd.append('customer_lat', clientLat);
    fd.append('customer_lng', clientLng);

    fetch('api/create_booking.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-paper-plane text-base mr-2"></i> Confirm & Request Garage Service`;
            if (res.success) {
                form.reset();
                renderActiveDispatchConsole(res.data.booking);
                document.getElementById('liveDispatchConsole').scrollIntoView({ behavior: 'smooth' });
                pollBookingsStatus(false);
            } else alert(res.message);
        });
}

function handleCadBooking(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitCad');
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-2"></i> Uploading Spec & Creating Project...`;

    const form = document.getElementById('cadBookingForm');
    const fd = new FormData(form);

    fetch('api/create_booking.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-paper-plane text-base mr-2"></i> Post 3D Project to Designer Radar`;
            if (res.success) {
                alert(`3D CAD Project Created! Ref: ${res.data.booking_code}`);
                form.reset();
                switchDashboardTab('my_bookings');
                pollBookingsStatus(true);
            } else alert(res.message);
        });
}

function previewSelectedFile(input, targetId) {
    if (input.files && input.files[0]) {
        document.getElementById(targetId).innerText = `Attached: ${input.files[0].name} (${(input.files[0].size/(1024*1024)).toFixed(2)} MB)`;
    }
}

// =========================================================================
// Real-Time Chat Modal & Polling Logic
// =========================================================================
function openChatModal(bookingId, title) {
    document.getElementById('chatActiveBookingId').value = bookingId;
    document.getElementById('chatModalTitle').innerText = title;
    document.getElementById('chatModal').classList.remove('hidden');

    loadChatMessages(bookingId);
    if (activeChatPollInterval) clearInterval(activeChatPollInterval);
    activeChatPollInterval = setInterval(() => loadChatMessages(bookingId), 3500);
}

function closeChatModal() {
    document.getElementById('chatModal').classList.add('hidden');
    if (activeChatPollInterval) {
        clearInterval(activeChatPollInterval);
        activeChatPollInterval = null;
    }
}

function loadChatMessages(bookingId) {
    const container = document.getElementById('chatMessagesContainer');
    fetch(`api/get_messages.php?booking_id=${bookingId}`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data.messages) {
                if (res.data.messages.length === 0) {
                    container.innerHTML = `<div class="text-center py-10 text-slate-500 text-xs">No messages yet. Send a message to start communicating.</div>`;
                    return;
                }
                let html = '';
                res.data.messages.forEach(m => {
                    if (m.is_mine) {
                        html += `
                            <div class="flex flex-col items-end">
                                <div class="bg-brand-500 text-slate-950 font-medium px-4 py-2.5 rounded-2xl rounded-tr-none text-xs max-w-xs shadow">
                                    ${m.message_text}
                                </div>
                                <span class="text-[10px] text-slate-500 mt-1 font-mono">${m.time_display}</span>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="flex flex-col items-start">
                                <span class="text-[10px] text-slate-400 mb-0.5 font-bold">${m.sender_name}</span>
                                <div class="bg-dark-800 text-slate-200 px-4 py-2.5 rounded-2xl rounded-tl-none text-xs max-w-xs border border-slate-700 shadow">
                                    ${m.message_text}
                                </div>
                                <span class="text-[10px] text-slate-500 mt-1 font-mono">${m.time_display}</span>
                            </div>
                        `;
                    }
                });
                container.innerHTML = html;
                container.scrollTop = container.scrollHeight;
            }
        });
}

function handleSendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('chatMessageInput');
    const msg = input.value.trim();
    const bookingId = document.getElementById('chatActiveBookingId').value;
    if (!msg || !bookingId) return;

    const fd = new FormData();
    fd.append('booking_id', bookingId);
    fd.append('message_text', msg);

    input.value = '';
    fetch('api/send_message.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) loadChatMessages(bookingId);
            else alert(res.message);
        });
}

// =========================================================================
// Monetization & Simulated PayHere / Genie Gateway
// =========================================================================
function openPaymentModal(bookingId, code, amount) {
    document.getElementById('payBookingId').value = bookingId;
    document.getElementById('payAmountVal').value = amount;
    document.getElementById('payModalRef').innerText = code;
    document.getElementById('payModalBase').innerText = 'Rs. ' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
    
    const fee = (amount * 0.10);
    document.getElementById('payModalFee').innerText = 'Rs. ' + fee.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('payModalTotal').innerText = 'Rs. ' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('paymentModal').classList.remove('hidden');
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.add('hidden');
}

function handleProcessPayment(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitPayment');
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i> Authorizing Gateway...`;

    const bookingId = document.getElementById('payBookingId').value;
    const amount = document.getElementById('payAmountVal').value;
    const form = document.getElementById('paymentForm');
    const fd = new FormData(form);
    fd.append('booking_id', bookingId);
    fd.append('amount', amount);

    fetch('api/process_payment.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-lock"></i> Authorize Payment`;
            if (res.success) {
                alert(`Payment Success!\n${res.message}\nReference: ${res.data.transaction_ref}\nPlatform Commission Retained: ${res.data.platform_fee_disp}`);
                closePaymentModal();
                pollBookingsStatus(true);
            } else alert(res.message);
        });
}

// =========================================================================
// 5-Star Customer Review Modal
// =========================================================================
function openReviewModal(bookingId, proName) {
    document.getElementById('reviewBookingId').value = bookingId;
    document.getElementById('reviewProName').innerText = `Rating: ${proName}`;
    setStarRating(5);
    document.getElementById('reviewModal').classList.remove('hidden');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
}

function setStarRating(num) {
    document.getElementById('selectedStarRating').value = num;
    for (let i = 1; i <= 5; i++) {
        const btn = document.getElementById('starBtn_' + i);
        if (i <= num) btn.innerHTML = `<i class="fas fa-star text-amber-400"></i>`;
        else btn.innerHTML = `<i class="far fa-star text-slate-600"></i>`;
    }
}

function handleReviewSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitReview');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('booking_id', document.getElementById('reviewBookingId').value);
    fd.append('rating', document.getElementById('selectedStarRating').value);
    fd.append('comment', document.getElementById('reviewCommentInput').value);

    fetch('api/submit_review.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            if (res.success) {
                alert(`Review Submitted! Provider rating updated to ${res.data.new_avg_rating} ★`);
                closeReviewModal();
                pollBookingsStatus(true);
            } else alert(res.message);
        });
}

// Live Status Polling
function pollBookingsStatus(manual = false) {
    const spinner = document.getElementById('refreshSpinner');
    if (manual && spinner) spinner.classList.add('fa-spin');

    fetch('api/get_booking_status.php')
        .then(r => r.json())
        .then(res => {
            if (manual && spinner) spinner.classList.remove('fa-spin');
            if (res.success && res.data.bookings) {
                document.getElementById('bookingCountBadge').innerText = res.data.count;
                if (manual) location.reload();
            }
        });
}

function cancelBooking(id) {
    if (!confirm('Cancel this request?')) return;
    const fd = new FormData();
    fd.append('booking_id', id);
    fd.append('status', 'cancelled');
    fetch('api/update_booking_status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) location.reload();
            else alert(res.message);
        });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
