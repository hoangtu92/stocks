<?php

use App\StockOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_orders', function (Blueprint $table) {
            $table->id();
            $table->date("date");
            $table->string("code");
            $table->integer("qty")->default(1);
            $table->decimal("price")->default(1);
            $table->decimal("fee");
            $table->decimal("tax");
            $table->enum("type", [StockOrder::BUY, StockOrder::SELL]);
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
        Schema::dropIfExists('stock_orders');
    }
}
