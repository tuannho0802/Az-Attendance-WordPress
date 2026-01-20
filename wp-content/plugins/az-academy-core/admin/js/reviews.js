(() => {
  var MODE = "class";
  var CHART = null;
  var DATA = null;
  function ensure(cb) {
    if (typeof Chart !== "undefined") {
      cb();
      return;
    }
    var s = document.createElement("script");
    s.src = "https://cdn.jsdelivr.net/npm/chart.js";
    s.async = true;
    s.onload = cb;
    document.head.appendChild(s);
  }
  function buildClassView(counts) {
    var labels = ["1 sao", "2 sao", "3 sao", "4 sao", "5 sao"];
    var bar = labels.map(function (_, i) {
      var k = i + 1;
      return counts && counts[k] ? counts[k] : 0;
    });
    var total = bar.reduce(function (a, b) {
      return a + b;
    }, 0) || 1;
    var line = bar.map(function (v) {
      return Math.round((v / total) * 100);
    });
    return {
      labels: labels,
      bar: bar,
      line: line,
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
        y1: {
          position: "right",
          min: 0,
          max: 100,
          ticks: { stepSize: 20 },
          grid: { drawOnChartArea: false },
        },
      },
      legend: true,
      lineLabel: "Tỷ lệ (%)",
    };
  }
  function buildSessionView(items) {
    var map = {};
    (items || []).forEach(function (it) {
      var d = String(it.date || "");
      if (!d) return;
      map[d] = map[d] || { sum: 0, c: 0 };
      map[d].sum += parseInt(it.rating, 10) || 0;
      map[d].c += 1;
    });
    var labels = Object.keys(map).sort();
    var bar = labels.map(function (d) {
      return map[d].c || 0;
    });
    var line = labels.map(function (d) {
      var m = map[d];
      return m.c ? Math.round((m.sum / m.c) * 100) / 100 : 0;
    });
    return {
      labels: labels,
      bar: bar,
      line: line,
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
        y1: {
          position: "right",
          min: 1,
          max: 5,
          ticks: { stepSize: 1 },
          grid: { drawOnChartArea: false },
        },
      },
      legend: false,
      lineLabel: "Điểm trung bình",
    };
  }
  function draw(view) {
    ensure(function () {
      var el = document.getElementById("azacReviewsMixedChart");
      if (!el) return;
      var cfg = view === "class" ? buildClassView(DATA.counts) : buildSessionView(DATA.items);
      if (CHART) {
        CHART.data.labels = cfg.labels;
        CHART.data.datasets[0].data = cfg.bar;
        CHART.data.datasets[1].data = cfg.line;
        CHART.data.datasets[1].label = cfg.lineLabel;
        CHART.options.scales = cfg.scales;
        CHART.options.plugins.legend.display = cfg.legend;
        CHART.update();
        return;
      }
      CHART = new Chart(el, {
        data: {
          labels: cfg.labels,
          datasets: [
            {
              type: "bar",
              label: "Số lượt đánh giá",
              data: cfg.bar,
              yAxisID: "y",
              backgroundColor: "rgba(52,152,219,.35)",
              borderColor: "#3498db",
            },
            {
              type: "line",
              label: cfg.lineLabel,
              data: cfg.line,
              yAxisID: "y1",
              borderColor: "#e67e22",
              backgroundColor: "rgba(230,126,34,.15)",
              tension: 0.25,
              fill: true,
              pointRadius: 3,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: "bottom", display: cfg.legend } },
          scales: cfg.scales,
        },
      });
    });
  }
  function render(data) {
    DATA = data || { counts: {}, items: [] };
    var avg = document.getElementById("azacReviewsAvg");
    var tot = document.getElementById("azacReviewsTotal");
    if (avg) avg.textContent = (Number(DATA.average) || 0).toFixed(1) + "/5.0";
    if (tot) tot.textContent = String(DATA.total || 0);
    draw(MODE);
    var list = document.getElementById("azacReviewsList");
    if (list) {
      var html = (DATA.items || [])
        .map(function (it) {
          var stars = Array(it.rating || 0)
            .fill('<span class="dashicons dashicons-star-filled" style="color:#f5b301"></span>')
            .join("");
          return (
            '<div class="azac-review-item"><div class="azac-review-top"><span class="azac-review-name">' +
            (it.name || "") +
            '</span><span class="azac-review-stars">' +
            stars +
            "</span></div><div class=\"azac-review-comment\">" +
            (it.comment || "") +
            "</div><div class=\"azac-review-date\">" +
            (it.date || "") +
            "</div></div>"
          );
        })
        .join("");
      list.innerHTML = html || "<div>Chưa có đánh giá</div>";
    }
  }
  function load() {
    var c = document.getElementById("azacReviewsClass");
    var f = document.getElementById("azacReviewsFilter");
    var cid = parseInt(c ? c.value : (window.azacReviews ? window.azacReviews.classId : 0), 10) || 0;
    var stars = f ? f.value : "";
    var fd = new FormData();
    fd.append("action", "azac_get_reviews");
    fd.append("nonce", window.azacReviews.nonce);
    fd.append("class_id", cid);
    if (stars) fd.append("stars", stars);
    fetch(window.azacReviews.ajaxUrl, { method: "POST", body: fd })
      .then(function (r) {
        return r.json();
      })
      .then(function (res) {
        if (res && res.success) {
          render(res.data || {});
        }
      })
      .catch(function () {});
  }
  function toggle(view) {
    MODE = view;
    document.querySelectorAll(".azac-view-btn").forEach(function (b) {
      b.classList.remove("button-primary");
    });
    var id = view === "class" ? "azacViewClass" : "azacViewSessions";
    var b = document.getElementById(id);
    if (b) b.classList.add("button-primary");
    draw(MODE);
  }
  function init() {
    var c = document.getElementById("azacReviewsClass");
    var f = document.getElementById("azacReviewsFilter");
    if (c) c.addEventListener("change", load);
    if (f) f.addEventListener("change", load);
    var btnC = document.getElementById("azacViewClass");
    var btnS = document.getElementById("azacViewSessions");
    if (btnC) btnC.addEventListener("click", function () {
      toggle("class");
    });
    if (btnS) btnS.addEventListener("click", function () {
      toggle("sessions");
    });
    load();
  }
  window.AZAC_Reviews = window.AZAC_Reviews || {};
  window.AZAC_Reviews.init = init;
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
