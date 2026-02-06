(function ($) {
  "use strict";

  $(document).ready(function () {
    const $form = $("#azac-add-student-form");
    const $btn = $("#btn-submit-student");
    const $msgBox = $("#register-message");
    
    // Inputs
    const $userLogin = $('#user_login');
    const $userEmail = $('#user_email');
    const $userPass = $('#user_pass');
    const $userPassConfirm = $('#user_pass_confirm');
    const $passMatchMsg = $('#pass-match-msg');
    
    // Buttons
    const $genPassBtn = $('#generate-password');
    const $togglePassBtn = $('#toggle-password');

    // Initial state
    validateForm();

    // Event Listeners
    $genPassBtn.on('click', function() {
        const password = generatePassword(12);
        $userPass.val(password);
        $userPassConfirm.val(password);
        validateForm();
        // Show password temporarily if hidden? Or just let user toggle.
        // Maybe change type to text temporarily to show it?
        if ($userPass.attr('type') === 'password') {
            $userPass.attr('type', 'text');
            $userPassConfirm.attr('type', 'text');
            $togglePassBtn.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
        }
    });

    $togglePassBtn.on('click', function() {
        const type = $userPass.attr('type') === 'password' ? 'text' : 'password';
        $userPass.attr('type', type);
        $userPassConfirm.attr('type', type);
        
        const $icon = $(this).find('.dashicons');
        if (type === 'text') {
            $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    // Real-time Validation
    $userLogin.on('input', validateForm);
    $userEmail.on('input', validateForm);
    $userPass.on('input', validateForm);
    $userPassConfirm.on('input', validateForm);

    function validateForm() {
        const login = $userLogin.val().trim();
        const email = $userEmail.val().trim();
        const pass = $userPass.val();
        const confirm = $userPassConfirm.val();
        
        let isValid = true;

        // Check required fields
        if (!login || !email || !pass || !confirm) {
            isValid = false;
        }

        // Validate Email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            isValid = false;
        }

        // Check Password Match
        if (pass && confirm) {
            if (pass === confirm) {
                $userPass.removeClass('input-error').addClass('input-success');
                $userPassConfirm.removeClass('input-error').addClass('input-success');
                $passMatchMsg.text('Mật khẩu khớp').css('color', 'green').show();
            } else {
                $userPass.addClass('input-error').removeClass('input-success');
                $userPassConfirm.addClass('input-error').removeClass('input-success');
                $passMatchMsg.text('Mật khẩu không khớp').css('color', 'red').show();
                isValid = false;
            }
        } else {
            $userPass.removeClass('input-error input-success');
            $userPassConfirm.removeClass('input-error input-success');
            $passMatchMsg.hide();
        }

        $btn.prop('disabled', !isValid);
    }

    function generatePassword(length) {
        const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
        let retVal = "";
        for (let i = 0, n = charset.length; i < length; ++i) {
            retVal += charset.charAt(Math.floor(Math.random() * n));
        }
        return retVal;
    }

    $form.on("submit", function (e) {
      e.preventDefault();

      // Final Validation before submit
      const pass = $userPass.val();
      const confirm = $userPassConfirm.val();

      if (pass !== confirm) {
        showError("Mật khẩu nhập lại không khớp.");
        return;
      }

      // Prepare AJAX
      const formData = new FormData(this);
      formData.append("action", "azac_register_student");
      formData.append("security", azac_obj.nonce);

      // Disable button
      $btn.prop("disabled", true).find(".btn-text").text("Đang xử lý...");

      $.ajax({
        url: azac_obj.ajax_url,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          if (response.success) {
            $form[0].reset();
            // Reset validation states
            $userPass.removeClass('input-success input-error');
            $userPassConfirm.removeClass('input-success input-error');
            $passMatchMsg.hide();
            validateForm(); // Re-validate (will disable button)
            
            showSuccess(response.data.message || "Thêm học viên thành công!");
          } else {
            showError(response.data.message || "Đã có lỗi xảy ra.");
          }
        },
        error: function (xhr, status, error) {
          showError("Lỗi kết nối: " + error);
        },
        complete: function () {
          // Restore button text but keep disabled state based on validation
          $btn.find(".btn-text").text("Thêm học viên mới");
          validateForm();
        },
      });
    });

    function showError(msg) {
      $msgBox.removeClass("notice-success").addClass("notice notice-error inline").show();
      $msgBox.find("p").text(msg);
    }

    function showSuccess(msg) {
      $msgBox.removeClass("notice-error").addClass("notice notice-success inline").show();
      $msgBox.find("p").text(msg);
      if (window.azacToast) {
        window.azacToast.show(msg, "success");
      }
    }
  });
})(jQuery);
