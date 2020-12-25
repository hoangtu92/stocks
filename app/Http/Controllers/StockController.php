<?php

namespace App\Http\Controllers;

use App\Crawler\StockHelper;
use App\Dl;
use App\Jobs\Crawl\CrawlAgent;
use App\Jobs\Crawl\CrawlARAV;
use App\Jobs\Crawl\CrawlDL;
use App\Jobs\Crawl\CrawlLargeTrade;
use App\Jobs\CrawlYahooPrice;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class StockController extends Controller
{


    public function crawlDl($filter_date = null){

        if(!$filter_date){
            $filter_date = date("Y-m-d");
        }

        Log::info("Crawling dl data for ".$filter_date);

        CrawlDl::dispatch($filter_date);

        return redirect(route("data", ["date" => $filter_date]));
    }

    public function crawlArav($filter_date = null){

        if(!$filter_date){
            $filter_date = $this->previousDay(date("Y-m-d"));
        }

        $d = date_create($filter_date);
        if($d->format("N") >= 6){
            $filter_date = $this->previousDay($filter_date);
        }

        Log::info("Crawling arav data for ".$filter_date);

        CrawlARAV::dispatchNow($filter_date);

        return redirect(route("data", ["date" => $filter_date]));
    }

    public function crawlXZ($filter_date = null){
        if(!$filter_date){
            $filter_date = date("Y-m-d");
        }

        CrawlLargeTrade::dispatchNow($filter_date);

        return redirect(route("data", ["date" => $filter_date]));
    }


    public function reCrawlAgency(Request $request){

        if(!$request->date){
            $request->date = date("Y-m-d");
        }

        //Log::debug("Some one request to crawl agency". json_encode($_REQUEST));
        CrawlAgent::dispatchNow($request->date);

        return Redirect::back();
    }

    public function crawlData($filter_date = null){

        if(!$filter_date){
            $filter_date = date("Y-m-d");
        }

        Log::info("Crawling past data for ".$filter_date);

        $today_date = getdate(strtotime($filter_date));

        //Exclude weekend
        if($today_date["wday"] > 0 && $today_date["wday"] < 6){

            CrawlDl::dispatchNow($filter_date);
            CrawlARAV::dispatchNow($filter_date);
            CrawlLargeTrade::dispatchNow($filter_date);
            CrawlAgent::dispatchNow($filter_date);
        }

        return redirect(route("data", ["date" => $filter_date]));

    }

    public function dl0($filter_date = null){
        $now = new DateTime();

        if(!$filter_date)
            $filter_date = $now->format("Y-m-d");

        $stocks = StockHelper::getDL0Stocks($filter_date);


        echo $this->toTable($stocks->toArray(), [
            "date" => "Date",
            "code" => "Code",
            "range" => "Range",
            "open" => "Open",
            "low" => "Low",
            "high" => "High",
        ]);
    }

    public function crawlYahoo(){
        $filter_date = date("Y-m-d");

        $stocks = DB::table("dl")
            ->whereRaw("dl.agency IS NOT NULL")
            ->where("dl.final", "<", 200)
            ->where("dl.final", ">", 10)
            ->where("date", StockHelper::previousDay(StockHelper::previousDay($filter_date)))
            ->get();

        #$stocks = [3625, 3499, 3522, 3625, 2460, 2486, 3050, 2617, 2506, 5531];

        foreach ($stocks as $stock){
            CrawlYahooPrice::dispatchSync($stock->code);
        }


    }

}
