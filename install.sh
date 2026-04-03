#!/bin/bash
##===============================================================================
## COOLIFY CUSTOM FORK - INSTALL
##
## Usage:
##   git clone https://github.com/aatos-git-collab/coolify-custom.git
##   cd coolify-custom
##   bash install.sh
##
## For private repos:
##   GH_TOKEN=ghp_xxx bash install.sh
##===============================================================================

set -e

#=========================================
# REBRAND CONFIGURATION
#=========================================
FORK_REPO="${FORK_REPO:-aatos-git-collab/coolify-custom}"
FORK_BRANCH="${FORK_BRANCH:-master}"
#=========================================

FORK_URL="https://github.com/${FORK_REPO}.git"
GH_TOKEN="${GH_TOKEN:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

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
echo "  ${FORK_REPO}"
echo "=========================================="
echo ""

if [ $EUID != 0 ]; then
    error "Please run as root or with sudo"
    exit 1
fi

#-----------------------------------
# If not in cloned repo, clone it
#-----------------------------------
if [ ! -d "$SCRIPT_DIR/.git" ]; then
    info "Cloning fork..."
    
    if [ -n "$GH_TOKEN" ]; then
        CLONE_URL="https://${GH_TOKEN}@github.com/${FORK_REPO}.git"
    else
        CLONE_URL="$FORK_URL"
    fi
    
    TEMP_DIR="/tmp/coolify-install-$$"
    if git clone --depth 1 --branch "$FORK_BRANCH" "$CLONE_URL" "$TEMP_DIR" 2>&1; then
        SCRIPT_DIR="$TEMP_DIR"
        success "Cloned to $TEMP_DIR"
    else
        error "Failed to clone"
        exit 1
    fi
fi

#-----------------------------------
# Run install from scripts/
#-----------------------------------
info "Running scripts/install.sh from: $SCRIPT_DIR"
echo ""

cd "$SCRIPT_DIR"
bash scripts/install.sh
