<?php

namespace App\Crawler;

use App\GeneralStock;
use Illuminate\Support\Facades\Log;

class CrawlGeneralStockFinal extends Crawler
{
    public $value;

    public function __construct($date_str){
        parent::__construct();

        $date = $this->getDate($date_str);

        $filter_date = "{$date['tw_year']}/{$date['month']}/{$date['day']}";

        Log::info("Crawling general stock final data for ".$date_str);

        //?response=json&date=20200903
        $url = 'https://www.twse.com.tw/exchangeReport/FMTQIK?'.http_build_query([
                "response" => "json",
                "date" => "{$date['year']}{$date['month']}{$date['day']}",
            ]);

        $response = $this->get_content($url);

        $json = json_decode($response);

        if(isset($json->data)){

            /*foreach($json->data as $d){
                $date = $this->date_from_tw($d[0]);
                $generalStock = GeneralStock::where("date", $date)->first();
                if(!$generalStock){

                    $generalStock = new GeneralStock([
                        "date" => $date
                    ]);
                }
                $generalStock->today_final = $this->format_number($d[4]);
                $generalStock->save();
            }*/

            $data =  array_reduce($json->data, function ($t, $e){
                $t[$e[0]] = $e;
                return $t;
            }, []);



            if( isset($data[$filter_date]) && $data[$filter_date][4]){
                $this->value =  $this->format_number($data[$filter_date][4]);
            }
            //Log::info(json_encode([$data, $this->value]));

        }
        else{
            Log::info("No data found: {$url} -  ".$response);
        }
    }

}
