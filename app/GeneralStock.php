<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GeneralStock extends Model
{
    //
    protected $table = 'general_stocks';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
    public $fillable = ["id", "date", "general_start", "price_905", "today_final"];

    const UP = 1;
    const DOWN = 0;
}
