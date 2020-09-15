<?php


namespace App\Crawler;


use ErrorException;
use Goutte\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Crawler
{

    public $arrContextOptions;
    public $ch;

    public function __construct(){
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 100);

        $this->arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );

    }

    public function get_content($url){
        try{
            return file_get_contents($url, false, stream_context_create($this->arrContextOptions));
        }
        catch (\Exception $e){
            Log::error($e->getMessage());
        }
        return null;
    }

    public function format_number($value){
        return floatval(preg_replace("/[\,]/", "", $value));
    }

    public function getDate($date){
        if(!$date) {
            $date = date_create(now());
        }
        if(is_string($date)){
            $date = date_create($date);
        }

        $year = $date->format("Y");
        $month = $date->format("m");
        $day = $date->format("d");
        $tw_year = $year - 1911;

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'tw_year' => $tw_year
        ];
    }

    public function date_from_tw($tw_date){
        $d = explode("/", $tw_date);
        $year = $d[0] + 1911;
        return "{$year}/{$d[1]}/{$d[2]}";
    }

    public function crawlGet($url, $selector){
        $client = new Client();
        $crawler = $client->request("GET", $url);
        return $crawler->filter($selector)->last();
    }

    public function curlGet($url, $params, $headers = []){
        curl_setopt($this->ch, CURLOPT_URL, $url."?".http_build_query($params));
        curl_setopt($this->ch, CURLOPT_POST, false);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        $data = curl_exec($this->ch);

        if(curl_errno($this->ch)){
            print curl_error($this->ch);
        }else{
            curl_close($this->ch);
        }

        return $data;
    }


    public function curlPost($url, $data, $headers = []){
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        $data = curl_exec($this->ch);

        if(curl_errno($this->ch)){
            print curl_error($this->ch);
        }else{
            curl_close($this->ch);
        }

        return $data;
    }

    public function previousDay($day){
        $previous_day = strtotime("$day -1 day");
        $previous_day_date = getdate($previous_day);

        if($previous_day_date["wday"] == 0 || $previous_day_date["wday"] == 6)
            return $this->previousDay(date('Y-m-d', $previous_day));
        else return date('Y-m-d', $previous_day);
    }

    public function nextDay($day){
        $previous_day = strtotime("$day +1 day");
        $previous_day_date = getdate($previous_day);

        if($previous_day_date["wday"] == 0 || $previous_day_date["wday"] == 6)
            return $this->nextDay(date('Y-m-d', $previous_day));
        else return date('Y-m-d', $previous_day);
    }

    public function previousDayJoin($day, $filter_date){
        return DB::table("dl")
            ->addSelect("code")
            ->addSelect(DB::raw("COUNT(*) as count"))
            ->whereRaw("(DAYOFWEEK('{$filter_date}') = 2 AND DATEDIFF('{$filter_date}', date) = {$day}+2) OR (DAYOFWEEK('{$filter_date}') > 2 AND DAYOFWEEK('{$filter_date}') <= 6 AND DATEDIFF('{$filter_date}', date) = {$day})")
            ->groupBy("code");
    }


    public function getStockData($code, $current_price, $filter_date = null){
        if(!$filter_date){
            $filter_date = $this->previousDay(date("Y-m-d"));
        }

        $last20Days = DB::table("general_stocks")
            ->addSelect("general_stocks.date")
            ->addSelect(DB::raw("SUM(gs2.today_final) as sum_today_final"))
            ->leftJoin(DB::raw("(SELECT today_final, date FROM general_stocks gs WHERE DAYOFWEEK(gs.date) BETWEEN 2 AND 6 ORDER BY gs.date DESC) gs2"),
                "general_stocks.date", ">=", "gs2.date")
            ->whereRaw("DAYOFWEEK(general_stocks.date) BETWEEN 2 and 6 AND DATEDIFF(general_stocks.date, gs2.date) <= 27")
            ->groupBy("general_stocks.date")
            ->orderByDesc("general_stocks.date");

        $last19Days = DB::table("general_stocks")
            ->addSelect("general_stocks.date")
            ->addSelect(DB::raw("SUM(gs2.today_final) as sum_today_final, COUNT(gs2.today_final) as count_rows"))
            ->leftJoin(DB::raw("(SELECT today_final, date FROM general_stocks gs WHERE DAYOFWEEK(gs.date) BETWEEN 2 AND 6 ORDER BY gs.date DESC) gs2"),
                "general_stocks.date", ">", "gs2.date")
            ->whereRaw("DAYOFWEEK(general_stocks.date) BETWEEN 2 and 6 AND DATEDIFF(general_stocks.date, gs2.date) <= 27")
            ->groupBy("general_stocks.date")
            ->orderByDesc("general_stocks.date");


        $previousDay1 = $this->previousDayJoin(1, $filter_date);
        $previousDay2 = $this->previousDayJoin(2, $filter_date);
        $previousDay3 = $this->previousDayJoin(3, $filter_date);

        $data = DB::table("dl")
            ->leftJoin("orders", function ($join){
                $join->on("orders.code","=", "dl.code")->on("orders.date", "=", "dl.date");
            })
            ->leftJoin("aravs", function ($join){
                $join->on("dl.code", "=", "aravs.code")->whereRaw(DB::raw("((DAYOFWEEK(dl.date) < 6 AND DAYOFWEEK(dl.date) > 1 AND DATEDIFF(aravs.date, dl.date) = 1)
                OR (DAYOFWEEK(dl.date) = 6 AND DATEDIFF(aravs.date, dl.date) = 3 ))"));
            })
            ->leftJoin("general_stocks", "general_stocks.date", "=", "dl.date")
            ->leftJoinSub($last20Days, "avg_today", "avg_today.date", "=", "general_stocks.date")
            ->leftJoinSub($last20Days, "avg_yesterday", function ($join){
                $join->on("avg_yesterday.date", "<", "general_stocks.date")->whereRaw(" ( (DAYOFWEEK(`general_stocks`.`date`) = 2 AND DATEDIFF(`general_stocks`.date, avg_yesterday.date) = 3) OR (DAYOFWEEK(`general_stocks`.`date`) BETWEEN 3 AND 6 AND DATEDIFF(`general_stocks`.date, avg_yesterday.date) = 1))");
            })
            ->leftJoinSub($last19Days, "last_19_days", "last_19_days.date", "=", "general_stocks.date")
            ->join("stocks", "stocks.code", "=", "dl.code")
            ->leftJoinSub($previousDay1, "previous_1_day", "dl.code", "=", "previous_1_day.code")
            ->leftJoinSub($previousDay2, "previous_2_day", "dl.code", "=", "previous_2_day.code")
            ->leftJoinSub($previousDay3, "previous_3_day", "dl.code", "=", "previous_3_day.code")
            ->select("dl.date")
            ->addSelect("dl.code")
            ->addSelect("stocks.type")
            ->addSelect(DB::raw("CONCAT(dl.code, ' - ', stocks.name) as stock"))
            ->addSelect(DB::raw("IF(previous_3_day.count = 1, (if(previous_2_day.count = 1, (if(previous_1_day.count=1, 4, 3)), (if(previous_1_day.count=1, 2, 1)) )), (if(previous_1_day.count=1, 2, 1)) ) as appearance"))
            ->addSelect(DB::raw("(SELECT 1) as qty"))
            ->addSelect(DB::raw("(SELECT 1) as price"))
            ->addSelect(DB::raw("(SELECT 1) as current_profit"))
            ->addSelect(DB::raw("(SELECT 1) as current_profit_percent"))
            ->addSelect(DB::raw("(SELECT 1) as fee"))
            ->addSelect(DB::raw("(SELECT 1) as tax"))

            ->addSelect(DB::raw("ROUND((dl.total_agency_vol/dl.vol)*100, 2) as total_agency_rate"))

            ->addSelect(DB::raw("ROUND((dl.single_agency_vol/dl.vol)*100, 2) as single_agency_rate"))
            ->addSelect("dl.agency_price")

            ->addSelect(DB::raw("(SELECT {$current_price}) as current_price"))

            ->addSelect("dl.large_trade")

            ->addSelect(DB::raw("orders.start as order_start"))
            ->addSelect(DB::raw("(SELECT current_price) as price_907"))


            ->addSelect(DB::raw("(
                SELECT today_final FROM general_stocks gs WHERE
                    (DAYOFWEEK(general_stocks.date) = 2 AND DATEDIFF(general_stocks.date, gs.date) = 3)
                 OR (DAYOFWEEK(general_stocks.date) != 2 AND DATEDIFF(general_stocks.date, gs.date) = 1)
            ) as yesterday_final"))

            ->addSelect(DB::raw("ROUND(avg_yesterday.sum_today_final/20, 2)+30 as predict_20d_average"))
            ->addSelect(DB::raw("( (SELECT predict_20d_average)*20 - last_19_days.sum_today_final - 700) as predict_final"))
            ->addSelect(DB::raw("((SELECT predict_final) - general_start) as general_predict"))


            ->addSelect(DB::raw("((orders.start-dl.final)/dl.final)*100 as BF"))
            ->addSelect(DB::raw("((orders.start-dl.agency_price)/dl.agency_price)*100 as BU"))
            ->addSelect(DB::raw("((general_stocks.general_start-(SELECT yesterday_final))/(SELECT yesterday_final))*100 as BN"))
            ->addSelect(DB::raw("(((SELECT current_price)-orders.start)/orders.start)*100 as BH"))

            ->addSelect(DB::raw("ROUND((SELECT BF), 2) as order_price_range"))

            ->addSelect(DB::raw("IF((SELECT current_price) IS NULL, '等資料', IF((SELECT current_price) <= orders.start, '下', '上' ) ) as trend"))

            ->addSelect(DB::raw("ROUND(IF( (SELECT BF) <= 2 AND (SELECT BU) >= 3.2 AND dl.large_trade >= 1.8, dl.final*1.055, 
                IF((SELECT BF) <= 2.2 AND (dl.single_agency_vol/dl.vol)*100 >= 10 AND (SELECT BU) >= 4, dl.final*1.065, 
                    IF(orders.start >= dl.final AND (SELECT BF) <1.5 AND dl.agency_price <= dl.final, dl.final*1.03, 
                        IF( (SELECT BU) >= 5 AND (SELECT BF) <= 2, dl.final*1.05, 
                            IF((SELECT general_predict) >= 0 AND dl.final >= 50, dl.agency_price,
                                IF((SELECT general_predict) <= 0.05 AND (SELECT BF) >= 0 AND dl.agency_price <= dl.final, dl.final*1.01,
                                    IF((SELECT BF) <= -0.01 AND dl.agency_price <= dl.final, dl.final*1.02,
                                        IF((SELECT general_predict) <= 0 AND dl.final >= 50, dl.agency_price*1.025, dl.final*1.015)
                                    )
                                )
                            )
                        )
                    )
                )
            ), 2) as agency_forecast"))

            ->addSelect(DB::raw("ROUND(((orders.start - (SELECT agency_forecast))/(SELECT agency_forecast))*100, 1) as start_agency_range"))

            ->addSelect(DB::raw("(((SELECT current_price)-(SELECT agency_forecast))/(SELECT agency_forecast))*100 as BI"))

            ->addSelect(DB::raw("
            IF((SELECT BN)<=-1, '馬上做多單',
                    IF((SELECT BF)>=5 AND (SELECT BH)>=1, '漲停不下單',
                        IF((SELECT BF)>=0.3 AND (SELECT BH)>=4.9, '漲停不下單',
                            IF((SELECT BF)>=3.8 AND (SELECT BH)>=4, '漲停不下單',
                                IF((SELECT BF)>=7.5 AND (SELECT current_price)>=orders.start, '漲停不下單',
                                    IF((SELECT general_predict)='' OR (SELECT general_predict) IS NULL OR (SELECT BN)='' OR orders.start IS NULL OR (SELECT current_price) IS NULL, '等資料',
                                        IF(general_stocks.price_905<general_stocks.general_start AND (SELECT BN)<=0.2 AND (SELECT BF)>=2.27 AND (SELECT current_price)>=orders.start AND orders.start>=(SELECT agency_forecast), '等拉高',
                                            IF((SELECT start_agency_range)<=0 AND (SELECT trend)='下' AND (SELECT BI)<=0 AND orders.start<=dl.agency_price, '等低點做多單',
                                                IF((SELECT start_agency_range)<=1.2 AND (SELECT current_price)<=orders.start AND orders.start<=dl.agency_price, '等低點做多單',
                                                    IF(general_stocks.price_905<general_stocks.general_start AND (SELECT BN)>=0.2 AND (SELECT trend)='上' AND (SELECT current_price)<=(SELECT agency_forecast) AND (select appearance) = 0, '馬上做多單',
                                                        IF(general_stocks.price_905>general_stocks.general_start AND (SELECT BF)>=5 AND (SELECT current_price)>=orders.start, '等拉高',
                                                            IF(general_stocks.price_905<general_stocks.general_start AND (SELECT trend)='上' AND orders.start<(SELECT agency_forecast) AND (SELECT BU)>1, '等低點做多單',
                                                                IF(general_stocks.price_905<general_stocks.general_start AND (SELECT trend)='下' AND orders.start<(SELECT agency_forecast) AND (SELECT BU)>1 AND (SELECT BH)<=-3, '等低點做多單',
                                                                    IF((SELECT general_predict) < 0 AND (SELECT BN)<=-0.01 AND (SELECT BF)<=0.1 AND orders.start<=dl.agency_price AND (SELECT current_price)>=orders.start AND (select appearance) = 0, '馬上做多單',
                                                                        IF(general_stocks.price_905<general_stocks.general_start AND (SELECT BN)>=0.5 AND orders.start<=(SELECT agency_forecast) AND (SELECT current_price)<=orders.start AND orders.start<=dl.agency_price AND (select appearance) = 0, '馬上做多單',
                                                                            IF((SELECT general_predict) >=0 AND (SELECT BN)<=0.2 AND (SELECT BN)<=-0.01 AND (SELECT BF)>=5 AND (SELECT current_price)<=orders.start AND orders.start<=dl.agency_price, '等低點做多單',
                                                                                IF((SELECT general_predict) >= 0 AND orders.start<=dl.agency_price AND (SELECT BN)>=0.05 AND (SELECT BF)>=3 AND (SELECT current_price)>=orders.start, '等低點做多單',
                                                                                    IF((SELECT trend)='下' AND (SELECT BN)<-0.4 AND (SELECT BN)<0 AND (SELECT BF)<=1 AND (SELECT BF)<=0.9 AND (SELECT current_price)<=orders.start AND orders.start<=dl.agency_price, '等低點做多單',
                                                                                        IF((SELECT general_predict) >= 0 AND (SELECT BN)<0 AND (SELECT BF)<=2.2 AND (SELECT current_price)<=orders.start, (SELECT current_price),
                                                                                            IF((SELECT general_predict) >= 0 AND (SELECT BN)>=0.1 AND (SELECT BF)>=2.3 AND (SELECT start_agency_range)>=1.5 AND (SELECT trend)='下', (SELECT current_price),
                                                                                                IF(general_stocks.price_905>general_stocks.general_start AND (SELECT BN)<=0.2 AND (SELECT BN)>=0.01 AND (SELECT current_price)<=orders.start AND orders.start<=dl.agency_price, '等低點做多單',
                                                                                                    IF((SELECT general_predict) < 0 AND (SELECT BN)<=0.01 AND (SELECT BF)<=2 AND (SELECT BF)>=1 AND (SELECT trend)='下', '等低點做多單',
                                                                                                        IF((SELECT general_predict) >= 0 AND (SELECT BN)<0 AND aravs.max>=dl.final AND dl.large_trade<=2 AND ((SELECT agency_forecast)-dl.final)/dl.final>=6.5, '做多單',
                                                                                                            IF(general_stocks.price_905>general_stocks.general_start AND (SELECT BF)<=0.05 AND  (SELECT single_agency_rate)>=4 AND (SELECT BU)<=2, '等拉高',
                                                                                                                IF(general_stocks.price_905>general_stocks.general_start AND (SELECT current_price)>=orders.start AND (SELECT BN)<=1.16, '等拉高',
                                                                                                                    IF((SELECT BF)<=2.2 AND  (SELECT single_agency_rate)>=10 AND (SELECT BU)>=4 AND (SELECT general_predict) >= 0, '等拉高',
                                                                                                                        IF(orders.start<=1.5 AND orders.start<=dl.agency_price AND dl.large_trade<=2 AND general_stocks.price_905>general_stocks.general_start AND (SELECT current_price)<orders.start AND (SELECT BH)>=-5, '等拉高',
                                                                                                                            IF((SELECT BF)<-9 AND dl.agency_price<=dl.final, dl.final,
                                                                                                                                IF((SELECT BF)<=1.5 AND dl.agency_price>=dl.final AND (SELECT agency_forecast)>=orders.start AND dl.large_trade<=2 AND (orders.start/(SELECT agency_forecast))<=1.005, '等拉高',
                                                                                                                                    IF(orders.start<=dl.agency_price AND (SELECT BF)<=4 AND (SELECT BF)>=3 AND (orders.start/(SELECT agency_forecast))<=1.012 AND dl.large_trade<6, '等拉高',
                                                                                                                                        IF((SELECT BF)<=-1 AND dl.agency_price<=dl.final, (SELECT current_price),
                                                                                                                                            IF((SELECT BF)<=1.2 AND (SELECT BU)>=1 AND (SELECT single_agency_rate)>=2.2 AND dl.large_trade>=1.8 AND (SELECT general_predict) >= 0, '等拉高',
                                                                                                                                                IF(general_stocks.price_905>general_stocks.general_start AND (SELECT BF)<=1 AND orders.start<=(SELECT agency_forecast) AND (SELECT current_price)<orders.start, '等拉高',
                                                                                                                                                    IF((SELECT general_predict) < 0 AND (SELECT BF)<=5 AND (SELECT BF)>=3.7 AND dl.agency_price<dl.final AND orders.start<=dl.agency_price AND (SELECT start_agency_range)>=1.5 AND (SELECT trend)='下', '等拉高',
                                                                                                                                                        IF((SELECT trend)='上' AND (SELECT BN)<=1.16, '等拉高', (SELECT current_price))
                                                                                                                                                    )
                                                                                                                                                )
                                                                                                                                            )
                                                                                                                                        )
                                                                                                                                    )
                                                                                                                                )
                                                                                                                            )
                                                                                                                        )
                                                                                                                    )
                                                                                                                )
                                                                                                            )
                                                                                                        )
                                                                                                    )
                                                                                                )
                                                                                            )
                                                                                        )
                                                                                    )
                                                                                )
                                                                            )
                                                                        )
                                                                    )
                                                                )
                                                            )
                                                        )
                                                    )
                                                )
                                            )
                                        )
                                    )
                                )
                            )
                        )
                    )
                )
                 as place_order"
            ))

            ->where("dl.code", $code)
            /*->orderBy("dl.range", "desc")*/
            /*->orderBy("appearance", "desc")
            ->orderBy("dl.date", "desc")
            ->orderBy("total_agency_rate", "desc")
            ->orderBy("single_agency_rate", "desc")
            ->orderBy("dl.large_trade", "desc")*/
            ->first();

        return $data;
    }
}
