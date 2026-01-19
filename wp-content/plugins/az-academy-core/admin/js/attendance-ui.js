(function ($) {
  $(function () {
    $(".azac-tab-btn").on("click", function () {
      $(".azac-tab-btn").removeClass("button-primary");
      $(this).addClass("button-primary");
      $(".azac-tab").removeClass("active");
      $($(this).data("target")).addClass("active");
      var target = $(this).data("target");
      var t = target === "#azac-checkin" ? "check-in" : "mid-session";
      if (window.AZAC_Att && typeof window.AZAC_Att.fetchExisting === "function") {
        window.AZAC_Att.fetchExisting(t);
      }
    });
    $("#azac-submit-checkin").on("click", function () {
      if (window.AZAC_Att && typeof window.AZAC_Att.submit === "function") {
        window.AZAC_Att.submit("check-in");
      }
    });
    $("#azac-submit-mid").on("click", function () {
      if (window.AZAC_Att && typeof window.AZAC_Att.submit === "function") {
        window.AZAC_Att.submit("mid-session");
      }
    });
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
    if (window.AZAC_Att && typeof window.AZAC_Att.fetchExisting === "function") {
      window.AZAC_Att.fetchExisting("check-in");
      window.AZAC_Att.fetchExisting("mid-session");
    }
    if (window.AZACU && typeof window.AZACU.updateSessionTitle === "function") {
      window.AZACU.updateSessionTitle(window.azacData.sessionDate || window.azacData.today);
    }
  });
})(jQuery);

