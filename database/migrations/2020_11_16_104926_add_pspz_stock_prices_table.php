<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPspzStockPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stock_prices', function (Blueprint $table) {
            $table->decimal("ps")->nullable(true)->after("best_ask_price");
            $table->decimal("pz")->nullable(true)->after("best_ask_price");
            $table->integer("ip")->nullable(true)->after("pz");

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
            $table->dropColumn("ps");
            $table->dropColumn("pz");
            $table->dropColumn("ip");
        });
    }
}
