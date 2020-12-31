<?php

namespace App\Jobs\Update;

use App\Crawler\StockHelper;
use App\GeneralPrice;
use App\GeneralStock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class UpdateGeneralStock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected GeneralPrice $generalPrice;

    /**
     * Create a new job instance.
     * @param GeneralPrice $generalPrice
     */
    public function __construct(GeneralPrice $generalPrice)
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

        /**
         * Update general stock
         */
        $generalStock = GeneralStock::where("date", $this->generalPrice->date)->first();


        if (!$generalStock) {
            $generalStock = new GeneralStock([
                "date" => $this->generalPrice->date
            ]);

            //Save yesterday final
            $yesterdayGeneral = GeneralStock::where("date", StockHelper::previousDay(date("Y-m-d")))->first();
            if($yesterdayGeneral)
                Redis::set("General:yesterday_final", $yesterdayGeneral->today_final);
        }
        /**
         * Update general stock page data
         */
        $time = getdate($this->generalPrice->tlong / 1000);

        if (!$generalStock->general_start) {
            Redis::set("General:open_today", $generalStock->general_start);

            $generalStock->general_start = $this->generalPrice->value;
            $generalStock->save();
        }

        if ($time["hours"] == 9 && $time["minutes"] >= 7) {
            $generalStock->price_905 = $this->generalPrice->value;
            $generalStock->save();
        }
        if ($time["hours"] == 13 && $time["minutes"] >= 30) {
            $generalStock->today_final = $this->generalPrice->value;
            $generalStock->save();
        }

    }
}
