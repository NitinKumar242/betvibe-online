# Phase 3 — Authentication System

## Agent Instructions
Read these docs before starting:
- docs/03_auth_system.md
- docs/02_database_schema.md (users, sessions tables)

---

## Prompt 3.1 — Auth Controller

```
Create /var/www/betvibe/app/Controllers/AuthController.php

Implement these endpoints:

POST /api/auth/register
- Validate: username (3-20 chars, alphanumeric+underscore, unique), email OR phone required (not both required), password min 8 chars, password_confirm match, age_confirm=1
- Check IP not in ip_blacklist
- Hash password with password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12])
- Generate ref_code: strtolower(substr(md5(uniqid()),0,6)) — ensure unique
- DB transaction: INSERT users, INSERT wallets (both zero balance)
- If ?ref= in URL: find user by ref_code, INSERT referrals(referrer_id, referred_id, pending)
- Block self-referral check
- Create session (see Prompt 3.2)
- Return: {success:true, user:{id,username,level,xp,ref_code}}

POST /api/auth/login
- Find user by email OR phone (whichever was provided)
- Check is_banned = 0, return 403 if banned
- Check lockout (failed_attempts >= 5 in last 15 min): return 429
- password_verify() — if fail: increment failed_attempts, return 401
- If success: reset failed_attempts, update last_login, last_ip, device_fp
- Create session
- Return: {success:true, user:{id,username,level,xp,real_balance,bonus_coins}}

POST /api/auth/logout
- Delete session from DB by token
- Expire cookie
- Return: {success:true}

GET /api/auth/me (requires auth middleware)
- Return current user data + balance

All responses must be JSON. Status codes matter.
```

---

## Prompt 3.2 — Session Service

```
Create /var/www/betvibe/app/Services/SessionService.php

Methods:

create(int $userId, bool $remember = false): string
- Generate token: bin2hex(random_bytes(32))
- Get client IP from $_SERVER['REMOTE_ADDR']
- Get device fingerprint: md5 of User-Agent + IP (simplified)
- expires_at: NOW() + 30 days (remember) or NOW() + 24 hours
- INSERT into sessions table
- Set cookie: setcookie('session_token', $token, [
    'expires' => $expiresTimestamp,
    'path' => '/',
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Strict'
  ])
- Return token

validate(): ?array
- Read cookie session_token
- Query sessions JOIN users WHERE token=? AND expires_at > NOW() AND is_banned=0
- Return user row or null
- Also: if IP changed, log warning but don't invalidate

destroy(string $token): void
- DELETE from sessions WHERE token=?
- Expire cookie (set past date)

cleanExpired(): void  (called by cron daily)
- DELETE FROM sessions WHERE expires_at < NOW()
```

---

## Prompt 3.3 — Auth Middleware

```
Create /var/www/betvibe/app/Middleware/AuthMiddleware.php

handle(): User|null
- Call SessionService::validate()
- If null and route is protected: return JSON {error:'Unauthorized'} with 401 status
- If valid: set $GLOBALS['currentUser'] = user
- Return user object

Create /var/www/betvibe/app/Middleware/CsrfMiddleware.php

For all POST/PUT/DELETE requests:
- Check header X-CSRF-Token OR form field _csrf
- Token stored in session ($_SESSION['csrf_token'])
- Generate if not exists: bin2hex(random_bytes(32))
- Validate on state-changing requests
- Exempt: /api/webhooks/* (uses signature verification instead)

Add to public/index.php router: run AuthMiddleware before protected routes.
```

---

## Prompt 3.4 — Auth Frontend Pages

```
Create frontend pages using vanilla HTML + CSS + JS (dark theme, #0D0D0D bg, purple accent #7F77DD):

1. public/login.php (or route /login):
- Email/Phone field + Password field
- "Remember me" checkbox
- Submit button: "Login Karo"
- Link to register
- Error messages shown inline (not alert)
- On success: redirect to /

2. public/register.php (or route /register):
- Username field (live availability check via GET /api/auth/check-username)
- Email OR Phone (tabbed or toggle)
- Password + confirm
- Age confirm checkbox
- Referral code field (auto-filled if ?ref= in URL, read-only)
- Submit button: "Account Banao"
- On success: redirect to /

3. Logout button:
- POST /api/auth/logout
- Clear localStorage
- Redirect to /login

Dark theme requirements:
- Background: #0D0D0D
- Input bg: #1A1A1A, border: #333
- Button bg: #7F77DD
- Error color: #E24B4A
- Font: Inter from Google Fonts

Mobile-first, 375px minimum width.
```

---

## Prompt 3.5 — Rate Limiting

```
Add rate limiting at PHP level (Nginx already has one — this is double-layer):

Create /var/www/betvibe/app/Services/RateLimiter.php

Uses MySQL table rate_limits:
CREATE TABLE rate_limits (
  key_hash VARCHAR(64) PRIMARY KEY,
  attempts INT DEFAULT 0,
  window_start DATETIME,
  INDEX idx_window (window_start)
);

check(string $key, int $maxAttempts, int $windowSeconds): bool
- key = md5("login:" . $ip) or md5("register:" . $ip)
- If no record or window expired: create/reset, return true
- If attempts >= maxAttempts: return false (rate limited)
- Else: increment attempts, return true

Apply to:
- POST /api/auth/login: 5 attempts per 15 min per IP
- POST /api/auth/register: 3 attempts per hour per IP

Return 429 with Retry-After header when limited.
Cleanup cron: DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL 1 HOUR
```
