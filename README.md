# Coolify Custom Fork

Custom Coolify fork with AI-powered features for automated deployment debugging and intelligent monitoring.

## Overview

This fork extends [Coolify](https://coolify.io) with AI features:

- **AiAutoFixJob** - Automatically analyzes and fixes deployment failures
- **AiLogMonitorJob** - AI-powered log monitoring with anomaly detection
- **AutoScaleSwarmJob** - Intelligent auto-scaling for Docker Swarm clusters
- **AiService** - Core AI service for natural language interactions
- **AiAutoFixService** - Service layer for auto-fix capabilities

## Repository Structure

```
coolify/
├── app/
│   ├── Jobs/
│   │   ├── AiAutoFixJob.php        # Auto-fix deployments
│   │   ├── AiLogMonitorJob.php     # AI log monitoring
│   │   └── AutoScaleSwarmJob.php   # Swarm auto-scaling
│   ├── Services/
│   │   ├── AiService.php           # Core AI service
│   │   └── AiAutoFixService.php    # Auto-fix logic
│   └── Livewire/
│       └── Settings/
│           └── Ai.php              # AI settings UI
│
├── scripts/
│   ├── custom/                     # Install-time scripts (auto-run)
│   │   ├── _loader.sh              # Auto-loader
│   │   ├── 01-ai-features.sh       # AI features installer
│   │   └── README.md
│   ├── local/                      # Development utilities
│   │   ├── deploy-local.sh         # Deploy to running instance
│   │   ├── build-image.sh          # Build Docker image
│   │   ├── pull-upstream.sh        # Pull from upstream
│   │   └── README.md
│   └── install.sh                  # Modified install script
│
├── .github/
│   └── workflows/
│       └── build-image.yml         # CI/CD pipeline
│
├── Makefile                        # Quick commands
└── README.md                       # This file
```

## Quick Start

### Prerequisites

- Docker & Docker Compose
- Linux server (Ubuntu 20.04+, Debian 11+, or similar)
- Root or sudo access

### Installation

```bash
# Clone this repository
git clone https://github.com/aatos-git-collab/coolify.git
cd coolify

# Run installer (pulls from this fork)
./scripts/install.sh
```

### Development Workflow

```bash
# Make changes to code...

# Test locally (fast deploy to running Coolify)
make dev-test

# Run tests
make test

# Build Docker image
make build

# Commit and push
make commit MSG="Your changes"
make push
```

## Make Commands

| Command | Description |
|---------|-------------|
| `make help` | Show all available commands |
| `make dev-test` | Deploy local changes to running Coolify |
| `make test` | Run custom install tests |
| `make build` | Build Docker image |
| `make build-push` | Build and push to registry |
| `make pull-upstream` | Pull latest from upstream Coolify |
| `make install` | Full install from this fork |
| `make logs` | View Coolify logs |
| `make status` | Show git status |
| `make commit MSG='...'` | Commit changes |
| `make push` | Push to origin |
| `make custom-files` | List custom AI files |

## Customization Pipeline

```
┌─────────────────────────────────────────────────────────────┐
│                    DEVELOPMENT                              │
│  1. Edit code in /root/AI-SmartPanel/coolify/                │
│  2. Test with: make dev-test                                │
│  3. Commit with: make commit MSG='...''                      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    PUSH TO GIT                              │
│  make push                                                  │
│  → Triggers GitHub Actions build-image.yml                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    CI/CD BUILD                              │
│  - Build Docker image with AI features                      │
│  - Push to ghcr.io/aatos-git-collab/coolify-custom         │
│  - Create release tags automatically                        │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    PRODUCTION DEPLOY                        │
│  On target server:                                          │
│  curl -fsSL https://raw.githubusercontent.com/... | bash    │
│  OR: ./scripts/install.sh                                   │
└─────────────────────────────────────────────────────────────┘
```

## Upstream Sync

To pull latest changes from upstream Coolify while preserving AI features:

```bash
make pull-upstream
```

This script:
1. Fetches upstream Coolify
2. Updates `v4.x-tracking` branch (clean mirror)
3. Merges into `master` (preserves custom files)
4. Runs tests
5. Pushes to origin

## Custom Files

This fork adds/modifies these files from upstream:

| File | Purpose |
|------|---------|
| `app/Jobs/AiAutoFixJob.php` | Auto-fix deployment failures |
| `app/Jobs/AiLogMonitorJob.php` | AI log monitoring job |
| `app/Jobs/AutoScaleSwarmJob.php` | Docker Swarm auto-scaling |
| `app/Services/AiService.php` | Core AI service |
| `app/Services/AiAutoFixService.php` | Auto-fix service layer |
| `app/Livewire/Settings/Ai.php` | AI settings UI component |
| `app/Console/Kernel.php` | Job scheduling (modified) |
| `.agents/skills/ai-integration/SKILL.md` | AI agent skills |

## Environment Variables

AI features use these environment variables (set in Coolify .env):

```env
# AI Service Configuration
AI_ENABLED=true
AI_MODEL=claude-3-sonnet
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-...

# Auto-fix Settings
AUTO_FIX_ENABLED=true
AUTO_FIX_ON_FAILURE=true

# Log Monitoring
LOG_MONITOR_ENABLED=true
LOG_MONITOR_INTERVAL=60
```

## Troubleshooting

### AI features not working

```bash
# Check if files exist
ls -la app/Services/AiService.php
ls -la app/Jobs/AiAutoFixJob.php

# Check Coolify logs
docker logs -f coolify

# Re-run deploy
make dev-test
```

### Build fails

```bash
# Check Docker is running
docker info

# Clean build cache
docker builder prune

# Try again
make build
```

### Upstream merge conflicts

```bash
# See what conflicts exist
git status
git diff --name-only --diff-filter=U

# Resolve conflicts manually
# Then:
git add .
git commit -m "Merge upstream - resolved conflicts"
```

## Contributing

1. Create feature branch: `git checkout -b feature/my-feature`
2. Make changes
3. Test locally: `make dev-test`
4. Commit: `make commit MSG='Add feature'`
5. Push: `git push origin feature/my-feature`
6. Create PR to `master`

## License

Same as upstream Coolify - [MIT License](https://github.com/coollabsio/coolify/blob/main/LICENSE)

## Support

- **Upstream Coolify**: https://coolify.io/docs
- **Custom Fork Issues**: https://github.com/aatos-git-collab/coolify/issues
- **AI Features**: See `.agents/skills/ai-integration/SKILL.md`

## Version Info

- **Upstream Version**: v4.0.0-beta.470
- **Custom Fork Base**: cd7e71d19
- **Last Upstream Sync**: Check `git log master --oneline -1`

---

*This fork is maintained by Aatos Team for AI-powered deployment automation.*
