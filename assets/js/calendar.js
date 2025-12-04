/**
 * Time Tracking Calendar JavaScript
 * @package TimeTracking
 */

// Global notification system
function notificationSystem() {
	return {
		notifications: [],
		
		getIcon(type) {
			const icons = {
				success: 'check-circle',
				error: 'exclamation-circle',
				info: 'info-circle',
				warning: 'exclamation-triangle'
			};
			return icons[type] || 'info-circle';
		},
		
		show(message, type = 'info', duration = 3000) {
			const id = Date.now() + Math.random();
			const notification = {
				id,
				message,
				type,
				visible: true,
				hiding: false
			};
			
			this.notifications.push(notification);
			
			// Auto remove after duration
			setTimeout(() => {
				const notif = this.notifications.find(n => n.id === id);
				if (notif) {
					notif.hiding = true;
					setTimeout(() => {
						this.notifications = this.notifications.filter(n => n.id !== id);
					}, 300);
				}
			}, duration);
		}
	};
}

// Global notification function
window.showNotification = function(message, type = 'info') {
	const container = document.querySelector('[x-data*="notificationSystem"]');
	if (container && container.__x) {
		container.__x.$data.show(message, type);
	}
};

function timeTrackingApp() {
	return {
		// State
		calendar: null,
		tasks: [],
		categories: [],
		sidebarOpen: true,
		activeTab: 'task',
		isLoading: true,
		
		// Current task
		currentTask: {
			id: null,
			title: '',
			startDate: '',
			startTime: '10:00',
			endDate: '',
			endTime: '11:00',
			category: '',
			secondaryCategories: [],
			description: ''
		},
		
		// New category
		newCategory: {
			name: '',
			color: '#3b82f6',
			textColor: '#ffffff'
		},

		// Editing category
		editingCategoryId: null,
		editingCategory: {
			name: '',
			color: '',
			textColor: ''
		},
		
		// Timer
		timerRunning: false,
		timerSeconds: 0,
		timerInterval: null,
		timerStartTime: null,
		currentTaskTimeLogs: [],
		editingLogId: null,
		
		// Initialize
		async init() {
			this.isLoading = true;
			
			// Initialize calendar FIRST with empty data
			this.$nextTick(() => {
				this.initializeCalendar();
			});
			
			// Load data in PARALLEL (not sequential)
			await Promise.all([
				this.loadCategories(),
				this.loadTasks()
			]);
			
			// Update calendar once after both are loaded
			this.updateCalendarEvents();
			
			this.isLoading = false;
			window.showNotification(ttCalendarData.i18n.calendarLoaded, 'success');
		},
		
		initializeCalendar() {
			const self = this;
			const calendarEl = document.getElementById('calendar');
			
			this.calendar = new FullCalendar.Calendar(calendarEl, {
				initialView: 'timeGridWeek',
				headerToolbar: {
					left: 'prev,next today',
					center: 'title',
					right: 'dayGridMonth,timeGridWeek,timeGridDay'
				},
				slotMinTime: ttCalendarData.startTime + ':00',
				slotMaxTime: ttCalendarData.endTime + ':00',
				slotDuration: '00:15:00',
				weekends: !ttCalendarData.hideWeekends,
				allDaySlot: false,
				nowIndicator: true,
				editable: true,
				selectable: true,
				selectMirror: true,
				dayMaxEvents: true,
				height: 'auto',
				
				// When user selects time range (drag to select)
				select: function(info) {
					const startDate = self.formatDateStr(info.start);
					const endDate = self.formatDateStr(info.end);
					const startTime = self.formatTimeStr(info.start);
					const endTime = self.formatTimeStr(info.end);
					
					self.currentTask = {
						id: null,
						title: '',
						startDate: startDate,
						startTime: startTime,
						endDate: endDate,
						endTime: endTime,
						category: '',
						secondaryCategories: [],
						description: ''
					};
					
					self.sidebarOpen = true;
					self.activeTab = 'task';
					self.currentTaskTimeLogs = [];
					self.resetTimer();
					
					// Focus on title input
					setTimeout(() => {
						const titleInput = document.querySelector('input[x-model="currentTask.title"]');
						if (titleInput) titleInput.focus();
					}, 100);
				},
				
				// When user clicks on an event
				eventClick: function(info) {
					self.editTaskFromEvent(info.event);
				},
				
				// When user drags an event
				eventDrop: function(info) {
					self.updateTaskFromEvent(info.event);
				},
				
				// When user resizes an event
				eventResize: function(info) {
					self.updateTaskFromEvent(info.event);
				},
				
				events: []
			});
			
			this.calendar.render();
		},
		
		formatDateStr(date) {
			const year = date.getFullYear();
			const month = String(date.getMonth() + 1).padStart(2, '0');
			const day = String(date.getDate()).padStart(2, '0');
			return `${year}-${month}-${day}`;
		},
		
		formatTimeStr(date) {
			const hours = String(date.getHours()).padStart(2, '0');
			const minutes = String(date.getMinutes()).padStart(2, '0');
			return `${hours}:${minutes}`;
		},
		
		updateCalendarEvents() {
			if (!this.calendar) return;

			const self = this;
			const events = this.tasks.map(task => {
				const categoryColors = this.getCategoryColors(task.category);

				return {
					id: task.id,
					title: task.title,
					start: `${task.startDate}T${task.startTime}`,
					end: `${task.endDate}T${task.endTime}`,
					backgroundColor: categoryColors.background,
					borderColor: categoryColors.background,
					textColor: categoryColors.text,
					extendedProps: {
						taskData: task
					}
				};
			});

			// More efficient: use removeAllEvents() then addEventSource()
			this.calendar.removeAllEvents();
			this.calendar.addEventSource(events);

			// Add secondary color circles after calendar renders
			setTimeout(() => {
				self.renderSecondaryColorCircles();
			}, 100);
		},

		renderSecondaryColorCircles() {
			// Remove existing secondary color circles
			document.querySelectorAll('.tt-secondary-colors').forEach(el => el.remove());

			// Add secondary color circles to each event
			this.tasks.forEach(task => {
				if (!task.secondaryCategories || task.secondaryCategories.length === 0) {
					return;
				}

				// Find the event element
				const eventEl = document.querySelector(`.fc-event[data-event-id="${task.id}"]`);
				if (!eventEl) return;

				// Find the event main content area
				const eventMain = eventEl.querySelector('.fc-event-main');
				if (!eventMain) return;

				// Create container for secondary color circles
				const circlesContainer = document.createElement('div');
				circlesContainer.className = 'tt-secondary-colors';

				// Add circles for each secondary category
				task.secondaryCategories.forEach(categoryId => {
					const category = this.categories.find(c => c.id == categoryId);
					if (category) {
						const circle = document.createElement('div');
						circle.className = 'tt-secondary-circle';
						circle.style.backgroundColor = category.color;
						circle.title = category.name;
						circlesContainer.appendChild(circle);
					}
				});

				eventMain.appendChild(circlesContainer);
			});
		},

		getCategoryColors(categoryId) {
			const category = this.categories.find(c => c.id == categoryId);
			return category ? {
				background: category.color,
				text: category.textColor
			} : {
				background: '#3b82f6',
				text: '#ffffff'
			};
		},
		
		editTaskFromEvent(event) {
			const taskData = event.extendedProps.taskData;
			this.currentTask = { ...taskData };
			this.sidebarOpen = true;
			this.activeTab = 'task';
			this.loadTimeLogsForTask(taskData.id);
			this.resetTimer();
		},
		
		async updateTaskFromEvent(event) {
			const start = event.start;
			const end = event.end;
			
			this.currentTask = {
				...event.extendedProps.taskData,
				startDate: this.formatDateStr(start),
				startTime: this.formatTimeStr(start),
				endDate: this.formatDateStr(end),
				endTime: this.formatTimeStr(end)
			};
			
			await this.saveTask(true);
		},
		
		// Tasks
		async loadTasks() {
			try {
				const formData = new FormData();
				formData.append('action', 'tt_get_tasks');
				formData.append('nonce', ttCalendarData.nonce);
				
				const response = await fetch(ttCalendarData.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				if (data.success) {
					this.tasks = data.data;
				} else {
					window.showNotification(ttCalendarData.i18n.errorLoadingTasks, 'error');
				}
			} catch (error) {
				window.showNotification(ttCalendarData.i18n.errorLoadingTasks + ': ' + error.message, 'error');
			}
		},
		
		async saveTask(silent = false) {
			try {
				const formData = new FormData();
				formData.append('action', 'tt_save_task');
				formData.append('nonce', ttCalendarData.nonce);
				formData.append('task_data', JSON.stringify(this.currentTask));
				
				const response = await fetch(ttCalendarData.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				if (data.success) {
					// Update the current task ID if it was a new task
					if (!this.currentTask.id && data.data && data.data.task_id) {
						this.currentTask.id = data.data.task_id;
					}
					
					await this.loadTasks();
					this.updateCalendarEvents();
					
					if (!silent) {
						window.showNotification(ttCalendarData.i18n.taskSaved, 'success');
					}
				} else {
					window.showNotification(ttCalendarData.i18n.errorSavingTask + ': ' + (data.data || ''), 'error');
				}
			} catch (error) {
				window.showNotification(ttCalendarData.i18n.errorSavingTask + ': ' + error.message, 'error');
			}
		},
		
		async deleteTask() {
			if (!this.currentTask.id) return;
			
			if (!confirm(ttCalendarData.i18n.confirmDeleteTask)) return;
			
			try {
				const formData = new FormData();
				formData.append('action', 'tt_delete_task');
				formData.append('nonce', ttCalendarData.nonce);
				formData.append('task_id', this.currentTask.id);
				
				const response = await fetch(ttCalendarData.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				if (data.success) {
					await this.loadTasks();
					this.updateCalendarEvents();
					this.resetCurrentTask();
					window.showNotification(ttCalendarData.i18n.taskDeleted, 'success');
				} else {
					window.showNotification(ttCalendarData.i18n.errorDeletingTask, 'error');
				}
			} catch (error) {
				window.showNotification(ttCalendarData.i18n.errorDeletingTask + ': ' + error.message, 'error');
			}
		},
		
		resetCurrentTask() {
			this.currentTask = {
				id: null,
				title: '',
				startDate: '',
				startTime: '10:00',
				endDate: '',
				endTime: '11:00',
				category: '',
				secondaryCategories: [],
				description: ''
			};
			this.currentTaskTimeLogs = [];
			this.resetTimer();
		},
		
		// Categories
		async loadCategories() {
			try {
				const formData = new FormData();
				formData.append('action', 'tt_get_categories');
				formData.append('nonce', ttCalendarData.nonce);
				
				const response = await fetch(ttCalendarData.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				if (data.success) {
					this.categories = data.data;
				} else {
					window.showNotification(ttCalendarData.i18n.errorLoadingCategories, 'error');
				}
			} catch (error) {
				window.showNotification(ttCalendarData.i18n.errorLoadingCategories + ': ' + error.message, 'error');
			}
		},
		
		async saveCategory() {
			try {
				const formData = new FormData();
				formData.append('action', 'tt_save_category');
				formData.append('nonce', ttCalendarData.nonce);
				formData.append('category_data', JSON.stringify(this.newCategory));
				
				const response = await fetch(ttCalendarData.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				if (data.success) {
					await this.loadCategories();
					this.newCategory = { name: '', color: '#3b82f6', textColor: '#ffffff' };
					window.showNotification(ttCalendarData.i18n.categorySaved, 'success');
				} else {
					window.showNotification(ttCalendarData.i18n.errorSavingCategory, 'error');
				}
			} catch (error) {
				window.showNotification(ttCalendarData.i18n.errorSavingCategory + ': ' + error.message, 'error');
			}
		},
		
		async deleteCategory(categoryId) {
			if (!confirm(ttCalendarData.i18n.confirmDeleteCategory)) return;

			try {
				const formData = new FormData();
				formData.append('action', 'tt_delete_category');
				formData.append('nonce', ttCalendarData.nonce);
				formData.append('category_id', categoryId);

				const response = await fetch(ttCalendarData.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const data = await response.json();
				if (data.success) {
					await this.loadCategories();
					window.showNotification(ttCalendarData.i18n.categoryDeleted, 'success');
				} else {
					window.showNotification(ttCalendarData.i18n.errorDeletingCategory, 'error');
				}
			} catch (error) {
				window.showNotification(ttCalendarData.i18n.errorDeletingCategory + ': ' + error.message, 'error');
			}
		},

		startEditCategory(category) {
			this.editingCategoryId = category.id;
			this.editingCategory = {
				name: category.name,
				color: category.color,
				textColor: category.textColor
			};
		},

		cancelEditCategory() {
			this.editingCategoryId = null;
			this.editingCategory = {
				name: '',
				color: '',
				textColor: ''
			};
		},

		async updateCategory(categoryId) {
			try {
				const formData = new FormData();
				formData.append('action', 'tt_update_category');
				formData.append('nonce', ttCalendarData.nonce);
				formData.append('category_data', JSON.stringify({
					id: categoryId,
					name: this.editingCategory.name,
					color: this.editingCategory.color,
					textColor: this.editingCategory.textColor
				}));

				const response = await fetch(ttCalendarData.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const data = await response.json();
				if (data.success) {
					await this.loadCategories();
					this.updateCalendarEvents();
					this.cancelEditCategory();
					window.showNotification(ttCalendarData.i18n.categoryUpdated || 'Category updated successfully!', 'success');
				} else {
					window.showNotification(ttCalendarData.i18n.errorUpdatingCategory || 'Error updating category', 'error');
				}
			} catch (error) {
				window.showNotification((ttCalendarData.i18n.errorUpdatingCategory || 'Error updating category') + ': ' + error.message, 'error');
			}
		},
		
		// Timer functions
		async startTimer() {
			// Check if task is saved first
			if (!this.currentTask.id) {
				window.showNotification(ttCalendarData.i18n.saveTaskFirst, 'warning');
				return;
			}
			
			this.timerRunning = true;
			this.timerStartTime = Date.now() - (this.timerSeconds * 1000);
			
			this.timerInterval = setInterval(() => {
				this.timerSeconds = Math.floor((Date.now() - this.timerStartTime) / 1000);
			}, 100);
			
			window.showNotification(ttCalendarData.i18n.timerStarted, 'info');
		},
		
		async stopTimer() {
			this.timerRunning = false;
			clearInterval(this.timerInterval);
			
			if (this.timerSeconds > 0 && this.currentTask.id) {
				await this.saveTimeLog(this.timerSeconds);
				window.showNotification(ttCalendarData.i18n.timeLogSaved + ': ' + this.formatTime(this.timerSeconds), 'success');
			} else if (!this.currentTask.id) {
				window.showNotification(ttCalendarData.i18n.cannotSaveTimeLog, 'warning');
			}
		},
		
		resetTimer() {
			this.timerRunning = false;
			this.timerSeconds = 0;
			clearInterval(this.timerInterval);
		},
		
		formatTime(seconds) {
			const hrs = Math.floor(seconds / 3600);
			const mins = Math.floor((seconds % 3600) / 60);
			const secs = seconds % 60;
			return `${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
		},
		
		async saveTimeLog(duration) {
			if (!this.currentTask.id) {
				window.showNotification(ttCalendarData.i18n.saveTaskFirst, 'warning');
				return;
			}
			
			try {
				const formData = new FormData();
				formData.append('action', 'tt_save_time_log');
				formData.append('nonce', ttCalendarData.nonce);
				formData.append('task_id', this.currentTask.id);
				formData.append('duration', duration);
				formData.append('note', '');
				
				const response = await fetch(ttCalendarData.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				if (data.success) {
					// Reload time logs to show the new one
					await this.loadTimeLogsForTask(this.currentTask.id);
					this.resetTimer();
				} else {
					window.showNotification(ttCalendarData.i18n.errorSavingTimeLog + ': ' + (data.data || ''), 'error');
				}
			} catch (error) {
				window.showNotification(ttCalendarData.i18n.errorSavingTimeLog + ': ' + error.message, 'error');
			}
		},
		
		async loadTimeLogsForTask(taskId) {
			if (!taskId) return;
			
			try {
				const formData = new FormData();
				formData.append('action', 'tt_get_time_logs');
				formData.append('nonce', ttCalendarData.nonce);
				formData.append('task_id', taskId);
				
				const response = await fetch(ttCalendarData.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				if (data.success) {
					this.currentTaskTimeLogs = Array.isArray(data.data) ? data.data : [];
				} else {
					this.currentTaskTimeLogs = [];
					window.showNotification(ttCalendarData.i18n.errorLoadingTimeLogs, 'error');
				}
			} catch (error) {
				this.currentTaskTimeLogs = [];
				window.showNotification(ttCalendarData.i18n.errorLoadingTimeLogs + ': ' + error.message, 'error');
			}
		},
		
		async updateTimeLogNote(logId, newNote) {
			if (!this.currentTask.id) return;
			
			// Don't update if note hasn't changed
			const currentLog = this.currentTaskTimeLogs.find(log => log.id === logId);
			if (currentLog && currentLog.note === newNote) {
				return;
			}
			
			try {
				const formData = new FormData();
				formData.append('action', 'tt_update_time_log_note');
				formData.append('nonce', ttCalendarData.nonce);
				formData.append('task_id', this.currentTask.id);
				formData.append('log_id', logId);
				formData.append('note', newNote);
				
				const response = await fetch(ttCalendarData.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				if (data.success) {
					await this.loadTimeLogsForTask(this.currentTask.id);
					window.showNotification(ttCalendarData.i18n.noteUpdated, 'success');
				} else {
					window.showNotification(ttCalendarData.i18n.errorUpdatingNote, 'error');
				}
			} catch (error) {
				window.showNotification(ttCalendarData.i18n.errorUpdatingNote + ': ' + error.message, 'error');
			}
		},
		
		async deleteTimeLog(logId) {
			if (!this.currentTask.id) return;
			
			if (!confirm(ttCalendarData.i18n.confirmDeleteTimeLog)) return;
			
			try {
				const formData = new FormData();
				formData.append('action', 'tt_delete_time_log');
				formData.append('nonce', ttCalendarData.nonce);
				formData.append('task_id', this.currentTask.id);
				formData.append('log_id', logId);
				
				const response = await fetch(ttCalendarData.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				if (data.success) {
					await this.loadTimeLogsForTask(this.currentTask.id);
					window.showNotification(ttCalendarData.i18n.timeLogDeleted, 'success');
				} else {
					window.showNotification(ttCalendarData.i18n.errorDeletingTimeLog, 'error');
				}
			} catch (error) {
				window.showNotification(ttCalendarData.i18n.errorDeletingTimeLog + ': ' + error.message, 'error');
			}
		},
		
		// Sidebar
		toggleSidebar() {
			this.sidebarOpen = !this.sidebarOpen;
		},
		
		closeSidebar() {
			this.sidebarOpen = false;
		}
	};
}
