<?php

namespace App\Jobs\Update;

use App\StockPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveStockPrice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 0;
    protected array $stockPrice;

    /**
     * Create a new job instance.
     *
     * @param array $stockPrice
     */
    public function __construct(array $stockPrice)
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

        $exists = StockPrice::where("date", $this->stockPrice['date'])->where("code", $this->stockPrice['code'])->where("tlong", $this->stockPrice['tlong'])->first();

        if(!$exists){
            $stockPrice = new StockPrice($this->stockPrice);
            $stockPrice->save();
        }

    }

}
