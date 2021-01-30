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
use Symfony\Component\DomCrawler\Crawler;

class CrawlHoliday implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 1;
    protected $year;
    protected $data = [];
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
        $table = StockHelper::crawlGet("https://www.tradinghours.com/exchanges/twse/market-holidays", "table.table");
        $table->filter("tbody tr")->each(function ($tr, $i) {
            $tr->filter("td")->each(function ($td, $j) use ($i){
                $td->filter("i")->each(function(Crawler $crawler){
                   foreach($crawler as $node){
                       $node->parentNode->removeChild($node);
                   }
                });
                $value = trim($td->text());
                if($j == 0)
                    $this->data[$i]["name"] = $value;
                if($j == 0){
                    $date = date_create_from_format("l, F j, Y", $value);
                    $this->data[$i]["date"] = $date->format("Y-m-d");
                }
            });
        });

        foreach ($this->data as $h){

            $holiday = Holiday::where("date", $h['date'])->first();

            if(!$holiday){
                $holiday = new Holiday($h);
                $holiday->save();
            }

        }
    }
}
