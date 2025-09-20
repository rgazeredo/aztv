<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tenant::create([
            'name' => 'Escola ABC',
            'slug' => 'escola-abc',
            'is_active' => true,
            'settings' => [
                'max_users' => 100,
                'features' => ['courses', 'reports', 'integrations'],
            ],
        ]);

        Tenant::create([
            'name' => 'Instituto XYZ',
            'slug' => 'instituto-xyz',
            'is_active' => true,
            'settings' => [
                'max_users' => 50,
                'features' => ['courses', 'reports'],
            ],
        ]);

        Tenant::create([
            'name' => 'Colégio 123',
            'slug' => 'colegio-123',
            'is_active' => true,
            'settings' => [
                'max_users' => 200,
                'features' => ['courses', 'reports', 'integrations', 'analytics'],
            ],
        ]);
    }
}
