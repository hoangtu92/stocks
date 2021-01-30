<?php

namespace App\Jobs\Order;

use App\Jobs\Analyze\MonitorOrders;
use App\StockVendors\SelectedVendor;
use App\VendorOrder;
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

class PlaceOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;
    public int $tries = 0;

    protected $price;
    protected int $qty;
    protected string $code;
    protected string $bs;
    protected $tlong;
    protected $stockPrice;

    /**
     * Create a new job instance.
     *
     * @param string $bs
     * @param $stockPrice
     * @param int $price
     */
    public function __construct(string $bs, $stockPrice, $price = 0)
    {
        //
        $this->bs = $bs;

        $this->stockPrice = $stockPrice;

        $this->qty = 1;
        $this->code = $this->stockPrice["code"];

        $this->price = $price;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //

        $time = new DateTime();
        $time->setTimestamp($this->stockPrice['tlong']/1000);
        $price = $this->price == 0 ? "" : $this->price;

        if($this->bs === VendorOrder::SELL)
            $result = SelectedVendor::sell($this->code, $this->qty, $price);
        elseif($this->bs === VendorOrder::BUY)
            $result = SelectedVendor::buy($this->code, $this->qty, $price);
        else return;

        if($result && $result["Status"]){

            if($this->price == "" && Setting::get("server_status") == 1){
                //sold at market price. Order will success immediately. run monitor
                MonitorOrders::dispatchNow();
            }

            echo "{$this->bs} {$this->qty} {$this->code} at {$this->price}\n";

            $stockOrder = new VendorOrder([
                "OID" => $result["OID"],
                "OrderNo" => $result["OrderNo"],
                "order_type" => VendorOrder::DL0,
                "deal_type" => VendorOrder::SHORT_SELL,
                "type" => $this->bs,
                "date" => $this->stockPrice['date'],
                "code" => $this->code,
                "qty" => $this->qty,
                "price" => $this->price,
                "tlong" => $this->tlong,
                "created_at" => $time->format("Y-m-d H:i:s")
            ]);

            if($this->bs === VendorOrder::BUY && !empty($this->price))
                Redis::hmset("Stock:pendingBuy#{$stockOrder->code}", $stockOrder->toArray());


            $stockOrder->save();

            //When every fucking time an order has been create. update this on memory.
            $result = array_merge([
                "CreateTime" => $time->format("Ymd Hisv"),
                "BS" => $this->bs == VendorOrder::SELL ? "S" : "B",
                "StockID" => $this->code,
                "Price" => $this->price,
                "State" => 30,
                "CanCancel" => "Y",
            ], $result, $stockOrder->toArray());

            Redis::hmset("Stock:PendingOrders:{$this->code}#{$result["OrderNo"]}", $result);

        }


    }
}
