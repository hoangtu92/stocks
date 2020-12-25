<?php

namespace App\Jobs\Crawl;

use App\Agent;
use App\Crawler\StockHelper;
use App\Dl;
use DOMDocument;
use Goutte\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Field\InputFormField;


class CrawlAgent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 0;
    public int $tries = 0;
    public int $backoff = 300;
    protected Client $client;
    protected string $filter_date;
    protected array $agent_data = [];

    /**
     * Create a new job instance.
     *
     * @param $filter_date
     */
    public function __construct($filter_date = null)
    {

        if(!$filter_date){
            $filter_date = date("Y-m-d");
        }
        $this->filter_date = $filter_date;

        //
        $this->client = new Client();
        $this->client->setServerParameter('User-Agent', "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.101 Safari/537.36");
        //nancyhsu0511@gmail.com
        //$data = ["email" => "kis77628@gmail.com", "password" => "ASDFvcx2z!"];
        $data = ["email" => "nancyhsu0511@gmail.com", "password" => "ASDFvcx2z!"];

        $crawler = $this->client->request("GET", "https://histock.tw/login");

        if(!$crawler){
            $this->log_file("login", date("Ymd-his"), $crawler->outerHtml());
        }
        else{
            $form = $crawler->selectButton('登入')->form();

            $eventTarget = "bLogin";
            $eventArgument = "";

            $domdocument = new DOMDocument;

            $ff = $domdocument->createElement('input');
            $ff->setAttribute('name', '__EVENTTARGET');
            $ff->setAttribute('value', $eventTarget);
            $formfield = new InputFormField($ff);

            $ff = $domdocument->createElement('input');
            $ff->setAttribute('name', '__EVENTARGUMENT');
            $ff->setAttribute('value', $eventArgument);
            $formfield2 = new InputFormField($ff);

            $form->set($formfield);
            $form->set($formfield2);

            $this->client->submit($form, $data);
        }


    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        #Log::info("Crawling Agency data for ".$this->filter_date);
        //
        $agents = Agent::all("name")->toArray();
        $agents = array_reduce($agents, function ($t, $e){
            $t[] = $e["name"];
            return $t;
        }, []);

        $dls = Dl::where("dl_date", $this->filter_date)->whereRaw("agency = ''")->get();

        foreach ($dls as $dl){
            $stockAgents = $this->get_agent_data($dl->code, $this->filter_date, $agents);
            $dl->update($stockAgents);
        }

    }


    function get_agent_data($stock_code, $date, $filter = []){

        $date = StockHelper::getDate($date);

        $filter_date = "{$date['year']}{$date['month']}{$date['day']}";

        $url = 'https://histock.tw/stock/branch.aspx?'.http_build_query([
                "from" => $filter_date,
                "to" => $filter_date,
                "no"=> $stock_code
            ]);

        # Log::debug($url);

        $crawler = $this->client->request("GET", $url);
        $table = $crawler->filter("table.tb-stock")->last();

        if($table){
            $table->filter("tr")->each(function ($tr, $i){
                if($i > 0 && $i <= 5){
                    $this->agent_data[$i] = [];
                    $tr->filter("td")->each(function ($td, $j) use ($i) {
                        if(in_array($j, [5,8,9])){
                            $this->agent_data[$i][$j] = $td->text();
                        }
                    });
                }
            });
        }

        if(count($this->agent_data) == 0){

            $this->log_file($filter_date, $stock_code, $crawler->outerHtml());
            //Its really empty
            #Log::debug("No agency found: {$stock_code}");
            return ["agency" => "", "total_agency_vol" => 0, "single_agency_vol" => 0, "agency_price" => 0];
        }


        $this->agent_data = array_filter($this->agent_data, function ($e) use ($filter) {
            return is_array($e) && isset($e[5]) && in_array($e[5], $filter);
        });

        if(count($this->agent_data) == 0){
            //No agency match the filter
            #Log::debug("No agency match the filter {$stock_code}");
            #Log::debug("Agent: ".json_encode($this->data));
            #Log::debug("Filter: ". json_encode($filter));
            return ["agency" => null, "total_agency_vol" => 0, "single_agency_vol" => 0, "agency_price" => 0];
        }

        $this->agent_data = array_reduce($this->agent_data, function ($t, $e){
            $t["agency"][] = $e[5];
            $t["total_agency_vol"] += StockHelper::format_number($e[8]);
            $t["single_agency_vol"] =  max(StockHelper::format_number($e[8]), $t["single_agency_vol"]);
            $t["agency_price"] =  max(StockHelper::format_number($e[9]), $t["agency_price"]);

            return $t;
        }, ["agency" => [], "total_agency_vol" => 0, "single_agency_vol" => 0, "agency_price" => 0]);


        $this->agent_data["agency"] = implode(", ", $this->agent_data["agency"]);



        return $this->agent_data;

        /**
         * {
        "1": {
            "5": "摩根大通",
            "8": "4,413",
            "9": "608.27"
            },
        }
         */

    }

    public function log_file($dir, $filename, $content){
        $path = public_path()."/histock/{$dir}";

        if(!file_exists($path)) mkdir($path);

        #file_put_contents("{$path}/{$stock_code}.html",$crawler->outerHtml());
        file_put_contents("{$path}/{$filename}.html",$content);
    }
}

