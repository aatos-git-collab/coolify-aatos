#!/bin/bash
## Install Coolify from Custom Fork
##
## Purpose: Install Coolify using OUR custom fork's configurations and images
##          Instead of pulling from coollabsio CDN, we use our own hosted files
##
## Usage:
##   curl -fsSL https://raw.githubusercontent.com/aatos-git-collab/coolify-custom/master/scripts/install.sh | bash
##   # Or download this script and run locally
##
## Environment Variables:
##   FORK_CDN        - Base URL for our fork's CDN (default: raw.githubusercontent.com/aatos-git-collab/coolify-custom/master)
##   FORK_REGISTRY   - Docker registry for our custom images (default: ghcr.io/aatos-git-collab)
##   FORK_BRANCH     - Branch to install from (default: master)
##
## What this does:
##   1. Downloads install.sh from our fork
##   2. Patches it to use our CDN instead of coollabs CDN
##   3. Runs the install with our configurations
##   4. Ensures custom images are used

set -e

# Configuration
FORK_REPO="aatos-git-collab/coolify-custom"
FORK_BRANCH="${FORK_BRANCH:-master}"
FORK_RAW_BASE="https://raw.githubusercontent.com/${FORK_REPO}/${FORK_BRANCH}"
FORK_CDN="${FORK_CDN:-$FORK_RAW_BASE}"
FORK_REGISTRY="${FORK_REGISTRY:-ghcr.io/aatos-git-collab}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
success() { echo -e "${GREEN}[OK]${NC} $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; }

echo ""
echo "=========================================="
echo "  INSTALL FROM CUSTOM FORK"
echo "=========================================="
echo ""
info "Repository: $FORK_REPO"
info "Branch: $FORK_BRANCH"
info "Registry: $FORK_REGISTRY"
info "CDN Base: $FORK_CDN"
echo ""

# Check if we're root
if [ $EUID != 0 ]; then
    error "Please run as root or with sudo"
    exit 1
fi

# Download install.sh from our fork
info "Downloading install.sh from custom fork..."
INSTALL_SCRIPT="/tmp/coolify-install-custom.sh"

if curl -fsSL "$FORK_CDN/scripts/install.sh" -o "$INSTALL_SCRIPT"; then
    success "Downloaded install.sh"
else
    error "Failed to download install.sh from fork"
    error "URL: $FORK_CDN/scripts/install.sh"
    exit 1
fi

# Patch the CDN URL in install.sh
info "Patching install.sh to use custom fork CDN..."
sed -i "s|https://cdn.coollabs.io/coolify|${FORK_CDN}|g" "$INSTALL_SCRIPT"
sed -i "s|https://github.com/coollabsio/coolify|https://github.com/${FORK_REPO}|g" "$INSTALL_SCRIPT"

# Verify patches
if grep -q "$FORK_CDN" "$INSTALL_SCRIPT"; then
    success "CDN patched to use custom fork"
else
    warn "CDN may not have patched correctly"
fi

# Make executable
chmod +x "$INSTALL_SCRIPT"

# Also download and patch upgrade.sh
info "Downloading and patching upgrade.sh..."
mkdir -p /data/coolify/source
curl -fsSL "$FORK_CDN/scripts/upgrade.sh" -o /data/coolify/source/upgrade.sh 2>/dev/null || true
if [ -f /data/coolify/source/upgrade.sh ]; then
    sed -i "s|https://cdn.coollabs.io/coolify|${FORK_CDN}|g" /data/coolify/source/upgrade.sh
    chmod +x /data/coolify/source/upgrade.sh
    success "upgrade.sh patched"
fi

# Download docker-compose files from our fork
info "Downloading docker-compose files from custom fork..."
curl -fsSL "$FORK_CDN/docker-compose.yml" -o /data/coolify/source/docker-compose.yml 2>/dev/null && success "docker-compose.yml" || warn "Failed"
curl -fsSL "$FORK_CDN/docker-compose.prod.yml" -o /data/coolify/source/docker-compose.prod.yml 2>/dev/null && success "docker-compose.prod.yml" || warn "Failed"
curl -fsSL "$FORK_CDN/.env.production" -o /data/coolify/source/.env.production 2>/dev/null && success ".env.production" || warn "Failed"

# Check if we have custom Docker images built
info "Checking for custom Docker images..."
CUSTOM_IMAGE="${FORK_REGISTRY}/coolify-custom:latest"
if docker image inspect "$CUSTOM_IMAGE" >/dev/null 2>&1; then
    info "Custom image found: $CUSTOM_IMAGE"
    info "To use it, update your docker-compose.yml to use this image"
else
    warn "Custom image not found locally: $CUSTOM_IMAGE"
    info "To build: cd /root/AI-SmartPanel/coolify && make build"
    info "Then re-run this install script"
fi

echo ""
echo "=========================================="
echo "  CUSTOM FORK SETUP COMPLETE"
echo "=========================================="
echo ""
info "Next steps:"
echo "  1. Build custom images: make build"
echo "  2. Or run standard install: curl -fsSL $FORK_CDN/scripts/install.sh | bash"
echo "  3. Or run the downloaded install script directly: bash $INSTALL_SCRIPT"
echo ""
info "For auto-updates to use your fork, update .env:"
echo "  echo 'AUTOUPDATE_SOURCE=aatos-git-collab/coolify-custom' >> /data/coolify/source/.env"
echo ""
