<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFailedCrawlsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('failed_crawls', function (Blueprint $table) {
            $table->id();
            $table->string("action");
            $table->integer("restart")->default(0);
            $table->boolean("resolved")->default(false);
            $table->timestamp("failed_at");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('failed_crawls');
    }
}
