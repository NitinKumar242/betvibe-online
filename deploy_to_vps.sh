#!/bin/bash
# ═══════════════════════════════════════════════════════
# BetVibe — Deploy to VPS from GitHub
# Usage: chmod +x deploy_to_vps.sh && ./deploy_to_vps.sh
# ═══════════════════════════════════════════════════════

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }

# VPS Configuration
VPS_HOST="31.97.56.21"
VPS_USER="root"
GITHUB_REPO="https://github.com/NitinKumar242/betvibe-online.git"
PROJECT_DIR="/var/www/betvibe"

echo ""
echo "╔══════════════════════════════════════════════╗"
echo "║     BetVibe — Deploy to VPS from GitHub     ║"
echo "╚══════════════════════════════════════════════╝"
echo ""

# ─── 1. Test SSH Connection ───────────────────────
log "Testing SSH connection to ${VPS_HOST}..."
if ! ssh -o ConnectTimeout=10 -o StrictHostKeyChecking=no ${VPS_USER}@${VPS_HOST} "echo 'Connection successful'" 2>/dev/null; then
    err "Cannot connect to VPS. Please check your SSH connection."
fi
log "SSH connection successful."

# ─── 2. Install Git on VPS (if not installed) ─────
log "Checking Git installation on VPS..."
ssh ${VPS_USER}@${VPS_HOST} << 'ENDSSH'
if ! command -v git &>/dev/null; then
    echo "Installing Git..."
    apt update && apt install -y git
fi
ENDSSH
log "Git is available on VPS."

# ─── 3. Clone Repository ───────────────────────────
log "Cloning repository from GitHub..."
ssh ${VPS_USER}@${VPS_HOST} << ENDSSH
# Backup existing installation if exists
if [ -d "${PROJECT_DIR}" ]; then
    echo "Backing up existing installation..."
    mv ${PROJECT_DIR} ${PROJECT_DIR}.backup.$(date +%Y%m%d_%H%M%S)
fi

# Create project directory
mkdir -p ${PROJECT_DIR}

# Clone repository
cd ${PROJECT_DIR}
git clone ${GITHUB_REPO} .
ENDSSH
log "Repository cloned successfully."

# ─── 4. Run Setup Script ───────────────────────────
log "Running VPS setup script..."
ssh ${VPS_USER}@${VPS_HOST} << ENDSSH
cd ${PROJECT_DIR}
chmod +x deploy/setup_vps.sh
./deploy/setup_vps.sh
ENDSSH
log "VPS setup completed."

# ─── 5. Display Important Information ──────────────
echo ""
echo "╔══════════════════════════════════════════════╗"
echo "║              Deployment Complete!            ║"
echo "╚══════════════════════════════════════════════╝"
echo ""
log "Your BetVibe application has been deployed!"
echo ""
warn "IMPORTANT: Please check the VPS output above for:"
warn "  - Database password (SAVE THIS!)"
warn "  - APP_SECRET (SAVE THIS!)"
warn ""
log "Next steps:"
log "  1. Update your domain DNS to point to ${VPS_HOST}"
log "  2. Configure SSL with: certbot --nginx -d betsvibe.online -d www.betsvibe.online"
log "  3. Update .env with your payment gateway credentials"
log "  4. Set up Telegram bot webhook"
log ""
log "Access your application at: http://${VPS_HOST}"
echo ""
