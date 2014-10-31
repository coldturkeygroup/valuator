<?php
/*
 * Plugin Name: Valuator
 * Version: 1.3.2
 * Plugin URI: http://www.coldturkeygroup.com/
 * Description: Home Valuation plugin that creates landing pages and allows visitors to get valuation data from Zillow API.
 * Author: Cold Turkey Group
 * Author URI: http://www.coldturkeygroup.com/
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * @package Valuator
 * @author Aaron Huisinga
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'VALUATOR_PLUGIN_PATH' ) )
	define( 'VALUATOR_PLUGIN_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );

require_once( 'classes/class-valuator.php' );

global $valuator;
$valuator = new ColdTurkey\Valuator\Valuator( __FILE__ );

if ( is_admin() ) {
	require_once( 'classes/class-valuator-admin.php' );
	$valuator_admin = new ColdTurkey\Valuator\Valuator_Admin( __FILE__ );
}
