# Release Notes - v1.2.0

## Overview

This release introduces automatic API controller installation, improved namespace consistency, and enhanced developer experience with automatic file management during package installation and uninstallation.

## 🚀 New Features

### Automatic API Controller Installation
- **API Controller Stub** - Controller is now automatically copied to `app/Http/Controllers/Atu/ShippingController.php` during installation
- **Automatic Cleanup** - Controller is automatically removed during uninstallation
- No manual file copying required - the installer handles everything

### Improved Namespace Structure
- Changed controller namespace from `App\Http\Controllers\Api\Atu` to `App\Http\Controllers\Atu`
- More consistent and cleaner namespace structure
- Updated all route references to match the new namespace

## ✨ Improvements

### Developer Experience
- **Zero Manual Setup** - API controller is automatically installed and configured
- **Consistent Namespace** - Simplified namespace structure without the `Api` prefix
- **Automatic Management** - Install and uninstall commands handle controller lifecycle automatically

### Documentation
- Updated README.md to reflect automatic controller installation
- Updated route examples to use the new namespace
- Clarified that controller is automatically copied during installation

## 🔧 Technical Changes

### Controller Installation
- Added `src/stubs/controllers/Atu/ShippingController.php` - Controller stub that gets automatically copied
- Installer now copies controller to `app/Http/Controllers/Atu/ShippingController.php`
- Uninstaller automatically removes the controller file

### Namespace Updates
- Updated controller namespace from `App\Http\Controllers\Api\Atu` to `App\Http\Controllers\Atu`
- Updated `src/stubs/reference/routes-to-add.php` with new namespace
- Updated `src/Support/Installer.php` ROUTE_BLOCK constant with new namespace
- Updated `src/stubs/reference/shipping-controller.php` reference file

## 📝 Breaking Changes

### Namespace Change
- **Controller namespace changed** from `App\Http\Controllers\Api\Atu\ShippingController` to `App\Http\Controllers\Atu\ShippingController`
- If you manually copied the controller in v1.1.0, you'll need to:
  1. Delete the old controller at `app/Http/Controllers/Api/Atu/ShippingController.php`
  2. Update your routes to use the new namespace
  3. Run `php artisan atushipping:update` to get the new controller automatically

## 📦 Installation

To install v1.2.0:

```bash
composer require vormia-folks/atu-shipping:^1.2
php artisan atushipping:install
```

The API controller will be automatically copied to `app/Http/Controllers/Atu/ShippingController.php` during installation.

## 🔄 Migration from v1.1.0

### If you manually copied the controller:
1. Delete the old controller: `app/Http/Controllers/Api/Atu/ShippingController.php`
2. Update your routes in `routes/api.php` to use `\App\Http\Controllers\Atu\ShippingController::class`
3. Run `php artisan atushipping:update` to get the new controller automatically

### If you haven't set up the controller yet:
- Simply run `php artisan atushipping:install` or `php artisan atushipping:update`
- The controller will be automatically copied with the correct namespace

## 📚 Files Added

- `src/stubs/controllers/Atu/ShippingController.php` - API controller stub (automatically copied during installation)

## 📚 Files Updated

- `src/stubs/reference/routes-to-add.php` - Updated controller namespace
- `src/stubs/reference/shipping-controller.php` - Updated namespace and documentation
- `src/Support/Installer.php` - Updated ROUTE_BLOCK constant with new namespace and all endpoints
- `README.md` - Updated routes section and installation instructions

## 🙏 Thank You

Thank you for using ATU Shipping! If you encounter any issues or have suggestions, please open an issue on the repository.

---

**Release Date:** 2026-01-15  
**Version:** 1.2.0  
**Previous Version:** 1.1.0
