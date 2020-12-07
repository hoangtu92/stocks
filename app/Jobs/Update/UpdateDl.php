<?php

namespace App\Jobs\Update;

use App\Dl;
use App\StockPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $stock = Dl::where("code", $this->stockPrice->code)->where("date", $this->stockPrice->date)->first();
        if($stock){
            if (!$stock->open) {
                $stock->open = $this->stockPrice->open;
                $stock->save();
            }

            if (!$stock->high || $this->stockPrice->high > $stock->high) {
                $stock->high = $this->stockPrice->high;
                $stock->save();
            }

            if (!$stock->low || $this->stockPrice->low < $stock->low) {
                $stock->low = $this->stockPrice->low;
                $stock->save();
            }

            if ($this->stockPrice->stock_time["hours"] == 9 && $this->stockPrice->stock_time["minutes"] >= 7 && !$stock->price_907) {
                $stock->price_907 = $this->stockPrice->current_price;
                $stock->save();
            }
        }
    }
}
