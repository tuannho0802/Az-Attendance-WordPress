<?php
if (!defined('ABSPATH')) {
    exit;
}

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

                        <div class="azac-layout" style="min-height: 800px !important;">
                            <!-- Main Column (75%) -->
                            <div class="azac-main-col">
                                <!-- Horizontal Timeline -->
                                <div class="azac-timeline">
                                    <div class="azac-tab active" data-target="overview" data-id="0">Tổng quan</div>
                                    <?php foreach ($sessions as $index => $sess): ?>
                                            <div class="azac-tab" data-target="session" data-id="<?php echo esc_attr($sess['id']); ?>"
                                                data-date="<?php echo esc_attr($sess['date']); ?>">
                                                Buổi <?php echo $index + 1; ?>: <?php echo date('d/m/y', strtotime($sess['date'])); ?>
                                            </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Content Area -->
                                <div class="azac-content-area" id="azac-pdf-content" style="min-height: 800px !important;">
                                    <div class="azac-content-header">
                                        <h2 id="azac-view-title">Tổng quan lớp học</h2>
                                        <div class="azac-actions">
                                            <?php if ($can_edit): ?>
                                                                <a href="<?php echo get_edit_post_link($post_id); ?>" id="azac-edit-class-btn" class="azac-btn">
                                                    <span class="dashicons dashicons-edit"></span> Chỉnh sửa
                                                </a>
                                                <button id="azac-edit-btn" class="azac-btn" style="display:none;">
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
                                                    <h3 style="margin-top:0; color:#0b3d3b; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">
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
                                                <p>Sử dụng trình soạn thảo bên dưới để nhập nội dung chi tiết cho buổi học này. Bạn có
                                                    thể chèn hình ảnh, định
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
                                                <button type="button" id="azac-upload-btn" class="azac-btn azac-btn-outline"
                                                    style="margin-top:10px;">
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
                                                <div id="azac-import-loading"
                                                    style="display:none; margin-top:10px; font-style:italic; color:#0f6d5e;">
                                                    <span class="dashicons dashicons-update is-spin"></span> Hệ thống đang chuyển đổi
                                                    định dạng và xử lý hình ảnh,
                                                    vui lòng đợi...
                                                </div>
                                                <input type="hidden" id="azac-att-ids" value="[]">
                                            </div>

                                            <div class="azac-editor-controls"
                                                style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; text-align: right;">
                                                <button id="azac-cancel-btn" class="azac-btn azac-btn-outline"
                                                    style="margin-right: 10px;">Hủy</button>
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
                                    <p><strong>Giảng viên:</strong>
                                        <?php echo esc_html(get_post_meta($post_id, 'az_giang_vien', true) ?: 'Chưa cập nhật'); ?>
                                    </p>
                                    <p><strong>Tổng số buổi:</strong>
                                        <?php echo esc_html(get_post_meta($post_id, 'az_tong_so_buoi', true)); ?></p>
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

<script>
    jQuery(document).ready(function ($) {
        // 1. Setup Variables
        var overviewContent = $('#azac-main-content').html();
        var overviewTitle = "Tổng quan lớp học";
        var currentSessionId = 0;

        // Define AJAX vars
        var azac_vars = {
            ajax_url: "<?php echo admin_url('admin-ajax.php'); ?>",
            nonce: "<?php echo wp_create_nonce('azac_session_content'); ?>"
        };

        // 2. Tab Switching Logic
        $('.azac-tab').on('click', function () {
            var $t = $(this);
            if ($t.hasClass('active')) return;

            $('.azac-tab').removeClass('active');
            $t.addClass('active');

            var target = $t.data('target');
            var id = $t.data('id');
            currentSessionId = id;

            // Reset UI
            $('#azac-editor-container').slideUp();

            // --- 1. Xác định vùng nội dung ---
            var $container = $('#azac-main-content');

            // --- 2. KHÔNG xóa nội dung cũ ngay. Hãy làm mờ nó đi và Khóa chiều cao ---
            $container.css({
                'opacity': '0.5',
                'min-height': $container.height() + 'px'
            });

            if (target === 'overview') {
                $container.html(overviewContent).css('opacity', '1');
                $('#azac-view-title').text(overviewTitle);
                $('#azac-attachments-section').hide();
                $('#azac-edit-btn').hide();
                $('#azac-edit-class-btn').show();

                // 4. Giải phóng chiều cao sau 500ms
                setTimeout(function () {
                    $container.css('min-height', '800px');
                }, 500);

                // Trigger Animation
                var $view = $('#azac-display-view');
                $view.removeClass('azac-fade-in');
                void $view[0].offsetWidth; // trigger reflow
                $view.addClass('azac-fade-in');
            } else {
                // Set Title
                var sessionTitle = $t.text().trim();
                $('#azac-view-title').text(sessionTitle);
                $('#azac-attachments-section').hide();
                $('#azac-edit-class-btn').hide();

                // Fetch Data
                $.post(azac_vars.ajax_url, {
                    action: 'azac_get_session_details',
                    nonce: azac_vars.nonce,
                    session_id: id
                }, function (res) {
                    if (res.success) {
                        var content = res.data.content;
                        // Content is now pre-processed by server with correct wrapper and min-height

                        // 3. Chỉ cập nhật nội dung khi dữ liệu đã sẵn sàng
                        $container.html(content).css('opacity', '1');

                        // 4. Giải phóng chiều cao sau 500ms
                        setTimeout(function () {
                            $container.css('min-height', '800px');
                        }, 500);

                        // Trigger Fade In Animation
                        var $view = $('#azac-display-view');
                        $view.removeClass('azac-fade-in');
                        void $view[0].offsetWidth; // trigger reflow
                        $view.addClass('azac-fade-in');

                        // Show Edit Button if user has permission (button exists)
                        $('#azac-edit-btn').show();

                        // Render Attachments
                        if (res.data.attachments && res.data.attachments.length > 0) {
                            renderAttachments(res.data.attachments, '#azac-attachments-list');
                            $('#azac-attachments-section').show();
                        }

                        // Update Editor Content if available
                        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('azac_session_editor')) {
                            tinyMCE.get('azac_session_editor').setContent(res.data.raw_content);
                        } else if ($('#azac_session_editor').length) {
                            $('#azac_session_editor').val(res.data.raw_content);
                        }

                        // Update Editor Attachments
                        if (res.data.attachments) {
                            renderEditorAttachments(res.data.attachments);
                        }

                    } else {
                        alert(res.data.message || 'Lỗi tải dữ liệu');
                        $container.css({ 'opacity': '1', 'min-height': '800px' }); // Reset on error
                    }
                }).fail(function () {
                    $container.css({ 'opacity': '1', 'min-height': '800px' }); // Reset on fail
                });
            }
        });

        // 3. Editor Logic
        $('#azac-edit-btn').on('click', function () {
            $('#azac-display-view').slideUp();
            $('#azac-editor-container').slideDown();
        });

        $('#azac-cancel-btn').on('click', function () {
            $('#azac-editor-container').slideUp();
            $('#azac-display-view').slideDown();
        });

        $('#azac-save-btn').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Đang lưu...');

            var content = '';
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('azac_session_editor') && !tinyMCE.get('azac_session_editor').isHidden()) {
                content = tinyMCE.get('azac_session_editor').getContent();
            } else {
                content = $('#azac_session_editor').val();
            }

            // Get Attachments IDs
            var attIds = [];
            // Assuming we store IDs in a hidden input or array. 
            // For now, let's parse from the rendered editor attachments or just rely on what we have.
            // The PHP code has: <input type="hidden" id="azac-att-ids" value="[]">
            var val = $('#azac-att-ids').val();
            if (val) {
                try {
                    attIds = JSON.parse(val);
                } catch (e) { }
            }

            $.post(azac_vars.ajax_url, {
                action: 'azac_save_session_content',
                nonce: azac_vars.nonce,
                session_id: currentSessionId,
                content: content,
                attachments: attIds
            }, function (res) {
                $btn.prop('disabled', false).text('Lưu thay đổi');
                if (res.success) {
                    alert('Đã lưu nội dung thành công!');
                    // Update Display View
                    $('#azac-main-content').html(res.data.content);
                    $('#azac-editor-container').slideUp();
                    $('#azac-display-view').slideDown();

                    // Update Attachments View
                    if (res.data.attachments && res.data.attachments.length > 0) {
                        renderAttachments(res.data.attachments, '#azac-attachments-list');
                        $('#azac-attachments-section').show();
                    } else {
                        $('#azac-attachments-section').hide();
                    }

                } else {
                    alert('Lỗi: ' + (res.data.message || 'Không thể lưu'));
                }
            }).fail(function () {
                $btn.prop('disabled', false).text('Lưu thay đổi');
                alert('Lỗi kết nối server.');
            });
        });

        // Helper: Render View Attachments
        function renderAttachments(atts, container) {
            var html = '';
            $.each(atts, function (i, att) {
                var iconClass = 'dashicons-media-default';
                if (att.mime.indexOf('pdf') !== -1) iconClass = 'dashicons-pdf';
                else if (att.mime.indexOf('image') !== -1) iconClass = 'dashicons-format-image';
                else if (att.mime.indexOf('word') !== -1) iconClass = 'dashicons-media-document';

                html += '<div class="azac-att-card">';
                html += '<span class="dashicons ' + iconClass + ' azac-att-icon"></span>';
                html += '<div class="azac-att-info"><a href="' + att.url + '" target="_blank" class="azac-att-title">' + att.title + '</a></div>';
                // View/Download actions could go here
                html += '</div>';
            });
            $(container).html(html);
        }

        // Helper: Render Editor Attachments (with delete option)
        function renderEditorAttachments(atts) {
            // Update hidden input
            var ids = atts.map(function (a) { return a.id; });
            $('#azac-att-ids').val(JSON.stringify(ids));

            var html = '';
            $.each(atts, function (i, att) {
                var iconClass = 'dashicons-media-default';
                if (att.mime.indexOf('pdf') !== -1) iconClass = 'dashicons-pdf';

                html += '<div class="azac-att-card" data-id="' + att.id + '">';
                html += '<span class="dashicons ' + iconClass + ' azac-att-icon"></span>';
                html += '<div class="azac-att-info"><span class="azac-att-title">' + att.title + '</span></div>';
                html += '<button type="button" class="button-link azac-remove-att" style="color:#a00;">Xóa</button>';
                html += '</div>';
            });
            $('#azac-editor-attachments').html(html);
        }

        // Remove Attachment Logic
        $(document).on('click', '.azac-remove-att', function () {
            var $card = $(this).closest('.azac-att-card');
            var id = $card.data('id');

            // Update hidden input
            var ids = JSON.parse($('#azac-att-ids').val() || '[]');
            ids = ids.filter(function (i) { return i != id; });
            $('#azac-att-ids').val(JSON.stringify(ids));

            $card.remove();
        });

        // 4. Upload Logic (Simplified)
        var frame;
        $('#azac-upload-btn').on('click', function (e) {
            e.preventDefault();
            if (frame) {
                frame.open();
                return;
            }
            frame = wp.media({
                title: 'Chọn tài liệu',
                button: { text: 'Thêm vào bài học' },
                multiple: true
            });
            frame.on('select', function () {
                var selection = frame.state().get('selection');
                var currentIds = JSON.parse($('#azac-att-ids').val() || '[]');
                var newAtts = []; // We need full objects to render, but simple IDs for input

                // We need to re-fetch full objects or just append to UI?
                // Let's just append to UI and update ID list
                selection.map(function (attachment) {
                    attachment = attachment.toJSON();
                    if (currentIds.indexOf(attachment.id) === -1) {
                        currentIds.push(attachment.id);
                        // Append UI
                        var iconClass = 'dashicons-media-default';
                        if (attachment.mime.indexOf('pdf') !== -1) iconClass = 'dashicons-pdf';

                        var html = '<div class="azac-att-card" data-id="' + attachment.id + '">';
                        html += '<span class="dashicons ' + iconClass + ' azac-att-icon"></span>';
                        html += '<div class="azac-att-info"><span class="azac-att-title">' + attachment.title + '</span></div>';
                        html += '<button type="button" class="button-link azac-remove-att" style="color:#a00;">Xóa</button>';
                        html += '</div>';
                        $('#azac-editor-attachments').append(html);
                    }
                });
                $('#azac-att-ids').val(JSON.stringify(currentIds));
            });
            frame.open();
        });

        // PDF Modal
        $(document).on('click', '.azac-att-title', function (e) {
            var url = $(this).attr('href');
            // If it's a PDF, prevent default and open modal
            if (url && url.indexOf('.pdf') !== -1) {
                e.preventDefault();
                $('#azac-pdf-frame').attr('src', url);
                $('#azac-pdf-modal').fadeIn();
            }
        });

        $('#azac-close-modal-btn, .azac-close').on('click', function () {
            $('#azac-pdf-modal').fadeOut();
            $('#azac-pdf-frame').attr('src', '');
        });
    });
</script>
<?php
get_footer();
?>