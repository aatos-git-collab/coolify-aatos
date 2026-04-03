# Local Development Scripts

These scripts are for development and deployment of this custom Coolify fork.

## Scripts

| Script | Purpose | When to Use |
|--------|---------|-------------|
| `deploy-local.sh` | Sync local files to running Coolify | After code changes during development |
| `build-image.sh` | Build Docker image | When ready to package for deployment |
| `pull-upstream.sh` | Sync from upstream Coolify | Regular maintenance to keep fork updated |

## Quick Commands

```bash
# From repo root, use Makefile instead:
make dev-test       # Deploy local changes
make build          # Build Docker image
make build-push     # Build and push
make pull-upstream  # Sync from upstream
```

## deploy-local.sh

**Purpose:** Quickly sync local source changes to a running Coolify instance WITHOUT rebuilding Docker image.

**Use when:**
- Developing and want fast iteration
- Testing changes without full rebuild
- Hot-fixing a running instance

**Requirements:**
- Coolify must be running (`docker ps` shows coolify container)
- Source directory must have the custom files

**Usage:**
```bash
# Default (source=repo root, target=/data/coolify/source)
./deploy-local.sh

# Custom paths
./deploy-local.sh --source /path/to/source --target /path/to/target

# Watch mode (continuous deploy)
while true; do ./deploy-local.sh; sleep 5; done
```

## build-image.sh

**Purpose:** Build a custom Docker image with AI features pre-baked in.

**Use when:**
- Preparing a release
- Need to deploy to multiple servers
- Want a self-contained image

**Usage:**
```bash
# Build with auto tag
./build-image.sh

# Specific version
./build-image.sh --tag v4.0.0-custom

# Build and push to registry
./build-image.sh --push --tag v4.0.0

# Custom registry
./build-image.sh --registry ghcr.io/myorg --push
```

## pull-upstream.sh

**Purpose:** Update this fork with latest Coolify upstream while preserving AI features.

**Use when:**
- Regular maintenance (weekly/monthly)
- New Coolify features needed
- Security patches from upstream

**Workflow:**
```bash
# Dry run first
./pull-upstream.sh --dry-run

# Full update
./pull-upstream.sh

# Skip tests
./pull-upstream.sh --no-test
```

**What it does:**
1. Fetches upstream Coolify
2. Updates `v4.x-tracking` branch (clean mirror)
3. Merges into `master` (preserves custom files)
4. Runs tests
5. Pushes to origin

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `SOURCE_DIR` | Repo root | Source directory for files |
| `TARGET_DIR` | `/data/coolify/source` | Target for deploy-local |
| `REGISTRY` | `ghcr.io/aatos-git-collab` | Docker registry |
| `IMAGE_NAME` | `coolify-custom` | Docker image name |

## Examples

### Full Development Cycle

```bash
# 1. Make code changes
vim app/Services/AiService.php

# 2. Test locally (fast)
make dev-test

# 3. Check logs
docker logs -f coolify

# 4. Repeat until happy

# 5. Commit
make commit MSG="Improve AI service"

# 6. Push (triggers CI)
make push
```

### Preparing a Release

```bash
# 1. Ensure all changes committed
make status

# 2. Run full test
make test

# 3. Build and push image
make build-push

# 4. Create git tag
git tag v4.0.0-custom
git push origin v4.0.0-custom
```

### Sync from Upstream

```bash
# 1. Check what's new
git fetch upstream
git log --oneline upstream/v4.x -10

# 2. Pull latest (dry run)
./pull-upstream.sh --dry-run

# 3. If looks good, do it
./pull-upstream.sh

# 4. Test
make dev-test

# 5. Push
make push
```

## Troubleshooting

### deploy-local.sh fails

```bash
# Check Coolify is running
docker ps | grep coolify

# Check target directory
ls -la /data/coolify/source

# Manual sync
rsync -av app/ /data/coolify/source/app/
```

### build-image.sh fails

```bash
# Check Docker
docker info

# Clean build cache
docker builder prune

# Check disk space
df -h
```

### pull-upstream.sh conflicts

```bash
# See conflicts
git status

# Abort merge if needed
git merge --abort

# Resolve manually, then:
git add .
git commit -m "Merge upstream - resolved conflicts"
```
