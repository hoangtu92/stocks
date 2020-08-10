<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAravsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('aravs', function (Blueprint $table) {
            $table->id();
            $table->string("code")->nullable(false);
            $table->string("name");
            $table->decimal("start");
            $table->decimal("max");
            $table->decimal("lowest");
            $table->decimal("final");
            $table->decimal("price_range");
            $table->date("date");
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
        Schema::dropIfExists('aravs');
    }
}
