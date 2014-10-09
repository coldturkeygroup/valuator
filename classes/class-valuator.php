<?php namespace ColdTurkey\Valuator;
	
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

require_once('class-frontdesk.php');
require_once('class-zillow.php');

use ColdTurkey\Valuator\FrontDesk;
use ColdTurkey\Valuator\Zillow; 

class Valuator {
	private $dir;
	private $file;
	private $assets_dir;
	private $assets_url;
	private $template_path;
	private $token;
	private $home_url;
	private $frontdesk;
	private $zillow;

	public function __construct( $file ) {
		$this->dir = dirname( $file );
		$this->file = $file;
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $file ) ) );
		$this->template_path = trailingslashit( $this->dir ) . 'templates/';
		$this->home_url = trailingslashit( home_url() );
		$this->token = 'valuator';
		$this->frontdesk = new FrontDesk();
		$this->zillow = new Zillow();
		
		global $wpdb;
		$this->table_name = $wpdb->prefix . $this->token;

		// Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		// Regsiter 'valuator' post type
		add_action('init', array( $this , 'register_post_type' ) );

		// Use built-in templates for landing pages
		add_action( 'template_redirect' , array( $this , 'page_templates' ) , 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// Handle form submissions
		add_action( 'wp_ajax_valuator_step_one', array( $this, 'process_step_one' ) );
		add_action( 'wp_ajax_nopriv_valuator_step_one', array( $this, 'process_step_one' ) );
		add_action( 'wp_ajax_valuator_step_two', array( $this, 'process_step_two' ) );
		add_action( 'wp_ajax_nopriv_valuator_step_two', array( $this, 'process_step_two' ) );
		add_action( 'wp_ajax_valuator_step_three', array( $this, 'process_step_three' ) );
		add_action( 'wp_ajax_nopriv_valuator_step_three', array( $this, 'process_step_three' ) );

		if ( is_admin() ) {

			add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ), 10 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 10 );
			add_filter( 'manage_edit-' . $this->token . '_columns', array( $this, 'register_custom_column_headings' ), 10, 1 );
			add_action( 'manage_posts_custom_column', array( $this, 'register_custom_columns' ), 10, 2 );
			// Create FrontDesk Campaigns for pages
			add_action('publish_valuator', array( $this, 'create_frontdesk_campaign' ) );

		}

		// Flush rewrite rules on plugin activation
		register_activation_hook( $file, array( $this, 'rewrite_flush' ) );

	}

	public function rewrite_flush() {
		$this->register_post_type();
		$this->build_database_table();
		flush_rewrite_rules();
	}

	public function register_post_type() {

		$labels = array(
			'name' => _x( 'Home Valuations', 'post type general name' , 'valuator' ),
			'singular_name' => _x( 'Home Valuation', 'post type singular name' , 'valuator' ),
			'add_new' => _x( 'Add New', $this->token , 'valuator' ),
			'add_new_item' => sprintf( __( 'Add New %s' , 'valuator' ), __( 'Page' , 'valuator' ) ),
			'edit_item' => sprintf( __( 'Edit %s' , 'valuator' ), __( 'Page' , 'valuator' ) ),
			'new_item' => sprintf( __( 'New %s' , 'valuator' ), __( 'Page' , 'valuator' ) ),
			'all_items' => sprintf( __( 'All %s' , 'valuator' ), __( 'Pages' , 'valuator' ) ),
			'view_item' => sprintf( __( 'View %s' , 'valuator' ), __( 'Page' , 'valuator' ) ),
			'search_items' => sprintf( __( 'Search %a' , 'valuator' ), __( 'Pages' , 'valuator' ) ),
			'not_found' =>  sprintf( __( 'No %s Found' , 'valuator' ), __( 'Pages' , 'valuator' ) ),
			'not_found_in_trash' => sprintf( __( 'No %s Found In Trash' , 'valuator' ), __( 'Pages' , 'valuator' ) ),
			'parent_item_colon' => '',
			'menu_name' => __( 'Home Valuations' , 'valuator' )

		);

		$slug = __( 'valuations' , 'valuator' );
		$custom_slug = get_option( 'valuator_slug' );
		if( $custom_slug && strlen( $custom_slug ) > 0 && $custom_slug != '' ) {
			$slug = $custom_slug;
		}


		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => $slug , 'feeds' => true ),
			'capability_type' => 'post',
			'has_archive' => true,
			'hierarchical' => false,
			'supports' => array( 'title', 'editor', 'thumbnail' ),
			'menu_position' => 5,
			'menu_icon' => 'dashicons-admin-home'
		);

		register_post_type( $this->token, $args );
	}
	
	public function build_database_table () {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->token;
		
		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
		  $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}
		
		if ( ! empty( $wpdb->collate ) ) {
		  $charset_collate .= " COLLATE {$wpdb->collate}";
		}
		
		$sql = "CREATE TABLE `$table_name` (
							`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
							`first_name` varchar(255) DEFAULT NULL,
							`last_name` varchar(255) DEFAULT NULL,
							`email` varchar(255) DEFAULT NULL,
							`address` varchar(255) NOT NULL,
							`address2` varchar(255) DEFAULT NULL,
							`phone` varchar(20) DEFAULT NULL,
							`reason` varchar(255) DEFAULT NULL,
							`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
							`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
							PRIMARY KEY (`id`),
							UNIQUE KEY `users_email_unique` (`email`)
						) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function register_custom_columns ( $column_name, $id ) {
		global $wpdb, $post;

		$meta = get_post_custom( $id );

		switch ( $column_name ) {
			
			case 'permalink':
				$link  = get_post_permalink( $id );
				$value = '<a href="'.$link.'">'.$link.'</a>';

				echo $value;
			break;

			default:
			break;

		}
	}

	public function register_custom_column_headings ( $defaults ) {
		$new_columns = array( 'permalink' => __( 'Link' , 'valuator' ) );

		$last_item = '';

		if ( isset( $defaults['date'] ) ) { unset( $defaults['date'] ); }

		if ( count( $defaults ) > 2 ) {
			$last_item = array_slice( $defaults, -1 );

			array_pop( $defaults );
		}
		$defaults = array_merge( $defaults, $new_columns );

		if ( $last_item != '' ) {
			foreach ( $last_item as $k => $v ) {
				$defaults[$k] = $v;
				break;
			}
		}

		return $defaults;
	}

	public function updated_messages ( $messages ) {
	  global $post, $post_ID;

	  $messages[$this->token] = array(
	    0 => '', // Unused. Messages start at index 1.
	    1 => sprintf( __( 'Page updated. %sView page%s.' , 'valuator' ), '<a href="' . esc_url( get_permalink( $post_ID ) ) . '">', '</a>' ),
	    4 => __( 'Page updated.' , 'valuator' ),
	    /* translators: %s: date and time of the revision */
	    5 => isset($_GET['revision']) ? sprintf( __( 'Page restored to revision from %s.' , 'valuator' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
	    6 => sprintf( __( 'Page published. %sView page%s.' , 'valuator' ), '<a href="' . esc_url( get_permalink( $post_ID ) ) . '">', '</a>' ),
	    7 => __( 'Page saved.' , 'valuator' ),
	    8 => sprintf( __( 'Page submitted. %sPreview page%s.' , 'valuator' ), '<a target="_blank" href="' . esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) . '">', '</a>' ),
	    9 => sprintf( __( 'Page scheduled for: %1$s. %2$sPreview page%3$s.' , 'valuator' ), '<strong>' . date_i18n( __( 'M j, Y @ G:i' , 'valuator' ), strtotime( $post->post_date ) ) . '</strong>', '<a target="_blank" href="' . esc_url( get_permalink( $post_ID ) ) . '">', '</a>' ),
	    10 => sprintf( __( 'Page draft updated. %sPreview page%s.' , 'valuator' ), '<a target="_blank" href="' . esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) . '">', '</a>' ),
	  );

	  return $messages;
	}

	public function enqueue_admin_styles() {

		// Admin CSS
		wp_register_style( 'valuator-admin', esc_url( $this->assets_url . 'css/admin.css' ), array(), '1.0.0' );
		wp_enqueue_style( 'valuator-admin' );

	}

	public function enqueue_admin_scripts() {

		// Admin JS
		wp_register_script( 'valuator-admin', esc_url( $this->assets_url . 'js/admin.js' ), array( 'jquery' ), '1.0.0' );
		wp_enqueue_script( 'valuator-admin' );

	}

	public function enqueue_scripts() {
		
		wp_register_style( 'valuator', esc_url( $this->assets_url . 'css/style.css' ), array(), '1.0.0' );
		wp_enqueue_style( 'valuator' );
		
		wp_register_script( 'google-places', 'https://maps.googleapis.com/maps/api/js?libraries=places', array( 'jquery' ));
		wp_register_script( 'valuator-js', esc_url( $this->assets_url . 'js/scripts.js' ), array());
		wp_enqueue_script( 'google-places' );
		wp_enqueue_script( 'valuator-js' );
		
		$localize = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		);
		wp_localize_script('valuator-js', 'Valuator', $localize);
		
	}
	
	public function page_templates() {

		// Single home valuation page template
		if( is_single() && get_post_type() == 'valuator' ) {
			include( $this->template_path . 'single-valuation.php' );
			exit;
		}

	}

	public function register_image_sizes() {
		if ( function_exists( 'add_image_size' ) ) {
			add_image_size( 'podcast-thumbnail', 200, 9999 ); // 200 pixels wide (and unlimited height)
		}
	}

	public function ensure_post_thumbnails_support() {
		if ( ! current_theme_supports( 'post-thumbnails' ) ) { add_theme_support( 'post-thumbnails' ); }
	}

	public function load_localisation() {
		load_plugin_textdomain( 'valuator', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}

	public function load_plugin_textdomain() {
	    $domain = 'valuator';
	    // The "plugin_locale" filter is also used in load_plugin_textdomain()
	    $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

	    load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( $this->file ) ) . '/lang/' );
	}
	
	public function create_frontdesk_campaign( $post_ID )
	{
		$title = get_the_title( $post_ID );
		$permalink = get_permalink( $post_ID );
		
		$this->frontdesk->createCampaign( $title, $permalink );
	}
	
	public function process_step_one() 
	{
		if ( isset( $_POST['valuator_nonce'] ) && wp_verify_nonce( $_POST['valuator_nonce'], 'valuator_step_one' ) ) {
			global $wpdb;
			
			$address = sanitize_text_field($_POST['address']);
			$unit = sanitize_text_field($_POST['address_2']);
			
			$wpdb->query( $wpdb->prepare( 
				'INSERT INTO ' . $this->table_name . '
				 ( address, address2, created_at, updated_at )
				 VALUES ( %s, %s, NOW(), NOW() )', 
			  array(
					$address, 
					$unit
				) 
			) );
			
			echo json_encode( array( 'property_id' => $wpdb->insert_id ) );
			die();
		}
	}
	
	public function process_step_two()
	{
		global $wpdb;
		$property_id = sanitize_text_field($_POST['property_id']);
		$first_name = sanitize_text_field($_POST['first_name']);
		$last_name = sanitize_text_field($_POST['last_name']);
		$email = sanitize_text_field($_POST['email']);
		
		// Get the property data saved from step one
		$property = $wpdb->get_row('SELECT address FROM ' . $this->table_name . ' WHERE id = \'' . $property_id . '\' ORDER BY id DESC LIMIT 0,1');
		
		// Get the Zestimate data
		$zestimate = $this->zillow->getZestimate( $property->address );
		
		$wpdb->query( $wpdb->prepare( 
			'UPDATE ' . $this->table_name . '
			 SET first_name = %s, last_name = %s, email = %s
			 WHERE id = \'' . $property_id . '\'', 
		  array(
				$first_name,
				$last_name,
				$email
			) 
		) );
		
		echo json_encode( $zestimate );
		die();
	}

}