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

Install the package via Composer:

```bash
composer require vormia-folks/atu-shipping
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=atu-shipping-config
```

Run the installation command to set up database tables:

```bash
php artisan atu:shipping:install
```

This will:
- Run all necessary migrations
- Publish configuration files
- Set up the database structure

## Configuration

After installation, you can configure the package in `config/atu-shipping.php`. The configuration file includes settings for:

- Default origin country
- Currency settings
- Logging preferences
- Integration with ATU Multi-Currency (optional)

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

## Commands

```bash
# Install the package (run migrations, publish configs)
php artisan atu:shipping:install

# Update the package
php artisan atu:shipping:update

# Uninstall the package
php artisan atu:shipping:uninstall

# Show help
php artisan atu:shipping:help
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
use Livewire\Volt\Volt;

Route::prefix('admin/atu/shipping')->name('admin.atu.shipping.')->middleware(['auth', 'admin'])->group(function () {
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

    {{-- Shipping Menu Item --}}
    <flux:navlist.item icon="truck" :href="route('admin.atu.shipping.couriers.index')"
        :current="request()->routeIs('admin.atu.shipping.*')" wire:navigate>
        {{ __('Shipping') }}
    </flux:navlist.item>

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
