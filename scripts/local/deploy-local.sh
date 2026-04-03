#!/bin/bash
## Deploy Local Changes to Running Coolify
##
## Purpose: Quickly sync local source changes to a running Coolify instance
##          WITHOUT building a new Docker image - for fast development/testing
##
## Usage: ./deploy-local.sh [--source /path/to/source] [--target /data/coolify]
##
## Requirements:
##   - Coolify must be running (docker ps shows coolify container)
##   - Source directory should be this repository
##   - Will overwrite files in target

set -e

# Configuration
SOURCE_DIR="${SOURCE_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
TARGET_DIR="${TARGET_DIR:-/data/coolify/source}"
CONTAINER_NAME="${CONTAINER_NAME:-coolify}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

info() { echo -e "${BLUE}[INFO]${NC} $1"; }
success() { echo -e "${GREEN}[PASS]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
fail() { echo -e "${RED}[FAIL]${NC} $1"; }

echo ""
echo "=========================================="
echo "  LOCAL DEPLOY TO RUNNING COOLIFY"
echo "=========================================="
echo ""
info "Source: $SOURCE_DIR"
info "Target: $TARGET_DIR"
info "Container: $CONTAINER_NAME"
echo ""

# Parse args
while [[ $# -gt 0 ]]; do
    case $1 in
        --source) SOURCE_DIR="$2"; shift 2 ;;
        --target) TARGET_DIR="$2"; shift 2 ;;
        --container) CONTAINER_NAME="$2"; shift 2 ;;
        *) shift ;;
    esac
done

# Validate source exists
if [ ! -d "$SOURCE_DIR" ]; then
    fail "Source directory not found: $SOURCE_DIR"
    exit 1
fi

# Validate Coolify is running
info "Checking Coolify container..."
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    fail "Coolify container '$CONTAINER_NAME' is not running"
    echo "Start Coolify first: docker compose up -d"
    exit 1
fi
success "Coolify container is running"

# Create target directory if needed
if [ ! -d "$TARGET_DIR" ]; then
    warn "Target directory doesn't exist: $TARGET_DIR"
    info "Creating..."
    mkdir -p "$TARGET_DIR"
fi

# Files/dirs to sync (order matters)
SYNC_ITEMS=(
    "app"
    "database"
    "routes"
    "resources/views"
    "bootstrap"
    "config"
    "scripts/custom"  # Our custom install scripts
    ".agents"         # AI skills
)

echo ""
info "=========================================="
info "STEP 1: SYNC FILES"
info "=========================================="
echo ""

for item in "${SYNC_ITEMS[@]}"; do
    SOURCE_PATH="$SOURCE_DIR/$item"
    TARGET_PATH="$TARGET_DIR/$item"
    
    if [ -e "$SOURCE_PATH" ]; then
        if [ -d "$SOURCE_PATH" ]; then
            info "Syncing directory: $item"
            rsync -a --delete "$SOURCE_PATH/" "$TARGET_PATH/"
        else
            info "Copying file: $item"
            cp -f "$SOURCE_PATH" "$TARGET_PATH"
        fi
    else
        warn "Skipping (not found): $item"
    fi
done

echo ""
info "=========================================="
info "STEP 2: RUN MIGRATIONS"
info "=========================================="
echo ""

if docker exec "$CONTAINER_NAME" php artisan migrate --force 2>/dev/null; then
    success "Migrations completed"
else
    warn "Migrations failed or no migrations to run"
fi

echo ""
info "=========================================="
info "STEP 3: CLEAR CACHES"
info "=========================================="
echo ""

docker exec "$CONTAINER_NAME" php artisan config:clear 2>/dev/null || warn "config:clear failed"
docker exec "$CONTAINER_NAME" php artisan view:clear 2>/dev/null || warn "view:clear failed"
docker exec "$CONTAINER_NAME" php artisan route:clear 2>/dev/null || warn "route:clear failed"
docker exec "$CONTAINER_NAME" php artisan cache:clear 2>/dev/null || warn "cache:clear failed"
success "Caches cleared"

echo ""
info "=========================================="
info "STEP 4: VERIFY DEPLOYMENT"
info "=========================================="
echo ""

# Check key files exist in container
VERIFY_FILES=(
    "app/Services/AiService.php"
    "app/Jobs/AiAutoFixJob.php"
    "app/Jobs/AiLogMonitorJob.php"
)

ALL_OK=true
for file in "${VERIFY_FILES[@]}"; do
    if docker exec "$CONTAINER_NAME" test -f "/var/www/html/$file" 2>/dev/null; then
        success "Verified: $file"
    else
        fail "Missing: $file"
        ALL_OK=false
    fi
done

echo ""
echo "=========================================="
echo "  DEPLOY COMPLETE"
echo "=========================================="
echo ""

if [ "$ALL_OK" = true ]; then
    success "All custom files deployed successfully!"
    echo ""
    info "To restart Coolify (for full reload):"
    echo "  docker restart $CONTAINER_NAME"
    echo ""
    info "To view logs:"
    echo "  docker logs -f $CONTAINER_NAME"
else
    warn "Some files failed verification"
fi
