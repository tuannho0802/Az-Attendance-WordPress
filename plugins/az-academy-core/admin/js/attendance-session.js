;(function ($) {
  $(function () {
    $("#azac_session_select").on("change", function () {
      var val = $(this).val();
      if (!val) return;
      window.azacData.sessionDate = val;
      if (window.AZACU && typeof window.AZACU.updateSessionTitle === "function") {
        window.AZACU.updateSessionTitle(val);
      }
      if (window.AZACU && typeof window.AZACU.resetItems === "function") {
        window.AZACU.resetItems("check-in");
        window.AZACU.resetItems("mid-session");
      }
      if (window.AZAC_Att && typeof window.AZAC_Att.fetchExisting === "function") {
        window.AZAC_Att.fetchExisting("check-in");
        window.AZAC_Att.fetchExisting("mid-session");
      }
    });
    $("#azac_add_session_btn").on("click", function () {
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
      var $btn = $(this).prop("disabled", true);
      $.post(window.azacData.ajaxUrl, payload, function (res) {
        $btn.prop("disabled", false);
        if (res && res.success && res.data && res.data.sessions) {
          var $sel = $("#azac_session_select").empty();
          window.azacData.sessions = res.data.sessions;
          res.data.sessions.forEach(function (s) {
            var label = s.date + (s.time ? " " + s.time : "");
            $("<option/>", { value: s.date, text: label }).appendTo($sel);
          });
          if (res.data.selected) {
            $sel.val(res.data.selected);
            window.azacData.sessionDate = res.data.selected;
            if (window.AZACU && typeof window.AZACU.updateSessionTitle === "function") {
              window.AZACU.updateSessionTitle(res.data.selected);
            }
            if (window.AZAC_Att && typeof window.AZAC_Att.fetchExisting === "function") {
              window.AZAC_Att.fetchExisting("check-in");
              window.AZAC_Att.fetchExisting("mid-session");
            }
          }
        } else {
          alert("Lỗi thêm buổi học");
        }
      });
    });
    $("#azac_update_session_btn").on("click", function () {
      var old = $("#azac_session_select").val();
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
      var $btn = $(this).prop("disabled", true);
      $.post(window.azacData.ajaxUrl, payload, function (res) {
        $btn.prop("disabled", false);
        if (res && res.success && res.data && res.data.sessions) {
          var $sel = $("#azac_session_select").empty();
          window.azacData.sessions = res.data.sessions;
          res.data.sessions.forEach(function (s) {
            var label = s.date + (s.time ? " " + s.time : "");
            $("<option/>", { value: s.date, text: label }).appendTo($sel);
          });
          if (res.data.selected) {
            $sel.val(res.data.selected);
            window.azacData.sessionDate = res.data.selected;
            if (window.AZACU && typeof window.AZACU.updateSessionTitle === "function") {
              window.AZACU.updateSessionTitle(res.data.selected);
            }
            if (window.AZAC_Att && typeof window.AZAC_Att.fetchExisting === "function") {
              window.AZAC_Att.fetchExisting("check-in");
              window.AZAC_Att.fetchExisting("mid-session");
            }
          }
        } else {
          alert("Lỗi cập nhật buổi học");
        }
      });
    });
  });
})(jQuery);
