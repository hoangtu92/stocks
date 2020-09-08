<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGeneralStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('general_stocks', function (Blueprint $table) {
            $table->id();
            $table->date("date");
            $table->decimal("general_start")->nullable(true);
            $table->decimal("price_905")->nullable(true);
            $table->decimal("today_final")->nullable(true);
            $table->decimal("yesterday_final")->nullable(true);
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
        Schema::dropIfExists('general_stocks');
    }
}
