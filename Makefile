# Coolify Custom Fork - Makefile
#
# Quick commands for development and deployment
#
# Usage:
#   make help              Show this help
#   make dev-test          Deploy local changes to running Coolify
#   make build             Build Docker image
#   make build-push        Build and push Docker image
#   make pull-upstream     Pull latest from upstream
#   make test              Run tests
#   make install           Full install from this fork

.PHONY: help dev-test build build-push pull-upstream test install clean logs

# Get the directory of this Makefile
SCRIPT_DIR := $(dir $(lastword $(MAKEFILE_LIST)))
SOURCE_DIR := $(shell cd $(SCRIPT_DIR) && pwd)

# Default target
help:
	@echo ""
	@echo "=========================================="
	@echo "  Coolify Custom Fork - Quick Commands"
	@echo "=========================================="
	@echo ""
	@echo "Development:"
	@echo "  make dev-test        Deploy local changes to running Coolify"
	@echo "  make test            Run tests"
	@echo "  make logs            View Coolify logs"
	@echo ""
	@echo "Build & Release:"
	@echo "  make build           Build Docker image"
	@echo "  make build-push      Build and push Docker image"
	@echo "  make install         Full install from this fork"
	@echo ""
	@echo "Maintenance:"
	@echo "  make pull-upstream   Pull latest from upstream"
	@echo "  make clean           Clean up temporary files"
	@echo ""
	@echo "Git:"
	@echo "  make status          Show git status"
	@echo "  make diff            Show changes"
	@echo "  make commit MSG='..' Commit with message"
	@echo ""
	@echo "=========================================="
	@echo ""

# Deploy local changes to running Coolify (fast, no rebuild)
dev-test:
	@echo "Deploying local changes to running Coolify..."
	@bash $(SOURCE_DIR)/scripts/local/deploy-local.sh

# Build Docker image
build:
	@echo "Building Docker image..."
	@bash $(SOURCE_DIR)/scripts/local/build-image.sh

# Build and push Docker image
build-push:
	@echo "Building and pushing Docker image..."
	@bash $(SOURCE_DIR)/scripts/local/build-image.sh --push

# Pull latest from upstream
pull-upstream:
	@echo "Pulling latest from upstream..."
	@bash $(SOURCE_DIR)/scripts/local/pull-upstream.sh

# Run tests
test:
	@echo "Running tests..."
	@bash $(SOURCE_DIR)/scripts/custom/_loader.sh

# Full install from this fork
install:
	@echo "Installing from custom fork..."
	@bash $(SOURCE_DIR)/scripts/install.sh

# View logs
logs:
	docker logs -f coolify

# Clean temporary files
clean:
	@echo "Cleaning temporary files..."
	@find $(SOURCE_DIR) -type f -name '*.log' -delete 2>/dev/null || true
	@find $(SOURCE_DIR) -type d -name '__pycache__' -exec rm -rf {} + 2>/dev/null || true
	@find $(SOURCE_DIR) -type d -name '.pytest_cache' -exec rm -rf {} + 2>/dev/null || true
	@echo "Clean complete"

# Git status
status:
	@git -C $(SOURCE_DIR) status

# Git diff
diff:
	@git -C $(SOURCE_DIR) diff --stat

# Git commit
commit:
ifndef MSG
	@echo "Error: MSG is required"
	@echo "Usage: make commit MSG='Your commit message'"
	@exit 1
endif
	@git -C $(SOURCE_DIR) add -A
	@git -C $(SOURCE_DIR) commit -m '$(MSG)'

# Git push
push:
	@git -C $(SOURCE_DIR) push origin master

# Show custom files
custom-files:
	@echo "Custom AI feature files:"
	@find $(SOURCE_DIR)/app -name 'Ai*.php' -type f 2>/dev/null
	@find $(SOURCE_DIR)/.agents -type f 2>/dev/null
