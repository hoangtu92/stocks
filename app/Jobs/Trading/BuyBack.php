<?php

namespace App\Jobs\Trading;

use App\StockOrder;
use App\StockPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class BuyBack implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $stockPrice;

    /**
     * Create a new job instance.
     *
     * @param StockPrice $stockPrice
     */
    public function __construct(StockPrice $stockPrice)
    {
        //
        $this->stockPrice = $stockPrice;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //

        $unCloseOrder = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", false)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->first();

        if(!$unCloseOrder) return;

        $general_start = (float) Redis::get("General:open_today");
        $yesterday_final = (float) Redis::get("General:yesterday_final");
        $general_trend = Redis::get("General:trend");
        $current_general = Redis::hgetall("General:realtime");


        $unCloseOrder->buy = $this->stockPrice->current_price;

        if (($unCloseOrder->profit_percent >= 2 ||
            ($unCloseOrder->profit_percent >= 1.5 && $this->stockPrice->current_price > 50) ||
            ($unCloseOrder->profit_percent >= 1.2 && $this->stockPrice->current_price > 100))
        ) {
            $unCloseOrder->close_deal_arr($this->stockPrice);
            Log::debug("{$this->stockPrice->stock_time["hours"]}:{$this->stockPrice->stock_time["minutes"]}:  [{$unCloseOrder->id}] GAIN {$this->stockPrice->code} at {$this->stockPrice->current_price} | profit: {$unCloseOrder->profit_percent}");
            return;
        }

        //Current price > yesterday final
        if($this->stockPrice->current_price > $this->stockPrice->yesterday_final
            //Price is going up but possibly fake. it could drop again
            && $this->stockPrice->current_price >= $this->stockPrice->high
            //general price was not start at high or it never dropped below yesterday_final before -> Predict it wont drop so do profit loss immediately
            && ($general_start <= $yesterday_final || $current_general['low'] >= $yesterday_final)
        ){
            $unCloseOrder->close_deal_arr($this->stockPrice);
            Log::debug("{$this->stockPrice->stock_time["hours"]}:{$this->stockPrice->stock_time["minutes"]}:  [{$unCloseOrder->id}] PROFIT LOSS {$unCloseOrder->code} at {$unCloseOrder->buy} | profit: {$unCloseOrder->profit_percent}");
            return;
        }


        //close all remain orders
        if ($this->stockPrice->stock_time["hours"] == 12 && $this->stockPrice->stock_time["minutes"] >= 30 && $unCloseOrder->profit_percent >= 0) {
            $unCloseOrder->close_deal_arr($this->stockPrice);
            Log::debug("{$this->stockPrice->stock_time["hours"]}:{$this->stockPrice->stock_time["minutes"]}:  [{$unCloseOrder->id}] CLEAN {$unCloseOrder->code} at {$unCloseOrder->buy} | profit: {$unCloseOrder->profit_percent}");
            return;
        }



    }
}
