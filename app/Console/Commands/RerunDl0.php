<?php

namespace App\Console\Commands;

use App\Crawler\StockHelper;
use App\Holiday;
use App\Jobs\Rerun\Dl0;
use App\StockOrder;
use App\StockPrice;
use App\VendorOrder;
use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;


class RerunDl0 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rerun:dl0 {type} {start?} {end?} {code?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $start = $this->argument("start");
        $end = $this->argument("end");
        $code = $this->argument("code");
        $type = $this->argument("type");

        $dates = [];

        if(!$start && !$end){
            $dates = $this->getDates();
        }
        elseif ($start && !$end){
            $dates = [$start];
        }
        elseif($start && $end){
            $dates = $this->getDates($start, $end);
        }


        foreach($dates as $filter_date){
            $d = date_create($filter_date);

            $h = Holiday::whereRaw("DATE_FORMAT(date, '%Y') =  {$d->format('Y')}")->get()->toArray();
            $holiday = array_reduce($h, function ($t, $e){
                $t[] = $e['date'];
                return $t;
            }, []);

            //If weekend or holiday
            if ($d->format("N") >= 6 || in_array($d->format("Y-m-d"), $holiday)){
                Log::debug("Today is off");
                continue;
            }

            StockHelper::loadGeneralPrices($filter_date);

            /**
             *
             */

            if($code){
                echo "Attempt to run DL0 on {$filter_date} for {$code}\n";

                if($type == 'real')
                    VendorOrder::where("date", $filter_date)->where("order_type", StockOrder::DL0)->where("code", $code)->delete();
                else
                    StockOrder::where("date", $filter_date)->where("order_type", StockOrder::DL0)->where("code", $code)->delete();


                Dl0::dispatch($code, $filter_date, $type)->onQueue("high");

                //$stockOrders = StockOrder::where("date", $filter_date)->where("code", $code)->get();
                //$this->summary($stockOrders);
            }
            else{

                echo "Attempt to run DL0 on {$filter_date}\n";

                if($type == 'real')
                    VendorOrder::where("date", $filter_date)->where("order_type", StockOrder::DL0)->delete();
                else
                    StockOrder::where("date", $filter_date)->where("order_type", StockOrder::DL0)->delete();

                //Get DL0 stocks

                $stocks = $stocks = DB::table("dl")
                    ->select("code")
                    ->whereRaw("dl.agency IS NOT NULL")
                    ->where("dl.final", "<", 200)
                    ->where("dl.final", ">", 10)
                    ->whereIn("date", [
                            StockHelper::previousDay($filter_date),
                            StockHelper::previousDay(StockHelper::previousDay($filter_date)),
                        ]
                    )
                    ->orderByDesc("dl.date")
                    ->groupBy("code")
                    ->get();


                foreach ($stocks as $stock){
                    $log = "Rerunning Stocks: {$stock->code} on {$filter_date}";
                    Log::info($log);

                    Dl0::dispatch($stock->code, $filter_date, $type)->onQueue("high");


                }

                //$stockOrders = StockOrder::where("date", $filter_date)->get();
                //$this->summary($stockOrders);

            }
        }



        return 0;
    }

    function summary($stockOrders){
        if($stockOrders){
            $gain = 0;
            $loss = 0;
            $total_fee = 0;
            $total_tax = 0;
            $total = 0;
            foreach ($stockOrders as $stockOrder){
                if($stockOrder->profit > 0){
                    $gain += $stockOrder->profit;
                }
                else{
                    $loss += $stockOrder->profit;
                }
                $total += $stockOrder->profit;
                $total_fee += $stockOrder->fee;
                $total_tax += $stockOrder->tax;
            }

            echo "TOTAL: {$total} | GAIN: {$gain} | LOSS: {$loss} | TAX: {$total_tax} | FEE: {$total_fee}\n";
        }
    }

    function getDates($start = null, $end = null, $format = 'Y-m-d'): array
    {

        if(!$start && !$end){
            $end = date("Y-m-d");
            $startObj = new DateTime();
            $startObj->modify("-1 months");

            $start = $startObj->format("Y-m-d");
        }


        // Declare an empty array
        $array = array();

        // Variable that store the date interval
        // of period 1 day
        $interval = new DateInterval('P1D');

        try {
            $realEnd = new DateTime($end);
        } catch (\Exception $e) {
            return [];
        }
        $realEnd->add($interval);

        try {
            $period = new DatePeriod(new DateTime($start), $interval, $realEnd);
        } catch (\Exception $e) {
            return [];
        }

        // Use loop to store date into array
        foreach($period as $date) {
            $array[] = $date->format($format);
        }

        // Return the array elements
        return $array;
    }
}
