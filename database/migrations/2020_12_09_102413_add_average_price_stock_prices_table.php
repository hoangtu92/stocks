<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAveragePriceStockPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('stock_prices', function (Blueprint $table) {
            $table->decimal("average_price")->nullable(true)->after("best_ask_price");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stock_prices', function (Blueprint $table) {
            $table->dropColumn("average_price");
        });
    }
}
