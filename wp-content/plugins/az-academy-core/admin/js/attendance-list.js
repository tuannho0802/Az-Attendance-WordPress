;(function($){
    $(function(){
        $('#azac_create_class_btn').on('click', function(){
            var title = $('#azac_new_class_title').val().trim();
            var teacher = $('#azac_new_class_teacher').val().trim();
            var sessions = parseInt($('#azac_new_class_sessions').val(),10)||0;
            if(!title){ alert('Nhập tên lớp'); return; }
            var payload = {
                action: 'azac_create_class',
                nonce: AZAC_LIST.nonce,
                title: title,
                teacher: teacher,
                sessions: sessions
            };
            var btn = $(this).prop('disabled', true);
            $.post(AZAC_LIST.ajaxUrl, payload, function(res){
                btn.prop('disabled', false);
                if(res && res.success){
                    var c = res.data;
                    var card = [
                        '<div class="azac-card">',
                        '<div class="azac-card-title">'+c.title+'</div>',
                        '<div class="azac-card-body">',
                        '<div>Giảng viên: '+teacher+'</div>',
                        '<div>Tổng số buổi: '+sessions+'</div>',
                        '<div>Số học viên: 0</div>',
                        '</div>',
                        '<div class="azac-card-actions"><a class="button button-primary" href="'+c.link+'">Xem điểm danh</a></div>',
                        '</div>'
                    ].join('');
                    $('.azac-grid').prepend(card);
                    $('#azac_new_class_title').val('');
                    $('#azac_new_class_teacher').val('');
                    $('#azac_new_class_sessions').val('0');
                }else{
                    alert('Không thể tạo lớp');
                }
            });
        });
    });
})(jQuery);

