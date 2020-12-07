<?php

namespace App\Jobs;

use App\Crawler\StockHelper;
use App\StockPrice;
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
    public int $tries = 0;
    protected int $code;

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
        $data = StockHelper::get_content($url);
        $data = json_decode(trim($data, "();"));

        $d = date_create_from_format("YmdHis", $data->tick[0]->t);

        $yesterday_final = Redis::get("Stock:yesterday_final#{$this->code}");
        if(!$yesterday_final){

            $stocks = DB::table("stocks")->where("code", $this->code)->get()->toArray();
            $url = StockHelper::getUrlFromStocks($stocks);

            $json = json_decode(StockHelper::get_content($url));

            if(isset($json->msgArray) && count($json->msgArray) > 0) {
                $stockData = $json->msgArray[0];
                $yesterday_final = isset($stockData->y) ? StockHelper::format_number($stockData->y) : 0;
                Redis::set("Stock:yesterday_final#{$this->code}", $yesterday_final, "EX", 500);
            }


        }

        if(isset($data->tick)){

            foreach($data->tick as $tick){

                $time = date_create_from_format("YmdHis", $tick->t);

                $last_price = StockPrice::where("date", $time->format("Y-m-d"))->where("code", $this->code)->where("tlong", "<", $time->getTimestamp()*1000)->orderByDesc("tlong")->first();

                if(!$last_price) {
                    $stockPrice = new StockPrice([
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
                    ]);
                    $stockPrice->save();
                }
                else {
                    $exists = StockPrice::where("date", $time->format("Y-m-d"))->where("code", $this->code)->where("tlong", $time->getTimestamp()*1000)->first();
                    $d = [
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
                    if($exists){
                        $exists->update($d);
                    }
                    else{
                        $stockPrice = new StockPrice([
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
                        ]);
                        $stockPrice->save();
                    }

                }


            }
        }
    }
}
