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
            ->addSelect(DB::raw("IF(previous_3_day.count = 1, (if(previous_2_day.count = 1, (if(previous_1_day.count=1, 4, 3)), (if(previous_1_day.count=1, 2, 1)) )), (if(previous_1_day.count=1, 2, 1)) ) as appearance"))
            ->addSelect(DB::raw("stocks.name as name"))

            ->addSelect("dl.agency")
            ->addSelect(DB::raw("ROUND((dl.total_agency_vol/dl.vol)*100, 2) as total_agency_rate"))

            ->addSelect(DB::raw("ROUND((dl.single_agency_vol/dl.vol)*100, 2) as single_agency_rate"))
            ->addSelect("dl.agency_price")

            ->addSelect("dl.large_trade")

            ->addSelect(DB::raw("(SELECT open FROM stock_prices WHERE code = dl.code AND date = dl.date and open > 0 ORDER BY tlong ASC LIMIT 1) as order_start"))
            ->addSelect(DB::raw("(SELECT best_ask_price FROM stock_prices WHERE code = dl.code AND date = dl.date  AND tlong < UNIX_TIMESTAMP('{$this->nextDay($filter_date)} 09:08:00')*1000 AND best_ask_price > 0 ORDER BY tlong DESC LIMIT 1) as price_907"))

            ->addSelect(DB::raw("(SELECT value FROM general_prices WHERE date = '{$this->nextDay($filter_date)}' ORDER BY tlong ASC LIMIT 1) as general_start"))
            ->addSelect(DB::raw("(SELECT value FROM general_prices WHERE date = '{$filter_date}' ORDER BY tlong DESC LIMIT 1) as yesterday_final"))

            ->addSelect(DB::raw("ROUND(avg_yesterday.sum_today_final/20, 2)+30 as predict_20d_average"))
            ->addSelect(DB::raw("( (SELECT predict_20d_average)*20 - last_19_days.sum_today_final - 700) as predict_final"))
            ->addSelect(DB::raw("((SELECT predict_final) - (SELECT general_start)) as general_predict"))


            ->addSelect(DB::raw("(((SELECT order_start)-dl.final)/dl.final)*100 as BF"))
            ->addSelect(DB::raw("(((SELECT order_start)-dl.agency_price)/dl.agency_price)*100 as BU"))
            ->addSelect(DB::raw("(((SELECT general_start)-(SELECT yesterday_final))/(SELECT yesterday_final))*100 as BN"))
            ->addSelect(DB::raw("(((SELECT price_907)-(SELECT order_start))/(SELECT order_start))*100 as BH"))

            ->addSelect(DB::raw("ROUND((SELECT BF), 2) as order_price_range"))

            ->addSelect(DB::raw("IF((SELECT price_907) IS NULL, '等資料', IF((SELECT price_907) <= (SELECT order_start), '下', '上' ) ) as trend"))

            ->addSelect(DB::raw("ROUND(IF( (SELECT BF) <= 2 AND (SELECT BU) >= 3.2 AND dl.large_trade >= 1.8, dl.final*1.055, 
                IF((SELECT BF) <= 2.2 AND (dl.single_agency_vol/dl.vol)*100 >= 10 AND (SELECT BU) >= 4, dl.final*1.065, 
                    IF((SELECT order_start) >= dl.final AND (SELECT BF) <1.5 AND dl.agency_price <= dl.final, dl.final*1.03, 
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

            ->addSelect(DB::raw("ROUND((((SELECT order_start) - (SELECT agency_forecast))/(SELECT agency_forecast))*100, 1) as start_agency_range"))

            ->addSelect(DB::raw("(((SELECT price_907)-(SELECT agency_forecast))/(SELECT agency_forecast))*100 as BI"))

            ->addSelect(DB::raw("
            IF((SELECT BN)<=-1, '馬上做多單',
                    IF((SELECT BF)>=5 AND (SELECT BH)>=1, '漲停不下單',
                        IF((SELECT BF)>=0.3 AND (SELECT BH)>=4.9, '漲停不下單',
                            IF((SELECT BF)>=3.8 AND (SELECT BH)>=4, '漲停不下單',
                                IF((SELECT BF)>=7.5 AND (SELECT price_907)>=(SELECT order_start), '漲停不下單',
                                    IF((SELECT general_predict)='' OR (SELECT general_predict) IS NULL OR (SELECT BN)='' OR (SELECT order_start) IS NULL OR (SELECT price_907) IS NULL, '等資料',
                                        IF(general_stocks.price_905<(SELECT general_start) AND (SELECT BN)<=0.2 AND (SELECT BF)>=2.27 AND (SELECT price_907)>=(SELECT order_start) AND (SELECT order_start)>=(SELECT agency_forecast), '等拉高',
                                            IF((SELECT start_agency_range)<=0 AND (SELECT trend)='下' AND (SELECT BI)<=0 AND (SELECT order_start)<=dl.agency_price, '等低點做多單',
                                                IF((SELECT start_agency_range)<=1.2 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price, '等低點做多單',
                                                    IF(general_stocks.price_905<(SELECT general_start) AND (SELECT BN)>=0.2 AND (SELECT trend)='上' AND (SELECT price_907)<=(SELECT agency_forecast) AND (select appearance) = 0, '馬上做多單',
                                                        IF(general_stocks.price_905>(SELECT general_start) AND (SELECT BF)>=5 AND (SELECT price_907)>=(SELECT order_start), '等拉高',
                                                            IF(general_stocks.price_905<(SELECT general_start) AND (SELECT trend)='上' AND (SELECT order_start)<(SELECT agency_forecast) AND (SELECT BU)>1, '等低點做多單',
                                                                IF(general_stocks.price_905<(SELECT general_start) AND (SELECT trend)='下' AND (SELECT order_start)<(SELECT agency_forecast) AND (SELECT BU)>1 AND (SELECT BH)<=-3, '等低點做多單',
                                                                    IF((SELECT general_predict) < 0 AND (SELECT BN)<=-0.01 AND (SELECT BF)<=0.1 AND (SELECT order_start)<=dl.agency_price AND (SELECT price_907)>=(SELECT order_start) AND (select appearance) = 0, '馬上做多單',
                                                                        IF(general_stocks.price_905<(SELECT general_start) AND (SELECT BN)>=0.5 AND (SELECT order_start)<=(SELECT agency_forecast) AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (select appearance) = 0, '馬上做多單',
                                                                            IF((SELECT general_predict) >=0 AND (SELECT BN)<=0.2 AND (SELECT BN)<=-0.01 AND (SELECT BF)>=5 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price, '等低點做多單',
                                                                                IF((SELECT general_predict) >= 0 AND (SELECT order_start)<=dl.agency_price AND (SELECT BN)>=0.05 AND (SELECT BF)>=3 AND (SELECT price_907)>=(SELECT order_start), '等低點做多單',
                                                                                    IF((SELECT trend)='下' AND (SELECT BN)<-0.4 AND (SELECT BN)<0 AND (SELECT BF)<=1 AND (SELECT BF)<=0.9 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price, '等低點做多單',
                                                                                        IF((SELECT general_predict) >= 0 AND (SELECT BN)<0 AND (SELECT BF)<=2.2 AND (SELECT price_907)<=(SELECT order_start), (SELECT price_907),
                                                                                            IF((SELECT general_predict) >= 0 AND (SELECT BN)>=0.1 AND (SELECT BF)>=2.3 AND (SELECT start_agency_range)>=1.5 AND (SELECT trend)='下', (SELECT price_907),
                                                                                                IF(general_stocks.price_905>(SELECT general_start) AND (SELECT BN)<=0.2 AND (SELECT BN)>=0.01 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price, '等低點做多單',
                                                                                                    IF((SELECT general_predict) < 0 AND (SELECT BN)<=0.01 AND (SELECT BF)<=2 AND (SELECT BF)>=1 AND (SELECT trend)='下', '等低點做多單',
                                                                                                        IF((SELECT general_predict) >= 0 AND (SELECT BN)<0 AND aravs.max>=dl.final AND dl.large_trade<=2 AND ((SELECT agency_forecast)-dl.final)/dl.final>=6.5, '做多單',
                                                                                                            IF(general_stocks.price_905>(SELECT general_start) AND (SELECT BF)<=0.05 AND  (SELECT single_agency_rate)>=4 AND (SELECT BU)<=2, '等拉高',
                                                                                                                IF(general_stocks.price_905>(SELECT general_start) AND (SELECT price_907)>=(SELECT order_start) AND (SELECT BN)<=1.16, '等拉高',
                                                                                                                    IF((SELECT BF)<=2.2 AND  (SELECT single_agency_rate)>=10 AND (SELECT BU)>=4 AND (SELECT general_predict) >= 0, '等拉高',
                                                                                                                        IF((SELECT order_start)<=1.5 AND (SELECT order_start)<=dl.agency_price AND dl.large_trade<=2 AND general_stocks.price_905>(SELECT general_start) AND (SELECT price_907)<(SELECT order_start) AND (SELECT BH)>=-5, '等拉高',
                                                                                                                            IF((SELECT BF)<-9 AND dl.agency_price<=dl.final, dl.final,
                                                                                                                                IF((SELECT BF)<=1.5 AND dl.agency_price>=dl.final AND (SELECT agency_forecast)>=(SELECT order_start) AND dl.large_trade<=2 AND ((SELECT order_start)/(SELECT agency_forecast))<=1.005, '等拉高',
                                                                                                                                    IF((SELECT order_start)<=dl.agency_price AND (SELECT BF)<=4 AND (SELECT BF)>=3 AND ((SELECT order_start)/(SELECT agency_forecast))<=1.012 AND dl.large_trade<6, '等拉高',
                                                                                                                                        IF((SELECT BF)<=-1 AND dl.agency_price<=dl.final, (SELECT price_907),
                                                                                                                                            IF((SELECT BF)<=1.2 AND (SELECT BU)>=1 AND (SELECT single_agency_rate)>=2.2 AND dl.large_trade>=1.8 AND (SELECT general_predict) >= 0, '等拉高',
                                                                                                                                                IF(general_stocks.price_905>(SELECT general_start) AND (SELECT BF)<=1 AND (SELECT order_start)<=(SELECT agency_forecast) AND (SELECT price_907)<(SELECT order_start), '等拉高',
                                                                                                                                                    IF((SELECT general_predict) < 0 AND (SELECT BF)<=5 AND (SELECT BF)>=3.7 AND dl.agency_price<dl.final AND (SELECT order_start)<=dl.agency_price AND (SELECT start_agency_range)>=1.5 AND (SELECT trend)='下', '等拉高',
                                                                                                                                                        IF((SELECT trend)='上' AND (SELECT BN)<=1.16, '等拉高', (SELECT price_907))
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

            ->where("dl.final", ">=", 10)
            ->where("dl.date", $filter_date)
            ->whereRaw("dl.agency IS NOT NULL")
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

            "general_predict" => "general_predict",
            "place_order" => "預計賣",
        ];

        $generalStock = GeneralStock::where("date", $filter_date)->first();

        $tomorrow = $this->nextDay($filter_date);

        return view("backend.order")->with(compact("data", "header", "filter_date", "generalStock", "tomorrow"));

    }

    public function data($date = null){


        $previousDay1 = $this->previousDayJoin(1, $date);
        $previousDay2 = $this->previousDayJoin(2, $date);
        $previousDay3 = $this->previousDayJoin(3, $date);


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


        $query = DB::table("dl")
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
            ->addSelect("dl.date")
            ->addSelect("dl.code")
            ->addSelect(DB::raw("stocks.name as name"))
            ->addSelect(DB::raw("IF(previous_3_day.count = 1, (if(previous_2_day.count = 1, (if(previous_1_day.count=1, 4, 3)), (if(previous_1_day.count=1, 2, 1)) )), (if(previous_1_day.count=1, 2, 1)) ) as appearance"))

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

            ->addSelect(DB::raw("(
                SELECT today_final FROM general_stocks gs WHERE
                    (DAYOFWEEK(general_stocks.date) = 2 AND DATEDIFF(general_stocks.date, gs.date) = 3)
                 OR (DAYOFWEEK(general_stocks.date) != 2 AND DATEDIFF(general_stocks.date, gs.date) = 1)
            ) as yesterday_final"))


            ->addSelect(DB::raw("ROUND(avg_yesterday.sum_today_final/20, 2)+30 as predict_20d_average"))
            ->addSelect(DB::raw("( (SELECT predict_20d_average)*20 - last_19_days.sum_today_final - 700) as predict_final"))
            ->addSelect(DB::raw("((SELECT predict_final) - (SELECT general_start)) as general_predict"))


            ->addSelect(DB::raw("(((SELECT order_start)-dl.final)/dl.final)*100 as BF"))
            ->addSelect(DB::raw("(((SELECT order_start)-dl.agency_price)/dl.agency_price)*100 as BU"))
            ->addSelect(DB::raw("((general_stocks.(SELECT general_start)-(SELECT yesterday_final))/(SELECT yesterday_final))*100 as BN"))
            ->addSelect(DB::raw("(((SELECT price_907)-(SELECT order_start))/(SELECT order_start))*100 as BH"))

            ->addSelect(DB::raw("ROUND((SELECT BF), 2) as order_price_range"))

            ->addSelect(DB::raw("IF((SELECT price_907) IS NULL, '等資料', IF((SELECT price_907) <= (SELECT order_start), '下', '上' ) ) as trend"))

            ->addSelect(DB::raw("ROUND(IF( (SELECT BF) <= 2 AND (SELECT BU) >= 3.2 AND dl.large_trade >= 1.8, dl.final*1.055, 
                IF((SELECT BF) <= 2.2 AND (dl.single_agency_vol/dl.vol)*100 >= 10 AND (SELECT BU) >= 4, dl.final*1.065, 
                    IF((SELECT order_start) >= dl.final AND (SELECT BF) <1.5 AND dl.agency_price <= dl.final, dl.final*1.03, 
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

            ->addSelect(DB::raw("ROUND((((SELECT order_start) - (SELECT agency_forecast))/(SELECT agency_forecast))*100, 1) as start_agency_range"))

            ->addSelect(DB::raw("(((SELECT price_907)-(SELECT agency_forecast))/(SELECT agency_forecast))*100 as BI"))

            ->addSelect(DB::raw("
            IF((SELECT BN)<=-1, '馬上做多單',
                    IF((SELECT BF)>=5 AND (SELECT BH)>=1, '漲停不下單',
                        IF((SELECT BF)>=0.3 AND (SELECT BH)>=4.9, '漲停不下單',
                            IF((SELECT BF)>=3.8 AND (SELECT BH)>=4, '漲停不下單',
                                IF((SELECT BF)>=7.5 AND (SELECT price_907)>=(SELECT order_start), '漲停不下單',
                                    IF((SELECT general_predict)='' OR (SELECT general_predict) IS NULL OR (SELECT BN)='' OR (SELECT order_start) IS NULL OR (SELECT price_907) IS NULL, '等資料',
                                        IF(general_stocks.price_905<general_stocks.(SELECT general_start) AND (SELECT BN)<=0.2 AND (SELECT BF)>=2.27 AND (SELECT price_907)>=(SELECT order_start) AND (SELECT order_start)>=(SELECT agency_forecast), '等拉高',
                                            IF((SELECT start_agency_range)<=0 AND (SELECT trend)='下' AND (SELECT BI)<=0 AND (SELECT order_start)<=dl.agency_price, '等低點做多單',
                                                IF((SELECT start_agency_range)<=1.2 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price, '等低點做多單',
                                                    IF(general_stocks.price_905<general_stocks.(SELECT general_start) AND (SELECT BN)>=0.2 AND (SELECT trend)='上' AND (SELECT price_907)<=(SELECT agency_forecast) AND (select appearance) = 0, '馬上做多單',
                                                        IF(general_stocks.price_905>general_stocks.(SELECT general_start) AND (SELECT BF)>=5 AND (SELECT price_907)>=(SELECT order_start), '等拉高',
                                                            IF(general_stocks.price_905<general_stocks.(SELECT general_start) AND (SELECT trend)='上' AND (SELECT order_start)<(SELECT agency_forecast) AND (SELECT BU)>1, '等低點做多單',
                                                                IF(general_stocks.price_905<general_stocks.(SELECT general_start) AND (SELECT trend)='下' AND (SELECT order_start)<(SELECT agency_forecast) AND (SELECT BU)>1 AND (SELECT BH)<=-3, '等低點做多單',
                                                                    IF((SELECT general_predict) < 0 AND (SELECT BN)<=-0.01 AND (SELECT BF)<=0.1 AND (SELECT order_start)<=dl.agency_price AND (SELECT price_907)>=(SELECT order_start) AND (select appearance) = 0, '馬上做多單',
                                                                        IF(general_stocks.price_905<general_stocks.(SELECT general_start) AND (SELECT BN)>=0.5 AND (SELECT order_start)<=(SELECT agency_forecast) AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (select appearance) = 0, '馬上做多單',
                                                                            IF((SELECT general_predict) >=0 AND (SELECT BN)<=0.2 AND (SELECT BN)<=-0.01 AND (SELECT BF)>=5 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price, '等低點做多單',
                                                                                IF((SELECT general_predict) >= 0 AND (SELECT order_start)<=dl.agency_price AND (SELECT BN)>=0.05 AND (SELECT BF)>=3 AND (SELECT price_907)>=(SELECT order_start), '等低點做多單',
                                                                                    IF((SELECT trend)='下' AND (SELECT BN)<-0.4 AND (SELECT BN)<0 AND (SELECT BF)<=1 AND (SELECT BF)<=0.9 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price, '等低點做多單',
                                                                                        IF((SELECT general_predict) >= 0 AND (SELECT BN)<0 AND (SELECT BF)<=2.2 AND (SELECT price_907)<=(SELECT order_start), (SELECT price_907),
                                                                                            IF((SELECT general_predict) >= 0 AND (SELECT BN)>=0.1 AND (SELECT BF)>=2.3 AND (SELECT start_agency_range)>=1.5 AND (SELECT trend)='下', (SELECT price_907),
                                                                                                IF(general_stocks.price_905>general_stocks.(SELECT general_start) AND (SELECT BN)<=0.2 AND (SELECT BN)>=0.01 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price, '等低點做多單',
                                                                                                    IF((SELECT general_predict) < 0 AND (SELECT BN)<=0.01 AND (SELECT BF)<=2 AND (SELECT BF)>=1 AND (SELECT trend)='下', '等低點做多單',
                                                                                                        IF((SELECT general_predict) >= 0 AND (SELECT BN)<0 AND aravs.max>=dl.final AND dl.large_trade<=2 AND ((SELECT agency_forecast)-dl.final)/dl.final>=6.5, '做多單',
                                                                                                            IF(general_stocks.price_905>general_stocks.(SELECT general_start) AND (SELECT BF)<=0.05 AND  (SELECT single_agency_rate)>=4 AND (SELECT BU)<=2, '等拉高',
                                                                                                                IF(general_stocks.price_905>general_stocks.(SELECT general_start) AND (SELECT price_907)>=(SELECT order_start) AND (SELECT BN)<=1.16, '等拉高',
                                                                                                                    IF((SELECT BF)<=2.2 AND  (SELECT single_agency_rate)>=10 AND (SELECT BU)>=4 AND (SELECT general_predict) >= 0, '等拉高',
                                                                                                                        IF((SELECT order_start)<=1.5 AND (SELECT order_start)<=dl.agency_price AND dl.large_trade<=2 AND general_stocks.price_905>general_stocks.(SELECT general_start) AND (SELECT price_907)<(SELECT order_start) AND (SELECT BH)>=-5, '等拉高',
                                                                                                                            IF((SELECT BF)<-9 AND dl.agency_price<=dl.final, dl.final,
                                                                                                                                IF((SELECT BF)<=1.5 AND dl.agency_price>=dl.final AND (SELECT agency_forecast)>=(SELECT order_start) AND dl.large_trade<=2 AND ((SELECT order_start)/(SELECT agency_forecast))<=1.005, '等拉高',
                                                                                                                                    IF((SELECT order_start)<=dl.agency_price AND (SELECT BF)<=4 AND (SELECT BF)>=3 AND ((SELECT order_start)/(SELECT agency_forecast))<=1.012 AND dl.large_trade<6, '等拉高',
                                                                                                                                        IF((SELECT BF)<=-1 AND dl.agency_price<=dl.final, (SELECT price_907),
                                                                                                                                            IF((SELECT BF)<=1.2 AND (SELECT BU)>=1 AND (SELECT single_agency_rate)>=2.2 AND dl.large_trade>=1.8 AND (SELECT general_predict) >= 0, '等拉高',
                                                                                                                                                IF(general_stocks.price_905>general_stocks.(SELECT general_start) AND (SELECT BF)<=1 AND (SELECT order_start)<=(SELECT agency_forecast) AND (SELECT price_907)<(SELECT order_start), '等拉高',
                                                                                                                                                    IF((SELECT general_predict) < 0 AND (SELECT BF)<=5 AND (SELECT BF)>=3.7 AND dl.agency_price<dl.final AND (SELECT order_start)<=dl.agency_price AND (SELECT start_agency_range)>=1.5 AND (SELECT trend)='下', '等拉高',
                                                                                                                                                        IF((SELECT trend)='上' AND (SELECT BN)<=1.16, '等拉高', (SELECT price_907))
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

            /*->addSelect("dl.dynamic_rate_sell")*/

            ->where("dl.final", ">=", 10)
            ->whereRaw("dl.agency IS NOT NULL AND dl.agency != ''")
            ->orderBy("dl.date", "desc")
            /*->orderBy("dl.range", "desc")*/
            ->orderBy("total_agency_rate", "desc")
            ->orderBy("single_agency_rate", "desc")
            ->orderBy("dl.large_trade", "desc");

        if($date){
            $query->where("dl.date", $date);

        }

        $list_dl = $query->take(30)->get()->toArray();

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
            "trend" => "開盤漲跌",
            //"start_agency_range" => "開盤主力差",
            "agency_forecast" => "主力賣出預測",
            "large_trade" => "爆量",

            "place_order" => "預計賣",
        ];

        $this->toTable($list_dl, $mapping_label);

    }

    public function generalStock($filter_date = null){
        $mapping_label = [
            "date" => "Date",
            "(SELECT general_start)" => "上市開盤",
            "price_905" => "上市開盤9:05",
            "(SELECT general_start)_rate" => "開盤漲跌幅",
            "range_value" => "上市漲跌",
            "today_final" => "上市收盤",
            "yesterday_final" => "昨收",
            "day_average_20" => "實際20MA",
            "predict_20d_average" => "20MA預測",
            "predict_final" => "收盤預測",
            "predict_BK" => "預測漲跌with開盤",

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
            ->addSelect("(SELECT general_start)")
            ->addSelect("price_905")
            ->addSelect("general_stocks.today_final")
            ->addSelect(DB::raw("(
                SELECT today_final FROM general_stocks gs WHERE
                    (DAYOFWEEK(general_stocks.date) = 2 AND DATEDIFF(general_stocks.date, gs.date) = 3)
                 OR (DAYOFWEEK(general_stocks.date) != 2 AND DATEDIFF(general_stocks.date, gs.date) = 1)
            ) as yesterday_final"))
            ->addSelect(DB::raw("IF(today_final IS NOT NULL, ROUND(avg_today.sum_today_final/20, 2), '') as day_average_20"))
            ->addSelect(DB::raw("ROUND(avg_yesterday.sum_today_final/20, 2)+30 as predict_20d_average"))
            ->addSelect(DB::raw("( (SELECT predict_20d_average)*20 - last_19_days.sum_today_final - 700) as predict_final"))
            ->addSelect(DB::raw("(IF((SELECT general_start) < -1, 1, (SELECT predict_final) - (SELECT general_start))) as predict_BK"))

            ->addSelect(DB::raw("( ROUND((((SELECT general_start) - (SELECT yesterday_final))/(SELECT yesterday_final))*100, 2) ) as (SELECT general_start)_rate"))
            ->addSelect(DB::raw("( ROUND(((general_stocks.today_final - (SELECT yesterday_final))/(SELECT yesterday_final))*100, 2) ) as range_value"))
            ->whereRaw("DAYOFWEEK(general_stocks.date) BETWEEN 2 and 6")
            ->orderByDesc("general_stocks.date");

        if($filter_date){
            $query->where("general_stocks.date", $filter_date);
        }

        $general_stocks = $query->take(21)->get()->toArray();

        $this->toTable($general_stocks, $mapping_label);
    }
}
