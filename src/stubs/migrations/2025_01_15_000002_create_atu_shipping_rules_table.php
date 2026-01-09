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
        Schema::create('atu_shipping_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained('atu_shipping_couriers')->onDelete('cascade');
            $table->string('name')->comment('Rule name/description');
            $table->integer('priority')->default(0)->comment('Lower priority = evaluated first');
            
            // Country constraints
            $table->char('from_country', 2)->nullable()->comment('Origin country code (ISO 3166-1 alpha-2)');
            $table->char('to_country', 2)->nullable()->comment('Destination country code (ISO 3166-1 alpha-2)');
            
            // Cart subtotal constraints
            $table->decimal('min_cart_subtotal', 12, 2)->nullable()->comment('Minimum cart subtotal');
            $table->decimal('max_cart_subtotal', 12, 2)->nullable()->comment('Maximum cart subtotal');
            
            // Weight constraints
            $table->decimal('min_weight', 10, 2)->nullable()->comment('Minimum weight in kg');
            $table->decimal('max_weight', 10, 2)->nullable()->comment('Maximum weight in kg');
            
            // Distance constraints (optional)
            $table->decimal('min_distance', 10, 2)->nullable()->comment('Minimum distance in km');
            $table->decimal('max_distance', 10, 2)->nullable()->comment('Maximum distance in km');
            
            // Carrier type (optional)
            $table->string('carrier_type', 50)->nullable()->comment('Carrier type (bike, van, pickup, etc.)');
            
            // Rule behavior
            $table->boolean('applies_per_item')->default(false)->comment('If true, evaluate per cart line; if false, use total cart weight');
            
            // Tax and currency
            $table->decimal('tax_rate', 5, 4)->nullable()->comment('Tax rate (e.g., 0.16 for 16%)');
            $table->char('currency', 3)->nullable()->comment('Currency code for this rule (defaults to base currency)');
            
            // Status
            $table->boolean('is_active')->default(true)->comment('Whether rule is active');
            $table->timestamps();

            $table->index(['courier_id', 'is_active', 'priority']);
            $table->index('from_country');
            $table->index('to_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atu_shipping_rules');
    }
};
