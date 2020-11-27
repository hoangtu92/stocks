<?php

namespace App\Http\Controllers;

use App\Agent;
use App\Arav;
use App\Crawler\CrawlHoliday;
use App\Crawler\DLExcludeFilter;
use App\Crawler\DLIncludeFilter;
use App\Crawler\CrawlAgency;
use App\Crawler\CrawlOrder;
use App\Crawler\Tpex;
use App\Crawler\CrawlLargeTradeRateSell;
use App\Crawler\Twse;
use App\Dl;
use App\Dl2;
use App\FailedCrawl;
use App\Holiday;
use App\Order;
use App\Stock;
use DateTime;
use DOMDocument;
use Goutte\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\DomCrawler\Field\InputFormField;

class StockController extends Controller
{
    private $range_filter = 9.5;
    private $vol_filter = 1000;
    private $client;

    private $today;

    public function __construct(){
        $this->today = date_create(now());
        $this->client = new Client();
        //nancyhsu0511@gmail.com
        //$data = ["email" => "kis77628@gmail.com", "password" => "ASDFvcx2z!"];
        $data = ["email" => "nancyhsu0511@gmail.com", "password" => "ASDFvcx2z!"];

        $crawler = $this->client->request("GET", "https://histock.tw/login");
        $form = $crawler->selectButton('登入')->form();

        $eventTarget = "bLogin";
        $eventArgument = "";

        $domdocument = new DOMDocument;

        $ff = $domdocument->createElement('input');
        $ff->setAttribute('name', '__EVENTTARGET');
        $ff->setAttribute('value', $eventTarget);
        $formfield = new InputFormField($ff);

        $ff = $domdocument->createElement('input');
        $ff->setAttribute('name', '__EVENTARGUMENT');
        $ff->setAttribute('value', $eventArgument);
        $formfield2 = new InputFormField($ff);

        $form->set($formfield);
        $form->set($formfield2);

        $this->client->submit($form, $data);

        //echo $crawler->outerHtml();

    }

    private function getStockAgent($stockCode, $date, $filter){
        $hiStock = new CrawlAgency($this->client);
        return $hiStock->get($stockCode, $date, $filter);
    }

    public function dl($filter_date){
        $TWO = new Tpex();

        $two_data = $TWO->get($filter_date);

        //Dl::where("dl_date", $filter_date)->delete();

        $excludeFilter = new DLExcludeFilter($filter_date);
        $includeFilter = new DLIncludeFilter($filter_date);

        $discarded_stocks = [];

        foreach ($two_data as $i => $data){

            //Stock info
            $stock = Stock::where("code", $data[0])->first();

            if(!$stock) Stock::create([
                "code" => $data[0],
                "name" => $data[1],
                "type" => Stock::OTC,
            ]);

            $d = [
                'date'  => $this->nextDay($filter_date),
                'dl_date'  => $filter_date,
                'code' => $data[0],
                'final' => $this->format_number($data[2]),
                'range' => $this->format_number($data[3]),
                'vol' => $this->format_number($data[8]),
                'agency' => ''
            ];



            $v2 = $d["final"];
            $v3 = $d["range"];
            $d["range"] = $v2 == $v3 ? 0 : round((($v2 - ($v2-$v3))/($v2-$v3) )*100, 2);

            $d["vol"] = round($d["vol"]/1000, 0);

            $appear_yesterday = Dl::where("code", $d["code"])->where("dl_date", $this->previousDay($filter_date))->first();

            if($d["final"] >= 10
                && $d['range'] >= $this->range_filter
                && ($d["vol"] > $this->vol_filter || $appear_yesterday)
                && strlen($d["code"]) <= 4
                && in_array($d["code"], $includeFilter->stockList)
                && !in_array($d["code"], $excludeFilter->stockList)
            ) {

                $result_data[$d["code"]] = $d;


                $dl = Dl::where("code", $d["code"])->where("dl_date", $filter_date)->first();
                if($dl){

                    $dl->update($d);
                }
                else{
                    Dl::create($d);
                }

            }else{
                $discarded_stocks[] = $d;
            }

            /**
             * Save dl2 stocks
             */
            if(strlen($d["code"]) <= 4
                && $d['final'] > 15
                && $d['final'] < 170
                && in_array($d["code"], $includeFilter->stockList)
                && !in_array($d["code"], $excludeFilter->stockList)){

                $dd = [
                    'date'  => $filter_date,
                    'code' => $data[0],
                    'final' => $this->format_number($data[2]),
                ];

                $dl2 = Dl2::where("code", $dd["code"])->where("date", $filter_date)->first();
                $previous_day = Dl2::where("code", $dd["code"])->where("date", $this->previousDay($filter_date))->first();
                $previous_2day = Dl2::where("code", $dd["code"])->where("date", $this->previousDay($this->previousDay($filter_date)))->first();
                if(($previous_day && $previous_2day && $previous_day->final >= 0 && $previous_2day->final>=0)  || !$previous_day || !$previous_2day ){
                    if ($dl2) {
                        $dl2->update($dd);
                    } else {
                        Dl2::create($dd);
                    }
                }

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
                "type" => Stock::TSE,
            ]);

            if(strlen($data[0]) > 4) continue;

            $d = [
                'date'  => $this->nextDay($filter_date),
                'dl_date'  => $filter_date,
                'code' => $data[0],
                'final' => $this->format_number($data[8]),
                'range' => $this->format_number($data[9]),
                'vol' => round($this->format_number($data[2])/1000, 2),
                'agency' => ''

            ];


            $v8 = $d["final"];
            $v9 = strip_tags($data['9']);
            $v10 = $v9 == '-' ? -$this->format_number($data[10]) : $this->format_number($data[10]) ;

            $d["range"] = $v8 == $v10 ? 0 : round((($v8 - ($v8-$v10))/($v8-$v10))*100, 2);

            $appear_yesterday = Dl::where("code", $d["code"])->where("dl_date", $this->previousDay($filter_date))->first();

            if($d["final"] >= 10
                && $d['range'] >= $this->range_filter
                && ($d["vol"] > $this->vol_filter || $appear_yesterday)
                && strlen($d["code"]) <= 4
                && in_array($d["code"], $includeFilter->stockList)
                && !in_array($d["code"], $excludeFilter->stockList)
            ) {

                $result_data[$d["code"]] = $d;

                $dl = Dl::where("code", $d["code"])->where("dl_date", $filter_date)->first();
                if ($dl) {
                    $dl->update($d);
                } else {
                    Dl::create($d);
                }

            }else{
                $discarded_stocks[] = $d;
            }

            /**
             * Save dl2 stocks
             */
            if(strlen($d["code"]) <= 4
            && $d['final'] > 15
            && $d['final'] < 170
            && in_array($d["code"], $includeFilter->stockList)
            && !in_array($d["code"], $excludeFilter->stockList)){

                $dd = [
                    'date'  => $filter_date,
                    'code' => $data[0],
                    'final' => $this->format_number($data[8])
                ];

                $dl2 = Dl2::where("code", $dd["code"])->where("date", $filter_date)->first();
                $previous_day = Dl2::where("code", $dd["code"])->where("date", $this->previousDay($filter_date))->first();
                $previous_2day = Dl2::where("code", $dd["code"])->where("date", $this->previousDay($this->previousDay($filter_date)))->first();
                if(($previous_day && $previous_2day && $previous_day->final >= 0 && $previous_2day->final >=0)  || !$previous_day || !$previous_2day ){
                    if ($dl2) {
                        $dl2->update($dd);
                    } else {
                        Dl2::create($dd);
                    }
                }

            }

        }

        //Log::info(json_encode($discarded_stocks));

        return $result_data;
    }

    private function agency($filter_date = null){

        $agents = Agent::all("name")->toArray();
        $agents = array_reduce($agents, function ($t, $e){
            $t[] = $e["name"];
            return $t;
        }, []);

        $dls = Dl::where("dl_date", $filter_date)->get();

        $result = [];

        foreach ($dls as $dl){
            $stockAgents = $this->getStockAgent($dl->code, $filter_date, $agents);
            $result[] = $stockAgents;
            $dl->update($stockAgents);
        }

        return $result;

    }

    private function xz($filter_date){
        $r = new CrawlLargeTradeRateSell();

        $dls = Dl::join("stocks", "stocks.code", "=", "dl.code")
            ->select(DB::raw("dl.*, stocks.type"))
            ->where("dl_date", $filter_date)->get();

        $result_data = [];

        foreach ($dls as $dl){

            $result = $dl->type == "otc" ? $r->getTpex($filter_date, $dl->code) : $r->getTwse($filter_date, $dl->code);
            if($result){
                $dl->large_trade = $result["x"];
                $dl->dynamic_rate_sell = $result["z"];

                $result_data[] = $result;

                $dl->save();
            }
        }

        return $result_data;

    }

    private function getPreviousDayDL($d, $filter_date){
        return Dl::where("code", $d["code"])->where("dl_date", $this->previousDay($filter_date))->first();
    }


    public function arav($filter_date){
        $TWO = new Tpex();

        # Arav::where("date", $filter_date)->delete();

        $two_data = $TWO->get($filter_date);

        $arav_data = [];
        foreach ($two_data as $i => $data){

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

    private function getOrder($filter_date, $key){

        //get today dl value
        $today_dl = DB::table("dl")
            ->join("stocks", "stocks.code", "=", "dl.code")
            ->addSelect(DB::raw("dl.*, stocks.type as type, stocks.name as name"))
            ->where("dl_date", $filter_date)
            ->get();



        $result = [];

        foreach ($today_dl as $dl){
            $data = new CrawlOrder($dl->type, $dl->code);
            if(isset($data->{$key}) && $data->{$key}){

                $result[] = $data;

                $order = Order::where("code", $dl->code)->where("date", $filter_date)->first();

                if(!$order){
                    $order = new Order([
                        "code" => $dl->code,
                        "date" => $filter_date
                    ]);
                }

                $order->{$key} = $data->{$key};
                $order->save();
            }
            else{

            }
        }

        return $result;
    }


    public function crawlDl($filter_date = null){

        if(!$filter_date){
            $filter_date = date("Y-m-d");
        }

        Log::info("Crawling dl data for ".$filter_date);

        $result = $this->dl($filter_date);

        if(count($result) == 0){
            FailedCrawl::create([
                "action" => "crawl_dl",
                "failed_at" => now()
            ]);
        }

        return redirect(route("data", ["date" => $filter_date]));
    }

    public function crawlArav($filter_date = null){

        if(!$filter_date){
            $filter_date = $this->previousDay(date("Y-m-d"));
        }

        $d = date_create($filter_date);
        if($d->format("N") >= 6){
            $filter_date = $this->previousDay($filter_date);
        }

        Log::info("Crawling arav data for ".$filter_date);

        $result = $this->arav($filter_date);

        Log::info(json_encode($result));

        if(count($result) == 0){
            FailedCrawl::create([
                "action" => "crawl_arav",
                "failed_at" => now()
            ]);
        }

        return redirect(route("data", ["date" => $filter_date]));
    }

    public function crawlXZ($filter_date = null){
        if(!$filter_date){
            $filter_date = date("Y-m-d");
        }
        Log::info("Crawling XZ data for ".$filter_date);
        $result = $this->xz($filter_date);

        if(count($result) == 0){
            FailedCrawl::create([
                "action" => "crawl_xz",
                "failed_at" => now()
            ]);
        }

        //echo json_encode($result);

        return redirect(route("data", ["date" => $filter_date]));
    }

    public function crawlAgency($filter_date = null){
        if(!$filter_date){
            $filter_date = date("Y-m-d");
        }
        Log::info("Crawling Agency data for ".$filter_date);
        $result = $this->agency($filter_date);


        if(count($result) == 0){
            FailedCrawl::create([
                "action" => "crawl_agency",
                "failed_at" => now()
            ]);
        }
        return redirect(route("data", ["date" => $filter_date]));
    }


    public function reCrawlAgency(){
        $dls = Dl::whereRaw("agency = ''")->get();


        if($dls == NULL){
            return Redirect::back();
        }

        $agents = Agent::all("name")->toArray();
        $agents = array_reduce($agents, function ($t, $e){
            $t[] = $e["name"];
            return $t;
        }, []);

        foreach ($dls as $dl){

            $stockAgents = $this->getStockAgent($dl->code, $dl->dl_date, $agents);
            if($stockAgents == false){
                //Make it out of re crawl list
                $dl->update(['agency' => NULL]);
            }
            else{
                # Log::info("Re Crawl Agency for {$dl->code} {$dl->dl_date}");
                # Log::info($stockAgents);
                $dl->update($stockAgents);
            }


        }

        return Redirect::back();
    }

    public function crawlData($filter_date = null){

        if(!$filter_date){
            $filter_date = date("Y-m-d");
        }


        Log::info("Crawling past data for ".$filter_date);

        $today_date = getdate(strtotime($filter_date));

        //Exclude weekend
        if($today_date["wday"] > 0 && $today_date["wday"] < 6){

            $this->dl($filter_date);

            $this->arav($this->nextDay($filter_date));

            $this->xz($filter_date);
            $this->agency($filter_date);

        }

        return redirect(route("data", ["date" => $filter_date]));

    }


    public function crawlHoliday($year){

        if(!isset($year)) $year = date("Y");

        $crawler = new CrawlHoliday($year);

        foreach ($crawler->data as $h){

            $holiday = Holiday::where("date", $h['date'])->first();

            if(!$holiday){
                $holiday = new Holiday($h);
                $holiday->save();
            }

        }

    }

    public function dl0($filter_date = null){
        $now = new DateTime();

        if(!$filter_date)
            $filter_date = $now->format("Y-m-d");

        $includeFilter = new DLIncludeFilter($filter_date);
        $excludeFilter = new DLExcludeFilter($filter_date);

        //Get DL0 stocks
        $stocks = Dl::join("stocks", "stocks.code", "=", "dl.code")
            ->select("dl.date")
            ->addSelect("dl.dl_date")
            ->addSelect("dl.id")
            ->addSelect("dl.code")
            ->addSelect("dl.range")
            ->addSelect("dl.open")
            ->addSelect("dl.low")
            ->addSelect("dl.high")
            ->addSelect("dl.price_907")
            ->addSelect("stocks.type")
            ->where("dl.final", "<", 170)
            ->whereRaw("dl.agency IS NOT NULL")
            ->whereIn("dl.date", [
                $filter_date,
                $this->previousDay($filter_date),
                $this->previousDay($this->previousDay($filter_date)),
                $this->previousDay($this->previousDay($this->previousDay($filter_date))),
                $this->previousDay($this->previousDay($this->previousDay($this->previousDay($filter_date)))),
                $this->previousDay($this->previousDay($this->previousDay($this->previousDay($this->previousDay($filter_date))))),
            ])
            ->whereIn("dl.code", $includeFilter->stockList)
            ->whereNotIn("dl.code", $excludeFilter->stockList)
            ->get()->toArray();


        echo $this->toTable($stocks, [
           "date" => "Date",
           "code" => "Code",
           "range" => "Range",
           "open" => "Open",
           "low" => "Low",
           "high" => "High",
        ]);
    }

    public function cmoney($code, $filter_date = null){

        $referer = "http://www.cmoney.tw/notice/chart/stockchart.aspx?action=r&id={$code}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $referer);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $rr = curl_exec($ch);

        if(preg_match("/var ck = \"(.+)\";/", $rr, $match)){

            $ck = $match[1];

            $url = "http://www.cmoney.tw/notice/chart/stock-chart-service.ashx";
            $params = [
                "action" => "r",
                "id" => $code,
                "ck" => $ck,
                "_" => time()*1000
            ];

            if($filter_date){
                $params['date'] = $filter_date;
            }
            $headers = [
                "Connection" => "keep-alive",
                "Pragma" => "no-cache",
                "Cache-Control"=> "no-cache",
                "Accept" => "application/json, text/javascript, */*; q=0.01",
                "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36",
                "X-Requested-With" => "XMLHttpRequest",
                "Referer" => $referer,
                "Accept-Language" => "en-US,en;q=0.9,ko;q=0.8,zh-TW;q=0.7,zh;q=0.6,vi;q=0.5",
            ];

            $url = $url."?".http_build_query($params);

            echo $url;


            $cookies = array();
            foreach ($_COOKIE as $key => $value)
            {
                if ($key != 'Array')
                {
                    $cookies[] = $key . '=' . $value;
                }
            }
            //curl_setopt( $ch, CURLOPT_COOKIE, implode(';', $cookies) );
            //curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            //curl_setopt($ch, CURLOPT_USERAGENT , "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36");
            curl_setopt($ch, CURLOPT_HEADER, $headers);
            curl_setopt($ch, CURLOPT_URL, $url);
            //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $res = curl_exec($ch);

            /*if(curl_errno($ch)){
                echo "Error";
                #$res = curl_error($ch);
            }*/


            curl_close($ch);



            return $res;
        }





    }

}
