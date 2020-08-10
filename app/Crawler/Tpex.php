<?php

namespace App\Crawler;

class Tpex extends Crawler
{

    private $url = "https://www.tpex.org.tw/web/stock/aftertrading/daily_close_quotes/stk_quote_result.php";

    public function get($date){

        $date = $this->getDate($date);

        $filter_date = "{$date['tw_year']}/{$date['month']}/{$date['day']}";


        $response = file_get_contents($this->url.'?'.http_build_query([
            "l" => "zh-tw",
            "o" => "json",
            "d" => $filter_date]));

        $json = json_decode($response);

        if($json){
            return $json->aaData;
        }
        return [];
    }
}
