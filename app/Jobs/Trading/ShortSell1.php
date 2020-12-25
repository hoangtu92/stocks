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

class ShortSell1 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $stockPrice;
    protected $stock_trend;

    /**
     * Create a new job instance.
     *
     * @param StockPrice $stockPrice
     */
    public function __construct(StockPrice $stockPrice)
    {
        //
        $this->stockPrice = $stockPrice;
        $this->stock_trend = StockHelper::get5MinsStockTrend($this->stockPrice);

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        $unclosed_order = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", false)
            ->where("order_type", "=", StockOrder::DL1)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->first();

        if ($unclosed_order) {
            $data = StockHelper::getStockData(StockHelper::previousDay($unclosed_order->date), $unclosed_order->code, $this->stockPrice->current_price);

            if (!is_numeric($data->place_order)) {
                if ($data->place_order == '等拉高') {

                    //Wait a bit and Short selling when meet condition
                    //if price still going up even over the AK suggested price, don’t sell yet. Pls wait until current price drop to  < ‘h’/1.05
                    if (($this->stockPrice->high >= $data->wail_until && $this->stockPrice->current_price < $this->stockPrice->high / 1.05)

                        //OR
                        //if it’s ‘h’ > agency forecast, and it’s dropping down now. need to sell it now, don’t need to wait until 9:07
                        || ($this->stockPrice->high >= $data->agency_forecast
                            //It is dropping
                            && $this->stockPrice->current_price < $this->stockPrice->high / 1.05
                            && ($this->stock_trend == "DOWN")
                        )) {

                        //Short selling now

                        $stockOrder = new StockOrder([
                            "order_type" => StockOrder::DL1,
                            "deal_type" => StockOrder::SHORT_SELL,
                            "date" => $this->stockPrice->date,
                            "tlong" => $this->stockPrice->tlong,
                            "code" => $data->code,
                            "qty" => 1,
                            "closed" => false,
                            "sell" => $this->stockPrice->best_bid_price
                        ]);

                        $stockOrder->save();
                        Log::debug("{$this->stockPrice->stock_time["hours"]}:{$this->stockPrice->stock_time["minutes"]}:  [{$stockOrder->id}] SHORT SELL {$this->stockPrice->code} at {$this->stockPrice->best_bid_price}");


                        return;


                    }

                }
            }
            elseif (is_numeric($data->place_order) && $data->place_order > 0) {
                //Short selling now
                $stockOrder = new StockOrder([
                    "order_type" => StockOrder::DL1,
                    "deal_type" => StockOrder::SHORT_SELL,
                    "date" => $this->stockPrice->date,
                    "tlong" => $this->stockPrice->tlong,
                    "code" => $this->stockPrice->code,
                    "qty" => 1,
                    "closed" => false,
                    "sell" => $data->place_order
                ]);

                $stockOrder->save();
                Log::debug("{$this->stockPrice->stock_time["hours"]}:{$this->stockPrice->stock_time["minutes"]}:  [{$stockOrder->id}] SHORT SELL {$this->stockPrice->code} at {$this->stockPrice->best_bid_price}");


                return;

            }

        }
        else{
            BuyBackStragedy2::dispatchNow($this->stockPrice);
        }

    }


}
