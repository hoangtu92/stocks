<?php

namespace App\Http\Controllers;

use App\Crawler\Crawler;
use App\Crawler\DLExcludeFilter;
use App\Crawler\DLIncludeFilter;
use App\Dl;
use App\Holiday;
use App\StockOrder;
use App\StockPrice;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        StockOrder::where("date", $filter_date)->where("order_type", StockOrder::DL1)->delete();


        $stocks = $crawler->getDL1Stocks($filter_date);

        if(!$stocks)
            $stocks =  $crawler->getDL1Stocks($this->previousDay($filter_date));


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

        return redirect(route("test", ["filter_date" => $filter_date]));
    }

    public function place_order_dl0($filter_date = null){

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

        StockOrder::where("date", $filter_date)->where("order_type", StockOrder::DL0)->delete();
        /**
         * Monitor Dl0 stocks price
         */


        $includeFilter = new DLIncludeFilter($filter_date);
        $excludeFilter = new DLExcludeFilter($filter_date);

        //Get DL0 stocks
        $stocks = Dl::join("stocks", "stocks.code", "=", "dl.code")
            ->select("dl.date")
            ->addSelect("dl.dl_date")
            ->addSelect("dl.id")
            ->addSelect("dl.code")
            ->addSelect("dl.range")
            ->addSelect("dl.open")
            ->addSelect("dl.low")
            ->addSelect("dl.high")
            ->addSelect("dl.price_907")
            ->addSelect("stocks.type")
            ->where("dl.final", "<", 170)
            ->whereRaw("dl.agency IS NOT NULL")
            ->whereIn("dl.date", [
                $filter_date,
                $this->previousDay($filter_date),
                $this->previousDay($this->previousDay($filter_date)),
                $this->previousDay($this->previousDay($this->previousDay($filter_date))),
                $this->previousDay($this->previousDay($this->previousDay($this->previousDay($filter_date)))),
                $this->previousDay($this->previousDay($this->previousDay($this->previousDay($this->previousDay($filter_date))))),
            ])
            ->whereIn("dl.code", $includeFilter->stockList)
            ->whereNotIn("dl.code", $excludeFilter->stockList)
            ->get();

        # Log::debug(json_encode($stocks->toArray()));

        foreach ($stocks as $stock){
            $stockPrices = StockPrice::where("code", $stock->code)
                ->where("date", $filter_date)
                ->orderBy("tlong", "asc")->get();

            foreach($stockPrices as $stockPrice){
                $crawler->monitorDL0($stockPrice);
            }
        }

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
            "open_time" => "Open Time",
            "stock" => "股名",
            "qty" => "張數",
            "sell" => "成本",
            "fee" => "手續費",
            "tax" => "交易稅",
            "current_price" => "現價",
            "current_profit" => "損益",
            "current_profit_percent" => "獲利率",

            //"type" => "交易別",
            "order_type" => "Order_type",
           // "closed" => "Closed"
        ];

        $header2 = [
            "id" => "ID",
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
            //"type" => "交易別",
            "order_type" => "Order_type",
        ];


        $openDeal = DB::table("stock_orders")
            ->join("stocks", "stocks.code", "=", "stock_orders.code")
            ->select(DB::raw("stock_orders.code as code"))
            ->addSelect("stock_orders.date")
            ->addSelect("stock_orders.id as order_id")
            ->addSelect(DB::raw("DATE_FORMAT(FROM_UNIXTIME(tlong/1000), '%H:%i:%s') as open_time"))
            ->addSelect(DB::raw("CONCAT(stock_orders.code, '-', stocks.name) as stock"))
            ->addSelect("stock_orders.qty")
            ->addSelect("stock_orders.sell")

            ->addSelect(DB::raw("ROUND(sell * 1.425, 0) as fee"))
            ->addSelect(DB::raw("ROUND(sell * 1.5, 0) as tax"))

            // AND stock_prices.tlong <= UNIX_TIMESTAMP(CONCAT(ADDDATE(stock_prices.date, 1), ' 09:08:00'))*1000
            ->addSelect(DB::raw("(SELECT IF(best_ask_price > 0, best_ask_price, latest_trade_price) from stock_prices WHERE stock_prices.code = stock_orders.code AND stock_prices.date = stock_orders.date AND stock_prices.tlong <= UNIX_TIMESTAMP(CONCAT(stock_prices.date, ' 13:30:00'))*1000 ORDER BY stock_prices.tlong DESC LIMIT 1) as current_price"))
            ->addSelect(DB::raw("ROUND((sell - (SELECT current_price) )*1000 - (SELECT fee) - (SELECT tax), 2) as current_profit"))
            ->addSelect(DB::raw("ROUND( ((SELECT current_profit)/(sell*1000 + (SELECT tax) + (SELECT fee)))*100, 2) as current_profit_percent"))



            ->addSelect("closed")
            ->addSelect("stock_orders.order_type")

            ->where("closed", "=", false)
            ->where("date", $filter_date)
            ->orderBy("stock_orders.date", "asc")
            ->orderBy("stock_orders.created_at", "asc")
            ->get()
            ->toArray();

        $closeDeal = DB::table("stock_orders")
            ->join("stocks", "stocks.code", "=", "stock_orders.code")
            ->select(DB::raw("stock_orders.code as code"))
            ->addSelect("stock_orders.date")
            ->addSelect("stock_orders.id")

            ->addSelect(DB::raw("DATE_FORMAT(FROM_UNIXTIME(stock_orders.tlong2/1000), '%H:%i:%s') as close_time"))
            ->addSelect(DB::raw("CONCAT(stock_orders.code, '-', stocks.name) as stock"))
            ->addSelect("stock_orders.qty")

            ->addSelect(DB::raw("DATE_FORMAT(FROM_UNIXTIME(stock_orders.tlong/1000), '%H:%i:%s') as open_time"))
            ->addSelect(DB::raw("(SELECT stock_orders.sell) as first_price"))
            ->addSelect(DB::raw("(SELECT stock_orders.buy) as second_price"))

            // AND stock_prices.tlong <= UNIX_TIMESTAMP(CONCAT(ADDDATE(stock_prices.date, 1), ' 09:08:00'))*1000

            ->addSelect(DB::raw("ROUND(stock_orders.sell * 1.425, 0) as fee"))
            ->addSelect(DB::raw("ROUND(stock_orders.sell * 1.5, 0) as tax"))

            ->addSelect(DB::raw("ROUND( (SELECT (stock_orders.sell - stock_orders.buy )*1000 - (SELECT fee)  - (SELECT tax) ) , 0) as profit"))
            ->addSelect(DB::raw("ROUND( ((SELECT profit)/(stock_orders.buy*1000  ))*100, 2) as profit_percent"))



            ->addSelect("stock_orders.order_type")
            ->where("date", $filter_date)
            ->where("closed", true)
            ->distinct("code")
            ->orderBy("stock_orders.id", "asc")
            ->orderBy("stock_orders.date", "desc")
            ->orderBy("stock_orders.created_at", "asc")
            ->get()
            ->toArray();


        return view("backend.place_order")->with(compact("openDeal","closeDeal", "header", "header2", "filter_date"));
    }
}
