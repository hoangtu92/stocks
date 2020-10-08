<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    //
    protected $table = 'holidays';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
    public $fillable = ["id", "name", "date"];
}
