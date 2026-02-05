(function ($) {
  // Expose to global for init - Defined immediately
  window.loadLogs = function (page) {
    var $container = $("#azac-logs-container");
    $container.css("opacity", "0.5");

    // Collect Filters
    var filterAction = $(
      "#azac-log-filter-action",
    ).val();
    var filterDateStart = $(
      "#azac-log-filter-date-start",
    ).val();
    var filterDateEnd = $(
      "#azac-log-filter-date-end",
    ).val();

    console.log("Loading Logs Page: " + page);

    $.ajax({
      url: AZAC_SYSTEM.ajaxUrl,
      type: "POST",
      data: {
        action: "azac_get_logs",
        nonce: AZAC_SYSTEM.nonce,
        paged: page,
        filter_action: filterAction,
        filter_date_start: filterDateStart,
        filter_date_end: filterDateEnd,
      },
      timeout: 30000, // 30s Timeout
      success: function (res) {
        $container.css("opacity", "1");
        if (res.success) {
          $container.html(res.data.html);
          AZAC_SYSTEM.currentLogPage = page;
        } else {
          $container.html(
            '<p class="error">Lỗi tải dữ liệu: ' +
              (res.data || "Unknown") +
              "</p>",
          );
        }
      },
      error: function (
        jqXHR,
        textStatus,
        errorThrown,
      ) {
        $container.css("opacity", "1");
        var msg = "Lỗi kết nối Server.";
        if (textStatus === "timeout") {
          msg =
            "Hết thời gian chờ (Timeout). Vui lòng thử lại.";
        } else if (textStatus === "error") {
          msg =
            "Lỗi máy chủ (" +
            jqXHR.status +
            ").";
        }
        $container.html(
          '<div class="notice notice-error inline"><p>' +
            msg +
            "</p></div>",
        );
        console.error(
          "AJAX Error:",
          textStatus,
          errorThrown,
        );
      },
    });
  };

  window.loadScanData = function (page) {
    var $container = $("#azac-scan-container");
    $container.css("opacity", "0.5");

    console.log(
      "Loading Scan Data Page: " + page,
    );

    $.ajax({
      url: AZAC_SYSTEM.ajaxUrl,
      type: "POST",
      data: {
        action: "azac_get_scan_data",
        nonce: AZAC_SYSTEM.nonce,
        paged: page,
      },
      timeout: 30000, // 30s Timeout
      success: function (res) {
        $container.css("opacity", "1");
        if (res.success) {
          $container.html(res.data.html);
          AZAC_SYSTEM.currentScanPage = page;
          if (res.data.total_text) {
            $("#azac-scan-total-count").text(
              res.data.total_text,
            );
          }
        } else {
          $container.html(
            '<p class="error">Lỗi tải dữ liệu: ' +
              (res.data || "Unknown") +
              "</p>",
          );
        }
      },
      error: function (
        jqXHR,
        textStatus,
        errorThrown,
      ) {
        $container.css("opacity", "1");
        var msg = "Lỗi kết nối Server.";
        if (textStatus === "timeout") {
          msg =
            "Hết thời gian chờ (Timeout). Vui lòng thử lại.";
        } else if (textStatus === "error") {
          msg =
            "Lỗi máy chủ (" +
            jqXHR.status +
            ").";
        }
        $container.html(
          '<div class="notice notice-error inline"><p>' +
            msg +
            "</p></div>",
        );
        console.error(
          "AJAX Error:",
          textStatus,
          errorThrown,
        );
      },
    });
  };

  $(function () {
    console.log("System Cleanup JS Ready");

    // Select All (Delegated) - System
    $(document).on(
      "change",
      "#cb-select-all-system",
      function () {
        $(".cb-select-system").prop(
          "checked",
          $(this).prop("checked"),
        );
      },
    );

    // Select All (Delegated) - Logs
    $(document).on(
      "change",
      "#cb-select-all-logs",
      function () {
        $(".cb-select-log").prop(
          "checked",
          $(this).prop("checked"),
        );
      },
    );

    // Single Delete (Delegated) - System
    $(document).on(
      "click",
      ".azac-delete-system-item",
      function (e) {
        e.preventDefault();
        if (!confirm(AZAC_SYSTEM.confirmDelete))
          return;

        var id = $(this).data("id");
        var type = $(this).data("type");
        var $row = $(this).closest("tr");
        var items = [type + "|" + id];

        performCleanup(items, function () {
          $row.fadeOut(300, function () {
            $(this).remove();
          });
          setTimeout(function () {
            if (
              $("#azac-system-tbody tr")
                .length === 0
            ) {
              loadScanData(
                AZAC_SYSTEM.currentScanPage ||
                  1,
              );
            }
          }, 500);
        });
      },
    );

    // Bulk Delete (System)
    $(document).on(
      "click",
      "#azac-do-system-bulk",
      function (e) {
        e.preventDefault();
        var action = $(
          "#azac-bulk-action-system",
        ).val();
        if (action !== "delete") return;

        var items = [];
        $(".cb-select-system:checked").each(
          function () {
            items.push($(this).val());
          },
        );

        if (items.length === 0) {
          alert(
            "Vui lòng chọn ít nhất một mục.",
          );
          return;
        }

        if (
          !confirm(
            AZAC_SYSTEM.confirmBulkDelete,
          )
        )
          return;

        var $btn = $(this);
        $btn.prop("disabled", true);

        performCleanup(
          items,
          function () {
            $btn.prop("disabled", false);
            loadScanData(
              AZAC_SYSTEM.currentScanPage || 1,
            );
          },
          function () {
            $btn.prop("disabled", false);
          },
        );
      },
    );

    // Bulk Delete (Logs)
    $(document).on(
      "click",
      "#azac-log-do-bulk",
      function (e) {
        e.preventDefault();
        var action = $(
          "#azac-log-bulk-action",
        ).val();
        if (action === "-1") return;

        var items = [];
        if (action === "delete_selected") {
          $(".cb-select-log:checked").each(
            function () {
              items.push($(this).val());
            },
          );
          if (items.length === 0) {
            alert(
              "Vui lòng chọn ít nhất một nhật ký.",
            );
            return;
          }
        }

        if (
          !confirm(
            "Bạn có chắc chắn muốn thực hiện hành động này?",
          )
        )
          return;

        var $btn = $(this);
        $btn
          .prop("disabled", true)
          .text("Đang xử lý...");

        $.post(
          AZAC_SYSTEM.ajaxUrl,
          {
            action: "azac_cleanup_logs",
            nonce: AZAC_SYSTEM.nonce, // Using system nonce for simplicity, but ideally should use specific nonce
            mode: action,
            items: items,
          },
          function (res) {
            $btn
              .prop("disabled", false)
              .text("Áp dụng");
            if (res.success) {
              alert("Thành công!");
              loadLogs(1);
            } else {
              alert(
                "Lỗi: " +
                  (res.data || "Unknown"),
              );
            }
          },
        );
      },
    );

    // New Cleanup Handlers (Card Buttons)
    $(document).on(
      "click",
      "#azac-cleanup-logs-old",
      function (e) {
        e.preventDefault();
        if (
          !confirm(
            "Bạn có chắc chắn muốn xóa các nhật ký cũ hơn 30 ngày?",
          )
        )
          return;

        var $btn = $(this);
        $btn
          .prop("disabled", true)
          .text("Đang xử lý...");

        $.post(
          AZAC_SYSTEM.ajaxUrl,
          {
            action: "azac_cleanup_logs",
            nonce: AZAC_SYSTEM.nonce,
            mode: "older_30",
          },
          function (res) {
            $btn
              .prop("disabled", false)
              .text("Xóa log > 30 ngày");
            if (res.success) {
              alert(
                "Đã xóa nhật ký cũ thành công!",
              );
              // Reload logs if on log tab, but here we are on scan tab.
              // Maybe nothing visual to update on this tab except success msg
            } else {
              alert(
                "Lỗi: " +
                  (res.data || "Unknown"),
              );
            }
          },
        );
      },
    );

    $(document).on(
      "click",
      "#azac-cleanup-logs-all",
      function (e) {
        e.preventDefault();
        if (!confirm(AZAC_SYSTEM.confirmDelete))
          return;

        var $btn = $(this);
        $btn
          .prop("disabled", true)
          .text("Đang xử lý...");

        $.post(
          AZAC_SYSTEM.ajaxUrl,
          {
            action: "azac_cleanup_logs",
            nonce: AZAC_SYSTEM.nonce,
            mode: "delete_all",
          },
          function (res) {
            $btn
              .prop("disabled", false)
              .text("Xóa toàn bộ");
            if (res.success) {
              alert("Đã xóa toàn bộ nhật ký!");
            } else {
              alert(
                "Lỗi: " +
                  (res.data || "Unknown"),
              );
            }
          },
        );
      },
    );
    // Fixed syntax error: removed garbage code
 

    // --- PAGINATION CLICK HANDLERS ---
    $(document).on(
      "click",
      ".azac-pagination a",
      function (e) {
        e.preventDefault();
        var page = $(this).data("page");
        var tab = $(this)
          .closest(".azac-pagination-wrapper")
          .data("tab");

        if (tab === "logs") {
          loadLogs(page);
        } else if (tab === "scan") {
          loadScanData(page);
        }
      },
    );

    // --- FILTER CLICK HANDLERS ---
    $("#azac-log-filter-submit").on(
      "click",
      function (e) {
        e.preventDefault();
        loadLogs(1);
      },
    );

    // --- LOAD FUNCTIONS MOVED TO TOP ---

    function performCleanup(
      items,
      onSuccess,
      onError,
    ) {
      // Chunking to avoid timeout
      var chunkSize = 20;
      var chunks = [];
      for (
        var i = 0;
        i < items.length;
        i += chunkSize
      ) {
        chunks.push(
          items.slice(i, i + chunkSize),
        );
      }

      var totalChunks = chunks.length;
      var currentChunk = 0;
      var hasError = false;

      // Create Progress UI if not exists (for bulk)
      var $bulkBtn = $("#azac-do-system-bulk");
      var originalBtnText = $bulkBtn.text();

      function processNextChunk() {
        if (currentChunk >= totalChunks) {
          // Done
          if ($bulkBtn.prop("disabled")) {
            $bulkBtn.text(originalBtnText);
          }
          if (onSuccess) onSuccess();
          return;
        }

        // Update UI
        if (totalChunks > 1) {
          var percent = Math.round(
            (currentChunk / totalChunks) * 100,
          );
          $bulkBtn.text(
            "Đang xóa... " + percent + "%",
          );
        }

        $.post(
          AZAC_SYSTEM.ajaxUrl,
          {
            action: "azac_system_cleanup",
            nonce: AZAC_SYSTEM.nonce,
            items: chunks[currentChunk],
          },
          function (res) {
            if (res.success) {
              if (res.data && res.data.counts) {
                var totalDeleted =
                  (res.data.counts.media || 0) +
                  (res.data.counts.physical || 0) +
                  (res.data.counts.attendance || 0) +
                  (res.data.counts.reviews || 0) +
                  (res.data.counts.meta || 0);
                if (typeof res.data.freed_bytes === "number") {
                  var mb = (res.data.freed_bytes / (1024 * 1024)).toFixed(2);
                  $("#azac-scan-total-count").text(
                    "Đã xóa " + totalDeleted + " mục, giải phóng " + mb + " MB",
                  );
                }
              }
              currentChunk++;
              processNextChunk();
            } else {
              alert(
                "Lỗi tại chunk " +
                  (currentChunk + 1) +
                  ": " +
                  (res.data || "Unknown error"),
              );
              hasError = true;
              if (onError) onError();
              // Stop processing on error? Or continue? Usually stop.
              if ($bulkBtn.prop("disabled")) {
                $bulkBtn.text(originalBtnText);
              }
            }
          },
        ).fail(function () {
          alert("Lỗi kết nối Server.");
          hasError = true;
          if (onError) onError();
          if ($bulkBtn.prop("disabled")) {
            $bulkBtn.text(originalBtnText);
          }
        });
      }

      processNextChunk();
    }
  });
})(jQuery);
