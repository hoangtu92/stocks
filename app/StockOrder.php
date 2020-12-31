<?php

namespace App;

use Backpack\Settings\app\Models\Setting;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class StockOrder extends Model
{
    //
    protected $table = 'stock_orders';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected $casts = [
        'buy'  =>  'float',
        'sell'       =>  'float',
    ];
    public $fillable = [
        "id",
        "code",
        "qty",
        "buy",
        "sell",
        "date",
        "tlong",
        "tlong2",
        "deal_type",
        "order_type",
        "closed",
        "created_at",
        "modified_at"
    ];

    const SHORT_SELL = "0";
    const BUY_LONG = "1";
    const DL0 = "dl0";
    const DL1 = "dl1";
    const DL2 = "dl2";


    public function stock(){
        return $this->belongsTo("\App\Stock", "code", "code");
    }

    public function getOpenTimeAttribute(){
        $date = new DateTime();
        $date->setTimestamp($this->tlong/1000);
        return $date->format("H:i:s");
    }

    public function getCloseTimeAttribute(){
        $date = new DateTime();
        $date->setTimestamp($this->tlong2/1000);
        return $date->format("H:i:s");
    }

    public function getTaxAttribute(){
        return ($this->sell*$this->qty*1.5);
    }

    public function getFeeAttribute(){
        return ($this->sell*$this->qty*1.425*0.38) + ($this->buy*$this->qty*1.425*0.38);
    }

    public function getCurrentPriceAttribute(){
        $now = time()*1000;
        $stockPrice = StockPrice::where("date", $this->date)
            ->where("tlong", "<=", $now)
            ->where("code", $this->code)
            ->orderByDesc("tlong")
            ->first();

        return $stockPrice ? $stockPrice->current_price : 0;
    }

    public function getFinalBuyAttribute(){
        $fee = ($this->attributes["buy"]*$this->qty* 1.425*0.38);
        return $this->attributes["buy"]*$this->qty*1000 + $fee;
    }

    public function getFinalSellAttribute(){
        $tax = ($this->attributes["sell"]*$this->qty*1.5);
        $fee = ($this->attributes["sell"]*$this->qty* 1.425*0.38);
        return $this->attributes["sell"]*$this->qty*1000 - $tax - $fee;
    }

    public function getProfitAttribute(){
        return $this->final_buy > 0 ? round($this->final_sell - $this->final_buy, 2) : 0;
    }
    public function getProfitPercentAttribute(){
        return $this->final_buy > 0 ? round(($this->profit/$this->final_buy)*100, 2) : 0;
    }

    public function calculateProfit($price){
        $fee = ($price*$this->qty* 1.425*0.38);
        $final_buy = $price*$this->qty*1000 + $fee;
        return $final_buy > 0 ? $this->final_sell - $final_buy : 0;
    }

    public function getCurrentProfitAttribute(){
        return $this->calculateProfit($this->current_price);
    }

    public function getCurrentProfitPercentAttribute(){
        return $this->current_price > 0 ? round(($this->current_profit/($this->current_price*$this->qty*1000))*100, 2) : 0;
    }


    /**
     * @param bool $market_price
     * @return bool|false|string
     */
    public function buy($market_price = false){
        if((int) Redis::get("bought") == 1) return false;
        Log::debug( "Buy back for stock order {$this->id} - Code: $this->code | Profit: {$this->profit_percent}" );
        if ( Setting::get( 'server_status' ) == '0' ) {
            return false;
        }

        $price = $market_price ? "" : $this->buy;

        $url       = "http://dev.ml-codesign.com:8083/api/Vendor/order/B/{$this->code}/{$this->qty}/{$price}";
        $r         = file_get_contents( $url );
        Log::debug("Buy response: ".$r);
        Redis::set("bought", 1);
        return $r;
    }

    /**
     * @param false $market_price
     * @return bool|false|string
     */
    public function sell($market_price = false){
        if((int) Redis::get("sold") == 1) return false;
        Log::debug( "Sell out for stock order {$this->id} - Code: $this->code | Profit: {$this->profit_percent}" );
        if ( Setting::get( 'server_status' ) == '0' ) {
            return false;
        }

        $price = $market_price ? "" : $this->sell;

        $url       = "http://dev.ml-codesign.com:8083/api/Vendor/order/S/{$this->code}/{$this->qty}/{$price}";
        $r         = file_get_contents( $url );
        Log::debug("Sell response: ".$r);

        Redis::set("sold", 1);
        return $r;
    }

    /**
     * @param $OID
     * @param $orderNo
     * @return false|string
     */
    public function cancel($OID, $orderNo){
        $url       = "http://dev.ml-codesign.com:8083/api/Vendor/cancelOrder/{$OID}/{$orderNo}";
        $r         = file_get_contents( $url );
        Log::debug("Cancel response: ".$r);
        return $r;
    }


}
