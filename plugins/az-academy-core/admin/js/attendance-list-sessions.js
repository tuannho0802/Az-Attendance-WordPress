(function ($) {
  $(function () {
    // --- Existing Handlers (Create Class, Delete Class, Update Status) ---
    $("#azac_create_class_btn").on(
      "click",
      function () {
        var title = $("#azac_new_class_title")
          .val()
          .trim();
        var teacherId =
          parseInt(
            $("#azac_new_class_teacher").val(),
            10,
          ) || 0;
        if (!title) {
          alert("Nhập tên lớp");
          return;
        }
        var payload = {
          action: "azac_create_class",
          nonce: AZAC_LIST && AZAC_LIST.nonce,
          title: title,
          teacher_id: teacherId,
        };
        var btn = $(this).prop(
          "disabled",
          true,
        );
        $.post(
          AZAC_LIST.ajaxUrl,
          payload,
          function (res) {
            btn.prop("disabled", false);
            if (res && res.success) {
              location.reload();
            } else {
              alert("Không thể tạo lớp");
            }
          },
        );
      },
    );

    $(document).on(
      "click",
      ".azac-delete-btn",
      function () {
        if (!AZAC_LIST || !AZAC_LIST.isAdmin)
          return;
        var $btn = $(this);
        var id =
          parseInt($btn.data("id"), 10) || 0;
        if (!id) return;
        if (
          !confirm(
            "Bạn chắc chắn muốn xóa lớp này? Hành động không thể hoàn tác.",
          )
        ) {
          return;
        }
        var payload = {
          action: "azac_delete_class",
          nonce: AZAC_LIST.deleteClassNonce,
          class_id: id,
        };
        $btn.prop("disabled", true);
        $.post(
          AZAC_LIST.ajaxUrl,
          payload,
          function (res) {
            $btn.prop("disabled", false);
            if (res && res.success) {
              location.reload();
            } else {
              alert("Không thể xóa lớp");
            }
          },
        );
      },
    );

    $(document).on(
      "click",
      ".azac-status-btn",
      function () {
        if (!AZAC_LIST || !AZAC_LIST.isAdmin)
          return;
        var $btn = $(this);
        var id =
          parseInt($btn.data("id"), 10) || 0;
        var status = String(
          $btn.data("status") || "",
        );
        if (!id || !status) return;
        var msg =
          status === "pending"
            ? "Bạn muốn đóng lớp này? Giáo viên/học viên sẽ không thao tác."
            : "Bạn muốn mở lớp này?";
        if (!confirm(msg)) return;
        var payload = {
          action: "azac_update_class_status",
          nonce: AZAC_LIST.updateStatusNonce,
          class_id: id,
          status: status,
        };
        $btn.prop("disabled", true);
        $.post(
          AZAC_LIST.ajaxUrl,
          payload,
          function (res) {
            $btn.prop("disabled", false);
            if (res && res.success) {
              location.reload();
            } else {
              alert(
                "Không thể cập nhật trạng thái lớp",
              );
            }
          },
        );
      },
    );

    // --- Tab Handling ---
    function activateTab(target) {
      $(".azac-tab-btn").removeClass(
        "button-primary",
      );
      $(".azac-tab").removeClass("active");
      $(target).addClass("active");
      $(
        '.azac-tab-btn[data-target="' +
          target +
          '"]',
      ).addClass("button-primary");
    }

    var CURRENT_PAGE = 1;

    $(
      '.azac-tab-btn[data-target="#azac-tab-sessions"]',
    ).on("click", function () {
      activateTab("#azac-tab-sessions");
      CURRENT_PAGE = 1;
      loadSessions();
    });

    // --- New Attendance List Logic ---

    // Init Select2
    if ($.fn.select2) {
      $("#azac-filter-class").select2({
        width: "100%",
        placeholder: "Chọn lớp học",
        allowClear: true,
      });
    }

    // Init Datepicker
    if ($.fn.datepicker) {
      $(
        "#azac-filter-date-start, #azac-filter-date-end",
      ).datepicker({
        dateFormat: "yy-mm-dd",
        changeMonth: true,
        changeYear: true,
        onSelect: function () {
          CURRENT_PAGE = 1;
          loadSessions();
        },
      });
    }

    // Filter Events
    $("#azac-filter-class").on(
      "change",
      function () {
        CURRENT_PAGE = 1;
        loadSessions();
      },
    );

    $("#azac-filter-sort").on(
      "change",
      function () {
        CURRENT_PAGE = 1;
        loadSessions();
      },
    );

    var searchTimeout;
    $("#azac-filter-search").on(
      "keyup",
      function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function () {
          CURRENT_PAGE = 1;
          loadSessions();
        }, 500);
      },
    );

    // Pagination Click
    $(document).on(
      "click",
      "#azac-sessions-pagination a",
      function (e) {
        e.preventDefault();
        var p = parseInt(
          $(this).data("page"),
          10,
        );
        if (p) {
          CURRENT_PAGE = p;
          loadSessions();
          $("html, body").animate(
            {
              scrollTop:
                $(
                  ".azac-session-filters-toolbar",
                ).offset().top - 60,
            },
            300,
          );
        }
      },
    );

    // Bulk Delete
    $(document).on(
      "click",
      "#azac-do-bulk-action",
      function (e) {
        e.preventDefault();
        var action = $(
          "#azac-bulk-action-selector-top",
        ).val();
        if (action !== "delete") {
          return;
        }

        var selected = [];
        $(".cb-select-1:checked").each(
          function () {
            selected.push($(this).val());
          },
        );

        if (selected.length === 0) {
          alert(
            "Vui lòng chọn ít nhất một buổi học.",
          );
          return;
        }

        if (
          !confirm(
            "Bạn có chắc chắn muốn xóa " +
              selected.length +
              " buổi học đã chọn?",
          )
        ) {
          return;
        }

        var btn = $(this);
        btn.prop("disabled", true);

        var data = {
          action: "azac_bulk_delete_sessions",
          _ajax_nonce:
            AZAC_LIST.bulkDeleteNonce,
          session_ids: selected,
        };

        console.log("Bulk Delete Data:", data);

        $.post(
          AZAC_LIST.ajaxUrl,
          data,
          function (res) {
            btn.prop("disabled", false);
            if (res.success) {
              location.reload();
            } else {
              alert(
                "Lỗi: " +
                  (res.data
                    ? res.data.message ||
                      res.data
                    : "Không thể xóa"),
              );
            }
          },
        ).fail(function (xhr) {
          btn.prop("disabled", false);
          var msg =
            "Lỗi kết nối hoặc server error (400/500).";
          if (
            xhr.responseJSON &&
            xhr.responseJSON.data
          ) {
            msg =
              xhr.responseJSON.data.message ||
              xhr.responseJSON.data;
          }
          alert(msg);
        });
      },
    );

    // Single Delete Session
    $(document).on(
      "click",
      ".azac-delete-session-btn",
      function (e) {
        e.preventDefault();
        var id = $(this).data("id");
        if (
          !confirm(
            "Bạn có chắc chắn muốn xóa buổi học này?",
          )
        ) {
          return;
        }

        $.post(
          AZAC_LIST.ajaxUrl,
          {
            action: "azac_delete_session",
            _ajax_nonce:
              AZAC_LIST.deleteSessionNonce,
            id: id,
          },
          function (res) {
            if (res.success) {
              location.reload();
            } else {
              alert(
                "Lỗi: " +
                  (res.data
                    ? res.data.message
                    : "Không thể xóa"),
              );
            }
          },
        );
      },
    );

    // Select All Checkbox
    $(document).on(
      "change",
      "#cb-select-all-1",
      function () {
        $(".cb-select-1").prop(
          "checked",
          $(this).prop("checked"),
        );
      },
    );

    // Initial Load
    if (
      $("#azac-tab-sessions").hasClass("active")
    ) {
      loadSessions();
    }

    function loadSessions() {
      if (
        !AZAC_LIST ||
        (!AZAC_LIST.isTeacher &&
          !AZAC_LIST.isAdmin &&
          !AZAC_LIST.isStudent)
      )
        return;

      var sort =
        $("#azac-filter-sort").val() ||
        "date_desc";
      var classId =
        $("#azac-filter-class").val() || "";
      var search =
        $("#azac-filter-search").val() || "";
      var dateStart =
        $("#azac-filter-date-start").val() ||
        "";
      var dateEnd =
        $("#azac-filter-date-end").val() || "";

      var payload = {
        action: "azac_list_sessions",
        nonce: AZAC_LIST.listSessionsNonce,
        paged: CURRENT_PAGE,
        per_page: 20,
        filter_class_id: classId,
        sort: sort,
        search: search,
        date_start: dateStart,
        date_end: dateEnd,
      };

      var $tbody = $(
        "#azac-sessions-table-body",
      );
      $tbody.html(
        '<tr><td colspan="5" style="text-align:center; padding: 20px;">Đang tải dữ liệu...</td></tr>',
      );

      var $pagination = $(
        "#azac-sessions-pagination",
      );

      $.post(
        AZAC_LIST.ajaxUrl,
        payload,
        function (res) {
          if (res && res.success && res.data) {
            var sessions =
              res.data.sessions || [];
            var totalPages =
              res.data.total_pages || 0;
            var availableClasses =
              res.data.available_classes || [];
            var currentPage =
              res.data.current_page || 1;

            // Populate Class Filter if empty (first load)
            if (
              $("#azac-filter-class option")
                .length <= 1 &&
              availableClasses.length > 0
            ) {
              var currentVal = $(
                "#azac-filter-class",
              ).val();
              var opts =
                '<option value="">Tất cả lớp học</option>';
              availableClasses.forEach(
                function (c) {
                  opts +=
                    '<option value="' +
                    c.id +
                    '">' +
                    c.title +
                    "</option>";
                },
              );
              // Re-init select2 or just update HTML and trigger change
              // Triggering change might cause loop if not careful, so just set val.
              $("#azac-filter-class").html(
                opts,
              );
              if (currentVal)
                $("#azac-filter-class")
                  .val(currentVal)
                  .trigger("change.select2");
            }

            if (!sessions.length) {
              $tbody.html(
                '<tr><td colspan="6" style="text-align:center; padding: 20px;">Không tìm thấy buổi học nào.</td></tr>',
              );
              $pagination.empty();
              return;
            }

            renderSessionsTable(
              sessions,
              $tbody,
            );
            renderPagination(
              currentPage,
              totalPages,
              $pagination,
            );
          } else {
            $tbody.html(
              '<tr><td colspan="8" style="text-align:center; color:red; padding: 20px;">Lỗi tải dữ liệu.</td></tr>',
            );
          }
        },
      );
    }

    function renderSessionsTable(
      sessions,
      $tbody,
    ) {
      var today = new Date();
      var y = today.getFullYear();
      var m = String(
        today.getMonth() + 1,
      ).padStart(2, "0");
      var d = String(today.getDate()).padStart(
        2,
        "0",
      );
      var todayStr = y + "-" + m + "-" + d;

      // Unified Palette (from PHP) - NO FALLBACK to old hardcoded values
      var palette =
        AZAC_LIST && AZAC_LIST.palette
          ? AZAC_LIST.palette
          : ["#15345a"];

      var html = sessions
        .map(function (s) {
          var dateStr = s.date;
          var timeStr = s.time
            ? s.time.substring(0, 5)
            : ""; // HH:mm

          // Calculate Date Status
          var dateStatusBadge = "";
          var rowClass = "";

          if (dateStr === todayStr) {
            dateStatusBadge =
              '<span class="azac-badge azac-badge-today">Hôm nay</span>';
            rowClass = "is-today";
          } else if (dateStr < todayStr) {
            dateStatusBadge =
              '<span class="azac-badge azac-badge-finished">Đã kết thúc</span>';
          } else {
            dateStatusBadge =
              '<span class="azac-badge azac-badge-pending">Sắp diễn ra</span>';
          }

          // Calculate Rate
          var total =
            parseInt(s.total, 10) || 0;
          var p =
            parseInt(
              (s.checkin &&
                s.checkin.present) ||
                0,
              10,
            ) +
              parseInt(
                (s.mid && s.mid.present) || 0,
                10,
              ) || 0;
          var t = Math.max(1, total * 2);
          var pp =
            Math.round((p / t) * 100) || 0;
          var ap = 100 - pp;

          // Status Badge
          var isCheckedIn = p > 0;
          var statusBadge = isCheckedIn
            ? '<span class="azac-badge azac-badge-success">Đã điểm danh</span>'
            : '<span class="azac-badge azac-badge-warning">Chưa điểm danh</span>';

          // Rate Display
          var rateHtml =
            '<span style="color:#27ae60; font-weight:bold">' +
            pp +
            '% Có mặt</span> <span style="color:#ccc">|</span> <span style="color:#c0392b">' +
            ap +
            "% Vắng</span>";

          var editUrl = s.link;

          // Action Buttons
          var actionButtons = "";

          // Show "Vào điểm danh" (Edit) only if user can manage (Manager/Admin/Editor) or is Teacher (logic might vary for Teacher but usually they can edit their classes)
          // Assuming Teacher can always edit their sessions? The PHP check allows Teacher.
          // AZAC_LIST.isTeacher is separate. Teachers usually use this button.
          // So condition: isTeacher OR canManage.
          if (
            AZAC_LIST.isTeacher ||
            AZAC_LIST.canManage
          ) {
            actionButtons +=
              '<a href="' +
              editUrl +
              '" class="button button-small" style="display:inline-flex;align-items:center;gap:3px">' +
              '<span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;padding-top:2px;"></span> Vào điểm danh' +
              "</a>";
          }

          if (AZAC_LIST.isRealAdmin) {
            actionButtons +=
              '<button type="button" class="button button-small azac-delete-session-btn" data-id="' +
              s.id +
              '" style="color:#a00; border-color:#d00; display:inline-flex; align-items:center; justify-content:center;">' +
              '<span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;"></span>' +
              "</button>";
          }

          // Color Logic (Unified Hash matching PHP's ord() bytes)
          var hash = 0;
          var className = s.class_title || "";
          if (window.TextEncoder) {
            var encoder = new TextEncoder();
            var bytes =
              encoder.encode(className);
            for (
              var i = 0;
              i < bytes.length;
              i++
            ) {
              hash += bytes[i];
            }
          } else {
            // Fallback for older browsers
            for (
              var i = 0;
              i < className.length;
              i++
            ) {
              hash += className.charCodeAt(i);
            }
          }
          // Use palette length for modulo
          var color =
            palette.length > 0
              ? palette[hash % palette.length]
              : "#15345a";

          var rowStyle =
            "border-top: 3px solid " +
            color +
            ";";

          // Mobile Badge (Append to Session Number/Time)
          var mobileBadge =
            '<div class="azac-mobile-badges"><span class="azac-badge-class" style="background-color: ' +
            color +
            ' !important;">' +
            s.class_title +
            "</span></div>";

          return [
            '<tr class="' +
              rowClass +
              '" style="' +
              rowStyle +
              '">',
            AZAC_LIST.isAdmin
              ? '<td class="check-column" data-label="Chọn" style="text-align:center; vertical-align:middle;"><input type="checkbox" name="session[]" value="' +
                s.id +
                '" class="cb-select-1"></td>'
              : "",
            '<td data-label="Lớp"><span class="azac-badge-class" style="background-color: ' +
              color +
              ' !important;">' +
              s.class_title +
              "</span></td>",
            '<td data-label="Buổi" style="text-align:center;"><span style="background:#e9ecef;color:#495057;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:bold;">#' +
              s.session_number +
              "</span>" +
              mobileBadge +
              "</td>",
            '<td data-label="Thời gian">' +
              dateStr +
              (timeStr
                ? ' <span style="color:#666">(' +
                  timeStr +
                  ")</span>"
                : "") +
              "</td>",
            '<td data-label="Trạng thái ngày">' +
              dateStatusBadge +
              "</td>",
            '<td data-label="Tỉ lệ">' +
              rateHtml +
              "</td>",
            '<td data-label="Trạng thái">' +
              statusBadge +
              "</td>",
            '<td data-label="Hành động"><div style="display:flex; gap:8px; align-items:center;">',
            actionButtons,
            "</div></td>",
            "</tr>",
          ].join("");
        })
        .join("");

      $tbody.html(html);
    }

    function renderPagination(
      currentPage,
      totalPages,
      $container,
    ) {
      if (totalPages <= 1) {
        $container.empty();
        return;
      }
      var html =
        '<div class="tablenav bottom"><div class="tablenav-pages"><span class="pagination-links">';

      if (currentPage > 1) {
        html +=
          '<a class="prev-page button" href="#" data-page="' +
          (currentPage - 1) +
          '"><span class="screen-reader-text">Trang trước</span><span aria-hidden="true">‹</span></a>';
      } else {
        html +=
          '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
      }

      html +=
        '<span class="paging-input"> <span class="tablenav-paging-text">' +
        currentPage +
        ' của <span class="total-pages">' +
        totalPages +
        "</span></span></span>";

      if (currentPage < totalPages) {
        html +=
          '<a class="next-page button" href="#" data-page="' +
          (currentPage + 1) +
          '"><span class="screen-reader-text">Trang sau</span><span aria-hidden="true">›</span></a>';
      } else {
        html +=
          '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
      }

      html += "</span></div></div>";
      $container.html(html);
    }
  });
})(jQuery);
