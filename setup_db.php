<?php
/**
 * PickFix - Automatic Database Setup & Health Check Tool
 * 
 * Creates the MySQL database 'pickfix_db' if it does not exist,
 * executes the complete schema.sql migration, seeds demo data,
 * and sets up required upload directories.
 */

// Enable comprehensive error reporting during setup
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require_once __DIR__ . '/config/database.php';

$outputMessages = [];
$status = 'ready';
$error = null;

// Allow automatic execution via CLI or via Web button
$isCli = (php_sapi_name() === 'cli');
$autoRun = $isCli || (isset($_POST['run_setup']) || isset($_GET['auto']));

if ($autoRun) {
    try {
        // Step 1: Connect to MySQL server without selecting DB
        $outputMessages[] = "Connecting to MySQL server at " . DB_HOST . ":" . DB_PORT . "...";
        $rawPdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET),
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
            ]
        );
        $outputMessages[] = "<span class='text-emerald-400'>[OK]</span> Connected to MySQL server successfully.";

        // Step 2: Read schema.sql
        $schemaFile = __DIR__ . '/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("schema.sql file not found in " . __DIR__);
        }
        $outputMessages[] = "Reading schema.sql (" . number_format(filesize($schemaFile)) . " bytes)...";
        $sql = file_get_contents($schemaFile);

        // Step 3: Execute SQL statements
        $outputMessages[] = "Executing database creation & migrations...";
        $rawPdo->exec($sql);
        $outputMessages[] = "<span class='text-emerald-400'>[OK]</span> Schema and demo seeds executed successfully.";

        // Step 4: Verify tables & run self-healing schema check
        $db = get_db();
        ensure_database_schema($db);
        $tablesStmt = $db->query("SHOW TABLES");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
        $outputMessages[] = "<span class='text-emerald-400'>[OK]</span> Verified " . count($tables) . " tables: " . implode(', ', $tables);

        // Step 5: Count seed records
        $userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $providerCount = $db->query("SELECT COUNT(*) FROM providers")->fetchColumn();
        $bookingCount = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
        $msgCount = $db->query("SELECT COUNT(*) FROM messages")->fetchColumn();
        $revCount = $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
        $txnCount = $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn();

        $outputMessages[] = "<span class='text-emerald-400'>[OK]</span> Seeded: $userCount users, $providerCount providers/garages, $bookingCount bookings, $msgCount chat messages, $revCount reviews, $txnCount monetization transactions.";

        // Step 6: Verify upload directories
        $dirs = [UPLOAD_DIR_REFERENCES, UPLOAD_DIR_DELIVERABLES];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
        $outputMessages[] = "<span class='text-emerald-400'>[OK]</span> Upload storage directories verified.";

        $status = 'success';
    } catch (Exception $e) {
        $status = 'error';
        $error = $e->getMessage();
        $outputMessages[] = "<span class='text-rose-400'>[FAIL]</span> Error: " . $e->getMessage();
    }
}

if ($isCli) {
    echo "=== PickFix Database Setup Tool ===\n";
    foreach ($outputMessages as $msg) {
        echo strip_tags($msg) . "\n";
    }
    if ($status === 'success') {
        echo "\nDatabase setup completed successfully! You can now use PickFix.\n";
        exit(0);
    } else {
        echo "\nSetup failed with error: " . $error . "\n";
        exit(1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PickFix - Database Migration & Setup Tool</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl">
        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-800">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-emerald-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                <i class="fas fa-database text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                    PickFix Database Setup
                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 font-semibold border border-emerald-500/30">Sri Lanka</span>
                </h1>
                <p class="text-xs text-slate-400">Initialize schema.sql, indexes, foreign keys & demo seeds</p>
            </div>
        </div>

        <?php if (!empty($outputMessages)): ?>
            <div class="bg-slate-950 border border-slate-800 rounded-xl p-4 mb-6 font-mono text-xs leading-relaxed max-h-60 overflow-y-auto">
                <?php foreach ($outputMessages as $msg): ?>
                    <div class="py-1 text-slate-300"><?= $msg ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($status === 'success'): ?>
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm mb-6 flex items-start gap-3">
                <i class="fas fa-check-circle text-lg mt-0.5"></i>
                <div>
                    <h3 class="font-bold">Database Ready & Seeded!</h3>
                    <p class="text-xs text-emerald-300/80 mt-1">The `pickfix_db` database is configured with chats, reviews, PayHere/Genie transactions, and garage mechanics.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6 text-xs">
                <div class="p-3 bg-slate-800/60 rounded-xl border border-slate-700/50">
                    <span class="text-slate-400 block font-semibold mb-1">Customer Account:</span>
                    <div class="font-mono text-emerald-300">customer@pickfix.lk</div>
                    <div class="text-slate-400">Pass: <span class="text-white font-mono">password123</span></div>
                </div>
                <div class="p-3 bg-slate-800/60 rounded-xl border border-slate-700/50">
                    <span class="text-slate-400 block font-semibold mb-1">Hybrid Garage Pro:</span>
                    <div class="font-mono text-amber-300">priyantha.hybrid@pickfix.lk</div>
                    <div class="text-slate-400">Pass: <span class="text-white font-mono">password123</span></div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <a href="login.php" class="flex-1 py-3 px-4 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold rounded-xl text-center text-sm transition">
                    <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
                </a>
                <a href="index.php" class="flex-1 py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-xl text-center text-sm transition">
                    <i class="fas fa-home mr-2"></i> Visit Homepage
                </a>
            </div>
        <?php elseif ($status === 'error'): ?>
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm mb-6 flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-lg mt-0.5"></i>
                <div>
                    <h3 class="font-bold">Setup Failed</h3>
                    <p class="text-xs text-rose-300/80 mt-1"><?= htmlspecialchars($error) ?></p>
                    <p class="text-xs text-slate-400 mt-2">Make sure your MySQL server is running in XAMPP on port <?= DB_PORT ?> with user '<?= DB_USER ?>'.</p>
                </div>
            </div>

            <form method="POST">
                <button type="submit" name="run_setup" value="1" class="w-full py-3 px-4 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded-xl text-center text-sm transition">
                    <i class="fas fa-redo mr-2"></i> Retry Database Setup
                </button>
            </form>
        <?php else: ?>
            <p class="text-sm text-slate-400 mb-6">
                Click the button below to initialize the <span class="text-white font-semibold">pickfix_db</span> database on your MySQL server, create all foreign key relational tables (including real-time messages, reviews, and PayHere monetization), and load demo Sri Lankan service providers.
            </p>

            <form method="POST">
                <button type="submit" name="run_setup" value="1" class="w-full py-3.5 px-4 bg-gradient-to-r from-emerald-500 to-indigo-600 hover:from-emerald-600 hover:to-indigo-700 text-white font-bold rounded-xl text-center text-sm shadow-lg shadow-emerald-500/20 transition">
                    <i class="fas fa-play mr-2"></i> Initialize Database Now
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
