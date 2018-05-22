<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEnergyUsageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('energy_usage', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kwh_used');
            $table->unsignedDecimal('cost');
            $table->Integer('high_temp');
            $table->Integer('low_temp');
            $table->Integer('average_temp');
            $table->date('date')->unique();
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
        Schema::dropIfExists('energy_usage');
    }
}
