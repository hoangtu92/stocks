<?php

namespace App\Http\Controllers;

use App\Crawler\Crawler;
use App\Holiday;
use App\StockOrder;
use App\StockPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    //

    public function place_order($filter_date = null){

        $crawler = new Crawler();
        if(!$filter_date)
            $filter_date = date("Y-m-d");

        $d = date_create($filter_date);

        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  {$d->format('Y')}")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e){
            $t[] = $e['date'];
            return $t;
        }, []);

        //If weekend or holiday
        if ($d->format("N") >= 6 || in_array($d->format("Y-m-d"), $holiday)){
            return false;
        }

        /**
         * Monitor General stock price
         */
        //$crawler->monitorGeneralStock();


        $stocks = $crawler->getDL1Stocks($filter_date);

        if(!$stocks)
            $stocks =  $crawler->getDL1Stocks($this->previousDay($filter_date));

        //echo json_encode($stocks);

        /*$dlStocks = [];
        foreach($stocks as $stock){
            $dlStocks[] = $stock->code;
        }

        $stocks_dl2 = $crawler->getDL2Stocks($filter_date, $dlStocks);*/


        foreach ($stocks as $stock){
            $stockPrices = StockPrice::where("code", $stock->code)
                ->where("date", $filter_date)
                ->orderBy("tlong", "asc")->get();

            foreach($stockPrices as $stockPrice){

                /**
                 * ---------------------------------------------------------------------------------------------------------------
                 */

                $crawler->monitorStock($stock, $stockPrice);

            }

        }

        /**
         * Monitor Dl2 stocks price
         */

        /*if($stocks_dl2){
            foreach ($stocks_dl2 as $dl2){

                $stockPrices = StockPrice::where("code", $dl2->code)
                    ->where("date", $filter_date)
                    ->orderBy("tlong", "asc")->get();

                foreach($stockPrices as $stockPrice) {
                    $crawler->monitorDl2Stock($dl2, $stockPrice, $filter_date);
                }
            }
        }*/

        return redirect(route("test", ["filter_date" => $filter_date]));
    }

    public function test($filter_date = null, Request $request)
    {

        if(!$filter_date)
            if($request->filled("filter_date"))
                $filter_date = $request->filter_date;
            else $filter_date = date("Y-m-d");

        $header = [
            "date" => "日期",
            "stock" => "股名",
            "qty" => "張數",
            "price" => "成本",
            "current_price" => "現價",
            "current_profit" => "損益",
            "current_profit_percent" => "獲利率",
            "fee" => "手續費",
            "tax" => "交易稅",
            "type" => "交易別",
            "order_type" => "Order_type",
           // "closed" => "Closed"
        ];

        $header2 = [
            "date" => "日期",
            "open_time" => "Open Time",
            "close_time" => "Close Time",
            "stock" => "股名",
            "qty" => "張數",
            "buy" => "買入",
            "sell" => "賣出",

            "profit" => "損益",
            "profit_percent" => "獲利率",
            "fee" => "手續費",
            "tax" => "交易稅",
            "type" => "交易別",
            "order_type" => "Order_type",
        ];

        $short_sell = StockOrder::SHORT_SELL;
        $buy_long = StockOrder::BUY_LONG;
        $sell = StockOrder::SELL;
        $buy = StockOrder::BUY;

        $openDeal = DB::table("stock_orders")
            ->join("stocks", "stocks.code", "=", "stock_orders.code")
            ->select(DB::raw("stock_orders.code as code"))
            ->addSelect("stock_orders.date")
            ->addSelect(DB::raw("CONCAT(stock_orders.code, '-', stocks.name) as stock"))
            ->addSelect("stock_orders.qty")
            ->addSelect("stock_orders.price")
            // AND stock_prices.tlong <= UNIX_TIMESTAMP(CONCAT(ADDDATE(stock_prices.date, 1), ' 09:08:00'))*1000
            ->addSelect(DB::raw("(SELECT best_ask_price from stock_prices WHERE stock_prices.code = stock_orders.code AND stock_prices.date = stock_orders.date AND stock_prices.tlong <= UNIX_TIMESTAMP(CONCAT(stock_prices.date, ' 13:35:00'))*1000 ORDER BY stock_prices.tlong DESC LIMIT 1) as current_price"))
            ->addSelect(DB::raw("ROUND(IF(stock_orders.type = '{$buy}', (SELECT ((SELECT current_price) - stock_orders.price )*1000 - fee - tax), (stock_orders.price - (SELECT current_price) )*1000 + fee + tax ), 0) as current_profit"))
            ->addSelect(DB::raw("ROUND( ((SELECT current_profit)/(stock_orders.price*1000 - tax - fee))*100, 2) as current_profit_percent"))

            ->addSelect(DB::raw("ROUND(fee, 0) as fee"))
            ->addSelect(DB::raw("ROUND(tax, 0) as tax"))

            ->addSelect(DB::raw("IF(stock_orders.type = '{$sell}', 'SELL', 'BUY') as type"))
            ->addSelect(DB::raw("(SELECT so.id FROM stock_orders so 
            WHERE so.code = stock_orders.code 
            AND so.date = stock_orders.date 
            AND so.tlong > stock_orders.tlong 
            AND ((stock_orders.type = '{$sell}' AND so.type = '{$buy}') OR (stock_orders.type = '{$buy}' AND so.type = '{$sell}') )
            LIMIT 1) as closed"))
            ->addSelect("stock_orders.order_type")

            ->havingRaw("closed is NULL")
            ->where("date", $filter_date)

            ->whereRaw("(stock_orders.deal_type = '{$short_sell}' AND stock_orders.type = '{$sell}')  OR (stock_orders.deal_type = '{$buy_long}' AND stock_orders.type = '{$buy}')")
            ->orderBy("stock_orders.date", "asc")
            ->orderBy("stock_orders.created_at", "asc")
            ->get()
            ->toArray();

        $closeDeal = DB::table("stock_orders")
            ->join("stocks", "stocks.code", "=", "stock_orders.code")
            ->select(DB::raw("stock_orders.code as code"))
            ->addSelect("stock_orders.date")

            ->addSelect(DB::raw("DATE_FORMAT(FROM_UNIXTIME(stock_orders.tlong/1000), '%H:%i:%s') as close_time"))
            ->addSelect(DB::raw("CONCAT(stock_orders.code, '-', stocks.name) as stock"))
            ->addSelect("stock_orders.qty")

            ->addSelect(DB::raw("(SELECT DATE_FORMAT(FROM_UNIXTIME(tlong/1000), '%H:%i:%s') from stock_orders so WHERE so.code = stock_orders.code AND so.date = stock_orders.date AND so.deal_type = stock_orders.deal_type AND so.type != stock_orders.type LIMIT 1) as open_time"))
            ->addSelect(DB::raw("(SELECT price from stock_orders so WHERE so.code = stock_orders.code AND so.date = stock_orders.date AND so.deal_type = stock_orders.deal_type AND so.type != stock_orders.type LIMIT 1) as first_price"))
            ->addSelect(DB::raw("(SELECT stock_orders.price) as second_price"))

            // AND stock_prices.tlong <= UNIX_TIMESTAMP(CONCAT(ADDDATE(stock_prices.date, 1), ' 09:08:00'))*1000

            ->addSelect(DB::raw("ROUND(IF(stock_orders.type = '{$buy}', (SELECT ((SELECT first_price) - stock_orders.price )*1000 - fee - tax), (stock_orders.price - (SELECT first_price) )*1000 + fee + tax ), 0) as profit"))
            ->addSelect(DB::raw("ROUND( ((SELECT profit)/(stock_orders.price*1000 - tax - fee))*100, 2) as profit_percent"))

            ->addSelect(DB::raw("ROUND(fee, 0) as fee"))
            ->addSelect(DB::raw("ROUND(tax, 0) as tax"))

            ->addSelect(DB::raw("IF(stock_orders.type = '0', 'SELL', 'BUY') as type"))
            ->addSelect("stock_orders.order_type")
            ->where("date", $filter_date)
            ->whereRaw("(stock_orders.deal_type = '{$short_sell}' AND stock_orders.type = '{$buy}') OR (stock_orders.deal_type = '{$buy_long}' AND stock_orders.type = '{$sell}')")
            ->distinct("code")
            ->orderBy("stock_orders.date", "desc")
            ->orderBy("stock_orders.created_at", "asc")
            ->get()
            ->toArray();


        return view("backend.place_order")->with(compact("openDeal","closeDeal", "header", "header2", "filter_date"));
    }
}
