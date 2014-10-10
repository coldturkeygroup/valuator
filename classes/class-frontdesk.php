<?php namespace ColdTurkey\Valuator;
	
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

// Composer autoloader
require_once VALUATOR_PLUGIN_PATH . 'assets/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class FrontDesk {
	
	protected $api_key;
	protected $api_version;
	protected $api_base;
	protected $guzzle;
	
	public function __construct( $api_version = 1 )
	{
		$this->api_key = get_option('valuator_frontdesk_key');
		$this->api_version = $api_version;
		$this->api_base = 'https://tryfrontdesk.com/api/v' . $api_version . '/';
		$this->guzzle = new Client();
		
		// Display admin notices when required
		add_action( 'admin_notices', array( $this, 'adminNotices' ) );
	}
	
	public function createCampaign( $title, $permalink )
	{		
		try
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
		
			add_filter( 'redirect_post_location', array( $this, 'add_success_var' ), 99 );
		}
		catch (RequestException $e) {
			add_filter( 'redirect_post_location', array( $this, 'add_error_var' ), 99 );
    }
	}
	
	public function createProspect( $data )
	{
		try
		{
			if($this->api_key != null || $this->api_key != '')
			{
				$response = $this->guzzle->post($this->api_base . 'subscribers/', [
			    'body' => [
		        'key' => $this->api_key,
		        'source' => $data['source'],
		        'email' => $data['email'],
		        'first_name' => $data['first_name'],
		        'last_name' => $data['last_name'],
						'address' => $data['address'],
						'address_2' => $data['address_2'],
						'city' => $data['city'],
						'state' => $data['state'],
						'zip_code' => $data['zip_code']
			    ]
				]);
				
				return $response->json()['data']['id'];
			}
			
			return null;
		}
		catch (RequestException $e) {
			return null;
    }
	}
	
	public function updateSubscriber( $id )
	{
		
	}
	
	public function destroyCampaign( $id )
	{
		
	}
	
	public function add_success_var( $location ) {
   remove_filter( 'redirect_post_location', array( $this, 'add_success_var' ), 99 );
   return add_query_arg( array( 'frontdesk_success' => true ), $location );
  }
	
	public function add_error_var( $location ) {
   remove_filter( 'redirect_post_location', array( $this, 'add_error_var' ), 99 );
   return add_query_arg( array( 'frontdesk_error' => true ), $location );
  }
	
	public function adminNotices()
	{
		if ( isset( $_GET['frontdesk_error'] ) )
			echo '<div class="error">
	      			<p>A Campaign with this URL already exists. No new FrontDesk Campaign has been created.</p>
						</div>';
		
		if ( isset( $_GET['frontdesk_success'] ) )
			echo '<div class="updated">
	      			<p>A Campaign for this page has been successfully setup on FrontDesk!</p>
						</div>';
	}
	
}