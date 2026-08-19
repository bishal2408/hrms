<?php

namespace Database\Seeders;

use App\Models\Company;
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
        $this->call([
            RoleSeeder::class,
            // Must follow RoleSeeder, and needs shield:generate to have created
            // the permission rows it attaches.
            RolePermissionSeeder::class,
            PayrollRateSeeder::class,
            TaxSlabSeeder::class,
            LeaveTypeSeeder::class,
            SalaryComponentTypeSeeder::class,
            DocumentTypeSeeder::class,
        ]);

        Company::create(['name' => 'PLACEHOLDER Company Name']);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assignRole('super_admin');
    }
}
