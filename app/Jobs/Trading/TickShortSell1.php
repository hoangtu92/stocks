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
    protected $general_trend;
    protected $stock_trend;
    protected $unclosed_orders;
    protected $previous_order;
    protected $general_start;
    protected $yesterday_final;
    protected $previous_price;
    protected $previous_1_mins_price;
    protected $lowest_updated;
    protected $highest_updated;
    protected $lowest_hasnot_update_for_over_1_mins;

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
            ->where("order_type", "=", StockOrder::DL1)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderByDesc("tlong")
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

        $this->previous_1_mins_price = Redis::hgetall("Stock:prices#{$this->stockPrice->code}|{$p1m}");

        $this->previous_price = Redis::hgetall("Stock:previousPrice#{$this->stockPrice->code}");


        $this->general_start = StockHelper::getGeneralStart($this->stockPrice->date);
        $this->yesterday_final = StockHelper::getYesterdayFinal($this->stockPrice->date);

        $this->lowest_updated = $this->previous_price && $this->previous_price['low'] > $this->stockPrice->low;
        $this->highest_updated = $this->previous_price && $this->previous_price['high'] < $this->stockPrice->high;
        $this->lowest_hasnot_update_for_over_1_mins = $this->previous_1_mins_price && $this->previous_1_mins_price['low'] == $this->stockPrice->low;
        $this->stock_trend = $this->previous_1_mins_price ? $this->previous_1_mins_price['best_ask_price'] > $this->stockPrice->current_price ? "DOWN" : "UP" : false;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //

        $general_is_raising = $this->general_trend == "UP"; #floor($this->current_general['value']) >= floor($this->current_general['high']);

        $stock_is_raising = $this->highest_updated;
        $end_of_day = $this->stockPrice->stock_time["hours"] >= 13;
        $general_start_high_then_drop_below_y = $this->general_start > $this->yesterday_final && $this->current_general['low'] < $this->yesterday_final;
        $current_price_greater_than_previous_sold = $this->previous_order && $this->stockPrice->current_price >= $this->previous_order->sell;
        $general_is_dropping = $this->current_general['value'] < $this->current_general['high'];
        $price_is_dropping_and_lower_than_yesterday_final = $this->lowest_updated &&
            (($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] > 1) || $this->stockPrice->stock_time['hours'] > 9) &&
            $this->stockPrice->current_price < $this->stockPrice->yesterday_final;
        $price_above_yesterday_final = $this->stockPrice->current_price > $this->stockPrice->yesterday_final;

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
        //if there is previous order, use previous price
        else{

            if(count($this->unclosed_orders) > 0){
                foreach ($this->unclosed_orders as $unclosed_order){

                    //If order hasn't confirmed done yet
                    if (!$unclosed_order->tlong) {

                        # Pending order
                        if ($unclosed_order->sell >= $this->stockPrice->current_price) {

                            echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   SEL   confirmed \n";

                            if($this->stockPrice->current_price > 0){
                                //Confirm previous order sold!! Request to close it
                                $unclosed_order->tlong = $this->stockPrice->tlong;
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
                                echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   CAN   time_since_order_requested: {$time_since_order_requested} \n";
                            }
                        }

                    } elseif (!$unclosed_order->tlong2) {

                        if ($unclosed_order->buy >= $this->stockPrice->current_price) {
                            //Order confirmed closed
                            $unclosed_order->closed = true;
                            $unclosed_order->tlong2 = $this->stockPrice->tlong;
                            $unclosed_order->save();
                            echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   GAI   profit: {$unclosed_order->profit_percent}\n";
                            return;

                        } else {

                            $profit = $unclosed_order->calculateProfit($this->stockPrice->current_price) * $unclosed_order->qty;
                            $profit_loss_more_than_1900 = $profit <= -1800;

                            $date = new DateTime();
                            $date->setDate($this->stockPrice->stock_time["year"], $this->stockPrice->stock_time["mon"], $this->stockPrice->stock_time["mday"]);
                            $date->setTime(9, 0, 0);
                            $time_since_begin = ($this->stockPrice->tlong / 1000 - $date->getTimestamp()) / 60;
                            $time_since_order_confirmed = ($this->stockPrice->tlong - $unclosed_order->tlong) / 1000 / 60;

                            if ($time_since_begin > 10 || $time_since_begin < 10 && $time_since_order_confirmed > 2) {
                                if ($this->stock_trend =="UP" && $this->previous_order && $this->previous_order->profit_percent > 0 && $time_since_order_confirmed > 1 || $time_since_order_confirmed >= 20 || $profit_loss_more_than_1900 || $end_of_day || $price_above_yesterday_final  || $current_price_greater_than_previous_sold) {

                                    if($this->stockPrice->current_price > 0){
                                        $unclosed_order->buy = $this->stockPrice->current_price;
                                        $unclosed_order->tlong2 = $this->stockPrice->tlong;
                                        $unclosed_order->closed = true;
                                        $unclosed_order->save();
                                        $reason = [];

                                        if($time_since_order_confirmed >= 20)
                                            $reason[]= "time_since_order_confirmed_over_20_mins";
                                        if($profit_loss_more_than_1900)
                                            $reason[] = "profit_loss_more_than_1800";
                                        if($end_of_day)
                                            $reason[] = "end_of_day";
                                        if($price_above_yesterday_final)
                                            $reason[] = "price_above_yesterday_final";
                                        if($current_price_greater_than_previous_sold)
                                            $reason[] = "current_price_greater_than_previous_sold";
                                        if($this->stock_trend == "UP")
                                            $reason[] = "Stock UP";


                                        $reason = implode(", ", $reason);

                                        echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   LOS   Reason: {$reason} \n";
                                        return;
                                    }


                                }
                            }


                        }


                    }
                }
            }
            /*if (count($this->unclosed_orders) < 2){
                if($this->previous_order && $this->previous_order->profit_percent > 0 || $this->lowest_updated)
                    $this->shortSell();
            }*/
        }
    }

    private function shortSell($price = null){

        $f_price = $this->stockPrice->current_price;

        if($price){
            $f_price = $price > 100 ? $price - 1.5 : $price - 0.2;
        }

        if($f_price <= 0) return;

        if(($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] < 30 && $this->stockPrice->current_price_range >= -3.5) ||
            ($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] >= 30) ||
            ($this->stockPrice->stock_time['hours'] > 9 && $this->stockPrice->stock_time['hours'] < 13) ||
            ($this->stockPrice->stock_time['hours'] == 13 && $this->stockPrice->stock_time['minutes'] < 10)){


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
                #echo "[{$this->stock_trend}] P: {$this->stockPrice->current_price} | L: {$this->stockPrice->low} | H: {$this->stockPrice->high} | Y: {$this->stockPrice->yesterday_final} | GT: {$this->current_general['tlong']} | GV: {$this->current_general['value']} | GL: {$this->current_general['low']} | GH: {$this->current_general['high']}\n";
                echo "{$stockOrder->code}   {$this->stockPrice->current_time}: [{$stockOrder->id}]   SEL   AT {$this->stockPrice->current_price}\n";

                $buy_price = $this->stockPrice->current_price > 100 ? $this->stockPrice->current_price - 1 : $this->stockPrice->current_price - 0.4;
                $stockOrder->buy = $buy_price;
                $stockOrder->save();

                $this->shortSell($stockOrder->sell);

            }
            else{
                echo "{$stockOrder->code}   {$this->stockPrice->current_time}: [{$stockOrder->id}]   TRY   AT {$f_price}\n";
            }

        }


    }

}
