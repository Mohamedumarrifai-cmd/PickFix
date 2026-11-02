<?php
/**
 * PickFix - Global Header Template
 * 
 * Reusable layout component with responsive Tailwind CSS, FontAwesome,
 * Leaflet.js map dependencies, dynamic session auth states, and flash notifications.
 */

require_once __DIR__ . '/../config/database.php';
pickfix_session_start();

$currentUser = current_user();
$currentPage = basename($_SERVER['PHP_SELF']);

// Flash message retrieval
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
$flashInfo = $_SESSION['flash_info'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_info']);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' | PickFix Sri Lanka' : 'PickFix - Sri Lanka On-Demand Services & 3D CAD Marketplace' ?></title>
    <meta name="description" content="Sri Lanka's 1st on-demand local services, emergency breakdown dispatch, and professional 3D CAD modeling marketplace.">

    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#ecfdf5',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                        },
                        accent: {
                            500: '#6366f1',
                            600: '#4f46e5',
                        },
                        dark: {
                            800: '#1e293b',
                            900: '#0f172a',
                            950: '#020617',
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- FontAwesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Leaflet.js Map CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .radar-pulse {
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: radar 2s infinite cubic-bezier(0.66, 0, 0, 1);
        }
        @keyframes radar {
            to { box-shadow: 0 0 0 20px rgba(16, 185, 129, 0); }
        }
        .sos-pulse {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            animation: sos 1.5s infinite cubic-bezier(0.66, 0, 0, 1);
        }
        @keyframes sos {
            to { box-shadow: 0 0 0 16px rgba(239, 68, 68, 0); }
        }
    </style>
</head>
<body class="bg-dark-950 text-slate-100 min-h-screen flex flex-col selection:bg-brand-500 selection:text-slate-950">

    <!-- Top Emergency Banner / Sri Lanka Hotline -->
    <div class="bg-gradient-to-r from-red-600 via-rose-600 to-amber-600 text-white text-xs font-semibold py-1.5 px-4 text-center tracking-wide flex items-center justify-center gap-3">
        <span class="flex items-center gap-1.5"><i class="fas fa-bolt animate-pulse"></i> 24/7 Islandwide Emergency Dispatch Active</span>
        <span class="hidden md:inline text-red-200">|</span>
        <span class="hidden md:inline"><i class="fas fa-phone-alt"></i> Emergency Hotlines: <strong>1990 (Suwa Seriya)</strong> / <strong>119 (Police)</strong> / PickFix SOS Radar</span>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="sticky top-0 z-50 backdrop-blur-md bg-dark-950/90 border-b border-slate-800/80 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                
                <!-- Logo & Brand -->
                <a href="index.php" class="flex items-center gap-3 group">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-brand-500 via-emerald-600 to-accent-500 flex items-center justify-center shadow-lg shadow-brand-500/25 group-hover:scale-105 transition transform">
                        <i class="fas fa-cube text-slate-950 text-xl font-black"></i>
                    </div>
                    <div class="flex flex-col">
                        <div class="flex items-center gap-1.5">
                            <span class="text-2xl font-black tracking-tight text-white">Pick<span class="text-brand-500">Fix</span></span>
                            <span class="bg-slate-800 text-[10px] uppercase font-bold text-slate-300 px-1.5 py-0.5 rounded border border-slate-700">LK</span>
                        </div>
                        <span class="text-[10px] text-slate-400 font-medium tracking-wide">Services & 3D CAD Network</span>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <div class="hidden lg:flex items-center gap-8">
                    <a href="index.php" class="text-sm font-semibold transition <?= $currentPage === 'index.php' ? 'text-brand-500' : 'text-slate-300 hover:text-white' ?>">
                        <i class="fas fa-home text-xs mr-1 opacity-70"></i> Home
                    </a>
                    <a href="index.php#services" class="text-sm font-semibold text-slate-300 hover:text-white transition">
                        <i class="fas fa-tools text-xs mr-1 opacity-70"></i> Physical Services
                    </a>
                    <a href="index.php#cad-studio" class="text-sm font-semibold text-indigo-400 hover:text-indigo-300 transition flex items-center gap-1.5">
                        <i class="fas fa-cubes text-xs"></i> 3D Design & CAD Lab
                        <span class="px-1.5 py-0.2 rounded text-[10px] bg-indigo-500/20 border border-indigo-500/30 font-bold">DIGITAL</span>
                    </a>
                    <a href="customer_dashboard.php?emergency=1" class="text-sm font-bold text-red-400 hover:text-red-300 transition flex items-center gap-1.5 sos-pulse px-3 py-1 rounded-full bg-red-500/10 border border-red-500/30">
                        <i class="fas fa-shield-alt text-xs animate-pulse"></i> SOS Dispatch
                    </a>
                </div>

                <!-- Auth Buttons / User State -->
                <div class="hidden sm:flex items-center gap-4">
                    <?php if ($currentUser): ?>
                        
                        <?php if ($currentUser['role'] === 'customer'): ?>
                            <a href="customer_dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-dark-800 border border-slate-700 hover:border-brand-500 text-slate-200 hover:text-white transition shadow-sm">
                                <i class="fas fa-th-large text-brand-500"></i> Customer Hub
                            </a>
                        <?php else: ?>
                            <a href="provider_dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-dark-800 border border-slate-700 hover:border-indigo-500 text-slate-200 hover:text-white transition shadow-sm">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                                Provider Radar
                            </a>
                        <?php endif; ?>

                        <!-- User Profile Dropdown Capsule -->
                        <div class="flex items-center gap-3 pl-2 border-l border-slate-800">
                            <div class="flex flex-col text-right">
                                <span class="text-xs font-bold text-white"><?= sanitize($currentUser['name']) ?></span>
                                <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                                    <?= $currentUser['role'] === 'provider' ? ($currentUser['category'] === '3d_design' ? '3D CAD Designer' : 'Service Pro') : 'Client' ?>
                                </span>
                            </div>
                            <a href="logout.php" title="Sign Out" class="w-9 h-9 rounded-xl bg-slate-800/80 hover:bg-rose-500/20 hover:text-rose-400 text-slate-400 flex items-center justify-center transition border border-slate-700/60">
                                <i class="fas fa-sign-out-alt text-sm"></i>
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Guest State -->
                        <a href="login.php" class="text-xs font-bold text-slate-300 hover:text-white px-3 py-2 transition">
                            Sign In
                        </a>
                        <a href="register.php?role=provider" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-dark-800 text-indigo-300 border border-indigo-500/30 hover:bg-dark-800/80 transition">
                            <i class="fas fa-briefcase text-xs"></i> Join as Pro / 3D Designer
                        </a>
                        <a href="register.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-gradient-to-r from-brand-500 to-emerald-600 hover:from-brand-600 hover:to-emerald-700 text-slate-950 shadow-lg shadow-brand-500/20 transition transform hover:-translate-y-0.5">
                            <i class="fas fa-user-plus text-xs"></i> Get Started
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button -->
                <div class="flex sm:hidden">
                    <button id="mobileMenuBtn" type="button" class="p-2.5 rounded-xl bg-slate-800/80 text-slate-400 hover:text-white hover:bg-slate-700 transition" aria-label="Toggle Navigation">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                </div>

            </div>
        </div>

        <!-- Mobile Drawer Menu -->
        <div id="mobileMenuDrawer" class="hidden sm:hidden border-t border-slate-800 bg-dark-900/95 px-4 pt-3 pb-6 space-y-3">
            <a href="index.php" class="block py-2 text-sm font-semibold text-slate-200 hover:text-brand-500">
                <i class="fas fa-home w-5 text-slate-400"></i> Home
            </a>
            <a href="index.php#services" class="block py-2 text-sm font-semibold text-slate-200 hover:text-brand-500">
                <i class="fas fa-tools w-5 text-slate-400"></i> Physical Services
            </a>
            <a href="index.php#cad-studio" class="block py-2 text-sm font-semibold text-indigo-400 hover:text-indigo-300">
                <i class="fas fa-cubes w-5 text-indigo-400"></i> 3D Design & CAD Modeling
            </a>
            <a href="customer_dashboard.php?emergency=1" class="block py-2 text-sm font-bold text-red-400">
                <i class="fas fa-shield-alt w-5 text-red-400 animate-pulse"></i> Emergency SOS Dispatch
            </a>

            <div class="pt-3 border-t border-slate-800 flex flex-col gap-2">
                <?php if ($currentUser): ?>
                    <?php if ($currentUser['role'] === 'customer'): ?>
                        <a href="customer_dashboard.php" class="w-full text-center py-2.5 rounded-xl text-xs font-bold bg-dark-800 text-white border border-slate-700">
                            Customer Hub
                        </a>
                    <?php else: ?>
                        <a href="provider_dashboard.php" class="w-full text-center py-2.5 rounded-xl text-xs font-bold bg-dark-800 text-white border border-slate-700">
                            Provider Radar & Deliveries
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="w-full text-center py-2.5 rounded-xl text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                        Sign Out (<?= sanitize($currentUser['name']) ?>)
                    </a>
                <?php else: ?>
                    <a href="login.php" class="w-full text-center py-2.5 rounded-xl text-xs font-bold bg-slate-800 text-white">
                        Sign In
                    </a>
                    <a href="register.php" class="w-full text-center py-2.5 rounded-xl text-xs font-bold bg-brand-500 text-slate-950">
                        Register Account
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Global Dynamic Flash Alerts -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full mt-4">
        <?php if ($flashSuccess): ?>
            <div class="flex items-center justify-between p-4 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-sm shadow-lg mb-4 animate-fade-in">
                <div class="flex items-center gap-3">
                    <i class="fas fa-check-circle text-lg"></i>
                    <span><?= sanitize($flashSuccess) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-emerald-300 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="flex items-center justify-between p-4 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-400 text-sm shadow-lg mb-4 animate-fade-in">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-lg"></i>
                    <span><?= sanitize($flashError) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-rose-300 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <?php if ($flashInfo): ?>
            <div class="flex items-center justify-between p-4 rounded-xl bg-blue-500/15 border border-blue-500/30 text-blue-400 text-sm shadow-lg mb-4 animate-fade-in">
                <div class="flex items-center gap-3">
                    <i class="fas fa-info-circle text-lg"></i>
                    <span><?= sanitize($flashInfo) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-blue-300 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Script to toggle mobile menu -->
    <script>
        document.getElementById('mobileMenuBtn')?.addEventListener('click', function() {
            document.getElementById('mobileMenuDrawer')?.classList.toggle('hidden');
        });
    </script>

    <!-- Page Content Container Starts in Templates -->

