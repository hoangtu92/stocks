<?php


namespace App\Crawler;


class DLExcludeFilter extends Crawler
{
    public $stockList = [];

    public function __construct($filter_date){
        parent::__construct();

        $date = $this->getDate($filter_date);

        $filter_date = "{$date['year']}{$date['month']}{$date['day']}";

        //?response=json&strDate=20200827&endDate=20200827&stockNo=
        $url  = "https://www.twse.com.tw/exchangeReport/TWTBAU2?".http_build_query([
                "response" => "json",
                "strDate" => $filter_date,
                "endDate" => $filter_date,
            ]);
        $res = json_decode($this->get_content($url));
        if(isset($res->data)){
            $this->stockList = array_merge(array_reduce($res->data, function ($t, $e){
                $t[] = $e[0];
                return $t;
            }, []));
        }


        //?l=zh-tw&sd=109/08/27&ed=109/08/27&stkno=&s=0,asc,0&o=json
        $filter_date = "{$date['tw_year']}/{$date['month']}/{$date['day']}";
        $url  = "https://www.tpex.org.tw/web/stock/trading/intraday_trading/n13his_result.php?".http_build_query([
                "o" => "json",
                "l" => "zh-tw",
                "s" => "0,asc,0",
                "sd" => $filter_date,
                "ed" => $filter_date,
            ]);
        $res = json_decode($this->get_content($url));
        if(isset($res->aaData)){
            $this->stockList = array_merge(array_reduce($res->aaData, function ($t, $e){
                $t[] = $e[0];
                return $t;
            }, []), $this->stockList);
        }

        return $this->stockList;
    }

}
