<?php      
/**
 * Plugin Name: Time Tracking
 * Plugin URI: https://sinanisler.com
 * Description: Advanced time tracking plugin with drag-to-select calendar, category management, and detailed time logging
 * Version: 3.9
 * Author: sinanisler
 * Author URI: https://sinanisler.com
 * License: GPL v2 or later
 * Text Domain: time-tracking
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 5.8
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'TIME_TRACKING_VERSION', '3.9' );
define( 'TIME_TRACKING_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TIME_TRACKING_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Time Tracking Plugin Class
 */
class TimeTrackingPlugin {

	/**
	 * Single instance of the class
	 *
	 * @var TimeTrackingPlugin
	 */
	private static $instance = null;

	/**
	 * Settings instance
	 *
	 * @var TT_Settings
	 */
	private $settings;

	/**
	 * Calendar instance
	 *
	 * @var TT_Calendar
	 */
	private $calendar;

	/**
	 * AJAX instance
	 *
	 * @var TT_Ajax
	 */
	private $ajax;

	/**
	 * Get single instance
	 *
	 * @return TimeTrackingPlugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Load plugin text domain
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Register post type and taxonomy
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );

		// Add admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Load dependencies
		$this->load_dependencies();

		// Initialize components
		$this->settings = new TT_Settings();
		$this->calendar = new TT_Calendar( $this->settings );
		$this->ajax     = new TT_Ajax();
		$this->todo_ajax = new TT_Todo_Ajax();
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'time-tracking',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Load required dependencies
	 */
	private function load_dependencies() {
		require_once plugin_dir_path(__FILE__) . 'includes/class-tt-settings.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-tt-calendar.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-tt-ajax.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-tt-todo-ajax.php';
		require_once plugin_dir_path(__FILE__) . 'includes/update-github.php';
	}

	/**
	 * Register custom post type for tasks
	 */
	public function register_post_type() {
		register_post_type(
			'tt_task',
			array(
				'labels'       => array(
					'name'          => __( 'Time Tasks', 'time-tracking' ),
					'singular_name' => __( 'Time Task', 'time-tracking' ),
				),
				'public'       => false,
				'show_ui'      => false,
				'show_in_menu' => false,
				'capability_type' => 'post',
				'supports'     => array( 'title', 'author' ),
				'has_archive'  => false,
			)
		);
	}

	/**
	 * Register taxonomy for categories
	 */
	public function register_taxonomy() {
		register_taxonomy(
			'tt_category',
			'tt_task',
			array(
				'labels'       => array(
					'name'          => __( 'Task Categories', 'time-tracking' ),
					'singular_name' => __( 'Task Category', 'time-tracking' ),
				),
				'public'       => false,
				'show_ui'      => false,
				'hierarchical' => true,
			)
		);
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		$settings        = $this->settings->get_settings();
		$allowed_roles   = isset( $settings['allowed_roles'] ) ? $settings['allowed_roles'] : array( 'administrator', 'editor', 'author', 'contributor' );
		$current_user    = wp_get_current_user();
		$user_has_access = false;

		// Check if user has any of the allowed roles
		foreach ( $allowed_roles as $role ) {
			if ( in_array( $role, $current_user->roles, true ) ) {
				$user_has_access = true;
				break;
			}
		}

		// If user doesn't have access, don't show menu
		if ( ! $user_has_access && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_menu_page(
			__( 'Time Tracking', 'time-tracking' ),
			__( 'Time Tracking', 'time-tracking' ),
			'read',
			'time-tracking',
			array( $this, 'render_main_page' ),
			'dashicons-clock',
			30
		);
	}

	/**
	 * Render main calendar page
	 */
	public function render_main_page() {
		// Enqueue scripts and styles
		$this->enqueue_calendar_assets();

		// Render calendar
		$this->calendar->render();
	}

	/**
	 * Enqueue calendar assets
	 */
	private function enqueue_calendar_assets() {
		// Get settings
		$settings = $this->settings->get_settings();

		// Enqueue custom CSS
		wp_enqueue_style(
			'tt-calendar-style',
			TIME_TRACKING_PLUGIN_URL . 'assets/css/style.css',
			array(),
			TIME_TRACKING_VERSION
		);

		// Enqueue custom JS
		wp_enqueue_script(
			'tt-calendar-script',
			TIME_TRACKING_PLUGIN_URL . 'assets/js/calendar.js',
			array(),
			TIME_TRACKING_VERSION,
			true
		);

		// Localize script with data and translations
		$start_time   = isset( $settings['start_time'] ) ? $settings['start_time'] : '09:00';
		$end_time     = isset( $settings['end_time'] ) ? $settings['end_time'] : '18:00';
		$working_days = isset( $settings['working_days'] ) ? $settings['working_days'] : array( 1, 2, 3, 4, 5 );

		// Check if weekends should be hidden
		$hide_weekends = ! ( in_array( 0, $working_days, true ) || in_array( 6, $working_days, true ) );

		wp_localize_script(
			'tt-calendar-script',
			'ttCalendarData',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'tt_nonce' ),
				'startTime'     => $start_time,
				'endTime'       => $end_time,
				'hideWeekends'  => $hide_weekends,
				'i18n'          => array(
					'calendarLoaded'          => __( 'Calendar loaded successfully', 'time-tracking' ),
					'errorLoadingTasks'       => __( 'Error loading tasks', 'time-tracking' ),
					'taskSaved'               => __( 'Task saved successfully!', 'time-tracking' ),
					'errorSavingTask'         => __( 'Error saving task', 'time-tracking' ),
					'confirmDeleteTask'       => __( 'Are you sure you want to delete this task?', 'time-tracking' ),
					'taskDeleted'             => __( 'Task deleted successfully!', 'time-tracking' ),
					'errorDeletingTask'       => __( 'Error deleting task', 'time-tracking' ),
					'errorLoadingCategories'  => __( 'Error loading categories', 'time-tracking' ),
					'categorySaved'           => __( 'Category saved successfully!', 'time-tracking' ),
					'errorSavingCategory'     => __( 'Error saving category', 'time-tracking' ),
					'confirmDeleteCategory'   => __( 'Are you sure you want to delete this category?', 'time-tracking' ),
					'categoryDeleted'         => __( 'Category deleted successfully!', 'time-tracking' ),
					'errorDeletingCategory'   => __( 'Error deleting category', 'time-tracking' ),
					'saveTaskFirst'           => __( 'Please save the task first before starting the timer', 'time-tracking' ),
					'timerStarted'            => __( 'Timer started', 'time-tracking' ),
					'timeLogSaved'            => __( 'Time log saved', 'time-tracking' ),
					'cannotSaveTimeLog'       => __( 'Cannot save time log - task not saved', 'time-tracking' ),
					'errorSavingTimeLog'      => __( 'Error saving time log', 'time-tracking' ),
					'errorLoadingTimeLogs'    => __( 'Error loading time logs', 'time-tracking' ),
					'noteUpdated'             => __( 'Note updated', 'time-tracking' ),
					'errorUpdatingNote'       => __( 'Error updating note', 'time-tracking' ),
					'confirmDeleteTimeLog'    => __( 'Delete this time log?', 'time-tracking' ),
					'timeLogDeleted'          => __( 'Time log deleted', 'time-tracking' ),
					'errorDeletingTimeLog'    => __( 'Error deleting time log', 'time-tracking' ),
				),
			)
		);
	}
}

// Initialize the plugin
TimeTrackingPlugin::get_instance();
