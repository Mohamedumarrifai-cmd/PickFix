<?php
/**
 * PickFix - Provider & Garage Mechanic Command Dashboard
 * 
 * Features:
 * 1. Live Job Radar (PickMe-style dispatch scanner with Emergency SOS priority alerts).
 * 2. Instant Online/Offline availability toggle & GPS telemetry sync.
 * 3. Real-Time Customer-Provider Live Chat integration.
 * 4. 3D Design & CAD Deliverable Upload Studio (STL, OBJ, STEP, 4K Render JPG/PNG, ZIP).
 * 5. Monetization & Platform Commission Ledger (10% platform fee, 90% net provider payout).
 * 6. Client 5-Star Reviews & Rating Showcase.
 */

// Enable comprehensive error reporting during debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require_once __DIR__ . '/config/database.php';
pickfix_session_start();
require_role('provider', 'login.php');

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

$pageTitle = "Provider Job Radar & Deliveries";
$providerProfile = null;
$incomingRadarRequests = [];
$myActiveJobs = [];
$completedJobs = [];
$providerTransactions = [];
$providerReviews = [];
$totalGross = 0;
$totalPlatformFee = 0;
$totalNetPayout = 0;

try {
    $db = get_db();

    // Fetch provider profile & telemetry (check both provider_id and user_id for resilience)
    $provStmt = $db->prepare("
        SELECT p.*, u.name, u.email, u.phone 
        FROM providers p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id = :pid OR p.user_id = :uid
        LIMIT 1
    ");
    $provStmt->execute([
        ':pid' => (int)($currentUser['provider_id'] ?? 0),
        ':uid' => (int)$currentUser['id']
    ]);
    $providerProfile = $provStmt->fetch();

    if (!$providerProfile) {
        $_SESSION['flash_error'] = "Provider profile record not found. Please log in again.";
        header("Location: login.php");
        exit;
    }

    // Refresh session provider_id if needed
    $_SESSION['provider_id'] = (int)$providerProfile['id'];
    $_SESSION['provider_category'] = $providerProfile['category'];

    $providerCategory = $providerProfile['category'];
    $is3DDesigner = ($providerCategory === '3d_design');
    $isMechanic = ($providerCategory === 'mechanic');

    // Fetch Open / Radar Requests (Matching Category OR Emergency SOS)
    $radarStmt = $db->prepare("
        SELECT 
            b.*, 
            u.name AS customer_name, 
            u.phone AS customer_phone
        FROM bookings b
        JOIN users u ON b.customer_id = u.id
        WHERE b.status = 'pending'
          AND (b.service_type = :cat OR b.is_emergency = 1 OR :is_3d_cat = '3d_design' OR :is_mech_cat = 'mechanic')
        ORDER BY b.is_emergency DESC, b.created_at DESC
        LIMIT 25
    ");
    $radarStmt->execute([
        ':cat'         => $providerCategory,
        ':is_3d_cat'   => $providerCategory,
        ':is_mech_cat' => $providerCategory
    ]);
    $incomingRadarRequests = $radarStmt->fetchAll() ?: [];

    // Fetch Provider's Accepted / In-Progress Active Jobs
    $activeJobsStmt = $db->prepare("
        SELECT 
            b.*, 
            u.name AS customer_name, 
            u.phone AS customer_phone,
            (SELECT COUNT(*) FROM messages m WHERE m.booking_id = b.id AND m.receiver_id = :uid AND m.is_read = 0) AS unread_messages
        FROM bookings b
        JOIN users u ON b.customer_id = u.id
        WHERE b.provider_id = :pid 
          AND b.status IN ('accepted', 'in_progress')
        ORDER BY b.created_at DESC
    ");
    $activeJobsStmt->execute([
        ':pid' => (int)$providerProfile['id'],
        ':uid' => (int)$currentUser['id']
    ]);
    $myActiveJobs = $activeJobsStmt->fetchAll() ?: [];

    // Fetch Provider's Completed Job History
    $historyStmt = $db->prepare("
        SELECT 
            b.*, 
            u.name AS customer_name, 
            u.phone AS customer_phone,
            r.rating AS review_rating,
            r.comment AS review_comment
        FROM bookings b
        JOIN users u ON b.customer_id = u.id
        LEFT JOIN reviews r ON b.id = r.booking_id
        WHERE b.provider_id = :pid 
          AND b.status = 'completed'
        ORDER BY b.updated_at DESC
        LIMIT 20
    ");
    $historyStmt->execute([':pid' => (int)$providerProfile['id']]);
    $completedJobs = $historyStmt->fetchAll() ?: [];

    // Fetch Monetization Transactions
    $txnStmt = $db->prepare("
        SELECT t.*, b.booking_code, b.project_title, u.name AS customer_name
        FROM transactions t
        JOIN bookings b ON t.booking_id = b.id
        JOIN users u ON t.customer_id = u.id
        WHERE t.provider_id = :pid
        ORDER BY t.created_at DESC
    ");
    $txnStmt->execute([':pid' => (int)$providerProfile['id']]);
    $providerTransactions = $txnStmt->fetchAll() ?: [];

    // Calculate revenue metrics
    foreach ($providerTransactions as $t) {
        $totalGross += (float)$t['amount_lkr'];
        $totalPlatformFee += (float)$t['platform_fee_lkr'];
        $totalNetPayout += (float)$t['provider_payout_lkr'];
    }

    // Fetch Reviews
    $reviewsStmt = $db->prepare("
        SELECT r.*, u.name AS customer_name, b.project_title, b.booking_code
        FROM reviews r
        JOIN users u ON r.customer_id = u.id
        JOIN bookings b ON r.booking_id = b.id
        WHERE r.provider_id = :pid
        ORDER BY r.created_at DESC
    ");
    $reviewsStmt->execute([':pid' => (int)$providerProfile['id']]);
    $providerReviews = $reviewsStmt->fetchAll() ?: [];

} catch (Throwable $e) {
    error_log("[PickFix Provider Dashboard Error] " . $e->getMessage());
}

$providerCategory = $providerProfile['category'] ?? 'plumber';
$is3DDesigner = ($providerCategory === '3d_design');
$isMechanic = ($providerCategory === 'mechanic');

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
    
    <!-- Top Bar: Provider ID, Workshop, Category & Online/Offline Switch -->
    <div class="bg-dark-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl mb-8 backdrop-blur-xl">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-slate-800">
            
            <!-- Left Info -->
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr <?= $is3DDesigner ? 'from-indigo-600 to-accent-500' : ($isMechanic ? 'from-amber-500 to-orange-600' : 'from-brand-500 to-emerald-600') ?> flex items-center justify-center text-white text-2xl font-bold shadow-lg">
                    <i class="fas <?= $is3DDesigner ? 'fa-cube' : ($isMechanic ? 'fa-car-wrench' : 'fa-tools') ?>"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-bold uppercase tracking-wider <?= $is3DDesigner ? 'text-indigo-400 bg-indigo-500/10 border-indigo-500/30' : ($isMechanic ? 'text-amber-400 bg-amber-500/10 border-amber-500/30' : 'text-brand-400 bg-brand-500/10 border-brand-500/30') ?> px-2.5 py-0.5 rounded-full border">
                            <?= sanitize(get_service_label($providerCategory)) ?>
                        </span>
                        <span class="text-[11px] text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20 font-semibold flex items-center gap-1">
                            <i class="fas fa-check-circle text-[10px]"></i> Verified (<?= sanitize($providerProfile['nic_number']) ?>)
                        </span>
                        <?php if ($providerProfile['towing_available']): ?>
                            <span class="text-[10px] bg-red-500/20 text-red-300 font-bold px-2 py-0.5 rounded-full border border-red-500/30">
                                <i class="fas fa-truck-pickup mr-1"></i> Flatbed Towing
                            </span>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-2xl font-black text-white">
                        <?= sanitize($providerProfile['workshop_name'] ?: $providerProfile['name']) ?>
                    </h1>
                    <p class="text-xs text-slate-400 mt-0.5">
                        <?= sanitize($providerProfile['sub_category'] ?: 'Sri Lanka Registered Pro') ?> • <?= sanitize($providerProfile['city']) ?>
                        <?php if (!empty($providerProfile['engine_types']) && $providerProfile['engine_types'] !== 'N/A'): ?>
                            • <span class="text-amber-400 font-semibold"><?= sanitize($providerProfile['engine_types']) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Right: Online/Offline Interactive Toggle -->
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-3 bg-slate-950 p-3 rounded-2xl border border-slate-800">
                    <div class="flex flex-col text-right">
                        <span id="statusLabel" class="text-xs font-extrabold uppercase <?= $providerProfile['is_online'] ? 'text-emerald-400' : 'text-slate-500' ?>">
                            <?= $providerProfile['is_online'] ? '● ONLINE & ACTIVE' : '○ OFFLINE' ?>
                        </span>
                        <span class="text-[10px] text-slate-500" id="radarTelemetryText">
                            <?= $providerProfile['is_online'] ? 'Broadcasting to Dispatch Radar' : 'Radar Scanning Paused' ?>
                        </span>
                    </div>

                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="onlineToggleSwitch" class="sr-only peer" <?= $providerProfile['is_online'] ? 'checked' : '' ?> onchange="toggleProviderOnlineStatus(this.checked)">
                        <div class="w-14 h-8 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>

                <button type="button" onclick="broadcastGPS()" class="px-4 py-3 rounded-2xl bg-dark-800 hover:bg-slate-800 text-slate-300 hover:text-white text-xs font-bold border border-slate-700 flex items-center gap-2 transition">
                    <i class="fas fa-satellite-dish text-indigo-400"></i>
                    <span>Ping GPS</span>
                </button>
            </div>
        </div>

        <!-- Metric Badges Row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6">
            <div class="p-4 bg-slate-950/60 rounded-2xl border border-slate-800/80">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Total Completed</span>
                <div class="text-2xl font-black text-white flex items-center gap-2">
                    <?= (int)$providerProfile['total_jobs'] ?>
                    <span class="text-xs font-normal text-slate-500">jobs</span>
                </div>
            </div>

            <div class="p-4 bg-slate-950/60 rounded-2xl border border-slate-800/80">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Overall Rating</span>
                <div class="text-2xl font-black text-amber-400 flex items-center gap-1.5">
                    <i class="fas fa-star text-base"></i>
                    <?= number_format((float)$providerProfile['rating'], 2) ?>
                </div>
            </div>

            <div class="p-4 bg-slate-950/60 rounded-2xl border border-slate-800/80">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Net Earnings (90%)</span>
                <div class="text-2xl font-black text-emerald-400">
                    <?= format_lkr($totalNetPayout) ?>
                </div>
            </div>

            <div class="p-4 bg-slate-950/60 rounded-2xl border border-slate-800/80">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Platform Commission (10%)</span>
                <div class="text-2xl font-black text-indigo-400">
                    <?= format_lkr($totalPlatformFee) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap items-center gap-2 mb-8 bg-dark-900 p-2 rounded-2xl border border-slate-800 max-w-4xl">
        <button type="button" onclick="switchProviderTab('radar')" id="pTabBtn-radar" class="flex-1 min-w-[120px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-brand-500 text-slate-950 shadow-md">
            <i class="fas fa-satellite-dish"></i>
            <span>Job Radar</span>
            <span class="px-1.5 py-0.2 rounded-full bg-slate-950 text-white font-mono text-[10px]"><?= count($incomingRadarRequests) ?></span>
        </button>

        <button type="button" onclick="switchProviderTab('active')" id="pTabBtn-active" class="flex-1 min-w-[130px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-400 hover:text-white">
            <i class="fas fa-person-digging"></i>
            <span>Active Jobs</span>
            <span class="px-1.5 py-0.2 rounded-full bg-slate-800 text-emerald-400 font-mono text-[10px]"><?= count($myActiveJobs) ?></span>
        </button>

        <button type="button" onclick="switchProviderTab('upload')" id="pTabBtn-upload" class="flex-1 min-w-[120px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-400 hover:text-white">
            <i class="fas fa-file-arrow-up text-indigo-400"></i>
            <span>Deliver 3D Files</span>
        </button>

        <button type="button" onclick="switchProviderTab('payouts')" id="pTabBtn-payouts" class="flex-1 min-w-[130px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-400 hover:text-white">
            <i class="fas fa-wallet text-emerald-400"></i>
            <span>Payouts & Fees</span>
            <span class="px-1.5 py-0.2 rounded-full bg-slate-800 text-slate-400 font-mono text-[10px]"><?= count($providerTransactions) ?></span>
        </button>

        <button type="button" onclick="switchProviderTab('reviews')" id="pTabBtn-reviews" class="flex-1 min-w-[120px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-400 hover:text-white">
            <i class="fas fa-star text-amber-400"></i>
            <span>Client Reviews</span>
            <span class="px-1.5 py-0.2 rounded-full bg-slate-800 text-amber-400 font-mono text-[10px]"><?= count($providerReviews) ?></span>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1: Live Job Radar (Incoming Open Requests)                           -->
    <!-- ========================================================================= -->
    <div id="pTabContent-radar" class="space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-500 animate-ping"></span>
                    Live Dispatch Radar: Open Requests
                </h2>
                <p class="text-xs text-slate-400">Emergency SOS alerts and trade requests matching your specialty</p>
            </div>
            <button type="button" onclick="location.reload()" class="px-3.5 py-2 rounded-xl bg-dark-800 hover:bg-slate-800 text-slate-300 hover:text-white text-xs font-bold border border-slate-700 flex items-center gap-2 transition">
                <i class="fas fa-sync-alt"></i> Refresh Radar
            </button>
        </div>

        <?php if (empty($incomingRadarRequests)): ?>
            <div class="text-center py-16 bg-dark-900 border border-slate-800 rounded-3xl p-8 space-y-3">
                <div class="w-16 h-16 rounded-2xl bg-slate-800/80 text-emerald-400 mx-auto flex items-center justify-center text-2xl radar-pulse">
                    <i class="fas fa-radar"></i>
                </div>
                <h3 class="text-base font-bold text-white">Radar is Scanning for Jobs...</h3>
                <p class="text-xs text-slate-400 max-w-md mx-auto leading-relaxed">
                    No open requests waiting in your sector right now. Keep your status <strong class="text-emerald-400">ONLINE</strong> to automatically receive requests.
                </p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($incomingRadarRequests as $req): ?>
                    <div class="bg-dark-900 border <?= $req['is_emergency'] ? 'border-red-500/80 shadow-red-500/10' : 'border-slate-800 hover:border-slate-700' ?> rounded-2xl p-6 shadow-xl flex flex-col justify-between space-y-5 transition relative overflow-hidden">
                        <?php if ($req['is_emergency']): ?>
                            <div class="absolute top-0 right-0 bg-red-600 text-white text-[10px] uppercase font-black px-4 py-1 rounded-bl-xl tracking-wider flex items-center gap-1.5 animate-pulse">
                                <i class="fas fa-bolt"></i> Emergency SOS (15-Min)
                            </div>
                        <?php endif; ?>

                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="font-mono text-xs font-bold text-slate-400 bg-slate-950 px-2 py-0.5 rounded border border-slate-800"><?= sanitize($req['booking_code']) ?></span>
                                <span class="text-xs font-bold text-brand-400"><?= sanitize(get_service_label($req['service_type'])) ?></span>
                            </div>

                            <h3 class="text-base font-bold text-white mb-2"><?= sanitize($req['project_title']) ?></h3>
                            <p class="text-xs text-slate-300 leading-relaxed mb-4"><?= nl2br(sanitize($req['project_notes'])) ?></p>

                            <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800/80 space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500"><i class="fas fa-map-marker-alt text-brand-500 mr-1.5"></i> Location:</span>
                                    <span class="font-semibold text-slate-200"><?= sanitize($req['customer_address'] ?: 'Colombo Area') ?></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500"><i class="fas fa-user text-slate-400 mr-1.5"></i> Client:</span>
                                    <span class="font-semibold text-slate-200"><?= sanitize($req['customer_name']) ?></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500"><i class="fas fa-wallet text-emerald-400 mr-1.5"></i> Budget / Payout:</span>
                                    <span class="font-bold text-emerald-400"><?= $req['budget'] ? format_lkr((float)$req['budget']) : format_lkr((float)$providerProfile['hourly_rate']) ?></span>
                                </div>
                            </div>

                            <?php if (!empty($req['reference_file_path'])): ?>
                                <div class="mt-3 p-2.5 rounded-xl bg-indigo-950/30 border border-indigo-500/30 flex items-center justify-between">
                                    <span class="text-xs text-indigo-300 flex items-center gap-1.5">
                                        <i class="fas fa-paperclip"></i> Reference Attachment
                                    </span>
                                    <a href="<?= sanitize($req['reference_file_path']) ?>" target="_blank" class="text-xs font-bold text-indigo-400 hover:text-white underline">Inspect Spec</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex items-center gap-3">
                            <button type="button" onclick="acceptJob(<?= $req['id'] ?>)" class="flex-1 py-3 px-4 rounded-xl bg-brand-500 hover:bg-brand-600 text-slate-950 font-black text-xs uppercase tracking-wider transition shadow-lg shadow-brand-500/20 flex items-center justify-center gap-2">
                                <i class="fas fa-check-circle"></i>
                                <span>Accept & Claim Job</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: My Active / Claimed Jobs (With Real-Time Chat Integration)        -->
    <!-- ========================================================================= -->
    <div id="pTabContent-active" class="hidden space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-screwdriver-wrench text-brand-500"></i> In-Progress Assignments & Chat
                </h2>
                <p class="text-xs text-slate-400">Communicate with clients, mark progress, and upload 3D files</p>
            </div>
        </div>

        <?php if (empty($myActiveJobs)): ?>
            <div class="text-center py-16 bg-dark-900 border border-slate-800 rounded-3xl p-8 space-y-3">
                <i class="fas fa-clipboard-check text-slate-600 text-4xl mb-2"></i>
                <h3 class="text-base font-bold text-white">No In-Progress Jobs</h3>
                <p class="text-xs text-slate-400">Claim jobs from the Job Radar to start work.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($myActiveJobs as $job): ?>
                    <div class="bg-dark-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-800">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-xs font-bold text-slate-400 bg-slate-950 px-2.5 py-1 rounded-lg border border-slate-800"><?= sanitize($job['booking_code']) ?></span>
                                <?= render_status_badge($job['status']) ?>
                                <?= render_payment_badge($job['payment_status'] ?? 'unpaid') ?>
                            </div>
                            <div class="text-xs text-slate-400">
                                Agreed Fee: <strong class="text-emerald-400"><?= $job['budget'] ? format_lkr((float)$job['budget']) : format_lkr((float)$providerProfile['hourly_rate']) ?></strong>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-8 space-y-2">
                                <h3 class="text-base font-bold text-white"><?= sanitize($job['project_title']) ?></h3>
                                <p class="text-xs text-slate-400"><?= nl2br(sanitize($job['project_notes'])) ?></p>

                                <?php if (!empty($job['reference_file_path'])): ?>
                                    <div class="pt-2">
                                        <a href="<?= sanitize($job['reference_file_path']) ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-indigo-400 hover:underline">
                                            <i class="fas fa-download"></i> Download Client Reference (<?= basename($job['reference_file_path']) ?>)
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="md:col-span-4 bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-2 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Client:</span>
                                    <span class="font-bold text-white"><?= sanitize($job['customer_name']) ?></span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Phone:</span>
                                    <a href="tel:<?= sanitize($job['customer_phone']) ?>" class="text-brand-400 font-mono font-bold hover:underline"><?= sanitize($job['customer_phone']) ?></a>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Address:</span>
                                    <span class="text-slate-300"><?= sanitize($job['customer_address']) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Controls -->
                        <div class="pt-4 border-t border-slate-800 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <!-- Real-Time Chat Button -->
                                <button type="button" onclick="openChatModal(<?= $job['id'] ?>, 'Chat with <?= addslashes(sanitize($job['customer_name'])) ?>')" class="py-2 px-3.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs transition border border-slate-700 flex items-center gap-1.5">
                                    <i class="fas fa-comments text-brand-400"></i>
                                    <span>Chat with Client</span>
                                    <?php if ($job['unread_messages'] > 0): ?>
                                        <span class="px-1.5 py-0.2 rounded-full bg-brand-500 text-slate-950 font-mono text-[10px] font-bold"><?= $job['unread_messages'] ?></span>
                                    <?php endif; ?>
                                </button>

                                <?php if ($job['status'] === 'accepted'): ?>
                                    <button type="button" onclick="updateStatus(<?= $job['id'] ?>, 'in_progress')" class="py-2 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition">
                                        <i class="fas fa-play mr-1.5"></i> Mark In Progress / Started
                                    </button>
                                <?php endif; ?>

                                <?php if ($job['service_type'] !== '3d_design'): ?>
                                    <button type="button" onclick="updateStatus(<?= $job['id'] ?>, 'completed')" class="py-2 px-4 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold text-xs transition">
                                        <i class="fas fa-check-double mr-1.5"></i> Mark Completed
                                    </button>
                                <?php endif; ?>
                            </div>

                            <button type="button" onclick="preselectDeliverableJob(<?= $job['id'] ?>)" class="py-2 px-4 rounded-xl bg-dark-800 hover:bg-slate-800 text-indigo-400 hover:text-white border border-indigo-500/30 text-xs font-bold transition flex items-center gap-1.5">
                                <i class="fas fa-upload text-indigo-400"></i>
                                <span>Upload Deliverable / CAD</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 3: 3D Design Deliverables & Work Upload Studio                       -->
    <!-- ========================================================================= -->
    <div id="pTabContent-upload" class="hidden space-y-6">
        <div class="max-w-3xl mx-auto bg-dark-900 border border-slate-800 rounded-3xl p-6 sm:p-10 shadow-2xl">
            <div class="pb-6 mb-6 border-b border-slate-800 flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-indigo-500/15 border border-indigo-500/30 text-indigo-400 flex items-center justify-center text-2xl">
                    <i class="fas fa-file-arrow-up"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white">3D Design Deliverable Upload Studio</h2>
                    <p class="text-xs text-slate-400">Deliver STL meshes, SolidWorks/STEP files, ZIP archives, or 4K renders</p>
                </div>
            </div>

            <form id="uploadDeliverableForm" onsubmit="handleDeliverableUpload(event)" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Select Project to Deliver</label>
                    <select id="deliverableBookingId" name="booking_id" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                        <option value="">-- Choose from your claimed projects --</option>
                        <?php foreach ($myActiveJobs as $aj): ?>
                            <option value="<?= $aj['id'] ?>">
                                <?= sanitize($aj['booking_code']) ?>: <?= sanitize($aj['project_title']) ?> (Client: <?= sanitize($aj['customer_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Deliverable File (.STL, .OBJ, .STEP, .DWG, .ZIP, .JPG)</label>
                    <div class="border-2 border-dashed border-slate-700 hover:border-indigo-500 rounded-2xl p-8 text-center transition bg-slate-950/70 cursor-pointer" onclick="document.getElementById('deliverableFileInput').click()">
                        <i class="fas fa-cubes text-4xl text-indigo-400 mb-3"></i>
                        <p class="text-xs font-bold text-slate-200 mb-1">Click to browse or drop completed CAD / render files here</p>
                        <p class="text-[11px] text-slate-500">Allowed: .STL, .OBJ, .STEP, .STP, .3MF, .DWG, .ZIP, .JPG, .PNG (Max 50MB)</p>
                        <input type="file" id="deliverableFileInput" name="deliverable_file" required class="hidden" onchange="previewSelectedFile(this, 'deliverablePreviewText')">
                        <div id="deliverablePreviewText" class="mt-4 text-xs font-mono font-bold text-emerald-400"></div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Delivery Notes & Printing Instructions</label>
                    <textarea id="deliverableNotes" name="delivery_notes" rows="3" placeholder="e.g. Watertight STL mesh exported in millimeters. 4K render included." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"></textarea>
                </div>

                <button type="submit" id="btnUploadDeliverable" class="w-full py-4 rounded-xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-emerald-600 hover:from-indigo-500 hover:to-emerald-500 text-white font-extrabold text-sm shadow-xl shadow-indigo-600/30 transition transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                    <i class="fas fa-check-circle text-base"></i>
                    <span>Submit Deliverable & Complete Project</span>
                </button>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 4: Monetization & Platform Payouts Ledger                             -->
    <!-- ========================================================================= -->
    <div id="pTabContent-payouts" class="hidden space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-wallet text-emerald-400"></i> Monetization & Revenue Ledger
                </h2>
                <p class="text-xs text-slate-400">Platform commission fees (10%) and net provider payouts (90%)</p>
            </div>
        </div>

        <?php if (empty($providerTransactions)): ?>
            <div class="text-center py-16 bg-dark-900 border border-slate-800 rounded-3xl p-8 space-y-2">
                <i class="fas fa-receipt text-slate-600 text-4xl mb-2"></i>
                <h3 class="text-base font-bold text-white">No Payment Records Yet</h3>
                <p class="text-xs text-slate-400">When clients settle bookings via PayHere, Genie, or Cash, transaction logs appear here.</p>
            </div>
        <?php else: ?>
            <div class="bg-dark-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-950 border-b border-slate-800 text-slate-400 uppercase font-mono text-[10px]">
                            <tr>
                                <th class="p-4">Transaction Ref</th>
                                <th class="p-4">Booking</th>
                                <th class="p-4">Client</th>
                                <th class="p-4">Gateway</th>
                                <th class="p-4">Gross LKR</th>
                                <th class="p-4">Platform Fee (10%)</th>
                                <th class="p-4">Your Net Payout (90%)</th>
                                <th class="p-4">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php foreach ($providerTransactions as $tx): ?>
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="p-4 font-mono font-bold text-white"><?= sanitize($tx['transaction_ref']) ?></td>
                                    <td class="p-4">
                                        <div class="font-bold text-white"><?= sanitize($tx['booking_code']) ?></div>
                                        <div class="text-[11px] text-slate-400"><?= sanitize($tx['project_title']) ?></div>
                                    </td>
                                    <td class="p-4 text-slate-300"><?= sanitize($tx['customer_name']) ?></td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                            <?= sanitize($tx['payment_gateway']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 font-bold text-white"><?= format_lkr((float)$tx['amount_lkr']) ?></td>
                                    <td class="p-4 text-indigo-400 font-mono">- <?= format_lkr((float)$tx['platform_fee_lkr']) ?></td>
                                    <td class="p-4 font-bold text-emerald-400 font-mono"><?= format_lkr((float)$tx['provider_payout_lkr']) ?></td>
                                    <td class="p-4 text-slate-400"><?= date('M d, g:i A', strtotime($tx['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 5: Client Reviews & Ratings Showcase                                 -->
    <!-- ========================================================================= -->
    <div id="pTabContent-reviews" class="hidden space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div>
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-star text-amber-400"></i> Client Reviews & Quality Ratings
                </h2>
                <p class="text-xs text-slate-400">Direct feedback received from completed bookings</p>
            </div>
            <div class="flex items-center gap-2 text-sm font-bold text-white">
                <span>Cumulative Rating:</span>
                <?= render_stars((float)$providerProfile['rating']) ?>
            </div>
        </div>

        <?php if (empty($providerReviews)): ?>
            <div class="text-center py-16 bg-dark-900 border border-slate-800 rounded-3xl p-8 space-y-2">
                <i class="fas fa-comment-dots text-slate-600 text-4xl mb-2"></i>
                <h3 class="text-base font-bold text-white">No Reviews Published Yet</h3>
                <p class="text-xs text-slate-400">Customer feedback will appear here as soon as orders are finalized.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($providerReviews as $rv): ?>
                    <div class="bg-dark-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-white text-xs"><?= sanitize($rv['customer_name']) ?></span>
                            <?= render_stars((float)$rv['rating']) ?>
                        </div>
                        <p class="text-xs text-slate-300 italic leading-relaxed">"<?= sanitize($rv['comment']) ?>"</p>
                        <div class="pt-2 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-500">
                            <span>Job: <?= sanitize($rv['booking_code']) ?></span>
                            <span><?= date('M d, Y', strtotime($rv['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Real-Time Chat Modal for Providers -->
<div id="providerChatModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-dark-900 border border-slate-800 rounded-3xl max-w-lg w-full shadow-2xl flex flex-col h-[560px] overflow-hidden">
        <div class="p-4 bg-slate-950 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-400 flex items-center justify-center font-bold">
                    <i class="fas fa-comments"></i>
                </div>
                <div>
                    <h3 id="pChatModalTitle" class="text-sm font-bold text-white">Client Discussion</h3>
                    <p class="text-[11px] text-emerald-400">Direct active channel</p>
                </div>
            </div>
            <button onclick="closeProviderChatModal()" class="w-8 h-8 rounded-lg bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div id="pChatMessagesContainer" class="flex-1 p-4 overflow-y-auto space-y-3 bg-dark-950/50">
            <div class="text-center text-slate-500 text-xs py-10">Loading messages...</div>
        </div>

        <form onsubmit="handleProviderSendMessage(event)" class="p-3 bg-slate-950 border-t border-slate-800 flex items-center gap-2">
            <input type="hidden" id="pChatActiveBookingId" value="">
            <input type="text" id="pChatMessageInput" placeholder="Message client..." class="flex-1 bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-xs text-white focus:outline-none focus:border-brand-500 transition" required>
            <button type="submit" class="px-5 py-3 rounded-xl bg-brand-500 hover:bg-brand-600 text-slate-950 font-bold text-xs transition">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

<script>
let pChatPollInterval = null;

function switchProviderTab(tabKey) {
    ['radar', 'active', 'upload', 'payouts', 'reviews'].forEach(t => {
        document.getElementById('pTabContent-' + t)?.classList.add('hidden');
        const btn = document.getElementById('pTabBtn-' + t);
        if (btn) btn.className = "flex-1 min-w-[120px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 text-slate-400 hover:text-white";
    });

    document.getElementById('pTabContent-' + tabKey)?.classList.remove('hidden');
    const activeBtn = document.getElementById('pTabBtn-' + tabKey);
    if (activeBtn) {
        activeBtn.className = "flex-1 min-w-[120px] py-2.5 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 bg-brand-500 text-slate-950 shadow-md";
    }
}

function toggleProviderOnlineStatus(isOnline) {
    const fd = new FormData();
    fd.append('is_online', isOnline ? '1' : '0');

    fetch('api/update_provider_status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const label = document.getElementById('statusLabel');
                const tele = document.getElementById('radarTelemetryText');
                if (isOnline) {
                    label.className = "text-xs font-extrabold uppercase text-emerald-400";
                    label.innerText = "● ONLINE & ACTIVE";
                    tele.innerText = "Broadcasting to Dispatch Radar";
                } else {
                    label.className = "text-xs font-extrabold uppercase text-slate-500";
                    label.innerText = "○ OFFLINE";
                    tele.innerText = "Radar Scanning Paused";
                }
            } else alert(res.message);
        });
}

function broadcastGPS() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            const fd = new FormData();
            fd.append('lat', pos.coords.latitude);
            fd.append('lng', pos.coords.longitude);
            fetch('api/update_provider_status.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => alert('GPS updated: ' + pos.coords.latitude.toFixed(4) + ', ' + pos.coords.longitude.toFixed(4)));
        });
    }
}

function acceptJob(id) {
    if (!confirm('Claim this service request?')) return;
    const fd = new FormData();
    fd.append('booking_id', id);
    fd.append('status', 'accepted');
    fetch('api/update_booking_status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) { alert('Job Claimed!'); location.reload(); }
            else alert(res.message);
        });
}

function updateStatus(id, st) {
    const fd = new FormData();
    fd.append('booking_id', id);
    fd.append('status', st);
    fetch('api/update_booking_status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) location.reload();
            else alert(res.message);
        });
}

function preselectDeliverableJob(id) {
    switchProviderTab('upload');
    const select = document.getElementById('deliverableBookingId');
    if (select) select.value = id;
}

function previewSelectedFile(input, targetId) {
    if (input.files && input.files[0]) {
        document.getElementById(targetId).innerText = `Selected Deliverable: ${input.files[0].name} (${(input.files[0].size/(1024*1024)).toFixed(2)} MB)`;
    }
}

function handleDeliverableUpload(e) {
    e.preventDefault();
    const btn = document.getElementById('btnUploadDeliverable');
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i> Uploading 3D Deliverables...`;

    const form = document.getElementById('uploadDeliverableForm');
    const fd = new FormData(form);

    fetch('api/upload_design_file.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-check-circle text-base mr-2"></i> Submit Deliverable & Complete Project`;
            if (res.success) {
                alert('Success: ' + res.message);
                location.reload();
            } else alert(res.message);
        });
}

// Real-Time Chat Modal for Provider
function openChatModal(bookingId, title) {
    document.getElementById('pChatActiveBookingId').value = bookingId;
    document.getElementById('pChatModalTitle').innerText = title;
    document.getElementById('providerChatModal').classList.remove('hidden');

    loadProviderMessages(bookingId);
    if (pChatPollInterval) clearInterval(pChatPollInterval);
    pChatPollInterval = setInterval(() => loadProviderMessages(bookingId), 3500);
}

function closeProviderChatModal() {
    document.getElementById('providerChatModal').classList.add('hidden');
    if (pChatPollInterval) {
        clearInterval(pChatPollInterval);
        pChatPollInterval = null;
    }
}

function loadProviderMessages(bookingId) {
    const container = document.getElementById('pChatMessagesContainer');
    fetch(`api/get_messages.php?booking_id=${bookingId}`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data.messages) {
                if (res.data.messages.length === 0) {
                    container.innerHTML = `<div class="text-center py-10 text-slate-500 text-xs">No messages yet.</div>`;
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

function handleProviderSendMessage(e) {
    e.preventDefault();
    const input = document.getElementById('pChatMessageInput');
    const msg = input.value.trim();
    const bookingId = document.getElementById('pChatActiveBookingId').value;
    if (!msg || !bookingId) return;

    const fd = new FormData();
    fd.append('booking_id', bookingId);
    fd.append('message_text', msg);

    input.value = '';
    fetch('api/send_message.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) loadProviderMessages(bookingId);
            else alert(res.message);
        });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
