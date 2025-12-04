<?php
/**
 * Calendar Page Handler
 *
 * @package TimeTracking
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Time Tracking Calendar Class
 */
class TT_Calendar {

	/**
	 * Settings instance
	 *
	 * @var TT_Settings
	 */
	private $settings;

	/**
	 * Constructor
	 *
	 * @param TT_Settings $settings Settings instance.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Render calendar page
	 */
	public function render() {
		$settings     = $this->settings->get_settings();

		// Get working hours and days
		$start_time   = isset( $settings['start_time'] ) ? $settings['start_time'] : '09:00';
		$end_time     = isset( $settings['end_time'] ) ? $settings['end_time'] : '18:00';
		$working_days = isset( $settings['working_days'] ) ? $settings['working_days'] : array( 1, 2, 3, 4, 5 );

		// Convert working days to boolean array for FullCalendar
		$hide_weekends = ! ( in_array( 0, $working_days, true ) || in_array( 6, $working_days, true ) );
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Time Tracking', 'time-tracking' ); ?></title>
			
			<!-- Tailwind CSS -->
			<script src="https://cdn.tailwindcss.com"></script>
			
			<!-- FullCalendar CSS & JS -->
			<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
			<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
			
			<!-- Alpine.js for reactivity -->
			<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
			
			<!-- SortableJS for drag and drop -->
			<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
			
			<!-- Font Awesome -->
			<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
			
			<!-- Custom Styles -->
			<link rel="stylesheet" href="<?php echo esc_url( TIME_TRACKING_PLUGIN_URL . 'assets/css/style.css' ); ?>">
		</head>
		<body class="bg-gray-50 time-tracking-page">
			
		<!-- Notification Container -->
		<div class="notification-container" x-data="notificationSystem()" id="notificationContainer">
			<template x-for="notification in notifications" :key="notification.id">
				<div 
					:class="`notification notification-${notification.type} ${notification.hiding ? 'hiding' : ''}`"
					x-show="notification.visible"
				>
					<i :class="`fas fa-${getIcon(notification.type)}`"></i>
					<span x-text="notification.message"></span>
				</div>
			</template>
		</div>
			
		<div x-data="timeTrackingApp()" x-init="init()" class="flex h-screen">
			
			<!-- Main Calendar Area -->
			<div class="flex-1 overflow-auto p-6">
				<div class="bg-white rounded-lg shadow-lg p-6 relative">
					
					<!-- Loading Overlay -->
					<div x-show="isLoading" class="absolute inset-0 bg-white bg-opacity-90 flex items-center justify-center z-50 rounded-lg">
						<div class="text-center">
							<i class="fas fa-spinner fa-spin text-5xl text-blue-500 mb-4"></i>
							<p class="text-gray-600 text-lg font-semibold"><?php esc_html_e( 'Loading calendar...', 'time-tracking' ); ?></p>
						</div>
					</div>
					
					<!-- Header -->
					<div class="flex justify-between items-center">
						<div>
							<h1 class="text-3xl font-bold text-gray-800"><?php esc_html_e( 'Time Tracking', 'time-tracking' ); ?></h1>
							<p class="text-gray-600">
								<?php
								/* translators: 1: Start time, 2: End time */
								printf( esc_html__( 'Work Schedule (%1$s - %2$s)', 'time-tracking' ), esc_html( $start_time ), esc_html( $end_time ) );
								?>
							</p>
						</div>
						
						<div class="flex gap-2">
							<button @click="toggleTodoSidebar()" class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg font-semibold">
								<i class="fas fa-list-check"></i> <?php esc_html_e( 'TO-DO', 'time-tracking' ); ?>
							</button>
							<button @click="toggleSidebar()" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold">
								<i class="fas fa-cog"></i> <?php esc_html_e( 'Task Settings', 'time-tracking' ); ?>
							</button>
						</div>
					</div>
					
					<!-- Calendar Container -->
					<div id="calendar"></div>
					
				</div>
			</div>
			
			<!-- Sidebar -->
			<div 
				x-show="sidebarOpen" 
				x-transition
				class="w-96 bg-white shadow-2xl sidebar border-l border-gray-200"
			>
				<div class="p-6">
					
					<!-- Close Button -->
					<div class="flex justify-between items-center mb-6">
						<h2 class="text-2xl font-bold text-gray-800" x-text="activeTab === 'task' ? '<?php echo esc_js( __( 'Task Details', 'time-tracking' ) ); ?>' : '<?php echo esc_js( __( 'Categories', 'time-tracking' ) ); ?>'"></h2>
						<button @click="closeSidebar()" class="text-gray-500 hover:text-gray-700">
							<i class="fas fa-times text-xl"></i>
						</button>
					</div>
					
					<!-- Tabs -->
					<div class="flex gap-2 border-b">
						<button 
							@click="activeTab = 'task'" 
							:class="activeTab === 'task' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-600'"
							class="px-4 py-2 font-semibold"
						>
							<i class="fas fa-tasks"></i> <?php esc_html_e( 'Task', 'time-tracking' ); ?>
						</button>
						<button 
							@click="activeTab = 'categories'" 
							:class="activeTab === 'categories' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-600'"
							class="px-4 py-2 font-semibold"
						>
							<i class="fas fa-folder"></i> <?php esc_html_e( 'Categories', 'time-tracking' ); ?>
						</button>
					</div>
					
					<!-- Task Tab -->
					<div x-show="activeTab === 'task'">
						<form @submit.prevent="saveTask()">
							
							<!-- Task Name -->
							<div class="mb-3">
								<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Task Name', 'time-tracking' ); ?></label>
								<input 
									type="text" 
									x-model="currentTask.title"
									class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
									placeholder="<?php esc_attr_e( 'Enter task name', 'time-tracking' ); ?>"
									required
								>
							</div>
							
							<!-- Start Date & Time -->
							<div class="grid grid-cols-2 gap-4 mb-3">
								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Start Date', 'time-tracking' ); ?></label>
									<input 
										type="date" 
										x-model="currentTask.startDate"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
										required
									>
								</div>
								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Start Time', 'time-tracking' ); ?></label>
									<input 
										type="time" 
										x-model="currentTask.startTime"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
										required
									>
								</div>
							</div>
							
							<!-- End Date & Time -->
							<div class="grid grid-cols-2 gap-4 mb-3">
								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'End Date', 'time-tracking' ); ?></label>
									<input 
										type="date" 
										x-model="currentTask.endDate"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
										required
									>
								</div>
								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'End Time', 'time-tracking' ); ?></label>
									<input 
										type="time" 
										x-model="currentTask.endTime"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
										required
									>
								</div>
							</div>
							
							<!-- Primary Category -->
							<div class="mb-3">
								<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Primary Category', 'time-tracking' ); ?></label>
								<select
									x-model="currentTask.category"
									class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
								>
									<option value=""><?php esc_html_e( 'Select Primary Category', 'time-tracking' ); ?></option>
									<template x-for="category in categories" :key="category.id">
										<option :value="category.id" x-text="category.name"></option>
									</template>
								</select>
							</div>

							<!-- Secondary Categories -->
							<div class="mb-3">
								<label class="block text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e( 'Secondary Categories', 'time-tracking' ); ?></label>
								<p class="text-xs text-gray-500 mb-2"><?php esc_html_e( 'Select additional categories to show as colored circles on the calendar', 'time-tracking' ); ?></p>
								<div class="max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-2 bg-gray-50">
									<template x-for="category in categories" :key="category.id">
										<label class="flex items-center gap-2 py-1 px-2 hover:bg-gray-100 rounded cursor-pointer">
											<input
												type="checkbox"
												:value="category.id"
												x-model="currentTask.secondaryCategories"
												class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500"
											>
											<div
												class="w-4 h-4 rounded-full border border-gray-300"
												:style="`background-color: ${category.color}`"
											></div>
											<span class="text-sm" x-text="category.name"></span>
										</label>
									</template>
								</div>
							</div>
							
							<!-- Description -->
							<div class="mb-3">
								<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Description', 'time-tracking' ); ?></label>
								<textarea 
									x-model="currentTask.description"
									rows="2"
									class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
									placeholder="<?php esc_attr_e( 'Add task description...', 'time-tracking' ); ?>"
								></textarea>
							</div>
							
							<!-- Time Tracking Section -->
							<div class="mb-4 p-3 bg-gradient-to-r from-blue-50 to-blue-50 rounded-lg" x-data="{ timeTrackingExpanded: false }">
								<button @click="timeTrackingExpanded = !timeTrackingExpanded" type="button" class="w-full flex justify-between items-center text-base font-semibold text-gray-800 mb-2 hover:text-blue-600">
									<span>
										<i class="fas fa-stopwatch"></i> <?php esc_html_e( 'Time Tracking', 'time-tracking' ); ?>
									</span>
									<i class="fas transition-transform duration-200" :class="timeTrackingExpanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
								</button>
								
								<div x-show="timeTrackingExpanded" x-collapse>
								
								<!-- Timer Display -->
								<div class="timer-display" x-text="formatTime(timerSeconds)"></div>
								
								<!-- Timer Controls -->
								<div class="flex gap-2 mb-3">
									<button 
										type="button"
										@click="startTimer()"
										x-show="!timerRunning"
										class="flex-1 px-3 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold text-sm"
									>
										<i class="fas fa-play"></i> <?php esc_html_e( 'Start', 'time-tracking' ); ?>
									</button>
									<button 
										type="button"
										@click="stopTimer()"
										x-show="timerRunning"
										class="flex-1 px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold text-sm"
									>
										<i class="fas fa-stop"></i> <?php esc_html_e( 'Stop', 'time-tracking' ); ?>
									</button>
									<button 
										type="button"
										@click="resetTimer()"
										class="px-3 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-sm"
									>
										<i class="fas fa-redo"></i>
									</button>
								</div>
								
								<!-- Time Logs -->
								<div class="mt-3">
									<h4 class="text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e( 'Time Logs', 'time-tracking' ); ?></h4>
									<div class="max-h-[200px] overflow-y-auto space-y-1">
										<template x-for="log in currentTaskTimeLogs" :key="log.id">
											<div class="time-log-entry">
												<div class="flex justify-between items-center">
													<span class="text-sm font-semibold" x-text="formatTime(log.duration)"></span>
													<div class="flex items-center gap-2">
														<span class="text-xs text-gray-500" x-text="new Date(log.timestamp).toLocaleString()"></span>
														<button 
															@click="deleteTimeLog(log.id)"
															class="text-red-500 hover:text-red-700"
															type="button"
														>
															<i class="fas fa-times text-xs"></i>
														</button>
													</div>
												</div>
												<div class="mt-1">
													<input 
														x-show="editingLogId === log.id"
														type="text"
														:value="log.note"
														@blur="editingLogId = null"
														@keyup.enter="updateTimeLogNote(log.id, $event.target.value); editingLogId = null"
														@keyup.escape="editingLogId = null"
														x-ref="noteInput"
														class="w-full text-xs px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500"
														placeholder="<?php esc_attr_e( 'Add note and press Enter...', 'time-tracking' ); ?>"
													/>
													<p 
														x-show="editingLogId !== log.id"
														@click="editingLogId = log.id; $nextTick(() => { const input = $el.parentElement.querySelector('input'); if(input) { input.focus(); input.select(); } })"
														class="text-xs text-gray-600 cursor-pointer hover:bg-gray-100 px-1 rounded"
														x-text="log.note || '<?php echo esc_js( __( 'Click to add note...', 'time-tracking' ) ); ?>'"
													></p>
												</div>
											</div>
										</template>
										<div x-show="currentTaskTimeLogs.length === 0" class="text-sm text-gray-500 text-center py-4">
											<?php esc_html_e( 'No time logs yet', 'time-tracking' ); ?>
										</div>
									</div>
								</div>
								</div>
							</div>
							
							<!-- Action Buttons -->
							<div class="flex gap-2">
								<button 
									type="submit"
									class="flex-1 px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold"
								>
									<i class="fas fa-save"></i> <?php esc_html_e( 'Save Task', 'time-tracking' ); ?>
								</button>
								<button 
									type="button"
									@click="deleteTask()"
									x-show="currentTask.id"
									class="px-6 py-3 bg-red-500 hover:bg-red-600 text-white rounded-lg"
								>
									<i class="fas fa-trash"></i>
								</button>
							</div>
							
						</form>
					</div>
					
					<!-- Categories Tab -->
					<div x-show="activeTab === 'categories'">

						<!-- Add New Category -->
						<div class="mb-6 p-4 bg-blue-50 rounded-lg">
							<h3 class="text-lg font-semibold text-gray-800 mb-3"><?php esc_html_e( 'Add New Category', 'time-tracking' ); ?></h3>
							<form @submit.prevent="saveCategory()">
								<div class="mb-3">
									<label class="block text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e( 'Category Name', 'time-tracking' ); ?></label>
									<input
										type="text"
										x-model="newCategory.name"
										class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
										placeholder="<?php esc_attr_e( 'e.g., Development, Meeting', 'time-tracking' ); ?>"
										required
									>
								</div>
								<div class="mb-3">
									<label class="block text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e( 'Background Color', 'time-tracking' ); ?></label>
									<div class="flex gap-2">
										<input
											type="color"
											x-model="newCategory.color"
											class="w-16 h-10 border border-gray-300 rounded cursor-pointer"
										>
										<input
											type="text"
											x-model="newCategory.color"
											class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
											placeholder="#3b82f6"
										>
									</div>
								</div>
								<div class="mb-3">
									<label class="block text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e( 'Text Color', 'time-tracking' ); ?></label>
									<div class="flex gap-2">
										<input
											type="color"
											x-model="newCategory.textColor"
											class="w-16 h-10 border border-gray-300 rounded cursor-pointer"
										>
										<input
											type="text"
											x-model="newCategory.textColor"
											class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
											placeholder="#ffffff"
										>
									</div>
								</div>
								<button
									type="submit"
									class="w-full px-6 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold"
								>
									<i class="fas fa-plus"></i> <?php esc_html_e( 'Add Category', 'time-tracking' ); ?>
								</button>
							</form>
						</div>

						<!-- Categories List -->
						<div>
							<h3 class="text-lg font-semibold text-gray-800 mb-3"><?php esc_html_e( 'Existing Categories', 'time-tracking' ); ?></h3>
							<div class="space-y-2">
								<template x-for="category in categories" :key="category.id">
									<div class="p-3 bg-gray-50 rounded-lg hover:bg-gray-100">
										<!-- View Mode -->
										<div x-show="editingCategoryId !== category.id" class="flex items-center justify-between">
											<div class="flex items-center gap-3">
												<div
													class="w-8 h-8 rounded"
													:style="`background-color: ${category.color}`"
												></div>
												<span class="font-semibold" x-text="category.name"></span>
											</div>
											<div class="flex gap-2">
												<button
													@click="startEditCategory(category)"
													class="text-blue-500 hover:text-blue-700"
												>
													<i class="fas fa-edit"></i>
												</button>
												<button
													@click="deleteCategory(category.id)"
													class="text-red-500 hover:text-red-700"
												>
													<i class="fas fa-trash"></i>
												</button>
											</div>
										</div>

										<!-- Edit Mode -->
										<div x-show="editingCategoryId === category.id">
											<form @submit.prevent="updateCategory(category.id)">
												<div class="mb-2">
													<input
														type="text"
														x-model="editingCategory.name"
														class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
														required
													>
												</div>
												<div class="mb-2">
													<label class="block text-xs font-semibold text-gray-600 mb-1"><?php esc_html_e( 'Background', 'time-tracking' ); ?></label>
													<div class="flex gap-2">
														<input
															type="color"
															x-model="editingCategory.color"
															class="w-12 h-8 border border-gray-300 rounded cursor-pointer"
														>
														<input
															type="text"
															x-model="editingCategory.color"
															class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
														>
													</div>
												</div>
												<div class="mb-2">
													<label class="block text-xs font-semibold text-gray-600 mb-1"><?php esc_html_e( 'Text', 'time-tracking' ); ?></label>
													<div class="flex gap-2">
														<input
															type="color"
															x-model="editingCategory.textColor"
															class="w-12 h-8 border border-gray-300 rounded cursor-pointer"
														>
														<input
															type="text"
															x-model="editingCategory.textColor"
															class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm"
														>
													</div>
												</div>
												<div class="flex gap-2">
													<button
														type="submit"
														class="flex-1 px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm font-semibold"
													>
														<i class="fas fa-save"></i> <?php esc_html_e( 'Save', 'time-tracking' ); ?>
													</button>
													<button
														type="button"
														@click="cancelEditCategory()"
														class="flex-1 px-3 py-1 bg-gray-500 hover:bg-gray-600 text-white rounded text-sm font-semibold"
													>
														<i class="fas fa-times"></i> <?php esc_html_e( 'Cancel', 'time-tracking' ); ?>
													</button>
												</div>
											</form>
										</div>
									</div>
								</template>
							</div>
						</div>

					</div>
					
				</div>
			</div>
			
			<!-- TODO Sidebar -->
			<div 
				x-show="todoSidebarOpen" 
				x-transition
				class="w-96 bg-white shadow-2xl sidebar border-l border-gray-200"
			>
				<div class="p-6">
					
					<!-- Header -->
					<div class="flex justify-between items-center mb-6">
						<h2 class="text-2xl font-bold text-gray-800"><?php esc_html_e( 'TO-DO List', 'time-tracking' ); ?></h2>
						<button @click="closeTodoSidebar()" class="text-gray-500 hover:text-gray-700">
							<i class="fas fa-times text-xl"></i>
						</button>
					</div>
					
					<!-- Add New TODO Form -->
					<form @submit.prevent="addTodo()" class="mb-6">
						<div class="mb-3">
							<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'New TO-DO Item', 'time-tracking' ); ?></label>
							<textarea 
								x-model="newTodoText"
								rows="2"
								class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
								placeholder="<?php esc_attr_e( 'What needs to be done?', 'time-tracking' ); ?>"
								required
							></textarea>
						</div>
						
						<!-- Optional Dates -->
						<div class="grid grid-cols-2 gap-3 mb-3">
							<div>
								<label class="block text-xs font-semibold text-gray-600 mb-1"><?php esc_html_e( 'Start Date (Optional)', 'time-tracking' ); ?></label>
								<input 
									type="date" 
									x-model="newTodoStartDate"
									class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
								>
							</div>
							<div>
								<label class="block text-xs font-semibold text-gray-600 mb-1"><?php esc_html_e( 'Deadline (Optional)', 'time-tracking' ); ?></label>
								<input 
									type="date" 
									x-model="newTodoEndDate"
									class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
								>
							</div>
						</div>
						
						<button
							type="submit"
							class="w-full px-6 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg font-semibold"
						>
							<i class="fas fa-plus"></i> <?php esc_html_e( 'Add TO-DO', 'time-tracking' ); ?>
						</button>
					</form>
					
					<!-- TODO List -->
					<div>
						<h3 class="text-lg font-semibold text-gray-800 mb-3"><?php esc_html_e( 'Your Items', 'time-tracking' ); ?></h3>
						<div id="todoList" class="space-y-2 max-h-[calc(100vh-400px)] overflow-y-auto">
							<template x-for="todo in todos" :key="todo.id">
								<div 
									:data-id="todo.id"
									class="todo-item p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-move border border-gray-200"
									:class="{ 'opacity-50': todo.completed }"
								>
									<div class="flex items-start gap-3">
										<!-- Drag Handle -->
										<div class="cursor-grab">
											<i class="fas fa-grip-vertical text-gray-400"></i>
										</div>
										
										<!-- Checkbox -->
										<input 
											type="checkbox"
											:checked="todo.completed"
											@change="toggleTodoComplete(todo.id, $event.target.checked)"
											class="w-5 h-5 text-purple-600 rounded focus:ring-2 focus:ring-purple-500 cursor-pointer"
										>
										
										<!-- Content -->
										<div class="flex-1">
											<p 
												class="text-sm font-medium text-gray-800 whitespace-pre-wrap"
												:class="{ 'line-through': todo.completed }"
												x-text="todo.text"
											></p>
											<div x-show="todo.start_date || todo.end_date" class="text-xs text-gray-500 mt-1">
												<span x-show="todo.start_date">
													<i class="fas fa-play-circle"></i> <span x-text="todo.start_date"></span>
												</span>
												<span x-show="todo.end_date" class="ml-2">
													<i class="fas fa-flag-checkered"></i> <span x-text="todo.end_date"></span>
												</span>
												</div>
										</div>
										
										<!-- Delete Button -->
										<button
											@click="deleteTodo(todo.id)"
											class="text-red-500 hover:text-red-700 flex-shrink-0"
										>
											<i class="fas fa-trash text-sm"></i>
										</button>
									</div>
								</div>
							</template>
							
							<div x-show="todos.length === 0" class="text-center py-8 text-gray-500">
								<i class="fas fa-clipboard-list text-4xl mb-2 opacity-30"></i>
								<p><?php esc_html_e( 'No TO-DO items yet. Add one above!', 'time-tracking' ); ?></p>
							</div>
						</div>
					</div>
					
				</div>
			</div>
			
		</div>
		
		<script src="<?php echo esc_url( TIME_TRACKING_PLUGIN_URL . 'assets/js/calendar.js' ); ?>"></script>
		
		</body>
		</html>
		<?php
	}
}
