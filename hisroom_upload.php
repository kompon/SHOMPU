<?php
// hisroom_upload.php
// แสดง "การจองของฉัน" + อัปโหลดสลิปในหน้าเดียว (ไม่แก้ไฟล์เดิม)
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// โหลดข้อมูลผู้ใช้จากฐาน user_db
$uRes = $userConn->query("SELECT * FROM users WHERE id=".(int)$_SESSION['user_id']." LIMIT 1");
if (!$uRes || $uRes->num_rows === 0) {
    session_destroy();
    header('Location: index.php'); exit();
}
$user = $uRes->fetch_assoc();
$user_id = (int)$user['id'];
$user_email = $user['email'] ?? '';

// จัดการอัปโหลดสลิป (POST)
$msg = null; $err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_slip'])) {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $email_in = trim($_POST['email'] ?? '');

    // ตรวจความเป็นเจ้าของการจอง (booking ต้องเป็นของ user นี้)
    $chk = $hotelConn->prepare("SELECT b.booking FROM bookings b WHERE b.booking=? AND b.user_id=? LIMIT 1");
    if ($chk) {
        $chk->bind_param('ii', $booking_id, $user_id);
        $chk->execute();
        $own = $chk->get_result()->fetch_assoc();
        $chk->close();
    }
    if (empty($own)) {
        $err = 'ไม่พบการจองนี้ในบัญชีของคุณ';
    }

    // ตรวจอีเมล
    if (!$err && (!filter_var($email_in, FILTER_VALIDATE_EMAIL))) {
        $err = 'รูปแบบอีเมลไม่ถูกต้อง';
    }

    // ตรวจไฟล์สลิป
    if (!$err) {
        if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
            $err = 'กรุณาเลือกไฟล์สลิป';
        } else {
            $file = $_FILES['slip'];
            if ($file['size'] <= 0 || $file['size'] > 2*1024*1024) {
                $err = 'ไฟล์ต้องไม่เกิน 2MB';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
                if (!isset($allowed[$mime])) {
                    $err = 'อนุญาตเฉพาะ .jpg .jpeg .png .pdf';
                }
            }
        }
    }

    // บันทึก
    if (!$err) {
        $destDir = __DIR__ . '/uploads/slips';
        if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
        if (!is_dir($destDir) || !is_writable($destDir)) {
            $err = 'ไม่สามารถเขียนโฟลเดอร์ uploads/slips ได้';
        } else {
            $ext = $allowed[$mime];
            $newName = 'booking_' . $booking_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = $destDir . '/' . $newName;
            $relPath  = 'uploads/slips/' . $newName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $err = 'อัปโหลดไฟล์ไม่สำเร็จ';
            } else {
                $ins = $hotelConn->prepare("INSERT INTO payments (booking_id, email, slip_path, verified) VALUES (?, ?, ?, 'pending')");
                if ($ins) {
                    $ins->bind_param('iss', $booking_id, $email_in, $relPath);
                    if ($ins->execute()) $msg = 'อัปโหลดสลิปสำเร็จ! สถานะกำลังรอตรวจสอบ';
                    else { $err = 'บันทึกข้อมูลไม่สำเร็จ'; @unlink($destPath); }
                    $ins->close();
                } else {
                    $err = 'เกิดข้อผิดพลาดภายใน (prepare insert)'; @unlink($destPath);
                }
            }
        }
    }
}

// ดึงการจองของฉัน + รายละเอียดห้อง
$sql = "
SELECT b.booking, b.booking_date, b.start_time, b.end_time, b.room_code,
       mr.room_name, mr.price_per_hour
FROM bookings b
JOIN meeting_rooms mr ON b.room_code = mr.room_code
WHERE b.user_id = ?
ORDER BY b.booking_date DESC, b.start_time DESC
";
$stmt = $hotelConn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// เตรียมดึงสถานะสลิปล่าสุดของแต่ละ booking
$latestPayment = [];
if ($bookings) {
    $ids = array_column($bookings, 'booking');
    $in  = implode(',', array_map('intval', $ids));
    $q = $hotelConn->query("
      SELECT p.*
      FROM payments p
      JOIN (
         SELECT booking_id, MAX(uploaded_at) AS mx
         FROM payments
         WHERE booking_id IN ($in)
         GROUP BY booking_id
      ) t ON p.booking_id=t.booking_id AND p.uploaded_at=t.mx
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $latestPayment[(int)$r['booking_id']] = $r;
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>การจองของฉัน + แนบสลิป</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .badge-pill{border-radius:999px}
    .pending{background:#fff5d1}
    .approved{background:#e9fff0}
    .rejected{background:#ffe8e8}
  </style>

  <style>
  .badge-pill {
    border-radius: 999px;
    padding: 0.4em 0.8em;
    font-weight: 600;
    font-size: 0.85rem;
  }
  .pending {
    background: #ff9800 !important;  /* ส้มเข้ม */
    color: #fff !important;
  }
  .approved {
    background: #2e7d32 !important;  /* เขียวเข้ม */
    color: #fff !important;
  }
  .rejected {
    background: #c62828 !important;  /* แดงเข้ม */
    color: #fff !important;
  }
</style>

</head>
<body class="bg-light">
<div class="container py-4">
  <h2 class="mb-3">การจองของฉัน</h2>
  <p class="text-muted mb-4">ดูรายการจอง และอัปโหลดสลิปชำระเงินต่อรายการได้จากหน้านี้</p>

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <?php if (!$bookings): ?>
    <div class="alert alert-secondary">ยังไม่มีรายการจอง</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle bg-white">
        <thead class="table-light">
          <tr>
            <th>#จอง</th>
            <th>ห้อง</th>
            <th>วันเวลา</th>
            <th>ราคา/ชม.</th>
            <th>สถานะสลิปล่าสุด</th>
            <th>แนบสลิป</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($bookings as $b): 
            $pid = (int)$b['booking'];
            $p = $latestPayment[$pid] ?? null;
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($b['booking']) ?></strong></td>
              <td><?= htmlspecialchars($b['room_name']) ?></td>
              <td>
                <?= htmlspecialchars($b['booking_date']) ?>
                <?= htmlspecialchars(substr($b['start_time'],0,5)) ?>–<?= htmlspecialchars(substr($b['end_time'],0,5)) ?>
              </td>
              <td><?= number_format((float)$b['price_per_hour'], 2) ?></td>
              <td>
                <?php if ($p): ?>
                  <span class="badge badge-pill <?= htmlspecialchars($p['verified']) ?>">
                    <?= htmlspecialchars($p['verified']) ?>
                  </span>
                  <div><small class="text-muted"><?= htmlspecialchars($p['uploaded_at']) ?></small></div>
                  <?php if (!empty($p['slip_path'])): ?>
                    <div><a href="<?= htmlspecialchars($p['slip_path']) ?>" target="_blank" rel="noopener">ดูสลิป</a></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">ยังไม่มีสลิป</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['booking'] ?>">
                  <input type="email" name="email" class="form-control form-control-sm"
                         placeholder="อีเมลผู้จอง"
                         value="<?= htmlspecialchars($user_email) ?>" required style="max-width:220px">
                  <input type="file" name="slip" accept=".jpg,.jpeg,.png,.pdf" class="form-control form-control-sm" required style="max-width:260px">
                  <button type="submit" name="upload_slip" class="btn btn-primary btn-sm">อัปโหลด</button>
                </form>
                <small class="text-muted">ไฟล์ .jpg .jpeg .png .pdf (≤ 2MB)</small>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
    <a href="index.php" class="btn btn-outline-secondary mt-3">กลับหน้าหลัก</a>
  <a href="javascript:history.back()" class="btn btn-secondary mt-3 ms-2">ย้อนกลับ</a>

</div>
</body>
</html>
