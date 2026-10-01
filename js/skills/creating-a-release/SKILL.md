---
name: creating-a-release
description: Cut, tag, and publish a new release of this project using release-it. Use when asked to create, cut, tag, or publish a release, or bump the project version.
---

# Creating a release

This project (per the [@limenet-ch/baseline](https://github.com/limenet/laravel-baseline)
standards) releases with [release-it](https://github.com/release-it/release-it). Versioning is
semantic and the canonical version lives in `package.json`.

## When to use this skill

Use this when the task is to create, cut, tag, or publish a new release — or otherwise bump the
project's version.

## How to release

Work through the steps in order. Do not start the release until step 1 says CI is green.

### 1. Wait for CI to be green

A release tags whatever `HEAD` is, so that commit has to have passed CI first.

1. Make sure CI has actually seen `HEAD`: `git fetch` and compare `git rev-parse HEAD` with
   `git rev-parse @{u}`. If `HEAD` has commits the remote does not, no pipeline covers them yet —
   ask the user before pushing, then wait for the pipeline that push starts.
2. Look up the pipeline for the branch with the CLI matching the remote
   (`git remote get-url origin`):

   **GitLab** (`glab`):

   ```bash
   branch=$(git branch --show-current)
   glab ci get --branch "$branch" --output json --jq '{sha, status}'
   ```

   If the status is still running (`created`, `waiting_for_resource`, `preparing`, `pending`,
   `running`, `scheduled`), block until it ends, then read the status again:

   ```bash
   glab ci status --branch "$branch" --wait
   glab ci get --branch "$branch" --output json --jq '{sha, status}'
   ```

   **GitHub** (`gh`) — one push can start several workflow runs, so check all of them for `HEAD`:

   ```bash
   gh run list --branch "$(git branch --show-current)" --commit "$(git rev-parse HEAD)" \
     --json databaseId,name,status,conclusion
   ```

   For every run whose `status` is not `completed`, wait for it:

   ```bash
   gh run watch <databaseId> --exit-status
   ```

3. Release only when the pipeline for `HEAD` is green — GitLab `success`, or every GitHub run
   concluded `success` (`skipped` / `neutral` are fine). If it failed or was cancelled, **stop**:
   report the failing job to the user and do not release.

   If nothing ran for `HEAD` itself — workflow path filters can skip a commit, and GitLab's
   pipeline `sha` may then belong to an earlier one — the latest pipeline on the branch is the
   gate instead and has to be green. If the project has no CI at all, say so and proceed.

### 2. Decide the version increment

How the next version is chosen depends on whether the project uses
[Conventional Commits](https://www.conventionalcommits.org/). It does when `.release-it.json`
configures the `@release-it/conventional-changelog` plugin — check the file, not the commit log.

- **Conventional Commits** — release-it derives the increment from the commits since the last
  tag. Pass no increment, unless the user explicitly asked for a specific one.
- **No Conventional Commits** — release-it has nothing to derive the increment from, so it has to
  come from the user:
  - If the request already names `major`, `minor`, or `patch`, use that.
  - Otherwise ask the user which one. Show them what is being released so they can decide:

    ```bash
    git log "$(git describe --tags --abbrev=0)"..HEAD --oneline
    ```

    You may suggest an increment per semver, but do not pick one on the user's behalf.

### 3. Run the release

Run release-it non-interactively — its prompts need a terminal an agent cannot answer. `--ci`
skips them, and the increment argument stands in for the version prompt:

```bash
# Conventional Commits, no explicit increment
npm run release -- --ci

# Otherwise (or to override), with the increment from step 2
npm run release -- minor --ci
```

`release-it` handles the version bump, git tag, commit, and (if configured) the remote release.

## Conventions

- **Semantic versioning.** The next version is patch / minor / major per semver, chosen as in
  step 2.
- **`package.json` `version` is authoritative.** Do not hand-edit it for a release — let
  `npm run release` set it.
- **Never release on red.** A failed or cancelled pipeline for `HEAD` ends the task; fixing it is
  a separate change.
- **No `@release-it/bumper`.** release-it already writes `package.json` itself, so the bumper
  plugin is redundant here. It is only needed when a *different* file holds the canonical version
  (as in the Laravel projects, where `composer.json` does). The `usesReleaseIt` baseline check
  fails if it is configured.

## Configuration reference

The relevant config the baseline enforces:

- `package.json` → `scripts.release` = `"release-it"`
- `package.json` → `devDependencies` includes `release-it`
- `.release-it.json` → no `plugins['@release-it/bumper']` entry
