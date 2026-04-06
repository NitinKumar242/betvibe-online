# Tech Stack + Server Configuration

## Server Specs (Hostinger VPS KVM)
- OS: Ubuntu 24.04 LTS
- CPU: 2 vCPU cores
- RAM: 8 GB
- Storage: 100 GB NVMe SSD
- Bandwidth: 8 TB/month
- IP: Static (assigned by Hostinger)

## Software Stack

### Web Server — Nginx
```nginx
server {
    listen 80;
    listen 443 ssl;
    server_name betvibe.com www.betvibe.com;
    root /var/www/betvibe/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Block direct access to app/ config/ storage/
    location ~* ^/(app|config|storage|vendor|cron|websocket|telegram)/ {
        deny all;
    }
}
```

### PHP Configuration (php.ini tweaks)
```ini
max_execution_time = 30
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

### MySQL Configuration
- Engine: InnoDB for all tables
- Charset: utf8mb4
- Collation: utf8mb4_unicode_ci
- Max connections: 200
- innodb_buffer_pool_size = 2G (25% of RAM)

### PHP-FPM Pool
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
```

## Composer Dependencies
```json
{
  "require": {
    "cboden/ratchet": "^0.4",
    "react/event-loop": "^1.3",
    "irazasyed/telegram-bot-sdk": "^3.0",
    "vlucas/phpdotenv": "^5.0",
    "phpmailer/phpmailer": "^6.0"
  }
}
```

## Environment Variables (.env)
```env
APP_NAME=BetVibe
APP_URL=https://betvibe.com
APP_ENV=production
APP_SECRET=RANDOM_32_CHAR_SECRET

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=betvibe_db
DB_USER=betvibe_user
DB_PASS=STRONG_PASSWORD

WATCHPAY_API_KEY=your_watchpay_key
WATCHPAY_SECRET=your_watchpay_secret
WATCHPAY_WEBHOOK_SECRET=your_webhook_secret

TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_ADMIN_CHAT_ID=your_admin_group_id
TELEGRAM_BOT_USERNAME=BetVibeSupport_bot

VAPID_PUBLIC_KEY=your_vapid_public
VAPID_PRIVATE_KEY=your_vapid_private

WS_PORT=8080
```

## Cron Jobs (crontab)
```cron
# Color Predict rounds (every 3 min)
*/3 * * * * php /var/www/betvibe/cron/round_timer.php color_predict

# Fast Parity rounds (every 1 min)
*/1 * * * * php /var/www/betvibe/cron/round_timer.php fast_parity

# Daily quests rotate at midnight
0 0 * * * php /var/www/betvibe/cron/daily_quests.php

# Weekly leaderboard reset + reward (Sunday midnight)
0 0 * * 0 php /var/www/betvibe/cron/leaderboard.php

# Daily revenue summary to Telegram (11pm NPT)
0 17 * * * php /var/www/betvibe/cron/daily_summary.php
```

## WebSocket Server (Ratchet)
- Runs on port 8080
- Nginx proxies /ws → localhost:8080
- Supervisor keeps it alive:
```ini
[program:betvibe-ws]
command=php /var/www/betvibe/websocket/server.php
autostart=true
autorestart=true
stderr_logfile=/var/log/betvibe-ws.err.log
```

## SSL (Let's Encrypt)
```bash
certbot --nginx -d betvibe.com -d www.betvibe.com
```
Auto-renewal via certbot timer.

## Security Hardening
- UFW firewall: allow 22, 80, 443, 8080 only
- Fail2ban: block IPs with 10+ failed SSH attempts
- MySQL: bind to 127.0.0.1 only (no external access)
- All passwords: bcrypt cost factor 12
- CSRF: double-submit cookie pattern on all POST
- Rate limiting: Nginx limit_req_zone on /api/ endpoints
