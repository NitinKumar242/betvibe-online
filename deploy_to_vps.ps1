# ═══════════════════════════════════════════════════════
# BetVibe — Deploy to VPS from GitHub (PowerShell)
# Usage: .\deploy_to_vps.ps1
# ═══════════════════════════════════════════════════════

$ErrorActionPreference = "Stop"

# VPS Configuration
$VPS_HOST = "31.97.56.21"
$VPS_USER = "root"
$GITHUB_REPO = "https://github.com/NitinKumar242/betvibe-online.git"
$PROJECT_DIR = "/var/www/betvibe"

function Log-Message {
    param([string]$Message)
    Write-Host "[✓] $Message" -ForegroundColor Green
}

function Warn-Message {
    param([string]$Message)
    Write-Host "[!] $Message" -ForegroundColor Yellow
}

function Error-Message {
    param([string]$Message)
    Write-Host "[✗] $Message" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "╔══════════════════════════════════════════════╗"
Write-Host "║     BetVibe — Deploy to VPS from GitHub     ║"
Write-Host "╚══════════════════════════════════════════════╝"
Write-Host ""

# ─── 1. Test SSH Connection ───────────────────────
Log-Message "Testing SSH connection to ${VPS_HOST}..."
$testResult = ssh -o ConnectTimeout=10 -o StrictHostKeyChecking=no ${VPS_USER}@${VPS_HOST} "echo 'Connection successful'" 2>&1
if ($LASTEXITCODE -ne 0) {
    Error-Message "Cannot connect to VPS. Please check your SSH connection."
}
Log-Message "SSH connection successful."

# ─── 2. Install Git on VPS (if not installed) ─────
Log-Message "Checking Git installation on VPS..."
ssh ${VPS_USER}@${VPS_HOST} @"
if ! command -v git &>/dev/null; then
    echo "Installing Git..."
    apt update && apt install -y git
fi
"@
Log-Message "Git is available on VPS."

# ─── 3. Clone Repository ───────────────────────────
Log-Message "Cloning repository from GitHub..."
ssh ${VPS_USER}@${VPS_HOST} @"
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
"@
Log-Message "Repository cloned successfully."

# ─── 4. Run Setup Script ───────────────────────────
Log-Message "Running VPS setup script..."
ssh ${VPS_USER}@${VPS_HOST} @"
cd ${PROJECT_DIR}
chmod +x deploy/setup_vps.sh
./deploy/setup_vps.sh
"@
Log-Message "VPS setup completed."

# ─── 5. Display Important Information ──────────────
Write-Host ""
Write-Host "╔══════════════════════════════════════════════╗"
Write-Host "║              Deployment Complete!            ║"
Write-Host "╚══════════════════════════════════════════════╝"
Write-Host ""
Log-Message "Your BetVibe application has been deployed!"
Write-Host ""
Warn-Message "IMPORTANT: Please check the VPS output above for:"
Warn-Message "  - Database password (SAVE THIS!)"
Warn-Message "  - APP_SECRET (SAVE THIS!)"
Write-Host ""
Log-Message "Next steps:"
Log-Message "  1. Update your domain DNS to point to ${VPS_HOST}"
Log-Message "  2. Configure SSL with: certbot --nginx -d betsvibe.online -d www.betsvibe.online"
Log-Message "  3. Update .env with your payment gateway credentials"
Log-Message "  4. Set up Telegram bot webhook"
Write-Host ""
Log-Message "Access your application at: http://${VPS_HOST}"
Write-Host ""
