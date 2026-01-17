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
    if (
      window.azacData &&
      window.azacData.stats &&
      typeof Chart !== "undefined"
    ) {
      var ctx =
        document.getElementById("azacChart");
      if (ctx) {
        new Chart(ctx, {
          type: "doughnut",
          data: {
            labels: ["Có mặt", "Vắng mặt"],
            datasets: [
              {
                data: [
                  window.azacData.stats.present,
                  window.azacData.stats.absent,
                ],
                backgroundColor: [
                  "#2ecc71",
                  "#e74c3c",
                ],
              },
            ],
          },
          options: { responsive: true },
        });
      }
    }
  });
})(jQuery);
