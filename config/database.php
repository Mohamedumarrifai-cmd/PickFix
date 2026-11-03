<?php
/**
 * PickFix - Database Connection & Core Utilities
 * 
 * Provides a PDO Database Singleton pattern for secure database interactions,
 * connection pooling, environment configuration, session management, and response helpers.
 * 
 * Sri Lanka's Premier On-Demand Services & 3D Fabrication Platform
 */

// Comprehensive error reporting during development/debugging to avoid silent 500 errors
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');

// Database Configuration Constants
defined('DB_HOST') or define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
defined('DB_PORT') or define('DB_PORT', getenv('DB_PORT') ?: '3306');
defined('DB_NAME') or define('DB_NAME', getenv('DB_NAME') ?: 'pickfix_db');
defined('DB_USER') or define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
defined('DB_CHARSET') or define('DB_CHARSET', 'utf8mb4');

// App Paths & Constants
defined('APP_NAME') or define('APP_NAME', 'PickFix');
defined('APP_TAGLINE') or define('APP_TAGLINE', "Sri Lanka's 1st On-Demand Local Services & 3D Design Network");
defined('UPLOAD_DIR_REFERENCES') or define('UPLOAD_DIR_REFERENCES', __DIR__ . '/../uploads/references/');
defined('UPLOAD_DIR_DELIVERABLES') or define('UPLOAD_DIR_DELIVERABLES', __DIR__ . '/../uploads/deliverables/');
defined('PLATFORM_COMMISSION_PERCENT') or define('PLATFORM_COMMISSION_PERCENT', 10.0); // 10% platform fee

/**
 * Singleton Database Class using PDO
 */
class Database {
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    private static ?string $lastError = null;

    /**
     * Private constructor prevents direct instantiation
     */
    private function __construct() {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true, // Enabled to allow safe parameter binding and prevent HY093 errors
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
        ];

        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Self-healing schema validation on connection
            ensure_database_schema($this->connection);
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            error_log("[PickFix Database Error] Initial connection failed: " . $e->getMessage());

            // If the database itself does not exist (Error 1049), attempt auto-creation
            if (strpos($e->getMessage(), '1049') !== false || strpos($e->getMessage(), 'Unknown database') !== false) {
                try {
                    $serverPdo = new PDO(
                        sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET),
                        DB_USER,
                        DB_PASS,
                        $options
                    );
                    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
                    ensure_database_schema($this->connection);
                    self::$lastError = null;
                } catch (Throwable $initErr) {
                    self::$lastError = $initErr->getMessage();
                    $this->connection = null;
                }
            } else {
                $this->connection = null;
            }
        }
    }

    /**
     * Get the singleton instance of Database
     * 
     * @return Database
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retrieve the active PDO connection
     * 
     * @return PDO|null
     */
    public function getConnection(): ?PDO {
        return $this->connection;
    }

    /**
     * Retrieve the last connection error message if any
     * 
     * @return string|null
     */
    public static function getLastError(): ?string {
        return self::$lastError;
    }

    /**
     * Prevent object cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton Database instance.");
    }
}

/**
 * Self-healing schema compatibility checker
 * Ensures that all newly introduced columns and tables exist so queries never throw 500 errors.
 */
function ensure_database_schema(PDO $pdo): void {
    static $alreadyChecked = false;
    if ($alreadyChecked) {
        return;
    }
    $alreadyChecked = true;

    try {
        // Check if tables exist
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        // 1. Providers columns
        if (in_array('providers', $tables)) {
            $provCols = $pdo->query("SHOW COLUMNS FROM providers")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('workshop_name', $provCols)) {
                $pdo->exec("ALTER TABLE providers ADD COLUMN `workshop_name` VARCHAR(150) NULL AFTER `user_id`");
            }
            if (!in_array('engine_types', $provCols)) {
                $pdo->exec("ALTER TABLE providers ADD COLUMN `engine_types` VARCHAR(120) NULL DEFAULT 'Petrol, Diesel, Hybrid/EV' AFTER `sub_category`");
            }
            if (!in_array('specialties', $provCols)) {
                $pdo->exec("ALTER TABLE providers ADD COLUMN `specialties` VARCHAR(255) NULL AFTER `engine_types`");
            }
            if (!in_array('towing_available', $provCols)) {
                $pdo->exec("ALTER TABLE providers ADD COLUMN `towing_available` TINYINT(1) NOT NULL DEFAULT 0 AFTER `specialties`");
            }
        }

        // 2. Bookings columns
        if (in_array('bookings', $tables)) {
            $bookCols = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('payment_status', $bookCols)) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN `payment_status` ENUM('unpaid', 'pending', 'paid') NOT NULL DEFAULT 'unpaid' AFTER `budget`");
            }
            if (!in_array('paid_amount', $bookCols)) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN `paid_amount` DECIMAL(10, 2) NULL AFTER `payment_status`");
            }
            if (!in_array('payment_method', $bookCols)) {
                $pdo->exec("ALTER TABLE bookings ADD COLUMN `payment_method` VARCHAR(50) NULL AFTER `paid_amount`");
            }
        }

        // 3. Messages table
        if (!in_array('messages', $tables)) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `messages` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `booking_id` INT NOT NULL,
                    `sender_id` INT NOT NULL,
                    `receiver_id` INT NOT NULL,
                    `message_text` TEXT NOT NULL,
                    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_messages_booking` (`booking_id`),
                    INDEX `idx_messages_sender` (`sender_id`),
                    INDEX `idx_messages_receiver` (`receiver_id`),
                    INDEX `idx_messages_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }

        // 4. Reviews table
        if (!in_array('reviews', $tables)) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `reviews` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `booking_id` INT NOT NULL UNIQUE,
                    `customer_id` INT NOT NULL,
                    `provider_id` INT NOT NULL,
                    `rating` TINYINT NOT NULL COMMENT '1 to 5 stars',
                    `comment` TEXT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_reviews_provider` (`provider_id`),
                    INDEX `idx_reviews_rating` (`rating`),
                    INDEX `idx_reviews_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }

        // 5. Transactions table
        if (!in_array('transactions', $tables)) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `transactions` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `booking_id` INT NOT NULL,
                    `customer_id` INT NOT NULL,
                    `provider_id` INT NOT NULL,
                    `amount_lkr` DECIMAL(10, 2) NOT NULL,
                    `platform_fee_lkr` DECIMAL(10, 2) NOT NULL,
                    `provider_payout_lkr` DECIMAL(10, 2) NOT NULL,
                    `payment_gateway` ENUM('payhere', 'genie', 'cash') NOT NULL DEFAULT 'payhere',
                    `status` ENUM('success', 'pending', 'failed') NOT NULL DEFAULT 'success',
                    `transaction_ref` VARCHAR(60) NOT NULL UNIQUE,
                    `payment_details` VARCHAR(255) NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_transactions_booking` (`booking_id`),
                    INDEX `idx_transactions_customer` (`customer_id`),
                    INDEX `idx_transactions_provider` (`provider_id`),
                    INDEX `idx_transactions_status` (`status`),
                    INDEX `idx_transactions_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }
    } catch (Throwable $e) {
        error_log("[PickFix Auto-Migration Warning] " . $e->getMessage());
    }
}

/**
 * Global helper to obtain the PDO database connection
 * 
 * @return PDO
 * @throws RuntimeException If database connection cannot be established
 */
function get_db(): PDO {
    $db = Database::getInstance()->getConnection();
    if (!$db) {
        $err = Database::getLastError();
        throw new RuntimeException("Database connection unavailable. Please run setup_db.php or verify MySQL service in XAMPP. Details: " . $err);
    }
    return $db;
}

/**
 * Safe database helper that returns null instead of throwing
 */
function get_db_safe(): ?PDO {
    try {
        return Database::getInstance()->getConnection();
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Start secure session with modern flags and headers-sent protection
 */
function pickfix_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_samesite', 'Lax');
        }
        session_start();
    }
}

/**
 * Check if a user is currently authenticated with valid positive ID
 */
function is_logged_in(): bool {
    pickfix_session_start();
    return !empty($_SESSION['user_id']) && is_numeric($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0 && !empty($_SESSION['role']);
}

/**
 * Get the currently authenticated user's session data safely
 */
function current_user(): ?array {
    pickfix_session_start();
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'          => (int)$_SESSION['user_id'],
        'name'        => $_SESSION['user_name'] ?? 'User',
        'email'       => $_SESSION['user_email'] ?? '',
        'phone'       => $_SESSION['user_phone'] ?? '',
        'role'        => $_SESSION['role'] ?? 'customer',
        'provider_id' => !empty($_SESSION['provider_id']) ? (int)$_SESSION['provider_id'] : null,
        'category'    => $_SESSION['provider_category'] ?? null
    ];
}

/**
 * Enforce role authorization or redirect safely without throwing unhandled exceptions
 * 
 * @param array|string $allowedRoles 'customer', 'provider', or ['customer', 'provider']
 * @param string $redirectFallback Target location if unauthorized
 */
function require_role($allowedRoles, string $redirectFallback = 'login.php'): void {
    pickfix_session_start();
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = "Please sign in to access this page.";
        if (!headers_sent()) {
            header("Location: $redirectFallback");
            exit;
        } else {
            echo "<script>window.location.href = '" . addslashes($redirectFallback) . "';</script>";
            exit;
        }
    }

    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $currentRole = $_SESSION['role'] ?? '';

    if (!in_array($currentRole, $roles, true)) {
        $target = ($currentRole === 'provider') ? 'provider_dashboard.php' : 'customer_dashboard.php';
        if (!headers_sent()) {
            header("Location: $target");
            exit;
        } else {
            echo "<script>window.location.href = '" . addslashes($target) . "';</script>";
            exit;
        }
    }
}

/**
 * Standardized JSON API Response
 */
function json_response(bool $success, $data = null, ?string $message = null, int $statusCode = 200): void {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'data'      => $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Sanitize string input for safe HTML output
 */
function sanitize(?string $str): string {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency in Sri Lankan Rupees (LKR)
 */
function format_lkr(?float $amount): string {
    return 'Rs. ' . number_format((float)$amount, 2);
}

/**
 * Human-readable service labels
 */
function get_service_label(string $type): string {
    $labels = [
        'plumber'     => 'Plumbing & Water Works',
        'electrician' => 'Electrical & Solar Power',
        'mechanic'    => 'Garage Mechanic & Breakdown',
        'ac_repair'   => 'Inverter AC Repair & Service',
        '3d_design'   => '3D CAD & Digital Prototyping',
        'emergency'   => 'Emergency SOS Fast-Response'
    ];
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

/**
 * Generates Tailwind CSS Status Badge HTML
 */
function render_status_badge(string $status): string {
    $status = strtolower($status);
    $configs = [
        'pending' => [
            'bg' => 'bg-amber-500/10 text-amber-400 border border-amber-500/30',
            'icon' => 'fa-clock',
            'label' => 'Pending Dispatch'
        ],
        'accepted' => [
            'bg' => 'bg-blue-500/10 text-blue-400 border border-blue-500/30',
            'icon' => 'fa-user-check',
            'label' => 'Pro Assigned'
        ],
        'in_progress' => [
            'bg' => 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/30',
            'icon' => 'fa-spinner fa-spin',
            'label' => 'In Progress'
        ],
        'completed' => [
            'bg' => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30',
            'icon' => 'fa-check-circle',
            'label' => 'Completed'
        ],
        'cancelled' => [
            'bg' => 'bg-rose-500/10 text-rose-400 border border-rose-500/30',
            'icon' => 'fa-times-circle',
            'label' => 'Cancelled'
        ]
    ];

    $cfg = $configs[$status] ?? [
        'bg' => 'bg-slate-700 text-slate-300 border border-slate-600',
        'icon' => 'fa-info-circle',
        'label' => ucfirst($status)
    ];

    return sprintf(
        '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold %s">
            <i class="fas %s text-[11px]"></i> %s
        </span>',
        $cfg['bg'],
        $cfg['icon'],
        $cfg['label']
    );
}

/**
 * Calculate Platform Monetization Commission and Net Provider Payout
 * 
 * @param float $amountLkr Gross transaction amount
 * @param float $percent Commission percentage (default 10%)
 * @return array ['platform_fee' => float, 'provider_payout' => float, 'rate' => float]
 */
function calculate_commission(float $amountLkr, float $percent = PLATFORM_COMMISSION_PERCENT): array {
    $platformFee = round(($amountLkr * ($percent / 100)), 2);
    $providerPayout = round($amountLkr - $platformFee, 2);
    return [
        'gross_amount'    => $amountLkr,
        'commission_rate' => $percent,
        'platform_fee'    => $platformFee,
        'provider_payout' => $providerPayout
    ];
}

/**
 * Render Payment Status Badge
 */
function render_payment_badge(?string $status): string {
    $status = strtolower((string)$status);
    if ($status === 'paid') {
        return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"><i class="fas fa-check"></i> Paid</span>';
    } elseif ($status === 'pending') {
        return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30"><i class="fas fa-hourglass-half"></i> Payment Pending</span>';
    } else {
        return '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-800 text-slate-400 border border-slate-700"><i class="fas fa-receipt"></i> Unpaid</span>';
    }
}

/**
 * Render Star Rating HTML
 */
function render_stars(float $rating): string {
    $full = floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = max(0, 5 - (int)$full - (int)$half);

    $html = '<span class="text-amber-400 inline-flex items-center gap-0.5">';
    for ($i = 0; $i < $full; $i++) {
        $html .= '<i class="fas fa-star text-xs"></i>';
    }
    if ($half) {
        $html .= '<i class="fas fa-star-half-alt text-xs"></i>';
    }
    for ($i = 0; $i < $empty; $i++) {
        $html .= '<i class="far fa-star text-xs text-slate-600"></i>';
    }
    $html .= sprintf(' <span class="text-slate-300 font-bold ml-1 text-xs">%.1f</span></span>', $rating);
    return $html;
}

/**
 * Calculate Haversine distance between two coordinates in kilometers
 */
function haversine_distance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earthRadiusKm = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadiusKm * $c, 2);
}

/**
 * Calculate estimated transit arrival time (ETA) in minutes
 */
function calculate_transit_eta(float $distanceKm, float $avgSpeedKmh = 28.0): int {
    return max(3, (int)round(($distanceKm / $avgSpeedKmh) * 60) + 3);
}
