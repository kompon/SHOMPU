<?php
session_start();
require_once __DIR__ . '/config.php'; // ต้องมี $conn = new mysqli(...)

function fail($msg) {
  return ['ok' => false, 'msg' => $msg];
}
function ok($msg) {
  return ['ok' => true, 'msg' => $msg];
}

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $email = trim($_POST['email'] ?? '');

    // ตรวจสอบอินพุต
    if ($booking_id <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result = fail('กรุณากรอกหมายเลขการจองและอีเมลให้ถูกต้อง');
    }

    // ตรวจสอบไฟล์
    if (!$result && (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK)) {
        $result = fail('กรุณาอัปโหลดไฟล์สลิป');
    }

    if (!$result) {
        // 1) ยืนยันว่า booking เป็นของอีเมลนี้จริง
        $sql = "
            SELECT b.id
            FROM bookings b
            INNER JOIN users u ON b.user_id = u.id
            WHERE b.id = ? AND u.email = ?
            LIMIT 1
        ";
        if (!$stmt = $conn->prepare($sql)) {
            $result = fail('เกิดข้อผิดพลาดภายใน (prepare)');
        } else {
            $stmt->bind_param('is', $booking_id, $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $result = fail('ไม่พบการจองนี้หรืออีเมลไม่ตรงกับผู้จอง');
            }
            $stmt->close();
        }
    }

    if (!$result) {
        // 2) ตรวจชนิดไฟล์/ขนาด
        $file = $_FILES['slip'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        if ($file['size'] <= 0 || $file['size'] > $maxSize) {
            $result = fail('ไฟล์ใหญ่เกิน 2MB หรือไม่พบไฟล์');
        } else {
            // ตรวจ MIME แบบ server-side
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
            if (!isset($allowedMimes[$mime])) {
                $result = fail('อนุญาตเฉพาะ .jpg .jpeg .png .pdf');
            }
        }
    }

    if (!$result) {
        // 3) เตรียมโฟลเดอร์ปลายทาง (ไม่แก้ .htaccess โฟลเดอร์เดิม)
        $destDir = __DIR__ . '/uploads/slips';
        if (!is_dir($destDir)) {
            // 0775: ให้เว็บเซิร์ฟเวอร์เขียนได้ แต่ไม่เปิดกว้างเกินไป
            @mkdir($destDir, 0775, true);
        }
        if (!is_dir($destDir) || !is_writable($destDir)) {
            $result = fail('ไม่สามารถสร้าง/เขียนโฟลเดอร์อัปโหลดได้ (uploads/slips)');
        }
    }

    if (!$result) {
        // 4) ตั้งชื่อไฟล์ปลอดภัย + ย้ายไฟล์
        $ext = $allowedMimes[$mime];
        $newName = 'booking_' . $booking_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $destDir . '/' . $newName;
        $relPath  = 'uploads/slips/' . $newName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $result = fail('อัปโหลดไฟล์ไม่สำเร็จ');
        } else {
            // 5) บันทึกฐานข้อมูล (ไม่แก้ตารางเดิม)
            $ins = $conn->prepare("
                INSERT INTO payments (booking_id, email, slip_path, verified)
                VALUES (?, ?, ?, 'pending')
            ");
            if (!$ins) {
                @unlink($destPath);
                $result = fail('เกิดข้อผิดพลาดภายใน (prepare insert)');
            } else {
                $ins->bind_param('iss', $booking_id, $email, $relPath);
                if ($ins->execute()) {
                    $result = ok('อัปโหลดสลิปสำเร็จ! สถานะกำลังรอตรวจสอบ');
                } else {
                    @unlink($destPath);
                    $result = fail('บันทึกข้อมูลไม่สำเร็จ');
                }
                $ins->close();
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>แนบสลิปการจอง</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, sans-serif; padding:24px; background:#f6f7fb;}
    .card{max-width:560px; margin:auto; background:#fff; border-radius:16px; padding:24px; box-shadow:0 10px 24px rgba(0,0,0,.08);}
    .row{margin-bottom:14px}
    label{display:block; font-weight:600; margin-bottom:6px}
    input[type="number"], input[type="email"], input[type="file"]{
      width:100%; padding:10px; border:1px solid #ddd; border-radius:10px
    }
    button{padding:12px 16px; border:0; background:#111; color:#fff; border-radius:12px; cursor:pointer}
    .alert{padding:10px 12px; border-radius:10px; margin-bottom:12px}
    .error{background:#ffe8e8; color:#a40000}
    .ok{background:#e9fff0; color:#006b2e}
    small{color:#666}
  </style>
</head>
<body>
  <div class="card">
    <h2>แนบสลิปการจอง</h2>
    <p>กรอกหมายเลขการจอง + อีเมล แล้วอัปโหลดสลิปเพื่อให้แอดมินตรวจสอบ</p>

    <?php if ($result): ?>
      <div class="alert <?= $result['ok'] ? 'ok' : 'error' ?>">
        <?= htmlspecialchars($result['msg']) ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
      <div class="row">
        <label for="booking_id">หมายเลขการจอง</label>
        <input type="number" id="booking_id" name="booking_id" required min="1" inputmode="numeric">
      </div>
      <div class="row">
        <label for="email">อีเมลผู้จอง</label>
        <input type="email" id="email" name="email" required>
      </div>
      <div class="row">
        <label for="slip">ไฟล์สลิป (.jpg .jpeg .png .pdf | ≤ 2MB)</label>
        <input type="file" id="slip" name="slip" accept=".jpg,.jpeg,.png,.pdf" required>
      </div>

      <button type="submit">อัปโหลดสลิป</button>
      <div class="row">
        <small>อัปโหลดแล้ว สถานะสลิปจะเป็น “pending” จนกว่าแอดมินจะยืนยัน</small>
      </div>
    </form>
  </div>
</body>
</html>