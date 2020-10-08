<?php


namespace App\Crawler;

class CrawlHoliday extends Crawler
{

    public $data = [];
    public function __construct($year){
        parent::__construct();

        $table = $this->crawlGet("https://www.tradinghours.com/exchanges/twse/market-holidays/{$year}", "table.table");
        $table->filter("tr")->each(function ($tr, $i) {


            $tr->filter("td")->each(function ($td, $j) use ($i){
                $value = trim($td->text());
                if($j == 1)
                    $this->data[$i]["name"] = $value;
                if($j == 2){
                    $date = date_create_from_format("F j, Y", $value);
                    $this->data[$i]["date"] = $date->format("Y-m-d");
                }

            });
        });

    }

}
