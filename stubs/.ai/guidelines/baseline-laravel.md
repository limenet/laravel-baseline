# Laravel Baseline Guidelines

This document provides opinionated guidelines for AI assistants working with Laravel projects that follow the laravel-baseline standards.

## Code Quality and Standards

### Running Linters and Static Analysis

Before committing code, always run the comprehensive lint checks:

**PHP Linting:**
```bash
ddev composer run ci-lint
```

This command runs both Laravel Pint (code formatting) and PHPStan (static analysis).

**JavaScript/Frontend Linting:**
```bash
npm run ci-lint
```

This command runs frontend linting tools (e.g., Prettier, ESLint) to ensure code formatting consistency.

All code must pass these checks before being committed.

### Using DDEV for Artisan Commands

When working in a DDEV environment, always use DDEV to execute artisan commands:

```bash
ddev exec php artisan <command>
```

Or use the shorthand:

```bash
ddev artisan <command>
```

Do not run artisan commands directly on the host machine, as this may cause inconsistencies with dependencies, PHP versions, or environment configuration.

## Testing Standards

### Run Tests

Execute the test suite using:

```bash
ddev composer run test
```

Or with DDEV:

```bash
ddev artisan test
```

## IDE Helper Configuration

This project uses Laravel IDE helpers to provide better autocomplete and static analysis. These should be regenerated after significant model or facade changes:

```bash
ddev artisan ide-helper:generate
ddev artisan ide-helper:models --nowrite
ddev artisan ide-helper:meta
```

## Development Workflow

1. **During Development**: Write tests alongside features
2. **Before Committing**: Run `ddev composer run ci-lint`, `npm run ci-lint`, and `ddev composer run test`

## Best Practices

- Follow PSR-12 coding standards (enforced by Laravel Pint)
- Write type-safe code (enforced by PHPStan at strict levels)
- Maintain high test coverage using Pest
- Use Rector for automated refactoring to modern PHP standards
- Keep all tooling configurations in sync with baseline standards
