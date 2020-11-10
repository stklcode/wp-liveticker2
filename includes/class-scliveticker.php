<?php
/**
 * Liveticker: Plugin main class.
 *
 * This file contains the plugin's base class.
 *
 * @package SCLiveticker
 */

namespace SCLiveticker;

use WP_Query;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Liveticker.
 */
class SCLiveticker {
	/**
	 * Options tag.
	 *
	 * @var string OPTIONS
	 */
	const VERSION = '1.2.0';

	/**
	 * Options tag.
	 *
	 * @var string OPTIONS
	 */
	const OPTION = 'stklcode-liveticker';

	/**
	 * Plugin options.
	 *
	 * @var array $options
	 */
	protected static $options;

	/**
	 * Marker if shortcode is present.
	 *
	 * @var boolean $shortcode_present
	 */
	protected static $shortcode_present = false;


	/**
	 * Marker if widget is present.
	 *
	 * @var boolean $shortcode_present
	 */
	protected static $widget_present = false;

	/**
	 * Plugin initialization.
	 *
	 * @return void
	 */
	public static function init() {
		// Skip on autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Load plugin options.
		self::update_options();

		// Add filter for REST API queries (TODO: conditionally).
		add_filter( 'rest_api_init', array( 'SCLiveticker\\Api', 'init' ) );
		add_filter( 'rest_scliveticker_tick_query', array( 'SCLiveticker\\Api', 'tick_query_filter' ), 10, 2 );

		// Skip on AJAX if not enabled.
		if ( ( ! isset( self::$options['enable_ajax'] ) || 1 !== self::$options['enable_ajax'] ) && ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		// Load Textdomain.
		load_plugin_textdomain( 'stklcode-liveticker', false );

		// Allow shortcodes in widgets.
		add_filter( 'widget_text', 'do_shortcode' );

		// Add shortcode.
		add_shortcode( 'liveticker', array( __CLASS__, 'shortcode_ticker_show' ) );

		// Enqueue styles and JavaScript.
		add_action( 'wp_footer', array( __CLASS__, 'enqueue_resources' ) );

		// Add AJAX hook if configured.
		if ( 1 === self::$options['enable_ajax'] ) {
			add_action( 'wp_ajax_sclt_update-ticks', array( __CLASS__, 'ajax_update' ) );
			add_action( 'wp_ajax_nopriv_sclt_update-ticks', array( __CLASS__, 'ajax_update' ) );
		}

		// Admin only actions.
		if ( is_admin() ) {
			// Add dashboard "right now" functionality.
			add_action( 'right_now_content_table_end', array( 'SCLiveticker\\Admin', 'dashboard_right_now' ) );

			// Settings.
			add_action( 'admin_init', array( 'SCLiveticker\\Admin', 'register_settings' ) );
			add_action( 'admin_menu', array( 'SCLiveticker\\Admin', 'register_settings_page' ) );
		}
	}

	/**
	 * Register tick post type.
	 *
	 * @return void
	 */
	public static function register_types() {
		// Add new taxonomy, make it hierarchical (like categories).
		$labels = array(
			'name'              => _x( 'Ticker', 'taxonomy general name', 'stklcode-liveticker' ),
			'singular_name'     => _x( 'Ticker', 'taxonomy singular name', 'stklcode-liveticker' ),
			'search_items'      => __( 'Search Tickers', 'stklcode-liveticker' ),
			'all_items'         => __( 'All Tickers', 'stklcode-liveticker' ),
			'parent_item'       => __( 'Parent Ticker', 'stklcode-liveticker' ),
			'parent_item_colon' => __( 'Parent Ticker:', 'stklcode-liveticker' ),
			'edit_item'         => __( 'Edit Ticker', 'stklcode-liveticker' ),
			'update_item'       => __( 'Update Ticker', 'stklcode-liveticker' ),
			'add_new_item'      => __( 'Add New Ticker', 'stklcode-liveticker' ),
			'new_item_name'     => __( 'New Ticker', 'stklcode-liveticker' ),
			'menu_name'         => __( 'Ticker', 'stklcode-liveticker' ),
		);

		register_taxonomy(
			'scliveticker_ticker',
			array( 'scliveticker_tick' ),
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'show_in_rest'      => true,
			)
		);

		// Post type arguments.
		$args = array(
			'labels'             => array(
				'name'               => __( 'Ticks', 'stklcode-liveticker' ),
				'singular_name'      => __( 'Tick', 'stklcode-liveticker' ),
				'add_new'            => __( 'Add New', 'stklcode-liveticker' ),
				'add_new_item'       => __( 'Add New Tick', 'stklcode-liveticker' ),
				'edit_item'          => __( 'Edit Tick', 'stklcode-liveticker' ),
				'new_item'           => __( 'New Tick', 'stklcode-liveticker' ),
				'all_items'          => __( 'All Ticks', 'stklcode-liveticker' ),
				'view_item'          => __( 'View Tick', 'stklcode-liveticker' ),
				'search_items'       => __( 'Search Ticks', 'stklcode-liveticker' ),
				'not_found'          => __( 'No Ticks found', 'stklcode-liveticker' ),
				'not_found_in_trash' => __( 'No Ticks found in Trash', 'stklcode-liveticker' ),
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Liveticker', 'stklcode-liveticker' ),
			),
			'public'             => false,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-rss',
			'capability_type'    => 'post',
			'supports'           => array( 'title', 'editor', 'author' ),
			'taxonomies'         => array( 'scliveticker_ticker' ),
			'has_archive'        => true,
			'show_in_rest'       => true,
		);

		register_post_type( 'scliveticker_tick', $args );
	}

	/**
	 * Output Liveticker
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public static function shortcode_ticker_show( $atts ) {
		// Indicate presence of shortcode (to enqueue styles/scripts later).
		self::$shortcode_present = true;

		// Initialize output.
		$output = '';

		// Check if first attribute is filled.
		if ( ! empty( $atts['ticker'] ) ) {
			$ticker = sanitize_text_field( $atts['ticker'] );

			// Set limit to infinite, if not set explicitly.
			if ( ! isset( $atts['limit'] ) ) {
				$atts['limit'] = - 1;
			}
			$limit = intval( $atts['limit'] );

			// Determine if feed link should be shown.
			if ( isset( $atts['feed'] ) ) {
				$show_feed = 'true' === strtolower( $atts['feed'] ) || '1' === $atts['feed'];
			} else {
				$show_feed = 1 === self::$options['show_feed'];
			}

			$output = '<div class="wp-block-scliveticker-ticker';
			if ( 1 === self::$options['enable_ajax'] ) {
				$output .= ' sclt-ajax" '
							. 'data-sclt-ticker="' . $ticker . '" '
							. 'data-sclt-limit="' . $limit . '" '
							. 'data-sclt-last="' . time();
			}
			$output .= '"><ul>';

			$args = array(
				'post_type'      => 'scliveticker_tick',
				'posts_per_page' => $limit,
				'tax_query'      => array(
					array(
						'taxonomy' => 'scliveticker_ticker',
						'field'    => 'slug',
						'terms'    => $ticker,
					),
				),
			);

			$wp_query = new WP_Query( $args );

			while ( $wp_query->have_posts() ) {
				$wp_query->the_post();
				$output .= self::tick_html( get_the_time( 'd.m.Y H:i' ), get_the_title(), get_the_content() );
			}

			$output .= '</ul></div>';

			// Show RSS feed link, if configured.
			if ( $show_feed ) {
				$feed_link = get_post_type_archive_feed_link( 'scliveticker_tick' ) . '';
				if ( false === strpos( $feed_link, '&' ) ) {
					$feed_link .= '?scliveticker_ticker=' . $ticker;
				} else {
					$feed_link .= '&scliveticker_ticker=' . $ticker;
				}
				$output .= '<a href="' . esc_attr( $feed_link ) . '">Feed</a>';
			}
		}

		return $output;
	}

	/**
	 * Register frontend JS.
	 *
	 * @return void
	 * @since 1.1 Combined former methods "enqueue_styles" and "enqueue_scripts".
	 */
	public static function enqueue_resources() {
		// Only add if shortcode is present.
		if ( self::$shortcode_present || self::$widget_present || self::block_present() ) {
			wp_enqueue_script(
				'scliveticker-js',
				SCLIVETICKER_BASE . 'scripts/liveticker.min.js',
				array(),
				self::VERSION,
				true
			);

			// Add endpoint to script.
			wp_localize_script(
				'scliveticker-js',
				'sclivetickerAjax',
				array(
					'ajax_url'      => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'scliveticker_update-ticks' ),
					'poll_interval' => self::$options['poll_interval'] * 1000,
				)
			);

			// Enqueue CSS if enabled.
			if ( 1 === self::$options['enable_css'] ) {
				wp_enqueue_style(
					'sclt-css',
					SCLIVETICKER_BASE . 'styles/liveticker.min.css',
					'',
					self::VERSION,
					'all'
				);
			}
		}
	}

	/**
	 * Process Ajax upload file
	 *
	 * @return void
	 */
	public static function ajax_update() {
		// Verify AJAX nonce.
		check_ajax_referer( 'scliveticker_update-ticks' );

		// Extract update requests.
		if ( isset( $_POST['update'] ) && is_array( $_POST['update'] ) ) {  // Input var okay.
			$res = array();
			// @codingStandardsIgnoreLine Sanitization of array handled on field level.
			foreach ( wp_unslash( $_POST['update'] ) as $update_req ) {
				if ( is_array( $update_req ) && ( isset( $update_req['s'] ) || isset( $update_req['w'] ) ) ) {
					if ( isset( $update_req['s'] ) ) {
						$is_widget = false;
						$slug      = sanitize_text_field( $update_req['s'] );
					} elseif ( isset( $update_req['w'] ) ) {
						$is_widget = true;
						$slug      = sanitize_text_field( $update_req['w'] );
					} else {
						// Should never occur, but for completeness' sake...
						break;
					}

					$limit     = ( isset( $update_req['l'] ) ) ? intval( $update_req['l'] ) : - 1;
					$last_poll = explode(
						',',
						gmdate(
							'Y,m,d,H,i,s',
							( isset( $update_req['t'] ) ) ? intval( $update_req['t'] ) : 0
						)
					);

					// Query new ticks from DB.
					$query_args = array(
						'post_type'      => 'scliveticker_tick',
						'posts_per_page' => $limit,
						'tax_query'      => array(
							array(
								'taxonomy' => 'scliveticker_ticker',
								'field'    => 'slug',
								'terms'    => $slug,
							),
						),
						'date_query'     => array(
							'column' => 'post_date_gmt',
							'after'  => array(
								'year'   => intval( $last_poll[0] ),
								'month'  => intval( $last_poll[1] ),
								'day'    => intval( $last_poll[2] ),
								'hour'   => intval( $last_poll[3] ),
								'minute' => intval( $last_poll[4] ),
								'second' => intval( $last_poll[5] ),
							),
						),
					);

					$query = new WP_Query( $query_args );

					$out = '';
					while ( $query->have_posts() ) {
						$query->the_post();
						if ( $is_widget ) {
							$out .= self::tick_html_widget( get_the_time( 'd.m.Y H:i' ), get_the_title(), false );
						} else {
							$out .= self::tick_html( get_the_time( 'd.m.Y H:i' ), get_the_title(), get_the_content(), $is_widget );
						}
					}

					if ( $is_widget ) {
						$res[] = array(
							'w' => $slug,
							'h' => $out,
							't' => time(),
						);
					} else {
						$res[] = array(
							's' => $slug,
							'h' => $out,
							't' => time(),
						);
					}
				}
			}
			// Echo JSON encoded result set.
			echo wp_json_encode( $res );
		}

		exit;
	}

	/**
	 * Mark that Widget is present.
	 *
	 * @return void
	 */
	public static function mark_widget_present() {
		self::$widget_present = true;
	}

	/**
	 * Update options.
	 *
	 * @param array $options Optional. New options to save.
	 *
	 * @return void
	 */
	protected static function update_options( $options = null ) {
		self::$options = wp_parse_args(
			get_option( self::OPTION ),
			self::default_options()
		);
	}

	/**
	 * Create default plugin configuration.
	 *
	 * @return array The options array.
	 */
	protected static function default_options() {
		return array(
			'enable_ajax'    => 1,
			'poll_interval'  => 60,
			'enable_css'     => 1,
			'show_feed'      => 0,
			'reset_settings' => 0,
		);
	}

	/**
	 * Generate HTML code for a tick element.
	 *
	 * @param string  $time      Tick time (readable).
	 * @param string  $title     Tick title.
	 * @param string  $content   Tick content.
	 * @param boolean $is_widget Is the code for Widget.
	 *
	 * @return string HTML code of tick.
	 */
	private static function tick_html( $time, $title, $content, $is_widget = false ) {
		return '<li class="sclt-tick">'
			. '<p><span class="sclt-tick_time">' . esc_html( $time ) . '</span>'
			. '<span class="sclt-tick-title">' . esc_html( $title ) . '</span></p>'
			. '<p class="sclt-tick-content">' . $content . '</p></li>';
	}

	/**
	 * Generate HTML code for a tick element in widget.
	 *
	 * @param string  $time      Tick time (readable).
	 * @param string  $title     Tick title.
	 * @param boolean $highlight Highlight element.
	 *
	 * @return string HTML code of widget tick.
	 */
	public static function tick_html_widget( $time, $title, $highlight ) {
		$out = '<li';
		if ( $highlight ) {
			$out .= ' class="sclt-widget-new"';
		}
		return $out . '>'
			. '<span class="sclt-widget-time">' . esc_html( $time ) . '</span>'
			. '<span class="sclt-widget-title">' . $title . '</span>'
			. '</li>';
	}

	/**
	 * Check if the Gutenberg block is present in current post.
	 *
	 * @return boolean True, if Gutenberg block is present.
	 * @since 1.1
	 */
	private static function block_present() {
		return function_exists( 'has_block' ) && // We are in WP 5.x environment.
			has_block( 'scliveticker/ticker' ); // Specific block is present.
	}
}
