# Time Tracking Plugin - Feature Documentation

## Overview
The Time Tracking plugin is a comprehensive time management solution for WordPress, featuring a modern calendar interface, task management, and detailed time logging capabilities.

## Core Features

### 1. Interactive Calendar
- **Drag-to-Select**: Click and drag to create time blocks directly on the calendar
- **Multiple Views**: Day, week, and month views available
- **Responsive Design**: Works seamlessly on desktop and mobile devices
- **Real-time Updates**: Changes reflect immediately without page reload

### 2. Task Management
- **Create Tasks**: Add tasks with start/end dates and times
- **Task Details**: Include descriptions for comprehensive task information
- **Drag & Drop**: Move and resize tasks directly on the calendar
- **Category Assignment**: Organize tasks with color-coded categories

### 3. Category System
- **Custom Categories**: Create unlimited categories for task organization
- **Color Coding**: Assign unique colors to each category for visual distinction
- **User-Specific**: Each user maintains their own set of categories

### 4. Time Tracking
- **Built-in Timer**: Start/stop timer to track actual time spent
- **Time Logs**: Save multiple time logs per task
- **Notes**: Add notes to time logs for detailed records
- **Time History**: View all time logs with timestamps

### 5. Settings & Customization

#### General Settings
- **Timezone Configuration**: Set timezone or use WordPress default
- **Color Scheme**: Choose between Light, Dark, or Auto (system-based)

#### Working Hours & Days
- **Custom Hours**: Set your work start and end times
- **Working Days**: Select which days of the week to display
- **Flexible Schedule**: Customize to match your workflow

#### Role Management
- **Access Control**: Define which user roles can access the plugin
- **Default Roles**: Administrator, Editor, Author, Contributor
- **Granular Control**: Add or remove roles as needed

### 6. Data Management

#### Export Data
- **JSON Format**: Export all your data in a portable format
- **Complete Backup**: Includes tasks, categories, and time logs
- **Easy Transfer**: Move data between installations

#### Import Data
- **Restore Data**: Import previously exported data
- **Merge or Replace**: Choose how to handle existing data
- **Validation**: Automatic data validation on import

#### Clear Data
- **User-Specific**: Only clears your own data
- **Safe Guards**: Multiple confirmations prevent accidents
- **Statistics**: See how many tasks and categories before clearing

## User Privacy & Security

### Data Isolation
- **Private Calendars**: Each user has their own private calendar
- **No Cross-Access**: Users cannot see or modify others' data
- **Secure Storage**: All data stored in WordPress database

### Security Features
- **Nonce Verification**: All AJAX requests verified
- **Permission Checks**: Operations checked against user permissions
- **SQL Injection Protection**: WordPress APIs prevent SQL injection
- **XSS Protection**: All output properly escaped
- **Directory Protection**: Index files prevent directory browsing

## Technical Specifications

### Requirements
- WordPress 5.8 or higher
- PHP 8.0 or higher
- Modern web browser with JavaScript enabled

### Database
- Uses WordPress custom post types (tt_task)
- Uses WordPress taxonomies (tt_category)
- Post meta for task details and time logs
- Term meta for category information

### Performance
- Efficient AJAX calls
- Minimal database queries
- Optimized calendar rendering
- Parallel data loading

## WordPress.org Compliance

### Standards
- WordPress Coding Standards compliant
- Full internationalization (i18n) support
- Translation ready with .pot file generation
- GPL v2+ licensed

### Best Practices
- Singleton pattern for main class
- Proper hooks and filters
- Clean uninstall process
- No external service dependencies (except CDN assets)

## Future Enhancements

### Planned Features
- Downloadable/offline assets (instead of CDN)
- Advanced reporting and analytics
- Team collaboration features
- Client project management
- Invoice generation
- CSV export option
- Calendar integrations (Google Calendar, iCal)

## Support & Documentation

### Getting Help
- Visit plugin settings page for options
- Check readme.txt for FAQs
- Contact plugin author at https://sinanisler.com

### Contributing
This plugin is open source under GPL v2+. Contributions are welcome!

## Changelog

### Version 3.0.0
- Complete plugin refactoring
- Modular architecture implementation
- Comprehensive settings page
- Role-based access control
- Data export/import functionality
- Dark mode support
- Enhanced security features
- WordPress.org compliance
- Full i18n implementation
- Uninstall script
