<?php namespace ColdTurkey\Valuator;
	
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

// Composer autoloader
require_once VALUATOR_PLUGIN_PATH . 'assets/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Zillow {
	
	protected $api_key;
	protected $api_base;
	protected $guzzle;
	
	public function __construct()
	{
		$this->api_key = get_option('valuator_zillow_key');
		$this->api_base = 'https://www.zillow.com/webservice/';
		$this->guzzle = new Client();
	}
	
	public function getZestimate( $address )
	{
		try
		{
			$address = $this->formatAddress( $address );
			
			if($this->api_key != null || $this->api_key != '')
			{
				$response = $this->guzzle->get($this->api_base . 'GetSearchResults.htm', [
			    'query' => [
		        'zws-id' => $this->api_key,
		        'address' => $address[0],
		        'citystatezip' => $address[1]
			    ]
				]);
				
				return $this->formatZestimate( $response->xml() );
			}
		}
		catch (RequestException $e) {
			return 'error';
    }
	}
	
	protected function formatAddress( $address )
	{
		$formatted_address = array();
		$address_array = explode(', ', $address);
		
		if(count($address_array) == 4)
		{
			// We have a full address, or at least a specific street
			array_push($formatted_address, $address_array[0]);
			array_push($formatted_address, $address_array[1].', '.$address_array[2]);
		}
		
		return $formatted_address;
	}
	
	protected function formatZestimate( $response )
	{
		$address = $response->response->results->result->address;
		$zestimate = $response->response->results->result->zestimate;
		
		return [
			'address' => $address->street . ', ' . $address->city . ', ' . $address->state . ' ' . $address->zipcode,
			'amount' => (string)$zestimate->amount,
			'low' => (string)$zestimate->valuationRange->low,
			'high' => (string)$zestimate->valuationRange->high
		];
		
	}
	
}