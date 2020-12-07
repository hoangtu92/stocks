<?php

namespace App\Jobs\Crawl;

use App\Crawler\StockHelper;
use App\Holiday;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CrawlHoliday implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 1;
    protected $year;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($year = null)
    {
        //
        if(!$year) $year = date("Y");
        $this->year = $year;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        $data = [];
        $table = StockHelper::crawlGet("https://www.tradinghours.com/exchanges/twse/market-holidays/{$this->year}", "table.table");
        $table->filter("tr")->each(function ($tr, $i) {
            $tr->filter("td")->each(function ($td, $j) use ($i){
                $value = trim($td->text());
                if($j == 1)
                    $data[$i]["name"] = $value;
                if($j == 2){
                    $date = date_create_from_format("F j, Y", $value);
                    $data[$i]["date"] = $date->format("Y-m-d");
                }

            });
        });

        foreach ($data as $h){

            $holiday = Holiday::where("date", $h['date'])->first();

            if(!$holiday){
                $holiday = new Holiday($h);
                $holiday->save();
            }

        }
    }
}
