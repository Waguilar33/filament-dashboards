<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\Database\Factories\DemoOrderFactory;

final class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        DemoOrderFactory::new()->count(300)->create();
    }
}
