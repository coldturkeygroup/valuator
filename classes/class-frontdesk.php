<?php namespace ColdTurkey\Valuator;
	
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

// Composer autoloader
require_once VALUATOR_PLUGIN_PATH . 'assets/vendor/autoload.php';

use GuzzleHttp\Client;

class FrontDesk {
	
	protected $api_key;
	private $api_version;
	private $api_base;
	private $guzzle;
	
	public function __construct( $api_version = 1 )
	{
		$this->api_key = get_option('valuator_frontdesk_key');
		$this->api_version = $api_version;
		$this->api_base = 'https://tryfrontdesk.com/api/v' . $api_version . '/';
		$this->guzzle = new Client();
	}
	
	public function createCampaign( $title, $permalink )
	{		
		if($this->api_key != null || $this->api_key != '')
			$response = $this->guzzle->post($this->api_base . 'campaigns/', [
		    'body' => [
	        'key' => $this->api_key,
	        'title' => $title,
	        'description' => 'Campaign for Home Valuation page',
	        'total_cost' => '10000',
	        'source' => $permalink
		    ]
			]);
	}
	
	public function createSubscriber()
	{
		
	}
	
	public function updateSubscriber( $id )
	{
		
	}
	
	public function destroyCampaign( $id )
	{
		
	}
	
}