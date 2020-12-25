<?php

namespace App\Jobs;

use App\Crawler\StockHelper;
use App\GeneralPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportCMoneyGeneral implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    protected $data;


    /**
     * Create a new job instance.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(isset($this->data->DataPrice)){
            $d = StockHelper::offset_date($this->data->DataPrice[0][0]/1000);
            GeneralPrice::where("date", $d->format("Y-m-d"))->delete();
            foreach ($this->data->DataPrice as $price){
                /**
                 * 0: timestamp
                 * 1: current price
                 * 2: volume
                 * 3: buy price
                 * 4: sold price
                 */
                $newdate = StockHelper::offset_date($price[0]/1000);
                $last_price = GeneralPrice::where("date", $newdate->format("Y-m-d"))->where("tlong", "<", $price[0])->orderBy("tlong", "desc")->first();

                if(!$last_price) {
                    $generalPrice = new GeneralPrice([
                        'low' => $price[1],
                        'high' => $price[1],
                        'value' => $price[1],
                        'tlong' => $newdate->getTimestamp()*1000,
                        'date' => $newdate->format("Y-m-d")
                    ]);
                    $generalPrice->save();
                }
                else{
                    $exists = GeneralPrice::where("date", $newdate->format("Y-m-d"))->where("tlong",  $price[0])->first();
                    if(!$exists){

                        $generalPrice = new GeneralPrice([
                            'low' => min($price[1], $last_price->low),
                            'high' => max($price[1], $last_price->high),
                            'value' => $price[1],
                            'tlong' => $newdate->getTimestamp()*1000,
                            'date' => $newdate->format("Y-m-d")
                        ]);
                        $generalPrice->save();
                    }
                    else{
                        $exists->update([
                            'low' => min($price[1], $last_price->low),
                            'high' => max($price[1], $last_price->high),
                            'value' => $price[1],
                            'tlong' => $newdate->getTimestamp()*1000,
                            'date' => $newdate->format("Y-m-d")
                        ]);
                    }

                }



            }
        }
    }
}
