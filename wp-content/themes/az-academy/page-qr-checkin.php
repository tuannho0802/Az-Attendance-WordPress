<?php
/*
Template Name: QR Check-in
*/
if (!defined('ABSPATH')) {
  exit;
}
get_header();
$current_user = wp_get_current_user();
$roles = (array) $current_user->roles;
$is_student = is_user_logged_in() && (in_array('az_student', $roles, true) || in_array('administrator', $roles, true));
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
?>
<main style="max-width:680px;margin:0 auto;padding:16px">
  <?php if (!$is_student): ?>
    <div style="background:#fff;border:1px solid #e2e2e2;border-radius:8px;padding:16px">
      <div style="font-weight:700;color:#0f6d5e;margin-bottom:8px">Yêu cầu đăng nhập bằng tài khoản Học viên</div>
      <p>Vui lòng đăng nhập để thực hiện điểm danh giữa giờ.</p>
    </div>
  <?php elseif (!$class_id): ?>
    <div style="background:#fff;border:1px solid #e2e2e2;border-radius:8px;padding:16px">
      <div style="font-weight:700;color:#0f6d5e;margin-bottom:8px">Thiếu thông tin lớp học</div>
      <p>Liên kết không hợp lệ. Vui lòng truy cập từ mã QR của giảng viên.</p>
    </div>
  <?php else: ?>
    <div
      style="background:#fff;border:1px solid #e2e2e2;border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:12px">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
        <div style="font-size:18px;font-weight:700;color:#0f6d5e">Điểm danh giữa giờ</div>
        <div style="background:#0f6d5e;color:#fff;border-radius:999px;padding:4px 10px;font-weight:600">Lớp
          #<?php echo esc_html($class_id); ?></div>
      </div>
      <form id="azac-mid-form" style="display:flex;flex-direction:column;gap:12px" method="post" action="">
        <input type="hidden" name="class_id" value="<?php echo esc_attr($class_id); ?>">
        <div style="display:flex;flex-direction:column;gap:6px">
          <label for="pin_code" style="font-weight:600;color:#333">Mã PIN 6 số</label>
          <input id="pin_code" name="pin_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
            placeholder="Nhập mã PIN" required
            style="padding:10px;border:1px solid #e2e2e2;border-radius:8px;font-size:16px">
        </div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <div style="font-weight:600;color:#333">Đánh giá buổi học</div>
          <div class="azac-stars" style="display:flex;gap:8px">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <label style="cursor:pointer;display:flex;align-items:center;gap:6px">
                <input type="radio" name="rating" value="<?php echo $i; ?>" <?php echo $i === 5 ? 'checked' : ''; ?>
                  style="accent-color:#f39c12">
                <span style="color:#f39c12;font-weight:700"><?php echo $i; ?>★</span>
              </label>
            <?php endfor; ?>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:6px">
          <label for="comment" style="font-weight:600;color:#333">Bạn thấy buổi học hôm nay thế nào?</label>
          <textarea id="comment" name="comment" rows="3" placeholder="Nhập cảm nhận ngắn gọn"
            style="padding:10px;border:1px solid #e2e2e2;border-radius:8px;font-size:14px"></textarea>
        </div>
        <div>
          <button type="submit" class="button button-primary" style="padding:10px 14px;border-radius:8px">Gửi điểm
            danh</button>
        </div>
      </form>
      <div id="azac-mid-result"
        style="display:none;background:#f8faf9;border:1px solid #eaeaea;border-radius:8px;padding:10px"></div>
    </div>
    <script>
      (function () {
        var form = document.getElementById('azac-mid-form');
        var result = document.getElementById('azac-mid-result');
        var nonce = "<?php echo esc_js(wp_create_nonce('azac_mid_submit')); ?>";
        var ajaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
        if (!form) return;
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          result.style.display = 'block';
          result.textContent = 'Đang xử lý...';
          var fd = new FormData(form);
          fd.append('action', 'azac_mid_session_submit');
          fd.append('nonce', nonce);
          fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
              if (res && res.success) {
                result.textContent = 'Đã điểm danh giữa giờ thành công! Cảm ơn bạn.';
                result.style.borderColor = '#2ecc71';
              } else {
                var msg = (res && res.data && res.data.message) ? res.data.message : 'Lỗi gửi điểm danh';
                result.textContent = msg;
                result.style.borderColor = '#e74c3c';
              }
            })
            .catch(function () {
              result.textContent = 'Lỗi mạng, vui lòng thử lại.';
              result.style.borderColor = '#e74c3c';
            });
        });
      })();
    </script>
  <?php endif; ?>
</main>
<?php get_footer(); ?>