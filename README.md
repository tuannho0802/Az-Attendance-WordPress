# 🎓 Az Academy Attendance System

> A clean, role-based WordPress attendance management system for schools and training centers.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![GitHub](https://img.shields.io/badge/GitHub-Visit%20Repo-black?logo=github)](https://github.com/tuannho0802/Az-Attendance-WordPress)

---

## 📖 Table of Contents

- [✨ Features](#-features)
- [🎯 Quick Start](#-quick-start)
- [📁 Project Structure](#-project-structure)
- [🔧 Technical Stack](#-technical-stack)
- [📚 Documentation](#-documentation)
- [🚀 Usage Guide](#-usage-guide)
- [👥 User Roles](#-user-roles)
- [🗄️ Database](#-database)
- [⚙️ Installation](#-installation)
- [🐛 Troubleshooting](#-troubleshooting)
- [🤝 Contributing](#-contributing)
- [📝 License](#-license)

---

## ✨ Features

### 🎓 Core Functionality

✅ **Class Management**
- Create and manage classes with ease
- Assign teachers to classes
- Track total sessions per class
- View student headcount in real-time

✅ **Student Management**
- Add students to classes (inline, no page reload)
- Auto-create WordPress users from email
- Link students directly to accounts
- View student details and attendance history

✅ **Attendance Tracking**
- Two attendance phases per session: Check-in & Mid-session
- Toggle attendance with a single click
- Add optional notes (late, excused, etc.)
- AJAX-powered (no page reload required)
- Persistent storage with instant reload

✅ **Role-Based Access Control**
- **Admin**: Full system control
- **Teacher**: Create classes, manage assigned classes only
- **Student**: View own classes and attendance records
- Automatic role migration from WordPress default roles

✅ **Clean UI/UX**
- Responsive design (desktop & mobile)
- Card-based layout for classes
- Summary statistics and charts
- Intuitive admin interface
- Mobile-friendly (tested < 782px)

### 🎨 Mobile & Responsiveness

📱 **Responsive Design**
- Desktop: Clean table layout with WordPress `.widefat` style
- Tablet: Smooth card transition
- Mobile: Optimized card layout with readable labels
- Touch-friendly toggle switches
- No horizontal scrolling

### 📊 Analytics & Reporting

📈 **Visual Feedback**
- Doughnut chart for presence/absence statistics
- Summary cards showing present/absent/late counts
- Per-session and per-student tracking
- Export-ready data structure

---

## 🎯 Quick Start

### 30-Second Setup

```bash
# 1. Clone the repository
git clone https://github.com/tuannho0802/Az-Attendance-WordPress.git

# 2. Copy plugin and theme
cp -r plugins/az-academy-core wp-content/plugins/
cp -r themes/az-academy wp-content/themes/

# 3. Activate in WordPress Admin
# Dashboard → Appearance → Themes → Activate "Az Academy"
# Dashboard → Plugins → Activate "Az Academy Core"

# 4. Done! 🎉
# Visit: WordPress Admin → Manage Attendance
```

### First-Time User Steps

1. ✅ Login to WordPress Admin
2. ✅ Go to **Manage Attendance** menu
3. ✅ Click **Create Class**
4. ✅ Add students inline
5. ✅ Open Class Dashboard
6. ✅ Toggle attendance for the session
7. ✅ Click **Save** → Done!

---

## 📁 Project Structure

```
Az-Attendance-WordPress/
│
├── 📦 plugins/az-academy-core/          ← Main plugin (admin-first)
│   ├── az-academy-core.php              ← Entry point & CPT registration
│   │
│   ├── 📂 includes/                     ← Core business logic
│   │   ├── class-azac-core-helper.php   ← Helper functions
│   │   ├── class-azac-database.php      ← Database operations
│   │   ├── class-azac-admin-pages.php   ← Admin UI rendering
│   │   ├── class-azac-role-manager.php  ← Role & permissions
│   │   └── class-azac-ajax-handler.php  ← AJAX endpoints
│   │
│   ├── 📂 admin/                        ← Admin interface assets
│   │   ├── css/
│   │   │   ├── admin-style.css          ← Global admin styles
│   │   │   ├── attendance.css           ← Dashboard styles
│   │   │   ├── attendance-list.css      ← List page styles
│   │   │   └── azac-teacher-view.css    ← Teacher-specific styles
│   │   │
│   │   └── js/
│   │       ├── attendance.js            ← Toggle & save logic
│   │       ├── attendance-list.js       ← List interactions
│   │       └── class-edit.js            ← Student addition logic
│   │
│   └── 📂 templates/                    ← Reusable HTML partials
│       ├── admin-header.php
│       ├── class-card-template.php
│       ├── attendance-table-template.php
│       └── badge-template.php
│
├── 🎨 themes/az-academy/                ← Simple frontend theme
│   ├── style.css                        ← Theme styling
│   ├── index.php                        ← Homepage
│   ├── single-az_class.php              ← Single class page
│   ├── header.php
│   └── footer.php
│
├── 📚 INSTRUCTION.MD                    ← Technical documentation (detailed)
├── 📖 HUONGDAN.MD                       ← Maintenance guide (Vietnamese)
├── README.md                            ← This file
└── .gitignore

```

### 📌 Key Directories at a Glance

| Directory | Purpose | When to Edit |
|-----------|---------|--------------|
| `includes/` | Business logic, database, roles | Core functionality changes |
| `admin/css/` | All admin styling | Visual updates, mobile fixes |
| `admin/js/` | Interaction scripts, AJAX | Feature additions, bug fixes |
| `templates/` | Reusable HTML components | UI improvements |
| `themes/` | Frontend website | Theme customization |

---

## 🔧 Technical Stack

### Languages & Frameworks

| Technology | Version | Purpose |
|-----------|---------|---------|
| **WordPress** | 5.0+ | CMS platform |
| **PHP** | 7.4+ | Backend logic |
| **JavaScript** | ES6+ | Frontend interactions |
| **CSS** | 3 | Styling & responsive design |
| **MySQL** | 5.7+ | Database |

### Key Libraries & APIs

- **Chart.js** (CDN) - Attendance statistics visualization
- **WordPress REST API** - Future extensibility
- **AJAX** - Seamless attendance updates
- **Custom Post Types** - Classes & Students data structure
- **Custom Database Tables** - Attendance records

### Naming & Conventions

```
Classes/Functions:  azac_snake_case()
CSS Classes:        .azac-namespace-element
Database:           wp_az_attendance
Post Types:         az_class, az_student
```

---

## 📚 Documentation

### 📖 INSTRUCTION.MD - For Developers & AI Agents
**[→ Read INSTRUCTION.MD](INSTRUCTION.MD)**

**Contents:**
- ✅ Complete technical architecture breakdown
- ✅ File-by-file component reference
- ✅ Database schema and relationships
- ✅ PHP coding standards and patterns
- ✅ JavaScript patterns and AJAX flows
- ✅ CSS namespace conventions
- ✅ Role-based access control details
- ✅ Common customization tasks

**Best for:**
- Understanding system internals
- Adding new features
- Modifying database structure
- AI agents reading code context

### 📘 HUONGDAN.MD - For Maintainers & Operators
**[→ Read HUONGDAN.MD](HUONGDAN.MD)**

**Contents:**
- ✅ Step-by-step installation guide
- ✅ Detailed file function inventory
- ✅ 7 common issues + solutions
- ✅ Debugging commands and tools
- ✅ Daily/weekly/monthly maintenance tasks
- ✅ Deployment checklist
- ✅ Changelog template

**Best for:**
- Troubleshooting problems
- Day-to-day maintenance
- Server deployment
- Error diagnosis and fixes

---

## 🚀 Usage Guide

### For Administrators

#### Creating a Class

1. **Navigate** to `WordPress Admin → Manage Attendance`
2. **Click** "Create Class" button
3. **Fill in:**
   - Class Title (e.g., "Advanced JavaScript")
   - Teacher (select from dropdown)
   - Total Sessions (e.g., 20)
4. **Click Save** → Class appears in the list
5. **Click Class Card** to open Class Dashboard

#### Adding Students

1. **Open** a class card or click "Edit"
2. **Scroll** to "Students" section
3. **Click** "Add Student" button
4. **Fill in:**
   - Student Name (required)
   - Email (optional - auto-creates WordPress user)
5. **Click Add** → Student appears in the class

#### Managing Attendance

1. **Open** Class Dashboard
2. **Select Tab:** "Check-in" or "Mid-session"
3. **Select Date:** Pick the session date
4. **Toggle** each student's attendance (switch on/off)
5. **Add Note** (optional): Click to add reason (late, absent, etc.)
6. **Click Save** → Data persists immediately
7. **Refresh** page → See saved attendance with badges

### For Teachers

#### View Assigned Classes

1. **Login** as teacher account
2. **Navigate** to `Manage Attendance`
3. **See** only classes assigned to you
4. **Cannot see** other teachers' classes

#### Edit Attendance

1. **Same as admin steps** (see "Managing Attendance" above)
2. **Can only edit** classes you're assigned to
3. **Cannot create new classes** (admin-only feature)

### For Students

#### View Your Classes

1. **Login** as student account
2. **Navigate** to `My Classes` (frontend)
3. **See** all classes you're enrolled in
4. **Click class card** for details

#### View Your Attendance

1. **Open** a class you're in
2. **Scroll** to "Your Attendance"
3. **View-only** (no editing)
4. **See** check-in & mid-session records

---

## 👥 User Roles

### Role Hierarchy & Capabilities

```
┌─────────────────────────────────────────────────┐
│ Administrator                                   │
│ ✅ Create classes                              │
│ ✅ Manage all classes                          │
│ ✅ Create/delete students                      │
│ ✅ Full attendance control                     │
│ ✅ Manage users & roles                        │
│ ✅ Access reports & analytics                  │
└─────────────────────────────────────────────────┘
                     ↑
┌─────────────────────────────────────────────────┐
│ Teacher (az_teacher)                            │
│ ✅ Create classes                              │
│ ✅ Edit only assigned classes                  │
│ ✅ Add/remove students in own classes          │
│ ✅ Full attendance control (own classes)       │
│ ❌ Cannot see other teachers' classes          │
│ ❌ Cannot manage users                         │
└─────────────────────────────────────────────────┘
                     ↑
┌─────────────────────────────────────────────────┐
│ Student (az_student)                            │
│ ✅ View own classes                            │
│ ✅ View own attendance records                 │
│ ❌ Cannot edit attendance                      │
│ ❌ Cannot see other students' records          │
│ ❌ Cannot create classes                       │
└─────────────────────────────────────────────────┘
```

### Auto-Migration on First Activation

When you **first activate the plugin**, it automatically:

1. Creates `az_teacher` and `az_student` roles
2. Migrates users from WordPress default roles:
   - `author` → `az_student`
   - `editor` → `az_student`
   - `contributor` → `az_student`
   - `subscriber` → `az_student`
3. Creates `az_student` CPT posts for migrated users
4. Removes default WP roles (to simplify the system)

---

## 🗄️ Database

### Main Table: `{prefix}_az_attendance`

Stores all attendance records with the following structure:

```sql
CREATE TABLE wp_az_attendance (
    id                bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    class_id          bigint unsigned NOT NULL,
    student_id        bigint unsigned NOT NULL,
    session_date      date NOT NULL,
    attendance_type   varchar(20) NOT NULL DEFAULT 'check-in',
    status            tinyint(1) NOT NULL DEFAULT 0,       -- 1 = present, 0 = absent
    note              text,                                  -- Optional note
    created_at        datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at        datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY class_id (class_id),
    KEY student_id (student_id),
    KEY session_date (session_date),
    UNIQUE KEY unique_attendance (class_id, student_id, session_date, attendance_type)
);
```

### Post Meta Storage

```
az_class posts store:
  ├── az_teacher_user     → Teacher user ID
  ├── az_students         → Array of student post IDs
  ├── az_total_sessions   → Number of planned sessions
  └── az_description      → Class description

az_student posts store:
  ├── az_user_id          → Linked WordPress user ID
  ├── az_email            → Student email
  └── az_phone            → Optional phone number
```

---

## ⚙️ Installation

### System Requirements

- 🖥️ **WordPress:** 5.0 or higher
- 🐘 **PHP:** 7.4 or higher (8.0+ recommended)
- 🗄️ **MySQL:** 5.7+ or MariaDB 10.3+
- 💾 **Disk Space:** 50MB (plugin + theme)
- 🔐 **Permissions:** Write access to `wp-content/`

### Step-by-Step Installation

#### Option 1: LocalWP (Development)

```bash
# 1. Create a Local site
Open LocalWP → "Create a new site"
  Name: az-attendance
  Domain: http://az-attendance.local/

# 2. Navigate to site root
cd ~/Local\ Sites/az-attendance/app/public

# 3. Clone and organize
git clone https://github.com/tuannho0802/Az-Attendance-WordPress.git temp
mv temp/plugins/az-academy-core wp-content/plugins/
mv temp/themes/az-academy wp-content/themes/
rm -rf temp

# 4. Activate in WordPress Admin
Visit: http://az-attendance.local/wp-admin
Dashboard → Appearance → Themes → Activate "Az Academy"
Dashboard → Plugins → Activate "Az Academy Core"

# 5. Verify installation
Dashboard → Manage Attendance (menu should appear)
```

#### Option 2: Production Server

```bash
# 1. SSH into your server
ssh user@your-domain.com

# 2. Navigate to WordPress root
cd /var/www/your-domain/wp-content/

# 3. Clone and organize
git clone https://github.com/tuannho0802/Az-Attendance-WordPress.git temp
mv temp/plugins/az-academy-core plugins/
mv temp/themes/az-academy themes/
rm -rf temp

# 4. Set permissions
chmod -R 755 plugins/az-academy-core/
chmod -R 755 themes/az-academy/

# 5. Activate via WordPress Admin (or WP-CLI)
wp plugin activate az-academy-core
wp theme activate az-academy
```

#### Option 3: Manual Upload (cPanel)

1. Download repository as ZIP
2. Extract `plugins/az-academy-core/` → Upload to `wp-content/plugins/`
3. Extract `themes/az-academy/` → Upload to `wp-content/themes/`
4. Go to WordPress Admin → Appearance → Themes → Activate "Az Academy"
5. Go to WordPress Admin → Plugins → Activate "Az Academy Core"

### Post-Installation Checklist

- ✅ Plugin is activated (Dashboard → Plugins)
- ✅ Theme is activated (Dashboard → Appearance → Themes)
- ✅ "Manage Attendance" menu appears in WordPress Admin
- ✅ Database table `wp_az_attendance` exists (check phpMyAdmin)
- ✅ No errors in `wp-content/debug.log`
- ✅ Can create a test class successfully

---

## 🐛 Troubleshooting

### Quick Problem Solver

#### ❌ Plugin won't activate

**Error:** "Parse error in az-academy-core.php"

**Solution:**
1. Check PHP version: `php -v` (need 7.4+)
2. Verify PHP syntax: `php -l plugins/az-academy-core/az-academy-core.php`
3. Check `wp-content/debug.log` for errors
4. Try deactivate + re-activate

#### ❌ "Manage Attendance" menu missing

**Error:** Menu doesn't appear in WordPress Admin

**Solution:**
1. Verify plugin is activated
2. Try deactivate + activate plugin again
3. Check user role (need `manage_options` capability)
4. Look in `debug.log` for errors

#### ❌ Mobile view looks broken

**Error:** On phones (< 782px), table rows have no labels

**Solution:**
1. Check all `<td>` elements have `data-label="..."` attribute
2. Verify `admin/css/admin-style.css` is loaded
3. Clear browser cache (Ctrl+Shift+Del)
4. Test in DevTools: F12 → Ctrl+Shift+M → Drag to < 782px

#### ❌ Attendance won't save

**Error:** Click toggle attendance → nothing happens

**Solution:**
1. Open DevTools: F12 → Network tab
2. Click attendance toggle → check for AJAX call
3. Should see `POST /wp-admin/admin-ajax.php?action=azac_save_attendance`
4. If request fails: check `debug.log` for permission errors
5. If 400/403: nonce might be invalid, refresh page

#### ❌ Teacher can't see assigned class

**Error:** Teacher logs in → "Manage Attendance" shows no classes

**Solution:**
1. Check teacher's role: Admin → Users → Edit teacher
   - Should have `az_teacher` role
2. Check class assignment: Admin → Edit Class → Check "Assigned Teacher"
3. Verify postmeta: Run in wp-cli:
   ```bash
   wp postmeta list --post_id=123 | grep az_teacher_user
   ```

### Get Detailed Help

For more troubleshooting steps, see:

- 📖 **[HUONGDAN.MD - Troubleshooting Section](HUONGDAN.MD#4-sửa-chữa-lỗi-phổ-biến)** (7 common issues + solutions)
- 📚 **[INSTRUCTION.MD - Debugging Guide](INSTRUCTION.MD#7-data-flow--ajax-patterns)**

---

## 🤝 Contributing

### How to Contribute

1. **Fork** the repository on GitHub
2. **Create** a feature branch: `git checkout -b feature/your-feature`
3. **Make** your changes and test thoroughly
4. **Commit** with clear messages: `git commit -m "feat: add new feature"`
5. **Push** to your fork: `git push origin feature/your-feature`
6. **Create** a Pull Request with detailed description

### Development Guidelines

- Follow WordPress coding standards
- Use `.azac-` prefix for all CSS classes
- Add `data-label` to all `<td>` elements
- Test on mobile (< 782px width)
- Update documentation in INSTRUCTION.MD
- Add entry to CHANGELOG

### Testing Checklist

- ✅ Test on desktop (> 1024px)
- ✅ Test on tablet (768px - 1024px)
- ✅ Test on mobile (< 768px)
- ✅ Test with admin role
- ✅ Test with teacher role
- ✅ Test with student role
- ✅ Check browser console (no errors)
- ✅ Check debug.log (no warnings)

---

## 📝 License

This project is licensed under the **MIT License** - see the [LICENSE](LICENSE) file for details.

### You are free to:

✅ Use this project for any purpose (commercial or personal)
✅ Modify and distribute the code
✅ Include it in proprietary applications

### Just remember to:

✅ Include a copy of the MIT License
✅ Include copyright notice
✅ State significant changes made

---

## 📞 Support & Resources

### Documentation Files

| File | Purpose | Best For |
|------|---------|----------|
| **[INSTRUCTION.MD](INSTRUCTION.MD)** | Technical deep-dive | Developers, AI agents |
| **[HUONGDAN.MD](HUONGDAN.MD)** | Maintenance & troubleshooting | Operators, maintainers |
| **[README.md](README.md)** | Overview & quick start | Everyone |

### Quick Links

- 🔗 **GitHub Repository:** https://github.com/tuannho0802/Az-Attendance-WordPress
- 📚 **WordPress Plugin Handbook:** https://developer.wordpress.org/plugins/
- 🎓 **WordPress Theme Guide:** https://developer.wordpress.org/themes/
- 🔧 **PHP Documentation:** https://www.php.net/manual/

### Common Commands

```bash
# Activate plugin via WP-CLI
wp plugin activate az-academy-core

# Activate theme via WP-CLI
wp theme activate az-academy

# Create test user
wp user create teacher_test teacher@test.local --role=az_teacher --user_pass=secure123

# Clear rewrite rules
wp rewrite flush

# Check for errors
tail -50 wp-content/debug.log
```

---

## 🎯 Roadmap & Future Features

### Planned Enhancements

- 📊 Advanced attendance reports and exports
- 📧 Email notifications for teachers & students
- 📱 Standalone mobile app
- 🔄 Attendance sync with third-party systems
- 🗂️ Bulk import/export functionality
- 🌍 Multi-language support
- 🔔 Real-time notifications

### Contributing Ideas

Have a feature idea? Please:

1. Check existing [issues on GitHub](https://github.com/tuannho0802/Az-Attendance-WordPress/issues)
2. Create a new issue with detailed description
3. Include use case and expected behavior
4. Attach mockups/screenshots if relevant

---

## ✨ Credits & Acknowledgments

**Built by:** [Tuan Nho](https://github.com/tuannho0802)

**Special Thanks:**
- WordPress community for excellent documentation
- Chart.js for beautiful visualizations
- All contributors and testers

---

## 📌 Version Information

- **Current Version:** 1.0.0
- **Last Updated:** February 2026
- **Compatibility:** WordPress 5.0+, PHP 7.4+

---

## 🔐 Security Notice

This plugin follows WordPress security best practices:

- ✅ All input is sanitized
- ✅ All output is escaped
- ✅ AJAX calls are nonce-protected
- ✅ User capabilities are verified
- ✅ Database queries use prepared statements

**Report Security Issues:** Please don't create public issues for security vulnerabilities. Contact the maintainer directly.

---

## 📊 Stats & Badges

[![Lines of Code](https://img.shields.io/badge/Code-~5000%20lines-blue)]()
[![Files](https://img.shields.io/badge/Files-15%2B-green)]()
[![Documentation](https://img.shields.io/badge/Docs-Comprehensive-brightgreen)]()
[![Mobile Ready](https://img.shields.io/badge/Mobile-Ready-blue)]()

---

<div align="center">

### Made with ❤️ for Education

**Give it a ⭐ if you find it helpful!**

[GitHub](https://github.com/tuannho0802/Az-Attendance-WordPress) • [Report Issue](https://github.com/tuannho0802/Az-Attendance-WordPress/issues) • [Wiki](https://github.com/tuannho0802/Az-Attendance-WordPress/wiki)

---

**Questions?** Check the [INSTRUCTION.MD](INSTRUCTION.MD) or [HUONGDAN.MD](HUONGDAN.MD) files.

</div>