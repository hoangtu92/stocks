<?php

/**
 * This job monitor vendor order list and see if there are any order
 * that has success or failed
 */

namespace App\Jobs\Analyze;

use App\Jobs\Order\PlaceOrder;
use App\StockOrder;
use App\StockVendors\SelectedVendor;
use Backpack\Settings\app\Models\Setting;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MonitorOrders implements ShouldQueue
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

        $r = Redis::keys("Stock:PendingOrders*");

        if($r){
            if(Setting::get("server_status") == 1){
                $vendorOrders = SelectedVendor::all();
            }

            foreach($r as $value){

                $order = Redis::hgetall(str_replace("dl0_strategy_1_database_", "", $value));
                $stockPrice = Redis::hgetall("Stock:currentPrice#{$order["code"]}");
                if($order){

                    if(Setting::get("server_status") == 1){
                        if (isset($vendorOrders[$order["OrderNo"]]))
                            $vendorOrder = $vendorOrders[$order["OrderNo"]];
                    }
                    else{
                        $vendorOrder = $order;
                    }

                    if (isset($vendorOrder) && isset($vendorOrder["State"])) {

                        //20210107-104427
                        if(isset($vendorOrder["ServerTime"]))
                            $serverTime = date_create_from_format("Ymd-His", $vendorOrder["ServerTime"]);
                        else{
                            $serverTime = new DateTime();
                            $serverTime->setTimestamp($stockPrice['tlong']/1000);
                        }


                        $createdTime = date_create_from_format("Ymd Hisv", $vendorOrder["CreateTime"]);

                        $time_since_order_created = ($serverTime->getTimestamp() - $createdTime->getTimestamp()) / 60; //Minutes;

                        switch ((int)$vendorOrder["State"]) {
                            case 30: //Commission success 委託成功 order Pending
                                //Pending

                                if ($vendorOrder["BS"] == "S" && $time_since_order_created >= 5 && $vendorOrder["CanCancel"] == "Y") {
                                    //Cancel order for timeout
                                    $r = SelectedVendor::cancel($vendorOrder["OID"], $vendorOrder["OrderNo"]);
                                    if ($r["Status"]) {
                                        $order['status'] = StockOrder::CANCELED;
                                        $order['updated_at'] = now();
                                        Redis::hmset("Stock:PendingOrders:{$order['code']}#{$order['OrderNo']}", $order);
                                        StockOrder::where("OrderNo", $order['OrderNo'])->where("code", $order['code'])->delete();
                                    }
                                }

                                break;
                            case 98: //Success
                                $order['tlong'] = $createdTime->getTimestamp() * 1000;
                                $order['status'] = StockOrder::SUCCESS;
                                $order['price'] = $vendorOrder["Price"];
                                $order['updated_at'] = now();

                                Redis::del("Stock:PendingOrders:{$order['code']}#{$order['OrderNo']}");
                                Redis::hmset("Stock:SuccessOrders:{$order['code']}#{$order['OrderNo']}", $order);

                                if($vendorOrder["BS"] == "S"){
                                    Redis::hmset("Stock:lastSold#{$order['code']}", $order);
                                    //Sell command has completed. Buy back immediately
                                    $countSuccessOrders = count(Redis::keys("Stock:SuccessOrders:{$order['code']}*"));

                                    echo "Success: {$countSuccessOrders}\n";
                                    //Buy back immediately
                                    if($countSuccessOrders > 0 && $countSuccessOrders%2 != 0){
                                        $buy_price = $order['price'] > 100 ? $order['price'] - 1 : $order['price'] - 0.4;

                                        PlaceOrder::dispatch(StockOrder::BUY, $stockPrice, $buy_price)->onQueue("high");
                                        echo "{$order['code']}: Sold at {$order['price']} | Buy Back at {$buy_price}\n";
                                    }

                                }
                                if($vendorOrder["BS"] == "B"){
                                    //Turn off server
                                    Setting::set("server_status", 0);
                                }

                                StockOrder::where("OrderNo", $order['OrderNo'])->where("code", $order['code'])->update([
                                    "tlong" => $order['tlong'],
                                    "status" => $order["status"],
                                    "price" => $order["price"],
                                    "updated_at" => $order['updated_at']
                                ]);

                                break;
                            case 99: //Deleted
                                $order['status'] = StockOrder::CANCELED;
                                $order['updated_at'] = now();
                                Redis::del("Stock:PendingOrders:{$order['code']}#{$order['OrderNo']}");
                                Redis::hmset("Stock:DeletedOrders:{$order['code']}#{$order['OrderNo']}", $order);
                                StockOrder::where("OrderNo", $order['OrderNo'])->where("code", $order['code'])->delete();
                                break;
                            default:
                                $order['status'] = StockOrder::FAILED;
                                $order['updated_at'] = now();
                                Redis::del("Stock:PendingOrders:{$order['code']}#{$order['OrderNo']}");
                                Redis::hmset("Stock:FailedOrders:{$order['code']}#{$order['OrderNo']}", $order);
                                StockOrder::where("OrderNo", $order['OrderNo'])->where("code", $order['code'])->delete();

                                //Error
                                break;
                        }
                    }

                }
            }
        }


        //


        /*"OID" => $row->{"@OID"},
        "OrderNo" => $row->{"@OrderNo"},
        "CreateTime" => $row->{"@CreateTime"},
        "ConfirmTime" => $row->{"@ConfirmTime"},
        "UpdateTime" => $row->{"@UpdateTime"},
        "BS" => $row->{"@BS"},
        "StockID" => $row->{"@StockID"},
        "Price" => $row->{"@Price"},
        "AvgPrice" => $row->{"@AvgPrice"},
        "State" => $row->{"@State"},
        "StateMsg" => $row->{"@StateMsg"},
        "CanCancel" => $row->{"@CanCancel"},
        "CanModify" => $row->{"@CanModify"}*/


    }
}
