<?php


namespace App\Crawler;

use Illuminate\Support\Facades\Log;

class CrawlAgency extends Crawler
{

    private $url = "https://histock.tw/stock/branch.aspx";
    private $data = [];
    private $client;

    public function __construct($client){
        parent::__construct();
        $this->client = $client;
    }

    public function get($stock_code, $date, $filter = []){

        $date = $this->getDate($date);

        $filter_date = "{$date['year']}{$date['month']}{$date['day']}";

        $url = $this->url.'?'.http_build_query([
                "from" => $filter_date,
                "to" => $filter_date,
                "no"=> $stock_code
            ]);

        $crawler = $this->client->request("GET", $url);
        $table = $crawler->filter("table.tb-stock")->last();


        $table->filter("tr")->each(function ($tr, $i){
           if($i > 0 && $i <= 5){
               $this->data[$i] = [];
               $tr->filter("td")->each(function ($td, $j) use ($i) {

                   if(in_array($j, [5,8,9])){
                       $this->data[$i][$j] = $td->text();
                   }
               });
           }
        });


        if(count($this->data) == 0){
            //Its really empty
            return ["agency" => [], "total_agency_vol" => 0, "single_agency_vol" => 0, "agency_price" => 0];
        }




        $data = array_filter($this->data, function ($e) use ($filter) {
            return in_array($e[5], $filter);
        });

        if(count($data) == 0){
            //No agency match the filter
            return false;
        }

        $data = array_reduce($data, function ($t, $e){
            $t["agency"][] = $e[5];
            $t["total_agency_vol"] += $this->format_number($e[8]);
            $t["single_agency_vol"] =  max($this->format_number($e[8]), $t["single_agency_vol"]);
            $t["agency_price"] =  max($this->format_number($e[9]), $t["agency_price"]);

            return $t;
        }, ["agency" => [], "total_agency_vol" => 0, "single_agency_vol" => 0, "agency_price" => 0]);


        $data["agency"] = implode(", ", $data["agency"]);



        return $data;
    }

}
