import { contextBridge, ipcRenderer } from 'electron';

contextBridge.exposeInMainWorld('electronAPI', {
  // Tasks
  getTasks: () => ipcRenderer.invoke('db:getTasks'),
  saveTask: (task) => ipcRenderer.invoke('db:saveTask', task),
  deleteTask: (taskId) => ipcRenderer.invoke('db:deleteTask', taskId),
  
  // Categories
  getCategories: () => ipcRenderer.invoke('db:getCategories'),
  saveCategory: (category) => ipcRenderer.invoke('db:saveCategory', category),
  deleteCategory: (categoryId) => ipcRenderer.invoke('db:deleteCategory', categoryId),
  
  // Time logs
  getTimeLogs: (taskId) => ipcRenderer.invoke('db:getTimeLogs', taskId),
  saveTimeLog: (log) => ipcRenderer.invoke('db:saveTimeLog', log),
  updateTimeLogNote: (logId, note) => ipcRenderer.invoke('db:updateTimeLogNote', logId, note),
  deleteTimeLog: (logId) => ipcRenderer.invoke('db:deleteTimeLog', logId),
});
