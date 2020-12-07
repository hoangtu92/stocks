<?php

namespace App\Crawler;

use App\StockPrice;
use Illuminate\Support\Facades\Redis;

class CrawlStockInfoData extends Crawler{

    public $data = [];
    public function __construct($url){
        parent::__construct();

        $response = $this->get_content($url);

        $json = json_decode($response);

        #Log::info("Stock info {$url} ".$response);

        if(isset($json->msgArray) && count($json->msgArray) > 0) {


            foreach ($json->msgArray as $stock){
                $latest_trade_price = isset($stock->z) ? $this->format_number($stock->z) : 0;
                $trade_volume = isset($stock->tv) ? $this->format_number($stock->tv) : 0;
                $accumulate_trade_volume = isset($stock->v) ? $this->format_number($stock->v) : 0;
                $yesterday_final = isset($stock->y) ? $this->format_number($stock->y) : 0;

                $best_bid_price = explode("_", $stock->b);
                $best_bid_volume = explode("_", $stock->g);
                $best_ask_price = explode("_", $stock->a);
                $best_ask_volume = explode("_", $stock->f);

                $pz = isset($stock->pz) ? $this->format_number($stock->pz) : 0;
                $ps = isset($stock->ps) ? $this->format_number($stock->ps) : 0;

                $open = $this->format_number($stock->o);
                $high = $this->format_number($stock->h);
                $low = $this->format_number($stock->l);

                $stockInfo = [
                    'code' => $stock->c,
                    'date' => date("Y-m-d"),
                    'tlong' => (int) $stock->tlong,
                    'latest_trade_price' => $latest_trade_price,
                    'trade_volume' => $trade_volume,
                    'accumulate_trade_volume' => $accumulate_trade_volume,
                    'best_bid_price' => $this->format_number($best_bid_price[0]),
                    'best_bid_volume' => $this->format_number($best_bid_volume[0]),
                    'best_ask_price' => $this->format_number($best_ask_price[0]),
                    'best_ask_volume' => $this->format_number($best_ask_volume[0]),
                    'ps' => $ps,
                    'pz' => $pz,
                    'open' => $open,
                    'high' => $high,
                    'low' => $low,
                    'yesterday_final' => $yesterday_final,
                ];


                $stockPrice = new StockPrice($stockInfo);
                $stockPrice->save();

                //Cache current stock info data to redis
                Redis::set("stock_info_{$stockPrice->code}", $stockPrice->toJson());


                $this->data[$stock->c] = $stockPrice;

            }


        }
    }
}
