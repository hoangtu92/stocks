<?php

namespace App\Jobs;

use App\Crawler\StockHelper;
use App\Jobs\Trading\SelectedStrategy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CrawlYahooPrice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;
    public int $tries = 2;
    protected $code;

    /**
     * Create a new job instance.
     *
     * @param $code
     */
    public function __construct($code)
    {
        //
        $this->code = $code;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //

        $url = "https://tw.quote.finance.yahoo.net/quote/q?".http_build_query([
                "type" => "tick",
                "callback" => "",
                "perd" => "1s",
                "mkt" => 10,
                "sym" => $this->code,
                "_" => time()
            ]);

        $date = date("Y-m-d");

        Redis::del("Stock:yesterday_final#{$this->code}#{$date}");
        Redis::del("Stock:previousPrice#{$this->code}#{$date}");

        $data = StockHelper::get_content($url);
        $data = json_decode(trim($data, "();"));

        #$d = date_create_from_format("YmdHis", $data->tick[0]->t);

        $yesterday_final = $data->mem->{"129"};


        if(isset($data->tick)){

            foreach($data->tick as $tick){

                $time = date_create_from_format("YmdHis", $tick->t);

                $last_price = (object) Redis::hgetall("Stock:previousPrice#{$this->code}#{$date}");

                if(!isset($last_price->code)){
                    $stockPrice = [
                        "code" => $this->code,
                        'best_ask_price' => $tick->p,
                        'latest_trade_price' => $tick->p,
                        'best_bid_price' => $tick->p,
                        'open' => $tick->p,
                        'low' => $tick->p,
                        'high' => $tick->p,
                        'trade_volume' => $tick->v,
                        'yesterday_final' => $yesterday_final,
                        'tlong' => $time->getTimestamp()*1000,
                        'date' => $time->format("Y-m-d")
                    ];
                }
                else {
                    $stockPrice = [
                        "code" => $this->code,
                        'best_ask_price' => $tick->p,
                        'latest_trade_price' => $tick->p,
                        'best_bid_price' => $tick->p,
                        'open' => $last_price->open,
                        'low' => min($tick->p, $last_price->low),
                        'high' => max($tick->p, $last_price->high),
                        'trade_volume' => $tick->v,
                        'yesterday_final' => $yesterday_final,
                        'tlong' => $time->getTimestamp() * 1000,
                        'date' => $time->format("Y-m-d")
                    ];

                }

                SelectedStrategy::dispatchNow($stockPrice);

            }
        }
    }
}
