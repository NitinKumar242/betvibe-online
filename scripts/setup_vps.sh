#!/bin/bash

################################################################################
# BetVibe VPS Setup Script for Ubuntu 24.04
# This script sets up a fresh Ubuntu 24.04 VPS for the BetVibe PHP gambling platform
#
# Usage: sudo bash setup_vps.sh
#
# IMPORTANT: Run this script as root or with sudo privileges
################################################################################

set -e  # Exit on any error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   error "This script must be run as root or with sudo privileges"
   exit 1
fi

log "Starting BetVibe VPS Setup for Ubuntu 24.04..."

################################################################################
# STEP 1: Update and upgrade system packages
################################################################################
log "Step 1: Updating and upgrading system packages..."
apt-get update -y
apt-get upgrade -y
apt-get dist-upgrade -y
apt-get autoremove -y
apt-get autoclean -y
log "Step 1 completed: System packages updated and upgraded"

################################################################################
# STEP 2: Install required packages
################################################################################
log "Step 2: Installing required packages..."

# Add PHP 8.2 repository
log "Adding PHP 8.2 repository..."
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update -y

# Install all required packages
log "Installing nginx, MySQL, PHP 8.2 and extensions..."
apt-get install -y \
    nginx \
    mysql-server \
    php8.2 \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-gd \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-zip \
    php8.2-bcmath \
    supervisor \
    certbot \
    python3-certbot-nginx \
    git \
    ufw \
    fail2ban

log "Step 2 completed: All packages installed successfully"

################################################################################
# STEP 3: Install Composer globally
################################################################################
log "Step 3: Installing Composer globally..."

# Download and install Composer
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    error "Invalid Composer installer checksum"
    rm composer-setup.php
    exit 1
fi

php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# Verify Composer installation
if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer --version | cut -d' ' -f3)
    log "Step 3 completed: Composer $COMPOSER_VERSION installed globally"
else
    error "Composer installation failed"
    exit 1
fi

################################################################################
# STEP 4: Configure UFW firewall
################################################################################
log "Step 4: Configuring UFW firewall..."

# Reset UFW to default settings
ufw --force reset

# Set default policies
ufw default deny incoming
ufw default allow outgoing

# Allow specific ports
ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw allow 8080/tcp comment 'Alternative HTTP'

# Enable UFW
ufw --force enable

# Show UFW status
ufw status verbose

log "Step 4 completed: UFW firewall configured and enabled"

################################################################################
# STEP 5: Configure Fail2ban
################################################################################
log "Step 5: Configuring Fail2ban..."

# Create Fail2ban local configuration
cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
# Ban hosts for one hour:
bantime = 3600
# A host is banned if it has generated "maxretry" within the last "findtime":
findtime = 600
maxretry = 5
# Send emails to the system administrator
destemail = root@localhost
sendername = Fail2Ban
sender = fail2ban@localhost
# Use system mail command
action = %(action_)s

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
port = http,https
logpath = /var/log/nginx/error.log

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 10
EOF

# Restart Fail2ban
systemctl restart fail2ban
systemctl enable fail2ban

# Show Fail2ban status
fail2ban-client status

log "Step 5 completed: Fail2ban configured with default settings"

################################################################################
# STEP 6: Run mysql_secure_installation
################################################################################
log "Step 6: Running mysql_secure_installation..."
warning "This step requires interactive input. Please follow the prompts."
warning "Recommended answers:"
warning "  - Set root password: YES (choose a strong password)"
warning "  - Remove anonymous users: YES"
warning "  - Disallow root login remotely: YES"
warning "  - Remove test database: YES"
warning "  - Reload privilege tables: YES"

mysql_secure_installation

log "Step 6 completed: mysql_secure_installation finished"

################################################################################
# STEP 7 & 8: Create MySQL database and user
################################################################################
log "Step 7 & 8: Creating MySQL database and user..."

# Prompt for database credentials
read -sp "Enter MySQL root password: " MYSQL_ROOT_PASSWORD
echo
read -sp "Enter password for betvibe_user (must be strong): " BETVIBE_USER_PASSWORD
echo

# Create database and user
mysql -u root -p"$MYSQL_ROOT_PASSWORD" << EOF
-- Create database with utf8mb4 character set
CREATE DATABASE IF NOT EXISTS betvibe_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user and grant privileges
CREATE USER IF NOT EXISTS 'betvibe_user'@'localhost' IDENTIFIED BY '$BETVIBE_USER_PASSWORD';
GRANT ALL PRIVILEGES ON betvibe_db.* TO 'betvibe_user'@'localhost';
FLUSH PRIVILEGES;

-- Display created database and user
SELECT 'Database created: betvibe_db' AS Status;
SELECT 'User created: betvibe_user' AS Status;
EOF

log "Step 7 & 8 completed: MySQL database 'betvibe_db' and user 'betvibe_user' created"

################################################################################
# Additional: Configure PHP-FPM for Nginx
################################################################################
log "Additional: Configuring PHP-FPM for Nginx..."

# Backup original php.ini
cp /etc/php/8.2/fpm/php.ini /etc/php/8.2/fpm/php.ini.backup

# Update PHP settings for production
sed -i 's/memory_limit = 128M/memory_limit = 256M/' /etc/php/8.2/fpm/php.ini
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 20M/' /etc/php/8.2/fpm/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 20M/' /etc/php/8.2/fpm/php.ini
sed -i 's/max_execution_time = 30/max_execution_time = 300/' /etc/php/8.2/fpm/php.ini
sed -i 's/;date.timezone =/date.timezone = UTC/' /etc/php/8.2/fpm/php.ini

# Restart PHP-FPM
systemctl restart php8.2-fpm
systemctl enable php8.2-fpm

log "PHP-FPM configured and restarted"

################################################################################
# Additional: Configure Nginx
################################################################################
log "Additional: Configuring Nginx..."

# Create a basic Nginx configuration for BetVibe
cat > /etc/nginx/sites-available/betvibe << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name _;

    root /var/www/betvibe/public;
    index index.php index.html index.htm;

    # Logging
    access_log /var/log/nginx/betvibe_access.log;
    error_log /var/log/nginx/betvibe_error.log;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # PHP-FPM configuration
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Deny access to sensitive files
    location ~ /\.(?:git|svn|hg|bzr|env) {
        deny all;
    }
}
EOF

# Enable the site
ln -sf /etc/nginx/sites-available/betvibe /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test Nginx configuration
nginx -t

# Restart Nginx
systemctl restart nginx
systemctl enable nginx

log "Nginx configured and restarted"

################################################################################
# Additional: Create project directory
################################################################################
log "Additional: Creating project directory..."

mkdir -p /var/www/betvibe
chown -R www-data:www-data /var/www/betvibe
chmod -R 755 /var/www/betvibe

log "Project directory created at /var/www/betvibe"

################################################################################
# Display installation summary
################################################################################
log "=========================================="
log "BetVibe VPS Setup Completed Successfully!"
log "=========================================="
echo ""
info "Installed Components:"
echo "  - Nginx Web Server"
echo "  - MySQL Server"
echo "  - PHP 8.2 with extensions: mysql, gd, mbstring, xml, curl, zip, bcmath"
echo "  - Composer"
echo "  - Supervisor"
echo "  - Certbot (for SSL certificates)"
echo "  - Git"
echo "  - UFW Firewall (ports: 22, 80, 443, 8080)"
echo "  - Fail2ban"
echo ""
info "Database Information:"
echo "  - Database Name: betvibe_db"
echo "  - Database User: betvibe_user"
echo "  - Character Set: utf8mb4"
echo ""
warning "IMPORTANT: Save your database credentials securely!"
warning "  - MySQL Root Password: [YOU ENTERED THIS]"
warning "  - BetVibe User Password: [YOU ENTERED THIS]"
echo ""
info "Next Steps:"
echo "  1. Clone your BetVibe repository to /var/www/betvibe"
echo "  2. Run 'composer install' in the project directory"
echo "  3. Configure .env file with database credentials"
echo "  4. Run database migrations"
echo "  5. Obtain SSL certificate: certbot --nginx -d yourdomain.com"
echo "  6. Configure Supervisor for queue workers (if needed)"
echo ""
log "Setup script finished at $(date)"
