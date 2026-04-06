#!/bin/bash

# Nginx Configuration Setup Script for BetVibe
# This script deploys the nginx configuration and enables the site

set -e

echo "=== BetVibe Nginx Configuration Setup ==="

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (use sudo)"
    exit 1
fi

# Define paths
NGINX_SITES_AVAILABLE="/etc/nginx/sites-available"
NGINX_SITES_ENABLED="/etc/nginx/sites-enabled"
CONFIG_FILE="betvibe"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Copy configuration to sites-available
echo "Copying configuration to $NGINX_SITES_AVAILABLE/$CONFIG_FILE..."
cp "$SCRIPT_DIR/nginx-betvibe.conf" "$NGINX_SITES_AVAILABLE/$CONFIG_FILE"

# Create symbolic link to enable the site
echo "Enabling site by creating symlink..."
ln -sf "$NGINX_SITES_AVAILABLE/$CONFIG_FILE" "$NGINX_SITES_ENABLED/$CONFIG_FILE"

# Remove default site if exists (optional)
if [ -L "$NGINX_SITES_ENABLED/default" ]; then
    echo "Removing default site..."
    rm "$NGINX_SITES_ENABLED/default"
fi

# Test nginx configuration
echo "Testing Nginx configuration..."
if nginx -t; then
    echo "âœ“ Nginx configuration test passed!"
else
    echo "âœ— Nginx configuration test failed!"
    exit 1
fi

# Reload nginx
echo "Reloading Nginx..."
if systemctl reload nginx; then
    echo "âœ“ Nginx reloaded successfully!"
else
    echo "âœ— Failed to reload Nginx!"
    exit 1
fi

echo ""
echo "=== Setup Complete ==="
echo "Nginx configuration for betsvibe.online has been deployed and enabled."
echo ""
echo "Next steps:"
echo "1. Ensure /var/www/betvibe/public exists with your application files"
echo "2. Run certbot to obtain SSL certificates:"
echo "   sudo certbot --nginx -d betsvibe.online -d www.betsvibe.online"
echo "3. Ensure PHP-FPM is running: sudo systemctl status php8.2-fpm"
echo "4. Ensure WebSocket server is running on port 8080"
