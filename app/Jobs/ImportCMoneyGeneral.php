<?php

namespace App\Jobs;

use App\Crawler\StockHelper;
use App\GeneralPrice;
use App\Jobs\Update\SaveGeneralPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ImportCMoneyGeneral implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    protected $data;


    /**
     * Create a new job instance.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(isset($this->data->DataPrice)){

            foreach ($this->data->DataPrice as $price){
                /**
                 * 0: timestamp
                 * 1: current price
                 * 2: volume
                 * 3: buy price
                 * 4: sold price
                 */
                $newdate = StockHelper::offset_date($price[0]/1000);

                $last_price = (object) Redis::hgetall("General:previousPrice#{$newdate->format("Y-m-d")}");
                if(!isset($last_price->low)) {
                    $generalPrice = [
                        'low' => $price[1],
                        'high' => $price[1],
                        'value' => $price[1],
                        'tlong' => $newdate->getTimestamp()*1000,
                        'date' => $newdate->format("Y-m-d")
                    ];
                }
                else{
                    $generalPrice = [
                        'low' => min($price[1], $last_price->low),
                        'high' => max($price[1], $last_price->high),
                        'value' => $price[1],
                        'tlong' => $newdate->getTimestamp()*1000,
                        'date' => $newdate->format("Y-m-d")
                    ];

                }

                SaveGeneralPrice::dispatch($generalPrice)->onQueue("low");
                Redis::hmset("General:previousPrice#{$newdate->format("Y-m-d")}", $generalPrice);

            }
        }
    }
}
