<?php

namespace App\Jobs\Trading;

use App\StockOrder;
use App\StockPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BuyBackParallel implements ShouldQueue
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

        $unclosed_orders = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", false)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderByDesc("tlong")
            ->get();

        if(!$unclosed_orders) return;

        foreach ($unclosed_orders as $unclosed_order){

            if(!$unclosed_order->buy){

                if($unclosed_order->sell == $this->stockPrice->current_price){

                    $unclosed_order->buy = $this->stockPrice->current_price - 0.4;
                    $unclosed_order->save();
                    Log::debug("{$this->stockPrice->stock_time["hours"]}:{$this->stockPrice->stock_time["minutes"]}:  [{$unclosed_order->id}] GAIN {$this->stockPrice->code} at {$this->stockPrice->current_price} | profit: {$unclosed_order->profit_percent}");


                    /**
                     * Parallfuckingel
                     */
                    $price = $unclosed_order->sell - 0.2;
                    $stockOrder = new StockOrder([
                        "order_type" => $unclosed_order->order_type,
                        "deal_type" => StockOrder::SHORT_SELL,
                        "date" => $this->stockPrice->date,
                        "tlong" => $this->stockPrice->tlong,
                        "code" => $this->stockPrice->code,
                        "qty" => ceil(150/$price),
                        "sell" => $price,
                        "closed" => false
                    ]);
                    $stockOrder->save();
                    /**
                     *
                     */

                }

            }

            else{
                if($unclosed_order->buy == $this->stockPrice->current_price){
                    $unclosed_order->closed = true;
                    $unclosed_order->tlong2 = $this->stockPrice->tlong;
                    $unclosed_order->save();
                }
            }
        }



    }
}
