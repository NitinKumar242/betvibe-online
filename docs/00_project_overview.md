# BetVibe — Project Overview

## Project Summary
BetVibe is a Gen Z-focused online gambling platform for the Nepal market. It features 16 casino-style games, a real-money wallet (WatchPay), referral system, and viral retention mechanics. Built on PHP 8.2 + MySQL + Nginx on Hostinger VPS.

## Core Goals
- 16 games with server-side RNG (no external API)
- ~20% average win ratio across all games (house edge built into payout math)
- Gen Z UX: dark theme, Hinglish UI, streaks, share cards, leaderboards
- Real money: NPR (Nepalese Rupee) via WatchPay
- Viral growth: referral system (deposit-triggered), win share cards
- Admin full control: win ratios, user management, finance dashboard
- Password recovery via Telegram bot (no OTP, no email link)

## Target Users
- Age 18–30, Nepal-based
- Mobile-first (PWA installable)
- Hinglish/Nepali language mix
- Social media active (WhatsApp, Instagram, TikTok)

## Tech Stack
| Layer | Technology |
|---|---|
| Backend | PHP 8.2 |
| Database | MySQL 8.0 |
| Web Server | Nginx + PHP-FPM |
| Real-time | Ratchet WebSocket (PHP) |
| Frontend | Vanilla JS + GSAP animations |
| Payments | WatchPay API |
| Notifications | Web Push API (PWA) |
| Bot | Telegram Bot API (PHP) |
| Image Gen | PHP GD Library |
| Hosting | Hostinger VPS KVM (2 vCPU / 8GB RAM / 100GB NVMe) |

## Folder Structure (Final Project)
```
/var/www/betvibe/
├── public/              # Web root (Nginx points here)
│   ├── index.php
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   └── sw.js            # Service worker for PWA
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Games/           # One class per game
│   ├── Services/
│   │   ├── RNGService.php
│   │   ├── WalletService.php
│   │   ├── ReferralService.php
│   │   └── TelegramService.php
│   └── Middleware/
├── config/
│   ├── database.php
│   ├── app.php
│   └── games.php        # Win ratios, limits per game
├── websocket/
│   └── server.php       # Ratchet WS server
├── telegram/
│   └── webhook.php      # Telegram bot webhook
├── cron/
│   ├── round_timer.php  # Color Predict / Fast Parity rounds
│   ├── leaderboard.php  # Weekly reset + reward
│   └── daily_quests.php # Rotate quests at midnight
├── storage/
│   ├── win_cards/       # Generated PNG win cards
│   └── logs/
└── vendor/              # Composer dependencies
```

## Key Business Rules
1. Real Balance = deposited money, withdrawable after 1x wagering requirement
2. Bonus Coins = earned via quests/referral/daily spin, playable only, never withdrawable
3. Referral credit triggers ONLY on referred user's first deposit (min NPR 200)
4. All game results generated server-side — client never determines outcome
5. Admin can adjust win ratio per game from admin panel (stored in config DB table)
6. Withdrawals under NPR 1000 = auto-approve after 2hr cooldown; above = manual review

## .md File Reading Order for Agent
Read files in this order before coding each phase:
00 → 01 → 02 → then phase-specific docs → then prompts/phase_XX.md
