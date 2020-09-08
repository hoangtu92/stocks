<?php


namespace App\Crawler;


use Illuminate\Support\Facades\Log;

class CrawlGeneralStock extends Crawler
{

    public $generalStockData;

    public function __construct($date_str, $time){
        parent::__construct();

        $date = $this->getDate($date_str);

        Log::info("Crawling general stock data for ".$date_str);

        //?response=json&date=20200903
        $url = 'https://www.twse.com.tw/exchangeReport/MI_5MINS_INDEX?'.http_build_query([
                "response" => "json",
                "date" => "{$date['year']}{$date['month']}{$date['day']}",
            ]);

        $response = $this->get_content($url);

        $json = json_decode($response);

        if(isset($json->data)){
            $this->generalStockData =  array_reduce($json->data, function ($t, $e){
                $t[$e[0]] = $e;
                return $t;
            }, []);

            if(isset($time) && isset($this->generalStockData[$time])){
                $this->generalStockData =  $this->generalStockData[$time];
            }

            Log::info("Crawling general stock {$date_str} {$time}");

        }
        else{
            Log::info("No data found: {$url} -  ".$response);
        }
        return [];
    }

}
