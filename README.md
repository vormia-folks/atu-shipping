# ATU Shipping

A Laravel package for rule-based shipping fee and shipping tax calculation engine. Designed to work independently or with optional integration to ATU Multi-Currency.

## Features

- **Rule-based shipping calculation** - Flexible rule system for calculating shipping fees
- **Multiple courier support** - Manage and calculate shipping for multiple couriers
- **Tax calculation** - Built-in shipping tax calculation support
- **Currency agnostic** - Works with base currency, optional multi-currency support
- **Cart and Order support** - Works with both cart and order contexts
- **Comprehensive logging** - Track shipping selections and calculations
- **Declarative rules** - Define only the constraints you need

## Requirements

- PHP ^8.2
- Laravel ^12.0 or ^13.0
- vormiaphp/vormia ^5.4
- a2-atu/a2commerce ^0.2.0

**Admin Livewire UI:** This package does not declare `livewire/livewire` in Composer. The copied admin views are **Livewire 4** single-file components. Install **Livewire 4** in your app—typically it is already required by **A2Commerce**, or you can add `livewire/livewire` yourself. See the [Livewire 4 documentation](https://livewire.laravel.com/docs/4.x/components).

**Optional:** For currency conversion helpers, install `vormia-folks/atu-multi-currency` (^2.0); see Composer `suggest`.

## Installation

Before installing ATU Shipping, ensure you have Laravel, Vormia, and A2Commerce installed. See the [A2Commerce installation guide](https://github.com/a2-atu/a2commerce) for detailed instructions on installing A2Commerce and its dependencies.

### Step 1: Install ATU Shipping

```bash
composer require vormia-folks/atu-shipping
```

### Step 2: Run ATU Shipping Installation

```bash
php artisan atushipping:install
```

This will automatically install ATU Shipping with all files and configurations:

**Automatically Installed:**

- ✅ Migrations are auto-loaded from the package’s `database/migrations` directory (via `loadMigrationsFrom()` — nothing copied into your app’s `database/migrations`)
- ✅ Seeder file copied to `database/seeders/ATUShippingSeeder.php`
- ✅ Configuration file copied to `config/atu-shipping.php`
- ✅ Reference `ShippingController` copied to `app/Http/Controllers/Atu/ShippingController.php`
- ✅ Admin Livewire views copied to `resources/views/livewire/admin/atu/shipping/**`
- ✅ Environment variables added to `.env` and `.env.example`
- ✅ Live API routes added to `routes/api.php` (between START/END markers)
- ✅ Live admin routes injected into the `auth` middleware group of `routes/web.php`
- ✅ Flux sidebar menu entries injected into your admin `sidebar.blade.php` (if found)

**Installation Options:**

- `--no-overwrite`: Keep existing files instead of replacing them
- `--skip-env`: Leave `.env` files untouched

**Example:**

```bash
# Install without overwriting existing files
php artisan atushipping:install --no-overwrite

# Install without modifying .env files
php artisan atushipping:install --skip-env
```

### Step 3: Run Migrations and Seeders

Migrations are loaded automatically from inside the package (no copies in
`database/migrations`), so a plain `php artisan migrate` is all you need. The
installer prompts to run both for you:

```bash
# Run migrations (package-shipped, auto-loaded)
php artisan migrate

# Run seeders to create default couriers
php artisan db:seed --class=ATUShippingSeeder
```

**Upgrading from older releases:** If an earlier version of this package copied migration files into your app’s `database/migrations/` directory, remove those duplicate `*atu_shipping*` PHP files after you upgrade. Laravel registers migrations by basename; keeping both the app copies and the package’s `loadMigrationsFrom` path can cause duplicate-name conflicts.

### Currency columns (3 to 4 characters)

A later migration, `2026_02_17_100000_alter_atu_shipping_tables_for_4char_currency_codes.php`, widens the `currency` column on `atu_shipping_rules` and `atu_shipping_logs` from 3 to 4 characters so you can store ISO 4217 codes and extended 4-character codes (for example with ATU Multi-Currency). It ships inside the package and runs automatically on `php artisan migrate`.

**If production was already altered manually** (columns are already `CHAR(4)` but this migration never ran), add a row to the `migrations` table so Laravel skips it: set `migration` to `2026_02_17_100000_alter_atu_shipping_tables_for_4char_currency_codes` and use a `batch` value consistent with your app.

## Configuration

After installation, you can configure the package in `config/atu-shipping.php`:

```php
return [
    'default_origin_country' => env('ATU_SHIPPING_DEFAULT_ORIGIN_COUNTRY', 'ZA'),
    'base_currency' => env('ATU_SHIPPING_BASE_CURRENCY', config('a2_commerce.currency', 'USD')),
    'enable_logging' => env('ATU_SHIPPING_ENABLE_LOGGING', true),
];
```

The configuration file includes settings for:

- Default origin country
- Base currency for shipping calculations
- Logging-related key (reserved; see [Logging](#logging))
- Optional Multi-Currency is a separate Composer package (`suggest`)

## Environment Variables

The following environment variables are added to your `.env` file during installation:

```env
# ATU Shipping Configuration
ATU_SHIPPING_DEFAULT_ORIGIN_COUNTRY=ZA
ATU_SHIPPING_BASE_CURRENCY=USD
ATU_SHIPPING_ENABLE_LOGGING=true
```

- `ATU_SHIPPING_DEFAULT_ORIGIN_COUNTRY`: Default origin country code (ISO 3166-1 alpha-2) if not specified when calculating shipping
- `ATU_SHIPPING_BASE_CURRENCY`: Base currency code for shipping calculations. Falls back to A2 Commerce currency if not set
- `ATU_SHIPPING_ENABLE_LOGGING`: Reserved for future use (default: `true`). Selection rows are written when `select()` runs; the flag does not disable logging in the current release

## Usage

### Basic Usage

Get shipping options for a cart:

```php
use Vormia\ATUShipping\Facades\ATU;

$options = ATU::shipping()
    ->forCart($cart)
    ->from('ZA')  // Origin country (ISO 3166-1 alpha-2)
    ->to('KE')    // Destination country
    ->options();

// Returns array of shipping options:
// [
//   [
//     'courier' => 'DHL',
//     'fee' => 755.0,
//     'tax' => 120.8,
//     'total' => 875.8,
//     'currency' => 'ZAR',
//     'rule_id' => 12,
//     'tax_rate' => 0.16
//   ]
// ]
```

### With Order Context

```php
$shipping = ATU::shipping()
    ->forOrder($order)
    ->from('ZA')
    ->to('KE');

// Get options
$options = $shipping->options();

// Select a courier (logs the selection)
$selected = $shipping->select('DHL');
```

### Rule Evaluation

The package uses a rule-based system where:

- Rules are evaluated by priority (ascending)
- All defined constraints must match for a rule to apply
- Undefined constraints are ignored
- Rules can be applied per cart or per item

### Supported Rule Constraints

**Evaluated at runtime** (see `RuleEvaluator`):

- From country / to country (nullable = wildcard)
- Min/max cart subtotal
- Min/max total weight
- Per-item vs cart-level weight for **per-kg** fees (`applies_per_item` on the rule)

**Stored on the rule** but **not** used by the current evaluator (reserved for future logic or custom extensions):

- Min/max distance
- Carrier type (e.g. bike, van, pickup)

Details: [docs/atu-shipping.md](docs/atu-shipping.md).

## Database Structure

The package creates the following tables:

- `atu_shipping_couriers` - Courier information
- `atu_shipping_rules` - Shipping rules
- `atu_shipping_fees` - Fee structures
- `atu_shipping_logs` - Shipping selection logs

## Available Commands

### Install Command

Install the package with all necessary files and configurations:

```bash
php artisan atushipping:install
```

**Options:**

- `--skip-env`: Do not modify .env files
- `--no-overwrite`: Skip existing files instead of replacing

**Automatically Installed:**

- ✅ Migrations auto-loaded from the package’s `database/migrations` (nothing copied into your app’s `database/migrations`)
- ✅ Seeder file copied to `database/seeders/ATUShippingSeeder.php`
- ✅ Configuration file copied to `config/atu-shipping.php`
- ✅ Reference `ShippingController` copied to `app/Http/Controllers/Atu/ShippingController.php`
- ✅ Admin Livewire views copied to `resources/views/livewire/admin/atu/shipping/**`
- ✅ Environment variables added to `.env` and `.env.example`
- ✅ Live API routes added to `routes/api.php`
- ✅ Live admin routes injected into `routes/web.php` (inside the auth middleware group)
- ✅ Flux sidebar menu entries injected into your sidebar blade (if found)

**Example:**

```bash
# Install without overwriting existing files
php artisan atushipping:install --no-overwrite

# Install without modifying .env files
php artisan atushipping:install --skip-env
```

### Update Command

Update package files and configurations, refresh migrations and seeders, clear caches:

```bash
php artisan atushipping:update
```

**Options:**

- `--skip-env`: Do not modify .env files
- `--force`: Skip confirmation prompts

This command will:

- Update all package files and stubs
- Update environment files (if not skipped)
- Ensure shipping routes are in `routes/api.php`
- Clear all application caches

**Example:**

```bash
# Update without confirmation
php artisan atushipping:update --force

# Update without modifying .env files
php artisan atushipping:update --skip-env
```

### Uninstall Command

Remove all package files and configurations:

```bash
php artisan atushipping:uninstall
```

**Options:**

- `--keep-env`: Preserve environment variables
- `--force`: Skip confirmation prompts

**⚠️ Warning:** This will remove all ATU Shipping files and optionally drop database tables. A backup will be created in `storage/app/atushipping-final-backup-{timestamp}/`.

**Example:**

```bash
# Uninstall without confirmation
php artisan atushipping:uninstall --force

# Uninstall but keep environment variables
php artisan atushipping:uninstall --keep-env
```

**Note:** The uninstall command will:

- Remove all copied files and stubs
- Remove marked blocks from `routes/api.php`, `routes/web.php`, and the Flux sidebar (when present)
- Optionally drop `atu_shipping_*` tables and prune this package’s rows from the `migrations` table (migration PHP stays in `vendor/` until you run `composer remove`)
- Optionally remove environment variables
- Create a backup before removal
- Clear application caches

After uninstalling, you'll need to manually remove the package from composer:

```bash
composer remove vormia-folks/atu-shipping
```

### Help Command

Display help information and usage examples:

```bash
php artisan atushipping:help
```

## UI Installation

After installing the base package, you can set up the admin UI components for managing shipping couriers, rules, and logs. The package includes reference files and view stubs that show you exactly what routes, menu items, and views to add.

### View Files

The package includes Livewire view files in `vendor/vormia-folks/atu-shipping/src/stubs/resources/views/livewire/admin/atu/shipping/`:

- **Couriers**: `couriers/index.blade.php`, `couriers/create.blade.php`, `couriers/edit.blade.php`
- **Rules**: `rules/index.blade.php`, `rules/create.blade.php`, `rules/edit.blade.php`
- **Logs**: `logs/index.blade.php`

Copy these files to your `resources/views/livewire/admin/atu/shipping/` directory to use the admin UI.

### Reference Files

The package provides reference files in `vendor/vormia-folks/atu-shipping/src/stubs/reference/`:

- **`admin-routes-to-add.php`** - Admin routes for managing couriers, rules, and logs
- **`routes-to-add.php`** - API routes for calculating shipping options (optional)
- **`sidebar-menu-to-add.blade.php`** - Sidebar menu items for the admin panel

### Manual Admin Routes Setup

Add the following routes to your admin routes file (e.g., `routes/admin.php` or `routes/web.php` with admin middleware):

```php
Route::prefix('admin/atu/shipping')->name('admin.atu.shipping.')->group(function () {
    // Couriers — second argument is the Livewire view name (see resources/views/livewire/...)
    Route::livewire('couriers', 'admin.atu.shipping.couriers.index')->name('couriers.index');
    Route::livewire('couriers/create', 'admin.atu.shipping.couriers.create')->name('couriers.create');
    Route::livewire('couriers/{id}/edit', 'admin.atu.shipping.couriers.edit')->name('couriers.edit');

    // Rules
    Route::livewire('rules', 'admin.atu.shipping.rules.index')->name('rules.index');
    Route::livewire('rules/create', 'admin.atu.shipping.rules.create')->name('rules.create');
    Route::livewire('rules/{id}/edit', 'admin.atu.shipping.rules.edit')->name('rules.edit');

    // Logs
    Route::livewire('logs', 'admin.atu.shipping.logs.index')->name('logs.index');
});
```

**Note:** `Route::livewire()` is provided by Livewire 4. See [Livewire routing](https://livewire.laravel.com/docs/4.x/navigate) and [components](https://livewire.laravel.com/docs/4.x/components).

### Manual API Routes Setup (Optional)

If you need API endpoints for calculating shipping options, add these routes to `routes/api.php`:

```php
Route::prefix('atu/shipping')->group(function () {
    // Calculate shipping options for a cart
    Route::post('/calculate', [
        \App\Http\Controllers\Atu\ShippingController::class,
        'calculate'
    ])->name('api.shipping.calculate');

    // Get shipping options for a cart
    Route::get('/options', [
        \App\Http\Controllers\Atu\ShippingController::class,
        'options'
    ])->name('api.shipping.options');

    // Select shipping courier for an order
    Route::post('/select', [
        \App\Http\Controllers\Atu\ShippingController::class,
        'select'
    ])->name('api.shipping.select');
});
```

**Note:** The `ShippingController` class is automatically copied to `app/Http/Controllers/Atu/ShippingController.php` during installation. You'll need to implement the `resolveCart()` and `resolveOrder()` methods based on your application's Cart and Order models.

#### API Endpoints

##### POST `/atu/shipping/calculate`

Calculate shipping options for a cart.

**Request Body:**

```json
{
  "cart_id": 123,
  "from": "ZA",
  "to": "KE"
}
```

**Response (200 OK):**

```json
{
  "success": true,
  "data": [
    {
      "courier": "DHL",
      "fee": 755.0,
      "tax": 120.8,
      "total": 875.8,
      "currency": "ZAR",
      "rule_id": 12,
      "tax_rate": 0.16
    }
  ]
}
```

**Error Response (422 Validation Error):**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "cart_id": ["The cart id field is required."],
    "from": ["The from field is required."]
  }
}
```

##### GET `/atu/shipping/options`

Get shipping options for a cart (same as calculate but via GET request).

**Query Parameters:**

- `cart_id` (required): Cart ID
- `from` (required): Origin country code (ISO 3166-1 alpha-2)
- `to` (required): Destination country code (ISO 3166-1 alpha-2)

**Example Request:**

```
GET /atu/shipping/options?cart_id=123&from=ZA&to=KE
```

**Response:** Same format as POST `/calculate`

##### POST `/atu/shipping/select`

Select a shipping courier for an order and log the selection.

**Request Body:**

```json
{
  "order_id": 456,
  "courier": "DHL",
  "from": "ZA",
  "to": "KE"
}
```

**Note:** `from` and `to` are optional. If not provided, the order's default origin and destination countries will be used (if available via `OrderInterface` methods).

**Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "fee": 755.0,
    "tax": 120.8,
    "total": 875.8,
    "currency": "ZAR"
  }
}
```

**Error Response (400 Bad Request):**

```json
{
  "success": false,
  "message": "Failed to select courier. No matching rule found or courier is not available."
}
```

### Sidebar Menu

The installer automatically injects the menu below into your Flux sidebar blade
(at `resources/views/layouts/app/sidebar.blade.php` or
`resources/views/components/layouts/app/sidebar.blade.php`). The injected block is
fenced by markers so `atushipping:uninstall` can remove it cleanly.

If your sidebar lives elsewhere, copy this manually from
`vendor/vormia-folks/atu-shipping/src/stubs/reference/sidebar-menu-to-add.blade.php`:

```blade
{{-- >>> ATU Shipping Sidebar START --}}
<flux:sidebar.item icon="truck" :href="route('admin.atu.shipping.couriers.index')" wire:navigate>
    {{ __('Shipping couriers') }}
</flux:sidebar.item>
<flux:sidebar.item icon="rectangle-stack" :href="route('admin.atu.shipping.rules.index')" wire:navigate>
    {{ __('Shipping rules') }}
</flux:sidebar.item>
<flux:sidebar.item icon="clipboard-document-list" :href="route('admin.atu.shipping.logs.index')" wire:navigate>
    {{ __('Shipping logs') }}
</flux:sidebar.item>
{{-- >>> ATU Shipping Sidebar END --}}
```

The package uses `flux:sidebar.item` (not `flux:navlist.item`) to match the
default Flux admin sidebar from `vormiaphp/ui-livewireflux-admin`.

**Reference Files:**

- Admin Routes: `vendor/vormia-folks/atu-shipping/src/stubs/reference/admin-routes-to-add.php`
- API Routes: `vendor/vormia-folks/atu-shipping/src/stubs/reference/routes-to-add.php`
- API Controller: `vendor/vormia-folks/atu-shipping/src/stubs/reference/shipping-controller.php`
- Sidebar Menu: `vendor/vormia-folks/atu-shipping/src/stubs/reference/sidebar-menu-to-add.blade.php`

## Contracts

The package uses interfaces for cart and order contexts:

- `Vormia\ATUShipping\Contracts\CartInterface`
- `Vormia\ATUShipping\Contracts\OrderInterface`

Your cart and order models should implement these interfaces to work with ATU Shipping.

## Core Principles

1. **A2 Commerce remains authoritative** - ATU Shipping never mutates cart, product, or order totals directly
2. **Rules are declarative** - A rule may define only the constraints it needs
3. **Evaluation is deterministic** - Same inputs always return the same result
4. **Currency-agnostic** - Uses base currency by default, optionally delegates to ATU Multi-Currency
5. **Ephemeral calculations** - All calculations are ephemeral until checkout

## Logging

When you call `select()` on the shipping service, a row is written to `atu_shipping_logs` on success (failures are caught and reported to Laravel’s log). Typical call sites:

- Checkout after the customer chooses a courier
- Any custom flow that finalizes shipping via `select()`

The `enable_logging` entry in `config/atu-shipping.php` is kept for forward compatibility; **today’s package does not skip logging based on that flag**. If you need toggled logging, wrap `select()` in your application or extend `ShippingService`.

Logs are stored in the `atu_shipping_logs` table and include:

- Courier and rule used
- Cart/order context
- Calculated fees and taxes
- Country information

## Integration with ATU Multi-Currency

Optional package `vormia-folks/atu-multi-currency` is listed under Composer `suggest`. The shipping fee calculator includes a **conversion hook** (`FeeCalculator::convertCurrency()`) that detects Multi-Currency classes but does **not** yet apply live conversion in core flows—amounts stay in the rule’s currency (or A2 Commerce default). Implement conversion in your app or extend the calculator if you need cross-currency totals. See [docs/atu-shipping.md](docs/atu-shipping.md#currency).

## Non-Goals

ATU Shipping does NOT:

- Split shipments (v1)
- Call courier APIs directly
- Track shipments
- Persist shipping data into A2 core tables

## Uninstallation

To completely remove the package:

```bash
# Uninstall package files and optionally drop tables
php artisan atushipping:uninstall

# Remove from composer
composer remove vormia-folks/atu-shipping
```

**Note:** The uninstall command will:

- Remove all copied files and stubs
- Remove marked blocks from `routes/api.php`, `routes/web.php`, and the Flux sidebar (when present)
- Optionally drop `atu_shipping_*` tables and prune this package’s migration rows (files under `vendor/` remain until `composer remove`)
- Optionally remove environment variables
- Create a backup before removal

**Backup Location:** A final backup is created in `storage/app/atushipping-final-backup-{timestamp}/` containing:

- Configuration file (`config/atu-shipping.php`)
- Routes file (`routes/api.php`)
- Environment file (`.env`)

## License

MIT

## Documentation

For detailed implementation guides and architecture documentation, see:

- **[Implementation guide](docs/atu-shipping.md)** — runtime flow, contracts, rule evaluation, currency columns, installer markers, and known limitations
- **[Package creation guide](docs/package-creation-guide.md)** — how this repository is structured for other ATU-style Laravel packages
- **[A2Commerce](https://github.com/a2-atu/a2commerce)** — base commerce functionality and installation

## Troubleshooting

### Migration Errors

If migrations fail:

- Ensure all A2Commerce migrations have been run first
- Check that the database connection is configured correctly
- Verify foreign key constraints are supported

### No Shipping Options Returned

If `options()` returns an empty array:

- Verify that couriers are active in the database
- Check that rules are configured and active
- Ensure origin and destination countries are set correctly
- Verify that cart/order implements the required interfaces (`CartInterface` or `OrderInterface`)

### Rule Not Matching

If a rule is not being applied:

- Check rule priority (lower numbers are evaluated first)
- Verify all rule constraints match (country, weight, subtotal, etc.)
- Ensure the rule is active
- Check that the courier associated with the rule is active

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For issues, questions, or contributions:

- Read [docs/atu-shipping.md](docs/atu-shipping.md) for behaviour and extension points
- Review [A2Commerce](https://github.com/a2-atu/a2commerce) for base functionality
- Open an issue on the package repository

## Version

Package constant `Vormia\ATUShipping\ATUShipping::VERSION` tracks the in-code release label (use Git tags as the source of truth for Composer installs; this repository does not duplicate a `version` field in `composer.json`).

---

**Built with ❤️ for the A2 Commerce ecosystem**
