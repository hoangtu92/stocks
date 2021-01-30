<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Dl2 extends Model
{
    //
    protected $table = 'dl2s';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $fillable = ["date", "code", "id", "final"];

    public function stock(){
        return $this->belongsTo("\App\Stock", "code", "code");
    }
}
