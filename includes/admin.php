<?php

namespace DT\NbAddon\GroupsTaxonomy\Admin;

add_action( 'admin_menu', __NAMESPACE__ . '\add_submenu_item', 20 );
add_action( 'init', __NAMESPACE__ . '\setup_groups' );
add_action( 'load-post.php', __NAMESPACE__ . '\init_metabox' );
add_action( 'load-post-new.php', __NAMESPACE__ . '\init_metabox' );
add_action( 'dt_meta_box_external_connection_details', __NAMESPACE__ . '\add_external_connection_details', 10, 1 );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\add_groups_check_scripts', 10, 1 );

/**
 * Register taxonomy for groups
 *
 * @since 1.3.0
 */
function setup_groups() {
	$taxonomy_capabilities = array(
		'manage_terms' => 'manage_categories',
		'edit_terms'   => 'manage_categories',
		'delete_terms' => 'manage_categories',
		'assign_terms' => 'edit_posts',
	);

	$taxonomy_labels = array(
		'name'              => esc_html__( 'External Connection Groups' ),
		'singular_name'     => esc_html__( 'External Connection Group' ),
		'search_items'      => esc_html__( 'Search External Connection Groups' ),
		'popular_items'     => esc_html__( 'Popular External Connection Groups' ),
		'all_items'         => esc_html__( 'All External Connection Groups' ),
		'parent_item'       => esc_html__( 'Parent External Connection Group' ),
		'parent_item_colon' => esc_html__( 'Parent External Connection Group' ),
		'edit_item'         => esc_html__( 'Edit External Connection Group' ),
		'update_item'       => esc_html__( 'Update External Connection Group' ),
		'add_new_item'      => esc_html__( 'Add New External Connection Group' ),
		'new_item_name'     => esc_html__( 'New External Connection Group Name' ),

	);
	$args = array(
		'labels'            => $taxonomy_labels,
		'public'            => false,
		'show_ui'           => true,
		'meta_box_cb'       => false,
		'show_tagcloud'     => false,
		'show_in_nav_menus' => false,
		'hierarchical'      => true,
		'rewrite'           => false,
		'capabilities'      => $taxonomy_capabilities,

	);
	register_taxonomy( 'dt_ext_connection_group', 'dt_ext_connection', $args );
}

/**
 * Add submenu for groups
 *
 * @since 1.3.0
 */
function add_submenu_item() {
	$link = admin_url( 'edit-tags.php' ) . '?taxonomy=dt_ext_connection_group&post_type=dt_ext_connection';
	add_submenu_page(
		'distributor',
		esc_html__( 'External Connection Groups', 'distributor' ),
		esc_html__( 'External Connection Groups', 'distributor' ),
		'manage_options',
		$link
	);
}

/**
 * Init actions for connection groups metabox
 */
function init_metabox() {
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\add_metabox' );
	add_action( 'save_post', __NAMESPACE__ . '\save_metabox', 10, 2 );

}

/**
 * Add metabox for connection groups
 */
function add_metabox() {
	add_meta_box(
		'external-connection-groups',
		__( 'External Connection Groups', 'distributor' ),
		__NAMESPACE__ . '\render_metabox',
		\DT\NbAddon\GroupsTaxonomy\Utils\get_distributable_custom_post_types(),
		'side',
		'high'
	);

}

/**
 * Render metabox for connection groups
 *
 * @param object(WP_Post) $post Current editing post object
 */
function render_metabox( $post ) {
	// Add nonce for security and authentication.
	wp_nonce_field( 'save_connection_groups', 'connection_groups_field' );
	\DT\NbAddon\GroupsTaxonomy\ExternalConnectionGroups::factory()->groups_checklist( 'dt_ext_connection_group', $post->ID );
}

/**
 * Process connection groups pushing
 *
 * @param Integer         $post_id Id of current post
 * @param object(WP_Post) $post Current editing post object
 */
function save_metabox( $post_id, $post ) {

	// Check if user has permissions to save data.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Check if not an autosave.
	if ( wp_is_post_autosave( $post_id ) ) {
		return;
	}

	// Check if not a revision.
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! in_array( $post->post_type, \DT\NbAddon\GroupsTaxonomy\Utils\get_distributable_custom_post_types(), true ) ) {
		return;
	}
	if ( 'auto-draft' === $post->post_status ) {
		return;
	}

	if ( ! isset( $_POST['connection_groups_field'] ) || ! wp_verify_nonce( $_POST['connection_groups_field'], 'save_connection_groups' ) ) {
		return;
	}
	$group_ids = $_POST['tax_input']['dt_ext_connection_group'] ?? array();
	$groups    = array();
	foreach ( $group_ids as $group_id ) {
		$term     = get_term_by( 'id', $group_id, 'dt_ext_connection_group' );
		$groups[] = $term->slug;
	}
	update_post_meta( $post_id, 'dt_connection_groups', $groups );

	$groups = get_post_meta( $post_id, 'dt_connection_groups', true );

	if ( ! empty( $groups ) && is_array( $groups ) ) {
		$pushed_groups = get_post_meta( $post_id, 'dt_connection_groups_pushed', true );

		if ( ! empty( $pushed_groups ) ) {
			if ( ! empty( array_diff( $groups, $pushed_groups ) ) ) {
				update_post_meta( $post_id, 'dt_connection_groups_pushing', array_diff( $groups, $pushed_groups ) );
			}
		} else {
			update_post_meta( $post_id, 'dt_connection_groups_pushing', $groups );
		}
	}

	/*TODO: find better place for this */
	if ( ! wp_next_scheduled( 'dt_push_groups_hook' ) ) {
		wp_schedule_single_event( time(), 'dt_push_groups_hook' );
	}
}

/**
 * Add external connection details
 *
 * @param \WP_Post $post Post object
 */
function add_external_connection_details( $post ) {
	$connection_groups = \DT\NbAddon\GroupsTaxonomy\ExternalConnectionGroups::factory();
	$connection_groups->display_groups( $post->ID );
}


/**
 * Add js scripts to pages
 *
 * @param string $hook Current hook
 */
function add_groups_check_scripts( $hook ) {
	if ( 'post.php' === $hook ) {
		wp_enqueue_script( 'dt_check_groups', plugins_url( '../dist/js/check-groups.js', __DIR__ ), array(), DT_VERSION, true );
	}
}
