;(function ($) {
  $(function () {
    console.log("Attendance Session JS loaded");
    console.log("azacData:", window.azacData);

    // Helper: Get status info based on date comparison
    function getStatusInfo(date) {
      if (!window.azacData || !window.azacData.today)
        return { label: "", className: "" };
      var today = window.azacData.today;
      if (date < today)
        return {
          label: "Đã qua",
          className: "tag-past",
        };
      if (date === today)
        return {
          label: "Hiện tại",
          className: "tag-current",
        };
      if (date > today)
        return {
          label: "Sắp tới",
          className: "tag-future",
        };
      return { label: "", className: "" };
    }

    // Helper: Render Option HTML
    // NOTE: This now prepares HTML for the CUSTOM DIV-based select.
    // The native <option> will keep simple text.
    function renderCustomOptionHtml(
      val,
      fullLabel,
      isListItem,
    ) {
      if (!val) return fullLabel; // Placeholder "Chọn buổi học"

      var status = getStatusInfo(val);
      if (!status || !status.label)
        return fullLabel;

      var displayLabel = fullLabel;
      // Fix: Ensure d/m/Y display if source is Y-m-d
      var m = String(fullLabel).match(
        /^(\d{4})-(\d{2})-(\d{2})(.*)/,
      );
      if (m) {
        displayLabel =
          m[3] + "/" + m[2] + "/" + m[1] + m[4];
      }

      return (
        '<span class="azac-tag ' +
        status.className +
        '">' +
        status.label +
        "</span> " +
        '<span class="azac-date">' +
        displayLabel +
        "</span>"
      );
    }

    // Function to build/refresh Custom Select
    function initCustomSelect() {
      var $nativeSelect = $(
        "#azac_session_select",
      );
      if ($nativeSelect.length === 0) return;

      // 1. Hide native select if not already hidden
      if (
        !$nativeSelect.hasClass(
          "azac-hidden-select",
        )
      ) {
        $nativeSelect.addClass(
          "azac-hidden-select",
        );
      }

      // 2. Prepare Wrapper
      var $wrapper = $(
        ".azac-custom-select-wrapper",
      );
      if ($wrapper.length === 0) {
        $wrapper = $(
          '<div class="azac-custom-select-wrapper"></div>',
        );
        $nativeSelect.after($wrapper);
      }
      $wrapper.empty(); // Rebuild content

      // 3. Build Trigger
      var $selectedOption = $nativeSelect.find(
        "option:selected",
      );
      var selectedText =
        $selectedOption.text() ||
        "Chọn buổi học";
      // Cleanup text if needed
      selectedText = selectedText.replace(
        /^\[(Đã qua|Hiện tại|Sắp tới)\]\s*/,
        "",
      );

      var selectedVal = $selectedOption.val();
      var triggerHtml = renderCustomOptionHtml(
        selectedVal,
        selectedText,
        false,
      );

      var $trigger = $(
        '<div class="azac-custom-select-trigger">' +
          triggerHtml +
          "</div>",
      );

      // 4. Build Options List
      var $options = $(
        '<div class="azac-custom-options"></div>',
      );
      $nativeSelect
        .find("option")
        .each(function () {
          var val = $(this).val();
          var text = $(this).text();
          text = text.replace(
            /^\[(Đã qua|Hiện tại|Sắp tới)\]\s*/,
            "",
          );

          var optionHtml =
            renderCustomOptionHtml(
              val,
              text,
              true,
            );
          var $optDiv = $(
            '<div class="azac-custom-option" data-value="' +
              val +
              '">' +
              optionHtml +
              "</div>",
          );

          if (val === selectedVal)
            $optDiv.addClass("selected");

          // Click on Custom Option
          $optDiv.on("click", function (e) {
            e.stopPropagation();
            var newVal = $(this).data("value");
            $nativeSelect
              .val(newVal)
              .trigger("change");
            $wrapper.removeClass("open");

            // Update Trigger Visual
            var newText = $(this).text(); // Use text from div (might need cleanup if HTML included? No, .text() strips tags)
            // Wait, we want the original label. Let's find it from native select to be safe or re-use text var from closure?
            // Closure 'text' var is from the loop. We need the text of the *clicked* item.
            // Better: get from native select after change?
            // Actually, we can just re-run initCustomSelect? No, that's heavy.
            // Let's just update trigger manually.
            var updatedText =
              $nativeSelect
                .find(
                  'option[value="' +
                    newVal +
                    '"]',
                )
                .text() || "";
            updatedText = updatedText.replace(
              /^\[(Đã qua|Hiện tại|Sắp tới)\]\s*/,
              "",
            );
            $trigger.html(
              renderCustomOptionHtml(
                newVal,
                updatedText,
                false,
              ),
            );

            $wrapper
              .find(".selected")
              .removeClass("selected");
            $(this).addClass("selected");
          });

          $options.append($optDiv);
        });

      $wrapper.append($trigger);
      $wrapper.append($options);

      // Trigger Click
      $trigger.on("click", function (e) {
        e.stopPropagation();
        // Close other dropdowns if any
        $(".azac-custom-select-wrapper")
          .not($wrapper)
          .removeClass("open");
        $wrapper.toggleClass("open");
      });

      // Global Click to Close (One-time binding check)
      if (!window.azacCustomSelectBound) {
        $(document).on("click", function () {
          $(
            ".azac-custom-select-wrapper",
          ).removeClass("open");
        });
        window.azacCustomSelectBound = true;
      }
    }

    // Initialize existing options - Revert to simple text but init Custom Select
    $("#azac_session_select option").each(
      function () {
        var val = $(this).val();
        var text = $(this).text();
        if (val) {
          // Clean up old prefix if present in text
          var cleanText = text.replace(
            /^\[(Đã qua|Hiện tại|Sắp tới)\]\s*/,
            "",
          );
          if (cleanText !== text) {
            $(this).text(cleanText);
          }
        }
      },
    );

    // Initial Build
    initCustomSelect();

    $("#azac_session_select").on(
      "change",
      function () {
        var val = $(this).val();
        if (!val) return;
        if (
          typeof updatePermissions ===
          "function"
        ) {
          updatePermissions(val);
        }
        window.azacData.sessionDate = val;
        if (
          window.AZACU &&
          typeof window.AZACU
            .updateSessionTitle === "function"
        ) {
          window.AZACU.updateSessionTitle(val);
        }
        if (
          window.AZACU &&
          typeof window.AZACU.resetItems ===
            "function"
        ) {
          window.AZACU.resetItems("check-in");
          window.AZACU.resetItems(
            "mid-session",
          );
        }
        if (
          window.AZAC_Att &&
          typeof window.AZAC_Att
            .fetchExisting === "function"
        ) {
          window.AZAC_Att.fetchExisting(
            "check-in",
          );
          window.AZAC_Att.fetchExisting(
            "mid-session",
          );
        }

        // Sync datepicker with dropdown selection
        var $dp = $("#azac_session_date");
        if ($dp.hasClass("hasDatepicker")) {
          // Fix: Parse Y-m-d from dropdown and set as Date object to match dd/mm/yy format
          if (
            val &&
            val.match(/^\d{4}-\d{2}-\d{2}$/)
          ) {
            var parts = val.split("-");
            var dObj = new Date(
              parseInt(parts[0], 10),
              parseInt(parts[1], 10) - 1,
              parseInt(parts[2], 10),
            );
            $dp.datepicker("setDate", dObj);
          } else {
            $dp.datepicker("setDate", val);
          }
        } else {
          $dp.val(val);
        }
        $dp.trigger("change");
      },
    );
    // Helper: Convert d/m/Y to Y-m-d for Server
    function toServerDate(dStr) {
      if (!dStr) return "";
      // Check if already Y-m-d
      if (dStr.match(/^\d{4}-\d{2}-\d{2}$/))
        return dStr;
      var parts = dStr.split("/");
      if (parts.length === 3) {
        return (
          parts[2] +
          "-" +
          parts[1] +
          "-" +
          parts[0]
        );
      }
      return dStr;
    }

    $("#azac_add_session_btn").on(
      "click",
      function () {
        var d = $("#azac_session_date").val();
        var t = $("#azac_session_time").val();
        if (!d) {
          alert("Chọn ngày buổi học");
          return;
        }
        var payload = {
          action: "azac_add_session",
          nonce: window.azacData.sessionNonce,
          class_id: window.azacData.classId,
          date: toServerDate(d),
          time: t,
        };
        var $btn = $(this).prop(
          "disabled",
          true,
        );
        $.post(
          window.azacData.ajaxUrl,
          payload,
          function (res) {
            $btn.prop("disabled", false);
            console.log(
              "AJAX Response Data:",
              res,
            );
            if (
              res &&
              res.success &&
              res.data &&
              res.data.sessions
            ) {
              var $sel = $(
                "#azac_session_select",
              ).empty();
              window.azacData.sessions =
                res.data.sessions;
              res.data.sessions.forEach(
                function (s) {
                  var label =
                    s.date +
                    (s.time
                      ? " " + s.time
                      : "");
                  // Native select keeps simple text
                  $("<option/>")
                    .val(s.date)
                    .text(label)
                    .appendTo($sel);
                },
              );

              // Rebuild Custom Select
              initCustomSelect();
              if (res.data.selected) {
                $sel.val(res.data.selected);
                window.azacData.sessionDate =
                  res.data.selected;
                if (
                  window.AZACU &&
                  typeof window.AZACU
                    .updateSessionTitle ===
                    "function"
                ) {
                  window.AZACU.updateSessionTitle(
                    res.data.selected,
                  );
                }
                if (
                  window.AZAC_Att &&
                  typeof window.AZAC_Att
                    .fetchExisting ===
                    "function"
                ) {
                  window.AZAC_Att.fetchExisting(
                    "check-in",
                  );
                  window.AZAC_Att.fetchExisting(
                    "mid-session",
                  );
                }
              }
              if (
                typeof window.showAzacToast ===
                "function"
              ) {
                var msg =
                  "Đã tạo thành công Buổi " +
                  res.data.sessions.length +
                  " ngày " +
                  d +
                  " cho lớp " +
                  (window.azacData.className ||
                    "...");
                window.showAzacToast(
                  msg,
                  "success",
                );
              }
            } else {
              alert("Lỗi thêm buổi học");
            }
          },
        );
      },
    );

    function updatePermissions(date) {
      if (!window.azacData) return;
      var isAdmin = window.azacData.isAdmin;
      var today = window.azacData.today;
      var canEdit = isAdmin || date === today;

      $(".azac-status, .azac-status-mid").prop(
        "disabled",
        !canEdit,
      );
      $(".azac-note, .azac-note-mid").prop(
        "readonly",
        !canEdit,
      );

      if (canEdit) {
        $(".azac-switch").removeClass(
          "azac-disabled",
        );
        $(
          "#azac-submit-checkin, #azac-submit-mid",
        ).show();
      } else {
        $(".azac-switch").addClass(
          "azac-disabled",
        );
        $(
          "#azac-submit-checkin, #azac-submit-mid",
        ).hide();
      }
    }
    $("#azac_update_session_btn").on(
      "click",
      function () {
        var old = $(
          "#azac_session_select",
        ).val();
        var d = $("#azac_session_date").val();
        var t = $("#azac_session_time").val();
        if (!old || !d) {
          alert("Chọn buổi và ngày");
          return;
        }
        var payload = {
          action: "azac_update_session",
          nonce: window.azacData.sessionNonce,
          class_id: window.azacData.classId,
          date: old,
          new_date: toServerDate(d),
          new_time: t,
        };
        var $btn = $(this).prop(
          "disabled",
          true,
        );
        $.post(
          window.azacData.ajaxUrl,
          payload,
          function (res) {
            $btn.prop("disabled", false);
            if (
              res &&
              res.success &&
              res.data &&
              res.data.sessions
            ) {
              var $sel = $(
                "#azac_session_select",
              ).empty();
              window.azacData.sessions =
                res.data.sessions;
              res.data.sessions.forEach(
                function (s) {
                  var label =
                    s.date +
                    (s.time
                      ? " " + s.time
                      : "");
                  // Native select keeps simple text
                  $("<option/>")
                    .val(s.date)
                    .text(label)
                    .appendTo($sel);
                },
              );

              // Rebuild Custom Select
              initCustomSelect();
              if (res.data.selected) {
                $sel.val(res.data.selected);
                window.azacData.sessionDate =
                  res.data.selected;
                if (
                  window.AZACU &&
                  typeof window.AZACU
                    .updateSessionTitle ===
                    "function"
                ) {
                  window.AZACU.updateSessionTitle(
                    res.data.selected,
                  );
                }
                if (
                  window.AZAC_Att &&
                  typeof window.AZAC_Att
                    .fetchExisting ===
                    "function"
                ) {
                  window.AZAC_Att.fetchExisting(
                    "check-in",
                  );
                  window.AZAC_Att.fetchExisting(
                    "mid-session",
                  );
                }
              }
              if (
                typeof window.showAzacToast ===
                "function"
              ) {
                var idx = -1;
                if (
                  res.data.sessions &&
                  res.data.sessions.length
                ) {
                  for (
                    var i = 0;
                    i <
                    res.data.sessions.length;
                    i++
                  ) {
                    if (
                      res.data.sessions[i]
                        .date ===
                      res.data.selected
                    ) {
                      idx = i;
                      break;
                    }
                  }
                }
                var sNum =
                  idx >= 0 ? idx + 1 : "?";
                window.showAzacToast(
                  "<div><strong>Đã lưu:</strong> Cập nhật Buổi " +
                    sNum +
                    " - Lớp " +
                    (window.azacData
                      .className || "...") +
                    "</div>",
                  "success",
                );
              }
            } else {
              if (
                typeof window.showAzacToast ===
                "function"
              ) {
                window.showAzacToast(
                  res.data.message ||
                    "Lỗi cập nhật buổi học",
                  "error",
                );
              } else {
                alert(
                  res.data.message ||
                    "Lỗi cập nhật buổi học",
                );
              }
            }
          },
        );
      },
    );

    $("#azac_delete_session_btn").on(
      "click",
      function () {
        var d = $("#azac_session_select").val();
        if (!d) {
          alert("Chọn buổi học");
          return;
        }
        if (
          !confirm("Bạn có chắc chắn muốn xóa?")
        )
          return;
        var payload = {
          action: "azac_delete_session",
          nonce: window.azacData.sessionNonce,
          class_id: window.azacData.classId,
          date: d,
        };
        var $btn = $(this).prop(
          "disabled",
          true,
        );
        $.post(
          window.azacData.ajaxUrl,
          payload,
          function (res) {
            $btn.prop("disabled", false);
            if (res && res.success) {
              alert("Xóa thành công");
              location.reload();
            } else {
              alert("Lỗi xóa buổi học");
            }
          },
        );
      },
    );

    $(document).on(
      "change",
      ".azac-teacher-checkin-cb",
      function () {
        var $cb = $(this);
        var classId = $cb.data("class");
        var date = $cb.data("date");
        var isCheckin = $cb.is(":checked")
          ? 1
          : 0;

        var payload = {
          action: "azac_teacher_checkin",
          nonce: window.azacData
            ? window.azacData.sessionNonce
            : "",
          class_id: classId,
          date: date,
          is_checkin: isCheckin,
        };

        $cb.prop("disabled", true);
        $.post(
          window.azacData.ajaxUrl,
          payload,
          function (res) {
            $cb.prop("disabled", false);
            if (res.success) {
              var $badge = $cb
                .closest("tr")
                .find(".azac-badge");
              if (isCheckin) {
                $badge
                  .removeClass(
                    "azac-badge-pending",
                  )
                  .addClass(
                    "azac-badge-publish",
                  )
                  .text("Đã dạy");
              } else {
                $badge
                  .removeClass(
                    "azac-badge-publish",
                  )
                  .addClass(
                    "azac-badge-pending",
                  )
                  .text("Chưa dạy");
              }
            } else {
              alert(res.data.message || "Lỗi");
              $cb.prop("checked", !isCheckin);
            }
          },
        ).fail(function () {
          $cb.prop("disabled", false);
          alert("Lỗi kết nối");
          $cb.prop("checked", !isCheckin);
        });
      },
    );

    // Dynamic Button Logic Helper (Enhanced)
    function toggleSessionButtons(date) {
      if (!window.azacData) return;

      // Convert input date (d/m/Y) to Y-m-d for comparison
      var compareDate = toServerDate(date);

      // Check in sessions array (updated via AJAX) first, fallback to existingDates
      var exists = false;
      if (
        window.azacData.sessions &&
        Array.isArray(window.azacData.sessions)
      ) {
        exists = window.azacData.sessions.some(
          function (s) {
            return s.date === compareDate;
          },
        );
      } else if (
        window.azacData.existingDates
      ) {
        exists =
          window.azacData.existingDates.includes(
            compareDate,
          );
      }

      var $btnAdd = $("#azac_add_session_btn");
      var $btnUpdate = $(
        "#azac_update_session_btn",
      );

      if (exists) {
        // State: Update
        $btnAdd.hide();
        $btnUpdate
          .show()
          .text("Cập nhật buổi")
          .addClass("btn-session-update")
          .removeClass("btn-session-add");
      } else {
        // State: Add
        $btnUpdate.hide();
        $btnAdd
          .show()
          .text("Thêm buổi")
          .addClass("btn-session-add")
          .removeClass("btn-session-update");
      }
    }

    if ($(".azac-datepicker").length) {
      var $dp = $(".azac-datepicker");
      var dates =
        window.azacData &&
        window.azacData.existingDates
          ? window.azacData.existingDates
          : [];

      console.log(
        "Initializing Datepicker with dates:",
        dates,
      );

      $dp.datepicker({
        dateFormat: "dd/mm/yy",
        beforeShowDay: function (date) {
          var string = $.datepicker.formatDate(
            "yy-mm-dd",
            date,
          );
          if (dates.includes(string)) {
            return [
              true,
              "azac-date-highlight",
              "Đã có buổi học",
            ];
          }
          return [true, ""];
        },
        onSelect: function (dateText) {
          toggleSessionButtons(dateText);
          $(this).trigger("change");
        },
      });

      console.log("Datepicker initialized");

      // Initial check
      toggleSessionButtons($dp.val());

      // Handle manual input change
      $dp.on("change keyup", function () {
        toggleSessionButtons($(this).val());
      });
    }
  });
})(jQuery);
