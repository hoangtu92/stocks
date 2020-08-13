<?php

namespace App\Http\Controllers;

use App\Arav;
use App\Crawler\MisTwse;
use App\Crawler\Tpex;
use App\Crawler\Twse;
use App\Dl;
use App\Order;
use App\Stock;
use Goutte\Client;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    private $filter = [];
    private $filter2 = [];
    private $range_filter = 9.5;
    private $vol_filter = 1000;

    private $today;

    public function __construct(){
        $this->today = date_create(now());
    }

    private function format_number($value){
        return floatval(preg_replace("/[\,]/", "", $value));
    }

    public function toTable($data, $mapping_label){

        if(empty($data)) return;

        print "<table border='1' cellpadding='5' cellspacing='0'>
<thead>
<tr>";


        foreach ($data[array_keys($data)[0]] as $key => $value){
            print "<th style='text-transform: uppercase'>{$mapping_label[$key]}<br>{$key}</th>";
        }
        print "</tr>
</thead>
<tbody>";


        foreach($data as $tr){
            print "<tr>";

            foreach($tr as $key=> $td){

                if($key == "range") $td .= "%";
                print "<td>{$td}</td>";
            }
            print "</tr>";
        }

        print "</tbody></table>";
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


    public function dl($filter_date){
        $TWO = new Tpex();

        $two_data = $TWO->get($filter_date);

        Dl::where("date", $filter_date)->delete();


        $date = date_create($filter_date);
        $year = $date->format("Y");
        $month = $date->format("m");
        $day = $date->format("d");

        $tw_year = $year - 1911;

        $result_data = [];

        $client = new Client();
        $crawler = $client->request("GET", "https://www.tpex.org.tw/web/stock/trading/intraday_trading/intraday_trading_list_print.php?l=zh-tw&d={$tw_year}/{$month}/{$day}&stock_code=&s=0,asc,1");
        $table = $crawler->filter("table")->last();

        $table->filter("tbody > tr")->each(function($tr){
            $tr->filter("td")->each(function ($td, $i){

                if($i == 0) {
                    $this->filter2[] = $td->text();
                }
            });
        });



        foreach ($two_data as $i => $data){

            //Stock info
            $stock = Stock::where("code", $data[0])->first();

            if(!$stock) Stock::create([
                "code" => $data[0],
                "name" => $data[1],
                "type" => Stock::OTC,
            ]);

            $d = [
                'date'  => $filter_date,
                'code' => $data[0],
                'final' => $this->format_number($data[2]),
                'range' => $this->format_number($data[3]),
                'vol' => $this->format_number($data[8])
            ];


            $v2 = $d["final"];
            $v3 = $d["range"];
            $d["range"] = $v2 == $v3 ? 0 : round((($v2 - ($v2-$v3))/($v2-$v3) )*100, 2);

            $d["vol"] = round($d["vol"]/1000, 0);

            if($d['range'] >= $this->range_filter && $d["vol"] > $this->vol_filter && strlen($d["code"]) <= 4 && in_array($d["code"], $this->filter2)) {
                $result_data[$d["code"]] = $d;

                $dl = Dl::where("code", $d["code"])->where("date", $filter_date)->first();
                if($dl){
                    $dl->update($d);
                }
                else{
                    Dl::create($d);
                }
            };

        }


        $crawler2 = $client->request("GET", "https://www.twse.com.tw/exchangeReport/TWTB4U?response=html&date={$year}{$month}{$day}&selectType=All");
        $table = $crawler2->filter("table")->last();

        $table->filter("tbody > tr")->each(function($tr){
            $tr->filter("td")->each(function ($td, $i){

                if($i == 0) {
                    $this->filter[] = $td->text();
                }
            });
        });

        $twse = new Twse();
        $twse_data = $twse->get($filter_date);
        foreach ($twse_data as $i => $data){

            //Stock info
            $stock = Stock::where("code", $data[0])->first();

            if(!$stock) Stock::create([
                "code" => $data[0],
                "name" => $data[1],
                "type" => Stock::TSE,
            ]);

            if(strlen($data[0]) > 4) continue;

            $d = [
                'date'  => $filter_date,
                'code' => $data[0],
                'final' => $this->format_number($data[8]),
                'range' => $this->format_number($data[9]),
                'vol' => $this->format_number($data[3])

            ];


            $v8 = $d["final"];
            $v9 = strip_tags($data['9']);
            $v10 = $v9 == '-' ? -$this->format_number($data[10]) : $this->format_number($data[10]) ;

            $d["range"] = $v8 == $v10 ? 0 : round((($v8 - ($v8-$v10))/($v8-$v10))*100, 2);

            if($d['range'] >= $this->range_filter && $d["vol"] > $this->vol_filter && strlen($d["code"]) <= 4 && in_array($d["code"], $this->filter)) {

                $result_data[$d["code"]] = $d;

                $dl = Dl::where("code", $d["code"])->where("date", $filter_date)->first();
                if($dl){
                    $dl->update($d);
                }
                else{
                    Dl::create($d);
                }
            }


        }



        return $result_data;
    }

    private function getPreviousDayDL($d, $filter_date){
        return Dl::where("code", $d["code"])->where("date", $this->previousDay($filter_date))->first();
    }


    public function arav($filter_date){
        $TWO = new Tpex();

        Arav::where("date", $filter_date)->delete();

        $two_data = $TWO->get($filter_date);


        $arav_data = [];
        foreach ($two_data as $i => $data){

            //Stock info
            $stock = Stock::where("code", $data[0])->first();

            if(!$stock) Stock::create([
                "code" => $data[0],
                "name" => $data[1],
                "type" => Stock::OTC,
            ]);

            if(strlen($data[0]) > 4) continue;

            $d = [
                'date'  => $filter_date,
                'code' => $data[0],
                'final' => $this->format_number($data[2]),
                'price_range' => $this->format_number($data[3]),
                'start' => $this->format_number($data[4]),
                'max' => $this->format_number($data[5]),
                'lowest' => $this->format_number($data[6])

            ];

            $dl = $this->getPreviousDayDL($d, $filter_date);
            if(!$dl) continue;

            $arav_data[] = $d;

            $arav = Arav::where("code", $d["code"])->where("date", $filter_date)->first();
            if($arav){
                $arav->update($d);
            }
            else{
                Arav::create($d);
            }
        }

        $twse = new Twse();
        $twse_data = $twse->get($filter_date);
        foreach ($twse_data as $i => $data){

            //Stock info
            $stock = Stock::where("code", $data[0])->first();

            if(!$stock) Stock::create([
                "code" => $data[0],
                "name" => $data[1],
                "type" => Stock::OTC,
            ]);

            if(strlen($data[0]) > 4) continue;

            $d = [
                'date'  => $filter_date,
                'code' => $data[0],
                'start' => $this->format_number($data[5]),
                'max' => $this->format_number($data[6]),
                'lowest' => $this->format_number($data[7]),
                'final' => $this->format_number($data[8]),
                'price_range' => $this->format_number($data[10])

            ];

            $dl = $this->getPreviousDayDL($d, $filter_date);
            if(!$dl) continue;

            $arav_data[] = $d;

            $arav = Arav::where("code", $d["code"])->where("date", $filter_date)->first();
            if($arav){
                $arav->update($d);
            }
            else{
                Arav::create($d);
            }
        }


        return $arav_data;
    }



    public function getDl($filter_date){
        $mapping_label = [
            "date" => "漲停日",
            "code" => "Code",
            "name" => "股票代號/名稱",
            "final" => "成交價",
            "range" => "漲跌幅",
            "vol" => "成交張數"
        ];

        $result_data = $this->dl($filter_date);

        $this->toTable($result_data, $mapping_label);
    }

    public function getArav($filter_date){
        $mapping_label = [
            "date" => "漲停日",
            "code" => "Code",
            "name" => "股票代號/名稱",
            "final" => "成交價",
            "start" => "最低",
            "max" => "最高",
            "lowest" => "最低",
            "price_range" => "漲跌幅",
        ];

        $arav_data = $this->arav($filter_date);

        $this->toTable($arav_data, $mapping_label);
    }


    public function crawlDataByDate($date_str){

        $today_date = getdate(strtotime($date_str));

        //Exclude weekend
        if($today_date["wday"] > 0 && $today_date["wday"] < 6){

            $this->dl($date_str);

            $next_day = $this->nextDay($date_str);

            $this->arav($next_day);

            $this->data($date_str);
        }


    }

    public function order(){
        $filter_date = $this->today->format("Y-m-d");
        $data = DB::table("orders")
            ->join("stocks", "stocks.code", "=", "orders.code")
            ->join("dl", "dl.code", "=", "orders.code")
            ->selectRaw(DB::raw("orders.date, stocks.code, stocks.name, dl.final, dl.range, dl.vol, orders.start as order_start"))
            ->where("orders.date", $this->previousDay($filter_date))
            ->get()
            ->toArray();

        $this->toTable($data, [
            "date" => "漲停日",
            "code" => "Code",
            "name" => "股票代號/名稱",

            "final" => "成交價",
            "range" => "漲跌幅",
            "vol" => "成交張數",

            "order_start" => "開盤價"
        ]);
    }

    public function crawlOrderByDate($filter_date = null){
        if (!$filter_date) $filter_date = $this->today->format("Y-m-d");

        Order::where("date", $filter_date)->delete();

        //get today dl value
        $today_dl = DB::table("dl")
            ->join("stocks", "stocks.code", "=", "dl.code")
            ->addSelect(DB::raw("dl.*, stocks.type as type, stocks.name as name"))
            ->where("date", $filter_date)
            ->get();

        $misTwse = new MisTwse();

        foreach ($today_dl as $dl){
            $data = $misTwse->get($dl->type, $dl->code);
            if(isset($data[0])){
                $order = Order::where("code", $dl->code)->where("date", $filter_date)->first();

                if($order){
                    $order->update([
                        "start" => $this->format_number($data[0]->o)
                    ]);
                }
                else{
                    Order::create([
                        "code" => $dl->code,
                        "date" => $filter_date,
                        "start" => $this->format_number($data[0]->o)
                    ]);
                }
            }
        }

        $data = DB::table("orders")
            ->join("stocks", "stocks.code", "=", "orders.code")
            ->join("dl", "dl.code", "=", "orders.code")
            ->selectRaw(DB::raw("dl.date, stocks.code, stocks.name, dl.final, dl.range, dl.vol, orders.start as order_start"))
            ->where("orders.date", $filter_date)
            ->get()
            ->toArray();

        $this->toTable($data, [
            "date" => "漲停日",
            "code" => "Code",
            "name" => "股票代號/名稱",

            "final" => "成交價",
            "range" => "漲跌幅",
            "vol" => "成交張數",

            "order_start" => "開盤價"
        ]);

    }

    public function crawlOrderToday(){
        $this->crawlOrderByDate($this->previousDay($this->today->format("Y-m-d")));
    }

    public function data($date = null){


        $query = DB::table("dl")
            ->leftJoin("aravs", function ($join){
                $join->on("dl.code", "=", "aravs.code")->whereRaw(DB::raw("((DAYOFWEEK(dl.date) < 6 AND DAYOFWEEK(dl.date) > 1 AND DATEDIFF(aravs.date, dl.date) = 1)
                OR (DAYOFWEEK(dl.date) = 6 AND DATEDIFF(aravs.date, dl.date) = 3 ))"));
            })
            ->join("stocks", "stocks.code", "=", "dl.code")
            ->select("dl.date")
            ->addSelect("dl.code")
            ->addSelect(DB::raw("stocks.name as name"))
            ->addSelect("dl.final")
            ->addSelect("range")
            ->addSelect("vol")

            ->addSelect(DB::raw("aravs.date as arav_date"))
            ->addSelect("start")
            ->addSelect("max")
            ->addSelect("lowest")
            ->addSelect(DB::raw("aravs.final as arav_final"))
            ->addSelect("price_range")

            ->orderBy("dl.date", "asc")
            ->orderBy("dl.final", "desc");

        if($date){
            $query->where("dl.date", $date);
        }

        $list_dl = $query->get()->toArray();

        $mapping_label = [
            "date" => "漲停日",
            "code" => "Code",
            "name" => "股票代號/名稱",
            "final" => "成交價",
            "range" => "漲跌幅",
            "vol" => "成交張數",


            "arav_date" => "ARAV DATE",
            "start" => "最低",
            "max" => "最高",
            "lowest" => "最低",
            "arav_final" => "成交價",
            "price_range" => "漲跌幅",
        ];

        $this->toTable($list_dl, $mapping_label);
    }
}
