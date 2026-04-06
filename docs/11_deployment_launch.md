# Deployment + Launch Checklist

## VPS Initial Setup Commands
```bash
# 1. Update system
apt update && apt upgrade -y

# 2. Install essentials
apt install -y nginx mysql-server php8.2 php8.2-fpm php8.2-mysql \
  php8.2-gd php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip \
  php8.2-bcmath supervisor certbot python3-certbot-nginx git ufw fail2ban

# 3. Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# 4. Setup MySQL
mysql_secure_installation
mysql -e "CREATE DATABASE betvibe_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER 'betvibe_user'@'localhost' IDENTIFIED BY 'STRONG_PASS';"
mysql -e "GRANT ALL ON betvibe_db.* TO 'betvibe_user'@'localhost';"

# 5. Setup firewall
ufw allow 22
ufw allow 80
ufw allow 443
ufw allow 8080
ufw enable

# 6. Clone project
git clone your_repo /var/www/betvibe
cd /var/www/betvibe
composer install --no-dev
cp .env.example .env
# Edit .env with real values

# 7. Run migrations
php artisan migrate  # or: mysql betvibe_db < db/schema.sql

# 8. Seed game config
php db/seed_games.php

# 9. Set permissions
chown -R www-data:www-data /var/www/betvibe
chmod -R 755 /var/www/betvibe
chmod -R 777 /var/www/betvibe/storage

# 10. Setup Nginx
cp deploy/nginx.conf /etc/nginx/sites-available/betvibe
ln -s /etc/nginx/sites-available/betvibe /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 11. SSL
certbot --nginx -d betvibe.com -d www.betvibe.com

# 12. Start WebSocket via Supervisor
cp deploy/supervisor.conf /etc/supervisor/conf.d/betvibe.conf
supervisorctl reread && supervisorctl update && supervisorctl start betvibe-ws

# 13. Setup crontab
crontab deploy/crontab.txt

# 14. Register Telegram webhook
curl "https://api.telegram.org/bot{TOKEN}/setWebhook?url=https://betvibe.com/telegram/webhook"
```

## Launch Checklist (Before Going Live)

### Security
- [ ] .env file not accessible via web (Nginx deny rule active)
- [ ] All passwords changed from defaults
- [ ] SSL certificate active (HTTPS everywhere)
- [ ] CSRF tokens on all POST forms
- [ ] Rate limiting on /api/auth/* endpoints
- [ ] SQL injection: all queries use prepared statements
- [ ] XSS: all output escaped with htmlspecialchars()
- [ ] Admin panel on separate subdomain with IP whitelist
- [ ] Telegram bot webhook verified (signature check working)

### Payments
- [ ] WatchPay API keys in .env (not hardcoded)
- [ ] Webhook signature verification working
- [ ] Test deposit flow end-to-end
- [ ] Test withdrawal flow end-to-end
- [ ] Idempotency check working (no double-credit on webhook retry)

### Games
- [ ] All 16 games tested with real bets
- [ ] RNG results are server-side only (network tab confirms)
- [ ] Win ratio approximately correct (test 100 rounds each game)
- [ ] Timer-based games (Color Predict, Fast Parity) cron running
- [ ] Crash WebSocket stable under load
- [ ] Multi-step games (Mines, Tower, HiLo) abandon timeout working

### Wallet
- [ ] Wagering requirement enforced
- [ ] Withdrawal cooldown working
- [ ] Bonus coins non-withdrawable confirmed
- [ ] Balance never goes negative

### Features
- [ ] Referral link working (register with ref → deposit → credit)
- [ ] Daily quests rotating at midnight
- [ ] Login rewards streak working
- [ ] Win share card generates correctly
- [ ] Live win feed showing on homepage
- [ ] Leaderboard updating in real-time
- [ ] Push notifications working (test on mobile)
- [ ] PWA installable on Android + iOS

### Admin
- [ ] Admin login working
- [ ] Game config changes apply immediately
- [ ] Withdrawal approve/reject working
- [ ] Password reset → Telegram delivery confirmed
- [ ] Audit log recording all admin actions
- [ ] Telegram bot commands working from admin group

### Performance
- [ ] Page load < 3 seconds on mobile
- [ ] Images compressed (WebP format)
- [ ] CSS/JS minified
- [ ] Gzip enabled in Nginx
- [ ] MySQL queries indexed (run EXPLAIN on slow queries)
- [ ] WebSocket handles 100+ concurrent connections

## Soft Launch Plan
1. Week 1: Internal testing only (admin + 5 test accounts)
2. Week 2: Invite 50 beta users (trusted friends)
3. Week 3: Referral program activated, viral sharing enabled
4. Week 4+: Full public launch + paid promotion

## Monitoring
```bash
# Watch error logs
tail -f /var/log/nginx/error.log
tail -f /var/www/betvibe/storage/logs/app.log

# Monitor WebSocket
supervisorctl status betvibe-ws

# MySQL slow queries
tail -f /var/log/mysql/mysql-slow.log

# Server resources
htop
df -h  # disk usage
```
