#!/bin/bash
##===============================================================================
## COOLIFY CUSTOM FORK INSTALLER
##
## Single source of truth for installing our custom Coolify.
## Everything comes from our fork - no external CDN dependencies.
##
## USAGE:
##   bash install.sh                    # Interactive (when cloned)
##   curl -fsSL <url>/install.sh | bash  # Remote install
##
## CONFIGURATION (change these for rebrand):
##   FORK_REPO  - GitHub repo (e.g., "organization/coolify-custom")
##   FORK_BRANCH - Branch to install from (default: master)
##
## For private repos:
##   GH_TOKEN=ghp_xxx bash install.sh
##===============================================================================

set -e

#=========================================
# REBRAND CONFIGURATION - CHANGE HERE
#=========================================
FORK_REPO="${FORK_REPO:-aatos-git-collab/coolify-custom}"
FORK_BRANCH="${FORK_BRANCH:-master}"
#=========================================

FORK_URL="https://github.com/${FORK_REPO}.git"
GH_TOKEN="${GH_TOKEN:-}"

# Determine source directory
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

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
echo "  COOLIFY CUSTOM FORK INSTALLER"
echo "  ${FORK_REPO}"
echo "=========================================="
echo ""

# Check if running as root
if [ $EUID != 0 ]; then
    error "Please run as root or with sudo"
    exit 1
fi

info "Installing from: https://github.com/${FORK_REPO}"
info "Branch: ${FORK_BRANCH}"
echo ""

#-----------------------------------
# Clone our fork if not in it
#-----------------------------------
if [ ! -d "${SOURCE_DIR}/.git" ] || [ ! -f "${SOURCE_DIR}/docker-compose.yml" ]; then
    warn "Not in cloned repo - will clone..."
    
    if [ -n "$GH_TOKEN" ]; then
        CLONE_URL="https://${GH_TOKEN}@github.com/${FORK_REPO}.git"
        info "Using authenticated access"
    else
        CLONE_URL="$FORK_URL"
    fi
    
    TEMP_DIR="/tmp/coolify-install-$$"
    info "Cloning fork..."
    
    if git clone --depth 1 --branch "$FORK_BRANCH" "$CLONE_URL" "$TEMP_DIR" 2>&1; then
        SOURCE_DIR="$TEMP_DIR"
        success "Clone complete"
    else
        error "Failed to clone ${FORK_REPO}"
        exit 1
    fi
    echo ""
fi

#-----------------------------------
# Prepare installation directory
#-----------------------------------
info "Preparing installation..."
mkdir -p /data/coolify/source

# Copy ALL files from our fork
cp "${SOURCE_DIR}/docker-compose.yml" /data/coolify/source/
cp "${SOURCE_DIR}/docker-compose.prod.yml" /data/coolify/source/
cp "${SOURCE_DIR}/.env.production" /data/coolify/source/
cp "${SOURCE_DIR}/versions.json" /data/coolify/source/

# Copy scripts
cp "${SOURCE_DIR}/scripts/install.sh" /data/coolify/source/
cp "${SOURCE_DIR}/scripts/upgrade.sh" /data/coolify/source/

# Copy custom scripts
mkdir -p /data/coolify/source/scripts/custom
cp "${SOURCE_DIR}/scripts/custom/"*.sh /data/coolify/source/scripts/custom/ 2>/dev/null || true
chmod +x /data/coolify/source/scripts/custom/*.sh 2>/dev/null || true

success "Files prepared in /data/coolify/source"
echo ""

#-----------------------------------
# Run the install
#-----------------------------------
info "Running Coolify installation..."
info "Log will be saved to /root/coolify-install.log"
echo ""

# Run install from our prepared source
# The install script will use /data/coolify/source as its CDN
CDN="/data/coolify/source" bash /data/coolify/source/scripts/install.sh 2>&1 | tee /root/coolify-install.log
INSTALL_RESULT=${PIPESTATUS[0]}

# Cleanup
if [ -n "$TEMP_DIR" ] && [ -d "$TEMP_DIR" ]; then
    rm -rf "$TEMP_DIR"
fi

echo ""
echo "=========================================="
if [ $INSTALL_RESULT -eq 0 ]; then
    success "INSTALLATION COMPLETE!"
    echo "=========================================="
    info "Your custom Coolify is ready!"
    info "Repo: https://github.com/${FORK_REPO}"
else
    error "INSTALLATION FAILED"
    echo "=========================================="
    info "Check log: /root/coolify-install.log"
fi

exit $INSTALL_RESULT
