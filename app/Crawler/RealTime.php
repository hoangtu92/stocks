<?php


namespace App\Crawler;


use App\GeneralPrice;
use App\StockOrder;
use App\StockPrice;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RealTime extends Crawler
{

    public function monitor(){
        $start = new DateTime();
        $stop = new DateTime();
        $aj = new DateTime();

        $start->setTime(9, 0, 0);
        $aj->setTime(9, 7, 0);
        $stop->setTime(13, 35, 0);

        $stocks = DB::table("dl")
            ->join("stocks", "stocks.code", "=", "dl.code")
            ->addSelect("dl.code")
            ->addSelect("stocks.type")
            ->where("dl.final", ">=", 10)
            ->whereRaw("dl.agency IS NOT NULL")
            ->where("dl.date", $this->previousDay(date("Y-m-d")))->get();

        $now = new DateTime();
        while($now >= $start && $now <= $stop){
            //Working time

            //Monitor  stocks price
            if($stocks){
                //Get realtime stock info of dl stocks
                $stockInfo = new CrawlStockInfoData($stocks->toArray());

                foreach ($stocks as $stock){


                    //Check if current stock has data
                    if(isset($stockInfo->data[$stock->code])){

                        //If stock price is not exists. create
                        $stockPrice = StockPrice::where("code", $stock->code)->where("tlong", $stockInfo->data[$stock->code]["tlong"])->first();

                        if(!$stockPrice)
                            $stockPrice = new StockPrice($stockInfo->data[$stock->code]);

                        $stockPrice->save();


                        //Perform task from 09:00 to 09:07
                        if($now <= $aj){
                            $data = $this->getStockData($stock->code, $stockInfo->data[$stock->code]["best_ask_price"]);

                            #Log::info("AJ stock data". json_encode($data));

                            if(is_numeric($data->place_order)){
                                //Short selling now
                                $order = new StockOrder([
                                    "type" => StockOrder::SELL,
                                    "date" => date("Y-m-d"),
                                    "code" => $data->code,
                                    "qty" => 1,
                                    "price" => $data->place_order,
                                    "fee" => 0,
                                    "tax" => 0,
                                ]);

                                $order->save();
                            }
                            else{
                                if($data->place_order == '馬上做多單'){
                                    //Buy long now. don’t need to wait 9:07 data
                                    $order = new StockOrder([
                                        "type" => StockOrder::BUY,
                                        "date" => date("Y-m-d"),
                                        "code" => $data->code,
                                        "qty" => 1,
                                        "price" => $data->current_price,
                                        "fee" => 0,
                                        "tax" => 0,
                                    ]);

                                    $order->save();
                                }
                                if($data->place_order == '等拉高'){
                                    //Wait a bit and Short selling when meet condition
                                    continue;
                                }
                            }

                        }
                        else{
                            //Perform task from 09:07 to 13:30
                        }

                        //Close deal??
                        $previous_order = DB::table("stock_orders")
                            ->addSelect("code")
                            ->addSelect("price")
                            ->addSelect("type")
                            ->where("code", $stock->code)
                            ->where("date", date("Y-m-d"))->first();

                        if($previous_order){
                            //Current profit = (current ‘a’ - the ‘a’ when you place order)*1000
                            $current_profit = ($stockInfo->data[$stock->code]["best_ask_price"] - $previous_order->price)*1000;

                            //Current profit %= (current ‘a’ - the ‘a’ when you place order)/ the ‘a’ when you place order*100
                            $current_profit_percent = (($stockInfo->data[$stock->code]["best_ask_price"] - $previous_order->price)/$previous_order->price)*100;
                            if($current_profit_percent >= 2){

                                //Get previous price
                                $previous_prices = StockPrice::where("code", $stock->code)->where("tlong", "<", $stockPrice->tlong)->orderBy("tlong", "desc")->take(3)->get();

                                if($previous_prices && isset($previous_prices[4])){

                                    //Close deal
                                    if($previous_order->type == StockOrder::SELL){
                                        //If short selling


                                        //If price was dropping but seems going up or stop dropping
                                        if($stockPrice->price >= $previous_prices[0]->price
                                            && $previous_prices[0]->price >= $previous_prices[1]->price
                                            && $previous_prices[1]->price < $previous_prices[2]->price){
                                            $order = new StockOrder([
                                                "type" => StockOrder::BUY,
                                                "date" => date("Y-m-d"),
                                                "code" => $stock->code,
                                                "qty" => 1,
                                                "price" => $stockPrice->price,
                                                "fee" => 0,
                                                "tax" => 0,
                                            ]);
                                        }


                                    }
                                    elseif($previous_order->type == StockOrder::BUY){
                                        //If buy long

                                        //If price was rising but seems going down or stop rising
                                        if($stockPrice->price <= $previous_prices[0]->price
                                            && $previous_prices[0]->price <= $previous_prices[1]->price
                                            && $previous_prices[1]->price > $previous_prices[2]->price) {
                                            $order = new StockOrder([
                                                "type" => StockOrder::SELL,
                                                "date" => date("Y-m-d"),
                                                "code" => $stock->code,
                                                "qty" => 1,
                                                "price" => $stockPrice->price,
                                                "fee" => 0,
                                                "tax" => 0,
                                            ]);
                                        }
                                    }


                                    $order->save();

                                }

                            }
                        }

                    }

                }

            }



            //Monitor General stock price
            $response = json_decode($this->get_content("https://mis.twse.com.tw/stock/data/mis_ohlc_TSE.txt?".http_build_query(["_" => time()])));

            if(isset($response->infoArray)){
                $info = $response->infoArray[0];
                $generalPrice = GeneralPrice::where("date", date("Y-m-d"))->where("tlong", $info->tlong)->first();
                if(!$generalPrice){
                    $generalPrice = new GeneralPrice([
                        'high' => $info->h,
                        'low' => $info->l,
                        'value' => $info->z,
                        'date' => date("Y-m-d"),
                        'tlong' => $info->tlong
                    ]);
                }

                $generalPrice->Save();
                #Log::info("Realtime general price: ". json_encode($info));
            }

            sleep(5);
            $now = new DateTime();
        }
    }

}
