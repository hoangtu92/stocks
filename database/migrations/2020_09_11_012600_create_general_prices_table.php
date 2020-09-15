<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGeneralPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('general_prices', function (Blueprint $table) {
            $table->id();
            $table->decimal("high")->comment("h");
            $table->decimal("low")->comment("l");
            $table->decimal("value")->comment("z");
            $table->date("date")->default(date("Y-m-d"));
            $table->bigInteger("tlong")->comment("tlong");
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
        Schema::dropIfExists('general_prices');
    }
}
