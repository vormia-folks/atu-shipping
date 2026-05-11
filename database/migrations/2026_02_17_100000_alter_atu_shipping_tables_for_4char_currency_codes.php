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
        Schema::table('atu_shipping_rules', function (Blueprint $table) {
            if (Schema::hasColumn('atu_shipping_rules', 'currency')) {
                $table->char('currency', 4)->nullable()->change();
            }
        });

        Schema::table('atu_shipping_logs', function (Blueprint $table) {
            if (Schema::hasColumn('atu_shipping_logs', 'currency')) {
                $table->char('currency', 4)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('atu_shipping_rules', function (Blueprint $table) {
            if (Schema::hasColumn('atu_shipping_rules', 'currency')) {
                $table->char('currency', 3)->nullable()->change();
            }
        });

        Schema::table('atu_shipping_logs', function (Blueprint $table) {
            if (Schema::hasColumn('atu_shipping_logs', 'currency')) {
                $table->char('currency', 3)->change();
            }
        });
    }
};
