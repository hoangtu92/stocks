<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveYesterdayFinalGeneralStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('general_stocks', function (Blueprint $table) {
            $table->dropColumn("yesterday_final");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('general_stocks', function (Blueprint $table) {
            $table->decimal("yesterday_final")->nullable(true);
        });
    }
}
