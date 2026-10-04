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
npx npm-check-updates -u --target semver
```

```bash
npm install
```

`npm-check-updates` rewrites the ranges in `package.json` to the newest version each constraint
allows, where `npm update` would only move the lock file.

### 3. Review changelogs against this project

For each package with a meaningful bump (from the survey and from step 2), fetch its changelog or
GitHub release notes for the version delta and assess the concrete impact on *this* project:

- Call out breaking changes, deprecations, and any required migration steps.
- Distinguish changes that actually affect this project's usage from cosmetic or internal ones —
  check how the package is used in the project's code before claiming impact.

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

### 5. Prune expired Trivy ignores (always)

Open `.trivyignore.yaml` and delete every entry whose `expired_at` is on or before today (Trivy
stops applying it at that point anyway). Get today's date:

```bash
ddev php -r 'echo date("Y-m-d"), PHP_EOL;'
```

The updates above are often what makes those findings go away: a `cooldown:` entry expires once
its fix is installable, and step 2 just installed it. If an expired entry's finding is still
reported, handle it with the `ignoring-trivy-findings` skill — upgrade, or renew it deliberately
with a new expiry and statement — rather than bumping the date. Do not swap the ignore for an npm
`overrides` entry that forces the fix in — least of all when the fix is only waiting out the
cooldown. Leave the file in place even if it
ends up empty.

### 6. Verify (always)

Run both lint suites and fix every issue before considering the update complete. Run each
separately:

```bash
ddev composer run ci-lint
```

```bash
npm run ci-lint
```

### 7. Record the run (last)

Once both `ci-lint` runs pass, mark the `updatesDependencies` periodic check as done so it stops
prompting for the next 30 days. `vendor/bin/baseline periodic` asks for confirmation interactively,
so write the timestamp into `.baseline.json` at the project root directly instead. Get the current
time in the format the baseline writes:

```bash
ddev php -r 'echo date(DATE_ATOM), PHP_EOL;'
```

Set it under the `periodic` key, creating the file or the key if it does not exist yet, and leave
`excludes`, `profile` and every other entry untouched:

```json
{
    "excludes": [],
    "periodic": {
        "updatesDependencies": "2026-01-31T09:15:00+00:00"
    }
}
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
- **Trivy ignores** — expired entries removed from `.trivyignore.yaml`, and how any finding they
  were still hiding was handled.
- **Lint fixes** — anything the `ci-lint` run required you to fix.

## Conventions

- **DDEV-first for composer.** Run composer through DDEV (`ddev composer …`) so the PHP version and
  environment match the container.
- **`ci-lint` is the gate.** Always run `ddev composer run ci-lint` and `npm run ci-lint` after
  updating, and fix every issue before finishing.
- **Assess impact against real usage.** Judge changelog impact by how the project actually uses the
  package — do not assume a change is relevant or irrelevant without looking.
- **Prefer Trivy ignores over npm `overrides`.** When a vulnerable transitive package cannot be
  moved to its fix by a normal update — because of the cooldown or because its parent does not
  allow the fixed version yet — record a dated entry in `.trivyignore.yaml` (see
  `ignoring-trivy-findings`) rather than forcing the version with `overrides` in `package.json`.
- **Recommend, then apply approved.** Survey and recommend beyond-constraint bumps; apply only the
  ones the developer approves. Never cross a version constraint automatically.
- **Leave Biome's `$schema` alone.** `biome.json` points at
  `./node_modules/@biomejs/biome/configuration_schema.json`, which always describes the installed
  Biome, so a Biome bump needs no config edit. Do not switch it to a versioned
  `https://biomejs.dev/schemas/<version>/schema.json` URL: the baseline's `biomeUsesLocalSchema`
  check rejects that.
- **Plain commit messages.** If asked to commit, write a plain, descriptive message in the
  imperative mood (this project does not use Conventional Commits).
