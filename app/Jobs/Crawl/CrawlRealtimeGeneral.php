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
        $this->stop->setTime(13, 35, 0);
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

        $response = json_decode(StockHelper::get_content("https://mis.twse.com.tw/stock/data/mis_ohlc_TSE.txt?" . http_build_query(["_" => time()])));

        if (isset($response->infoArray) && isset($response->infoArray[0])) {
            $info = $response->infoArray[0];
            if (isset($info->h) && isset($info->z) && isset($info->tlong) && isset($info->l)) {

                $generalPrice = GeneralPrice::where("date", $now->format("Y-m-d"))->where("tlong", $info->tlong)->first();
                if(!$generalPrice){
                    $generalPrice = new GeneralPrice([
                        'high' => $info->h,
                        'low' => $info->l,
                        'value' => $info->z,
                        'date' => date("Y-m-d"),
                        'tlong' => $info->tlong
                    ]);

                    $generalPrice->Save();
                }

            }

        }

        $this->callback();
    }
}
