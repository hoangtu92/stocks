<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GeneralPrice extends Model
{
    protected $table = 'general_prices';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
    public $fillable = [
        "id",
        "high",
        "low",
        "value",
        "date",
        "tlong",
        "created_at",
        "modified_at"
    ];
}
