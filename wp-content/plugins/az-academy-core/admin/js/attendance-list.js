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
      } else if (
        $(this).data("target") ===
        "#azac-tab-stats"
      ) {
        loadStudentStats();
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
    $(document).on(
      "click",
      ".azac-delete-btn",
      function () {
        if (!AZAC_LIST || !AZAC_LIST.isAdmin)
          return;
        var $btn = $(this);
        var id =
          parseInt($btn.data("id"), 10) || 0;
        if (!id) return;
        if (
          !confirm(
            "Bạn chắc chắn muốn xóa lớp này? Hành động không thể hoàn tác."
          )
        ) {
          return;
        }
        var payload = {
          action: "azac_delete_class",
          nonce: AZAC_LIST.deleteClassNonce,
          class_id: id,
        };
        $btn.prop("disabled", true);
        $.post(
          AZAC_LIST.ajaxUrl,
          payload,
          function (res) {
            $btn.prop("disabled", false);
            if (res && res.success) {
              location.reload();
            } else {
              alert("Không thể xóa lớp");
            }
          }
        );
      }
    );
    $(document).on(
      "click",
      ".azac-status-btn",
      function () {
        if (!AZAC_LIST || !AZAC_LIST.isAdmin)
          return;
        var $btn = $(this);
        var id =
          parseInt($btn.data("id"), 10) || 0;
        var status = String(
          $btn.data("status") || ""
        );
        if (!id || !status) return;
        var msg =
          status === "pending"
            ? "Bạn muốn đóng lớp này? Giáo viên/học viên sẽ không thao tác."
            : "Bạn muốn mở lớp này?";
        if (!confirm(msg)) return;
        var payload = {
          action: "azac_update_class_status",
          nonce: AZAC_LIST.updateStatusNonce,
          class_id: id,
          status: status,
        };
        $btn.prop("disabled", true);
        $.post(
          AZAC_LIST.ajaxUrl,
          payload,
          function (res) {
            $btn.prop("disabled", false);
            if (res && res.success) {
              location.reload();
            } else {
              alert(
                "Không thể cập nhật trạng thái lớp"
              );
            }
          }
        );
      }
    );
    function loadSessions() {
      if (
        !AZAC_LIST ||
        (!AZAC_LIST.isTeacher &&
          !AZAC_LIST.isAdmin &&
          !AZAC_LIST.isStudent)
      )
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
                  AZAC_LIST.isStudent
                    ? '">Xem điểm danh</a></div>'
                    : '">Vào điểm danh</a></div>',
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
    function renderChart(
      id,
      present,
      absent,
      title
    ) {
      var el = document.getElementById(id);
      if (!el) return;
      var existing =
        window.Chart && window.Chart.getChart
          ? window.Chart.getChart(el)
          : null;
      if (existing) {
        existing.data.datasets[0].data = [
          present,
          absent,
        ];
        existing.options.plugins.centerText.value =
          Math.round(
            (present /
              Math.max(1, present + absent)) *
              100
          ) + "%";
        existing.update();
        return;
      }
      if (typeof Chart === "undefined") return;
      var pct = Math.round(
        (present /
          Math.max(1, present + absent)) *
          100
      );
      new Chart(el, {
        type: "doughnut",
        data: {
          labels: ["Có mặt", "Vắng mặt"],
          datasets: [
            {
              data: [present, absent],
              backgroundColor:
                title === "Đầu giờ"
                  ? ["#2ecc71", "#e74c3c"]
                  : ["#3498db", "#f39c12"],
            },
          ],
        },
        options: {
          responsive: true,
          cutout: "70%",
          plugins: {
            legend: { position: "top" },
            centerText: {
              title: title,
              value: pct + "%",
            },
          },
        },
      });
    }
    function loadStudentStats() {
      if (!AZAC_LIST || !AZAC_LIST.isStudent)
        return;
      var payload = {
        action: "azac_student_stats",
        nonce: AZAC_LIST.studentStatsNonce,
      };
      var $grid = $("#azac-stats-grid").html(
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
            res.data.classes
          ) {
            if (!res.data.classes.length) {
              $grid.html(
                '<div class="azac-card"><div class="azac-card-title">Chưa có dữ liệu điểm danh</div></div>'
              );
              return;
            }
            var html = res.data.classes
              .map(function (c) {
                return [
                  '<div class="azac-card">',
                  '<div class="azac-card-title">',
                  c.title,
                  "</div>",
                  '<div class="azac-card-body">',
                  '<div class="azac-chart-row">',
                  '<div class="azac-chart-box"><canvas id="azstc-',
                  c.id,
                  '"></canvas></div>',
                  '<div class="azac-chart-box"><canvas id="azstm-',
                  c.id,
                  '"></canvas></div>',
                  "</div>",
                  "</div>",
                  '<div class="azac-card-actions"><a class="button button-primary" href="',
                  c.link,
                  '">Xem lớp</a></div>',
                  "</div>",
                ].join("");
              })
              .join("");
            $grid.html(html);
            res.data.classes.forEach(
              function (c) {
                renderChart(
                  "azstc-" + c.id,
                  parseInt(
                    c.checkin.present,
                    10
                  ) || 0,
                  parseInt(
                    c.checkin.absent,
                    10
                  ) || 0,
                  "Đầu giờ"
                );
                renderChart(
                  "azstm-" + c.id,
                  parseInt(c.mid.present, 10) ||
                    0,
                  parseInt(c.mid.absent, 10) ||
                    0,
                  "Giữa giờ"
                );
              }
            );
          } else {
            $grid.html(
              '<div class="azac-card"><div class="azac-card-title">Không tải được thống kê</div></div>'
            );
          }
        }
      );
    }
    if (AZAC_LIST && AZAC_LIST.isStudent) {
      activateTab("#azac-tab-stats");
      loadStudentStats();
    } else {
      activateTab("#azac-tab-sessions");
      if (
        AZAC_LIST &&
        (AZAC_LIST.isTeacher ||
          AZAC_LIST.isAdmin ||
          AZAC_LIST.isStudent)
      ) {
        loadSessions();
      }
    }
  });
})(jQuery);
