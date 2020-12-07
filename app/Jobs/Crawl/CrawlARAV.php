<?php

namespace App\Jobs\Crawl;

use App\Arav;
use App\Crawler\StockHelper;
use App\Crawler\Tpex;
use App\Crawler\Twse;
use App\Dl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CrawlARAV implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 1;
    protected $filter_date;

    /**
     * Create a new job instance.
     *
     * @param null $filter_date
     */
    public function __construct($filter_date = null)
    {
        //
        if(!$filter_date){
            $filter_date = date("Y-m-d");
        }
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

        # Arav::where("date", $filter_date)->delete();

        $two_data = $TWO->get($this->filter_date);

        $arav_data = [];
        foreach ($two_data as $i => $data){

            if(strlen($data[0]) > 4) continue;

            $d = [
                'date'  => $this->filter_date,
                'code' => $data[0],
                'final' => StockHelper::format_number($data[2]),
                'price_range' => StockHelper::format_number($data[3]),
                'start' => StockHelper::format_number($data[4]),
                'max' => StockHelper::format_number($data[5]),
                'lowest' => StockHelper::format_number($data[6])

            ];


            $dl = Dl::where("code", $d["code"])->where("date", $this->filter_date)->first();
            if(!$dl) continue;



            $arav_data[] = $d;

            $arav = Arav::where("code", $d["code"])->where("date", $this->filter_date)->first();
            if($arav){
                $arav->update($d);
            }
            else{
                Arav::create($d);
            }
        }

        $twse = new Twse();
        $twse_data = $twse->get($this->filter_date);
        foreach ($twse_data as $i => $data){

            if(strlen($data[0]) > 4) continue;

            $d = [
                'date'  => $this->filter_date,
                'code' => $data[0],
                'start' => StockHelper::format_number($data[5]),
                'max' => StockHelper::format_number($data[6]),
                'lowest' => StockHelper::format_number($data[7]),
                'final' => StockHelper::format_number($data[8]),
                'price_range' => StockHelper::format_number($data[10])

            ];

            $dl = Dl::where("code", $d["code"])->where("date", $this->filter_date)->first();
            if(!$dl) continue;

            $arav_data[] = $d;

            $arav = Arav::where("code", $d["code"])->where("date", $this->filter_date)->first();
            if($arav){
                $arav->update($d);
            }
            else{
                Arav::create($d);
            }
        }
    }
}
