<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $tenant = Tenant::firstOrCreate(
            ['domain' => 'acme.hrms.test'],
            ['name' => 'Acme Corporation', 'timezone' => 'UTC']
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@hrms.test'],
            [
                'name' => 'System Admin',
                'password' => 'password',
                'tenant_id' => $tenant->id,
            ]
        );
        $admin->asSystemAdmin();
    }
}
