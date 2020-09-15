<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_prices', function (Blueprint $table) {
            $table->id();
            $table->string("code", 10);
            $table->decimal("latest_trade_price");
            $table->decimal("trade_volume");
            $table->decimal("accumulate_trade_volume");
            $table->decimal("best_bid_price");
            $table->decimal("best_bid_volume");
            $table->decimal("best_ask_price");
            $table->decimal("best_ask_volume");
            $table->decimal("open");
            $table->decimal("high");
            $table->decimal("low");
            $table->date("date")->default(date("Y-m-d"));
            $table->bigInteger("tlong");
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
        Schema::dropIfExists('stock_prices');
    }
}
