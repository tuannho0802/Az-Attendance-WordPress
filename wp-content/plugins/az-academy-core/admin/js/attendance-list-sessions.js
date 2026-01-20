(function ($) {
  $(function () {
    $("#azac_create_class_btn").on(
      "click",
      function () {
        var title = $("#azac_new_class_title")
          .val()
          .trim();
        var teacherId =
          parseInt(
            $("#azac_new_class_teacher").val(),
            10,
          ) || 0;
        if (!title) {
          alert("Nhập tên lớp");
          return;
        }
        var payload = {
          action: "azac_create_class",
          nonce: AZAC_LIST && AZAC_LIST.nonce,
          title: title,
          teacher_id: teacherId,
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
              location.reload();
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
    $(
      '.azac-tab-btn[data-target="#azac-tab-sessions"]'
    ).on("click", function () {
      activateTab("#azac-tab-sessions");
      loadSessions();
    });
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
            var SESS =
              res.data.sessions.slice();
            var GROUP =
              $("#azac-filter-group").val() ||
              "session";
            var SORT =
              $("#azac-filter-sort").val() ||
              "date_desc";
            var CLASS =
              $("#azac-filter-class").val() ||
              "";
            function uniqClasses(arr) {
              var map = {};
              arr.forEach(function (s) {
                map[s.class_id] = s.class_title;
              });
              return Object.keys(map).map(
                function (k) {
                  return {
                    id: parseInt(k, 10),
                    title: map[k],
                  };
                }
              );
            }
            function fillClassFilter() {
              var list = uniqClasses(SESS);
              var $sel = $(
                "#azac-filter-class"
              );
              var opts = [
                '<option value="">Tất cả</option>',
              ].concat(
                list.map(function (c) {
                  return (
                    '<option value="' +
                    c.id +
                    '">' +
                    c.title +
                    "</option>"
                  );
                })
              );
              $sel.html(opts.join(""));
            }
            function sortSessions(arr) {
              var a = arr.slice();
              if (SORT === "date_asc") {
                a.sort(function (x, y) {
                  return x.date < y.date
                    ? -1
                    : x.date > y.date
                      ? 1
                      : 0;
                });
              } else if (SORT === "rate_desc") {
                a.sort(function (x, y) {
                  var rx =
                    (x.rate &&
                      x.rate.overall) ||
                    0;
                  var ry =
                    (y.rate &&
                      y.rate.overall) ||
                    0;
                  return ry - rx;
                });
              } else if (SORT === "rate_asc") {
                a.sort(function (x, y) {
                  var rx =
                    (x.rate &&
                      x.rate.overall) ||
                    0;
                  var ry =
                    (y.rate &&
                      y.rate.overall) ||
                    0;
                  return rx - ry;
                });
              } else {
                a.sort(function (x, y) {
                  return x.date > y.date
                    ? -1
                    : x.date < y.date
                      ? 1
                      : 0;
                });
              }
              return a;
            }
            function renderInlineBar(
              id,
              presentTotal,
              absentTotal,
            ) {
              var el =
                document.getElementById(id);
              if (!el) return;
              var ex =
                window.Chart &&
                window.Chart.getChart
                  ? window.Chart.getChart(el)
                  : null;
              var p = presentTotal || 0;
              var a = absentTotal || 0;
              if (ex) {
                ex.data.datasets[0].data = [
                  p,
                  a,
                ];
                ex.update();
                return;
              }
              if (typeof Chart === "undefined")
                return;
              new Chart(el, {
                type: "pie",
                data: {
                  labels: ["Có mặt", "Vắng"],
                  datasets: [
                    {
                      data: [p, a],
                      backgroundColor: [
                        "#2ecc71",
                        "#e74c3c",
                      ],
                      borderWidth: 0,
                    },
                  ],
                },
                options: {
                  responsive: false,
                  maintainAspectRatio: false,
                  plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false },
                  },
                },
              });
            }
            function renderGroupedByClass() {
              var byClass = {};
              SESS.forEach(function (s) {
                if (
                  CLASS &&
                  String(s.class_id) !==
                    String(CLASS)
                )
                  return;
                var k = s.class_id;
                if (!byClass[k]) {
                  byClass[k] = {
                    class_id: s.class_id,
                    class_title: s.class_title,
                    link:
                      "admin.php?page=azac-classes-list&class_id=" +
                      s.class_id,
                    checkin: {
                      present: 0,
                      absent: 0,
                    },
                    mid: {
                      present: 0,
                      absent: 0,
                    },
                    latest: s.date,
                    total: parseInt(s.total, 10) || 0,
                    _session_count: 0,
                  };
                }
                byClass[k]._session_count++;
                if (!byClass[k].total) {
                  byClass[k].total = parseInt(s.total, 10) || byClass[k].total;
                }
                byClass[k].checkin.present +=
                  (s.checkin &&
                    s.checkin.present) ||
                  0;
                byClass[k].checkin.absent +=
                  (s.checkin &&
                    s.checkin.absent) ||
                  0;
                byClass[k].mid.present +=
                  (s.mid && s.mid.present) || 0;
                byClass[k].mid.absent +=
                  (s.mid && s.mid.absent) || 0;
                if (s.date > byClass[k].latest)
                  byClass[k].latest = s.date;
              });
              var arr = Object.keys(
                byClass,
              ).map(function (k) {
                var o = byClass[k];
                var present =
                  o.checkin.present +
                    o.mid.present || 0;
                var totalSessions =
                  o._session_count || 0;
                var totalRoster = o.total || 0;
                var grandTotal = Math.max(
                  1,
                  totalSessions *
                    2 *
                    totalRoster,
                );
                var absent = Math.max(
                  0,
                  grandTotal - present,
                );
                var rate =
                  Math.round(
                    (present / grandTotal) *
                      100,
                  ) || 0;
                return {
                  class_id: o.class_id,
                  class_title: o.class_title,
                  link: o.link,
                  checkin: o.checkin,
                  mid: o.mid,
                  total: o.total,
                  grandTotal: grandTotal,
                  presentSum: present,
                  rate: rate,
                  latest: o.latest,
                };
              });
              if (SORT === "rate_desc") {
                arr.sort(function (a, b) {
                  return b.rate - a.rate;
                });
              } else if (SORT === "rate_asc") {
                arr.sort(function (a, b) {
                  return a.rate - b.rate;
                });
              } else if (SORT === "date_asc") {
                arr.sort(function (a, b) {
                  return a.latest < b.latest
                    ? -1
                    : a.latest > b.latest
                      ? 1
                      : 0;
                });
              } else {
                arr.sort(function (a, b) {
                  return a.latest > b.latest
                    ? -1
                    : a.latest < b.latest
                      ? 1
                      : 0;
                });
              }
              var html = arr
                .map(function (c) {
                  var color =
                    window.AZACU &&
                    typeof window.AZACU
                      .getClassColor ===
                      "function"
                      ? window.AZACU.getClassColor(
                          c.class_id,
                        )
                      : "#3498db";
                  var p = c.presentSum || 0;
                  var t = Math.max(
                    1,
                    c.grandTotal || 0,
                  );
                  var a = Math.max(0, t - p);
                  var pp =
                    Math.round((p / t) * 100) ||
                    0;
                  var ap =
                    Math.round((a / t) * 100) ||
                    0;
                  return [
                    '<div class="azac-card">',
                    '<div class="azac-card-title">',
                    '<div class="azac-title-row">',
                    '<div class="azac-title-left">',
                    c.class_title,
                    ' <span class="azac-date-badge" style="background:',
                    color,
                    '">',
                    c.latest,
                    "</span>",
                    "</div>",
                    "</div>",
                    "</div>",
                    '<div class="azac-card-body">',
                    '<div class="azac-body-row">',
                    '<div class="azac-body-left">',
                    "<div>Tỉ lệ có mặt: ",
                    c.rate,
                    "%</div>",
                    "</div>",
                    '<div class="azac-body-right">',
                    '<div class="azac-inline-legend">',
                    '<span class="azac-mini-badge azac-mini-badge--absent">',
                    ap,
                    "% Vắng</span>",
                    '<span class="azac-mini-badge azac-mini-badge--present">',
                    pp,
                    "% Có mặt</span>",
                    "</div>",
                    '<div class="azac-body-pie"><canvas id="azpie-class-',
                    c.class_id,
                    '"></canvas></div>',
                    "</div>",
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
              if (
                window.AZACU &&
                typeof window.AZACU
                  .ensureChart === "function"
              ) {
                window.AZACU.ensureChart(
                  function () {
                    arr.forEach(function (c) {
                      renderInlineBar(
                        "azpie-class-" +
                          c.class_id,
                        c.presentSum || 0,
                        Math.max(
                          0,
                          (c.grandTotal || 0) -
                            (c.presentSum || 0),
                        ),
                      );
                    });
                  },
                );
              }
            }
            function renderSessions() {
              var filtered = SESS.filter(
                function (s) {
                  if (
                    CLASS &&
                    String(s.class_id) !==
                      String(CLASS)
                  )
                    return false;
                  return true;
                },
              );
              var arr = sortSessions(filtered);
              var html = arr
                .map(function (s) {
                  var id =
                    s.class_id +
                    "-" +
                    String(s.date).replace(
                      /[^0-9]/g,
                      "",
                    );
                  var color =
                    window.AZACU &&
                    typeof window.AZACU
                      .getClassColor ===
                      "function"
                      ? window.AZACU.getClassColor(
                          s.class_id,
                        )
                      : "#3498db";
                  var total =
                    parseInt(s.total, 10) || 0;
                  var p =
                    parseInt(
                      (s.checkin &&
                        s.checkin.present) ||
                        0,
                      10,
                    ) +
                      parseInt(
                        (s.mid &&
                          s.mid.present) ||
                          0,
                        10,
                      ) || 0;
                  var t = Math.max(
                    1,
                    total * 2,
                  );
                  var a = Math.max(0, t - p);
                  var pp =
                    Math.round((p / t) * 100) ||
                    0;
                  var ap =
                    Math.round((a / t) * 100) ||
                    0;
                  return [
                    '<div class="azac-card">',
                    '<div class="azac-card-title">',
                    '<div class="azac-title-row">',
                    '<div class="azac-title-left">',
                    s.class_title,
                    ' <span class="azac-date-badge" style="background:',
                    color,
                    '">',
                    s.date,
                    "</span>",
                    "</div>",
                    "</div>",
                    "</div>",
                    '<div class="azac-card-body">',
                    '<div class="azac-body-row">',
                    '<div class="azac-body-left">',
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
                    '<div class="azac-body-right">',
                    '<div class="azac-inline-legend">',
                    '<span class="azac-mini-badge azac-mini-badge--absent">',
                    ap,
                    "% Vắng</span>",
                    '<span class="azac-mini-badge azac-mini-badge--present">',
                    pp,
                    "% Có mặt</span>",
                    "</div>",
                    '<div class="azac-body-pie"><canvas id="azpie-sess-',
                    id,
                    '"></canvas></div>',
                    "</div>",
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
              if (
                window.AZACU &&
                typeof window.AZACU
                  .ensureChart === "function"
              ) {
                window.AZACU.ensureChart(
                  function () {
                    arr.forEach(function (s) {
                      var id =
                        s.class_id +
                        "-" +
                        String(s.date).replace(
                          /[^0-9]/g,
                          "",
                        );
                      var total =
                        parseInt(s.total, 10) ||
                        0;
                      var p =
                        parseInt(
                          (s.checkin &&
                            s.checkin
                              .present) ||
                            0,
                          10,
                        ) +
                          parseInt(
                            (s.mid &&
                              s.mid.present) ||
                              0,
                            10,
                          ) || 0;
                      var t = Math.max(
                        1,
                        total * 2,
                      );
                      var a = Math.max(
                        0,
                        t - p,
                      );
                      renderInlineBar(
                        "azpie-sess-" + id,
                        p,
                        a,
                      );
                    });
                  },
                );
              }
            }
            fillClassFilter();
            $("#azac-filter-group")
              .off("change")
              .on("change", function () {
                GROUP =
                  $(this).val() || "session";
                if (GROUP === "class")
                  renderGroupedByClass();
                else renderSessions();
              });
            $("#azac-filter-sort")
              .off("change")
              .on("change", function () {
                SORT =
                  $(this).val() || "date_desc";
                if (GROUP === "class")
                  renderGroupedByClass();
                else renderSessions();
              });
            $("#azac-filter-class")
              .off("change")
              .on("change", function () {
                CLASS = $(this).val() || "";
                if (GROUP === "class")
                  renderGroupedByClass();
                else renderSessions();
              });
            if (GROUP === "class")
              renderGroupedByClass();
            else renderSessions();
          } else {
            $grid.html(
              '<div class="azac-card"><div class="azac-card-title">Không tải được danh sách buổi</div></div>'
            );
          }
        }
      );
    }
    if (AZAC_LIST && !AZAC_LIST.isStudent) {
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
