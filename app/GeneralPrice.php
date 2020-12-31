<?php

namespace App;

use App\Jobs\Update\UpdateGeneralStock;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class GeneralPrice extends Model
{
    protected $table = 'general_prices';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
    public $hidden = ["created_at", "updated_at"];
    protected $appends = ['current_time'];
    protected $casts = [
        'high'  =>  'float',
        'low'       =>  'float',
        'value'       =>  'float',
    ];
    public $fillable = [
        "id",
        "high",
        "low",
        "value",
        "date",
        "tlong",
        "created_at",
        "updated_at"
    ];

    protected static function booted()
    {
        parent::booted(); // TODO: Change the autogenerated stub

        static::created(function (GeneralPrice $generalPrice){
            Redis::hmset("General:realtime", $generalPrice->toArray());

            $time = getdate($generalPrice->tlong / 1000);

            if ( ($time["hours"] == 9 && ($time["minutes"] == 1 || $time["minutes"] == 7)) || ($time["hours"] == 13 && $time["minutes"] >= 30)) {
                UpdateGeneralStock::dispatchNow($generalPrice);
            }

        });
    }

    /**
     * @return float|int
     */
    public function getPriceRangeAttribute(){
        $yesterday_final = (float) Redis::get("General:yesterday_final");
        return $yesterday_final > 0 ? (($this->value - $yesterday_final)/$yesterday_final)*100 : 0;
    }

    /**
     * @return DateTime
     * @throws \Exception
     */
    public function getTimeAttribute(){
        $date = new DateTime();
        $date->setTimestamp($this->tlong/1000);
        return $date;
    }

    /**
     * @return String
     */
    public function getCurrentTimeAttribute(){
        return $this->time->format("H:i:s");
    }


}
