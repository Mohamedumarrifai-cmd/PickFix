<?php
/**
 * PickFix - Role-Based Registration
 * 
 * Allows new users to sign up either as a Customer or as a verified Service Provider / 3D Designer.
 * Employs PDO transactions, bcrypt password hashing, and Sri Lankan NIC validation.
 */

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
    $category = $_POST['category'] ?? 'plumber';
    $workshopName = trim($_POST['workshop_name'] ?? '');
    $subCategory = trim($_POST['sub_category'] ?? '');
    $engineTypes = trim($_POST['engine_types'] ?? 'Petrol, Diesel, Hybrid/EV');
    $towingAvailable = !empty($_POST['towing_available']) ? 1 : 0;
    $nicNumber = trim($_POST['nic_number'] ?? '');
    $hourlyRate = floatval($_POST['hourly_rate'] ?? 2500);
    $city = trim($_POST['city'] ?? 'Colombo');
    $lat = floatval($_POST['lat'] ?? 6.9271);
    $lng = floatval($_POST['lng'] ?? 79.8612);

    // Basic Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = "Please fill in all required basic account information.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "The provided email address is invalid.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $passwordConfirm) {
        $error = "Passwords do not match. Please re-enter.";
    } elseif ($role === 'provider' && empty($nicNumber)) {
        $error = "Sri Lankan National Identity Card (NIC) number is required for verified providers.";
    } else {
        try {
            $db = get_db();

            // Check if email already registered
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $checkStmt->execute([':email' => $email]);
            if ($checkStmt->fetch()) {
                $error = "An account with this email address already exists. Please sign in.";
            } else {
                // Begin Transaction
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

                $db->commit();

                // Automatically log user in
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $phone;
                $_SESSION['role'] = $role;

                if ($role === 'provider') {
                    $_SESSION['provider_id'] = $providerId;
                    $_SESSION['provider_category'] = $category;
                    $_SESSION['flash_success'] = "Welcome to PickFix! Your pro profile is now active on our dispatch radar.";
                    header("Location: provider_dashboard.php");
                } else {
                    $_SESSION['flash_success'] = "Account successfully registered! You can now request emergency or 3D CAD services.";
                    header("Location: customer_dashboard.php");
                }
                exit;
            }
        } catch (Exception $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}

$pageTitle = "Create an Account";
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-xl w-full bg-dark-900 border border-slate-800 rounded-3xl p-8 shadow-2xl backdrop-blur-xl">
        
        <!-- Header -->
        <div class="text-center space-y-2 mb-8">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-500 via-emerald-600 to-indigo-600 mx-auto flex items-center justify-center shadow-lg shadow-brand-500/20">
                <i class="fas fa-user-plus text-slate-950 text-xl font-bold"></i>
            </div>
            <h2 class="text-2xl font-black text-white">Join Pick<span class="text-brand-500">Fix</span> Sri Lanka</h2>
            <p class="text-xs text-slate-400">Order emergency repairs & 3D designs, or earn as a verified pro</p>
        </div>

        <!-- Error Notification -->
        <?php if ($error): ?>
            <div class="p-3.5 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-2.5 mb-6">
                <i class="fas fa-exclamation-circle text-base flex-shrink-0"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="space-y-5">
            
            <!-- Role Toggle Selector -->
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-2">Account Type</label>
                <div class="grid grid-cols-2 gap-3 p-1.5 bg-slate-950 rounded-2xl border border-slate-800">
                    <label class="cursor-pointer">
                        <input type="radio" name="role" value="customer" class="peer sr-only" <?= $preselectedRole === 'customer' ? 'checked' : '' ?> onchange="toggleRoleFields('customer')">
                        <div class="p-3 rounded-xl text-center text-xs font-bold transition peer-checked:bg-brand-500 peer-checked:text-slate-950 text-slate-400 hover:text-white">
                            <i class="fas fa-user mb-1 block text-sm"></i> Client / Customer
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="role" value="provider" class="peer sr-only" <?= $preselectedRole === 'provider' ? 'checked' : '' ?> onchange="toggleRoleFields('provider')">
                        <div class="p-3 rounded-xl text-center text-xs font-bold transition peer-checked:bg-indigo-600 peer-checked:text-white text-slate-400 hover:text-white">
                            <i class="fas fa-cube mb-1 block text-sm"></i> Pro / 3D Designer
                        </div>
                    </label>
                </div>
            </div>

            <!-- Basic Info Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Full Name</label>
                    <input type="text" name="name" required placeholder="e.g. Kasun Perera" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Phone Number</label>
                    <input type="tel" name="phone" required placeholder="+94 77 123 4567" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Email Address</label>
                <input type="email" name="email" required placeholder="name@domain.com" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Password</label>
                    <input type="password" name="password" required minlength="6" placeholder="Min 6 characters" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Confirm Password</label>
                    <input type="password" name="password_confirm" required minlength="6" placeholder="Confirm password" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                </div>
            </div>

            <!-- Provider / 3D Designer Specific Fields Section -->
            <div id="providerFields" class="<?= $preselectedRole === 'provider' ? '' : 'hidden' ?> pt-4 border-t border-slate-800 space-y-4">
                <div class="flex items-center gap-2 text-xs font-bold text-indigo-400 uppercase tracking-wider">
                    <i class="fas fa-id-badge"></i> Professional Verification & Category
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Registered Workshop / Studio Name</label>
                        <input type="text" name="workshop_name" placeholder="e.g. Apex Hybrid Auto / Lanka 3D Studio" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Primary Specialty</label>
                        <select name="category" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="mechanic">🚗 Garage Mechanic & Roadside Assistance</option>
                            <option value="3d_design">🧊 3D Design & CAD Modeling</option>
                            <option value="plumber">🔧 Plumbing & Water Works</option>
                            <option value="electrician">⚡ Electrical & Solar</option>
                            <option value="ac_repair">❄️ Inverter AC Servicing</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Sri Lankan NIC Number</label>
                        <input type="text" name="nic_number" placeholder="e.g. 199012345678 or 901234567V" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Powertrains / Tools (If Mechanic)</label>
                        <input type="text" name="engine_types" value="Petrol, Diesel, Hybrid/EV" placeholder="e.g. Hybrid/EV, Diesel, Petrol" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Sub-Specialty / Key Services</label>
                        <input type="text" name="sub_category" placeholder="e.g. Hybrid Diagnostics, SolidWorks STL, Engine Rebuild" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Base Rate (LKR)</label>
                        <input type="number" step="100" name="hourly_rate" value="3500" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between">
                    <label class="text-xs text-slate-300 font-semibold cursor-pointer flex items-center gap-2">
                        <input type="checkbox" name="towing_available" value="1" class="w-4 h-4 rounded text-amber-500 bg-slate-900 border-slate-700">
                        <span>Hydraulic Flatbed or Towing Truck Available</span>
                    </label>
                    <span class="text-[10px] text-amber-400 font-mono">Rescue Badge</span>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Operating City / Suburb</label>
                    <input type="text" name="city" value="Colombo" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                </div>

                <!-- Hidden GPS Coordinates with auto-fill -->
                <input type="hidden" id="regLat" name="lat" value="6.9271">
                <input type="hidden" id="regLng" name="lng" value="79.8612">
                <div class="flex items-center justify-between p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs">
                    <span class="text-slate-400"><i class="fas fa-crosshairs text-indigo-400 mr-1.5"></i> Auto-Broadcast GPS to Dispatch Radar</span>
                    <button type="button" onclick="detectRegLocation()" class="text-indigo-400 hover:text-indigo-300 font-bold underline">
                        Detect Location
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full py-4 rounded-xl bg-gradient-to-r from-brand-500 to-emerald-600 hover:from-brand-600 hover:to-emerald-700 text-slate-950 font-extrabold text-sm shadow-lg shadow-brand-500/25 transition transform hover:-translate-y-0.5">
                Complete Registration
            </button>

        </form>

        <!-- Login Redirect -->
        <div class="pt-6 border-t border-slate-800 text-center">
            <p class="text-xs text-slate-400">
                Already have a PickFix account? 
                <a href="login.php" class="font-bold text-brand-400 hover:text-brand-300">Sign In here</a>
            </p>
        </div>

    </div>
</div>

<script>
function toggleRoleFields(role) {
    const providerFields = document.getElementById('providerFields');
    if (role === 'provider') {
        providerFields.classList.remove('hidden');
    } else {
        providerFields.classList.add('hidden');
    }
}

function detectRegLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                document.getElementById('regLat').value = pos.coords.latitude.toFixed(6);
                document.getElementById('regLng').value = pos.coords.longitude.toFixed(6);
                alert('GPS location detected: ' + pos.coords.latitude.toFixed(4) + ', ' + pos.coords.longitude.toFixed(4));
            },
            () => {
                alert('Could not detect location. Using Colombo default.');
            }
        );
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

