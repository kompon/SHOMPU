<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit();
}

$room_code = $_GET['room_code'] ?? '';
$date = $_GET['date'] ?? '';
$duration = $_GET['duration'] ?? 'full';  // รับค่า 'half' หรือ 'full' (default เต็มวัน)

if (!$room_code || !$date) {
  die("ข้อมูลไม่ครบถ้วน");
}

// ✅ ดึงข้อมูลห้องประชุม
$stmt = $hotelConn->prepare("SELECT * FROM meeting_rooms WHERE room_code = ?");
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($hotelConn->error));
}
$stmt->bind_param("s", $room_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("ไม่พบข้อมูลห้องประชุม");
}

$room = $result->fetch_assoc();

// ✅ กำหนดราคาตามชื่อห้องประชุม
switch ($room['name']) {
  case 'ห้องประชุมภูเวียง':
    $base_price = 1000;
    break;
  case 'ห้องประชุมภูผาม่าน':
  case 'ห้องประชุมภูพาน':
    $base_price = 2000;
    break;
  case 'ห้องประชุมอัพเดอะทาวน์':
    $base_price = 1500;
    break;
  default:
    $base_price = 0;
}

// ✅ กำหนดราคาตามช่วงเวลา
if ($duration === 'half') {
  $price = $base_price / 2;
} else {
  $price = $base_price;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>ตรวจสอบการจอง</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet" />
  <style>
    body {
      font-family: 'Sarabun', sans-serif;
      background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
      color: #fff;
      padding: 30px;
      max-width: 600px;
      margin: auto;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .checkout-container {
      background: rgba(255, 255, 255, 0.1);
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.5);
      width: 100%;
    }
    h1 {
      margin-bottom: 25px;
      font-weight: 600;
      font-size: 2rem;
      text-align: center;
    }
    p {
      font-size: 1.1rem;
      margin: 12px 0;
    }
    strong {
      color: #00cc99;
    }
    button {
      margin-top: 30px;
      width: 100%;
      background-color: #00cc99;
      border: none;
      padding: 15px 0;
      font-size: 1.2rem;
      font-weight: 600;
      color: #0f2027;
      border-radius: 12px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    button:hover {
      background-color: #009970;
      color: #fff;
    }
  </style>
</head>
<body>
  <div class="checkout-container">
    <h1>ตรวจสอบการจองห้องประชุม</h1>
    <p><strong>ห้องประชุม:</strong> <?= htmlspecialchars($room['name']) ?></p>
    <p><strong>วันที่จอง:</strong> <?= htmlspecialchars($date) ?></p>
    <p><strong>ราคา:</strong> <?= number_format($price, 2) ?> บาท</p>

    <form action="payment.php" method="POST">
      <input type="hidden" name="room_code" value="<?= htmlspecialchars($room_code) ?>">
      <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
      <input type="hidden" name="duration" value="<?= htmlspecialchars($duration) ?>">
      <input type="hidden" name="price" value="<?= $price ?>">
      <button type="submit">ไปชำระเงิน</button>
    </form>
  </div>
</body>
</html>
