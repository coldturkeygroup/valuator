<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

class Valuator {
	private $dir;
	private $file;
	private $assets_dir;
	private $assets_url;
	private $template_path;
	private $token;
	private $home_url;

	public function __construct( $file ) {
		$this->dir = dirname( $file );
		$this->file = $file;
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $file ) ) );
		$this->template_path = trailingslashit( $this->dir ) . 'templates/';
		$this->home_url = trailingslashit( home_url() );
		$this->token = 'valuator';

		// Handle localisation
		$this->load_plugin_textdomain();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		// Regsiter 'valuator' post type
		add_action('init', array( $this , 'register_post_type' ) );

		// Use built-in templates for landing pages
		add_action( 'template_redirect' , array( $this , 'page_templates' ) , 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		if ( is_admin() ) {

			add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ), 10 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 10 );
			add_filter( 'manage_edit-' . $this->token . '_columns', array( $this, 'register_custom_column_headings' ), 10, 1 );
			add_action( 'manage_posts_custom_column', array( $this, 'register_custom_columns' ), 10, 2 );
			add_filter( 'manage_edit-series_columns' , array( $this , 'edit_series_columns' ) );
      add_filter( 'manage_series_custom_column' , array( $this , 'add_series_columns' ) , 1 , 3 );

		}

		// Fluch rewrite rules on plugin activation
		register_activation_hook( $file, array( $this, 'rewrite_flush' ) );

	}

	public function rewrite_flush() {
		$this->register_post_type();
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

}