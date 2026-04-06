# BetVibe VPS Setup Script

This script automates the initial server setup for the BetVibe PHP gambling platform on a fresh Ubuntu 24.04 VPS.

## Prerequisites

- Fresh Ubuntu 24.04 VPS (Hostinger or any provider)
- Root access or sudo privileges
- SSH access to the server

## Quick Start

1. Upload the script to your VPS:
   ```bash
   scp scripts/setup_vps.sh root@your-vps-ip:/root/
   ```

2. SSH into your VPS:
   ```bash
   ssh root@your-vps-ip
   ```

3. Make the script executable:
   ```bash
   chmod +x setup_vps.sh
   ```

4. Run the script:
   ```bash
   sudo bash setup_vps.sh
   ```

## What the Script Does

### Step 1: System Update
- Updates package lists
- Upgrades installed packages
- Removes unnecessary packages

### Step 2: Package Installation
Installs the following packages:
- **Web Server**: nginx
- **Database**: mysql-server
- **PHP 8.2**: php8.2, php8.2-fpm, php8.2-mysql, php8.2-gd, php8.2-mbstring, php8.2-xml, php8.2-curl, php8.2-zip, php8.2-bcmath
- **Process Manager**: supervisor
- **SSL**: certbot, python3-certbot-nginx
- **Version Control**: git
- **Security**: ufw, fail2ban

### Step 3: Composer Installation
- Downloads and installs Composer globally
- Verifies installation integrity

### Step 4: UFW Firewall Configuration
- Resets firewall to default settings
- Allows only ports: 22 (SSH), 80 (HTTP), 443 (HTTPS), 8080 (Alternative HTTP)
- Enables the firewall

### Step 5: Fail2ban Configuration
- Configures default jail settings
- Sets up protection for SSH and Nginx
- Enables and starts the service

### Step 6: MySQL Secure Installation
- Runs `mysql_secure_installation` interactively
- **You will be prompted to:**
  - Set a root password (choose a strong one!)
  - Remove anonymous users
  - Disallow root login remotely
  - Remove test database
  - Reload privilege tables

### Step 7 & 8: Database and User Creation
- Creates database `betvibe_db` with utf8mb4 character set
- Creates MySQL user `betvibe_user`
- **You will be prompted to enter:**
  - MySQL root password
  - Password for betvibe_user (must be strong)

### Additional Configurations

#### PHP-FPM Configuration
- Sets memory limit to 256MB
- Sets upload_max_filesize to 20MB
- Sets post_max_size to 20MB
- Sets max_execution_time to 300 seconds
- Sets timezone to UTC

#### Nginx Configuration
- Creates a basic Nginx configuration for BetVibe
- Configures PHP-FPM integration
- Adds security headers
- Sets up proper logging

#### Project Directory
- Creates `/var/www/betvibe` directory
- Sets proper permissions (www-data:www-data)

## Post-Setup Steps

After the script completes, you need to:

1. **Deploy your application:**
   ```bash
   cd /var/www/betvibe
   git clone <your-repository-url> .
   composer install
   ```

2. **Configure environment:**
   ```bash
   cp .env.example .env
   nano .env  # Edit with your database credentials
   ```

3. **Run database migrations:**
   ```bash
   php artisan migrate
   ```

4. **Obtain SSL certificate:**
   ```bash
   certbot --nginx -d yourdomain.com
   ```

5. **Configure Supervisor** (if using queue workers):
   ```bash
   nano /etc/supervisor/conf.d/betvibe-worker.conf
   supervisorctl reread
   supervisorctl update
   supervisorctl start betvibe-worker:*
   ```

## Security Notes

⚠️ **IMPORTANT: Save your credentials securely!**
- MySQL root password
- BetVibe user password

The script configures:
- UFW firewall allowing only necessary ports
- Fail2ban for brute-force protection
- Security headers in Nginx
- Proper file permissions

## Troubleshooting

### Script fails during package installation
```bash
# Try updating repositories manually
apt-get update
apt-get upgrade
```

### MySQL connection issues
```bash
# Check MySQL status
systemctl status mysql

# Check MySQL logs
tail -f /var/log/mysql/error.log
```

### Nginx not starting
```bash
# Test configuration
nginx -t

# Check logs
tail -f /var/log/nginx/error.log
```

### PHP-FPM issues
```bash
# Check status
systemctl status php8.2-fpm

# Check logs
tail -f /var/log/php8.2-fpm.log
```

## Firewall Management

### Check firewall status:
```bash
ufw status verbose
```

### Allow additional ports:
```bash
ufw allow <port>/tcp
```

### Disable firewall (not recommended):
```bash
ufw disable
```

## Service Management

### Restart services:
```bash
systemctl restart nginx
systemctl restart php8.2-fpm
systemctl restart mysql
systemctl restart fail2ban
```

### Check service status:
```bash
systemctl status nginx
systemctl status php8.2-fpm
systemctl status mysql
systemctl status fail2ban
```

## Useful Commands

### View Nginx access logs:
```bash
tail -f /var/log/nginx/betvibe_access.log
```

### View Nginx error logs:
```bash
tail -f /var/log/nginx/betvibe_error.log
```

### View Fail2ban status:
```bash
fail2ban-client status
fail2ban-client status sshd
```

### Unban an IP from Fail2ban:
```bash
fail2ban-client set sshd unbanip <IP_ADDRESS>
```

## Script Location

The setup script is located at: [`scripts/setup_vps.sh`](scripts/setup_vps.sh)

## Support

For issues or questions about the BetVibe platform, refer to the project documentation in the `docs/` directory.
