(() => {
  var MODE = "class";
  var CHART = null;
  var DATA = null;
  var CURRENT_PAGE = 1;
  var PAGE_SIZE = 20;
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
  function colorsForStars() {
    return [
      "#e74c3c",
      "#e67e22",
      "#f1c40f",
      "#27ae60",
      "#2ecc71",
    ];
  }
  function buildClassConfig(counts) {
    var isMobile = window.innerWidth < 768;
    var labels = isMobile
      ? ["5s", "4s", "3s", "2s", "1s"]
      : ["5 sao", "4 sao", "3 sao", "2 sao", "1 sao"];
    var data = [5, 4, 3, 2, 1].map(
      function (k) {
        return counts && counts[k]
          ? counts[k]
          : 0;
      },
    );
    var total =
      data.reduce(function (a, b) {
        return a + b;
      }, 0) || 1;
    var colors = colorsForStars();
    var percents = data.map(function (v) {
      return Math.round(
        (v / Math.max(1, total)) * 100,
      );
    });
    var PercentPlugin = {
      id: "azPercent",
      afterDatasetsDraw: function (chart) {
        var ctx = chart.ctx;
        var meta = chart.getDatasetMeta(0);
        var ds = chart.data.datasets[0];
        var arr = ds.data || [];
        var tot =
          arr.reduce(function (a, b) {
            return a + (b || 0);
          }, 0) || 1;
        ctx.save();
        ctx.fillStyle = "#555";
        ctx.textAlign = "left";
        ctx.textBaseline = "middle";
        ctx.font =
          "12px system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, sans-serif";
        for (
          var i = 0;
          i < meta.data.length;
          i++
        ) {
          var v = arr[i] || 0;
          var pct =
            Math.round((v / tot) * 100) + "%";
          var pt = meta.data[i];
          ctx.fillText(pct, pt.x + 6, pt.y);
        }
        ctx.restore();
      },
    };
    return {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Số lượt đánh giá",
            data: data,
            backgroundColor: colors,
            borderColor: colors,
            maxBarThickness: 32,
            hoverBackgroundColor: colors,
            order: 1,
          },
          {
            type: "line",
            label: "Tỷ trọng (%)",
            data: percents,
            yAxisID: "y1",
            borderColor: "#0064e0",
            backgroundColor: "transparent",
            pointRadius: 3,
            tension: 0.25,
            order: 2,
          },
        ],
      },
      options: {
        indexAxis: "x",
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 800,
          easing: "easeOutQuart",
        },
        transitions: {
          show: {
            animation: {
              duration: 800,
              easing: "easeOutQuart",
            },
            animations: {
              colors: {
                type: "color",
                properties: [
                  "borderColor",
                  "backgroundColor",
                ],
                from: "transparent",
              },
            },
          },
        },
        plugins: {
          legend: { display: true },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var v = ctx.raw || 0;
                if (
                  ctx.dataset.type === "line" ||
                  ctx.dataset.yAxisID === "y1"
                ) {
                  return (
                    ctx.dataset.label +
                    ": " +
                    v +
                    "%"
                  );
                }
                var pct = Math.round(
                  (v / total) * 100,
                );
                return (
                  ctx.dataset.label +
                  ": " +
                  v +
                  " (" +
                  pct +
                  "%)"
                );
              },
            },
          },
          title: {
            display: true,
            text: "Tổng lớp – phân bố số sao (Bar + Line)",
          },
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 },
          },
          y1: {
            beginAtZero: true,
            min: 0,
            max: 100,
            position: "right",
            grid: { drawOnChartArea: false },
          },
        },
      },
      plugins: [PercentPlugin],
    };
  }
  function buildSessionConfig(items) {
    var map = {};
    (items || []).forEach(function (it) {
      var d = String(it.date || "");
      if (!d) return;
      if (!map[d]) map[d] = { sum: 0, c: 0 };
      map[d].sum +=
        parseInt(it.rating, 10) || 0;
      map[d].c += 1;
    });
    var labels = Object.keys(map).sort();
    var avgs = labels.map(function (d) {
      var m = map[d];
      return m.c
        ? Math.round((m.sum * 100) / m.c) / 100
        : 0;
    });
    var counts = labels.map(function (d) {
      return map[d].c || 0;
    });
    return {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Điểm TB",
            data: avgs,
            backgroundColor:
              "rgba(0,100,224,.35)",
            borderColor: "#0064e0",
            hoverBackgroundColor:
              "rgba(0,100,224,.55)",
            maxBarThickness: 40,
            azCounts: counts,
            order: 1,
          },
          {
            type: "line",
            label: "Số lượt đánh giá",
            data: counts,
            yAxisID: "y1",
            borderColor: "#e67e22",
            backgroundColor: "transparent",
            pointRadius: 3,
            tension: 0.25,
            order: 2,
          },
        ],
      },
      options: {
        responsive: true,
        animation: {
          duration: 800,
          easing: "easeOutQuart",
        },
        transitions: {
          show: {
            animation: {
              duration: 800,
              easing: "easeOutQuart",
            },
            animations: {
              colors: {
                type: "color",
                properties: [
                  "borderColor",
                  "backgroundColor",
                ],
                from: "transparent",
              },
            },
          },
        },
        maintainAspectRatio: false,
        plugins: {
          legend: { display: true },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var cnt =
                  (ctx.dataset.azCounts || [])[
                    ctx.dataIndex
                  ] || 0;
                var avg = Number(
                  ctx.raw || 0,
                ).toFixed(2);
                if (
                  ctx.dataset.type === "line" ||
                  ctx.dataset.yAxisID === "y1"
                ) {
                  return (
                    "Ngày: " +
                    ctx.label +
                    " - " +
                    ctx.dataset.label +
                    ": " +
                    cnt
                  );
                }
                return (
                  "Ngày: " +
                  ctx.label +
                  " - " +
                  ctx.dataset.label +
                  ": " +
                  avg
                );
              },
            },
          },
          title: {
            display: true,
            text: "Theo buổi – điểm TB (Bar) + lượt đánh giá (Line)",
          },
        },
        scales: {
          y: {
            min: 0,
            max: 5,
            ticks: { stepSize: 1 },
          },
          x: {},
          y1: {
            beginAtZero: true,
            position: "right",
            grid: { drawOnChartArea: false },
          },
        },
      },
    };
  }
  function draw(view) {
    ensure(function () {
      var el = document.getElementById("azacReviewsMixedChart");
      if (!el) return;
      var cfg =
        view === "class"
          ? buildClassConfig(DATA.counts)
          : buildSessionConfig(DATA.items);
      if (CHART) {
        CHART.destroy();
        CHART = null;
      }
      CHART = new Chart(el, cfg);
      try {
        CHART.update("show");
      } catch (e) {}
    });
  }
  function render(data) {
    DATA = data || { counts: {}, items: [] };
    var avg = document.getElementById("azacReviewsAvg");
    var tot = document.getElementById("azacReviewsTotal");
    if (avg) {
      var v = Number(DATA.average) || 0;
      avg.textContent = v.toFixed(1) + "/5.0";
      avg.classList.remove(
        "azac-grade-good",
        "azac-grade-mid",
        "azac-grade-bad",
      );
      if (v >= 4.5)
        avg.classList.add("azac-grade-good");
      else if (v >= 3.5)
        avg.classList.add("azac-grade-mid");
      else avg.classList.add("azac-grade-bad");
    }
    if (tot) tot.textContent = String(DATA.total || 0);
    draw(MODE);
    var list = document.getElementById("azacReviewsList");
    if (list) {
      var sess = Array.isArray(DATA.sessions)
        ? DATA.sessions
        : [];
      var html = (DATA.items || [])
        .map(function (it) {
          var stars = Array(it.rating || 0)
            .fill('<span class="dashicons dashicons-star-filled" style="color:#f5b301"></span>')
            .join("");
          var initial =
            (it.name || "")
              .trim()
              .charAt(0)
              .toUpperCase() || "U";
          var avatarSrc = String(
            it.avatar ||
              "https://secure.gravatar.com/avatar/?d=mp&s=48",
          );
          var avatarHtml =
            '<img class="azac-avatar-img" src="' +
            avatarSrc +
            '" alt="avatar"/>';
          var idx = 0;
          if (sess && sess.length) {
            var i = sess.indexOf(
              String(it.date || ""),
            );
            idx = i >= 0 ? i + 1 : 0;
          }
          return (
            '<div class="azac-review-item"><div class="azac-review-top"><div class="azac-review-left">' +
            avatarHtml +
            '<span class="azac-review-name">' +
            (it.name || "") +
            "</span>" +
            (idx
              ? '<span class="azac-badge">Buổi ' +
                idx +
                "</span>"
              : "") +
            '</div><span class="azac-review-stars">' +
            stars +
            '</span></div><div class="azac-review-comment">' +
            (it.comment || "") +
            '</div><div class="azac-review-date"><span class="azac-badge azac-badge-date">' +
            (it.date || "") +
            "</span>" +
            "</div></div>"
          );
        })
        .join("");
      list.innerHTML = html || "<div>Chưa có đánh giá</div>";
      try {
        var cid =
          parseInt(
            window.azacReviews
              ? window.azacReviews.classId
              : 0,
            10,
          ) || 0;
        if (
          cid &&
          window.AZACU &&
          typeof window.AZACU.getClassColor ===
            "function"
        ) {
          var color = String(
            window.AZACU.getClassColor(cid) ||
              "#0064e0",
          );
          var els = list.querySelectorAll(
            ".azac-badge-date",
          );
          els.forEach(function (b) {
            b.style.borderColor = color;
            b.style.color = color;
            if (
              color.charAt(0) === "#" &&
              color.length === 7
            ) {
              b.style.backgroundColor =
                color + "11";
            } else {
              b.style.backgroundColor = color;
            }
          });
        }
      } catch (e) {}
    }
  }
  function renderPagination(
    totalPages,
    currentPage,
  ) {
    var container = document.getElementById(
      "azacReviewsPagination",
    );
    if (!container) return;
    if (totalPages <= 1) {
      container.innerHTML = "";
      return;
    }
    var html =
      '<div class="tablenav-pages"><span class="pagination-links">';
    if (currentPage > 1) {
      html +=
        '<a class="prev-page button" href="#" data-page="' +
        (currentPage - 1) +
        '"><span class="screen-reader-text">Trang trước</span><span aria-hidden="true">‹</span></a>';
    } else {
      html +=
        '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
    }
    html +=
      '<span class="paging-input"><span class="tablenav-paging-text"> ' +
      currentPage +
      ' của <span class="total-pages">' +
      totalPages +
      "</span></span></span>";
    if (currentPage < totalPages) {
      html +=
        '<a class="next-page button" href="#" data-page="' +
        (currentPage + 1) +
        '"><span class="screen-reader-text">Trang sau</span><span aria-hidden="true">›</span></a>';
    } else {
      html +=
        '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
    }
    html += "</span></div>";
    container.innerHTML = html;
    container
      .querySelectorAll("a.button")
      .forEach(function (btn) {
        btn.addEventListener(
          "click",
          function (e) {
            e.preventDefault();
            var p = parseInt(
              this.getAttribute("data-page"),
              10,
            );
            if (p && p !== CURRENT_PAGE) {
              CURRENT_PAGE = p;
              load();
            }
          },
        );
      });
  }
  function load() {
    var c = document.getElementById("azacReviewsClass");
    var f = document.getElementById("azacReviewsFilter");
    var s = document.getElementById(
      "azacReviewsSearch",
    );
    var cid = parseInt(c ? c.value : (window.azacReviews ? window.azacReviews.classId : 0), 10) || 0;
    var stars = f ? f.value : "";
    var search = s ? s.value : "";
    var fd = new FormData();
    fd.append("action", "azac_get_reviews");
    fd.append("nonce", window.azacReviews.nonce);
    fd.append("class_id", cid);
    fd.append("paged", CURRENT_PAGE);
    fd.append("per_page", PAGE_SIZE);
    if (stars) fd.append("stars", stars);
    if (search) fd.append("search", search);
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
    if (c)
      c.addEventListener("change", function () {
        CURRENT_PAGE = 1;
        load();
      });
    if (f)
      f.addEventListener("change", function () {
        CURRENT_PAGE = 1;
        load();
      });
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
