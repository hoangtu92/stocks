<?php

namespace App\Console;

use App\Crawler\RealTime\RealtimeDL0;
use App\Crawler\RealTime\RealtimeGeneral;
use App\Crawler\RealTime\RealTimeDL1;
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

        /*$schedule->call(function () {
            $r = file_get_contents("http://dev.ml-codesign.com:8083/api/Vendor/login");
            Log::debug($r);
        })->dailyAt("08:30");

        $schedule->call(function () {
            $r = file_get_contents("http://dev.ml-codesign.com:8083/api/Vendor/logout");
            Log::debug($r);
        })->dailyAt("14:30");*/

        //Log::info("Every minute run");

        $schedule->call(function () {
            /**
             * Start to monitor stock data
             */
            Log::info("Start general realtime crawl");
            $realTime = new RealtimeGeneral();
            $realTime->monitor();


        })->name("get_general_realtime")->everyMinute()->between("9:00", "13:35")->runInBackground()->withoutOverlapping();


        $schedule->call(function () {
            /**
             * Start to monitor stock data
             */
            Log::info("Start dl0 realtime crawl");
            $realTime = new RealtimeDL0();
            $realTime->monitor();


        })->name("get_dl_0_realtime")->everyMinute()->between("9:00", "13:35")->runInBackground()->withoutOverlapping();



        $schedule->call(function () {
            /**
             * Start to monitor stock data
             */
            Log::info("Start dl1 realtime crawl");
            $realTime = new RealTimeDL1();
            $realTime->monitor();


        })->name("get_dl_1_realtime")->everyMinute()->between("9:00", "13:35")->runInBackground()->withoutOverlapping();


        $schedule->call(function (){

            file_get_contents(route('crawl_holiday'));

        })->name("holiday")->yearly();


        $schedule->call(function () {
            file_get_contents(route('crawl_dl'));
        })->dailyAt("17:05");

        $schedule->call(function () {
            file_get_contents(route('crawl_arav', ['date' => date("Y-m-d")]));
        })->dailyAt("17:10");

        $schedule->call(function () {
            file_get_contents(route('crawl_xz'));
        })->dailyAt("18:10");

        $schedule->call(function (){
            file_get_contents(route("re_crawl_agency"));
        })->everyMinute()->between("18:10", "18:30");


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
