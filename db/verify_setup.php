<?php
/**
 * BetVibe — Setup Verification Script
 * Run: php db/verify_setup.php
 * Checks all tables, games, admin account, .env, and PHP extensions
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

echo "╔══════════════════════════════════════════════╗\n";
echo "║         BetVibe Setup Verification           ║\n";
echo "╚══════════════════════════════════════════════╝\n\n";

$passed = 0;
$failed = 0;
$warnings = 0;

function check($label, $condition, $errorMsg = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ {$label}\n";
        $passed++;
    } else {
        echo "  ❌ {$label}" . ($errorMsg ? " — {$errorMsg}" : '') . "\n";
        $failed++;
    }
}

function warn($label, $msg = '') {
    global $warnings;
    echo "  ⚠️  {$label}" . ($msg ? " — {$msg}" : '') . "\n";
    $warnings++;
}

// ─── 1. Environment Variables ──────────────────────
echo "── Environment ──\n";
$requiredEnvVars = [
    'APP_NAME', 'APP_URL', 'APP_ENV', 'APP_SECRET',
    'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
    'WS_PORT',
];
$optionalEnvVars = [
    'WATCHPAY_MERCHANT_ID', 'WATCHPAY_API_KEY',
    'TELEGRAM_BOT_TOKEN', 'TELEGRAM_ADMIN_CHAT_ID',
    'VAPID_PUBLIC_KEY', 'VAPID_PRIVATE_KEY',
];

foreach ($requiredEnvVars as $var) {
    check("ENV: {$var}", !empty($_ENV[$var]), 'Not set');
}

// Domain check
check("APP_URL contains betsvibe.online",
    str_contains($_ENV['APP_URL'] ?? '', 'betsvibe.online'),
    'Expected betsvibe.online in APP_URL'
);

foreach ($optionalEnvVars as $var) {
    if (empty($_ENV[$var])) {
        warn("ENV: {$var}", 'Not set — feature may not work');
    } else {
        check("ENV: {$var}", true);
    }
}
echo "\n";

// ─── 2. PHP Extensions ────────────────────────────
echo "── PHP Extensions ──\n";
$requiredExtensions = ['pdo', 'pdo_mysql', 'gd', 'mbstring', 'xml', 'curl', 'zip', 'bcmath', 'json', 'openssl'];
foreach ($requiredExtensions as $ext) {
    check("PHP ext: {$ext}", extension_loaded($ext), 'Not installed');
}
check("PHP version >= 8.2", version_compare(PHP_VERSION, '8.2.0', '>='), 'Current: ' . PHP_VERSION);
echo "\n";

// ─── 3. Database Connection ───────────────────────
echo "── Database ──\n";
try {
    $dsn = "mysql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']};charset=utf8mb4";
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    ]);
    check("Database connection", true);
} catch (PDOException $e) {
    check("Database connection", false, $e->getMessage());
    echo "\n❌ Cannot continue without database. Fix connection and retry.\n";
    exit(1);
}

// ─── 4. All Tables ────────────────────────────────
echo "\n── Tables (21 expected) ──\n";
$expectedTables = [
    'admins', 'users', 'wallets', 'sessions', 'transactions',
    'withdrawal_requests', 'game_config', 'game_rounds', 'bets',
    'referrals', 'daily_quests', 'user_quest_progress', 'login_rewards',
    'win_streaks', 'admin_audit_log', 'ip_blacklist',
    'telegram_support_tickets', 'push_subscriptions',
    'rate_limits', 'active_game_sessions', 'telegram_states',
];

$actualTables = [];
$res = $pdo->query("SHOW TABLES");
while ($row = $res->fetch(PDO::FETCH_NUM)) {
    $actualTables[] = $row[0];
}

foreach ($expectedTables as $table) {
    check("Table: {$table}", in_array($table, $actualTables), 'Missing — run schema.sql');
}

// Check InnoDB
$nonInnoDB = [];
foreach ($actualTables as $table) {
    $status = $pdo->query("SHOW TABLE STATUS WHERE Name = '{$table}'")->fetch();
    if ($status && $status->Engine !== 'InnoDB') {
        $nonInnoDB[] = "{$table} ({$status->Engine})";
    }
}
if (!empty($nonInnoDB)) {
    warn("Non-InnoDB tables", implode(', ', $nonInnoDB));
} else {
    check("All tables use InnoDB", true);
}
echo "\n";

// ─── 5. Game Config ───────────────────────────────
echo "── Game Config (16 games expected) ──\n";
$gameCount = $pdo->query("SELECT COUNT(*) as c FROM game_config")->fetch()->c;
check("Games in game_config", $gameCount == 16, "Found: {$gameCount}");

$expectedGames = [
    'color_predict', 'fast_parity', 'crash', 'limbo', 'mines', 'plinko',
    'dice_duel', 'keno', 'hilo', 'tower_climb', 'dragon_tiger',
    'spin_wheel', 'coin_flip', 'roulette_lite', 'lucky_slots', 'number_guess',
];
$actualGames = $pdo->query("SELECT game_slug FROM game_config")->fetchAll(PDO::FETCH_COLUMN);
foreach ($expectedGames as $slug) {
    if (!in_array($slug, $actualGames)) {
        check("Game: {$slug}", false, 'Missing from game_config');
    }
}

$enabledCount = $pdo->query("SELECT COUNT(*) as c FROM game_config WHERE is_enabled = 1")->fetch()->c;
check("Enabled games", $enabledCount > 0, "{$enabledCount} enabled");
echo "\n";

// ─── 6. Admin Account ────────────────────────────
echo "── Admin Account ──\n";
$adminCount = $pdo->query("SELECT COUNT(*) as c FROM admins")->fetch()->c;
check("Admin account exists", $adminCount > 0, "Create with seed_games.php");

$superAdmin = $pdo->query("SELECT COUNT(*) as c FROM admins WHERE role = 'super_admin'")->fetch()->c;
check("Super admin exists", $superAdmin > 0, "No super_admin role found");
echo "\n";

// ─── 7. Users Schema ─────────────────────────────
echo "── Users Schema ──\n";
$columns = [];
$res = $pdo->query("DESCRIBE users");
while ($row = $res->fetch()) {
    $columns[] = $row->Field;
}
$requiredColumns = [
    'id', 'username', 'email', 'phone', 'password_hash', 'avatar_id',
    'xp', 'level', 'failed_attempts', 'is_banned', 'fraud_flag',
    'fraud_reason', 'ref_code', 'kyc_status',
];
foreach ($requiredColumns as $col) {
    check("users.{$col}", in_array($col, $columns), 'Column missing');
}
echo "\n";

// ─── 8. File System ──────────────────────────────
echo "── File System ──\n";
$rootDir = dirname(__DIR__);

$requiredDirs = [
    'public', 'public/assets/css', 'public/assets/js', 'public/assets/images',
    'app/Controllers', 'app/Models', 'app/Services', 'app/Games',
    'app/Core', 'app/Middleware', 'app/Exceptions',
    'config', 'cron', 'db', 'deploy', 'storage', 'storage/logs',
    'storage/win_cards', 'websocket', 'telegram', 'vendor',
];

foreach ($requiredDirs as $dir) {
    check("Dir: {$dir}", is_dir("{$rootDir}/{$dir}"), 'Missing');
}

// Check write permissions
$writableDirs = ['storage', 'storage/logs', 'storage/win_cards'];
foreach ($writableDirs as $dir) {
    check("Writable: {$dir}", is_writable("{$rootDir}/{$dir}"), 'Not writable');
}

// Key files
$requiredFiles = [
    'public/index.php', 'public/manifest.json', 'public/sw.js',
    'public/assets/css/app.css', 'public/assets/js/app.js',
    'public/assets/js/socket.js',
    'app/Core/App.php', 'app/Core/DB.php',
    '.env', 'composer.json', 'composer.lock',
];
foreach ($requiredFiles as $file) {
    check("File: {$file}", file_exists("{$rootDir}/{$file}"), 'Missing');
}

// PWA icons
check("PWA icon 192", file_exists("{$rootDir}/public/assets/images/icon-192.png"), 'Missing');
check("PWA icon 512", file_exists("{$rootDir}/public/assets/images/icon-512.png"), 'Missing');
echo "\n";

// ─── 9. Composer Dependencies ────────────────────
echo "── Composer Dependencies ──\n";
$requiredPackages = [
    'vlucas/phpdotenv', 'cboden/ratchet', 'react/event-loop',
    'irazasyed/telegram-bot-sdk', 'monolog/monolog',
];
$composerLock = json_decode(file_get_contents("{$rootDir}/composer.lock"), true);
$installedPackages = array_column($composerLock['packages'] ?? [], 'name');
foreach ($requiredPackages as $pkg) {
    check("Package: {$pkg}", in_array($pkg, $installedPackages), 'Not installed');
}
echo "\n";

// ─── Summary ─────────────────────────────────────
echo "══════════════════════════════════════════════\n";
echo "  Results: ✅ {$passed} passed  |  ❌ {$failed} failed  |  ⚠️  {$warnings} warnings\n";
echo "══════════════════════════════════════════════\n";

if ($failed === 0) {
    echo "\n🎉 All checks passed! BetVibe is ready for deployment.\n";
} else {
    echo "\n⚠️  Fix the {$failed} failed check(s) before going live.\n";
}

exit($failed > 0 ? 1 : 0);
