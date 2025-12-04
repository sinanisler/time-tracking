<?php
/**
 * TODO Management AJAX Handler
 *
 * @package TimeTracking
 * @since 3.9.1
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Time Tracking TODO AJAX Class
 */
class TT_Todo_Ajax {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_tt_save_todo', array( $this, 'ajax_save_todo' ) );
		add_action( 'wp_ajax_tt_get_todos', array( $this, 'ajax_get_todos' ) );
		add_action( 'wp_ajax_tt_update_todo', array( $this, 'ajax_update_todo' ) );
		add_action( 'wp_ajax_tt_delete_todo', array( $this, 'ajax_delete_todo' ) );
		add_action( 'wp_ajax_tt_reorder_todos', array( $this, 'ajax_reorder_todos' ) );
	}

	/**
	 * AJAX: Save todo
	 */
	public function ajax_save_todo() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$todo_data = json_decode( stripslashes( $_POST['todo_data'] ), true );
		$user_id   = get_current_user_id();

		// Get existing todos
		$todos = get_user_meta( $user_id, '_tt_todos', true );
		if ( ! is_array( $todos ) ) {
			$todos = array();
		}

		// Create new todo
		$new_todo = array(
			'id'          => uniqid( 'todo_', true ),
			'text'        => sanitize_textarea_field( $todo_data['text'] ),
			'completed'   => false,
			'created_at'  => current_time( 'mysql' ),
			'start_date'  => isset( $todo_data['start_date'] ) ? sanitize_text_field( $todo_data['start_date'] ) : '',
			'end_date'    => isset( $todo_data['end_date'] ) ? sanitize_text_field( $todo_data['end_date'] ) : '',
		);

		// Add to beginning of array (newest first)
		array_unshift( $todos, $new_todo );

		// Save todos
		update_user_meta( $user_id, '_tt_todos', $todos );

		wp_send_json_success( array( 'todo' => $new_todo ) );
	}

	/**
	 * AJAX: Get todos
	 */
	public function ajax_get_todos() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$user_id = get_current_user_id();
		$todos   = get_user_meta( $user_id, '_tt_todos', true );

		if ( ! is_array( $todos ) ) {
			$todos = array();
		}

		wp_send_json_success( $todos );
	}

	/**
	 * AJAX: Update todo
	 */
	public function ajax_update_todo() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$todo_id   = sanitize_text_field( $_POST['todo_id'] );
		$updates   = json_decode( stripslashes( $_POST['updates'] ), true );
		$user_id   = get_current_user_id();

		$todos = get_user_meta( $user_id, '_tt_todos', true );
		if ( ! is_array( $todos ) ) {
			wp_send_json_error( __( 'No todos found', 'time-tracking' ) );
		}

		// Find and update the todo
		foreach ( $todos as &$todo ) {
			if ( $todo['id'] === $todo_id ) {
				if ( isset( $updates['completed'] ) ) {
					$todo['completed'] = (bool) $updates['completed'];
				}
				if ( isset( $updates['text'] ) ) {
					$todo['text'] = sanitize_textarea_field( $updates['text'] );
				}
				if ( isset( $updates['start_date'] ) ) {
					$todo['start_date'] = sanitize_text_field( $updates['start_date'] );
				}
				if ( isset( $updates['end_date'] ) ) {
					$todo['end_date'] = sanitize_text_field( $updates['end_date'] );
				}
				break;
			}
		}

		update_user_meta( $user_id, '_tt_todos', $todos );

		wp_send_json_success();
	}

	/**
	 * AJAX: Delete todo
	 */
	public function ajax_delete_todo() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$todo_id = sanitize_text_field( $_POST['todo_id'] );
		$user_id = get_current_user_id();

		$todos = get_user_meta( $user_id, '_tt_todos', true );
		if ( ! is_array( $todos ) ) {
			wp_send_json_error( __( 'No todos found', 'time-tracking' ) );
		}

		// Filter out the todo to delete
		$todos = array_filter(
			$todos,
			function ( $todo ) use ( $todo_id ) {
				return $todo['id'] !== $todo_id;
			}
		);

		// Re-index the array
		$todos = array_values( $todos );

		update_user_meta( $user_id, '_tt_todos', $todos );

		wp_send_json_success();
	}

	/**
	 * AJAX: Reorder todos
	 */
	public function ajax_reorder_todos() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$todo_ids = json_decode( stripslashes( $_POST['todo_ids'] ), true );
		$user_id  = get_current_user_id();

		$todos = get_user_meta( $user_id, '_tt_todos', true );
		if ( ! is_array( $todos ) ) {
			wp_send_json_error( __( 'No todos found', 'time-tracking' ) );
		}

		// Create a map of todos by ID
		$todo_map = array();
		foreach ( $todos as $todo ) {
			$todo_map[ $todo['id'] ] = $todo;
		}

		// Reorder based on the provided IDs
		$reordered_todos = array();
		foreach ( $todo_ids as $id ) {
			if ( isset( $todo_map[ $id ] ) ) {
				$reordered_todos[] = $todo_map[ $id ];
			}
		}

		update_user_meta( $user_id, '_tt_todos', $reordered_todos );

		wp_send_json_success();
	}
}
