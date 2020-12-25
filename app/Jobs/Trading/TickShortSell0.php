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
    protected $previous_20_mins_price;
    protected $lowest_updated;
    protected $highest_updated;
    protected $lowest_hasnot_update_for_over_1_mins;
    protected $highest_hasnot_update_for_over_20_mins;

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
            ->orderByDesc("tlong")
            ->get();

        $this->previous_order = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", true)
            ->where("order_type", "=", StockOrder::DL0)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderByDesc("tlong2")
            ->first();


        $stockDate = date_create_from_format("Y-m-d H:i", $this->stockPrice->time->format("Y-m-d H:i"));
        $p1m = $stockDate->getTimestamp() - 60;
        $p20m = $stockDate->getTimestamp() - (60*20);

        $this->previous_1_mins_price = Redis::hgetall("Stock:prices#{$this->stockPrice->code}|{$p1m}");
        $this->previous_20_mins_price = Redis::hgetall("Stock:prices#{$this->stockPrice->code}|{$p20m}");

        $this->previous_price = Redis::hgetall("Stock:previousPrice#{$this->stockPrice->code}");


        $this->general_start = StockHelper::getGeneralStart($this->stockPrice->date);
        $this->yesterday_final = StockHelper::getYesterdayFinal($this->stockPrice->date);

        $this->lowest_updated = $this->previous_price && $this->previous_price['low'] > $this->stockPrice->low;
        $this->highest_updated = $this->previous_price && $this->previous_price['high'] < $this->stockPrice->high;
        $this->lowest_hasnot_update_for_over_1_mins = $this->previous_1_mins_price && $this->previous_1_mins_price['low'] == $this->stockPrice->low;
        $this->highest_hasnot_update_for_over_20_mins = $this->previous_20_mins_price && $this->previous_20_mins_price['high'] == $this->stockPrice->high;
        $this->stock_trend = $this->previous_1_mins_price ? $this->previous_1_mins_price['best_ask_price'] > $this->stockPrice->best_ask_price ? "DOWN" : "UP" : false;

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

        $general_is_raising = $this->general_trend == "UP";
        $stock_is_raising = $this->highest_updated;
        $general_start_high_then_drop_below_y = $this->general_start > $this->yesterday_final && $this->current_general['low'] < $this->yesterday_final;
        $general_is_dropping = $this->current_general && $this->current_general['value'] < $this->current_general['high'];

        $high_range = (($this->stockPrice->high - $this->stockPrice->yesterday_final)/$this->stockPrice->yesterday_final)*100;
        $low_range = (($this->stockPrice->low - $this->stockPrice->yesterday_final)/$this->stockPrice->yesterday_final)*100;

        //Selling condition
        $price_is_dropping_and_lower_than_yesterday_final = $this->lowest_updated &&
            (($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] > 10) || $this->stockPrice->stock_time['hours'] > 9) &&
            $this->stockPrice->best_bid_price <= $this->stockPrice->yesterday_final;

        //Buy back condition
        $end_of_day = $this->stockPrice->stock_time["hours"] >= 13 && $this->stockPrice->stock_time["minutes"] > 10;
        $price_above_yesterday_final = $this->stockPrice->best_ask_price > $this->stockPrice->yesterday_final;
        $current_price_greater_than_previous_sold = $this->previous_order && $this->stockPrice->best_ask_price >= $this->previous_order->sell;



        //Check if there is un close order?
        if(!$this->previous_order && count($this->unclosed_orders) == 0){

            if($price_is_dropping_and_lower_than_yesterday_final) {
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
                                echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   SEL   AT  {$unclosed_order->sell}\n";


                                $unclosed_order->buy = $unclosed_order->sell > 100 ? $unclosed_order->sell - 1 : $unclosed_order->sell - 0.4;
                                $unclosed_order->save();

                                if($this->lowest_updated)
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
                        if ($unclosed_order->buy >= $this->stockPrice->best_ask_price) {
                            //Order confirmed closed
                            $unclosed_order->closed = true;
                            $unclosed_order->tlong2 = $this->stockPrice->tlong;
                            $unclosed_order->save();
                            echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   GAI   PROFIT: {$unclosed_order->profit_percent}\n";
                            return;

                        } else {

                            $profit = $unclosed_order->calculateProfit($this->stockPrice->best_ask_price);
                            $profit_loss_more_than_1900 = $profit <= -1800;

                            $fee = $this->stockPrice->best_ask_price*$unclosed_order->qty* 1.425;
                            $final_buy = $this->stockPrice->best_ask_price*$unclosed_order->qty*1000 + $fee;
                            $profit_percent = ($profit/$final_buy)*100;

                            $date = new DateTime();
                            $date->setDate($this->stockPrice->stock_time["year"], $this->stockPrice->stock_time["mon"], $this->stockPrice->stock_time["mday"]);
                            $date->setTime(9, 0, 0);
                            $time_since_begin = ($this->stockPrice->tlong / 1000 - $date->getTimestamp()) / 60;
                            $time_since_order_confirmed = ($this->stockPrice->tlong - $unclosed_order->tlong) / 1000 / 60;

                            #echo "HIGH_RANGE: {$high_range} | LOW_RANGE: {$low_range}\n";

                            if ($time_since_begin > 10 || $time_since_begin < 10 && $time_since_order_confirmed > 3) {

                                if ( ($this->stock_trend =="UP" && $this->previous_order && $this->previous_order->profit_percent > 0) ||

                                    ($this->stock_trend =="UP" && $time_since_begin < 10) ||

                                    $this->lowest_hasnot_update_for_over_1_mins && $this->highest_updated ||

                                    ($time_since_order_confirmed >= 20 && $this->highest_updated) ||

                                    $profit_loss_more_than_1900 ||

                                    $end_of_day ||

                                    $price_above_yesterday_final  ||

                                    $current_price_greater_than_previous_sold ||

                                    //Im predict that when highest value is not a great high. then by the end price will goes up higher. So close immediately pls
                                    $this->stock_trend =="UP" && $high_range < 2 && $low_range < -2 && $profit_percent <= -2

                                ) {

                                    if($this->stockPrice->best_ask_price > 0){
                                        $unclosed_order->buy = $this->stockPrice->best_ask_price;
                                        $unclosed_order->tlong2 = $this->stockPrice->tlong;
                                        $unclosed_order->closed = true;
                                        $unclosed_order->save();
                                        $reason = [];

                                        if($this->stock_trend =="UP" && $this->previous_order && $this->previous_order->profit_percent > 0)
                                            $reason[]= "PRICE UP + LAST ORDER GAIN";

                                        if($this->stock_trend == "UP" && $time_since_begin < 10)
                                            $reason[] = "PRICE UP FIRST 10M";

                                        if($this->lowest_hasnot_update_for_over_1_mins && $this->highest_updated)
                                            $reason[] = "L FIXED OVER 1M + H UPDATED";

                                        if($time_since_order_confirmed >= 20 && $this->highest_updated)
                                            $reason[]= "TIME > 20M AND H UPDATED";

                                        if($profit_loss_more_than_1900)
                                            $reason[] = "PROFIT < -1800";

                                        if($end_of_day)
                                            $reason[] = "EOD";

                                        if($price_above_yesterday_final)
                                            $reason[] = "PRICE > Y: {$this->stockPrice->best_ask_price}/{$this->stockPrice->yesterday_final}";

                                        if($current_price_greater_than_previous_sold)
                                            $reason[] = "PRICE > LAST SOLD: {$this->stockPrice->best_ask_price}/{$this->previous_order->sell}";

                                        if($this->stock_trend =="UP" && $high_range < 2 && $low_range > -2 && $profit_percent <= -2)
                                            $reason[] = "PRICE UP + PROFIT PERCENT < -2 + POTENTIAL RAISE HIGHER";

                                        $reason = implode(", ", $reason);

                                        echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   LOS   PROFIT: {$unclosed_order->profit_percent} | REASON: {$reason}\n";
                                        return;
                                    }


                                }
                            }


                        }


                    }
                }
            }
            if (count($this->unclosed_orders) < 2){
                if($this->lowest_updated )
                    $this->shortSell();
            }
        }
    }

    private function shortSell($price = null){

        $f_price = $this->stockPrice->best_bid_price;

        if($price){
            $f_price = $price > 100 ? $price - 1.5 : $price - 0.2;
        }

        if($f_price <= 0 || $f_price >= $this->stockPrice->yesterday_final) return;

        if(($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] < 30 && $this->stockPrice->current_price_range >= -3.5) ||
            ($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] >= 30) ||
            ($this->stockPrice->stock_time['hours'] > 9 && $this->stockPrice->stock_time['hours'] < 13) ||
            ($this->stockPrice->stock_time['hours'] == 13 && $this->stockPrice->stock_time['minutes'] < 10)){

            $stockOrder = new StockOrder([
                "order_type" => StockOrder::DL0,
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


                $this->shortSell($stockOrder->sell);

            }
            else{
                echo "{$stockOrder->code}   {$this->stockPrice->current_time}: [{$stockOrder->id}]   TRY   AT {$f_price}\n";
            }

        }


    }

}
