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
        Schema::create('atu_shipping_couriers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Courier name (e.g., DHL, FedEx)');
            $table->string('code', 50)->unique()->comment('Unique courier code');
            $table->text('description')->nullable()->comment('Courier description');
            $table->boolean('is_active')->default(true)->comment('Whether courier is active');
            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atu_shipping_couriers');
    }
};
