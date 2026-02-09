(function ($) {
  /**
   * AZ Academy System Cleanup JavaScript
   *
   * Xử lý giao diện quản lý dọn dẹp dữ liệu:
   * - Tải dữ liệu quét (scan)
   * - Hiển thị danh sách mồ côi (gồm cả điểm danh không hợp lệ - NEW)
   * - Xử lý xóa hàng loạt (bulk delete) với chunking
   * - Quản lý logs
   *
   * Sửa lỗi:
   * - Thêm xử lý loại 'attendance_integrity' mới
   * - Cải thiện error handling
   * - Tối ưu chunking để tránh timeout
   */

  /**
   * PHẦN 1: FUNCTION TOÀN CẦU - Định nghĩa ngay đầu
   * Không thể gọi đến trước khi định nghĩa
   */

  /**
   * Tải dữ liệu quét (Scan)
   *
   * Hiển thị các loại dữ liệu mồ côi dưới dạng card:
   * - Media không sử dụng
   * - File vật lý lạc lõng
   * - Điểm danh không hợp lệ (NEW)
   * - Review không liên kết
   * - Metadata rác
   *
   * @param {int} page Trang hiện tại
   */
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
      timeout: 30000, // 30 giây timeout
      success: function (res) {
        $container.css("opacity", "1");
        if (res.success) {
          // Hiển thị HTML quét
          $container.html(res.data.html);
          AZAC_SYSTEM.currentScanPage = page;

          // Cập nhật tổng số item
          if (res.data.total_text) {
            $("#azac-scan-total-count").text(
              res.data.total_text,
            );
          }

          // Gắn event handler cho card buttons (NEW)
          attachCardButtonHandlers();
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

  /**
   * Tải chi tiết mồ côi theo loại
   *
   * Gọi sau khi user click "Xem chi tiết" trên card
   * Hiển thị bảng danh sách các item mồ côi
   *
   * @param {string} type Loại dữ liệu: media, physical, attendance_integrity, reviews, meta
   * @param {int}    page Trang hiện tại
   */
  window.loadOrphanedDetails = function (
    type,
    page,
  ) {
    var $container = $(
      "#azac-orphaned-container",
    );
    if (!$container.length) {
      // Nếu không có container, tạo mới
      $container = $("<div>")
        .attr("id", "azac-orphaned-container")
        .insertAfter("#azac-scan-container");
    }

    $container.css("opacity", "0.5");
    console.log(
      "Loading orphaned details: " +
        type +
        ", page: " +
        page,
    );

    $.ajax({
      url: AZAC_SYSTEM.ajaxUrl,
      type: "POST",
      data: {
        action: "azac_fetch_orphaned_details",
        nonce: AZAC_SYSTEM.nonce,
        type: type,
        paged: page,
      },
      timeout: 30000,
      success: function (res) {
        $container.css("opacity", "1");
        if (res.success) {
          $container.html(res.data.html);
          AZAC_SYSTEM.currentOrphanedType =
            type;
          AZAC_SYSTEM.currentOrphanedPage =
            page;

          // Gắn event handler cho checkbox và delete button
          attachOrphanedHandlers();
        } else {
          $container.html(
            '<p class="error">Lỗi tải chi tiết: ' +
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
            "Hết thời gian chờ. Vui lòng thử lại.";
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

  /**
   * Tải logs hoạt động
   *
   * @param {int} page Trang hiện tại
   */
  window.loadLogs = function (page) {
    var $container = $("#azac-logs-container");
    $container.css("opacity", "0.5");

    // Lấy bộ lọc nếu có
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
      timeout: 30000,
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

  /**
   * PHẦN 2: HELPER FUNCTION
   */

  /**
   * Gắn event handler cho các card button
   *
   * NEW: Xử lý click vào card để hiển thị chi tiết
   */
  function attachCardButtonHandlers() {
    $(document).on(
      "click",
      ".azac-card-btn",
      function (e) {
        e.preventDefault();
        var type = $(this).data("type");
        console.log(
          "Card button clicked: " + type,
        );

        // Tải chi tiết
        loadOrphanedDetails(type, 1);
      },
    );
  }

  /**
   * Gắn event handler cho các item mồ côi
   *
   * NEW: Xử lý checkbox, delete button
   */
  function attachOrphanedHandlers() {
    // Select All checkbox
    $(document).on(
      "change",
      "#cb-select-all-orphaned",
      function () {
        $(".cb-select-orphaned").prop(
          "checked",
          $(this).prop("checked"),
        );
      },
    );

    // Delete single item
    $(document).on(
      "click",
      ".azac-delete-orphaned-item",
      function (e) {
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.data("id");
        var type =
          AZAC_SYSTEM.currentOrphanedType;
        var items = [type + "|" + id];

        azacConfirm(
          "Xác nhận xóa",
          "Bạn có chắc chắn muốn xóa item này?",
          { isDanger: true },
        ).then(function (confirmed) {
          if (!confirmed) return;

          var $row = $btn.closest("tr");
          performCleanup(items, function () {
            $row.fadeOut(300, function () {
              $(this).remove();
            });
            setTimeout(function () {
              loadOrphanedDetails(type, 1);
            }, 500);
          });
        });
      },
    );

    // Bulk delete orphaned
    $(document).on(
      "click",
      "#azac-do-orphaned-bulk",
      function (e) {
        e.preventDefault();
        var $btn = $(this);
        var action = $(
          "#azac-bulk-action-orphaned",
        ).val();
        var type =
          AZAC_SYSTEM.currentOrphanedType;

        if (action !== "delete") return;

        var items = [];
        $(".cb-select-orphaned:checked").each(
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

        azacConfirm(
          "Xác nhận dọn dẹp",
          "Bạn có chắc chắn muốn xóa " +
            items.length +
            " item?",
          { isDanger: true },
        ).then(function (confirmed) {
          if (!confirmed) return;

          $btn.prop("disabled", true);
          performCleanup(items, function () {
            $btn.prop("disabled", false);
            loadOrphanedDetails(type, 1);
          });
        });
      },
    );
  }

  /**
   * PHẦN 3: MAIN INITIALIZATION
   */
  $(function () {
    console.log("System Cleanup JS Ready");

    /**
     * ===== TAB PAGINATION =====
     */
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
        } else if (tab === "orphaned") {
          var type =
            AZAC_SYSTEM.currentOrphanedType;
          loadOrphanedDetails(type, page);
        }
      },
    );

    /**
     * ===== FILTER (LOGS) =====
     */
    $("#azac-log-filter-submit").on(
      "click",
      function (e) {
        e.preventDefault();
        loadLogs(1);
      },
    );

    /**
     * ===== LOG ACTIONS =====
     */

    // Select All Logs checkbox
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

    // Bulk action logs
    $(document).on(
      "click",
      "#azac-log-do-bulk",
      function (e) {
        e.preventDefault();
        var $btn = $(this);
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
              "Vui lòng chọn ít nhất một nhật kỳ.",
            );
            return;
          }
        }

        azacConfirm(
          "Xác nhận xử lý nhật kỳ",
          "Bạn có chắc chắn muốn thực hiện hành động này?",
          { isDanger: true },
        ).then(function (confirmed) {
          if (!confirmed) return;

          $btn
            .prop("disabled", true)
            .text("Đang xử lý...");

          $.post(
            AZAC_SYSTEM.ajaxUrl,
            {
              action: "azac_cleanup_logs",
              nonce: AZAC_SYSTEM.nonce,
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
        });
      },
    );

    // Cleanup old logs (> 30 days)
    $(document).on(
      "click",
      "#azac-cleanup-logs-old",
      function (e) {
        e.preventDefault();
        var $btn = $(this);

        azacConfirm(
          "Xác nhận xóa nhật kỳ cũ",
          "Bạn có chắc chắn muốn xóa các nhật kỳ cũ hơn 30 ngày?",
          { isDanger: true },
        ).then(function (confirmed) {
          if (!confirmed) return;

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
                  "Đã xóa nhật kỳ cũ thành công!",
                );
              } else {
                alert(
                  "Lỗi: " +
                    (res.data || "Unknown"),
                );
              }
            },
          );
        });
      },
    );

    // Cleanup all logs
    $(document).on(
      "click",
      "#azac-cleanup-logs-all",
      function (e) {
        e.preventDefault();
        var $btn = $(this);

        azacConfirm(
          "Xác nhận xóa toàn bộ nhật kỳ",
          "Hành động này không thể hoàn tác. Bạn chắc chắn?",
          { isDanger: true },
        ).then(function (confirmed) {
          if (!confirmed) return;

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
                alert(
                  "Đã xóa toàn bộ nhật kỳ!",
                );
                loadLogs(1);
              } else {
                alert(
                  "Lỗi: " +
                    (res.data || "Unknown"),
                );
              }
            },
          );
        });
      },
    );
  });

  /**
   * PHẦN 4: XỬ LÝ XÓA HÀNG LOẠT (CHUNKING)
   *
   * Chia thành chunk nhỏ để:
   * 1. Tránh timeout (quá nhiều item xóa cùng lúc)
   * 2. Hiển thị progress bar
   * 3. Xử lý lỗi từng chunk
   *
   * Hỗ trợ loại dữ liệu mới: attendance_integrity
   */
  window.performCleanup = function (
    items,
    onSuccess,
    onError,
  ) {
    // Chunk size: 20 item mỗi request
    // Vì attendance_integrity có thể nhiều hơn, nên giữ chunk nhỏ
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

    // Tìm button có data-type (scan hoặc orphaned)
    var $bulkBtn = $(
      "#azac-do-system-bulk, #azac-do-orphaned-bulk",
    ).filter(function () {
      return !$(this).prop("disabled");
    });
    var originalBtnText =
      $bulkBtn.text() || "Xóa";

    function processNextChunk() {
      if (currentChunk >= totalChunks) {
        // Hoàn thành
        if ($bulkBtn.length) {
          $bulkBtn.text(originalBtnText);
        }
        if (onSuccess) {
          onSuccess();
        }
        return;
      }

      // Cập nhật progress
      if (totalChunks > 1 && $bulkBtn.length) {
        var percent = Math.round(
          (currentChunk / totalChunks) * 100,
        );
        $bulkBtn.text(
          "Đang xóa... " + percent + "%",
        );
      }

      // Gửi request xóa chunk hiện tại
      $.post(
        AZAC_SYSTEM.ajaxUrl,
        {
          action: "azac_system_cleanup",
          nonce: AZAC_SYSTEM.nonce,
          items: chunks[currentChunk],
        },
        function (res) {
          if (res.success) {
            // Chunk xóa thành công
            console.log(
              "Chunk " +
                (currentChunk + 1) +
                " deleted successfully",
            );

            // Cập nhật thống kê nếu có
            if (
              res.data &&
              res.data.counts &&
              res.data.freed_bytes
            ) {
              var totalDeleted =
                (res.data.counts.media || 0) +
                (res.data.counts.physical ||
                  0) +
                (res.data.counts
                  .attendance_integrity || 0) +
                (res.data.counts.reviews || 0) +
                (res.data.counts.meta || 0);

              var mb = (
                res.data.freed_bytes /
                (1024 * 1024)
              ).toFixed(2);
              $("#azac-scan-total-count").text(
                "Đã xóa " +
                  totalDeleted +
                  " mục, giải phóng " +
                  mb +
                  " MB",
              );
            }

            currentChunk++;
            processNextChunk();
          } else {
            // Chunk xóa lỗi - dừng
            alert(
              "Lỗi xử lý chunk " +
                (currentChunk + 1) +
                ": " +
                (res.data || "Unknown error"),
            );
            hasError = true;
            if ($bulkBtn.length) {
              $bulkBtn.text(originalBtnText);
            }
            if (onError) {
              onError();
            }
          }
        },
      ).fail(function () {
        alert("Lỗi kết nối Server.");
        hasError = true;
        if ($bulkBtn.length) {
          $bulkBtn.text(originalBtnText);
        }
        if (onError) {
          onError();
        }
      });
    }

    // Bắt đầu xử lý
    processNextChunk();
  };
})(jQuery);