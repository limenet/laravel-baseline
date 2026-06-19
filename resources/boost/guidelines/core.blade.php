## Laravel Baseline

This project follows the [limenet/laravel-baseline](https://github.com/limenet/laravel-baseline)
standards. The baseline is continuously enforced by `php artisan limenet:laravel-baseline:check`
(run with `--fix` after every `composer update`), so scaffolding stays correct automatically —
your job is to follow the conventions below while writing code.

### Linting and static analysis

Before considering a change complete, run the lint suite and fix every issue:

```bash
ddev composer run ci-lint
```

`ci-lint` runs Laravel Pint (code style) and PHPStan/Larastan (static analysis at a strict
level). For frontend changes, also run:

```bash
npm run ci-lint
```

All code must pass these checks before being committed.

### Use DDEV for artisan and composer

This project runs inside DDEV. Always execute artisan and composer through DDEV so the PHP
version, extensions, and environment match the container — never run them on the host:

```bash
ddev artisan <command>
ddev composer run <script>
```

### Testing

Tests use Pest. Run the suite with:

```bash
ddev composer run test
```

Write tests alongside features and keep coverage high.

### IDE helpers

This project uses `barryvdh/laravel-ide-helper`. Regenerate the helper files after significant
model or facade changes so static analysis and autocomplete stay accurate:

```bash
ddev artisan ide-helper:generate
ddev artisan ide-helper:models --nowrite
ddev artisan ide-helper:meta
```

### Development workflow

1. **During development:** write tests alongside features.
2. **Before committing:** run `ddev composer run ci-lint`, `npm run ci-lint`, and
   `ddev composer run test`.
3. **Review changes:** use the `/code-review` skill to review recent changes for correctness
   bugs and simplification opportunities.

### Best practices

- Follow the code style enforced by Laravel Pint.
- Write type-safe code (PHPStan runs at a strict level).
- Maintain high test coverage with Pest.
- Rector handles automated refactoring to modern PHP — keep code compatible with its rule set.
- Keep all tooling configuration in sync with the baseline standards.
