<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::updateOrCreate(
            ['slug' => 'tecnologia'],
            [
                'name' => 'Tecnología',
                'description' => 'Accesorios y productos tecnológicos.',
                'is_active' => true,
            ],
        );
    }
}
