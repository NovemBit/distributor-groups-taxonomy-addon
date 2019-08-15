<?php

namespace DT\NbAddon\GroupsTaxonomy\Hooks;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'plugins_loaded',
		function () {
			add_filter( 'cron_schedules', __NAMESPACE__ . '\add_cron_interval' );
		}
	);
}

/**
 * Get connections for group
 *
 * @param string $group Single group term
 * @return array
 */
function get_connections( $group ) {
	$term             = get_term_by( 'slug', $group, 'dt_ext_connection_group' );
	$connections      = array();
	$connection_array = get_posts(
		array(
			'post_type'   => 'dt_ext_connection',
			'numberposts' => -1,
			'tax_query'   => array(
				array(
					'taxonomy'         => 'dt_ext_connection_group',
					'field'            => 'term_id',
					'terms'            => $term->term_id,
					'include_children' => true,
				),
			),
		)
	);
	if ( ! empty( $connection_array ) ) {
		foreach ( $connection_array as $index => $conn_obj ) {
			$connection         = array();
			$connection['id']   = $conn_obj->ID;
			$connection['type'] = $conn_obj->post_type;
			$connections[]      = $connection;
		}
	}
	return $connections;
}

/**
 * Push post to single connection
 *
 * @param array           $connection Single connection array
 * @param object(WP_Post) $post Current editing post object
 */
function push_connection( $connection, $post ) {
	$connection_map = get_post_meta( $post->ID, 'dt_connection_map', true );
	if ( empty( $connection_map ) ) {
		$connection_map = array();
	}

	if ( empty( $connection_map['external'] ) ) {
		$connection_map['external'] = array();
	}
	if ( 'dt_ext_connection' === $connection['type'] ) {
		$external_connection_type = get_post_meta( $connection['id'], 'dt_external_connection_type', true );
		$external_connection_url  = get_post_meta( $connection['id'], 'dt_external_connection_url', true );
		$external_connection_auth = get_post_meta( $connection['id'], 'dt_external_connection_auth', true );

		if ( empty( $external_connection_auth ) ) {
			$external_connection_auth = array();
		}

		if ( ! empty( $external_connection_type ) && ! empty( $external_connection_url ) && class_exists( '\Distributor\Connections' ) ) {
			$external_connection_class = \Distributor\Connections::factory()->get_registered()[ $external_connection_type ];

			$auth_handler = new $external_connection_class::$auth_handler_class( $external_connection_auth );

			$external_connection = new $external_connection_class( get_the_title( $connection['id'] ), $external_connection_url, $connection['id'], $auth_handler );

			$push_args = array();

			if ( ! empty( $connection_map['external'][ (int) $connection['id'] ] ) && ! empty( $connection_map['external'][ (int) $connection['id'] ]['post_id'] ) ) {
				$push_args['remote_post_id'] = (int) $connection_map['external'][ (int) $connection['id'] ]['post_id'];
			}

			if ( ! empty( $post->post_status ) ) {
				$push_args['post_status'] = $post->post_status;
			}

			$remote_id = $external_connection->push( intval( $post->ID ), $push_args );

			/**
			 * Record the external connection id's remote post id for this local post
			 */

			if ( ! is_wp_error( $remote_id ) && 0 !== (int) $remote_id ) {
				$connection_map['external'][ (int) $connection['id'] ] = array(
					'post_id' => (int) $remote_id,
					'time'    => time(),
				);

				$external_push_results[ (int) $connection['id'] ] = array(
					'post_id' => (int) $remote_id,
					'date'    => date( 'F j, Y g:i a' ),
					'status'  => 'success',
				);
				$external_connection->log_sync( array( $remote_id => $post->ID ) );
			} elseif ( ! is_wp_error( $remote_id ) && 0 === (int) $remote_id ) {
				$external_push_results[ (int) $connection['id'] ] = array(
					'post_id' => (int) $remote_id,
					'date'    => date( 'F j, Y g:i a' ),
					'status'  => 'fail',
				);
			} else {
				$external_push_results[ (int) $connection['id'] ] = array(
					'post_id' => $remote_id->get_error_message(),
					'date'    => date( 'F j, Y g:i a' ),
					'status'  => 'fail',
				);
			}
		}
	}
	update_post_meta( intval( $post->ID ), 'dt_connection_map', $connection_map );
}

/**
 * Add new interval for cron job
 *
 * @param array $schedules Array of existing schedules
 * @return array
 */
function add_cron_interval( $schedules ) {
	$schedules['ten_seconds'] = array(
		'interval' => 60,
		'display'  => esc_html__( 'Every Ten Seconds' ),
	);

	return $schedules;
}
