# Az Attendance WordPress (Az Academy Core + Theme)

Az Attendance is a WordPress-based attendance management system built around:
- Az Academy Core plugin (admin-first attendance flows)
- Az Academy theme (simple frontend that aligns with the plugin)

The goal is to manage classes, students, and attendance directly inside WordPress Admin, with clean role-based UX for teachers and students.

Repository: https://github.com/tuannho0802/Az-Attendance-WordPress

## Overview
- Custom Post Types:
  - Classes: `az_class` (internal slug `az_class`, rewrite to `/lop-hoc` if enabled)
  - Students: `az_student`
- Attendance storage:
  - Database table: `{prefix}_az_attendance`
  - Unique per (class_id, student_id, session_date, attendance_type)
- Admin interface:
  - Manage Attendance menu with class cards and inline “Create Class”
  - Class Dashboard with two attendance phases: Check-in and Mid-session
  - Inline student creation inside class edit screen (no full page navigation)
- Roles:
  - Administrator: full access
  - Teacher (`az_teacher`): can create classes, view/edit assigned classes
  - Student (`az_student`): sees only classes they belong to, can interact with attendance for themselves
- UI polish:
  - Clean admin cards/tables, consistent primary color, lightweight chart
  - Streamlined admin bar and menus for teacher/student

## Features
- Create and manage classes from a dedicated admin page (cards)
- Inline student creation while editing a class; optional email to auto-create a WP user (assigned `az_student`)
- Two attendance phases per session date:
  - Check-in (start of class)
  - Mid-session
- Persist attendance with AJAX (nonce-protected); refresh loads saved state immediately
- Teacher access limited to their assigned classes; students limited to their own records
- Frontend theme page shows class cards with basic info

## Architecture
- Plugin (Core): `wp-content/plugins/az-academy-core`
  - CPT registration, meta boxes, AJAX handlers, admin pages, role management, database setup
  - Admin assets:
    - CSS: `admin/css/admin-style.css`, `admin/css/attendance.css`, `admin/css/attendance-list.css`
    - JS: `admin/js/attendance.js`, `admin/js/attendance-list.js`, `admin/js/class-edit.js`
- Theme: `wp-content/themes/az-academy`
  - Minimal header/footer, homepage listing class cards, single template for `az_class`
  - CSS aligns with plugin’s brand colors

## Database
Table: `{prefix}_az_attendance`
- Columns:
  - `id` bigint unsigned AUTO_INCREMENT (PK)
  - `class_id` bigint unsigned
  - `student_id` bigint unsigned
  - `session_date` date
  - `attendance_type` varchar(20) (“check-in” | “mid-session”)
  - `status` tinyint(1) (0/1)
  - `note` text
  - `created_at` datetime DEFAULT CURRENT_TIMESTAMP
  - `updated_at` datetime ON UPDATE CURRENT_TIMESTAMP
- Indexes:
  - `KEY class_id`, `KEY student_id`, `KEY session_date`
  - `UNIQUE KEY (class_id, student_id, session_date, attendance_type)`

## Installation (LocalWP)
Prerequisites:
- Local by Flywheel / LocalWP installed
- A Local site created, for example:
  - Name: `az-attendance`
  - Domain: `http://az-attendance.local/`
  - Path: `c:\Users\TUAN\Local Sites\az-attendance\`

Steps:
- Open the Local site’s `app/public` folder:  
  `c:\Users\TUAN\Local Sites\az-attendance\app\public`
- Place plugin and theme:
  - Copy (or clone) plugin folder into:  
    `wp-content/plugins/az-academy-core`
  - Copy theme folder into:  
    `wp-content/themes/az-academy`
- In WordPress Admin:
  - Activate theme “Az Academy”
  - Activate plugin “Az Academy Core”
- Optional (roles cleanup & migration):
  - Deactivate then re-activate “Az Academy Core” to:
    - Migrate users in default WP roles (author/editor/contributor/subscriber) to `az_student`
    - Create `az_student` CPT posts for those users if missing
    - Remove those default roles to simplify the system

## Usage
- Manage Attendance (Admin menu):
  - View class cards: title, teacher, total sessions, student count
  - Inline “Create Class” form: title, teacher, total sessions
  - Open “Class Dashboard” for per-class attendance
- Class Dashboard:
  - Two tabs: “Check-in” and “Mid-session”
  - Toggle attendance via switches; add optional notes
  - Save persists the session’s data; reloading shows stored state
  - Summary cards and a small doughnut chart for presence/absence
- Edit Class:
  - Inline add students: Name + optional Email
    - With email: creates/links a WP user (role `az_student`)
    - Without email: creates only a `az_student` CPT record
  - Selected students are saved to the class meta
- Roles & Access:
  - Administrator: full control
  - Teacher: can create classes; can access/edit only classes assigned to them
  - Student: sees classes they belong to; attendance limited to their own record

## Frontend (Theme)
- Homepage lists class cards with:
  - Title, teacher, total sessions, headcount
  - Link to class single page (`single-az_class.php`)
- Single class page shows the class info, and a button to open the admin edit screen (for authorized users)

## Customization
- Colors:
  - Admin primary/dark in `admin/css/admin-style.css`
  - Theme overrides in `themes/az-academy/style.css`
- Charts:
  - Chart.js via CDN; can be replaced with local scripts if required
- Rewrite rules:
  - Plugin flushes rewrite on version change; you can re-activate to refresh

## Troubleshooting
- Attendance not persisting:
  - Ensure buttons trigger AJAX (check browser console / network)
  - Nonce must be valid; reloading the page regenerates it
  - Verify the `{prefix}_az_attendance` table exists and has the unique index
- Role permissions:
  - Teacher must be assigned to the class (`az_teacher_user` meta)
  - Students must be included in class `az_students` meta
- Frontend 404 for `az_class`:
  - Activate plugin, or re-activate to flush rewrite rules

## Project Paths (Local)
- Plugin: `wp-content/plugins/az-academy-core`
- Theme: `wp-content/themes/az-academy`
- DB table: `{prefix}_az_attendance` (e.g., `wp_az_attendance` with default prefix)

## License
- Choose and add a license file appropriate for your project and distribution needs.

## Links
- Repository: https://github.com/tuannho0802/Az-Attendance-WordPress
