<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Workbench\Database\Factories\UserFactory;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        UserFactory::new()->create(['name' => 'Owner', 'email' => 'owner@example.com']);
        $teammate = UserFactory::new()->create(['name' => 'Teammate', 'email' => 'teammate@example.com']);

        $teammate->assignRole(Role::create(['name' => 'Sales']));

        $this->call(DemoOrderSeeder::class);
    }
}
