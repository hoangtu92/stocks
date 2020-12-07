<?php

namespace App\Console\Commands;

use App\Crawler\StockHelper;
use App\Holiday;
use App\Jobs\Trading\ShortSell0;
use App\Jobs\Trading\TickShortSell0;
use App\StockOrder;
use App\StockPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RerunDl0 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rerun:dl0 {filter_date?} {code?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $filter_date;

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

        Redis::flushall();
        $this->filter_date = $this->argument("filter_date");
        if(!$this->filter_date)
            $this->filter_date = date("Y-m-d");

        $d = date_create($this->filter_date);

        $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  {$d->format('Y')}")->get()->toArray();
        $holiday = array_reduce($h, function ($t, $e){
            $t[] = $e['date'];
            return $t;
        }, []);

        //If weekend or holiday
        if ($d->format("N") >= 6 || in_array($d->format("Y-m-d"), $holiday)){
            Log::debug("Today is off\n");
            return false;
        }

        StockHelper::loadGeneralPrices($this->filter_date);

        /**
         *
         */

        if($this->argument("code")){
            $code = $this->argument("code");

            echo "Attempt to run DL0 on {$this->filter_date} for {$code}\n";
            StockOrder::where("date", $this->filter_date)->where("order_type", StockOrder::DL0)->where("code", $code)->delete();

            $this->callback($code);

        }
        else{

            echo "Attempt to run DL0 on {$this->filter_date}\n";
            StockOrder::where("date", $this->filter_date)->where("order_type", StockOrder::DL0)->delete();

            //Get DL0 stocks
            $stocks = StockHelper::getDL0Stocks($this->filter_date);

            foreach ($stocks as $stock){
                if((bool) Redis::get("STOP_RERUN")) break;
                $this->callback($stock->code);
            }
        }

        return 0;
    }

    function callback($code){
        $stockPrices = StockPrice::where("code", $code)
            ->where("date", $this->filter_date)
            ->orderBy("tlong", "asc")->get();

        foreach($stockPrices as $stockPrice){
            #if((bool) Redis::get("STOP_RERUN")) break;
            TickShortSell0::dispatchNow($stockPrice);
        }
    }
}
