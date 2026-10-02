---
name: adding-a-check
description: Add a new baseline check to this repository. Use whenever a new check is requested, described, or implied — including "add a check for X", "the baseline should enforce X", or "we should verify X in projects". Establishes which runner(s) the check targets and whether it auto-fixes before any code is written.
---

# Adding a check

This repository ships **two runners** from one policy (see CLAUDE.md): the Composer package in
`src/` and the npm package in `js/`. A new check therefore has two decisions attached to it that
cannot be inferred from the request, and getting either wrong means rewriting the check.

## Ask first — always, before writing any code

Ask all three questions in a single `AskUserQuestion` call. Do not guess, and do not start with an
implementation "to be adjusted later": the runner choice changes where the policy values live and
whether a fixture is per-engine, the profile choice changes which projects ever run the check, and
the autofix choice changes the base class the check extends.

### 1. Which runner(s)?

- **Both** — the standard lives in every project. Ask whether the *values* are identical or differ
  per ecosystem; if they differ, they go into `policy/policy.json` under a split keyed by who the
  value applies to — `shared`, `composer`, `laravel` / `php` / `wordpress`, `js` (see
  `ci.requiredJobs`, `ciLint.required`, `claude.allow`, and CLAUDE.md §4b) — and the fixtures are
  written per engine.
- **PHP only** — anything composer-, artisan-, Rector-, PHPStan-, Spatie-Health- or DDEV-shaped.
- **JS only** — anything that has no meaning in a Laravel project, or whose PHP counterpart would
  assert the opposite (`usesReleaseIt` is the precedent).

Recommend an answer with a reason rather than presenting a bare choice — most checks have an
obvious home, and the question exists for the ones that do not.

### 2. Which PHP profiles? (skip for JS-only checks)

The PHP runner checks three kinds of project: `laravel` (artisan), and — through the standalone
`vendor/bin/baseline` — `php` (any composer project) and `wordpress` (a theme or plugin).

- **Laravel only** — the default: `AbstractCheck::profiles()` returns `[Profile::Laravel]`. Anything
  touching artisan, `config/*.php`, the schedule, Laravel packages or rector-laravel.
- **Every PHP profile** — override `profiles()` to return `Profile::cases()`. Tooling that means the
  same in any composer project (composer scripts, PHPStan, plain Rector sets, DDEV, editor/CI files).
- **A subset** (e.g. `[Profile::WordPress]`) — standards that only exist in that kind of project.

A check that runs outside Laravel must not use Laravel helpers (`base_path()`, `str()`, `config()`,
facades …) — use `$this->path()` and plain PHP. `tests/StandaloneFrameworkFreeTest.php` enforces
this. When the *values* differ between profiles, branch on `$this->profile()` and keep the values in
`policy/` under per-profile keys. Fixtures declare the profiles they run under with an optional
`"profiles"` list (default `["laravel"]`).

### 3. Autofix?

- **Yes** — extend `AbstractFixableCheck` (PHP) / `FixableCheck` (TS) and implement `fix()`;
  `check()` is the dry run, so detection and repair can never drift apart. The README entry must be
  marked 🔧 — `ReadmeFixableChecksTest` enforces this in *both* directions.
- **No** — extend `AbstractCheck` (PHP) / `Check` (TS) and implement `check()`. The README entry
  must **not** carry 🔧.

Push back on autofix when the repair needs a human decision — a conflict between two declared
values, anything that installs a package, or anything that would silently rewrite a file the
developer curated. `nodeVersion` refusing to resolve an `engines.node` / `.nvmrc` disagreement is
the precedent: it detects, comments, and stops.

## Then follow CLAUDE.md

CLAUDE.md holds the actual mechanics — class placement, registry registration, the README format
enforced by tests, the shared-fixture format, and the rule that **any constant a check compares
against belongs in `policy/`, not in the class**. Follow it rather than restating it here.

Two things that are easy to miss:

- `tests/CheckCommandTest.php` hard-codes the total registered check count.
- Adding a check on one side only is fine, but the fixture must declare its `engines` accordingly,
  and a fixture directory name must be the kebab-case of the check name.

## Verify both sides

A policy change can break the runner you did not touch:

```bash
composer test && composer analyse && composer format
npm run ci-lint && npm run build && npm test
```
