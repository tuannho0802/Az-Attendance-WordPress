;(function ($) {
  $(function () {
    function activateTab(target) {
      $(".azac-tab-btn").removeClass("button-primary");
      $(".azac-tab").removeClass("active");
      $(target).addClass("active");
      $('.azac-tab-btn[data-target="' + target + '"]').addClass("button-primary");
    }
    $('.azac-tab-btn[data-target="#azac-tab-stats"]').on("click", function () {
      activateTab("#azac-tab-stats");
      loadStudentStats();
    });
    function renderChart(id, present, absent, title) {
      var el = document.getElementById(id);
      if (!el) return;
      var existing =
        window.Chart && window.Chart.getChart
          ? window.Chart.getChart(el)
          : null;
      if (existing) {
        existing.data.datasets[0].data = [present, absent];
        existing.options.plugins.centerText.value =
          Math.round(
            (present / Math.max(1, present + absent)) * 100
          ) + "%";
        existing.update();
        return;
      }
      if (typeof Chart === "undefined") return;
      var pct = Math.round(
        (present / Math.max(1, present + absent)) * 100
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
      var checkinPresent = sessions.map(function (s) {
        return s.checkin === 1 ? 1 : 0;
      });
      var checkinAbsent = sessions.map(function (s) {
        return s.checkin === 0 ? 1 : 0;
      });
      var checkinUnknown = sessions.map(function (s) {
        return s.checkin === null || typeof s.checkin === "undefined" ? 1 : 0;
      });
      var midPresent = sessions.map(function (s) {
        return s.mid === 1 ? 1 : 0;
      });
      var midAbsent = sessions.map(function (s) {
        return s.mid === 0 ? 1 : 0;
      });
      var midUnknown = sessions.map(function (s) {
        return s.mid === null || typeof s.mid === "undefined" ? 1 : 0;
      });
      if (existing) {
        existing.data.labels = labels;
        existing.data.datasets[0].data = checkinPresent;
        existing.data.datasets[1].data = checkinAbsent;
        existing.data.datasets[2].data = checkinUnknown;
        existing.data.datasets[3].data = midPresent;
        existing.data.datasets[4].data = midAbsent;
        existing.data.datasets[5].data = midUnknown;
        existing.update();
        return;
      }
      if (typeof Chart === "undefined") return;
      var chart = new Chart(el, {
        type: "bar",
        data: {
          labels: labels,
          datasets: [
            { label: "Đầu giờ - Có mặt", data: checkinPresent, backgroundColor: "#2ecc71", stack: "checkin" },
            { label: "Đầu giờ - Vắng mặt", data: checkinAbsent, backgroundColor: "#e74c3c", stack: "checkin" },
            { label: "Đầu giờ - Chưa có", data: checkinUnknown, backgroundColor: "#bdc3c7", stack: "checkin" },
            { label: "Giữa giờ - Có mặt", data: midPresent, backgroundColor: "#3498db", stack: "mid" },
            { label: "Giữa giờ - Vắng mặt", data: midAbsent, backgroundColor: "#f39c12", stack: "mid" },
            { label: "Giữa giờ - Chưa có", data: midUnknown, backgroundColor: "#dfe6e9", stack: "mid" },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: "top" },
            title: { display: true, text: "Các buổi" },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  var d = ctx.dataset.label || "";
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
                  return v === 1 ? "Có mặt" : "Vắng";
                },
              },
            },
            x: { stacked: true },
          },
          onClick: function (evt, elements, c) {
            var els =
              c.getElementsAtEventForMode(evt, "nearest", { intersect: true }, true) || [];
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
    function loadStudentStats() {
      if (!AZAC_LIST || !AZAC_LIST.isStudent) return;
      var payload = {
        action: "azac_student_stats",
        nonce: AZAC_LIST.studentStatsNonce,
      };
      var $grid = $("#azac-stats-grid").html(
        '<div class="azac-card"><div class="azac-card-title">Đang tải...</div></div>'
      );
      $.post(AZAC_LIST.ajaxUrl, payload, function (res) {
        if (res && res.success && res.data && res.data.classes) {
          if (!res.data.classes.length) {
            $grid.html(
              '<div class="azac-card"><div class="azac-card-title">Chưa có dữ liệu điểm danh</div></div>'
            );
            return;
          }
          var html = res.data.classes
            .map(function (c) {
              // Hash Color Logic (Unified)
              var palette = (AZAC_LIST && AZAC_LIST.palette) ? AZAC_LIST.palette : ["#15345a"];
              var color = "#15345a";
              
              if (palette.length > 0) {
                  var hash = 0;
                  var cName = c.title || "";
                  if (window.TextEncoder) {
                    var encoder = new TextEncoder();
                    var bytes = encoder.encode(cName);
                    for (var i = 0; i < bytes.length; i++) {
                      hash += bytes[i];
                    }
                  } else {
                    for (var i = 0; i < cName.length; i++) {
                        hash += cName.charCodeAt(i);
                    }
                  }
                  color = palette[hash % palette.length];
              }

              var total = Array.isArray(c.sessions) ? c.sessions.length : 0;
              var full = 0;
              var absent = 0;
              var unknown = 0;
              if (Array.isArray(c.sessions)) {
                c.sessions.forEach(function (s) {
                  if (s.checkin === 1 && s.mid === 1) full++;
                  else if (s.checkin === 0 || s.mid === 0) absent++;
                  else unknown++;
                });
              }
              var rate = Math.round((full / Math.max(1, total)) * 100);
              return [
                '<div class="azac-card">',
                '<div class="azac-card-title" style="background-color: ' +
                  color +
                  ' !important; color: #fff;">' +
                  c.title +
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
                          s.checkin === null ||
                          typeof s.checkin ===
                            "undefined"
                            ? "Chưa có"
                            : s.checkin === 1
                              ? "Có mặt"
                              : "Vắng mặt";
                        var ciClass =
                          s.checkin === null ||
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
                '<div class="azac-card-actions azac-actions--single"><a class="button button-primary" href="',
                c.link,
                '">Xem lớp</a></div>',
                "</div>",
              ].join("");
            })
            .join("");
          $grid.html(html);
          if (window.AZACU && typeof window.AZACU.ensureChart === "function") {
            window.AZACU.ensureChart(function () {
              res.data.classes.forEach(function (c) {
                renderChart(
                  "azstc-" + c.id,
                  parseInt(c.checkin.present, 10) || 0,
                  parseInt(c.checkin.absent, 10) || 0,
                  "Đầu giờ"
                );
                renderChart(
                  "azstm-" + c.id,
                  parseInt(c.mid.present, 10) || 0,
                  parseInt(c.mid.absent, 10) || 0,
                  "Giữa giờ"
                );
                if (Array.isArray(c.sessions)) {
                  renderSessionChart("azsbar-" + c.id, c.sessions);
                }
              });
            });
          }
        } else {
          $grid.html(
            '<div class="azac-card"><div class="azac-card-title">Không tải được thống kê</div></div>'
          );
        }
      });
    }
    if (AZAC_LIST && AZAC_LIST.isStudent) {
      activateTab("#azac-tab-stats");
      loadStudentStats();
    }
  });
})(jQuery);
