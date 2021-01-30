<?php

namespace App\Console\Commands;

use App\Crawler\StockHelper;
use App\Jobs\CrawlYahooPrice;
use App\StockOrder;
use App\StockPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CrawlYahooStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:yahoo {filter_date?} {code?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl yahoo stock today';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        StockHelper::loadGeneralPrices();
        $filter_date = $this->argument("filter_date");
        if(!$filter_date) $filter_date = date("Y-m-d");


        $code = $this->argument("code");

        if($code){
            StockPrice::where("code", $code)->where("date", $filter_date)->delete();
            StockOrder::where("code", $code)->where("date", $filter_date)->delete();
            CrawlYahooPrice::dispatch($code)->onQueue("high");
            echo "Crawling job for {$code} queued\n";
        }
        else{

            $stocks = DB::table("dl")
                ->select("code")
                ->whereRaw("dl.agency IS NOT NULL")
                ->where("dl.final", "<", 200)
                ->where("dl.final", ">", 10)
                ->whereIn("date", [
                    $filter_date,
                    StockHelper::previousDay($filter_date),
                    StockHelper::previousDay(StockHelper::previousDay($filter_date)),
                    ]
                )
                ->orderByDesc("dl.date")
                ->groupBy("code")
                ->get();

            foreach ($stocks as $stock){
                CrawlYahooPrice::dispatch($stock->code)->onQueue("high");
                echo "Crawling job for {$stock->code} queued\n";
            }
        }

        return 0;
    }
}
