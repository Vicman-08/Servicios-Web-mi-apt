<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categoryId = (string) Category::where('slug', 'tecnologia')->firstOrFail()->getKey();
        $products = [
            [
                'sku' => 'TEC-001',
                'name' => 'Teclado mecánico',
                'description' => 'Teclado compacto con iluminación y conexión USB.',
                'price' => '899.90',
                'stock' => 12,
                'tags' => ['teclado', 'computación', 'usb'],
                'attributes' => ['conexión' => 'USB', 'tipo' => 'Mecánico'],
            ],
            [
                'sku' => 'MOU-002',
                'name' => 'Mouse inalámbrico',
                'description' => 'Mouse ergonómico con receptor USB y batería recargable.',
                'price' => '449.50',
                'stock' => 20,
                'tags' => ['mouse', 'inalámbrico', 'computación'],
                'attributes' => ['conexión' => 'Inalámbrica', 'batería' => 'Recargable'],
            ],
            [
                'sku' => 'AUD-003',
                'name' => 'Audífonos USB',
                'description' => 'Audífonos con micrófono para clases y videollamadas.',
                'price' => '629.00',
                'stock' => 8,
                'tags' => ['audio', 'micrófono', 'usb'],
                'attributes' => ['conexión' => 'USB', 'micrófono' => 'Integrado'],
            ],
        ];

        foreach ($products as $data) {
            Product::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    ...$data,
                    'category_id' => $categoryId,
                    'images' => [],
                    'currency' => 'MXN',
                    'is_active' => true,
                ],
            );
        }
    }
}
