(function ($) {
  function fetchExisting(type) {
    if (window.AZACU && typeof window.AZACU.resetItems === "function") {
      window.AZACU.resetItems(type);
    }
    var payload = {
      action: "azac_get_attendance",
      nonce: window.azacData.nonce,
      class_id: window.azacData.classId,
      type: type,
      session_date: window.azacData.sessionDate || window.azacData.today,
    };
    $.post(window.azacData.ajaxUrl, payload, function (res) {
      if (res && res.success && res.data && res.data.items) {
        if (window.AZACU && typeof window.AZACU.applyItems === "function") {
          window.AZACU.applyItems(type, res.data.items);
        }
        if (window.AZAC_Att && typeof window.AZAC_Att.updateChart === "function") {
          window.AZAC_Att.updateChart(type, res.data.items);
        }
      }
    });
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
    if (window.AZACU && typeof window.AZACU.collectItems === "function") {
      items = window.AZACU.collectItems(selectorStatus, selectorNote);
    } else {
      items = [];
    }
    var payload = {
      action: "azac_save_attendance",
      nonce: window.azacData.nonce,
      class_id: window.azacData.classId,
      type: type,
      session_date: window.azacData.sessionDate || window.azacData.today,
      items: items,
    };
    $.post(window.azacData.ajaxUrl, payload, function (res) {
      if (res && res.success) {
        if (window.azacToast) { azacToast.success("Đã lưu " + res.data.inserted + " bản ghi"); } else { alert("Đã lưu " + res.data.inserted + " bản ghi"); }
        fetchExisting(type);
      } else {
        if (window.azacToast) { azacToast.error("Lỗi lưu"); } else { alert("Lỗi lưu"); }
      }
    });
  }
  window.AZAC_Att = window.AZAC_Att || {};
  window.AZAC_Att.fetchExisting = fetchExisting;
  window.AZAC_Att.submit = submit;
})(jQuery);

