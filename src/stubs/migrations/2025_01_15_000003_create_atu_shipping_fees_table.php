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
        Schema::create('atu_shipping_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('atu_shipping_rules')->onDelete('cascade');
            $table->enum('fee_type', ['flat', 'per_kg'])->comment('Fee calculation type');
            $table->decimal('flat_fee', 12, 2)->nullable()->comment('Flat fee amount (if fee_type is flat)');
            $table->decimal('per_kg_fee', 12, 2)->nullable()->comment('Per kg fee amount (if fee_type is per_kg)');
            $table->timestamps();

            $table->index('rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atu_shipping_fees');
    }
};
