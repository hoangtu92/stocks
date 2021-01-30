<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LimitOrder extends Model
{
    protected $table = 'limit_orders';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $fillable = ["date", "id", "max", "count"];

}
