<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'super_admin',
            'admin',
            'supplier',
            'regular_supplier',
            'retailer_premium',
            'retailer_basic',
            'end_user',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role],
                ['guard_name' => 'web']
            );
        }
    }
}
