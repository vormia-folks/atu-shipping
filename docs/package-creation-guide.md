# Package creation guide (ATU-style Laravel packages)

This guide summarizes how **atu-shipping** is structured so you can mirror the pattern for other ATU or Vormia packages. It is not a generic Laravel tutorial; for that, see the [Laravel package development documentation](https://laravel.com/docs/packages).

## Composer and autoload

- **PSR-4** autoload the main namespace from `src/`.
- Put generated/copied **stubs** under `src/stubs/` and add that tree to `exclude-from-classmap` so stub classes are not autoloaded from vendor until copied into the host app.
- Register the service provider under `extra.laravel.providers` so Laravel discovers it after `composer require`.
- Prefer **no** `"version"` field in `composer.json`; tag releases in Git instead.

## Service provider responsibilities

- `register()` — bind singletons (e.g. `ShippingService`, installer helpers).
- `boot()` — `loadMigrationsFrom()` for package-owned migrations; `commands()` for Artisan commands.
- Avoid publishing assets by default if your flow is **installer-driven** (copy on demand).

## Installer pattern

A dedicated class (here `Support\Installer`) should:

1. **Copy stubs** to predictable app paths (`config/`, `resources/views/`, `app/Http/Controllers/`, `database/seeders/`), with `--no-overwrite` semantics when requested.
2. **Idempotent route/menu injection** — wrap injected snippets in unique START/END markers so update/uninstall can find and replace or remove them.
3. **Env keys** — append documented keys to `.env` / `.env.example`, and optionally strip them on uninstall.

Keep marker strings in **one place** (class constants) to avoid drift between install and uninstall.

## Artisan commands

- **Install** — copy files, inject routes, optional env.
- **Update** — typically re-run install with overwrite, clear relevant caches.
- **Uninstall** — remove copied files, strip marked sections, optional DB drop, backup before destructive steps.
- **Help** — print quick reference for teams.

Register commands in the service provider only; keep command classes thin and delegate to `Installer` / services.

## Domain code

- **Models** — Eloquent models with explicit `$table`, `$fillable`, `$casts`, scopes (`active`, `orderedByPriority`).
- **Services** — stateless or small-scoped classes injected into facades or commands.
- **Contracts** — PHP interfaces for anything the host app must implement (cart, order, etc.).
- **Facades** — thin static proxies resolving from the container (document that downstream packages may extend the same facade name in a monolith; avoid collisions).

## Tests

- Use `orchestra/testbench` only if you need a full Laravel app; this repo uses lightweight unit tests where possible.
- Test **pure logic** (`FeeCalculator`, `RuleEvaluator`) with minimal fixtures.
- Test **installer** behavior against a temporary filesystem or mocked `Filesystem`.

## Documentation

- Keep the **README** oriented toward consumers (install, env, usage examples).
- Keep **`docs/*.md`** oriented toward implementers (accurate behavior, extension points, limitations).
- When README references `docs/`, ensure those files exist in the repository.
