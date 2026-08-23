---
name: auditing-laravel-idioms
description: Audit the project's cache, session and request-data usage to confirm modern Laravel idioms (typed cache getters, BackedEnum keys, whenFilledEnum) are followed. Use when asked to audit or check modern Laravel idiom adoption, or when prompted by the `followsModernLaravelIdioms` periodic baseline check.
---

# Auditing Laravel idioms

This project (per the [limenet/laravel-baseline](https://github.com/limenet/laravel-baseline)
standards) prefers current framework idioms for cache access, session access and reading backed
enums out of request data. This skill scans the app code for the older patterns and reports where
they can be modernised.

## When to use this skill

Use this when the task is to audit or check whether the project follows modern Laravel idioms for
cache, session and request-data usage. This is the skill the `followsModernLaravelIdioms` periodic
check (from limenet/laravel-baseline) points developers to — run it when that check prompts you, or
whenever someone asks to review modern Laravel idiom adoption.

The idioms in scope:

1. **Typed cache getters** instead of `Cache::get()` for typed values *(Laravel 12.45+ / 13.x)*.
2. **`BackedEnum` keys passed directly** to cache/session instead of unwrapping `->value`
   *(Laravel 12.45+ / 13.x)*.
3. **`whenFilledEnum()`** instead of `whenFilled()` plus a manual `tryFrom()` null guard when
   reading a backed enum out of request data *(Laravel 13.16+)*.

Check the `laravel/framework` constraint in `composer.json` before auditing: skip idiom 3 entirely
if the project is not on Laravel 13.16 or newer.

## How to audit

Run grep/ripgrep over the application code (`app/`, `routes/`, and any other first-party
directories) to find the older patterns. The matches are candidates, not certain violations —
read each one in context before reporting.

**Typed cache getters.** Find `Cache::get(` / `cache()->get(` calls (and `->get(` on cache
repository instances) where the result is used as a typed scalar or array. These are candidates
to replace with `Cache::string()` / `Cache::integer()` / `Cache::float()` / `Cache::boolean()` /
`Cache::array()`:

```bash
rg "Cache::get\(|cache\(\)->get\(" app/
# only read calls have typed variants — put/add/forever/forget/has/increment have none
```

**BackedEnum keys.** Find `->value` being passed into a cache or session key argument — e.g.
`Cache::put(SomeEnum::Foo->value, ...)`, `session()->get(SomeEnum::Bar->value)`. These can drop
the `->value` and pass the enum case directly:

```bash
rg "->value" app/ | rg -i "cache|session"
```

Inspect each hit: confirm the `->value` is in fact a cache/session **key** (not a value being
stored or compared) before flagging it.

**`whenFilledEnum()`.** Find `whenFilled(` calls whose closure body resolves a backed enum — the
tell is a `tryFrom(` (or `from(`) call on the closure parameter, usually followed by a `null`
guard:

```bash
rg -A 6 "whenFilled\(" app/ | rg -B 2 "tryFrom\(|::from\("
```

Read the whole closure before flagging: the rewrite only applies when the enum resolution is the
closure's **guard** — i.e. an invalid value means the closure does nothing. If the `null` branch
does real work (falls back to another filter, adds a validation error, throws), that behaviour
belongs in `whenFilledEnum()`'s optional fourth callback, so mention it rather than dropping it.
Leave the call alone when the enum is only one of several things the closure does, or when the
closure keeps using the raw string as well.

## What to report

Present findings for the developer to confirm — **do not auto-rewrite** unless the user explicitly
asks. For each candidate, list:

- The `file:line` location.
- The suggested modern replacement (e.g. `Cache::get('foo')` → `Cache::string('foo')`,
  `Cache::put(CacheKey::Foo->value, $x)` → `Cache::put(CacheKey::Foo, $x)`, or a `whenFilled()` +
  `tryFrom()` block → `whenFilledEnum('key', Status::class, ...)`).

Notes to include with the report:

- These are **style / modernisation improvements, not bugs** — the existing code works.
- Typed getters **throw on a type mismatch**, so a blind replacement can change behaviour if a
  cached value's runtime type is uncertain. Only suggest a typed getter where the expected type is
  clear, and call out any case where it isn't.

## Conventions

- **Typed cache getters.** Prefer `Cache::string()`, `Cache::integer()`, `Cache::float()`,
  `Cache::boolean()`, `Cache::array()` (each `($key, $default)`) over `Cache::get()` when reading a
  value of a known scalar/array type.
- **BackedEnum keys passed directly.** Pass a `BackedEnum` case straight to cache/session keys
  (`Cache::put(CacheKey::Profile, $data)`, `session()->put(CheckoutSession::Cart, $items)`) instead
  of unwrapping `->value`.
- **`whenFilledEnum()` for request data.** Use
  `$request->whenFilledEnum('status', Status::class, fn (Status $status) => …)` over `whenFilled()`
  with a manual `tryFrom()` and null guard. The callback fires only when the key is filled and the
  value maps to a valid case, and receives a typed enum instance; an optional fourth callback runs
  when it doesn't. Available on anything using `InteractsWithData` — `Request`, form requests, etc.

The first two idioms require Laravel 12.45+ / 13.x; `whenFilledEnum()` requires Laravel 13.16+.
