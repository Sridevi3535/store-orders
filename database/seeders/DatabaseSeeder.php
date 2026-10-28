<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // [name, code, price, tax %, stock]
        $products = [
            ['Colgate Toothpaste', 'COLG-100', 50, 8, 40],
            ['Parle-G Biscuit', 'PARLE-G', 10, 8, 100],
            ['Bread', 'BREAD-01', 40, 5, 4],      // low stock
            ['Milk 1L', 'MILK-1L', 56, 5, 9],      // low stock
            ['Eggs (12)', 'EGGS-12', 84, 0, 2],    // low stock
            ['Basmati Rice 1kg', 'RICE-1KG', 120, 5, 60],
            ['Sunflower Oil 1L', 'OIL-1L', 150, 5, 25],
            ['Tea Powder 250g', 'TEA-250', 110, 5, 8], // low stock
            ['Bath Soap', 'SOAP-01', 35, 18, 70],
        ];

        foreach ($products as [$name, $code, $price, $tax, $stock]) {
            Product::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'price' => $price, 'tax_percent' => $tax, 'stock' => $stock]
            );
        }

        Customer::updateOrCreate(['email' => 'thomas@example.com'], ['name' => 'Thomas']);
        Customer::factory()->count(5)->create();
    }
}