# Release Notes - v1.1.0

## Overview

This release adds API endpoints for shipping calculations, providing RESTful access to shipping functionality for frontend applications and external integrations.

## 🚀 New Features

### API Endpoints
- **POST `/atu/shipping/calculate`** - Calculate shipping options for a cart
- **GET `/atu/shipping/options`** - Get shipping options for a cart (alternative to calculate)
- **POST `/atu/shipping/select`** - Select a shipping courier for an order and log the selection

### API Controller
- New `ShippingController` reference implementation at `src/stubs/reference/shipping-controller.php`
- Controller located at `\App\Http\Controllers\Api\Atu\ShippingController`
- Full validation, error handling, and JSON response formatting
- Support for both cart and order contexts via API

## ✨ Improvements

### Documentation
- Added comprehensive API documentation with request/response examples
- Updated routes reference to reflect new controller namespace
- Added API endpoint documentation with JSON examples
- Included error response documentation for all endpoints

### Developer Experience
- Reference controller stub with clear implementation guidance
- Example code for resolving Cart and Order models
- Consistent JSON response format across all endpoints

## 🔧 Technical Changes

- Updated routes reference file to use `\App\Http\Controllers\Api\Atu\ShippingController` namespace
- Added API controller stub with three endpoint methods
- Enhanced README with API usage examples and endpoint documentation

## 📝 API Endpoints

### POST `/atu/shipping/calculate`
Calculate shipping options for a cart. Accepts `cart_id`, `from` (country code), and `to` (country code) in request body.

### GET `/atu/shipping/options`
Get shipping options for a cart via GET request. Accepts same parameters as query string.

### POST `/atu/shipping/select`
Select a shipping courier for an order. Accepts `order_id`, `courier` (required), and optional `from`/`to` country codes.

All endpoints return standardized JSON responses with success/error indicators and appropriate HTTP status codes.

## 📦 Installation

To use the API endpoints:

1. Copy the controller stub from `vendor/vormia-folks/atu-shipping/src/stubs/reference/shipping-controller.php` to `app/Http/Controllers/Api/Atu/ShippingController.php`

2. Implement the `resolveCart()` and `resolveOrder()` methods based on your application's Cart and Order models

3. Add the API routes to `routes/api.php` (see reference file at `vendor/vormia-folks/atu-shipping/src/stubs/reference/routes-to-add.php`)

## 🔄 Migration from v1.0.0

No breaking changes. This is a backward-compatible release. The API endpoints are optional and do not affect existing functionality.

To add API support:
- Copy the controller stub and implement the model resolution methods
- Add the routes to your `routes/api.php` file

## 📚 Files Added

- `src/stubs/reference/shipping-controller.php` - API controller reference implementation

## 📚 Files Updated

- `src/stubs/reference/routes-to-add.php` - Updated controller namespace
- `README.md` - Added API documentation section with examples

## 🙏 Thank You

Thank you for using ATU Shipping! If you encounter any issues or have suggestions, please open an issue on the repository.

---

**Release Date:** 2026-01-09  
**Version:** 1.1.0  
**Previous Version:** 1.0.0
