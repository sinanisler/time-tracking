import React, { useState, useEffect, useRef } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { Task, Category, TimeLog } from './types';

const App: React.FC = () => {
  const [tasks, setTasks] = useState<Task[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [sidebarOpen, setSidebarOpen] = useState(true);
  const [activeTab, setActiveTab] = useState<'task' | 'categories'>('task');
  const [isLoading, setIsLoading] = useState(true);
  const [notification, setNotification] = useState<{ message: string; type: string } | null>(null);

  const [currentTask, setCurrentTask] = useState<Task>({
    title: '',
    startDate: '',
    startTime: '10:00',
    endDate: '',
    endTime: '11:00',
    category: undefined,
    description: '',
  });

  const [newCategory, setNewCategory] = useState<Category>({
    name: '',
    color: '#3b82f6',
  });

  const [timerRunning, setTimerRunning] = useState(false);
  const [timerSeconds, setTimerSeconds] = useState(0);
  const [currentTaskTimeLogs, setCurrentTaskTimeLogs] = useState<TimeLog[]>([]);
  const [editingLogId, setEditingLogId] = useState<number | null>(null);
  
  const timerIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const timerStartTimeRef = useRef<number>(0);
  const calendarRef = useRef<FullCalendar>(null);

  // Show notification
  const showNotification = (message: string, type: string = 'info') => {
    setNotification({ message, type });
    setTimeout(() => setNotification(null), 3000);
  };

  // Load initial data
  useEffect(() => {
    const loadData = async () => {
      setIsLoading(true);
      try {
        const [tasksData, categoriesData] = await Promise.all([
          window.electronAPI.getTasks(),
          window.electronAPI.getCategories(),
        ]);
        setTasks(tasksData);
        setCategories(categoriesData);
        showNotification('Calendar loaded successfully', 'success');
      } catch (error) {
        showNotification('Error loading data', 'error');
        console.error(error);
      } finally {
        setIsLoading(false);
      }
    };
    loadData();
  }, []);

  // Timer effect
  useEffect(() => {
    if (timerRunning) {
      timerIntervalRef.current = setInterval(() => {
        setTimerSeconds(Math.floor((Date.now() - timerStartTimeRef.current) / 1000));
      }, 100);
    } else {
      if (timerIntervalRef.current) {
        clearInterval(timerIntervalRef.current);
      }
    }
    return () => {
      if (timerIntervalRef.current) {
        clearInterval(timerIntervalRef.current);
      }
    };
  }, [timerRunning]);

  const formatTime = (seconds: number): string => {
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    return `${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  };

  const formatDateStr = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  const formatTimeStr = (date: Date): string => {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${hours}:${minutes}`;
  };

  const getCategoryColor = (categoryId?: number): string => {
    if (!categoryId) return '#3b82f6';
    const category = categories.find((c) => c.id === categoryId);
    return category ? category.color : '#3b82f6';
  };

  const loadTimeLogs = async (taskId: number) => {
    try {
      const logs = await window.electronAPI.getTimeLogs(taskId);
      setCurrentTaskTimeLogs(logs);
    } catch (error) {
      showNotification('Error loading time logs', 'error');
      console.error(error);
    }
  };

  const saveTask = async (silent: boolean = false) => {
    try {
      const result = await window.electronAPI.saveTask(currentTask);
      
      if (!currentTask.id && result.id) {
        setCurrentTask({ ...currentTask, id: result.id });
      }

      const tasksData = await window.electronAPI.getTasks();
      setTasks(tasksData);

      if (!silent) {
        showNotification('Task saved successfully!', 'success');
      }
    } catch (error) {
      showNotification('Error saving task', 'error');
      console.error(error);
    }
  };

  const deleteTask = async () => {
    if (!currentTask.id) return;
    if (!window.confirm('Are you sure you want to delete this task?')) return;

    try {
      await window.electronAPI.deleteTask(currentTask.id);
      const tasksData = await window.electronAPI.getTasks();
      setTasks(tasksData);
      resetCurrentTask();
      showNotification('Task deleted successfully!', 'success');
    } catch (error) {
      showNotification('Error deleting task', 'error');
      console.error(error);
    }
  };

  const resetCurrentTask = () => {
    setCurrentTask({
      title: '',
      startDate: '',
      startTime: '10:00',
      endDate: '',
      endTime: '11:00',
      category: undefined,
      description: '',
    });
    setCurrentTaskTimeLogs([]);
    resetTimer();
  };

  const saveCategory = async () => {
    try {
      await window.electronAPI.saveCategory(newCategory);
      const categoriesData = await window.electronAPI.getCategories();
      setCategories(categoriesData);
      setNewCategory({ name: '', color: '#3b82f6' });
      showNotification('Category saved successfully!', 'success');
    } catch (error) {
      showNotification('Error saving category', 'error');
      console.error(error);
    }
  };

  const deleteCategory = async (categoryId: number) => {
    if (!window.confirm('Are you sure you want to delete this category?')) return;

    try {
      await window.electronAPI.deleteCategory(categoryId);
      const categoriesData = await window.electronAPI.getCategories();
      setCategories(categoriesData);
      showNotification('Category deleted successfully!', 'success');
    } catch (error) {
      showNotification('Error deleting category', 'error');
      console.error(error);
    }
  };

  const startTimer = async () => {
    if (!currentTask.id) {
      showNotification('Please save the task first before starting the timer', 'warning');
      return;
    }

    setTimerRunning(true);
    timerStartTimeRef.current = Date.now() - timerSeconds * 1000;
    showNotification('Timer started', 'info');
  };

  const stopTimer = async () => {
    setTimerRunning(false);

    if (timerSeconds > 0 && currentTask.id) {
      await saveTimeLog(timerSeconds);
      showNotification(`Time log saved: ${formatTime(timerSeconds)}`, 'success');
    } else if (!currentTask.id) {
      showNotification('Cannot save time log - task not saved', 'warning');
    }
  };

  const resetTimer = () => {
    setTimerRunning(false);
    setTimerSeconds(0);
    if (timerIntervalRef.current) {
      clearInterval(timerIntervalRef.current);
    }
  };

  const saveTimeLog = async (duration: number) => {
    if (!currentTask.id) {
      showNotification('Please save the task first before logging time', 'warning');
      return;
    }

    try {
      await window.electronAPI.saveTimeLog({
        taskId: currentTask.id,
        duration,
        note: '',
        timestamp: new Date().toISOString(),
      });
      await loadTimeLogs(currentTask.id);
      resetTimer();
    } catch (error) {
      showNotification('Error saving time log', 'error');
      console.error(error);
    }
  };

  const updateTimeLogNote = async (logId: number, note: string) => {
    if (!currentTask.id) return;

    try {
      await window.electronAPI.updateTimeLogNote(logId, note);
      await loadTimeLogs(currentTask.id);
      showNotification('Note updated', 'success');
    } catch (error) {
      showNotification('Error updating note', 'error');
      console.error(error);
    }
  };

  const deleteTimeLog = async (logId: number) => {
    if (!currentTask.id) return;
    if (!window.confirm('Delete this time log?')) return;

    try {
      await window.electronAPI.deleteTimeLog(logId);
      await loadTimeLogs(currentTask.id);
      showNotification('Time log deleted', 'success');
    } catch (error) {
      showNotification('Error deleting time log', 'error');
      console.error(error);
    }
  };

  const handleSelect = (info: any) => {
    setCurrentTask({
      id: undefined,
      title: '',
      startDate: formatDateStr(info.start),
      startTime: formatTimeStr(info.start),
      endDate: formatDateStr(info.end),
      endTime: formatTimeStr(info.end),
      category: undefined,
      description: '',
    });
    setSidebarOpen(true);
    setActiveTab('task');
    setCurrentTaskTimeLogs([]);
    resetTimer();
  };

  const handleEventClick = (info: any) => {
    const task = tasks.find((t) => t.id === parseInt(info.event.id));
    if (task) {
      setCurrentTask({ ...task });
      setSidebarOpen(true);
      setActiveTab('task');
      if (task.id) {
        loadTimeLogs(task.id);
      }
      resetTimer();
    }
  };

  const handleEventChange = async (info: any) => {
    const start = info.event.start;
    const end = info.event.end;
    const task = tasks.find((t) => t.id === parseInt(info.event.id));

    if (task) {
      const updatedTask = {
        ...task,
        startDate: formatDateStr(start),
        startTime: formatTimeStr(start),
        endDate: formatDateStr(end),
        endTime: formatTimeStr(end),
      };
      setCurrentTask(updatedTask);
      await window.electronAPI.saveTask(updatedTask);
      const tasksData = await window.electronAPI.getTasks();
      setTasks(tasksData);
    }
  };

  const calendarEvents = tasks.map((task) => ({
    id: String(task.id),
    title: task.title,
    start: `${task.startDate}T${task.startTime}`,
    end: `${task.endDate}T${task.endTime}`,
    backgroundColor: getCategoryColor(task.category),
    borderColor: getCategoryColor(task.category),
  }));

  return (
    <div className="flex h-screen bg-gray-50">
      {/* Notification */}
      {notification && (
        <div className="fixed top-4 left-4 z-50 max-w-sm">
          <div
            className={`p-4 rounded-lg shadow-lg ${
              notification.type === 'success'
                ? 'bg-green-500'
                : notification.type === 'error'
                ? 'bg-red-500'
                : notification.type === 'warning'
                ? 'bg-yellow-500'
                : 'bg-blue-500'
            } text-white`}
          >
            {notification.message}
          </div>
        </div>
      )}

      {/* Main Calendar Area */}
      <div className="flex-1 overflow-auto p-6">
        <div className="bg-white rounded-lg shadow-lg p-6 relative">
          {/* Loading Overlay */}
          {isLoading && (
            <div className="absolute inset-0 bg-white bg-opacity-90 flex items-center justify-center z-50 rounded-lg">
              <div className="text-center">
                <div className="text-5xl text-purple-500 mb-4">⏳</div>
                <p className="text-gray-600 text-lg font-semibold">Loading calendar...</p>
              </div>
            </div>
          )}

          {/* Header */}
          <div className="flex justify-between items-center mb-4">
            <div>
              <h1 className="text-3xl font-bold text-gray-800">Time Tracking</h1>
              <p className="text-gray-600">Workweek Schedule (Monday - Friday, 9 AM - 6 PM)</p>
            </div>

            <button
              onClick={() => setSidebarOpen(!sidebarOpen)}
              className="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg font-semibold"
            >
              ⚙️ Task Settings
            </button>
          </div>

          {/* Calendar */}
          <FullCalendar
            ref={calendarRef}
            plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
            initialView="timeGridWeek"
            headerToolbar={{
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,timeGridWeek,timeGridDay',
            }}
            slotMinTime="09:00:00"
            slotMaxTime="18:00:00"
            slotDuration="00:15:00"
            weekends={false}
            allDaySlot={false}
            nowIndicator={true}
            editable={true}
            selectable={true}
            selectMirror={true}
            dayMaxEvents={true}
            height="auto"
            events={calendarEvents}
            select={handleSelect}
            eventClick={handleEventClick}
            eventDrop={handleEventChange}
            eventResize={handleEventChange}
          />
        </div>
      </div>

      {/* Sidebar */}
      {sidebarOpen && (
        <div className="w-96 bg-white shadow-2xl border-l border-gray-200 overflow-y-auto">
          <div className="p-6">
            {/* Close Button */}
            <div className="flex justify-between items-center mb-6">
              <h2 className="text-2xl font-bold text-gray-800">
                {activeTab === 'task' ? 'Task Details' : 'Categories'}
              </h2>
              <button onClick={() => setSidebarOpen(false)} className="text-gray-500 hover:text-gray-700">
                ✕
              </button>
            </div>

            {/* Tabs */}
            <div className="flex gap-2 border-b mb-4">
              <button
                onClick={() => setActiveTab('task')}
                className={`px-4 py-2 font-semibold ${
                  activeTab === 'task' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-600'
                }`}
              >
                📋 Task
              </button>
              <button
                onClick={() => setActiveTab('categories')}
                className={`px-4 py-2 font-semibold ${
                  activeTab === 'categories' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-600'
                }`}
              >
                📁 Categories
              </button>
            </div>

            {/* Task Tab */}
            {activeTab === 'task' && (
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  saveTask();
                }}
              >
                {/* Task Name */}
                <div className="mb-3">
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Task Name</label>
                  <input
                    type="text"
                    value={currentTask.title}
                    onChange={(e) => setCurrentTask({ ...currentTask, title: e.target.value })}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Enter task name"
                    required
                  />
                </div>

                {/* Start Date & Time */}
                <div className="grid grid-cols-2 gap-4 mb-3">
                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Start Date</label>
                    <input
                      type="date"
                      value={currentTask.startDate}
                      onChange={(e) => setCurrentTask({ ...currentTask, startDate: e.target.value })}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Start Time</label>
                    <input
                      type="time"
                      value={currentTask.startTime}
                      onChange={(e) => setCurrentTask({ ...currentTask, startTime: e.target.value })}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                      required
                    />
                  </div>
                </div>

                {/* End Date & Time */}
                <div className="grid grid-cols-2 gap-4 mb-3">
                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">End Date</label>
                    <input
                      type="date"
                      value={currentTask.endDate}
                      onChange={(e) => setCurrentTask({ ...currentTask, endDate: e.target.value })}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                      required
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">End Time</label>
                    <input
                      type="time"
                      value={currentTask.endTime}
                      onChange={(e) => setCurrentTask({ ...currentTask, endTime: e.target.value })}
                      className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                      required
                    />
                  </div>
                </div>

                {/* Category */}
                <div className="mb-3">
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Category</label>
                  <select
                    value={currentTask.category || ''}
                    onChange={(e) =>
                      setCurrentTask({ ...currentTask, category: e.target.value ? parseInt(e.target.value) : undefined })
                    }
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="">Select Category</option>
                    {categories.map((category) => (
                      <option key={category.id} value={category.id}>
                        {category.name}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Description */}
                <div className="mb-3">
                  <label className="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                  <textarea
                    value={currentTask.description || ''}
                    onChange={(e) => setCurrentTask({ ...currentTask, description: e.target.value })}
                    rows={2}
                    className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    placeholder="Add task description..."
                  />
                </div>

                {/* Time Tracking Section */}
                <div className="mb-4 p-3 bg-gradient-to-r from-purple-50 to-blue-50 rounded-lg">
                  <h3 className="text-base font-semibold text-gray-800 mb-2">⏱️ Time Tracking</h3>

                  {/* Timer Display */}
                  <div className="text-3xl font-bold font-mono text-center p-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-lg mb-3">
                    {formatTime(timerSeconds)}
                  </div>

                  {/* Timer Controls */}
                  <div className="flex gap-2 mb-3">
                    {!timerRunning ? (
                      <button
                        type="button"
                        onClick={startTimer}
                        className="flex-1 px-3 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold text-sm"
                      >
                        ▶️ Start
                      </button>
                    ) : (
                      <button
                        type="button"
                        onClick={stopTimer}
                        className="flex-1 px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold text-sm"
                      >
                        ⏹️ Stop
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={resetTimer}
                      className="px-3 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg text-sm"
                    >
                      🔄
                    </button>
                  </div>

                  {/* Time Logs */}
                  <div className="mt-3">
                    <h4 className="text-sm font-semibold text-gray-700 mb-2">Time Logs</h4>
                    <div className="max-h-[200px] overflow-y-auto space-y-1">
                      {currentTaskTimeLogs.length === 0 ? (
                        <div className="text-sm text-gray-500 text-center py-4">No time logs yet</div>
                      ) : (
                        currentTaskTimeLogs.map((log) => (
                          <div key={log.id} className="border-l-3 border-l-blue-500 p-2 bg-gray-50 rounded">
                            <div className="flex justify-between items-center">
                              <span className="text-sm font-semibold">{formatTime(log.duration)}</span>
                              <div className="flex items-center gap-2">
                                <span className="text-xs text-gray-500">
                                  {new Date(log.timestamp).toLocaleString()}
                                </span>
                                <button
                                  onClick={() => deleteTimeLog(log.id!)}
                                  className="text-red-500 hover:text-red-700"
                                  type="button"
                                >
                                  ✕
                                </button>
                              </div>
                            </div>
                            <div className="mt-1">
                              {editingLogId === log.id ? (
                                <input
                                  type="text"
                                  defaultValue={log.note}
                                  onBlur={(e) => {
                                    updateTimeLogNote(log.id!, e.target.value);
                                    setEditingLogId(null);
                                  }}
                                  onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                      updateTimeLogNote(log.id!, e.currentTarget.value);
                                      setEditingLogId(null);
                                    }
                                    if (e.key === 'Escape') {
                                      setEditingLogId(null);
                                    }
                                  }}
                                  autoFocus
                                  className="w-full text-xs px-2 py-1 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500"
                                  placeholder="Add note and press Enter..."
                                />
                              ) : (
                                <p
                                  onClick={() => setEditingLogId(log.id!)}
                                  className="text-xs text-gray-600 cursor-pointer hover:bg-gray-100 px-1 rounded"
                                >
                                  {log.note || 'Click to add note...'}
                                </p>
                              )}
                            </div>
                          </div>
                        ))
                      )}
                    </div>
                  </div>
                </div>

                {/* Action Buttons */}
                <div className="flex gap-2">
                  <button
                    type="submit"
                    className="flex-1 px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-semibold"
                  >
                    💾 Save Task
                  </button>
                  {currentTask.id && (
                    <button
                      type="button"
                      onClick={deleteTask}
                      className="px-6 py-3 bg-red-500 hover:bg-red-600 text-white rounded-lg"
                    >
                      🗑️
                    </button>
                  )}
                </div>
              </form>
            )}

            {/* Categories Tab */}
            {activeTab === 'categories' && (
              <div>
                {/* Add New Category */}
                <div className="mb-6 p-4 bg-blue-50 rounded-lg">
                  <h3 className="text-lg font-semibold text-gray-800 mb-3">Add New Category</h3>
                  <form
                    onSubmit={(e) => {
                      e.preventDefault();
                      saveCategory();
                    }}
                  >
                    <div className="mb-3">
                      <label className="block text-sm font-semibold text-gray-700 mb-2">Category Name</label>
                      <input
                        type="text"
                        value={newCategory.name}
                        onChange={(e) => setNewCategory({ ...newCategory, name: e.target.value })}
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g., Development, Meeting"
                        required
                      />
                    </div>
                    <div className="mb-3">
                      <label className="block text-sm font-semibold text-gray-700 mb-2">Color</label>
                      <div className="flex gap-2">
                        <input
                          type="color"
                          value={newCategory.color}
                          onChange={(e) => setNewCategory({ ...newCategory, color: e.target.value })}
                          className="w-16 h-10 border border-gray-300 rounded cursor-pointer"
                        />
                        <input
                          type="text"
                          value={newCategory.color}
                          onChange={(e) => setNewCategory({ ...newCategory, color: e.target.value })}
                          className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                          placeholder="#3b82f6"
                        />
                      </div>
                    </div>
                    <button
                      type="submit"
                      className="w-full px-6 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-semibold"
                    >
                      ➕ Add Category
                    </button>
                  </form>
                </div>

                {/* Categories List */}
                <div>
                  <h3 className="text-lg font-semibold text-gray-800 mb-3">Existing Categories</h3>
                  <div className="space-y-2">
                    {categories.map((category) => (
                      <div
                        key={category.id}
                        className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100"
                      >
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 rounded" style={{ backgroundColor: category.color }}></div>
                          <span className="font-semibold">{category.name}</span>
                        </div>
                        <button onClick={() => deleteCategory(category.id!)} className="text-red-500 hover:text-red-700">
                          🗑️
                        </button>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default App;
