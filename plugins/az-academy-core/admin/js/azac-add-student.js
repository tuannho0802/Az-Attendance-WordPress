(function ($) {
  "use strict";

  $(document).ready(function () {
    const $form = $("#azac-add-student-form");
    const $btn = $form.find(
      "#btn-submit-student",
    );
    const $msgBox = $("#register-message");

    $form.on("submit", function (e) {
      e.preventDefault();

      // Client-side validation
      const pass = $("#user_pass").val();
      const confirm = $(
        "#user_pass_confirm",
      ).val();

      if (pass !== confirm) {
        showError(
          "Mật khẩu nhập lại không khớp.",
        );
        return;
      }

      // Prepare AJAX
      const formData = new FormData(this);
      formData.append(
        "action",
        "azac_register_student",
      );
      formData.append(
        "security",
        azac_obj.nonce,
      );

      // Disable button
      $btn
        .prop("disabled", true)
        .find(".btn-text")
        .text("Đang xử lý...");

      $.ajax({
        url: azac_obj.ajax_url,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (response.success) {
            // Success
            $form[0].reset();
            showSuccess(
              response.data.message ||
                "Thêm học viên thành công!",
            );

            // Reload to show flash toast if needed, or just stay
            // For better UX in admin, maybe just clear form and show success
          } else {
            showError(
              response.data.message ||
                "Đã có lỗi xảy ra.",
            );
          }
        },
        error: function (xhr, status, error) {
          showError("Lỗi kết nối: " + error);
        },
        complete: function () {
          $btn
            .prop("disabled", false)
            .find(".btn-text")
            .text("Thêm học viên mới");
        },
      });
    });

    function showError(msg) {
      $msgBox
        .removeClass("notice-success")
        .addClass("notice notice-error inline")
        .show();
      $msgBox.find("p").text(msg);
    }

    function showSuccess(msg) {
      $msgBox
        .removeClass("notice-error")
        .addClass(
          "notice notice-success inline",
        )
        .show();
      $msgBox.find("p").text(msg);

      // Trigger global toast if available
      if (window.azacToast) {
        window.azacToast.show(msg, "success");
      }
    }
  });
})(jQuery);
