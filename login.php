<?php
/**
 * PickFix - Role-Based Authentication: Login
 * 
 * Secure login with bcrypt password verification, role-based redirection,
 * and convenient demo shortcuts for immediate evaluation.
 */

require_once __DIR__ . '/config/database.php';
pickfix_session_start();

// If already authenticated, redirect to appropriate hub
if (is_logged_in()) {
    $role = $_SESSION['role'] ?? 'customer';
    header("Location: " . ($role === 'provider' ? 'provider_dashboard.php' : 'customer_dashboard.php'));
    exit;
}

$error = null;
$email = '';

// Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please provide both your registered email and password.";
    } else {
        try {
            $db = get_db();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Password matches! Initialize session
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_phone'] = $user['phone'];
                $_SESSION['role'] = $user['role'];

                // If user is a provider, also fetch provider profile details
                if ($user['role'] === 'provider') {
                    $provStmt = $db->prepare("SELECT id, category, is_online FROM providers WHERE user_id = :uid LIMIT 1");
                    $provStmt->execute([':uid' => $user['id']]);
                    $prov = $provStmt->fetch();

                    if ($prov) {
                        $_SESSION['provider_id'] = (int)$prov['id'];
                        $_SESSION['provider_category'] = $prov['category'];
                    }
                    $_SESSION['flash_success'] = "Welcome back, " . $user['name'] . "! Your job radar is active.";
                    header("Location: provider_dashboard.php");
                    exit;
                } else {
                    $_SESSION['flash_success'] = "Welcome back, " . $user['name'] . "!";
                    header("Location: customer_dashboard.php");
                    exit;
                }
            } else {
                $error = "Invalid email address or password. Please try again.";
            }
        } catch (Exception $e) {
            $error = "Authentication failed: " . $e->getMessage();
        }
    }
}

$pageTitle = "Sign In";
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-dark-900 border border-slate-800 rounded-3xl p-8 shadow-2xl backdrop-blur-xl">
        
        <!-- Header -->
        <div class="text-center space-y-2">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-500 to-accent-500 mx-auto flex items-center justify-center shadow-lg shadow-brand-500/20">
                <i class="fas fa-lock text-slate-950 text-xl font-bold"></i>
            </div>
            <h2 class="text-2xl font-black text-white">Sign In to Pick<span class="text-brand-500">Fix</span></h2>
            <p class="text-xs text-slate-400">Access your on-demand service hub or 3D CAD designer dashboard</p>
        </div>

        <!-- Error Notification -->
        <?php if ($error): ?>
            <div class="p-3.5 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-400 text-xs flex items-center gap-2.5">
                <i class="fas fa-exclamation-circle text-base flex-shrink-0"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Quick 1-Click Demo Logins -->
        <div class="p-3 bg-slate-950/80 rounded-2xl border border-slate-800/80 space-y-2">
            <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 px-1">
                <span><i class="fas fa-bolt text-amber-400 mr-1"></i> Quick 1-Click Demo Accounts:</span>
                <span class="text-[10px] text-slate-500 font-mono">password123</span>
            </div>
            <div class="grid grid-cols-4 gap-2">
                <button type="button" onclick="fillLogin('customer@pickfix.lk', 'password123')" class="px-2 py-2 rounded-xl bg-dark-800 hover:bg-slate-700 text-[11px] font-semibold text-emerald-400 border border-emerald-500/30 transition text-center">
                    <i class="fas fa-user block text-xs mb-0.5"></i> Customer
                </button>
                <button type="button" onclick="fillLogin('priyantha.hybrid@pickfix.lk', 'password123')" class="px-2 py-2 rounded-xl bg-dark-800 hover:bg-slate-700 text-[11px] font-semibold text-amber-400 border border-amber-500/30 transition text-center">
                    <i class="fas fa-car-burst block text-xs mb-0.5"></i> Garage Pro
                </button>
                <button type="button" onclick="fillLogin('roshan.3d@pickfix.lk', 'password123')" class="px-2 py-2 rounded-xl bg-dark-800 hover:bg-slate-700 text-[11px] font-semibold text-indigo-400 border border-indigo-500/30 transition text-center">
                    <i class="fas fa-cube block text-xs mb-0.5"></i> 3D Artist
                </button>
                <button type="button" onclick="fillLogin('kasun.plumber@pickfix.lk', 'password123')" class="px-2 py-2 rounded-xl bg-dark-800 hover:bg-slate-700 text-[11px] font-semibold text-blue-400 border border-blue-500/30 transition text-center">
                    <i class="fas fa-wrench block text-xs mb-0.5"></i> Plumber
                </button>
            </div>
        </div>

        <!-- Login Form -->
        <form method="POST" action="login.php" class="space-y-4">
            
            <div>
                <label for="email" class="block text-xs font-bold text-slate-300 mb-1.5 uppercase">Email Address</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-4 top-3.5 text-slate-500 text-sm"></i>
                    <input type="email" id="email" name="email" required value="<?= sanitize($email) ?>" placeholder="e.g. name@example.com" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-bold text-slate-300 uppercase">Password</label>
                    <span class="text-[11px] text-slate-500">Demo: <code class="text-emerald-400 font-mono">password123</code></span>
                </div>
                <div class="relative">
                    <i class="fas fa-lock absolute left-4 top-3.5 text-slate-500 text-sm"></i>
                    <input type="password" id="password" name="password" required placeholder="••••••••" class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
                </div>
            </div>

            <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-brand-500 to-emerald-600 hover:from-brand-600 hover:to-emerald-700 text-slate-950 font-extrabold text-sm shadow-lg shadow-brand-500/25 transition transform hover:-translate-y-0.5">
                Sign In to Dashboard
            </button>

        </form>

        <!-- Footer / Register Link -->
        <div class="pt-4 border-t border-slate-800 text-center space-y-2">
            <p class="text-xs text-slate-400">
                Don't have an account yet? 
                <a href="register.php" class="font-bold text-brand-400 hover:text-brand-300">Create an Account</a>
            </p>
            <p class="text-[11px] text-slate-500">
                Are you a service technician or 3D CAD freelancer? 
                <a href="register.php?role=provider" class="text-indigo-400 hover:underline">Register as a Pro</a>
            </p>
        </div>

    </div>
</div>

<script>
function fillLogin(userEmail, userPassword) {
    document.getElementById('email').value = userEmail;
    document.getElementById('password').value = userPassword;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

