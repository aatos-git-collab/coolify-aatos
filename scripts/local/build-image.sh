#!/bin/bash
## Build Custom Docker Image
##
## Purpose: Build a custom Docker image from local source
##          This creates an image with AI features pre-baked in
##
## Usage: ./build-image.sh [--tag VERSION] [--registry REGISTRY]
##
## Examples:
##   ./build-image.sh                      # Build with auto tag
##   ./build-image.sh --tag v4.0.0-custom   # Specific tag
##   ./build-image.sh --registry ghcr.io/myorg  # Custom registry

set -e

# Configuration
SOURCE_DIR="${SOURCE_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
DEFAULT_REGISTRY="${DEFAULT_REGISTRY:-ghcr.io/aatos-git-collab}"
IMAGE_NAME="${IMAGE_NAME:-coolify-custom}"
CONTAINER_NAME="${CONTAINER_NAME:-coolify}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info() { echo -e "${BLUE}[INFO]${NC} $1"; }
success() { echo -e "${GREEN}[PASS]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
fail() { echo -e "${RED}[FAIL]${NC} $1"; }

# Parse args
VERSION=""
REGISTRY="$DEFAULT_REGISTRY"
PUSH=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --tag) VERSION="$2"; shift 2 ;;
        --registry) REGISTRY="$2"; shift 2 ;;
        --push) PUSH=true; shift ;;
        *) shift ;;
    esac
done

# Auto-generate version if not provided
if [ -z "$VERSION" ]; then
    # Get version from git tag or use date
    VERSION=$(git -C "$SOURCE_DIR" describe --tags 2>/dev/null || echo "dev-$(date +%Y%m%d-%H%M%S)")
fi

FULL_IMAGE="${REGISTRY}/${IMAGE_NAME}:${VERSION}"
LATEST_IMAGE="${REGISTRY}/${IMAGE_NAME}:latest"

echo ""
echo "=========================================="
echo "  BUILD CUSTOM COOLIFY IMAGE"
echo "=========================================="
echo ""
info "Source: $SOURCE_DIR"
info "Registry: $REGISTRY"
info "Image: $IMAGE_NAME"
info "Version: $VERSION"
info "Full Image: $FULL_IMAGE"
echo ""

# Validate source
if [ ! -d "$SOURCE_DIR/.git" ]; then
    fail "Not a git repository: $SOURCE_DIR"
    exit 1
fi

# Check Docker
info "Checking Docker..."
if ! docker info >/dev/null 2>&1; then
    fail "Docker is not running"
    exit 1
fi
success "Docker is running"

# Get commit hash for label
COMMIT_SHA=$(git -C "$SOURCE_DIR" rev-parse --short HEAD 2>/dev/null || echo "unknown")

# Find Dockerfile
DOCKERFILE_PATH=""
for df in "$SOURCE_DIR/docker/Dockerfile" "$SOURCE_DIR/Dockerfile" "$SOURCE_DIR/docker/production/Dockerfile"; do
    if [ -f "$df" ]; then
        DOCKERFILE_PATH="$df"
        break
    fi
done

if [ -z "$DOCKERFILE_PATH" ]; then
    warn "No Dockerfile found in standard locations"
    info "Looking for Dockerfile in docker/ directory..."
    find "$SOURCE_DIR/docker" -name "Dockerfile*" 2>/dev/null | head -5
    fail "Cannot build - Dockerfile required"
    exit 1
fi

info "Using Dockerfile: $DOCKERFILE_PATH"

echo ""
info "=========================================="
info "BUILDING IMAGE"
info "=========================================="
echo ""

# Build the image
docker build \
    -t "$FULL_IMAGE" \
    -t "$LATEST_IMAGE" \
    --label "org.opencontainers.image.source=https://github.com/aatos-git-collab/coolify" \
    --label "org.opencontainers.image.revision=$COMMIT_SHA" \
    --label "org.opencontainers.image.version=$VERSION" \
    -f "$DOCKERFILE_PATH" \
    "$SOURCE_DIR"

success "Image built: $FULL_IMAGE"

echo ""
info "=========================================="
info "IMAGE DETAILS"
info "=========================================="
echo ""

docker image inspect "$FULL_IMAGE" --format='{{.Size}}' | while read size; do
    info "Size: $(numfmt --to=iec "$size" 2>/dev/null || echo "$size")"
done

docker images "$FULL_IMAGE" --format "{{.Repository}}:{{.Tag}} | {{.Size}} | {{.CreatedSince}}"

echo ""

# Push if requested
if [ "$PUSH" = true ]; then
    echo ""
    info "=========================================="
    info "PUSHING TO REGISTRY"
    info "=========================================="
    echo ""
    
    # Login prompt if needed
    info "You may need to login first:"
    echo "  docker login $REGISTRY"
    echo ""
    
    if docker push "$FULL_IMAGE"; then
        success "Pushed: $FULL_IMAGE"
    else
        fail "Push failed"
        exit 1
    fi
    
    if docker push "$LATEST_IMAGE"; then
        success "Pushed: $LATEST_IMAGE"
    else
        warn "Push failed: $LATEST_IMAGE"
    fi
else
    info "To push image, run with --push flag:"
    echo "  $0 --push"
fi

echo ""
echo "=========================================="
echo "  BUILD COMPLETE"
echo "=========================================="
echo ""
success "Image: $FULL_IMAGE"
success "To use this image, update your docker-compose.yml:"
echo ""
echo "  image: ${FULL_IMAGE}"
echo "  # or"
echo "  image: ${LATEST_IMAGE}"
echo ""
