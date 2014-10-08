<?php
/*
 * Plugin Name: Valuator
 * Version: 1.0
 * Plugin URI: http://www.coldturkrygroup.com/
 * Description: Home Valuator plugin that creates landing pages and allows users to get data from Zillow API.
 * Author: Aaron Huisinga
 * Author URI: http://www.coldturkeygroup.com/
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: valuator
 * Domain Path: /lang/
 *
 * @package Valuator
 * @author Aaron Huisinga
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'VALUATOR_PLUGIN_PATH' ) )
	define( 'VALUATOR_PLUGIN_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );

//require_once( 'valuator-functions.php' );
require_once( 'classes/class-valuator.php' );

global $valuator;
$valuator = new ColdTurkey\Valuator\Valuator( __FILE__ );

if( is_admin() ) {
	require_once( 'classes/class-valuator-admin.php' );
	$valuator_admin = new ColdTurkey\Valuator\Valuator_Admin( __FILE__ );
}

?>