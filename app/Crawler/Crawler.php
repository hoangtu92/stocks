<?php


namespace App\Crawler;


use App\Dl;
use App\Dl2;
use App\GeneralPrice;
use App\GeneralStock;
use App\Holiday;
use App\Order;
use App\StockOrder;
use App\StockPrice;
use Backpack\Settings\app\Models\Setting;
use DateTime;
use ErrorException;
use Goutte\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use const http\Client\Curl\PROXY_HTTP;

class Crawler {

	public $arrContextOptions;
	public $ch;

	public function __construct() {
		$this->ch = curl_init();
		curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $this->ch, CURLOPT_TIMEOUT, 100 );

		$this->arrContextOptions = array(
			"ssl" => array(
				"verify_peer"      => false,
				"verify_peer_name" => false,
			),
		);

	}

	public function get_content( $url ) {
		try {
			return file_get_contents( $url, false, stream_context_create( $this->arrContextOptions ) );
		} catch ( \Exception $e ) {
			//Log::error($e->getMessage());
		}

		return null;
	}

	public function getHoliday() {
		$year    = date( "Y" );
		$h       = Holiday::whereRaw( "DATE_FORMAT(date, '%Y') =  {$year}" )->get()->toArray();
		$holiday = array_reduce( $h, function ( $t, $e ) {
			$t[] = $e['date'];

			return $t;
		}, [] );

		return $holiday;
	}

	public function format_number( $value ) {
		return floatval( preg_replace( "/[\,]/", "", $value ) );
	}

	public function getDate( $date ) {
		if ( ! $date ) {
			$date = date_create( now() );
		}
		if ( is_string( $date ) ) {
			$date = date_create( $date );
		}

		$year    = $date->format( "Y" );
		$month   = $date->format( "m" );
		$day     = $date->format( "d" );
		$tw_year = $year - 1911;

		return [
			'year'    => $year,
			'month'   => $month,
			'day'     => $day,
			'tw_year' => $tw_year
		];
	}

	public function date_from_tw( $tw_date ) {
		$d    = explode( "/", $tw_date );
		$year = $d[0] + 1911;

		return "{$year}/{$d[1]}/{$d[2]}";
	}

	public function crawlGet( $url, $selector ) {
		$client  = new Client();
		$crawler = $client->request( "GET", $url );

		return $crawler->filter( $selector )->last();
	}

	public function curlGet( $url, $params = [], $headers = [] ) {
		$proxy = [
			"23.254.25.196:3128",
			"23.236.253.222:3128",
			"45.57.151.193:3128",
			"196.17.214.46:3128",
			"45.57.151.211:3128",
			"23.254.25.25:3128",
			"196.18.146.111:3128",
			"196.17.214.252:3128",
			"45.57.151.129:3128",
			"23.236.253.238:3128",
			"104.144.248.57:3128",
			"196.18.146.39:3128",
			"104.144.248.54:3128",
			"196.18.146.164:3128",
			"104.144.248.52:3128",
			"23.236.253.134:3128",
			"196.18.146.244:3128",
			"196.17.214.183:3128",
			"104.144.248.51:3128",
			"23.254.25.166:3128",
			"23.236.253.181:3128",
			"45.57.151.108:3128",
			"196.17.214.20:3128",
			"23.254.25.174:3128",
			"104.144.248.49:3128",
		];

		$p = rand( 0, count( $proxy ) - 1 );
		// echo "connect using proxy {$proxy[$p]}<br>";
		curl_setopt( $this->ch, CURLOPT_URL, $url . "?" . http_build_query( $params ) );
		curl_setopt( $this->ch, CURLOPT_POST, false );
		curl_setopt( $this->ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $this->ch, CURLOPT_PROXY, $proxy[ $p ] );
		//curl_setopt($this->ch, CURLOPT_PROXY, "103.249.100.152:80");
		$data = curl_exec( $this->ch );

		if ( curl_errno( $this->ch ) ) {
			print curl_error( $this->ch );
		} else {
			curl_close( $this->ch );
		}


		return $data;
	}


	public function curlPost( $url, $data, $headers = [] ) {
		curl_setopt( $this->ch, CURLOPT_URL, $url );
		curl_setopt( $this->ch, CURLOPT_POST, true );
		curl_setopt( $this->ch, CURLOPT_POSTFIELDS, http_build_query( $data ) );
		curl_setopt( $this->ch, CURLOPT_HTTPHEADER, $headers );
		$data = curl_exec( $this->ch );

		if ( curl_errno( $this->ch ) ) {
			print curl_error( $this->ch );
		} else {
			curl_close( $this->ch );
		}

		return $data;
	}

	public function previousDay( $day ) {

		$date              = $this->getDate( $day );
		$previous_day      = strtotime( "$day -1 day" );
		$previous_day_date = getdate( $previous_day );

		$h       = Holiday::whereRaw( "DATE_FORMAT(date, '%Y') =  '{$date['year']}'" )->get()->toArray();
		$holiday = array_reduce( $h, function ( $t, $e ) {
			$t[] = $e['date'];

			return $t;
		}, [] );

		if ( $previous_day_date["wday"] == 0 || $previous_day_date["wday"] == 6 || in_array( date( 'Y-m-d', $previous_day ), $holiday ) ) {
			return $this->previousDay( date( 'Y-m-d', $previous_day ) );
		} else {
			return date( 'Y-m-d', $previous_day );
		}
	}

	public function nextDay( $day ) {
		$date          = $this->getDate( $day );
		$next_day      = strtotime( "$day +1 day" );
		$next_day_date = getdate( $next_day );

		$h       = Holiday::whereRaw( "DATE_FORMAT(date, '%Y') =  '{$date['year']}'" )->get()->toArray();
		$holiday = array_reduce( $h, function ( $t, $e ) {
			$t[] = $e['date'];

			return $t;
		}, [] );

		if ( $next_day_date["wday"] == 0 || $next_day_date["wday"] == 6 || in_array( date( 'Y-m-d', $next_day ), $holiday ) ) {
			return $this->nextDay( date( 'Y-m-d', $next_day ) );
		} else {
			return date( 'Y-m-d', $next_day );
		}
	}

	public function previousDayJoin( $day, $filter_date ) {

		$d = $this->previousDay( $filter_date );

		$data = DB::table( "dl" )
		          ->addSelect( "dl.code" )
		          ->addSelect( DB::raw( "COUNT(*) as count" ) )
		          ->where( "dl_date", "=", $d )
		          ->whereRaw( "dl.agency IS NOT NULL" )
		          ->where( "dl.final", ">=", 10 )
		          ->where( "dl.final", "<", 170 )
		          ->groupBy( "dl.code" );

		if ( $day == 2 ) {
			$pv1 = $this->previousDayJoin( 1, $d );

			return $data->joinSub( $pv1, "previous_day_2_join", "dl.code", "=", "previous_day_2_join.code" );
		}
		if ( $day == 3 ) {
			$pv1 = $this->previousDayJoin( 2, $this->previousDay( $d ) );

			return $data->joinSub( $pv1, "previous_day_3_join", "dl.code", "=", "previous_day_3_join.code" );
		}

		return $data;
	}


	public function getStockData( $filter_date = null, $code = null, $current_price = null, $current_highest_price = null ) {

		/*$last20Days = DB::table("general_stocks")
			->addSelect("general_stocks.date")
			->addSelect(DB::raw("SUM(gs2.today_final) as sum_today_final"))
			->leftJoin(DB::raw("(SELECT today_final, date FROM general_stocks gs WHERE DAYOFWEEK(gs.date) BETWEEN 2 AND 6 ORDER BY gs.date DESC) gs2"),
				"general_stocks.date", ">=", "gs2.date")
			->whereRaw("DAYOFWEEK(general_stocks.date) BETWEEN 2 and 6 AND DATEDIFF(general_stocks.date, gs2.date) <= 27")
			->groupBy("general_stocks.date")
			->orderByDesc("general_stocks.date");

		$last19Days = DB::table("general_stocks")
			->addSelect("general_stocks.date")
			->addSelect(DB::raw("SUM(gs2.today_final) as sum_today_final, COUNT(gs2.today_final) as count_rows"))
			->leftJoin(DB::raw("(SELECT today_final, date FROM general_stocks gs WHERE DAYOFWEEK(gs.date) BETWEEN 2 AND 6 ORDER BY gs.date DESC) gs2"),
				"general_stocks.date", ">", "gs2.date")
			->whereRaw("DAYOFWEEK(general_stocks.date) BETWEEN 2 and 6 AND DATEDIFF(general_stocks.date, gs2.date) <= 27")
			->groupBy("general_stocks.date")
			->orderByDesc("general_stocks.date");*/


		$previousDay1 = $this->previousDayJoin( 1, $filter_date );
		$previousDay2 = $this->previousDayJoin( 2, $filter_date );
		$previousDay3 = $this->previousDayJoin( 3, $filter_date );


		$data = DB::table( "dl" )
		          ->leftJoin( "aravs", function ( $join ) {
			          $join->on( "dl.code", "=", "aravs.code" )->whereRaw( DB::raw( "dl.date = aravs.date" ) );
		          } )
		          ->leftJoin( "general_stocks", "general_stocks.date", "=", "dl.date" )
			//->leftJoinSub($last20Days, "avg_today", "avg_today.date", "=", "general_stocks.date")
			/*->leftJoinSub($last20Days, "avg_yesterday", function ($join) {
				$join->on("avg_yesterday.date", "<", "general_stocks.date")->whereRaw(" ( (DAYOFWEEK(`general_stocks`.`date`) = 2 AND DATEDIFF(`general_stocks`.date, avg_yesterday.date) = 3) OR (DAYOFWEEK(`general_stocks`.`date`) BETWEEN 3 AND 6 AND DATEDIFF(`general_stocks`.date, avg_yesterday.date) = 1))");
			})*/
			//->leftJoinSub($last19Days, "last_19_days", "last_19_days.date", "=", "general_stocks.date")
			      ->join( "stocks", "stocks.code", "=", "dl.code" )
		          ->leftJoinSub( $previousDay1, "previous_1_day", "dl.code", "=", "previous_1_day.code" )
		          ->leftJoinSub( $previousDay2, "previous_2_day", "dl.code", "=", "previous_2_day.code" )
		          ->leftJoinSub( $previousDay3, "previous_3_day", "dl.code", "=", "previous_3_day.code" )
		          ->select( "dl.dl_date as date" )
		          ->addSelect( "dl.code" )
		          ->addSelect( DB::raw( "IF(previous_3_day.count = 1, (if(previous_2_day.count = 1, (if(previous_1_day.count=1, 4, 3)), (if(previous_1_day.count=1, 2, 1)) )), (if(previous_1_day.count=1, 2, 1)) ) as appearance" ) )
		          ->addSelect( DB::raw( "stocks.name as name" ) )
		          ->addSelect( "dl.agency" )
		          ->addSelect( "dl.final" )
		          ->addSelect( "dl.range" )
		          ->addSelect( DB::raw( "ROUND(dl.vol, 0) as vol" ) )
		          ->addSelect( DB::raw( "ROUND(dl.total_agency_vol, 0) as total_agency_vol" ) )
		          ->addSelect( DB::raw( "ROUND(dl.single_agency_vol, 0) as single_agency_vol" ) )
		          ->addSelect( "aravs.start" )
		          ->addSelect( "aravs.max" )
		          ->addSelect( "aravs.lowest" )
		          ->addSelect( DB::raw( "aravs.final as arav_final" ) )
		          ->addSelect( "aravs.price_range" )
		          ->addSelect( "dl.borrow_ticket" )
		          ->addSelect( DB::raw( "ROUND((dl.total_agency_vol/dl.vol)*100, 2) as total_agency_rate" ) )
		          ->addSelect( DB::raw( "ROUND((dl.single_agency_vol/dl.vol)*100, 2) as single_agency_rate" ) )
		          ->addSelect( "dl.agency_price" )
		          ->addSelect( "dl.large_trade" )
		          ->addSelect( "dl.date as next_date" )
		          ->addSelect( DB::raw( "dl.open as order_start" ) );

		if ( $current_price ) {
			$data = $data->addSelect( DB::raw( "(SELECT {$current_price}) as price_907" ) );
		} else {
			$data = $data->addSelect( DB::raw( "dl.price_907 as price_907" ) );
		}

		if ( $current_highest_price ) {
			$data = $data->addSelect( DB::raw( "(SELECT {$current_highest_price}) as current_high" ) );
		} else {
			$data = $data->addSelect( DB::raw( "dl.high as current_high" ) );
		}

		$data = $data
			->addSelect( DB::raw( "(SELECT today_final FROM general_stocks WHERE date = dl.dl_date LIMIT 1) as yesterday_final" ) )
			->addSelect( DB::raw( "general_stocks.general_start as general_start" ) )
			->addSelect( DB::raw( "general_stocks.price_905 as general_price_907" ) )
			//->addSelect(DB::raw("ROUND(avg_yesterday.sum_today_final/20, 2)+30 as predict_20d_average"))

			//->addSelect(DB::raw("( (SELECT predict_20d_average)*20 - last_19_days.sum_today_final - 700) as predict_final"))
			->addSelect( DB::raw( "(SELECT predict_final FROM general_stocks WHERE date = next_date LIMIT 1) as predict_final" ) )
			->addSelect( DB::raw( "IF(general_stocks.custom_general_predict IS NULL, ((SELECT general_price_907) - (SELECT general_start)), general_stocks.custom_general_predict) as general_predict" ) )
			->addSelect( DB::raw( "(((SELECT order_start)-dl.final)/dl.final)*100 as BF" ) )
			->addSelect( DB::raw( "(((SELECT order_start)-dl.agency_price)/dl.agency_price)*100 as BU" ) )
			->addSelect( DB::raw( "(((SELECT general_start)-(SELECT yesterday_final))/(SELECT yesterday_final))*100 as BN" ) )
			->addSelect( DB::raw( "(((SELECT price_907)-(SELECT order_start))/(SELECT order_start))*100 as BH" ) )
			->addSelect( DB::raw( "ROUND((SELECT BF), 2) as order_price_range" ) )
			->addSelect( DB::raw( "IF((SELECT price_907) IS NULL, '等資料', IF((SELECT price_907) <= (SELECT order_start), '下', '上' ) ) as trend" ) )
			->addSelect( DB::raw( "ROUND(IF( (SELECT BF) <= 2 AND (SELECT BU) >= 3.2 AND (SELECT single_agency_rate) >= 2.2 AND dl.large_trade >= 1.8, dl.final*1.055, 
                IF((SELECT BF) <= 2.2 AND (SELECT single_agency_rate) >= 10 AND (SELECT BU) >= 4, dl.final*1.065, 
                    IF((SELECT order_start) >= dl.final AND (SELECT BF) <1.5 AND dl.agency_price <= dl.final, dl.final*1.03, 
                        IF( (SELECT BU) >= 5 AND (SELECT BF) <= 2, dl.final*1.05, 
                            IF((SELECT general_predict) >= 0 AND dl.final >= 50, dl.agency_price,
                                IF((SELECT general_predict) <= 0.05 AND (SELECT BF) >= 0 AND dl.agency_price <= dl.final, dl.final*1.01,
                                    IF((SELECT BF) <= -0.01 AND dl.agency_price <= dl.final, dl.final*1.02,
                                        IF((SELECT general_predict) <= 0 AND dl.final >= 50, dl.agency_price*1.025, dl.final*1.015)
                                    )
                                )
                            )
                        )
                    ) 
                )
            ), 2) as agency_forecast" ) )
			->addSelect( DB::raw( "ROUND((((SELECT order_start) - (SELECT agency_forecast))/(SELECT agency_forecast))*100, 1) as start_agency_range" ) )
			->addSelect( DB::raw( "(((SELECT price_907)-(SELECT agency_forecast))/(SELECT agency_forecast))*100 as BI" ) )
			->addSelect( DB::raw( "
            IF((SELECT BN)<=-1, '馬上做多單',
                    IF((SELECT BF)>=5 AND (SELECT BH)>=1, '漲停不下單',
                        IF((SELECT BF)>=0.3 AND (SELECT BH)>=4.9, '漲停不下單',
                            IF((SELECT BF)>=3.8 AND (SELECT BH)>=4, '漲停不下單',
                                IF((SELECT BF)>=7.5 AND (SELECT price_907)>=(SELECT order_start), '漲停不下單',
                                    IF((SELECT general_predict)='' OR (SELECT general_predict) IS NULL OR (SELECT BN)='' OR (SELECT order_start) IS NULL OR (SELECT price_907) IS NULL, '等資料',
                                        IF((SELECT price_907) <= dl.agency_price, '等拉高',
                                            IF((SELECT general_price_907)<(SELECT general_start) AND (SELECT BN)<=0.2 AND (SELECT BF)>=2.27 AND (SELECT price_907)>=(SELECT order_start) AND (SELECT order_start)>=(SELECT agency_forecast), '等拉高',
                                                IF((SELECT start_agency_range)<=0 AND (SELECT trend)='下' AND (SELECT BI)<=0 AND (SELECT order_start)<=dl.agency_price AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                    IF((SELECT start_agency_range)<=1.2 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                        IF((SELECT general_price_907)<(SELECT general_start) AND (SELECT BN)>=0.2 AND (SELECT trend)='上' AND (SELECT price_907)<(SELECT agency_forecast) AND (select appearance) = 0, '馬上做多單',
                                                            IF((SELECT general_price_907)>(SELECT general_start) AND (SELECT BF)>=5 AND (SELECT price_907)>=(SELECT order_start), '等拉高',
                                                                IF((SELECT general_price_907)<(SELECT general_start) AND (SELECT trend)='上' AND (SELECT order_start)<(SELECT agency_forecast) AND (SELECT BU)>1 AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                    IF((SELECT general_price_907)<(SELECT general_start) AND (SELECT trend)='下' AND (SELECT order_start)<(SELECT agency_forecast) AND (SELECT BU)>1 AND (SELECT BH)<=-3 AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                        IF((SELECT general_predict) < 0 AND (SELECT BN)<=-0.01 AND (SELECT BF)<=0.1 AND (SELECT order_start)<=dl.agency_price AND (SELECT price_907)>=(SELECT order_start) AND (SELECT price_907) < (SELECT agency_forecast) AND (select appearance) = 0, '馬上做多單',
                                                                            IF((SELECT general_price_907)<(SELECT general_start) AND (SELECT BN)>=0.5 AND (SELECT order_start)<=(SELECT agency_forecast) AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (SELECT price_907) < (SELECT agency_forecast) AND (select appearance) = 0, '馬上做多單',
                                                                                IF((SELECT general_predict) >=0 AND (SELECT BN)<=0.2 AND (SELECT BN)<=-0.01 AND (SELECT BF)>=5 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                                    IF((SELECT general_predict) >= 0 AND (SELECT order_start)<=dl.agency_price AND (SELECT BN)>=0.05 AND (SELECT BF)>=3 AND (SELECT price_907)>=(SELECT order_start) AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                                        IF((SELECT trend)='下' AND (SELECT BN)<-0.4 AND (SELECT BN)<0 AND (SELECT BF)<=1 AND (SELECT BF)<=0.9 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                                            IF((SELECT general_predict) >= 0 AND (SELECT BN)<0 AND (SELECT BF)<=2.2 AND (SELECT price_907)<=(SELECT order_start), (SELECT price_907),
                                                                                                IF((SELECT general_predict) >= 0 AND (SELECT BN)>=0.1 AND (SELECT BF)>=2.3 AND (SELECT start_agency_range)>=1.5 AND (SELECT trend)='下', (SELECT price_907),
                                                                                                    IF((SELECT general_price_907)>(SELECT general_start) AND (SELECT BN)<=0.2 AND (SELECT BN)>=0.01 AND (SELECT price_907)<=(SELECT order_start) AND (SELECT order_start)<=dl.agency_price AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                                                        IF((SELECT general_predict) < 0 AND (SELECT BN)<=0.01 AND (SELECT BF)<=2 AND (SELECT BF)>=1 AND (SELECT trend)='下' AND (SELECT current_high) < (SELECT agency_forecast), '等低點做多單',
                                                                                                            IF((SELECT general_predict) >= 0 AND (SELECT BN)<0 AND (SELECT aravs.max)>=dl.final AND dl.large_trade<=2 AND ((SELECT agency_forecast)-dl.final)/dl.final>=6.5, '做多單',
                                                                                                                IF((SELECT general_price_907)>(SELECT general_start) AND (SELECT BF)<=0.05 AND  (SELECT single_agency_rate)>=4 AND (SELECT BU)<=2, '等拉高',
                                                                                                                    IF((SELECT general_price_907)>(SELECT general_start) AND (SELECT price_907)>=(SELECT order_start) AND (SELECT BN)<=1.16, '等拉高',
                                                                                                                        IF((SELECT BF)<=2.2 AND  (SELECT single_agency_rate)>=10 AND (SELECT BU)>=4 AND (SELECT general_predict) >= 0, '等拉高',
                                                                                                                            IF((SELECT order_start)<=1.5 AND (SELECT order_start)<=dl.agency_price AND dl.large_trade<=2 AND (SELECT general_price_907)>(SELECT general_start) AND (SELECT price_907)<(SELECT order_start) AND (SELECT BH)>=-5, '等拉高',
                                                                                                                                IF((SELECT BF)<-9 AND dl.agency_price<=dl.final, dl.final,
                                                                                                                                    IF((SELECT BF)<=1.5 AND dl.agency_price>=dl.final AND (SELECT agency_forecast)>=(SELECT order_start) AND dl.large_trade<=2 AND ((SELECT order_start)/(SELECT agency_forecast))<=1.005, '等拉高',
                                                                                                                                        IF((SELECT order_start)<=dl.agency_price AND (SELECT BF)<=4 AND (SELECT BF)>=3 AND ((SELECT order_start)/(SELECT agency_forecast))<=1.012 AND dl.large_trade<6, '等拉高',
                                                                                                                                            IF((SELECT BF)<=-1 AND dl.agency_price<=dl.final, (SELECT price_907),
                                                                                                                                                IF((SELECT BF)<=1.2 AND (SELECT BU)>=1 AND (SELECT single_agency_rate)>=2.2 AND dl.large_trade>=1.8 AND (SELECT general_predict) >= 0, '等拉高',
                                                                                                                                                    IF((SELECT general_price_907)>(SELECT general_start) AND (SELECT BF)<=1 AND (SELECT order_start)<=(SELECT agency_forecast) AND (SELECT price_907)<(SELECT order_start), '等拉高',
                                                                                                                                                        IF((SELECT general_predict) < 0 AND (SELECT BF)<=5 AND (SELECT BF)>=3.7 AND dl.agency_price<dl.final AND (SELECT order_start)<=dl.agency_price AND (SELECT start_agency_range)>=1.5 AND (SELECT trend)='下', '等拉高',
                                                                                                                                                            IF((SELECT trend)='上' AND (SELECT BN)<=1.16, '等拉高', (SELECT price_907))
                                                                                                                                                        )
                                                                                                                                                    )
                                                                                                                                                )
                                                                                                                                            )
                                                                                                                                        )
                                                                                                                                    )
                                                                                                                                )
                                                                                                                            )
                                                                                                                        )
                                                                                                                    )
                                                                                                                )
                                                                                                            )
                                                                                                        )
                                                                                                    )
                                                                                                )
                                                                                            )
                                                                                        )
                                                                                    )
                                                                                )
                                                                            )
                                                                        )
                                                                    )
                                                                )
                                                            )
                                                        )
                                                    )
                                                )
                                            )
                                        )
                                    )
                                )
                            )
                        )
                    )
                )
                 as place_order" ) )
			->addSelect( DB::raw( "
            ROUND(IF((SELECT place_order)='等拉高' AND (SELECT general_price_907)>=(SELECT general_start) AND (SELECT trend)='上' AND (SELECT BN)>=0.3 AND (SELECT BF)>=5, (SELECT order_start)*1.023,
                IF((SELECT place_order)='等拉高' AND (SELECT trend)='上' AND (SELECT general_price_907)>=(SELECT general_start) AND (SELECT BN)>=0.3 AND (SELECT BF)<5 AND (SELECT order_start)<100, (SELECT order_start)*1.038,
                    IF((SELECT place_order)='等拉高' AND (SELECT general_price_907)>(SELECT general_start) AND (SELECT price_907)>=(SELECT order_start) AND (SELECT start_agency_range)<=1.5, (SELECT order_start)*1.032,
                        IF((SELECT place_order)='等拉高' AND (SELECT general_price_907)>(SELECT general_start) AND (SELECT price_907)>=(SELECT order_start) AND (SELECT start_agency_range)>=1.5, (SELECT order_start)*1.024,
                            IF((SELECT place_order)='等拉高' AND (SELECT trend)='上' AND (SELECT general_price_907)>=(SELECT general_start) AND (SELECT BN)>=0.3 AND (SELECT BF)<5 AND (SELECT order_start)>=100, (SELECT order_start)*1.038,  
                                IF((SELECT place_order)='等拉高' AND (SELECT trend)='上' AND (SELECT general_price_907)<=(SELECT general_start) AND (SELECT BF)>=0.5, (SELECT order_start)*1.045,
                                    IF((SELECT place_order)='等拉高' AND (SELECT BF)<=1.5 AND dl.agency_price<=dl.final AND dl.large_trade<=2, dl.agency_price*1.017,
                                        IF((SELECT place_order)='等拉高' AND (SELECT general_predict) < 0 AND (SELECT price_907)>=(SELECT order_start) AND (SELECT BU)<=4.2 AND (SELECT BU)>=2 AND (SELECT start_agency_range)<=1.5, (SELECT order_start)*1.043,
                                            IF((SELECT BF)<=4 AND (SELECT BF)>=3 AND (SELECT place_order)='等拉高', (SELECT order_start)*1.018,
                                                IF((SELECT place_order)='等拉高' AND (SELECT BF)<=5 AND (SELECT BF)>=4, (SELECT order_start)*1.03, 
                                                    IF((SELECT place_order)='做多單' AND (SELECT BF)<=5 AND (SELECT BF)>=4, (SELECT order_start)*1.028,
                                                        IF((SELECT place_order) != '等拉高' OR (SELECT place_order)='漲停不下單', 0, (SELECT agency_forecast))
                                                    )
                                                )
                                            )
                                        )
                                    )
                                )
                            )
                        )
                    )
                )
            ),2) as wail_until" ) );
		//->addSelect(DB::raw("(SELECT IF(best_ask_price-wail_until >=0, 'YES', 'NO') as pass_ak FROM stock_prices WHERE code = dl.code AND date = ADDDATE(dl.date ,1)) as pass_ak"));

		if ( $filter_date ) {
			$data = $data->where( "dl.dl_date", $filter_date );
		}

		if ( $code ) {
			$data = $data->where( "dl.code", $code )->first();
		} else {
			$data = $data->whereRaw( "dl.agency IS NOT NULL" )
			             ->where( "dl.final", ">=", 10 )
			             ->where( "dl.final", "<=", 170 )
				/*->orderBy("dl.range", "desc")*/
				         ->orderBy( "dl.date", "desc" )
			             ->orderBy( "appearance", "desc" )
			             ->orderBy( "total_agency_rate", "desc" )
			             ->orderBy( "single_agency_rate", "desc" )
			             ->orderBy( "dl.large_trade", "desc" )
			             ->get()
			             ->toArray();
			/*->toSql();*/
		}

		//echo $data;


		return $data;
	}


	public function monitorGeneralStock() {

	}

	public function monitorStock( $stock, $stockPrice ) {

        $current_price = $stockPrice->best_ask_price > 0 ? $stockPrice->best_ask_price : $stockPrice->latest_trade_price;

        if($current_price <= 0) return;


        $stockTime = getdate( $stockPrice->tlong / 1000 );

		//Get previous price
		$previous_prices = StockPrice::where( "code", $stockPrice->code )
		                             ->where( "tlong", "<", $stockPrice->tlong )
		                             ->where( "date", $stockPrice->date )
		                             ->orderBy( "tlong", "desc" )
		                             ->take( 3 )->get();


		$fee              = round( $current_price * 1.425 );
		$tax              = round( $current_price * 1.5 );
		$previous_price_0 = 0;
		$previous_price_1 = 0;
		$previous_price_2 = 0;

		if ( isset( $previous_prices[0] ) ) {
			$previous_price_0 = $previous_prices[0]->best_ask_price > 0 ? $previous_prices[0]->best_ask_price : $previous_prices[0]->high;
		}

		if ( isset( $previous_prices[1] ) ) {
			$previous_price_1 = $previous_prices[1]->best_ask_price > 0 ? $previous_prices[1]->best_ask_price : $previous_prices[1]->high;
		}

		if ( isset( $previous_prices[2] ) ) {
			$previous_price_2 = $previous_prices[1]->best_ask_price > 0 ? $previous_prices[1]->best_ask_price : $previous_prices[1]->high;
		}

		if ( ! $stock->open && $stockPrice->open != 0 ) {
			$stock->open = $stockPrice->open;
			$stock->save();
		}

		if ( ! $stock->high || $stockPrice->high > $stock->high ) {
			$stock->high = $stockPrice->high;
			$stock->save();
		}

		if ( ! $stock->low || $stockPrice->low < $stock->low ) {
			$stock->low = $stockPrice->low;
			$stock->save();
		}

		if ( $stockTime["hours"] == 9 && in_array( $stockTime["minutes"], [ 8, 9, 10 ] ) && ! $stock->price_907 ) {
			$stock->price_907 = $current_price;
			$stock->save();
		}

		//Perform task from 09:00 to 09:07
		if ( $stockTime["hours"] < 9 || ( $stockTime["hours"] == 9 && $stockTime["minutes"] <= 7 ) ) {

			/**
			 * Update stock price data
			 */


			if ( $stockTime["hours"] == 9 && $stockTime["minutes"] == 7 ) {
				$stock->price_907 = $current_price;
				$stock->save();
			}

			$data = $this->getStockData( $stock->dl_date, $stockPrice->code, $current_price );

			#Log::info("AJ stock data". json_encode($data));

			if ( ! is_numeric( $data->place_order ) ) {
				if ( $data->place_order == '馬上做多單' ) {
					/*$stockOrder = StockOrder::where( "code", $stock->code )
					                        ->where( "order_type", StockOrder::DL1 )
					                        ->where( "date", $stockPrice->date )
					                        ->where( "closed", false )
					                        ->where( "deal_type", StockOrder::BUY_LONG )
					                        ->where( "type", StockOrder::BUY )
					                        ->first();*/
					/*if ( ! $stockOrder ) {
						//Buy long now. don’t need to wait 9:07 data

					} else {
						#$stockOrder->qty++;
					}*/
					#$stockOrder->save();
				}
				if ( $data->place_order == '等拉高' ) {
					//Wait a bit and Short selling when meet condition
					if ( isset( $previous_prices[2] ) ) {

						//if price still going up even over the AK suggested price, don’t sell yet. Pls wait until current price drop to  < ‘h’/1.05
						if ( ( $stockPrice->high >= $data->wail_until && $current_price < $stockPrice->high / 1.05 )

						     //OR
						     //if it’s ‘h’ > agency forecast, and it’s dropping down now. need to sell it now, don’t need to wait until 9:07
						     || ( $stockPrice->high >= $data->agency_forecast && $current_price < $stockPrice->high / 1.05
						          && $current_price < $previous_price_0
						          && $previous_price_0 < $previous_price_1
						          && $previous_price_1 < $previous_price_2 ) ) {

							//Short selling now

							$stockOrder = StockOrder::where( "code", $stock->code )
							                        ->where( "order_type", StockOrder::DL1 )
							                        ->where( "date", $stockPrice->date )
							                        ->where( "closed", false )
							                        ->where( "deal_type", StockOrder::SHORT_SELL )
							                        ->first();

							if ( ! $stockOrder ) {
								$stockOrder = new StockOrder( [
									"order_type" => StockOrder::DL1,
									"deal_type"  => StockOrder::SHORT_SELL,
									"date"       => $stockPrice->date,
									"tlong"      => $stockPrice->tlong,
									"code"       => $data->code,
									"qty"        => 1,
									"sell"      => $current_price
								] );

								$stockOrder->save();

								return;
							}


						}


					}

				}
			}

			if ( $stockTime["hours"] == 9 && $stockTime["minutes"] == 7 ) {

				if ( is_numeric( $data->place_order ) && $data->place_order > 0 ) {
					//Short selling now
					$stockOrder = StockOrder::where( "code", $stock->code )
					                        ->where( "order_type", StockOrder::DL1 )
					                        ->where( "deal_type", StockOrder::SHORT_SELL )
					                        ->where( "date", $stockPrice->date )
					                        ->where( "closed", false )
					                        ->first();

					if ( ! $stockOrder ) {

						$stockOrder = new StockOrder( [
							"order_type" => StockOrder::DL1,
							"deal_type"  => StockOrder::SHORT_SELL,
							"date"       => $stockPrice->date,
							"tlong"      => $stockPrice->tlong,
							"code"       => $stockPrice->code,
							"qty"        => 1,
							"sell"      => $data->place_order
						] );

						$stockOrder->save();

						return;


					}

				}

			}
		}

		/**
		 * ---------------------------------------------------------------------------------------------------------------
		 */

		//Close deal??

		$previous_order = StockOrder::where( "code", $stock->code )
		                    ->where( "closed", "=", false )
		                    ->where( "order_type", "=", StockOrder::DL1 )
		                    ->where( "date", $stockPrice->date )
		                    ->first();

		if ( $previous_order && $previous_order->sell > 0 ) {

			#Log::debug("TB test previous order {$previous_order->id} {$previous_order->code}");

			$sell_price = $previous_order->sell;
			$buy_price  = $current_price;

            $current_profit         = ( $sell_price - $buy_price ) * 1000 - $fee - $tax;
            $current_profit_percent = ( $current_profit / ( $buy_price * 1000 + $fee + $tax ) ) * 100;

			//Close deal when profit >= 2 and check formula > 0.4
			if ( $current_profit_percent >= 2 ) {

				if ( $previous_prices && isset( $previous_prices[2] ) ) {

					$check = ( ( $previous_price_1 - $current_price ) / $current_price ) * 100;

					//Close deal
					if ( $previous_order->deal_type == StockOrder::SHORT_SELL ) {
						//If short selling

						//If price was dropping but seems going up or stop dropping
						if ( $current_price == $stockPrice->low && $check > 0.4 ) {

							//Buy Back
							$this->buyBack( $previous_order, $stockPrice );

							return;
						}

					}


				}

			}

			if(
			    ($current_profit_percent >= 1.5 && $current_price >= 50) ||
                $current_profit_percent >= 1.2 && $current_price >= 100
            ){
                $this->buyBack( $previous_order, $stockPrice );

                return;
            }


			//Close deal when current H >= previousH*1.005
			/*$pvh = DB::table( "stock_prices" )
			         ->selectRaw( "MAX(high) as high" )
			         ->where( "code", $stock->code )
			         ->where( "date", $stockPrice->date )
			         ->where( "tlong", "<", $stockPrice->tlong )
			         ->groupBy( "code" )
			         ->orderByDesc( "tlong" )
			         ->first();

			if ( $pvh ) {
				$previousH = $pvh->high;
				//$previousH*1.005
				if ( $stockPrice->high >= $previousH * 1.005 ) {
					Log::debug( "Locked in high?? {$stockPrice->code} {$stockPrice->high} - {$previousH} {$stock->borrow_ticket}" );
					if ( $previous_order->deal_type == StockOrder::SHORT_SELL ) {
						if ( ! $stock->borrow_ticket ) {
							$this->buyBack( $previous_order, $stockPrice );

							return;
						}
					}
				}
			}*/



			//Close deal when  current price =previous_order_price/1.01
			if ( $current_price == $previous_order->sell / 1.015 ) {
				if ( $previous_order->deal_type == StockOrder::SHORT_SELL ) {
					$this->buyBack( $previous_order, $stockPrice );

					return;
				}
			}

			//Close deal at the end of trading day and stock is not locked in high
			// && $current_price < $previous_order->price - $tax - $fee
			if ( $stockTime["hours"] == 13 && $stockTime["minutes"] >= 8 ) {
				if ( $previous_order->deal_type == StockOrder::SHORT_SELL ) {
					$this->buyBack( $previous_order, $stockPrice );
					return;
				}
			}


		}

		/**
		 * ---------------------------------------------------------------------------------------------------------------
		 */
	}


    /**
     * @param $unclosed_order
     * @param $stockPrice
     * @param null $price
     *
     * @return mixed
     */
	public function buyBack( $unclosed_order, $stockPrice, $price = null ) {

		//Check if already close deal?
		if ( ! $unclosed_order->closed ) {

            Log::debug( "Buy back for stock order {$unclosed_order->id} - $stockPrice->code" );

            # $fee = round( $stockPrice->best_ask_price * 1.425 );
			# $tax = round( $stockPrice->best_ask_price * 1.5 );
			//Buy Back

            $price = $price ? $price : ($stockPrice->best_ask_price > 0 ? $stockPrice->best_ask_price : $stockPrice->latest_trade_price);

            $unclosed_order->closed = true;
            $unclosed_order->tlong2 = $stockPrice->tlong;
            $unclosed_order->buy = $price;
            $unclosed_order->save();

		}

		return $unclosed_order;
	}

	public function getStocksURL() {

		$stocks = DB::table( "stocks" )
		            ->whereRaw( " LENGTH(code) = 4" );

		$nop = 80;

		$list = [];

		for ( $i = 0; $i < $stocks->count(); $i += $nop ) {

			$sub = DB::table( "stocks" )
			         ->whereRaw( " LENGTH(code) = 4" )
			         ->limit( $nop )->offset( $i );

			$list[] = DB::table( DB::raw( "({$sub->toSql()}) as sub" ) )
			            ->mergeBindings( $sub )
			            ->selectRaw( 'CONCAT("https://mis.twse.com.tw/stock/api/getStockInfo.jsp?ex_ch=", GROUP_CONCAT(type, "_", code, ".tw" separator "|")) as url' )
			            ->first();
		}

		return $list;
	}

	public function getUrlFromStocks( $stocks ) {
		$stocks_str = implode( "|", array_reduce( $stocks, function ( $t, $e ) {
			$t[] = "{$e['type']}_{$e['code']}.tw";

			return $t;
		}, [] ) );

		//?ex_ch=tse_3218.tw
		return 'https://mis.twse.com.tw/stock/api/getStockInfo.jsp?' . http_build_query( [
				"ex_ch" => $stocks_str,
				"t"     => time()
			] );
	}

	public function getDL1Stocks( $filter_date ) {
		if ( ! $filter_date ) {
			$filter_date = date( "Y-m-d" );
		}

		return Dl::join( "stocks", "stocks.code", "=", "dl.code" )
		         ->select( "dl.date" )
		         ->addSelect( "dl.dl_date" )
		         ->addSelect( "dl.id" )
		         ->addSelect( "dl.code" )
		         ->addSelect( "dl.open" )
		         ->addSelect( "dl.low" )
		         ->addSelect( "dl.high" )
		         ->addSelect( "dl.price_907" )
		         ->addSelect( "dl.borrow_ticket" )
		         ->addSelect( "stocks.type" )
		         ->where( "dl.final", ">=", 10 )
		         ->where( "dl.final", "<=", 170 )
		         ->whereRaw( "dl.agency IS NOT NULL" )
		         ->where( "dl.date", $filter_date )->get();
	}

	public function sell( $stock ) {

		if ( Setting::get( 'server_status' ) == '0' ) {
			return;
		}

		$r = file_get_contents( "http://dev.ml-codesign.com:8083/api/Vendor/login" );
		Log::debug( $r );

		$hasTicket = $stock->borrow_ticket ? "True" : "False";
		$url       = "http://dev.ml-codesign.com:8083/api/Vendor/sell/{$stock->code}?hasTicket={$hasTicket}";
		$r         = file_get_contents( $url );
		Log::debug( $url );
		Log::debug( $r );

		$r = file_get_contents( "http://dev.ml-codesign.com:8083/api/Vendor/logout" );
		Log::debug( $r );
	}

	public function buy( $stock ) {

		if ( Setting::get( 'server_status' ) == '0' ) {
			return;
		}

		$r = file_get_contents( "http://dev.ml-codesign.com:8083/api/Vendor/login" );
		Log::debug( $r );

		$hasTicket = $stock->borrow_ticket ? "True" : "False";
		$url       = "http://dev.ml-codesign.com:8083/api/Vendor/buy/{$stock->code}?hasTicket={$hasTicket}";
		$r         = file_get_contents( $url );
		Log::debug( $url );
		Log::debug( $r );

		$r = file_get_contents( "http://dev.ml-codesign.com:8083/api/Vendor/logout" );
		Log::debug( $r );
	}

	public function exec_on_working_time( $filter_date = null, $callback = null ) {
		$now = new DateTime();

		$holiday = $this->getHoliday();

		//If weekend or holiday
		if ( $now->format( "N" ) >= 6 || in_array( date( "Y-m-d" ), $holiday ) ) {
			return false;
		}

		$start = new DateTime();
		$stop  = new DateTime();

		if ( ! $filter_date ) {
			$filter_date = $now->format( "Y-m-d" );
		}

		$start->setTime( 9, 0, 0 );
		$stop->setTime( 13, 35, 0 );


		while ( $now >= $start && $now <= $stop ) {
			call_user_func( $callback, $filter_date );
		}

		return true;
	}

	public function monitorDL0( $stockPrice ) {

        $current_price = $stockPrice->best_ask_price > 0 ? $stockPrice->best_ask_price : $stockPrice->latest_trade_price;

        if($current_price <= 0) return;

		//Working time
		$generalStock   = GeneralStock::where( "date", $stockPrice->date )->first();
		$yesterdayGeneral = GeneralStock::where( "date", $this->previousDay( $stockPrice->date ) )->first();

		$stockTime           = getdate( $stockPrice->tlong / 1000 );


        $unclosed_order = StockOrder::where( "code", $stockPrice->code )
                                    ->where( "closed", "=", false )
                                    ->where( "order_type", "=", StockOrder::DL0 )
                                    ->where( "deal_type", "=", StockOrder::SHORT_SELL )
                                    ->where( "date", $stockPrice->date )
                                    ->first();

        $previous_order = StockOrder::where( "code", $stockPrice->code )
                                    ->where( "closed", "=", true )
                                    ->where( "order_type", "=", StockOrder::DL0 )
                                    ->where( "deal_type", "=", StockOrder::SHORT_SELL )
                                    ->where( "date", $stockPrice->date )
                                    ->orderByDesc("tlong")
                                    ->first();

		if(!$unclosed_order){


            if(!$previous_order || ($previous_order && $previous_order->profit_percent > 0)){

                if ( $generalStock->general_start > $yesterdayGeneral->today_final ) {

                    //2. only place order between 9:01 and 13:00 current price range <7%
                    if (
                    (
                        ( $stockTime["hours"] == 9 && $stockTime["minutes"] >= 1 ) ||
                        ( $stockTime["hours"] > 9 && $stockTime["hours"] < 13 ) /*||
						( $stockTime["hours"] == 13 && $stockTime["minutes"] <= 5 )*/
                    )

                    ) {

                        #Log::debug(json_encode([$stockPrice->best_ask_price, $stockPrice->yesterday_final, $stockPrice->best_ask_price, $stockPrice->high]));

                        //3. current price <= Y , sell it now
                        if ( $current_price <= $stockPrice->yesterday_final && $current_price < $stockPrice->high ) {

                            #Log::debug("Current general: {$currentGeneral->general_start} | Yesterday General: {$yesterdayGeneral->today_final} | Current price : {$current_price} | Yesterday final: {$stockPrice->yesterday_final} | High: {$stockPrice->high}");

                            StockOrder::create( [
                                "order_type" => StockOrder::DL0,
                                "deal_type"  => StockOrder::SHORT_SELL,
                                "date"       => $stockPrice->date,
                                "tlong"      => $stockPrice->tlong,
                                "code"       => $stockPrice->code,
                                "qty"        => 1,
                                "sell"      => $current_price,
                                "closed"     => false
                            ] );
                            return;


                        }
                    }
                }
            }
        }
        else{



            if ( $unclosed_order->sell > 0 ) {

                $sell_price = $unclosed_order->sell;
                $buy_price  = $current_price;

                //$current_profit = ($sell_price - $buy_price) * 1000 + $fee + $tax;
                //$current_profit_percent = ($current_profit / ( $buy_price  * 1000 - $tax - $fee)) * 100;

                $fee = round( $unclosed_order->sell * 1.425 );
                $tax = round( $unclosed_order->sell * 1.5 );
                $current_profit         = ( $sell_price - $buy_price ) * 1000/* - $fee - $tax*/;
                $current_profit_percent = ( $current_profit / ( $buy_price * 1000) ) * 100;


                Log::debug(json_encode([
                    "time" => "{$stockTime["hours"]}:{$stockTime["minutes"]}",
                    "order_id" => $unclosed_order->id,
                    "code" => $stockPrice->code,
                    "profit_percent" => $current_profit_percent ,
                    "sell_price" => $unclosed_order->sell,
                    "current_price" => $current_price,
                    "yesterday_final" => $stockPrice->yesterday_final,
                ]));

                //parallel order
                if ( ($current_profit_percent >= 1 && $current_price <= 50) ||
                      ($current_profit_percent >= 0.7 && $current_price > 50) ||
                      ($current_profit_percent >= 0.6 && $current_price > 100)
                ) {

                    $total_exists = StockOrder::where( "code", $stockPrice->code )
                                              ->where( "closed", "=", false )
                                              ->where( "order_type", "=", StockOrder::DL0 )
                                              ->where( "deal_type", "=", StockOrder::SHORT_SELL )
                                              ->where( "date", $stockPrice->date )->count();

                    if($total_exists < 2){

                        $currentGeneral = GeneralPrice::where("date", $stockPrice->date)
                            ->orderByDesc("tlong")
                            ->first();

                        $firstGeneral = GeneralPrice::where("date", $stockPrice->date)
                            ->orderBy("tlong")
                            ->first();

                        $previousGeneral = GeneralPrice::where("date", $stockPrice->date)
                            ->where("high", "<", $currentGeneral->high)
                            ->orderByDesc("tlong")
                            ->first();

                        $time_since_first_order = (($stockPrice->tlong - $unclosed_order->tlong)/1000)/60;

                        if($previousGeneral && $currentGeneral->low == $firstGeneral->low && $time_since_first_order >= 15){
                            //Dont place another order
                            Log::debug("No Parallel order:  high: {$previousGeneral->high}/{$currentGeneral->high} | low: {$firstGeneral->low}/{$currentGeneral->low} | time_since_first_order: {$time_since_first_order}");

                        }
                        else{
                            //Place parallel order
                            StockOrder::create( [
                                "order_type" => StockOrder::DL0,
                                "deal_type"  => StockOrder::SHORT_SELL,
                                "date"       => $stockPrice->date,
                                "tlong"      => $stockPrice->tlong,
                                "code"       => $stockPrice->code,
                                "qty"        => 1,
                                "sell"      => $current_price,
                                "closed"     => false
                            ] );
                        }


                    }

                }


                //1. Current profit > 2%
                if ( ($current_profit_percent >= 2 ||
                     ($current_profit_percent >= 1.5 && $current_price > 50) ||
                     ($current_profit_percent >= 1.2 && $current_price > 100))
                ) {
                    $o = $this->buyBack( $unclosed_order, $stockPrice );
                    Log::debug( "Buy back {$o->id}: {$stockPrice->code} at {$stockTime["hours"]}:{$stockTime["minutes"]} Sell Price: {$unclosed_order->sell} | Buy Price: {$current_price}  | current profit percent: {$current_profit_percent} | calculate profit: {$o->profit_percent}" );

                    return;
                }


                if($current_price <= $stockPrice->low){
                    $o = $this->buyBack( $unclosed_order, $stockPrice );
                    Log::debug( "Buy back lowest {$o->id}: {$stockPrice->code} at {$stockTime["hours"]}:{$stockTime["minutes"]} Sell Price: {$unclosed_order->sell} | Buy Price: {$current_price}  | current profit percent: {$current_profit_percent} | calculate profit: {$o->profit_percent}" );
                    return;
                }

                //Profit loss
                if( (!$previous_order && $current_price >= $stockPrice->yesterday_final) ||
                    ($previous_order && $current_price >= $previous_order->sell)
                ){

                    $unclosed_order_time = getdate( $unclosed_order->tlong / 1000 );

                    //Only close if order has been sold at least 3 mins
                    if(
                        $stockTime["hours"] > $unclosed_order_time["hours"] ||
                        ($stockTime["hours"] == $unclosed_order_time["hours"] && $stockTime["minutes"] - $unclosed_order_time["minutes"] >= 3)
                    ){
                        $o = $this->buyBack( $unclosed_order, $stockPrice );
                        Log::debug("Profit Loss 1 {$o->id} {$unclosed_order_time["hours"]}:{$unclosed_order_time["minutes"]} - {$stockTime['hours']}:{$stockTime['minutes']} - {$stockPrice->code} {$current_price}/{$stockPrice->yesterday_final}  Sell Price: {$unclosed_order->sell} | Buy Price: {$current_price}  | current profit percent: {$current_profit_percent}");

                        return;
                    }
                }

                if($current_price >= $previous_order->sell && $current_profit_percent <= 1.2){
                    $o = $this->buyBack( $unclosed_order, $stockPrice );
                    Log::debug("Profit Loss 2 {$o->id} {$unclosed_order_time["hours"]}:{$unclosed_order_time["minutes"]} - {$stockTime['hours']}:{$stockTime['minutes']} - {$stockPrice->code} {$current_price}/{$stockPrice->yesterday_final}  Sell Price: {$unclosed_order->sell} | Buy Price: {$current_price}  | current profit percent: {$current_profit_percent}");

                }


                //close all remain orders
                if ( $stockTime["hours"] >= 13 && $stockTime["minutes"] > 0 ) {
                    if ( $unclosed_order->deal_type == StockOrder::SHORT_SELL ) {
                        $o = $this->buyBack( $unclosed_order, $stockPrice );
                        Log::debug("Clear order {$o->id} at {$stockTime['hours']}:{$stockTime['minutes']} {$unclosed_order->code} Sell Price: {$unclosed_order->sell} | Buy Price: {$current_price}  | current profit percent: {$current_profit_percent}");
                        return;
                    }
                }

            }
        }




	}

}


