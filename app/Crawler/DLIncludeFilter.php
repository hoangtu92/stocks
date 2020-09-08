<?php


namespace App\Crawler;


class DLIncludeFilter extends Crawler
{

    public $stockList = [];

    public function __construct($filter_date)
    {

        parent::__construct();

        $date = $this->getDate($filter_date);

        /**
         * Get TWSE filter list
         */

        $filter_date = "{$date['year']}{$date['month']}{$date['day']}";
        //?response=json&date=20200807&selectType=All
        $url  = "https://www.twse.com.tw/exchangeReport/TWTB4U?".http_build_query([
                "response" => "json",
                "selectType" => "All",
                "date" => $filter_date
            ]);
        $res = json_decode($this->get_content($url));
        if(isset($res->data)){
            $this->stockList = array_merge(array_reduce($res->data, function ($t, $e){
                $t[] = $e[0];
                return $t;
            }, []));
        }


        /**
         * Get Tpex filter list
         */
        //l=zh-tw&d={$tw_year}/{$month}/{$day}&stock_code=&s=0,asc,1
        $filter_date = "{$date['tw_year']}/{$date['month']}/{$date['day']}";
        $url = "https://www.tpex.org.tw/web/stock/trading/intraday_trading/intraday_trading_list_result.php?" . http_build_query([
                "l" => "zh-tw",
                "d" => $filter_date,
                "s" => "0,asc,1"
        ]);

        $res = json_decode($this->get_content($url));
        if(isset($res->aaData)){
            $this->stockList = array_merge(array_reduce($res->aaData, function ($t, $e){
                $t[] = $e[0];
                return $t;
            }, []), $this->stockList);
        }

    }

}
