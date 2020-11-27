<?php


namespace App\Crawler\RealTime;

use App\Crawler\Crawler;
use App\Crawler\CrawlStockInfoData;
use App\GeneralPrice;
use App\GeneralStock;
use DateTime;
use Illuminate\Support\Facades\Log;


class RealTimeDL1 extends Crawler
{


    public function __invoke()
    {

        /**
         * Start to monitor stock data
         */
        Log::info("Start dl1 realtime crawl");

        $now = new DateTime();
        $start = new DateTime();
        $stop = new DateTime();

        $filter_date = $now->format("Y-m-d");


        $start->setTime(9, 0, 0);
        $stop->setTime(13, 35, 0);


        //Play DL1
        $stocks = $this->getDL1Stocks($filter_date);

        if(!$stocks){
            $this->getDL1Stocks($this->previousDay($filter_date));
        }

        $url = $this->getUrlFromStocks($stocks->toArray());

        while ($now >= $start && $now <= $stop) {
            //Working time

            //Get realtime price of all stocks

            $stockInfo = new CrawlStockInfoData($url);

            #Log::debug(json_encode($stockInfo->data));

            //Working time
            $currentGeneral = GeneralPrice::where("date", $filter_date)->orderBy("tlong", "desc")->first();
            $previousGeneral = null;

            if($currentGeneral){

                $previousGeneral = GeneralPrice::where("date", $filter_date)
                    ->where("tlong", "<=", $currentGeneral->tlong - 300000)
                    ->orderByDesc("tlong")
                    ->first();
            }


            $yesterdayGeneral = GeneralStock::where("date", $this->previousDay($filter_date))->first();

            /**
             * Monitor DL1 stocks price
             */
            if ($stocks) {
                //Get realtime stock info of dl stocks

                foreach ($stocks as $stock) {

                    //Check if current stock has data
                    if (isset($stockInfo->data[$stock->code])) {

                        $this->monitorStock($stock, $stockInfo->data[$stock->code], $yesterdayGeneral, $previousGeneral, $currentGeneral);

                    }
                }

            }

            sleep(5);
            $now = new DateTime();
        }

        return true;
    }

}
