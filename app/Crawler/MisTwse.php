<?php


namespace App\Crawler;


use Illuminate\Support\Facades\Log;

class MisTwse extends Crawler
{

    private $url = "https://mis.twse.com.tw/stock/api/getStockInfo.jsp";
    //?ex_ch=tse_3218.tw

    public function get($type, $code){

        $url = $this->url.'?'.http_build_query([
                "ex_ch" => "{$type}_{$code}.tw",
            ]);


        $response = file_get_contents($url);

        $json = json_decode($response);

        if(isset($json->msgArray)){
            return $json->msgArray;
        }
        else{
            Log::info("No data found on Mis Tpex: {$url} -  ".$response);
        }
        return [];
    }

}
