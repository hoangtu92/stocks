<?php

namespace App\Jobs\Trading;

use App\Crawler\StockHelper;
use App\StockOrder;
use App\StockPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BuyBackStragedy2 implements ShouldQueue
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
        $current_general = StockHelper::getCurrentGeneralPrice($this->stockPrice->tlong);
        #$current_stock_trend = StockHelper::get5MinsStockTrend($this->stockPrice);

        if(!$current_general) {
            return;
        }

        $unclosed_order = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", false)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderByDesc("tlong")
            ->first();

        if(!$unclosed_order) return;


        $general_start = StockHelper::getGeneralStart($this->stockPrice->date);
        $yesterday_final = StockHelper::getYesterdayFinal($this->stockPrice->date);


        $unclosed_order->buy = $this->stockPrice->current_price;

        if ($unclosed_order->profit_percent >= 0.3) {
            $unclosed_order->close_deal_arr($this->stockPrice);
            Log::debug("{$this->stockPrice->stock_time["hours"]}:{$this->stockPrice->stock_time["minutes"]}:  [{$unclosed_order->id}] GAIN {$this->stockPrice->code} at {$this->stockPrice->current_price} | profit: {$unclosed_order->profit_percent}");
            return;
        }

        if (
            ($general_start > $yesterday_final &&
                floor($current_general['value']) < floor($current_general['high']) &&
                $this->stockPrice->current_price >= $this->stockPrice->yesterday_final*1.03) ||

            (floor($current_general['value']) >= floor($current_general['high']) &&
                $this->stockPrice->current_price >= $unclosed_order->sell)

            # || ($this->stockPrice->current_price >= $unclosed_order->sell && $current_stock_trend == 'up')

        ) {

            $unclosed_order->close_deal_arr($this->stockPrice);
            Log::debug("{$this->stockPrice->stock_time["hours"]}:{$this->stockPrice->stock_time["minutes"]}:  [{$unclosed_order->id}] PROFIT LOSS {$unclosed_order->code} at {$unclosed_order->buy} | profit: {$unclosed_order->profit_percent}");
            return;

        }


        //close all remain orders
        if ($this->stockPrice->stock_time["hours"] == 12 &&
            $this->stockPrice->stock_time["minutes"] >= 30 &&
            $unclosed_order->profit_percent >= 0) {

            $unclosed_order->close_deal_arr($this->stockPrice);
            Log::debug("{$this->stockPrice->stock_time["hours"]}:{$this->stockPrice->stock_time["minutes"]}:  [{$unclosed_order->id}] CLEAN FLAT {$unclosed_order->code} at {$unclosed_order->buy} | profit: {$unclosed_order->profit_percent}");
            return;
        }

        if ($this->stockPrice->stock_time["hours"] >= 13){
            $unclosed_order->close_deal_arr($this->stockPrice);
            Log::debug("{$this->stockPrice->stock_time["hours"]}:{$this->stockPrice->stock_time["minutes"]}:  [{$unclosed_order->id}] CLEAN {$unclosed_order->code} at {$unclosed_order->buy} | profit: {$unclosed_order->profit_percent}");
            return;
        }
    }
}
