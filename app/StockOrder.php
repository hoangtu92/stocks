<?php

namespace App;

use Backpack\Settings\app\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class StockOrder extends Model
{
    //
    protected $table = 'stock_orders';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
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

    public function getProfitAttribute(){
        $fee = 0;//round( $this->sell * 1.425 );
        $tax = 0;//round( $this->sell * 1.5 );
        return $this->buy > 0 ? ($this->sell - $this->buy)*1000 - $tax - $fee : 0;
    }


    public function getProfitPercentAttribute(){
        return $this->buy > 0 ? ($this->profit/($this->buy*1000))*100 : 0;
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

    public function close_deal(StockPrice $current_stock_price = null){

        if(!$current_stock_price){
            $current_stock_price = StockPrice::where("date", $this->date)->where("code", $this->code)->orderByDesc("tlong")->first();
        }

        $this->closed = true;
        $this->tlong2 = $current_stock_price->tlong;
        $this->buy = $current_stock_price->current_price;
        $this->save();

        if($this->deal_type == self::SHORT_SELL){
            $this->buy();
        }
        else{
            $this->sell();
        }

        return $this;

    }


}
