<?php

namespace App\Http\Controllers\Api\Atu;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Vormia\ATUShipping\Contracts\CartInterface;
use Vormia\ATUShipping\Contracts\OrderInterface;
use Vormia\ATUShipping\Facades\ATU;

/**
 * ATU Shipping API Controller
 *
 * This controller provides API endpoints for calculating and selecting shipping options.
 * Copy this file to app/Http/Controllers/Api/Atu/ShippingController.php
 */
class ShippingController
{
    /**
     * Calculate shipping options for a cart (POST).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calculate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required|integer',
            'from' => 'required|string|size:2',
            'to' => 'required|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cart = $this->resolveCart($request->input('cart_id'));
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found or does not implement CartInterface',
            ], 404);
        }

        try {
            $options = ATU::shipping()
                ->forCart($cart)
                ->from($request->input('from'))
                ->to($request->input('to'))
                ->options();

            return response()->json([
                'success' => true,
                'data' => $options,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate shipping options',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get shipping options for a cart (GET).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function options(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required|integer',
            'from' => 'required|string|size:2',
            'to' => 'required|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cart = $this->resolveCart($request->input('cart_id'));
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found or does not implement CartInterface',
            ], 404);
        }

        try {
            $options = ATU::shipping()
                ->forCart($cart)
                ->from($request->input('from'))
                ->to($request->input('to'))
                ->options();

            return response()->json([
                'success' => true,
                'data' => $options,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get shipping options',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Select shipping courier for an order (POST).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function select(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'courier' => 'required|string',
            'from' => 'nullable|string|size:2',
            'to' => 'nullable|string|size:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $order = $this->resolveOrder($request->input('order_id'));
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or does not implement OrderInterface',
            ], 404);
        }

        try {
            $shipping = ATU::shipping()->forOrder($order);

            // Set countries if provided, otherwise use order's default countries
            if ($request->has('from')) {
                $shipping->from($request->input('from'));
            }
            if ($request->has('to')) {
                $shipping->to($request->input('to'));
            }

            $result = $shipping->select($request->input('courier'));

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to select courier. No matching rule found or courier is not available.',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to select shipping courier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve cart model from ID.
     *
     * @param int $cartId
     * @return CartInterface|null
     */
    protected function resolveCart(int $cartId): ?CartInterface
    {
        // Replace with your actual Cart model class
        // Example: $cart = \App\Models\Cart::find($cartId);
        // if ($cart instanceof CartInterface) {
        //     return $cart;
        // }
        // return null;

        // For now, return null - implement based on your application's Cart model
        return null;
    }

    /**
     * Resolve order model from ID.
     *
     * @param int $orderId
     * @return OrderInterface|null
     */
    protected function resolveOrder(int $orderId): ?OrderInterface
    {
        // Replace with your actual Order model class
        // Example: $order = \App\Models\Order::find($orderId);
        // if ($order instanceof OrderInterface) {
        //     return $order;
        // }
        // return null;

        // For now, return null - implement based on your application's Order model
        return null;
    }
}
