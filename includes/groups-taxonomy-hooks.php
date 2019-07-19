<?php

namespace DT\NbAddon\GroupsTaxonomy\Hooks;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'plugins_loaded',
		function () {
			add_action( 'dt_push_groups_hook', __NAMESPACE__ . '\dt_push_groups' );
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
				\Distributor\Logger\log( 'success', 'first push', $connection['id'], $post->ID, null, $post->post_type );
				$external_connection->log_sync( array( $remote_id => $post->ID ) );
			} elseif ( ! is_wp_error( $remote_id ) && 0 === (int) $remote_id ) {
				$external_push_results[ (int) $connection['id'] ] = array(
					'post_id' => (int) $remote_id,
					'date'    => date( 'F j, Y g:i a' ),
					'status'  => 'fail',
				);
				\Distributor\Logger\log( 'error', 'first push', $connection['id'], $post->ID, 'Can not set up remote id properly', $post->post_type );
			} else {
				$external_push_results[ (int) $connection['id'] ] = array(
					'post_id' => (int) $remote_id,
					'date'    => date( 'F j, Y g:i a' ),
					'status'  => 'fail',
				);
				\Distributor\Logger\log( 'error', 'first push', $connection['id'], $post->ID, $remote_id->get_error_messages(), $post->post_type );
			}
		}
	}
	update_post_meta( intval( $post->ID ), 'dt_connection_map', $connection_map );
}

/**
 * Perform scheduled push groups
 */
function dt_push_groups() {
	$query = new \WP_Query(
		array(
			'post_type'      => \DT\NbAddon\GroupsTaxonomy\Utils\get_distributable_custom_post_types(),
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'posts_per_page' => 20,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'dt_connection_groups_pushing',
					'compare' => 'EXISTS',
				),
			),
		)
	);

	$all_posts = $query->posts;
	if ( ! empty( $all_posts ) ) {
		foreach ( $all_posts as $post ) {
			$connection_map = get_post_meta( $post->ID, 'dt_connection_groups_pushing', true );
			if ( empty( $connection_map ) ) {
				delete_post_meta( $post->ID, 'dt_connection_groups_pushing' );
				continue;
			} elseif ( ! is_array( $connection_map ) ) {
				$connection_map = array( $connection_map );
			}
			$successed_groups = get_post_meta( $post->ID, 'dt_connection_groups_pushed', true );
			if ( empty( $successed_groups ) || null === $successed_groups ) {
				$successed_groups = array();
			}
			foreach ( $connection_map as $group ) {

				$index                = get_term_by( 'slug', $group, 'dt_ext_connection_group' )->term_id;
				$push_connections = get_connections( $group );
				if ( empty( $push_connections ) ) {
					$key = array_search( $group, $connection_map, true );
					if ( ! in_array( $group, $successed_groups, true ) ) {
						$successed_groups[] = $group;
						update_post_meta( $post->ID, 'dt_connection_groups_pushed', $successed_groups );
					}
					if ( false !== $key || null !== $key ) {
						unset( $connection_map[ $key ] );
					}
					continue;
				}
				$pushed_connections_map = get_post_meta( $post->ID, 'dt_connection_map', true );

				foreach ( $push_connections as $con ) {
					if ( empty( $pushed_connections_map ) || ! isset( $pushed_connections_map['external'] ) || ! in_array( $con['id'], array_keys( $pushed_connections_map['external'] ), true ) ) {
						push_connection( $con, $post );
					}
				}

				$key = array_search( $group, $connection_map, true );
				if ( ! in_array( $group, $successed_groups, true ) ) {
					$successed_groups[] = $group;
					update_post_meta( $post->ID, 'dt_connection_groups_pushed', $successed_groups );
				}
				if ( false !== $key || null !== $key ) {
					unset( $connection_map[ $key ] );
				}
			}
			if ( empty( $connection_map ) ) {
				delete_post_meta( $post->ID, 'dt_connection_groups_pushing' );
			} else {
				update_post_meta( $post->ID, 'dt_connection_groups_pushing', $connection_map );
			}
		}
	}

	// Re-schedule a new event when there are still others to be distributed.
	if ( $query->found_posts > $query->post_count ) {
		wp_schedule_single_event( time(), 'dt_push_groups_hook' );
	}
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
