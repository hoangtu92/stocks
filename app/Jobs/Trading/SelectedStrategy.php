<?php

namespace App\Jobs\Trading;

use App\Crawler\StockHelper;
use App\Jobs\Update\SaveStockPrice;
use App\Jobs\Trading\DL0_Strategy_2D;
use App\Stock;
use App\StockPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SelectedStrategy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected StockPrice $stockPrice;

    /**
     * Create a new job instance.
     *
     * @param array $stockPrice
     */
    public function __construct(array $stockPrice)
    {
        //
        $this->stockPrice = new StockPrice($stockPrice);
        SaveStockPrice::dispatch($stockPrice)->onQueue("low");
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        //
        $dl0 = [];
        if(env("STRATEGY") == "DL0_2D"){
            $dl0 = StockHelper::getDL0StocksCode($this->stockPrice->date, 2);
        }
        if(env("STRATEGY") == "DL0_1D"){
            $dl0 = StockHelper::getDL0StocksCode($this->stockPrice->date, 1);
        }

        if(in_array($this->stockPrice->code, $dl0)){
            DL0_Strategy_2D::dispatchNow($this->stockPrice);
        }

        if(env("STRATEGY") == "DL1_AJ" || 1){

            $stock = StockHelper::getStockDl($this->stockPrice);

            if($stock) {

                #Log::info("{$stockPrice->current_time}: {$stock['code']} | Open: {$stock['open']} | Low: {$stock['low']} | High: {$stock['high']} | 09:07: {$stock['price_907']}");

                $update = false;
                if (!$stock['open'] || $stock['open'] <= 0) {
                    $stock['open'] = $this->stockPrice->open;
                    $update = true;
                }

                if (!$stock['high'] || $this->stockPrice->high > $stock['high']) {
                    $stock['high'] = $this->stockPrice->high;
                    $update = true;
                }

                if (!$stock['low'] || $this->stockPrice['low'] < $stock['low']) {
                    $stock['low'] = $this->stockPrice->low;
                    $update = true;
                }

                if ($this->stockPrice->stock_time["hours"] == 9 && $this->stockPrice->stock_time["minutes"] >= 7 && (!$stock['price_907'] || $stock['price_907'] <= 0)) {
                    $stock['price_907'] = $this->stockPrice->current_price;
                    $update = true;
                }

                if ($update) {
                    $data = [
                        "open" => $stock['open'],
                        "low" => $stock["low"],
                        "high" => $stock["high"],
                        "price_907" => $stock["price_907"] ? $stock["price_907"] : null,
                    ];
                    DB::table("dl")->where("date", "=", $stock['date'])
                        ->where("code", "=", $stock['code'])->update($data);

                    TickShortSell1::dispatchNow($this->stockPrice);

                    Redis::hmset("stock:dl#{$this->stockPrice->code}", $stock);
                }
            }

        }

        Redis::hmset("Stock:previousPrice#{$this->stockPrice->code}#{$this->stockPrice->date}", $this->stockPrice->toArray());
    }
}
