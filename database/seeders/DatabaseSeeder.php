<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['admin', 'manager', 'customer'] as $role) {
            User::updateOrCreate(
                ['email' => "{$role}@example.com"],
                ['name' => ucfirst($role), 'password' => 'password', 'role' => $role],
            );
        }

        // Starter menu; admins can add/remove categories via /api/categories.
        $categories = [
            'blazer' => 'Blazer',
            'shirt' => 'Shirt',
            't-shirt' => 'T-Shirt',
            'polo' => 'Polo',
            'pant' => 'Pant',
            'panjabi' => 'Panjabi',
            'accessories' => 'Accessories',
        ];

        foreach ($categories as $slug => $name) {
            Category::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        $products = [
            ['blazer', 'Classic Navy Blazer', 6500, 7500, 12, true],
            ['blazer', 'Slim Fit Grey Blazer', 5800, null, 8, false],
            ['shirt', 'Oxford Cotton Shirt', 1800, 2200, 30, true],
            ['shirt', 'Linen Casual Shirt', 1650, null, 25, false],
            ['t-shirt', 'Basic Crew Neck T-Shirt', 550, null, 60, true],
            ['t-shirt', 'Graphic Print T-Shirt', 650, 800, 40, false],
            ['polo', 'Pique Polo Shirt', 1200, null, 35, true],
            ['polo', 'Striped Polo Shirt', 1350, 1500, 20, false],
            ['pant', 'Chino Pant', 1900, null, 28, true],
            ['pant', 'Formal Trouser', 2100, 2500, 18, false],
            ['panjabi', 'Embroidered Cotton Panjabi', 2800, 3200, 15, true],
            ['panjabi', 'Silk Blend Panjabi', 4200, null, 10, false],
            ['accessories', 'Leather Belt', 950, null, 50, true],
            ['accessories', 'Classic Analog Watch', 3500, 4000, 0, false],
        ];

        foreach ($products as $i => [$slug, $name, $price, $compare, $stock, $featured]) {
            Product::updateOrCreate(['sku' => sprintf('IK-%04d', $i + 1)], [
                'category_id' => Category::where('slug', $slug)->value('id'),
                'name' => $name,
                'description' => "{$name} from the IK Home collection.",
                'price' => $price,
                'compare_price' => $compare,
                'stock' => $stock,
                'is_featured' => $featured,
            ]);
        }
    }
}
