<?php

namespace App\Crawler\tpex;

use App\Crawler\Crawler;
use Illuminate\Support\Facades\Log;

class DailyTradingInfo extends Crawler {

    private function getPrevious3Days($date){

        $today = "{$date['year']}/{$date['month']}/{$date['day']}";
        $todayTW = "{$date['tw_year']}/{$date['month']}/{$date['day']}";

        $day1before = $this->getDate($this->previousDay($today));
        $day1beforeTW = "{$day1before['tw_year']}/{$day1before['month']}/{$day1before['day']}";

        $day2before = $this->getDate($this->previousDay("{$day1before['year']}/{$day1before['month']}/{$day1before['day']}"));
        $day2beforeTW = "{$day2before['tw_year']}/{$day2before['month']}/{$day2before['day']}";

        $day3before = $this->getDate($this->previousDay("{$day2before['year']}/{$day2before['month']}/{$day2before['day']}"));
        $day3beforeTW = "{$day3before['tw_year']}/{$day3before['month']}/{$day3before['day']}";

        return [$todayTW, $day1beforeTW, $day2beforeTW, $day3beforeTW];
    }

    private function getLargeTrade($result, $date){
        $previousDate = $this->getPrevious3Days($date);

        $data = ["previous_3_days" => 0, "today" => 0];
        foreach ($result as $r){
            if($r[0] == $previousDate[0]){
                $data["today"] = $this->format_number($r[1]);
            }
            if(in_array($r[0], [$previousDate[1], $previousDate[2], $previousDate[3]] )){
                $data["previous_3_days"] += $this->format_number($r[1]);
            }
        }

        $highestPrice = array_reduce($result, function ($t, $e){
            $t = max($t, $this->format_number($e[6]));
            return $t;
        }, 0);


        return [
            "x" => $data["previous_3_days"] == 0 ? 0 : round($data["today"]/($data["previous_3_days"]/3), 2),
            "z" => $highestPrice
        ];
    }

    public function getTpex($date, $stock_code){

        $date = $this->getDate($date);

        $filter_date = "{$date['tw_year']}/{$date['month']}";



        $url = 'https://www.tpex.org.tw/web/stock/aftertrading/daily_trading_info/st43_result.php?'.http_build_query([
                "l" => "zh-tw",
                "o" => "json",
                "stkno" => $stock_code,
                "d" => $filter_date]);


        $res = json_decode($this->get_content($url));

        if(isset($res->aaData)){
            return $this->getLargeTrade($res->aaData, $date);
        }
        Log::info("Failed to get data {$url}");

        return null;
    }

    public function getTwse($date, $stock_code){

        $date = $this->getDate($date);

        $filter_date = "{$date['year']}{$date['month']}{$date['day']}";


        $url = 'https://www.twse.com.tw/exchangeReport/STOCK_DAY?'.http_build_query([
                "response" => "json",
                "stockNo" => $stock_code,
                "date" => $filter_date]);

        $content = file_get_contents($url);

        $response = json_decode($content);

        if(isset($response->data)){
            return $this->getLargeTrade($response->data, $date);
        }

        Log::info("Failed to get data {$url}");

        return null;

    }
}
