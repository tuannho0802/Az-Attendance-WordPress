jQuery(document).ready(function ($) {
    // --- TABS ---
    $('.azac-nav-item').on('click', function () {
        $('.azac-nav-item').removeClass('active');
        $(this).addClass('active');
        $('.azac-tab-pane').removeClass('active');
        $('#tab-' + $(this).data('tab')).addClass('active');
    });

    // --- AJAX HANDLERS ---
    
    // 1. SCAN USERS
    $("#btn-scan-users").on(
      "click",
      function () {
        const $btn = $(this);
        $btn
          .addClass("disabled")
          .text("Đang quét...");
        $("#users-results-area").addClass(
          "hidden",
        );

        $.post(
          azacConfig.ajaxUrl,
          {
            action: "azac_sp_scan_users",
            _ajax_nonce: azacConfig.nonce,
          },
          function (res) {
            $btn
              .removeClass("disabled")
              .text("Bắt đầu quét");
            if (res.success) {
              renderUsers(res.data);
              $(
                "#users-results-area",
              ).removeClass("hidden");
            } else {
              alert("Lỗi: " + res.data);
            }
          },
        );
      },
    );

    function renderUsers(users) {
      const $tbody = $("#table-users tbody");
      $tbody.empty();

      if (users.length === 0) {
        $tbody.html(
          '<tr><td colspan="6" style="text-align:center">Hệ thống an toàn. Không tìm thấy user đáng ngờ.</td></tr>',
        );
        return;
      }

      users.forEach((u) => {
        let badgeClass =
          u.status === "warning"
            ? "badge-warning"
            : u.status === "danger"
              ? "badge-danger"
              : "badge-success";

        // Access Control for Button
        let actionHtml = azacConfig.isAdmin
          ? `<button class="button button-small action-fix-user" data-id="${u.ID}" data-action="lock">Khóa</button>`
          : `<span class="dashicons dashicons-lock" title="Yêu cầu quyền Admin"></span> Restricted`;

        let row = `
                <tr>
                    <td data-label="Avatar">${u.avatar}</td>
                    <td data-label="User"><strong>${u.user_login}</strong><br><small>${u.user_email}</small></td>
                    <td data-label="Vai trò">${u.roles}</td>
                    <td data-label="Lý do cảnh báo"><span class="azac-reason-text">${u.reason}</span></td>
                    <td data-label="Trạng thái"><span class="azac-badge ${badgeClass}">${u.status_text}</span></td>
                    <td data-label="Hành động">${actionHtml}</td>
                </tr>
            `;
        $tbody.append(row);
      });
    }

    // 2. SCAN MALWARE
    $("#btn-scan-malware").on(
      "click",
      function () {
        const $btn = $(this);
        $btn.addClass("hidden");
        $("#malware-progress-wrap").removeClass(
          "hidden",
        );
        $("#malware-results-area").addClass(
          "hidden",
        );
        $("#table-malware tbody").empty();

        // Start Scan
        scanMalwareChunk(0, "posts");
      },
    );

    function scanMalwareChunk(offset, phase) {
      $.post(
        azacConfig.ajaxUrl,
        {
          action: "azac_sp_scan_malware",
          offset: offset,
          phase: phase,
          _ajax_nonce: azacConfig.nonce,
        },
        function (res) {
          if (res.success) {
            const data = res.data;
            // Update Progress
            $(".azac-progress-fill").css(
              "width",
              data.percent + "%",
            );
            $(".azac-progress-text").text(
              `Đang quét... ${data.percent}% (${data.phase})`,
            );

            // Append issues if any
            if (
              data.issues &&
              data.issues.length > 0
            ) {
              renderMalware(data.issues);
            }

            if (!data.done) {
              scanMalwareChunk(
                data.next_offset,
                data.phase,
              );
            } else {
              // Done
              $(
                "#malware-progress-wrap",
              ).addClass("hidden");
              $("#btn-scan-malware")
                .removeClass("hidden")
                .text("Quét lại");
              $(
                "#malware-results-area",
              ).removeClass("hidden");

              if (
                $("#table-malware tbody tr")
                  .length === 0
              ) {
                $("#table-malware tbody").html(
                  '<tr><td colspan="4" style="text-align:center">Hệ thống sạch. Không phát hiện mã độc.</td></tr>',
                );
              }
            }
          } else {
            alert("Scan Error");
            $("#btn-scan-malware").removeClass(
              "hidden",
            );
          }
        },
      );
    }

    function renderMalware(issues) {
      const $tbody = $("#table-malware tbody");
      issues.forEach((i) => {
        let actionHtml = azacConfig.isAdmin
          ? `<button class="button button-small action-fix-malware" data-id="${i.id}">Xử lý</button>`
          : `<span class="dashicons dashicons-lock" title="Restricted"></span>`;

        let row = `
                <tr>
                    <td data-label="Loại"><span class="azac-badge badge-warning">${i.type}</span></td>
                    <td data-label="Đối tượng"><strong>${i.name}</strong><br><small>ID: ${i.id}</small></td>
                    <td data-label="Lý do / Đoạn mã nghi vấn">
                        <span class="azac-reason-text">${i.reason}</span>
                        <code class="azac-code-block">${i.snippet}</code>
                    </td>
                    <td data-label="Hành động">${actionHtml}</td>
                </tr>
            `;
        $tbody.append(row);
      });
    }

    // 3. CHECK INTEGRITY
    $("#btn-check-integrity").on(
      "click",
      function () {
        const $btn = $(this);
        $btn
          .addClass("disabled")
          .text("Đang kiểm tra...");
        $("#integrity-results-area").addClass(
          "hidden",
        );

        $.post(
          azacConfig.ajaxUrl,
          {
            action: "azac_sp_check_integrity",
            _ajax_nonce: azacConfig.nonce,
          },
          function (res) {
            $btn
              .removeClass("disabled")
              .text("Kiểm tra ngay");
            if (res.success) {
              renderIntegrity(res.data);
              $(
                "#integrity-results-area",
              ).removeClass("hidden");
            }
          },
        );
      },
    );

    function renderIntegrity(items) {
      const $tbody = $(
        "#table-integrity tbody",
      );
      $tbody.empty();

      if (items.length === 0) {
        $tbody.html(
          '<tr><td colspan="4" style="text-align:center">Dữ liệu toàn vẹn.</td></tr>',
        );
        return;
      }

      items.forEach((i) => {
        let actionHtml = azacConfig.isAdmin
          ? `<button class="button button-small action-fix-integrity" data-id="${i.class_id}">Dọn dẹp</button>`
          : `<span class="dashicons dashicons-lock"></span>`;

        let row = `
                <tr>
                    <td data-label="Lớp học"><strong>${i.class_name}</strong><br><small>ID: ${i.class_id}</small></td>
                    <td data-label="Chi tiết lỗi"><span class="azac-reason-text">${i.reason}</span></td>
                    <td data-label="Số lượng bản ghi">${i.records_count}</td>
                    <td data-label="Hành động">${actionHtml}</td>
                </tr>
            `;
        $tbody.append(row);
      });
    }

    // --- ACTION LISTENERS ---
    $(document).on(
      "click",
      ".action-fix-user",
      function () {
        if (
          !confirm(
            "Bạn có chắc muốn khóa tài khoản này?",
          )
        )
          return;
        const id = $(this).data("id");
        const $row = $(this).closest("tr");

        $.post(
          azacConfig.ajaxUrl,
          {
            action: "azac_sp_fix_user",
            id: id,
            _ajax_nonce: azacConfig.nonce,
          },
          function (res) {
            if (res.success) {
              $row.fadeOut();
            } else {
              alert(res.data);
            }
          },
        );
      },
    );

    $(document).on(
      "click",
      ".action-fix-integrity",
      function () {
        if (
          !confirm(
            "Dọn dẹp dữ liệu rác cho lớp này?",
          )
        )
          return;
        const id = $(this).data("id");
        const $row = $(this).closest("tr");

        $.post(
          azacConfig.ajaxUrl,
          {
            action: "azac_sp_fix_integrity",
            id: id,
            _ajax_nonce: azacConfig.nonce,
          },
          function (res) {
            if (res.success) {
              $row.fadeOut();
            } else {
              alert(res.data);
            }
          },
        );
      },
    );

    $(document).on(
      "click",
      ".action-fix-malware",
      function () {
        alert(
          "Tính năng đang được hoàn thiện.",
        );
      },
    );

});
