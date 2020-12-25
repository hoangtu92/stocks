<?php

namespace App\Jobs\Crawl;


use App\Crawler\StockHelper;
use App\StockPrice;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CrawlRealtimeStock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 0;
    protected $start, $stop;

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
        /**
         * Start to monitor stock data
         */
        Log::info("Start stock realtime crawl");

        $filter_date = date("Y-m-d");


        $list2 = DB::table("dl")->join("stocks", "stocks.code", "=", "dl.code")
            ->addSelect("dl.code")
            ->addSelect("stocks.type")
            ->where("dl.final", ">", 10)
            ->where("dl.final", "<", 200)
            ->whereRaw("dl.agency IS NOT NULL")
            ->whereIn("dl.date", [
                $filter_date,
            ])
            ->get()->toArray();


        $list1 = DB::table("dl")->join("stocks", "stocks.code", "=", "dl.code")
            ->addSelect("dl.code")
            ->addSelect("stocks.type")
            ->where("dl.final", ">", 10)
            ->where("dl.final", "<", 200)
            ->whereRaw("dl.agency IS NOT NULL")
            ->whereIn("dl.date", [
                #StockHelper::previousDay($filter_date),
                StockHelper::previousDay(StockHelper::previousDay($filter_date)),
                /*StockHelper::previousDay(StockHelper::previousDay(StockHelper::previousDay($filter_date))),
                StockHelper::previousDay(StockHelper::previousDay(StockHelper::previousDay(StockHelper::previousDay($filter_date)))),
                StockHelper::previousDay(StockHelper::previousDay(StockHelper::previousDay(StockHelper::previousDay(StockHelper::previousDay($filter_date))))),*/
            ])
            //->whereIn("dl.code", $includeFilter->stockList)
            //->whereNotIn("dl.code", $excludeFilter->stockList)
            //->whereNotIn("dl.code", $ll2)
            ->get()->toArray();


        $stocks = array_merge($list1, $list2);

        Redis::del("Stock:DL0");

        foreach ($stocks as $st){
            Redis::lpush("Stock:DL0", $st->code);
        }

        $url = StockHelper::getUrlFromStocks($stocks);

        $this->callback($url);
    }

    public function callback($url){
        $now = new DateTime();

        if($now < $this->start || $now > $this->stop){
            return false;
        }


        //Crawl realtime stock data and save to db
        $response = StockHelper::get_content($url);

        $json = json_decode($response);

        #Log::info("Stock info {$url} ".$response);

        if(isset($json->msgArray) && count($json->msgArray) > 0) {

            foreach ($json->msgArray as $stock){
                $latest_trade_price = isset($stock->z) ? StockHelper::format_number($stock->z) : 0;
                $trade_volume = isset($stock->tv) ? StockHelper::format_number($stock->tv) : 0;
                $accumulate_trade_volume = isset($stock->v) ? StockHelper::format_number($stock->v) : 0;
                $yesterday_final = isset($stock->y) ? StockHelper::format_number($stock->y) : 0;

                $best_bid_price = explode("_", $stock->b);
                $best_bid_volume = explode("_", $stock->g);
                $best_ask_price = explode("_", $stock->a);
                $best_ask_volume = explode("_", $stock->f);

                $pz = isset($stock->pz) ? StockHelper::format_number($stock->pz) : 0;
                $ps = isset($stock->ps) ? StockHelper::format_number($stock->ps) : 0;

                $open = StockHelper::format_number($stock->o);
                $high = StockHelper::format_number($stock->h);
                $low = StockHelper::format_number($stock->l);

                $stockInfo = [
                    'code' => $stock->c,
                    'date' => date("Y-m-d"),
                    'tlong' => (int) $stock->tlong,
                    'latest_trade_price' => $latest_trade_price,
                    'trade_volume' => $trade_volume,
                    'accumulate_trade_volume' => $accumulate_trade_volume,
                    'best_bid_price' => StockHelper::format_number($best_bid_price[0]),
                    'best_bid_volume' => StockHelper::format_number($best_bid_volume[0]),
                    'best_ask_price' => StockHelper::format_number($best_ask_price[0]),
                    'best_ask_volume' => StockHelper::format_number($best_ask_volume[0]),
                    'ps' => $ps,
                    'pz' => $pz,
                    'open' => $open,
                    'high' => $high,
                    'low' => $low,
                    'yesterday_final' => $yesterday_final,
                ];

                $stockPrice = StockPrice::where("date", $stockInfo['date'])->where("code", $stockInfo['code'])->where("tlong", $stockInfo['tlong'])->first();

                if(!$stockPrice){
                    $stockPrice = new StockPrice($stockInfo);
                    $stockPrice->save();
                }



            }
        }


        return $this->callback($url);

    }
}
