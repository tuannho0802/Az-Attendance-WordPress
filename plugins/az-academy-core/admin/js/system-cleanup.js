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

    // --- PHYSICAL FILE SCAN (BATCH PROCESSING) ---
    $(document).on(
      "click",
      "#azac-start-physical-scan",
      function (e) {
        e.preventDefault();

        var $btn = $(this);
        var $status = $(
          "#azac-physical-scan-status",
        );
        var $bar = $(
          "#azac-physical-scan-progress-bar",
        );
        var $barInner = $bar.find(".bar");
        var $tbody = $("#azac-system-tbody");

        $btn.prop("disabled", true);
        $status.show();
        $status
          .find(".status-text")
          .text("Đang khởi tạo...");
        $bar.show();
        $barInner.css("width", "0%");

        // Step 1: Init (Get Folders)
        $.post(
          AZAC_SYSTEM.ajaxUrl,
          {
            action: "azac_init_physical_scan",
            nonce: AZAC_SYSTEM.nonce,
          },
          function (res) {
            if (res.success) {
              var folders = res.data.folders;
              var total = res.data.total;
              processFolders(folders, 0, total);
            } else {
              alert(
                "Lỗi khởi tạo: " + res.data,
              );
              resetScanUI();
            }
          },
        ).fail(function () {
          alert(
            "Lỗi kết nối khi khởi tạo quét.",
          );
          resetScanUI();
        });

        function processFolders(
          folders,
          index,
          total,
        ) {
          if (index >= total) {
            // Done
            $status
              .find(".spinner")
              .removeClass("is-active");
            $status
              .find(".status-text")
              .text("Hoàn tất!");
            $status
              .find(".progress-percent")
              .text("100%");
            $barInner.css("width", "100%");
            $btn
              .prop("disabled", false)
              .text("Quét lại");

            // Check if empty results
            if (
              $tbody.find("tr").length === 0 ||
              ($tbody.find("tr").length === 1 &&
                $tbody.find("td[colspan]")
                  .length > 0)
            ) {
              // Do nothing, maybe show message?
              // If we found items, they are appended.
            }
            return;
          }

          var folder = folders[index];
          var percent = Math.round(
            (index / total) * 100,
          );
          $status
            .find(".status-text")
            .text(
              "Đang quét: " +
                (folder ? folder : "Root") +
                "...",
            );
          $status
            .find(".progress-percent")
            .text(percent + "%");
          $barInner.css("width", percent + "%");

          $.post(
            AZAC_SYSTEM.ajaxUrl,
            {
              action:
                "azac_scan_physical_folder",
              nonce: AZAC_SYSTEM.nonce,
              folder: folder,
            },
            function (res) {
              if (res.success) {
                var orphans = res.data.orphans;
                if (
                  orphans &&
                  orphans.length > 0
                ) {
                  // Remove "System Clean" message if exists
                  if (
                    $tbody.find("td[colspan]")
                      .length > 0
                  ) {
                    $tbody.empty();
                  }

                  // Append rows
                  orphans.forEach(
                    function (item) {
                      var rowHtml = `
                                  <tr data-id="${item.raw_id}" data-type="${item.type}" style="display:none;">
                                      <td class="check-column" data-label="Chọn" style="text-align:center; vertical-align:middle;">
                                          <input type="checkbox" class="cb-select-system" value="${item.type}|${item.raw_id}">
                                      </td>
                                      <td data-label="Loại dữ liệu"><span style="background:#666; color:#fff; padding:2px 6px; border-radius:4px; font-size:11px; text-transform:uppercase;">FILE RÁC</span></td>
                                      <td data-label="Dung lượng">${item.size}</td>
                                      <td data-label="Mô tả chi tiết">${item.desc}</td>
                                      <td data-label="Ngày phát hiện">${item.date}</td>
                                      <td data-label="Hành động" style="text-align:right;">
                                          <button type="button" class="button button-small azac-delete-system-item" data-id="${item.raw_id}" data-type="${item.type}" style="color:#a00; border-color:#a00;">Xóa</button>
                                      </td>
                                  </tr>
                              `;
                      var $row = $(rowHtml);
                      $tbody.append($row);
                      $row.fadeIn();
                    },
                  );
                }

                // Next
                processFolders(
                  folders,
                  index + 1,
                  total,
                );
              } else {
                console.error(
                  "Scan error for folder " +
                    folder +
                    ": " +
                    res.data,
                );
                // Continue anyway
                processFolders(
                  folders,
                  index + 1,
                  total,
                );
              }
            },
          ).fail(function () {
            console.error(
              "Network error for folder " +
                folder,
            );
            // Continue anyway
            processFolders(
              folders,
              index + 1,
              total,
            );
          });
        }

        function resetScanUI() {
          $btn.prop("disabled", false);
          $status.hide();
          $bar.hide();
        }
      },
    );

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