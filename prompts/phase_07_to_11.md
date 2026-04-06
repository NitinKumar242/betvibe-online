# Phase 7 — Referral System

## Agent Instructions
Read: docs/08_referral_system.md

## Prompt 7.1 — ReferralService + API

```
Create /var/www/betvibe/app/Services/ReferralService.php

Implement full ReferralService from docs/08_referral_system.md.

Key methods:
- checkFirstDeposit(int $userId, float $depositAmount): void
  (Called from WatchPay webhook after every deposit)
- getDashboard(int $userId): array
  Returns: {total, pending, converted, total_earned, recent_referrals:[]}
- generateShareLink(User $user): string
  Returns: APP_URL . '/r/' . user->ref_code

Create route handler for /r/{code}:
- Look up ref_code in users table
- Redirect to /register?ref={code}
- If ref_code not found: redirect to /register (no error shown)

API endpoints:
GET /api/referral/dashboard — auth required
GET /api/referral/share-link — returns link + WhatsApp share URL

Frontend: Referral page at /referral
- Your link: [betvibe.com/r/nitin99] [Copy] [WhatsApp]
- Stats cards: Total/Pending/Converted/Earned
- Referral table with status badges
```

---

# Phase 8 — Gen Z Retention Features

## Agent Instructions
Read: docs/07_genz_features.md

## Prompt 8.1 — Win Streak + Share Card

```
Create /var/www/betvibe/app/Services/StreakService.php
Implement full class from docs/07_genz_features.md.

Create /var/www/betvibe/app/Services/WinCardService.php
Implement image generation using PHP GD:
- Download Inter-Bold.ttf font → storage/fonts/
- Dark background 800×420px
- Username, game, "Won NPR X", "Yx multiplier", site URL
- Save as PNG in storage/win_cards/
- Return public URL

GET /api/win-card/{betId}
- Auth required, only own bets
- Check if win card exists, generate if not
- Return image URL

Frontend: After any win >= 3x, show share button overlay:
- "Share your win!" modal
- Preview of win card image
- Buttons: [WhatsApp] [Instagram] [Copy Link]
- WhatsApp: window.open('https://wa.me/?text=I just won NPR {amount} on BetVibe! 🔥 ' + link)
```

---

## Prompt 8.2 — Daily Quests Engine

```
Create /var/www/betvibe/app/Services/QuestService.php
Implement full class from docs/07_genz_features.md.

Create cron/daily_quests.php (runs at midnight NPT = 18:15 UTC):
- Generate 3 quests for today (1 easy + 1 medium + 1 hard)
- Pick from a pool of 20+ quest templates stored in config/quest_templates.php
- Rotate so same quest doesn't repeat within 7 days
- INSERT into daily_quests for today's date

GET /api/quests/today — auth required
Returns: [{quest_id, title, description, difficulty, xp_reward, progress, total_needed, is_complete}]

Frontend: Quest page at /quests
- 3 quest cards with progress bars
- XP reward badge on each
- Completed quests show green checkmark + claimed animation
- "Come back tomorrow for new quests" message after all completed
```

---

## Prompt 8.3 — Daily Login Reward + Leaderboard

```
Implement daily login reward (docs/07_genz_features.md):

GET /api/daily-reward/status — returns {can_claim, day_number, reward_coins, streak}
POST /api/daily-reward/claim — claims reward, returns {coins_given, day_number}

Frontend popup: Show on dashboard load if can_claim=true
- 7-day calendar grid showing rewards
- Current day highlighted
- "Claim Karo!" button
- Animation: coins flying to balance display

Leaderboard (docs/07_genz_features.md):

GET /api/leaderboard?period=daily|weekly|alltime
Returns top 10 with masked usernames, own rank always included

GET /api/leaderboard/my-rank?period=daily
Returns own position even if not in top 10

Frontend: /leaderboard page
- 3 tab pills: Today / This Week / All Time
- Top 3 with gold/silver/bronze styling
- Own rank pinned at bottom if not in top 10
- Auto-refresh every 60 seconds

cron/leaderboard.php (runs Sunday midnight NPT):
- Query top 3 weekly profit earners
- Credit NPR 500/200/100 real balance bonus
- INSERT transactions
- Send Telegram notification to admin
```

---

## Prompt 8.4 — WebSocket Real-time Features

```
Create /var/www/betvibe/websocket/server.php using Ratchet:

Handles:
- Crash game multiplier ticks (broadcast to all)
- Round start/end for Color Predict + Fast Parity
- Live win feed (broadcast to all)
- Personal events: balance_update, win_card_ready, level_up
- Connection auth: user sends session token on connect

Create /var/www/betvibe/app/Services/WebSocketService.php:
- Static method: broadcast(array $message) — sends to all connected clients
- Static method: sendToUser(int $userId, array $message) — sends to specific user
- Connects to WS server via internal socket (loopback)

Supervisor config for WS server:
/etc/supervisor/conf.d/betvibe-ws.conf:
[program:betvibe-ws]
command=php /var/www/betvibe/websocket/server.php
autostart=true
autorestart=true
user=www-data

Frontend websocket client (public/assets/js/socket.js):
- Auto-reconnect with exponential backoff
- Message type handlers: crash_tick, round_start, round_end, win_feed, balance_update, level_up
- Exports global socket object
```

---

# Phase 9 — Admin Panel (Full)

## Agent Instructions
Read: docs/09_admin_telegram.md

## Prompt 9.1 — Admin Auth + Dashboard

```
Create admin panel at admin.betvibe.com (separate Nginx server block).

Admin login: /admin/login
- Username + Password (no session sharing with main site)
- admin_session stored separately (admin_sessions table or prefixed key)
- Restrict to whitelisted IPs only (configurable in .env: ADMIN_IP_WHITELIST)

Create /var/www/betvibe/app/Controllers/AdminController.php

GET /admin/dashboard:
- Cards: Deposits today, Withdrawals today, House profit today, Active users today, New registrations today
- 30-day revenue chart (bar chart using Chart.js)
- Revenue by game (donut chart)
- Recent 10 bets across all users
- All data from queries in docs/09_admin_telegram.md

Styling: Can use light theme for admin (easier to scan data)
Use simple table layouts. Chart.js CDN for graphs.
```

---

## Prompt 9.2 — User Management + Game Control

```
Add to AdminController.php:

GET /admin/users?search=&page=1
- Paginated (25/page), search by username/email/phone
- Columns: ID, Username, Balance, Total Deposited, Total Withdrawn, Status, Joined
- Quick actions: View | Ban | Adjust Balance

GET /admin/users/{id}
- Full user detail: all transactions, all bets (paginated), referrals made
- Bet history: game, amount, result, multiplier, time

POST /admin/users/{id}/ban { reason }
POST /admin/users/{id}/unban
POST /admin/users/{id}/adjust-balance { type:'real'|'bonus', amount, reason }
POST /admin/users/{id}/reset-password (from docs/09_admin_telegram.md)

GET /admin/games
- Table of all 16 games with current config
- Inline edit: win_ratio slider (5-50%), min/max bet, enable toggle
- Save changes button per game

All admin actions logged to admin_audit_log.

GET /admin/fraud
- Users with fraud_flag=1
- Show flag reason, account details, IP
- Actions: Dismiss | Investigate | Ban

GET /admin/audit
- Full audit log with filters (admin, action type, date range)
```

---

## Prompt 9.3 — Finance + Withdrawal Queue

```
GET /admin/withdrawals
- Pending queue sorted by amount DESC
- Columns: ID, Username, Amount, WatchPay Account, Requested At
- Filter: pending/approved/rejected

POST /admin/withdrawals/{id}/approve
- Call WatchPayService::processPayout()
- Update status, log audit
- Send Telegram notification to user via bot

POST /admin/withdrawals/{id}/reject { reason }
- Restore balance to user
- Update status
- Send Telegram: "Your withdrawal was rejected: {reason}"

GET /admin/finance
- Revenue chart 30/60/90 days
- Game breakdown: total bets, total payout, house profit per game
- Top depositors list
- Withdrawal history
```

---

# Phase 10 — Telegram Bot + Push Notifications + PWA

## Agent Instructions
Read: docs/09_admin_telegram.md, docs/10_frontend_pwa.md

## Prompt 10.1 — Telegram Bot Full Implementation

```
Create /var/www/betvibe/telegram/webhook.php
Create /var/www/betvibe/app/Services/TelegramService.php

Implement full bot from docs/09_admin_telegram.md:

TelegramService methods:
- sendMessage(string $chatId, string $text, bool $markdown=false): void
- sendToAdminGroup(string $text): void
- answerCallbackQuery(string $callbackId, string $text): void

Bot conversation state stored in MySQL:
CREATE TABLE telegram_states (
  chat_id VARCHAR(50) PRIMARY KEY,
  state VARCHAR(50),
  data JSON,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

Implement all commands from docs.
Register webhook:
POST https://api.telegram.org/bot{TOKEN}/setWebhook
Body: { url: "https://betvibe.com/telegram/webhook" }
```

---

## Prompt 10.2 — PWA + Push Notifications

```
Create public/manifest.json with content from docs/10_frontend_pwa.md.
Create public/sw.js with service worker from docs/10_frontend_pwa.md.

Add to all pages: <link rel="manifest" href="/manifest.json">

Push Notification Setup:
1. Generate VAPID keys: php -r "echo base64_encode(random_bytes(32));"
2. Add to .env
3. Create /api/push/subscribe endpoint:
   - Auth required
   - Saves push subscription to push_subscriptions table
4. Create PushNotificationService.php:
   - Uses Web Push PHP library (or manual VAPID implementation)
   - send(int $userId, string $title, string $body, string $url): void

Notification triggers:
- Lucky Hours start (7:55pm NPT) → all users
- Weekly leaderboard reward → top 3 users
- Withdrawal status update → specific user
- Referral converted → referrer

Lucky Hours notification cron (runs 7:55pm NPT = 14:10 UTC):
- Send push to all users with push_subscriptions
- Title: "⚡ Lucky Hours Shuru Ho Gaya!"
- Body: "Abhi khelo — bonus multipliers available for 2 hours!"

Add PWA install prompt to main app:
- Show "Install BetVibe" banner after 2nd visit
- A2HS (Add to Home Screen) via BeforeInstallPromptEvent
- Dismiss option (don't show again for 7 days)
```

---

# Phase 11 — Launch + Final Polish

## Prompt 11.1 — Performance Optimization

```
Run these optimizations before launch:

1. PHP OPcache: enable in php.ini
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.max_accelerated_files=10000

2. Nginx gzip: already in config, verify active

3. Database: run EXPLAIN on these queries:
   - Leaderboard query (add index if needed)
   - Bet history query
   - Active round lookup

4. Images: convert all PNG assets to WebP
   find /var/www/betvibe/public/assets/images -name "*.png" -exec cwebp {} -o {}.webp \;

5. JS/CSS minification:
   Create deploy/minify.sh using terser and clean-css (npm tools)
   Output to public/assets/dist/

6. Add Cache-Control headers to Nginx for static assets:
   location ~* \.(js|css|png|webp|ico|woff2)$ { expires 30d; }
```

---

## Prompt 11.2 — Security Audit + Go-Live

```
Final security checks:

1. Verify .env not accessible: curl https://betvibe.com/.env (should 403)
2. Verify /app directory blocked: curl https://betvibe.com/app/Core/DB.php (should 403)
3. Test CSRF: send POST without token (should 403)
4. Test auth: access /wallet without login (should redirect to /login)
5. Test rate limit: 6 login attempts in a row (6th should get 429)
6. Test SQL injection: username field with '; DROP TABLE users; -- (should fail safely)
7. Verify HTTPS redirect: curl -I http://betvibe.com (should 301 to HTTPS)
8. Check all game results server-side: open Network tab, place bet, verify result not in request

Run: php -l on all PHP files to check for syntax errors
Run: mysql -e "SHOW TABLE STATUS FROM betvibe_db" to verify all tables using InnoDB

Final: php /var/www/betvibe/db/verify_setup.php
(Create this script to check: all tables exist, all 16 games in config, admin account exists, .env loaded)

Deploy checklist: docs/11_deployment_launch.md
```
