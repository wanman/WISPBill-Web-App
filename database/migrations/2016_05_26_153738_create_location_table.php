<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLocationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::create('locations', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('longitude', 10, 7);
            $table->decimal('latitude', 10, 7);
            $table->string('type');
            $table->string('name');
            $table->longText('coverage')->nullable()->default(NULL);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
         Schema::drop('locations');
    }
}
