# Time Tracking Desktop Application

A cross-platform desktop application for time tracking with a visual calendar interface, category management, and detailed time logging capabilities.

## Features

- **Visual Calendar Interface**: Interactive week/month/day views with drag-to-select functionality
- **Task Management**: Create, edit, and delete tasks with date/time ranges
- **Category System**: Organize tasks with color-coded categories
- **Time Tracking**: Built-in timer to track time spent on tasks
- **Time Logs**: Detailed logging with notes for each time entry
- **Local Database**: All data stored locally using SQLite
- **Cross-Platform**: Runs on Windows and macOS

## Technology Stack

- **Framework**: Electron
- **Frontend**: React + TypeScript
- **Build Tool**: Vite
- **Styling**: Tailwind CSS
- **Calendar**: FullCalendar
- **Database**: SQLite (better-sqlite3)

## Prerequisites

- Node.js 18 or higher
- npm or yarn

## Installation

1. Clone the repository:
```bash
git clone https://github.com/sinanisler/time-tracking.git
cd time-tracking
```

2. Install dependencies:
```bash
npm install
```

## Development

Run the application in development mode:

```bash
npm run electron:dev
```

This will:
1. Start the Vite development server
2. Launch the Electron application
3. Enable hot-reload for React components
4. Open DevTools automatically

## Building

### Build for Current Platform

```bash
npm run electron:build
```

### Build for Windows

```bash
npm run electron:build:win
```

### Build for macOS

```bash
npm run electron:build:mac
```

The built applications will be available in the `dist-electron` directory.

## Project Structure

```
time-tracking/
├── electron/           # Electron main process
│   ├── main.js        # Main Electron entry point
│   └── preload.js     # Preload script for IPC
├── src/               # React application
│   ├── components/    # React components (future)
│   ├── database/      # Database utilities (future)
│   ├── types/         # TypeScript type definitions
│   ├── styles/        # CSS and Tailwind styles
│   ├── App.tsx        # Main React component
│   └── main.tsx       # React entry point
├── public/            # Static assets
├── dist/              # Vite build output
└── dist-electron/     # Electron build output
```

## Database Schema

The application uses SQLite with the following tables:

### Categories
- `id`: Primary key
- `name`: Category name
- `color`: Hex color code
- `created_at`: Timestamp

### Tasks
- `id`: Primary key
- `title`: Task name
- `start_date`: Start date (YYYY-MM-DD)
- `start_time`: Start time (HH:MM)
- `end_date`: End date (YYYY-MM-DD)
- `end_time`: End time (HH:MM)
- `category_id`: Foreign key to categories
- `description`: Task description
- `created_at`: Timestamp

### Time Logs
- `id`: Primary key
- `task_id`: Foreign key to tasks
- `duration`: Duration in seconds
- `note`: Optional note
- `timestamp`: When the log was created

## Usage

1. **Creating Tasks**: 
   - Drag on the calendar to select a time range
   - Fill in task details in the sidebar
   - Click "Save Task"

2. **Tracking Time**:
   - Open a task
   - Click "Start" to begin timing
   - Click "Stop" to save the time log
   - Add notes to time logs by clicking on them

3. **Managing Categories**:
   - Switch to the "Categories" tab
   - Add new categories with custom colors
   - Delete categories as needed

4. **Calendar Views**:
   - Switch between Month, Week, and Day views
   - Navigate using prev/next buttons
   - Click "Today" to jump to current date

## Data Storage

All data is stored locally in a SQLite database located at:
- **Windows**: `%APPDATA%/time-tracking/timetracking.db`
- **macOS**: `~/Library/Application Support/time-tracking/timetracking.db`

## License

GPL-2.0-or-later

## Author

Sinan Isler - [sinanisler.com](https://sinanisler.com)

## Converting from WordPress Plugin

This application was converted from a WordPress plugin to a standalone desktop application. The original WordPress plugin used:
- WordPress custom post types for tasks
- WordPress taxonomies for categories
- WordPress post meta for time logs

The desktop version maintains the same functionality while using SQLite for data storage and Electron for cross-platform desktop support.
