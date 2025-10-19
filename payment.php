<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

$host = "localhost";
$dbname = "hotel_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $e->getMessage());
}

// รับข้อมูลจากฟอร์ม
$room_id = $_POST['room_id'] ?? '';
$booking_date = $_POST['booking_date'] ?? '';
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';

// ตรวจสอบข้อมูลครบหรือไม่
if (!$room_id || !$booking_date || !$start_time || !$end_time) {
    die("กรุณากรอกข้อมูลให้ครบทุกช่อง");
}

// ดึงข้อมูลห้องประชุมที่เลือก
$stmt = $pdo->prepare("SELECT * FROM meeting_rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    die("ไม่พบข้อมูลห้องประชุมที่เลือก");
}

// คำนวณเวลารวม (ชั่วโมง)
$startTimestamp = strtotime("$booking_date $start_time");
$endTimestamp = strtotime("$booking_date $end_time");

if ($startTimestamp >= $endTimestamp) {
    die("เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น");
}

$hours = ($endTimestamp - $startTimestamp) / 3600;
$total_price = $hours * $room['price_per_hour'];

?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<title>ใบชำระเงิน</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">ใบชำระเงินสำหรับการจองห้องประชุม</h2>

    <div class="card p-4">
        <p><strong>ชื่อห้องประชุม:</strong> <?= htmlspecialchars($room['room_name']) ?></p>
        <p><strong>วันที่จอง:</strong> <?= htmlspecialchars($booking_date) ?></p>
        <p><strong>เวลา:</strong> <?= htmlspecialchars($start_time) ?> ถึง <?= htmlspecialchars($end_time) ?> (<?= number_format($hours, 2) ?> ชั่วโมง)</p>
        <p><strong>ราคา/ชั่วโมง:</strong> <?= number_format($room['price_per_hour'], 2) ?> บาท</p>
        <hr>
        <h4>รวมเป็นเงิน: <?= number_format($total_price, 2) ?> บาท</h4>
    </div>

    <form method="POST" action="confirm_booking.php" class="mt-4">
        <!-- ส่งข้อมูลต่อไปเพื่อบันทึกการจอง -->
        <input type="hidden" name="room_id" value="<?= htmlspecialchars($room_id) ?>" />
        <input type="hidden" name="booking_date" value="<?= htmlspecialchars($booking_date) ?>" />
        <input type="hidden" name="start_time" value="<?= htmlspecialchars($start_time) ?>" />
        <input type="hidden" name="end_time" value="<?= htmlspecialchars($end_time) ?>" />
        <input type="hidden" name="total_price" value="<?= htmlspecialchars($total_price) ?>" />

        <button type="submit" class="btn btn-success">ยืนยันการจองและชำระเงิน</button>
        <a href="rooms.php" class="btn btn-secondary ms-2">ย้อนกลับ</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
