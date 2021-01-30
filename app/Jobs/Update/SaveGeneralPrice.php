<?php

namespace App\Jobs\Update;

use App\Crawler\StockHelper;
use App\GeneralPrice;
use App\GeneralStock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class SaveGeneralPrice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $generalPrice;

    /**
     * Create a new job instance.
     *
     * @param array $generalPrice
     */
    public function __construct(array $generalPrice)
    {
        //
        $this->generalPrice = $generalPrice;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //

        $exists = GeneralPrice::where("date", $this->generalPrice['date'])
            ->where("tlong",  $this->generalPrice['tlong'])
            ->first();

        if(!$exists) {
            $generalPrice = new GeneralPrice($this->generalPrice);
            $generalPrice->save();

            $time = getdate($this->generalPrice['tlong'] / 1000);

            if(($time['hours'] == 9 && $time["minutes"] <= 1)
                || ($time['hours'] == 9 && $time["minutes"] == 7)
                || ($time['hours'] == 13 && $time["minutes"] >= 30)
            ){
                $generalStock = GeneralStock::where("date", $this->generalPrice['date'])->first();

                if (!$generalStock) {
                    $generalStock = new GeneralStock([
                        "date" => $this->generalPrice['date']
                    ]);
                    $generalStock->save();

                    //Save yesterday final
                    $yesterdayGeneral = GeneralStock::where("date", StockHelper::previousDay(date("Y-m-d")))->first();
                    if($yesterdayGeneral)
                        Redis::set("General:yesterday_final", $yesterdayGeneral->today_final);
                }
                /**
                 * Update general stock page data
                 */

                if (!$generalStock->general_start) {
                    Redis::set("General:open_today", $generalStock->general_start);

                    $generalStock->general_start = $this->generalPrice['value'];
                    $generalStock->save();
                }

                if ($time["hours"] == 9 && $time["minutes"] == 7) {
                    $generalStock->price_905 = $this->generalPrice['value'];
                    $generalStock->save();
                }
                if ($time["hours"] == 13 && $time["minutes"] >= 30) {
                    $generalStock->today_final = $this->generalPrice['value'];
                    $generalStock->save();
                }
            }

        }
    }
}
