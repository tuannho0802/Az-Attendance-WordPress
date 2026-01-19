(function ($) {
  function applyItems(type, items) {
    var isCheckin = type === "check-in";
    var selectorStatus = isCheckin
      ? ".azac-status"
      : ".azac-status-mid";
    var selectorNote = isCheckin
      ? ".azac-note"
      : ".azac-note-mid";
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
  function fetchExisting(type) {
    var payload = {
      action: "azac_get_attendance",
      nonce: window.azacData.nonce,
      class_id: window.azacData.classId,
      type: type,
      session_date: window.azacData.today,
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
      session_date: window.azacData.today,
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
    fetchExisting("check-in");
    fetchExisting("mid-session");
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
