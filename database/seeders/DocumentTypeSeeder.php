<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * Illustrative starter set matching the examples in the roadmap
 * (contracts, citizenship copies, certificates) — hr_admin can add more
 * from Setup, this is just a usable starting point.
 */
class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['Employment Contract', 'Citizenship Copy', 'Certificate', 'Other'];

        foreach ($types as $name) {
            DocumentType::create(['name' => $name, 'is_active' => true]);
        }
    }
}
