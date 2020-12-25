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
        return ($this->sell*$this->qty*1.425) + ($this->buy*$this->qty*1.425);
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
        $fee = ($this->attributes["buy"]*$this->qty* 1.425);
        return $this->attributes["buy"]*$this->qty*1000 + $fee;
    }

    public function getFinalSellAttribute(){
        $tax = ($this->attributes["sell"]*$this->qty*1.5);
        $fee = ($this->attributes["sell"]*$this->qty* 1.425);
        return $this->attributes["sell"]*$this->qty*1000 - $tax - $fee;
    }

    public function getProfitAttribute(){
        return $this->final_buy > 0 ? round($this->final_sell - $this->final_buy, 2) : 0;
    }
    public function getProfitPercentAttribute(){
        return $this->final_buy > 0 ? round(($this->profit/$this->final_buy)*100, 2) : 0;
    }

    public function calculateProfit($price){
        $fee = ($price*$this->qty* 1.425);
        $final_buy = $price*$this->qty*1000 + $fee;
        return $final_buy > 0 ? $this->final_sell - $final_buy : 0;
    }

    public function getCurrentProfitAttribute(){
        return $this->calculateProfit($this->current_price);
    }

    public function getCurrentProfitPercentAttribute(){
        return round(($this->current_profit/($this->current_price*$this->qty*1000))*100, 2);
    }







    /**
     * @return bool|false|string
     */
    public function buy(){
        # Log::debug( "Buy back for stock order {$this->id} - Code: $this->code | Profit: {$this->profit_percent}" );
        if ( Setting::get( 'server_status' ) == '0' ) {
            return false;
        }

        $url       = "http://dev.ml-codesign.com:8083/api/Vendor/buy/{$this->code}?hasTicket=False";
        $r         = file_get_contents( $url );
        Log::debug($r);
        return $r;
    }

    /**
     * @return bool|false|string
     */
    public function sell(){
        # Log::debug( "Sell out for stock order {$this->id} - Code: $this->code | Profit: {$this->profit_percent}" );
        if ( Setting::get( 'server_status' ) == '0' ) {
            return false;
        }

        $url       = "http://dev.ml-codesign.com:8083/api/Vendor/sell/{$this->code}?hasTicket=False";
        $r         = file_get_contents( $url );
        Log::debug($r);
        return $r;
    }


}
