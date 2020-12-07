<?php


namespace App\Crawler\RealTime;


use App\Crawler\Crawler;
use App\Crawler\CrawlStockInfoData;
use App\Crawler\DLExcludeFilter;
use App\Crawler\DLIncludeFilter;
use App\Crawler\StockHelper;
use App\Dl;
use App\GeneralPrice;
use App\GeneralStock;
use DateTime;
use Illuminate\Support\Facades\Log;

class RealtimeDL0 extends Crawler
{

    public function __invoke()
    {

        /**
         * Start to monitor stock data
         */
        Log::info("Start dl0 realtime crawl");

        $now = new DateTime();


        $filter_date = $now->format("Y-m-d");

        $includeFilter = new DLIncludeFilter($filter_date);
        $excludeFilter = new DLExcludeFilter($filter_date);

        //Get DL0 stocks
        $stocks = Dl::join("stocks", "stocks.code", "=", "dl.code")
            ->select("dl.date")
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
            ->groupBy(["dl.code", "stocks.type"])
            ->get();

        #Log::debug(json_encode($stocks));

        $generalStock   = GeneralStock::where( "date", $filter_date )->first();
        $yesterdayGeneral = GeneralStock::where("date", $this->previousDay($filter_date))->first();

        $url = StockHelper::getUrlFromStocks($stocks->toArray());
        $this->callback($url, $stocks, $generalStock, $yesterdayGeneral);

        return true;
    }

    public function callback($url, $stocks,  $generalStock, $yesterdayGeneral)
    {

        $now = new DateTime();
        $start = new DateTime();
        $stop = new DateTime();

        $start->setTime(9, 0, 0);
        $stop->setTime(13, 35, 0);

        if($now < $start || $now > $stop){
            return;
        }

        //Working time

        # Log::debug(json_encode([$currentGeneral->value, $yesterdayGeneral->today_final]));

        //1. general current price > yesterday general final


        //Get realtime price of all stocks

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


                    StockHelper::monitorDL0($stockPrice, $generalStock, $yesterdayGeneral);


                }
            }

        }



        $this->callback($url, $stocks, $generalStock, $yesterdayGeneral);
    }

}
