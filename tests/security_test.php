<?php
/**
 * BetVibe - Automated Security Test Script
 * Checks for common security vulnerabilities
 *
 * Usage: php tests/security_test.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo "=== BetVibe Security Audit ===\n\n";

$passed = 0;
$failed = 0;

// ──── 1. Sensitive Files Not Publicly Accessible ────
echo "🔒 Checking sensitive file exposure...\n";
$sensitiveFiles = ['.env', 'composer.json', 'composer.lock', 'db/schema.sql', 'vendor/autoload.php'];
$appUrl = $_ENV['APP_URL'] ?? 'http://localhost';

foreach ($sensitiveFiles as $file) {
    $url = "{$appUrl}/{$file}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "  ❌ {$file} is ACCESSIBLE! (HTTP {$httpCode})\n";
        $failed++;
    } else {
        echo "  ✅ {$file} is blocked (HTTP {$httpCode})\n";
        $passed++;
    }
}

// ──── 2. Password Security ────
echo "\n🔑 Checking password security...\n";
$db = \App\Core\DB::getInstance();

// Check for weak bcrypt cost
$sample = $db->first("SELECT password_hash FROM users LIMIT 1");
if ($sample) {
    $hash = $sample['password_hash'];
    if (str_starts_with($hash, '$2y$12$') || str_starts_with($hash, '$2y$10$')) {
        echo "  ✅ bcrypt in use (good)\n";
        $passed++;
    } else {
        echo "  ⚠️ Non-bcrypt hash detected: " . substr($hash, 0, 10) . "...\n";
        $failed++;
    }
} else {
    echo "  ⚠️ No users to check\n";
}

// ──── 3. SQL Injection Patterns ────
echo "\n💉 Scanning for SQL injection patterns...\n";
$phpFiles = glob(__DIR__ . '/../app/**/*.php');
$phpFiles = array_merge($phpFiles, glob(__DIR__ . '/../app/**/**/*.php'));
$unsafePatterns = 0;

foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    // Check for string concatenation in queries
    if (preg_match_all('/->query\s*\(\s*["\'].*\$[a-zA-Z_]+.*["\']/', $content, $matches)) {
        foreach ($matches[0] as $match) {
            // Skip if it's using bound parameters or constants
            if (strpos($match, '?') !== false) continue;
            echo "  ⚠️ Potential SQL injection in " . basename($file) . ": " . substr($match, 0, 60) . "...\n";
            $unsafePatterns++;
        }
    }
}

if ($unsafePatterns === 0) {
    echo "  ✅ No SQL injection patterns found\n";
    $passed++;
} else {
    echo "  ❌ {$unsafePatterns} potential SQL injection patterns\n";
    $failed++;
}

// ──── 4. CORS/Security Headers ────
echo "\n🛡️ Checking security headers...\n";
$ch = curl_init($appUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => true,
    CURLOPT_TIMEOUT => 5,
]);
$response = curl_exec($ch);
curl_close($ch);

$secHeaders = [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1',
    'Strict-Transport-Security' => '',
    'Content-Security-Policy' => '',
];

foreach ($secHeaders as $header => $expected) {
    if (stripos($response, $header) !== false) {
        echo "  ✅ {$header} present\n";
        $passed++;
    } else {
        echo "  ⚠️ {$header} missing (recommended)\n";
    }
}

// ──── 5. Rate Limiting ────
echo "\n⏱️ Checking rate limiting...\n";
$rateLimitHit = false;
$testUrl = "{$appUrl}/api/wallet/balance";

for ($i = 0; $i < 50; $i++) {
    $ch = curl_init($testUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 429) {
        $rateLimitHit = true;
        echo "  ✅ Rate limiting active (hit at request #{$i})\n";
        $passed++;
        break;
    }
}

if (!$rateLimitHit) {
    echo "  ⚠️ No rate limiting detected after 50 requests (check nginx config)\n";
}

// ──── 6. Environment ────
echo "\n🌍 Environment checks...\n";
$appEnv = $_ENV['APP_ENV'] ?? 'development';
$debug = $_ENV['APP_DEBUG'] ?? 'true';

if ($appEnv === 'production') {
    echo "  ✅ APP_ENV = production\n";
    $passed++;
} else {
    echo "  ⚠️ APP_ENV = {$appEnv} (should be 'production' in prod)\n";
}

if ($debug === 'false' || $debug === '0') {
    echo "  ✅ APP_DEBUG = false\n";
    $passed++;
} else {
    echo "  ❌ APP_DEBUG is enabled in production!\n";
    $failed++;
}

// ──── Summary ────
echo "\n" . str_repeat('=', 40) . "\n";
echo "✅ Passed: {$passed}\n";
echo "❌ Issues: {$failed}\n";
echo str_repeat('=', 40) . "\n";

if ($failed === 0) {
    echo "\n🎉 Security audit passed!\n";
} else {
    echo "\n⚠️ {$failed} security issues found. Address before go-live.\n";
}

exit($failed > 0 ? 1 : 0);
