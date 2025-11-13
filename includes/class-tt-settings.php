<?php
/**
 * Settings Page Handler
 *
 * @package TimeTracking
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Time Tracking Settings Class
 */
class TT_Settings {

	/**
	 * Settings page slug
	 *
	 * @var string
	 */
	private $page_slug = 'time-tracking-settings';

	/**
	 * Option name for settings
	 *
	 * @var string
	 */
	private $option_name = 'tt_settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_tt_export_data', array( $this, 'ajax_export_data' ) );
		add_action( 'wp_ajax_tt_import_data', array( $this, 'ajax_import_data' ) );
		add_action( 'wp_ajax_tt_clear_user_data', array( $this, 'ajax_clear_user_data' ) );
		add_action( 'wp_ajax_tt_get_user_data_count', array( $this, 'ajax_get_user_data_count' ) );
	}

	/**
	 * Add settings page to menu
	 */
	public function add_settings_page() {
		add_submenu_page(
			'time-tracking',
			__( 'Time Tracking Settings', 'time-tracking' ),
			__( 'Settings', 'time-tracking' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( $this->option_name, $this->option_name, array( $this, 'sanitize_settings' ) );

		// General Settings Section
		add_settings_section(
			'tt_general_section',
			__( 'General Settings', 'time-tracking' ),
			array( $this, 'render_general_section' ),
			$this->page_slug
		);

		// Working Hours Settings Section
		add_settings_section(
			'tt_working_hours_section',
			__( 'Working Hours & Days', 'time-tracking' ),
			array( $this, 'render_working_hours_section' ),
			$this->page_slug
		);

		// Category Settings Section
		add_settings_section(
			'tt_category_section',
			__( 'Default Categories', 'time-tracking' ),
			array( $this, 'render_category_section' ),
			$this->page_slug
		);

		// Role Management Section
		add_settings_section(
			'tt_role_section',
			__( 'Role Management', 'time-tracking' ),
			array( $this, 'render_role_section' ),
			$this->page_slug
		);

		// Data Management Section
		add_settings_section(
			'tt_data_section',
			__( 'Data Management', 'time-tracking' ),
			array( $this, 'render_data_section' ),
			$this->page_slug
		);
	}

	/**
	 * Get default settings
	 *
	 * @return array
	 */
	private function get_default_settings() {
		return array(
			'timezone'           => wp_timezone_string(),
			'color_scheme'       => 'light',
			'start_time'         => '09:00',
			'end_time'           => '18:00',
			'working_days'       => array( 1, 2, 3, 4, 5 ), // Monday to Friday
			'allowed_roles'      => array( 'administrator', 'editor', 'author', 'contributor' ),
			'default_categories' => array(),
		);
	}

	/**
	 * Get settings
	 *
	 * @return array
	 */
	public function get_settings() {
		$defaults = $this->get_default_settings();
		$settings = get_option( $this->option_name, $defaults );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Raw input data.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		if ( isset( $input['timezone'] ) ) {
			$sanitized['timezone'] = sanitize_text_field( $input['timezone'] );
		}

		if ( isset( $input['color_scheme'] ) ) {
			$sanitized['color_scheme'] = in_array( $input['color_scheme'], array( 'light', 'dark', 'auto' ), true ) ? $input['color_scheme'] : 'light';
		}

		if ( isset( $input['start_time'] ) ) {
			$sanitized['start_time'] = sanitize_text_field( $input['start_time'] );
		}

		if ( isset( $input['end_time'] ) ) {
			$sanitized['end_time'] = sanitize_text_field( $input['end_time'] );
		}

		if ( isset( $input['working_days'] ) && is_array( $input['working_days'] ) ) {
			$sanitized['working_days'] = array_map( 'intval', $input['working_days'] );
		}

		if ( isset( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ) {
			$sanitized['allowed_roles'] = array_map( 'sanitize_text_field', $input['allowed_roles'] );
		}

		if ( isset( $input['default_categories'] ) ) {
			$sanitized['default_categories'] = $input['default_categories'];
		}

		return $sanitized;
	}

	/**
	 * Render general section
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure general time tracking settings.', 'time-tracking' ) . '</p>';
	}

	/**
	 * Render working hours section
	 */
	public function render_working_hours_section() {
		echo '<p>' . esc_html__( 'Set default working hours and days for the calendar.', 'time-tracking' ) . '</p>';
	}

	/**
	 * Render category section
	 */
	public function render_category_section() {
		echo '<p>' . esc_html__( 'Define default categories for new users.', 'time-tracking' ) . '</p>';
	}

	/**
	 * Render role section
	 */
	public function render_role_section() {
		echo '<p>' . esc_html__( 'Select which user roles can access the time tracking plugin.', 'time-tracking' ) . '</p>';
	}

	/**
	 * Render data section
	 */
	public function render_data_section() {
		echo '<p>' . esc_html__( 'Export, import, or clear time tracking data.', 'time-tracking' ) . '</p>';
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'time-tracking' ) );
		}

		$settings = $this->get_settings();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( $this->option_name );
				?>

				<!-- General Settings -->
				<div class="tt-settings-section">
					<h2><?php esc_html_e( 'General Settings', 'time-tracking' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="tt_timezone"><?php esc_html_e( 'Timezone', 'time-tracking' ); ?></label>
							</th>
							<td>
								<select name="<?php echo esc_attr( $this->option_name ); ?>[timezone]" id="tt_timezone" class="regular-text">
									<?php
									$timezones        = timezone_identifiers_list();
									$current_timezone = $settings['timezone'];
									foreach ( $timezones as $timezone ) {
										printf(
											'<option value="%s" %s>%s</option>',
											esc_attr( $timezone ),
											selected( $current_timezone, $timezone, false ),
											esc_html( $timezone )
										);
									}
									?>
								</select>
								<p class="description">
									<?php
									printf(
										/* translators: %s: WordPress timezone setting */
										esc_html__( 'Default: %s (WordPress timezone)', 'time-tracking' ),
										esc_html( wp_timezone_string() )
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="tt_color_scheme"><?php esc_html_e( 'Color Scheme', 'time-tracking' ); ?></label>
							</th>
							<td>
								<select name="<?php echo esc_attr( $this->option_name ); ?>[color_scheme]" id="tt_color_scheme">
									<option value="light" <?php selected( $settings['color_scheme'], 'light' ); ?>><?php esc_html_e( 'Light', 'time-tracking' ); ?></option>
									<option value="dark" <?php selected( $settings['color_scheme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'time-tracking' ); ?></option>
									<option value="auto" <?php selected( $settings['color_scheme'], 'auto' ); ?>><?php esc_html_e( 'Auto (System)', 'time-tracking' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Choose the color scheme for the calendar interface.', 'time-tracking' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Working Hours & Days -->
				<div class="tt-settings-section">
					<h2><?php esc_html_e( 'Working Hours & Days', 'time-tracking' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="tt_start_time"><?php esc_html_e( 'Start Time', 'time-tracking' ); ?></label>
							</th>
							<td>
								<input type="time" name="<?php echo esc_attr( $this->option_name ); ?>[start_time]" id="tt_start_time" value="<?php echo esc_attr( $settings['start_time'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Default start time for the workday.', 'time-tracking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="tt_end_time"><?php esc_html_e( 'End Time', 'time-tracking' ); ?></label>
							</th>
							<td>
								<input type="time" name="<?php echo esc_attr( $this->option_name ); ?>[end_time]" id="tt_end_time" value="<?php echo esc_attr( $settings['end_time'] ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'Default end time for the workday.', 'time-tracking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Working Days', 'time-tracking' ); ?>
							</th>
							<td>
								<fieldset>
									<?php
									$days = array(
										0 => __( 'Sunday', 'time-tracking' ),
										1 => __( 'Monday', 'time-tracking' ),
										2 => __( 'Tuesday', 'time-tracking' ),
										3 => __( 'Wednesday', 'time-tracking' ),
										4 => __( 'Thursday', 'time-tracking' ),
										5 => __( 'Friday', 'time-tracking' ),
										6 => __( 'Saturday', 'time-tracking' ),
									);
									foreach ( $days as $day_num => $day_name ) {
										$checked = in_array( $day_num, $settings['working_days'], true );
										printf(
											'<label><input type="checkbox" name="%s[working_days][]" value="%d" %s /> %s</label><br>',
											esc_attr( $this->option_name ),
											absint( $day_num ),
											checked( $checked, true, false ),
											esc_html( $day_name )
										);
									}
									?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Select the days of the week that are considered working days.', 'time-tracking' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Role Management -->
				<div class="tt-settings-section">
					<h2><?php esc_html_e( 'Role Management', 'time-tracking' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Allowed Roles', 'time-tracking' ); ?>
							</th>
							<td>
								<fieldset>
									<?php
									$roles = wp_roles()->get_names();
									foreach ( $roles as $role_id => $role_name ) {
										$checked = in_array( $role_id, $settings['allowed_roles'], true );
										printf(
											'<label><input type="checkbox" name="%s[allowed_roles][]" value="%s" %s /> %s</label><br>',
											esc_attr( $this->option_name ),
											esc_attr( $role_id ),
											checked( $checked, true, false ),
											esc_html( translate_user_role( $role_name ) )
										);
									}
									?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Select which user roles can access and use the time tracking plugin.', 'time-tracking' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Save Settings', 'time-tracking' ) ); ?>
			</form>

			<!-- Data Management -->
			<div class="tt-settings-section" style="margin-top: 40px;">
				<h2><?php esc_html_e( 'Data Management', 'time-tracking' ); ?></h2>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Export Data', 'time-tracking' ); ?>
						</th>
						<td>
							<button type="button" class="button button-secondary" id="tt-export-data">
								<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export My Data', 'time-tracking' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Export all your time tracking data as JSON.', 'time-tracking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Import Data', 'time-tracking' ); ?>
						</th>
						<td>
							<input type="file" id="tt-import-file" accept=".json" style="display: none;" />
							<button type="button" class="button button-secondary" id="tt-import-data">
								<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Import Data', 'time-tracking' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Import time tracking data from a JSON file.', 'time-tracking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Clear Data', 'time-tracking' ); ?>
						</th>
						<td>
							<div id="tt-user-count-info" style="margin-bottom: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
								<p style="margin: 0;">
									<span class="dashicons dashicons-info"></span>
									<?php esc_html_e( 'Loading user data information...', 'time-tracking' ); ?>
								</p>
							</div>
							<button type="button" class="button button-danger" id="tt-clear-data" style="background: #dc3232; border-color: #dc3232; color: #fff;">
								<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear My Data', 'time-tracking' ); ?>
							</button>
							<p class="description" style="color: #d63638;">
								<strong><?php esc_html_e( 'Warning:', 'time-tracking' ); ?></strong> 
								<?php esc_html_e( 'This will permanently delete all your time tracking data. This action cannot be undone.', 'time-tracking' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<style>
			.tt-settings-section {
				background: #fff;
				border: 1px solid #ccd0d4;
				padding: 20px;
				margin-top: 20px;
			}
			.tt-settings-section h2 {
				margin-top: 0;
				border-bottom: 1px solid #e0e0e0;
				padding-bottom: 10px;
			}
			.button-danger:hover {
				background: #c62d2d !important;
			}
		</style>

		<script>
		jQuery(document).ready(function($) {
			// Load user count on page load
			loadUserDataCount();

			// Export data
			$('#tt-export-data').on('click', function() {
				var button = $(this);
				button.prop('disabled', true);
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'tt_export_data',
						nonce: '<?php echo esc_js( wp_create_nonce( 'tt_nonce' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							// Create download
							var dataStr = JSON.stringify(response.data, null, 2);
							var dataBlob = new Blob([dataStr], {type: 'application/json'});
							var url = URL.createObjectURL(dataBlob);
							var link = document.createElement('a');
							link.href = url;
							link.download = 'time-tracking-export-' + Date.now() + '.json';
							link.click();
							URL.revokeObjectURL(url);
							alert('<?php echo esc_js( __( 'Data exported successfully!', 'time-tracking' ) ); ?>');
						} else {
							alert('<?php echo esc_js( __( 'Error exporting data:', 'time-tracking' ) ); ?> ' + (response.data || ''));
						}
						button.prop('disabled', false);
					},
					error: function() {
						alert('<?php echo esc_js( __( 'Error exporting data.', 'time-tracking' ) ); ?>');
						button.prop('disabled', false);
					}
				});
			});

			// Import data
			$('#tt-import-data').on('click', function() {
				$('#tt-import-file').click();
			});

			$('#tt-import-file').on('change', function(e) {
				var file = e.target.files[0];
				if (!file) return;

				var reader = new FileReader();
				reader.onload = function(e) {
					try {
						var data = JSON.parse(e.target.result);
						
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'tt_import_data',
								nonce: '<?php echo esc_js( wp_create_nonce( 'tt_nonce' ) ); ?>',
								import_data: JSON.stringify(data)
							},
							success: function(response) {
								if (response.success) {
									alert('<?php echo esc_js( __( 'Data imported successfully!', 'time-tracking' ) ); ?>');
									location.reload();
								} else {
									alert('<?php echo esc_js( __( 'Error importing data:', 'time-tracking' ) ); ?> ' + (response.data || ''));
								}
							},
							error: function() {
								alert('<?php echo esc_js( __( 'Error importing data.', 'time-tracking' ) ); ?>');
							}
						});
					} catch (err) {
						alert('<?php echo esc_js( __( 'Invalid JSON file.', 'time-tracking' ) ); ?>');
					}
				};
				reader.readAsText(file);
			});

			// Clear data
			$('#tt-clear-data').on('click', function() {
				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete ALL your time tracking data? This action cannot be undone!', 'time-tracking' ) ); ?>')) {
					return;
				}

				if (!confirm('<?php echo esc_js( __( 'This is your final warning. All your tasks, categories, and time logs will be permanently deleted. Continue?', 'time-tracking' ) ); ?>')) {
					return;
				}

				var button = $(this);
				button.prop('disabled', true);

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'tt_clear_user_data',
						nonce: '<?php echo esc_js( wp_create_nonce( 'tt_nonce' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							alert('<?php echo esc_js( __( 'All your data has been cleared.', 'time-tracking' ) ); ?>');
							location.reload();
						} else {
							alert('<?php echo esc_js( __( 'Error clearing data:', 'time-tracking' ) ); ?> ' + (response.data || ''));
							button.prop('disabled', false);
						}
					},
					error: function() {
						alert('<?php echo esc_js( __( 'Error clearing data.', 'time-tracking' ) ); ?>');
						button.prop('disabled', false);
					}
				});
			});

			function loadUserDataCount() {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'tt_get_user_data_count',
						nonce: '<?php echo esc_js( wp_create_nonce( 'tt_nonce' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							var info = response.data;
							var html = '<p style="margin: 0;"><span class="dashicons dashicons-info"></span> ';
							
							if (info.total_users > 1) {
								html += '<?php echo esc_js( __( 'There are', 'time-tracking' ) ); ?> <strong>' + info.total_users + '</strong> <?php echo esc_js( __( 'users using this plugin.', 'time-tracking' ) ); ?> ';
							} else {
								html += '<?php echo esc_js( __( 'You are the only user using this plugin.', 'time-tracking' ) ); ?> ';
							}
							
							html += '<?php echo esc_js( __( 'Your data:', 'time-tracking' ) ); ?> <strong>' + info.user_tasks + '</strong> <?php echo esc_js( __( 'tasks', 'time-tracking' ) ); ?>, ';
							html += '<strong>' + info.user_categories + '</strong> <?php echo esc_js( __( 'categories', 'time-tracking' ) ); ?>.';
							html += '</p>';
							
							$('#tt-user-count-info').html(html);
						}
					}
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * AJAX: Export user data
	 */
	public function ajax_export_data() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$user_id = get_current_user_id();

		// Get user's tasks
		$tasks_query = new WP_Query(
			array(
				'post_type'      => 'tt_task',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'author'         => $user_id,
			)
		);

		$tasks = array();
		foreach ( $tasks_query->posts as $post ) {
			$tasks[] = array(
				'title'       => $post->post_title,
				'startDate'   => get_post_meta( $post->ID, '_tt_start_date', true ),
				'startTime'   => get_post_meta( $post->ID, '_tt_start_time', true ),
				'endDate'     => get_post_meta( $post->ID, '_tt_end_date', true ),
				'endTime'     => get_post_meta( $post->ID, '_tt_end_time', true ),
				'description' => get_post_meta( $post->ID, '_tt_description', true ),
				'time_logs'   => get_post_meta( $post->ID, '_tt_time_logs', true ),
			);
		}

		// Get user's categories
		$categories_terms = get_terms(
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
		foreach ( $categories_terms as $term ) {
			$categories[] = array(
				'name'  => get_term_meta( $term->term_id, '_tt_category_display_name', true ),
				'color' => get_term_meta( $term->term_id, '_tt_category_color', true ),
			);
		}

		$export_data = array(
			'version'    => TIME_TRACKING_VERSION,
			'exported'   => current_time( 'mysql' ),
			'user_id'    => $user_id,
			'tasks'      => $tasks,
			'categories' => $categories,
		);

		wp_send_json_success( $export_data );
	}

	/**
	 * AJAX: Import user data
	 */
	public function ajax_import_data() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$import_data = json_decode( stripslashes( $_POST['import_data'] ), true );

		if ( ! $import_data || ! isset( $import_data['tasks'] ) ) {
			wp_send_json_error( __( 'Invalid import data', 'time-tracking' ) );
		}

		$user_id = get_current_user_id();

		// Import categories first
		$category_map = array();
		if ( isset( $import_data['categories'] ) && is_array( $import_data['categories'] ) ) {
			foreach ( $import_data['categories'] as $cat ) {
				$term = wp_insert_term(
					sanitize_text_field( $cat['name'] ) . '_user_' . $user_id,
					'tt_category'
				);

				if ( ! is_wp_error( $term ) ) {
					update_term_meta( $term['term_id'], '_tt_category_color', sanitize_hex_color( $cat['color'] ) );
					update_term_meta( $term['term_id'], '_tt_category_user', $user_id );
					update_term_meta( $term['term_id'], '_tt_category_display_name', sanitize_text_field( $cat['name'] ) );
				}
			}
		}

		// Import tasks
		$imported_count = 0;
		foreach ( $import_data['tasks'] as $task ) {
			$post_id = wp_insert_post(
				array(
					'post_title'  => sanitize_text_field( $task['title'] ),
					'post_type'   => 'tt_task',
					'post_status' => 'publish',
					'post_author' => $user_id,
				)
			);

			if ( ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_tt_start_date', sanitize_text_field( $task['startDate'] ) );
				update_post_meta( $post_id, '_tt_start_time', sanitize_text_field( $task['startTime'] ) );
				update_post_meta( $post_id, '_tt_end_date', sanitize_text_field( $task['endDate'] ) );
				update_post_meta( $post_id, '_tt_end_time', sanitize_text_field( $task['endTime'] ) );
				update_post_meta( $post_id, '_tt_description', sanitize_textarea_field( $task['description'] ) );

				if ( isset( $task['time_logs'] ) ) {
					update_post_meta( $post_id, '_tt_time_logs', $task['time_logs'] );
				}

				$imported_count++;
			}
		}

		wp_send_json_success(
			array(
				/* translators: %d: number of imported tasks */
				'message' => sprintf( __( 'Imported %d tasks successfully', 'time-tracking' ), $imported_count ),
			)
		);
	}

	/**
	 * AJAX: Clear user data
	 */
	public function ajax_clear_user_data() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$user_id = get_current_user_id();

		// Delete user's tasks
		$tasks_query = new WP_Query(
			array(
				'post_type'      => 'tt_task',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'author'         => $user_id,
				'fields'         => 'ids',
			)
		);

		foreach ( $tasks_query->posts as $task_id ) {
			wp_delete_post( $task_id, true );
		}

		// Delete user's categories
		$categories = get_terms(
			array(
				'taxonomy'   => 'tt_category',
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => '_tt_category_user',
						'value'   => $user_id,
						'compare' => '=',
					),
				),
			)
		);

		foreach ( $categories as $cat_id ) {
			wp_delete_term( $cat_id, 'tt_category' );
		}

		wp_send_json_success(
			array(
				'message' => __( 'All your data has been cleared successfully', 'time-tracking' ),
			)
		);
	}

	/**
	 * AJAX: Get user data count
	 */
	public function ajax_get_user_data_count() {
		check_ajax_referer( 'tt_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'time-tracking' ) );
		}

		$user_id = get_current_user_id();

		// Count total users with data
		$users_with_tasks = $this->count_users_with_data();

		// Count user's tasks
		$user_tasks = new WP_Query(
			array(
				'post_type'      => 'tt_task',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'author'         => $user_id,
				'fields'         => 'ids',
			)
		);

		// Count user's categories
		$user_categories = get_terms(
			array(
				'taxonomy'   => 'tt_category',
				'hide_empty' => false,
				'fields'     => 'count',
				'meta_query' => array(
					array(
						'key'     => '_tt_category_user',
						'value'   => $user_id,
						'compare' => '=',
					),
				),
			)
		);

		wp_send_json_success(
			array(
				'total_users'      => $users_with_tasks,
				'user_tasks'       => $user_tasks->found_posts,
				'user_categories'  => $user_categories,
			)
		);
	}

	/**
	 * Count users with time tracking data
	 *
	 * @return int
	 */
	private function count_users_with_data() {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_author) 
				FROM {$wpdb->posts} 
				WHERE post_type = %s 
				AND post_status = 'publish'",
				'tt_task'
			)
		);

		return (int) $count;
	}
}
