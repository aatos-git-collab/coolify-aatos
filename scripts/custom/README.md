# Custom Install Scripts

This directory contains modular install scripts that run automatically during Coolify installation.

## How It Works

The `install.sh` calls `scripts/custom/_loader.sh` which automatically runs all `*.sh` files in this directory.

## Adding a New Feature

1. Create a new script in this directory
2. Name it with a number prefix for ordering: `XX-name.sh`
3. Make it executable: `chmod +x your-script.sh`
4. That's it! It'll run automatically on next install

## Current Scripts

| Script | Purpose |
|--------|---------|
| `01-ai-features.sh` | AI features: AiAutoFixJob, AiLogMonitorJob, AutoScaleSwarmJob, AiService |
| `02-deploy-ai.sh` | Deploy script for AI features to running Coolify instance |
| `03-deploy-simple.sh` | Simple deploy script for copying custom files |
| `_loader.sh` | Auto-runs all scripts (do not modify) |

## Script Descriptions

### 01-ai-features.sh
Installs AI feature files that are part of this custom fork:
- AiAutoFixJob - Auto-fix deployment errors
- AiLogMonitorJob - Monitor logs with AI  
- AutoScaleSwarmJob - Auto-scale Docker Swarm
- AiService - Core AI service
- AiAutoFixService - AI auto-fix service

### 02-deploy-ai.sh
Deployment utility - syncs files from source to /data/coolify and runs migrations.

### 03-deploy-simple.sh  
Simple deployment utility - copies key files (app, migrations, routes, views) to running instance.

## Execution Order

Scripts run in alphabetical order. Number prefixes (`01-`, `02-`, etc.) control order.

## Notes

- Scripts run with root privileges
- Access to `$COOLIFY_SOURCE` environment variable
- Check `/data/coolify/source/installation-*.log` for logs
- Scripts should be idempotent (safe to run multiple times)
