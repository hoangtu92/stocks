<?php


namespace App\Crawler;

use App\Dl;
use App\Dl2;
use App\Holiday;
use App\StockOrder;
use App\StockPrice;
use DateTime;
use Illuminate\Support\Facades\Log;


class RealTime extends Crawler
{

    public function monitor($filter_date = false)
    {

        $now = new DateTime();

        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  {$now->format('Y')}")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e){
            $t[] = $e['date'];
            return $t;
        }, []);

        //If weekend or holiday
        if ($now->format("N") >= 6 || in_array($now->format("Y-m-d"), $holiday)){
            return false;
        }

        $start = new DateTime();
        $stop = new DateTime();

        if(!$filter_date)
            $filter_date = $now->format("Y-m-d");


        $start->setTime(9, 0, 0);
        $stop->setTime(13, 35, 0);


        //Play DL1
        $stocks = $this->getDL1Stocks($filter_date);

        if(!$stocks){
            $this->getDL1Stocks($this->previousDay($filter_date));
        }

        /*$dlStocks = [];
        foreach($stocks as $stock){
            $dlStocks[] = $stock->code;
        }

        $stocks_dl2 = $this->getDL2Stocks($filter_date, $dlStocks);*/

        while ($now >= $start && $now <= $stop) {
            //Working time

            /**
             * Monitor General stock price
             */
            $this->monitorGeneralStock();

            //Get realtime price of all stocks
            $url = $this->getUrlFromStocks($stocks->toArray());
            $stockInfo = new CrawlStockInfoData($url);

            #Log::debug(json_encode($stockInfo->data));

            /**
             * Monitor DL1 stocks price
             */
            if ($stocks) {
                //Get realtime stock info of dl stocks
                #$stockInfo = new CrawlStockInfoData($stocks->toArray());

                foreach ($stocks as $stock) {

                    //Check if current stock has data
                    if (isset($stockInfo->data[$stock->code])) {

                        $this->monitorStock($stock, $stockInfo->data[$stock->code]);

                    }
                }

            }

            /**
             * Monitor Dl2 stocks price
             */

            /*if($stocks_dl2){
                foreach ($stocks_dl2 as $dl2){
                    if (isset($stockPrices[$dl2->code])) {
                        $this->monitorDl2Stock($dl2, $stockPrices[$dl2->code], $filter_date);
                    }
                }
            }*/

            sleep(1);
            $now = new DateTime();
        }

        return true;
    }

}
