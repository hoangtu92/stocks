<?php

namespace App\Jobs\Trading;

use App\Crawler\StockHelper;
use App\StockOrder;
use App\StockPrice;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class DL0_Strategy_0 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected StockPrice $currentPrice;
    protected array $currentGeneral = [];
    protected $generalTrend;
    protected $unClosedOrders;
    protected $previousOrder;
    protected bool $shouldSell = false;
    protected bool $shouldSellAnother = false;
    protected bool $shouldStop = false;

    protected float $generalY;
    protected float $generalO;
    protected $previousPrice;
    protected $EOD;
    protected float $timeSinceBegin;
    protected bool $lowestUpdated = false;
    protected bool $highestUpdated = false;
    protected bool $highWasGreat = false;
    protected bool $lowWasGreat = false;

    protected bool $passTheTop = false;
    protected $p1mPrice;
    protected $p5mPrice;
    protected $p10mPrice;
    protected $p20mPrice;

    /**
     * Create a new job instance.
     *
     * @param StockPrice $currentPrice
     * @throws \Exception
     */
    public function __construct(StockPrice $currentPrice)
    {
        //
        $this->currentPrice = $currentPrice;
        $this->currentGeneral = StockHelper::getCurrentGeneralPrice($this->currentPrice->tlong);
        $this->generalTrend = StockHelper::getGeneralTrend($this->currentPrice, 5);
        # $this->stock_trend  = StockHelper::getStockTrend($this->currentPrice, 5);
        $this->unClosedOrders = StockOrder::where("code", $this->currentPrice->code)
            ->where("closed", "=", false)
            ->where("order_type", "=", StockOrder::DL0)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->currentPrice->date)
            ->orderBy("tlong")
            ->get();

        $this->previousOrder = StockOrder::where("code", $this->currentPrice->code)
            ->where("closed", "=", true)
            ->where("order_type", "=", StockOrder::DL0)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->currentPrice->date)
            ->orderByDesc("tlong2")
            ->first();

        $this->previousPrice = Redis::hgetall("Stock:previousPrice#{$this->currentPrice->code}");
        $stockDate = date_create_from_format("Y-m-d H:i", $this->currentPrice->time->format("Y-m-d H:i"));
        $p1m = $stockDate->getTimestamp() - 60;
        $p5m = $stockDate->getTimestamp() - (60 * 5);
        $p10m = $stockDate->getTimestamp() - (60 * 10);
        $p20m = $stockDate->getTimestamp() - (60 * 20);

        $this->p1mPrice = Redis::hgetall("Stock:prices#{$this->currentPrice->code}|{$p1m}");
        $this->p5mPrice = Redis::hgetall("Stock:prices#{$this->currentPrice->code}|{$p5m}");
        $this->p10mPrice = Redis::hgetall("Stock:prices#{$this->currentPrice->code}|{$p10m}");
        $this->p20mPrice = Redis::hgetall("Stock:prices#{$this->currentPrice->code}|{$p20m}");

        //General
        $this->generalO = StockHelper::getGeneralStart($this->currentPrice->date);
        $this->generalY = StockHelper::getYesterdayFinal($this->currentPrice->date);

        $this->EOD = $this->currentPrice->stock_time["hours"] >= 13 && $this->currentPrice->stock_time["minutes"] > 10;

        $date = new DateTime();
        $date->setDate($this->currentPrice->stock_time["year"], $this->currentPrice->stock_time["mon"], $this->currentPrice->stock_time["mday"]);
        $date->setTime(9, 0, 0);
        $this->timeSinceBegin = round(($this->currentPrice->tlong / 1000 - $date->getTimestamp()) / 60);


        $this->analyzecurrentPrice();

    }

    /**
     * Short sell
     * if price is null, it will sell at market price
     * @param null $price
     */
    private function sell($price = null)
    {

        $f_price = $this->currentPrice->best_bid_price;

        if ($price) {
            $f_price = $price > 100 ? $price - 1.5 : $price - 0.2;
        }

        if ($f_price <= 0) return;

        $stockOrder = new StockOrder([
            "order_type" => StockOrder::DL0,
            "deal_type" => StockOrder::SHORT_SELL,
            "date" => $this->currentPrice->date,
            "code" => $this->currentPrice->code,
            "qty" => round(150 / $f_price),//$this->general_start < $this->yesterday_final ? 1 : round(150/$f_price),
            "sell" => $f_price,
            "tlong" => !$price ? $this->currentPrice->tlong : NULL,
            "closed" => false,
            "created_at" => $this->currentPrice->time->format("Y-m-d H:i:s")
        ]);
        $stockOrder->save();

        if (!$price) {

            //Buy price is expected to be lower than sell price 1/0.4 points
            $stockOrder->buy = $this->getBuyPrice($f_price);
            $stockOrder->save();

            echo "{$stockOrder->code}   {$this->currentPrice->current_time}: [{$stockOrder->id}]   SEL   AT {$f_price}\n";

        } else {
            echo "{$stockOrder->code}   {$this->currentPrice->current_time}: [{$stockOrder->id}]   TRY   AT {$f_price}\n";
        }

    }


    /**
     * Stop loss
     * @param $unclosedOrder
     * @param array $reason
     */
    private function stop($unclosedOrder, array $reason)
    {

        if (count($reason) <= 0 || $this->currentPrice->best_ask_price <= 0) return;

        $reason = implode(", ", $reason);

        $unclosedOrder->buy = $this->currentPrice->best_ask_price;
        $unclosedOrder->tlong2 = $this->currentPrice->tlong;
        $unclosedOrder->closed = true;
        $unclosedOrder->save();

        echo "{$unclosedOrder->code}   {$this->currentPrice->current_time}: [{$unclosedOrder->id}]   LOS   PROFIT: {$unclosedOrder->profit_percent}%/{$unclosedOrder->profit} | REASON: {$reason}\n";

        $remain_unclosed_order = StockOrder::where("code", $this->currentPrice->code)->where("date", $this->currentPrice->date)->where("closed", false)->first();
        if ($remain_unclosed_order) {
            $remain_unclosed_order->buy = $this->currentPrice->best_ask_price;
            $remain_unclosed_order->closed = true;
            $remain_unclosed_order->tlong2 = $this->currentPrice->tlong;
            $remain_unclosed_order->save();

            $order_profit = round($remain_unclosed_order->profit_percent, 2);
            echo "{$remain_unclosed_order->code}   {$this->currentPrice->current_time}: [{$remain_unclosed_order->id}]   LOS   PROFIT: {$remain_unclosed_order->profit_percent}%/{$remain_unclosed_order->profit} | REASON: FOLLOW THE STOP\n";
        }

    }

    private function gain($order)
    {
        //Order confirmed closed
        $order->closed = true;
        $order->tlong2 = $this->currentPrice->tlong;
        $order->save();
        echo "{$order->code}   {$this->currentPrice->current_time}: [{$order->id}]   GAI   PROFIT: {$order->profit_percent}%/{$order->profit}\n";
    }

    private function cancelOrder($order)
    {
        $created_at = date_create_from_format("Y-m-d H:i:s", $order->created_at);
        $time_since_order_requested = round(($this->currentPrice->tlong - $created_at->getTimestamp() * 1000) / 1000 / 60);
        if ($time_since_order_requested > 5 || $this->currentPrice->stock_time['hours'] == 13 && $this->currentPrice->stock_time['minutes'] >= 20) {
            $order->delete();
            echo "{$order->code}   {$this->currentPrice->current_time}: [{$order->id}]   CAN   EXPIRED: {$time_since_order_requested} MIN \n";
        }
    }

    function getProfitPercent(StockOrder $order)
    {
        $profit = $order->calculateProfit($this->currentPrice->best_ask_price);

        $fee = $this->currentPrice->best_ask_price * $order->qty * 1.425 * 0.38;
        $final_buy = $this->currentPrice->best_ask_price * $order->qty * 1000 + $fee;
        $profit_percent = $final_buy > 0 ? ($profit / $final_buy) * 100 : 0;

        Redis::set("Stock:profit_percent#{$this->currentPrice->code}", $profit_percent);
        Redis::set("Stock:profit#{$this->currentPrice->code}", $profit);

        return $profit_percent;
    }

    function getBuyPrice($order_or_price){
        if(is_numeric($order_or_price))
            return $order_or_price > 100 ? $order_or_price - 1 : $order_or_price - 0.5;
        else
        return $order_or_price->sell > 100 ? $order_or_price->sell - 1 : $order_or_price->sell - 0.7;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //

        $this->stockPattern();
        return;

        if (!$this->previousOrder && count($this->unClosedOrders) == 0) {

            if ($this->shouldSell) {
                $this->sell();
            }
        } else {
            if (count($this->unClosedOrders) > 0) {
                foreach ($this->unClosedOrders as $unclosedOrder) {

                    //If order hasn't confirmed done yet
                    if (!$unclosedOrder->tlong) {

                        //Compare sell price with current ask price. If sell price
                        if ($unclosedOrder->sell <= $this->currentPrice->best_bid_price) {

                            //Confirm previous order sold!! Request to close it
                            $unclosedOrder->tlong = $this->currentPrice->tlong;
                            $unclosedOrder->buy = $this->getBuyPrice($unclosedOrder);
                            $unclosedOrder->save();

                            echo "{$unclosedOrder->code}   {$this->currentPrice->current_time}: [{$unclosedOrder->id}]   SEL   AT  {$unclosedOrder->sell} | BUY AT {$unclosedOrder->buy}\n";

                        } else {
                            //Cancel pending order
                            $this->cancelOrder($unclosedOrder);

                        }
                    } elseif (!$unclosedOrder->tlong2) {
                        if ($unclosedOrder->buy >= $this->currentPrice->best_ask_price && $this->currentPrice->best_ask_price > 0) {
                            $this->gain($unclosedOrder);
                        } else {
                            $reason = $this->findStopReason($unclosedOrder);
                            if ($this->shouldStop) {
                                $this->stop($unclosedOrder, $reason);
                            }
                        }

                    }
                }
            }

            if ($this->shouldSellAnother) {
                $this->sell();
            }
        }

    }

    /**
     * Analyze current stock price. predict the outcome.
     * whether should sell or should buy
     */
    function analyzeCurrentPrice()
    {

        $this->lowestUpdated = $this->previousPrice && $this->previousPrice['low'] > $this->currentPrice->low;
        $this->highestUpdated = $this->previousPrice && $this->previousPrice['high'] < $this->currentPrice->high;
        $openRange = 100*($this->currentPrice->open - $this->currentPrice->yesterday_final)/$this->currentPrice->yesterday_final;
        $highRange = (($this->currentPrice->high - $this->currentPrice->yesterday_final) / $this->currentPrice->yesterday_final) * 100;
        $lowRange = (($this->currentPrice->low - $this->currentPrice->yesterday_final) / $this->currentPrice->yesterday_final) * 100;

        $this->highWasGreat = $this->currentPrice->high >= 100 ? $highRange > 2 : $highRange > 3.5;
        $this->lowWasGreat = $lowRange < -3.5;

        if ($this->previousPrice && $this->previousPrice['best_ask_price'] > $this->currentPrice->best_ask_price) {
            Redis::set("Stock:status#{$this->currentPrice->code}", "RISE");
            $this->passTheTop = false;
        }

        if($this->p5mPrice && $this->p1mPrice && Redis::get("Stock:status#{$this->currentPrice->code}") == "RISE"
            && $this->p1mPrice['best_ask_price'] > $this->currentPrice->best_ask_price
            && $this->p5mPrice['best_ask_price'] > $this->currentPrice->best_ask_price
            ){
            $this->passTheTop = true;
            Redis::set("Stock:status#{$this->currentPrice->code}", "FALL");
        }

        $time_since_previous_order = 0;
        if ($this->previousOrder) {
            $time_since_previous_order = ($this->currentPrice->tlong - $this->previousOrder->tlong2) / 1000 / 60;
        }

        $sellTimeOver = $this->currentPrice->stock_time['hours'] == 13 && $this->currentPrice->stock_time['minutes'] >= 8;


        /**
         * When price is opened higher than yesterday final, then dropped lower than yesterday final.
         * its mean that people are dying to to sell out.
         **/

        if (1
            && (($this->lowestUpdated && $this->highWasGreat) || $this->passTheTop)
            && $this->currentPrice->best_ask_price < $this->currentPrice->yesterday_final
            && $this->timeSinceBegin > 10
            && !$sellTimeOver
            && ($time_since_previous_order == 0 || $time_since_previous_order > 5)
        ) {
            $this->shouldSell = true;

            if(count($this->unClosedOrders) < 2) {
                $this->shouldSellAnother = true;
            }
        }


        $this->shouldStop = true;
    }

    function findStopReason(StockOrder $order){
        $previous_profit_percent = (float) Redis::get("Stock:profit_percent#{$this->currentPrice->code}");
        $previous_profit = (float) Redis::get("Stock:profit#{$this->currentPrice->code}");
        $profit = $order->calculateProfit($this->currentPrice->best_ask_price);
        $profit_percent = $this->getProfitPercent($order);
        $time_since_order_confirmed = ($this->currentPrice->tlong - $order->tlong) / 1000 / 60;

        $reason = [];

        if ($profit_percent > 0 && $profit_percent < $previous_profit_percent) {
            #$reason[] = "PROFIT DECREASING {$previous_profit_percent}/{$profit_percent}";
        }

        if($time_since_order_confirmed > 20){
            $reason[] = "TIMEOUT EXCEED 20M";
            //A long decline
        }

        if ($this->EOD) {
            $reason[] = "EOD";
        }

        if ($profit <= -1800) {
            $reason[] = "PROFIT < -1800";
        }

        if ($this->currentPrice->best_ask_price > $this->currentPrice->yesterday_final && $this->timeSinceBegin < 30) {
            $reason[] = "PRICE > Y: {$this->currentPrice->best_ask_price}/{$this->currentPrice->yesterday_final}";
        }

        if ($this->previousOrder && $this->currentPrice->best_ask_price >= $this->previousOrder->sell) {
            #$reason[] = "PRICE > LAST SOLD: {$this->currentPrice->best_ask_price}/{$this->previousOrder->sell}";
        }


        return $reason;
    }

    private function stockPattern(){
        /**
         * Analyze stock pattern
         */

        if(!$this->previousPrice) return;

        $hPoints = (array) Redis::lrange("Stock:highPoints#{$this->currentPrice->code}", 0, -1);
        $lPoints = (array) Redis::lrange("Stock:lowPoints#{$this->currentPrice->code}", 0, -1);

        //get first point
        $tmpHPrice = (float) Redis::get("Stock:tmpHPrice#{$this->currentPrice->code}");
        $tmpLPrice = (float) Redis::get("Stock:tmpLPrice#{$this->currentPrice->code}");

        $lastHPoint = (float) Redis::lindex("Stock:highPoints#{$this->currentPrice->code}", -1);
        $lastLPoint = (float) Redis::lindex("Stock:lowPoints#{$this->currentPrice->code}", -1);

        //If first point not exists Set first point to open price
        if(!$tmpHPrice){
            Redis::set("Stock:tmpHPrice#{$this->currentPrice->code}", $this->currentPrice->open);
        }

        if(!$tmpLPrice){
            Redis::set("Stock:tmpLPrice#{$this->currentPrice->code}", $this->currentPrice->open);
        }

        $highPriceRange = 0;
        $lowPriceRange = 0;

        if($tmpHPrice && $tmpLPrice){
            //If tmp price exists. Time to compare with current price
            $highPriceRange = round(100*($this->currentPrice->best_ask_price - $tmpHPrice)/$tmpHPrice, 2);
            $lowPriceRange = round(100*($this->currentPrice->best_ask_price - $tmpLPrice)/$tmpLPrice, 2);

            //When current price higher than tmp price. Price is up 1% than previous price
            //update tmp price to current price
            if($this->currentPrice->best_ask_price > $this->previousPrice['best_ask_price']){
                if($lowPriceRange > 1){
                    $tmpHPrice = $this->currentPrice->best_ask_price;
                    Redis::set("Stock:tmpHPrice#{$this->currentPrice->code}", $tmpHPrice);

                    //Bottom is made, save the bottom
                    if($lastLPoint != $tmpLPrice)
                        Redis::rpush("Stock:lowPoints#{$this->currentPrice->code}", $tmpLPrice);

                }
            }
            else{
                //When current price lower than first point. Price is going down 1% than previous price
                if($highPriceRange < -1){
                    //Update tmp point
                    $tmpLPrice = $this->currentPrice->best_ask_price;
                    Redis::set("Stock:tmpLPrice#{$this->currentPrice->code}", $tmpLPrice);

                    //The top is made. save the top
                    if($lastHPoint != $tmpHPrice)
                        Redis::rpush("Stock:highPoints#{$this->currentPrice->code}", $tmpHPrice);

                }
            }



        }

        echo "{$this->currentPrice->current_time}: [C]: {$this->currentPrice->best_ask_price}   |   [H]: {$tmpHPrice}   |   [L]:    {$tmpLPrice}    |   {$lowPriceRange}/{$highPriceRange}  |   H points: ".json_encode($hPoints)." |   L points: ".json_encode($lPoints)."\n";
    }

}
