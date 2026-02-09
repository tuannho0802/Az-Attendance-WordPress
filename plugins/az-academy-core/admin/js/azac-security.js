jQuery(document).ready(function ($) {
  // --- TABS LOGIC ---
  $(".azac-sp-tabs a").on(
    "click",
    function (e) {
      e.preventDefault();
      $(".azac-sp-tabs a").removeClass(
        "nav-tab-active",
      );
      $(this).addClass("nav-tab-active");
      $(".azac-tab-pane").removeClass("active");
      $(
        "#" + "tab-" + $(this).data("tab"),
      ).addClass("active");
    },
  );

  // --- HELPER: RENDER TABLE ---
  function renderRow(
    tableId,
    cells,
    actionBtn,
  ) {
    let html = "<tr>";
    html += `<th scope="row" class="check-column"><input type="checkbox" name="item[]"></th>`;
    cells.forEach(
      (c) => (html += `<td>${c}</td>`),
    );
    html += `<td>${actionBtn}</td></tr>`;
    $(tableId + " tbody").append(html);
  }

  function clearTable(tableId) {
    $(tableId + " tbody").empty();
  }

  // --- USER SCANNER ---
  $("#btn-scan-users").on("click", function () {
    const $btn = $(this);
    $btn
      .prop("disabled", true)
      .text("Đang quét...");
    clearTable("#table-users");

    $.post(
      window.azacData.ajaxUrl,
      {
        action: "azac_sp_scan_users",
        nonce: window.azacData.nonce,
      },
      function (res) {
        $btn
          .prop("disabled", false)
          .text("Quét Người dùng");
        if (res.success) {
          if (res.data.items.length === 0) {
            $("#table-users tbody").html(
              '<tr class="no-items"><td colspan="7">Hệ thống sạch sẽ. Không phát hiện user lạ.</td></tr>',
            );
            return;
          }
          res.data.items.forEach((item) => {
            let btn = "";
            if (item.type === "orphan_user") {
              btn = `<button class="button button-small action-fix" data-type="orphan_user" data-id="${item.id}">Xóa dữ liệu rác</button>`;
            } else {
              btn = `<button class="button button-small button-link-delete action-fix" data-type="user" data-id="${item.id}">Khóa TK</button>`;
            }

            renderRow(
              "#table-users",
              [
                item.login,
                item.email,
                item.role,
                item.registered,
                `<span class="azac-badge warning">${item.issue}</span>`,
              ],
              btn,
            );
          });
        }
      },
    );
  });

  // --- MALWARE SCANNER (Chunked) ---
  $("#btn-scan-malware").on(
    "click",
    function () {
      const $btn = $(this);
      const $prog = $("#malware-progress");
      $btn.prop("disabled", true);
      $prog.removeClass("hidden");
      clearTable("#table-malware");

      scanMalwareChunk(0);
    },
  );

  function scanMalwareChunk(offset) {
    $.post(
      window.azacData.ajaxUrl,
      {
        action: "azac_sp_scan_malware",
        nonce: window.azacData.nonce,
        offset: offset,
      },
      function (res) {
        if (res.success) {
          const data = res.data;
          const pct = Math.round(
            (data.processed / data.total) * 100,
          );
          $("#malware-progress .bar").css(
            "width",
            pct + "%",
          );
          $("#malware-progress .text").text(
            pct + "%",
          );

          data.items.forEach((item) => {
            const extra = JSON.stringify({
              source: item.source,
              sig: item.sig,
              path: item.path || "",
            });
            const btn = `<button class="button button-small button-link-delete action-fix" data-type="malware" data-id="${item.id}" data-extra='${extra}'>Làm sạch</button>`;
            renderRow(
              "#table-malware",
              [
                `<span class="azac-badge info">${item.source}</span>`,
                item.title,
                `<code class="malware-snippet">${item.snippet}</code>`,
              ],
              btn,
            );
          });

          if (!data.done) {
            scanMalwareChunk(data.next_offset);
          } else {
            $("#btn-scan-malware").prop(
              "disabled",
              false,
            );
            if (
              $("#table-malware tbody tr")
                .length === 0
            ) {
              $("#table-malware tbody").html(
                '<tr class="no-items"><td colspan="4">Không phát hiện mã độc.</td></tr>',
              );
            }
          }
        }
      },
    );
  }

  // --- INTEGRITY SCANNER ---
  $("#btn-scan-integrity").on(
    "click",
    function () {
      const $btn = $(this);
      $btn
        .prop("disabled", true)
        .text("Checking...");
      clearTable("#table-integrity");

      $.post(
        window.azacData.ajaxUrl,
        {
          action: "azac_sp_check_integrity",
          nonce: window.azacData.nonce,
        },
        function (res) {
          $btn
            .prop("disabled", false)
            .text("Kiểm tra Toàn vẹn");
          if (res.success) {
            if (res.data.items.length === 0) {
              $("#table-integrity tbody").html(
                '<tr class="no-items"><td colspan="5">Dữ liệu đồng bộ 100%.</td></tr>',
              );
              return;
            }
            res.data.items.forEach((item) => {
              const extra = JSON.stringify({
                class_id: item.class_id,
              });
              const btn = `<button class="button button-small action-fix" data-type="integrity" data-id="${item.student_id}" data-extra='${extra}'>Dọn dẹp</button>`;
              renderRow(
                "#table-integrity",
                [
                  `<b>#${item.student_id}</b>`,
                  item.class_name +
                    ` (#${item.class_id})`,
                  item.issue,
                ],
                btn,
              );
            });
          }
        },
      );
    },
  );

  // --- GLOBAL FIX ACTION ---
  $(document).on(
    "click",
    ".action-fix",
    function (e) {
      e.preventDefault();
      if (
        !confirm(
          "Bạn có chắc chắn muốn thực hiện hành động này?",
        )
      )
        return;

      const $btn = $(this);
      const type = $btn.data("type");
      const id = $btn.data("id");
      const extra = $btn.data("extra") || {};

      $btn
        .prop("disabled", true)
        .text("Đang xử lý...");

      $.post(
        window.azacData.ajaxUrl,
        {
          action: "azac_sp_fix_item",
          nonce: window.azacData.nonce,
          type: type,
          id: id,
          extra: extra,
        },
        function (res) {
          if (res.success) {
            $btn
              .closest("tr")
              .fadeOut(function () {
                $(this).remove();
              });
            alert(res.data.message);
          } else {
            alert("Lỗi: " + res.data.message);
            $btn
              .prop("disabled", false)
              .text("Thử lại");
          }
        },
      );
    },
  );
});
