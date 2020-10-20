<?php

namespace App\Crawler;

use App\StockPrice;

class CrawlStockInfoData extends Crawler{

    public $data = [];
    public function __construct($url){
        parent::__construct();

        /*$stocks_str = implode("|", array_reduce($stocks, function ($t, $e){
            $t[] = "{$e['type']}_{$e['code']}.tw";
            return $t;
        }, []));

        //?ex_ch=tse_3218.tw
        $url = 'https://mis.twse.com.tw/stock/api/getStockInfo.jsp?'.http_build_query([
                "ex_ch" => $stocks_str,
                "t" => time()
            ]);*/

        $response = $this->get_content($url);

        $json = json_decode($response);

        #Log::info("Stock info {$url} ".$response);

        if(isset($json->msgArray) && count($json->msgArray) > 0) {


            foreach ($json->msgArray as $stock){
                $latest_trade_price = isset($stock->z) ? $this->format_number($stock->z) : 0;
                $trade_volume = isset($stock->tv) ? $this->format_number($stock->tv) : 0;
                $accumulate_trade_volume = isset($stock->v) ? $this->format_number($stock->v) : 0;

                $best_bid_price = explode("_", $stock->b);
                $best_bid_volume = explode("_", $stock->g);
                $best_ask_price = explode("_", $stock->a);
                $best_ask_volume = explode("_", $stock->f);

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
                    'open' => $open,
                    'high' => $high,
                    'low' => $low,
                ];

                //If stock price is not exists. create
                $stockPrice = StockPrice::where("code", $stock->c)
                    ->where("date", $stockInfo['date'])
                    ->where("tlong", $stockInfo["tlong"])
                    ->first();

                if (!$stockPrice) {
                    $stockPrice = new StockPrice($stockInfo);
                    $stockPrice->save();
                }

                $this->data[$stock->c] = $stockPrice;



            }


        }
    }
}
