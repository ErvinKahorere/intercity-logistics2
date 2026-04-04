<?php

namespace Database\Seeders;

use App\Models\PackageType;
use Illuminate\Database\Seeder;

class PackageTypeSeeder extends Seeder
{
    public function run(): void
    {
        $packageTypes = [
            'Documents',
            'Small Parcels',
            'Electronics',
            'Fragile Items',
            'Furniture',
            'Household Goods',
            'Office Equipment',
            'Retail Stock',
            'Food Supplies',
            'Refrigerated Goods',
            'Building Materials',
            'Agricultural Inputs',
            'Livestock Feed',
            'Livestock',
            'Motorbikes',
            'Vehicles',
            'Bulk Cargo',
            'Palletised Freight',
            'Industrial Equipment',
            'Heavy Machinery',
            'Mining Equipment',
            'Oversized Cargo',
            'Hazard-managed Cargo',
        ];

        foreach ($packageTypes as $type) {
            PackageType::firstOrCreate(['name' => $type]);
        }
    }
}
