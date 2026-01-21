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

    .azac-layout {
        display: flex;
        gap: 20px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    /* Sidebar / Timeline */
    .azac-sidebar {
        flex: 0 0 250px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 10px 0;
        height: fit-content;
    }

    .azac-tab {
        display: block;
        padding: 12px 20px;
        cursor: pointer;
        border-left: 4px solid transparent;
        transition: all 0.2s;
        font-weight: 500;
        color: #333;
    }

    .azac-tab:hover {
        background: #f0f4f8;
        color: var(--az-teal);
    }

    .azac-tab.active {
        border-left-color: var(--az-teal);
        background: #eef3f9;
        color: var(--az-teal);
        font-weight: 700;
    }

    /* Content Area */
    .azac-content-area {
        flex: 1;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 30px;
        min-width: 0;
        /* Prevent overflow */
    }

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
                    <!-- Sidebar Tabs -->
                    <div class="azac-sidebar">
                        <div class="azac-tab active" data-target="overview" data-id="0">Tổng quan</div>
                        <?php foreach ($sessions as $index => $sess): ?>
                            <div class="azac-tab" data-target="session" data-id="<?php echo esc_attr($sess['id']); ?>"
                                data-date="<?php echo esc_attr($sess['date']); ?>">
                                Buổi <?php echo $index + 1; ?>: <?php echo date('d/m', strtotime($sess['date'])); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                
                    <!-- Content Area -->
                    <div class="azac-content-area">
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
                        <div id="azac-display-view">
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
                                <p>Sử dụng trình soạn thảo bên dưới để nhập nội dung chi tiết cho buổi học này. Bạn có thể chèn hình
                                    ảnh, định dạng văn bản và tải lên tài liệu đính kèm.</p>
                            </div>

                            <?php
                            wp_editor('', 'azac_session_editor', [
                                'media_buttons' => true,
                                'textarea_rows' => 25, // Increased rows
                                'teeny' => false,
                                'quicktags' => true,
                                'editor_height' => 500 // Try to force height
                            ]);
                            ?>
                            <div class="azac-editor-meta" style="margin-top: 15px;">
                                <h4>Tài liệu</h4>
                                <div id="azac-editor-attachments" class="azac-att-grid"></div>
                                <button type="button" id="azac-upload-btn" class="azac-btn azac-btn-outline" style="margin-top:10px;">
                                    <span class="dashicons dashicons-upload"></span> Upload Tài liệu
                                </button>
                                <input type="hidden" id="azac-att-ids" value="[]">
                            </div>
                            
                            <div class="azac-editor-controls"
                                style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; text-align: right;">
                                <button id="azac-cancel-btn" class="azac-btn azac-btn-outline" style="margin-right: 10px;">Hủy</button>
                                <button id="azac-save-btn" class="azac-btn">Lưu thay đổi</button>
                            </div>
                            </div>
                        <?php endif; ?>
                        
                        </div>
                </div>

            </article>
        <?php endwhile; endif; ?>
    </main>
</div>

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
                                    <a href="${att.url}" target="_blank" class="azac-att-title">${att.title}</a>
                                </div>
                                <a href="${att.url}" download class="azac-att-actions dashicons dashicons-download"></a>
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

    // Print PDF
    $('#azac-print-btn').on('click', function () {
        window.print();
    });
});
                                </script>

<?php
get_footer();
?>
