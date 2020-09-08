<?php


namespace App\Crawler\twse;


use App\Crawler\Crawler;
use DateTime;
use Illuminate\Support\Facades\Log;

class CrawlGeneralStockToday extends Crawler
{

    public $value;


    public function __construct($timestamp){

        parent::__construct();

        $url = 'https://mis.twse.com.tw/stock/data/mis_ohlc_TSE.txt?'.http_build_query([
                "_" => $timestamp]);

        $arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );

        Log::info("get stock data {$url}");

        $content = file_get_contents($url, false, stream_context_create($arrContextOptions));

        $response = json_decode($content);
        if(isset($response->ohlcArray)){

            $data = array_reduce($response->ohlcArray, function ($t, $e){
                $t[$e->t] = $e;
                return $t;
            }, []);

            $this->value = $data[$timestamp]->c;
        }
    }


}
