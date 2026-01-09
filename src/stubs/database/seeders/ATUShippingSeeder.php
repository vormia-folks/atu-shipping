<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ATUShippingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed default couriers
        $couriers = [
            [
                'name' => 'DHL',
                'code' => 'dhl',
                'description' => 'DHL Express Shipping',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'FedEx',
                'code' => 'fedex',
                'description' => 'FedEx Shipping',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Standard Shipping',
                'code' => 'standard',
                'description' => 'Standard Shipping Service',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($couriers as $courier) {
            DB::table('atu_shipping_couriers')->insert($courier);
        }

        $this->command->info('✅ Default couriers seeded successfully!');
        $this->command->info('   You can now create shipping rules for these couriers.');
    }
}
