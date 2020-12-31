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

class TickShortSell0 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected StockPrice $stockPrice;
    protected $current_general;
    protected $general_trend;
    protected $stock_trend;
    protected $unclosed_orders;
    protected $previous_order;
    protected $general_start;
    protected $yesterday_final;
    protected $previous_price;
    protected $previous_1_mins_price;
    protected $previous_5_mins_price;
    protected $previous_10_mins_price;
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
        $this->general_trend = StockHelper::getGeneralTrend($this->stockPrice, 5);
        # $this->stock_trend  = StockHelper::getStockTrend($this->stockPrice, 5);
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

        $previous_orders_count_last_5_mins = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", true)
            ->where("order_type", "=", StockOrder::DL0)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->where("tlong2", ">=", $this->stockPrice->tlong - (1000*60*5))
            ->orderByDesc("tlong2")
            ->count();

        $previous_orders_count = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", true)
            ->where("order_type", "=", StockOrder::DL0)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderByDesc("tlong2")
            ->count();


        $stockDate = date_create_from_format("Y-m-d H:i", $this->stockPrice->time->format("Y-m-d H:i"));
        $p1m = $stockDate->getTimestamp() - 60;
        $p5m = $stockDate->getTimestamp() - (60 * 5);
        $p10m = $stockDate->getTimestamp() - (60 * 10);
        $p20m = $stockDate->getTimestamp() - (60 * 20);

        $this->previous_1_mins_price = Redis::hgetall("Stock:prices#{$this->stockPrice->code}|{$p1m}");
        $this->previous_5_mins_price = Redis::hgetall("Stock:prices#{$this->stockPrice->code}|{$p5m}");
        $this->previous_10_mins_price = Redis::hgetall("Stock:prices#{$this->stockPrice->code}|{$p10m}");
        $this->previous_20_mins_price = Redis::hgetall("Stock:prices#{$this->stockPrice->code}|{$p20m}");

        $this->previous_price = Redis::hgetall("Stock:previousPrice#{$this->stockPrice->code}");
        $this->general_start = StockHelper::getGeneralStart($this->stockPrice->date);
        $this->yesterday_final = StockHelper::getYesterdayFinal($this->stockPrice->date);


        $general_start_high_and_drop_below_y = (100*($this->general_start - $this->yesterday_final)/ $this->yesterday_final >= 0.3)
            && $this->current_general['low'] < $this->yesterday_final;

        $this->lowest_updated = $this->previous_price && $this->previous_price['low'] - $this->stockPrice->low;
        $this->highest_updated = $this->previous_price && $this->previous_price['high'] < $this->stockPrice->high;
        $this->lowest_hasnot_update_for_over_1_mins = $this->previous_1_mins_price && $this->previous_1_mins_price['low'] == $this->stockPrice->low;
        $this->highest_hasnot_update_for_over_20_mins = $this->previous_20_mins_price && $this->previous_20_mins_price['high'] == $this->stockPrice->high;
        $this->stock_trend = $this->previous_1_mins_price ? $this->previous_1_mins_price['best_ask_price'] > $this->stockPrice->best_ask_price ? "DOWN" : "UP" : false;

        $this->high_range = (($this->stockPrice->high - $this->stockPrice->yesterday_final) / $this->stockPrice->yesterday_final) * 100;
        $this->low_range = (($this->stockPrice->low - $this->stockPrice->yesterday_final) / $this->stockPrice->yesterday_final) * 100;

        $this->high_was_great = $this->stockPrice->high >= 100 ? $this->high_range > 10 : $this->high_range > 2;
        $this->low_was_great = $this->low_range < -2;

        $this->predict = false;
        //If high and low was great
        if ($this->high_was_great && $this->low_was_great) {
            //when LH was great. There is chance that price will drop
            $this->predict = "DROP";
        } //if high and low was not great
        elseif (!$this->high_was_great && !$this->low_was_great) {
            //when neither LH was great. price is unpredictable. => DO profit immediately
            $this->predict = "UNKNOWN";
        } //if high was great and low was not great
        elseif ($this->high_was_great && !$this->low_was_great) {
            //when H was great but L was not. price will drop more
            $this->predict = "DROP";
        } //if high was not great but low was great
        elseif (!$this->high_was_great && $this->low_was_great) {
            //when H was not great but L was. could rise high
            $this->predict = "RAISE";
        }


        /**
         * Analyze stock price
         */
        $open_price_range = (($this->stockPrice->open - $this->stockPrice->yesterday_final) / $this->stockPrice->yesterday_final) * 100;

        if ($open_price_range > 1.5) {
            $this->stage_3 = true;
            if ($this->stock_trend == "UP") {
                $this->stage_1 = true;
                $this->stage_2 = true;
                $this->stage_3 = false;
            } elseif ($this->stock_trend == "DOWN") {
                $this->stage_4 = true;
            }
        }

        $time_since_previous_order = 0;
        if ($this->previous_order) {
            $time_since_previous_order = ($this->stockPrice->tlong - $this->previous_order->tlong2) / 1000 / 60;
        }

        $date = new DateTime();
        $date->setDate($this->stockPrice->stock_time["year"], $this->stockPrice->stock_time["mon"], $this->stockPrice->stock_time["mday"]);
        $date->setTime(9, 0, 0);
        $this->time_since_begin = ($this->stockPrice->tlong / 1000 - $date->getTimestamp()) / 60;

        if (
            //$open_price_range > 3 &&
            $this->high_was_great
            && $this->lowest_updated >= 0.3
            && count($this->unclosed_orders) < 2
            && $this->stockPrice->best_bid_price <= $this->stockPrice->yesterday_final
            && $this->time_since_begin > 10
            && ($time_since_previous_order == 0 || $time_since_previous_order > 5)
        ) {
            #$this->should_sell = true;
        }


        if ($this->stock_trend == "UP") {
            #Redis::set("Stock:status#{$this->stockPrice->code}", "RISE");
        }
        $this->get3points();
        $points = Redis::lrange("Stock:trend#{$this->stockPrice->code}", 0, 3);
        if (
            count($points) == 4 && $points[0] == "RISE" && $points[1] == "FALL" && $points[2] == "FALL" && $points[3] == "FALL"
            && $this->time_since_begin > 10
            && count($this->unclosed_orders) < 2
            && (!$this->previous_order || $this->previous_order->profit_percent > 0 && $this->stock_trend == "DOWN")
            && $this->stockPrice->best_bid_price <= $this->stockPrice->yesterday_final
            && ($time_since_previous_order == 0 || $time_since_previous_order > 5)

        ) {
            $this->should_sell = true;
            $this->should_sell_another = true;
            #Redis::set("Stock:status#{$this->stockPrice->code}", "FALL");
        }



        if(!$this->high_was_great && $this->low_was_great && $previous_orders_count > 2){
            $this->should_buy = true;
        }



    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        return;

        //best_bid_price = use when selling | The bid price refers to the highest price a buyer will pay
        //best_ask_price = use when buy back | The ask price refers to the lowest price a seller will accept

        //Selling condition
        $price_is_dropping_and_lower_than_yesterday_final = $this->lowest_updated &&
            (($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] > 10) || $this->stockPrice->stock_time['hours'] > 9) &&
            $this->stockPrice->best_bid_price <= $this->stockPrice->yesterday_final;

        //Buy back condition
        $end_of_day = $this->stockPrice->stock_time["hours"] >= 13 && $this->stockPrice->stock_time["minutes"] > 10;
        $price_above_yesterday_final = $this->stockPrice->best_ask_price > $this->stockPrice->yesterday_final;
        $current_price_greater_than_previous_sold = $this->previous_order && $this->stockPrice->best_ask_price >= $this->previous_order->sell;


        //Check if there is un close order?
        if (!$this->previous_order && count($this->unclosed_orders) == 0) {

            if ($this->should_sell) {
                //request to create first order
                $this->shortSell();
            }
        } else {

            if (count($this->unclosed_orders) > 0) {
                foreach ($this->unclosed_orders as $unclosed_order) {

                    //If order hasn't confirmed done yet
                    if (!$unclosed_order->tlong) {

                        //Compare sell price with current ask price. If sell price
                        if ($unclosed_order->sell <= $this->stockPrice->best_bid_price) {

                            if ($this->stockPrice->best_bid_price > 0) {
                                //Confirm previous order sold!! Request to close it
                                $unclosed_order->tlong = $this->stockPrice->tlong;
                                echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   SEL   AT  {$unclosed_order->sell}\n";


                                $unclosed_order->buy = $unclosed_order->sell > 100 ? $unclosed_order->sell - 1 : $unclosed_order->sell - 0.4;
                                $unclosed_order->save();

                                if ($this->should_sell_another)
                                    $this->shortSell($unclosed_order->sell);
                            }

                        } else {
                            //Cancel pending order
                            $created_at = date_create_from_format("Y-m-d H:i:s", $unclosed_order->created_at);
                            $time_since_order_requested = ($this->stockPrice->tlong - $created_at->getTimestamp() * 1000) / 1000 / 60;
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

                            $remain_unclosed_order = StockOrder::where("code", $this->stockPrice->code)->where("date", $this->stockPrice->date)->where("closed", false)->first();
                            if ($remain_unclosed_order) {
                                $remain_unclosed_order->buy = $this->stockPrice->best_ask_price;
                                $remain_unclosed_order->closed = true;
                                $remain_unclosed_order->tlong2 = $this->stockPrice->tlong;
                                $remain_unclosed_order->save();
                                echo "{$remain_unclosed_order->code}   {$this->stockPrice->current_time}: [{$remain_unclosed_order->id}]   GAI   PROFIT: {$remain_unclosed_order->profit_percent}\n";
                            }
                            return;

                        } else {

                            $reason = [];

                            if ($this->stockPrice->best_ask_price > 0) {

                                $previous_profit_percent = (float) Redis::get("Stock:profit_percent#{$this->stockPrice->code}");
                                $previous_profit = (float) Redis::get("Stock:profit#{$this->stockPrice->code}");
                                $profit = $unclosed_order->calculateProfit($this->stockPrice->best_ask_price);
                                $profit_percent = $this->getProfitPercent($unclosed_order);

                                $time_since_order_confirmed = ($this->stockPrice->tlong - $unclosed_order->tlong) / 1000 / 60;

                                /**
                                 * Main rule
                                 */
                                if($this->should_buy){
                                    $reason[] = "TREND UP";
                                }
                                if ($this->lowest_hasnot_update_for_over_1_mins) {
                                    $reason[] = "L FIXED OVER 1M";
                                }

                                if ($this->low_was_great && $profit <= -1800) {
                                    $reason[] = "PROFIT < -1800";
                                }

                                if ($profit_percent > 0 && $profit_percent < $previous_profit_percent) {
                                    #$reason[] = "PROFIT DECREASING {$previous_profit_percent}/{$profit_percent}";
                                }

                                if(!$this->high_was_great && $this->low_was_great && $profit_percent <= -2 && $profit_percent < $previous_profit_percent){
                                    #$reason[] = "PROFIT DECREASE BADLY {$previous_profit_percent}/{$profit_percent}";
                                }

                                if ($end_of_day) {
                                    $reason[] = "EOD";
                                }


                                if (($price_above_yesterday_final && $this->time_since_begin < 30)) {
                                    #$reason[] = "PRICE > Y: {$this->stockPrice->best_ask_price}/{$this->stockPrice->yesterday_final}";
                                }

                                if ($current_price_greater_than_previous_sold) {
                                    #$reason[] = "PRICE > LAST SOLD: {$this->stockPrice->best_ask_price}/{$this->previous_order->sell}";
                                }

                                /**
                                 * Additional rule
                                 */
                                if ($this->stock_trend == "UP" && $this->previous_order && $this->previous_order->profit_percent > 0) {
                                    #$reason[]= "PRICE UP + LAST ORDER GAIN";
                                }

                                if ($this->stock_trend == "UP" && $this->time_since_begin < 10) {
                                    #$reason[] = "PRICE UP FIRST 10M";
                                }

                                if ($time_since_order_confirmed >= 10 && $this->highest_updated) {
                                    #$reason[]= "TIME > 20M AND H UPDATED";
                                }

                                if ($this->stock_trend == "UP" && $profit_percent <= -2) {
                                    #$reason[] = "PRICE UP + PROFIT PERCENT < -2";
                                }

                                if (count($reason) > 0) {

                                    $unclosed_order->buy = $this->stockPrice->best_ask_price;
                                    $unclosed_order->tlong2 = $this->stockPrice->tlong;
                                    $unclosed_order->closed = true;
                                    $unclosed_order->save();

                                    $reason = implode(", ", $reason);

                                    echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   LOS   PROFIT: {$unclosed_order->profit_percent} | REASON: {$reason}\n";


                                    $remain_unclosed_order = StockOrder::where("code", $this->stockPrice->code)->where("date", $this->stockPrice->date)->where("closed", false)->first();
                                    if ($remain_unclosed_order) {
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
            if ($this->should_sell) {
                $this->shortSell();
            }
        }
    }

    private function shortSell($price = null)
    {

        $f_price = $this->stockPrice->best_bid_price;

        if ($price) {
            $f_price = $price > 100 ? $price - 1.5 : $price - 0.2;
        }

        if ($f_price <= 0 /*|| $f_price >= $this->stockPrice->yesterday_final*/) return;

        if (($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] < 30 && $this->stockPrice->current_price_range >= -3.5) ||
            ($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] >= 30) ||
            ($this->stockPrice->stock_time['hours'] > 9 && $this->stockPrice->stock_time['hours'] < 13) ||
            ($this->stockPrice->stock_time['hours'] == 13 && $this->stockPrice->stock_time['minutes'] < 10)) {

            $stockOrder = new StockOrder([
                "order_type" => StockOrder::DL0,
                "deal_type" => StockOrder::SHORT_SELL,
                "date" => $this->stockPrice->date,
                "code" => $this->stockPrice->code,
                "qty" => round(150 / $f_price),//$this->general_start < $this->yesterday_final ? 1 : round(150/$f_price),
                "sell" => $f_price,
                "tlong" => !$price ? $this->stockPrice->tlong : NULL,
                "closed" => false,
                "created_at" => $this->stockPrice->time->format("Y-m-d H:i:s")
            ]);
            $stockOrder->save();

            if (!$price) {

                //Buy price is expected to be lower than sell price 1/0.4 points
                $buy_price = $f_price > 100 ? $f_price - 1 : $f_price - 0.4;
                $stockOrder->buy = $buy_price;
                $stockOrder->save();

                echo "{$stockOrder->code}   {$this->stockPrice->current_time}: [{$stockOrder->id}]   SEL   AT {$f_price} | TRY TO BUY AT {$buy_price} | PREDICT: {$this->predict}\n";


                if ($this->should_sell_another) {
                    $this->shortSell($stockOrder->sell);
                }

            } else {
                echo "{$stockOrder->code}   {$this->stockPrice->current_time}: [{$stockOrder->id}]   TRY   AT {$f_price} | PREDICT: {$this->predict}\n";
            }

        }


    }

    function getProfitPercent($unclosed_order)
    {
        $profit = $unclosed_order->calculateProfit($this->stockPrice->best_ask_price);

        $fee = $this->stockPrice->best_ask_price * $unclosed_order->qty * 1.425 * 0.38;
        $final_buy = $this->stockPrice->best_ask_price * $unclosed_order->qty * 1000 + $fee;
        $profit_percent = $final_buy > 0 ? ($profit / $final_buy) * 100 : 0;

        Redis::set("Stock:profit_percent#{$this->stockPrice->code}", $profit_percent);
        Redis::set("Stock:profit#{$this->stockPrice->code}", $profit);

        return $profit_percent;
    }

    function get3points()
    {

        if ($this->stock_trend == "UP") {
            Redis::set("Stock:status#{$this->stockPrice->code}", "RISE");
            $this->add_point("RISE");

        }

        if($this->stock_trend == "DOWN"){
            Redis::set("Stock:status#{$this->stockPrice->code}", "FALL");
            $this->add_point("FALL");
        }

        $highest_data_payload = Redis::get("Stock:current_highest#{$this->stockPrice->code}");
        if($highest_data_payload){
            $highest_data = preg_split("/\|/", $highest_data_payload);
            $current_highest = $highest_data[0];
            $tlong = $highest_data[1];

            if($current_highest > 0){
                if($this->stock_trend == "UP"){
                    Redis::set("Stock:current_highest#{$this->stockPrice->code}", "{$this->stockPrice->best_ask_price}|{$this->stockPrice->tlong}");
                }
                elseif($this->stock_trend == "DOWN"){
                    Redis::rpush("Stock:highest#{$this->stockPrice->code}", $current_highest);
                }

                echo "{$current_highest}\n";
                #echo "{$highest_data_payload}\n";

                $highest_values = (array) Redis::lrange("Stock:highest#{$this->stockPrice->code}", 0, -1);
                $previous_highest = (float) Redis::lindex("Stock:highest#{$this->stockPrice->code}", -1);

                //echo json_encode($highest_values)."| {$current_highest} | {$highest_data_payload}\n";
            }
        }
        else{
            Redis::set("Stock:current_highest#{$this->stockPrice->code}", "{$this->stockPrice->open}|{$this->stockPrice->tlong}");
        }






    }

    function add_point($status){

        if(Redis::llen("Stock:trend#{$this->stockPrice->code}") == 4){
            Redis::lpop("Stock:trend#{$this->stockPrice->code}");
        }
        Redis::rpush("Stock:trend#{$this->stockPrice->code}", $status);
    }

}
