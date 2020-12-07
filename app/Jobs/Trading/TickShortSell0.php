<?php

namespace App\Jobs\Trading;

use App\Crawler\StockHelper;
use App\StockOrder;
use App\StockPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TickShortSell0 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected StockPrice $stockPrice;
    protected array $current_general;
    protected $general_trend;
    protected $stock_trend;
    protected $unclosed_orders;

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
        $this->general_trend = StockHelper::getGeneralTrend($this->stockPrice, 6);
        $this->stock_trend  = StockHelper::get5MinsStockTrend($this->stockPrice);
        $this->unclosed_orders = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", false)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderByDesc("tlong")
            ->get();

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //

        Log::info("{$this->stockPrice->current_time}: V: {$this->stockPrice->current_price} | L: {$this->stockPrice->low} H: {$this->stockPrice->high}     {$this->general_trend}     {$this->current_general['current_time']}: GV: {$this->current_general['value']} | GL: {$this->current_general['low']} | GH: {$this->current_general['high']}");


        //Check if there is un close order?
        if(count($this->unclosed_orders) == 0){

            //request to create first order
            $this->shortSell($this->stockPrice->yesterday_final);
        }
        else{

            foreach ($this->unclosed_orders as $unclosed_order){

                //If order hasn't confirmed done yet
                if(!$unclosed_order->buy){

                    if($unclosed_order->sell == $this->stockPrice->current_price){

                        //Confirm previous order sold!! Request to close it
                        $unclosed_order->tlong = $this->stockPrice->tlong;
                        $unclosed_order->buy = $this->stockPrice->current_price - 0.4;
                        $unclosed_order->save();

                        //Request to sell parallel order
                        $price = $unclosed_order->sell - 0.2;
                        $this->shortSell($price);

                    }
                    //Cancel pending order
                    else{
                        $cancel_rule_1 = $this->general_trend == "UP" && floor($this->current_general['value']) >= floor($this->current_general['high']);
                        $cancel_rule_2 = $this->stock_trend == "UP" && floor($this->stockPrice->current_price) >= floor($this->stockPrice->high);
                        if($cancel_rule_1 || $cancel_rule_2 ){
                            $unclosed_order->delete();
                            echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   CAN   TT: [$this->stock_trend] GT: [$this->general_trend] \n";
                        }
                    }

                }
                elseif(!$unclosed_order->tlong2){

                    if($unclosed_order->buy == $this->stockPrice->current_price){
                        //Order confirmed closed
                        $unclosed_order->closed = true;
                        $unclosed_order->tlong2 = $this->stockPrice->tlong;
                        $unclosed_order->save();
                        echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   GAI   TT: [$this->stock_trend] GT: [$this->general_trend] | profit: {$unclosed_order->profit_percent}\n";
                        return;

                    }
                    else{
                        $close_rule_2 = $this->general_trend == "UP" &&  floor($this->current_general['value']) > floor($this->current_general['low']) && floor($this->current_general['value']) <= floor($this->current_general['high']);
                        $close_rule_3 = $this->stock_trend == "UP" && floor($this->stockPrice->current_price) > floor($this->stockPrice->low) && floor($this->stockPrice->current_price) < floor($this->stockPrice->high);
                        $close_rule_4 = $this->stock_trend == "UP" && $this->stockPrice->stock_time["hours"] > 12;
                        if($close_rule_2 || $close_rule_3 || $close_rule_4){
                            $unclosed_order->buy = $this->stockPrice->current_price;
                            $unclosed_order->tlong2 = $this->stockPrice->tlong;
                            $unclosed_order->closed = true;
                            $unclosed_order->save();
                            echo "{$unclosed_order->code}   {$this->stockPrice->current_time}: [{$unclosed_order->id}]   LOS   TT: [$this->stock_trend] GT: [$this->general_trend] \n";
                            return;

                        }
                    }


                }
            }
        }
    }

    private function shortSell($price){

        $previous_orders = StockOrder::where("code", $this->stockPrice->code)
            ->where("closed", "=", true)
            ->where("order_type", "=", StockOrder::DL0)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $this->stockPrice->date)
            ->orderByDesc("tlong2")
            ->take(2)
            ->get();

        $check_ok = true;
        if(count($previous_orders) > 0){
            $check_ok = false;
            foreach($previous_orders as $previous_order){
                if($previous_order->profit_percent > 0){
                    $check_ok = true;
                }
            }
        }

        if(!$check_ok) {
            return;
        }

        $sell_rule_1 = isset($previous_orders[0]) && $previous_orders[0]->profit_percent > 0;
        $sell_rule_2 = $this->general_trend == "DOWN" && floor($this->current_general['value']) > floor($this->current_general['low']) ;

        if($sell_rule_1 || $sell_rule_2) {

            if(($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] < 30 && $this->stockPrice->current_price_range >= -3.5) ||
                ($this->stockPrice->stock_time['hours'] == 9 && $this->stockPrice->stock_time['minutes'] >= 30) ||
                ($this->stockPrice->stock_time['hours'] > 9 && $this->stockPrice->stock_time['hours'] <= 11) ||
                ($this->stockPrice->stock_time['hours'] > 11 && $this->stockPrice->stock_time['hours'] < 12 && $this->stockPrice->current_price_range < 3)){


                $stockOrder = new StockOrder([
                    "order_type" => StockOrder::DL0,
                    "deal_type" => StockOrder::SHORT_SELL,
                    "date" => $this->stockPrice->date,
                    "code" => $this->stockPrice->code,
                    "qty" => ceil(150/$price),
                    "sell" => $price,
                    "closed" => false
                ]);
                $stockOrder->save();
                echo "{$stockOrder->code}   {$this->stockPrice->current_time}: [{$stockOrder->id}]   SEL   TT: [$this->stock_trend] GT: [$this->general_trend] \n";

            }

        }


    }
}
