#!/bin/bash

# Simple deploy script - copies files and runs migrations
# Usage: ./deploy-simple.sh

set -e

SOURCE="/root/coolify"
TARGET="/data/coolify/source"

echo "=== Coolify Deployment ==="

# Check Coolify container
if ! docker ps --format '{{.Names}}' | grep -q "^coolify$"; then
    echo "Error: Coolify container not running"
    exit 1
fi

# Copy key files (app, config, migrations, routes, views)
echo "Copying files..."

# App directory
cp -r $SOURCE/app $TARGET/ 2>/dev/null || true

# Migrations
cp -r $SOURCE/database/migrations/* $TARGET/database/migrations/ 2>/dev/null || true

# Routes
cp $SOURCE/routes/web.php $TARGET/routes/ 2>/dev/null || true

# Views
cp -r $SOURCE/resources/views/livewire/* $TARGET/resources/views/livewire/ 2>/dev/null || true
cp -r $SOURCE/resources/views/components $TARGET/resources/views/ 2>/dev/null || true
cp -r $SOURCE/resources/views/livewire/settings $TARGET/resources/views/livewire/ 2>/dev/null || true

# Scripts
mkdir -p $TARGET/scripts
cp $SOURCE/scripts/deploy-ai-features.sh $TARGET/scripts/ 2>/dev/null || true

# Config - InstanceSettings
cp $SOURCE/app/Models/InstanceSettings.php $TARGET/app/Models/ 2>/dev/null || true
cp $SOURCE/app/Models/Application.php $TARGET/app/Models/ 2>/dev/null || true
cp $SOURCE/app/Models/ApplicationDeploymentQueue.php $TARGET/app/Models/ 2>/dev/null || true

# Services
cp -r $SOURCE/app/Services/AiService.php $TARGET/app/Services/ 2>/dev/null || true

# Jobs
cp -r $SOURCE/app/Jobs/AutoScaleSwarmJob.php $TARGET/app/Jobs/ 2>/dev/null || true
cp $SOURCE/app/Jobs/ApplicationDeploymentJob.php $TARGET/app/Jobs/ 2>/dev/null || true

# Livewire components
cp -r $SOURCE/app/Livewire/Settings/Ai.php $TARGET/app/Livewire/Settings/ 2>/dev/null || true
cp -r $SOURCE/app/Livewire/Settings/SwarmDomains.php $TARGET/app/Livewire/Settings/ 2>/dev/null || true
cp -r $SOURCE/app/Livewire/Project/Application/Swarm.php $TARGET/app/Livewire/Project/Application/ 2>/dev/null || true

# Models
cp -r $SOURCE/app/Models/SwarmDomainMapping.php $TARGET/app/Models/ 2>/dev/null || true

# Console Kernel (for scheduling)
cp $SOURCE/app/Console/Kernel.php $TARGET/app/Console/ 2>/dev/null || true

echo "Running migrations..."
docker exec coolify php artisan migrate --force 2>/dev/null || true

echo "Clearing caches..."
docker exec coolify php artisan config:clear 2>/dev/null || true
docker exec coolify php artisan view:clear 2>/dev/null || true
docker exec coolify php artisan route:clear 2>/dev/null || true

echo ""
echo "=== Done ==="
echo "Restart Coolify to apply changes: docker restart coolify"