<?php

namespace App\Jobs\Rerun;

use App\Crawler\StockHelper;
use App\Holiday;
use App\Jobs\Trading\TickShortSell1;
use App\StockOrder;
use App\StockPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Dl1 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filter_date;
    protected $code;
    public int $timeout = 0;
    public int $tries = 2;

    /**
     * Create a new job instance.
     *
     * @param $filter_date
     * @param null $code
     */
    public function __construct($filter_date, $code = null)
    {
        //
        $this->filter_date = $filter_date;

        $this->code = $code;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        //Redis::flushall();

        $d = date_create($this->filter_date);

        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  {$d->format('Y')}")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e){
            $t[] = $e['date'];
            return $t;
        }, []);

        //If weekend or holiday
        if ($d->format("N") >= 6 || in_array($d->format("Y-m-d"), $holiday)){
            Log::debug("Today is off\n");
            return;
        }

        StockHelper::loadGeneralPrices($this->filter_date);

        /**
         *
         */

        if($this->code){

            echo "Attempt to run DL1 on {$this->filter_date} for {$this->code}\n";
            StockOrder::where("date", $this->filter_date)->where("order_type", StockOrder::DL1)->where("code", $this->code)->delete();

            $this->callback($this->code);

        }
        else{

            echo "Attempt to run DL1 on {$this->filter_date}\n";
            StockOrder::where("date", $this->filter_date)->where("order_type", StockOrder::DL1)->delete();

            //Get DL1 stocks
            $stocks = StockHelper::getDL1Stocks($this->filter_date);

            foreach ($stocks as $stock){
                if((bool) Redis::get("STOP_RERUN")) break;
                $this->callback($stock->code);
            }
        }

        return;
    }

    function callback($code){
        $stockPrices = StockPrice::where("code", $code)
            ->where("date", $this->filter_date)
            ->orderBy("tlong", "asc")->get();

        foreach($stockPrices as $stockPrice){
            #if((bool) Redis::get("STOP_RERUN")) break;
            TickShortSell1::dispatchNow($stockPrice);

            $d = $stockPrice->time->format("Y-m-d H:i");
            $date = date_create_from_format("Y-m-d H:i", $d);

            Redis::hmset("Stock:previousPrice#{$stockPrice->code}", $stockPrice->toArray());
            Redis::hmset("Stock:prices#{$stockPrice->code}|{$date->getTimestamp()}", $stockPrice->toArray());
        }
    }
}
