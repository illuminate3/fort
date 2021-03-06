<?php

/*
 * NOTICE OF LICENSE
 *
 * Part of the Rinvex Fort Package.
 *
 * This source file is subject to The MIT License (MIT)
 * that is bundled with this package in the LICENSE file.
 *
 * Package: Rinvex Fort Package
 * License: The MIT License (MIT)
 * Link:    https://rinvex.com
 */

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RinvexFortRolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table(config('rinvex.fort.tables.roles'))->truncate();

        // Get roles data
        $roles = require __DIR__.'/../../resources/data/roles.php';

        // Create new roles
        foreach ($roles as $role) {
            app('rinvex.fort.role')->create($role);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
