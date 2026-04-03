#!/bin/bash
## Pull Latest from Upstream
##
## Purpose: Update this fork with latest changes from upstream Coolify
##          while preserving our custom AI features
##
## Usage: ./pull-upstream.sh [--branch v4.x-tracking] [--dry-run]
##
## Workflow:
##   1. Fetch upstream changes
##   2. Merge upstream into tracking branch (clean)
##   3. Merge tracking into master (preserving custom)
##   4. Run tests
##   5. Push if tests pass

set -e

# Configuration
SOURCE_DIR="${SOURCE_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)}"
UPSTREAM_REMOTE="${UPSTREAM_REMOTE:-upstream}"
TRACKING_BRANCH="${TRACKING_BRANCH:-v4.x-tracking}"
MASTER_BRANCH="${MASTER_BRANCH:-master}"

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

DRY_RUN=false
TEST_AFTER=true

# Parse args
while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run) DRY_RUN=true; shift ;;
        --no-test) TEST_AFTER=false; shift ;;
        --branch) TRACKING_BRANCH="$2"; shift 2 ;;
        *) shift ;;
    esac
done

cd "$SOURCE_DIR"

echo ""
echo "=========================================="
echo "  PULL UPSTREAM INTO CUSTOM FORK"
echo "=========================================="
echo ""
info "Source: $SOURCE_DIR"
info "Upstream: $UPSTREAM_REMOTE"
info "Tracking branch: $TRACKING_BRANCH"
info "Target branch: $MASTER_BRANCH"
[ "$DRY_RUN" = true ] && warn "DRY RUN MODE"
echo ""

# Check git status first
if [ -n "$(git status --porcelain)" ]; then
    warn "Working tree has uncommitted changes"
    warn "Commit or stash them before proceeding"
    echo ""
    git status --short | head -10
    echo ""
    read -p "Continue anyway? (y/N) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        fail "Aborted"
        exit 1
    fi
fi

# Ensure upstream remote exists
info "Checking upstream remote..."
if ! git remote get-url "$UPSTREAM_REMOTE" >/dev/null 2>&1; then
    info "Adding upstream remote..."
    git remote add "$UPSTREAM_REMOTE" "https://github.com/coollabsio/coolify.git"
fi

UPSTREAM_URL=$(git remote get-url "$UPSTREAM_REMOTE")
info "Upstream URL: $UPSTREAM_URL"

echo ""
info "=========================================="
info "STEP 1: FETCH UPSTREAM"
info "=========================================="
echo ""

git fetch "$UPSTREAM_REMOTE"

UPSTREAM_BRANCH="${UPSTREAM_REMOTE}/v4.x"
if git rev-parse "$UPSTREAM_BRANCH" >/dev/null 2>&1; then
    info "Upstream v4.x: $(git log -1 --format='%H %s' "$UPSTREAM_BRANCH")"
else
    fail "Cannot find upstream v4.x branch"
    exit 1
fi

echo ""
info "=========================================="
info "STEP 2: UPDATE TRACKING BRANCH"
info "=========================================="
echo ""

# Checkout tracking branch
git checkout "$TRACKING_BRANCH" 2>/dev/null || {
    info "Creating tracking branch: $TRACKING_BRANCH"
    git checkout -b "$TRACKING_BRANCH" "${UPSTREAM_REMOTE}/v4.x"
}

# Reset tracking to upstream (clean mirror)
if [ "$DRY_RUN" = true ]; then
    info "[DRY RUN] Would reset $TRACKING_BRANCH to $UPSTREAM_BRANCH"
else
    info "Resetting $TRACKING_BRANCH to $UPSTREAM_BRANCH"
    git reset --hard "$UPSTREAM_BRANCH"
fi

echo ""
info "=========================================="
info "STEP 3: MERGE INTO MASTER"
info "=========================================="
echo ""

git checkout "$MASTER_BRANCH"

if [ "$DRY_RUN" = true ]; then
    info "[DRY RUN] Would merge $TRACKING_BRANCH into $MASTER_BRANCH"
else
    info "Merging $TRACKING_BRANCH into $MASTER_BRANCH"
    
    # Try merge, handle conflicts
    if git merge "$TRACKING_BRANCH" --no-edit; then
        success "Merge successful"
    else
        warn "Merge conflicts detected"
        warn "Resolve conflicts manually, then:"
        echo "  git add ."
        echo "  git commit -m 'Merge $TRACKING_BRANCH into $MASTER_BRANCH'"
        echo "  $0 --continue"
        exit 1
    fi
fi

echo ""
info "=========================================="
info "STEP 4: CHECK CUSTOM FILES"
info "=========================================="
echo ""

CUSTOM_FILES=(
    "app/Jobs/AiAutoFixJob.php"
    "app/Jobs/AiLogMonitorJob.php"
    "app/Services/AiService.php"
    "app/Services/AiAutoFixService.php"
    ".agents/skills/ai-integration/SKILL.md"
)

ALL_OK=true
for file in "${CUSTOM_FILES[@]}"; do
    if [ -f "$file" ]; then
        success "Found: $file"
    else
        fail "MISSING: $file"
        ALL_OK=false
    fi
done

if [ "$ALL_OK" = false ]; then
    fail "Some custom files are missing after merge!"
    exit 1
fi

success "All custom files intact"

echo ""
info "=========================================="
info "STEP 5: RUN TESTS"
info "=========================================="
echo ""

if [ "$TEST_AFTER" = true ]; then
    if [ -f "./scripts/custom/_loader.sh" ]; then
        info "Running install tests..."
        bash ./scripts/custom/_loader.sh || {
            fail "Tests failed"
            exit 1
        }
    else
        info "No custom tests found, skipping"
    fi
else
    info "Skipping tests (--no-test flag)"
fi

echo ""
info "=========================================="
info "STEP 6: PUSH"
info "=========================================="
echo ""

if [ "$DRY_RUN" = true ]; then
    info "[DRY RUN] Would push to origin"
    info "Branches:"
    git branch -v
else
    info "Pushing to origin..."
    git push origin "$TRACKING_BRANCH" "$MASTER_BRANCH"
    success "Pushed!"
fi

echo ""
echo "=========================================="
echo "  UPSTREAM UPDATE COMPLETE"
echo "=========================================="
echo ""
info "Tracking branch: $TRACKING_BRANCH"
info "Master branch: $MASTER_BRANCH"
info "Commit: $(git rev-parse --short HEAD)"
echo ""

if [ "$DRY_RUN" = true ]; then
    success "DRY RUN COMPLETE - no changes made"
else
    success "Fork is now up to date with upstream"
fi
