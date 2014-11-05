<?php namespace ColdTurkey\Valuator;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

require_once( 'class-frontdesk.php' );
require_once( 'class-zillow.php' );

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

	/**
	 * Basic constructor for the Valuator class
	 *
	 * @param string $file
	 */
	public function __construct( $file )
	{
		$this->dir           = dirname( $file );
		$this->file          = $file;
		$this->assets_dir    = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url    = esc_url( trailingslashit( plugins_url( '/assets/', $file ) ) );
		$this->template_path = trailingslashit( $this->dir ) . 'templates/';
		$this->home_url      = trailingslashit( home_url() );
		$this->token         = 'valuator';
		$this->frontdesk     = new FrontDesk();
		$this->zillow        = new Zillow();

		global $wpdb;
		$this->table_name = $wpdb->base_prefix . $this->token;

		// Regsiter 'valuator' post type
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Use built-in templates for landing pages
		add_action( 'template_redirect', array( $this, 'page_templates' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Handle form submissions
		add_action( 'wp_ajax_valuator_step_one', array( $this, 'process_step_one' ) );
		add_action( 'wp_ajax_nopriv_valuator_step_one', array( $this, 'process_step_one' ) );
		add_action( 'wp_ajax_valuator_step_two', array( $this, 'process_step_two' ) );
		add_action( 'wp_ajax_nopriv_valuator_step_two', array( $this, 'process_step_two' ) );
		add_action( 'wp_ajax_valuator_step_three', array( $this, 'process_step_three' ) );
		add_action( 'wp_ajax_nopriv_valuator_step_three', array( $this, 'process_step_three' ) );
		add_action( 'admin_post_valuator_remove_leads', array( $this, 'remove_leads' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'meta_box_setup' ), 20 );
			add_action( 'save_post', array( $this, 'meta_box_save' ) );
			add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ), 10 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 10 );
			add_filter( 'manage_edit-' . $this->token . '_columns', array(
					$this,
					'register_custom_column_headings'
				), 10, 1 );
			add_action( 'manage_posts_custom_column', array( $this, 'register_custom_columns' ), 10, 2 );
			// Create FrontDesk Campaigns for pages
			add_action( 'publish_valuator', array( $this, 'create_frontdesk_campaign' ) );
		}

		// Flush rewrite rules on plugin activation
		register_activation_hook( $file, array( $this, 'rewrite_flush' ) );

	}

	/**
	 * Functions to be called when the plugin is
	 * deactivated and then reactivated.
	 *
	 */
	public function rewrite_flush()
	{
		$this->register_post_type();
		$this->build_database_table();
		flush_rewrite_rules();
	}

	/**
	 * Registers the Valuator custom post type
	 * with WordPress, used for our pages.
	 *
	 */
	public function register_post_type()
	{

		$labels = array(
			'name'               => _x( 'Home Valuations', 'post type general name', 'valuator' ),
			'singular_name'      => _x( 'Home Valuation', 'post type singular name', 'valuator' ),
			'add_new'            => _x( 'Add New', $this->token, 'valuator' ),
			'add_new_item'       => sprintf( __( 'Add New %s', 'valuator' ), __( 'Page', 'valuator' ) ),
			'edit_item'          => sprintf( __( 'Edit %s', 'valuator' ), __( 'Page', 'valuator' ) ),
			'new_item'           => sprintf( __( 'New %s', 'valuator' ), __( 'Page', 'valuator' ) ),
			'all_items'          => sprintf( __( 'All %s', 'valuator' ), __( 'Pages', 'valuator' ) ),
			'view_item'          => sprintf( __( 'View %s', 'valuator' ), __( 'Page', 'valuator' ) ),
			'search_items'       => sprintf( __( 'Search %a', 'valuator' ), __( 'Pages', 'valuator' ) ),
			'not_found'          => sprintf( __( 'No %s Found', 'valuator' ), __( 'Pages', 'valuator' ) ),
			'not_found_in_trash' => sprintf( __( 'No %s Found In Trash', 'valuator' ), __( 'Pages', 'valuator' ) ),
			'parent_item_colon'  => '',
			'menu_name'          => __( 'Home Valuations', 'valuator' )

		);

		$slug        = __( 'valuations', 'valuator' );
		$custom_slug = get_option( 'valuator_slug' );
		if ( $custom_slug && strlen( $custom_slug ) > 0 && $custom_slug != '' ) {
			$slug = $custom_slug;
		}


		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => $slug, 'feeds' => true ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail' ),
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-admin-home'
		);

		register_post_type( $this->token, $args );
	}

	/**
	 * Construct the actual database table that
	 * will be used with all of the pages for
	 * this plugin. The table stores data
	 * from visitors and form submissions.
	 *
	 */
	public function build_database_table()
	{
		global $wpdb;
		$table_name = $wpdb->base_prefix . $this->token;
		
		if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) 
		{
			$charset_collate = '';
	
			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
			}
	
			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE {$wpdb->collate}";
			}
	
			$sql = "CREATE TABLE `$table_name` (
								`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
								`frontdesk_id` int(10) unsigned DEFAULT NULL,
								`blog_id` int(10) unsigned DEFAULT 0,
								`first_name` varchar(255) DEFAULT NULL,
								`last_name` varchar(255) DEFAULT NULL,
								`email` varchar(255) DEFAULT NULL,
								`address` varchar(255) NOT NULL,
								`address2` varchar(255) DEFAULT NULL,
								`phone` varchar(20) DEFAULT NULL,
								`property_estimate` varchar(20) DEFAULT NULL,
								`created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
								`updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
								PRIMARY KEY (`id`)
							) $charset_collate;";
	
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
	}

	/**
	 * Register the custom columns that are displayed
	 * in the All Pages view for our custom post type.
	 *
	 * @param string $column_name
	 * @param integer $id
	 */
	public function register_custom_columns( $column_name, $id )
	{
		switch ( $column_name ) {

			case 'permalink':
				$link  = get_post_permalink( $id );
				$value = '<a href="' . $link . '">' . $link . '</a>';

				echo $value;
				break;

			default:
				break;

		}
	}

	/**
	 * Register the headings for our defined custom columns
	 *
	 * @param array $defaults
	 *
	 * @return array
	 */
	public function register_custom_column_headings( $defaults )
	{
		$new_columns = array( 'permalink' => __( 'Link', 'valuator' ) );

		$last_item = '';

		if ( isset( $defaults['date'] ) ) {
			unset( $defaults['date'] );
		}

		if ( count( $defaults ) > 2 ) {
			$last_item = array_slice( $defaults, - 1 );

			array_pop( $defaults );
		}
		$defaults = array_merge( $defaults, $new_columns );

		if ( $last_item != '' ) {
			foreach ( $last_item as $k => $v ) {
				$defaults[ $k ] = $v;
				break;
			}
		}

		return $defaults;
	}

	/**
	 * Define the strings that will be displayed
	 * for users based on different actions they
	 * perform with the plugin in the dashboard.
	 *
	 * @param array $messages
	 *
	 * @return array
	 */
	public function updated_messages( $messages )
	{
		global $post, $post_ID;

		$messages[ $this->token ] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => sprintf( __( 'Page updated. %sView page%s.', 'valuator' ), '<a href="' . esc_url( get_permalink( $post_ID ) ) . '">', '</a>' ),
			4  => __( 'Page updated.', 'valuator' ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Page restored to revision from %s.', 'valuator' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => sprintf( __( 'Page published. %sView page%s.', 'valuator' ), '<a href="' . esc_url( get_permalink( $post_ID ) ) . '">', '</a>' ),
			7  => __( 'Page saved.', 'valuator' ),
			8  => sprintf( __( 'Page submitted. %sPreview page%s.', 'valuator' ), '<a target="_blank" href="' . esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) . '">', '</a>' ),
			9  => sprintf( __( 'Page scheduled for: %1$s. %2$sPreview page%3$s.', 'valuator' ), '<strong>' . date_i18n( __( 'M j, Y @ G:i', 'valuator' ), strtotime( $post->post_date ) ) . '</strong>', '<a target="_blank" href="' . esc_url( get_permalink( $post_ID ) ) . '">', '</a>' ),
			10 => sprintf( __( 'Page draft updated. %sPreview page%s.', 'valuator' ), '<a target="_blank" href="' . esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) . '">', '</a>' ),
		);

		return $messages;
	}

	/**
	 * Build the meta box containing our custom fields
	 * for our Valuator post type creator & editor.
	 *
	 */
	public function meta_box_setup()
	{
		add_meta_box( 'valuation-data', __( 'Valuation Page Details', 'valuator' ), array(
			$this,
			'meta_box_content'
		), $this->token, 'normal', 'high' );

		do_action( 'valuator_meta_boxes' );
	}

	/**
	 * Build the custom fields that will be displayed
	 * in the meta box for our Valuator post type.
	 *
	 */
	public function meta_box_content()
	{
		global $post_id;
		$fields     = get_post_custom( $post_id );
		$field_data = $this->get_custom_fields_settings();

		$html = '';

		$html .= '<input type="hidden" name="valuator_' . $this->token . '_nonce" id="valuator_' . $this->token . '_nonce" value="' . wp_create_nonce( plugin_basename( $this->dir ) ) . '" />';

		if ( 0 < count( $field_data ) ) {
			$html .= '<table class="form-table">' . "\n";
			$html .= '<tbody>' . "\n";

			$html .= '<input id="valuator_post_id" type="hidden" value="' . $post_id . '" />';

			foreach ( $field_data as $k => $v ) {
				$data = $v['default'];
				if ( isset( $fields[ $k ] ) && isset( $fields[ $k ][0] ) ) {
					$data = $fields[ $k ][0];
				}

				if ( $k == 'media_file' ) {
					$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td><input type="button" class="button" id="upload_media_file_button" value="' . __( 'Upload Image', 'valuator' ) . '" data-uploader_title="Choose an image" data-uploader_button_text="Insert image file" /><input name="' . esc_attr( $k ) . '" type="text" id="upload_media_file" class="regular-text" value="' . esc_attr( $data ) . '" />' . "\n";
					$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
					$html .= '</td><tr/>' . "\n";
				} elseif ( $k == 'media_text' ) {
					$rows = '8';
					$data = stripslashes( $data );
					$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td><textarea style="width:100%" name="' . esc_attr( $k ) . '" id="media_text" rows="' . $rows . '">' . esc_textarea( $data ) . '</textarea>' . "\n";
					$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
					$html .= '</td><tr/>' . "\n";
				} else {
					$default_color = '';
					$html .= '<tr valign="top"><th scope="row"><label for="' . esc_attr( $k ) . '">' . $v['name'] . '</label></th><td>';
					$html .= '<input name="' . esc_attr( $k ) . '" id="primary_color" class="valuator-color"  type="text" value="' . esc_attr( $data ) . '"' . $default_color . ' />';
					$html .= '<p class="description">' . $v['description'] . '</p>' . "\n";
					$html .= '</td><tr/>' . "\n";
				}

				$html .= '</td><tr/>' . "\n";
			}

			$html .= '</tbody>' . "\n";
			$html .= '</table>' . "\n";
		}

		echo $html;
	}

	/**
	 * Save the data entered by the user using
	 * the custom fields for our Valuator post type.
	 *
	 * @param integer $post_id
	 */
	public function meta_box_save( $post_id )
	{
		// Verify
		if ( ( get_post_type() != $this->token ) || ! wp_verify_nonce( $_POST[ 'valuator_' . $this->token . '_nonce' ], plugin_basename( $this->dir ) ) ) {
			return $post_id;
		}

		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return $post_id;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
			}
		}

		$field_data = $this->get_custom_fields_settings();
		$fields     = array_keys( $field_data );

		foreach ( $fields as $f ) {

			if ( isset( $_POST[ $f ] ) ) {
				${$f} = strip_tags( trim( $_POST[ $f ] ) );
			}

			// Escape the URLs.
			if ( 'url' == $field_data[ $f ]['type'] ) {
				${$f} = esc_url( ${$f} );
			}

			if ( ${$f} == '' ) {
				delete_post_meta( $post_id, $f, get_post_meta( $post_id, $f, true ) );
			} else {
				update_post_meta( $post_id, $f, ${$f} );
			}
		}

	}

	/**
	 * Register the stylesheets that will be
	 * used for our scripts in the dashboard.
	 *
	 */
	public function enqueue_admin_styles()
	{

		wp_enqueue_style( 'wp-color-picker' );

	}

	/**
	 * Register the Javascript files that will be
	 * used for our scripts in the dashboard.
	 */
	public function enqueue_admin_scripts()
	{

		// Admin JS
		wp_register_script( 'valuator-admin', esc_url( $this->assets_url . 'js/admin.js' ), array(
			'jquery',
			'wp-color-picker'
		), '1.0.0' );
		wp_enqueue_script( 'valuator-admin' );

	}

	/**
	 * Register the Javascript files that will be
	 * used for our templates.
	 */
	public function enqueue_scripts()
	{

		wp_register_style( 'valuator', esc_url( $this->assets_url . 'css/style.css' ), array(), '1.2.0' );
		wp_register_style( 'animate', esc_url( $this->assets_url . 'css/animate.css' ), array(), '1.2.0' );
		wp_register_style( 'roboto', 'http://fonts.googleapis.com/css?family=Roboto:400,400italic,500,500italic,700,700italic,900,900italic,300italic,300' );
		wp_register_style( 'robo-slab', 'http://fonts.googleapis.com/css?family=Roboto+Slab:400,700,300,100' );
		wp_enqueue_style( 'valuator' );
		wp_enqueue_style( 'animate' );
		wp_enqueue_style( 'roboto' );
		wp_enqueue_style( 'roboto-slab' );

		wp_register_script( 'google-places', 'https://maps.googleapis.com/maps/api/js?libraries=places', array( 'jquery' ) );
		wp_register_script( 'valuator-js', esc_url( $this->assets_url . 'js/scripts.js' ), array() );
		wp_enqueue_script( 'google-places' );
		wp_enqueue_script( 'valuator-js' );

		$localize = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		);
		wp_localize_script( 'valuator-js', 'Valuator', $localize );

	}

	/**
	 * Define the custom fields that will
	 * be displayed and used for our
	 * Valuator post type.
	 *
	 * @return mixed
	 */
	public function get_custom_fields_settings()
	{
		$fields = array();

		$fields['media_file'] = array(
			'name'        => __( 'Media file:', 'valuator' ),
			'description' => __( 'If using an image on the final opt-in page, upload it here. If using a YouTube video (recommended), paste the link to the video here instead.', 'valuator' ),
			'type'        => 'url',
			'default'     => '',
			'section'     => 'info'
		);

		$fields['media_text'] = array(
			'name'        => __( 'Opt-In Text:', 'valuator' ),
			'description' => __( 'If using an image on the final opt-in page, enter the block of text that will be displayed under it. If using a video, no text will be displayed.', 'valuator' ),
			'type'        => 'text',
			'default'     => '',
			'section'     => 'info'
		);

		$fields['primary_color'] = array(
			'name'        => __( 'Primary Color', 'valuator' ),
			'description' => __( 'Change the primary color of the valuation page.', 'valuator' ),
			'type'        => 'color',
			'default'     => '',
			'section'     => 'info'
		);

		$fields['hover_color'] = array(
			'name'        => __( 'Hover Color', 'valuator' ),
			'description' => __( 'Change the button hover color of the valuation page.', 'valuator' ),
			'type'        => 'color',
			'default'     => '',
			'section'     => 'info'
		);

		return apply_filters( 'valuator_valuation_fields', $fields );
	}

	/**
	 * Define the custom templates that
	 * are used for our plugin.
	 *
	 */
	public function page_templates()
	{

		// Single home valuation page template
		if ( is_single() && get_post_type() == 'valuator' ) {
			include( $this->template_path . 'single-valuation.php' );
			exit;
		}

	}

	/**
	 * Get the optional media file selected for
	 * a defined Valuator page, and differentiate
	 * between a supplied video link or image file.
	 *
	 * @param integer $pageID
	 *
	 * @return bool|string
	 */
	public function get_media_file( $pageID )
	{

		if ( $pageID ) {
			$file = get_post_meta( $pageID, 'media_file', true );

			if ( preg_match( '/(\.jpg|\.png|\.bmp|\.gif)$/', $file ) ) {
				return '<img src="' . $file . '" style="margin-left:auto;margin-right:auto;margin-bottom:0px;display:block;" class="img-responsive img-thumbnail">';
			} elseif ( preg_match( '/(youtube|youtu.be|vimeo)/', $file ) ) {
				$video = $this->prepare_video( $file );

				return '<div class="embed-responsive embed-responsive-16by9"><iframe class="embed-responsive-item" src="' . $video . '"></iframe></div>';
			}
		}

		return false;

	}

	/**
	 * Take a defined online video link,
	 * and convert it to work as an
	 * embedded video file.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function prepare_video( $url )
	{
		if ( strpos( $url, 'youtube-nocookie.com' ) !== false || strpos( $url, 'player.vimeo.com' ) !== false )
			return $url;

		if ( strpos( $url, '&' ) !== false )
			$url = substr( $url, 0, strpos( $url, "&" ) );
		if ( strpos( $url, '#' ) !== false )
			$url = substr( $url, 0, strpos( $url, "#" ) );

		if ( strpos( $url, 'youtube' ) !== false ) {
			$video_id = substr( strrchr( $url, '=' ), 1 );

			return '//www.youtube-nocookie.com/embed/' . $video_id . '?rel=0&autohide=1&fs=0&showinfo=0&autoplay=1';
		} else if ( strpos( $url, 'youtu.be' ) !== false ) {
			if ( strpos( $url, '?' ) !== false )
				$url = substr( $url, 0, strpos( $url, "?" ) );
			$video_id = substr( strrchr( $url, '/' ), 1 );

			return '//www.youtube-nocookie.com/embed/' . $video_id . '?rel=0&autohide=1&fs=0&showinfo=0&autoplay=1';
		} else if ( strpos( $url, 'vimeo' ) !== false ) {
			$video_id = substr( strrchr( $url, '/' ), 1 );

			return '//player.vimeo.com/video/' . $video_id . '?autoplay=1';
		}
	}

	/**
	 * Create a campaign on tryfrontdesk.com
	 * for a defined Valuator created page.
	 *
	 * @param integer $post_ID
	 */
	public function create_frontdesk_campaign( $post_ID )
	{
		$title     = get_the_title( $post_ID );
		$permalink = get_permalink( $post_ID );

		$this->frontdesk->createCampaign( $title, $permalink );
	}

	/**
	 * Perform the required actions for the first
	 * step of the Valuator template page.
	 * Create a DB record for the user, and return the ID.
	 *
	 * @return json
	 */
	public function process_step_one()
	{
		if ( isset( $_POST['valuator_nonce'] ) && wp_verify_nonce( $_POST['valuator_nonce'], 'valuator_step_one' ) ) {
			global $wpdb;
			$blog_id = get_current_blog_id();

			$address = sanitize_text_field( $_POST['address'] );
			$unit    = sanitize_text_field( $_POST['address_2'] );

			$wpdb->query( $wpdb->prepare(
				'INSERT INTO ' . $this->table_name . '
				 ( blog_id, address, address2, created_at, updated_at )
				 VALUES ( %s, %s, %s, NOW(), NOW() )',
				array(
					$blog_id,
					$address,
					$unit
				)
			) );

			echo json_encode( array( 'property_id' => $wpdb->insert_id ) );
			die();
		}
	}

	/**
	 * Perform the required actions for the second
	 * step of the Valuator template page.
	 * Update the user record with the newly given data.
	 * Get a Zillow Zestimate for the given address.
	 * Create a prospect on tryfrontdesk.com with the given data.
	 *
	 * @return json
	 */
	public function process_step_two()
	{
		global $wpdb;
		$blog_id		 = get_current_blog_id();
		$page_id     = sanitize_text_field( $_POST['page_id'] );
		$property_id = sanitize_text_field( $_POST['property_id'] );
		$first_name  = sanitize_text_field( $_POST['first_name'] );
		$last_name   = sanitize_text_field( $_POST['last_name'] );
		$email       = sanitize_text_field( $_POST['email'] );
		$source      = sanitize_text_field( $_POST['permalink'] );

		// Get the property data saved from step one
		$property = $wpdb->get_row( 'SELECT address, address2 FROM ' . $this->table_name . ' WHERE id = \'' . $property_id . '\' ORDER BY id DESC LIMIT 0,1' );
		
		// Get the Zestimate data
		$zestimate = $this->zillow->getZestimate( $property->address );

		// Add the media file to the response
		$zestimate['media'] = $this->get_media_file( $page_id );

		// Add the media text to the response if required
		if ( strpos( $zestimate['media'], '<img' ) !== false ) {
			$zestimate['text'] = get_post_meta( $page_id, 'media_text', true );
		}
		
		// Verify that the property had a result
		if ( array_key_exists('error', $zestimate) )
		{
			// Update the prospect data
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . $this->table_name . '
				 SET first_name = %s, last_name = %s, email = %s, property_estimate = %s
				 WHERE id = \'' . $property_id . '\'',
				array(
					$first_name,
					$last_name,
					$email,
					'No Result'
				)
			) );
			
			echo json_encode( $zestimate );
			die();
		}
		
		// Create the prospect on FrontDesk
		$frontdesk_id = $this->frontdesk->createProspect( array(
			'source'     => $source,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'email'      => $email,
			'address'    => $zestimate['street'],
			'address_2'  => $property->address2,
			'city'       => $zestimate['city'],
			'state'      => $zestimate['state'],
			'zip_code'   => $zestimate['zip_code']
		) );

		if ( $frontdesk_id != null ) {
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . $this->table_name . '
				 SET frontdesk_id = %s
				 WHERE id = \'' . $property_id . '\'',
				array(
					$frontdesk_id
				)
			) );
		}

		// Update the prospect data
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . $this->table_name . '
			 SET first_name = %s, last_name = %s, email = %s, address = %s, property_estimate = %s
			 WHERE id = \'' . $property_id . '\'',
			array(
				$first_name,
				$last_name,
				$email,
				(string) $zestimate['address'],
				(string) $zestimate['amount']
			)
		) );

		echo json_encode( $zestimate );
		die();
	}

	/**
	 * Perform the required actions for the third
	 * and final step of the Valuator template page.
	 * Update the user record with the newly given data.
	 * Update the existing prospect on tryfrontdesk.com with the given data.
	 *
	 * @return json
	 */
	public function process_step_three()
	{
		global $wpdb;
		$property_id = sanitize_text_field( $_POST['property_id'] );
		$phone       = sanitize_text_field( $_POST['phone'] );

		// Get the property data saved from step two
		$subscriber = $wpdb->get_row( 'SELECT frontdesk_id, email FROM ' . $this->table_name . ' WHERE id = \'' . $property_id . '\' ORDER BY id DESC LIMIT 0,1' );

		// Update the FrontDesk prospect if exists
		if ( $subscriber->frontdesk_id != null ) {
			$this->frontdesk->updateProspect( $subscriber->frontdesk_id, array(
				'email' => $subscriber->email,
				'phone' => $phone
			) );
		}

		// Update the prospect data
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . $this->table_name . '
			 SET phone = %s
			 WHERE id = \'' . $property_id . '\'',
			array(
				$phone
			)
		) );

		echo json_encode( array( 'success' => true ) );
		die();
	}
	
	public function remove_leads()
	{
		global $wpdb;
		$leads_to_delete = implode(',', $_POST['delete_lead']);
		
		// Update the prospect data
		$wpdb->query( $wpdb->prepare(
			'DELETE FROM `' . $this->table_name . '`
			 WHERE `id` IN (' . $leads_to_delete . ')'
		) );
		
		wp_redirect(  admin_url( 'edit.php?post_type=valuator&page=valuator_leads&deleted=true' ) );
		die();
	}

}