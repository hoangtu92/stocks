<?php


namespace App\Crawler\RealTime;


use App\Crawler\Crawler;
use App\GeneralPrice;
use App\GeneralStock;
use DateTime;
use Illuminate\Support\Facades\Log;

class RealtimeGeneral extends Crawler
{
    public function __invoke()
    {
        /**
         * Start to monitor stock data
         */
        Log::info("Start general realtime crawl");

        $now = new DateTime();
        $start = new DateTime();
        $stop = new DateTime();

        $start->setTime(9, 0, 0);
        $stop->setTime(13, 35, 0);

        $generalStock = GeneralStock::where("date", $now->format("Y-m-d"))->first();
        if (!$generalStock) {
            $generalStock = new GeneralStock([
                "date" => $now->format("Y-m-d")
            ]);

        }

        while ($now >= $start && $now <= $stop) {
            $this->callback($generalStock);
        }

        return true;
    }

    public function callback($generalStock){
        /**
         * Monitor General stock price
         */
        $response = json_decode($this->get_content("https://mis.twse.com.tw/stock/data/mis_ohlc_TSE.txt?" . http_build_query(["_" => time()])));

        if (isset($response->infoArray)) {
            $info = $response->infoArray[0];
            if (isset($info->h) && isset($info->z) && isset($info->tlong) && isset($info->l)) {
                $generalPrice = GeneralPrice::where("date", date("Y-m-d"))->where("tlong", $info->tlong)->first();
                $date = new DateTime();
                date_timestamp_set($date, $info->tlong / 1000);
                if (!$generalPrice) {
                    $generalPrice = new GeneralPrice([
                        'high' => $info->h,
                        'low' => $info->l,
                        'value' => $info->z,
                        'date' => $date->format("Y-m-d"),
                        'tlong' => $info->tlong
                    ]);

                    $generalPrice->Save();
                }


                #Log::info("Realtime general price: ". json_encode($info));

                /**
                 * Update general stock page data
                 */
                $time = getdate($info->tlong / 1000);

                if (!$generalStock->general_start) {
                    $generalStock->general_start = $generalPrice->value;
                    $generalStock->save();
                }
                if ($time["hours"] == 9 && $time["minutes"] == 7) {
                    $generalStock->price_905 = $generalPrice->value;
                    $generalStock->save();
                }
                if ($time["hours"] == 13 && $time["minutes"] >= 30) {
                    $generalStock->today_final = $generalPrice->value;
                    $generalStock->save();
                }
            }

        }
    }
}
