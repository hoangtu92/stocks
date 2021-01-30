<?php

namespace App\Jobs\Crawl;

use App\Crawler\StockHelper;
use App\GeneralPrice;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlRealtimeGeneral implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;
    public int $tries = 0;
    protected DateTime $start;
    protected DateTime $stop;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
        $this->start = new DateTime();
        $this->stop = new DateTime();

        $this->start->setTime(9, 0, 0);
        $this->stop->setTime(13, 36, 0);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        /**
         * Start to monitor stock data
         */
        Log::info("Start general realtime crawl");

        $this->callback();
    }


    public function callback(){

        $now = new DateTime();

        if($now < $this->start || $now > $this->stop){
            return;
        }

        StockHelper::getGeneralData();

        $this->callback();
    }
}
