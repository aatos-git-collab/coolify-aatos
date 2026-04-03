---
name: ci-cd-testing
description: >-
  Handles testing, verification, and CI/CD pipelines for Coolify. Activates when running tests,
  creating verification scripts, deploying changes, or debugging integration issues.
---

# CI/CD Testing

## When to Apply

Activate this skill when:
- Running test suites (Pest, browser tests)
- Creating verification scripts
- Deploying changes to Coolify
- Debugging CI/CD pipeline issues

## Running Tests

```bash
# All tests
php artisan test --compact

# v4 Feature tests (recommended for new features)
php artisan test --compact tests/v4/Feature/

# Specific test
php artisan test --compact --filter=testName

# Browser tests with visible browser
./vendor/bin/pest tests/v4/Browser/ --headed

# Unit tests
php artisan test --compact tests/Unit/
```

## Test Structure

- `tests/v4/Feature/` - New feature tests (SQLite :memory:)
- `tests/v4/Browser/` - Browser tests (Playwright)
- `tests/Unit/` - Unit tests
- `tests/Feature/` - Legacy feature tests (PostgreSQL)

## Deploy Scripts

| Script | Purpose |
|--------|---------|
| `scripts/deploy-ai-features.sh` | Deploy AI + swarm features |
| `scripts/deploy-simple.sh` | Simple file sync + migrations |

### Deploy Process

```bash
# Run deploy script
./scripts/deploy-ai-features.sh

# Or manual:
docker cp app coolify:/data/coolify/source/
docker cp database/migrations coolify:/data/coolify/source/
docker exec coolify php artisan migrate --force
docker exec coolify php artisan config:clear
docker restart coolify
```

## Verification Checklist

After deploying new features:

1. **Run tests**: `php artisan test --compact`
2. **Check migrations**: `docker exec coolify php artisan migrate:status`
3. **Verify UI**: Access the dashboard and check new features
4. **Check logs**: `docker logs coolify` for errors

## Common Issues

- **Migration conflicts**: Ensure migrations don't conflict with existing ones
- **Missing files**: Verify all necessary files are copied to container
- **Cache issues**: Run `config:clear`, `view:clear`, `route:clear`
- **Database schema**: Regenerate testing schema if needed:
  ```bash
  docker exec coolify php artisan schema:generate-testing
  ```

## Browser Testing

```bash
# Start dev environment
spin up

# Run browser tests
php artisan test --compact tests/v4/Browser/

# With visible browser
./vendor/bin/pest tests/v4/Browser/ --headed
```

## Creating New Tests

```php
// tests/v4/Feature/ExampleTest.php
<?php

use App\Models\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::create(['id' => 0]);
});

it('works', function () {
    expect(true)->toBeTrue();
});
```
