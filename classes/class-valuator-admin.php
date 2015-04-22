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
		$this->token      = 'pf_valuator';

		// Register podcast settings
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// Add settings page to menu
		add_action( 'admin_menu', [ $this, 'add_menu_item' ] );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), [ $this, 'add_settings_link' ] );

		// Load scripts for settings page
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ], 10 );

		// Display notices in the WP admin
		add_action( 'admin_notices', [ $this, 'admin_notices' ], 10 );
		add_action( 'admin_init', [ $this, 'admin_notice_actions' ], 1 );

	}

	/**
	 * Add the menu links for the plugin
	 *
	 */
	public function add_menu_item()
	{
		add_submenu_page( 'edit.php?post_type=pf_valuator', 'Leads', 'Leads', 'manage_options', "pf_valuator_leads", [
			$this,
			'leads_page'
		] );

		add_submenu_page( 'edit.php?post_type=pf_valuator', 'Home Valuation Settings', 'Settings', 'manage_options', 'pf_valuator_settings', [
			$this,
			'settings_page'
		] );
	}

	/**
	 * Add the link to our Settings page
	 * from the plugins page
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function add_settings_link( $links )
	{
		$settings_link = '<a href="edit.php?post_type=pf_valuator&page=pf_valuator_settings">Settings</a>';
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
		wp_register_script( 'valuator-admin', esc_url( $this->assets_url . 'js/admin.js' ), [ 'jquery' ], '1.7.5' );
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
		$zillow_key         = get_option( 'pf_valuator_zillow_key' );
		$hide_zillow_notice = get_user_meta( $user_id, 'pf_valuator_hide_zillow_notice', true );

		// Version notice
		if ( $wp_version < 3.5 ) {
			?>
			<div class="error">
				<p><?php printf( __( '%1$sHome Valuator%2$s requires WordPress 3.5 or above in order to function correctly. You are running v%3$s - please update now.', 'pf_valuator' ), '<strong>', '</strong>', $wp_version ); ?></p>
			</div>
		<?php
		}

		// No API key defined notice
		if ( ( $zillow_key == null || $zillow_key == '' ) && ! $hide_zillow_notice ) {
			?>
			<div class="error">
				<p><?php printf( __( '%1$sHome Valuator%2$s requires you to enter your Zillow API key. Without it, we will be unable to provide values for any homes. Enter the key on the %3$ssettings page%4$s. %5$sHide this notice%6$s', 'pf_valuator' ), '<strong>', '</strong>', '<a href="edit.php?post_type=pf_valuator&page=pf_valuator_settings">', '</a>', '<em><a href="' . esc_url( add_query_arg( 'pf_valuator_hide_notice', 'zillow' ) ) . '">', '</a></em>' ); ?></p>
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
		if ( isset( $_GET['pf_valuator_hide_notice'] ) ) {
			global $current_user;
			$user_id = $current_user->ID;

			switch ( esc_attr( $_GET['pf_valuator_hide_notice'] ) ) {
				case 'zillow':
					add_user_meta( $user_id, 'pf_valuator_hide_zillow_notice', true );
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
		add_settings_section( 'customize', __( 'Basic Settings', 'pf_valuator' ), [
			$this,
			'main_settings'
		], 'pf_valuator' );

		// Add settings fields
		add_settings_field( 'pf_valuator_slug', __( 'URL slug for home valuation pages:', 'pf_valuator' ), [
			$this,
			'slug_field'
		], 'pf_valuator', 'customize' );
		add_settings_field( 'pf_valuator_zillow_key', __( 'Zillow API key:', 'pf_valuator' ), [
			$this,
			'zillow_key_field'
		], 'pf_valuator', 'customize' );
		add_settings_field( 'pf_valuator_frontdesk_key', __( 'FrontDesk API key:', 'pf_valuator' ), [
			$this,
			'frontdesk_key_field'
		], 'pf_valuator', 'customize' );

		// Register settings fields
		register_setting( 'pf_valuator', 'pf_valuator_slug', [ $this, 'validate_slug' ] );
		register_setting( 'pf_valuator', 'pf_valuator_zillow_key' );
		register_setting( 'pf_valuator', 'pf_valuator_frontdesk_key' );

		// Allow plugins to add more settings fields
		do_action( 'pf_valuator_settings_fields' );

	}

	/**
	 * Define the main description string
	 * for the Settings page.
	 *
	 */
	public function main_settings()
	{
		echo '<p>' . __( 'These are a few simple settings for setting up your home valuation pages. <br> <a href="https://www.youtube.com/watch?v=eTOaHOoPY_Q" target="_blank">Watch our video about setting up your API keys.</a>', 'pf_valuator' ) . '</p>';
	}

	/**
	 * Create the slug field for the Settings page.
	 * The slug field allows users to choose which
	 * subdirectory their valuation pages are nested in.
	 *
	 */
	public function slug_field()
	{

		$option = get_option( 'pf_valuator_slug' );

		$data = 'pf_valuator';
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$data = $option;
		}

		$default_url = $this->home_url . '?post_type=valuations';

		echo '<input id="slug" type="text" name="pf_valuator_slug" value="' . $data . '"/>
				<label for="slug"><span class="description">' . sprintf( __( 'Provide a custom URL slug for the home valuation pages archive and single home valuation pages.', 'pf_valuator' ) ) . '</span></label>';
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

		$option = get_option( 'pf_valuator_zillow_key' );

		$data = '';
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$data = $option;
		}

		echo '<input id="zillow_key" type="text" name="pf_valuator_zillow_key" value="' . $data . '"/>
					<label for="zillow_key"><span class="description">' . __( 'Enter your API key generated by Zillow. To sign up for an API key, visit <a href="https://www.zillow.com/webservice/Registration.htm" target="_blank">https://www.zillow.com/webservice/Registration.htm</a> to get started.', 'pf_valuator' ) . '</span></label>';

	}

	/**
	 * Create the FrontDesk key field for the Settings page.
	 * The FrontDesk key field allows users to define their
	 * API key to be used in all FrontDesk requests.
	 */
	public function frontdesk_key_field()
	{

		$option = get_option( 'pf_valuator_frontdesk_key' );

		$data = get_option( 'pf_frontdesk_key', '' );
		if ( $option && strlen( $option ) > 0 && $option != '' ) {
			$data = $option;
		}

		echo '<input id="frontdesk_key" type="text" name="pf_valuator_frontdesk_key" value="' . $data . '"/>
					<label for="frontdesk_key"><span class="description">' . __( 'Enter your API key generated by FrontDesk. To access your API key, visit <a href="https://tryfrontdesk.com/account/api" target="_blank">https://tryfrontdesk.com/account/api</a>.', 'pf_valuator' ) . '</span></label>';

	}

	/**
	 * Define the basic Valuator URL
	 *
	 */
	public function pf_valuator_url()
	{

		$pf_valuator_url = $this->home_url;

		$slug = get_option( 'pf_valuator_slug' );
		if ( $slug && strlen( $slug ) > 0 && $slug != '' ) {
			$pf_valuator_url .= $slug;
		} else {
			$pf_valuator_url .= '?post_type=pf_valuator';
		}

		echo '<a href="' . esc_url( $pf_valuator_url ) . '" target="_blank">' . $pf_valuator_url . '</a>';

	}

	/**
	 * Create the actual HTML structure
	 * for the Settings page for the plugin
	 *
	 */
	public function settings_page()
	{
		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == true ) {
			flush_rewrite_rules();
			echo '<div class="updated">
	      			<p>Your settings were successfully updated.</p>
						</div>';
		}

		echo '<div class="wrap" id="pf_valuator_settings">
					<h2>' . __( 'Home Valuator Settings', 'pf_valuator' ) . '</h2>
					<form method="post" action="options.php" enctype="multipart/form-data">
						<div class="clear"></div>';

		settings_fields( 'pf_valuator' );
		do_settings_sections( 'pf_valuator' );

		echo '<p class="submit">
							<input name="Submit" type="submit" class="button-primary" value="' . esc_attr( __( 'Save Settings', 'pf_valuator' ) ) . '" />
						</p>
					</form>
			  </div>';
	}

	/**
	 * Create the actual HTML structure
	 * for the Leads page for the plugin
	 *
	 */
	public function leads_page()
	{
		global $wpdb;
		$blog_id    = get_current_blog_id();
		$table_name = $wpdb->base_prefix . $this->token;

		if ( isset( $_GET['lead_type'] ) && $_GET['lead_type'] == 'complete' ) {
			$leads = $wpdb->get_results( "SELECT DISTINCT * FROM `$table_name` WHERE `blog_id` = '$blog_id' AND `phone` is not null ORDER BY `id` DESC" );
		} else {
			$leads = $wpdb->get_results( "SELECT DISTINCT * FROM `$table_name` WHERE `blog_id` = '$blog_id' ORDER BY `id` DESC" );
		}

		?>
		<div class="wrap" id="pf_valuator_leads">
			<h2>Home Valuator Leads</h2>

			<?php
			if ( isset( $_GET['deleted'] ) && $_GET['deleted'] == true )
				echo '<div class="updated">
	      				<p>The requested leads have been deleted!</p>
							</div>';
			?>

			<ul id="settings-sections" class="subsubsub hide-if-no-js" style="margin-bottom:15px;">
				<li><a class="tab all <? if ( ! isset( $_GET['lead_type'] ) ) {
						echo 'current';
					} ?>" href="edit.php?post_type=pf_valuator&page=pf_valuator_leads">All Leads</a> |
				</li>
				<li><a class="tab <? if ( isset( $_GET['lead_type'] ) ) {
						echo 'current';
					} ?>" href="edit.php?post_type=pf_valuator&page=pf_valuator_leads&lead_type=complete">Complete Leads</a></li>
			</ul>

			<form id="leads_form" method="post" action="admin-post.php">
				<input type="hidden" name="action" value="pf_valuator_remove_leads">
				<?php wp_nonce_field( 'pf_valuator_remove_leads' ); ?>
				<table class="widefat fixed" style="margin-bottom:5px" cellspacing="0">
					<thead>
					<tr>
						<th scope="col" class="manage-column entry_nowrap" style="width: 2.2em;"></th>
						<th scope="col" class="manage-column entry_nowrap">Name</th>
						<th scope="col" class="manage-column entry_nowrap">Email</th>
						<th scope="col" class="manage-column entry_nowrap">Address</th>
						<th scope="col" class="manage-column entry_nowrap" style="width: 4em;">Unit #</th>
						<th scope="col" class="manage-column entry_nowrap">Phone</th>
						<th scope="col" class="manage-column entry_nowrap">Estimated Value</th>
						<th scope="col" class="manage-column entry_nowrap">Submitted</th>
					</tr>
					</thead>
					<tbody class="user-list">
					<?php
					$i = 0;
					foreach ( $leads as $lead ) {
						$name = $lead->first_name;
						if ( $lead->last_name != null )
							$name .= ' ' . $lead->last_name;
						if ( $i % 2 === 0
							? $alternate = ''
							: $alternate = 'alternate'
						) ;
						$i ++;

						echo '<tr class="author-self status-inherit lead_unread ' . $alternate . '" valign="top">
				      					<td><input type="checkbox" name="delete_lead[]" value="' . $lead->id . '"></td>
				      					<td class="entry_nowrap">' . $name . '</td>
				      					<td class="entry_nowrap">' . $lead->email . '</td>
				      					<td class="entry_nowrap">' . $lead->address . '</td>
				      					<td class="entry_nowrap">' . $lead->address2 . '</td>
				      					<td class="entry_nowrap">' . $lead->phone . '</td>
				      					<td class="entry_nowrap">' . $lead->property_estimate . '</td>
				      					<td class="entry_nowrap">' . date( "M j Y, h:i:a", strtotime( $lead->created_at ) ) . '</td>
				      				</tr>';
					}
					?>
					</tbody>
					<tfoot>
					<tr>
						<th scope="col" class="manage-column entry_nowrap"></th>
						<th scope="col" class="manage-column entry_nowrap">Name</th>
						<th scope="col" class="manage-column entry_nowrap">Email</th>
						<th scope="col" class="manage-column entry_nowrap">Address</th>
						<th scope="col" class="manage-column entry_nowrap">Unit #</th>
						<th scope="col" class="manage-column entry_nowrap">Phone</th>
						<th scope="col" class="manage-column entry_nowrap">Estimated Value</th>
						<th scope="col" class="manage-column entry_nowrap">Submitted</th>
					</tr>
					</tfoot>
				</table>
				<input type="submit" class="button" value="Delete Selected Leads">
			</form>
		</div>
	<?php
	}

}