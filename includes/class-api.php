<?php
/**
 * Liveticker: Plugin API class.
 *
 * This file contains the plugin's REST API extensions.
 *
 * @package SCLiveticker
 */

namespace SCLiveticker;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Liveticker.
 *
 * @since 1.2
 */
class Api {
	/**
	 * Initialize custom fields for REST API responses.
	 *
	 * @return void
	 */
	public static function init() {
		// Add rendered modification date to WP_Post object.
		register_rest_field(
			'scliveticker_tick',
			'modified_rendered',
			array(
				'get_callback' => function ( $post ) {
					return get_the_modified_date( 'd.m.Y H:i', $post );
				},
				'schema'       => array(
					'description' => __( 'Rendered modification date and time.', 'stklcode-liveticker' ),
					'type'        => 'string',
				),
			)
		);
	}

	/**
	 * Filter tick queries by ticker slug and date.
	 *
	 * @param array            $args    Query vars.
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return array Filtered query values.
	 */
	public static function tick_query_filter( $args, $request ) {
		// Extract arguments.
		$ticker_slug = $request->get_param( 'ticker' );
		$limit       = intval( $request->get_param( 'limit' ) );
		$last_poll   = $request->get_param( 'last' );

		if ( ! empty( $ticker_slug ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'scliveticker_ticker',
				'field'    => 'slug',
				'terms'    => $ticker_slug,
			);
		}

		if ( ! empty( $limit ) && $limit > 0 ) {
			$args['posts_per_page'] = $limit;
		} else {
			$args['paged'] = false;
		}

		if ( $last_poll > 0 ) {
			$last_poll = explode(
				',',
				gmdate(
					'Y,m,d,H,i,s',
					rest_parse_date( $last_poll )
				)
			);

			$args['date_query'] = array(
				'column' => 'post_date_gmt',
				'after'  => array(
					'year'   => intval( $last_poll[0] ),
					'month'  => intval( $last_poll[1] ),
					'day'    => intval( $last_poll[2] ),
					'hour'   => intval( $last_poll[3] ),
					'minute' => intval( $last_poll[4] ),
					'second' => intval( $last_poll[5] ),
				),
			);
		}

		return $args;
	}
}
