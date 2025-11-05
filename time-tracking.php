<?php
/**
 * Plugin Name: Time Tracking
 * Plugin URI: https://sinanisler.com
 * Description: Advanced time tracking plugin with drag-to-select calendar, category management, and detailed time logging
 * Version: 3.0.0
 * Author: Sinan Isler
 * Author URI: https://sinanisler.com
 * License: GPL v2 or later
 * Text Domain: time-tracking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TIME_TRACKING_VERSION', '3.0.0');
define('TIME_TRACKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TIME_TRACKING_PLUGIN_URL', plugin_dir_url(__FILE__));

class TimeTrackingPlugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_tt_save_task', array($this, 'ajax_save_task'));
        add_action('wp_ajax_tt_delete_task', array($this, 'ajax_delete_task'));
        add_action('wp_ajax_tt_get_tasks', array($this, 'ajax_get_tasks'));
        add_action('wp_ajax_tt_save_category', array($this, 'ajax_save_category'));
        add_action('wp_ajax_tt_delete_category', array($this, 'ajax_delete_category'));
        add_action('wp_ajax_tt_get_categories', array($this, 'ajax_get_categories'));
        add_action('wp_ajax_tt_save_time_log', array($this, 'ajax_save_time_log'));
        add_action('wp_ajax_tt_get_time_logs', array($this, 'ajax_get_time_logs'));
    }
    
    public function register_post_type() {
        register_post_type('tt_task', array(
            'labels' => array(
                'name' => __('Time Tasks', 'time-tracking'),
                'singular_name' => __('Time Task', 'time-tracking'),
            ),
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'capability_type' => 'post',
            'supports' => array('title'),
            'has_archive' => false,
        ));
    }
    
    public function register_taxonomy() {
        register_taxonomy('tt_category', 'tt_task', array(
            'labels' => array(
                'name' => __('Task Categories', 'time-tracking'),
                'singular_name' => __('Task Category', 'time-tracking'),
            ),
            'public' => false,
            'show_ui' => false,
            'hierarchical' => true,
        ));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Time Tracking', 'time-tracking'),
            __('Time Tracking', 'time-tracking'),
            'manage_options',
            'time-tracking',
            array($this, 'render_main_page'),
            'dashicons-clock',
            30
        );
    }
    
    public function render_main_page() {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Time Tracking</title>
            
            <!-- Tailwind CSS -->
            <script src="https://cdn.tailwindcss.com"></script>
            
            <!-- FullCalendar CSS & JS -->
            <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
            <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
            
            <!-- Alpine.js for reactivity -->
            <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
            
            <!-- Font Awesome -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    overflow-x: hidden;
                }
                .fc .fc-timegrid-slot {
                    height: 30px !important;
                }
                
                .sidebar {
                    height: 100vh;
                    overflow-y: auto;
                    position: sticky;
                    top: 0;
                }
                
                .time-log-entry {
                    border-left: 3px solid #3b82f6;
                    padding: 8px;
                    margin: 4px 0;
                    background-color: #f9fafb;
                    border-radius: 4px;
                }
                
                .timer-display {
                    font-size: 32px;
                    font-weight: bold;
                    font-family: monospace;
                    text-align: center;
                    padding: 16px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border-radius: 8px;
                    margin: 16px 0;
                }
                
                #calendar {
                    height: calc(100vh - 180px);
                }
                
                /* FullCalendar custom styling */
                .fc {
                    font-family: inherit;
                }
                
                .fc-toolbar-title {
                    font-size: 1.5rem !important;
                    font-weight: 600 !important;
                }
                
                .fc-button {
                    background-color: #8b5cf6 !important;
                    border-color: #8b5cf6 !important;
                    text-transform: capitalize !important;
                    padding: 0.5rem 1rem !important;
                }
                
                .fc-button:hover {
                    background-color: #7c3aed !important;
                }
                
                .fc-button-active {
                    background-color: #6d28d9 !important;
                }
                
                .fc-event {
                    cursor: pointer;
                    border-radius: 4px;
                }
                
                .fc-daygrid-day-number {
                    padding: 8px;
                }
                
                .fc-col-header-cell {
                    background-color: #f9fafb;
                    font-weight: 600;
                }
            </style>
        </head>
        <body class="bg-gray-50">
            
        <div x-data="timeTrackingApp()" x-init="init()" class="flex h-screen">
            
            <!-- Main Calendar Area -->
            <div class="flex-1 overflow-auto p-6">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    
                    <!-- Header -->
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">Time Tracking</h1>
                            <p class="text-gray-600">Workweek Schedule (Monday - Friday, 9 AM - 6 PM)</p>
                        </div>
                        
                        <div class="flex gap-2">
                            <button @click="toggleSidebar()" class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg font-semibold">
                                <i class="fas fa-cog"></i> Task Settings
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
                        <h2 class="text-2xl font-bold text-gray-800" x-text="activeTab === 'task' ? 'Task Details' : 'Categories'"></h2>
                        <button @click="closeSidebar()" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    
                    <!-- Tabs -->
                    <div class="flex gap-2 mb-6 border-b">
                        <button 
                            @click="activeTab = 'task'" 
                            :class="activeTab === 'task' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-600'"
                            class="px-4 py-2 font-semibold"
                        >
                            <i class="fas fa-tasks"></i> Task
                        </button>
                        <button 
                            @click="activeTab = 'categories'" 
                            :class="activeTab === 'categories' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-600'"
                            class="px-4 py-2 font-semibold"
                        >
                            <i class="fas fa-folder"></i> Categories
                        </button>
                    </div>
                    
                    <!-- Task Tab -->
                    <div x-show="activeTab === 'task'">
                        <form @submit.prevent="saveTask()">
                            
                            <!-- Task Name -->
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Task Name</label>
                                <input 
                                    type="text" 
                                    x-model="currentTask.title"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Enter task name"
                                    required
                                >
                            </div>
                            
                            <!-- Start Date & Time -->
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                                    <input 
                                        type="date" 
                                        x-model="currentTask.startDate"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                        required
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Time</label>
                                    <input 
                                        type="time" 
                                        x-model="currentTask.startTime"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                        required
                                    >
                                </div>
                            </div>
                            
                            <!-- End Date & Time -->
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                                    <input 
                                        type="date" 
                                        x-model="currentTask.endDate"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                        required
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Time</label>
                                    <input 
                                        type="time" 
                                        x-model="currentTask.endTime"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                        required
                                    >
                                </div>
                            </div>
                            
                            <!-- Category -->
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                                <select 
                                    x-model="currentTask.category"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">Select Category</option>
                                    <template x-for="category in categories" :key="category.id">
                                        <option :value="category.id" x-text="category.name"></option>
                                    </template>
                                </select>
                            </div>
                            
                            <!-- Description -->
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                                <textarea 
                                    x-model="currentTask.description"
                                    rows="4"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    placeholder="Add task description..."
                                ></textarea>
                            </div>
                            
                            <!-- Time Tracking Section -->
                            <div class="mb-6 p-4 bg-gradient-to-r from-purple-50 to-blue-50 rounded-lg">
                                <h3 class="text-lg font-semibold text-gray-800 mb-3">
                                    <i class="fas fa-stopwatch"></i> Time Tracking
                                </h3>
                                
                                <!-- Timer Display -->
                                <div class="timer-display" x-text="formatTime(timerSeconds)"></div>
                                
                                <!-- Timer Controls -->
                                <div class="flex gap-2 mb-4">
                                    <button 
                                        type="button"
                                        @click="startTimer()"
                                        x-show="!timerRunning"
                                        class="flex-1 px-4 py-3 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold"
                                    >
                                        <i class="fas fa-play"></i> Start
                                    </button>
                                    <button 
                                        type="button"
                                        @click="stopTimer()"
                                        x-show="timerRunning"
                                        class="flex-1 px-4 py-3 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold"
                                    >
                                        <i class="fas fa-stop"></i> Stop
                                    </button>
                                    <button 
                                        type="button"
                                        @click="resetTimer()"
                                        class="px-4 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg"
                                    >
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </div>
                                
                                <!-- Time Logs -->
                                <div class="mt-4">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Time Logs</h4>
                                    <div class="max-h-48 overflow-y-auto space-y-2">
                                        <template x-for="log in currentTaskTimeLogs" :key="log.id">
                                            <div class="time-log-entry">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-sm font-semibold" x-text="formatTime(log.duration)"></span>
                                                    <span class="text-xs text-gray-500" x-text="new Date(log.timestamp).toLocaleString()"></span>
                                                </div>
                                                <p class="text-xs text-gray-600 mt-1" x-text="log.note || 'No note'"></p>
                                            </div>
                                        </template>
                                        <div x-show="currentTaskTimeLogs.length === 0" class="text-sm text-gray-500 text-center py-4">
                                            No time logs yet
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
                                    <i class="fas fa-save"></i> Save Task
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
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Add New Category</h3>
                            <form @submit.prevent="saveCategory()">
                                <div class="mb-3">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category Name</label>
                                    <input 
                                        type="text" 
                                        x-model="newCategory.name"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                        placeholder="e.g., Development, Meeting"
                                        required
                                    >
                                </div>
                                <div class="mb-3">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Color</label>
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
                                <button 
                                    type="submit"
                                    class="w-full px-6 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold"
                                >
                                    <i class="fas fa-plus"></i> Add Category
                                </button>
                            </form>
                        </div>
                        
                        <!-- Categories List -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Existing Categories</h3>
                            <div class="space-y-2">
                                <template x-for="category in categories" :key="category.id">
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100">
                                        <div class="flex items-center gap-3">
                                            <div 
                                                class="w-8 h-8 rounded"
                                                :style="`background-color: ${category.color}`"
                                            ></div>
                                            <span class="font-semibold" x-text="category.name"></span>
                                        </div>
                                        <button 
                                            @click="deleteCategory(category.id)"
                                            class="text-red-500 hover:text-red-700"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        
                    </div>
                    
                </div>
            </div>
            
        </div>
        
        <script>
        function timeTrackingApp() {
            return {
                // State
                calendar: null,
                tasks: [],
                categories: [],
                sidebarOpen: true,
                activeTab: 'task',
                
                // Current task
                currentTask: {
                    id: null,
                    title: '',
                    startDate: '',
                    startTime: '10:00',
                    endDate: '',
                    endTime: '11:00',
                    category: '',
                    description: ''
                },
                
                // New category
                newCategory: {
                    name: '',
                    color: '#3b82f6'
                },
                
                // Timer
                timerRunning: false,
                timerSeconds: 0,
                timerInterval: null,
                timerStartTime: null,
                currentTaskTimeLogs: [],
                
                // Initialize
                async init() {
                    await this.loadCategories();
                    await this.loadTasks();
                    this.$nextTick(() => {
                        this.initializeCalendar();
                    });
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
                        slotMinTime: '09:00:00',
                        slotMaxTime: '18:00:00',
                        slotDuration: '00:30:00',
                        weekends: false, // Hide weekends
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
                    this.updateCalendarEvents();
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
                    
                    const events = this.tasks.map(task => {
                        const categoryColor = this.getCategoryColor(task.category);
                        
                        return {
                            id: task.id,
                            title: task.title,
                            start: `${task.startDate}T${task.startTime}`,
                            end: `${task.endDate}T${task.endTime}`,
                            backgroundColor: categoryColor,
                            borderColor: categoryColor,
                            extendedProps: {
                                taskData: task
                            }
                        };
                    });
                    
                    // Remove all existing events
                    this.calendar.getEvents().forEach(event => event.remove());
                    
                    // Add new events
                    events.forEach(event => this.calendar.addEvent(event));
                },
                
                getCategoryColor(categoryId) {
                    const category = this.categories.find(c => c.id == categoryId);
                    return category ? category.color : '#3b82f6';
                },
                
                editTaskFromEvent(event) {
                    const taskData = event.extendedProps.taskData;
                    this.currentTask = { ...taskData };
                    this.sidebarOpen = true;
                    this.activeTab = 'task';
                    this.loadTimeLogsForTask(taskData.id);
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
                        formData.append('nonce', '<?php echo wp_create_nonce("tt_nonce"); ?>');
                        
                        const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            this.tasks = data.data;
                            this.updateCalendarEvents();
                        }
                    } catch (error) {
                        console.error('Error loading tasks:', error);
                    }
                },
                
                async saveTask(silent = false) {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'tt_save_task');
                        formData.append('nonce', '<?php echo wp_create_nonce("tt_nonce"); ?>');
                        formData.append('task_data', JSON.stringify(this.currentTask));
                        
                        const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            await this.loadTasks();
                            if (!this.currentTask.id) {
                                this.resetCurrentTask();
                            }
                            if (!silent) {
                                alert('Task saved successfully!');
                            }
                        } else {
                            alert('Error saving task: ' + (data.data || 'Unknown error'));
                        }
                    } catch (error) {
                        console.error('Error saving task:', error);
                        alert('Error saving task');
                    }
                },
                
                async deleteTask() {
                    if (!this.currentTask.id) return;
                    
                    if (!confirm('Are you sure you want to delete this task?')) return;
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'tt_delete_task');
                        formData.append('nonce', '<?php echo wp_create_nonce("tt_nonce"); ?>');
                        formData.append('task_id', this.currentTask.id);
                        
                        const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            await this.loadTasks();
                            this.resetCurrentTask();
                            alert('Task deleted successfully!');
                        }
                    } catch (error) {
                        console.error('Error deleting task:', error);
                        alert('Error deleting task');
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
                        formData.append('nonce', '<?php echo wp_create_nonce("tt_nonce"); ?>');
                        
                        const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            this.categories = data.data;
                            this.updateCalendarEvents();
                        }
                    } catch (error) {
                        console.error('Error loading categories:', error);
                    }
                },
                
                async saveCategory() {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'tt_save_category');
                        formData.append('nonce', '<?php echo wp_create_nonce("tt_nonce"); ?>');
                        formData.append('category_data', JSON.stringify(this.newCategory));
                        
                        const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            await this.loadCategories();
                            this.newCategory = { name: '', color: '#3b82f6' };
                            alert('Category saved successfully!');
                        }
                    } catch (error) {
                        console.error('Error saving category:', error);
                        alert('Error saving category');
                    }
                },
                
                async deleteCategory(categoryId) {
                    if (!confirm('Are you sure you want to delete this category?')) return;
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'tt_delete_category');
                        formData.append('nonce', '<?php echo wp_create_nonce("tt_nonce"); ?>');
                        formData.append('category_id', categoryId);
                        
                        const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            await this.loadCategories();
                            alert('Category deleted successfully!');
                        }
                    } catch (error) {
                        console.error('Error deleting category:', error);
                        alert('Error deleting category');
                    }
                },
                
                // Timer functions
                startTimer() {
                    this.timerRunning = true;
                    this.timerStartTime = Date.now() - (this.timerSeconds * 1000);
                    
                    this.timerInterval = setInterval(() => {
                        this.timerSeconds = Math.floor((Date.now() - this.timerStartTime) / 1000);
                    }, 100);
                },
                
                async stopTimer() {
                    this.timerRunning = false;
                    clearInterval(this.timerInterval);
                    
                    if (this.timerSeconds > 0 && this.currentTask.id) {
                        await this.saveTimeLog(this.timerSeconds);
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
                        alert('Please save the task first before logging time.');
                        return;
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'tt_save_time_log');
                        formData.append('nonce', '<?php echo wp_create_nonce("tt_nonce"); ?>');
                        formData.append('task_id', this.currentTask.id);
                        formData.append('duration', duration);
                        formData.append('note', '');
                        
                        const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            await this.loadTimeLogsForTask(this.currentTask.id);
                            this.resetTimer();
                        }
                    } catch (error) {
                        console.error('Error saving time log:', error);
                    }
                },
                
                async loadTimeLogsForTask(taskId) {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'tt_get_time_logs');
                        formData.append('nonce', '<?php echo wp_create_nonce("tt_nonce"); ?>');
                        formData.append('task_id', taskId);
                        
                        const response = await fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            this.currentTaskTimeLogs = data.data;
                        }
                    } catch (error) {
                        console.error('Error loading time logs:', error);
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
        </script>
        
        </body>
        </html>
        <?php
    }
    
    // AJAX Handlers
    
    public function ajax_save_task() {
        check_ajax_referer('tt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $task_data = json_decode(stripslashes($_POST['task_data']), true);
        
        $post_data = array(
            'post_title' => sanitize_text_field($task_data['title']),
            'post_type' => 'tt_task',
            'post_status' => 'publish'
        );
        
        if (!empty($task_data['id'])) {
            $post_data['ID'] = intval($task_data['id']);
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }
        
        if (is_wp_error($post_id)) {
            wp_send_json_error($post_id->get_error_message());
        }
        
        update_post_meta($post_id, '_tt_start_date', sanitize_text_field($task_data['startDate']));
        update_post_meta($post_id, '_tt_start_time', sanitize_text_field($task_data['startTime']));
        update_post_meta($post_id, '_tt_end_date', sanitize_text_field($task_data['endDate']));
        update_post_meta($post_id, '_tt_end_time', sanitize_text_field($task_data['endTime']));
        update_post_meta($post_id, '_tt_description', sanitize_textarea_field($task_data['description']));
        
        if (!empty($task_data['category'])) {
            wp_set_object_terms($post_id, intval($task_data['category']), 'tt_category');
        }
        
        wp_send_json_success(array('task_id' => $post_id));
    }
    
    public function ajax_delete_task() {
        check_ajax_referer('tt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $task_id = intval($_POST['task_id']);
        $result = wp_delete_post($task_id, true);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to delete task');
        }
    }
    
    public function ajax_get_tasks() {
        check_ajax_referer('tt_nonce', 'nonce');
        
        $args = array(
            'post_type' => 'tt_task',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        );
        
        $query = new WP_Query($args);
        $tasks = array();
        
        foreach ($query->posts as $post) {
            $category_terms = wp_get_object_terms($post->ID, 'tt_category');
            
            $tasks[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'startDate' => get_post_meta($post->ID, '_tt_start_date', true),
                'startTime' => get_post_meta($post->ID, '_tt_start_time', true),
                'endDate' => get_post_meta($post->ID, '_tt_end_date', true),
                'endTime' => get_post_meta($post->ID, '_tt_end_time', true),
                'description' => get_post_meta($post->ID, '_tt_description', true),
                'category' => !empty($category_terms) ? $category_terms[0]->term_id : ''
            );
        }
        
        wp_send_json_success($tasks);
    }
    
    public function ajax_save_category() {
        check_ajax_referer('tt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $category_data = json_decode(stripslashes($_POST['category_data']), true);
        
        $term = wp_insert_term(
            sanitize_text_field($category_data['name']),
            'tt_category'
        );
        
        if (is_wp_error($term)) {
            wp_send_json_error($term->get_error_message());
        }
        
        update_term_meta($term['term_id'], '_tt_category_color', sanitize_hex_color($category_data['color']));
        
        wp_send_json_success(array('category_id' => $term['term_id']));
    }
    
    public function ajax_delete_category() {
        check_ajax_referer('tt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $category_id = intval($_POST['category_id']);
        $result = wp_delete_term($category_id, 'tt_category');
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
    
    public function ajax_get_categories() {
        check_ajax_referer('tt_nonce', 'nonce');
        
        $terms = get_terms(array(
            'taxonomy' => 'tt_category',
            'hide_empty' => false
        ));
        
        $categories = array();
        
        foreach ($terms as $term) {
            $categories[] = array(
                'id' => $term->term_id,
                'name' => $term->name,
                'color' => get_term_meta($term->term_id, '_tt_category_color', true) ?: '#3b82f6'
            );
        }
        
        wp_send_json_success($categories);
    }
    
    public function ajax_save_time_log() {
        check_ajax_referer('tt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $task_id = intval($_POST['task_id']);
        $duration = intval($_POST['duration']);
        $note = sanitize_textarea_field($_POST['note']);
        
        $logs = get_post_meta($task_id, '_tt_time_logs', true);
        if (!is_array($logs)) {
            $logs = array();
        }
        
        $logs[] = array(
            'id' => uniqid(),
            'duration' => $duration,
            'note' => $note,
            'timestamp' => current_time('mysql')
        );
        
        update_post_meta($task_id, '_tt_time_logs', $logs);
        
        wp_send_json_success();
    }
    
    public function ajax_get_time_logs() {
        check_ajax_referer('tt_nonce', 'nonce');
        
        $task_id = intval($_POST['task_id']);
        $logs = get_post_meta($task_id, '_tt_time_logs', true);
        
        if (!is_array($logs)) {
            $logs = array();
        }
        
        wp_send_json_success($logs);
    }
}

// Initialize the plugin
TimeTrackingPlugin::get_instance();