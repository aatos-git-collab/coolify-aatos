#!/bin/bash
## Custom Coolify Fork - Installation Script
##
## This is the entry point for installing OUR custom Coolify fork.
## It clones from our GitHub fork instead of pulling from coollabsio.
##
## Usage:
##   curl -fsSL https://github.com/aatos-git-collab/coolify-custom/raw/master/install.sh | bash
##   # OR
##   bash <(curl -fsSL https://github.com/aatos-git-collab/coolify-custom/raw/master/install.sh)
##
## For PRIVATE repos, use token authentication:
##   GH_TOKEN=ghp_xxx curl -fsSL https://github.com/aatos-git-collab/coolify-custom/raw/master/install.sh | bash

set -e

# Our fork configuration
FORK_REPO="aatos-git-collab/coolify-custom"
FORK_BRANCH="master"
FORK_URL="https://github.com/${FORK_REPO}.git"

# Get token from env if available (for private repos)
GH_TOKEN="${GH_TOKEN:-}"

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

# Build the clone URL (with token if available)
if [ -n "$GH_TOKEN" ]; then
    CLONE_URL="https://${GH_TOKEN}@github.com/${FORK_REPO}.git"
    info "Using authenticated clone (token detected)"
else
    CLONE_URL="$FORK_URL"
fi

# Clone our fork to temp location
info "Cloning custom fork..."
TEMP_DIR="/tmp/coolify-fork-install-$$"

if git clone --depth 1 --branch "$FORK_BRANCH" "$CLONE_URL" "$TEMP_DIR" 2>/dev/null; then
    success "Cloned ${FORK_REPO}"
else
    error "Failed to clone ${FORK_REPO}"
    error "Make sure the repository exists and is accessible"
    exit 1
fi

# Run the install script from our cloned repo
info "Running install.sh from custom fork..."
bash "${TEMP_DIR}/scripts/install.sh" "$@"
INSTALL_RESULT=$?

# Cleanup
rm -rf "$TEMP_DIR"

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
