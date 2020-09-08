<?php

namespace App\Http\Controllers;

use App\FailedCrawl;
use App\GeneralStock;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FrontController extends Controller
{
    private $today;

    public function __construct(){
        $this->today = date_create(now());
    }

    private function toTable($data, $mapping_label){

        if(empty($data)) return;

        print <<<EOF
<style>
.level-3{
    background-color: red;
}
.level-2{
    background-color: yellow;
}
.level-1{
   
}
</style>
EOF;

        print "<table border='1' cellpadding='5' cellspacing='0'>
<thead>
<tr>";


        foreach ($data[array_keys($data)[0]] as $key => $value){

            if(isset($mapping_label[$key]))

            print "<th style='text-transform: uppercase'>{$mapping_label[$key]}<br><small style='font-size: 9px'>{$key}</small></th>";
        }
        print "</tr>
</thead>
<tbody>";


        foreach($data as $tr){

            print "<tr>";

            foreach($tr as $key=> $td){

                if(!isset($mapping_label[$key])) continue;

                if(preg_match("/range|rate/", $key)) $td .= "%";
                print "<td>{$td}</td>";
            }
            print "</tr>";
        }

        print "</tbody></table>";
    }

    public function previousDayJoin($day, $filter_date){
        return DB::table("dl")
            ->addSelect("code")
            ->addSelect(DB::raw("COUNT(*) as count"))
            ->whereRaw("(DAYOFWEEK('{$filter_date}') = 2 AND DATEDIFF('{$filter_date}', date) = {$day}+2) OR (DAYOFWEEK('{$filter_date}') > 2 AND DAYOFWEEK('{$filter_date}') <= 6 AND DATEDIFF('{$filter_date}', date) = {$day})")
            ->groupBy("code");
    }

    //
    public function order(Request $request){
        $filter_date = $request->filter_date;
        if(!$request->filter_date){
            $filter_date = $this->today->format("Y-m-d");
            if(date("H") < 17){
                $filter_date = $this->previousDay($filter_date);
            }
        }

        $d = date_create($filter_date);
        //If weekend
        if($d->format("N") == 6){
            $filter_date = $d->modify("-1day")->format("Y-m-d");
        }

        if($d->format("N") == 7){
            $filter_date = $d->modify("-2day")->format("Y-m-d");
        }

        $up = GeneralStock::UP;
        $down = GeneralStock::DOWN;


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
            ->join("stocks", "stocks.code", "=", "dl.code")
            ->leftJoinSub($previousDay1, "previous_1_day", "dl.code", "=", "previous_1_day.code")
            ->leftJoinSub($previousDay2, "previous_2_day", "dl.code", "=", "previous_2_day.code")
            ->leftJoinSub($previousDay3, "previous_3_day", "dl.code", "=", "previous_3_day.code")
            ->select("dl.date")
            ->addSelect("dl.code")
            ->addSelect(DB::raw("IF(previous_3_day.count = 1, (if(previous_2_day.count = 1, (if(previous_1_day.count=1, 4, 3)), (if(previous_1_day.count=1, 2, 1)) )), (if(previous_1_day.count=1, 2, 1)) ) as appearance"))
            ->addSelect(DB::raw("stocks.name as name"))

            ->addSelect("dl.agency")
            ->addSelect(DB::raw("ROUND((dl.total_agency_vol/dl.vol)*100, 2) as total_agency_rate"))

            ->addSelect(DB::raw("ROUND((dl.single_agency_vol/dl.vol)*100, 2) as single_agency_rate"))
            ->addSelect("dl.agency_price")

            ->addSelect("dl.large_trade")

            ->addSelect(DB::raw("orders.start as order_start"))
            ->addSelect(DB::raw("orders.price_909 as price_907"))

            ->addSelect(DB::raw("ROUND(((orders.start-dl.final)/dl.final)*100, 2) as order_price_range"))

            ->addSelect(DB::raw("IF(orders.price_909 IS NULL, '等資料', IF(orders.price_909 <= orders.start, '下', '上' ) ) as trend"))

            ->addSelect(DB::raw("ROUND(IF( ((orders.start-dl.final)/dl.final)*100 <= 2 AND ((orders.start-dl.agency_price)/dl.agency_price)*100 >= 3.2 AND dl.large_trade >= 1.8, dl.final*1.055, 
                IF(((orders.start-dl.final)/dl.final)*100 <= 2.2 AND (dl.single_agency_vol/dl.vol)*100 >= 10 AND ((orders.start-dl.agency_price)/dl.agency_price)*100 >= 4, dl.final*1.065, 
                    IF(orders.start >= dl.final AND ((orders.start-dl.final)/dl.final)*100 <1.5 AND dl.agency_price <= dl.final, dl.final*1.03, 
                        IF( ((orders.start-dl.agency_price)/dl.agency_price)*100 >= 5 AND ((orders.start-dl.final)/dl.final)*100 <= 2, dl.final*1.05, 
                            IF(general_stocks.general_predict = '{$up}' AND dl.final >= 50, dl.agency_price,
                                IF(general_stocks.general_predict <= 0.05 AND ((orders.start-dl.final)/dl.final)*100 >= 0 AND dl.agency_price <= dl.final, dl.final*1.01,
                                    IF(((orders.start-dl.final)/dl.final)*100 <= -0.01 AND dl.agency_price <= dl.final, dl.final*1.02,
                                        IF(general_stocks.general_predict = '{$down}' AND dl.final >= 50, dl.agency_price*1.025, dl.final*1.015)
                                    )
                                )
                            )
                        )
                    )
                )
            ), 2) as agency_forecast"))

            ->addSelect(DB::raw("ROUND(((orders.start - (SELECT agency_forecast))/(SELECT agency_forecast))*100, 1) as start_agency_range"))

            ->where("dl.final", ">=", 7)
            ->where("dl.date", $filter_date)
            //->whereRaw("dl.agency IS NOT NULL AND dl.agency != ''")
            /*->orderBy("dl.range", "desc")*/
            ->orderBy("appearance", "desc")
            ->orderBy("dl.date", "desc")
            ->orderBy("total_agency_rate", "desc")
            ->orderBy("single_agency_rate", "desc")
            ->orderBy("dl.large_trade", "desc")
            ->get()
            ->toArray();



        $header = [
            "date" => "漲停日",
            "code" => "Code",
            "name" => "名稱",

            "final" => "成交價",
            "range" => "漲跌幅",
            "vol" => "成交張數",
            "agency" => "主要",
            "agency_price" => "買均價",

            "total_agency_rate" => "佔比",
            "single_agency_rate" => "集中度",


            "order_start" => "開盤價",
            "price_907" => "9:07價",
            "order_price_range" => "開盤漲幅",

            "trend" => "開盤漲跌",
            "start_agency_range" => "開盤主力差",
            "agency_forecast" => "主力賣出預測",


            "large_trade" => "爆量",
        ];

        $generalStock = GeneralStock::where("date", $filter_date)->first();

        $tomorrow = $this->nextDay($filter_date);

        return view("backend.order")->with(compact("data", "header", "filter_date", "generalStock", "tomorrow"));

    }

    public function data($date = null){

        $up = GeneralStock::UP;
        $down = GeneralStock::DOWN;


        $query = DB::table("dl")
            ->leftJoin("orders", function ($join){
                $join->on("orders.code","=", "dl.code")->on("orders.date", "=", "dl.date");
            })
            ->leftJoin("aravs", function ($join){
                $join->on("dl.code", "=", "aravs.code")->whereRaw(DB::raw("((DAYOFWEEK(dl.date) < 6 AND DAYOFWEEK(dl.date) > 1 AND DATEDIFF(aravs.date, dl.date) = 1)
                OR (DAYOFWEEK(dl.date) = 6 AND DATEDIFF(aravs.date, dl.date) = 3 ))"));
            })
            ->leftJoin("general_stocks", "general_stocks.date", "=", "dl.date")
            ->join("stocks", "stocks.code", "=", "dl.code")
            ->addSelect("dl.date")
            ->addSelect("dl.code")
            ->addSelect(DB::raw("stocks.name as name"))
            ->addSelect("dl.final")
            ->addSelect("dl.range")
            ->addSelect(DB::raw("ROUND(dl.vol, 0) as vol"))
            ->addSelect("dl.agency")
            ->addSelect(DB::raw("ROUND(dl.total_agency_vol, 0) as total_agency_vol"))
            ->addSelect(DB::raw("ROUND((dl.total_agency_vol/dl.vol)*100, 2) as total_agency_rate"))

            ->addSelect(DB::raw("ROUND(dl.single_agency_vol, 0) as single_agency_vol"))
            ->addSelect(DB::raw("ROUND((dl.single_agency_vol/dl.vol)*100, 2) as single_agency_rate"))

            ->addSelect("dl.agency_price")

            ->addSelect("dl.large_trade")

            ->addSelect(DB::raw("aravs.date as arav_date"))
            ->addSelect("aravs.start")
            ->addSelect("aravs.max")
            ->addSelect("aravs.lowest")
            ->addSelect(DB::raw("aravs.final as arav_final"))
            ->addSelect("aravs.price_range")

            ->addSelect(DB::raw("ROUND(((orders.start-dl.final)/dl.final)*100, 2) as order_price_range"))


            ->addSelect(DB::raw("ROUND(IF( ((orders.start-dl.final)/dl.final)*100 <= 2 AND ((orders.start-dl.agency_price)/dl.agency_price)*100 >= 3.2 AND dl.large_trade >= 1.8, dl.final*1.055, 
                IF(((orders.start-dl.final)/dl.final)*100 <= 2.2 AND (dl.single_agency_vol/dl.vol)*100 >= 10 AND ((orders.start-dl.agency_price)/dl.agency_price)*100 >= 4, dl.final*1.065, 
                    IF(orders.start >= dl.final AND ((orders.start-dl.final)/dl.final)*100 <1.5 AND dl.agency_price <= dl.final, dl.final*1.03, 
                        IF( ((orders.start-dl.agency_price)/dl.agency_price)*100 >= 5 AND ((orders.start-dl.final)/dl.final)*100 <= 2, dl.final*1.05, 
                            IF(general_stocks.general_predict = '{$up}' AND dl.final >= 50, dl.agency_price,
                                IF(general_stocks.general_predict <= 0.05 AND ((orders.start-dl.final)/dl.final)*100 >= 0 AND dl.agency_price <= dl.final, dl.final*1.01,
                                    IF(((orders.start-dl.final)/dl.final)*100 <= -0.01 AND dl.agency_price <= dl.final, dl.final*1.02,
                                        IF(general_stocks.general_predict = '{$down}' AND dl.final >= 50, dl.agency_price*1.025, dl.final*1.015)
                                    )
                                )
                            )
                        )
                    )
                )
            ), 2) as agency_forecast"))

            /*->addSelect("dl.dynamic_rate_sell")*/

            ->where("dl.final", ">=", 7)
            ->whereRaw("dl.agency IS NOT NULL AND dl.agency != ''")
            ->orderBy("dl.date", "desc")
            /*->orderBy("dl.range", "desc")*/
            ->orderBy("total_agency_rate", "desc")
            ->orderBy("single_agency_rate", "desc")
            ->orderBy("dl.large_trade", "desc");

        if($date){
            $query->where("dl.date", $date);

        }

        $list_dl = $query->get()->toArray();

        if(count($list_dl) == 0 && $date){
            FailedCrawl::create([
                "action" => "crawl_data_by_date",
                "failed_at" => now()
            ]);
        }

        $mapping_label = [
            "date" => "漲停日",
            "code" => "Code",
            "name" => "名稱",
            "final" => "成交價",
            "range" => "漲跌幅",
            "vol" => "成交張數",


            "arav_date" => "ARAV DATE",
            "start" => "開盤",
            "max" => "最高",
            "lowest" => "最低",
            "arav_final" => "成交價",
            "price_range" => "漲跌幅",

            "order_price_range" => "開盤漲幅",
            "agency" => "主要",
            "total_agency_vol" => "隔日沖買超",
            "agency_price" => "買均價",
            "single_agency_vol" => "集中張數",


            "total_agency_rate" => "占比",
            "single_agency_rate" => "集中度",
            "large_trade" => "爆量",

            "agency_forecast" => "主力賣出預測",
            /*"dynamic_rate_sell" => "預計賣",*/
        ];

        $this->toTable($list_dl, $mapping_label);

    }

    public function generalStock($filter_date = null){
        $mapping_label = [
            "date" => "Date",
            "general_start" => "上市開盤",
            "price_905" => "上市開盤9:05",
            "general_start_rate" => "開盤漲跌幅",
            "range_value" => "上市漲跌",
            "today_final" => "上市收盤",
            "yesterday_final" => "昨收",
            "day_average_20" => "實際20MA",
            "predict_20d_average" => "20MA預測",
            "predict_final" => "收盤預測",

        ];

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


        $query = DB::table("general_stocks")
            ->leftJoinSub($last20Days, "avg_today", "avg_today.date", "=", "general_stocks.date")
            ->leftJoinSub($last20Days, "avg_yesterday", function ($join){
                $join->on("avg_yesterday.date", "<", "general_stocks.date")->whereRaw(" ( (DAYOFWEEK(`general_stocks`.`date`) = 2 AND DATEDIFF(`general_stocks`.date, avg_yesterday.date) = 3) OR (DAYOFWEEK(`general_stocks`.`date`) BETWEEN 3 AND 6 AND DATEDIFF(`general_stocks`.date, avg_yesterday.date) = 1))");
            })
            ->leftJoinSub($last19Days, "last_19_days", "last_19_days.date", "=", "general_stocks.date")
            ->addSelect("general_stocks.date")
            ->addSelect("general_start")
            ->addSelect("price_905")
            ->addSelect("general_stocks.today_final")
            ->addSelect(DB::raw("(
                SELECT today_final FROM general_stocks gs WHERE
                    (DAYOFWEEK(general_stocks.date) = 2 AND DATEDIFF(general_stocks.date, gs.date) = 3)
                 OR (DAYOFWEEK(general_stocks.date) != 2 AND DATEDIFF(general_stocks.date, gs.date) = 1)
            ) as yesterday_final"))
            ->addSelect(DB::raw("ROUND(avg_today.sum_today_final/20, 2) as day_average_20"))
            ->addSelect(DB::raw("ROUND(avg_yesterday.sum_today_final/20, 2)+30 as predict_20d_average"))
            ->addSelect(DB::raw("( (SELECT predict_20d_average)*20 - last_19_days.sum_today_final - 900) as predict_final"))

            ->addSelect(DB::raw("( ROUND(((general_start - (SELECT yesterday_final))/(SELECT yesterday_final))*100, 2) ) as general_start_rate"))
            ->addSelect(DB::raw("( ROUND(((general_stocks.today_final - (SELECT yesterday_final))/(SELECT yesterday_final))*100, 2) ) as range_value"))
            ->whereRaw("DAYOFWEEK(general_stocks.date) BETWEEN 2 and 6")
            ->orderByDesc("general_stocks.date");

        if($filter_date){
            $query->where("general_stocks.date", $filter_date);
        }

        $general_stocks = $query->take(30)->get()->toArray();

        $this->toTable($general_stocks, $mapping_label);
    }
}
