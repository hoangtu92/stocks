<?php

namespace App\Console\Commands;

use App\Crawler\StockHelper;
use App\Jobs\CrawlYahooPrice;
use App\StockPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrawlYahooStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:yahoo {code?}';

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

        $code = $this->argument("code");

        if($code){
            StockPrice::where("code", $code)->where("date", date("Y-m-d"))->delete();
            CrawlYahooPrice::dispatch($code)->onQueue("high");
            echo "Crawling job for {$code} queued\n";
        }
        else{
            $filter_date = date("Y-m-d");

            StockPrice::where("date", $filter_date)->delete();

            $stocks = DB::table("dl")
                ->select("code")
                ->whereRaw("dl.agency IS NOT NULL")
                ->where("dl.final", "<", 200)
                ->where("dl.final", ">", 10)
                ->whereIn("date", [$filter_date, StockHelper::previousDay(StockHelper::previousDay($filter_date))])
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
