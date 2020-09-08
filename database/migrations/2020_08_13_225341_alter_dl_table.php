<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterDlTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table("dl", function (Blueprint $table){
            $table->string("agency")->nullable(true);
            $table->decimal("total_agency_vol")->nullable(true);
            $table->decimal("agency_price")->nullable(true);
            $table->decimal("single_agency_vol")->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table("dl", function (Blueprint $table){
           $table->dropColumn("agency");
           $table->dropColumn("total_agency_vol");
           $table->dropColumn("agency_price");
           $table->dropColumn("single_agency_vol");
        });
    }
}
