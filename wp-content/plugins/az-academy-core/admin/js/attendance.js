(function ($) {
  function formatDate(d) {
    var parts = String(d || "").split("-");
    if (parts.length === 3)
      return [
        parts[2],
        parts[1],
        parts[0],
      ].join("/");
    return String(d || "");
  }
  function updateSessionTitle(date) {
    var list =
      window.azacData &&
      window.azacData.sessions
        ? window.azacData.sessions
        : [];
    var idx = 0;
    for (var i = 0; i < list.length; i++) {
      if (
        (list[i].date || "") ===
        String(date || "")
      ) {
        idx = i + 1;
        break;
      }
    }
    var text =
      "Buổi học thứ: " +
      (idx || 1) +
      " • Ngày: " +
      formatDate(date);
    var el = document.getElementById(
      "azac_session_title"
    );
    if (el) el.textContent = text;
  }
  function applyItems(type, items) {
    var isCheckin = type === "check-in";
    var selectorStatus = isCheckin
      ? ".azac-status"
      : ".azac-status-mid";
    var selectorNote = isCheckin
      ? ".azac-note"
      : ".azac-note-mid";
    // apply received items onto the cleared UI
    $(selectorStatus).each(function () {
      var id = parseInt(
        $(this).data("student"),
        10
      );
      var data = items[id];
      if (data) {
        $(this).prop("checked", !!data.status);
        $(
          selectorNote +
            '[data-student="' +
            id +
            '"]'
        ).val(data.note || "");
      }
    });
  }
  function resetItems(type) {
    var isCheckin = type === "check-in";
    var selectorStatus = isCheckin
      ? ".azac-status"
      : ".azac-status-mid";
    var selectorNote = isCheckin
      ? ".azac-note"
      : ".azac-note-mid";
    $(selectorStatus).prop("checked", false);
    $(selectorNote).val("");
  }
  function updateChart(type, items) {
    if (typeof Chart === "undefined") return;
    var present = 0,
      absent = 0;
    Object.keys(items || {}).forEach(function (
      k
    ) {
      var it = items[k];
      if (!it) return;
      if (parseInt(it.status, 10) === 1)
        present++;
      else absent++;
    });
    var total = Math.max(1, present + absent);
    var pct = Math.round(
      (present / total) * 100
    );
    if (!window._azCharts) {
      window._azCharts = {
        checkin: null,
        mid: null,
        pluginRegistered: false,
      };
    }
    if (!window._azCharts.pluginRegistered) {
      var CenterText = {
        id: "centerText",
        beforeDraw: function (
          chart,
          args,
          opts
        ) {
          var meta = chart.getDatasetMeta(0);
          if (
            !meta ||
            !meta.data ||
            !meta.data.length
          )
            return;
          var pt = meta.data[0];
          var x = pt.x,
            y = pt.y;
          var ctx = chart.ctx;
          ctx.save();
          ctx.textAlign = "center";
          ctx.textBaseline = "middle";
          ctx.fillStyle = "#0b3d3b";
          ctx.font =
            "600 14px system-ui,-apple-system,Segoe UI,Roboto";
          if (opts && opts.title)
            ctx.fillText(
              String(opts.title),
              x,
              y - 12
            );
          ctx.fillStyle = "#0f6d5e";
          ctx.font =
            "700 18px system-ui,-apple-system,Segoe UI,Roboto";
          if (opts && opts.value)
            ctx.fillText(
              String(opts.value),
              x,
              y + 8
            );
          ctx.restore();
        },
      };
      if (
        !Chart.registry.plugins.get(
          "centerText"
        )
      )
        Chart.register(CenterText);
      window._azCharts.pluginRegistered = true;
    }
    function ensureChart(id, title) {
      var el = document.getElementById(id);
      if (!el) return null;
      var existing = Chart.getChart(el);
      if (existing) return existing;
      var chart = new Chart(el, {
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
      return chart;
    }
    var key =
      type === "check-in" ? "checkin" : "mid";
    if (!window._azCharts[key]) {
      window._azCharts[key] = ensureChart(
        type === "check-in"
          ? "azacChartCheckin"
          : "azacChartMid",
        type === "check-in"
          ? "Đầu giờ"
          : "Giữa giờ"
      );
    } else {
      var chart = window._azCharts[key];
      chart.data.datasets[0].data = [
        present,
        absent,
      ];
      chart.options.plugins.centerText.value =
        pct + "%";
      chart.update();
    }
  }
  function fetchExisting(type) {
    resetItems(type);
    var payload = {
      action: "azac_get_attendance",
      nonce: window.azacData.nonce,
      class_id: window.azacData.classId,
      type: type,
      session_date:
        window.azacData.sessionDate ||
        window.azacData.today,
    };
    $.post(
      window.azacData.ajaxUrl,
      payload,
      function (res) {
        if (
          res &&
          res.success &&
          res.data &&
          res.data.items
        ) {
          applyItems(type, res.data.items);
          updateChart(type, res.data.items);
        }
      }
    );
  }
  function collectItems(
    selectorStatus,
    selectorNote
  ) {
    var items = [];
    $(selectorStatus).each(function () {
      var id = parseInt(
        $(this).data("student"),
        10
      );
      var status = $(this).is(":checked")
        ? 1
        : 0;
      var note = (
        $(
          selectorNote +
            '[data-student="' +
            id +
            '"]'
        ).val() || ""
      ).toString();
      items.push({
        id: id,
        status: status,
        note: note,
      });
    });
    return items;
  }
  function submit(type) {
    var items, selectorStatus, selectorNote;
    if (type === "check-in") {
      selectorStatus = ".azac-status";
      selectorNote = ".azac-note";
    } else {
      selectorStatus = ".azac-status-mid";
      selectorNote = ".azac-note-mid";
    }
    items = collectItems(
      selectorStatus,
      selectorNote
    );
    var payload = {
      action: "azac_save_attendance",
      nonce: window.azacData.nonce,
      class_id: window.azacData.classId,
      type: type,
      session_date:
        window.azacData.sessionDate ||
        window.azacData.today,
      items: items,
    };
    $.post(
      window.azacData.ajaxUrl,
      payload,
      function (res) {
        if (res && res.success) {
          alert(
            "Đã lưu " +
              res.data.inserted +
              " bản ghi"
          );
          fetchExisting(type);
        } else {
          alert("Lỗi lưu");
        }
      }
    );
  }
  $(function () {
    $("#azac_session_select").on(
      "change",
      function () {
        var val = $(this).val();
        if (val) {
          window.azacData.sessionDate = val;
          updateSessionTitle(val);
          resetItems("check-in");
          resetItems("mid-session");
          fetchExisting("check-in");
          fetchExisting("mid-session");
        }
      }
    );
    $("#azac_add_session_btn").on(
      "click",
      function () {
        var d = $("#azac_session_date").val();
        var t = $("#azac_session_time").val();
        if (!d) {
          alert("Chọn ngày buổi học");
          return;
        }
        var payload = {
          action: "azac_add_session",
          nonce: window.azacData.sessionNonce,
          class_id: window.azacData.classId,
          date: d,
          time: t,
        };
        var btn = $(this).prop(
          "disabled",
          true
        );
        $.post(
          window.azacData.ajaxUrl,
          payload,
          function (res) {
            btn.prop("disabled", false);
            if (
              res &&
              res.success &&
              res.data &&
              res.data.sessions
            ) {
              var sel = $(
                "#azac_session_select"
              ).empty();
              window.azacData.sessions =
                res.data.sessions;
              res.data.sessions.forEach(
                function (s) {
                  var label =
                    s.date +
                    (s.time
                      ? " " + s.time
                      : "");
                  $("<option/>", {
                    value: s.date,
                    text: label,
                  }).appendTo(sel);
                }
              );
              if (res.data.selected) {
                sel.val(res.data.selected);
                window.azacData.sessionDate =
                  res.data.selected;
                updateSessionTitle(
                  res.data.selected
                );
                fetchExisting("check-in");
                fetchExisting("mid-session");
              }
            } else {
              alert("Lỗi thêm buổi học");
            }
          }
        );
      }
    );
    $("#azac_update_session_btn").on(
      "click",
      function () {
        var old = $(
          "#azac_session_select"
        ).val();
        var d = $("#azac_session_date").val();
        var t = $("#azac_session_time").val();
        if (!old || !d) {
          alert("Chọn buổi và ngày");
          return;
        }
        var payload = {
          action: "azac_update_session",
          nonce: window.azacData.sessionNonce,
          class_id: window.azacData.classId,
          date: old,
          new_date: d,
          new_time: t,
        };
        var btn = $(this).prop(
          "disabled",
          true
        );
        $.post(
          window.azacData.ajaxUrl,
          payload,
          function (res) {
            btn.prop("disabled", false);
            if (
              res &&
              res.success &&
              res.data &&
              res.data.sessions
            ) {
              var sel = $(
                "#azac_session_select"
              ).empty();
              window.azacData.sessions =
                res.data.sessions;
              res.data.sessions.forEach(
                function (s) {
                  var label =
                    s.date +
                    (s.time
                      ? " " + s.time
                      : "");
                  $("<option/>", {
                    value: s.date,
                    text: label,
                  }).appendTo(sel);
                }
              );
              if (res.data.selected) {
                sel.val(res.data.selected);
                window.azacData.sessionDate =
                  res.data.selected;
                updateSessionTitle(
                  res.data.selected
                );
                fetchExisting("check-in");
                fetchExisting("mid-session");
              }
            } else {
              alert("Lỗi cập nhật buổi học");
            }
          }
        );
      }
    );
    $("#azac_start_mid_btn").on(
      "click",
      function () {
        var payload = {
          action: "azac_generate_mid_pin",
          nonce:
            (window.AZAC_MID &&
              AZAC_MID.midNonce) ||
            "",
          class_id: window.azacData.classId,
          session_date: window.azacData.today,
        };
        var btn = $(this).prop(
          "disabled",
          true
        );
        $.post(
          window.azacData.ajaxUrl,
          payload,
          function (res) {
            btn.prop("disabled", false);
            if (
              res &&
              res.success &&
              res.data
            ) {
              var url = String(
                res.data.url || ""
              );
              var pin = String(
                res.data.pin_code || ""
              );
              $("#azac_mid_pin").text(pin);
              $("#azac_mid_qr").attr(
                "src",
                "https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=" +
                  encodeURIComponent(url)
              );
              $("#azac-mid-modal").css(
                "display",
                "flex"
              );
            } else {
              alert("Không thể tạo PIN");
            }
          }
        );
      }
    );
    $("#azac_mid_close_modal").on(
      "click",
      function () {
        $("#azac-mid-modal").hide();
      }
    );
    $("#azac_end_mid_btn").on(
      "click",
      function () {
        var payload = {
          action: "azac_close_mid_pin",
          nonce:
            (window.AZAC_MID &&
              AZAC_MID.closeNonce) ||
            "",
          class_id: window.azacData.classId,
        };
        var btn = $(this).prop(
          "disabled",
          true
        );
        $.post(
          window.azacData.ajaxUrl,
          payload,
          function (res) {
            btn.prop("disabled", false);
            if (res && res.success) {
              alert(
                "Đã kết thúc điểm danh giữa giờ"
              );
              $("#azac-mid-modal").hide();
            } else {
              alert(
                "Không thể kết thúc điểm danh"
              );
            }
          }
        );
      }
    );
    $(".azac-tab-btn").on("click", function () {
      $(".azac-tab-btn").removeClass(
        "button-primary"
      );
      $(this).addClass("button-primary");
      $(".azac-tab").removeClass("active");
      $($(this).data("target")).addClass(
        "active"
      );
      var target = $(this).data("target");
      var t =
        target === "#azac-checkin"
          ? "check-in"
          : "mid-session";
      fetchExisting(t);
    });
    $("#azac-submit-checkin").on(
      "click",
      function () {
        submit("check-in");
      }
    );
    $("#azac-submit-mid").on(
      "click",
      function () {
        submit("mid-session");
      }
    );
    $(document).on(
      "change",
      ".azac-status",
      function () {
        var id =
          parseInt(
            $(this).data("student"),
            10
          ) || 0;
        if (!id) return;
        var note = String(
          $(
            '.azac-note[data-student="' +
              id +
              '"]'
          ).val() || ""
        );
        var payload = {
          action: "azac_save_attendance",
          nonce: window.azacData.nonce,
          class_id: window.azacData.classId,
          type: "check-in",
          session_date:
            window.azacData.sessionDate ||
            window.azacData.today,
          items: [
            {
              id: id,
              status: $(this).is(":checked")
                ? 1
                : 0,
              note: note,
            },
          ],
        };
        $.post(
          window.azacData.ajaxUrl,
          payload,
          function (res) {
            if (res && res.success) {
              fetchExisting("check-in");
            }
          }
        );
      }
    );
    $(document).on(
      "change",
      ".azac-status-mid",
      function () {
        var id =
          parseInt(
            $(this).data("student"),
            10
          ) || 0;
        if (!id) return;
        var note = String(
          $(
            '.azac-note-mid[data-student="' +
              id +
              '"]'
          ).val() || ""
        );
        var payload = {
          action: "azac_save_attendance",
          nonce: window.azacData.nonce,
          class_id: window.azacData.classId,
          type: "mid-session",
          session_date:
            window.azacData.sessionDate ||
            window.azacData.today,
          items: [
            {
              id: id,
              status: $(this).is(":checked")
                ? 1
                : 0,
              note: note,
            },
          ],
        };
        $.post(
          window.azacData.ajaxUrl,
          payload,
          function (res) {
            if (res && res.success) {
              fetchExisting("mid-session");
            }
          }
        );
      }
    );
    fetchExisting("check-in");
    fetchExisting("mid-session");
    updateSessionTitle(
      window.azacData.sessionDate ||
        window.azacData.today
    );
    function renderCharts() {
      if (
        !(
          window.azacData &&
          window.azacData.stats
        )
      )
        return;
      if (typeof Chart === "undefined") return;
      function pct(present, absent) {
        var total = Math.max(
          1,
          (parseInt(present, 10) || 0) +
            (parseInt(absent, 10) || 0)
        );
        return Math.round(
          ((parseInt(present, 10) || 0) /
            total) *
            100
        );
      }
      var CenterText = {
        id: "centerText",
        beforeDraw: function (
          chart,
          args,
          opts
        ) {
          var meta = chart.getDatasetMeta(0);
          if (
            !meta ||
            !meta.data ||
            !meta.data.length
          )
            return;
          var pt = meta.data[0];
          var x = pt.x,
            y = pt.y;
          var ctx = chart.ctx;
          ctx.save();
          ctx.textAlign = "center";
          ctx.textBaseline = "middle";
          ctx.fillStyle = "#0b3d3b";
          ctx.font =
            "600 14px system-ui,-apple-system,Segoe UI,Roboto";
          if (opts && opts.title)
            ctx.fillText(
              String(opts.title),
              x,
              y - 12
            );
          ctx.fillStyle = "#0f6d5e";
          ctx.font =
            "700 18px system-ui,-apple-system,Segoe UI,Roboto";
          if (opts && opts.value)
            ctx.fillText(
              String(opts.value),
              x,
              y + 8
            );
          ctx.restore();
        },
      };
      if (
        !Chart.registry.plugins.get(
          "centerText"
        )
      ) {
        Chart.register(CenterText);
      }
      var c1 = document.getElementById(
        "azacChartCheckin"
      );
      var c2 = document.getElementById(
        "azacChartMid"
      );
      if (c1) {
        var p1 =
          window.azacData.stats.checkin.present;
        var a1 =
          window.azacData.stats.checkin.absent;
        var r1 = pct(p1, a1);
        new Chart(c1, {
          type: "doughnut",
          data: {
            labels: ["Có mặt", "Vắng mặt"],
            datasets: [
              {
                data: [p1, a1],
                backgroundColor: [
                  "#2ecc71",
                  "#e74c3c",
                ],
              },
            ],
          },
          options: {
            responsive: true,
            cutout: "70%",
            plugins: {
              legend: { position: "top" },
              centerText: {
                title: "Đầu giờ",
                value: r1 + "%",
              },
            },
          },
        });
      }
      if (c2) {
        var p2 =
          window.azacData.stats.mid.present;
        var a2 =
          window.azacData.stats.mid.absent;
        var r2 = pct(p2, a2);
        new Chart(c2, {
          type: "doughnut",
          data: {
            labels: ["Có mặt", "Vắng mặt"],
            datasets: [
              {
                data: [p2, a2],
                backgroundColor: [
                  "#3498db",
                  "#f39c12",
                ],
              },
            ],
          },
          options: {
            responsive: true,
            cutout: "70%",
            plugins: {
              legend: { position: "top" },
              centerText: {
                title: "Giữa giờ",
                value: r2 + "%",
              },
            },
          },
        });
      }
    }
    if (typeof Chart !== "undefined") {
      renderCharts();
    } else {
      var s = document.createElement("script");
      s.src =
        "https://cdn.jsdelivr.net/npm/chart.js";
      s.async = true;
      s.onload = renderCharts;
      document.head.appendChild(s);
    }
  });
})(jQuery);
