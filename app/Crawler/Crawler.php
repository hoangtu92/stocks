<?php


namespace App\Crawler;


use App\Dl;
use App\GeneralPrice;
use App\GeneralStock;
use App\Holiday;
use App\StockOrder;
use App\StockPrice;
use Goutte\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Crawler
{

    public function __construct()
    {

    }

    public function get_content($url)
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

    public function getHoliday()
    {
        $year = date("Y");
        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  {$year}")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e) {
            $t[] = $e['date'];

            return $t;
        }, []);

        return $holiday;
    }

    public function format_number($value)
    {
        return floatval(preg_replace("/[\,]/", "", $value));
    }

    public function getDate($date)
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

    public function date_from_tw($tw_date)
    {
        $d = explode("/", $tw_date);
        $year = $d[0] + 1911;

        return "{$year}/{$d[1]}/{$d[2]}";
    }

    public function crawlGet($url, $selector)
    {
        $client = new Client();
        $crawler = $client->request("GET", $url);

        return $crawler->filter($selector)->last();
    }


    public function previousDay($day)
    {

        $date = $this->getDate($day);
        $previous_day = strtotime("$day -1 day");
        $previous_day_date = getdate($previous_day);

        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  '{$date['year']}'")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e) {
            $t[] = $e['date'];

            return $t;
        }, []);

        if ($previous_day_date["wday"] == 0 || $previous_day_date["wday"] == 6 || in_array(date('Y-m-d', $previous_day), $holiday)) {
            return $this->previousDay(date('Y-m-d', $previous_day));
        } else {
            return date('Y-m-d', $previous_day);
        }
    }

    public function nextDay($day)
    {
        $date = $this->getDate($day);
        $next_day = strtotime("$day +1 day");
        $next_day_date = getdate($next_day);

        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  '{$date['year']}'")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e) {
            $t[] = $e['date'];

            return $t;
        }, []);

        if ($next_day_date["wday"] == 0 || $next_day_date["wday"] == 6 || in_array(date('Y-m-d', $next_day), $holiday)) {
            return $this->nextDay(date('Y-m-d', $next_day));
        } else {
            return date('Y-m-d', $next_day);
        }
    }

    public function previousDayJoin($day, $filter_date)
    {

        $d = $this->previousDay($filter_date);

        $data = DB::table("dl")
            ->addSelect("dl.code")
            ->addSelect(DB::raw("COUNT(*) as count"))
            ->where("dl_date", "=", $d)
            ->whereRaw("dl.agency IS NOT NULL")
            ->where("dl.final", ">=", 10)
            ->where("dl.final", "<", 170)
            ->groupBy("dl.code");

        if ($day == 2) {
            $pv1 = $this->previousDayJoin(1, $d);

            return $data->joinSub($pv1, "previous_day_2_join", "dl.code", "=", "previous_day_2_join.code");
        }
        if ($day == 3) {
            $pv1 = $this->previousDayJoin(2, $this->previousDay($d));

            return $data->joinSub($pv1, "previous_day_3_join", "dl.code", "=", "previous_day_3_join.code");
        }

        return $data;
    }


    public function getStockData($filter_date = null, $code = null, $current_price = null, $current_highest_price = null)
    {

        $previousDay1 = $this->previousDayJoin(1, $filter_date);
        $previousDay2 = $this->previousDayJoin(2, $filter_date);
        $previousDay3 = $this->previousDayJoin(3, $filter_date);


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
                ->where("dl.final", "<=", 170)
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


    public function getStocksURL()
    {

        $stocks = DB::table("stocks")
            ->whereRaw(" LENGTH(code) = 4");

        $time = time();

        $nop = 80;

        $list = [];

        for ($i = 0; $i < $stocks->count(); $i += $nop) {

            $sub = DB::table("stocks")
                ->whereRaw(" LENGTH(code) = 4")
                ->limit($nop)->offset($i);

            $list[] = DB::table(DB::raw("({$sub->toSql()}) as sub"))
                ->mergeBindings($sub)
                ->selectRaw('CONCAT("https://mis.twse.com.tw/stock/api/getStockInfo.jsp?json=1&delay=0&ex_ch=", GROUP_CONCAT(type, "_", code, ".tw" separator "|")) as url')
                ->first();
        }

        return $list;
    }

    public function getUrlFromStocks($stocks)
    {
        $stocks_str = implode("|", array_reduce($stocks, function ($t, $e) {
            $t[] = "{$e['type']}_{$e['code']}.tw";

            return $t;
        }, []));

        //?ex_ch=tse_3218.tw
        return 'https://mis.twse.com.tw/stock/api/getStockInfo.jsp?' . http_build_query([
                "ex_ch" => $stocks_str,
                "json" => 1,
                "_" => time()
            ]);
    }

    public function getDL1Stocks($filter_date)
    {
        if (!$filter_date) {
            $filter_date = date("Y-m-d");
        }

        return Dl::join("stocks", "stocks.code", "=", "dl.code")
            ->select("dl.date")
            ->addSelect("dl.dl_date")
            ->addSelect("dl.id")
            ->addSelect("dl.code")
            ->addSelect("dl.open")
            ->addSelect("dl.low")
            ->addSelect("dl.high")
            ->addSelect("dl.price_907")
            ->addSelect("dl.borrow_ticket")
            ->addSelect("stocks.type")
            ->where("dl.final", ">=", 10)
            ->where("dl.final", "<=", 170)
            ->whereRaw("dl.agency IS NOT NULL")
            ->where("dl.date", $filter_date)->get();
    }

    public function monitorStock($stock, StockPrice $stockPrice, GeneralStock $generalStock, GeneralStock $yesterdayGeneral)
    {

        if ($stockPrice->current_price <= 0) return;

        $current_general = GeneralPrice::where("date", $stockPrice->date)->where("tlong", "<=", $stockPrice->tlong)->orderByDesc("tlong")->first();

        if(!$current_general) return;



        if (!$stock->open && $stockPrice->open != 0) {
            $stock->open = $stockPrice->open;
            $stock->save();
        }

        if (!$stock->high || $stockPrice->high > $stock->high) {
            $stock->high = $stockPrice->high;
            $stock->save();
        }

        if (!$stock->low || $stockPrice->low < $stock->low) {
            $stock->low = $stockPrice->low;
            $stock->save();
        }

        if ($stockPrice->stock_time["hours"] == 9 && in_array($stockPrice->stock_time["minutes"], [8, 9, 10]) && !$stock->price_907) {
            $stock->price_907 = $stockPrice->current_price;
            $stock->save();
        }

        $unclosed_order = StockOrder::where("code", $stockPrice->code)
            ->where("closed", "=", false)
            ->where("order_type", "=", StockOrder::DL1)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $stockPrice->date)
            ->first();

        /*$previous_order = StockOrder::where("code", $stockPrice->code)
            ->where("closed", "=", true)
            ->where("order_type", "=", StockOrder::DL1)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $stockPrice->date)
            ->orderByDesc("tlong")
            ->first();*/

        if (!$unclosed_order) {

            //Perform task from 09:00 to 09:07
            if ($stockPrice->stock_time["hours"] < 9 || ($stockPrice->stock_time["hours"] == 9 && $stockPrice->stock_time["minutes"] <= 7)) {

                /**
                 * Update stock price data
                 */


                if ($stockPrice->stock_time["hours"] == 9 && $stockPrice->stock_time["minutes"] == 7) {
                    $stock->price_907 = $stockPrice->current_price;
                    $stock->save();
                }

                $data = $this->getStockData($stock->dl_date, $stockPrice->code, $stockPrice->current_price);

                #Log::info("AJ stock data". json_encode($data));

                if (!is_numeric($data->place_order)) {
                    if ($data->place_order == '等拉高') {

                        //Get previous price
                        $previous_prices = StockPrice::where("code", $stockPrice->code)
                            ->where("tlong", "<", $stockPrice->tlong)
                            ->where("date", $stockPrice->date)
                            ->orderBy("tlong", "desc")
                            ->take(3)->get();


                        $previous_price_0 = 0;
                        $previous_price_1 = 0;
                        $previous_price_2 = 0;

                        if (isset($previous_prices[0])) {
                            $previous_price_0 = $previous_prices[0]->current_price;
                        }

                        if (isset($previous_prices[1])) {
                            $previous_price_1 = $previous_prices[1]->current_price;
                        }

                        if (isset($previous_prices[2])) {
                            $previous_price_2 = $previous_prices[1]->current_price;
                        }

                        //Wait a bit and Short selling when meet condition
                        if (isset($previous_prices[2])) {

                            //if price still going up even over the AK suggested price, don’t sell yet. Pls wait until current price drop to  < ‘h’/1.05
                            if (($stockPrice->high >= $data->wail_until && $stockPrice->current_price < $stockPrice->high / 1.05)

                                //OR
                                //if it’s ‘h’ > agency forecast, and it’s dropping down now. need to sell it now, don’t need to wait until 9:07
                                || ($stockPrice->high >= $data->agency_forecast && $stockPrice->current_price < $stockPrice->high / 1.05
                                    && $stockPrice->current_price < $previous_price_0
                                    && $previous_price_0 < $previous_price_1
                                    && $previous_price_1 < $previous_price_2)) {

                                //Short selling now

                                $stockOrder = new StockOrder([
                                    "order_type" => StockOrder::DL1,
                                    "deal_type" => StockOrder::SHORT_SELL,
                                    "date" => $stockPrice->date,
                                    "tlong" => $stockPrice->tlong,
                                    "code" => $data->code,
                                    "qty" => 1,
                                    "closed" => false,
                                    "sell" => $stockPrice->best_bid_price
                                ]);

                                $stockOrder->save();

                                return;


                            }


                        }

                    }
                }

                if ($stockPrice->stock_time["hours"] == 9 && $stockPrice->stock_time["minutes"] == 7) {

                    if (is_numeric($data->place_order) && $data->place_order > 0) {
                        //Short selling now
                        $stockOrder = new StockOrder([
                            "order_type" => StockOrder::DL1,
                            "deal_type" => StockOrder::SHORT_SELL,
                            "date" => $stockPrice->date,
                            "tlong" => $stockPrice->tlong,
                            "code" => $stockPrice->code,
                            "qty" => 1,
                            "closed" => false,
                            "sell" => $data->place_order
                        ]);

                        $stockOrder->save();

                        return;

                    }

                }
            }
        }

        /**
         * ---------------------------------------------------------------------------------------------------------------
         */

        //Close deal??

        else {

            $this->closeOrder($stockPrice, $unclosed_order, $generalStock, $yesterdayGeneral);
        }

        /**
         * ---------------------------------------------------------------------------------------------------------------
         */
    }

    /**
     * @param StockPrice $stockPrice
     * @param GeneralStock|null $generalStock
     * @param GeneralStock|null $yesterdayGeneral
     */
    public function monitorDL0(StockPrice $stockPrice, GeneralStock $generalStock, GeneralStock $yesterdayGeneral)
    {

        if ($stockPrice->current_price <= 0) {
            Log::debug("Price zero");
            return;
        }

        $current_general = GeneralPrice::where("date", $stockPrice->date)->where("tlong", "<=", $stockPrice->tlong)->orderByDesc("tlong")->first();

        if(!$current_general) {
            Log::debug("no general");
            return;
        }



        $unclosed_order = StockOrder::where("code", $stockPrice->code)
            ->where("closed", false)
            ->where("order_type", StockOrder::DL0)
            ->where("deal_type",  StockOrder::SHORT_SELL)
            ->where("date", $stockPrice->date)
            ->orderBy("tlong")
            ->first();

        $previous_order = StockOrder::where("code", $stockPrice->code)
            ->where("closed", "=", true)
            ->where("order_type", "=", StockOrder::DL0)
            ->where("deal_type", "=", StockOrder::SHORT_SELL)
            ->where("date", $stockPrice->date)
            ->orderByDesc("tlong")
            ->first();


        if (!$unclosed_order) {


            //2. only place order between 9:01 and 13:00 current price range <7%
            if (
            (
                ($stockPrice->stock_time["hours"] == 9 && $stockPrice->stock_time["minutes"] >= 1) ||
                ($stockPrice->stock_time["hours"] > 9 && $stockPrice->stock_time["hours"] < 12) ||
                ($stockPrice->stock_time["hours"] == 12 && $stockPrice->stock_time["minutes"] <= 30)
            )

            ) {

                //3. current price <= Y, current price < high and current price range >= -1 , sell it now
                if ($stockPrice->current_price < $stockPrice->yesterday_final
                    && $stockPrice->current_price < $stockPrice->high
                    && $stockPrice->current_price_range >= -3.5) {

                    if(!$previous_order || ($previous_order && ( $previous_order->profit_percent > 0 || ($previous_order->profit_percent <= 0 && $current_general->value < $current_general->high) )) ){
                        $order = new StockOrder([
                            "order_type" => StockOrder::DL0,
                            "deal_type" => StockOrder::SHORT_SELL,
                            "date" => $stockPrice->date,
                            "tlong" => $stockPrice->tlong,
                            "code" => $stockPrice->code,
                            "qty" => ceil(100/$stockPrice->current_price),
                            "sell" => $stockPrice->best_bid_price,
                            "closed" => false
                        ]);

                        $order->save();

                        Log::debug("{$stockPrice->stock_time["hours"]}:{$stockPrice->stock_time["minutes"]}:  [{$order->id}] Short sell {$stockPrice->code} at {$stockPrice->best_bid_price}");
                        return;
                    }


                }
            }


        } else {
            $this->closeOrder($stockPrice, $unclosed_order, $generalStock, $yesterdayGeneral);
        }


    }

    /**
     * @param StockPrice $stockPrice
     * @param StockOrder $unclosed_order
     * @param GeneralStock $generalStock
     * @param GeneralStock|null $yesterdayGeneral
     */
    public function closeOrder(StockPrice $stockPrice, StockOrder $unclosed_order, GeneralStock $generalStock, GeneralStock $yesterdayGeneral = null)
    {
        $current_general = GeneralPrice::where("date", $stockPrice->date)->where("tlong", "<=", $stockPrice->tlong)->orderByDesc("tlong")->first();

        if(!$current_general) return;

        $unclosed_order->buy = $stockPrice->current_price;

        //1. Current profit is greater or equal 2%
        //2. Current profit is greater or equal 1.5% and current price is greater than 50
        //2. Current profit is greater or equal 1.2% and current price is greater than 100
        if (($unclosed_order->profit_percent >= 0.5)
        ) {
            $unclosed_order->close_deal($stockPrice);
            Log::debug("{$stockPrice->stock_time["hours"]}:{$stockPrice->stock_time["minutes"]}:  [{$unclosed_order->id}] GAIN {$stockPrice->code} at {$stockPrice->current_price} | profit: {$current_profit_percent}");
            return;
        }

        if (
            /*($stockPrice->current_price >= $unclosed_order->sell)
            && $stockPrice->current_price >= $stockPrice->high
            && ($generalStock->general_start <= $yesterdayGeneral->today_final)*/

            ($generalStock->general_start > $yesterdayGeneral->today_final &&
            $current_general->value < $current_general->high &&
            $stockPrice->current_price_range > $stockPrice->yesterday_final*1.03) ||

            ($current_general->value >= $current_general->high &&
            $stockPrice->current_price > $unclosed_order->sell)

        ) {

            $unclosed_order->close_deal($stockPrice);

            Log::debug("{$stockPrice->stock_time["hours"]}:{$stockPrice->stock_time["minutes"]}:  [{$unclosed_order->id}] PROFIT LOSS {$stockPrice->code} at {$stockPrice->current_price} | profit: {$current_profit_percent}");
            return;
        }


        //close all remain orders
        if ($stockPrice->stock_time["hours"] == 12 && $stockPrice->stock_time["minutes"] >= 30 || $stockPrice->stock_time["hours"] > 12) {
            $unclosed_order->close_deal($stockPrice);
            Log::debug("{$stockPrice->stock_time["hours"]}:{$stockPrice->stock_time["minutes"]}:  [{$unclosed_order->id}] CLEAN {$stockPrice->code} at {$stockPrice->current_price} | profit: {$current_profit_percent}");
            return;
        }
    }

}


