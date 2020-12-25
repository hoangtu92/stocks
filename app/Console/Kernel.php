<?php

namespace App\Console;


use App\Crawler\StockHelper;
use App\Jobs\Analyze\AnalyzeGeneral;
use App\Jobs\Crawl\CrawlAgent;
use App\Jobs\Crawl\CrawlARAV;
use App\Jobs\Crawl\CrawlDL;
use App\Jobs\Crawl\CrawlHoliday;
use App\Jobs\Crawl\CrawlLargeTrade;
use App\Jobs\Crawl\CrawlRealtimeGeneral;
use App\Jobs\Crawl\CrawlRealtimeStock;
use App\StockOrder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
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

        /*$schedule->call(function () {
            $r = file_get_contents("http://dev.ml-codesign.com:8083/api/Vendor/login");
            Log::debug($r);
        })->dailyAt("08:30");

        $schedule->call(function () {
            $r = file_get_contents("http://dev.ml-codesign.com:8083/api/Vendor/logout");
            Log::debug($r);
        })->dailyAt("14:30");*/

        #Log::info("Every minute run");


        $schedule->call(function () {
            Redis::set("is_holiday", StockHelper::isHoliday());
        })->name("is_holiday_today")
            ->dailyAt("00:01");

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

        $schedule->call(function (){CrawlRealtimeStock::dispatchNow();})
            ->name("get_realtime_stock")
            ->weekdays()
            ->when(function () {
                return !(bool)Redis::get("is_holiday");
            })
            ->everyMinute()
            ->between("9:00", "13:30")
            ->withoutOverlapping()
            ->runInBackground();

        /**
         * Crawl dl0 real time
         */
        /*$schedule->job(new AnalyzeGeneral, "high")
            ->name("get_previous_general")
            ->weekdays()
            ->everyMinute()
            ->between("9:05", "13:25")
            ->runInBackground()
            ->withoutOverlapping()
            ->when(function () {
                return !(bool)Redis::get("is_holiday");
            });*/


        /**
         * Close all orders at 13:08
         */
        $schedule->call(function () {
            $orders = StockOrder::where("closed", false)->get();
            foreach ($orders as $order) {
                $order->close_deal();
            }
        })->weekdays()->at("13:08")
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
            ->between("18:10", "21:30")
            ->when(function () {
                return !(bool)Redis::get("is_holiday");
            });

        /**
         * Crawl holiday
         */
        $schedule->job(new CrawlHoliday, "high")->name("holiday")->weekdays()->yearly();

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
