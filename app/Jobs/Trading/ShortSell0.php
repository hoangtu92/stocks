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

class ShortSell0 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    protected StockPrice $stockPrice;

    /**
     * Create a new job instance.
     *
     * @param StockPrice $stockPrice
     * @throws \Exception
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

        #$trend = $current_stock_trend == "UP" ? "U" : "D";

        Log::info("{$this->stockPrice->current_time}: V: {$this->stockPrice->current_price} | L: {$this->stockPrice->low} H: {$this->stockPrice->high}              {$current_general['current_time']}: GV: {$current_general['value']} | GL: {$current_general['low']} | GH: {$current_general['high']}");


        if(!$current_general) {
                return;
        }

        $unclosed_order = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", false)
            ->where("order_type", "=", StockOrder::DL0)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderByDesc("tlong")
            ->first();

        $previous_order = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", true)
            ->where("order_type", "=", StockOrder::DL0)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderByDesc("tlong")
            ->first();


        if(!$unclosed_order){

            if ($this->stockPrice->current_price <= $this->stockPrice->yesterday_final
                && $this->stockPrice->current_price < $this->stockPrice->high) {

                if(($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] < 30 && $this->stockPrice->current_price_range >= -3.5) ||
                    ($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] >= 30) ||
                    ($this->stockPrice->stock_time['hours'] > 9)){


                    if (!$previous_order || ($previous_order && ($previous_order->profit_percent > 0 || ($previous_order->profit_percent <= 0 && $current_general['value'] < $current_general['high'])))) {
                        //Start selling

                        if($previous_order) $time_since_first_order = (($this->stockPrice->tlong - $previous_order->tlong2) / 1000)/60; //Minutes

                        if(!$previous_order || ($previous_order && $time_since_first_order < 20 && $this->stockPrice->current_price < $previous_order->sell)) {

                            $stockOrder = new StockOrder([
                                "order_type" => StockOrder::DL0,
                                "deal_type" => StockOrder::SHORT_SELL,
                                "date" => $this->stockPrice->date,
                                "tlong" => $this->stockPrice->tlong,
                                "code" => $this->stockPrice->code,
                                "qty" => ceil(150/$this->stockPrice->current_price),
                                "sell" => $this->stockPrice->best_bid_price,
                                "closed" => false
                            ]);
                            $stockOrder->save();

                            Log::debug("{$this->stockPrice->stock_time["hours"]}:{$this->stockPrice->stock_time["minutes"]}:  [{$stockOrder->id}] SHORT SELL {$this->stockPrice->code} at {$this->stockPrice->best_bid_price}");



                        }

                    }

                }


            }

        }
        BuyBackParallel::dispatchNow($this->stockPrice);

    }

}
