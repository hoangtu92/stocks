<?php


namespace App\Crawler\RealTime;


use App\Crawler\Crawler;
use App\GeneralPrice;
use App\GeneralStock;
use DateTime;

class RealtimeGeneral extends Crawler
{
    public function monitor()
    {
        $now = new DateTime();
        $start = new DateTime();
        $stop = new DateTime();

        $start->setTime(9, 0, 0);
        $stop->setTime(13, 35, 0);


        while ($now >= $start && $now <= $stop) {
            $this->callback();
        }

        return true;
    }

    public function callback(){
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
                }

                $generalPrice->Save();
                #Log::info("Realtime general price: ". json_encode($info));

                /**
                 * Update general stock page data
                 */
                $time = getdate($info->tlong / 1000);
                $generalStock = GeneralStock::where("date", $generalPrice->date)->first();
                if (!$generalStock) {
                    $generalStock = new GeneralStock([
                        "date" => $generalPrice->date
                    ]);

                }

                if (!$generalStock->general_start) {
                    $generalStock->general_start = $generalPrice->value;
                    $generalStock->save();
                }
                if ($time["hours"] == 9 && $time["minutes"] == 7) {
                    $generalStock->price_905 = $generalPrice->value;
                    $generalStock->save();
                }
                if ($time["hours"] == 13 && in_array($time["minutes"], [30, 35])) {
                    $generalStock->today_final = $generalPrice->value;
                    $generalStock->save();
                }
            }

        }
    }
}
