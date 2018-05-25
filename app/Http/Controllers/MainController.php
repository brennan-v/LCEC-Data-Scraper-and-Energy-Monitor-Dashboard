<?php

namespace App\Http\Controllers;

use DB;
use DateTime;
use App\Http\Controllers\Controller;
use App\EnergyUsage;
use Goutte\Client;
use Illuminate\Support\Facades\Redis;

class MainController extends Controller
{
    /**
     * Get and show current 7 day trailing history of KWH usage/cost.
     *
     * @param  int  $id
     * @return Response
     */
    public function index()
    {
        /**
         * Scrape Customer LCEC Energy Usage Data
         *
         */
        function scrapeCustomerLCECEnergyUsage(){
            //rate limiter to prevent excessive calls to LCEC website
            //initiate and/or add to scrape counter
            Redis::incr('scrape_count');
            //if scrape_count greater than (or equal) to 3 tries...
            if(Redis::get('scrape_count') > 3 ){
                //if rate_limit_enabled val init'd...
                if(Redis::get('rate_limit_enabled')){
                    //DO NOT init scraper. must wait until rate_limit_enabled has expired.
                    return;
                }
                //if rate_limit_enabled var is not in memory...
                // set rate_limit_enabled var and expirations.
                // default is 60 minutes (3600).
                Redis::set('rate_limit_enabled', true);
                Redis::expire("scrape_count", getenv('RATE_LIMIT_EXPIRATION'));
                Redis::expire("rate_limit_enabled", getenv('RATE_LIMIT_EXPIRATION'));
            }else{
                //scrape data from website.
                $client = new Client(); //init new Goutte (like goot)  web instance
                $crawler = $client->request('GET', 'https://wss.lcec.net/SelfService/SSvcController/login');
                //login actions
                $form = $crawler->selectButton('Button')->form(); //select button to submit form
                $crawler = $client->submit($form, array('userId' => getenv('ACCOUNT_USER_ID'), 'password' => getenv('ACCOUNT_PASSWORD'))); //POST with the authentication credentials from .env file

                $link = $crawler->selectLink(getenv('ACCOUNT_NUMBER'))->link(); //use account number from .env file
                $crawler = $client->click($link); //enter account area

                //now that we're authenticated, change view to energy dashboard
                $crawler = $client->request('GET', 'https://wss.lcec.net/SelfService/SSvcController/ViewUsage?width=540&height=375');

                //get all area elements in page and iterate through each one...
                $crawler->filterXpath("//area")->each(function ($node) {

                    $energy_data_string = $node->attr('title')."<br/>"; //extract title data
                    //if it's a temperature string - don't do anything.
                    //TODO: Get better temperature data from alternate source
                    if(strpos($energy_data_string , 'High') !== false){
                        return;
                    }
                    //kwh
                    $kwh_val = explode("KWH", $energy_data_string, 2);
                    $kwh_val = $kwh_val[0];
                    //$
                    preg_match('/\$(\d\.\d+)/', $energy_data_string, $dollar_matches);
                    $dollar_val = $dollar_matches[1];
                    //date
                    preg_match('/\d{2}\-\d{2}\-\d{4}/',$energy_data_string, $date_matches);
                    $date = $date_matches[0];
                    $date_var = \DateTime::createFromFormat('m-d-Y', $date);
                    $date_val = $date_var->format('Y-m-d');

                    //if date already exists in the database...do not update - skip
                    if(EnergyUsage::where('date', $date_val)->exists()){
                        return;
                    }else{ //if date doesn't exist in database...put it in.
                        $energy_row = new EnergyUsage;
                        $energy_row->kwh_used = $kwh_val;
                        $energy_row->cost = $dollar_val;
                        $energy_row->high_temp = 1;
                        $energy_row->low_temp = 1;
                        $energy_row->average_temp = 1;
                        $energy_row->date = $date_val;
                        $energy_row->save();
                        return;
                    }

                });
            }
        }

        function showLastSevenDayEnergyUsage(){
            $from_unix_time = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
            $day_unix = strtotime("yesterday", $from_unix_time);
            $yesterday = date('Y-m-d', $day_unix);

            $energy_data = EnergyUsage::where('date', '<=', $yesterday)
                ->latest('date')
                ->take(7)
                ->orderBy('date', 'desc')
                ->get();

            $energy_data = $energy_data->reverse();

            $avgKWH = $energy_data->avg('kwh_used');;

            $kwh_used = null;
            $days = null;
            $cost = null;
            $bar_color = null;
            $border_color = null;

            foreach($energy_data as $data){
                //to prevent dox
                if(env("DEMO_MODE")){
                    $variance = rand(1,25);
                    $data->kwh_used = ($data->kwh_used - $variance);
                }
                $kwh_used .= $data->kwh_used.',';

                $dates = \DateTime::createFromFormat('Y-m-d', $data->date);
                $days .= '"'.$dates->format('m-d-Y').'",';

                $cost .=  $data->cost.',';

                if($avgKWH+5 < $data->kwh_used){
                    $bar_color .= '"rgba(255, 99, 132, 0.2)",';
                    $border_color .= '"rgb(255, 99, 132)",';
                }elseif($avgKWH >= $data->kwh_used){
                    $bar_color .= '"rgba(75, 192, 192, 0.2)",';
                    $border_color .= '"rgb(75, 192, 192)",';
                }else{
                    $bar_color .= '"rgba(75, 192, 192, 0.2)",';
                    $border_color .= '"rgb(75, 192, 192)",';
                }

            }
            $data_array = array(
                'kwh_used'=>$kwh_used,
                'dates'=>$days,
                'cost'=>$cost,
                'bar_color'=>$bar_color,
                'border_color'=>$border_color
            );
            return $data_array;
        }
        //Init GET

        //get today's and yesterday's date
        $from_unix_time = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
        $today_unix = strtotime("today", $from_unix_time);
        $day_unix = strtotime("yesterday", $from_unix_time);

        $today = date('Y-m-d', $today_unix);
        $yesterday = date('Y-m-d', $day_unix);

        //IF yesterday's date isn't in the database
        // AND
        // it's after 4pm of today (because LCEC latest energy usage stat has the previous day's data ONLY available usually after 4pm of the next day)
        if(!EnergyUsage::where('date', $yesterday)->exists() && new DateTime() > new DateTime($today.' 16:00:00')){
            var_dump('scrape init');
            scrapeCustomerLCECEnergyUsage();
        }
        //Show the last seven day's KWH usage
        $data_array = showLastSevenDayEnergyUsage();
        //Pass data to view
        return view('index', $data_array);
    }
}