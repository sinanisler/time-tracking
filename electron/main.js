import { app, BrowserWindow, ipcMain } from 'electron';
import path from 'path';
import { fileURLToPath } from 'url';
import Database from 'better-sqlite3';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

let mainWindow;
let db;

// Initialize database
function initDatabase() {
  const userDataPath = app.getPath('userData');
  const dbPath = path.join(userDataPath, 'timetracking.db');
  
  db = new Database(dbPath);
  
  // Create tables
  db.exec(`
    CREATE TABLE IF NOT EXISTS categories (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      color TEXT NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS tasks (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      start_date TEXT NOT NULL,
      start_time TEXT NOT NULL,
      end_date TEXT NOT NULL,
      end_time TEXT NOT NULL,
      category_id INTEGER,
      description TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS time_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      task_id INTEGER NOT NULL,
      duration INTEGER NOT NULL,
      note TEXT,
      timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
    );
  `);
}

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1400,
    height: 900,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.join(__dirname, 'preload.js'),
    },
  });

  // Load the app
  if (process.env.NODE_ENV === 'development') {
    mainWindow.loadURL('http://localhost:5173');
    mainWindow.webContents.openDevTools();
  } else {
    mainWindow.loadFile(path.join(__dirname, '../dist/index.html'));
  }
}

// Database IPC handlers
ipcMain.handle('db:getTasks', () => {
  const stmt = db.prepare('SELECT * FROM tasks ORDER BY start_date, start_time');
  return stmt.all();
});

ipcMain.handle('db:saveTask', (event, task) => {
  if (task.id) {
    const stmt = db.prepare(`
      UPDATE tasks 
      SET title = ?, start_date = ?, start_time = ?, end_date = ?, end_time = ?, 
          category_id = ?, description = ?
      WHERE id = ?
    `);
    stmt.run(
      task.title, task.startDate, task.startTime, task.endDate, task.endTime,
      task.category || null, task.description, task.id
    );
    return { id: task.id };
  } else {
    const stmt = db.prepare(`
      INSERT INTO tasks (title, start_date, start_time, end_date, end_time, category_id, description)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `);
    const result = stmt.run(
      task.title, task.startDate, task.startTime, task.endDate, task.endTime,
      task.category || null, task.description
    );
    return { id: result.lastInsertRowid };
  }
});

ipcMain.handle('db:deleteTask', (event, taskId) => {
  const stmt = db.prepare('DELETE FROM tasks WHERE id = ?');
  stmt.run(taskId);
  return { success: true };
});

ipcMain.handle('db:getCategories', () => {
  const stmt = db.prepare('SELECT * FROM categories ORDER BY name');
  return stmt.all();
});

ipcMain.handle('db:saveCategory', (event, category) => {
  const stmt = db.prepare('INSERT INTO categories (name, color) VALUES (?, ?)');
  const result = stmt.run(category.name, category.color);
  return { id: result.lastInsertRowid };
});

ipcMain.handle('db:deleteCategory', (event, categoryId) => {
  const stmt = db.prepare('DELETE FROM categories WHERE id = ?');
  stmt.run(categoryId);
  return { success: true };
});

ipcMain.handle('db:getTimeLogs', (event, taskId) => {
  const stmt = db.prepare('SELECT * FROM time_logs WHERE task_id = ? ORDER BY timestamp DESC');
  return stmt.all(taskId);
});

ipcMain.handle('db:saveTimeLog', (event, log) => {
  const stmt = db.prepare('INSERT INTO time_logs (task_id, duration, note, timestamp) VALUES (?, ?, ?, ?)');
  const result = stmt.run(log.taskId, log.duration, log.note, log.timestamp);
  return { id: result.lastInsertRowid };
});

ipcMain.handle('db:updateTimeLogNote', (event, logId, note) => {
  const stmt = db.prepare('UPDATE time_logs SET note = ? WHERE id = ?');
  stmt.run(note, logId);
  return { success: true };
});

ipcMain.handle('db:deleteTimeLog', (event, logId) => {
  const stmt = db.prepare('DELETE FROM time_logs WHERE id = ?');
  stmt.run(logId);
  return { success: true };
});

app.whenReady().then(() => {
  initDatabase();
  createWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    if (db) db.close();
    app.quit();
  }
});
