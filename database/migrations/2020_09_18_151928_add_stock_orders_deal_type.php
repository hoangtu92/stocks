<?php

use App\StockOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockOrdersDealType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stock_orders', function (Blueprint $table) {
            $table->enum("deal_type", [StockOrder::SHORT_SELL, StockOrder::BUY_LONG])->nullable(true);
            $table->bigInteger("tlong")->comment("tlong");
            $table->string("OID")->nullable(true);
            $table->string("OrderNo")->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stock_orders', function (Blueprint $table) {
           $table->dropColumn("deal_type");
           $table->dropColumn("tlong");
            $table->dropColumn("OID");
            $table->dropColumn("OrderNo");
        });
    }
}
