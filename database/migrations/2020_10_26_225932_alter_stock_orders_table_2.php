<?php

use App\StockOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterStockOrdersTable2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stock_orders', function (Blueprint $table) {
            $table->string("order_type")->nullable(false)->change();
            $table->boolean("closed")->default(false);
            $table->bigInteger("tlong2")->comment("tlong");
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
            $table->enum("order_type", [StockOrder::DL1, StockOrder::DL2])->default(StockOrder::DL1)->comment("Is order from dl1 or dl2 stocks")->change();
            $table->dropColumn("closed");
            $table->dropColumn("tlong2");
        });
    }
}
