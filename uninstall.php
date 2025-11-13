<?php
/**
 * Uninstall script for Time Tracking Plugin
 *
 * @package TimeTracking
 * @since 3.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all time tracking data
 */
function tt_uninstall_plugin() {
	global $wpdb;

	// Delete all tasks
	$tasks = get_posts(
		array(
			'post_type'      => 'tt_task',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		)
	);

	foreach ( $tasks as $task_id ) {
		wp_delete_post( $task_id, true );
	}

	// Delete all categories
	$categories = get_terms(
		array(
			'taxonomy'   => 'tt_category',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	foreach ( $categories as $cat_id ) {
		wp_delete_term( $cat_id, 'tt_category' );
	}

	// Delete plugin options
	delete_option( 'tt_settings' );

	// Delete post meta (cleanup in case any orphaned meta exists)
	$wpdb->query(
		"DELETE FROM {$wpdb->postmeta} 
		WHERE meta_key LIKE '_tt_%'"
	);

	// Delete term meta (cleanup in case any orphaned meta exists)
	$wpdb->query(
		"DELETE FROM {$wpdb->termmeta} 
		WHERE meta_key LIKE '_tt_%'"
	);

	// Clear any cached data
	wp_cache_flush();
}

// Execute uninstall
tt_uninstall_plugin();
