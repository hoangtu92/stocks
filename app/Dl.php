<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Dl extends Model
{
    //
    protected $table = 'dl';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $fillable = ["date", "dl_date", "code", "name", "id", "final", "range", "vol", "agency", "agency_price", "total_agency_vol", "single_agency_vol", "type", "large_trade", "dynamic_rate_sell", "borrow_ticket", "open", "high", "low", "price_907"];

    public function stock(){
        return $this->belongsTo("\App\Stock", "code", "code");
    }

}
