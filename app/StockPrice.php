<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockPrice extends Model
{
    protected $table = 'stock_prices';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
    public $fillable = [
        "id",
        "code",
        "latest_trade_price",
        "trade_volume",
        "accumulate_trade_volume",
        "best_bid_price",
        "best_bid_volume",
        "best_ask_price",
        "best_ask_volume",
        "ps",
        "pz",
        "open",
        "high",
        "low",
        "yesterday_final",
        "date",
        "tlong",
        "created_at",
        "modified_at"
    ];

    public function getCurrentPriceAttribute(){
        return $this->latest_trade_price > 0 ? $this->latest_trade_price : $this->best_ask_price;
    }

    public function getCurrentPriceRangeAttribute(){
        return $this->yesterday_final > 0 ? (($this->current_price - $this->yesterday_final)/$this->yesterday_final)*100 : 0;
    }

}
