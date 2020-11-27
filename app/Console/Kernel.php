<?php

namespace App\Console;

use App\Crawler\Crawler;
use App\Crawler\RealTime\RealtimeDL0;
use App\Crawler\RealTime\RealtimeGeneral;
use App\Crawler\RealTime\RealTimeDL1;
use App\StockOrder;
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

        #Log::info("Every minute run");
        $crawler = new Crawler();
        $holiday = $crawler->getHoliday();


        /**
         * Crawl realtime data
         */
        $schedule->call(new RealtimeGeneral)->name("get_general_realtime")
            ->weekdays()
            ->when(function () use ($holiday) {
                return !in_array(date("Y-m-d"), $holiday);
            })
            ->everyMinute()
            ->between("9:00", "13:30")
            ->runInBackground()
            ->withoutOverlapping();
            //->emailOutputOnFailure('hoangtu92@live.com');

        /**
         * Crawl dl 1 real time
         */
        $schedule->call(new RealTimeDL1)->name("get_dl_1_realtime")
            ->weekdays()
            ->when(function () use ($holiday) {
                return !in_array(date("Y-m-d"), $holiday);
            })
            ->everyMinute()
            ->between("9:00", "13:30")
            ->runInBackground()
            ->withoutOverlapping();
            //->emailOutputOnFailure('hoangtu92@live.com');


        /**
         * Crawl dl0 real time
         */
        $schedule->call(new RealtimeDL0)->name("get_dl_0_realtime_xxx")
            ->weekdays()
            ->when(function () use ($holiday) {
                #Log::debug("Is not holiday: ".!in_array(date("Y-m-d"), $holiday));
                return !in_array(date("Y-m-d"), $holiday);
            })
            ->everyMinute()
            ->between("9:01", "13:30")
            ->runInBackground()
            ->withoutOverlapping();
        //->emailOutputOnFailure('hoangtu92@live.com');



        /**
         * Close all orders at 13:08
         */
        $schedule->call(function () {
            $orders = StockOrder::where("closed", false)->get();

            foreach ($orders as $order){
                $order->close_deal();
            }
        })->weekdays()
            ->when(function () use ($holiday) {
                return !in_array(date("Y-m-d"), $holiday);
            })
            ->at("13:08")
            ->runInBackground();


        /**
         * Crawl dl data
         */
        $schedule->call(function () {
            file_get_contents(route('crawl_dl'));
        })->weekdays()
            ->when(function () use ($holiday) {
                return !in_array(date("Y-m-d"), $holiday);
            })
            ->at("17:05")
            ->runInBackground();

        /**
         * Crawl arav data
         */
        $schedule->call(function () {
            file_get_contents(route('crawl_arav', ['date' => date("Y-m-d")]));
        })->weekdays()
            ->when(function () use ($holiday) {
                return !in_array(date("Y-m-d"), $holiday);
            })
            ->at("17:10")
            ->runInBackground();

        /**
         * Crawl xz and agency
         */
        $schedule->call(function () {
            file_get_contents(route('crawl_xz'));
            file_get_contents(route('crawl_agency'));
        })->weekdays()
            ->when(function () use ($holiday) {
                return !in_array(date("Y-m-d"), $holiday);
            })
            ->at("18:10")
            ->runInBackground();


        /**
         * Re crawl agency
         */
        $schedule->call(function (){
            file_get_contents(route("re_crawl_agency"));
        })->weekdays()
            ->when(function () use ($holiday) {
                return !in_array(date("Y-m-d"), $holiday);
            })
            ->everyMinute()
            ->between("18:30", "19:00")
            ->runInBackground();


        /**
         * Crawl holiday
         */
        $schedule->call(function (){
            file_get_contents(route('crawl_holiday'));
        })->name("holiday")
            ->weekdays()
            ->yearly()
            ->runInBackground();


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
