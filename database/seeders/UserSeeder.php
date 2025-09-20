<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Super Admin (sem tenant)
        User::create([
            'name' => 'Super Administrador',
            'email' => 'admin@az.tv.br',
            'role' => 'admin',
            'theme' => 'light',
            'tenant_id' => null, // Admin não tem tenant
            'password' => Hash::make('123456'),
            'email_verified_at' => now(),
        ]);

        // Buscar tenants para associar usuários
        $escolaAbc = Tenant::where('slug', 'escola-abc')->first();
        $institutoXyz = Tenant::where('slug', 'instituto-xyz')->first();
        $colegio123 = Tenant::where('slug', 'colegio-123')->first();

        // Usuários da Escola ABC
        User::create([
            'name' => 'João Silva',
            'email' => 'joao@escola-abc.com',
            'role' => 'client',
            'theme' => 'light',
            'tenant_id' => $escolaAbc->id,
            'password' => Hash::make('123456'),
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Maria Santos',
            'email' => 'maria@escola-abc.com',
            'role' => 'client',
            'theme' => 'light',
            'tenant_id' => $escolaAbc->id,
            'password' => Hash::make('123456'),
            'email_verified_at' => now(),
        ]);

        // Usuários do Instituto XYZ
        User::create([
            'name' => 'Pedro Costa',
            'email' => 'pedro@instituto-xyz.com',
            'role' => 'client',
            'theme' => 'light',
            'tenant_id' => $institutoXyz->id,
            'password' => Hash::make('123456'),
            'email_verified_at' => now(),
        ]);

        // Usuários do Colégio 123
        User::create([
            'name' => 'Ana Oliveira',
            'email' => 'ana@colegio-123.com',
            'role' => 'client',
            'theme' => 'light',
            'tenant_id' => $colegio123->id,
            'password' => Hash::make('123456'),
            'email_verified_at' => now(),
        ]);
    }
}
