# Time Tracking Plugin - Developer Guide

## Architecture Overview

The plugin follows a modular architecture with clear separation of concerns:

```
time-tracking/
├── time-tracking.php          # Main plugin file (bootstrap)
├── includes/                  # PHP classes
│   ├── class-tt-settings.php  # Settings page handler
│   ├── class-tt-calendar.php  # Calendar page renderer
│   └── class-tt-ajax.php      # AJAX request handlers
├── assets/
│   ├── css/
│   │   └── style.css         # Custom styles
│   └── js/
│       └── calendar.js       # Calendar JavaScript
├── languages/                 # Translation files
├── readme.txt                # WordPress.org readme
└── uninstall.php             # Uninstall script
```

## Class Structure

### TimeTrackingPlugin (Main Class)
**File:** `time-tracking.php`

**Responsibilities:**
- Plugin initialization
- Loading dependencies
- Registering post types and taxonomies
- Managing admin menu
- Asset enqueueing

**Key Methods:**
- `get_instance()` - Singleton pattern implementation
- `load_textdomain()` - Load translation files
- `register_post_type()` - Register tt_task post type
- `register_taxonomy()` - Register tt_category taxonomy
- `add_admin_menu()` - Add menu with role-based access
- `enqueue_calendar_assets()` - Load CSS/JS with localization

### TT_Settings
**File:** `includes/class-tt-settings.php`

**Responsibilities:**
- Settings page rendering
- Settings registration and sanitization
- Data export/import functionality
- User data management

**Key Methods:**
- `add_settings_page()` - Register settings submenu
- `register_settings()` - Register settings with WordPress
- `get_settings()` - Retrieve settings with defaults
- `ajax_export_data()` - Handle data export
- `ajax_import_data()` - Handle data import
- `ajax_clear_user_data()` - Clear user's data

**Settings Structure:**
```php
array(
    'timezone'           => 'UTC',
    'color_scheme'       => 'light',
    'start_time'         => '09:00',
    'end_time'           => '18:00',
    'working_days'       => array(1, 2, 3, 4, 5),
    'allowed_roles'      => array('administrator', 'editor'),
    'default_categories' => array(),
)
```

### TT_Calendar
**File:** `includes/class-tt-calendar.php`

**Responsibilities:**
- Calendar page HTML rendering
- Sidebar interface rendering
- Applying user settings to calendar

**Key Methods:**
- `render()` - Render complete calendar page

### TT_Ajax
**File:** `includes/class-tt-ajax.php`

**Responsibilities:**
- Handle all AJAX requests
- Task CRUD operations
- Category CRUD operations
- Time log management

**Key Methods:**
- `ajax_save_task()` - Create/update task
- `ajax_delete_task()` - Delete task
- `ajax_get_tasks()` - Retrieve user tasks
- `ajax_save_category()` - Create category
- `ajax_delete_category()` - Delete category
- `ajax_get_categories()` - Retrieve user categories
- `ajax_save_time_log()` - Add time log
- `ajax_get_time_logs()` - Get task time logs
- `ajax_update_time_log_note()` - Update log note
- `ajax_delete_time_log()` - Delete time log

## Database Schema

### Custom Post Type: tt_task
**Post Fields:**
- `post_title` - Task name
- `post_author` - User ID (owner)
- `post_status` - Always 'publish'

**Post Meta:**
- `_tt_start_date` - Start date (YYYY-MM-DD)
- `_tt_start_time` - Start time (HH:MM)
- `_tt_end_date` - End date (YYYY-MM-DD)
- `_tt_end_time` - End time (HH:MM)
- `_tt_description` - Task description
- `_tt_time_logs` - Array of time logs

**Time Log Structure:**
```php
array(
    'id'        => 'log_unique_id',
    'duration'  => 3600, // seconds
    'note'      => 'Task note',
    'timestamp' => '2024-01-01 12:00:00'
)
```

### Taxonomy: tt_category
**Term Meta:**
- `_tt_category_color` - Hex color code
- `_tt_category_user` - Owner user ID
- `_tt_category_display_name` - Display name

## Security Implementation

### Nonce Verification
All AJAX requests verify nonces:
```php
check_ajax_referer('tt_nonce', 'nonce');
```

### User Permission Checks
```php
// Check if user is logged in
if (!is_user_logged_in()) {
    wp_send_json_error(__('You must be logged in', 'time-tracking'));
}

// Verify ownership
$post = get_post($task_id);
if (!$post || $post->post_author != get_current_user_id()) {
    wp_send_json_error(__('Permission denied', 'time-tracking'));
}
```

### Data Sanitization
```php
// Text fields
sanitize_text_field($input);

// Textarea
sanitize_textarea_field($input);

// Hex colors
sanitize_hex_color($input);

// Integers
intval($input);
absint($input);
```

### Output Escaping
```php
// HTML
esc_html($text);

// Attributes
esc_attr($text);

// URLs
esc_url($url);

// JavaScript
esc_js($text);
```

## Hooks & Filters

### Actions
- `init` - Register post types, taxonomies, load textdomain
- `admin_menu` - Add admin menu pages
- `wp_ajax_*` - AJAX handlers

### Available Filters (for future extensibility)
Consider adding filters for:
- Settings default values
- Allowed roles
- Calendar configuration
- Export data format

## JavaScript API

### Global Objects
**ttCalendarData** - Localized data object
```javascript
{
    ajaxUrl: string,      // WordPress AJAX URL
    nonce: string,        // Security nonce
    startTime: string,    // Work start time
    endTime: string,      // Work end time
    hideWeekends: bool,   // Hide weekends flag
    i18n: object         // Translations
}
```

### Main Functions
**timeTrackingApp()** - Alpine.js component
- `init()` - Initialize calendar and load data
- `loadTasks()` - Fetch user tasks
- `saveTask()` - Save/update task
- `deleteTask()` - Delete task
- `loadCategories()` - Fetch categories
- `saveCategory()` - Create category
- `deleteCategory()` - Delete category
- `startTimer()` - Start time tracking
- `stopTimer()` - Stop and save time log
- `formatTime()` - Format seconds to HH:MM:SS

## WordPress Coding Standards

The plugin follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/):

### PHP Standards
- Tabs for indentation
- Yoda conditions
- Single and double quotes appropriately
- Braces on same line (except functions/classes)
- Space after control structures

### Naming Conventions
- `snake_case` for functions and variables
- `PascalCase` for class names
- `SCREAMING_SNAKE_CASE` for constants
- `tt_` prefix for all database entities

### Documentation
- PHPDoc blocks for all functions and classes
- Inline comments for complex logic
- File headers with package information

## Translation

### Text Domain
`time-tracking`

### Creating POT File
```bash
wp i18n make-pot . languages/time-tracking.pot
```

### Translation Functions Used
- `__()` - Return translated string
- `_e()` - Echo translated string
- `esc_html__()` - Return escaped translated string
- `esc_html_e()` - Echo escaped translated string
- `esc_attr__()` - Return escaped attribute translation
- `sprintf()` with placeholders for dynamic content

## Testing Checklist

### Functionality Testing
- [ ] Create, edit, delete tasks
- [ ] Create, edit, delete categories
- [ ] Start/stop timer
- [ ] Add/edit/delete time logs
- [ ] Export data
- [ ] Import data
- [ ] Clear data
- [ ] Change settings
- [ ] Test role-based access

### Security Testing
- [ ] Verify nonce protection
- [ ] Test user isolation
- [ ] Check permission enforcement
- [ ] Validate SQL injection protection
- [ ] Test XSS prevention

### Compatibility Testing
- [ ] Test on different PHP versions (8.0+)
- [ ] Test on different WordPress versions (5.8+)
- [ ] Test with different themes
- [ ] Test with common plugins
- [ ] Test on different browsers

## Debugging

### Enable WordPress Debug Mode
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check Error Logs
- WordPress: `wp-content/debug.log`
- Browser Console: F12 Developer Tools

### Common Issues
1. **AJAX not working**: Check nonce generation
2. **Calendar not loading**: Check JavaScript console
3. **Permissions errors**: Verify role settings
4. **Missing translations**: Check text domain

## Contributing

### Code Style
- Follow WordPress Coding Standards
- Add PHPDoc comments
- Use proper escaping and sanitization
- Test thoroughly before committing

### Pull Request Process
1. Fork the repository
2. Create feature branch
3. Make changes with tests
4. Submit pull request with description
5. Wait for review

## Resources

- [WordPress Developer Handbook](https://developer.wordpress.org/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [FullCalendar Documentation](https://fullcalendar.io/docs)
- [Alpine.js Documentation](https://alpinejs.dev/)
