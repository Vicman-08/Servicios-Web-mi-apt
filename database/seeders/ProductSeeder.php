<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'sku' => 'TEC-001',
                'name' => 'Teclado mecánico',
                'description' => 'Teclado compacto con iluminación y conexión USB.',
                'price' => '899.90',
                'stock' => 12,
            ],
            [
                'sku' => 'MOU-002',
                'name' => 'Mouse inalámbrico',
                'description' => 'Mouse ergonómico con receptor USB y batería recargable.',
                'price' => '449.50',
                'stock' => 20,
            ],
            [
                'sku' => 'AUD-003',
                'name' => 'Audífonos USB',
                'description' => 'Audífonos con micrófono para clases y videollamadas.',
                'price' => '629.00',
                'stock' => 8,
            ],
        ];

        foreach ($products as $data) {
            Product::updateOrCreate(
                ['sku' => $data['sku']],
                [...$data, 'currency' => 'MXN', 'is_active' => true],
            );
        }
    }
}
