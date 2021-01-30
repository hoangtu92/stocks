<?php

namespace App\Jobs\Update;

use App\Dl;
use App\StockPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class UpdateDl implements ShouldQueue
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
        $stock = Redis::hgetall("stock:dl#{$this->stockPrice->code}");
        if(!$stock){
            $stock = Dl::where("code", $this->stockPrice->code)->where("date", $this->stockPrice->date)->first()->toArray();
            Redis::hmset("stock:dl#{$this->stockPrice->code}", $stock);
        }
        if($stock){
            $update = false;
            if (!$stock['open']) {
                $stock['open'] = $this->stockPrice->open;
                $update = true;
            }

            if (!$stock['high'] || $this->stockPrice->high > $stock['high']) {
                $stock['high'] = $this->stockPrice->high;
                $update = true;
            }

            if (!$stock->low || $this->stockPrice->low < $stock->low) {
                $stock->low = $this->stockPrice->low;
                $update = true;
            }

            if ($this->stockPrice->stock_time["hours"] == 9 && $this->stockPrice->stock_time["minutes"] >= 7 && !$stock['price_907']) {
                $stock['price_907'] = $this->stockPrice->current_price;
                $update = true;
            }

            if($update){
                DB::table("dl")->where("date", "=", $stock['date'])
                    ->where("code", "=", $stock['code'])->update($stock);
            }
        }
    }
}
