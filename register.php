<?php
/**
 * PickFix - Role-Based Registration & Provider Onboarding Module
 * 
 * Sri Lanka's On-Demand Local Services, Garage Mechanics & 3D CAD Platform
 * Features:
 * 1. Seamless dynamic role toggle (Client / Customer vs Service Provider / 3D Designer).
 * 2. Specialized provider fields: Sri Lankan NIC validation, trade category & sub-specialty presets.
 * 3. Workshop name, powertrain coverage (Hybrid/EV, Diesel, Petrol), and towing capability.
 * 4. Sri Lankan city presets & HTML5 Geolocation API for instant radar coordinate locking.
 * 5. Secure atomic PDO database transaction ($db->beginTransaction() / commit()) with rollback.
 * 6. Instant provider session synchronization and redirect to provider_dashboard.php.
 */

// Enable error reporting during development
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require_once __DIR__ . '/config/database.php';
pickfix_session_start();

if (is_logged_in()) {
    $role = $_SESSION['role'] ?? 'customer';
    header("Location: " . ($role === 'provider' ? 'provider_dashboard.php' : 'customer_dashboard.php'));
    exit;
}

$error = null;
$preselectedRole = ($_GET['role'] ?? '') === 'provider' ? 'provider' : 'customer';

// Handle Registration Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $role = in_array($_POST['role'] ?? '', ['customer', 'provider']) ? $_POST['role'] : 'customer';

    // Provider-specific inputs
    $category = trim($_POST['category'] ?? 'plumber');
    $workshopName = trim($_POST['workshop_name'] ?? '');
    $subCategory = trim($_POST['sub_category'] ?? '');
    $engineTypes = trim($_POST['engine_types'] ?? 'Petrol, Diesel, Hybrid/EV');
    $towingAvailable = !empty($_POST['towing_available']) ? 1 : 0;
    $nicNumber = strtoupper(trim($_POST['nic_number'] ?? ''));
    $hourlyRate = floatval($_POST['hourly_rate'] ?? 2500);
    $city = trim($_POST['city'] ?? 'Colombo');
    $lat = floatval($_POST['lat'] ?? 6.92710000);
    $lng = floatval($_POST['lng'] ?? 79.86120000);

    // Basic Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = "Please fill in all required basic account information.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "The provided email address is invalid.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $passwordConfirm) {
        $error = "Passwords do not match. Please re-enter.";
    } elseif ($role === 'provider') {
        // Sri Lankan NIC Format Validation (Old: 9 digits + V/X, New: 12 digits)
        if (empty($nicNumber)) {
            $error = "Sri Lankan National Identity Card (NIC) number is required for verified providers.";
        } elseif (!preg_match('/^(?:[0-9]{9}[vVxX]|[0-9]{12})$/', $nicNumber)) {
            $error = "Invalid Sri Lankan NIC number. Please enter a valid 9-digit (e.g. 901234567V) or 12-digit (e.g. 199012345678) NIC.";
        }
    }

    if (!$error) {
        try {
            $db = get_db();

            // Check if email already registered
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $checkStmt->execute([':email' => $email]);
            if ($checkStmt->fetch()) {
                $error = "An account with this email address already exists. Please sign in.";
            } else {
                // Begin Atomic PDO Transaction
                $db->beginTransaction();

                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $insertUser = $db->prepare("
                    INSERT INTO users (name, email, phone, password_hash, role)
                    VALUES (:name, :email, :phone, :hash, :role)
                ");
                $insertUser->execute([
                    ':name'  => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':hash'  => $passwordHash,
                    ':role'  => $role
                ]);
                $userId = (int)$db->lastInsertId();

                // If registering as a Service Provider / 3D Designer / Garage Mechanic
                if ($role === 'provider') {
                    $insertProvider = $db->prepare("
                        INSERT INTO providers (
                            user_id, workshop_name, category, sub_category, engine_types,
                            towing_available, nic_number, is_verified, is_online, current_lat, current_lng, city, hourly_rate
                        ) VALUES (
                            :uid, :workshop, :cat, :sub, :engine,
                            :towing, :nic, 1, 1, :lat, :lng, :city, :rate
                        )
                    ");
                    $insertProvider->execute([
                        ':uid'      => $userId,
                        ':workshop' => $workshopName ?: $name,
                        ':cat'      => $category,
                        ':sub'      => $subCategory,
                        ':engine'   => $engineTypes,
                        ':towing'   => $towingAvailable,
                        ':nic'      => $nicNumber,
                        ':lat'      => $lat,
                        ':lng'      => $lng,
                        ':city'     => $city,
                        ':rate'     => $hourlyRate
                    ]);
                    $providerId = (int)$db->lastInsertId();
                }

                // Commit Transaction
                $db->commit();

                // Automatically synchronize session
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $phone;
                $_SESSION['role'] = $role;

                if ($role === 'provider') {
                    $_SESSION['provider_id'] = $providerId;
                    $_SESSION['provider_category'] = $category;
                    $_SESSION['flash_success'] = "Welcome to PickFix! Your provider profile is verified and active on the Sri Lanka Dispatch Radar.";
                    header("Location: provider_dashboard.php");
                } else {
                    $_SESSION['flash_success'] = "Account successfully created! You can now request on-demand repairs or 3D CAD modeling.";
                    header("Location: customer_dashboard.php");
                }
                exit;
            }
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("[PickFix Registration Error] " . $e->getMessage());
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}

$pageTitle = "Create an Account | Join PickFix";
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl w-full bg-dark-900 border border-slate-800 rounded-3xl p-6 sm:p-10 shadow-2xl backdrop-blur-xl">
        
        <!-- Header -->
        <div class="text-center space-y-2 mb-8">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-brand-500 via-emerald-600 to-indigo-600 mx-auto flex items-center justify-center shadow-lg shadow-brand-500/25">
                <i class="fas fa-bolt text-slate-950 text-2xl font-bold"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                Join Pick<span class="text-brand-500">Fix</span> Sri Lanka
            </h1>
            <p class="text-xs text-slate-400 max-w-md mx-auto">
                On-demand emergency trades, mobile mechanics, drivers & professional 3D CAD design network.
            </p>
        </div>

        <!-- Error Notification -->
        <?php if ($error): ?>
            <div class="p-4 rounded-2xl bg-rose-500/15 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-3 mb-6 shadow-lg">
                <i class="fas fa-exclamation-triangle text-lg flex-shrink-0"></i>
                <span class="font-medium"><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" id="registrationForm" class="space-y-6">
            
            <!-- Dynamic Role Toggle Selector (Interactive Cards) -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-2.5 tracking-wider">Select Account Type</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    
                    <!-- Customer Card -->
                    <label class="cursor-pointer">
                        <input type="radio" name="role" value="customer" class="peer sr-only" <?= $preselectedRole === 'customer' ? 'checked' : '' ?> onchange="toggleRoleFields('customer')">
                        <div class="p-4 rounded-2xl border-2 transition-all flex items-start gap-3.5 peer-checked:border-brand-500 peer-checked:bg-brand-500/10 border-slate-800 bg-slate-950/60 hover:border-slate-700">
                            <div class="w-10 h-10 rounded-xl bg-brand-500/20 text-brand-400 flex items-center justify-center flex-shrink-0 text-lg">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-bold text-white flex items-center justify-between">
                                    <span>Client / Customer</span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-brand-400 opacity-0 peer-checked:opacity-100 transition"></span>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-0.5 leading-relaxed">Book emergency trades, garage rescue, or 3D CAD modeling</p>
                            </div>
                        </div>
                    </label>

                    <!-- Provider Card -->
                    <label class="cursor-pointer">
                        <input type="radio" name="role" value="provider" class="peer sr-only" <?= $preselectedRole === 'provider' ? 'checked' : '' ?> onchange="toggleRoleFields('provider')">
                        <div class="p-4 rounded-2xl border-2 transition-all flex items-start gap-3.5 peer-checked:border-indigo-500 peer-checked:bg-indigo-500/10 border-slate-800 bg-slate-950/60 hover:border-slate-700">
                            <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center flex-shrink-0 text-lg">
                                <i class="fas fa-screwdriver-wrench"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-bold text-white flex items-center justify-between">
                                    <span>Service Pro / Driver</span>
                                    <span class="text-[9px] font-mono font-bold bg-amber-500 text-slate-950 px-1.5 py-0.2 rounded uppercase">90% Payout</span>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-0.5 leading-relaxed">Accept nearby radar dispatches & earn on your schedule</p>
                            </div>
                        </div>
                    </label>

                </div>
            </div>

            <!-- Basic Info Grid -->
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Full Legal Name</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-4 top-3.5 text-slate-500 text-sm"></i>
                            <input type="text" name="name" required placeholder="e.g. Kasun Perera" value="<?= sanitize($_POST['name'] ?? '') ?>" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Mobile Phone Number</label>
                        <div class="relative">
                            <i class="fas fa-phone absolute left-4 top-3.5 text-slate-500 text-sm"></i>
                            <input type="tel" name="phone" required placeholder="+94 77 123 4567" value="<?= sanitize($_POST['phone'] ?? '') ?>" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Email Address</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-4 top-3.5 text-slate-500 text-sm"></i>
                        <input type="email" name="email" required placeholder="kasun@domain.com" value="<?= sanitize($_POST['email'] ?? '') ?>" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Password</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-4 top-3.5 text-slate-500 text-sm"></i>
                            <input type="password" name="password" required minlength="6" placeholder="Min 6 characters" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Confirm Password</label>
                        <div class="relative">
                            <i class="fas fa-shield-halved absolute left-4 top-3.5 text-slate-500 text-sm"></i>
                            <input type="password" name="password_confirm" required minlength="6" placeholder="Re-enter password" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- Service Provider & PickMe-Style Pro Onboarding Fields                     -->
            <!-- ========================================================================= -->
            <div id="providerFields" class="<?= $preselectedRole === 'provider' ? '' : 'hidden' ?> pt-6 border-t border-slate-800 space-y-5">
                
                <div class="p-3.5 rounded-2xl bg-indigo-500/10 border border-indigo-500/25 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-400 animate-ping"></span>
                        <span class="text-xs font-bold text-indigo-300 uppercase tracking-wider">PickFix Pro Radar Verification</span>
                    </div>
                    <span class="text-[10px] font-mono bg-indigo-500/20 text-indigo-300 px-2 py-0.5 rounded-full font-bold">Islandwide Network</span>
                </div>

                <!-- Primary Trade Category -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Select Your Primary Profession</label>
                    <select id="proCategorySelect" name="category" onchange="handleProCategoryChange(this.value)" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                        <option value="mechanic" <?= ($_POST['category'] ?? '') === 'mechanic' ? 'selected' : '' ?>>🚗 Garage Mechanic & Roadside Rescue</option>
                        <option value="plumber" <?= ($_POST['category'] ?? '') === 'plumber' ? 'selected' : '' ?>>🔧 Plumber & Water Systems Specialist</option>
                        <option value="electrician" <?= ($_POST['category'] ?? '') === 'electrician' ? 'selected' : '' ?>>⚡ Electrician & Solar Power Technician</option>
                        <option value="ac_repair" <?= ($_POST['category'] ?? '') === 'ac_repair' ? 'selected' : '' ?>>❄️ Inverter AC Servicing & HVAC</option>
                        <option value="3d_design" <?= ($_POST['category'] ?? '') === '3d_design' ? 'selected' : '' ?>>🧊 3D CAD Modeling & Digital Prototyping</option>
                        <option value="driver" <?= ($_POST['category'] ?? '') === 'driver' ? 'selected' : '' ?>>🚕 Transport & Chauffeur Driver</option>
                    </select>
                </div>

                <!-- Workshop / Agency Name & Sri Lankan NIC -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase" id="labelWorkshop">Registered Workshop / Studio Name</label>
                        <input type="text" id="inputWorkshop" name="workshop_name" placeholder="e.g. Apex Hybrid Auto Clinic" value="<?= sanitize($_POST['workshop_name'] ?? '') ?>" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-300 uppercase">Sri Lankan NIC Number</label>
                            <span id="nicFormatBadge" class="text-[10px] font-mono text-slate-400">9-digit + V or 12-digit</span>
                        </div>
                        <input type="text" id="inputNic" name="nic_number" oninput="validateNicLive(this.value)" placeholder="e.g. 199012345678 or 901234567V" value="<?= sanitize($_POST['nic_number'] ?? '') ?>" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition font-mono uppercase">
                    </div>
                </div>

                <!-- Sub-Category & Preset Quick Tags -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Sub-Specialties / Offered Services</label>
                    <input type="text" id="inputSubCategory" name="sub_category" placeholder="e.g. Hybrid Diagnostics, Engine Overhaul, Towing" value="<?= sanitize($_POST['sub_category'] ?? '') ?>" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition mb-2">
                    
                    <!-- Quick Tag Presets -->
                    <div id="subCategoryPresetsContainer" class="flex flex-wrap gap-1.5">
                        <!-- Populated dynamically by handleProCategoryChange() -->
                    </div>
                </div>

                <!-- Powertrains & Towing (For Mechanics & Garages) -->
                <div id="mechanicExtraFields" class="space-y-4 p-4 rounded-2xl bg-slate-950 border border-slate-800">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Powertrain / Engine Specializations</label>
                        <input type="text" name="engine_types" value="<?= sanitize($_POST['engine_types'] ?? 'Petrol, Diesel, Hybrid/EV') ?>" class="w-full bg-dark-900 border border-slate-700 rounded-xl px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition">
                    </div>

                    <div class="flex items-center justify-between p-3 rounded-xl bg-dark-900 border border-slate-800">
                        <label class="text-xs text-slate-300 font-semibold cursor-pointer flex items-center gap-2.5">
                            <input type="checkbox" name="towing_available" value="1" <?= !empty($_POST['towing_available']) ? 'checked' : '' ?> class="w-4 h-4 rounded text-amber-500 bg-slate-950 border-slate-700 focus:ring-amber-500">
                            <span>Hydraulic Flatbed or Recovery Towing Truck Available</span>
                        </label>
                        <span class="text-[10px] text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded font-mono font-bold">24/7 Rescue</span>
                    </div>
                </div>

                <!-- Base Service Rate with Quick Badges -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-300 uppercase">Hourly or Callout Base Rate (LKR)</label>
                        <span class="text-[10px] text-emerald-400 font-mono">You keep 90% net payout</span>
                    </div>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-xs font-bold text-slate-400">Rs.</span>
                        <input type="number" step="100" min="500" id="inputHourlyRate" name="hourly_rate" value="<?= sanitize($_POST['hourly_rate'] ?? '2500') ?>" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-12 pr-4 py-3 text-sm text-white font-mono focus:outline-none focus:border-indigo-500 transition">
                    </div>
                    <!-- Quick Rate Preset Buttons -->
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-[10px] text-slate-500 uppercase font-bold">Presets:</span>
                        <button type="button" onclick="setRate(1500)" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] font-mono text-slate-300 transition">Rs. 1,500</button>
                        <button type="button" onclick="setRate(2500)" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] font-mono text-slate-300 transition">Rs. 2,500</button>
                        <button type="button" onclick="setRate(3500)" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] font-mono text-slate-300 transition">Rs. 3,500</button>
                        <button type="button" onclick="setRate(5000)" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] font-mono text-slate-300 transition">Rs. 5,000</button>
                    </div>
                </div>

                <!-- Sri Lankan Operating Hub & GPS Coordinates -->
                <div class="space-y-3 p-4 rounded-2xl bg-slate-950 border border-slate-800">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-300 uppercase">Primary Service Base & GPS Lock</label>
                        <span id="gpsLockStatus" class="inline-flex items-center gap-1.5 text-[10px] font-mono text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            <span>GPS: 6.9271, 79.8612</span>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- City Hub Preset -->
                        <div>
                            <select id="cityPresetSelect" onchange="handleCityPresetChange(this.value)" class="w-full bg-dark-900 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500 transition">
                                <option value="colombo_03">📍 Colombo 03 (Colpetty)</option>
                                <option value="colombo_07">📍 Colombo 07 (Cinnamon Gardens)</option>
                                <option value="dehiwala">📍 Dehiwala / Mount Lavinia</option>
                                <option value="nugegoda">📍 Nugegoda / Kotte</option>
                                <option value="gampaha">📍 Gampaha City</option>
                                <option value="negombo">📍 Negombo Beach / Katunayake</option>
                                <option value="kandy">📍 Kandy Town</option>
                                <option value="galle">📍 Galle Fort & Southern Suburbs</option>
                                <option value="kurunegala">📍 Kurunegala</option>
                                <option value="matara">📍 Matara</option>
                                <option value="jaffna">📍 Jaffna</option>
                            </select>
                            <input type="hidden" id="regCity" name="city" value="Colombo">
                        </div>

                        <!-- Detect Location Button -->
                        <div>
                            <button type="button" onclick="detectRegLocation()" class="w-full py-2.5 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition flex items-center justify-center gap-1.5 shadow-md shadow-indigo-600/20">
                                <i class="fas fa-crosshairs text-indigo-200"></i>
                                <span>Detect Live Device GPS</span>
                            </button>
                        </div>
                    </div>

                    <!-- Hidden Coordinate Carriers -->
                    <input type="hidden" id="regLat" name="lat" value="6.92710000">
                    <input type="hidden" id="regLng" name="lng" value="79.86120000">
                </div>

            </div>

            <!-- Submit Button -->
            <button type="submit" id="btnSubmitRegister" class="w-full py-4 rounded-xl bg-gradient-to-r from-brand-500 to-emerald-600 hover:from-brand-600 hover:to-emerald-700 text-slate-950 font-black text-sm uppercase tracking-wider shadow-xl shadow-brand-500/25 transition transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                <i class="fas fa-check-circle text-base"></i>
                <span id="btnSubmitText">Complete Registration & Enter Hub</span>
            </button>

        </form>

        <!-- Login Redirect -->
        <div class="pt-6 mt-6 border-t border-slate-800 text-center">
            <p class="text-xs text-slate-400">
                Already registered with PickFix? 
                <a href="login.php" class="font-bold text-brand-400 hover:text-brand-300 ml-1 underline">Sign In to Dashboard</a>
            </p>
        </div>

    </div>
</div>

<script>
// Preset city coordinates for Sri Lankan Hubs
const SRI_LANKA_HUBS = {
    'colombo_03': { name: 'Colombo 03', lat: 6.9034, lng: 79.8540 },
    'colombo_07': { name: 'Colombo 07', lat: 6.9117, lng: 79.8646 },
    'dehiwala':   { name: 'Dehiwala', lat: 6.8511, lng: 79.8659 },
    'nugegoda':   { name: 'Nugegoda', lat: 6.8722, lng: 79.8828 },
    'gampaha':    { name: 'Gampaha', lat: 7.0897, lng: 79.9925 },
    'negombo':    { name: 'Negombo', lat: 7.2008, lng: 79.8736 },
    'kandy':      { name: 'Kandy', lat: 7.2906, lng: 80.6337 },
    'galle':      { name: 'Galle', lat: 6.0535, lng: 80.2210 },
    'kurunegala': { name: 'Kurunegala', lat: 7.4863, lng: 80.3623 },
    'matara':     { name: 'Matara', lat: 5.9549, lng: 80.5550 },
    'jaffna':     { name: 'Jaffna', lat: 9.6615, lng: 80.0255 }
};

// Category configurations for auto-filling presets
const CATEGORY_CONFIGS = {
    'mechanic': {
        workshop: 'Apex Hybrid Auto Clinic',
        subDefaults: 'Hybrid Diagnostics, Engine Overhaul, Roadside Rescue',
        presets: ['Hybrid Diagnostics', 'ECU Tuning', 'Common Rail Diesel', 'Towing Truck', 'Bodywork & Paint'],
        showMechanicExtra: true
    },
    'driver': {
        workshop: 'City Chauffeur & Cab Service',
        subDefaults: 'Sedan Chauffeur, Airport Transfer, KDH Van',
        presets: ['Sedan Chauffeur', 'Airport Drop', 'KDH Van Long Distance', 'VIP Escort'],
        showMechanicExtra: false
    },
    'plumber': {
        workshop: 'Lanka Flow Plumbing Works',
        subDefaults: 'Emergency Pipe Leaks, Pressure Pumps, Water Tank Fixtures',
        presets: ['Main Pipe Leaks', 'Overhead Water Tank', 'Water Pressure Pump', 'Drainage Clearing'],
        showMechanicExtra: false
    },
    'electrician': {
        workshop: 'ElectroTech Power Solutions',
        subDefaults: '3-Phase Wiring, Breaker Panel, Solar Inverter Repair',
        presets: ['3-Phase Breakers', 'Solar Inverter Setup', 'Short Circuit Rescue', 'House Rewiring'],
        showMechanicExtra: false
    },
    'ac_repair': {
        workshop: 'CoolBreeze Inverter AC Care',
        subDefaults: 'Inverter AC Gas Refill, Chemical Cleaning, Compressor Repair',
        presets: ['Inverter Gas Top-Up', 'Deep Chemical Clean', 'PCB Circuit Board', 'HVAC Installation'],
        showMechanicExtra: false
    },
    '3d_design': {
        workshop: 'Colombo 3D Prototyping & CAD Lab',
        subDefaults: 'SolidWorks Modeling, STL 3D Printing, 4K Product Renders',
        presets: ['SolidWorks Parts', 'Watertight STL 3D Print', 'Architectural 3D BIM', 'Blender Visuals'],
        showMechanicExtra: false
    }
};

function toggleRoleFields(role) {
    const providerFields = document.getElementById('providerFields');
    const btnText = document.getElementById('btnSubmitText');
    
    if (role === 'provider') {
        providerFields.classList.remove('hidden');
        btnText.innerText = "Register as Service Pro & Activate Radar";
        handleProCategoryChange(document.getElementById('proCategorySelect').value);
    } else {
        providerFields.classList.add('hidden');
        btnText.innerText = "Complete Registration & Enter Hub";
    }
}

function handleProCategoryChange(category) {
    const conf = CATEGORY_CONFIGS[category] || CATEGORY_CONFIGS['plumber'];
    
    const workshopInput = document.getElementById('inputWorkshop');
    if (workshopInput && !workshopInput.value) {
        workshopInput.placeholder = 'e.g. ' + conf.workshop;
    }

    const subInput = document.getElementById('inputSubCategory');
    if (subInput && !subInput.value) {
        subInput.value = conf.subDefaults;
    }

    const mechExtra = document.getElementById('mechanicExtraFields');
    if (mechExtra) {
        mechExtra.style.display = conf.showMechanicExtra ? 'block' : 'none';
    }

    // Render presets
    const container = document.getElementById('subCategoryPresetsContainer');
    if (container) {
        container.innerHTML = '';
        conf.presets.forEach(p => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'px-2 py-1 rounded-md bg-dark-800 hover:bg-indigo-600 hover:text-white text-[10px] text-slate-300 font-medium transition border border-slate-700';
            btn.innerText = '+ ' + p;
            btn.onclick = () => {
                if (subInput.value && !subInput.value.includes(p)) {
                    subInput.value += ', ' + p;
                } else if (!subInput.value) {
                    subInput.value = p;
                }
            };
            container.appendChild(btn);
        });
    }
}

function validateNicLive(value) {
    const badge = document.getElementById('nicFormatBadge');
    const trimmed = value.trim().toUpperCase();
    const oldNicRegex = /^[0-9]{9}[VX]$/;
    const newNicRegex = /^[0-9]{12}$/;

    if (oldNicRegex.test(trimmed)) {
        badge.className = "text-[10px] font-mono font-bold text-emerald-400";
        badge.innerHTML = `<i class="fas fa-check-circle mr-1"></i> Valid 9-Digit NIC (Old Format)`;
    } else if (newNicRegex.test(trimmed)) {
        badge.className = "text-[10px] font-mono font-bold text-emerald-400";
        badge.innerHTML = `<i class="fas fa-check-circle mr-1"></i> Valid 12-Digit NIC (New Format)`;
    } else if (trimmed.length > 0) {
        badge.className = "text-[10px] font-mono text-amber-400";
        badge.innerHTML = `<i class="fas fa-info-circle mr-1"></i> Enter 9 digits + V/X or 12 digits`;
    } else {
        badge.className = "text-[10px] font-mono text-slate-400";
        badge.innerText = "9-digit + V or 12-digit";
    }
}

function setRate(amount) {
    const input = document.getElementById('inputHourlyRate');
    if (input) input.value = amount;
}

function handleCityPresetChange(key) {
    const hub = SRI_LANKA_HUBS[key];
    if (!hub) return;

    document.getElementById('regCity').value = hub.name;
    document.getElementById('regLat').value = hub.lat.toFixed(6);
    document.getElementById('regLng').value = hub.lng.toFixed(6);

    const badge = document.getElementById('gpsLockStatus');
    if (badge) {
        badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span><span>Locked: ${hub.lat.toFixed(4)}, ${hub.lng.toFixed(4)} (${hub.name})</span>`;
    }
}

function detectRegLocation() {
    if (!('geolocation' in navigator)) {
        alert('Geolocation is not supported by your browser.');
        return;
    }

    const badge = document.getElementById('gpsLockStatus');
    if (badge) {
        badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span><span>Detecting GPS Coordinates...</span>`;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            document.getElementById('regLat').value = lat.toFixed(6);
            document.getElementById('regLng').value = lng.toFixed(6);
            document.getElementById('regCity').value = "Live GPS Location";

            if (badge) {
                badge.className = "inline-flex items-center gap-1.5 text-[10px] font-mono text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20";
                badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span><span>GPS Locked: ${lat.toFixed(4)}, ${lng.toFixed(4)}</span>`;
            }
        },
        (err) => {
            alert('Could not detect location: ' + err.message + '. Defaulting to Colombo preset.');
            handleCityPresetChange('colombo_03');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

document.addEventListener('DOMContentLoaded', () => {
    const isPro = document.querySelector('input[name="role"][value="provider"]').checked;
    if (isPro) {
        handleProCategoryChange(document.getElementById('proCategorySelect').value);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
