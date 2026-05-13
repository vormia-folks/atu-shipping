# ATU Shipping — implementation guide

This document is the technical companion to the repository [README](../README.md). It describes how the package behaves in code, where to extend it, and known limitations.

## Package layout

| Area | Path | Role |
|------|------|------|
| Service provider | `src/ATUShippingServiceProvider.php` | Registers services, loads migrations from `database/migrations`, publishes nothing mandatory (install is Artisan-driven). |
| Public API | `Vormia\ATUShipping\Facades\ATU` | `ATU::shipping()` returns `ShippingService`. |
| Shipping orchestration | `src/Support/ShippingService.php` | Cart/order context, `from()` / `to()`, `options()`, `select()`. |
| Rule matching | `src/Support/RuleEvaluator.php` | Per active courier: first active rule that matches wins (ordered by `priority` ascending). |
| Pricing | `src/Support/FeeCalculator.php` | Flat or per-kg fee, optional per-line weight when `applies_per_item` is true; tax from `tax_rate`. |
| Installer | `src/Support/Installer.php` | Copies stubs, env keys, injects marked blocks into `routes/api.php`, `routes/web.php`, and Flux sidebar. |
| Stubs | `src/stubs/**` | Config, views, controller, seeder, reference snippets (excluded from Composer classmap). |

Package constants and paths: `Vormia\ATUShipping\ATUShipping` (`VERSION`, `stubsPath()`, `migrationsPath()`).

## Runtime flow

1. **Context** — Call `ATU::shipping()->forCart($cart)` or `->forOrder($order)`. For orders, `from` / `to` are pre-filled when `OrderInterface::getOriginCountry()` / `getDestinationCountry()` return values; you can still chain `->from()` / `->to()` to override.
2. **Countries** — `options()` and `select()` return no results unless both `from` and `to` ISO 3166-1 alpha-2 codes are set.
3. **Rules** — For each **active** courier, rules are loaded with `fee`, ordered by `priority` ascending. The **first** rule that passes `RuleEvaluator::ruleMatches()` is the match for that courier.
4. **Fees** — `FeeCalculator` uses the related `Fee` row: `fee_type` `flat` uses `flat_fee`; `per_kg` multiplies `per_kg_fee` by total cart weight, or by each line’s `(weight × quantity)` when `applies_per_item` is true.
5. **Tax** — `tax = fee × tax_rate` (nullable/zero treated as no tax). Totals are rounded to two decimal places.
6. **Select** — Resolves courier by **name** (`Courier::where('name', …)`), re-runs evaluation, then persists a row in `atu_shipping_logs` when logging succeeds. Order id is taken from `getId()` if present, else public `id` property.

## Contracts

Implement these on your cart and order models (or adapters) so subtotals, weights, and line items are consistent with your storefront.

### `CartInterface`

- `getSubtotal(): float` — base-currency subtotal.
- `getTotalWeight(): float` — total weight (kg) for the cart.
- `getItems(): array` — each element should include `weight`, `quantity`, and may include `origin_country` (reserved for future use).

### `OrderInterface`

Extends the same monetary/weight/item shape as the cart, plus:

- `getDestinationCountry(): ?string`
- `getOriginCountry(): ?string`

## Rule constraints: evaluated vs stored

The `atu_shipping_rules` table includes columns for distance and carrier type. **`RuleEvaluator` only enforces:**

- `from_country` / `to_country` (when non-null, must equal the request)
- `min_cart_subtotal` / `max_cart_subtotal`
- `min_weight` / `max_weight`
- Active flag and courier association

**Not evaluated in v1 rule matching** (columns exist for admin/data and future logic):

- `min_distance` / `max_distance`
- `carrier_type`

If you need those constraints, extend `RuleEvaluator` (or wrap `ShippingService`) in your application.

## Currency

- Rule output currency comes from `rules.currency`, falling back to `config('a2_commerce.currency', 'USD')`.
- Migration `2026_02_17_100000_alter_atu_shipping_tables_for_4char_currency_codes` widens `currency` on `atu_shipping_rules` and `atu_shipping_logs` to four characters for ISO 4217 and extended codes (e.g. multi-currency setups).

`FeeCalculator::convertCurrency()` is a stub hook: it detects ATU Multi-Currency classes but does not convert amounts yet; amounts stay in the rule’s currency unless you add app-level conversion.

## Configuration (`config/atu-shipping.php`)

| Key | Purpose |
|-----|---------|
| `default_origin_country` | Default origin when your app does not pass one (used by stubs/controller patterns; shipping service still requires explicit `from()` unless you set it in app code). |
| `base_currency` | Documented baseline; fee display still follows per-rule `currency` / A2 Commerce default. |
| `enable_logging` | Present for forward compatibility. **Selection logging in `ShippingService` does not currently gate on this flag**; log writes run on successful `select()` unless an exception is caught (errors are written to Laravel’s log). |

## Installer markers

Uninstall removes only content between these markers:

- API: `// >>> ATU Shipping API Routes START` … `END` in `routes/api.php`
- Admin: `// >>> ATU Shipping Admin Routes START` … `END` in `routes/web.php`
- Sidebar: Blade comments `{{-- >>> ATU Shipping Sidebar START --}}` … `END` in matched sidebar paths

Admin routes use **Livewire 4** `Route::livewire()` (see [Livewire 4 docs](https://livewire.laravel.com/docs/4.x/components)).

## Testing and quality

PHPUnit tests live under `tests/` (e.g. `FeeCalculatorTest`, `ShippingServiceTest`, installer tests). Run from the package root:

```bash
./vendor/bin/phpunit
```

## Further reading

- [Laravel 12.x / 13.x](https://laravel.com/docs) — service container, migrations, Artisan.
- [Livewire 4](https://livewire.laravel.com/docs/4.x/components) — `Route::livewire` and full-page components.
- [A2Commerce](https://github.com/a2-atu/a2commerce) — commerce baseline this package builds on.
