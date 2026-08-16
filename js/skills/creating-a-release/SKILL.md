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

Run the release script — it drives release-it interactively (choose the next version, then it
tags, commits, and publishes):

```bash
npm run release
```

`release-it` handles the version bump, git tag, commit, and (if configured) the remote release.

## Conventions

- **Semantic versioning.** Pick the next version per semver (patch / minor / major) based on the
  changes since the last tag.
- **`package.json` `version` is authoritative.** Do not hand-edit it for a release — let
  `npm run release` set it.
- **No `@release-it/bumper`.** release-it already writes `package.json` itself, so the bumper
  plugin is redundant here. It is only needed when a *different* file holds the canonical version
  (as in the Laravel projects, where `composer.json` does). The `usesReleaseIt` baseline check
  fails if it is configured.

## Configuration reference

The relevant config the baseline enforces:

- `package.json` → `scripts.release` = `"release-it"`
- `package.json` → `devDependencies` includes `release-it`
- `.release-it.json` → no `plugins['@release-it/bumper']` entry
