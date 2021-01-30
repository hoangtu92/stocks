<?php

namespace App;
use DateTime;
use Illuminate\Database\Eloquent\Model;

class StockPrice extends Model
{
    protected $table = 'stock_prices';
    protected $primaryKey = 'id';
    protected $appends = ["current_time"];
    public $incrementing = true;

    protected $hidden = ["created_at", "updated_at", "trade_volume", "accumulate_trade_volume", "best_bid_volume", "best_ask_volume", "ps", "pz", "ip"];
    protected $casts = [
        'best_ask_price'  =>  'float',
        'best_bid_price'       =>  'float',
        'yesterday_final' => 'float',
        'high' => 'float',
        'low' => 'float',
        'latest_trade_price' => 'float',
        'average_price' => 'float'
    ];
    protected $fillable = [
        "id",
        "code",
        "latest_trade_price",
        "trade_volume",
        "accumulate_trade_volume",
        "best_bid_price",
        "best_bid_volume",
        "best_ask_price",
        "best_ask_volume",
        "average_price",
        "ps",
        "pz",
        "ip",
        "open",
        "high",
        "low",
        "yesterday_final",
        "date",
        "tlong",
        "created_at",
        "modified_at"
    ];

    /**
     * @return mixed
     */
    public function getCurrentPriceAttribute(){
        return $this->best_ask_price;
    }

    /**
     * @return float|int
     */
    public function getCurrentPriceRangeAttribute(){
        return $this->yesterday_final > 0 ? (($this->current_price - $this->yesterday_final)/$this->yesterday_final)*100 : 0;
    }

    /**
     * @return array
     */
    public function getStockTimeAttribute(){
        return getdate($this->tlong / 1000);
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
