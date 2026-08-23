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
level). For frontend changes, also run the following — on the host, not through DDEV:

```bash
npm run ci-lint
```

All code must pass these checks before being committed.

### Use DDEV for artisan and composer — but not for npm

This project runs inside DDEV. Always execute artisan and composer through DDEV so the PHP
version, extensions, and environment match the container — never run them on the host:

```bash
ddev artisan <command>
ddev composer run <script>
```

Node tooling is the exception: run `npm` (and everything it drives, such as Vite) **on the host,
outside DDEV** — never `ddev npm`:

```bash
npm install
npm run dev
npm run build
npm run ci-lint
```

### Use project-relative paths

Commands run from the project root. Reference files with paths relative to the project root
(e.g. `app/Models/User.php`), not absolute paths. Do **not** change directories or target the
repo from elsewhere — avoid `cd <dir>` and `git -C <dir>`. Run `git`, `artisan`, and `composer`
from the project root as-is. Changing directories or redirecting the working tree breaks command
whitelisting and is never necessary here.

### Run commands separately, not chained

Run each command as its own invocation rather than chaining them with `&&` or `;`. For example,
run `ci-lint` and `test` as two separate commands:

```bash
ddev composer run ci-lint
ddev composer run test
```

not `ddev composer run ci-lint && ddev composer run test`. Separate, whitelisted commands keep
each step independently approvable and make failures easier to attribute.

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

### Git and commits

- Work happens directly on `main`/`master` by default. Don't create a feature branch unless the
  task explicitly calls for one.
- Do **not** use Conventional Commits. Write plain, descriptive commit messages in the imperative
  mood (e.g. "Add invoice export", not "feat: add invoice export").

### Modern Laravel idioms

Prefer the current framework idioms — they keep static analysis clean and read more clearly.
The cache and session idioms below require Laravel 12.45+ / 13.x; `whenFilledEnum()` requires
Laravel 13.16+.

**Typed cache getters.** When you read a cached value you expect to be a specific scalar or array
type, use the typed getter instead of `Cache::get()`. The typed getters return a properly-typed
value (and throw on a type mismatch) rather than `mixed`, so PHPStan stays happy:

```php
$name = Cache::string('user:display_name', 'Guest');
// not: Cache::get('user:display_name', 'Guest')
```

The full set is `Cache::string()`, `Cache::integer()`, `Cache::float()`, `Cache::boolean()`, and
`Cache::array()`, each taking `($key, $default)`.

**BackedEnum keys for cache and session.** Pass a `BackedEnum` case directly as a cache or session
key — Laravel resolves it to its backing value automatically, so there's no need to unwrap
`->value` by hand:

```php
Cache::put(CacheKey::Profile, $data);
session()->put(CheckoutSession::Cart, $items);
// not: CacheKey::Profile->value / CheckoutSession::Cart->value
```

**`whenFilledEnum()` for backed enums in request data.** When a request key holds the backing value
of a `BackedEnum`, use `whenFilledEnum()` instead of `whenFilled()` plus a manual `tryFrom()` and
null guard. The callback only fires when the key is filled *and* the value maps to a valid case,
and it receives a typed enum instance:

```php
$request->whenFilledEnum('status', Status::class, function (Status $status) use ($query): void {
    $query->where('status', $status);
});

// not:
$request->whenFilled('status', function (string $input) use ($query): void {
    $status = Status::tryFrom($input);
    if ($status === null) {
        return;
    }
    $query->where('status', $status);
});
```

It takes an optional fourth callback that runs when the primary one doesn't — use it to handle a
missing or invalid value without throwing. The method lives on `InteractsWithData`, so it is
available on `Request`, form requests, and anything else using that trait.

### Best practices

- Follow the code style enforced by Laravel Pint.
- Write type-safe code (PHPStan runs at a strict level).
- Maintain high test coverage with Pest.
- Rector handles automated refactoring to modern PHP — keep code compatible with its rule set.
- Keep all tooling configuration in sync with the baseline standards.
