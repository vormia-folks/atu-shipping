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
- Laravel ^12.0
- vormiaphp/vormia ^4.4
- a2-atu/a2commerce ^0.1.6

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

- ✅ All migration files copied to `database/migrations`
- ✅ Seeder file copied to `database/seeders`
- ✅ Configuration file copied to `config/atu-shipping.php`
- ✅ Environment variables added to `.env` and `.env.example`
- ✅ Shipping routes (commented out) added to `routes/api.php`

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

The installation command will prompt you to run migrations and seeders. You can also run them manually:

```bash
# Run migrations
php artisan migrate

# Run seeders to create default couriers
php artisan db:seed --class=ATUShippingSeeder
```

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
- Logging preferences
- Integration with ATU Multi-Currency (optional)

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
- `ATU_SHIPPING_ENABLE_LOGGING`: Whether to log shipping selections (default: `true`). Logging happens at checkout or when manually triggered

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

- From country
- To country
- Min/max cart subtotal
- Min/max total weight
- Min/max distance (if provided)
- Carrier type (bike, van, pickup)
- Per-item or cart-level application

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

- ✅ All migration files copied to `database/migrations`
- ✅ Seeder file copied to `database/seeders`
- ✅ Configuration file copied to `config/atu-shipping.php`
- ✅ Environment variables added to `.env` and `.env.example`
- ✅ Shipping routes (commented out) added to `routes/api.php`

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
- Remove routes from `routes/api.php`
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
    // Couriers
    Volt::route('couriers', 'admin.atu.shipping.couriers.index')->name('couriers.index');
    Volt::route('couriers/create', 'admin.atu.shipping.couriers.create')->name('couriers.create');
    Volt::route('couriers/{id}/edit', 'admin.atu.shipping.couriers.edit')->name('couriers.edit');

    // Rules
    Volt::route('rules', 'admin.atu.shipping.rules.index')->name('rules.index');
    Volt::route('rules/create', 'admin.atu.shipping.rules.create')->name('rules.create');
    Volt::route('rules/{id}/edit', 'admin.atu.shipping.rules.edit')->name('rules.edit');

    // Logs
    Volt::route('logs', 'admin.atu.shipping.logs.index')->name('logs.index');
});
```

**Note:** If you have configured your own starterkit, make sure to add `use Livewire\Volt\Volt;` at the top of your routes file.

### Manual API Routes Setup (Optional)

If you need API endpoints for calculating shipping options, add these routes to `routes/api.php`:

```php
Route::prefix('atu/shipping')->group(function () {
    // Calculate shipping options for a cart
    Route::post('/calculate', [
        \App\Http\Controllers\ATU\Shipping\ShippingController::class,
        'calculate'
    ])->name('api.shipping.calculate');

    // Get shipping options for a cart
    Route::get('/options', [
        \App\Http\Controllers\ATU\Shipping\ShippingController::class,
        'options'
    ])->name('api.shipping.options');

    // Select shipping courier for an order
    Route::post('/select', [
        \App\Http\Controllers\ATU\Shipping\ShippingController::class,
        'select'
    ])->name('api.shipping.select');
});
```

**Note:** You'll need to create the `ShippingController` class to handle these endpoints. The controller should use the `ATU::shipping()` facade to perform calculations.

### Manual Sidebar Menu Setup

Add the following menu items to your admin sidebar (e.g., `resources/views/components/layouts/app/sidebar.blade.php`):

```blade
@if (auth()->user()?->isAdminOrSuperAdmin())
    <hr />

    {{-- Shipping Couriers Submenu --}}
    <flux:navlist.item icon="truck" :href="route('admin.atu.shipping.couriers.index')"
        :current="request()->routeIs('admin.atu.shipping.couriers.*')" wire:navigate>
        {{ __('Couriers') }}
    </flux:navlist.item>

    {{-- Shipping Rules Submenu --}}
    <flux:navlist.item icon="document-text" :href="route('admin.atu.shipping.rules.index')"
        :current="request()->routeIs('admin.atu.shipping.rules.*')" wire:navigate>
        {{ __('Shipping Rules') }}
    </flux:navlist.item>

    {{-- Shipping Logs Submenu --}}
    <flux:navlist.item icon="document-duplicate" :href="route('admin.atu.shipping.logs.index')"
        :current="request()->routeIs('admin.atu.shipping.logs.index')" wire:navigate>
        {{ __('Shipping Logs') }}
    </flux:navlist.item>
@endif
```

**Reference Files:**

- Admin Routes: `vendor/vormia-folks/atu-shipping/src/stubs/reference/admin-routes-to-add.php`
- API Routes: `vendor/vormia-folks/atu-shipping/src/stubs/reference/routes-to-add.php`
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

Shipping selections are logged automatically when:

- A courier is selected at checkout
- Manual admin recalculation occurs
- Reporting is generated

Logs are stored in the `atu_shipping_logs` table and include:

- Courier and rule used
- Cart/order context
- Calculated fees and taxes
- Country information

## Integration with ATU Multi-Currency

If ATU Multi-Currency is installed and configured, the package will automatically use it for currency conversion. Otherwise, it falls back to the base currency.

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
- Remove routes from `routes/api.php`
- Optionally drop database tables (with confirmation)
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

- **Build Guide**: `docs/atu-shipping.md` - Authoritative implementation guide and technical documentation
- **Package Creation Guide**: `docs/package-creation-guide.md` - Guide for creating similar packages
- **A2Commerce Documentation**: See [A2Commerce GitHub repository](https://github.com/a2-atu/a2commerce) for base functionality

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

- Check the documentation in `docs/atu-shipping.md`
- Review [A2Commerce documentation](https://github.com/a2-atu/a2commerce) for base functionality
- Open an issue on the package repository

## Version

Current version: **0.1.0**

---

**Built with ❤️ for the A2 Commerce ecosystem**
