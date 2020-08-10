<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Dl extends Model
{
    //
    protected $table = 'dl';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
    public $fillable = ["date", "code", "name", "id", "final", "range", "vol"];

}
