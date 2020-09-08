<?php

use App\GeneralStock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterGeneralStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('general_stocks', function (Blueprint $table) {
            $table->enum("general_predict", [GeneralStock::DOWN, GeneralStock::UP])->nullable(true);
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
            $table->dropColumn("general_predict");
        });
    }
}
