<?php

namespace App;

use DateTime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorOrder extends Model
{
    use HasFactory;
    protected $table = 'vendor_orders';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $fillable = [
        "id",
        "StockID",
        "qty",
        "BS",
        "price",
        "fee",
        "tax",
        "OID",
        "OrderNo",
        "date",
        "status",
        "tlong",
        "deal_type",
        "order_type",
        "created_at",
        "modified_at"
    ];

    const SHORT_SELL = "0";
    const BUY_LONG = "1";
    const DL0 = "dl0";
    const DL1 = "dl1";
    const DL2 = "dl2";
    const BUY = 'buy';
    const SELL = 'sell';
    const SUCCESS = "success";
    const FAILED = "failed";
    const PENDING = "pending";
    const CANCELED = "canceled";


    public function stock(){
        return $this->belongsTo("\App\Stock", "code", "code");
    }

    public function getTimeAttribute(){
        $date = new DateTime();
        $date->setTimestamp($this->tlong/1000);
        return $date->format("H:i:s");
    }
}
