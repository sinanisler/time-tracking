<?php
/**
 * AJAX Handler Class
 *
 * @package TimeTracking
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Time Tracking AJAX Class
 */
class TT_Ajax {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_tt_save_task', array( $this, 'ajax_save_task' ) );
		add_action( 'wp_ajax_tt_delete_task', array( $this, 'ajax_delete_task' ) );
		add_action( 'wp_ajax_tt_get_tasks', array( $this, 'ajax_get_tasks' ) );
		add_action( 'wp_ajax_tt_save_category', array( $this, 'ajax_save_category' ) );
		add_action( 'wp_ajax_tt_update_category', array( $this, 'ajax_update_category' ) );
		add_action( 'wp_ajax_tt_delete_category', array( $this, 'ajax_delete_category' ) );
		add_action( 'wp_ajax_tt_get_categories', array( $this, 'ajax_get_categories' ) );
		add_action( 'wp_ajax_tt_save_time_log', array( $this, 'ajax_save_time_log' ) );
		add_action( 'wp_ajax_tt_get_time_logs', array( $this, 'ajax_get_time_logs' ) );
		add_action( 'wp_ajax_tt_update_time_log_note', array( $this, 'ajax_update_time_log_note' ) );
		add_action( 'wp_ajax_tt_delete_time_log', array( $this, 'ajax_delete_time_log' ) );
	}

	/**
	 * AJAX: Save task
	 */
	public function ajax_save_task() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$task_data = json_decode( stripslashes( $_POST['task_data'] ), true );

		$post_data = array(
			'post_title'  => sanitize_text_field( $task_data['title'] ),
			'post_type'   => 'tt_task',
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		);

		if ( ! empty( $task_data['id'] ) ) {
			$post_data['ID'] = intval( $task_data['id'] );

			// Verify user owns this task
			$existing_post = get_post( $post_data['ID'] );
			if ( ! $existing_post || $existing_post->post_author != get_current_user_id() ) {
				wp_send_json_error( __( 'You do not have permission to edit this task', 'time-tracking' ) );
			}

			$post_id = wp_update_post( $post_data );
		} else {
			$post_id = wp_insert_post( $post_data );
		}

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( $post_id->get_error_message() );
		}

		update_post_meta( $post_id, '_tt_start_date', sanitize_text_field( $task_data['startDate'] ) );
		update_post_meta( $post_id, '_tt_start_time', sanitize_text_field( $task_data['startTime'] ) );
		update_post_meta( $post_id, '_tt_end_date', sanitize_text_field( $task_data['endDate'] ) );
		update_post_meta( $post_id, '_tt_end_time', sanitize_text_field( $task_data['endTime'] ) );
		update_post_meta( $post_id, '_tt_description', sanitize_textarea_field( $task_data['description'] ) );

		if ( ! empty( $task_data['category'] ) ) {
			wp_set_object_terms( $post_id, intval( $task_data['category'] ), 'tt_category' );
		}

		// Save secondary categories
		if ( isset( $task_data['secondaryCategories'] ) && is_array( $task_data['secondaryCategories'] ) ) {
			$secondary_categories = array_map( 'intval', $task_data['secondaryCategories'] );
			update_post_meta( $post_id, '_tt_secondary_categories', $secondary_categories );
		} else {
			delete_post_meta( $post_id, '_tt_secondary_categories' );
		}

		wp_send_json_success( array( 'task_id' => $post_id ) );
	}

	/**
	 * AJAX: Delete task
	 */
	public function ajax_delete_task() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$task_id = intval( $_POST['task_id'] );

		// Verify user owns this task
		$post = get_post( $task_id );
		if ( ! $post || $post->post_author != get_current_user_id() ) {
			wp_send_json_error( __( 'You do not have permission to delete this task', 'time-tracking' ) );
		}

		// Also delete all time logs metadata
		delete_post_meta( $task_id, '_tt_time_logs' );

		$result = wp_delete_post( $task_id, true );

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( __( 'Failed to delete task', 'time-tracking' ) );
		}
	}

	/**
	 * AJAX: Get tasks
	 */
	public function ajax_get_tasks() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$args = array(
			'post_type'      => 'tt_task',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'author'         => get_current_user_id(),
		);

		$query = new WP_Query( $args );
		$tasks = array();

		foreach ( $query->posts as $post ) {
			$category_terms       = wp_get_object_terms( $post->ID, 'tt_category' );
			$secondary_categories = get_post_meta( $post->ID, '_tt_secondary_categories', true );

			$tasks[] = array(
				'id'                  => $post->ID,
				'title'               => $post->post_title,
				'startDate'           => get_post_meta( $post->ID, '_tt_start_date', true ),
				'startTime'           => get_post_meta( $post->ID, '_tt_start_time', true ),
				'endDate'             => get_post_meta( $post->ID, '_tt_end_date', true ),
				'endTime'             => get_post_meta( $post->ID, '_tt_end_time', true ),
				'description'         => get_post_meta( $post->ID, '_tt_description', true ),
				'category'            => ! empty( $category_terms ) ? $category_terms[0]->term_id : '',
				'secondaryCategories' => is_array( $secondary_categories ) ? $secondary_categories : array(),
			);
		}

		wp_send_json_success( $tasks );
	}

	/**
	 * AJAX: Save category
	 */
	public function ajax_save_category() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$category_data = json_decode( stripslashes( $_POST['category_data'] ), true );
		$user_id       = get_current_user_id();

		// Create category name with user ID to make it unique per user
		$category_name = sanitize_text_field( $category_data['name'] ) . '_user_' . $user_id;

		$term = wp_insert_term( $category_name, 'tt_category' );

		if ( is_wp_error( $term ) ) {
			wp_send_json_error( $term->get_error_message() );
		}

		update_term_meta( $term['term_id'], '_tt_category_color', sanitize_hex_color( $category_data['color'] ) );
		update_term_meta( $term['term_id'], '_tt_category_text_color', isset( $category_data['textColor'] ) ? sanitize_hex_color( $category_data['textColor'] ) : '#ffffff' );
		update_term_meta( $term['term_id'], '_tt_category_user', $user_id );
		update_term_meta( $term['term_id'], '_tt_category_display_name', sanitize_text_field( $category_data['name'] ) );

		wp_send_json_success( array( 'category_id' => $term['term_id'] ) );
	}

	/**
	 * AJAX: Update category
	 */
	public function ajax_update_category() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$category_data = json_decode( stripslashes( $_POST['category_data'] ), true );
		$category_id   = intval( $category_data['id'] );
		$user_id       = get_current_user_id();

		// Verify user owns this category
		$category_user = get_term_meta( $category_id, '_tt_category_user', true );
		if ( $category_user != $user_id ) {
			wp_send_json_error( __( 'You do not have permission to edit this category', 'time-tracking' ) );
		}

		// Update the display name and colors in term meta
		update_term_meta( $category_id, '_tt_category_display_name', sanitize_text_field( $category_data['name'] ) );
		update_term_meta( $category_id, '_tt_category_color', sanitize_hex_color( $category_data['color'] ) );
		update_term_meta( $category_id, '_tt_category_text_color', sanitize_hex_color( $category_data['textColor'] ) );

		wp_send_json_success();
	}

	/**
	 * AJAX: Delete category
	 */
	public function ajax_delete_category() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$category_id = intval( $_POST['category_id'] );
		$user_id     = get_current_user_id();

		// Verify user owns this category
		$category_user = get_term_meta( $category_id, '_tt_category_user', true );
		if ( $category_user != $user_id ) {
			wp_send_json_error( __( 'You do not have permission to delete this category', 'time-tracking' ) );
		}

		$result = wp_delete_term( $category_id, 'tt_category' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Get categories
	 */
	public function ajax_get_categories() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$user_id = get_current_user_id();

		$terms = get_terms(
			array(
				'taxonomy'   => 'tt_category',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'     => '_tt_category_user',
						'value'   => $user_id,
						'compare' => '=',
					),
				),
			)
		);

		$categories = array();

		foreach ( $terms as $term ) {
			$display_name = get_term_meta( $term->term_id, '_tt_category_display_name', true );
			$color        = get_term_meta( $term->term_id, '_tt_category_color', true );
			$text_color   = get_term_meta( $term->term_id, '_tt_category_text_color', true );
			$categories[] = array(
				'id'        => $term->term_id,
				'name'      => $display_name ? $display_name : $term->name,
				'color'     => $color ? $color : '#3b82f6',
				'textColor' => $text_color ? $text_color : '#ffffff',
			);
		}

		wp_send_json_success( $categories );
	}

	/**
	 * AJAX: Save time log
	 */
	public function ajax_save_time_log() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$task_id  = intval( $_POST['task_id'] );
		$duration = intval( $_POST['duration'] );
		$note     = sanitize_textarea_field( $_POST['note'] );

		// Verify user owns this task
		$post = get_post( $task_id );
		if ( ! $post || $post->post_author != get_current_user_id() ) {
			wp_send_json_error( __( 'You do not have permission to log time for this task', 'time-tracking' ) );
		}

		// Get existing logs
		$logs = get_post_meta( $task_id, '_tt_time_logs', true );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		// Add new log with unique ID
		$new_log = array(
			'id'        => uniqid( 'log_', true ),
			'duration'  => $duration,
			'note'      => $note,
			'timestamp' => current_time( 'mysql' ),
		);

		$logs[] = $new_log;

		// Save updated logs
		$result = update_post_meta( $task_id, '_tt_time_logs', $logs );

		if ( false !== $result ) {
			wp_send_json_success(
				array(
					'log'        => $new_log,
					'total_logs' => count( $logs ),
				)
			);
		} else {
			wp_send_json_error( __( 'Failed to save time log', 'time-tracking' ) );
		}
	}

	/**
	 * AJAX: Get time logs
	 */
	public function ajax_get_time_logs() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$task_id = intval( $_POST['task_id'] );

		// Verify user owns this task
		$post = get_post( $task_id );
		if ( ! $post || $post->post_author != get_current_user_id() ) {
			wp_send_json_error( __( 'You do not have permission to view logs for this task', 'time-tracking' ) );
		}

		$logs = get_post_meta( $task_id, '_tt_time_logs', true );

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		// Sort logs by timestamp (newest first)
		usort(
			$logs,
			function ( $a, $b ) {
				return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
			}
		);

		wp_send_json_success( $logs );
	}

	/**
	 * AJAX: Update time log note
	 */
	public function ajax_update_time_log_note() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$task_id  = intval( $_POST['task_id'] );
		$log_id   = sanitize_text_field( $_POST['log_id'] );
		$new_note = sanitize_textarea_field( $_POST['note'] );

		// Verify user owns this task
		$post = get_post( $task_id );
		if ( ! $post || $post->post_author != get_current_user_id() ) {
			wp_send_json_error( __( 'You do not have permission to update this log', 'time-tracking' ) );
		}

		$logs = get_post_meta( $task_id, '_tt_time_logs', true );
		if ( ! is_array( $logs ) ) {
			wp_send_json_error( __( 'No logs found', 'time-tracking' ) );
		}

		// Find and update the log
		foreach ( $logs as &$log ) {
			if ( $log['id'] === $log_id ) {
				$log['note'] = $new_note;
				break;
			}
		}

		$result = update_post_meta( $task_id, '_tt_time_logs', $logs );

		if ( false !== $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( __( 'Failed to update note', 'time-tracking' ) );
		}
	}

	/**
	 * AJAX: Delete time log
	 */
	public function ajax_delete_time_log() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$task_id = intval( $_POST['task_id'] );
		$log_id  = sanitize_text_field( $_POST['log_id'] );

		// Verify user owns this task
		$post = get_post( $task_id );
		if ( ! $post || $post->post_author != get_current_user_id() ) {
			wp_send_json_error( __( 'You do not have permission to delete this log', 'time-tracking' ) );
		}

		$logs = get_post_meta( $task_id, '_tt_time_logs', true );
		if ( ! is_array( $logs ) ) {
			wp_send_json_error( __( 'No logs found', 'time-tracking' ) );
		}

		// Filter out the log to delete
		$logs = array_filter(
			$logs,
			function ( $log ) use ( $log_id ) {
				return $log['id'] !== $log_id;
			}
		);

		// Re-index the array
		$logs = array_values( $logs );

		$result = update_post_meta( $task_id, '_tt_time_logs', $logs );

		if ( false !== $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( __( 'Failed to delete log', 'time-tracking' ) );
		}
	}
}
