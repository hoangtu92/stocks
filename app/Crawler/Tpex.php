<?php

namespace App\Crawler;

use Illuminate\Support\Facades\Log;

class Tpex extends Crawler
{

    private $url = "https://www.tpex.org.tw/web/stock/aftertrading/daily_close_quotes/stk_quote_result.php";

    public function get($date){

        $date = $this->getDate($date);

        $filter_date = "{$date['tw_year']}/{$date['month']}/{$date['day']}";

        $url = $this->url.'?'.http_build_query([
                "l" => "zh-tw",
                "o" => "json",
                "d" => $filter_date]);


        $response = StockHelper::get_content($url);

        $json = json_decode($response);

        if(isset($json->aaData)){

            return $json->aaData;
        }
        else{
            Log::info("No data found on Tpex: {$url} -  ".$response);
        }
        return [];
    }
}
