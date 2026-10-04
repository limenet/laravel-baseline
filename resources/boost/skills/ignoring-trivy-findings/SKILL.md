---
name: ignoring-trivy-findings
description: Add, renew, or prune entries in .trivyignore.yaml — either short-lived because the fixed version is still inside the npm install cooldown, or longer-lived because the upgrade is blocked by a project constraint. Use when the Trivy `security` CI job fails on a vulnerability, when asked to ignore or suppress a Trivy finding, or when reviewing existing Trivy ignores.
---

# Ignoring Trivy findings

This project (per the [limenet/laravel-baseline](https://github.com/limenet/laravel-baseline)
standards) scans itself with Trivy in the `security` CI job, configured by `trivy.yaml`, which
points `ignorefile` at `.trivyignore.yaml`. An ignore is a **dated exception, never a permanent
one**: every entry carries an `expired_at` and a `statement`, so it resurfaces on its own and the
next person knows why it was there.

There are exactly two reasons to ignore a vulnerability, and they get different expiries:

| Kind | Why it cannot be fixed now | `expired_at` |
| --- | --- | --- |
| **cooldown** | The fixed npm version exists but was published less than `min-release-age` days ago, so `.npmrc` deliberately refuses to install it yet. | Aligned to the day the fix becomes installable. |
| **constraint** | The fix needs an upgrade this project cannot take in the near future (a blocked major, a framework or platform pin, an upstream that has not released a fix). | At most 90 days out, then re-evaluate. |

Anything else is not an ignore — it is an upgrade. In particular, a **composer** finding is never a
cooldown case: the baseline only enforces a cooldown for npm, so if a fixed composer version exists
and fits the constraints, update it (`ddev composer update <vendor/package>`) instead.

## When to use this skill

- The `security` CI job (or a local Trivy run) fails on a vulnerability you cannot fix right now.
- You are asked to ignore, suppress or accept a Trivy finding.
- You are reviewing `.trivyignore.yaml`, or running the `updating-dependencies` skill, which prunes
  expired entries as one of its steps.

## How to do it

### 1. Prune expired entries first (always)

Every time this skill runs — and every time dependencies are updated — remove the entries that have
already expired before adding anything new. Trivy stops applying an entry once its `expired_at` is
reached, so an expired entry suppresses nothing; it only makes the file look like the finding is
still being handled. Get today's date:

```bash
ddev php -r 'echo date("Y-m-d"), PHP_EOL;'
```

Delete every entry, in any section, whose `expired_at` is **on or before** today. Then deal with
whatever those entries were hiding (step 2 shows the findings again):

- **Expired cooldown entry, finding still reported** — the cooldown has passed, so the fix is now
  installable: upgrade (step 3). Do not re-add the entry.
- **Expired constraint entry, finding still reported** — re-evaluate. If the blocker is gone,
  upgrade. If it is not, add a fresh entry (step 4) with an updated statement; renewing is a
  deliberate decision, not a date bump.
- **Finding no longer reported** — nothing to do; the entry was stale anyway.

If a section ends up empty, remove its key. Leave `.trivyignore.yaml` itself in place even when it
is empty — the `hasTrivyConfig` baseline check requires the file to exist.

### 2. Get the findings

Trivy runs on the host, not in DDEV. If `trivy` is installed, run it from the project root — it
picks up `trivy.yaml`, and with it the ignore file, automatically:

```bash
trivy fs .
```

Otherwise read the findings from the failed `security` job log in GitLab CI. For each
vulnerability, note the ID (`CVE-…` or `GHSA-…`), the package, its ecosystem (composer or npm), the
installed version and the **fixed version**.

To see which ignore entries are still matching something, add `--show-suppressed`; an entry whose
finding is absent from that output is stale and can be removed too.

### 3. Fix what is fixable

For each finding, try the fix before reaching for an ignore:

- **No fixed version** — not fixable by upgrading; treat it as **constraint** (step 4) with the
  statement saying no fix has been released.
- **Composer package** — update it within its constraint. If that requires crossing a constraint,
  it is a **constraint** case unless the developer approves the bump.
- **npm package** — check when the fixed version was published (pick the fixed version on the
  release line the project would move to):

  ```bash
  npm view <package> time --json
  ```

  Compare it with `min-release-age` (in days) from `.npmrc`. If the fixed version is already older
  than that, the fix is installable: update it (`npm update <package>`, or bump the direct
  dependency that pulls it in). If it is newer, it is a **cooldown** case. Packages listed under
  `min-release-age-exclude[]` in `.npmrc` are exempt from the cooldown and are never a cooldown
  case.

Prefer an ignore over an npm `overrides` entry. When the vulnerable package is transitive and the
dependency that pulls it in does not allow the fixed version yet, do not add an `overrides` entry to
`package.json` to force it: an override pins a version its parent was never tested against, and
unlike an ignore it never expires, so it outlives its reason without anyone being reminded. Add a
**constraint** entry instead, naming the parent that has to release, and let the normal update pick
up the fix once it does. Above all, never override a **cooldown** case — the fix arrives through the
normal update within days, and the dated ignore covers exactly that window.

Never lower `min-release-age`, add an exclude, or pass a flag that bypasses the cooldown just to
install a fix sooner. The cooldown is the supply-chain protection; the ignore is how it coexists
with a known vulnerability for a few days.

### 4. Add the entry

Add one entry per vulnerability ID under `vulnerabilities:`, creating the key if the file is empty.
Every entry has `id`, `expired_at` (`YYYY-MM-DD`) and `statement`; the statement starts with the
kind so the next prune can tell them apart.

**Cooldown** — align `expired_at` with the cooldown: the fixed version's publish date plus
`min-release-age` days, plus **one more day**. `expired_at` is a date, so it expires at the start of
that day; without the extra day the entry would lapse a few hours before `npm` will install the
fix. Example: fixed version published 2026-10-03T14:20Z, `min-release-age=7` → installable from
2026-10-10T14:20Z → `expired_at: 2026-10-11`.

**Constraint** — set `expired_at` no more than 90 days out, sooner if the blocker has a known end
(a planned upgrade, an announced upstream release). The statement names the blocker and what would
unblock it.

```yaml
vulnerabilities:
  - id: CVE-2026-12345
    expired_at: 2026-10-11
    statement: "cooldown: example-pkg 3.4.2 fixes it, published 2026-10-03; installable from 2026-10-10 (min-release-age=7)"
  - id: GHSA-abcd-efgh-ijkl
    expired_at: 2026-12-31
    statement: "constraint: vendor/package fixed in 5.0, which needs Laravel 14; unblocked by the Laravel 14 upgrade"
```

Do not omit `expired_at` — an entry without it is permanent, which this project does not allow.
Do not widen an entry beyond the one ID it is about.

### 5. Verify

Re-run `trivy fs .` (or push and let the `security` job run) and confirm the job is green, then run
the lint suite:

```bash
ddev composer run ci-lint
```

## What to report

- **Pruned** — each removed entry, and what happened to its finding (upgraded, renewed, or gone).
- **Fixed** — vulnerabilities resolved by upgrading, each as `package old → new`.
- **Ignored** — each new or renewed entry: ID, package, kind, `expired_at`, and the reason.

## Conventions

- **Upgrade first, ignore second.** An ignore is only for a fix that cannot be installed yet.
- **Every entry expires.** Cooldown entries expire when the fix becomes installable; constraint
  entries within 90 days.
- **Prune on every pass.** Expired entries are removed whenever this skill or
  `updating-dependencies` runs.
- **Never weaken the cooldown** to get a fix in faster.
- **Ignore, don't override.** Do not add npm `overrides` to force a fixed transitive version; a
  dated ignore is the tool, and for a cooldown case the only one.
- **Plain commit messages.** If asked to commit, write a plain, descriptive message in the
  imperative mood (this project does not use Conventional Commits).
