<?php

namespace App\Console;

use App\Crawler\CrawlGeneralStock;
use App\Crawler\twse\CrawlGeneralStockToday;
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

    public function nextDay($day){
        $previous_day = strtotime("$day +1 day");
        $previous_day_date = getdate($previous_day);

        if($previous_day_date["wday"] == 0 || $previous_day_date["wday"] == 6)
            return $this->nextDay(date('Y-m-d', $previous_day));
        else return date('Y-m-d', $previous_day);
    }

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
        $schedule->call(function () {

            file_get_contents(route('general_stock'));


            $tmrGeneralProduct = GeneralStock::where("date", $this->nextDay(date("Y-m-d")))->first();

            if(!$tmrGeneralProduct)
                $tmrGeneralProduct = new GeneralStock([
                    "date" => $this->nextDay(date("Y-m-d"))
                ]);

            $tmrGeneralProduct->save();


        })->dailyAt("09:00");

        $schedule->call(function (){
            file_get_contents(route('general_stock_today', ["key" => "general_start"]));
        })->dailyAt("09:01");


        $schedule->call(function () {
            file_get_contents(route('crawl_order', ['key' => 'start']));
        })->dailyAt("9:02");

        $schedule->call(function (){
            file_get_contents(route('general_stock_today', ["key" => "price_905"]));
        })->dailyAt("09:05");

        $schedule->call(function () {
            file_get_contents(route('crawl_order', ['key' => 'price_909']));
        })->dailyAt("9:07");

        $schedule->call(function (){
            file_get_contents(route('general_stock_today', ["key" => "today_final"]));
        })->dailyAt("13:31");

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
