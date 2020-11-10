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
		$last_poll   = intval( $request->get_param( 'last' ) );

		if ( ! empty( $ticker_slug ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'scliveticker_ticker',
				'field'    => 'slug',
				'terms'    => $ticker_slug,
			);
		}

		if ( ! empty( $limit ) ) {
			$args['posts_per_page'] = $limit;
		}

		if ( $last_poll > 0 ) {
			$last_poll = explode(
				',',
				gmdate(
					'Y,m,d,H,i,s',
					$last_poll
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
