<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

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
        "price",
        "fee",
        "tax",
        "date",
        "tlong",
        "type",
        "deal_type",
        "created_at",
        "modified_at"
    ];

    const BUY = "1";
    const SELL = "0";
    const SHORT_SELL = "0";
    const BUY_LONG = "1";
}
