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
use Illuminate\Support\Facades\Redis;

class TickShortSell1 implements ShouldQueue
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

    protected $previous_1_mins_price;
    protected $previous_5_mins_price;
    protected $previous_20_mins_price;
    protected $lowest_updated;
    protected $highest_updated;
    protected $lowest_hasnot_update_for_over_1_mins;
    protected $highest_hasnot_update_for_over_20_mins;
    protected $low_range;
    protected $high_range;
    protected $high_was_great;
    protected $low_was_great;
    protected $predict;

    protected $stage_1; //When stock is at the bottom
    protected $stage_2; //When stock is rising from bottom
    protected $stage_3; // When stock price is stable for a while after rise from stage 2
    protected $stage_4; // When stock price is slowly drop from a high mountain.

    protected $should_sell = false;
    protected $should_sell_another = false;
    protected $should_buy = false;
    protected $time_since_begin;

    /**
     * Create a new job instance.
     *
     * @param StockPrice $stockPrice
     */
    public function __construct(StockPrice $stockPrice)
    {
        //
        $this->stockPrice = $stockPrice;
        $this->current_general = StockHelper::getCurrentGeneralPrice($this->stockPrice->tlong);
        if($this->current_general){
            $this->previous_general =StockHelper::getPreviousGeneralPrice($this->current_general['tlong']);
        }


        $this->general_trend = StockHelper::getGeneralTrend($this->stockPrice, 5);
        # $this->stock_trend  = StockHelper::getStockTrend($this->stockPrice, 5);
        $this->unclosed_orders = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", false)
            ->where("order_type", "=", StockOrder::DL1)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderBy("tlong")
            ->get();

        $this->previous_order = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", true)
            ->where("order_type", "=", StockOrder::DL1)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderByDesc("tlong2")
            ->first();


        $stockDate = date_create_from_format("Y-m-d H:i", $this->stockPrice->time->format("Y-m-d H:i"));
        $p1m = $stockDate->getTimestamp() - 60;
        $p5m = $stockDate->getTimestamp() - (60*5);
        $p20m = $stockDate->getTimestamp() - (60*20);

        $this->previous_1_mins_price = Redis::hgetall("Stock:prices#{$this->stockPrice->code}|{$p1m}");
        $this->previous_5_mins_price = Redis::hgetall("Stock:prices#{$this->stockPrice->code}|{$p5m}");
        $this->previous_20_mins_price = Redis::hgetall("Stock:prices#{$this->stockPrice->code}|{$p20m}");

        $this->previous_price = Redis::hgetall("Stock:previousPrice#{$this->stockPrice->code}");
        $this->general_start = StockHelper::getGeneralStart($this->stockPrice->date);
        $this->yesterday_final = StockHelper::getYesterdayFinal($this->stockPrice->date);

        $this->lowest_updated = $this->previous_price && $this->previous_price['low'] - $this->stockPrice->low;
        $this->highest_updated = $this->previous_price && $this->previous_price['high'] < $this->stockPrice->high;
        $this->lowest_hasnot_update_for_over_1_mins = $this->previous_1_mins_price && $this->previous_1_mins_price['low'] == $this->stockPrice->low;
        $this->highest_hasnot_update_for_over_20_mins = $this->previous_20_mins_price && $this->previous_20_mins_price['high'] == $this->stockPrice->high;
        $this->stock_trend = $this->previous_5_mins_price ? $this->previous_5_mins_price['best_ask_price'] > $this->stockPrice->best_ask_price ? "DOWN" : "UP" : false;

        $this->high_range = (($this->stockPrice->high - $this->stockPrice->yesterday_final)/$this->stockPrice->yesterday_final)*100;
        $this->low_range = (($this->stockPrice->low - $this->stockPrice->yesterday_final)/$this->stockPrice->yesterday_final)*100;

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
            #&& $open_price_range > 3
            #&& $this->high_was_great
            && ($this->stockPrice->stock_time['hours'] < 13 || ($this->stockPrice->stock_time['hours'] == 13 && $this->stockPrice->stock_time['minutes'] < 10))
            && $this->lowest_updated > 0
            && count($this->unclosed_orders) < 2
            && $this->stockPrice->best_bid_price <= $this->stockPrice->yesterday_final
            && $this->time_since_begin > 10
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

        #$general_is_raising = $this->general_trend == "UP";
        #$stock_is_raising = $this->highest_updated;
        #$general_start_high_then_drop_below_y = $this->general_start > $this->yesterday_final && $this->current_general['low'] < $this->yesterday_final;
        #$general_is_dropping = $this->current_general && $this->current_general['value'] < $this->current_general['high'];

        $general_highest_updated = $this->previous_general && $this->previous_general['high'] < $this->current_general['high'];
        $general_lowest_updated = $this->previous_general && $this->previous_general['low'] > $this->current_general['low'];

        //Buy back condition
        $end_of_day = $this->stockPrice->stock_time["hours"] >= 13 && $this->stockPrice->stock_time["minutes"] > 10;
        $price_above_yesterday_final = $this->stockPrice->best_ask_price > $this->stockPrice->yesterday_final;
        $current_price_greater_than_previous_sold = $this->previous_order && $this->stockPrice->best_ask_price >= $this->previous_order->sell;


        //$stock_aj = StockHelper::getStockData(StockHelper::previousDay($this->stockPrice->date), $this->stockPrice->code, $this->stockPrice->current_price);
        //If no order yet. place first order use market price
        if(count($this->unclosed_orders) == 0 && !$this->previous_order){

            /*if ($stock_aj->place_order == '等拉高') {

                if(($this->stockPrice->high >= $stock_aj->wail_until &&
                        $this->lowest_updated) ||

                    ($this->stockPrice->high >= $stock_aj->agency_forecast
                        //It is dropping
                        && $this->lowest_updated)){

                    //Silent!!!
                    $this->shortSell();
                }
            }*/
            if($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] == 7) {
                $stock_aj = StockHelper::getStockData(StockHelper::previousDay($this->stockPrice->date), $this->stockPrice->code, $this->stockPrice->current_price);

                if($stock_aj->place_order > 0){

                    $this->shortSell($stock_aj->place_order);
                }
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
                                echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   SEL   AT  {$unclosed_order->sell}\n";


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
                                echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   CAN   DURATION: {$time_since_order_requested} \n";
                            }
                        }

                    } elseif (!$unclosed_order->tlong2) {

                        //Compare buy price with current ask price. If current ask price is lower than buy price. then order success
                        if ($unclosed_order->buy >= $this->stockPrice->best_ask_price && $this->stockPrice->best_ask_price > 0) {
                            //Order confirmed closed
                            $unclosed_order->closed = true;
                            $unclosed_order->tlong2 = $this->stockPrice->tlong;
                            $unclosed_order->save();
                            echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   GAI   PROFIT: {$unclosed_order->profit_percent}\n";

                            /*$remain_unclosed_order = StockOrder::where("code", $this->stockPrice->code)->where("date", $this->stockPrice->date)->where("closed", false)->first();
                            if($remain_unclosed_order){
                                $remain_unclosed_order->buy = $this->stockPrice->best_ask_price;
                                $remain_unclosed_order->closed = true;
                                $remain_unclosed_order->tlong2 = $this->stockPrice->tlong;
                                $remain_unclosed_order->save();
                                echo "{$remain_unclosed_order->code}   {$this->stockPrice->current_time}: [{$remain_unclosed_order->id}]   GAI   PROFIT: {$remain_unclosed_order->profit_percent}\n";
                            }*/
                            return;

                        } else {

                            $reason = [];

                            if($this->stockPrice->best_ask_price > 0){

                                $previous_profit = (float) Redis::get("Stock:profit#{$this->stockPrice->code}");
                                $previous_profit_percent = (float) Redis::get("Stock:profit_percent#{$this->stockPrice->code}");
                                $profit = $unclosed_order->calculateProfit($this->stockPrice->best_ask_price);
                                $profit_percent = $this->getProfitPercent($unclosed_order);

                                $time_since_order_confirmed = ($this->stockPrice->tlong - $unclosed_order->tlong) / 1000 / 60;

                                if ($this->highest_updated) {
                                    $reason[] = "PRICE IS RISING";
                                }

                                if ($this->stockPrice->current_price_range >= 7.5) {
                                    $reason[] = "PRICE RANGE ABOVE 6%";
                                }

                                if ($end_of_day) {
                                    $reason[] = "EOD";
                                }

                                if ($this->time_since_begin > 10 || $this->time_since_begin < 10 && $time_since_order_confirmed > 2) {

                                    if (count($reason) > 0) {

                                        $unclosed_order->buy = $this->stockPrice->best_ask_price;
                                        $unclosed_order->tlong2 = $this->stockPrice->tlong;
                                        $unclosed_order->closed = true;
                                        $unclosed_order->save();

                                        $reason = implode(", ", $reason);

                                        echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   LOS   PROFIT: {$unclosed_order->profit_percent} | REASON: {$reason}\n";


                                        $remain_unclosed_order = StockOrder::where("code", $this->stockPrice->code)->where("date", $this->stockPrice->date)->where("closed", false)->first();
                                        if($remain_unclosed_order){
                                            $remain_unclosed_order->buy = $this->stockPrice->best_ask_price;
                                            $remain_unclosed_order->closed = true;
                                            $remain_unclosed_order->tlong2 = $this->stockPrice->tlong;
                                            $remain_unclosed_order->save();
                                            echo "{$remain_unclosed_order->code}   {$this->stockPrice->current_time}: [{$remain_unclosed_order->id}]   LOS   PROFIT: {$remain_unclosed_order->profit_percent} | REASON: {$reason}\n";

                                        }

                                        return;

                                    }
                                }

                            }


                        }


                    }
                }
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
                "order_type" => StockOrder::DL1,
                "deal_type" => StockOrder::SHORT_SELL,
                "date" => $this->stockPrice->date,
                "code" => $this->stockPrice->code,
                "qty" => $this->general_start < $this->yesterday_final ? 1 : round(150/$f_price),
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

                echo "{$stockOrder->code}   {$this->stockPrice->current_time}: [{$stockOrder->id}]   SEL   AT {$f_price} | TRY TO BUY AT {$buy_price}\n";


                if($this->should_sell_another){
                    $this->shortSell($stockOrder->sell);
                }

            }
            else{
                echo "{$stockOrder->code}   {$this->stockPrice->current_time}: [{$stockOrder->id}]   TRY   AT {$f_price} \n";
            }



    }

    function getProfitPercent($unclosed_order){
        $profit = $unclosed_order->calculateProfit($this->stockPrice->best_ask_price);

        $fee = $this->stockPrice->best_ask_price*$unclosed_order->qty* 1.425*0.38;
        $final_buy = $this->stockPrice->best_ask_price*$unclosed_order->qty*1000 + $fee;
        $profit_percent = $final_buy > 0 ? ($profit/$final_buy)*100 : 0;

        Redis::set("Stock:profit_percent#{$this->stockPrice->code}", $profit_percent);
        Redis::set("Stock:profit#{$this->stockPrice->code}", $profit);

        return $profit_percent;
    }


}
