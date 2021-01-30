<?php

namespace App\Jobs\Trading;

use App\Crawler\StockHelper;
use App\Jobs\Analyze\MonitorOrders;
use App\Jobs\Order\PlaceOrder;
use App\StockOrder;
use App\StockPrice;
use App\StockVendors\SelectedVendor;
use Backpack\Settings\app\Models\Setting;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class DL0_Real_Strategy_2D implements ShouldQueue
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
    protected $should_profit_loss = false;
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
        if ($this->current_general) {
            $this->previous_general = StockHelper::getPreviousGeneralPrice($this->current_general['tlong']);
        }

        $this->general_trend = StockHelper::getGeneralTrend($this->stockPrice, 5);


        $stockDate = date_create_from_format("Y-m-d H:i", $this->stockPrice->time->format("Y-m-d H:i"));

        $this->previous_price = Redis::hgetall("Stock:previousPrice#{$this->stockPrice->code}");
        $this->general_start = StockHelper::getGeneralStart($this->stockPrice->date);
        $this->yesterday_final = StockHelper::getYesterdayFinal($this->stockPrice->date);

        $this->lowest_updated = $this->previous_price && $this->previous_price['low'] - $this->stockPrice->low;
        $this->highest_updated = $this->previous_price && $this->previous_price['high'] < $this->stockPrice->high;
        $this->stock_trend = $this->previous_5_mins_price ? $this->previous_5_mins_price['best_ask_price'] > $this->stockPrice->best_ask_price ? "DOWN" : "UP" : false;

        $this->high_range = $this->stockPrice->yesterday_final > 0 ? (($this->stockPrice->high - $this->stockPrice->yesterday_final) / $this->stockPrice->yesterday_final) * 100 : 0;
        $this->low_range = $this->stockPrice->yesterday_final > 0 ? (($this->stockPrice->low - $this->stockPrice->yesterday_final) / $this->stockPrice->yesterday_final) * 100 : 0;

        $this->high_was_great = $this->high_range > 2;
        $this->low_was_great = $this->low_range < -2;


        $date = new DateTime();
        $date->setDate($this->stockPrice->stock_time["year"], $this->stockPrice->stock_time["mon"], $this->stockPrice->stock_time["mday"]);
        $date->setTime(9, 0, 0);
        $this->time_since_begin = ($this->stockPrice->tlong / 1000 - $date->getTimestamp()) / 60;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //Fake vendor response
        if(Setting::get("server_status") == 0){

            //Get all orders in memory and update status of each order if matched the conditions
            $r = Redis::keys("Stock:PendingOrders:{$this->stockPrice->code}#*");
            foreach($r as $value){
                $o = Redis::hgetall(str_replace("dl0_strategy_1_database_", "", $value));
                if($o && $o["State"] == 30){
                    if($o["Price"] == 0){
                        $o["State"] = 98;
                        $o["CanCancel"] = "N";
                        $o["ConfirmTime"] = date("Ymd Hisv");
                        $o["Price"] = $o["BS"] == "S" ? $this->stockPrice->best_bid_price : $this->stockPrice->best_ask_price;
                        Redis::hmset("Stock:PendingOrders:{$o["StockID"]}#{$o["OrderNo"]}", $o);
                        echo "Order Success {$o["BS"]} {$o["StockID"]} : {$o["OrderNo"]} at {$o["Price"]}\n";
                    }
                    else{
                        if($o["BS"] == "S"){
                            if($o["Price"] <= $this->stockPrice->best_bid_price){
                                $o["State"] = 98;
                                $o["CanCancel"] = "N";
                                $o["ConfirmTime"] = date("Ymd Hisv");
                                $o["Price"] = $this->stockPrice->best_bid_price;
                                Redis::hmset("Stock:PendingOrders:{$o["StockID"]}#{$o["OrderNo"]}", $o);
                                echo "Order Success {$o["BS"]} {$o["StockID"]} : {$o["OrderNo"]} at {$o["Price"]}\n";
                            }
                        }
                        else{
                            if($o["Price"] >= $this->stockPrice->best_ask_price){
                                $o["State"] = 98;
                                $o["CanCancel"] = "N";
                                $o["ConfirmTime"] = date("Ymd Hisv");
                                $o["Price"] = $this->stockPrice->best_ask_price;
                                Redis::hmset("Stock:PendingOrders:{$o["StockID"]}#{$o["OrderNo"]}", $o);
                                echo "Order Success {$o["BS"]} {$o["StockID"]} : {$o["OrderNo"]} at {$o["Price"]}\n";
                            }
                        }
                    }
                }
            }

        }

        //best_bid_price = use when selling | The bid price refers to the highest price a buyer will pay
        //best_ask_price = use when buy back | The ask price refers to the lowest price a seller will accept

        $general_highest_updated = $this->previous_general && $this->previous_general['high'] < $this->current_general['high'];
        $general_lowest_updated = $this->previous_general && $this->previous_general['low'] > $this->current_general['low'];

        //Buy back condition
        $end_of_day = $this->stockPrice->stock_time["hours"] >= 13 && $this->stockPrice->stock_time["minutes"] > 10;
        $price_above_yesterday_final = $this->stockPrice->best_ask_price > $this->stockPrice->yesterday_final;
        $current_price_greater_than_previous_sold = $this->previous_order && $this->stockPrice->best_ask_price >= $this->previous_order->sell;

        $pendingSell = Redis::get("Stock:pendingSell#{$this->stockPrice->code}");
        $lastSold = Redis::hgetall("Stock:lastSold#{$this->stockPrice->code}");
        $pendingBuy = Redis::hgetall("Stock:pendingBuy#{$this->stockPrice->code}");
        $lastBought = Redis::hgetall("Stock:lastBought#{$this->stockPrice->code}");
        $orderCount = Redis::keys("Stock:Orders:{$this->stockPrice->code}*");

        if (1
            && ($this->stockPrice->stock_time['hours'] < 13 || ($this->stockPrice->stock_time['hours'] == 13 && $this->stockPrice->stock_time['minutes'] < 10))
            && $this->lowest_updated > 0
            && $this->stockPrice->best_bid_price <= $this->stockPrice->yesterday_final
            && $this->time_since_begin > 10
            && $pendingSell != 1 && count($orderCount) == 0
        ) {

            //If sell condition has met. place sell order at market price to obtain current price

            Redis::set("Stock:pendingSell#{$this->stockPrice->code}", 1);

            #echo "{$this->stockPrice->code}   {$this->stockPrice->current_time}: SEL AT MARKET PRICE\n";

            if(Setting::get("server_status") == 1)
                PlaceOrder::dispatch(StockOrder::SELL, $this->stockPrice->toArray())->onQueue("high");
            else
                PlaceOrder::dispatchNow(StockOrder::SELL, $this->stockPrice->toArray());

        }

        $countSuccessOrders = count(Redis::keys("Stock:SuccessOrders:{$this->stockPrice->code}*"));


        if($lastSold && $pendingBuy && $countSuccessOrders > 0 && $countSuccessOrders%2 != 0){

            echo "Pending buy available\n";

            $reason = [];

            $fee = $lastSold['price'] * 1.425 * 0.38 + $this->stockPrice->best_ask_price * 1.425 * 0.38;
            $tax = $lastSold['price'] * 1.5;

            $profit = ($lastSold['price'] - $this->stockPrice->best_ask_price) * 1000;
            $profit_after_tax_fee = $profit - $fee - $tax;
            $profit_percent = ($profit_after_tax_fee / $this->stockPrice->best_ask_price) * 100;

            $time_since_order_confirmed = ($this->stockPrice->tlong - $lastSold['tlong']) / 1000 / 60;

            if ($this->stockPrice->stock_time['hours'] >= 10) {

                if ($profit * $lastSold['qty'] <= -1800 && $general_highest_updated) {
                    $reason[] = "PROFIT < -1800 When General High Updated | {$profit}";
                }
            }

            if (($price_above_yesterday_final && $this->time_since_begin < 30)) {
                $reason[] = "PRICE > Y: {$this->stockPrice->best_ask_price}/{$this->stockPrice->yesterday_final}";
            }

            if ($this->highest_updated) {
                $reason[] = "PRICE IS RISING";
            }

            if ($this->stockPrice->current_price_range >= 6) {
                $reason[] = "PRICE RANGE ABOVE 6%";
            }

            if ($end_of_day) {
                $reason[] = "EOD";
            }

            if ($time_since_order_confirmed > 20) {
                $reason[] = "TIMEOUT";
            }

            if ($this->time_since_begin > 10 || $this->time_since_begin < 10 && $time_since_order_confirmed > 5) {

                if (count($reason) > 0 && $time_since_order_confirmed > 3) {

                    //Cancel previous buy order
                    $r = SelectedVendor::cancel($pendingBuy['OID'], $pendingBuy['OrderNo']);
                    if ($r["Status"]) {
                        echo "Cancel buy order {$pendingBuy['OrderNo']}\n";
                        Redis::del("Stock:pendingBuy#{$this->stockPrice->code}");
                        //Buy at market price
                        PlaceOrder::dispatch(StockOrder::BUY, $this->stockPrice->toArray())->onQueue("high");

                        $reason = implode(", ", $reason);

                        echo "{$this->stockPrice->code}   {$this->stockPrice->current_time}: [{$lastSold['OrderNo']}]   LOS   PROFIT: {$profit_percent} | REASON: {$reason}\n";

                        //Turn off server
                        Setting::set("server_status", 0);
                    }

                    return;

                }
            }

        }

        if(Setting::get("server_status") == 0){
            MonitorOrders::dispatchNow();
        }
    }
}
