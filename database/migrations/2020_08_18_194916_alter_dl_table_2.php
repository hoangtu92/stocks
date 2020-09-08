<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterDlTable2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table("dl", function (Blueprint $table){
            $table->decimal("large_trade")->nullable(true);
            $table->decimal("dynamic_rate_sell")->nullable(true);

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table("dl", function (Blueprint $table){
            $table->dropColumn("large_trade");
            $table->dropColumn("dynamic_rate_sell")->nullable(true);
        });
    }
}
