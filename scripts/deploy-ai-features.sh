#!/bin/bash

set -e

echo "=== Coolify AI Features Deployment Script ==="
echo "Source: /root/coolify"
echo "Target: /data/coolify"
echo ""

# Check if running as root or with docker access
if ! command -v docker &> /dev/null; then
    echo "Error: Docker is not available"
    exit 1
fi

# Check if target directory exists
if [ ! -d "/data/coolify/source" ]; then
    echo "Warning: /data/coolify/source does not exist, creating..."
    mkdir -p /data/coolify/source
fi

echo "Step 1: Syncing files..."
rsync -av --exclude='.git' --exclude='node_modules' --exclude='vendor' --exclude='storage' --exclude='.env' /root/coolify/ /data/coolify/source/

echo ""
echo "Step 2: Running database migrations..."
docker exec coolify php artisan migrate --force

echo ""
echo "Step 3: Clearing caches..."
docker exec coolify php artisan config:clear
docker exec coolify php artisan view:clear
docker exec coolify php artisan route:clear

echo ""
echo "Step 4: Publishing assets..."
docker exec coolify npm run build 2>/dev/null || echo "Skipping npm build (may not be needed)"

echo ""
echo "=== Deployment Complete ==="
echo ""
echo "New features deployed:"
echo "  - AI Auto-Debug on deployment failure"
echo "  - Swarm Auto-Scaling configuration"
echo "  - Swarm Load Balancer (Settings > Swarm LB)"
echo ""
echo "To view logs: docker logs -f coolify"