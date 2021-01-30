<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    //
    protected $table = 'holidays';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $fillable = ["id", "name", "date"];
}
