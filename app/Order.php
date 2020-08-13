<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    //
    protected $table = 'orders';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
    public $fillable = ["id", "code", "sell_status", "sell_value", "buy_value", "minimum_buy", "price_range", "date", "start"];
}
