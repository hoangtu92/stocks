<?php


namespace App\Crawler;


use Illuminate\Support\Facades\Log;

class MisTwse extends Crawler
{

    public $start;
    public $price_909;
    public $value;


    public function __construct($type, $code){
        parent::__construct();

        //?ex_ch=tse_3218.tw
        $url = 'https://mis.twse.com.tw/stock/api/getStockInfo.jsp?'.http_build_query([
                "ex_ch" => "{$type}_{$code}.tw",
            ]);

        $response = $this->get_content($url);

        $json = json_decode($response);

        if(isset($json->msgArray)){
            $this->start = $this->format_number($json->msgArray[0]->o);
            $data = explode("_", $json->msgArray[0]->a);
            $this->price_909 = $this->format_number($data[0]);

            $v = $this->format_number($json->msgArray[0]->z);
            $this->value = $v ? $v : $this->format_number($json->msgArray[0]->h);
        }
        else{
            Log::info("No data found on Mis Tpex: {$url} -  ".$response);
        }
    }

}
