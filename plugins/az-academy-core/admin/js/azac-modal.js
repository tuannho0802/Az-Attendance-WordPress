(function($) {
    'use strict';

    // Global Object
    window.AzacModal = {
        init: function() {
            if ($('#azac-modal-overlay').length === 0) {
                this.createModal();
            }
        },

        createModal: function() {
            var html = 
                '<div id="azac-modal-overlay" class="azac-modal-overlay">' +
                    '<div class="azac-modal-container">' +
                        '<div class="azac-modal-header">' +
                            '<h3 class="azac-modal-title">Xác nhận</h3>' +
                            '<span class="azac-modal-close">&times;</span>' +
                        '</div>' +
                        '<div class="azac-modal-body">' +
                            '<div class="azac-modal-icon dashicons dashicons-warning"></div>' +
                            '<div class="azac-modal-content"></div>' +
                        '</div>' +
                        '<div class="azac-modal-footer">' +
                            '<button type="button" class="azac-modal-btn azac-btn-cancel">Hủy bỏ</button>' +
                            '<button type="button" class="azac-modal-btn azac-btn-confirm">Xác nhận</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            
            $('body').append(html);

            // Bind events
            $(document).on('click', '.azac-modal-close, .azac-btn-cancel', function() {
                AzacModal.close(false);
            });
            
            // Close on overlay click
            $(document).on('click', '.azac-modal-overlay', function(e) {
                if ($(e.target).hasClass('azac-modal-overlay')) {
                    AzacModal.close(false);
                }
            });
        },

        show: function(title, message, options) {
          this.init();

          var $overlay = $(
            "#azac-modal-overlay",
          );
          var $title = $overlay.find(
            ".azac-modal-title",
          );
          var $content = $overlay.find(
            ".azac-modal-content",
          );
          var $confirmBtn = $overlay.find(
            ".azac-btn-confirm",
          );

          // Defaults
          options = options || {};
          var btnText =
            options.confirmText || "Xác nhận";
          var btnClass = options.isDanger
            ? "azac-danger"
            : "";

          // Set content
          $title.text(title || "Xác nhận");
          $content.html(
            message ||
              "Bạn có chắc chắn muốn thực hiện hành động này?",
          );
          $confirmBtn
            .text(btnText)
            .removeClass("azac-danger")
            .addClass(btnClass);

          // Reset handlers
          $confirmBtn
            .off("click")
            .on("click", function () {
              AzacModal.close(true);
            });

          // Show
          $overlay.css("display", "flex");
          // Small timeout to ensure display:flex applies before opacity transition triggers
          setTimeout(function () {
            $overlay.addClass(
              "azac-modal-visible",
            );
          }, 10);

          $("body").addClass("azac-modal-open");

          // Return Promise
          return new Promise(function (
            resolve,
          ) {
            AzacModal.resolve = resolve;
          });
        },

        close: function(confirmed) {
            var $overlay = $('#azac-modal-overlay');
            $overlay.removeClass('azac-modal-visible');
            setTimeout(function() {
                $overlay.hide();
                $('body').removeClass('azac-modal-open');
                if (AzacModal.resolve) {
                    AzacModal.resolve(confirmed);
                    AzacModal.resolve = null;
                }
            }, 300);
        }
    };

    // Global Alias
    window.azacConfirm = function(title, message, options) {
        return AzacModal.show(title, message, options);
    };

})(jQuery);
