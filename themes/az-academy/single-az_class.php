<?php
if (!defined('ABSPATH')) { exit; }

// Enqueue Media if user can edit
$can_edit = false;
$can_view_full = false;
$post_id = get_the_ID();
$current_user = wp_get_current_user();

// 1. Check Admin / Manager
if (current_user_can('administrator') || current_user_can('manage_options')) {
    $can_edit = true;
    $can_view_full = true;
}
// 2. Check Teacher
elseif (in_array('az_teacher', (array) $current_user->roles)) {
    $teacher_id = get_post_meta($post_id, 'az_teacher_user', true);
    if ($teacher_id == $current_user->ID) {
        $can_edit = true;
        $can_view_full = true;
    }
}
// 3. Check Student
else {
    if ($current_user->ID) {
        // Find Student CPT linked to this User
        $student_cpts = get_posts([
            'post_type' => 'az_student',
            'meta_key' => 'az_user_id',
            'meta_value' => $current_user->ID,
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        if (!empty($student_cpts)) {
            $student_cpt_id = $student_cpts[0];
            $class_students = get_post_meta($post_id, 'az_students', true);
            // Check if Student CPT ID is in Class Student List
            if (is_array($class_students) && in_array($student_cpt_id, $class_students)) {
                $can_view_full = true;
            }
        }
    }
}

if ($can_edit) {
    // acf_form_head(); // Removed to avoid dependency error
    wp_enqueue_media();
}
// Ensure jQuery is loaded for all users (including Students)
wp_enqueue_script('jquery');

get_header();
?>
<style>
    /* Brand Colors */
    :root {
        --az-teal: #15345a;
        /* Updated brand color */
        --az-teal-hover: #1f4a7c;
        --az-bg: #f5f7fa;
    }

    /* Modal Styles */
    .azac-modal {
        display: none; 
        position: fixed; 
        z-index: 9999; 
        left: 0;
        top: 0;
        width: 100%; 
        height: 100%; 
        overflow: auto; 
        background-color: rgba(0,0,0,0.6); 
        backdrop-filter: blur(5px);
    }

    .azac-modal-content {
        background-color: #fefefe;
        margin: 2% auto; 
        padding: 0;
        border: 1px solid #888;
        width: 80%; 
        max-width: 900px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
        height: 90vh;
    }

    .azac-modal-header {
        padding: 15px 20px;
        background: #004E44;
        color: white;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .azac-modal-header h3 {
        margin: 0;
        font-size: 18px;
        color: white;
    }

    .azac-close {
        color: white;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }

    .azac-close:hover,
    .azac-close:focus {
        color: #ddd;
        text-decoration: none;
        cursor: pointer;
    }
     .azac-actions{
        display: flex;
        gap: 10px;
     }

    .azac-modal-body {
        flex: 1;
        padding: 0;
        background: #525659; /* PDF Viewer Background */
        overflow: hidden;
    }

    .azac-modal-body iframe {
        width: 100%;
        height: 100%;
        border: none;
    }

    .azac-modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #ddd;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        background: #fff;
        border-bottom-left-radius: 12px;
        border-bottom-right-radius: 12px;
    }

    /* Layout Structure */
    .azac-layout {
        display: grid;
        grid-template-columns: 3fr 1fr; /* 75% - 25% */
        gap: 30px;
        margin-top: 30px;
    }

    @media (max-width: 991px) {
        .azac-layout {
            grid-template-columns: 1fr;
        }
    }

    /* Main Column */
    .azac-main-col {
        min-width: 0;
    }

    /* Timeline / Tabs (Horizontal) */
    .azac-timeline {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 10px;
        padding-top: 10px;
        margin-bottom: 20px;
        border-bottom: 2px solid #eee;
        scrollbar-width: thin;
    }
    
    .azac-tab {
        flex: 0 0 auto;
        padding: 10px 20px;
        background: #fff;
        border-radius: 20px;
        border: 1px solid #ddd;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        white-space: nowrap;
        color: #555;
    }

    .azac-tab:hover {
        border-color: var(--az-teal);
        color: var(--az-teal);
        transform: translateY(-2px);
    }
</style>

<div class="container">
    <main>
        <?php if (have_posts()):
            while (have_posts()):
                the_post();

                // --- VIEW MODE CHECK ---
                if ($can_view_full):
                    $sessions = class_exists('AzAC_Core_Sessions') ? AzAC_Core_Sessions::get_class_sessions($post_id) : [];
                ?>
                            <article <?php post_class(); ?>>
                    <h1><?php the_title(); ?></h1>
                
                    <div class="azac-layout">
                        <!-- Main Column (75%) -->
                        <div class="azac-main-col">
                            <!-- Horizontal Timeline -->
                            <div class="azac-timeline">
                                <div class="azac-tab active" data-target="overview" data-id="0">Tổng quan</div>
                                <?php foreach ($sessions as $index => $sess): ?>
                                    <div class="azac-tab" data-target="session" data-id="<?php echo esc_attr($sess['id']); ?>"
                                        data-date="<?php echo esc_attr($sess['date']); ?>">
                                        Buổi <?php echo $index + 1; ?>: <?php echo date('d/m', strtotime($sess['date'])); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                
                            <!-- Content Area -->
                            <div class="azac-content-area" id="azac-pdf-content">
                                <div class="azac-content-header">
                                    <h2 id="azac-view-title">Tổng quan lớp học</h2>
                                    <div class="azac-actions">
                                        <?php if ($can_edit): ?>
                                            <button id="azac-edit-btn" class="azac-btn">
                                                <span class="dashicons dashicons-edit"></span> Chỉnh sửa
                                            </button>
                                        <?php endif; ?>
                                        <button id="azac-print-btn" class="azac-btn azac-btn-outline">
                                            <span class="dashicons dashicons-printer"></span> Xuất PDF
                                        </button>
                                    </div>
                                </div>
                
                                <!-- Display View -->
                                <div id="azac-display-view" class="azac-fade-in">
                                    <h3
                                        style="margin-top:0; color:#0b3d3b; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">
                                        Bài giảng chính</h3>
                                    <div id="azac-main-content">
                                        <?php the_content(); ?>
                                    </div>
                
                                    <div id="azac-attachments-section" class="azac-attachments" style="display:none;">
                                        <h3>Tài liệu đính kèm</h3>
                                        <div id="azac-attachments-list" class="azac-att-grid"></div>
                                    </div>
                                </div>

                                <!-- Editor View (Hidden) -->
                                <?php if ($can_edit): ?>
                                <div id="azac-editor-container">
                                    <div class="azac-editor-instruction">
                                        <h4>Soạn thảo nội dung buổi học</h4>
                                        <p>Sử dụng trình soạn thảo bên dưới để nhập nội dung chi tiết cho buổi học này. Bạn có thể chèn hình ảnh, định
                                            dạng văn bản và tải lên tài liệu đính kèm.</p>
                                    </div>

                                        <?php
                                            wp_editor('', 'azac_session_editor', [
                                                'media_buttons' => true,
                                                'textarea_rows' => 25,
                                                'teeny' => false,
                                                'quicktags' => true,
                                                'editor_height' => 500
                                            ]);
                                            ?>
                                        
                                        <div class="azac-editor-meta" style="margin-top: 15px;">
                                            <h4>Tài liệu</h4>
                                            <div id="azac-editor-attachments" class="azac-att-grid"></div>
                                            <button type="button" id="azac-upload-btn" class="azac-btn azac-btn-outline" style="margin-top:10px;">
                                                <span class="dashicons dashicons-upload"></span> Upload Tài liệu
                                            </button>
                                            <button type="button" id="azac-import-pdf-btn" class="azac-btn azac-btn-outline"
                                                style="margin-top:10px; margin-left:10px;">
                                                <span class="dashicons dashicons-media-document"></span> Import từ PDF
                                            </button>
                                            <button type="button" id="azac-clean-format-btn" class="azac-btn azac-btn-outline"
                                                style="margin-top:10px; margin-left:10px; display:none;">
                                                <span class="dashicons dashicons-editor-removeformatting"></span> Clean Format
                                            </button>
                                            <div id="azac-import-loading" style="display:none; margin-top:10px; font-style:italic; color:#0f6d5e;">
                                                <span class="dashicons dashicons-update is-spin"></span> Hệ thống đang chuyển đổi định dạng và xử lý hình ảnh,
                                                vui lòng đợi...
                                            </div>
                                            <input type="hidden" id="azac-att-ids" value="[]">
                                        </div>

                                        <div class="azac-editor-controls" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; text-align: right;">
                                            <button id="azac-cancel-btn" class="azac-btn azac-btn-outline" style="margin-right: 10px;">Hủy</button>
                                            <button id="azac-save-btn" class="azac-btn">Lưu thay đổi</button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                        </div>
                                        </div>
                                                                        
                        <!-- Right Sidebar (25%) -->
                        <div class="azac-info-sidebar">
                            <div class="azac-info-card">
                                <div class="azac-info-header">Thông tin lớp học</div>
                                <div class="azac-info-body">
                                    <div class="azac-info-row">
                                        <span class="azac-info-label">Tên lớp</span>
                                        <span class="azac-info-value" id="azac-class-name">
                                            <?php the_title(); ?>
                                        </span>
                                    </div>
                                    <div class="azac-info-row">
                                        <span class="azac-info-label">Giảng viên</span>
                                        <span class="azac-info-value" id="azac-teacher-name">
                                            <?php
                                            $teacher_name = get_post_meta($post_id, 'az_giang_vien', true);
                                            echo $teacher_name ? esc_html($teacher_name) : 'Chưa cập nhật';
                                            ?>
                                        </span>
                                    </div>
                                    <div class="azac-info-row">
                                        <span class="azac-info-label">Sĩ số</span>
                                        <span class="azac-info-value">
                                            <?php
                                            $students = get_post_meta($post_id, 'az_students', true);
                                            echo is_array($students) ? count($students) : 0;
                                            ?> Học viên
                                        </span>
                                    </div>
                        
                                    <!-- Progress Bar -->
                                    <?php
                                    $actual_sessions = count($sessions); // Numerator: Actual created sessions
                                    $plan_sessions = (int) get_post_meta($post_id, 'az_tong_so_buoi', true); // Denominator: Planned sessions
                        
                                    // Safety: If plan is 0 or less, default to actual to avoid division by zero or weirdness
                                    if ($plan_sessions <= 0) {
                                        $plan_sessions = $actual_sessions > 0 ? $actual_sessions : 1;
                                    }

                                    $percent = ($actual_sessions / $plan_sessions) * 100;
                                    if ($percent > 100)
                                        $percent = 100;
                                    ?>
                                    <div class="azac-info-row" style="border:none;">
                                        <span class="azac-info-label">Tiến độ (
                                            <?php echo $actual_sessions; ?>/
                                            <?php echo $plan_sessions; ?> buổi)
                                        </span>
                                        <div class="azac-progress-wrapper">
                                            <div class="azac-progress-bar">
                                                <div class="azac-progress-fill" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <?php
                    // --- PREVIEW MODE ---
                else:
                    ?>
                    <article <?php post_class(); ?>>
                        <div class="azac-preview-container">
                            <h1 class="azac-preview-title"><?php the_title(); ?></h1>
                        <div class="azac-preview-card">
                            <div class="azac-preview-icon">
                                <span class="dashicons dashicons-lock"></span>
                            </div>
                            <h2 class="azac-preview-heading">Nội dung bị giới hạn</h2>
                            <p class="azac-preview-message">
                                Bạn không có quyền truy cập vào nội dung đầy đủ của lớp học này.<br>
                                Nội dung này chỉ dành cho <strong>Giảng viên</strong> và <strong>Học viên</strong> của lớp.
                            </p>
                            
                            <div class="azac-class-info-preview">
                                <p><strong>Giảng viên:</strong> <?php echo esc_html(get_post_meta($post_id, 'az_giang_vien', true) ?: 'Chưa cập nhật'); ?></p>
                                <p><strong>Tổng số buổi:</strong> <?php echo esc_html(get_post_meta($post_id, 'az_tong_so_buoi', true)); ?></p>
                                <p><strong>Mô tả:</strong> <?php echo get_the_excerpt(); ?></p>
                            </div>

                            <div class="azac-preview-actions">
                                <a href="<?php echo home_url('/'); ?>" class="azac-btn azac-btn-outline">Quay lại Trang chủ</a>
    <?php if (!is_user_logged_in()): ?>
        <a href="<?php echo wp_login_url(get_permalink()); ?>" class="azac-btn">Đăng nhập</a>
    <?php endif; ?>
</div>
</div>
</div>
</article>
                <?php endif; // End View Mode Check ?>
                
                <?php endwhile;
        else: ?>
                <p>Không có nội dung.</p>
                <?php endif; ?>
    </main>
</div>

<!-- PDF Preview Modal (Kept outside loop) -->
<div id="azac-pdf-modal" class="azac-modal">
    <div class="azac-modal-content">
        <div class="azac-modal-header">
            <h3>Xem trước tài liệu</h3>
            <span class="azac-close">&times;</span>
        </div>
        <div class="azac-modal-body">
            <iframe id="azac-pdf-frame" src=""></iframe>
        </div>
        <div class="azac-modal-footer">
            <button id="azac-close-modal-btn" class="azac-btn azac-btn-outline">Đóng</button>
        </div>
    </div>
</div>

<?php
get_footer();
?>
