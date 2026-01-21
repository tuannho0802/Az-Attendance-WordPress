<?php
if (!defined('ABSPATH')) { exit; }

// Enqueue Media if user can edit
$can_edit = false;
$post_id = get_the_ID();
$current_user = wp_get_current_user();
if (current_user_can('administrator')) {
    $can_edit = true;
} elseif (in_array('az_teacher', (array) $current_user->roles)) {
    $teacher_id = get_post_meta($post_id, 'az_teacher_user', true);
    if ($teacher_id == $current_user->ID) {
        $can_edit = true;
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

    .azac-tab.active {
        background: var(--az-teal);
        color: white;
        border-color: var(--az-teal);
        box-shadow: 0 4px 6px rgba(21, 52, 90, 0.2);
    }

    /* Content Box */
    .azac-content-area {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        padding: 40px;
        min-height: 600px; /* Fixed min-height */
        transition: opacity 0.3s ease;
    }

    .azac-fade-in {
        animation: fadeIn 0.4s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Right Sidebar (Class Info) */
    .azac-info-sidebar {
        position: sticky;
        top: 20px;
    }

    .azac-info-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .azac-info-header {
        background: #004E44; /* Teal for Header */
        color: white;
        padding: 15px 20px;
        font-size: 16px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .azac-info-body {
        padding: 20px;
    }

    .azac-info-row {
        margin-bottom: 15px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    .azac-info-row:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }

    .azac-info-label {
        font-size: 12px;
        color: #888;
        text-transform: uppercase;
        margin-bottom: 4px;
        display: block;
    }

    .azac-info-value {
        font-size: 15px;
        font-weight: 600;
        color: #333;
    }

    /* Progress Bar */
    .azac-progress-wrapper {
        margin-top: 10px;
    }
    .azac-progress-bar {
        height: 8px;
        background: #eee;
        border-radius: 4px;
        overflow: hidden;
    }
    .azac-progress-fill {
        height: 100%;
        background: var(--az-teal);
        border-radius: 4px;
    }

    /* Hide Sidebar styling from previous version */
    .azac-sidebar { display: none; }

    .azac-content-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }

    .azac-btn {
        background-color: var(--az-teal);
        color: white;
        padding: 10px 20px;
        /* Bigger button */
        border-radius: 6px;
        text-decoration: none;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 15px;
        /* Bigger text */
        transition: background 0.2s;
        font-weight: 500;
    }

    .azac-btn:hover {
        background-color: var(--az-teal-hover);
        color: white;
    }

    .azac-btn-outline {
        background: transparent;
        border: 1px solid var(--az-teal);
        color: var(--az-teal);
    }

    .azac-btn-outline:hover {
        background: var(--az-teal);
        color: white;
    }

    /* Editor Specifics */
    #wp-azac_session_editor-wrap {
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
    }

    #wp-azac_session_editor-editor-container {
        border-radius: 0 0 4px 4px;
    }

    /* Make editor taller */
    .mce-edit-area iframe {
        height: 500px !important;
        /* Force taller height */
        min-height: 400px;
    }

    /* Editor Container */
    #azac-editor-container {
        display: none;
        margin-top: 20px;
        background: #fff;
    }

    .azac-editor-instruction {
        background: #eef3f9;
        border-left: 4px solid var(--az-teal);
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }

    .azac-editor-instruction h4 {
        margin: 0 0 5px 0;
        color: var(--az-teal);
    }

    .azac-editor-instruction p {
        margin: 0;
        font-size: 14px;
        color: #555;
    }

    /* Attachments */
    .azac-attachments {
        margin-top: 30px;
        border-top: 1px solid #eee;
        padding-top: 20px;
    }

    .azac-att-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .azac-att-card {
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f9f9f9;
        transition: transform 0.2s;
    }

    .azac-att-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .azac-att-icon {
        font-size: 24px;
        width: 32px;
        text-align: center;
    }

    .azac-att-icon.pdf {
        color: #e74c3c;
    }

    .azac-att-icon.word {
        color: #3498db;
    }

    .azac-att-icon.image {
        color: #2ecc71;
    }

    .azac-att-info {
        flex: 1;
        overflow: hidden;
    }

    .azac-att-title {
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
    }

    .azac-att-actions {
        font-size: 12px;
    }

    /* Editor Container */
    #azac-editor-container {
        display: none;
        margin-top: 20px;
    }

    /* Print Styles */
    @media print {

        header,
        footer,
        .azac-sidebar,
        .azac-actions,
        .azac-editor-controls {
            display: none !important;
        }

        .azac-content-area {
            box-shadow: none;
            padding: 0;
        }

        .azac-layout {
            display: block;
        }

        body {
            background: white;
        }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .azac-sidebar {
            flex: 100%;
            overflow-x: auto;
            white-space: nowrap;
            padding: 5px;
        }

        .azac-tab {
            display: inline-block;
            border-left: none;
            border-bottom: 3px solid transparent;
        }

        .azac-tab.active {
            border-left: none;
            border-bottom-color: var(--az-teal);
        }
    }
</style>

<div class="container">
    <main>
        <?php if (have_posts()):
            while (have_posts()):
                the_post();
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
                <h3 style="margin-top:0; color:#0b3d3b; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">Bài giảng chính</h3>
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
                    <p>Sử dụng trình soạn thảo bên dưới để nhập nội dung chi tiết cho buổi học này. Bạn có thể chèn hình ảnh, định dạng văn bản và tải lên tài liệu đính kèm.</p>
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
                    <button type="button" id="azac-import-pdf-btn" class="azac-btn azac-btn-outline" style="margin-top:10px; margin-left:10px;">
            <span class="dashicons dashicons-media-document"></span> Import từ PDF
        </button>
        <button type="button" id="azac-clean-format-btn" class="azac-btn azac-btn-outline" style="margin-top:10px; margin-left:10px; display:none;">
            <span class="dashicons dashicons-editor-removeformatting"></span> Clean Format
        </button>
        <div id="azac-import-loading" style="display:none; margin-top:10px; font-style:italic; color:#0f6d5e;">
            <span class="dashicons dashicons-update is-spin"></span> Hệ thống đang chuyển đổi định dạng và xử lý hình ảnh, vui lòng đợi...
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

                <!-- PDF Preview Modal -->
    <div id="azac-pdf-modal" class="azac-modal">
        <div class="azac-modal-content">
            <div class="azac-modal-header">
                <h3>Xem trước PDF</h3>
                <span class="azac-close">&times;</span>
            </div>
            <div class="azac-modal-body">
                <iframe id="azac-pdf-preview-frame"></iframe>
            </div>
            <div class="azac-modal-footer">
                <button id="azac-confirm-download" class="azac-btn">
                    <span class="dashicons dashicons-download" style="margin-top:4px;"></span> Tải xuống
                </button>
                <button class="azac-btn azac-btn-outline azac-close-modal">Hủy</button>
            </div>
        </div>
    </div>
    <!-- Quick View Modal -->
    <div id="azac-quickview-modal" class="azac-modal">
        <div class="azac-modal-content" style="max-width: 1000px; height: 95vh;">
            <div class="azac-modal-header">
                <h3 id="azac-qv-title">Xem tài liệu</h3>
                <span class="azac-close azac-close-qv">&times;</span>
            </div>
            <div class="azac-modal-body" id="azac-qv-body" style="background: #333; display:flex; align-items:center; justify-content:center;">
                <!-- Content injected here -->
            </div>
        </div>
    </div>
</article>
        <?php endwhile; endif; ?>
    </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    // Set worker src
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>
<script>
jQuery(document).ready(function($) {
    var currentSessionId = 0; // 0 = Overview
    var isEditing = false;
    var overviewContent = $('#azac-main-content').html();
    
    // Switch Tabs
    $('.azac-tab').on('click', function() {
        if (isEditing) {
            if(!confirm('Bạn đang chỉnh sửa. Hủy thay đổi để chuyển tab?')) return;
            exitEditMode();
        }

        $('.azac-tab').removeClass('active');
        $(this).addClass('active');

        var target = $(this).data('target');
        var id = $(this).data('id');
        var date = $(this).data('date');

        currentSessionId = id;

        if (target === 'overview') {
            $('#azac-view-title').text('Tổng quan lớp học');
            $('#azac-main-content').html(overviewContent);
            $('#azac-attachments-section').hide();
            
        <?php if ($can_edit): ?>
                    // Redirect to WP Admin for Overview
                    $('#azac-edit-btn').html('<span class="dashicons dashicons-edit"></span> Chỉnh sửa lớp').off('click').on('click', function () {
                        window.location.href = '<?php echo admin_url('post.php?post=' . $post_id . '&action=edit'); ?>';
                    }).show();
                <?php endif; ?>
            } else {
                $('#azac-view-title').text('Nội dung buổi học: ' + $(this).text().trim());
                $('#azac-main-content').html('<p>Đang tải...</p>');
                $('#azac-attachments-section').hide();

                <?php if ($can_edit): ?>
                    // Inline Edit for Session
                    $('#azac-edit-btn').html('<span class="dashicons dashicons-edit"></span> Chỉnh sửa bài giảng').off('click').on('click', function () {
                        enterEditMode();
                    }).show();
                <?php endif; ?>

                loadSessionContent(id);
            }
        });

        // Initialize button state for Overview (Default)
        <?php if ($can_edit): ?>
            $('#azac-edit-btn').html('<span class="dashicons dashicons-edit"></span> Chỉnh sửa lớp').off('click').on('click', function () {
                window.location.href = '<?php echo admin_url('post.php?post=' . $post_id . '&action=edit'); ?>';
        });
    <?php endif; ?>

    // Load Session Content
    function loadSessionContent(id) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'azac_get_session_details',
                session_id: id,
                nonce: '<?php echo wp_create_nonce("azac_session_content"); ?>'
            },
            success: function (res) {
                if (res.success) {
                    $('#azac-main-content').html(res.data.content || '<p>Chưa có nội dung.</p>');

                    // Attachments
                    var attHtml = '';
                    var attIds = [];
                    if (res.data.attachments && res.data.attachments.length > 0) {
                        res.data.attachments.forEach(function (att) {
                            attIds.push(att.id);
                            var iconClass = 'dashicons dashicons-media-default';
                            if (att.mime.includes('pdf')) iconClass = 'dashicons dashicons-media-document pdf';
                            else if (att.mime.includes('word') || att.mime.includes('document')) iconClass = 'dashicons dashicons-media-document word';
                            else if (att.mime.includes('image')) iconClass = 'dashicons dashicons-format-image image';

                            attHtml += `
                            <div class="azac-att-card">
                                <span class="azac-att-icon ${iconClass.split(' ').pop()}"><span class="${iconClass}"></span></span>
                                <div class="azac-att-info">
                                    <a href="#" onclick="openQuickView('${att.url}', '${att.mime}', '${att.title}'); return false;" class="azac-att-title">${att.title}</a>
                                </div>
                                <div style="display:flex; gap:8px;">
                                    <span class="dashicons dashicons-visibility" title="Xem nhanh" style="cursor:pointer; color:#15345a;" onclick="openQuickView('${att.url}', '${att.mime}', '${att.title}')"></span>
                                    <a href="${att.url}" download class="dashicons dashicons-download" title="Tải xuống" style="text-decoration:none; color:#555;"></a>
                                </div>
                            </div>`;
                        });
                        $('#azac-attachments-list').html(attHtml);
                        $('#azac-attachments-section').show();
                    } else {
                        $('#azac-attachments-list').empty();
                        $('#azac-attachments-section').hide();
                    }
                    
                    // Store for editor
                    $('#azac-att-ids').val(JSON.stringify(attIds));
                    renderEditorAttachments(res.data.attachments || []);
                } else {
                    $('#azac-main-content').html('<p class="error">Lỗi tải dữ liệu.</p>');
                }
            }
        });
    }

    // Edit Mode
    function enterEditMode() {
        if (currentSessionId == 0) return;
        isEditing = true;
        
        // Get raw content from server again to be safe? Or use current HTML?
        // Using AJAX to get raw content for editor is safer
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'azac_get_session_details',
                session_id: currentSessionId,
                nonce: '<?php echo wp_create_nonce("azac_session_content"); ?>'
            },
            success: function (res) {
                if (res.success) {
                    var content = res.data.raw_content || '';
                    if (typeof tinymce !== 'undefined' && tinymce.get('azac_session_editor')) {
                        tinymce.get('azac_session_editor').setContent(content);
                    } else {
                        $('#azac_session_editor').val(content);
                    }

                    $('#azac-display-view').hide();
                    $('#azac-editor-container').show();
                    $('#azac-edit-btn').hide();
                    $('#azac-print-btn').hide(); // Hide Print in Edit Mode
                }
            }
        });
    }

    $('#azac-cancel-btn').on('click', function () {
        exitEditMode();
    });

    function exitEditMode() {
        isEditing = false;
        $('#azac-editor-container').hide();
        $('#azac-display-view').show();
        $('#azac-edit-btn').show();
        $('#azac-print-btn').show(); // Show Print in View Mode
    }

    // Save Content
    $('#azac-save-btn').on('click', function () {
        var content = '';
        if (typeof tinymce !== 'undefined' && tinymce.get('azac_session_editor')) {
            content = tinymce.get('azac_session_editor').getContent();
        } else {
            content = $('#azac_session_editor').val();
        }

        var attIds = JSON.parse($('#azac-att-ids').val() || '[]');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'azac_save_session_content',
                session_id: currentSessionId,
                content: content,
                attachments: attIds,
                nonce: '<?php echo wp_create_nonce("azac_session_content"); ?>'
            },
            beforeSend: function () {
                $('#azac-save-btn').text('Đang lưu...').prop('disabled', true);
            },
            success: function (res) {
                if (res.success) {
                    alert('Đã lưu thành công!');
                    loadSessionContent(currentSessionId); // Reload view
                    exitEditMode();
                } else {
                    alert('Lỗi: ' + res.data.message);
                }
            },
            complete: function () {
                $('#azac-save-btn').text('Lưu thay đổi').prop('disabled', false);
            }
        });
    });

    // Media Uploader
    var mediaFrame;
    $('#azac-upload-btn').on('click', function (e) {
        e.preventDefault();
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }
        mediaFrame = wp.media({
            title: 'Chọn tài liệu đính kèm',
            button: { text: 'Thêm vào bài giảng' },
            multiple: true
        });
        mediaFrame.on('select', function () {
            var selection = mediaFrame.state().get('selection');
            var currentIds = JSON.parse($('#azac-att-ids').val() || '[]');
            var newAtts = []; // For rendering

            selection.map(function (attachment) {
                attachment = attachment.toJSON();
                if (!currentIds.includes(attachment.id)) {
                    currentIds.push(attachment.id);
                    newAtts.push({
                        id: attachment.id,
                        title: attachment.title,
                        url: attachment.url,
                        mime: attachment.mime
                    });
                }
            });

            $('#azac-att-ids').val(JSON.stringify(currentIds));
            appendEditorAttachments(newAtts);
        });
        mediaFrame.open();
    });

    function renderEditorAttachments(atts) {
        $('#azac-editor-attachments').empty();
        appendEditorAttachments(atts);
    }

    function appendEditorAttachments(atts) {
        var html = '';
        atts.forEach(function (att) {
            html += `
            <div class="azac-att-card" id="edit-att-${att.id}">
                <div class="azac-att-info">${att.title}</div>
                <span class="dashicons dashicons-trash" style="cursor:pointer; color:red;" onclick="removeAttachment(${att.id})"></span>
            </div>`;
        });
        $('#azac-editor-attachments').append(html);
    }

    // Global function for onclick handling
    window.removeAttachment = function (id) {
        if (!confirm('Xóa tài liệu này?')) return;
        var currentIds = JSON.parse($('#azac-att-ids').val() || '[]');
        var index = currentIds.indexOf(id);
        if (index > -1) {
            currentIds.splice(index, 1);
            $('#azac-att-ids').val(JSON.stringify(currentIds));
            $('#edit-att-' + id).remove();
        }
    };

    // Quick View Logic
    window.openQuickView = function(url, mime, title) {
        $('#azac-qv-title').text(title);
        var content = '';
        if (mime.includes('image')) {
            content = '<img src="' + url + '" style="max-width:100%; max-height:90vh; box-shadow:0 0 20px rgba(0,0,0,0.5);">';
        } else if (mime.includes('pdf')) {
            content = '<iframe src="' + url + '" style="width:100%; height:100%; border:none;"></iframe>';
        } else {
            // Fallback for others (e.g. Word) - Google Docs Viewer or just Link
            content = '<iframe src="https://docs.google.com/viewer?url=' + encodeURIComponent(url) + '&embedded=true" style="width:100%; height:100%; border:none;"></iframe>';
        }
        $('#azac-qv-body').html(content);
        $('#azac-quickview-modal').fadeIn();
    };

    $('.azac-close-qv').on('click', function() {
        $('#azac-quickview-modal').fadeOut();
        $('#azac-qv-body').empty();
    });

    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).is('#azac-quickview-modal')) {
            $('#azac-quickview-modal').fadeOut();
            $('#azac-qv-body').empty();
        }
    });

    // Import PDF Logic
    $('#azac-import-pdf-btn').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({
            title: 'Chọn file PDF để Import',
            button: { text: 'Import nội dung' },
            library: { type: 'application/pdf' },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            importPdfContent(attachment.url);
        });
        
        frame.open();
    });

    function importPdfContent(url) {
        // UI Loading
        $('#azac-import-loading').show();
        $('#azac-import-pdf-btn').prop('disabled', true);
        
        var loadingMsg = '<p><em>Đang phân tích PDF...</em></p>';
        if (typeof tinymce !== 'undefined' && tinymce.get('azac_session_editor')) {
            tinymce.get('azac_session_editor').setContent(loadingMsg);
        } else {
            $('#azac_session_editor').val('Đang phân tích PDF...');
        }
        
        // PDF.js Logic
        var loadingTask = pdfjsLib.getDocument(url);
        loadingTask.promise.then(async function(pdf) {
            try {
                var maxPages = pdf.numPages;
                var finalHtml = '';
                var mostCommonHeight = 0;
                var totalImagesFound = 0;

                // 1. Analyze first page
                try {
                    var firstPage = await pdf.getPage(1);
                    var textContent = await firstPage.getTextContent();
                    var heights = {};
                    textContent.items.forEach(item => {
                         var h = Math.round(Math.abs(item.transform[3])); 
                         if(h > 0) heights[h] = (heights[h] || 0) + 1;
                    });
                    var maxCount = 0;
                    for(var h in heights) {
                        if(heights[h] > maxCount) {
                            maxCount = heights[h];
                            mostCommonHeight = parseInt(h);
                        }
                    }
                } catch(e) { console.error('Analyze error', e); mostCommonHeight = 12; }

                var totalImagesUploaded = 0;
                var MAX_IMAGES = 5; // Hard limit to prevent flooding

                // 2. Process all pages
                for (var j = 1; j <= maxPages; j++) {
                    var page = await pdf.getPage(j);
                    var imagePromises = [];
                    
                    // --- Image Extraction ---
                    if (totalImagesUploaded < MAX_IMAGES) {
                        try {
                            var ops = await page.getOperatorList();
                            for (var i = 0; i < ops.fnArray.length; i++) {
                                if (totalImagesUploaded + imagePromises.length >= MAX_IMAGES) break; // Stop collecting

                                if (ops.fnArray[i] === pdfjsLib.OPS.paintImageXObject) {
                                    var imgName = ops.argsArray[i][0];
                                    imagePromises.push(new Promise(function(resolve) {
                                        var tm = setTimeout(function(){ resolve(null); }, 1000); // 1s Timeout (Reduced)
                                        try {
                                            page.objs.get(imgName, function(img) {
                                                clearTimeout(tm);
                                                if (!img) { resolve(null); return; }
                                                
                                                // STRICTER FILTER: > 150px to avoid icons/spam
                                                if (img.width < 150 || img.height < 150) { resolve(null); return; }

                                                var canvas = document.createElement('canvas');
                                                canvas.width = img.width;
                                                canvas.height = img.height;
                                                var ctx = canvas.getContext('2d');

                                                try {
                                                    // Draw logic (simplified for stability)
                                                    if (img.bitmap) {
                                                        ctx.drawImage(img.bitmap, 0, 0);
                                                    } else {
                                                        // Attempt generic draw, ignore complex data types for now to prevent loops/errors
                                                        // Most real images work with drawImage(img) or have bitmap
                                                        ctx.drawImage(img, 0, 0); 
                                                    }
                                                    resolve(canvas.toDataURL('image/jpeg', 0.7)); // JPEG to save size
                                                } catch (e) { resolve(null); }
                                            });
                                        } catch(e) { clearTimeout(tm); resolve(null); }
                                    }));
                                }
                            }
                        } catch(e) { console.warn('Image extraction skipped for page ' + j, e); }
                    }

                    // Upload Images (Sequential to prevent server overload)
                    var pageUploadedImages = [];
                    if(imagePromises.length > 0) {
                         var base64Images = await Promise.all(imagePromises);
                         for(var imgData of base64Images) {
                             if (totalImagesUploaded >= MAX_IMAGES) break;
                             
                             if(imgData && imgData.length > 5000) { // > ~5KB
                                 try {
                                     // Sync Upload (await)
                                     var uploadRes = await $.ajax({
                                         url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                        type: 'POST',
                                        data: {
                                            action: 'azac_upload_pdf_image',
                                            image: imgData,
                                            nonce: '<?php echo wp_create_nonce("azac_session_content"); ?>'
                                        }
                                    });
                                    if (uploadRes.success) {
                                        pageUploadedImages.push(uploadRes.data.url);
                                        totalImagesUploaded++;
                                        totalImagesFound++;
                                    }
                                } catch (err) { console.error('Upload failed', err); }
                            }
                        }
                    }

                    // --- Text Extraction ---
                    var textContent = await page.getTextContent();
                    var lines = {};
                    textContent.items.forEach(function (item) {
                        var y = Math.round(item.transform[5]);
                        var foundKey = Object.keys(lines).find(k => Math.abs(k - y) < 5);
                        var key = foundKey || y;
                        if (!lines[key]) lines[key] = [];
                        lines[key].push(item);
                    });

                    var sortedY = Object.keys(lines).sort((a, b) => b - a);
                    var pageHtml = '';

                    sortedY.forEach(function (y) {
                        var items = lines[y];
                        items.sort((a, b) => a.transform[4] - b.transform[4]);

                        var lineText = '';
                        var currentSize = 0;

                        items.forEach(function (item) {
                            if (!item.str.trim()) return;
                            lineText += item.str + ' ';
                            currentSize = Math.max(currentSize, Math.round(Math.abs(item.transform[3])));
                        });

                        if (!lineText.trim()) return;

                        var tag = 'p';
                        if (currentSize > mostCommonHeight * 1.8) tag = 'h2';
                        else if (currentSize > mostCommonHeight * 1.3) tag = 'h3';
                        else if (currentSize > mostCommonHeight * 1.1) tag = 'strong';

                        if (lineText.trim().match(/^[•\-\*]\s/)) {
                            tag = 'li';
                            lineText = lineText.replace(/^[•\-\*]\s/, '');
                        }

                        if (tag === 'strong') pageHtml += '<p><strong>' + lineText + '</strong></p>';
                        else if (tag === 'li') pageHtml += '<li>' + lineText + '</li>';
                        else pageHtml += '<' + tag + '>' + lineText + '</' + tag + '>';
                    });

                    finalHtml += pageHtml;

                    if (pageUploadedImages.length > 0) {
                        finalHtml += '<div class="pdf-images-extracted">';
                        pageUploadedImages.forEach(url => {
                            finalHtml += '<img src="' + url + '" style="max-width:100%; height:auto; margin: 10px 0;">';
                        });
                        finalHtml += '</div>';
                    }
                    
                    finalHtml += '<hr>';
                }
                
                finalHtml = finalHtml.replace(/((<li>.*?<\/li>)+)/g, '<ul>$1</ul>');

                // Fallback only if absolutely empty
                if (finalHtml.trim().length < 50 && totalImagesFound === 0) {
                     finalHtml = `<h3>Nội dung bài giảng (PDF)</h3>
                     <p>Tài liệu gốc: <a href="${url}" target="_blank">Xem file PDF</a></p>
                     <iframe src="${url}" width="100%" height="800px" style="border:1px solid #ddd;"></iframe>`;
                } else {
                    finalHtml += `<p>---<br><em>Imported from PDF</em></p>`;
                }
                
                if (typeof tinymce !== 'undefined' && tinymce.get('azac_session_editor')) {
                    tinymce.get('azac_session_editor').setContent(finalHtml);
                } else {
                    $('#azac_session_editor').val(finalHtml);
                }
                
                $('#azac-import-loading').hide();
                $('#azac-import-pdf-btn').prop('disabled', false);
                $('#azac-clean-format-btn').show();

            } catch(e) {
                console.error(e);
                alert('Lỗi xử lý PDF: ' + e.message);
                $('#azac-import-loading').hide();
                $('#azac-import-pdf-btn').prop('disabled', false);
            }

        }, function (reason) {
            console.error(reason);
            alert('Lỗi khi đọc file PDF: ' + reason);
            $('#azac-import-loading').hide();
            $('#azac-import-pdf-btn').prop('disabled', false);
        });
    }

    // Clean Format Logic
    $('#azac-clean-format-btn').on('click', function() {
        var editor = tinymce.get('azac_session_editor');
        var content = editor ? editor.getContent() : $('#azac_session_editor').val();
        
        // Remove style attributes
        content = content.replace(/ style="[^"]*"/g, '');
        // Remove class attributes
        content = content.replace(/ class="[^"]*"/g, '');
        // Remove empty spans
        content = content.replace(/<span>(.*?)<\/span>/g, '$1');
        // Remove empty paragraphs
        content = content.replace(/<p>\s*<\/p>/g, '');
        
        if (editor) editor.setContent(content);
        else $('#azac_session_editor').val(content);
        
        alert('Đã dọn dẹp định dạng rác!');
    });

    // Print PDF Logic (Native Browser Print for Vector Quality)
    $('#azac-print-btn').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.html('<span class="dashicons dashicons-update is-spin"></span> Đang xử lý...').prop('disabled', true);

        // Generate Dynamic Filename and Title
        function formatName(str) {
            if (!str) return '';
            return str.normalize("NFD")
                      .replace(/[\u0300-\u036f]/g, "")
                      .replace(/đ/g, "d").replace(/Đ/g, "D")
                      .replace(/[^a-zA-Z0-9\s]/g, "") 
                      .split(/\s+/)
                      .map(function(w) { return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase(); })
                      .join("");
        }

        var className = formatName($('#azac-class-name').text());
        var teacherName = formatName($('#azac-teacher-name').text());
        var activeTab = $('.azac-tab.active');
        var mode = activeTab.data('target'); 
        var filename = 'Document';
        var pdfTitle = $('#azac-view-title').text(); // Default title

        if (mode === 'overview') {
            filename = className;
            if (teacherName) filename += '_' + teacherName;
        } else {
            var dateRaw = activeTab.data('date'); 
            var sessionName = activeTab.text().trim(); // e.g. "Buổi 1: 20/01"
            pdfTitle = 'Nội dung: ' + sessionName + ' - ' + $('#azac-class-name').text(); // Update PDF Title

            var prefix = 'TaiLieu';
            if(dateRaw) {
                var parts = dateRaw.split('-');
                var yy = parts[0].slice(-2);
                var dateFormatted = parts[2] + '_' + parts[1] + '_' + yy; 
                prefix += '_' + dateFormatted;
            }
            
            filename = prefix + '_' + className;
            if (teacherName) filename += '_' + teacherName;
        }

        // Create Hidden Iframe
        var $iframe = $('<iframe id="azac-print-frame" name="azac-print-frame"></iframe>');
        $iframe.css({ position: 'fixed', top: '-10000px', left: '-10000px', width: '1px', height: '1px', opacity: '0' });
        $('body').append($iframe);

        var frameDoc = $iframe[0].contentWindow.document;
        var contentHtml = $('#azac-main-content').html();

        // Write Content
        frameDoc.open();
        frameDoc.write('<!DOCTYPE html>');
        frameDoc.write('<html><head>');
        frameDoc.write('<title>' + filename + '</title>'); // Sets default filename
        frameDoc.write('<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">');
        frameDoc.write('<style>');
        frameDoc.write('@page { size: A4; margin: 2cm 2cm 2cm 2.5cm; }'); // Standard margins
        frameDoc.write('body { font-family: "Roboto", Arial, sans-serif; font-size: 11pt; line-height: 1.5; color: #000; margin: 0; padding: 0; -webkit-print-color-adjust: exact; }');
        frameDoc.write('h1 { font-size: 16pt; font-weight: 700; color: #004E44; border-bottom: 2px solid #004E44; padding-bottom: 10px; margin-bottom: 20px; }');
        frameDoc.write('img { max-width: 100% !important; height: auto !important; display: block; margin: 15px auto; }');
        frameDoc.write('table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }');
        frameDoc.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        frameDoc.write('p { margin-bottom: 10px; }');
        frameDoc.write('a { color: #000; text-decoration: none; }');
        frameDoc.write('</style>');
        frameDoc.write('</head><body>');
        frameDoc.write('<h1>' + pdfTitle + '</h1>');
        frameDoc.write(contentHtml);
        frameDoc.write('</body></html>');
        frameDoc.close();

        // Wait for images to load then print
        var images = frameDoc.images;
        var loaded = 0;
        var total = images.length;

        function doPrint() {
            try {
                $iframe[0].contentWindow.focus();
                $iframe[0].contentWindow.print();
            } catch (e) {
                console.error(e);
                alert('Không thể mở hộp thoại in. Vui lòng thử lại.');
            }
            
            // Cleanup after print dialog closes (or timeout)
            // Note: print() is blocking in some browsers, non-blocking in others.
            // We'll leave the iframe for a bit then remove it.
            setTimeout(function() {
                $iframe.remove();
                $btn.html(originalText).prop('disabled', false);
            }, 2000);
        }

        if (total === 0) {
            setTimeout(doPrint, 500); // Small delay for fonts
        } else {
            var isPrinted = false;
            var checkPrint = function() {
                if (!isPrinted) {
                    isPrinted = true;
                    doPrint();
                }
            };

            for (var i = 0; i < total; i++) {
                if (images[i].complete) {
                    loaded++;
                } else {
                    images[i].onload = function() { loaded++; if (loaded === total) checkPrint(); };
                    images[i].onerror = function() { loaded++; if (loaded === total) checkPrint(); };
                }
            }
            if (loaded === total) {
                setTimeout(checkPrint, 500);
            }
            
            // Fallback safety
            setTimeout(checkPrint, 5000);
        }
    });

    // Remove old modal logic if present (cleaned up via overwrite)
});
                                </script>

<!-- Quick View Modal -->
<div id="azac-quickview-modal" class="azac-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.8); backdrop-filter: blur(5px);">
    <div class="azac-modal-content" style="background-color:#fefefe; margin:2% auto; padding:0; border:none; width:90%; height:90vh; display:flex; flex-direction:column; border-radius:8px; overflow:hidden; box-shadow: 0 5px 30px rgba(0,0,0,0.3);">
        <div style="padding:15px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:#fff;">
            <h3 id="azac-qv-title" style="margin:0; font-size:18px; color:#0b3d3b; font-weight:600;">Xem tài liệu</h3>
            <span class="azac-close-qv" style="color:#aaa; font-size:28px; font-weight:bold; cursor:pointer; line-height: 1;">&times;</span>
        </div>
        <div id="azac-qv-body" style="flex:1; overflow:hidden; background:#f0f2f5; display:flex; align-items:center; justify-content:center; position:relative;">
            <div class="azac-loader" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Close Modal on Outside Click
    $(window).on('click', function(event) {
        if ($(event.target).is('#azac-quickview-modal')) {
            $('#azac-quickview-modal').fadeOut();
            $('#azac-qv-body').empty(); // Stop video/iframe
        }
    });
    
    // Close Button
    $('.azac-close-qv').on('click', function() {
        $('#azac-quickview-modal').fadeOut();
        $('#azac-qv-body').empty();
    });
});
</script>

<?php
get_footer();
?>
