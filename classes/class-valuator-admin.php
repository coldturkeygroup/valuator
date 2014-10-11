<?php namespace ColdTurkey\Valuator;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

class Valuator_Admin {
	private $dir;
	private $file;
	private $assets_dir;
	private $assets_url;
	private $home_url;
	private $token;

	/**
	 * Basic constructor for the Valuator Admin class
	 *
	 * @param string $file
	 */
	public function __construct( $file )
	{
		$this->dir        = dirname( $file );
		$this->file       = $file;
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $file ) ) );
		$this->home_url   = trailingslashit( home_url() );
		$this->token      = 'valuator';

		// Register podcast settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Add settings page to menu
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), array( $this, 'add_settings_link' ) );

		// Load scripts for settings page
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 10 );

		// Display notices in the WP admin
		add_action( 'admin_notices', array( $this, 'admin_notices' ), 10 );
		add_action( 'admin_init', array( $this, 'admin_notice_actions' ), 1 );

	}

	/**
	 * Add the Settings page for the plugin
	 *
	 */
	public function add_menu_item()
	{
		add_submenu_page( 'edit.php?post_type=valuator', 'Home Valuation Settings', 'Settings', 'manage_options', 'valuator_settings', array(
				$this,
				'settings_page'
			) );
	}

	/**
	 * Add the link to our Settings page
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function add_settings_link( $links )
	{
		$settings_link = '<a href="edit.php?post_type=valuator&page=valuator_settings">Settings</a>';
		array_push( $links, $settings_link );

		return $links;
	}

	/**
	 * Register the Javascript files used by
	 * the plugin in the WordPress dashboard
	 *
	 */
	public function enqueue_admin_scripts()
	{
		global $wp_version;
		// Admin JS
		wp_register_script( 'valuator-admin', esc_url( $this->assets_url . 'js/admin.js' ), array( 'jquery' ), '1.7.5' );
		wp_enqueue_script( 'valuator-admin' );

		if ( $wp_version >= 3.5 ) {
			// Media uploader scripts
			wp_enqueue_media();
		}

	}

	/**
	 * Define different notices that can be
	 * displayed to the user in the dashboard
	 *
	 */
	public function admin_notices()
	{
		global $current_user, $wp_version;
		$user_id            = $current_user->ID;
		$zillow_key         = get_option( 'valuator_zillow_key' );
		$hide_zillow_notice = get_user_meta( $user_id, 'valuator_hide_zillow_notice', true );

		// Version notice
		if ( $wp_version < 3.5 ) {
			?>
			<div class="error">
				<p><?php printf( __( '%1$sHome Valuator%2$s requires WordPress 3.5 or above in order to function correctly. You are running v%3$s - please update now.', 'valuator' ), '<strong>', '</strong>', $wp_version ); ?></p>
			</div>
		<?php
		}

		// No API key defined notice
		if ( ( $zillow_key == null || $zillow_key == '' ) && ! $hide_zillow_notice ) {
			?>
			<div class="error">
				<p><?php printf( __( '%1$sHome Valuator%2$s requires you to enter your Zillow API key. Without it, we will be unable to provide values for any homes. Enter the key on the %3$ssettings page%4$s. %5$sHide this notice%6$s', 'valuator' ), '<strong>', '</strong>', '<a href="edit.php?post_type=valuator&page=valuator_settings">', '</a>', '<em><a href="' . add_query_arg( 'valuator_hide_notice', 'zillow' ) . '">', '</a></em>' ); ?></p>
			</div>
		<?php
		}
	}

	/**
	 * Allow users to hide a notice generated
	 * by the plugin without resolving the cause
	 *
	 */
	public function admin_notice_actions()
	{
		if ( isset( $_GET['valuator_hide_notice'] ) ) {
			global $current_user;
			$user_id = $current_user->ID;

			switch ( esc_attr( $_GET['valuator_hide_notice'] ) ) {
				case 'zillow':
					add_user_meta( $user_id, 'valuator_hide_zillow_notice', true );
					break;
			}

		}
	}

	/**
	 * Register the different settings available
	 * to customize the plugin.
	 *
	 */
	public function register_settings()
	{

		// Add settings section
		add_settings_section( 'customize', __( 'Basic Settings', 'valuator' ), array(
				$this,
				'main_settings'
			), 'valuator' );

		// Add settings fields
		add_settings_field( 'valuator_slug', __( 'URL slug for home valuation pages:', 'valuator' ), array(
				$this,
				'slug_field'
			), 'valuator', 'customize' );
		add_settings_field( 'valuator_zillow_key', __( 'Zillow API key:', 'valuator' ), array(
				$this,
				'zillow_key_field'
			), 'valuator', 'customize' );
		add_settings_field( 'valuator_frontdesk_key', __( 'FrontDesk API key:', 'valuator' ), array(
				$this,
				'frontdesk_key_field'
			), 'valuator', 'customize' );

		// Register settings fields
		register_setting( 'valuator', 'valuator_slug', array( $this, 'validate_slug' ) );
		register_setting( 'valuator', 'valuator_zillow_key' );
		register_setting( 'valuator', 'valuator_frontdesk_key' );

		// Allow plugins to add more settings fields
		do_action( 'valuator_settings_fields' );

	}

	/**
	 * Define the main description string
	 * for the Settings page.
	 *
	 */
	public function main_settings()
	{
		echo '<p>' . __( 'These are a few simple settings for settings up your home valuation pages.', 'valuator' ) . '</p>';
	}

	/**
	 * Create the slug field for the Settings page.
	 * The slug field allows users to choose which
	 * subdirectory their valuation pages are nested in.
	 *
	 */
	public function slug_field()
	{

		$option = get_option( 'valuator_slug' );

		$data = 'valuator';
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$data = $option;
		}

		$default_url = $this->home_url . '?post_type=valuations';

		echo '<input id="slug" type="text" name="valuator_slug" value="' . $data . '"/>
				<label for="slug"><span class="description">' . sprintf( __( 'Provide a custom URL slug for the home valuation pages archive and single home valuation pages. You must re-save your %1$spermalinks%2$s after changing this setting.', 'valuator' ), '<a href="' . esc_attr( 'options-permalink.php' ) . '">', '</a>', '<a href="' . esc_url( $default_url ) . '">' . $default_url . '</a>' ) . '</span></label>';
	}

	/**
	 * Validates that a slug has been defined,
	 * and formats it properly as a URL
	 *
	 * @param string $slug
	 *
	 * @return string
	 */
	public function validate_slug( $slug )
	{
		if ( $slug && strlen( $slug ) > 0 && $slug != '' ) {
			$slug = urlencode( strtolower( str_replace( ' ', '-', $slug ) ) );
		}

		return $slug;
	}

	/**
	 * Create the Zillow key field for the Settings page.
	 * The Zillow key field allows users to define their
	 * API key to be used in all Zillow requests.
	 */
	public function zillow_key_field()
	{

		$option = get_option( 'valuator_zillow_key' );

		$data = '';
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$data = $option;
		}

		echo '<input id="zillow_key" type="text" name="valuator_zillow_key" value="' . $data . '"/>
					<label for="zillow_key"><span class="description">' . __( 'Enter your API key generated by Zillow. To sign up for an API key, visit <a href="https://www.zillow.com/webservice/Registration.htm" target="_blank">https://www.zillow.com/webservice/Registration.htm</a> to get started.', 'valuator' ) . '</span></label>';

	}

	/**
	 * Create the FrontDesk key field for the Settings page.
	 * The FrontDesk key field allows users to define their
	 * API key to be used in all FrontDesk requests.
	 */
	public function frontdesk_key_field()
	{

		$option = get_option( 'valuator_frontdesk_key' );

		$data = '';
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$data = $option;
		}

		echo '<input id="frontdesk_key" type="text" name="valuator_frontdesk_key" value="' . $data . '"/>
					<label for="frontdesk_key"><span class="description">' . __( 'Enter your API key generated by FrontDesk. To access your API key, visit <a href="https://tryfrontdesk.com/settings/" target="_blank">https://tryfrontdesk.com/settings/</a>, and choose the Preferences tab.', 'valuator' ) . '</span></label>';

	}

	/**
	 * Define the basic Valuator URL
	 *
	 */
	public function valuator_url()
	{

		$valuator_url = $this->home_url;

		$slug = get_option( 'valuator_slug' );
		if ( $slug && strlen( $slug ) > 0 && $slug != '' ) {
			$valuator_url .= $slug;
		} else {
			$valuator_url .= '?post_type=valuator';
		}

		echo '<a href="' . esc_url( $valuator_url ) . '" target="_blank">' . $valuator_url . '</a>';

	}

	/**
	 * Create the actual HTML structure
	 * for the Settings page for the plugin
	 * 
	 */
	public function settings_page()
	{

		echo '<div class="wrap" id="valuator_settings">
					<h2>' . __( 'Home Valuator Settings', 'valuator' ) . '</h2>
					<form method="post" action="options.php" enctype="multipart/form-data">
						<div class="clear"></div>';

		settings_fields( 'valuator' );
		do_settings_sections( 'valuator' );

		echo '<p class="submit">
							<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings', 'valuator' ) ) . '" />
						</p>
					</form>
			  </div>';
	}

}