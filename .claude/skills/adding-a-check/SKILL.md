---
name: adding-a-check
description: Add a new baseline check to this repository. Use whenever a new check is requested, described, or implied — including "add a check for X", "the baseline should enforce X", or "we should verify X in projects". Establishes which runner(s) the check targets and whether it auto-fixes before any code is written.
---

# Adding a check

This repository ships **two runners** from one policy (see CLAUDE.md): the Composer package in
`src/` and the npm package in `js/`. A new check therefore has two decisions attached to it that
cannot be inferred from the request, and getting either wrong means rewriting the check.

## Ask first — always, before writing any code

Ask both questions in a single `AskUserQuestion` call. Do not guess, and do not start with an
implementation "to be adjusted later": the runner choice changes where the policy values live and
whether a fixture is per-engine, and the autofix choice changes the base class the check extends.

### 1. Which runner(s)?

- **Both** — the standard lives in every project. Ask whether the *values* are identical or differ
  per ecosystem; if they differ, they go into `policy/policy.json` under a `php` / `js` split (see
  `ci.requiredJobs`, `ciLint.required`, `claude.allow`) and the fixtures are written per engine.
- **PHP only** — anything composer-, artisan-, Rector-, PHPStan-, Spatie-Health- or DDEV-shaped.
- **JS only** — anything that has no meaning in a Laravel project, or whose PHP counterpart would
  assert the opposite (`usesReleaseIt` is the precedent).

Recommend an answer with a reason rather than presenting a bare choice — most checks have an
obvious home, and the question exists for the ones that do not.

### 2. Autofix?

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

- `tests/CheckCommandTest.php` hard-codes the registered check count in **two** places.
- Adding a check on one side only is fine, but the fixture must declare its `engines` accordingly,
  and a fixture directory name must be the kebab-case of the check name.

## Verify both sides

A policy change can break the runner you did not touch:

```bash
composer test && composer analyse && composer format
npm run ci-lint && npm run build && npm test
```
