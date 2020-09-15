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
        "open",
        "high",
        "low",
        "date",
        "tlong",
        "created_at",
        "modified_at"
    ];
}
