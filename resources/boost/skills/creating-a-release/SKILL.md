---
name: creating-a-release
description: Cut, tag, and publish a new release of this Laravel project using release-it. Use when asked to create, cut, tag, or publish a release, or bump the project version.
---

# Creating a release

This project (per the [limenet/laravel-baseline](https://github.com/limenet/laravel-baseline)
standards) releases with [release-it](https://github.com/release-it/release-it) and the
`@release-it/bumper` plugin. Versioning is semantic and the canonical version lives in
`composer.json`.

## When to use this skill

Use this when the task is to create, cut, tag, or publish a new release — or otherwise bump the
project's version.

## How to release

Run the release script — it drives release-it interactively (choose the next version, then it
tags, commits, and publishes):

```bash
npm run release
```

`release-it` handles the version bump, git tag, commit, and (if configured) the GitHub/GitLab
release. The `@release-it/bumper` plugin writes the chosen version into the `version` field of
`composer.json`, keeping it the single source of truth.

## Conventions

- **Semantic versioning.** Pick the next version per semver (patch / minor / major) based on the
  changes since the last tag.
- **`composer.json` `version` is authoritative.** Do not hand-edit it for a release — let
  `npm run release` / `@release-it/bumper` set it.

## Configuration reference

The relevant config the baseline enforces:

- `package.json` → `scripts.release` = `"release-it"`
- `.release-it.json` → `plugins['@release-it/bumper'].out` = `{ "file": "composer.json", "path": "version" }`
