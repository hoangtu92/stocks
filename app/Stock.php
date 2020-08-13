<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    //

    const OTC = "otc";
    const TSE = "tse";

    protected $table = 'stocks';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
    public $fillable = ["id", "code", "name", "type"];
}
