<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('atu_shipping_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->nullable()->constrained('atu_shipping_couriers')->onDelete('set null');
            $table->foreignId('rule_id')->nullable()->constrained('atu_shipping_rules')->onDelete('set null');
            $table->unsignedBigInteger('order_id')->nullable()->comment('Order ID (references a2_ec_orders or similar)');
            
            // Context data
            $table->decimal('cart_subtotal', 12, 2)->comment('Cart subtotal at time of calculation');
            $table->decimal('total_weight', 10, 2)->comment('Total weight in kg');
            $table->char('from_country', 2)->nullable()->comment('Origin country code');
            $table->char('to_country', 2)->nullable()->comment('Destination country code');
            
            // Calculated values
            $table->decimal('shipping_fee', 12, 2)->comment('Base shipping fee');
            $table->decimal('shipping_tax', 12, 2)->comment('Shipping tax amount');
            $table->decimal('shipping_total', 12, 2)->comment('Total shipping cost (fee + tax)');
            $table->char('currency', 3)->comment('Currency code');
            $table->decimal('tax_rate', 5, 4)->nullable()->comment('Tax rate applied');
            
            // Additional context
            $table->json('context')->nullable()->comment('Additional context data');
            
            $table->timestamps();

            $table->index('courier_id');
            $table->index('rule_id');
            $table->index('order_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atu_shipping_logs');
    }
};
