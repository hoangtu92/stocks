<?php

namespace App\Jobs;

use App\Jobs\Trading\SelectedStrategy;
use App\StockOrder;
use App\StockPrice;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        Redis::del("Stock:yesterday_final#{$this->code}");
        Redis::del("Stock:previousPrice#{$this->code}");

        if(isset($this->data->DataPrice)){


            $d = $this->offset_date($this->data->DataPrice[0][0]/1000);

            StockPrice::where("code", $this->code)->where("date", $d->format("Y-m-d"))->delete();
            StockOrder::where("code", $this->code)->where("date", $d->format("Y-m-d"))->delete();



            foreach ($this->data->DataPrice as $price){
                /**
                 * 0: timestamp
                 * 1: current price
                 * 2: volume
                 * 3: buy price
                 * 4: sold price
                 */
                $newdate = $this->offset_date($price[0]/1000);

                $last_price = (object) Redis::hgetall("Stock:previousPrice#{$this->code}");

                if(!isset($last_price->code)){
                    $stockPrice = [
                        "code" => $this->code,
                        'best_ask_price' => $price[3],
                        'latest_trade_price' => $price[1],
                        'best_bid_price' => $price[4],
                        'open' => $price[1],
                        'low' => $price[1],
                        'high' => $price[1],
                        'trade_volume' => $price[2],
                        'yesterday_final' => $this->data->BasePrice,
                        'tlong' => $newdate->getTimestamp()*1000,
                        'date' => $newdate->format("Y-m-d")
                    ];
                }
                else{
                    $stockPrice = [
                        "code" => $this->code,
                        'best_ask_price' => $price[3],
                        'latest_trade_price' => $price[1],
                        'best_bid_price' => $price[4],
                        'open' => $last_price->open,
                        'low' => min($price[1], $last_price->low),
                        'high' => max($price[1], $last_price->high),
                        'trade_volume' => $price[2],
                        'yesterday_final' => $this->data->BasePrice,
                        'tlong' => $newdate->getTimestamp()*1000,
                        'date' => $newdate->format("Y-m-d")
                    ];

                }

                SelectedStrategy::dispatchNow($stockPrice);

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
