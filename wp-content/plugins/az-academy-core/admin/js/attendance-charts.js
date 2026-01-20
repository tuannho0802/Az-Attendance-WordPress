(function ($) {
  function registerCenterText() {
    if (typeof Chart === "undefined") return;
    if (!Chart.registry.plugins.get("centerText")) {
      var CenterText = {
        id: "centerText",
        beforeDraw: function (chart, args, opts) {
          var meta = chart.getDatasetMeta(0);
          if (!meta || !meta.data || !meta.data.length) return;
          var pt = meta.data[0];
          var x = pt.x, y = pt.y;
          var ctx = chart.ctx;
          ctx.save();
          ctx.textAlign = "center";
          ctx.textBaseline = "middle";
          ctx.fillStyle = "#0b3d3b";
          ctx.font = "600 14px system-ui,-apple-system,Segoe UI,Roboto";
          if (opts && opts.title) ctx.fillText(String(opts.title), x, y - 12);
          ctx.fillStyle = "#0f6d5e";
          ctx.font = "700 18px system-ui,-apple-system,Segoe UI,Roboto";
          if (opts && opts.value) ctx.fillText(String(opts.value), x, y + 8);
          ctx.restore();
        },
      };
      Chart.register(CenterText);
    }
  }
  function ensureChart(id, title, present, absent, pct, colors) {
    var el = document.getElementById(id);
    if (!el) return null;
    var existing = Chart.getChart(el);
    if (existing) return existing;
    return new Chart(el, {
      type: "doughnut",
      data: {
        labels: ["Có mặt", "Vắng mặt"],
        datasets: [
          {
            data: [present, absent],
            backgroundColor: colors,
          },
        ],
      },
      options: {
        responsive: true,
        cutout: "70%",
        plugins: {
          legend: { position: "top" },
          centerText: { title: title, value: pct + "%" },
        },
      },
    });
  }
  function updateChart(type, items) {
    if (typeof Chart === "undefined") return;
    registerCenterText();
    var present = 0;
    Object.keys(items || {}).forEach(function (k) {
      var it = items[k];
      if (!it) return;
      if (parseInt(it.status, 10) === 1)
        present++;
    });
    var totalEls =
      type === "check-in"
        ? $(".azac-status").length
        : $(".azac-status-mid").length;
    var total = Math.max(
      1,
      parseInt(totalEls, 10) || 0,
    );
    var absent = Math.max(0, total - present);
    var pct = Math.min(
      100,
      Math.round((present / total) * 100),
    );
    if (!window._azCharts) window._azCharts = { checkin: null, mid: null };
    var key = type === "check-in" ? "checkin" : "mid";
    var colors = type === "check-in" ? ["#2ecc71", "#e74c3c"] : ["#3498db", "#f39c12"];
    if (!window._azCharts[key]) {
      window._azCharts[key] = ensureChart(
        type === "check-in" ? "azacChartCheckin" : "azacChartMid",
        type === "check-in" ? "Đầu giờ" : "Giữa giờ",
        present,
        absent,
        pct,
        colors
      );
    } else {
      var chart = window._azCharts[key];
      chart.data.datasets[0].data = [present, absent];
      chart.options.plugins.centerText.value = pct + "%";
      chart.update();
    }
  }
  function renderCharts() {
    if (!(window.azacData && window.azacData.stats)) return;
    if (typeof Chart === "undefined") return;
    registerCenterText();
    var c1 = document.getElementById("azacChartCheckin");
    var c2 = document.getElementById("azacChartMid");
    if (c1) {
      var p1 =
        parseInt(
          window.azacData.stats.checkin.present,
          10,
        ) || 0;
      var total1 =
        document.querySelectorAll(
          ".azac-status",
        ).length || 0;
      var a1 = Math.max(0, total1 - p1);
      var r1 = Math.min(
        100,
        Math.round(
          (p1 / Math.max(1, total1)) * 100,
        ),
      );
      ensureChart("azacChartCheckin", "Đầu giờ", p1, a1, r1, ["#2ecc71", "#e74c3c"]);
    }
    if (c2) {
      var p2 =
        parseInt(
          window.azacData.stats.mid.present,
          10,
        ) || 0;
      var total2 =
        document.querySelectorAll(
          ".azac-status-mid",
        ).length || 0;
      var a2 = Math.max(0, total2 - p2);
      var r2 = Math.min(
        100,
        Math.round(
          (p2 / Math.max(1, total2)) * 100,
        ),
      );
      ensureChart("azacChartMid", "Giữa giờ", p2, a2, r2, ["#3498db", "#f39c12"]);
    }
  }
  window.AZAC_Att = window.AZAC_Att || {};
  window.AZAC_Att.updateChart = updateChart;
  $(function () {
    if (typeof Chart !== "undefined") {
      renderCharts();
    } else {
      var s = document.createElement("script");
      s.src = "https://cdn.jsdelivr.net/npm/chart.js";
      s.async = true;
      s.onload = renderCharts;
      document.head.appendChild(s);
    }
  });
})(jQuery);
