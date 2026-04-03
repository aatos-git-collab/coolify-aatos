#!/bin/bash
## Custom Coolify Fork - Installation Script
##
## This is the entry point for installing OUR custom Coolify fork.
## It pulls configurations from our GitHub fork instead of coollabsio.
##
## Usage:
##   curl -fsSL https://raw.githubusercontent.com/aatos-git-collab/coolify-custom/master/install.sh | bash
##   # OR
##   bash <(curl -fsSL https://raw.githubusercontent.com/aatos-git-collab/coolify-custom/master/install.sh)
##
## This script:
##   1. Downloads the official install.sh from coollabsio
##   2. Patches it to use OUR fork for configuration files
##   3. Runs the install with OUR customizations

set -e

# Our fork configuration
FORK_REPO="aatos-git-collab/coolify-custom"
FORK_BRANCH="master"
FORK_RAW_BASE="https://raw.githubusercontent.com/${FORK_REPO}/${FORK_BRANCH}"

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

info "This will install Coolify from CUSTOM FORK:"
info "  Repository: https://github.com/${FORK_REPO}"
info "  Branch: ${FORK_BRANCH}"
info ""
info "Custom features:"
info "  - AI Auto-Fix Job"
info "  - AI Log Monitor Job"
info "  - AI Service integrations"
echo ""

# Download the official install.sh
info "Downloading install.sh..."
TEMP_INSTALL="/tmp/coolify-install-$(date +%s).sh"

if curl -fsSL "https://cdn.coollabs.io/coolify/install.sh" -o "$TEMP_INSTALL"; then
    success "Downloaded official install.sh"
else
    error "Failed to download install.sh"
    exit 1
fi

# Patch to use our fork CDN for configuration files
info "Patching to use custom fork configurations..."
sed -i "s|https://cdn.coollabs.io/coolify|${FORK_RAW_BASE}|g" "$TEMP_INSTALL"
sed -i "s|https://github.com/coollabsio/coolify|https://github.com/${FORK_REPO}|g" "$TEMP_INSTALL"

# Ensure custom scripts directory exists in our fork path
mkdir -p "/data/coolify/source"

# Download our custom scripts to be available during install
info "Downloading custom scripts from fork..."
curl -fsSL "${FORK_RAW_BASE}/scripts/custom/_loader.sh" -o "/data/coolify/source/custom/_loader.sh" 2>/dev/null && success "_loader.sh" || warn "custom/_loader.sh not found"
curl -fsSL "${FORK_RAW_BASE}/scripts/custom/01-ai-features.sh" -o "/data/coolify/source/custom/01-ai-features.sh" 2>/dev/null && success "01-ai-features.sh" || warn "01-ai-features.sh not found"

# Make install script executable
chmod +x "$TEMP_INSTALL"

# Run the patched install script
info "Starting Coolify installation..."
echo ""
echo "=========================================="
echo ""

# Execute with all passed arguments
bash "$TEMP_INSTALL" "$@"

INSTALL_RESULT=$?

# Cleanup
rm -f "$TEMP_INSTALL" 2>/dev/null || true

if [ $INSTALL_RESULT -eq 0 ]; then
    echo ""
    echo "=========================================="
    success "CUSTOM FORK INSTALLATION COMPLETE!"
    echo "=========================================="
    echo ""
    info "Your custom Coolify fork is now installed!"
    info "Check the docs: https://github.com/${FORK_REPO}"
else
    echo ""
    echo "=========================================="
    error "INSTALLATION FAILED"
    echo "=========================================="
fi

exit $INSTALL_RESULT
