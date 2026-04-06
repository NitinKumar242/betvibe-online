# Authentication System

## Overview
No OTP. No social login. No email recovery links.
Auth = Email/Phone + Password only.
Password recovery = Telegram bot /recover command → admin manual reset.

## Registration Flow
1. User fills form: email OR phone + username + password + confirm password + age checkbox
2. Frontend validates: password match, min 8 chars, username format (3-20 chars, alphanumeric + underscore)
3. POST /api/auth/register
4. Server checks: username unique, email/phone unique, not banned IP
5. bcrypt hash password (cost 12)
6. Generate unique ref_code (6 chars alphanumeric)
7. INSERT users + wallets rows in transaction
8. If referral code in URL: INSERT referrals row (status=pending)
9. Generate session token (64 char random hex), INSERT sessions
10. Set httpOnly cookie: session_token (30 days if remember, 24hr if not)
11. Return: { success: true, user: { id, username, level, xp } }

## Login Flow
1. POST /api/auth/login { identifier, password, remember_me }
2. Find user by email OR phone
3. Check is_banned = 0
4. Check failed_attempts < 5 (rate limited per IP + per account)
5. password_verify() against hash
6. If fail: increment failed_attempts, return error
7. If success: reset failed_attempts, update last_login + last_ip + device_fp
8. Generate new session token, INSERT sessions
9. Set httpOnly cookie
10. Return user data

## Session Middleware (every protected request)
```php
class AuthMiddleware {
    public function handle(Request $request): ?User {
        $token = $_COOKIE['session_token'] ?? null;
        if (!$token) return null;

        $session = DB::query(
            "SELECT s.*, u.* FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token = ? AND s.expires_at > NOW()
             AND u.is_banned = 0",
            [$token]
        )->first();

        if (!$session) return null;

        // IP change check
        if ($session->ip !== $_SERVER['REMOTE_ADDR']) {
            // flag for re-login but don't hard block (VPN users)
        }

        return $session;
    }
}
```

## Password Recovery via Telegram Bot
### User-side flow:
1. User opens Telegram, messages @BetVibeSupport_bot
2. Sends /recover command
3. Bot replies: "Please send your registered username"
4. User sends username
5. Bot logs request in telegram_support_tickets table, notifies admin group
6. Bot replies: "Your request has been submitted. Admin will reset within 24 hours."

### Admin-side flow:
1. Admin sees alert in Telegram group: "🔑 Password Recovery Request — Username: nitin99 — Telegram: @userhandle"
2. Admin verifies identity (asks security question or checks account details)
3. Admin opens Admin Panel → User Management → Search username → Reset Password
4. Admin Panel generates temp password (8 chars), updates bcrypt hash
5. Admin Panel sends temp password to Telegram bot via API
6. Bot sends private message to user: "Your temporary password is: XXXXX — Please login and change immediately."

## Security Rules
- Passwords: bcrypt, cost factor 12
- Session tokens: random_bytes(32) → bin2hex = 64 chars
- httpOnly + Secure + SameSite=Strict cookies
- CSRF: X-CSRF-Token header on all state-changing POSTs
- Rate limit login: 5 attempts per 15 min per IP (Nginx + PHP double layer)
- Account lock: 5 wrong passwords = 15 min lock (failed_attempts counter in users table)
- Session expiry: 30 days (remember me) / 24 hours (normal)
- Logout: DELETE session from DB + expire cookie

## Registration Validation Rules
```php
$rules = [
    'username'  => 'required|min:3|max:20|regex:/^[a-zA-Z0-9_]+$/|unique:users',
    'email'     => 'required_without:phone|email|unique:users',
    'phone'     => 'required_without:email|min:10|max:15|unique:users',
    'password'  => 'required|min:8|max:64',
    'password_confirm' => 'required|same:password',
    'age_confirm' => 'required|accepted',
];
```
