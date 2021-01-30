<?php

namespace App\Jobs\Crawl;


use App\Crawler\StockHelper;
use App\Jobs\Trading\SelectedStrategy;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class CrawlRealtimeStock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;
    public int $tries = 0;
    protected DateTime $start;
    protected DateTime $stop;
    protected string $url;

    /**
     * Create a new job instance.
     *
     * @param string $url
     */
    public function __construct(string $url)
    {

        $this->url =$url;
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

        $this->callback($this->url);
    }

    /**
     * @param $url
     * @return false
     */
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

                //"ip":"0", //1: Trending down, 2: Trending up, 4: Suspend closing, 5: Suspend opening
                $ip = $stock->ip;

                $open = StockHelper::format_number($stock->o);
                $high = StockHelper::format_number($stock->h);
                $low = StockHelper::format_number($stock->l);

                $stockPrice = [
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
                    'ip' => $ip,
                    'open' => $open,
                    'high' => $high,
                    'low' => $low,
                    'yesterday_final' => $yesterday_final,
                ];

                Redis::hmset("Stock:currentPrice#{$stockPrice['code']}", $stockPrice);

                SelectedStrategy::dispatchNow($stockPrice);

            }
        }

        return $this->callback($url);

    }
}
