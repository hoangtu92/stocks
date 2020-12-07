<?php

namespace App\Jobs;

use App\Crawler\StockHelper;
use App\StockPrice;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ImportCMoney implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    protected $code, $data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code, $data)
    {
        //
        $this->code = $code;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        if(isset($this->data->DataPrice)){
            $d = $this->offset_date($this->data->DataPrice[0][0]/1000);

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


            foreach ($this->data->DataPrice as $price){
                /**
                 * 0: timestamp
                 * 1: current price
                 * 2: volume
                 * 3: buy price
                 * 4: sold price
                 */
                $newdate = $this->offset_date($price[0]/1000);

                $last_price = StockPrice::where("date", $newdate->format("Y-m-d"))->where("code", $this->code)->where("tlong", "<", $price[0])->orderBy("tlong", "desc")->first();
                if(!$last_price){
                    $stockPrice = new StockPrice([
                        "code" => $this->code,
                        'best_ask_price' => $price[3],
                        'latest_trade_price' => $price[1],
                        'best_bid_price' => $price[4],
                        'open' => $price[1],
                        'low' => $price[1],
                        'high' => $price[1],
                        'trade_volume' => $price[2],
                        'yesterday_final' => $yesterday_final,
                        'tlong' => $newdate->getTimestamp()*1000,
                        'date' => $newdate->format("Y-m-d")
                    ]);
                    $stockPrice->save();
                }
                else{
                    $exists = StockPrice::where("date", $newdate->format("Y-m-d"))->where("code", $this->code)->where("tlong",  $price[0])->first();
                    $d = [
                        "code" => $this->code,
                        'best_ask_price' => $price[3],
                        'latest_trade_price' => $price[1],
                        'best_bid_price' => $price[4],
                        'open' => $last_price->open,
                        'low' => min($price[1], $last_price->low),
                        'high' => max($price[1], $last_price->high),
                        'trade_volume' => $price[2],
                        'yesterday_final' => $yesterday_final,
                        'tlong' => $newdate->getTimestamp()*1000,
                        'date' => $newdate->format("Y-m-d")
                    ];

                    if(!$exists){
                        $stockPrice = new StockPrice($d);
                        $stockPrice->save();
                    }
                    else{
                        $exists->update($d);
                    }

                }



            }
        }

    }

    function offset_date($timestamp){
        $time = new DateTime();
        $time->setTimestamp($timestamp);

        $tz=timezone_open("Asia/Taipei");

        $offset =  timezone_offset_get($tz, $time);

        $newdate = new DateTime();
        $newdate->setTimestamp($time->getTimestamp() - $offset);

        return $newdate;
    }
}
