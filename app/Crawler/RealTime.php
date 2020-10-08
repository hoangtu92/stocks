<?php


namespace App\Crawler;

use App\Dl;
use App\StockPrice;
use DateTime;


class RealTime extends Crawler
{

    public function monitor($filter_date = false)
    {

        $now = new DateTime();

        if ($now->format("N") > 6){
            return $this->monitor($this->previousDay($filter_date));
        }

        $start = new DateTime();
        $stop = new DateTime();

        if(!$filter_date)
            $filter_date = $now->format("Y-m-d");


        $start->setTime(9, 0, 0);
        $stop->setTime(13, 35, 0);


        $stocks = Dl::join("stocks", "stocks.code", "=", "dl.code")
            ->select("dl.date")
            ->addSelect("dl.code")
            ->addSelect("dl.open")
            ->addSelect("dl.low")
            ->addSelect("dl.high")
            ->addSelect("dl.price_907")
            ->addSelect("dl.borrow_ticket")
            ->addSelect("stocks.type")
            ->where("dl.final", ">=", 10)
            ->where("dl.final", "<=", 170)
            ->whereRaw("dl.agency IS NOT NULL")
            ->where("dl.date", $this->previousDay($filter_date))->get();

        if(!$stocks){
            $this->monitor($this->previousDay($filter_date));
        }


        while ($now >= $start && $now <= $stop) {
            //Working time

            /**
             * Monitor General stock price
             */
            $this->monitorGeneralStock();

            /**
             * Monitor  stocks price
             */
            if ($stocks) {
                //Get realtime stock info of dl stocks
                $stockInfo = new CrawlStockInfoData($stocks->toArray());

                foreach ($stocks as $stock) {


                    //Check if current stock has data
                    if (isset($stockInfo->data[$stock->code])) {


                        //If stock price is not exists. create
                        $stockPrice = StockPrice::where("code", $stock->code)
                            ->where("date", $stockInfo->data[$stock->code]['date'])
                            ->where("tlong", $stockInfo->data[$stock->code]["tlong"])
                            ->first();

                        if (!$stockPrice) {
                            $stockPrice = new StockPrice($stockInfo->data[$stock->code]);
                            $stockPrice->save();
                        }


                        $this->monitorStock($stock, $stockPrice, $filter_date);

                    }

                }

            }


            sleep(5);
            $now = new DateTime();
        }
    }

}
