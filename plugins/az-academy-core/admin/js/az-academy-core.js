jQuery(document).ready(function($) {
    // Tabs
    $('.azac-tab-btn').on('click', function() {
        $('.azac-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.azac-tab-pane').removeClass('active');
        $('#tab-' + $(this).data('tab')).addClass('active');
    });

    // --- SCAN USERS ---
    $('#btn-scan-users').on('click', function() {
        let $btn = $(this);
        $btn.prop('disabled', true).text('Đang quét...');
        
        $.post(azacConfig.ajax_url, {
            action: 'azac_sp_scan_users',
            _ajax_nonce: azacConfig.nonce
        }, function(res) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Bắt đầu Quét User');
            if(res.success) {
                renderUsers(res.data);
            } else {
                alert('Lỗi: ' + res.data);
            }
        });
    });

    function renderUsers(users) {
        const $tbody = $('#table-users tbody');
        $tbody.empty();
        
        if(users.length === 0) {
            $tbody.html('<tr><td colspan="6" style="text-align:center">Hệ thống an toàn. Không tìm thấy user đáng ngờ.</td></tr>');
            return;
        }

        users.forEach(u => {
            let badgeClass = u.status === 'warning' ? 'badge-warning' : (u.status === 'danger' ? 'badge-danger' : 'badge-success');
            
            // Permission Logic
            let actionHtml = '';
            if (azacConfig.isAdmin) {
                 actionHtml = `<button class="button button-small action-fix-user" data-id="${u.ID}">Khóa</button>`;
            } else {
                 actionHtml = `<span class="dashicons dashicons-lock" title="Cần quyền Admin"></span> Restricted`;
            }

            let row = `
                <tr>
                    <td data-label="Avatar"><img src="${u.avatar}" class="azac-avatar" style="border-radius:50%; width:32px"></td>
                    <td data-label="User Info"><strong>${u.user_login}</strong><br><small>${u.user_email}</small></td>
                    <td data-label="Vai trò">${u.roles}</td>
                    <td data-label="Lý do cảnh báo"><span class="azac-reason-text">${u.reason}</span></td>
                    <td data-label="Trạng thái"><span class="azac-badge ${badgeClass}">${u.status_text}</span></td>
                    <td data-label="Hành động">${actionHtml}</td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    // --- SCAN MALWARE ---
    $('#btn-scan-malware').on('click', function() {
        let $btn = $(this);
        $btn.prop('disabled', true);
        $('.azac-progress-wrapper').show();
        $('.azac-progress-fill').css('width', '50%'); // Demo progress

        $.post(azacConfig.ajax_url, {
            action: 'azac_sp_scan_malware',
            _ajax_nonce: azacConfig.nonce
        }, function(res) {
            $btn.prop('disabled', false);
            $('.azac-progress-fill').css('width', '100%');
            setTimeout(() => { $('.azac-progress-wrapper').hide(); $('.azac-progress-fill').css('width', '0%'); }, 1000);
            
            if(res.success) {
                renderMalware(res.data);
            }
        });
    });

    function renderMalware(items) {
        const $tbody = $('#table-malware tbody');
        $tbody.empty();

        if(items.length === 0) {
            $tbody.html('<tr><td colspan="5" style="text-align:center; color:green;">✅ Không phát hiện mã độc.</td></tr>');
            return;
        }

        items.forEach(it => {
            let actionHtml = azacConfig.isAdmin 
                ? `<button class="button button-small button-link-delete action-fix-malware" data-key="${it.action_key}">Xử lý</button>`
                : `<span class="dashicons dashicons-no" title="Cần quyền Admin"></span>`;

            let row = `
                <tr>
                    <td data-label="Loại"><strong>${it.type}</strong></td>
                    <td data-label="Đối tượng">ID: ${it.id}<br><em>${it.name}</em></td>
                    <td data-label="Đoạn mã nghi vấn"><div class="azac-code-block"><pre><code>${it.snippet}</code></pre></div></td>
                    <td data-label="Lý do / Pattern"><span class="azac-reason-text">${it.reason}</span></td>
                    <td data-label="Hành động">${actionHtml}</td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    // --- SCAN INTEGRITY ---
    $('#btn-scan-integrity').on('click', function() {
        let $btn = $(this);
        $btn.prop('disabled', true).text('Đang kiểm tra...');
        
        $.post(azacConfig.ajax_url, {
            action: 'azac_sp_scan_integrity',
            _ajax_nonce: azacConfig.nonce
        }, function(res) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Kiểm tra Dữ liệu');
            if(res.success) {
                renderIntegrity(res.data);
            }
        });
    });

    function renderIntegrity(items) {
        const $tbody = $('#table-integrity tbody');
        $tbody.empty();

        if(items.length === 0) {
            $tbody.html('<tr><td colspan="5" style="text-align:center; color:green;">✅ Dữ liệu toàn vẹn.</td></tr>');
            return;
        }

        items.forEach(it => {
            let actionHtml = azacConfig.isAdmin 
                ? `<button class="button button-small action-fix-integrity" data-cid="${it.class_id}" data-sid="${it.student_id}">Dọn dẹp</button>`
                : `<span class="dashicons dashicons-shield" title="Restricted"></span>`;

            let row = `
                <tr>
                    <td data-label="Lớp học (ID)">${it.class_id}</td>
                    <td data-label="Học viên (ID)">${it.student_id}</td>
                    <td data-label="Chi tiết lỗi"><span class="azac-reason-text">${it.reason}</span></td>
                    <td data-label="Trạng thái"><span class="badge-warning">Warning</span></td>
                    <td data-label="Hành động">${actionHtml}</td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    // --- ACTIONS ---
    $(document).on('click', '.action-fix-user', function() {
        if(!confirm('Bạn có chắc muốn khóa tài khoản này?')) return;
        let id = $(this).data('id');
        let $btn = $(this);
        
        $.post(azacConfig.ajax_url, {
            action: 'azac_sp_fix_user',
            id: id,
            _ajax_nonce: azacConfig.nonce
        }, function(res) {
            if(res.success) {
                alert(res.data);
                $btn.closest('tr').fadeOut();
            } else {
                alert(res.data);
            }
        });
    });

    $(document).on('click', '.action-fix-integrity', function() {
        if(!confirm('Xóa bản ghi điểm danh này?')) return;
        let cid = $(this).data('cid');
        let sid = $(this).data('sid');
        let $btn = $(this);

        $.post(azacConfig.ajax_url, {
            action: 'azac_sp_fix_integrity',
            class_id: cid,
            student_id: sid,
            _ajax_nonce: azacConfig.nonce
        }, function(res) {
            if(res.success) {
                $btn.closest('tr').fadeOut();
            } else {
                alert(res.data);
            }
        });
    });
});
