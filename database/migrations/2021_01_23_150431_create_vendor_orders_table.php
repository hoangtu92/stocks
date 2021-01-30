<?php

use App\VendorOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendorOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendor_orders', function (Blueprint $table) {
            $table->id();

            $table->date("date");
            $table->string("code");
            $table->string("OID")->nullable(true);
            $table->string("OrderNo")->nullable(true);
            $table->integer("qty")->default(1);
            $table->decimal("price")->default(0);

            $table->decimal("fee")->default(0);
            $table->decimal("tax")->default(0);
            $table->enum("status", [VendorOrder::PENDING, VendorOrder::CANCELED, VendorOrder::FAILED, VendorOrder::SUCCESS])->default(VendorOrder::PENDING);

            $table->string("order_type")->nullable(false);
            $table->enum("deal_type", [VendorOrder::SHORT_SELL, VendorOrder::BUY_LONG])->nullable(VendorOrder::SHORT_SELL);
            $table->enum("type", [VendorOrder::SELL, VendorOrder::BUY])->nullable(VendorOrder::SELL);
            $table->boolean("closed")->default(false);
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
        Schema::dropIfExists('vendor_orders');
    }
}
