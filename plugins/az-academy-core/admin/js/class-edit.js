;(function($){
    $(function(){
        function recalcStudentCount(){
            var count = $('input[name="az_students[]"]:checked').length;
            $('#az_so_hoc_vien').val(count);
        }
        if (
          window.AZAC_CLASS_EDIT &&
          AZAC_CLASS_EDIT.isTeacher
        ) {
          function hideSubmit() {
            var nodes =
              document.querySelectorAll(
                "button, a"
              );
            nodes.forEach(function (el) {
              var txt = (el.textContent || "")
                .trim()
                .toLowerCase();
              var aria = (
                el.getAttribute("aria-label") ||
                ""
              ).toLowerCase();
              if (
                txt === "submit for review" ||
                txt === "gửi xét duyệt" ||
                aria.indexOf(
                  "submit for review"
                ) !== -1 ||
                aria.indexOf(
                  "gửi xét duyệt"
                ) !== -1
              ) {
                el.style.display = "none";
              }
            });
          }
          hideSubmit();
          var obs = new MutationObserver(
            function () {
              hideSubmit();
            }
          );
          obs.observe(document.body, {
            childList: true,
            subtree: true,
          });
        }
        $('#azac_add_student_btn').on('click', function(){
            var name = $('#azac_new_student_name').val().trim();
            var email = $('#azac_new_student_email').val().trim();
            var classId = $(this).data('class');
            if(!name){ alert('Nhập họ tên học viên'); return; }
            var payload = {
                action: 'azac_add_student',
                nonce: window.azacClassEditData.nonce,
                name: name,
                email: email,
                class_id: classId
            };
            var btn = $(this);
            btn.prop('disabled', true);
            $.post(window.azacClassEditData.ajaxUrl, payload, function(res){
                btn.prop('disabled', false);
                if(res && res.success){
                    var id = res.data.id, title = res.data.title;
                    var label = $('<label/>');
                    var input = $('<input/>', {type:'checkbox', name:'az_students[]', value:id, checked:true});
                    label.append(input).append(' '+title);
                    $('#azac_students_grid').prepend(label);
                    $('#azac_new_student_name').val('');
                    $('#azac_new_student_email').val('');
                    recalcStudentCount();
                } else {
                    alert('Lỗi tạo học viên');
                }
            });
        });
        $(document).on('change', 'input[name="az_students[]"]', function(){
            recalcStudentCount();
        });
        recalcStudentCount();
    });
})(jQuery);
