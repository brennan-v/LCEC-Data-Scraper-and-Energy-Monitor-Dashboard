<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Goutte\Client;

class MainController extends Controller
{
    /**
     * Get and show current 30 day trailing history of KWH usage/cost and temperature for that day.
     *
     * @param  int  $id
     * @return Response
     */
    public function index()
    {

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
            print $node->attr('title')."<br/>"; //extract title data
        });
    }
}