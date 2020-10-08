<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDlBorrowTicket extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dl', function (Blueprint $table) {
            $table->boolean("borrow_ticket")->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dl', function (Blueprint $table) {
            $table->dropColumn("borrow_ticket");
        });
    }
}
