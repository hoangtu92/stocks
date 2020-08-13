<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string("code")->nullable(false);
            $table->date("date");
            $table->string("sell_status")->comment("AJ")->nullable(true);
            $table->decimal("sell_value")->comment("AK")->nullable(true);
            $table->decimal("buy_value")->comment("AM")->nullable(true);
            $table->string("minimum_buy")->comment("AN")->nullable(true);
            $table->decimal("start")->comment("AR")->nullable(true);
            $table->decimal("price_range")->comment("AW")->nullable(true);
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
        Schema::dropIfExists('orders');
    }
}
