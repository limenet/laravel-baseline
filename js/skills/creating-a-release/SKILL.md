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

A release is often started and then left to run unattended — waiting for CI can take a while, and
the user may have walked away by the time it ends. So **every question is asked in step 1, before
any waiting starts.** From step 2 on, do not ask the user anything: whatever would need them then
is a stop, reported once the task ends.

Work through the steps in order. Do not start the release until step 2 says CI is green.

### 1. Settle everything up front

Gather what follows, then put every open question to the user in **one** `AskUserQuestion` call
(it takes up to four questions). Only once all of them are answered move on to step 2.

1. **Clean working tree.** release-it refuses to run on uncommitted changes. If
   `git status --porcelain` prints anything, ask how to resolve it — do not commit or stash on
   the user's behalf.
2. **Has CI seen `HEAD`?** `git fetch`, then compare `git rev-parse HEAD` with
   `git rev-parse @{u}`. If `HEAD` has commits the remote does not, no pipeline covers them yet —
   ask whether to push them.
3. **The version increment.** How the next version is chosen depends on whether the project uses
   [Conventional Commits](https://www.conventionalcommits.org/). It does when `.release-it.json`
   configures the `@release-it/conventional-changelog` plugin — check the file, not the commit log.

   - **Conventional Commits** — release-it derives the increment from the commits since the last
     tag. Pass no increment and ask nothing, unless the user explicitly asked for a specific one.
   - **No Conventional Commits** — release-it has nothing to derive the increment from, so it has
     to come from the user. If the request already names `major`, `minor`, or `patch`, use that.
     Otherwise ask with `AskUserQuestion` — never a plain-text question, which an unattended run
     would leave sitting in the transcript. Read what is being released first:

     ```bash
     git log "$(git describe --tags --abbrev=0)"..HEAD --oneline
     ```

     Offer `major`, `minor` and `patch` as the options, put the increment semver suggests for those
     commits first and mark it `(Recommended)`, and summarise the commits in the question. Do not
     pick one on the user's behalf.
4. **Credentials.** Make sure nothing will fail for want of a login after the wait:
   - the CI CLI for the remote (`git remote get-url origin`) is authenticated — `gh auth status`
     or `glab auth status`;
   - the token release-it needs for the remote release is set — `GITHUB_TOKEN` when
     `.release-it.json` enables `github.release`, `GITLAB_TOKEN` when it enables
     `gitlab.release`. Check that the variable is non-empty; never print it.

   If either is missing, ask the user to fix it now.

If the user chose to push, push now — the pipeline that push starts is the one step 2 waits for.

### 2. Wait for CI to be green

A release tags whatever `HEAD` is, so that commit has to have passed CI first.

1. Look up the pipeline for the branch with the CLI matching the remote:

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

2. Release only when the pipeline for `HEAD` is green — GitLab `success`, or every GitHub run
   concluded `success` (`skipped` / `neutral` are fine). If it failed or was cancelled, **stop**:
   report the failing job to the user and do not release.

   If nothing ran for `HEAD` itself — workflow path filters can skip a commit, and GitLab's
   pipeline `sha` may then belong to an earlier one — the latest pipeline on the branch is the
   gate instead and has to be green. If the project has no CI at all, say so and proceed.

### 3. Run the release

Run release-it non-interactively — its prompts need a terminal an agent cannot answer. `--ci`
skips them, and the increment argument stands in for the version prompt:

```bash
# Conventional Commits, no explicit increment
npm run release -- --ci

# Otherwise (or to override), with the increment from step 1
npm run release -- minor --ci
```

`release-it` handles the version bump, git tag, commit, and (if configured) the remote release.

### 4. Watch the tag pipeline

Pushing the tag usually starts a pipeline of its own — the one that builds, publishes or deploys
the release — so the task is not done when release-it exits. Watch it the same way as in step 2,
for the tag's commit:

```bash
tag=$(git describe --tags --abbrev=0)
```

**GitLab** — the tag is a ref like a branch:

```bash
glab ci status --branch "$tag" --wait
glab ci get --branch "$tag" --output json --jq '{sha, status}'
```

**GitHub** — list every run for the tagged commit and `gh run watch <databaseId> --exit-status`
each one not yet `completed`:

```bash
gh run list --commit "$(git rev-list -n 1 "$tag")" --json databaseId,name,event,headBranch,status,conclusion
```

A pipeline can take a few seconds to appear after the push; if none shows up, check again a
couple of times over about a minute before concluding the project runs nothing on tags — then say
so. Report the outcome either way. If the tag pipeline fails, report the failing job and stop: the
tag is already public, so do **not** delete, move or re-create it — fixing the failure is a
separate change.

## Conventions

- **Semantic versioning.** The next version is patch / minor / major per semver, chosen as in
  step 1.
- **`package.json` `version` is authoritative.** Do not hand-edit it for a release — let
  `npm run release` set it.
- **Never release on red.** A failed or cancelled pipeline for `HEAD` ends the task; fixing it is
  a separate change.
- **A release ends with its tag pipeline.** Report how the pipeline the tag started finished, not
  just that release-it exited.
- **No `@release-it/bumper`.** release-it already writes `package.json` itself, so the bumper
  plugin is redundant here. It is only needed when a *different* file holds the canonical version
  (as in the Laravel projects, where `composer.json` does). The `usesReleaseIt` baseline check
  fails if it is configured.

## Configuration reference

The relevant config the baseline enforces:

- `package.json` → `scripts.release` = `"release-it"`
- `package.json` → `devDependencies` includes `release-it`
- `.release-it.json` → no `plugins['@release-it/bumper']` entry
