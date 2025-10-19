<?php
// check_status.php — รองรับทั้งกรณีมี/ไม่มีคอลัมน์ total_price + แสดงสลิปใน Modal + ปุ่มย้อนกลับ
require 'config.php';
session_start();

$err = null; $result = null; $payment = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $email = trim($_POST['email'] ?? '');

    if ($booking_id <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'กรุณากรอกข้อมูลให้ครบและถูกต้อง';
    } else {
        
        $stmt = $userConn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$u) {
            $err = 'ไม่พบอีเมลนี้ในระบบ';
        } else {
            $uid = (int)$u['id'];

            
            $hasTotal = $hotelConn->query("SHOW COLUMNS FROM bookings LIKE 'total_price'")->num_rows > 0;
            $selTotal = $hasTotal ? "b.total_price," : "NULL AS total_price,";

            
            $sql = "SELECT b.booking, b.user_id, b.room_code, b.booking_date, b.start_time, b.end_time,
                           $selTotal
                           mr.room_name, mr.price_per_hour
                    FROM bookings b
                    JOIN meeting_rooms mr ON b.room_code = mr.room_code
                    WHERE b.booking=? AND b.user_id=? LIMIT 1";
            $stmt = $hotelConn->prepare($sql);
            $stmt->bind_param('ii', $booking_id, $uid);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$result) {
                $err = 'ไม่พบข้อมูลการจองของคุณ';
            } else {
                // 3) สถานะสลิปล่าสุด (ถ้ามีตาราง payments)
                $hasPayments = $hotelConn->query("SHOW TABLES LIKE 'payments'")->num_rows > 0;
                if ($hasPayments) {
                    $stmt = $hotelConn->prepare("SELECT verified, uploaded_at, slip_path
                                                 FROM payments
                                                 WHERE booking_id=?
                                                 ORDER BY uploaded_at DESC LIMIT 1");
                    $stmt->bind_param('i', $booking_id);
                    $stmt->execute();
                    $payment = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                }

                // 4) คำนวณยอดรวมจากข้อมูลจริงใน DB
                $toHms = function($t){
                    $t = trim($t);
                    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) return $t;
                    if (preg_match('/^\d{2}:\d{2}$/', $t))     return $t.':00';
                    $ts = strtotime($t); return $ts ? date('H:i:s', $ts) : '00:00:00';
                };
                $s = new DateTime("1970-01-01 ".$toHms($result['start_time']));
                $e = new DateTime("1970-01-01 ".$toHms($result['end_time']));
                $hours = max(0, ($e->getTimestamp() - $s->getTimestamp())/3600);

                $total = ($result['total_price'] !== null)
                       ? (float)$result['total_price']
                       : $hours * (float)$result['price_per_hour'];

                $result['hours'] = $hours;
                $result['total'] = $total;
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>ตรวจสอบสถานะการจอง</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f7f7fb}
    .card{max-width:880px;margin:32px auto;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.06)}
  </style>
</head>
<body>
<div class="card p-4">
  <h3 class="mb-3">ตรวจสอบสถานะการจอง</h3>
  <p class="text-muted">กรอกหมายเลขการจองและอีเมลผู้จองเพื่อดูรายละเอียดและสถานะล่าสุด</p>

  <?php if ($err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
  <?php endif; ?>

  <form method="post" class="mb-4">
    <div class="mb-3">
      <label class="form-label" for="booking_id">หมายเลขการจอง</label>
      <input type="number" class="form-control" id="booking_id" name="booking_id"
             required value="<?= htmlspecialchars($_POST['booking_id'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label" for="email">อีเมลผู้จอง</label>
      <input type="email" class="form-control" id="email" name="email"
             required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <button type="submit" class="btn btn-warning text-white">ตรวจสอบ</button>

  </form>

  <?php if ($result): ?>
    <div class="border rounded p-3 bg-white">
      <h5 class="mb-3">ผลการค้นหา</h5>
      <div><strong>เลขที่การจอง:</strong> <?= (int)$result['booking'] ?></div>
      <div><strong>ห้อง:</strong> <?= htmlspecialchars($result['room_name']) ?></div>
      <div><strong>วันที่:</strong> <?= htmlspecialchars($result['booking_date']) ?></div>
      <div><strong>เวลา:</strong> <?= htmlspecialchars(substr($result['start_time'],0,5)) ?>
        – <?= htmlspecialchars(substr($result['end_time'],0,5)) ?>
        (<?= number_format($result['hours'],2) ?> ชม.)
      </div>
      <div><strong>ราคา/ชั่วโมง:</strong> <?= number_format((float)$result['price_per_hour'],2) ?> บาท</div>
      <hr>
      <div><strong>ยอดรวม:</strong> <?= number_format((float)$result['total'],2) ?> บาท</div>

      <?php if ($payment): ?>
        <div class="mt-3">
          <strong>สถานะสลิป:</strong>
          <span class="badge bg-<?= $payment['verified']==='approved'?'success':($payment['verified']==='rejected'?'danger':'warning') ?>">
            <?= htmlspecialchars($payment['verified']) ?>
          </span>
          <div class="text-muted"><small>อัปโหลดล่าสุด: <?= htmlspecialchars($payment['uploaded_at']) ?></small></div>
          <?php if (!empty($payment['slip_path'])): ?>
            <!-- ปุ่มเปิดดูสลิปใน Modal -->
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#slipModal">
              ดูสลิป
            </button>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="mt-2 text-muted">ยังไม่พบการแนบสลิป</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- ปุ่มย้อนกลับ -->
  <div class="mt-3 d-flex gap-2">
  <a href="javascript:history.back()" class="btn btn-outline-secondary">ย้อนกลับ</a>
  <a href="home.php" class="btn btn-success">เสร็จสิ้น</a>
</div>


<?php if ($result && $payment && !empty($payment['slip_path'])): ?>
<!-- Modal ดูสลิป -->
<div class="modal fade" id="slipModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">สลิปการชำระเงิน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <?php if (preg_match('/\.pdf$/i', $payment['slip_path'])): ?>
          <iframe src="<?= htmlspecialchars($payment['slip_path']) ?>" width="100%" height="500" style="border:0"></iframe>
        <?php else: ?>
          <img src="<?= htmlspecialchars($payment['slip_path']) ?>" class="img-fluid" style="max-height:500px;object-fit:contain" alt="สลิป">
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <a href="<?= htmlspecialchars($payment['slip_path']) ?>" class="btn btn-outline-primary" target="_blank" rel="noopener">เปิดในแท็บใหม่</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
