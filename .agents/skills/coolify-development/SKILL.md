---
name: coolify-development
description: >-
  General Coolify development guidelines. Activates when working on the Coolify codebase,
  understanding architecture, or following project conventions.
---

# Coolify Development

## Project Overview

Coolify is an open-source, self-hostable PaaS built with:
- Laravel 12 (Laravel 10 file structure)
- Livewire 3 + Alpine.js
- Tailwind CSS v4
- PostgreSQL + Redis

## Key Conventions

### Code Style
- Run `vendor/bin/pint --dirty --format agent` before committing
- Use PHP 8.4 features (constructor promotion, explicit return types)

### Models
- Always use `$casts` method (not `$casts` property) for type casting
- Use Eloquent relationships, avoid raw DB queries
- Include return type hints on all methods

### Livewire Components
- Use `App\Livewire` namespace
- Use `$this->dispatch()` for events
- Validate and authorize in actions like HTTP requests

### Tests
- New tests go in `tests/v4/Feature/`
- Always seed `InstanceSettings::create(['id' => 0])` in browser tests
- Use `RefreshDatabase` trait

## Important Files

| Directory | Purpose |
|-----------|---------|
| `app/Livewire/` | All UI components |
| `app/Models/` | Eloquent models |
| `app/Actions/` | Domain actions |
| `app/Jobs/` | Queue jobs |
| `app/Services/` | Business logic |
| `bootstrap/helpers/` | Helper functions |

## Getting Help

- Use `search-docs` tool for Laravel/Pest/Livewire docs
- Check existing components for patterns
- Run tests: `php artisan test --compact`
