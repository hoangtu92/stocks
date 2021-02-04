<?php

namespace App\Console;


use App\Crawler\StockHelper;
use App\Jobs\Crawl\CrawlARAV;
use App\Jobs\Crawl\CrawlDL;
use App\Jobs\Crawl\CrawlHoliday;
use App\Jobs\Crawl\CrawlLargeTrade;
use App\Jobs\Crawl\CrawlRealtimeGeneral;
use App\Jobs\Crawl\CrawlRealtimeStock;
use App\Jobs\Trading\MonitorOrder;
use App\StockOrder;
use App\StockVendors\SelectedVendor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        #Log::info("every mins");
        $schedule->call(function (){
            Redis::flushall();
        })->dailyAt("08:00");

        $schedule->call(function () {

            $r = SelectedVendor::login();
            Log::debug("Vendor Login" .json_encode($r));
        })->dailyAt("09:00");

        $schedule->call(function () {
            $r = SelectedVendor::logout();
            Log::debug("Vendor Logout" .json_encode($r));
        })->dailyAt("14:30");

        #Log::info("Every minute run");


        $schedule->call(function () {

            Redis::set("is_holiday", StockHelper::isHoliday());
            if(StockHelper::isHoliday()){
                Log::info("Holiday");
            }
        })->name("is_holiday_today")
            ->dailyAt("01:00");

        /**
         * Crawl realtime data
         */

        $schedule->call(function (){CrawlRealtimeGeneral::dispatchNow();})
           ->name("get_realtime_general")
           ->weekdays()
           ->when(function () {
               return !(bool)Redis::get("is_holiday");
           })
            ->everyMinute()
            ->between("9:00", "13:35")
            ->withoutOverlapping()
            ->runInBackground();

        /**
         * DL2D FBS 1
         */
        $schedule->call(function (){
            $url = StockHelper::get_Dl2D_URL();
            if(isset($url[0])){
                Log::info("FBS play DL2D: {$url[0]}");
                CrawlRealtimeStock::dispatchNow($url[0]);
            }
        })
            ->name("FBS_DL02D_1")
            ->weekdays()
            ->when(function () {
                return !(bool)Redis::get("is_holiday") && env("STRATEGY") == "DL0_2D";
            })
            ->everyMinute()
            ->between("9:00", "13:30")
            ->withoutOverlapping()
            ->runInBackground();


        /**
         * DL2D FBS 2
         */
        $schedule->call(function (){
            $url = StockHelper::get_Dl2D_URL();
            if(isset($url[1])){
                Log::info("FBS play DL2D: {$url[1]}");
                CrawlRealtimeStock::dispatchNow($url[1]);
            }
        })
            ->name("FBS_DL02D_2")
            ->weekdays()
            ->when(function () {
                return !(bool)Redis::get("is_holiday") && env("STRATEGY") == "DL0_2D";
            })
            ->everyMinute()
            ->between("9:00", "13:30")
            ->withoutOverlapping()
            ->runInBackground();

        /**
         * DL2D FBS 3
         */
        $schedule->call(function (){
            $url = StockHelper::get_Dl2D_URL();
            if(isset($url[2])){
                Log::info("FBS play DL2D: {$url[2]}");
                CrawlRealtimeStock::dispatchNow($url[2]);
            }
        })
            ->name("FBS_DL02D_3")
            ->weekdays()
            ->when(function () {
                return !(bool)Redis::get("is_holiday") && env("STRATEGY") == "DL0_2D";
            })
            ->everyMinute()
            ->between("9:00", "13:30")
            ->withoutOverlapping()
            ->runInBackground();


        /**
         * DL2D FBS 4
         */
        $schedule->call(function (){
            $url = StockHelper::get_Dl2D_URL();
            if(isset($url[3])){
                Log::info("FBS play DL2D: {$url[3]}");
                CrawlRealtimeStock::dispatchNow($url[3]);
            }
        })
            ->name("FBS_DL02D_4")
            ->weekdays()
            ->when(function () {
                return !(bool)Redis::get("is_holiday") && env("STRATEGY") == "DL0_2D";
            })
            ->everyMinute()
            ->between("9:00", "13:30")
            ->withoutOverlapping()
            ->runInBackground();

        /**
         * Monitor orders
         */
        $schedule->job(new MonitorOrder, "medium")
            ->weekdays()
            ->when(function () {
                return !(bool)Redis::get("is_holiday");
            })
            ->everyMinute()
            ->between("9:00", "13:30");

        $schedule->call(function (){
            StockHelper::getGeneralData();
        })->weekdays()
            ->at("19:48")
            ->when(function () {
                return !(bool)Redis::get("is_holiday");
            });

        /**
         * Crawl dl data
         */
        $schedule->job(new CrawlDL, "high")
            ->weekdays()
            ->at("17:05")
            ->when(function () {
                return !(bool)Redis::get("is_holiday");
            });

        /**
         * Crawl arav data
         */
        $schedule->job(new CrawlARAV, "high")
            ->weekdays()
            ->at("17:10")
            ->when(function () {
                return !(bool)Redis::get("is_holiday");
            });

        /**
         * Crawl xz and agency
         */
        $schedule->job(new CrawlLargeTrade, "high")
            ->weekdays()
            ->at("18:10")
            ->when(function () {
                return !(bool)Redis::get("is_holiday");
            });


        /**
         * Re crawl agency
         */
        $schedule->call(function (){
            file_get_contents(route("re_crawl_agency"));
        })
            ->weekdays()
            ->at("18:20")
            ->when(function () {
                return !(bool)Redis::get("is_holiday");
            });

        $schedule->call(function (){
            file_get_contents(route("re_crawl_agency"));
        })
            ->weekdays()
            ->at("12:10");


        $schedule->call(function (){
            file_get_contents(route("re_crawl_agency"));
        })
            ->weekdays()
            ->at("01:22");

        /**
         * Crawl holiday
         */
        $schedule->job(new CrawlHoliday, "high")
            ->name("holiday")
            ->weekly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
