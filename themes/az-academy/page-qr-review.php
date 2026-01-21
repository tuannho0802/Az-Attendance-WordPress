<?php
/*
Template Name: QR Review
*/
if (!defined('ABSPATH')) {
  exit;
}
get_header();
$current_user = wp_get_current_user();
$roles = (array) $current_user->roles;
$is_student = is_user_logged_in() && (in_array('az_student', $roles, true) || in_array('administrator', $roles, true));
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$class_title = $class_id ? get_the_title($class_id) : '';
$teacher_name = '';
if ($class_id) {
  $teacher_user_id = intval(get_post_meta($class_id, 'az_teacher_user', true));
  if ($teacher_user_id) {
    $u = get_userdata($teacher_user_id);
    if ($u) {
      $teacher_name = $u->display_name ?: $u->user_login;
    }
  }
  if (!$teacher_name) {
    $teacher_name = sanitize_text_field(get_post_meta($class_id, 'az_giang_vien', true));
  }
}
$today = current_time('Y-m-d');
$session_index = 0;
$session_date_display = '';
if ($class_id) {
  global $wpdb;
  $sess_table = $wpdb->prefix . 'az_sessions';
  $rows = $wpdb->get_results($wpdb->prepare("SELECT session_date FROM {$sess_table} WHERE class_id=%d ORDER BY session_date ASC", $class_id), ARRAY_A);
  $idx = 0;
  foreach ($rows as $i => $r) {
    $d = sanitize_text_field($r['session_date']);
    if ($d === $today) {
      $idx = $i + 1;
      $session_date_display = $d;
      break;
    }
  }
  if ($idx === 0 && !empty($rows)) {
    $idx = count($rows);
    $session_date_display = sanitize_text_field($rows[$idx - 1]['session_date']);
  }
  $session_index = $idx ?: 1;
}
$redirect = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<main style="max-width:680px;margin:0 auto;padding:16px">
  <style>
    .menu a[href*="qr-checkin"], .menu a[href*="qr-review"] { display: none !important; }
  </style>
  <?php if (!$is_student): ?>
    <div style="background:#fff;border:1px solid #e2e2e2;border-radius:8px;padding:16px">
      <div style="font-weight:700;color:#0f6d5e;margin-bottom:8px">Yêu cầu đăng nhập bằng tài khoản Học viên</div>
      <p>Vui lòng đăng nhập để gửi đánh giá buổi học.</p>
      <p><a class="button button-primary" href="<?php echo esc_url(wp_login_url($redirect)); ?>">Vào trang đăng nhập</a></p>
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
        <div style="font-size:18px;font-weight:700;color:#0f6d5e">Đánh giá chất lượng buổi học - Az Academy</div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <div style="background:#0f6d5e;color:#fff;border-radius:999px;padding:4px 10px;font-weight:600">
            Lớp: <?php echo esc_html($class_title ?: ('#' . $class_id)); ?>
          </div>
          <div
            style="background:#106d5e1a;color:#0f6d5e;border-radius:999px;padding:4px 10px;font-weight:600;border:1px solid #0f6d5e">
            Buổi thứ:
            <?php echo esc_html($session_index); ?><?php if ($session_date_display) {
                 echo ' • Ngày: ' . esc_html(date_i18n('d/m/Y', strtotime($session_date_display)));
               } ?>
          </div>
          <?php if ($teacher_name): ?>
            <div
              style="background:#106d5e1a;color:#0f6d5e;border-radius:999px;padding:4px 10px;font-weight:600;border:1px solid #0f6d5e">
              GV: <?php echo esc_html($teacher_name); ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <form id="azac-mid-form" style="display:flex;flex-direction:column;gap:12px" method="post" action="">
        <input type="hidden" name="class_id" value="<?php echo esc_attr($class_id); ?>">
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
          <button type="submit" id="azac-submit-feedback" class="button button-primary" style="padding:10px 14px;border-radius:8px">Gửi đánh giá</button>
        </div>
      </form>
      <div id="azac-mid-result"
        style="display:none;background:#f8faf9;border:1px solid #eaeaea;border-radius:8px;padding:10px"></div>
    </div>
    <script>
      (function () {
        var form = document.getElementById('azac-mid-form');
        var result = document.getElementById('azac-mid-result');
        var btn = document.getElementById('azac-submit-feedback');
        var nonce = "<?php echo esc_js(wp_create_nonce('azac_feedback_submit')); ?>";
        var ajaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
        var submitting = false;
        if (!form) return;
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          if (submitting) return;
          var ok = window.confirm('Bạn có chắc muốn gửi đánh giá?');
          if (!ok) return;
          submitting = true;
          if (btn) btn.disabled = true;
          result.style.display = 'block';
          result.textContent = 'Đang xử lý...';
          var fd = new FormData(form);
          fd.append('action', 'azac_feedback_submit');
          fd.append('nonce', nonce);
          fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
              submitting = false;
              if (btn) btn.disabled = false;
              if (res && res.success) {
                result.textContent = 'Đã gửi đánh giá buổi học, cảm ơn bạn.';
                result.style.borderColor = '#2ecc71';
              } else {
                var msg = (res && res.data && res.data.message) ? res.data.message : 'Lỗi gửi điểm danh';
                result.textContent = msg;
                result.style.borderColor = '#e74c3c';
              }
            })
            .catch(function () {
              submitting = false;
              if (btn) btn.disabled = false;
              result.textContent = 'Lỗi mạng, vui lòng thử lại.';
              result.style.borderColor = '#e74c3c';
            });
        });
      })();
    </script>
  <?php endif; ?>
</main>
<?php get_footer(); ?>
