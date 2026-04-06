# Phase 1 — VPS Setup + Project Skeleton

## Agent Instructions
Read these docs before starting:
- docs/00_project_overview.md
- docs/01_tech_stack.md

---

## Prompt 1.1 — VPS Initial Setup

```
You are setting up a fresh Ubuntu 24.04 VPS on Hostinger for a PHP gambling platform called BetVibe.

Execute the following setup steps in order:

1. Update and upgrade the system packages
2. Install: nginx, mysql-server, php8.2, php8.2-fpm, php8.2-mysql, php8.2-gd, php8.2-mbstring, php8.2-xml, php8.2-curl, php8.2-zip, php8.2-bcmath, supervisor, certbot, python3-certbot-nginx, git, ufw, fail2ban
3. Install Composer globally
4. Configure UFW firewall: allow ports 22, 80, 443, 8080 only, then enable
5. Configure Fail2ban with default settings
6. Run mysql_secure_installation (interactive)
7. Create MySQL database: betvibe_db (utf8mb4)
8. Create MySQL user: betvibe_user with strong password, grant all on betvibe_db

Output: Confirmation of each step with any output or errors shown.
```

---

## Prompt 1.2 — Project Folder Structure

```
Create the complete BetVibe project folder structure at /var/www/betvibe/

Required structure:
/var/www/betvibe/
├── public/
│   ├── index.php
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   └── sw.js
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Games/
│   ├── Services/
│   └── Middleware/
├── config/
├── websocket/
│   └── server.php
├── telegram/
│   └── webhook.php
├── cron/
│   ├── round_timer.php
│   ├── leaderboard.php
│   ├── daily_quests.php
│   └── daily_summary.php
├── storage/
│   ├── win_cards/
│   └── logs/
├── db/
│   ├── schema.sql
│   └── seed_games.php
├── deploy/
│   ├── nginx.conf
│   ├── supervisor.conf
│   └── crontab.txt
├── .env.example
├── .env
└── composer.json

Create all folders and placeholder files. Set ownership to www-data.
```

---

## Prompt 1.3 — Composer + Dependencies

```
Inside /var/www/betvibe/, create composer.json with these dependencies:
- cboden/ratchet: ^0.4 (WebSocket server)
- react/event-loop: ^1.3
- irazasyed/telegram-bot-sdk: ^3.0
- vlucas/phpdotenv: ^5.0

Run: composer install

Then create the autoload structure in composer.json:
"autoload": {
    "psr-4": {
        "App\\": "app/"
    }
}

Run: composer dump-autoload
```

---

## Prompt 1.4 — Nginx Configuration

```
Create /etc/nginx/sites-available/betvibe with this configuration:

Requirements:
- Listen on 80 and 443 (SSL placeholder, certbot will fill)
- Server name: betvibe.com www.betvibe.com
- Root: /var/www/betvibe/public
- PHP-FPM socket: /run/php/php8.2-fpm.sock
- Proxy /ws → localhost:8080 (WebSocket upgrade headers)
- Block direct access to: app/, config/, storage/, vendor/, cron/, websocket/, telegram/
- Enable gzip compression for css, js, json, html
- Cache static assets (images, css, js) for 30 days
- Rate limiting on /api/auth/ → 10 req/min per IP

Enable the site. Test nginx config. Reload nginx.
```

---

## Prompt 1.5 — Environment File

```
Create /var/www/betvibe/.env with all required variables.

Required variables (use placeholder values, admin will fill real ones):
- APP_NAME=BetVibe
- APP_URL=https://betvibe.com
- APP_ENV=production
- APP_SECRET=(generate random 32 char string)
- DB_HOST=127.0.0.1
- DB_PORT=3306
- DB_NAME=betvibe_db
- DB_USER=betvibe_user
- DB_PASS=(placeholder)
- WATCHPAY_API_KEY=(placeholder)
- WATCHPAY_SECRET=(placeholder)
- WATCHPAY_WEBHOOK_SECRET=(placeholder)
- TELEGRAM_BOT_TOKEN=(placeholder)
- TELEGRAM_ADMIN_CHAT_ID=(placeholder)
- TELEGRAM_BOT_USERNAME=BetVibeSupport_bot
- VAPID_PUBLIC_KEY=(placeholder)
- VAPID_PRIVATE_KEY=(placeholder)
- WS_PORT=8080

Also create .env.example with same keys but empty values (for git).
Add .env to .gitignore.
```

---

## Prompt 1.6 — PHP Core Bootstrap

```
Create /var/www/betvibe/app/Core/App.php — the application bootstrap class:

Requirements:
- Load .env using vlucas/phpdotenv
- Initialize DB connection (singleton pattern)
- Route requests based on URL path + HTTP method
- Handle JSON API responses
- Global error handler (log to storage/logs/error.log)
- CSRF token generation and validation for POST requests
- Session start with secure settings

Create /var/www/betvibe/app/Core/DB.php:
- PDO-based database class
- Prepared statement methods: query($sql, $params), first(), all()
- Transaction support: transaction(callable $fn)
- Connection pooling not needed (PHP-FPM handles this)

Create /var/www/betvibe/public/index.php:
- Bootstrap the App class
- Route to appropriate Controller
- Handle 404 with JSON response for /api/* routes

Validate: test /api/health endpoint returns {"status":"ok"}
```
