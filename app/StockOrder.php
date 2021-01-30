<?php

namespace App;

use DateTime;
use Illuminate\Database\Eloquent\Model;

class StockOrder extends Model
{
    //
    protected $table = 'stock_orders';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $fillable = [
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
    const BUY = 'buy';
    const SELL = 'sell';
    const SUCCESS = "success";
    const FAILED = "failed";
    const PENDING = "pending";
    const CANCELED = "canceled";


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
        $fee = ($this->buy*$this->qty* 1.425*0.38);
        return $this->buy*$this->qty*1000 + $fee;
    }

    public function getFinalSellAttribute(){
        $tax = ($this->sell*$this->qty*1.5);
        $fee = ($this->sell*$this->qty* 1.425*0.38);
        return $this->sell*$this->qty*1000 - $tax - $fee;
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

    public function close_deal(){

    }


}
