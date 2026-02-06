# HƯỚNG DẪN BẢO TRÌ & SỬA CHỮA - AZ ACADEMY ATTENDANCE

**Phiên bản:** 1.0  
**Ngôn ngữ:** Tiếng Việt + English (dual)  
**Dành cho:** Developers, Maintainers, AI Agents  

---

## MỤC LỤC (TABLE OF CONTENTS)

1. [Hướng Dẫn Cài Đặt (Installation)](#1-hướng-dẫn-cài-đặt)
2. [Cấu Trúc Thư Mục Chi Tiết (Directory Map)](#2-cấu-trúc-thư-mục-chi-tiết)
3. [Danh Sách File & Chức Năng (File Inventory)](#3-danh-sách-file--chức-năng)
4. [Sửa Chữa Lỗi Phổ Biến (Troubleshooting)](#4-sửa-chữa-lỗi-phổ-biến)
5. [Quy Trình Bảo Trì (Maintenance Workflows)](#5-quy-trình-bảo-trì)
6. [Ghi Chép Thay Đổi (Change Log Template)](#6-ghi-chép-thay-đổi)

---

## 1. HƯỚNG DẪN CÀI ĐẶT

### Yêu Cầu Hệ Thống (Requirements)

- **WordPress:** 5.0 trở lên
- **PHP:** 7.4 trở lên (khuyến khích 8.0+)
- **Database:** MySQL 5.7+ hoặc MariaDB 10.3+
- **Thẻ nhớ:** Ít nhất 256MB cho WordPress
- **Quyền file:** wp-content/plugins và wp-content/themes phải ghi được

### Cài Đặt Trên LocalWP (Phát Triển)

#### Bước 1: Tạo Local Site

```bash
1. Mở LocalWP (Local by Flywheel)
2. Click "Create a new site"
3. Đặt tên site: "az-attendance"
4. Chọn WordPress version mới nhất
5. Username: admin
6. Password: [tạo mật khẩu mạnh]
7. Click "Create Site"
```

**Kết quả:** LocalWP tạo folder tại:
```
C:\Users\[YourUser]\Local Sites\az-attendance\app\public\
```

#### Bước 2: Clone Repository

```bash
# Mở Git Bash / Terminal tại thư mục public
cd C:\Users\[YourUser]\Local Sites\az-attendance\app\public

# Clone repository
git clone https://github.com/tuannho0802/Az-Attendance-WordPress.git temp

# Di chuyển plugin và theme
mv temp/plugins/az-academy-core wp-content/plugins/
mv temp/themes/az-academy wp-content/themes/
rm -rf temp
```

#### Bước 3: Kích Hoạt Plugin & Theme

```
1. Truy cập WordPress Admin (http://az-attendance.local/wp-admin)
2. Đăng nhập với tài khoản admin
3. Appearance → Themes → Kích hoạt "Az Academy"
4. Plugins → Tìm "Az Academy Core" → Click "Activate"
```

#### Bước 4: Kiểm Tra Cài Đặt

```
1. Dashboard → Xem "Manage Attendance" menu xuất hiện
2. Kiểm tra database:
   - phpMyAdmin: wp_az_attendance table tồn tại?
   - Nếu không, deactivate & activate plugin lại
3. Tạo class test:
   - Manage Attendance → "Create Class"
   - Nhập "Test Class" → Save
   - Xác nhận class xuất hiện trong list
```

**Nếu gặp lỗi:** Xem phần [4. Sửa Chữa Lỗi Phổ Biến](#4-sửa-chữa-lỗi-phổ-biến)

---

## 2. CẤU TRÚC THƯ MỤC CHI TIẾT

### Cây Thư Mục Đầy Đủ

```
Az-Attendance-WordPress/
├── plugins/
│   └── az-academy-core/
│       ├── az-academy-core.php              [ENTRY POINT]
│       ├── includes/
│       │   ├── class-azac-core-helper.php   [Helper functions]
│       │   ├── class-azac-database.php      [DB operations]
│       │   ├── class-azac-admin-pages.php   [Admin UI rendering]
│       │   ├── class-azac-role-manager.php  [Role/permission setup]
│       │   └── class-azac-ajax-handler.php  [AJAX endpoints]
│       ├── admin/
│       │   ├── css/
│       │   │   ├── admin-style.css          [Global admin styling]
│       │   │   ├── attendance.css           [Class dashboard styles]
│       │   │   ├── attendance-list.css      [Attendance list styles]
│       │   │   └── azac-teacher-view.css    [Teacher-specific styles]
│       │   └── js/
│       │       ├── attendance.js            [Attendance interaction logic]
│       │       ├── attendance-list.js       [List page interactions]
│       │       └── class-edit.js            [Student addition logic]
│       └── templates/
│           ├── admin-header.php             [Admin page header]
│           ├── class-card-template.php      [Class card component]
│           ├── attendance-table-template.php [Attendance table component]
│           └── badge-template.php           [Status badge component]
│
├── themes/
│   └── az-academy/
│       ├── style.css                        [Frontend styling]
│       ├── index.php                        [Homepage]
│       ├── single-az_class.php              [Single class page]
│       ├── header.php                       [Header template]
│       └── footer.php                       [Footer template]
│
└── README.md                                [Project overview]
```

### Giải Thích Thư Mục

| Thư Mục | Mục Đích | AI Agent cần biết gì |
|---------|---------|----------------------|
| `plugins/az-academy-core/` | Hạt nhân plugin (admin-first) | Tất cả logic chính ở đây |
| `includes/` | Business logic (DB, roles, pages) | Không được phá: kỹ thuật cao |
| `admin/css/` | Styling toàn bộ giao diện admin | Phải giữ `.azac-` namespace |
| `admin/js/` | Tương tác (AJAX, toggles, forms) | Phải dùng fetch, nonce verification |
| `templates/` | Reusable HTML partials | Có thể mở rộng dễ dàng |
| `themes/az-academy/` | Frontend (không bắt buộc) | Có thể custom hoặc ignore |

---

## 3. DANH SÁCH FILE & CHỨC NĂNG

### 3.1 Plugin Core Files

#### ✅ `az-academy-core.php` (Entry Point)
**Dòng mã:** ~200-300 dòng  
**Chức năng chính:**
```
[✓] Plugin header metadata
[✓] Custom Post Type registration (az_class, az_student)
[✓] Database table creation on activation
[✓] CSS/JS enqueue
[✓] Admin menu registration
[✓] Role initialization
```

**Khi cần sửa:**
- Thêm CPT mới? → Thêm `register_post_type()` call
- Thêm script/CSS? → Thêm vào `azac_enqueue_admin_assets()`
- Thêm admin menu? → Thêm `add_menu_page()` call

**Cảnh báo:** Không được xóa activation hook, sẽ mất bảng dữ liệu!

---

#### ✅ `includes/class-azac-core-helper.php` (Helper Functions)
**Dòng mã:** ~400-500 dòng  
**Chức năng chính:**
```
azac_get_class_by_id($class_id)           → Lấy thông tin lớp
azac_get_students_in_class($class_id)     → Danh sách học viên
azac_format_date($date, $format)          → Format ngày
azac_get_attendance_by_date($class_id)    → Lấy điểm danh
azac_get_badge_class($status)             → CSS badge
azac_check_user_access($user_id, $class)  → Kiểm tra quyền
azac_paginate(...)                        → Phân trang
azac_sanitize_input($input, $type)        → Vệ sinh input
```

**Khi cần sửa:**
- Thêm helper mới? → Thêm function vào file này
- Sửa logic date? → Sửa `azac_format_date()`
- Thêm loại badge? → Sửa `azac_get_badge_class()`

**Cảnh báo:** Thay đổi signature function sẽ break tất cả nơi dùng!

---

#### ✅ `includes/class-azac-database.php` (DB Operations)
**Dòng mã:** ~300-400 dòng  
**Chức năng chính:**
```
azac_create_attendance_table()             → Tạo bảng on activation
azac_insert_attendance(...)                → Lưu/cập nhật điểm danh
azac_get_attendance_records(...)           → Lấy bản ghi
azac_delete_attendance($id)                → Xóa bản ghi
azac_get_attendance_stats(...)             → Thống kê
```

**Khi cần sửa:**
- Thêm cột mới? → Cập nhật `CREATE TABLE` statement
- Thay đổi schema? → Increment version, trigger re-activation
- Thêm query? → Dùng wpdb global: `global $wpdb; $wpdb->query(...)`

**Cảnh báo:** Sai SQL sẽ crash toàn bộ hệ thống!

**Ví dụ an toàn:**
```php
global $wpdb;
$result = $wpdb->insert(
    $wpdb->prefix . 'az_attendance',
    [
        'class_id' => $class_id,
        'student_id' => $student_id,
        'session_date' => $date,
        'status' => $status,
    ],
    ['%d', '%d', '%s', '%d']
);
```

---

#### ✅ `includes/class-azac-admin-pages.php` (Admin UI)
**Dòng mã:** ~800-1000 dòng  
**Chức năng chính:**
```
azac_attendance_manage_page()              → Trang manage (class cards)
azac_class_dashboard_page()                → Trang điểm danh (tabs)
azac_render_class_card($class)             → Thành phần class card
azac_render_attendance_table(...)          → Bảng điểm danh
azac_render_inline_create_class_form()     → Form tạo lớp
azac_render_student_addition_form()        → Form thêm học viên
```

**Khi cần sửa:**
- Thêm cột bảng? → Thêm `<td data-label="...">` 
- Thay đổi card layout? → Sửa HTML structure
- Thêm form field? → Thêm `<input>` + JS validation

**CRITICAL:** Mọi `<td>` phải có `data-label` hoặc mobile vỡ!

**Ví dụ sai:**
```php
// ❌ SẼ VỠ TRÊN MOBILE
echo '<td>Student Name: John</td>';
```

**Ví dụ đúng:**
```php
// ✅ ĐÚNG - Mobile sẽ hiển thị label
echo '<td data-label="Student Name">John</td>';
```

---

#### ✅ `includes/class-azac-role-manager.php` (Role Management)
**Dòng mã:** ~250-350 dòng  
**Chức năng chính:**
```
azac_register_custom_roles()                → Tạo az_teacher, az_student
azac_check_class_access($user_id, $id)     → Kiểm tra quyền truy cập
azac_migrate_wp_roles_to_custom()           → Migrate role on activation
azac_get_user_classes($user_id)             → Danh sách lớp của user
```

**Khi cần sửa:**
- Thêm role mới? → Thêm `add_role()` call
- Thay đổi capability? → Sửa array `$caps`
- Thay đổi access logic? → Sửa `check_class_access()`

**Cảnh báo:** Sai permission logic → user mất access!

---

#### ✅ `includes/class-azac-ajax-handler.php` (AJAX Endpoints)
**Dòng mã:** ~400-500 dòng  
**Chức năng chính:**
```
wp_ajax_azac_save_attendance                → Lưu điểm danh (toggle)
wp_ajax_azac_create_class                   → Tạo lớp (AJAX)
wp_ajax_azac_add_student_to_class           → Thêm học viên
wp_ajax_azac_delete_attendance              → Xóa bản ghi
```

**Khi cần sửa:**
- Thêm endpoint mới? → Thêm `add_action('wp_ajax_azac_...')`
- Sửa validation? → Sửa `check_ajax_referer()` + `sanitize_*()`
- Thay đổi response? → Sửa `wp_send_json_*()`

**CRITICAL PATTERN:**
```php
add_action('wp_ajax_azac_my_action', function() {
    // 1. Verify nonce (SECURITY)
    check_ajax_referer('azac_nonce', 'nonce');
    
    // 2. Check capability
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Forbidden']);
    }
    
    // 3. Sanitize input
    $input = sanitize_text_field($_POST['input']);
    
    // 4. Do work
    $result = do_something($input);
    
    // 5. Send response
    wp_send_json_success(['data' => $result]);
});
```

---

### 3.2 Admin CSS Files

#### ✅ `admin/css/admin-style.css` (Global Styles)
**Dòng mã:** ~1000-1500 dòng  
**Classes chính:**
```
.azac-admin-teal              → Container chính (specificity override)
.azac-card                    → Card component
.azac-table-responsive        → Wrapper cho bảng
.azac-switch                  → Toggle switch (attendance)
.azac-badge, .azac-badge-*   → Status badges (success, error, warning)
.azac-btn, .azac-btn-primary  → Button styles
.azac-input-group             → Form input wrapper
.azac-section                 → Section divider
```

**Khi cần sửa:**
- Thêm class mới? → Thêm rule mới, bắt đầu với `.azac-`
- Thay đổi màu? → Sửa CSS variable hoặc color value
- Fix mobile? → Thêm `@media (max-width: 782px)` rule

**Lưu ý Namespace:**
```css
/* ✅ CORRECT - Specific, won't conflict */
.azac-admin-teal .azac-card { }

/* ❌ WRONG - Too generic, may conflict */
.card { }
.admin { }
```

---

#### ✅ `admin/css/attendance.css` (Dashboard Styles)
**Dòng mã:** ~500-700 dòng  
**Chức năng chính:**
```
[✓] Tab styling (active/inactive states)
[✓] Attendance table layout
[✓] Summary cards styling
[✓] Chart container
[✓] Mobile card layout (display: block)
```

**Khi cần sửa:**
- Thêm tab? → Thêm `.azac-tab-content` + CSS
- Fix card layout? → Kiểm tra mobile breakpoint
- Thay đổi summary? → Sửa `.azac-summary-card`

---

#### ✅ `admin/css/attendance-list.css` (List Page Styles)
**Dòng mã:** ~300-400 dòng  
**Chức năng chính:**
```
[✓] Class cards grid layout
[✓] Card spacing and borders
[✓] Responsive grid (desktop vs mobile)
[✓] Inline form styling
```

---

#### ✅ `admin/css/azac-teacher-view.css` (Teacher Role Styles)
**Dòng mã:** ~200-300 dòng  
**Chức năng chính:**
```
[✓] Simplified toolbar (teacher-only)
[✓] Hidden admin elements
[✓] Teacher-specific colors/layout
```

**Khi cần sửa:**
- Ẩn element từ teacher? → Thêm `display: none` với specificity đủ cao

---

### 3.3 Admin JavaScript Files

#### ✅ `admin/js/attendance.js` (Main Interaction Logic)
**Dòng mã:** ~600-800 dòng  
**Functions chính:**
```javascript
toggleAttendance(studentId, type, status)     // Toggle switch
saveAttendance(classId, sessionDate, type)    // Save all (AJAX)
loadAttendanceState(classId, date, type)      // Load on page load
updateSummaryCards()                           // Recalculate stats
initializeChart()                              // Setup Chart.js
```

**Khi cần sửa:**
- Thêm field mới? → Thêm vào `attendanceData` object
- Thay đổi AJAX call? → Sửa `fetch()` + URL params
- Thay đổi chart? → Sửa Chart.js config

**Ví dụ thêm note field:**
```javascript
const updateNote = (studentId, type, note) => {
    attendanceData[studentId][type].note = sanitizeHtml(note);
    // Save to server...
};
```

---

#### ✅ `admin/js/attendance-list.js` (List Page Logic)
**Dòng mã:** ~300-400 dòng  
**Functions chính:**
```javascript
openClassDashboard(classId)      // Navigate to class
deleteClass(classId)              // Delete + confirm
initializeClassCards()            // Event listeners
filterClasses(searchTerm)          // Search/filter
```

---

#### ✅ `admin/js/class-edit.js` (Student Addition Logic)
**Dòng mã:** ~250-350 dòng  
**Functions chính:**
```javascript
addStudentRow()                   // Add input row
removeStudentRow(rowIndex)        // Remove row
validateStudentForm()             // Validation
submitStudentData()               // AJAX save
```

**Khi cần sửa:**
- Thêm field student? → Thêm `<input>` row + validation
- Thay đổi validation? → Sửa `validateStudentForm()`

---

### 3.4 Templates

#### ✅ `templates/admin-header.php`
**Chức năng:** Render header cho admin pages  
**Variables:**
```php
$title = 'Page Title';
$breadcrumbs = ['Home', 'Manage', 'Current Page'];
$actions = ['Create', 'Export', ...];
```

---

#### ✅ `templates/class-card-template.php`
**Chức năng:** Render single class card  
**Variables:**
```php
$class = [
    'ID' => 123,
    'post_title' => 'Class Name',
    'teacher_name' => 'John Teacher',
    'total_sessions' => 10,
    'student_count' => 25,
];
```

---

#### ✅ `templates/attendance-table-template.php`
**Chức năng:** Render attendance table (editable or view-only)  
**Variables:**
```php
$students = [...];
$attendance_records = [...];
$editable = true;
$session_date = '2024-02-06';
```

---

#### ✅ `templates/badge-template.php`
**Chức năng:** Render status badge  
**Variables:**
```php
$status = 'success'; // success | error | warning | info
$label = 'Present';
```

---

### 3.5 Theme Files

#### ✅ `themes/az-academy/style.css`
**Dòng mã:** ~400-600 dòng  
**Chức năng chính:**
```css
[✓] Theme header metadata
[✓] Primary color scheme
[✓] Typography (fonts, sizes)
[✓] Layout (header, footer, body)
[✓] Card and button styles
[✓] Responsive breakpoints
```

---

#### ✅ `themes/az-academy/index.php`
**Chức năng:** Homepage  
**Displays:**
```
[✓] Site header (via header.php)
[✓] Loop: list of az_class posts as cards
[✓] Each card: title, teacher, sessions, student count
[✓] Links to single-az_class.php
[✓] Site footer (via footer.php)
```

**Khi cần sửa:**
- Thêm filter? → Thêm `WP_Query` args
- Thay đổi card layout? → Sửa HTML structure
- Thêm pagination? → Thêm `paginate_links()`

---

#### ✅ `themes/az-academy/single-az_class.php`
**Chức năng:** Single class page  
**Displays:**
```
[✓] Site header
[✓] Class title, teacher, description
[✓] Total sessions, student count
[✓] Student list (if visible)
[✓] "Edit in Admin" button (if authorized)
[✓] Site footer
```

---

#### ✅ `themes/az-academy/header.php`
**Chức năng:** Header template  
**Includes:**
```html
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- wp_head() hooks for theme CSS, plugin CSS, etc -->
</head>
<body>
    <!-- Navigation menu -->
    <!-- Admin bar (if logged in) -->
```

---

#### ✅ `themes/az-academy/footer.php`
**Chức năng:** Footer template  
**Outputs:**
```html
    <!-- wp_footer() hooks for JS -->
    <!-- Copyright, credits -->
</body>
</html>
```

---

## 4. SỬA CHỮA LỖI PHỔ BIẾN

### Lỗi #1: Plugin không kích hoạt được

**Triệu chứng:**
```
"Parse error in az-academy-core.php line 50"
hoặc
"Fatal error: Call to undefined function..."
```

**Nguyên nhân:**
- File PHP có syntax error
- Thiếu file include
- PHP version quá cũ

**Cách sửa:**

1. **Kiểm tra syntax:**
```bash
php -l plugins/az-academy-core/az-academy-core.php
```

2. **Kiểm tra logs:**
```bash
tail -50 wp-content/debug.log
```

3. **Kiểm tra file includes:**
```php
// Trong az-academy-core.php, kiểm tra include_once
include_once plugin_dir_path(__FILE__) . 'includes/class-azac-database.php';
// File này tồn tại không?
```

---

### Lỗi #2: Bảng dữ liệu không được tạo

**Triệu chứng:**
```
"Table 'wp_az_attendance' doesn't exist"
khi lưu điểm danh
```

**Nguyên nhân:**
- Activation hook không chạy
- Quyền database không đủ
- Upgrade từ version cũ chưa migrate

**Cách sửa:**

1. **Re-activate plugin:**
```
Plugins → Az Academy Core → Deactivate
Plugins → Az Academy Core → Activate
```

2. **Kiểm tra bảng tồn tại:**
```bash
# phpMyAdmin hoặc WP-CLI
wp db tables | grep az_attendance
```

3. **Tạo bảng thủ công (nếu cần):**
```sql
-- Chạy trong phpMyAdmin
CREATE TABLE `wp_az_attendance` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `class_id` bigint unsigned NOT NULL,
    `student_id` bigint unsigned NOT NULL,
    `session_date` date NOT NULL,
    `attendance_type` varchar(20) NOT NULL DEFAULT 'check-in',
    `status` tinyint(1) NOT NULL DEFAULT 0,
    `note` text,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `class_id` (`class_id`),
    KEY `student_id` (`student_id`),
    KEY `session_date` (`session_date`),
    UNIQUE KEY `unique_attendance` (`class_id`, `student_id`, `session_date`, `attendance_type`)
);
```

---

### Lỗi #3: Mobile view vỡ (bảng không có label)

**Triệu chứng:**
```
Trên điện thoại (<782px): cột bảng không có nhãn,
chỉ thấy dữ liệu
```

**Nguyên nhân:**
- `<td>` không có `data-label="..."`
- CSS mobile breakpoint sai

**Cách sửa:**

1. **Kiểm tra tất cả `<td>` có label:**
```php
// ❌ SAI
echo '<td>John Doe</td>';

// ✅ ĐÚNG
echo '<td data-label="Student Name">John Doe</td>';
```

2. **Kiểm tra CSS:**
```css
/* admin-style.css phải có */
@media (max-width: 782px) {
    table { display: block; }
    td {
        display: flex;
        justify-content: space-between;
    }
    td::before {
        content: attr(data-label);
        font-weight: bold;
    }
}
```

3. **Test trên DevTools:**
```
F12 → DevTools → Ctrl+Shift+M (toggle mobile)
Drag width to < 782px
Kiểm tra mỗi <td> có label không
```

---

### Lỗi #4: Attendance không lưu được (AJAX fail)

**Triệu chứng:**
```
Click toggle attendance → không có gì xảy ra
F12 Console: XHR request failed hoặc 400/403
```

**Nguyên nhân:**
- Nonce sai hoặc hết hạn
- User không có quyền
- AJAX URL sai
- Backend error

**Cách sửa:**

1. **Kiểm tra nonce trong HTML:**
```html
<!-- Mở Page Source (Ctrl+U) tìm -->
<input type="hidden" name="nonce" value="abc123...">
```

2. **Kiểm tra AJAX log:**
```bash
# F12 → Network tab → tìm admin-ajax.php requests
# Kiểm tra:
# - Status: 200 OK?
# - Response: { success: true } ?
# - Parameters: action, nonce, student_id, status ?
```

3. **Kiểm tra PHP error log:**
```bash
tail wp-content/debug.log | grep "azac_save_attendance"
```

4. **Fix nonce (client side):**
```javascript
// attendance.js
const nonce = document.querySelector('[name="nonce"]').value;
if (!nonce) {
    console.error('Nonce not found in page!');
    return;
}

fetch(ajaxUrl, {
    method: 'POST',
    body: new FormData(document.querySelector('form'))
    // hoặc
    // body: `action=azac_save_attendance&nonce=${nonce}&...`
});
```

5. **Fix nonce (server side):**
```php
// Trong class-azac-ajax-handler.php
add_action('wp_ajax_azac_save_attendance', function() {
    // Check nonce FIRST
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'azac_nonce')) {
        wp_send_json_error(['message' => 'Nonce verification failed']);
    }
    
    // Then check capability
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }
    
    // Then process...
});
```

---

### Lỗi #5: User không thấy class (Permission denied)

**Triệu chứng:**
```
Teacher account: "Manage Attendance" menu không xuất hiện
hoặc
Học viên: chỉ thấy một số class
```

**Nguyên nhân:**
- User không được assign vào lớp
- Role sai
- Access check logic sai

**Cách sửa:**

1. **Kiểm tra user role:**
```php
// Chạy trong wp-cli hoặc admin
$user = get_user_by('login', 'teacher_username');
$user_roles = $user->roles; // Should contain 'az_teacher'
```

2. **Kiểm tra class assignment:**
```php
// Check postmeta
$class_id = 123;
$teacher_id = 456;

// Lấy teacher assigned
$assigned_teacher = get_post_meta($class_id, 'az_teacher_user', true);
echo "Assigned: $assigned_teacher, Looking for: $teacher_id";
```

3. **Manually assign (fix):**
```php
// Trong WordPress admin hoặc WP-CLI
update_post_meta($class_id, 'az_teacher_user', $teacher_id);
```

4. **Kiểm tra access logic:**
```php
// class-azac-role-manager.php
function azac_check_class_access($user_id, $class_id) {
    if (current_user_can('manage_options')) return true;
    
    $assigned_teacher = get_post_meta($class_id, 'az_teacher_user', true);
    return ($user_id == $assigned_teacher);
}
```

---

### Lỗi #6: CSS không load (giao diện xấu)

**Triệu chứng:**
```
Admin page không có styling (chỉ text trắng)
```

**Nguyên nhân:**
- CSS file sai path
- Enqueue sai
- Cache plugin blocking

**Cách sửa:**

1. **Kiểm tra enqueue:**
```php
// az-academy-core.php
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style(
        'azac-admin-style',
        plugin_dir_url(__FILE__) . 'admin/css/admin-style.css',
        [],
        filemtime(plugin_dir_path(__FILE__) . 'admin/css/admin-style.css')
    );
});
```

2. **Kiểm tra file tồn tại:**
```bash
ls -la plugins/az-academy-core/admin/css/
# admin-style.css có tồn tại không?
```

3. **Clear cache:**
```bash
# Nếu dùng caching plugin (WP Super Cache, W3 Total Cache)
# Xóa cache
# Hoặc tạm disable cache plugin
```

4. **Kiểm tra dev tools:**
```
F12 → Network → CSS files
- Status 200?
- Nội dung có CSS không (không phải HTML error)?
```

---

### Lỗi #7: JavaScript lỗi (toggle không động)

**Triệu chứng:**
```
F12 Console: Uncaught ReferenceError: ... is not defined
Toggle button click → không có gì xảy ra
```

**Nguyên nhân:**
- File JS không load
- Global variable sai
- Function không defined

**Cách sửa:**

1. **Kiểm tra JS file load:**
```bash
# F12 → Network → Filter: JS
# Tìm attendance.js → Status 200?
```

2. **Kiểm trap global variables:**
```javascript
// Trong attendance.js, phải define
const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
const classDashboardNonce = '<?php echo wp_create_nonce('azac_nonce'); ?>';
```

3. **Kiểm tra PHP inline script:**
```php
// Trong class-azac-admin-pages.php
?>
<script>
    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var nonce = '<?php echo wp_create_nonce('azac_nonce'); ?>';
</script>
<?php
wp_enqueue_script('azac-attendance', ...);
```

---

## 5. QUY TRÌNH BẢO TRÌ

### 5.1 Hàng Ngày (Daily)

- [ ] Kiểm tra plugin error log: `wp-content/debug.log`
- [ ] Verify attendance data persisting correctly
- [ ] Check user reports of access issues

### 5.2 Hàng Tuần (Weekly)

- [ ] Database backup
- [ ] Review attendance records for inconsistencies
- [ ] Test AJAX endpoints manually
- [ ] Verify mobile responsiveness (< 782px)

### 5.3 Hàng Tháng (Monthly)

- [ ] Update WordPress to latest version
- [ ] Review and update plugin version number
- [ ] Audit user roles and permissions
- [ ] Performance review (slow queries?)
- [ ] Security audit (sanitization, escaping, nonce)

### 5.4 Khi Deploy Thay Đổi

```
1. [GIT] Commit changes
   git add .
   git commit -m "feat: add new attendance field"

2. [TEST] Test on staging/local
   - Activate plugin
   - Create test class and student
   - Test attendance toggle
   - Test on mobile (< 782px)
   - Check error log

3. [BACKUP] Backup database and files
   - wp-content/uploads
   - {prefix}_az_attendance table

4. [DEPLOY] Update production
   - Copy files to server
   - Run activation hook if needed (deactivate + activate)
   - Verify plugin active

5. [VERIFY] Smoke test on production
   - Login as admin
   - Check "Manage Attendance" exists
   - Create test class
   - Toggle attendance
   - Check no errors in log
```

---

## 6. GHI CHÉP THAY ĐỔI (CHANGELOG TEMPLATE)

### Version 1.1.0 (Date: YYYY-MM-DD)

#### ✨ Features
- [x] Add note field to attendance record
- [x] Export attendance to CSV

#### 🐛 Fixes
- [x] Fix mobile table label display
- [x] Fix AJAX nonce validation on slow networks

#### ⚠️ Breaking Changes
- None

#### 📝 Migration Steps
```sql
-- Add new column
ALTER TABLE wp_az_attendance ADD COLUMN `note` TEXT AFTER `status`;
```

#### 🧪 Testing Checklist
- [ ] Create class, add students, toggle attendance
- [ ] Check attendance saves on toggle
- [ ] Test on mobile (< 782px)
- [ ] Verify teacher can't see other teachers' classes
- [ ] Verify student can't edit others' attendance
- [ ] Export CSV and verify format
- [ ] Check debug.log has no errors

---

## QUICK REFERENCE

### File Search Guide

| Tôi muốn... | Tìm file |
|-------------|----------|
| Thêm input form field | `class-azac-admin-pages.php` |
| Sửa AJAX endpoint | `class-azac-ajax-handler.php` |
| Thêm CSS class | `admin/css/admin-style.css` |
| Thêm helper function | `class-azac-core-helper.php` |
| Thêm database operation | `class-azac-database.php` |
| Thêm user permission | `class-azac-role-manager.php` |
| Sửa JavaScript interaction | `admin/js/attendance.js` |
| Thay đổi database schema | `az-academy-core.php` (activation hook) |

### Debugging Commands

```bash
# Check PHP syntax
php -l plugins/az-academy-core/includes/*.php

# Check logs
tail -100 wp-content/debug.log

# Check database table
wp db query "SHOW TABLES LIKE 'wp_az%'"

# Check user roles
wp user list --field=ID,user_login | xargs -I {} wp user get {} --field=roles

# Create user for testing
wp user create teacher_test teacher@test.local --role=az_teacher --user_pass=password
```

---

**Last Updated:** February 2026  
**For Questions:** See INSTRUCTION.MD for technical details
