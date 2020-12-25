<?php


namespace App\Crawler;


use App\Dl;
use App\GeneralPrice;
use App\GeneralStock;
use App\Holiday;
use App\StockOrder;
use App\StockPrice;
use DateTime;
use Goutte\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class StockHelper
{

    public static function get_content($url)
    {
        try {
            return file_get_contents($url, false, stream_context_create(array(
                "ssl" => array(
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ),
            )));

        } catch (\Exception $e) {
            //Log::error($e->getMessage());
        }

        return null;
    }

    /**
     * @return array
     */
    public static function getHoliday()
    {
        $year = date("Y");
        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  {$year}")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e) {
            $t[] = $e['date'];

            return $t;
        }, []);

        return (array)$holiday;
    }

    /**
     * @return bool
     */
    public static function isHoliday()
    {
        return in_array(date("Y-m-d"), self::getHoliday());
    }

    public static function crawlGet($url, $selector)
    {
        $client = new Client();
        $crawler = $client->request("GET", $url);

        return $crawler->filter($selector)->last();
    }

    /**
     * @param $value
     * @return float
     */
    public static function format_number($value)
    {
        return floatval(preg_replace("/[\,]/", "", $value));
    }

    /**
     * @param $stocks
     * @return string
     */
    public static function getUrlFromStocks($stocks)
    {
        $stocks_str = implode("|", array_reduce($stocks, function ($t, $e) {
            $t[] = "{$e->type}_{$e->code}.tw";

            return $t;
        }, []));

        //?ex_ch=tse_3218.tw
        return 'https://mis.twse.com.tw/stock/api/getStockInfo.jsp?' . http_build_query([
                "ex_ch" => $stocks_str,
                "json" => 1,
                "_" => time()
            ]);
    }

    /**
     * @param $date
     * @return array
     */
    public static function getDate($date)
    {
        if (!$date) {
            $date = date_create(now());
        }
        if (is_string($date)) {
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


    public static function previousDay($day)
    {

        $date = self::getDate($day);
        $previous_day = strtotime("$day -1 day");
        $previous_day_date = getdate($previous_day);

        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  '{$date['year']}'")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e) {
            $t[] = $e['date'];

            return $t;
        }, []);

        if ($previous_day_date["wday"] == 0 || $previous_day_date["wday"] == 6 || in_array(date('Y-m-d', $previous_day), $holiday)) {
            return self::previousDay(date('Y-m-d', $previous_day));
        } else {
            return date('Y-m-d', $previous_day);
        }
    }

    public static function nextDay($day)
    {
        $date = self::getDate($day);
        $next_day = strtotime("$day +1 day");
        $next_day_date = getdate($next_day);

        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  '{$date['year']}'")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e) {
            $t[] = $e['date'];

            return $t;
        }, []);

        if ($next_day_date["wday"] == 0 || $next_day_date["wday"] == 6 || in_array(date('Y-m-d', $next_day), $holiday)) {
            return self::nextDay(date('Y-m-d', $next_day));
        } else {
            return date('Y-m-d', $next_day);
        }
    }

    public static function offset_date($timestamp)
    {
        $time = new DateTime();
        $time->setTimestamp($timestamp);

        $tz = timezone_open("Asia/Taipei");

        $offset = timezone_offset_get($tz, $time);

        $newdate = new DateTime();
        $newdate->setTimestamp($time->getTimestamp() - $offset);

        return $newdate;
    }

    public static function previousDayJoin($day, $filter_date)
    {

        $d = self::previousDay($filter_date);

        $data = DB::table("dl")
            ->addSelect("dl.code")
            ->addSelect(DB::raw("COUNT(*) as count"))
            ->where("dl_date", "=", $d)
            ->whereRaw("dl.agency IS NOT NULL")
            ->where("dl.final", ">=", 10)
            ->where("dl.final", "<", 170)
            ->groupBy("dl.code");

        if ($day == 2) {
            $pv1 = self::previousDayJoin(1, $d);

            return $data->joinSub($pv1, "previous_day_2_join", "dl.code", "=", "previous_day_2_join.code");
        }
        if ($day == 3) {
            $pv1 = self::previousDayJoin(2, self::previousDay($d));

            return $data->joinSub($pv1, "previous_day_3_join", "dl.code", "=", "previous_day_3_join.code");
        }

        return $data;
    }


    public static function getStockData($filter_date = null, $code = null, $current_price = null, $current_highest_price = null)
    {

        $previousDay1 = self::previousDayJoin(1, $filter_date);
        $previousDay2 = self::previousDayJoin(2, $filter_date);
        $previousDay3 = self::previousDayJoin(3, $filter_date);


        $data = DB::table("dl")
            ->leftJoin("aravs", function ($join) {
                $join->on("dl.code", "=", "aravs.code")->whereRaw(DB::raw("dl.date = aravs.date"));
            })
            ->leftJoin("general_stocks", "general_stocks.date", "=", "dl.date")
            ->join("stocks", "stocks.code", "=", "dl.code")
            ->leftJoinSub($previousDay1, "previous_1_day", "dl.code", "=", "previous_1_day.code")
            ->leftJoinSub($previousDay2, "previous_2_day", "dl.code", "=", "previous_2_day.code")
            ->leftJoinSub($previousDay3, "previous_3_day", "dl.code", "=", "previous_3_day.code")
            ->select("dl.dl_date as date")
            ->addSelect("dl.code")
            ->addSelect(DB::raw("IF(previous_3_day.count = 1, (if(previous_2_day.count = 1, (if(previous_1_day.count=1, 4, 3)), (if(previous_1_day.count=1, 2, 1)) )), (if(previous_1_day.count=1, 2, 1)) ) as appearance"))
            ->addSelect(DB::raw("stocks.name as name"))
            ->addSelect("dl.agency")
            ->addSelect("dl.final")
            ->addSelect("dl.range")
            ->addSelect(DB::raw("ROUND(dl.vol, 0) as vol"))
            ->addSelect(DB::raw("ROUND(dl.total_agency_vol, 0) as total_agency_vol"))
            ->addSelect(DB::raw("ROUND(dl.single_agency_vol, 0) as single_agency_vol"))
            ->addSelect("aravs.start")
            ->addSelect("aravs.max")
            ->addSelect("aravs.lowest")
            ->addSelect(DB::raw("aravs.final as arav_final"))
            ->addSelect("aravs.price_range")
            ->addSelect("dl.borrow_ticket")
            ->addSelect(DB::raw("ROUND((dl.total_agency_vol/dl.vol)*100, 2) as total_agency_rate"))
            ->addSelect(DB::raw("ROUND((dl.single_agency_vol/dl.vol)*100, 2) as single_agency_rate"))
            ->addSelect("dl.agency_price")
            ->addSelect("dl.large_trade")
            ->addSelect("dl.date as next_date")
            ->addSelect(DB::raw("dl.open as order_start"));

        if ($current_price) {
            $data = $data->addSelect(DB::raw("(SELECT {$current_price}) as price_907"));
        } else {
            $data = $data->addSelect(DB::raw("dl.price_907 as price_907"));
        }

        if ($current_highest_price) {
            $data = $data->addSelect(DB::raw("(SELECT {$current_highest_price}) as current_high"));
        } else {
            $data = $data->addSelect(DB::raw("dl.high as current_high"));
        }

        $data = $data
            ->addSelect(DB::raw("(SELECT today_final FROM general_stocks WHERE date = dl.dl_date LIMIT 1) as yesterday_final"))
            ->addSelect(DB::raw("general_stocks.general_start as general_start"))
            ->addSelect(DB::raw("general_stocks.price_905 as general_price_907"))
            ->addSelect(DB::raw("(SELECT predict_final FROM general_stocks WHERE date = next_date LIMIT 1) as predict_final"))
            ->addSelect(DB::raw("IF(general_stocks.custom_general_predict IS NULL, ((SELECT general_price_907) - (SELECT general_start)), general_stocks.custom_general_predict) as general_predict"))
            ->addSelect(DB::raw("(((SELECT order_start)-dl.final)/dl.final)*100 as BF"))
            ->addSelect(DB::raw("(((SELECT order_start)-dl.agency_price)/dl.agency_price)*100 as BU"))
            ->addSelect(DB::raw("(((SELECT general_start)-(SELECT yesterday_final))/(SELECT yesterday_final))*100 as BN"))
            ->addSelect(DB::raw("(((SELECT price_907)-(SELECT order_start))/(SELECT order_start))*100 as BH"))
            ->addSelect(DB::raw("ROUND((SELECT BF), 2) as order_price_range"))
            ->addSelect(DB::raw("IF((SELECT price_907) IS NULL, '等資料', IF((SELECT price_907) <= (SELECT order_start), '下', '上' ) ) as trend"))
            ->addSelect(DB::raw("ROUND(IF( (SELECT BF) <= 2 AND (SELECT BU) >= 3.2 AND (SELECT single_agency_rate) >= 2.2 AND dl.large_trade >= 1.8, dl.final*1.055, 
                IF((SELECT BF) <= 2.2 AND (SELECT single_agency_rate) >= 10 AND (SELECT BU) >= 4, dl.final*1.065, 
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
                                        IF((SELECT price_907) <= dl.agency_price, '等拉高',
                                            IF((SELECT general_price_907)<(SELECT general_start) AND (SELECT BN)<=0.2 AND (SELECT BF)>=2.27 AND (SELECT price_907)>=(SELECT order_start) AND (SELECT order_start)>=(SELECT agency_forecast), '等拉高',
                                                IF((SELECT start_agency_range)<=0 AND (SELECT trend)='下' AND (SELECT BI)<=0 AND (SELECT order_start)<=dl.agency_price AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                    IF((SELECT start_agency_range)<=1.2 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                        IF((SELECT general_price_907)<(SELECT general_start) AND (SELECT BN)>=0.2 AND (SELECT trend)='上' AND (SELECT price_907)<(SELECT agency_forecast) AND (select appearance) = 0, '馬上做多單',
                                                            IF((SELECT general_price_907)>(SELECT general_start) AND (SELECT BF)>=5 AND (SELECT price_907)>=(SELECT order_start), '等拉高',
                                                                IF((SELECT general_price_907)<(SELECT general_start) AND (SELECT trend)='上' AND (SELECT order_start)<(SELECT agency_forecast) AND (SELECT BU)>1 AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                    IF((SELECT general_price_907)<(SELECT general_start) AND (SELECT trend)='下' AND (SELECT order_start)<(SELECT agency_forecast) AND (SELECT BU)>1 AND (SELECT BH)<=-3 AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                        IF((SELECT general_predict) < 0 AND (SELECT BN)<=-0.01 AND (SELECT BF)<=0.1 AND (SELECT order_start)<=dl.agency_price AND (SELECT price_907)>=(SELECT order_start) AND (SELECT price_907) < (SELECT agency_forecast) AND (select appearance) = 0, '馬上做多單',
                                                                            IF((SELECT general_price_907)<(SELECT general_start) AND (SELECT BN)>=0.5 AND (SELECT order_start)<=(SELECT agency_forecast) AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (SELECT price_907) < (SELECT agency_forecast) AND (select appearance) = 0, '馬上做多單',
                                                                                IF((SELECT general_predict) >=0 AND (SELECT BN)<=0.2 AND (SELECT BN)<=-0.01 AND (SELECT BF)>=5 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                                    IF((SELECT general_predict) >= 0 AND (SELECT order_start)<=dl.agency_price AND (SELECT BN)>=0.05 AND (SELECT BF)>=3 AND (SELECT price_907)>=(SELECT order_start) AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                                        IF((SELECT trend)='下' AND (SELECT BN)<-0.4 AND (SELECT BN)<0 AND (SELECT BF)<=1 AND (SELECT BF)<=0.9 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                                            IF((SELECT general_predict) >= 0 AND (SELECT BN)<0 AND (SELECT BF)<=2.2 AND (SELECT price_907)<=(SELECT order_start), (SELECT price_907),
                                                                                                IF((SELECT general_predict) >= 0 AND (SELECT BN)>=0.1 AND (SELECT BF)>=2.3 AND (SELECT start_agency_range)>=1.5 AND (SELECT trend)='下', (SELECT price_907),
                                                                                                    IF((SELECT general_price_907)>(SELECT general_start) AND (SELECT BN)<=0.2 AND (SELECT BN)>=0.01 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                                                        IF((SELECT general_predict) < 0 AND (SELECT BN)<=0.01 AND (SELECT BF)<=2 AND (SELECT BF)>=1 AND (SELECT trend)='下' AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                                                            IF((SELECT general_predict) >= 0 AND (SELECT BN)<0 AND (SELECT aravs.max)>=dl.final AND dl.large_trade<=2 AND ((SELECT agency_forecast)-dl.final)/dl.final>=6.5, '做多單',
                                                                                                                IF((SELECT general_price_907)>(SELECT general_start) AND (SELECT BF)<=0.05 AND  (SELECT single_agency_rate)>=4 AND (SELECT BU)<=2, '等拉高',
                                                                                                                    IF((SELECT general_price_907)>(SELECT general_start) AND (SELECT price_907)>=(SELECT order_start) AND (SELECT BN)<=1.16, '等拉高',
                                                                                                                        IF((SELECT BF)<=2.2 AND  (SELECT single_agency_rate)>=10 AND (SELECT BU)>=4 AND (SELECT general_predict) >= 0, '等拉高',
                                                                                                                            IF((SELECT order_start)<=1.5 AND (SELECT order_start)<=dl.agency_price AND dl.large_trade<=2 AND (SELECT general_price_907)>(SELECT general_start) AND (SELECT price_907)<(SELECT order_start) AND (SELECT BH)>=-5, '等拉高',
                                                                                                                                IF((SELECT BF)<-9 AND dl.agency_price<=dl.final, dl.final,
                                                                                                                                    IF((SELECT BF)<=1.5 AND dl.agency_price>=dl.final AND (SELECT agency_forecast)>=(SELECT order_start) AND dl.large_trade<=2 AND ((SELECT order_start)/(SELECT agency_forecast))<=1.005, '等拉高',
                                                                                                                                        IF((SELECT order_start)<=dl.agency_price AND (SELECT BF)<=4 AND (SELECT BF)>=3 AND ((SELECT order_start)/(SELECT agency_forecast))<=1.012 AND dl.large_trade<6, '等拉高',
                                                                                                                                            IF((SELECT BF)<=-1 AND dl.agency_price<=dl.final, (SELECT price_907),
                                                                                                                                                IF((SELECT BF)<=1.2 AND (SELECT BU)>=1 AND (SELECT single_agency_rate)>=2.2 AND dl.large_trade>=1.8 AND (SELECT general_predict) >= 0, '等拉高',
                                                                                                                                                    IF((SELECT general_price_907)>(SELECT general_start) AND (SELECT BF)<=1 AND (SELECT order_start)<=(SELECT agency_forecast) AND (SELECT price_907)<(SELECT order_start), '等拉高',
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
                )
                 as place_order"))
            ->addSelect(DB::raw("
            ROUND(IF((SELECT place_order)='等拉高' AND (SELECT general_price_907)>=(SELECT general_start) AND (SELECT trend)='上' AND (SELECT BN)>=0.3 AND (SELECT BF)>=5, (SELECT order_start)*1.023,
                IF((SELECT place_order)='等拉高' AND (SELECT trend)='上' AND (SELECT general_price_907)>=(SELECT general_start) AND (SELECT BN)>=0.3 AND (SELECT BF)<5 AND (SELECT order_start)<100, (SELECT order_start)*1.038,
                    IF((SELECT place_order)='等拉高' AND (SELECT general_price_907)>(SELECT general_start) AND (SELECT price_907)>=(SELECT order_start) AND (SELECT start_agency_range)<=1.5, (SELECT order_start)*1.032,
                        IF((SELECT place_order)='等拉高' AND (SELECT general_price_907)>(SELECT general_start) AND (SELECT price_907)>=(SELECT order_start) AND (SELECT start_agency_range)>=1.5, (SELECT order_start)*1.024,
                            IF((SELECT place_order)='等拉高' AND (SELECT trend)='上' AND (SELECT general_price_907)>=(SELECT general_start) AND (SELECT BN)>=0.3 AND (SELECT BF)<5 AND (SELECT order_start)>=100, (SELECT order_start)*1.038,  
                                IF((SELECT place_order)='等拉高' AND (SELECT trend)='上' AND (SELECT general_price_907)<=(SELECT general_start) AND (SELECT BF)>=0.5, (SELECT order_start)*1.045,
                                    IF((SELECT place_order)='等拉高' AND (SELECT BF)<=1.5 AND dl.agency_price<=dl.final AND dl.large_trade<=2, dl.agency_price*1.017,
                                        IF((SELECT place_order)='等拉高' AND (SELECT general_predict) < 0 AND (SELECT price_907)>=(SELECT order_start) AND (SELECT BU)<=4.2 AND (SELECT BU)>=2 AND (SELECT start_agency_range)<=1.5, (SELECT order_start)*1.043,
                                            IF((SELECT BF)<=4 AND (SELECT BF)>=3 AND (SELECT place_order)='等拉高', (SELECT order_start)*1.018,
                                                IF((SELECT place_order)='等拉高' AND (SELECT BF)<=5 AND (SELECT BF)>=4, (SELECT order_start)*1.03, 
                                                    IF((SELECT place_order)='做多單' AND (SELECT BF)<=5 AND (SELECT BF)>=4, (SELECT order_start)*1.028,
                                                        IF((SELECT place_order) != '等拉高' OR (SELECT place_order)='漲停不下單', 0, (SELECT agency_forecast))
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
            ),2) as wail_until"));

        if ($filter_date) {
            $data = $data->where("dl.dl_date", $filter_date);
        }

        if ($code) {
            $data = $data->where("dl.code", $code)->first();
        } else {
            $data = $data->whereRaw("dl.agency IS NOT NULL")
                ->where("dl.final", ">=", 10)
                ->where("dl.final", "<=", 200)
                ->orderBy("dl.date", "desc")
                ->orderBy("appearance", "desc")
                ->orderBy("total_agency_rate", "desc")
                ->orderBy("single_agency_rate", "desc")
                ->orderBy("dl.large_trade", "desc")
                ->get()
                ->toArray();
        }

        return $data;
    }


    public static function getCurrentGeneralPrice($tlong = null)
    {

        if (!$tlong) $tlong = time() * 1000;

        $date = new DateTime();
        $date->setTimestamp($tlong / 1000);

        $current_general = $general = Redis::hgetall("General:realtime#{$date->format("YmdHi")}");;

        if (!$current_general) {

            $current_general = GeneralPrice::where("date", $date->format("Y-m-d"))->where("tlong", "<=", $tlong)->orderByDesc("tlong")->first();
            if ($current_general) {
                $current_general = $current_general->toArray();
                Redis::hmset("General:realtime#{$date->format("YmdHi")}", $current_general);
            }
        }

        return $current_general;
    }

    /**
     * @param $filter_date
     * @return float
     */
    public static function getGeneralStart($filter_date)
    {
        $general_start = (float)Redis::get("General:open_today");
        if (!$general_start) {
            $stock = GeneralStock::where("date", $filter_date)->first();
            if ($stock) {
                $general_start = $stock->general_start;
                Redis::set("General:open_today", $general_start);
            }

        }

        return $general_start;
    }

    /**
     * @return float
     */
    public static function getYesterdayFinal($filter_date)
    {
        $y = (float)Redis::get("General:yesterday_final");
        if (!$y) {
            $stock = GeneralStock::where("date", self::previousDay($filter_date))->first();
            if ($stock) {
                $y = $stock->today_final;
                Redis::set("General:yesterday_final", $y);
            }

        }

        return $y;
    }

    public static function getStockTrend(StockPrice $stockPrice, $minute = 5)
    {

        $trend = Redis::get("Stock:trend#{$stockPrice->code}*{$stockPrice->time->format("YmdHi")}");

        if (!$trend) {
            $milliseconds = $minute * 60 * 1000;
            $query = DB::select("SELECT IF(s1.best_ask_price > (SELECT best_ask_price from stock_prices s2 WHERE s1.date = s2.date AND s1.code = s2.code AND s1.tlong - s2.tlong >= {$milliseconds} ORDER BY s2.tlong DESC limit 1), 'UP', 'DOWN') as trend
FROM `stock_prices` s1
WHERE s1.date = '{$stockPrice->date}' AND s1.tlong <= {$stockPrice->tlong} AND code = {$stockPrice->code}
ORDER BY `tlong` DESC LIMIT 1");
            $trend = $query[0]->trend;
            Redis::set("Stock:trend#{$stockPrice->code}*{$stockPrice->time->format("YmdHi")}", $trend);
        }

        return $trend;
    }

    public static function getGeneralTrend(StockPrice $stockPrice, $minute = 5)
    {
        $trend = Redis::get("General:trend#{$stockPrice->time->format("YmdHi")}");

        if (!$trend) {
            $milliseconds = $minute * 60 * 1000;
            $general_trend = DB::select("SELECT DATE_FORMAT(FROM_UNIXTIME(s1.tlong/1000), '%H:%i:%s') as time,  IF(s1.value > (SELECT value from general_prices s2 WHERE s1.date = s2.date AND s1.tlong - s2.tlong >= {$milliseconds} ORDER BY s2.tlong DESC limit 1), 'UP', 'DOWN') as trend
FROM `general_prices` s1
WHERE s1.date = '{$stockPrice->date}' AND s1.tlong <= {$stockPrice->tlong}
ORDER BY `time` DESC LIMIT 1");
            if ($general_trend) {
                Redis::set("General:trend#{$stockPrice->time->format("YmdHi")}", $general_trend[0]->trend, "EX", 600);
            }

        }

        return $trend;
    }


    public static function getDL0Stocks($filter_date)
    {
        if (!$filter_date) {
            $filter_date = date("Y-m-d");
        }

        $stocks = Dl::join("stocks", "stocks.code", "=", "dl.code")
            ->addSelect("dl.code")
            ->addSelect("stocks.type")
            ->where("dl.final", "<", 200)
            ->where("dl.final", ">", 10)
            ->whereRaw("dl.agency IS NOT NULL")
            ->whereIn("dl.date", [
                $filter_date,
                self::previousDay($filter_date),
                self::previousDay(self::previousDay($filter_date)),
            ])
            ->groupBy(["dl.code", "stocks.type"])
            ->orderByDesc("dl.date")
            ->get();

        Redis::del("Stock:DL0");
        foreach ($stocks as $stock) {
            Redis::lpush("Stock:DL0", $stock->code);
        }

        return $stocks;
    }


    public static function getDL1Stocks($filter_date)
    {
        if (!$filter_date) {
            $filter_date = date("Y-m-d");
        }

        $stocks = Dl::join("stocks", "stocks.code", "=", "dl.code")
            ->addSelect("dl.code")
            ->addSelect("stocks.type")
            ->where("dl.final", ">=", 10)
            ->where("dl.final", "<=", 200)
            ->whereRaw("dl.agency IS NOT NULL")
            ->groupBy(["dl.code", "stocks.type"])
            ->orderByDesc("dl.date")
            ->where("dl.date", $filter_date)->get();

        Redis::del("Stock:DL1");
        foreach ($stocks as $stock) {
            Redis::lpush("Stock:DL1", $stock->code);
        }

        return $stocks;
    }

    public static function getDL0StocksCode($filter_date = null)
    {
        if (!$filter_date) $filter_date = date("Y-m-d");
        $dl0 = Redis::lrange("Stock:DL0", 0, -1);
        if (!$dl0) {
            Log::debug("No dl0 in redis. retrieving from db");
            $stocks = self::getDL0Stocks($filter_date);

            foreach ($stocks as $stock) {
                $dl0[] = $stock->code;
                Redis::lpush("Stock:DL0", $stock->code);
            }
        }

        return $dl0;
    }

    public static function getDL1StocksCode($filter_date = null)
    {
        if (!$filter_date) {
            $filter_date = date("Y-m-d");
        }

        $dl1 = Redis::lrange("Stock:DL1", 0, -1);

        if (!$dl1) {
            Log::debug("No dl1 in redis. retrieving from db");
            $stocks = self::getDL1Stocks($filter_date);

            foreach ($stocks as $stock) {
                $dl0[] = $stock->code;
                Redis::lpush("Stock:DL1", $stock->code);
            }
        }

        return $dl1;


    }

    public static function loadGeneralPrices($filter_date = null)
    {
        if (!$filter_date) $filter_date = date("Y-m-d");
        $general_realtime = GeneralPrice::where("date", $filter_date)->orderBy("tlong")->get();
        foreach ($general_realtime as $generalPrice) {
            Redis::hmset("General:realtime#{$generalPrice->time->format("YmdHi")}", $generalPrice->toArray());
        }
    }


}
