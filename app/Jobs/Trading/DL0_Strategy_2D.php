<?php

namespace App\Jobs\Trading;

use App\Crawler\StockHelper;
use App\StockOrder;
use App\StockPrice;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DL0_Strategy_2D implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected StockPrice $stockPrice;
    protected $current_general;
    protected $previous_general;
    protected $general_trend;
    protected $stock_trend;
    protected $unclosed_orders;
    protected $previous_order;
    protected $general_start;
    protected $yesterday_final;
    protected $previous_price;

    protected $previous_5_mins_price;
    protected $lowest_updated;
    protected $highest_updated;
    protected $low_range;
    protected $high_range;
    protected $high_was_great;
    protected $low_was_great;
    protected $predict;

    protected $should_sell = false;
    protected $should_sell_another = false;
    protected $should_buy = false;
    protected $time_since_begin;
    protected array $prices = [];

    /**
     * Create a new job instance.
     *
     * @param StockPrice $stockPrice
     */
    public function __construct(StockPrice $stockPrice)
    {



        //
        $belowYForOneMinute = false;
        $this->stockPrice = $stockPrice;
        $this->current_general = StockHelper::getCurrentGeneralPrice($this->stockPrice->tlong);
        if($this->current_general){
            $this->previous_general =StockHelper::getPreviousGeneralPrice($this->current_general['tlong']);
        }

        $this->unclosed_orders = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", false)
            ->where("order_type", "=", StockOrder::DL0)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderBy("tlong", "asc")
            ->get();

        $this->previous_order = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", true)
            ->where("order_type", "=", StockOrder::DL0)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderByDesc("tlong2")
            ->first();

        $range = range($this->stockPrice->tlong - 600, $this->stockPrice->tlong);

        if($this->stockPrice->best_bid_price <= $this->stockPrice->yesterday_final)
            $belowYForOneMinute = true;

        foreach ($range as $tmp){
            $price = Redis::hgetall("Stock:prices#{$this->stockPrice->code}#{$tmp}");

            if($price){

                if($price['best_bid_price'] > $price['yesterday_final']){
                    $belowYForOneMinute = false;
                }

            }
        }



        $this->lowest_updated = Redis::get("Stock:lowest_updated#{$this->stockPrice->code}#{$this->stockPrice->date}") == 1;
        $this->highest_updated = Redis::get("Stock:highest_updated#{$this->stockPrice->code}#{$this->stockPrice->date}") == 1;

        $this->previous_price = Redis::hgetall("Stock:previousPrice#{$this->stockPrice->code}#{$this->stockPrice->date}");
        $this->general_start = StockHelper::getGeneralStart($this->stockPrice->date);
        $this->yesterday_final = StockHelper::getYesterdayFinal($this->stockPrice->date);

        //$this->lowest_updated = $this->previous_price && $this->previous_price['low'] - $this->stockPrice->low;
        //$this->highest_updated = $this->previous_price && $this->previous_price['high'] < $this->stockPrice->high;
        $this->stock_trend = $this->previous_5_mins_price ? $this->previous_5_mins_price['best_ask_price'] > $this->stockPrice->best_ask_price ? "DOWN" : "UP" : false;

        $this->high_range = $this->stockPrice->yesterday_final > 0 ? (($this->stockPrice->high - $this->stockPrice->yesterday_final)/$this->stockPrice->yesterday_final)*100 : 0;
        $this->low_range = $this->stockPrice->yesterday_final > 0 ? (($this->stockPrice->low - $this->stockPrice->yesterday_final)/$this->stockPrice->yesterday_final)*100 : 0;

        $this->high_was_great = $this->high_range > 2;
        $this->low_was_great = $this->low_range < -2;

        /**
         * Analyze stock price
         */
        $time_since_previous_order = 0;
        if($this->previous_order){
            $time_since_previous_order = ($this->stockPrice->tlong - $this->previous_order->tlong2) / 1000 / 60;
        }

        $date = new DateTime();
        $date->setDate($this->stockPrice->stock_time["year"], $this->stockPrice->stock_time["mon"], $this->stockPrice->stock_time["mday"]);
        $date->setTime(9, 0, 0);
        $this->time_since_begin = ($this->stockPrice->tlong / 1000 - $date->getTimestamp()) / 60;


        if(1
            && ($this->stockPrice->stock_time['hours'] < 13 || ($this->stockPrice->stock_time['hours'] == 13 && $this->stockPrice->stock_time['minutes'] < 10))
            && $this->lowest_updated
            //&& $this->stockPrice->open > $this->stockPrice->yesterday_final
            /*&& count($this->unclosed_orders) < 2
            && $belowYForOneMinute
            && $this->time_since_begin > 1*/
            && !$this->previous_order
            #&& ($time_since_previous_order == 0 || $time_since_previous_order > 5 )
        ){
            $this->should_sell = true;

        }
        $this->should_sell_another = false;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        //

        //best_bid_price = use when selling | The bid price refers to the highest price a buyer will pay
        //best_ask_price = use when buy back | The ask price refers to the lowest price a seller will accept

        $general_highest_updated = $this->previous_general && $this->previous_general['high'] < $this->current_general['high'];
        $general_lowest_updated = $this->previous_general && $this->previous_general['low'] > $this->current_general['low'];


        //Buy back condition
        $end_of_day = $this->stockPrice->stock_time["hours"] >= 13 && $this->stockPrice->stock_time["minutes"] > 10;


        //Check if there is un close order?
        if(!$this->previous_order && count($this->unclosed_orders) == 0){

            if($this->should_sell) {
                //request to create first order
                $this->shortSell();
            }
        }
        else{

            if(count($this->unclosed_orders) > 0){
                foreach ($this->unclosed_orders as $unclosed_order){

                    //If order hasn't confirmed done yet
                    if (!$unclosed_order->tlong) {

                        //Compare sell price with current ask price. If sell price
                        if ($unclosed_order->sell <= $this->stockPrice->best_bid_price) {

                            if($this->stockPrice->best_bid_price > 0){
                                //Confirm previous order sold!! Request to close it
                                $unclosed_order->tlong = $this->stockPrice->tlong;
                                $log = "{$unclosed_order->code} {$this->stockPrice->date}  {$this->stockPrice->current_time}: [{$unclosed_order->id}]   SEL   AT  {$unclosed_order->sell}\n";
                                Log::info($log);

                                $unclosed_order->buy = $unclosed_order->sell > 100 ? $unclosed_order->sell - 1 : $unclosed_order->sell - 0.4;
                                $unclosed_order->save();

                                if($this->should_sell_another)
                                    $this->shortSell($unclosed_order->sell);
                            }

                        } else {
                            //Cancel pending order
                            $created_at = date_create_from_format("Y-m-d H:i:s", $unclosed_order->created_at);
                            $time_since_order_requested = ($this->stockPrice->tlong - $created_at->getTimestamp()*1000) / 1000 / 60;
                            if ($time_since_order_requested > 5 || $this->stockPrice->stock_time['hours'] == 13 && $this->stockPrice->stock_time['minutes'] >= 20) {
                                $unclosed_order->delete();
                                $log = "{$unclosed_order->code}  {$this->stockPrice->date} {$this->stockPrice->current_time}: [{$unclosed_order->id}]   CAN   DURATION: {$time_since_order_requested} \n";
                                Log::info($log);
                            }
                        }

                    } elseif (!$unclosed_order->tlong2) {

                        //Compare buy price with current ask price. If current ask price is lower than buy price. then order success
                        if ($unclosed_order->buy >= $this->stockPrice->best_ask_price && $this->stockPrice->best_ask_price > 0) {
                            //Order confirmed closed
                            $unclosed_order->closed = true;
                            $unclosed_order->tlong2 = $this->stockPrice->tlong;
                            $unclosed_order->save();

                            $log =  "{$unclosed_order->code} {$this->stockPrice->date}  {$this->stockPrice->current_time}: [{$unclosed_order->id}]   GAI   PROFIT: {$unclosed_order->profit_percent}";
                            Log::info($log);
                            return;

                        } else {

                            $reason = [];

                            if($this->stockPrice->best_ask_price > 0){

                                $time_since_order_confirmed = ($this->stockPrice->tlong - $unclosed_order->tlong) / 1000 / 60;


                                if($this->stockPrice->best_ask_price  > $this->stockPrice->yesterday_final + $this->stockPrice->yesterday_final*0.04){
                                    $reason[] = "PRICE ABOVE Y 4%";
                                }


                                if ($this->stockPrice->current_price_range >= 7.5) {
                                    $reason[] = "PRICE RANGE ABOVE 7.5%";
                                }

                                if ($end_of_day) {
                                    $reason[] = "EOD";
                                }

                                /*if ($time_since_order_confirmed > 20) {
                                    $reason[] = "TIMEOUT";
                                }*/

                                if ($this->time_since_begin > 10 || $this->time_since_begin < 10 && $time_since_order_confirmed > 2) {

                                    if (count($reason) > 0) {

                                        $unclosed_order->buy = $this->stockPrice->best_ask_price;
                                        $unclosed_order->tlong2 = $this->stockPrice->tlong;
                                        $unclosed_order->closed = true;
                                        $unclosed_order->save();

                                        $reason = implode(", ", $reason);

                                        $log =  "{$unclosed_order->code} {$this->stockPrice->date}  {$this->stockPrice->current_time}: [{$unclosed_order->id}]   LOS   PROFIT: {$unclosed_order->profit_percent} | REASON: {$reason}";
                                        Log::info($log);


                                        return;

                                    }
                                }

                            }


                        }


                    }
                }
            }
            elseif ($this->should_sell){
                $this->shortSell();
            }
        }

    }


    private function shortSell($price = null){

        $f_price = $this->stockPrice->best_bid_price;

        if($price){
            $f_price = $price > 100 ? $price - 1.5 : $price - 0.2;
        }

        if($f_price <= 0) return;


        $stockOrder = new StockOrder([
            "order_type" => StockOrder::DL0,
            "deal_type" => StockOrder::SHORT_SELL,
            "date" => $this->stockPrice->date,
            "code" => $this->stockPrice->code,
            "qty" => $this->general_start <= 14233 ? 1 : round(60/$f_price),
            "sell" => $f_price,
            "tlong" =>  !$price ? $this->stockPrice->tlong : NULL,
            "closed" => false,
            "created_at" => $this->stockPrice->time->format("Y-m-d H:i:s")
        ]);
        $stockOrder->save();

        if(!$price){

            //Buy price is expected to be lower than sell price 1/0.4 points
            $buy_price = $f_price > 100 ? $f_price - 1 : $f_price - 0.4;
            $stockOrder->buy = $buy_price;
            $stockOrder->save();

            $log = "{$stockOrder->code} {$this->stockPrice->date}  {$this->stockPrice->current_time}: [{$stockOrder->id}]   SEL   AT {$f_price} | TRY TO BUY AT {$buy_price}";
            Log::info($log);

            if($this->should_sell_another){
                $this->shortSell($stockOrder->sell);
            }

        }
        else{
            $log = "{$stockOrder->code} {$this->stockPrice->date}  {$this->stockPrice->current_time}: [{$stockOrder->id}]   TRY   AT {$f_price} \n";
            Log::info($log);
        }



    }

}
