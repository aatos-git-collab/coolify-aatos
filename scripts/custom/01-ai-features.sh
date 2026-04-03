#!/bin/bash
## AI Features Custom Install Script
## 
## This script sets up the AI features for Coolify:
## - AiAutoFixJob
## - AiLogMonitorJob
## - AutoScaleSwarmJob
## - AiService
## - AiAutoFixService
## - AI Skill System (.agents/)
##
## Usage: Called automatically by _loader.sh during install

set -e

COOLIFY_SOURCE="${COOLIFY_SOURCE:-/data/coolify/source}"
CUSTOM_SOURCE="${CUSTOM_SOURCE:-$COOLIFY_SOURCE}"

echo "[AI Features] Starting AI features installation..."

# Check if Coolify source exists
if [ ! -d "$COOLIFY_SOURCE" ]; then
    echo "[AI Features] Warning: Coolify source not found at $COOLIFY_SOURCE"
    echo "[AI Features] Skipping AI features installation."
    exit 0
fi

# Check if AI features already exist
if [ -f "$COOLIFY_SOURCE/app/Services/AiService.php" ]; then
    echo "[AI Features] AI features already installed (AiService.php found)"
    echo "[AI Features] Skipping installation."
    exit 0
fi

echo "[AI Features] Installing AI features..."

# The AI features are already in the source code
# This script just ensures they're properly enabled

# Create AI skills directory if needed
mkdir -p "$COOLIFY_SOURCE/.agents/skills/ai-integration"

# Verify key AI files exist
AI_FILES=(
    "app/Jobs/AiAutoFixJob.php"
    "app/Jobs/AiLogMonitorJob.php"
    "app/Jobs/AutoScaleSwarmJob.php"
    "app/Services/AiService.php"
    "app/Services/AiAutoFixService.php"
)

MISSING_FILES=0
for file in "${AI_FILES[@]}"; do
    if [ ! -f "$COOLIFY_SOURCE/$file" ]; then
        echo "[AI Features] Warning: Missing $file"
        MISSING_FILES=$((MISSING_FILES + 1))
    fi
done

if [ "$MISSING_FILES" -eq 0 ]; then
    echo "[AI Features] ✓ All AI feature files present"
else
    echo "[AI Features] ✗ $MISSING_FILES AI feature file(s) missing"
    echo "[AI Features] Make sure you're using the custom Coolify fork"
    exit 1
fi

# Create symlink for AI skills if using custom fork
if [ -f "$COOLIFY_SOURCE/.agents/skills/ai-integration/SKILL.md" ]; then
    echo "[AI Features] ✓ AI skills directory configured"
else
    echo "[AI Features] Warning: AI skills not found"
fi

echo "[AI Features] ✓ AI features installation complete"
echo ""
echo "[AI Features] Available AI Features:"
echo "  - AiAutoFixJob: Auto-fix deployment errors"
echo "  - AiLogMonitorJob: Monitor logs with AI"
echo "  - AutoScaleSwarmJob: Auto-scale Docker Swarm"
echo "  - AiService: Core AI service"
echo "  - AiAutoFixService: AI auto-fix service"
echo ""
