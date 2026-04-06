#!/bin/bash
# ═══════════════════════════════════════════════════════
# BetVibe — Complete VPS Setup Script
# Run on fresh Ubuntu 24.04 (Hostinger VPS)
# Usage: chmod +x setup_vps.sh && sudo ./setup_vps.sh
# ═══════════════════════════════════════════════════════

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }

echo ""
echo "╔══════════════════════════════════════════════╗"
echo "║        BetVibe VPS Setup — Ubuntu 24.04      ║"
echo "╚══════════════════════════════════════════════╝"
echo ""

# ─── 1. System Update ─────────────────────────────
log "Updating system packages..."
apt update && apt upgrade -y
log "System updated."

# ─── 2. Install Software ──────────────────────────
log "Installing Nginx, MySQL, PHP 8.2, and tools..."
apt install -y \
  nginx mysql-server \
  php8.2 php8.2-fpm php8.2-mysql php8.2-gd php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath \
  supervisor certbot python3-certbot-nginx \
  git ufw fail2ban
log "Software installed."

# ─── 3. Install Composer ──────────────────────────
log "Installing Composer..."
if ! command -v composer &>/dev/null; then
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer
  chmod +x /usr/local/bin/composer
fi
log "Composer installed: $(composer --version)"

# ─── 4. Configure UFW Firewall ────────────────────
log "Configuring UFW firewall..."
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw allow 8080/tcp  # WebSocket
echo "y" | ufw enable
ufw status
log "UFW configured."

# ─── 5. Configure Fail2ban ────────────────────────
log "Configuring Fail2ban..."
cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local 2>/dev/null || true
cat > /etc/fail2ban/jail.d/custom.conf << 'EOF'
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true
port = ssh
maxretry = 3
bantime = 24h

[nginx-http-auth]
enabled = true

[nginx-botsearch]
enabled = true
EOF
systemctl enable fail2ban
systemctl restart fail2ban
log "Fail2ban configured."

# ─── 6. MySQL Setup ──────────────────────────────
log "Setting up MySQL..."

# Generate a strong password
DB_PASS=$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 20)

mysql -e "CREATE DATABASE IF NOT EXISTS betvibe_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'betvibe_user'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON betvibe_db.* TO 'betvibe_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Secure MySQL
mysql -e "DELETE FROM mysql.user WHERE User='';"
mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
mysql -e "DROP DATABASE IF EXISTS test;"
mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
mysql -e "FLUSH PRIVILEGES;"

log "MySQL configured. DB password: ${DB_PASS}"
warn "⚠️  SAVE THIS PASSWORD — you'll need it for .env"

# ─── 7. Clone/Setup Project ──────────────────────
log "Setting up project directory..."
mkdir -p /var/www/betvibe
# If cloning from git, uncomment:
# git clone YOUR_REPO_URL /var/www/betvibe
# Otherwise, assumes files are already uploaded

cd /var/www/betvibe

if [ -f composer.json ]; then
  log "Installing Composer dependencies..."
  composer install --no-dev --optimize-autoloader
fi

# ─── 8. Environment File ─────────────────────────
if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
    log "Created .env from .env.example"
  fi
fi

# Update DB password in .env
if [ -f .env ]; then
  sed -i "s/DB_PASS=.*/DB_PASS=${DB_PASS}/" .env
  # Generate APP_SECRET
  APP_SECRET=$(openssl rand -hex 16)
  sed -i "s/APP_SECRET=.*/APP_SECRET=${APP_SECRET}/" .env
  log "Updated .env with DB password and APP_SECRET"
fi

# ─── 9. Run Database Schema ──────────────────────
if [ -f db/schema.sql ]; then
  log "Running database schema..."
  mysql -u betvibe_user -p"${DB_PASS}" betvibe_db < db/schema.sql
  log "Schema applied."
fi

if [ -f db/seed_games.php ]; then
  log "Seeding game config..."
  php db/seed_games.php
  log "Games seeded."
fi

# ─── 10. Set Permissions ─────────────────────────
log "Setting file permissions..."
chown -R www-data:www-data /var/www/betvibe
chmod -R 755 /var/www/betvibe
chmod -R 775 /var/www/betvibe/storage
chmod 600 /var/www/betvibe/.env
log "Permissions set."

# ─── 11. PHP-FPM Configuration ───────────────────
log "Configuring PHP-FPM..."
cat > /etc/php/8.2/fpm/pool.d/betvibe.conf << 'EOF'
[betvibe]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm-betvibe.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
EOF

# PHP.ini tweaks
cat >> /etc/php/8.2/fpm/conf.d/99-betvibe.ini << 'EOF'
max_execution_time = 30
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
EOF

systemctl restart php8.2-fpm
log "PHP-FPM configured."

# ─── 12. Nginx Setup ─────────────────────────────
log "Configuring Nginx..."
if [ -f deploy/nginx.conf ]; then
  cp deploy/nginx.conf /etc/nginx/sites-available/betvibe
else
  cat > /etc/nginx/sites-available/betvibe << 'NGINX'
server {
    listen 80;
    server_name betsvibe.online www.betsvibe.online;
    root /var/www/betvibe/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
    limit_req_zone $binary_remote_addr zone=api:10m rate=60r/m;
    limit_req_zone $binary_remote_addr zone=bets:10m rate=30r/m;

    # PHP handling
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 30;
    }

    # WebSocket proxy
    location /ws {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
    }

    # Block sensitive directories
    location ~* ^/(app|config|storage|vendor|cron|websocket|telegram|db|deploy)/ {
        deny all;
    }

    # Block dotfiles
    location ~ /\. {
        deny all;
    }

    # Rate limit auth endpoints
    location /api/auth/ {
        limit_req zone=login burst=5 nodelay;
        try_files $uri /index.php?$query_string;
    }

    location /api/games/ {
        limit_req zone=bets burst=10 nodelay;
        try_files $uri /index.php?$query_string;
    }

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml;
    gzip_min_length 256;

    # Static asset caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|webp|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
}
NGINX
fi

# Enable site
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/betvibe /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
log "Nginx configured and reloaded."

# ─── 13. Supervisor (WebSocket) ──────────────────
log "Configuring Supervisor for WebSocket server..."
cat > /etc/supervisor/conf.d/betvibe-ws.conf << 'EOF'
[program:betvibe-ws]
command=php /var/www/betvibe/websocket/server.php
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/betvibe-ws.log
stderr_logfile=/var/log/betvibe-ws.err.log
EOF

supervisorctl reread
supervisorctl update
supervisorctl start betvibe-ws || true
log "Supervisor configured."

# ─── 14. Crontab ─────────────────────────────────
log "Setting up cron jobs..."
if [ -f deploy/crontab.txt ]; then
  crontab -u www-data deploy/crontab.txt
else
  (crontab -u www-data -l 2>/dev/null; cat << 'CRON'
# BetVibe Cron Jobs
*/3 * * * * php /var/www/betvibe/cron/round_timer.php color_predict >> /var/www/betvibe/storage/logs/cron.log 2>&1
*/1 * * * * php /var/www/betvibe/cron/round_timer.php fast_parity >> /var/www/betvibe/storage/logs/cron.log 2>&1
0 18 * * * php /var/www/betvibe/cron/daily_quests.php >> /var/www/betvibe/storage/logs/cron.log 2>&1
0 18 * * 0 php /var/www/betvibe/cron/leaderboard.php >> /var/www/betvibe/storage/logs/cron.log 2>&1
0 17 * * * php /var/www/betvibe/cron/daily_summary.php >> /var/www/betvibe/storage/logs/cron.log 2>&1
*/30 * * * * php /var/www/betvibe/cron/auto_approve_withdrawals.php >> /var/www/betvibe/storage/logs/cron.log 2>&1
0 * * * * php /var/www/betvibe/cron/cleanup_sessions.php >> /var/www/betvibe/storage/logs/cron.log 2>&1
10 14 * * * php /var/www/betvibe/cron/lucky_hours_notify.php >> /var/www/betvibe/storage/logs/cron.log 2>&1
CRON
) | crontab -u www-data -
fi
log "Cron jobs configured."

# ─── 15. SSL (Let's Encrypt) ─────────────────────
warn "Run SSL setup manually after DNS is pointing to this server:"
warn "  certbot --nginx -d betsvibe.online -d www.betsvibe.online"

# ─── 16. Verification ────────────────────────────
log "Running setup verification..."
cd /var/www/betvibe
php db/verify_setup.php || true

echo ""
echo "╔══════════════════════════════════════════════╗"
echo "║          VPS Setup Complete! 🎉              ║"
echo "╠══════════════════════════════════════════════╣"
echo "║  Database Password: ${DB_PASS}              "
echo "║  App Secret: ${APP_SECRET:-check .env}      "
echo "║                                              ║"
echo "║  Next Steps:                                 ║"
echo "║  1. Update .env with real API keys           ║"
echo "║  2. Point DNS to this server IP              ║"
echo "║  3. Run certbot for SSL                      ║"
echo "║  4. Test: curl http://localhost/api/health   ║"
echo "╚══════════════════════════════════════════════╝"
echo ""
