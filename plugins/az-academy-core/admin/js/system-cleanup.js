(function ($) {
    $(function () {
        // Select All
        $('#cb-select-all-system').on('change', function () {
            $('.cb-select-system').prop('checked', $(this).prop('checked'));
        });

        // Single Delete
        $('.azac-delete-system-item').on('click', function (e) {
            e.preventDefault();
            if (!confirm(AZAC_SYSTEM.confirmDelete)) return;

            var id = $(this).data('id');
            var type = $(this).data('type');
            var $row = $(this).closest('tr');
            var items = [type + '|' + id];

            performCleanup(items, function () {
                $row.fadeOut(300, function () { $(this).remove(); });
                updateCount();
            });
        });

        // Bulk Delete
        $('#azac-do-system-bulk').on('click', function (e) {
            e.preventDefault();
            var action = $('#azac-bulk-action-system').val();
            if (action !== 'delete') return;

            var items = [];
            $('.cb-select-system:checked').each(function () {
                items.push($(this).val());
            });

            if (items.length === 0) {
                alert('Vui lòng chọn ít nhất một mục.');
                return;
            }

            if (!confirm(AZAC_SYSTEM.confirmBulkDelete)) return;

            var $btn = $(this);
            $btn.prop('disabled', true);

            performCleanup(items, function () {
                $btn.prop('disabled', false);
                // Reload to refresh list easily
                location.reload();
            }, function () {
                $btn.prop('disabled', false);
            });
        });

        function performCleanup(items, onSuccess, onError) {
            $.post(AZAC_SYSTEM.ajaxUrl, {
                action: 'azac_system_cleanup',
                nonce: AZAC_SYSTEM.nonce,
                items: items
            }, function (res) {
                if (res.success) {
                    // alert(res.data.message); // Optional
                    if (onSuccess) onSuccess();
                } else {
                    alert('Lỗi: ' + (res.data || 'Unknown error'));
                    if (onError) onError();
                }
            }).fail(function () {
                alert('Lỗi kết nối Server.');
                if (onError) onError();
            });
        }

        function updateCount() {
            var count = $('#azac-system-tbody tr').length;
            if (count === 0) {
                location.reload();
            }
        }
    });
})(jQuery);
