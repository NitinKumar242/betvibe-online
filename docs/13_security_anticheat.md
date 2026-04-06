# Security + Anti-Fraud Implementation

## Server-Side Security Layers

### Layer 1: Nginx (First line)
```nginx
# Rate limit zones
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
limit_req_zone $binary_remote_addr zone=api:10m rate=60r/m;
limit_req_zone $binary_remote_addr zone=bets:10m rate=30r/m;

# Apply to routes
location /api/auth/ {
    limit_req zone=login burst=5 nodelay;
}
location /api/games/ {
    limit_req zone=bets burst=10 nodelay;
}
location /api/ {
    limit_req zone=api burst=20 nodelay;
}
```

### Layer 2: PHP Application
```php
// All auth routes: RateLimiter check
// All POST routes: CSRF token check
// All /api/* routes: Session auth check
// All game routes: Active game session check (no parallel bets)
```

### Layer 3: Database
```sql
-- Wallet operations use SELECT FOR UPDATE (row lock)
-- All transactions in DB transactions (rollback on error)
-- Bet table append-only (no UPDATE except status)
```

## Input Validation Rules
```php
class Validator {
    // Username: 3-20 chars, alphanumeric + underscore only
    public static function username(string $v): bool {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $v);
    }

    // Amount: positive number, max 2 decimal places
    public static function amount(mixed $v): bool {
        return is_numeric($v) && $v > 0 && $v == round($v, 2);
    }

    // Phone: 10-15 digits, optional + prefix
    public static function phone(string $v): bool {
        return preg_match('/^\+?[0-9]{10,15}$/', $v);
    }
}

// XSS prevention: all output through htmlspecialchars()
function esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// SQL: ALL queries use PDO prepared statements
// NEVER: "SELECT * FROM users WHERE id = " . $id
// ALWAYS: DB::query("SELECT * FROM users WHERE id = ?", [$id])
```

## Anti-Fraud Detection Rules
```php
class FraudDetectionService {
    // Run after each deposit + withdrawal request
    public function runChecks(int $userId): void {
        $this->checkMultipleAccountsSameIP($userId);
        $this->checkDepositWithdrawNoWager($userId);
        $this->checkRapidBetPattern($userId);
        $this->checkReferralAbuse($userId);
    }

    private function checkMultipleAccountsSameIP(int $userId): void {
        $user = DB::query("SELECT last_ip FROM users WHERE id=?", [$userId])->first();
        $count = DB::query(
            "SELECT COUNT(*) as c FROM users WHERE last_ip=? AND id!=? AND created_at > NOW()-INTERVAL 30 DAY",
            [$user->last_ip, $userId]
        )->first()->c;
        if ($count >= 2) $this->flag($userId, "multiple_accounts_same_ip:{$user->last_ip}");
    }

    private function checkDepositWithdrawNoWager(int $userId): void {
        $wallet = DB::query("SELECT total_wagered FROM wallets WHERE user_id=?", [$userId])->first();
        $totalDeposited = DB::query(
            "SELECT COALESCE(SUM(amount),0) as d FROM transactions WHERE user_id=? AND type='deposit'",
            [$userId])->first()->d;
        if ($totalDeposited > 500 && $wallet->total_wagered < $totalDeposited * 0.05) {
            $this->flag($userId, "deposit_no_wager:deposited={$totalDeposited},wagered={$wallet->total_wagered}");
        }
    }

    private function checkRapidBetPattern(int $userId): void {
        // More than 30 bets in 60 seconds = bot behavior
        $recentBets = DB::query(
            "SELECT COUNT(*) as c FROM bets WHERE user_id=? AND created_at > NOW()-INTERVAL 60 SECOND",
            [$userId])->first()->c;
        if ($recentBets > 30) $this->flag($userId, "rapid_bets:{$recentBets}_in_60s");
    }

    private function checkReferralAbuse(int $userId): void {
        // User referred 5+ people from same IP range
        $sameIpReferrals = DB::query(
            "SELECT COUNT(*) as c FROM referrals r
             JOIN users u ON u.id=r.referred_id
             WHERE r.referrer_id=? AND u.last_ip=(SELECT last_ip FROM users WHERE id=?)",
            [$userId, $userId])->first()->c;
        if ($sameIpReferrals >= 3) $this->flag($userId, "referral_abuse:same_ip_referrals={$sameIpReferrals}");
    }

    private function flag(int $userId, string $reason): void {
        DB::query("UPDATE users SET fraud_flag=1, fraud_reason=? WHERE id=? AND fraud_flag=0",
            [$reason, $userId]);
        if (DB::affectedRows() > 0) { // Only notify on first flag
            TelegramService::sendToAdminGroup("🚨 Fraud Flag\nUser ID: {$userId}\nReason: {$reason}");
        }
    }
}
```

## Device Fingerprinting
```php
// Server-side fingerprint (simple, no JS needed)
function getDeviceFingerprint(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    return md5($ua . '|' . $lang . '|' . $encoding);
}
```

## Session Security
```php
// httpOnly = JS cannot read cookie (XSS protection)
// Secure = HTTPS only
// SameSite=Strict = CSRF protection at cookie level
setcookie('session_token', $token, [
    'expires'  => $expires,
    'path'     => '/',
    'domain'   => 'betvibe.com',
    'httponly' => true,
    'secure'   => true,
    'samesite' => 'Strict',
]);
```

## CSRF Protection
```php
// Generate token per session
function getCsrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate on POST
function validateCsrf(): bool {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? $_POST['_csrf']
          ?? null;
    return $token && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Add to all HTML forms:
// <input type="hidden" name="_csrf" value="<?= getCsrfToken() ?>">
// Add to all fetch() calls:
// headers: { 'X-CSRF-Token': window.csrfToken }
```

## SQL Injection Prevention
```php
// ALL database calls through prepared statements
// DB::query() always uses PDO with ? placeholders
// NEVER concatenate user input into SQL

// Bad (never do):
$user = DB::raw("SELECT * FROM users WHERE username = '$username'");

// Good (always do):
$user = DB::query("SELECT * FROM users WHERE username = ?", [$username])->first();
```

## Admin Panel IP Whitelist
```php
// Add to Admin panel middleware
function checkAdminIP(): void {
    $allowedIPs = explode(',', env('ADMIN_IP_WHITELIST', '127.0.0.1'));
    if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
        http_response_code(403);
        die('Access denied');
    }
}
```

## WatchPay Webhook Signature Verification
```php
// Always verify before processing
$signature = $_SERVER['HTTP_X_WATCHPAY_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');
$expected = hash_hmac('sha256', $payload, env('WATCHPAY_WEBHOOK_SECRET'));

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    die('Invalid signature');
}
```
