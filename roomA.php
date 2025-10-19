<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// ดึงรายชื่อห้องทั้งหมด
$rooms = [];
$resRooms = $hotelConn->query("SELECT * FROM meeting_rooms ORDER BY name ASC");
if ($resRooms && $resRooms->num_rows > 0) {
    while ($r = $resRooms->fetch_assoc()) {
        $rooms[$r['room_code']] = $r;
    }
}

// ห้องที่เลือกจาก dropdown (default เอาห้องแรก)
$room_code = $_GET['room_code'] ?? '';
if (!$room_code || !isset($rooms[$room_code])) {
    // ถ้าไม่มีหรือไม่ถูกต้อง เลือกห้องแรกในรายการแทน
    $room_code = array_key_first($rooms);
}

$room = $rooms[$room_code];

// ดึงวันที่ถูกจองของห้องนี้ (3 เดือนข้างหน้า)
$today = date('Y-m-01');
$endDate = date('Y-m-t', strtotime("+3 months"));

$stmt2 = $hotelConn->prepare("SELECT booking_date FROM bookings WHERE room_code = ? AND booking_date BETWEEN ? AND ?");
if ($stmt2 === false) {
    die("Prepare failed: " . htmlspecialchars($hotelConn->error));
}
$stmt2->bind_param("sss", $room_code, $today, $endDate);
$stmt2->execute();

$reservedDates = [];
$result2 = $stmt2->get_result();
while ($row = $result2->fetch_assoc()) {
    $reservedDates[] = $row['booking_date'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>รายละเอียดห้องประชุม</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet" />
  <style>
    body {
      font-family: 'Sarabun', sans-serif;
      background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
      color: #fff;
      padding: 30px;
      max-width: 900px;
      margin: auto;
    }
    h1 {
      margin-bottom: 10px;
    }
    select {
      color: #fff; /* สีข้อความใน dropdown */
      background: rgba(255,255,255,0.1); /* สีพื้นหลัง (ตามที่มีอยู่ก็ได้) */
      font-weight: 600; /* หนาขึ้น */
      font-size: 16px; /* ขนาดตัวอักษร */
      padding: 10px;
      border-radius: 10px;
      border: none;
      width: 100%;
      max-width: 300px;
      /* เพิ่มให้ข้อความใน dropdown อ่านง่าย */
      text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
    }

    img {
      width: 100%;          /* กว้างเต็ม container */
      max-width: 600px;     /* ไม่เกิน 600px กำหนดขนาดสูงสุด */
      height: auto;         /* รักษาสัดส่วน */
      border-radius: 12px;
      margin-bottom: 20px;
      object-fit: cover;    /* ครอบภาพให้เต็มพื้นที่ กรณีความกว้างกับสูงไม่เท่ากัน */
      display: block;
      margin-left: auto;
      margin-right: auto;
    }

    .calendar {
      margin-top: 30px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th, td {
      border: 1px solid rgba(255,255,255,0.2);
      text-align: center;
      padding: 10px;
    }
    .unavailable {
      background: #ff4d4d;
      color: white;
    }
    .available {
      background: #00cc99;
      color: white;
      cursor: pointer;
    }
    .month-selector {
      margin-bottom: 20px;
    }
  </style>
</head>
<body>
  <h1>รายละเอียดห้องประชุม</h1>

  <form method="GET" id="roomForm">
    <label for="room_code">เลือกห้องประชุม:</label>
    <select name="room_code" id="room_code" onchange="document.getElementById('roomForm').submit()">
      <?php foreach ($rooms as $code => $r): ?>
        <option value="<?= htmlspecialchars($code) ?>" <?= $code === $room_code ? 'selected' : '' ?>>
          <?= htmlspecialchars($r['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <h2><?= htmlspecialchars($room['name']) ?></h2>

  <?php if (!empty($room['image']) && file_exists('uploads/' . $room['image'])): ?>
    <img src="uploads/<?= htmlspecialchars($room['image']) ?>" alt="ภาพห้องประชุม" />
  <?php endif; ?>

  <p><?= htmlspecialchars($room['description']) ?></p>
  <p><strong>จำนวนที่นั่ง:</strong> <?= $room['capacity'] ?></p>
  <p><strong>ขนาด:</strong> <?= $room['room_size'] ?></p>
  <p><strong>อุปกรณ์:</strong> <?= $room['tools'] ?></p>

  <div class="month-selector">
    <label for="month">เลือกเดือน: </label>
    <select id="month" onchange="changeMonth()">
      <?php
        for ($i = 0; $i < 4; $i++) {
          $date = strtotime("+$i month");
          $val = date('Y-m', $date);
          $text = date('F Y', $date);
          echo "<option value=\"$val\">$text</option>";
        }
      ?>
    </select>
  </div>

  <div class="calendar" id="calendar-container">
    <!-- ปฏิทินจะแสดงที่นี่ -->
  </div>

<script>
  const reservedDates = <?= json_encode($reservedDates) ?>;

  function changeMonth() {
    const container = document.getElementById("calendar-container");
    const selectedMonth = document.getElementById("month").value;
    const year = parseInt(selectedMonth.split("-")[0]);
    const month = parseInt(selectedMonth.split("-")[1]) - 1;

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    let html = `<table><thead><tr>`;
    ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'].forEach(day => html += `<th>${day}</th>`);
    html += `</tr></thead><tbody><tr>`;

    for (let i = 0; i < firstDay.getDay(); i++) html += `<td></td>`;

    for (let d = 1; d <= lastDay.getDate(); d++) {
      const fullDate = `${selectedMonth}-${String(d).padStart(2, '0')}`;
      const isReserved = reservedDates.includes(fullDate);
      const tdClass = isReserved ? 'unavailable' : 'available';

      if (isReserved) {
        // ถ้าวันนี้ถูกจองแล้ว แค่แสดงเฉยๆ
        html += `<td class="${tdClass}">${d}</td>`;
      } else {
        // ถ้าวันนี้ว่าง ให้คลิกได้และพาไปหน้า checkout พร้อมส่งรหัสห้องกับวันที่
        html += `<td class="${tdClass}" onclick="goToCheckout('${fullDate}')">${d}</td>`;
      }

      if ((firstDay.getDay() + d) % 7 === 0) html += '</tr><tr>';
    }

    html += `</tr></tbody></table>`;
    container.innerHTML = html;
  }

  function goToCheckout(date) {
    const roomCode = "<?= htmlspecialchars($room_code) ?>";
    const duration = 'full';
    const startTime = '09:00';
    const endTime = '17:00';

    const url = new URL('checkout.php', window.location.origin);
    url.searchParams.set('room_code', roomCode);
    url.searchParams.set('date', date);
    url.searchParams.set('duration', duration);
    url.searchParams.set('start_time', startTime);
    url.searchParams.set('end_time', endTime);

    window.location.href = `checkout.php?room_code=${encodeURIComponent(roomCode)}&date=${encodeURIComponent(date)}`;
  }

  window.onload = changeMonth;
</script>

</body>
</html>
