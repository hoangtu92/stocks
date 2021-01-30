<?php

namespace App\Jobs\Trading;

use App\Crawler\StockHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class MonitorOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //

    }

    public function callback(){
        $url       = "http://dev.ml-codesign.com:8083/api/Vendor/all";
        $data         = file_get_contents( $url );

        $arr = StockHelper::XML2Json($data);

        //20210107-104427
        $serverTime = date_create_from_format("Ymd-His", $arr["@attributes"]["ServerTime"]);

        foreach($arr["Data"]["Row"] as $row) {

            $attr = $row["@attributes"];

            $createdTime = date_create_from_format("Ymd Hisv", $attr["CreateTime"]);

            $diff  =  ($serverTime->getTimestamp() - $createdTime->getTimestamp())/60; //Minutes;


            //Check if order can cancel
            if($attr["CanCancel"] == "Y"){

                //Check if type is Sell
                if ($attr["BS"] == "S") {
                    //Check if order has been created over 5 min
                    if($diff >= 5) {
                        //cancel if pending sell too long
                        $url       = "http://dev.ml-codesign.com:8083/api/Vendor/cancelOrder/{$attr["OID"]}/{$attr["orderNo"]}";
                        $r         = file_get_contents( $url );
                        Log::debug("Cancel response: ".$r);
                        return $r;

                    }
                }
            }

            switch((int) $attr["State"]){
                case 30: //Commission success 委託成功 order Pending
                    //Pending
                    break;
                case 98: //Success
                    if($attr["BS"] == "S"){

                    }
                    break;
                case 99: //Deleted
                    break;
                default:
                    break;
            }
        }

    }
}
