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


                        $this->monitorDL0($stockPrice);


                    }
                }

            }

        }
        return true;
    }

}
