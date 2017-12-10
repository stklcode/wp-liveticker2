<?php
/**
 * WP Liveticker 2: Plugin main class.
 *
 * This file contains the plugin's base class.
 *
 * @package WPLiveticker2
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WP Liveticker 2.
 */
class WPLiveticker2 {
	/**
	 * Options tag.
	 *
	 * @var string OPTIONS
	 */
	const VERSION = '1.0.0';

	/**
	 * Options tag.
	 *
	 * @var string OPTIONS
	 */
	const OPTION = 'wplt2';

	/**
	 * Plugin options.
	 *
	 * @var array $_options
	 */
	protected static $_options;

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

		// Skip on AJAX if not enabled disabled.
		if ( ( ! isset( self::$_options['enable_ajax'] ) || 1 !== self::$_options['enable_ajax'] ) && ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		// Load Textdomain.
		load_plugin_textdomain( 'wplt2', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

		// Allow shortcodes in widgets.
		add_filter( 'widget_text', 'do_shortcode' );

		// Add shortcode.
		add_shortcode( 'liveticker', array( 'WPLiveticker2', 'shortcode_ticker_show' ) );

		// Enqueue styles.
		add_action( 'wp_footer', array( 'WPLiveticker2', 'enqueue_styles' ) );

		// Enqueue JavaScript.
		add_action( 'wp_footer', array( 'WPLiveticker2', 'enqueue_scripts' ) );

		// Add AJAX hook if configured.
		if ( 1 === self::$_options['enable_ajax'] ) {
			add_action( 'wp_ajax_wplt2_update-ticks', array( 'WPLiveticker2', 'ajax_update' ) );
			add_action( 'wp_ajax_nopriv_wplt2_update-ticks', array( 'WPLiveticker2', 'ajax_update' ) );
		}

		// Admin only actions.
		if ( is_admin() ) {
			// Add dashboard "right now" functionality.
			add_action( 'right_now_content_table_end', array( 'WPLiveticker2_Admin', 'dashboard_right_now' ) );

			// Settings.
			add_action( 'admin_init', array( 'WPLiveticker2_Admin', 'register_settings' ) );
			add_action( 'admin_menu', array( 'WPLiveticker2_Admin', 'register_settings_page' ) );
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
			'name'              => _x( 'Ticker', 'taxonomy general name' ),
			'singular_name'     => _x( 'Ticker', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Tickers', 'wplt2' ),
			'all_items'         => __( 'All Tickers', 'wplt2' ),
			'parent_item'       => __( 'Parent Ticker', 'wplt2' ),
			'parent_item_colon' => __( 'Parent Ticker:', 'wplt2' ),
			'edit_item'         => __( 'Edit Ticker', 'wplt2' ),
			'update_item'       => __( 'Update Ticker', 'wplt2' ),
			'add_new_item'      => __( 'Add New Ticker', 'wplt2' ),
			'new_item_name'     => __( 'New Ticker', 'wplt2' ),
			'menu_name'         => __( 'Ticker', 'wplt2' ),
		);

		register_taxonomy(
			'wplt2_ticker',
			array( 'wplt2_tick' ),
			array(
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
			)
		);

		// Post type arguments.
		$args = array(
			'labels'             => array(
				'name'               => __( 'Ticks', 'wplt2' ),
				'singular_name'      => __( 'Tick', 'wplt2' ),
				'add_new'            => __( 'Add New', 'wplt2' ),
				'add_new_item'       => __( 'Add New Tick', 'wplt2' ),
				'edit_item'          => __( 'Edit Tick', 'wplt2' ),
				'new_item'           => __( 'New Tick', 'wplt2' ),
				'all_items'          => __( 'All Ticks', 'wplt2' ),
				'view_item'          => __( 'View Tick', 'wplt2' ),
				'search_items'       => __( 'Search Ticks', 'wplt2' ),
				'not_found'          => __( 'No Ticks found', 'wplt2' ),
				'not_found_in_trash' => __( 'No Ticks found in Trash', 'wplt2' ),
				'parent_item_colon'  => '',
				'menu_name'          => __( 'Liveticker', 'wplt2' ),
			),
			'public'             => false,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-rss',
			'capability_type'    => 'post',
			'supports'           => array( 'title', 'editor', 'author' ),
			'taxonomies'         => array( 'wplt2_ticker' ),
			'has_archive'        => true,
		);

		register_post_type( 'wplt2_tick', $args );
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
				$show_feed = 1 === self::$_options['show_feed'];
			}

			$output = '<ul class="wplt2-ticker';
			if ( 1 === self::$_options['enable_ajax'] ) {
				$output .= ' wplt2-ticker-ajax" '
							. 'data-wplt2-ticker="' . $ticker . '" '
							. 'data-wplt2-limit="' . $limit . '" '
							. 'data-wplt2-last="' . time();
			}
			$output .= '">';

			$args = array(
				'post_type'      => 'wplt2_tick',
				'posts_per_page' => $limit,
				'tax_query'      => array(
					array(
						'taxonomy' => 'wplt2_ticker',
						'field'    => 'slug',
						'terms'    => $ticker,
					),
				),
			);

			$wp_query = new WP_Query( $args );

			while ( $wp_query->have_posts() ) {
				$wp_query->the_post();
				$output .= self::tick_html( get_the_time( 'd.m.Y H.i' ), get_the_title(), get_the_content() );
			}

			$output .= '</ul>';

			// Show RSS feed link, if configured.
			if ( $show_feed ) {
				// TODO: For some reason get_term_feed_link() does not give the desired result...
				$feed_link = get_post_type_archive_feed_link( 'wplt2_tick' ) . '';
				if ( false === strpos( $feed_link, '&' ) ) {
					$feed_link .= '?wplt2_ticker=' . $ticker;
				} else {
					$feed_link .= '&wplt2_ticker=' . $ticker;
				}
				$output .= '<a href="' . esc_attr( $feed_link ) . '">Feed</a>';
			}
		}// End if().

		return $output;
	}

	/**
	 * Register frontend CSS.
	 */
	public static function enqueue_styles() {
		// Only add if shortcode is present.
		if ( self::$shortcode_present || self::$widget_present ) {
			wp_enqueue_style(
				'wplt-css',
				WPLT2_BASE . 'styles/wp-liveticker2.css',
				'',
				self::VERSION, 'all'
			);
		}
	}

	/**
	 * Register frontend JS.
	 */
	public static function enqueue_scripts() {
		// Only add if shortcode is present.
		if ( self::$shortcode_present || self::$widget_present ) {
			wp_enqueue_script(
				'wplt2-js',
				WPLT2_BASE . 'scripts/wp-liveticker2.js',
				array( 'jquery' ),
				self::VERSION,
				true
			);

			// Add endpoint to script.
			wp_localize_script(
				'wplt2-js',
				'ajax_object',
				array(
					'ajax_url'      => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'wplt2_update-ticks' ),
					'poll_interval' => self::$_options['poll_interval'] * 1000,
				)
			);
		}
	}

	/**
	 * Process Ajax upload file
	 *
	 * @return void
	 */
	public static function ajax_update() {
		// Verify AJAX nonce.
		check_ajax_referer( 'wplt2_update-ticks' );

		// Extract update requests.
		if ( isset( $_POST['update'] ) && is_array( $_POST['update'] ) ) {
			$res = array();
			// @codingStandardsIgnoreLine Sanitization of arrayhandled on field level.
			foreach ( wp_unslash( $_POST['update'] ) as $update_req ) {
				if ( is_array( $update_req ) && ( isset( $update_req['s'] ) || isset( $update_req['w'] ) ) ) {
					if ( isset( $update_req['s'] ) ) {
						$is_widget = false;
						$slug      = sanitize_text_field( $update_req['s'] );
					} elseif ( isset( $update_req['w'] ) ) {
						$is_widget = true;
						$slug      = sanitize_text_field( $update_req['w'] );
					} else {
						// Should never occur, but for completenes' sake...
						break;
					}

					$limit     = ( isset( $update_req['l'] ) ) ? intval( $update_req['l'] ) : - 1;
					$last_poll = ( isset( $update_req['t'] ) ) ? intval( $update_req['t'] ) : 0;

					// Query new ticks from DB.
					$query_args = array(
						'post_type'      => 'wplt2_tick',
						'posts_per_page' => $limit,
						'tax_query'      => array(
							array(
								'taxonomy' => 'wplt2_ticker',
								'field'    => 'slug',
								'terms'    => $slug,
							),
						),
						'date_query'     => array(
							'after' => date( 'c', $last_poll ),
						),
					);

					$query = new WP_Query( $query_args );

					$out = '';
					while ( $query->have_posts() ) {
						$query->the_post();
						if ( $is_widget ) {
							$out .= self::tick_html_widget( get_the_time( 'd.m.Y H.i' ), get_the_title(), false );
						} else {
							$out .= self::tick_html( get_the_time( 'd.m.Y H.i' ), get_the_title(), get_the_content(), $is_widget );
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
			echo json_encode( $res );
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
	 */
	protected static function update_options( $options = null ) {
		self::$_options = wp_parse_args(
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
		return '<li class="wplt2-tick">'
			. '<p><span class="wplt2-tick_time">' . esc_html( $time ) . '</span>'
			. '<span class="wplt2-tick-title">' . esc_html( $title ) . '</span></p>'
			. '<p class="wplt2-tick-content">' . $content . '</p></li>';
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
			$out .= ' class="wplt2-widget-new"';
		}
		return $out . '>'
			. '<span class="wplt2-widget-time">' . esc_html( $time ) . '</span>'
			. '<span class="wplt2-widget-title">' . $title . '</span>'
			. '</li>';
	}
}
