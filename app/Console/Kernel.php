<?php

namespace App\Console;

use App\Crawler\Crawler;
use App\Crawler\RealTime;
use App\GeneralStock;
use DateTime;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];


    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        //Log::info("Every minute run");
        $schedule->call(function (){
            Log::info("rerawl agency task");
            $d = new DateTime();

            $p = new DateTime();
            $p->setTime(18, 12, 0);

            $t = new DateTime();
            $t->setTime(18, 30, 0);

            if($d >= $p && $d <= $t)
                file_get_contents(route("re_crawl_agency"));

        })->everyMinute()->between("18:12", "18:30");


        $schedule->call(function () {

            /**
             * Start to monitor stock data
             */
            Log::info("Start realtime crawl");
            $realTime = new RealTime();
            $realTime->monitor();

           /* $tmrGeneralProduct = GeneralStock::where("date", (new Crawler)->nextDay(date("Y-m-d")))->first();

            if(!$tmrGeneralProduct)
                $tmrGeneralProduct = new GeneralStock([
                    "date" => $this->nextDay(date("Y-m-d"))
                ]);

            $tmrGeneralProduct->save();*/


        })->name("realtime")->everyMinute()->between("9:00", "13:35")->runInBackground()->withoutOverlapping();


        $schedule->call(function () {
            file_get_contents(route('crawl_dl'));
        })->dailyAt("17:05");

        $schedule->call(function () {
            file_get_contents(route('crawl_arav', ['date' => date("Y-m-d")]));
        })->dailyAt("17:10");

        $schedule->call(function () {
            file_get_contents(route('crawl_xz'));
            file_get_contents(route('crawl_agency'));
        })->dailyAt("18:10");


    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
