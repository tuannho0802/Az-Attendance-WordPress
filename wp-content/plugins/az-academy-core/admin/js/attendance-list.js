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
            var CLASS_COLOR_MAP = {};
            var PALETTE = [
              "#2ecc71",
              "#3498db",
              "#9b59b6",
              "#e67e22",
              "#1abc9c",
              "#e74c3c",
              "#16a085",
              "#2980b9",
              "#8e44ad",
              "#d35400",
              "#27ae60",
              "#f39c12",
              "#34495e",
              "#7f8c8d",
              "#e84393",
              "#00cec9",
              "#6c5ce7",
              "#fdcb6e",
              "#00b894",
              "#0984e3",
              "#d63031",
              "#ff7675",
              "#636e72",
              "#55efc4",
              "#a29bfe",
              "#fab1a0",
              "#74b9ff",
              "#b2bec3",
              "#ff6b6b",
              "#4dabf7",
              "#f4a261",
              "#2a9d8f",
              "#e76f51",
              "#264653",
            ];
            function getClassColor(cid) {
              var key = String(cid);
              if (CLASS_COLOR_MAP[key])
                return CLASS_COLOR_MAP[key];
              var n = parseInt(cid, 10);
              var idx = Math.abs(
                (n * 9301 + 49297) %
                  PALETTE.length
              );
              var color = PALETTE[idx];
              CLASS_COLOR_MAP[key] = color;
              return color;
            }
            function renderInlineBar(
              id,
              ciPresent,
              ciAbsent,
              midPresent,
              midAbsent
            ) {
              var el =
                document.getElementById(id);
              if (!el) return;
              var ex =
                window.Chart &&
                window.Chart.getChart
                  ? window.Chart.getChart(el)
                  : null;
              var p =
                (ciPresent || 0) +
                (midPresent || 0);
              var a =
                (ciAbsent || 0) +
                (midAbsent || 0);
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
            function renderMini(
              id,
              present,
              absent,
              colorA,
              colorB
            ) {
              var el =
                document.getElementById(id);
              if (!el) return;
              var ex =
                window.Chart &&
                window.Chart.getChart
                  ? window.Chart.getChart(el)
                  : null;
              if (ex) {
                ex.data.datasets[0].data = [
                  present || 0,
                  absent || 0,
                ];
                ex.update();
                return;
              }
              if (typeof Chart === "undefined")
                return;
              new Chart(el, {
                type: "doughnut",
                data: {
                  datasets: [
                    {
                      data: [
                        present || 0,
                        absent || 0,
                      ],
                      backgroundColor: [
                        colorA,
                        colorB,
                      ],
                    },
                  ],
                },
                options: {
                  responsive: false,
                  maintainAspectRatio: false,
                  cutout: "70%",
                  plugins: {
                    legend: { display: false },
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
                      "admin.php?page=azac-class-dashboard&class_id=" +
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
                  };
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
                byClass
              ).map(function (k) {
                var o = byClass[k];
                var rp =
                  o.checkin.present +
                    o.mid.present || 0;
                var ra =
                  o.checkin.absent +
                    o.mid.absent || 0;
                var rate =
                  Math.round(
                    (rp /
                      Math.max(1, rp + ra)) *
                      100
                  ) || 0;
                return {
                  class_id: o.class_id,
                  class_title: o.class_title,
                  link: o.link,
                  checkin: o.checkin,
                  mid: o.mid,
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
                  var color = getClassColor(
                    c.class_id
                  );
                  var p =
                    (c.checkin &&
                      c.checkin.present) +
                      (c.mid &&
                        c.mid.present) || 0;
                  var a =
                    (c.checkin &&
                      c.checkin.absent) +
                      (c.mid && c.mid.absent) ||
                    0;
                  var t = Math.max(1, p + a);
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
              ensureChart(function () {
                arr.forEach(function (c) {
                  renderInlineBar(
                    "azpie-class-" + c.class_id,
                    c.checkin.present,
                    c.checkin.absent,
                    c.mid.present,
                    c.mid.absent
                  );
                });
              });
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
                }
              );
              var arr = sortSessions(filtered);
              var html = arr
                .map(function (s) {
                  var id =
                    s.class_id +
                    "-" +
                    String(s.date).replace(
                      /[^0-9]/g,
                      ""
                    );
                  var color = getClassColor(
                    s.class_id
                  );
                  var p =
                    ((s.checkin &&
                      s.checkin.present) ||
                      (s.checkin === 1
                        ? 1
                        : 0)) +
                    ((s.mid && s.mid.present) ||
                      (s.mid === 1 ? 1 : 0));
                  var a =
                    ((s.checkin &&
                      s.checkin.absent) ||
                      (s.checkin === 0
                        ? 1
                        : 0)) +
                    ((s.mid && s.mid.absent) ||
                      (s.mid === 0 ? 1 : 0));
                  var t = Math.max(1, p + a);
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
              ensureChart(function () {
                arr.forEach(function (s) {
                  var id =
                    s.class_id +
                    "-" +
                    String(s.date).replace(
                      /[^0-9]/g,
                      ""
                    );
                  renderInlineBar(
                    "azpie-sess-" + id,
                    (s.checkin &&
                      s.checkin.present) ||
                      0,
                    (s.checkin &&
                      s.checkin.absent) ||
                      0,
                    (s.mid && s.mid.present) ||
                      0,
                    (s.mid && s.mid.absent) || 0
                  );
                });
              });
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
    function renderSessionChart(id, sessions) {
      var el = document.getElementById(id);
      if (!el) return;
      var existing =
        window.Chart && window.Chart.getChart
          ? window.Chart.getChart(el)
          : null;
      var labels = sessions.map(function (s) {
        return s.date;
      });
      var checkinPresent = sessions.map(
        function (s) {
          return s.checkin === 1 ? 1 : 0;
        }
      );
      var checkinAbsent = sessions.map(
        function (s) {
          return s.checkin === 0 ? 1 : 0;
        }
      );
      var checkinUnknown = sessions.map(
        function (s) {
          return s.checkin === null ||
            typeof s.checkin === "undefined"
            ? 1
            : 0;
        }
      );
      var midPresent = sessions.map(
        function (s) {
          return s.mid === 1 ? 1 : 0;
        }
      );
      var midAbsent = sessions.map(
        function (s) {
          return s.mid === 0 ? 1 : 0;
        }
      );
      var midUnknown = sessions.map(
        function (s) {
          return s.mid === null ||
            typeof s.mid === "undefined"
            ? 1
            : 0;
        }
      );
      if (existing) {
        existing.data.labels = labels;
        existing.data.datasets[0].data =
          checkinPresent;
        existing.data.datasets[1].data =
          checkinAbsent;
        existing.data.datasets[2].data =
          checkinUnknown;
        existing.data.datasets[3].data =
          midPresent;
        existing.data.datasets[4].data =
          midAbsent;
        existing.data.datasets[5].data =
          midUnknown;
        existing.update();
        return;
      }
      if (typeof Chart === "undefined") return;
      var chart = new Chart(el, {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            {
              label: "Đầu giờ - Có mặt",
              data: checkinPresent,
              backgroundColor: "#2ecc71",
              stack: "checkin",
            },
            {
              label: "Đầu giờ - Vắng mặt",
              data: checkinAbsent,
              backgroundColor: "#e74c3c",
              stack: "checkin",
            },
            {
              label: "Đầu giờ - Chưa có",
              data: checkinUnknown,
              backgroundColor: "#bdc3c7",
              stack: "checkin",
            },
            {
              label: "Giữa giờ - Có mặt",
              data: midPresent,
              backgroundColor: "#3498db",
              stack: "mid",
            },
            {
              label: "Giữa giờ - Vắng mặt",
              data: midAbsent,
              backgroundColor: "#f39c12",
              stack: "mid",
            },
            {
              label: "Giữa giờ - Chưa có",
              data: midUnknown,
              backgroundColor: "#dfe6e9",
              stack: "mid",
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: "top" },
            title: {
              display: true,
              text: "Các buổi",
            },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  var d =
                    ctx.dataset.label || "";
                  return d;
                },
              },
            },
          },
          scales: {
            y: {
              beginAtZero: true,
              suggestedMax: 1,
              ticks: {
                stepSize: 1,
                callback: function (v) {
                  return v === 1
                    ? "Có mặt"
                    : "Vắng";
                },
              },
            },
            x: {
              stacked: true,
            },
          },
          onClick: function (evt, elements, c) {
            var els =
              c.getElementsAtEventForMode(
                evt,
                "nearest",
                { intersect: true },
                true
              ) || [];
            if (!els.length) return;
            var idx = els[0].index;
            var s = sessions[idx];
            if (s && s.link) {
              window.location.href = s.link;
            }
          },
        },
      });
      return chart;
    }
    function ensureChart(callback) {
      if (typeof Chart !== "undefined") {
        try {
          callback();
        } catch (e) {}
        return;
      }
      var s = document.createElement("script");
      s.src =
        "https://cdn.jsdelivr.net/npm/chart.js";
      s.async = true;
      s.onload = function () {
        try {
          callback();
        } catch (e) {}
      };
      document.head.appendChild(s);
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
                var total = Array.isArray(
                  c.sessions
                )
                  ? c.sessions.length
                  : 0;
                var full = 0;
                var absent = 0;
                var unknown = 0;
                if (Array.isArray(c.sessions)) {
                  c.sessions.forEach(
                    function (s) {
                      if (
                        s.checkin === 1 &&
                        s.mid === 1
                      )
                        full++;
                      else if (
                        s.checkin === 0 ||
                        s.mid === 0
                      )
                        absent++;
                      else unknown++;
                    }
                  );
                }
                var rate = Math.round(
                  (full / Math.max(1, total)) *
                    100
                );
                return [
                  '<div class="azac-card">',
                  '<div class="azac-card-title">',
                  c.title,
                  "</div>",
                  '<div class="azac-card-body">',
                  '<div class="azac-metrics">',
                  "<span>Buổi: ",
                  total,
                  "</span> • <span>Đi đủ: ",
                  full,
                  "</span> • <span>Vắng: ",
                  absent,
                  "</span> • <span>Tỉ lệ đi đủ: ",
                  rate,
                  "%</span>",
                  "</div>",
                  '<div class="azac-chart-row">',
                  '<div class="azac-chart-box"><canvas id="azstc-',
                  c.id,
                  '"></canvas></div>',
                  '<div class="azac-chart-box"><canvas id="azstm-',
                  c.id,
                  '"></canvas></div>',
                  "</div>",
                  '<div class="azac-chart-row">',
                  '<div class="azac-chart-box" style="width:100%"><canvas id="azsbar-',
                  c.id,
                  '"></canvas></div>',
                  "</div>",
                  '<div class="azac-sessions-list">',
                  Array.isArray(c.sessions)
                    ? c.sessions
                        .map(function (s) {
                          var ciText =
                            s.checkin ===
                              null ||
                            typeof s.checkin ===
                              "undefined"
                              ? "Chưa có"
                              : s.checkin === 1
                                ? "Có mặt"
                                : "Vắng mặt";
                          var ciClass =
                            s.checkin ===
                              null ||
                            typeof s.checkin ===
                              "undefined"
                              ? "azac-badge azac-badge--ci-unknown"
                              : s.checkin === 1
                                ? "azac-badge azac-badge--ci-present"
                                : "azac-badge azac-badge--ci-absent";
                          var miText =
                            s.mid === null ||
                            typeof s.mid ===
                              "undefined"
                              ? "Chưa có"
                              : s.mid === 1
                                ? "Có mặt"
                                : "Vắng mặt";
                          var miClass =
                            s.mid === null ||
                            typeof s.mid ===
                              "undefined"
                              ? "azac-badge azac-badge--mid-unknown"
                              : s.mid === 1
                                ? "azac-badge azac-badge--mid-present"
                                : "azac-badge azac-badge--mid-absent";
                          return [
                            '<div class="azac-session-row">',
                            '<span class="azac-badge azac-badge--label">',
                            s.date,
                            "</span>",
                            '<span class="azac-badge azac-badge--label">Đầu giờ</span>',
                            '<span class="',
                            ciClass,
                            '">',
                            ciText,
                            "</span>",
                            '<span class="azac-badge azac-badge--label">Giữa giờ</span>',
                            '<span class="',
                            miClass,
                            '">',
                            miText,
                            "</span>",
                            "</div>",
                          ].join("");
                        })
                        .join("")
                    : "",
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
            ensureChart(function () {
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
                    parseInt(
                      c.mid.present,
                      10
                    ) || 0,
                    parseInt(
                      c.mid.absent,
                      10
                    ) || 0,
                    "Giữa giờ"
                  );
                  if (
                    Array.isArray(c.sessions)
                  ) {
                    renderSessionChart(
                      "azsbar-" + c.id,
                      c.sessions
                    );
                  }
                }
              );
            });
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
