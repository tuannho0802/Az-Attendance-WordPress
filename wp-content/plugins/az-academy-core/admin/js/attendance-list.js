;(function ($) {
  $(function () {
    function activateTab(target) {
      $(".azac-tab-btn").removeClass(
        "button-primary"
      );
      $(".azac-tab").removeClass("active");
      $(target).addClass("active");
      $(
        '.azac-tab-btn[data-target="' +
          target +
          '"]'
      ).addClass("button-primary");
    }
    $(".azac-tab-btn").on("click", function () {
      activateTab($(this).data("target"));
      if (
        $(this).data("target") ===
        "#azac-tab-sessions"
      ) {
        loadSessions();
      }
    });
    $("#azac_create_class_btn").on(
      "click",
      function () {
        var title = $("#azac_new_class_title")
          .val()
          .trim();
        var teacher = $(
          "#azac_new_class_teacher"
        )
          .val()
          .trim();
        var sessions =
          parseInt(
            $("#azac_new_class_sessions").val(),
            10
          ) || 0;
        if (!title) {
          alert("Nhập tên lớp");
          return;
        }
        var payload = {
          action: "azac_create_class",
          nonce: AZAC_LIST.nonce,
          title: title,
          teacher: teacher,
          sessions: sessions,
        };
        var btn = $(this).prop(
          "disabled",
          true
        );
        $.post(
          AZAC_LIST.ajaxUrl,
          payload,
          function (res) {
            btn.prop("disabled", false);
            if (res && res.success) {
              var c = res.data;
              var card = [
                '<div class="azac-card">',
                '<div class="azac-card-title">' +
                  c.title +
                  "</div>",
                '<div class="azac-card-body">',
                "<div>Giảng viên: " +
                  teacher +
                  "</div>",
                "<div>Tổng số buổi: " +
                  sessions +
                  "</div>",
                "<div>Số học viên: 0</div>",
                "</div>",
                '<div class="azac-card-actions"><a class="button button-primary" href="' +
                  c.link +
                  '">Xem điểm danh</a></div>',
                "</div>",
              ].join("");
              $(
                "#azac-tab-classes .azac-grid"
              ).prepend(card);
              $("#azac_new_class_title").val(
                ""
              );
              $("#azac_new_class_teacher").val(
                ""
              );
              $("#azac_new_class_sessions").val(
                "0"
              );
            } else {
              alert("Không thể tạo lớp");
            }
          }
        );
      }
    );
    function loadSessions() {
      if (!AZAC_LIST || !AZAC_LIST.isTeacher)
        return;
      var payload = {
        action: "azac_list_sessions",
        nonce: AZAC_LIST.listSessionsNonce,
      };
      var $grid = $("#azac-sessions-grid").html(
        '<div class="azac-card"><div class="azac-card-title">Đang tải...</div></div>'
      );
      $.post(
        AZAC_LIST.ajaxUrl,
        payload,
        function (res) {
          if (
            res &&
            res.success &&
            res.data &&
            res.data.sessions
          ) {
            if (!res.data.sessions.length) {
              $grid.html(
                '<div class="azac-card"><div class="azac-card-title">Chưa có buổi học nào</div></div>'
              );
              return;
            }
            var html = res.data.sessions
              .map(function (s) {
                return [
                  '<div class="azac-card">',
                  '<div class="azac-card-title">',
                  s.class_title,
                  " • ",
                  s.date,
                  s.time ? " " + s.time : "",
                  "</div>",
                  '<div class="azac-card-body">',
                  "<div>Lớp: ",
                  s.class_title,
                  "</div>",
                  "<div>Ngày: ",
                  s.date,
                  "</div>",
                  "<div>Giờ: ",
                  s.time || "",
                  "</div>",
                  "</div>",
                  '<div class="azac-card-actions"><a class="button button-primary" href="',
                  s.link,
                  '">Vào điểm danh</a></div>',
                  "</div>",
                ].join("");
              })
              .join("");
            $grid.html(html);
          } else {
            $grid.html(
              '<div class="azac-card"><div class="azac-card-title">Không tải được danh sách buổi</div></div>'
            );
          }
        }
      );
    }
    activateTab("#azac-tab-classes");
    if (AZAC_LIST && AZAC_LIST.isTeacher) {
      // optionally preload sessions
    }
  });
})(jQuery);
