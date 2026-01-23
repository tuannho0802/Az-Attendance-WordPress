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
              '<tr><td colspan="5" style="text-align:center; color:red; padding: 20px;">Lỗi tải dữ liệu.</td></tr>',
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

      var html = sessions
        .map(function (s) {
          var dateStr = s.date;
          var timeStr = s.time
            ? s.time.substring(0, 5)
            : ""; // HH:mm

          // Calculate Date Status
          var dateStatusBadge = "";
          var rowStyle = "";

          if (dateStr === todayStr) {
            dateStatusBadge =
              '<span style="background:#d4edda; color:#155724; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">Hôm nay</span>';
            rowStyle =
              "background-color: #fff9c4;"; // Light yellow highlight
          } else if (dateStr < todayStr) {
            dateStatusBadge =
              '<span style="background:#e9ecef; color:#495057; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">Đã kết thúc</span>';
          } else {
            dateStatusBadge =
              '<span style="background:#fff3cd; color:#856404; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">Sắp diễn ra</span>';
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
            ? '<span style="background:#d4edda; color:#155724; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">Đã điểm danh</span>'
            : '<span style="background:#fff3cd; color:#856404; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">Chưa điểm danh</span>';

          // Rate Display
          var rateHtml =
            '<span style="color:#27ae60; font-weight:bold">' +
            pp +
            '% Có mặt</span> <span style="color:#ccc">|</span> <span style="color:#c0392b">' +
            ap +
            "% Vắng</span>";

          var editUrl = s.link;

          return [
            '<tr style="' + rowStyle + '">',
            "<td><strong>" +
              s.class_title +
              "</strong></td>",
            '<td style="text-align:center;"><span style="background:#e9ecef;color:#495057;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:bold;">#' +
              s.session_number +
              "</span></td>",
            "<td>" +
              dateStr +
              (timeStr
                ? ' <span style="color:#666">(' +
                  timeStr +
                  ")</span>"
                : "") +
              "</td>",
            "<td>" + dateStatusBadge + "</td>",
            "<td>" + rateHtml + "</td>",
            "<td>" + statusBadge + "</td>",
            "<td>",
            '<a href="' +
              editUrl +
              '" class="button button-small" style="display:inline-flex;align-items:center;gap:3px">',
            '<span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;padding-top:2px;"></span> Vào điểm danh',
            "</a>",
            "</td>",
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
