# AZ ACADEMY ATTENDANCE SYSTEM - TECHNICAL DOCUMENTATION

**Version:** 1.0  
**Project Name:** Az Academy Attendance WordPress Plugin  
**Repository:** https://github.com/tuannho0802/Az-Attendance-WordPress  
**Purpose:** Quản lý điểm danh, lớp học và học viên chuyên sâu cho học viện  

---

## TABLE OF CONTENTS

1. [Project Architecture](#1-project-architecture)
2. [Plugin Structure (`az-academy-core`)](#2-plugin-structure-az-academy-core)
3. [Theme Structure (`az-academy`)](#3-theme-structure-az-academy)
4. [Database Architecture](#4-database-architecture)
5. [File-by-File Breakdown](#5-file-by-file-breakdown)
6. [Coding Standards & Constraints](#6-coding-standards--constraints)
7. [Data Flow & AJAX Patterns](#7-data-flow--ajax-patterns)
8. [Role-Based Access Control](#8-role-based-access-control)

---

## 1. PROJECT ARCHITECTURE

### Overall Structure
```
Az-Attendance-WordPress/
├── plugins/
│   └── az-academy-core/           # Main Plugin (Admin-first)
│       ├── az-academy-core.php    # Entry point & CPT registration
│       ├── includes/              # Core logic & functionality
│       ├── admin/                 # Admin UI, CSS, JS
│       └── templates/             # Reusable admin templates
├── themes/
│   └── az-academy/                # Minimal frontend theme
│       ├── style.css              # Theme styling
│       ├── index.php              # Homepage
│       └── single-az_class.php    # Single class page
└── README.md                       # Project overview
```

### Core Technologies
- **Framework:** WordPress Plugin Custom (No heavy frameworks)
- **Data Storage:** Custom Post Types (CPT) + Custom Database Table
- **Frontend:** Vanilla JS + AJAX (no jQuery dependency for new code)
- **Styling:** Custom CSS with responsive design
- **Charts:** Chart.js (CDN)

---

## 2. PLUGIN STRUCTURE (`az-academy-core`)

### 2.1 Root Files

#### `az-academy-core.php` (Entry Point)
**Purpose:** Plugin header, initialization, and Custom Post Type (CPT) registration  
**Key Functions:**
- Defines plugin metadata (name, version, author)
- Registers CPT: `az_class` and `az_student`
- Hooks plugin activation/deactivation for database table setup
- Enqueues admin assets (CSS, JS)
- Registers custom taxonomies if needed

**Critical Code Patterns:**
```php
// CPT Registration
register_post_type('az_class', [
    'label' => 'Classes',
    'public' => true,
    'show_in_menu' => true,
    'rewrite' => ['slug' => 'lop-hoc'],
    'supports' => ['title', 'custom-fields'],
]);

// Activation hook
register_activation_hook(__FILE__, 'azac_create_attendance_table');

// Enqueue admin assets
add_action('admin_enqueue_scripts', 'azac_enqueue_admin_assets');
```

---

### 2.2 `includes/` Directory

**Purpose:** Core business logic, helpers, and controllers  
**Key Files:**

#### `class-azac-core-helper.php`
**Purpose:** Utility functions for the entire plugin  
**Key Functions:**
- `azac_get_class_by_id($class_id)` - Fetch class object with metadata
- `azac_get_students_in_class($class_id)` - Get all students in a class
- `azac_format_date($date, $format)` - Format dates consistently (YYYY-MM-DD)
- `azac_get_attendance_by_date($class_id, $date)` - Fetch attendance records for a session
- `azac_get_badge_class($status)` - Return CSS class for status badge (Success/Error/Warning)
- `azac_paginate($total_items, $items_per_page, $current_page)` - Pagination logic
- `azac_sanitize_input($input, $type)` - Input sanitization (text/email/number)

**When to use:** Always call these functions instead of querying directly.

#### `class-azac-database.php` (if exists)
**Purpose:** Database operations for attendance table  
**Key Functions:**
- `azac_create_attendance_table()` - Creates `{prefix}_az_attendance` table
- `azac_insert_attendance($class_id, $student_id, $date, $type, $status, $note)` - Insert/update attendance
- `azac_get_attendance_records($class_id, $date)` - Fetch all records for a session
- `azac_delete_attendance($id)` - Delete single record

**Database Table Schema:**
```sql
CREATE TABLE {prefix}_az_attendance (
    id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id bigint unsigned NOT NULL,
    student_id bigint unsigned NOT NULL,
    session_date date NOT NULL,
    attendance_type varchar(20) NOT NULL DEFAULT 'check-in',
    status tinyint(1) NOT NULL DEFAULT 0,
    note text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY class_id (class_id),
    KEY student_id (student_id),
    KEY session_date (session_date),
    UNIQUE KEY unique_attendance (class_id, student_id, session_date, attendance_type)
);
```

#### `class-azac-admin-pages.php`
**Purpose:** Render all admin UI (tables, cards, forms)  
**Key Methods/Functions:**
- `azac_attendance_manage_page()` - Main "Manage Attendance" page with class cards
- `azac_class_dashboard_page()` - Per-class attendance editor (Check-in / Mid-session tabs)
- `azac_render_class_card($class)` - Render single class card (title, teacher, stats)
- `azac_render_attendance_table($class_id, $date, $type)` - Render student attendance table
- `azac_render_inline_create_class_form()` - Inline form to create new class
- `azac_render_student_addition_form()` - Form to add students to class (inline on edit screen)

**Important:** Every table row (`<tr>`) must have `<td data-label="Column Name">` for mobile responsiveness.

#### `class-azac-role-manager.php`
**Purpose:** User role and permission management  
**Key Functions:**
- `azac_register_custom_roles()` - Create `az_teacher` and `az_student` roles
- `azac_check_class_access($user_id, $class_id)` - Verify user can access a class
- `azac_migrate_wp_roles_to_custom()` - Migrate built-in WP roles to custom roles on activation
- `azac_get_user_classes($user_id)` - Get all classes assigned to a user

**Role Capabilities:**
- `az_teacher`: can create/edit/delete classes, view attendance, edit student lists
- `az_student`: can view classes, cannot modify attendance directly (view-only)
- `administrator`: full control

#### `class-azac-ajax-handler.php`
**Purpose:** AJAX endpoints for attendance operations  
**Key Endpoints:**
- `wp_ajax_azac_save_attendance` - Save attendance toggle (Check-in or Mid-session)
  - Expects: `class_id`, `student_id`, `session_date`, `attendance_type`, `status`, `nonce`
  - Returns: JSON `{ success: bool, message: string, data: {...} }`
- `wp_ajax_azac_create_class` - Create a new class
- `wp_ajax_azac_add_student_to_class` - Add student to a class
- `wp_ajax_azac_delete_attendance` - Delete an attendance record

**Security:** All AJAX endpoints check nonce via `wp_verify_nonce()`.

---

### 2.3 `admin/` Directory

**Purpose:** Admin interface assets (CSS, JS, admin-specific files)

#### `css/admin-style.css`
**Purpose:** Global admin styling for all pages  
**Key Classes:**
- `.azac-admin-teal` - Main container (specificity override)
- `.azac-card` - Card component (classes, summary boxes)
- `.azac-table-responsive` - Table wrapper for mobile responsiveness
- `.azac-switch` - Toggle switch component for attendance
- `.azac-badge` - Status badge (Success, Error, Warning)
- `.azac-btn` - Button styles
- `.azac-input-group` - Form input wrapper

**Namespace Rule:** All classes MUST start with `.azac-`  
**Mobile Breakpoint:** `< 782px` (WordPress default)

#### `css/attendance.css`
**Purpose:** Specific styles for class dashboard (Check-in / Mid-session tabs)  
**Key Styles:**
- Tab styling (active/inactive states)
- Table layout for attendance (desktop and mobile card layout)
- Summary cards styling
- Chart container styling

#### `css/attendance-list.css`
**Purpose:** Styles for the attendance list/overview page  
**Key Styles:**
- Class cards layout (grid on desktop, stack on mobile)
- Inline form styles

#### `css/azac-teacher-view.css`
**Purpose:** Independent styles for teacher role dashboard  
**Key Styles:**
- Teacher-specific menu and toolbar styling
- Simplified interface for non-admin users

#### `js/attendance.js`
**Purpose:** Main attendance interaction logic for class dashboard  
**Key Functions:**
- `toggleAttendance(studentId, type, status)` - Toggle attendance switch
- `saveAttendance(classId, sessionDate, type)` - Save all attendance for a session (AJAX)
- `loadAttendanceState(classId, sessionDate, type)` - Load saved state on page load
- `updateSummaryCards()` - Recalculate and update summary (present/absent count)
- `initializeChart()` - Initialize Chart.js doughnut chart for presence/absence

**Data Structure:**
```javascript
const attendanceData = {
    [studentId]: {
        'check-in': { status: 1, note: 'Present' },
        'mid-session': { status: 0, note: 'Late' }
    }
};
```

#### `js/attendance-list.js`
**Purpose:** Manage attendance overview/list page interactions  
**Key Functions:**
- `openClassDashboard(classId)` - Navigate to class dashboard
- `deleteClass(classId)` - Delete a class (with confirmation)
- `initializeClassCards()` - Setup event listeners for class card actions
- `filterClasses(searchTerm)` - Filter classes by title

#### `js/class-edit.js`
**Purpose:** Handle inline student addition and class editing  
**Key Functions:**
- `addStudentRow()` - Add new student input row
- `removeStudentRow(rowIndex)` - Remove student input row
- `validateStudentForm()` - Validate student name and optional email
- `submitStudentData()` - AJAX call to add student to class

---

### 2.4 `templates/` Directory

**Purpose:** Reusable HTML template snippets (partials)

#### `admin-header.php`
**Purpose:** Header for admin pages  
**Outputs:**
- Title, breadcrumbs, action buttons

#### `class-card-template.php`
**Purpose:** Reusable class card component  
**Variables Needed:**
```php
$class = [
    'ID' => $class_id,
    'post_title' => 'Class Name',
    'teacher_name' => 'Teacher Name',
    'total_sessions' => 10,
    'student_count' => 25,
];
```

#### `attendance-table-template.php`
**Purpose:** Reusable attendance table (with or without switches for editing)  
**Variables Needed:**
```php
$students = [...];
$attendance_records = [...];
$editable = true; // If false, display-only
```

#### `badge-template.php`
**Purpose:** Status badge (Present/Absent/Late)  
**Variables Needed:**
```php
$status = 'success'; // success | error | warning
$label = 'Present';
```

---

## 3. THEME STRUCTURE (`az-academy`)

**Purpose:** Minimal frontend theme aligned with plugin  
**Approach:** Simple, supporting the plugin without redundant functionality

### Root Files

#### `style.css`
**Purpose:** Theme CSS and WordPress theme header  
**Key Styles:**
- Color scheme aligned with plugin (primary color)
- Responsive layout for class cards
- Typography (heading and body font stacks)
- Link and button styling

#### `index.php`
**Purpose:** Homepage template  
**Displays:**
- List of all `az_class` posts as cards
- Each card shows: title, teacher, total sessions, student count
- Link to single class page

#### `single-az_class.php`
**Purpose:** Single class page template  
**Displays:**
- Full class details (title, teacher, description, sessions)
- Button to edit class (if user has permission)
- List of students (if public or user is admin/teacher)

#### `header.php`
**Purpose:** Header template (minimal)  
**Outputs:**
- Site title and navigation menu
- Admin bar for logged-in users

#### `footer.php`
**Purpose:** Footer template  
**Outputs:**
- Footer text, copyright, credits

---

## 4. DATABASE ARCHITECTURE

### Main Table: `{prefix}_az_attendance`

| Column | Type | Nullable | Default | Purpose |
|--------|------|----------|---------|---------|
| `id` | bigint unsigned | NO | AUTO_INCREMENT | Primary key |
| `class_id` | bigint unsigned | NO | - | Reference to `az_class` post |
| `student_id` | bigint unsigned | NO | - | Reference to `az_student` post |
| `session_date` | date | NO | - | Attendance date (YYYY-MM-DD) |
| `attendance_type` | varchar(20) | NO | 'check-in' | "check-in" or "mid-session" |
| `status` | tinyint(1) | NO | 0 | 1 = Present, 0 = Absent |
| `note` | text | YES | NULL | Optional note (late, excused, etc.) |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | datetime | NO | CURRENT_TIMESTAMP | Last update time |

### Indexes
```sql
KEY class_id (class_id)
KEY student_id (student_id)
KEY session_date (session_date)
UNIQUE KEY unique_attendance (class_id, student_id, session_date, attendance_type)
```

### Post Type Meta (Stored in `{prefix}_postmeta`)

#### For `az_class` posts:
- `az_teacher_user` - Teacher user ID (single)
- `az_students` - Serialized array of student post IDs
- `az_total_sessions` - Number of sessions planned
- `az_description` - Class description

#### For `az_student` posts:
- `az_user_id` - Link to WordPress user (if created via email)
- `az_email` - Student email (if provided)
- `az_phone` - Student phone (optional)

---

## 5. FILE-BY-FILE BREAKDOWN

### Critical Files (Must Not Break)

| File Path | Purpose | If Broken |
|-----------|---------|-----------|
| `az-academy-core.php` | Plugin entry, CPT registration | Plugin won't activate, CPT won't exist |
| `includes/class-azac-database.php` | Attendance table operations | Attendance save/load fails |
| `includes/class-azac-admin-pages.php` | Admin UI rendering | Admin pages show blank or errors |
| `admin/css/admin-style.css` | Global admin styling | Interface looks broken, especially on mobile |
| `admin/js/attendance.js` | Attendance toggle logic | Attendance buttons won't work |
| `admin/js/ajax-handler.js` | AJAX communication | Saves won't persist |

### Important Files (Should Modify Carefully)

| File Path | Purpose | Risk |
|-----------|---------|------|
| `includes/class-azac-role-manager.php` | Role setup & access control | Users may lose permissions |
| `includes/class-azac-core-helper.php` | Helper functions | If changed, all dependent code breaks |
| `admin/css/attendance.css` | Mobile/desktop layout | Mobile responsiveness breaks |

### Optional Files (Safe to Customize)

| File Path | Purpose | Customization |
|-----------|---------|---------------|
| `themes/az-academy/style.css` | Frontend styling | Colors, fonts, layout |
| `themes/az-academy/index.php` | Homepage layout | Card display, ordering |
| `admin/css/azac-teacher-view.css` | Teacher-specific styling | Simplify for specific roles |

---

## 6. CODING STANDARDS & CONSTRAINTS

### CSS Namespace Rule
**MUST FOLLOW:** All CSS classes must start with `.azac-`

```css
/* CORRECT */
.azac-card { }
.azac-button { }
.azac-admin-teal { }

/* INCORRECT - Will be ignored or cause conflicts */
.card { }
.button { }
.admin-panel { }
```

### Mobile Responsive Pattern (Table-to-Card)

**Desktop (>782px):** Standard WordPress `.widefat.fixed.striped` table
```html
<table class="widefat fixed striped">
    <tr>
        <td data-label="Student Name">John Doe</td>
        <td data-label="Status">Present</td>
    </tr>
</table>
```

**Mobile (<782px):** CSS transforms table into card layout
```css
@media (max-width: 782px) {
    table { display: block; }
    tr { display: block; border: 1px solid #ccc; margin-bottom: 1rem; }
    td {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem;
    }
    td::before {
        content: attr(data-label);
        font-weight: bold;
        width: 40%;
    }
}
```

**CRITICAL:** Every `<td>` must have `data-label="Column Name"` or mobile view breaks.

### PHP Coding Standards

**File Structure:**
```php
<?php
/**
 * File description
 * 
 * @package Az_Academy_Core
 */

defined('ABSPATH') or die('No direct access');

// Class or function definition
class MyClass { }
function my_function() { }
```

**Naming Convention:**
- Functions: `azac_snake_case()`
- Classes: `AZAC_Pascal_Case`
- Variables: `$snake_case`
- Constants: `AZAC_CONSTANT_NAME`

**Security:**
- Always use `wp_verify_nonce()` for AJAX
- Always escape output: `echo esc_html($var);`
- Always sanitize input: `sanitize_text_field($_POST['name'])`

### JavaScript Standards

**Modern JS (ES6+):**
```javascript
// Use arrow functions, const/let, template literals
const toggleAttendance = (studentId) => {
    const data = { student_id: studentId };
    // Use fetch() instead of $.ajax()
    fetch(ajaxUrl, { method: 'POST', body: JSON.stringify(data) })
        .then(r => r.json())
        .then(data => console.log(data));
};
```

**Avoid:**
- jQuery (unless already in project)
- Global variables
- Inline event handlers

---

## 7. DATA FLOW & AJAX PATTERNS

### Attendance Save Flow

**User Action → AJAX Call → Database Update → Refresh UI**

```
[User clicks attendance toggle]
  ↓
[JavaScript attendance.js captures: studentId, type, status]
  ↓
[fetch() POST to wp-admin/admin-ajax.php?action=azac_save_attendance]
  ↓
[AJAX handler validates nonce, class access]
  ↓
[Insert/update row in az_attendance table]
  ↓
[Return JSON: { success: true, attendance: {...} }]
  ↓
[JavaScript updates UI: badge color, summary count, chart]
```

### Nonce Pattern

**In PHP (page render):**
```php
wp_nonce_field('azac_attendance_nonce', 'nonce');
// OR in admin pages
wp_create_nonce('azac_attendance_nonce');
```

**In JavaScript (AJAX):**
```javascript
const nonce = document.querySelector('[name="nonce"]').value;
fetch(ajaxUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `action=azac_save_attendance&nonce=${nonce}&student_id=${id}&status=${status}`
});
```

**In AJAX Handler:**
```php
add_action('wp_ajax_azac_save_attendance', function() {
    check_ajax_referer('azac_attendance_nonce', 'nonce');
    // Process request...
});
```

---

## 8. ROLE-BASED ACCESS CONTROL

### User Roles

| Role | Capabilities | Access |
|------|--------------|--------|
| `administrator` | Full control | All classes, all students, full edit |
| `az_teacher` | Create/manage classes | Only own classes (assigned via `az_teacher_user`) |
| `az_student` | View classes and attendance | Only own records (via `az_students` meta) |
| Built-in WP roles | Migrated to `az_student` on activation | View-only |

### Access Control Checks

**Class Level:**
```php
$user_id = get_current_user_id();
$class_id = $_GET['class_id'];
$can_edit = azac_check_class_access($user_id, $class_id);

if (!$can_edit && !current_user_can('manage_options')) {
    wp_die('Access denied');
}
```

**Attendance Level:**
```php
// Only allow user to edit own attendance or if they're the teacher
$student_id = $_POST['student_id'];
$is_own_record = ($student_id == $user_id);
$is_teacher_or_admin = current_user_can('manage_options') || azac_is_class_teacher($class_id);

if (!($is_own_record || $is_teacher_or_admin)) {
    wp_send_json_error('Permission denied');
}
```

---

## QUICK REFERENCE: COMMON TASKS

### Add a New Admin Page

1. Create function in `class-azac-admin-pages.php`:
   ```php
   function azac_my_new_page() {
       // Render HTML
   }
   ```

2. Register in `az-academy-core.php`:
   ```php
   add_action('admin_menu', function() {
       add_menu_page(
           'My Page',
           'My Page',
           'manage_options',
           'azac-my-page',
           'azac_my_new_page',
           'dashicons-clipboard'
       );
   });
   ```

### Add a New AJAX Endpoint

1. Create handler in `class-azac-ajax-handler.php`:
   ```php
   add_action('wp_ajax_azac_my_action', function() {
       check_ajax_referer('azac_nonce', 'nonce');
       // Process...
       wp_send_json_success(['message' => 'Done']);
   });
   ```

2. Call from JavaScript:
   ```javascript
   fetch(ajaxUrl + '?action=azac_my_action', {
       method: 'POST',
       headers: { 'X-WP-Nonce': nonce }
   }).then(r => r.json());
   ```

### Fix Mobile Responsiveness

1. Check all `<td>` elements have `data-label`:
   ```php
   // WRONG
   echo '<td>John Doe</td>';
   
   // CORRECT
   echo '<td data-label="Student Name">John Doe</td>';
   ```

2. Verify CSS uses `.azac-` namespace
3. Test with DevTools mobile view (< 782px)

### Modify Database Schema

1. Update `azac_create_attendance_table()` in `class-azac-database.php`
2. Increment version in `az-academy-core.php`: `define('AZAC_VERSION', '1.1');`
3. Deactivate and re-activate plugin to run new schema

---

## DEPLOYMENT CHECKLIST

- [ ] All PHP files are properly escaped and sanitized
- [ ] All AJAX calls use nonce verification
- [ ] All CSS classes start with `.azac-`
- [ ] All `<td>` elements have `data-label` attribute
- [ ] Mobile view tested at < 782px width
- [ ] Role permissions tested for all user types
- [ ] Database table created successfully on activation
- [ ] No PHP notices or warnings in debug mode

---

## SUPPORT & TROUBLESHOOTING

See `HUONGDAN.MD` for step-by-step troubleshooting guide.

---

**Last Updated:** February 2026  
**Maintainer:** Your Team
