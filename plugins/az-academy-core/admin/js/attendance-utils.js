(function ($) {
  window.AZACU = window.AZACU || {};
  window.AZACU.formatDate = function (d) {
    var parts = String(d || "").split("-");
    if (parts.length === 3)
      return [
        parts[2],
        parts[1],
        parts[0],
      ].join("/");
    return String(d || "");
  };
  window.AZACU.updateSessionTitle = function (
    date
  ) {
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
      window.AZACU.formatDate(date);
    var el = document.getElementById(
      "azac_session_title"
    );
    if (el) el.textContent = text;
  };
  window.AZACU.applyItems = function (
    type,
    items
  ) {
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
  };
  window.AZACU.resetItems = function (type) {
    var isCheckin = type === "check-in";
    var selectorStatus = isCheckin
      ? ".azac-status"
      : ".azac-status-mid";
    var selectorNote = isCheckin
      ? ".azac-note"
      : ".azac-note-mid";
    $(selectorStatus).prop("checked", false);
    $(selectorNote).val("");
  };
  window.AZACU.collectItems = function (
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
  };
  (function () {
    var MAP = {};
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
    window.AZACU.getClassColor = function (
      cid
    ) {
      var key = String(cid);
      if (MAP[key]) return MAP[key];
      var n = parseInt(cid, 10);
      var idx = Math.abs(
        (n * 9301 + 49297) % PALETTE.length
      );
      var color = PALETTE[idx];
      MAP[key] = color;
      return color;
    };
  })();
  window.AZACU.ensureChart = function (
    callback
  ) {
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
  };
})(jQuery);
