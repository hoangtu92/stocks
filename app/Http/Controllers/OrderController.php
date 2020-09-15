<?php

namespace App\Http\Controllers;

use App\Crawler\Crawler;
use App\Crawler\CrawlStockInfoData;
use App\Dl;
use App\StockOrder;
use App\StockPrice;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    //

    public function place_order(){
        $crawler = new Crawler();
        $filter_date = $this->previousDay(date("Y-m-d"));

        $d = date_create($filter_date);

        //If weekend
        if($d->format("N") == 6 || $d->format("N") == 7){
            return false;
        }

        $start = new DateTime();
        $aj = new DateTime();

        $start->setTime(9, 0, 0);
        $aj->setTime(9, 7, 0);

        $start_tmp = $start->getTimestamp()*1000;
        $aj_tmp = $aj->getTimestamp()*1000;



        $stocks = DB::table("dl")
            ->join("stocks", "stocks.code", "=", "dl.code")
            ->addSelect("dl.code")
            ->addSelect("stocks.type")
            ->where("dl.final", ">=", 10)
            ->whereRaw("dl.agency IS NOT NULL")
            ->where("dl.date", $this->previousDay(date("Y-m-d")))->get();



        foreach ($stocks as $stock){
            $stockPrices = StockPrice::where("code", $stock->code)
                ->where("date", $this->previousDay(date("Y-m-d")))
                ->whereRaw("tlong BETWEEN {$start_tmp} AND {$aj_tmp}")
                ->orderBy("tlong", "asc")->get();

            foreach($stockPrices as $stockPrice){
                $data = $crawler->getStockData($stock->code, $stockPrice->best_ask_price);


                if(!is_numeric($data->place_order)){

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
                        break;
                    }
                    if($data->place_order == '等拉高'){
                        //Wait a bit and Short selling when meet condition
                        continue;
                    }

                }
                elseif($data->place_order > 0){
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
                    break;
                }



                //Close deal??
                $previous_buy = DB::table("stock_orders")
                    ->addSelect("code")
                    ->addSelect("price")
                    ->where("type", StockOrder::BUY)
                    ->where("date", date("Y-m-d"))->first();

                if($previous_buy){
                    //Current profit = (current ‘a’ - the ‘a’ when you place order)*1000
                    $current_profit = ($stockPrice->price - $previous_buy->price)*1000;
                    //Current profit %= (current ‘a’ - the ‘a’ when you place order)/ the ‘a’ when you place order*100
                    $current_profit_percent = (($stockPrice->price - $previous_buy->price)/$previous_buy->price)*100;
                    if($current_profit_percent >= 2){
                        //Close deal
                        $order = new StockOrder([
                            "type" => StockOrder::SELL,
                            "date" => date("Y-m-d"),
                            "code" => $data->code,
                            "qty" => 1,
                            "price" => $stockPrice->price,
                            "fee" => 0,
                            "tax" => 0,
                        ]);

                        $order->save();
                    }
                }

            }

        }

        return redirect(route("test"));
    }

    public function test()
    {

        $header = [
            "date" => "日期",
            "stock" => "股名",
            "qty" => "張數",
            "price" => "成本",
            "current_profit" => "損益",
            "current_profit_percent" => "Current profit %",
            "fee" => "手續費",
            "tax" => "交易稅",
            "type" => "TYPE"
        ];

        $previous_buy = DB::table("stock_orders")
            ->addSelect("code")
            ->addSelect("price")
            ->where("type", StockOrder::BUY)
            ->where("date", date("Y-m-d"))
            ->orderByDesc("date");

        $orders = DB::table("stock_orders")
            ->leftJoinSub($previous_buy, "previous_buy", "previous_buy.code", "=", "stock_orders.code")
            ->select("stock_orders.*")
            ->addSelect(DB::raw("IF(previous_buy.price > 0, (stock_orders.price - previous_buy.price), 0) as current_profit"))
            ->addSelect(DB::raw("IF(previous_buy.price > 0, ((SELECT current_profit)/previous_buy.price)*100, 0) as current_profit_percent"))
            ->addSelect(DB::raw("IF(stock_orders.type = '0', 'SELL', 'BUY') as type"))
            ->where("date", date("Y-m-d"))
            ->get()
            ->toArray();
        return $this->toTable($orders, $header);
    }
}
