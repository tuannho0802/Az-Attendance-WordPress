(function ($) {
  $(function () {
    console.log(
      "AzAC Params: Get Full Data !!",
    );

    // Helper: Recalculate student count and update hidden field
    function recalcStudentCount() {
      var rows = $(
        "#azac_students_tbody tr[data-id]",
      );
      var count = rows.length;
      $("#az_so_hoc_vien").val(count);

      // Handle empty state
      if (count === 0) {
        if (
          $("#azac_students_tbody .no-items")
            .length === 0
        ) {
          $("#azac_students_tbody").html(
            '<tr class="no-items"><td colspan="5">Chưa có học viên nào.</td></tr>',
          );
        }
      } else {
        $(
          "#azac_students_tbody .no-items",
        ).remove();
      }
    }

    // Helper: Add student to table
    function addStudentToTable(s) {
      // Check for duplicates
      if (
        $(
          '#azac_students_tbody tr[data-id="' +
            s.id +
            '"]',
        ).length > 0
      ) {
        return;
      }

      var tr = $(
        '<tr data-id="' + s.id + '"></tr>',
      );
      var nameCell =
        "<td>" +
        s.name +
        '<input type="hidden" name="az_students[]" value="' +
        s.id +
        '"></td>';
      var emailCell =
        "<td>" + (s.email || "") + "</td>";
      var phoneCell =
        "<td>" + (s.phone || "") + "</td>";
      var bizCell =
        "<td>" + (s.biz || "") + "</td>";
      var actionCell =
        '<td><button type="button" class="button-link azac-remove-student-btn" style="color:#b32d2e;">Xóa</button></td>';

      tr.append(
        nameCell +
          emailCell +
          phoneCell +
          bizCell +
          actionCell,
      );
      $("#azac_students_tbody").append(tr);
      recalcStudentCount();
    }

    // --- Event Handlers ---

    // 1. Search Button Click
    $("#azac_search_btn").on(
      "click",
      function (e) {
        e.preventDefault();
        var btn = $(this);

        // Null check and trim for existing fields
        var nameInput = $("#azac_search_name");
        var name = nameInput.length
          ? (nameInput.val() || "").trim()
          : "";

        var phoneInput = $(
          "#azac_search_phone",
        );
        var phone = phoneInput.length
          ? (phoneInput.val() || "").trim()
          : "";

        // Removed fields (Email, Biz) - set to empty string
        var email = "";
        var biz = "";

        if (!window.azac_params) {
          console.error("Missing azac_params");
          return;
        }

        btn
          .prop("disabled", true)
          .text("Đang tìm...");

        $.post(
          azac_params.ajaxUrl,
          {
            action: "azac_search_students",
            security: azac_params.nonce,
            name: name,
            phone: phone,
          },
          function (res) {
            btn
              .prop("disabled", false)
              .text("Tìm kiếm");
            var resDiv = $(
              "#azac_search_results",
            );
            resDiv.empty().show();

            if (
              res.success &&
              res.data.results &&
              res.data.results.length > 0
            ) {
              var ul = $(
                '<ul style="margin:0; padding:0;"></ul>',
              );
              res.data.results.forEach(
                function (s) {
                  var li = $(
                    '<li style="padding:8px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;"></li>',
                  );
                  var info =
                    "<strong>" +
                    s.name +
                    "</strong>";
                  if (s.email)
                    info += " - " + s.email;
                  if (s.phone)
                    info += " - " + s.phone;

                  var leftDiv = $(
                    "<div>" + info + "</div>",
                  );

                  var addBtn = $(
                    '<button type="button" class="button button-small">Thêm</button>',
                  );

                  // Check if already in table
                  if (
                    $(
                      '#azac_students_tbody tr[data-id="' +
                        s.id +
                        '"]',
                    ).length > 0
                  ) {
                    addBtn
                      .prop("disabled", true)
                      .text("Đã có");
                  } else {
                    addBtn.on(
                      "click",
                      function () {
                        addStudentToTable(s);
                        $(this)
                          .prop(
                            "disabled",
                            true,
                          )
                          .text("Đã thêm");
                      },
                    );
                  }

                  li.append(leftDiv).append(
                    addBtn,
                  );
                  ul.append(li);
                },
              );
              resDiv.append(ul);
            } else {
              resDiv.html(
                '<p style="padding:10px; margin:0;">Không tìm thấy học viên nào phù hợp.</p>',
              );
            }
          },
        ).fail(function () {
          btn
            .prop("disabled", false)
            .text("Tìm kiếm");
          alert("Lỗi kết nối server.");
        });
      },
    );

    // 2. Add Selected from "Available List"
    $("#azac_select_all_available").on(
      "change",
      function () {
        $(".azac-available-cb").prop(
          "checked",
          $(this).prop("checked"),
        );
      },
    );

    $("#azac_add_selected_btn").on(
      "click",
      function (e) {
        e.preventDefault();
        var btn = $(this);
        var checked = $(
          ".azac-available-cb:checked",
        );

        if (checked.length === 0) {
          alert(
            "Vui lòng chọn ít nhất một học viên từ danh sách.",
          );
          return;
        }

        if (
          !window.azac_params ||
          !window.azac_params.nonce
        ) {
          alert(
            "Lỗi cấu hình: Thiếu Nonce (azac_params.nonce).",
          );
          return;
        }

        var studentIds = [];
        var studentsInfo = [];

        checked.each(function () {
          var cb = $(this);
          studentIds.push(cb.val());
          studentsInfo.push({
            cb: cb,
            info: cb.data("info"),
          });
        });

        btn
          .prop("disabled", true)
          .text("Đang thêm...");

        $.post(
          azac_params.ajaxUrl,
          {
            action:
              "azac_add_students_to_class",
            security: azac_params.nonce, // As requested
            class_id: azac_params.classId,
            student_ids: studentIds,
          },
          function (res) {
            btn
              .prop("disabled", false)
              .text("Thêm đã chọn");
            if (res.success) {
              // Update UI
              studentsInfo.forEach(
                function (item) {
                  if (item.info) {
                    addStudentToTable(
                      item.info,
                    );
                  }
                  // Remove from available list
                  item.cb
                    .closest("li")
                    .remove();
                },
              );
              // Uncheck "Select All" if it was checked
              $(
                "#azac_select_all_available",
              ).prop("checked", false);
            } else {
              alert(
                "Lỗi: " +
                  (res.data.message ||
                    "Không thể thêm học viên."),
              );
            }
          },
        ).fail(function () {
          btn
            .prop("disabled", false)
            .text("Thêm đã chọn");
          alert("Lỗi kết nối server.");
        });
      },
    );

    // 3. Remove Student (Delegate to document for dynamic elements)
    $(document).on(
      "click",
      ".azac-remove-student-btn",
      function (e) {
        e.preventDefault();

        var btn = $(this);
        var tr = btn.closest("tr");
        var studentId = tr.data("id");

        if (!window.azac_params) {
          alert(
            "Lỗi: Không tìm thấy dữ liệu cấu hình (azac_params missing).",
          );
          return;
        }

        azacConfirm("Xóa học viên", "Bạn có chắc chắn muốn xóa học viên này khỏi lớp? (Hành động này sẽ được lưu ngay lập tức)", { confirmText: "Xóa", isDanger: true }).then(function(confirmed) {
            if (!confirmed) return;

            // Change button state
            btn
              .prop("disabled", true)
              .text("Đang xóa...");
  
            // Ajax request
            $.post(
              azac_params.ajaxUrl,
              {
                action:
                  "azac_remove_student_from_class",
                security: azac_params.nonce,
                class_id: azac_params.classId,
                student_id: studentId,
              },
            )
              .done(function (res) {
                if (res.success) {
                  tr.remove();
                  recalcStudentCount();
                } else {
                  alert(
                    res.data ||
                      "Không thể xóa học viên.",
                  );
                  btn
                    .prop("disabled", false)
                    .text("Xóa");
                }
              })
              .fail(function () {
                alert("Lỗi kết nối server.");
                btn
                  .prop("disabled", false)
                  .text("Xóa");
              });
        });
      },
    );

    // 4. Hide "Submit for Review" for Teachers (RBAC)
    if (
      window.azac_params &&
      azac_params.isTeacher
    ) {
      function hideSubmit() {
        var nodes = document.querySelectorAll(
          "button, a, input[type='submit']",
        );
        nodes.forEach(function (el) {
          var txt = (
            el.textContent ||
            el.value ||
            ""
          )
            .trim()
            .toLowerCase();
          var aria = (
            el.getAttribute("aria-label") || ""
          ).toLowerCase();
          var name = (
            el.getAttribute("name") || ""
          ).toLowerCase();

          if (
            txt === "submit for review" ||
            txt === "gửi xét duyệt" ||
            name === "publish" ||
            aria.indexOf(
              "submit for review",
            ) !== -1 ||
            aria.indexOf("gửi xét duyệt") !== -1
          ) {
            // Only hide if it's the publishing action, not "Update" if allowed
            // Actually teacher shouldn't publish.
            el.style.display = "none";
          }
        });

        // Specifically hide the major publishing actions div if needed
        $("#publishing-action").hide();
      }
      hideSubmit();
      var obs = new MutationObserver(
        function () {
          hideSubmit();
        },
      );
      obs.observe(document.body, {
        childList: true,
        subtree: true,
      });
    }

    // Initial calculation
    recalcStudentCount();
  });
})(jQuery);
