<?php
/* booking_form.php */
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

/* --------- DB CONFIG (แก้ให้ตรงของคุณ) --------- */
$host = "localhost";
$dbname = "hotel_db";
$username = "root";
$password = "";

/* --------- ดึงห้อง + อีเวนต์ที่จอง --------- */
try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);

  // ห้องประชุม
  $rooms = $pdo->query("
    SELECT room_code, room_name, price_per_hour, image
    FROM meeting_rooms
    ORDER BY room_name ASC
  ")->fetchAll(PDO::FETCH_ASSOC);

  // อีเวนต์ทั้งหมด (ให้ JS กรองตามห้อง)
  $stmt = $pdo->query("
    SELECT b.booking,
           b.booking_date,
           b.start_time,
           b.end_time,
           b.room_code,
           COALESCE(r.room_name, CONCAT('ห้อง ', b.room_code)) AS room_name
    FROM bookings b
    LEFT JOIN meeting_rooms r ON r.room_code = b.room_code
    ORDER BY b.booking_date ASC, b.start_time ASC
  ");
  $events = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $events[] = [
      'id'    => (string)$row['booking'],
      'title' => $row['room_name'] . ' ' .
                 '(' . substr($row['start_time'],0,5) . '–' . substr($row['end_time'],0,5) . ')',
      'start' => $row['booking_date'] . 'T' . substr($row['start_time'],0,8),
      'end'   => $row['booking_date'] . 'T' . substr($row['end_time'],0,8),
      'room'  => $row['room_code']
    ];
  }
} catch (PDOException $e) {
  die("DB Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>จองห้องประชุม</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- FullCalendar CSS -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css" rel="stylesheet" />
  <style>
    body{background:#f7f9fc}
    .room-image-wrapper{width:320px;height:200px;border:1px solid #ddd;border-radius:10px;overflow:hidden;background:#fff}
    #roomImage{width:100%;height:100%;object-fit:cover;display:none}
    .fc .fc-toolbar-title{font-weight:800;color:#ff6f00}
    .fc .fc-button-primary{background:#ff6f00;border-color:#ff6f00}
  </style>
</head>
<body>
<div class="container py-4">
  <h3 class="mb-3">จองห้องประชุม</h3>

  <form id="bookingForm" method="post" action="payment.php" novalidate>
    <!-- เลือกห้อง -->
    <div class="mb-3">
      <label class="form-label">ห้องประชุม</label>
      <select class="form-select" id="room_id" name="room_id" required>
        <option value="">-- เลือกห้องประชุม --</option>
        <?php foreach($rooms as $r): ?>
          <option
            value="<?= htmlspecialchars($r['room_code']) ?>"
            data-price="<?= htmlspecialchars($r['price_per_hour']) ?>"
            data-image="<?= htmlspecialchars($r['image']) ?>"
          >
            <?= htmlspecialchars($r['room_name']) ?> (<?= htmlspecialchars($r['room_code']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <div class="room-image-wrapper mt-2">
        <img id="roomImage" alt="รูปห้อง"/>
      </div>
    </div>

    <!-- วันที่ -->
    <div class="mb-3">
      <label class="form-label">วันที่จอง</label>
      <input class="form-control" id="booking_date" name="booking_date"
        placeholder="คลิกเพื่อเลือกจากปฏิทิน" readonly required />
      <div class="form-text">คลิกช่องด้านบนเพื่อเปิดปฏิทิน</div>
    </div>

    <!-- เวลา -->
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">เวลาเริ่ม</label>
        <select class="form-select" id="start_time" name="start_time" required>
          <option value="">-- เลือกเวลาเริ่ม --</option>
          <?php for($h=8;$h<=20;$h++) foreach([0,30] as $m){ $t=sprintf('%02d:%02d',$h,$m); ?>
            <option value="<?= $t ?>"><?= $t ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">เวลาสิ้นสุด</label>
        <select class="form-select" id="end_time" name="end_time" required>
          <option value="">-- เลือกเวลาสิ้นสุด --</option>
          <?php for($h=8;$h<=20;$h++) foreach([0,30] as $m){ $t=sprintf('%02d:%02d',$h,$m); ?>
            <option value="<?= $t ?>"><?= $t ?></option>
          <?php } ?>
        </select>
      </div>
    </div>

    <!-- จำนวนคน + ความต้องการเพิ่มเติม -->
    <div class="row g-3 mt-1">
      <div class="col-md-6">
        <label class="form-label">จำนวนผู้เข้าประชุม</label>
        <input type="number" min="1" class="form-control" id="attendees" name="attendees" placeholder="เช่น 20" required />
      </div>
      <div class="col-md-6">
        <label class="form-label">ความต้องการเพิ่มเติม</label>
        <input type="text" class="form-control" id="requirements" name="requirements"
          placeholder="ไมโครโฟน 2 ตัว, โปรเจ็กเตอร์, กระดานไวท์บอร์ด ฯลฯ" />
      </div>
    </div>

    <!-- ราคา -->
    <div id="priceInfo" class="mt-3 text-success fw-bold"></div>

    <!-- ปุ่ม -->
    <div class="row g-2 mt-3">
      <div class="col-12 col-md-6 d-grid">
        <button type="button" id="submitBtn" class="btn btn-primary btn-lg">ไปหน้าชำระเงิน</button>
      </div>
      <div class="col-12 col-md-3 d-grid">
        <a class="btn btn-outline-secondary btn-lg" href="home.php">กลับหน้าหลัก</a>
      </div>
      <div class="col-12 col-md-3 d-grid">
        <a class="btn btn-outline-dark btn-lg" href="check_status.php">ตรวจสอบสถานะ</a>
      </div>
    </div>
  </form>
</div>

<!-- Modal ปฏิทิน -->
<div class="modal fade" id="calendarModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">เลือกวันที่จอง</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><div id="calendar"></div></div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- ใช้ GLOBAL BUILD ของ FullCalendar (สำคัญมาก) -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/th.global.min.js"></script>
<script>
  // ===== ดาต้าจาก PHP =====
  const allEvents = <?= json_encode($events, JSON_UNESCAPED_UNICODE) ?>;

  // ===== อิลิเมนต์ =====
  const roomSelect  = document.getElementById('room_id');
  const roomImage   = document.getElementById('roomImage');
  const bookingDate = document.getElementById('booking_date');
  const startTime   = document.getElementById('start_time');
  const endTime     = document.getElementById('end_time');
  const priceInfo   = document.getElementById('priceInfo');

  let calendar = null;
  const calendarModal = new bootstrap.Modal(document.getElementById('calendarModal'));

  // กรองอีเวนต์ตามห้อง
  function filteredEvents() {
    const room = roomSelect.value;
    return room ? allEvents.filter(e => e.room === room) : allEvents;
  }

  // สร้าง/อัปเดตปฏิทิน
  function ensureCalendar() {
    const el = document.getElementById('calendar');

    if (calendar) {
      calendar.removeAllEventSources();
      calendar.addEventSource(filteredEvents());
      calendar.render();
      return;
    }
    calendar = new FullCalendar.Calendar(el, {
      locale: 'th',
      height: 'auto',
      initialView: 'dayGridMonth',
      events: filteredEvents(),
      dateClick: info => {
        bookingDate.value = info.dateStr;
        calendarModal.hide();
      },
      eventDidMount: info => {
        info.el.style.background = '#ffd54f';
        info.el.style.borderRadius = '6px';
        info.el.style.color = '#000';
        info.el.style.fontWeight = '600';
      }
    });
    calendar.render();
  }

  function showCalendar() {
    calendarModal.show();
    setTimeout(ensureCalendar, 200); // ให้โมดัลวาง layout ก่อนค่อย render
  }

  // อัปเดตรูปห้อง
  function updateRoomImage() {
    const opt = roomSelect.selectedOptions[0];
    const img = opt ? opt.dataset.image : '';
    if (img) {
      roomImage.src = 'uploads/' + img;
      roomImage.style.display = 'block';
    } else {
      roomImage.style.display = 'none';
    }
  }

  // คิดราคา
  function calculatePrice() {
    const opt = roomSelect.selectedOptions[0];
    const price = opt ? parseFloat(opt.dataset.price || 0) : 0;
    const s = startTime.value, e = endTime.value;
    if (!price || !s || !e) { priceInfo.textContent = ''; return; }
    const st = new Date(`1970-01-01T${s}:00`).getTime();
    const et = new Date(`1970-01-01T${e}:00`).getTime();
    if (et > st) {
      const hrs = (et - st) / 3600000;
      priceInfo.textContent = `ราคารวมโดยประมาณ: ${(hrs * price).toFixed(2)} บาท (${hrs.toFixed(1)} ชม. × ${price.toFixed(2)} บาท/ชม.)`;
    } else {
      priceInfo.textContent = '';
    }
  }

  // === Events ===
  bookingDate.addEventListener('click', showCalendar);
  bookingDate.addEventListener('focus', showCalendar);
  roomSelect.addEventListener('change', () => {
    updateRoomImage();
    calculatePrice();
    if (calendar) { ensureCalendar(); }
  });
  startTime.addEventListener('change', calculatePrice);
  endTime.addEventListener('change', calculatePrice);

  document.getElementById('submitBtn').addEventListener('click', () => {
    const form = document.getElementById('bookingForm');
    if (!form.checkValidity()) {
      alert('กรุณากรอกข้อมูลให้ครบก่อนทำรายการ');
      return;
    }
    form.submit();
  });

  // เริ่มต้น
  updateRoomImage();
</script>
</body>
</html>
