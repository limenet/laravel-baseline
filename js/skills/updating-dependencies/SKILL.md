---
name: updating-dependencies
description: Update the project's npm dependencies, review changelogs for their impact on this project, and report majors blocked by semver. Use when asked to update or upgrade dependencies, run the monthly dependency update, or when prompted by the `updatesDependencies` periodic baseline check.
---

# Updating dependencies

This project (per the [@limenet-ch/baseline](https://github.com/limenet/laravel-baseline)
standards) keeps its npm dependencies current on a monthly cadence. This skill drives that routine:
it surveys what is outdated, applies the safe in-constraint updates, reviews each meaningful bump's
changelog against how this project actually uses the package, and reports the majors that are
blocked by the version constraints so they can be tackled deliberately.

## When to use this skill

Use this when the task is to update or upgrade dependencies, run the monthly dependency update, or
bring npm packages current. This is the skill the `updatesDependencies` periodic check points
developers to — run it when that check prompts you.

The routine is **agent-driven, not interactive**: run the survey commands non-interactively,
present the findings, and apply only the upgrades the developer approves. Do not run interactive
TUIs (e.g. `npm-check-updates --interactive`) — you drive the selection yourself.

## How to update

### 1. Survey what is outdated (no changes yet)

Group the available upgrades by patch / minor / major, without changing anything:

```bash
npx npm-check-updates --format group
```

### 2. Apply the in-constraint updates

Update everything that fits the existing constraints and refresh the lock file:

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

Present the grouped major upgrades from `npm-check-updates` and recommend per package based on the
changelog review. Apply **only the upgrades the developer approves**, one at a time:

```bash
npx npm-check-updates -u <package>
```

```bash
npm install
```

Watch for `engines` conflicts: this project sets `engine-strict=true`, so a package requiring a
newer Node than `engines.node` allows will refuse to install rather than warn.

### 5. Verify (always)

Run the lint suite and fix every issue before considering the update complete:

```bash
npm run ci-lint
```

## What to report

Produce a written summary:

- **Updated in-constraint** — packages that moved, each as `old → new`.
- **Changelog impact** — per package, what changed and whether it affects this project (with the
  migration step, if any).
- **Blocked by semver** — the majors / upgrades held back by the constraints, each with a
  recommendation: bump now, defer, or skip, and why.
- **Lint fixes** — anything the `ci-lint` run required you to fix.

## Conventions

- **`ci-lint` is the gate.** Always run `npm run ci-lint` after updating, and fix every issue
  before finishing.
- **Assess impact against real usage.** Judge changelog impact by how the project actually uses the
  package — do not assume a change is relevant or irrelevant without looking.
- **Recommend, then apply approved.** Survey and recommend beyond-constraint bumps; apply only the
  ones the developer approves. Never cross a version constraint automatically.
- **Respect the install cooldown.** `.npmrc` sets `min-release-age`, so a version published within
  that window is deliberately skipped. Do not lower or bypass it to pull in a fresh release.
- **Keep Biome's `$schema` in sync.** If `biome` is installed and its version changed, update the
  `$schema` URL in `biome.json` to match the new version (e.g.
  `https://biomejs.dev/schemas/<new-version>/schema.json`). Skip this if there is no `biome.json` or
  Biome was not bumped.
- **Plain commit messages.** If asked to commit, write a plain, descriptive message in the
  imperative mood (this project does not use Conventional Commits).
