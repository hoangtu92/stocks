<?php

namespace App\Http\Controllers;

use App\Crawler\Crawler;
use App\Crawler\StockHelper;
use App\FailedCrawl;
use App\GeneralStock;
use App\Holiday;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FrontController extends Controller
{
    private $today;

    public function __construct()
    {
        $this->today = date_create(now());
    }


    //
    public function order(Request $request)
    {
        $filter_date = $request->filter_date;
        if (!$request->filter_date) {
            $filter_date = date("Y-m-d");
            if (date("H") < 17) {
                $filter_date = $this->previousDay($filter_date);
            }
        }
        $d = date_create($filter_date);

        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  '{$d->format('Y')}'")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e){
            $t[] = $e['date'];
            return $t;
        }, []);


        //If weekend
        if ($d->format("N") >= 6 || in_array($filter_date, $holiday)) {
            $filter_date = $this->previousDay($filter_date);
        }

        $data = StockHelper::getStockData($filter_date);

        if($data == null){
            return redirect(route("order", ["filter_date" => $this->previousDay($filter_date)]));
        }

        $tomorrow = $this->nextDay($filter_date);


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

            /*"BF" => "BF",
            "BU" => "BU",
            "BH" => "BH",
            "BN" => "BN",
            "appearance" => "appearance",
            "general_start" => "G start",
            "general_price_907" => "G 907",*/
            "place_order" => "預計賣",
            "wail_until" => "等拉高到",
            //"borrow_ticket" => "券",
            /*"previous_high" => "previous_high",
            "current_high" => "current_high",*/

        ];

        return view("backend.order")->with(compact("data", "header", "filter_date", "tomorrow"));

    }

    public function data(Request $request, $date = null)
    {
        if(!$date) $date = $request->filter_date;

        $filter_date = $date;

        $data = StockHelper::getStockData($date);

        $header = [
            "date" => "漲停日",
            "code" => "Code",
            "name" => "名稱",
            "final" => "成交價",
            "range" => "漲跌幅",
            "vol" => "成交張數",

            "agency" => "主要",
            "total_agency_vol" => "隔日沖買超",
            "total_agency_rate" => "占比",
            "single_agency_vol" => "集中張數",
            "single_agency_rate" => "集中度",
            "agency_price" => "買均價",

            "large_trade" => "爆量",
            "trend" => "開盤漲跌",
            "place_order" => "預計賣",
            //"wait_until" => "等拉高到",
            "agency_forecast" => "主力賣出預測",

            "order_start" => "開盤價",
            //"start" => "開盤",
            "max" => "最高",
            "lowest" => "最低",
            "arav_final" => "成交價",
            "price_range" => "漲跌幅",
            "order_price_range" => "開盤漲幅",
            "price_907" => "9:07價",

            //"order_price_range" => "開盤漲幅",

            //"start_agency_range" => "開盤主力差",




        ];

        //$this->toTable($data, $mapping_label);
        return view("backend.data")->with(compact("data", "header", "filter_date"));

    }

    public function generalStock(Request $request, $filter_date = null)
    {

        if(!$filter_date && $request->filled("date"))
            $filter_date = $request->date;

        $header = [
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
            "predict_BK" => "預測漲跌with開盤",
            "custom_general_predict" => "預測",

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
            ->leftJoinSub($last20Days, "avg_yesterday", function ($join) {
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
            ->addSelect(DB::raw("IF(today_final IS NOT NULL, ROUND(avg_today.sum_today_final/20, 2), '') as day_average_20"))
            ->addSelect(DB::raw("ROUND(avg_yesterday.sum_today_final/20, 2)+30 as predict_20d_average"))
            //->addSelect(DB::raw("( (SELECT predict_20d_average)*20 - last_19_days.sum_today_final) as predict_final"))
            ->addSelect("general_stocks.predict_final as predict_final")
            ->addSelect(DB::raw("(IF(general_start < -1, 1, (SELECT predict_final) - general_start)) as predict_BK"))
            ->addSelect("general_stocks.custom_general_predict")
            ->addSelect(DB::raw("( ROUND(((general_start - (SELECT yesterday_final))/(SELECT yesterday_final))*100, 2) ) as general_start_rate"))
            ->addSelect(DB::raw("( ROUND(((general_stocks.today_final - (SELECT yesterday_final))/(SELECT yesterday_final))*100, 2) ) as range_value"))
            ->whereRaw("DAYOFWEEK(general_stocks.date) BETWEEN 2 and 6")
            ->orderByDesc("general_stocks.date");

        if ($filter_date) {
            $query->where("general_stocks.date", $filter_date);
        }

        $data = $query->take(21)->get()->toArray();


        $tmr = $this->nextDay(date("Y-m-d"));
        $tmrGeneralProduct = GeneralStock::where("date", $tmr)->first();
        if (!$tmrGeneralProduct) {
            $tmrGeneralProduct = new GeneralStock([
                "date" => $tmr
            ]);
            $tmrGeneralProduct->save();
        }


        //$this->toTable($data, $header);
        return view("backend.general")->with(compact("data", "header", "filter_date"));
    }
}
