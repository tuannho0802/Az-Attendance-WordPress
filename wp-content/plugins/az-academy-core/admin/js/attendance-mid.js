(function ($) {
  $(function () {
    $("#azac_start_mid_btn").on("click", function () {
      var reviewUrl =
        (window.AZAC_MID && AZAC_MID.reviewUrl) ||
        (window.location.origin + "/qr-checkin/?class_id=" + encodeURIComponent(window.azacData.classId));
      $("#azac_mid_qr").attr(
        "src",
        "https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=" + encodeURIComponent(reviewUrl)
      );
      $("#azac-mid-modal").css("display", "flex");
    });
    $("#azac_mid_close_modal").on("click", function () {
      $("#azac-mid-modal").hide();
    });
  });
})(jQuery);
