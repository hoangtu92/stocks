<?php


namespace App\Crawler\RealTime;


use App\Crawler\Crawler;
use App\Crawler\CrawlStockInfoData;
use App\Crawler\DLExcludeFilter;
use App\Crawler\DLIncludeFilter;
use App\Dl;
use App\GeneralPrice;
use App\GeneralStock;
use App\StockOrder;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RealtimeDL0 extends Crawler
{

    public function monitor()
    {
        $now = new DateTime();

        $holiday = $this->getHoliday();

        //If weekend or holiday
        if ($now->format("N") >= 6 || in_array($now->format("Y-m-d"), $holiday)) {
            return false;
        }

        $start = new DateTime();
        $stop = new DateTime();

        $start->setTime(9, 0, 0);
        $stop->setTime(13, 35, 0);

        $filter_date = $now->format("Y-m-d");

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
            ->whereRaw("DATEDIFF('{$filter_date}', dl.date) <= 3")
            ->whereIn("dl.code", $includeFilter->stockList)
            ->whereNotIn("dl.code", $excludeFilter->stockList)
            ->get();

        #Log::debug(json_encode($stocks));


        while ($now >= $start && $now <= $stop) {
            $this->callback($stocks);
        }

        return true;
    }

    public function callback($stocks)
    {

        $filter_date = date("Y-m-d");

        //Working time
        $currentGeneral = GeneralPrice::where("date", $filter_date)->orderBy("tlong", "desc")->first();
        $yesterdayGeneral = GeneralStock::where("date", $this->previousDay($filter_date))->first();

        # Log::debug(json_encode([$currentGeneral->value, $yesterdayGeneral->today_final]));

        //1. general current price > yesterday general final
        if ($currentGeneral->value > $yesterdayGeneral->today_final) {

            //Get realtime price of all stocks
            $url = $this->getUrlFromStocks($stocks->toArray());
            $stockInfo = new CrawlStockInfoData($url);

            #Log::debug(json_encode($stockInfo->data));

            /**
             * Monitor DL0 stocks price
             */
            if ($stocks) {
                //Get realtime stock info of dl 0 stocks
                foreach ($stocks as $stock) {

                    //Check if current stock has data
                    if (isset($stockInfo->data[$stock->code])) {

                        $stockPrice = $stockInfo->data[$stock->code];
                        $stockTime = getdate($stockPrice->tlong / 1000);
                        $current_price = $stockPrice->best_ask_price > 0 ? $stockPrice->best_ask_price : $stockPrice->high;

                        $current_price_range = $stockPrice->yesterday_final > 0 ? (($current_price - $stockPrice->yesterday_final) / $stockPrice->yesterday_final) * 100 : 0;
                        //2. only place order when 9:01 current price range <7%
                        if ($stockTime["hours"] == 9 && $stockTime["minutes"] == 1 && $current_price_range < 7) {


                            //3. current price >= Y , sell it now
                            if ($stockPrice->best_ask_price >= $stockPrice->yesterday_final) {
                                $stockOrder = StockOrder::where("code", $stock->code)
                                    ->where("order_type", StockOrder::DL0)
                                    ->where("date", $stockPrice->date)
                                    ->where("deal_type", StockOrder::SHORT_SELL)
                                    ->where("type", StockOrder::SELL)
                                    ->first();

                                if (!$stockOrder) {


                                    $fee = round($current_price * 1.425);
                                    $tax = round($current_price * 1.5);

                                    $stockOrder = new StockOrder([
                                        "type" => StockOrder::SELL,
                                        "order_type" => StockOrder::DL0,
                                        "deal_type" => StockOrder::SHORT_SELL,
                                        "date" => $stockPrice->date,
                                        "tlong" => $stockPrice->tlong,
                                        "code" => $stock->code,
                                        "qty" => 1,
                                        "price" => $current_price,
                                        "fee" => $fee,
                                        "tax" => $tax,
                                    ]);
                                }

                                $stockOrder->save();
                            }
                        }

                        $short_sell = StockOrder::SHORT_SELL;
                        $buy_long = StockOrder::BUY_LONG;
                        $sell = StockOrder::SELL;
                        $buy = StockOrder::BUY;

                        $previous_order = DB::table("stock_orders")
                            ->addSelect("code")
                            ->addSelect("id")
                            ->addSelect("price")
                            ->addSelect("type")
                            ->addSelect("deal_type")
                            ->addSelect("order_type")
                            ->where("code", $stock->code)
                            ->where("order_type", "=", StockOrder::DL0)
                            ->where("date", $stockPrice->date)
                            ->whereRaw("( (stock_orders.deal_type = '{$short_sell}' AND stock_orders.type = '{$sell}') OR (stock_orders.deal_type = '{$buy_long}' AND stock_orders.type = '{$buy}') )")
                            ->first();

                        if ($previous_order && $previous_order->price > 0) {

                            if ($previous_order->type == StockOrder::BUY) {
                                $buy_price = $previous_order->price;
                                $sell_price = $current_price;
                            } else {
                                $sell_price = $previous_order->price;
                                $buy_price = $current_price;
                            }

                            $current_profit = ($sell_price - $buy_price) * 1000;
                            $current_profit_percent = ($current_profit / ($buy_price * 1000)) * 100;

                            //1. Current profit >2%
                            if ($current_profit_percent >= 2) {
                                //4. stop loss rule: when current price > “prev H” . buy it back to close deal
                                $pvh = DB::table("stock_prices")
                                    ->select("high")
                                    ->addSelect("low")
                                    ->where("code", $stock->code)
                                    ->where("date", $stockPrice->date)
                                    ->where("tlong", "<", $stockPrice->tlong)
                                    ->where("high", "<", $stockPrice->high)
                                    ->where("high", ">", 0)
                                    ->groupBy("code")
                                    ->orderByDesc("tlong")
                                    ->first();

                                $pvl = DB::table("stock_prices")
                                    ->select("high")
                                    ->addSelect("low")
                                    ->where("code", $stock->code)
                                    ->where("date", $stockPrice->date)
                                    ->where("tlong", "<", $stockPrice->tlong)
                                    ->where("low", ">", $stockPrice->low)
                                    ->where("low", ">", 0)
                                    ->groupBy("code")
                                    ->orderByDesc("tlong")
                                    ->first();

                                # If pvh and pvl exists mean that stock is up/down twice
                                if ($pvh && $pvl) {

                                    $this->buyBack($previous_order, $stockPrice);

                                    //2. Meet: best bid price=lowest, and go up meet “prev H” but not > max H. And drop again

                                }
                            }


                        }


                    }
                }

            }

        }
        return true;
    }

}
