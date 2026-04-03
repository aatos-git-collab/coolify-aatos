#!/bin/bash
## Auto-loader for custom install scripts
## Place any *.sh file in this directory and it will be auto-executed during install
##
## Usage: This script is called by install.sh automatically
## Just add your feature scripts here and they'll run in alphabetical order

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo ""
echo "============================================================"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Running Custom Install Scripts"
echo "============================================================"

if [ ! -d "$SCRIPT_DIR" ]; then
    echo "Custom scripts directory not found: $SCRIPT_DIR"
    exit 0
fi

# Count scripts
SCRIPT_COUNT=$(find "$SCRIPT_DIR" -maxdepth 1 -name "*.sh" -not -name "_loader.sh" | wc -l)

if [ "$SCRIPT_COUNT" -eq 0 ]; then
    echo "No custom install scripts found."
    exit 0
fi

echo "Found $SCRIPT_COUNT custom script(s) to run:"
echo ""

# Run each script in alphabetical order (except _loader.sh)
FAILED=0
for script in $(find "$SCRIPT_DIR" -maxdepth 1 -name "*.sh" -not -name "_loader.sh" | sort); do
    SCRIPT_NAME=$(basename "$script")
    echo "------------------------------------------------------------"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Running: $SCRIPT_NAME"
    echo "------------------------------------------------------------"
    
    if bash "$script"; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✓ $SCRIPT_NAME completed successfully"
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✗ $SCRIPT_NAME failed (exit code: $?)"
        FAILED=$((FAILED + 1))
    fi
    echo ""
done

echo "============================================================"
if [ "$FAILED" -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] All custom scripts completed successfully"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $FAILED custom script(s) failed"
fi
echo "============================================================"

exit $FAILED
