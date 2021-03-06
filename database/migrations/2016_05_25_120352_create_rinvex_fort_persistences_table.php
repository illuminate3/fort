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

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRinvexFortPersistencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('rinvex.fort.tables.persistences'), function (Blueprint $table) {
            // Columns
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('token');
            $table->string('agent')->nullable();
            $table->string('ip')->nullable();
            $table->boolean('attempt')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->unique('token');
            $table->foreign('user_id')
                  ->references('id')
                  ->on(config('rinvex.fort.tables.users'))
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Engine
            $table->engine = 'InnoDB';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop(config('rinvex.fort.tables.persistences'));
    }
}
