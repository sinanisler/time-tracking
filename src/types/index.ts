export interface Task {
  id?: number;
  title: string;
  startDate: string;
  startTime: string;
  endDate: string;
  endTime: string;
  category?: number;
  description?: string;
}

export interface Category {
  id?: number;
  name: string;
  color: string;
}

export interface TimeLog {
  id?: number;
  task_id?: number;
  taskId?: number;
  duration: number;
  note?: string;
  timestamp: string;
}

export interface ElectronAPI {
  getTasks: () => Promise<Task[]>;
  saveTask: (task: Task) => Promise<{ id: number }>;
  deleteTask: (taskId: number) => Promise<{ success: boolean }>;
  getCategories: () => Promise<Category[]>;
  saveCategory: (category: Category) => Promise<{ id: number }>;
  deleteCategory: (categoryId: number) => Promise<{ success: boolean }>;
  getTimeLogs: (taskId: number) => Promise<TimeLog[]>;
  saveTimeLog: (log: TimeLog) => Promise<{ id: number }>;
  updateTimeLogNote: (logId: number, note: string) => Promise<{ success: boolean }>;
  deleteTimeLog: (logId: number) => Promise<{ success: boolean }>;
}

declare global {
  interface Window {
    electronAPI: ElectronAPI;
  }
}
