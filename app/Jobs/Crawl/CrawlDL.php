<?php

namespace App\Jobs\Crawl;

use App\Crawler\DLExcludeFilter;
use App\Crawler\DLIncludeFilter;
use App\Crawler\StockHelper;
use App\Crawler\Tpex;
use App\Crawler\Twse;
use App\Dl;
use App\Dl2;
use App\Stock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CrawlDL implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 1;
    protected $filter_date;

    /**
     * Create a new job instance.
     *
     * @param $filter_date
     */
    public function __construct($filter_date = null)
    {
        if(!$filter_date){
            $filter_date = date("Y-m-d");
        }
        //
        $this->filter_date = $filter_date;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $TWO = new Tpex();

        $two_data = $TWO->get($this->filter_date);

        //Dl::where("dl_date", $filter_date)->delete();

        $excludeFilter = new DLExcludeFilter($this->filter_date);
        $includeFilter = new DLIncludeFilter($this->filter_date);

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
                'date'  => StockHelper::nextDay($this->filter_date),
                'dl_date'  => $this->filter_date,
                'code' => $data[0],
                'final' => StockHelper::format_number($data[2]),
                'range' => StockHelper::format_number($data[3]),
                'vol' => StockHelper::format_number($data[8]),
                'agency' => ''
            ];

            $v2 = $d["final"];
            $v3 = $d["range"];
            $d["range"] = $v2 == $v3 ? 0 : round((($v2 - ($v2-$v3))/($v2-$v3) )*100, 2);

            $d["vol"] = round($d["vol"]/1000, 0);

            $appear_yesterday = Dl::where("code", $d["code"])->where("dl_date", StockHelper::previousDay($this->filter_date))->first();

            if($d["final"] >= 10
                && $d['range'] >= 9.5
                && ($d["vol"] > 1000 || $appear_yesterday)
                && strlen($d["code"]) <= 4
                && in_array($d["code"], $includeFilter->stockList)
                && !in_array($d["code"], $excludeFilter->stockList)
            ) {

                $result_data[$d["code"]] = $d;


                $dl = Dl::where("code", $d["code"])->where("dl_date", $this->filter_date)->first();
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
                    'date'  => $this->filter_date,
                    'code' => $data[0],
                    'final' => StockHelper::format_number($data[2]),
                ];

                $dl2 = Dl2::where("code", $dd["code"])->where("date", $this->filter_date)->first();
                $previous_day = Dl2::where("code", $dd["code"])->where("date", StockHelper::previousDay($this->filter_date))->first();
                $previous_2day = Dl2::where("code", $dd["code"])->where("date", StockHelper::previousDay(StockHelper::previousDay($this->filter_date)))->first();
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
        $twse_data = $twse->get($this->filter_date);
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
                'date'  => StockHelper::nextDay($this->filter_date),
                'dl_date'  => $this->filter_date,
                'code' => $data[0],
                'final' => StockHelper::format_number($data[8]),
                'range' => StockHelper::format_number($data[9]),
                'vol' => round(StockHelper::format_number($data[2])/1000, 2),
                'agency' => ''

            ];


            $v8 = $d["final"];
            $v9 = strip_tags($data['9']);
            $v10 = $v9 == '-' ? - StockHelper::format_number($data[10]) : StockHelper::format_number($data[10]) ;

            $d["range"] = $v8 == $v10 ? 0 : round((($v8 - ($v8-$v10))/($v8-$v10))*100, 2);

            $appear_yesterday = Dl::where("code", $d["code"])->where("dl_date", StockHelper::previousDay($this->filter_date))->first();

            if($d["final"] >= 10
                && $d['range'] >= 9.5
                && ($d["vol"] > 1000 || $appear_yesterday)
                && strlen($d["code"]) <= 4
                && in_array($d["code"], $includeFilter->stockList)
                && !in_array($d["code"], $excludeFilter->stockList)
            ) {

                $result_data[$d["code"]] = $d;

                $dl = Dl::where("code", $d["code"])->where("dl_date", $this->filter_date)->first();
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
                    'date'  => $this->filter_date,
                    'code' => $data[0],
                    'final' => StockHelper::format_number($data[8])
                ];

                $dl2 = Dl2::where("code", $dd["code"])->where("date", $this->filter_date)->first();
                $previous_day = Dl2::where("code", $dd["code"])->where("date", StockHelper::previousDay($this->filter_date))->first();
                $previous_2day = Dl2::where("code", $dd["code"])->where("date", StockHelper::previousDay(StockHelper::previousDay($this->filter_date)))->first();
                if(($previous_day && $previous_2day && $previous_day->final >= 0 && $previous_2day->final >=0)  || !$previous_day || !$previous_2day ){
                    if ($dl2) {
                        $dl2->update($dd);
                    } else {
                        Dl2::create($dd);
                    }
                }

            }

        }
    }
}
