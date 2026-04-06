# BetVibe — Agent Master Index

## IMPORTANT: Agent ko yeh file SABSE PEHLE padhni hai.
## Har phase start karne se pehle is file mein listed docs read karo.

---

## Project Reading Order

### Always Read First (Every Session)
1. docs/00_project_overview.md — What are we building?
2. docs/01_tech_stack.md — Server + dependencies
3. docs/02_database_schema.md — All 18 tables

### Then Read Phase-Specific Docs

| Phase | Prompt File | Read These Docs First |
|---|---|---|
| 1 | prompts/phase_01_server_setup.md | 00, 01 |
| 2 | prompts/phase_02_database.md | 02 |
| 3 | prompts/phase_03_auth.md | 03 |
| 4 | prompts/phase_04_wallet.md | 04 |
| 5 | prompts/phase_05_game_engine.md | 05, 06 |
| 6 | prompts/phase_06_all_games.md | 05, 06 |
| 7 | prompts/phase_07_to_11.md (Phase 7) | 08 |
| 8 | prompts/phase_07_to_11.md (Phase 8) | 07 |
| 9 | prompts/phase_07_to_11.md (Phase 9) | 09 |
| 10 | prompts/phase_07_to_11.md (Phase 10) | 09, 10 |
| 11 | prompts/phase_07_to_11.md (Phase 11) | 11 |

---

## All Files in This Project

### /docs/ — Reference Documentation
```
00_project_overview.md    — Goals, stack, folder structure, business rules
01_tech_stack.md          — VPS setup, Nginx, PHP, MySQL, Composer, crons
02_database_schema.md     — All 18 SQL tables + seed data
03_auth_system.md         — Email/phone+password auth, sessions, Telegram recovery
04_wallet_payment.md      — WatchPay deposit/withdraw, balance types, wagering
05_rng_engine.md          — RNGService, house edge math per game
06_all_games_mechanics.md — All 16 games: rules, payouts, API patterns
07_genz_features.md       — Streaks, win cards, quests, leaderboard, live feed
08_referral_system.md     — Deposit-triggered referral, anti-abuse, dashboard
09_admin_telegram.md      — Admin panel, Telegram bot, fraud detection
10_frontend_pwa.md        — Dark theme, GSAP animations, WebSocket client, PWA
11_deployment_launch.md   — VPS setup commands, launch checklist, monitoring
```

### /prompts/ — Phase-by-Phase Build Prompts
```
phase_01_server_setup.md  — VPS init, folder structure, Nginx, .env, PHP bootstrap
phase_02_database.md      — Schema creation, seeding, Model classes, DB helpers
phase_03_auth.md          — AuthController, SessionService, middleware, frontend pages
phase_04_wallet.md        — WalletService, WatchPay integration, withdrawal flow, wallet UI
phase_05_game_engine.md   — RNGService, BetService, GameFactory, XP system
phase_06_all_games.md     — All 16 game classes + frontend pages
phase_07_to_11.md         — Referral, Gen Z features, Admin panel, Telegram bot, Launch
```

---

## Agent Rules

1. **Never skip reading docs** before starting a phase prompt
2. **Server-side RNG only** — never let client determine game outcome
3. **DB transactions** for all wallet operations — no partial updates
4. **Prepared statements** for all SQL — no string concatenation in queries
5. **bcrypt cost 12** for all passwords — never md5/sha1 for passwords
6. **CSRF tokens** on all POST requests except webhooks (which use signature verification)
7. **One phase at a time** — complete and test before moving to next
8. **Test after each prompt** — verify the feature works before continuing
9. **Auth middleware** on all /api/ routes except: /api/auth/*, /api/webhooks/*, /api/games (list only)
10. **Balance type separation** — real and bonus never mixed in same transaction

---

## Environment Variables Required (fill before Phase 1)
```
WATCHPAY_API_KEY        — Get from WatchPay dashboard
WATCHPAY_SECRET         — Get from WatchPay dashboard
WATCHPAY_WEBHOOK_SECRET — Set in WatchPay webhook settings, same value here
TELEGRAM_BOT_TOKEN      — Create bot via @BotFather on Telegram
TELEGRAM_ADMIN_CHAT_ID  — Your admin group chat ID (send /start to @userinfobot)
VAPID_PUBLIC_KEY        — Generate: php artisan webpush:vapid (or manual)
VAPID_PRIVATE_KEY       — Same as above
```

---

## Quick Reference: API Endpoints Summary

### Auth
- POST /api/auth/register
- POST /api/auth/login
- POST /api/auth/logout
- GET  /api/auth/me
- GET  /api/auth/check-username?username=X

### Wallet
- GET  /api/wallet/balance
- POST /api/wallet/deposit
- POST /api/wallet/withdraw
- GET  /api/wallet/transactions

### Games
- GET  /api/games (list all)
- POST /api/games/{slug}/play (instant games)
- POST /api/games/{slug}/start (multi-step)
- POST /api/games/{slug}/action (multi-step)
- POST /api/games/{slug}/cashout (multi-step)
- GET  /api/games/{slug}/history
- GET  /api/games/color-predict/current-round
- GET  /api/games/fast-parity/current-round

### Features
- GET  /api/referral/dashboard
- GET  /api/leaderboard?period=daily|weekly|alltime
- GET  /api/quests/today
- POST /api/daily-reward/claim
- GET  /api/daily-reward/status
- POST /api/push/subscribe
- GET  /api/win-card/{betId}

### Webhooks (no auth, signature verified)
- POST /api/webhooks/watchpay
- POST /telegram/webhook

### Admin (admin.betvibe.com)
- GET  /admin/dashboard
- GET  /admin/users
- GET  /admin/users/{id}
- POST /admin/users/{id}/ban
- POST /admin/users/{id}/reset-password
- GET  /admin/withdrawals
- POST /admin/withdrawals/{id}/approve
- POST /admin/withdrawals/{id}/reject
- GET  /admin/games
- POST /admin/games/{slug}/config
- GET  /admin/fraud
- GET  /admin/audit
- GET  /admin/finance
