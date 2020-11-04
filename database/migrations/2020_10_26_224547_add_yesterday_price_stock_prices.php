<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddYesterdayPriceStockPrices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stock_prices', function (Blueprint $table) {
            $table->decimal("yesterday_final")->nullable(true)->after("low");
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
            $table->dropColumn("yesterday_final");
        });
    }
}
