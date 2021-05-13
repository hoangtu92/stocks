<?php

namespace App\Jobs\Rerun;

use App\Jobs\Trading\DL0_Real_Strategy_2D;
use App\Jobs\Trading\DL0_Strategy_2D;
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

    protected int $code;
    protected string $type;
    protected string $filter_date;
    public int $timeout = 0;
    public int $tries = 0;

    /**
     * Create a new job instance.
     *
     * @param $code
     * @param $filter_date
     * @param string $type
     */
    public function __construct($code, $filter_date, $type = 'fake')
    {
        //
        $this->code = $code;
        $this->filter_date = $filter_date;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $stockPrices = StockPrice::where("code", $this->code)
            ->where("date", $this->filter_date)
            ->orderBy("tlong", "asc")->get();

        foreach($stockPrices as $stockPrice){

            Redis::hmset("Stock:currentPrice#{$stockPrice->code}#{$stockPrice->date}", $stockPrice->toArray());
            Redis::hmset("Stock:prices#{$stockPrice['code']}#{$stockPrice->tlong}", $stockPrice->toArray());

            $lowest = (float) Redis::get("Stock:lowest#{$stockPrice->code}#{$stockPrice->date}");
            $highest =  (float) Redis::get("Stock:highest#{$stockPrice->code}#{$stockPrice->date}");

            if(!$lowest){
                Redis::set("Stock:lowest#{$stockPrice->code}#{$stockPrice->date}", $stockPrice->best_bid_price);
            }
            else if($stockPrice->best_bid_price < $lowest){
                //Lowest updated
                Redis::set("Stock:lowest_updated#{$stockPrice->code}#{$stockPrice->date}", 1);
                Redis::set("Stock:lowest#{$stockPrice->code}#{$stockPrice->date}", $stockPrice->best_bid_price);
            }
            else{
                Redis::set("Stock:lowest_updated#{$stockPrice->code}#{$stockPrice->date}", 0);
            }

            if(!$highest){
                Redis::set("Stock:highest#{$stockPrice->code}#{$stockPrice->date}", $stockPrice->best_bid_price);
            }
            else if($highest < $stockPrice->best_bid_price){
                //Highest updated

                Redis::set("Stock:highest_updated#{$stockPrice->code}#{$stockPrice->date}", 1);
                Redis::set("Stock:highest#{$stockPrice->code}#{$stockPrice->date}", $stockPrice->best_bid_price);
            }
            else{
                Redis::set("Stock:highest_updated#{$stockPrice->code}#{$stockPrice->date}", 0);
            }

            //Log::debug("{$stockPrice->current_time} {$lowest}");


            if($this->type == 'real'){
                DL0_Real_Strategy_2D::dispatchNow($stockPrice);
            }
            else{
                DL0_Strategy_2D::dispatchNow($stockPrice);

            }
            Redis::hmset("Stock:previousPrice#{$stockPrice->code}#{$stockPrice->date}", $stockPrice->toArray());
        }

    }
}
