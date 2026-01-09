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

## Support

For issues, questions, or contributions, please refer to the [documentation](docs/atu-shipping.md) or open an issue on the repository.

## Version

Current version: **0.1.0**
