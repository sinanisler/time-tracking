# WordPress Plugin to Desktop Application Conversion Guide

This document explains how the Time Tracking WordPress plugin was converted into a standalone desktop application.

## Overview

The original application was a WordPress plugin that used:
- **WordPress Custom Post Types** for tasks
- **WordPress Taxonomies** for categories
- **WordPress Post Meta** for time logs
- **Alpine.js** for reactivity
- **FullCalendar** for the calendar interface
- **Tailwind CSS** for styling

The desktop version uses:
- **SQLite Database** for data storage
- **Electron** for desktop application framework
- **React** for UI components
- **FullCalendar React** for the calendar interface
- **Tailwind CSS** for styling (maintained)

## Architecture Changes

### Data Storage Layer

#### Before (WordPress)
```php
// Tasks stored as custom post type
register_post_type('tt_task', ...);

// Categories as taxonomy
register_taxonomy('tt_category', 'tt_task', ...);

// Time logs as post meta
update_post_meta($post_id, '_tt_time_logs', $logs);
```

#### After (Desktop)
```javascript
// SQLite database with proper tables
CREATE TABLE tasks (...);
CREATE TABLE categories (...);
CREATE TABLE time_logs (...);

// IPC communication between Electron and React
window.electronAPI.getTasks();
window.electronAPI.saveTask(task);
```

### Frontend Architecture

#### Before (WordPress + Alpine.js)
```html
<div x-data="timeTrackingApp()">
  <div x-show="sidebarOpen">...</div>
</div>
```

#### After (React)
```tsx
function App() {
  const [sidebarOpen, setSidebarOpen] = useState(true);
  return (
    <div>
      {sidebarOpen && <div>...</div>}
    </div>
  );
}
```

### API Communication

#### Before (WordPress AJAX)
```javascript
const formData = new FormData();
formData.append('action', 'tt_save_task');
fetch(ajaxurl, { method: 'POST', body: formData });
```

#### After (Electron IPC)
```javascript
await window.electronAPI.saveTask(task);
```

## Key Technical Decisions

### Why Electron over Tauri?

1. **Mature Ecosystem**: Electron has been around longer with more resources
2. **SQLite Integration**: Better-sqlite3 works seamlessly with Electron
3. **Developer Experience**: Hot reload and debugging tools are excellent
4. **Cross-platform**: Proven track record for Windows and macOS

### Why SQLite over Other Solutions?

1. **No Server Required**: Embedded database perfect for desktop apps
2. **Performance**: Fast for local operations
3. **Reliability**: ACID compliant, battle-tested
4. **Simple**: No setup or configuration needed
5. **Portable**: Single file database that moves with the app

### Why React over Alpine.js?

1. **Type Safety**: TypeScript support for better development experience
2. **Component Reusability**: Easier to break down into modular components
3. **Ecosystem**: Rich library of React components (like FullCalendar React)
4. **Developer Tools**: Better debugging and development tools
5. **State Management**: More predictable state management patterns

## Database Schema Design

The SQLite schema was designed to closely mirror the WordPress data structure:

### Tasks Table
```sql
CREATE TABLE tasks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  start_date TEXT NOT NULL,
  start_time TEXT NOT NULL,
  end_date TEXT NOT NULL,
  end_time TEXT NOT NULL,
  category_id INTEGER,
  description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
);
```

**Mapping from WordPress:**
- `post_title` → `title`
- `_tt_start_date` meta → `start_date`
- `_tt_start_time` meta → `start_time`
- etc.

### Categories Table
```sql
CREATE TABLE categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  color TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**Mapping from WordPress:**
- `term_name` → `name`
- `_tt_category_color` meta → `color`

### Time Logs Table
```sql
CREATE TABLE time_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id INTEGER NOT NULL,
  duration INTEGER NOT NULL,
  note TEXT,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
);
```

**Mapping from WordPress:**
- Previously stored as serialized array in post meta
- Now proper relational table with foreign key constraints

## Feature Parity

All features from the WordPress plugin have been maintained:

✅ **Calendar View**
- Drag-to-select time ranges
- Multiple view modes (month/week/day)
- Event drag-and-drop
- Event resizing

✅ **Task Management**
- Create new tasks
- Edit existing tasks
- Delete tasks
- Assign categories
- Add descriptions

✅ **Category System**
- Create categories with custom colors
- Assign tasks to categories
- Color-coded calendar events
- Delete categories

✅ **Time Tracking**
- Start/stop timer
- Track time per task
- Save time logs
- Add notes to time logs
- Delete time logs

✅ **Data Persistence**
- All data stored locally
- Survives app restarts
- No internet connection required

## Development Workflow

### Setting Up Development Environment

1. Clone the repository
2. Install dependencies: `npm install`
3. Run in development: `npm run electron:dev`

### Building for Production

1. Build React app: `npm run build`
2. Package with Electron: `npm run electron:build`

The electron-builder automatically handles:
- Code signing (with proper certificates)
- Native module rebuilding (better-sqlite3)
- Platform-specific installers
- Auto-updates (can be configured)

## Testing Strategy

### Manual Testing Checklist

- [ ] Create a new task via calendar drag
- [ ] Edit an existing task
- [ ] Delete a task
- [ ] Create a category
- [ ] Assign task to category
- [ ] Start timer on a task
- [ ] Stop timer and verify log is saved
- [ ] Add note to time log
- [ ] Delete time log
- [ ] Restart app and verify data persistence
- [ ] Test calendar navigation
- [ ] Test different calendar views
- [ ] Drag/resize events on calendar

## Performance Considerations

### SQLite Optimization

- **Indexes**: Added on frequently queried columns
- **Prepared Statements**: Used for all queries to prevent SQL injection
- **Transactions**: Could be added for bulk operations

### React Optimization

- **Memo**: Could be added for expensive computations
- **Lazy Loading**: Could split code with React.lazy()
- **Virtual Scrolling**: Not needed yet, but available for large lists

## Security Considerations

1. **Context Isolation**: Enabled in Electron for security
2. **Preload Script**: Limited API exposure to renderer
3. **No Remote Module**: Avoided deprecated remote module
4. **SQL Injection**: Prevented via prepared statements
5. **XSS Protection**: React automatically escapes content

## Future Enhancements

Potential features that could be added:

1. **Data Export/Import**
   - Export to CSV/JSON
   - Import from CSV/JSON
   - Backup/restore functionality

2. **Reporting**
   - Time summaries by day/week/month
   - Category-based reports
   - Charts and visualizations

3. **Sync Capabilities**
   - Cloud sync (optional)
   - Multi-device support
   - Conflict resolution

4. **Customization**
   - Theme support
   - Customizable work hours
   - Weekend toggle
   - Notification preferences

5. **Advanced Features**
   - Recurring tasks
   - Task templates
   - Keyboard shortcuts
   - Search functionality

## Migration from WordPress Plugin

If you have existing data in the WordPress plugin, you can migrate it:

1. **Export from WordPress**:
```php
// Custom export script to generate JSON
$tasks = get_posts(['post_type' => 'tt_task']);
$categories = get_terms(['taxonomy' => 'tt_category']);
// ... export logic
```

2. **Import to Desktop App**:
```javascript
// Custom import script to read JSON and insert into SQLite
// This would be a one-time migration tool
```

## Conclusion

The conversion from WordPress plugin to desktop application was successful while maintaining all original features. The new architecture provides:

- **Better Performance**: Direct database access without HTTP overhead
- **Offline First**: No server or internet connection required
- **Native Experience**: True desktop application feel
- **Type Safety**: TypeScript catches errors at compile time
- **Modern Stack**: React, Vite, and Electron provide excellent DX

The application is now ready for distribution as a standalone executable for Windows and macOS users.
