<?php


namespace App\Crawler;


use Illuminate\Support\Facades\Log;

class Twse extends Crawler
{

    private $url = "https://www.twse.com.tw/exchangeReport/MI_INDEX";

    public function get($date){

        $date = $this->getDate($date);

        $filter_date = "{$date['year']}{$date['month']}{$date['day']}";

        $url = $this->url.'?'.http_build_query([
                "type" => "ALLBUT0999",
                "response" => "json",
                "date" => $filter_date]);

        $response = StockHelper::get_content($url, false);

        $json = json_decode($response);

        if(isset($json->data9)){
            return $json->data9;
        }
        else{
            Log::info("No data found on Twse: {$url} -  ".$response);
        }
        return [];
    }

}
