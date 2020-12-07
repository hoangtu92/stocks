<?php

namespace App\Jobs\Crawl;

use App\Crawler\StockHelper;
use App\Dl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrawlLargeTrade implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 1;
    protected $filter_date;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($filter_date = null)
    {
        //
        if(!$filter_date){
            $filter_date = date("Y-m-d");
        }
        $this->filter_date = $filter_date;


    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        Log::info("Crawling XZ data for ".$this->filter_date);
        $dls = Dl::join("stocks", "stocks.code", "=", "dl.code")
            ->select(DB::raw("dl.*, stocks.type"))
            ->where("dl_date", $this->filter_date)->get();

        foreach ($dls as $dl){

            $result = $dl->type == "otc" ? $this->getTpex($this->filter_date, $dl->code) : $this->getTwse($this->filter_date, $dl->code);
            if($result){
                $dl->large_trade = $result["x"];
                $dl->dynamic_rate_sell = $result["z"];

                $dl->save();
            }
        }
    }

    private function getPrevious3Days($date){

        $today = "{$date['year']}/{$date['month']}/{$date['day']}";
        $todayTW = "{$date['tw_year']}/{$date['month']}/{$date['day']}";

        $day1before = StockHelper::getDate(StockHelper::previousDay($today));
        $day1beforeTW = "{$day1before['tw_year']}/{$day1before['month']}/{$day1before['day']}";

        $day2before = StockHelper::getDate(StockHelper::previousDay("{$day1before['year']}/{$day1before['month']}/{$day1before['day']}"));
        $day2beforeTW = "{$day2before['tw_year']}/{$day2before['month']}/{$day2before['day']}";

        $day3before = StockHelper::getDate(StockHelper::previousDay("{$day2before['year']}/{$day2before['month']}/{$day2before['day']}"));
        $day3beforeTW = "{$day3before['tw_year']}/{$day3before['month']}/{$day3before['day']}";

        return [$todayTW, $day1beforeTW, $day2beforeTW, $day3beforeTW];
    }

    private function getLargeTrade($result, $date){
        $previousDate = $this->getPrevious3Days($date);

        $data = ["previous_3_days" => 0, "today" => 0];
        foreach ($result as $r){
            if($r[0] == $previousDate[0]){
                $data["today"] = StockHelper::format_number($r[1]);
            }
            if(in_array($r[0], [$previousDate[1], $previousDate[2], $previousDate[3]] )){
                $data["previous_3_days"] += StockHelper::format_number($r[1]);
            }
        }

        $highestPrice = array_reduce($result, function ($t, $e){
            $t = max($t, StockHelper::format_number($e[6]));
            return $t;
        }, 0);


        return [
            "x" => $data["previous_3_days"] == 0 ? 0 : round($data["today"]/($data["previous_3_days"]/3), 2),
            "z" => $highestPrice
        ];
    }

    public function getTpex($date, $stock_code){

        $date = StockHelper::getDate($date);

        $filter_date = "{$date['tw_year']}/{$date['month']}";


        if($date['month'] == 1){
            $pm = 12;
            $py = $date['tw_year'] - 1;
        }
        else{
            $pm = $date['month'] - 1;
            $py = $date['tw_year'];
        }

        if($pm < 10) $pm = "0{$pm}";

        $filter_date2 = "{$py}/{$pm}";

        $url = 'https://www.tpex.org.tw/web/stock/aftertrading/daily_trading_info/st43_result.php?'.http_build_query([
                "l" => "zh-tw",
                "o" => "json",
                "stkno" => $stock_code,
                "d" => $filter_date]);


        $res = json_decode(StockHelper::get_content($url));

        if(isset($res->aaData)){

            $url2 = 'https://www.tpex.org.tw/web/stock/aftertrading/daily_trading_info/st43_result.php?'.http_build_query([
                    "l" => "zh-tw",
                    "o" => "json",
                    "stkno" => $stock_code,
                    "d" => $filter_date2]);

            $res2 = json_decode(StockHelper::get_content($url2));

            $result = $res->aaData;
            if(isset($res2->aaData)){
                $result = array_merge($res2->aaData, $res->aaData);
            }

            # Log::info($url2.json_encode($result));

            return $this->getLargeTrade($result, $date);
        }
        Log::info("Failed to get data {$url}");

        return null;
    }

    public function getTwse($date, $stock_code){

        $date = StockHelper::getDate($date);

        $filter_date = "{$date['year']}{$date['month']}{$date['day']}";

        if($date['month'] == 1){
            $pm = 12;
            $py = $date['year'] - 1;
        }
        else{
            $pm = $date['month'] - 1;
            $py = $date['year'];
        }
        if($pm < 10) $pm = "0{$pm}";
        $filter_date2 = "{$py}{$pm}01";


        $url = 'https://www.twse.com.tw/exchangeReport/STOCK_DAY?'.http_build_query([
                "response" => "json",
                "stockNo" => $stock_code,
                "date" => $filter_date]);

        $res = json_decode(StockHelper::get_content($url));

        if(isset($res->data)){
            $url2 = 'https://www.twse.com.tw/exchangeReport/STOCK_DAY?'.http_build_query([
                    "response" => "json",
                    "stockNo" => $stock_code,
                    "date" => $filter_date2]);

            $res2 = json_decode(StockHelper::get_content($url2));

            $result = $res->data;
            if(isset($res2->data)){
                $result = array_merge($res2->data, $res->data);
            }
            #Log::info($url2.json_encode($result));

            return $this->getLargeTrade($result, $date);
        }

        Log::info("Failed to get data {$url}");

        return null;

    }
}
