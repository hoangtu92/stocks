<?php

namespace App\Jobs\Rerun;

use App\Crawler\StockHelper;
use App\Holiday;
use App\Jobs\Trading\DL0_Strategy_0;
use App\Jobs\Trading\DL0_Strategy_2D;
use App\Jobs\Trading\TickShortSell0;
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

class Dl0 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filter_date;
    protected $code;
    protected $strategy;
    public int $timeout = 0;
    public int $tries = 2;

    /**
     * Create a new job instance.
     *
     * @param $filter_date
     * @param null $code
     * @param int $strategy
     */
    public function __construct($filter_date, $strategy = 1, $code = null)
    {
        //
        $this->strategy = $strategy;
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

        $d = date_create($this->filter_date);

        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  {$d->format('Y')}")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e){
            $t[] = $e['date'];
            return $t;
        }, []);

        //If weekend or holiday
        if ($d->format("N") >= 6 || in_array($d->format("Y-m-d"), $holiday)){
            Log::debug("Today is off\n");
            return false;
        }

        StockHelper::loadGeneralPrices($this->filter_date);

        /**
         *
         */

        if($this->code){
            echo "Attempt to run DL0 on {$this->filter_date} for {$this->code}\n";
            StockOrder::where("date", $this->filter_date)->where("order_type", StockOrder::DL0)->where("code", $this->code)->delete();

            $this->callback($this->code);

            $stockOrders = StockOrder::where("date", $this->filter_date)->where("code", $this->code)->get();
            $this->summary($stockOrders);
        }
        else{

            echo "Attempt to run DL0 on {$this->filter_date}\n";

            StockOrder::where("date", $this->filter_date)->where("order_type", StockOrder::DL0)->delete();

            //Get DL0 stocks
            $stocks = StockHelper::getDL0Stocks($this->filter_date);


            foreach ($stocks as $stock){
                echo "Stocks: {$stock->code}\n";
                $this->callback($stock->code);
            }

            $stockOrders = StockOrder::where("date", $this->filter_date)->get();
            $this->summary($stockOrders);

        }

        return;
    }

    function callback($code){
        $stockPrices = StockPrice::where("code", $code)
            ->where("date", $this->filter_date)
            ->orderBy("tlong", "asc")->get();

        foreach($stockPrices as $stockPrice){

            Redis::hmset("Stock:currentPrice#{$stockPrice->code}", $stockPrice->toArray());

            if($this->strategy == 1){
                DL0_Strategy_2D::dispatchNow($stockPrice);
            }
            Redis::hmset("Stock:previousPrice#{$stockPrice->code}", $stockPrice->toArray());
        }
    }

    function summary($stockOrders){
        if($stockOrders){
            $gain = 0;
            $loss = 0;
            $total_fee = 0;
            $total_tax = 0;
            $total = 0;
            foreach ($stockOrders as $stockOrder){
                if($stockOrder->profit > 0){
                    $gain += $stockOrder->profit;
                }
                else{
                    $loss += $stockOrder->profit;
                }
                $total += $stockOrder->profit;
                $total_fee += $stockOrder->fee;
                $total_tax += $stockOrder->tax;
            }

            echo "TOTAL: {$total} | GAIN: {$gain} | LOSS: {$loss} | TAX: {$total_tax} | FEE: {$total_fee}\n";
        }
    }
}
