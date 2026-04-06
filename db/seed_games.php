<?php
/**
 * BetVibe - Database Seed Script
 * Seeds game_config, admins, and test user data
 */

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Database configuration
$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_NAME'] ?? 'betvibe';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Connected to database successfully.\n\n";

    // Seed game_config table
    echo "Seeding game_config table...\n";
    $games = [
        ['color-predict', 'Color Predict', 18.00, 10, 5000, 180],
        ['fast-parity', 'Fast Parity', 19.00, 10, 5000, 60],
        ['crash', 'Crash', 20.00, 10, 10000, 0],
        ['limbo', 'Limbo', 20.00, 10, 10000, 0],
        ['mines', 'Mines', 22.00, 10, 10000, 0],
        ['plinko', 'Plinko', 21.00, 10, 10000, 0],
        ['dice-duel', 'Dice Duel', 19.00, 10, 10000, 0],
        ['keno', 'Keno', 20.00, 10, 5000, 0],
        ['hilo', 'HiLo Cards', 20.00, 10, 10000, 0],
        ['tower', 'Tower Climb', 19.00, 10, 10000, 0],
        ['dragon-tiger', 'Dragon Tiger', 18.50, 10, 10000, 0],
        ['spin-wheel', 'Spin Wheel', 20.00, 10, 10000, 0],
        ['coin-flip', 'Coin Flip', 19.00, 10, 10000, 0],
        ['roulette', 'Roulette Lite', 18.90, 10, 10000, 0],
        ['lucky-slots', 'Lucky Slots', 19.00, 10, 5000, 0],
        ['number-guess', 'Number Guess', 20.00, 10, 10000, 0],
    ];

    $stmt = $pdo->prepare("INSERT INTO game_config (game_slug, display_name, win_ratio, min_bet, max_bet, round_duration) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name), win_ratio=VALUES(win_ratio), min_bet=VALUES(min_bet), max_bet=VALUES(max_bet), round_duration=VALUES(round_duration)");

    foreach ($games as $game) {
        $stmt->execute($game);
        echo "  - Inserted: {$game[1]} ({$game[0]})\n";
    }

    $gameCount = $pdo->query("SELECT COUNT(*) FROM game_config")->fetchColumn();
    echo "  Total games in game_config: $gameCount\n\n";

    // Seed admins table
    echo "Seeding admins table...\n";
    $adminPassword = password_hash('Admin@12345', PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, role) VALUES (?, ?, 'super_admin') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)");
    $stmt->execute(['admin', $adminPassword]);
    echo "  - Inserted super admin: admin\n";

    $adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    echo "  Total admins: $adminCount\n\n";

    // Seed test user
    echo "Seeding test user...\n";
    $testPassword = password_hash('TestUser@123', PASSWORD_BCRYPT);
    $refCode = strtoupper(substr(md5(uniqid()), 0, 8));

    // Insert test user
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, ref_code) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)");
    $stmt->execute(['testuser', $testPassword, $refCode]);
    echo "  - Inserted test user: testuser\n";

    // Get test user ID
    $testUserId = $pdo->query("SELECT id FROM users WHERE username = 'testuser'")->fetchColumn();

    // Insert wallet for test user
    $stmt = $pdo->prepare("INSERT INTO wallets (user_id, real_balance) VALUES (?, 1000.00) ON DUPLICATE KEY UPDATE real_balance=VALUES(real_balance)");
    $stmt->execute([$testUserId]);
    echo "  - Created wallet with 1000 NPR for testuser\n";

    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "  Total users: $userCount\n\n";

    echo "=== SEEDING COMPLETE ===\n";
    echo "Summary:\n";
    echo "  - Games seeded: $gameCount\n";
    echo "  - Admins seeded: $adminCount\n";
    echo "  - Users seeded: $userCount\n";
    echo "\nLogin credentials:\n";
    echo "  Admin: admin / Admin@12345\n";
    echo "  Test User: testuser / TestUser@123\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
