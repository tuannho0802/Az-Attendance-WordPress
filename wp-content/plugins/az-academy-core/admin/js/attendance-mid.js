(function ($) {
  $(function () {
    $("#azac_start_mid_btn").on("click", function () {
      var payload = {
        action: "azac_generate_mid_pin",
        nonce: (window.AZAC_MID && AZAC_MID.midNonce) || "",
        class_id: window.azacData.classId,
        session_date: window.azacData.today,
      };
      var btn = $(this).prop("disabled", true);
      $.post(window.azacData.ajaxUrl, payload, function (res) {
        btn.prop("disabled", false);
        if (res && res.success && res.data) {
          var url = String(res.data.url || "");
          var pin = String(res.data.pin_code || "");
          $("#azac_mid_pin").text(pin);
          $("#azac_mid_qr").attr(
            "src",
            "https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=" + encodeURIComponent(url)
          );
          $("#azac-mid-modal").css("display", "flex");
        } else {
          alert("Không thể tạo PIN");
        }
      });
    });
    $("#azac_mid_close_modal").on("click", function () {
      $("#azac-mid-modal").hide();
    });
    $("#azac_end_mid_btn").on("click", function () {
      var payload = {
        action: "azac_close_mid_pin",
        nonce: (window.AZAC_MID && AZAC_MID.closeNonce) || "",
        class_id: window.azacData.classId,
      };
      var btn = $(this).prop("disabled", true);
      $.post(window.azacData.ajaxUrl, payload, function (res) {
        btn.prop("disabled", false);
        if (res && res.success) {
          alert("Đã kết thúc điểm danh giữa giờ");
          $("#azac-mid-modal").hide();
        } else {
          alert("Không thể kết thúc điểm danh");
        }
      });
    });
  });
})(jQuery);

