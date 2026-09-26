---
name: updating-dependencies
description: Update the project's composer and npm dependencies, review changelogs for their impact on this project, and report majors blocked by semver. Use when asked to update or upgrade dependencies, run the monthly dependency update, or when prompted by the `updatesDependencies` periodic baseline check.
---

# Updating dependencies

This project (per the [limenet/laravel-baseline](https://github.com/limenet/laravel-baseline)
standards) keeps its composer and npm dependencies current on a monthly cadence. This skill drives
that routine: it surveys what is outdated, applies the safe in-constraint updates, reviews each
meaningful bump's changelog against how this project actually uses the package, and reports the
majors that are blocked by the version constraints so they can be tackled deliberately.

## When to use this skill

Use this when the task is to update or upgrade dependencies, run the monthly dependency update, or
bring composer/npm packages current. This is the skill the `updatesDependencies` periodic check
(from limenet/laravel-baseline) points developers to — run it when that check prompts you.

The routine is **agent-driven, not interactive**: run the survey commands non-interactively,
present the findings, and apply only the upgrades the developer approves. Do not run interactive
TUIs (e.g. `npm-check-updates --interactive`) — you drive the selection yourself.

## How to update

### 1. Survey what is outdated (no changes yet)

List outdated direct composer dependencies — this shows the installed version, the latest version
allowed by the current constraint, and the latest version overall:

```bash
ddev composer outdated --direct
```

Group the available npm upgrades by patch / minor / major, without changing anything:

```bash
npx npm-check-updates --format group
```

### 2. Apply the in-constraint updates

Update everything that fits the existing constraints and refresh the lock files. Run each
separately:

```bash
ddev composer update
```

```bash
npm update
```

If `biome` moved (here or in step 4), sync the `$schema` in `biome.json` to the new version — see
the Biome convention below.

### 3. Review changelogs against this project

For each package with a meaningful bump (from the survey and from step 2), fetch its changelog or
GitHub release notes for the version delta and assess the concrete impact on *this* project:

- Call out breaking changes, deprecations, and any required migration steps.
- Distinguish changes that actually affect this project's usage from cosmetic or internal ones —
  check how the package is used in the app code before claiming impact.

### 4. Handle the majors blocked by semver

- **Composer:** from `ddev composer outdated --direct`, report every direct package whose *latest*
  version exceeds the latest version allowed by the constraint. These majors are blocked by the
  `composer.json` constraint and require a deliberate constraint bump — recommend, but do not cross
  the constraint automatically.
- **npm:** present the grouped major upgrades from `npm-check-updates` and recommend per package
  based on the changelog review. Apply **only the upgrades the developer approves**, one at a time:

```bash
npx npm-check-updates -u <package>
```

```bash
npm install
```

### 5. Verify (always)

Run both lint suites and fix every issue before considering the update complete. Run each
separately:

```bash
ddev composer run ci-lint
```

```bash
npm run ci-lint
```

### 6. Record the run (last)

Once both `ci-lint` runs pass, mark the `updatesDependencies` periodic check as done so it stops
prompting for the next 30 days. `limenet:laravel-baseline:periodic` asks for confirmation
interactively, so write the timestamp into `config/baseline.php` directly instead. Get the current
time in the format the baseline writes:

```bash
ddev php -r 'echo date(DATE_ATOM), PHP_EOL;'
```

Set it under the `periodic` key, adding that key if it does not exist yet, and leave `excludes` and
every other entry untouched:

```php
return [
    'excludes' => [],
    'periodic' => [
        'updatesDependencies' => '2026-01-31T09:15:00+00:00',
    ],
];
```

Skip this step if the update was abandoned or `ci-lint` still fails — the date records a completed
update, not an attempted one.

## What to report

Produce a written summary:

- **Updated in-constraint** — composer and npm packages that moved, each as `old → new`.
- **Changelog impact** — per package, what changed and whether it affects this project (with the
  migration step, if any).
- **Blocked by semver** — the majors / upgrades held back by the constraints, each with a
  recommendation: bump now, defer, or skip, and why.
- **Lint fixes** — anything the `ci-lint` run required you to fix.

## Conventions

- **DDEV-first for composer.** Run composer through DDEV (`ddev composer …`) so the PHP version and
  environment match the container.
- **`ci-lint` is the gate.** Always run `ddev composer run ci-lint` and `npm run ci-lint` after
  updating, and fix every issue before finishing.
- **Assess impact against real usage.** Judge changelog impact by how the project actually uses the
  package — do not assume a change is relevant or irrelevant without looking.
- **Recommend, then apply approved.** Survey and recommend beyond-constraint bumps; apply only the
  ones the developer approves. Never cross a version constraint automatically.
- **Keep Biome's `$schema` in sync.** If `biome` is installed and its version changed, update the
  `$schema` URL in `biome.json` to match the new version (e.g.
  `https://biomejs.dev/schemas/<new-version>/schema.json`). Skip this if there is no `biome.json` or
  Biome was not bumped.
- **Plain commit messages.** If asked to commit, write a plain, descriptive message in the
  imperative mood (this project does not use Conventional Commits).
