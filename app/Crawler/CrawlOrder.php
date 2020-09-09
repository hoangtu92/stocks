<?php


namespace App\Crawler;


use Illuminate\Support\Facades\Log;

class CrawlOrder extends Crawler
{

    public $start;
    public $price_909;
    public $value;
    private $loop = 0;


    public function __construct($type, $code){
        parent::__construct();

        $this->loop++;

        //?ex_ch=tse_3218.tw
        $url = 'https://mis.twse.com.tw/stock/api/getStockInfo.jsp?'.http_build_query([
                "ex_ch" => "{$type}_{$code}.tw",
            ]);

        $response = $this->get_content($url);

        $json = json_decode($response);

        if(isset($json->msgArray) && count($json->msgArray) > 0){
            $this->start = $this->format_number($json->msgArray[0]->o);
            $data = explode("_", $json->msgArray[0]->a);
            $this->price_909 = $this->format_number($data[0]);

            $z = $this->format_number($json->msgArray[0]->z);
            $this->value = $z ? $z : $this->format_number($json->msgArray[0]->h);

            if(!$this->start)
                $this->start = $this->value;

            if(!$this->price_909)
                $this->price_909 = $this->value;

            if(!$this->start || !$this->price_909){
                //Wait for 1 seconds
                sleep(1);
                Log::info("Re crawl Order data: {$url} ".json_encode($json->msgArray[0]));
                //Crawl data again
                if($this->loop <= 5)
                    self::__construct($type, $code);
            }

        }
        else{
            Log::info("No data found on Mis Tpex: {$url} -  ".$response);
        }
    }

}
