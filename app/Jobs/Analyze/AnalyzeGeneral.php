<?php

namespace App\Jobs\Analyze;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AnalyzeGeneral implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //


        //Count all the times that price reach higher points
        $high = DB::select("SELECT COUNT(*)  as high_count FROM (SELECT high
    FROM general_prices
	WHERE date = CURRENT_DATE 
    AND tlong <= UNIX_TIMESTAMP(CURRENT_TIMESTAMP)*1000
    GROUP by high) t");
        if($high){
            Redis::set("General:high_count", $high[0]->high_count);
        }

        //Count all the times that price reach lower points
        $low = DB::select("SELECT COUNT(*) as low_count FROM (SELECT low
    FROM general_prices
	where date = CURRENT_DATE
    AND tlong <= UNIX_TIMESTAMP(CURRENT_TIMESTAMP)*1000 
    GROUP by low) t;");

        if($low){
            Redis::set("General:low_count", $low[0]->low_count);
        }

        //Check current general trend within 5 mins is up or down
        $general_trend = DB::select("SELECT IF(s1.value > (SELECT value from general_prices s2 WHERE s1.date = s2.date AND s1.tlong - s2.tlong >= 600000 ORDER BY s2.tlong DESC limit 1), 'UP', 'DOWN') as trend
    FROM `general_prices` s1
    WHERE s1.date = CURRENT_DATE
    AND s1.tlong <= UNIX_TIMESTAMP(CURRENT_TIMESTAMP)*1000
    LIMIT 1");

        if($general_trend){
            Redis::set("General:trend", $general_trend[0]->trend);
        }


    }
}
