(function ($) {
  $(function () {
    $(".azac-tab-btn").on("click", function () {
      $(".azac-tab-btn").removeClass(
        "button-primary",
      );
      $(this).addClass("button-primary");
      $(".azac-tab").removeClass("active");
      $($(this).data("target")).addClass(
        "active",
      );
      var target = $(this).data("target");
      if (
        target === "#azac-checkin" ||
        target === "#azac-mid"
      ) {
        var t =
          target === "#azac-checkin"
            ? "check-in"
            : "mid-session";
        if (
          window.AZAC_Att &&
          typeof window.AZAC_Att
            .fetchExisting === "function"
        ) {
          window.AZAC_Att.fetchExisting(t);
        }
      } else if (target === "#azac-reviews") {
        if (
          window.AZAC_Reviews &&
          typeof window.AZAC_Reviews.load ===
            "function"
        ) {
          var s = document.getElementById(
            "azacReviewsFilter",
          );
          window.AZAC_Reviews.load(
            s ? s.value : "",
          );
        }
      }
    });
    $("#azac-submit-checkin").on(
      "click",
      function () {
        if (
          window.AZAC_Att &&
          typeof window.AZAC_Att.submit ===
            "function"
        ) {
          window.AZAC_Att.submit("check-in");
        }
      },
    );
    $("#azac-submit-mid").on(
      "click",
      function () {
        if (
          window.AZAC_Att &&
          typeof window.AZAC_Att.submit ===
            "function"
        ) {
          window.AZAC_Att.submit("mid-session");
        }
      },
    );
    /* Conflicting handlers disabled to prevent double AJAX and flicker - Logic moved to attendance-session.js
    $(document).on("change", ".azac-status", function () {
      var id = parseInt($(this).data("student"), 10) || 0;
      if (!id) return;
      var note = String($('.azac-note[data-student="' + id + '"]').val() || "");
      var payload = {
        action: "azac_save_attendance",
        nonce: window.azacData.nonce,
        class_id: window.azacData.classId,
        type: "check-in",
        session_date: window.azacData.sessionDate || window.azacData.today,
        items: [
          {
            id: id,
            status: $(this).is(":checked") ? 1 : 0,
            note: note,
          },
        ],
      };
      $.post(window.azacData.ajaxUrl, payload, function (res) {
        if (res && res.success && window.AZAC_Att && typeof window.AZAC_Att.fetchExisting === "function") {
          window.AZAC_Att.fetchExisting("check-in");
        }
      });
    });
    $(document).on("change", ".azac-status-mid", function () {
      var id = parseInt($(this).data("student"), 10) || 0;
      if (!id) return;
      var note = String($('.azac-note-mid[data-student="' + id + '"]').val() || "");
      var payload = {
        action: "azac_save_attendance",
        nonce: window.azacData.nonce,
        class_id: window.azacData.classId,
        type: "mid-session",
        session_date: window.azacData.sessionDate || window.azacData.today,
        items: [
          {
            id: id,
            status: $(this).is(":checked") ? 1 : 0,
            note: note,
          },
        ],
      };
      $.post(window.azacData.ajaxUrl, payload, function (res) {
        if (res && res.success && window.AZAC_Att && typeof window.AZAC_Att.fetchExisting === "function") {
          window.AZAC_Att.fetchExisting("mid-session");
        }
      });
    });
    */
    if (
      window.AZAC_Att &&
      typeof window.AZAC_Att.fetchExisting ===
        "function"
    ) {
      window.AZAC_Att.fetchExisting("check-in");
      window.AZAC_Att.fetchExisting(
        "mid-session",
      );
    }
    if (
      window.AZACU &&
      typeof window.AZACU.updateSessionTitle ===
        "function" &&
      window.azacData &&
      (window.azacData.sessionDate ||
        window.azacData.today)
    ) {
      window.AZACU.updateSessionTitle(
        window.azacData.sessionDate ||
          window.azacData.today,
      );
    }
  });
})(jQuery);
// Reviews page helpers
(function () {
  if (
    document.getElementById(
      "azacReviewsMixedChart",
    )
  ) {
    return;
  }
  window.AZAC_Reviews = window.AZAC_Reviews || {};
  window.AZAC_Reviews.load = function (stars) {
    if (!(window.azacReviews && window.azacReviews.ajaxUrl)) return;
    var cid = window.azacReviews.classId || 0;
    var cSel = document.getElementById("azacReviewsClass");
    if (cSel) cid = parseInt(cSel.value, 10) || cid;
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
          renderReviews(res.data || {});
        }
      });
    function renderReviews(data) {
      try {
        var avgEl = document.getElementById("azacReviewsAvg");
        var totEl = document.getElementById("azacReviewsTotal");
        if (avgEl) avgEl.textContent = (Number(data.average) || 0).toFixed(1) + "/5.0";
        if (totEl) totEl.textContent = String(data.total || 0);
        var el = document.getElementById("azacReviewsChart");
        var labels = ["1 sao", "2 sao", "3 sao", "4 sao", "5 sao"];
        var colors = ["#e74c3c", "#e74c3c", "#f39c12", "#2ecc71", "#3498db"];
        var ds = [
          data.counts[1] || 0,
          data.counts[2] || 0,
          data.counts[3] || 0,
          data.counts[4] || 0,
          data.counts[5] || 0,
        ];
        function renderChart() {
          if (!el) return;
          var existing = window.Chart && window.Chart.getChart ? window.Chart.getChart(el) : null;
          if (existing) {
            existing.data.labels = labels;
            existing.data.datasets[0].data = ds;
            existing.update();
          } else {
            new Chart(el, {
              type: "bar",
              data: { labels: labels, datasets: [{ data: ds, backgroundColor: colors }] },
              options: { responsive: true, plugins: { legend: { display: false } } },
            });
          }
        }
        if (typeof Chart === "undefined") {
          var s = document.createElement("script");
          s.src = "https://cdn.jsdelivr.net/npm/chart.js";
          s.async = true;
          s.onload = renderChart;
          document.head.appendChild(s);
        } else {
          renderChart();
        }
        var list = document.getElementById("azacReviewsList");
        if (list) {
          var html = (data.items || [])
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
      } catch (e) {}
    }
  };
  if (typeof jQuery !== "undefined") {
    jQuery(function () {
      var selC = document.getElementById("azacReviewsClass");
      var selF = document.getElementById("azacReviewsFilter");
      function reload() {
        var s = selF ? selF.value : "";
        if (window.AZAC_Reviews && typeof window.AZAC_Reviews.load === "function") {
          window.AZAC_Reviews.load(s);
        }
      }
      if (selC) selC.addEventListener("change", reload);
      if (selF) selF.addEventListener("change", reload);
      if (document.getElementById("azacReviewsChart")) {
        reload();
      }
    });
  }
})();
