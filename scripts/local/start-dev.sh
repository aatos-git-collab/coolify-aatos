#!/bin/bash
# Auto-detect server IP and start dev environment

set -e

cd /root/AI-SmartPanel/coolify

# Auto-detect primary IP
SERVER_IP=$(ip route get 1 2>/dev/null | awk '{print $(NF-2); exit}' || hostname -I | awk '{print $1}')

echo "Detected server IP: $SERVER_IP"

# Stop any existing dev containers
docker compose -f docker-compose.dev.yml -p coolify-dev down 2>/dev/null || true

# Start dev with auto-detected IP via environment
SERVER_IP=$SERVER_IP docker compose -f docker-compose.dev.yml -p coolify-dev up -d

echo ""
echo "Dev environment started:"
echo "  Coolify UI: http://$SERVER_IP:8010"
echo "  Vite HMR:   http://$SERVER_IP:5174"
echo ""
echo "NOTE: First time, run 'docker exec coolify-dev-coolify-1 php artisan db:seed --force'"
