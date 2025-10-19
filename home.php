<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ดึงข้อมูลผู้ใช้
$user_id = intval($_SESSION['user_id']);
$userRes = $userConn->query("SELECT * FROM users WHERE id = $user_id");
if ($userRes && $userRes->num_rows > 0) {
    $user = $userRes->fetch_assoc();
} else {
    session_destroy();
    header("Location: login.php");
    exit;
}

// ดึงข้อมูลห้องจาก hotel_db
$rooms = [];
$res = $hotelConn->query("SELECT * FROM meeting_rooms ORDER BY room_name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rooms[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>เลือกห้องประชุม | โรงแรมขอนแก่นโฮเต็ล</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
<style>
body { font-family:'Sarabun',sans-serif; background:#fff; margin:0; }
.sidebar {
  position:fixed; left:0; top:0; bottom:0; width:250px; background:#111; color:#fff;
  display:flex; flex-direction:column; justify-content:space-between; padding:20px 0;
}
.sidebar a { color:#fff; text-decoration:none; display:block; padding:10px 20px; font-size:17px; transition:.3s; }
.sidebar a:hover { background:#333; }
.sidebar h2 { text-align:left; padding:0 20px; font-weight:700; font-size:20px; margin-bottom:10px; }
.contact-box { font-size:14px; border-top:1px solid #444; margin:10px 20px 0; padding-top:10px; color:#ccc; }

.content { margin-left:260px; padding:30px; }
h1 { text-align:center; font-weight:700; font-size:2.4rem; color:#000; text-shadow:2px 2px 6px rgba(0,0,0,.2);}
.subtitle { text-align:center; color:#555; margin-bottom:25px; }
.container-rooms { 
  display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:25px; max-width:1200px; margin:auto;
}
.room-card {
  background:#fff; border-radius:12px; box-shadow:0 6px 14px rgba(0,0,0,.1);
  overflow:hidden; text-align:center; cursor:pointer; transition:.3s;
}
.room-card:hover { transform:translateY(-6px); box-shadow:0 10px 20px rgba(0,0,0,.15);}
.room-card img { width:100%; height:180px; object-fit:cover; }
.room-name { font-size:1.2rem; font-weight:600; padding:15px 10px 10px; color:#000; }
.modal-body p { color:#000; }
.btn-primary { background:#007bff; border:none; }
.btn-primary:hover { background:#0056b3; }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <div>
    <h2>เมนู</h2>
    <a href="hisroom.php">ประวัติการจองของฉัน</a>
    <a href="rooms.php" class="active">จองห้องประชุม</a>
    <a href="check_status.php">ตรวจสอบสถานะการจอง</a>
  </div>
  <div class="contact-box">
    <div><strong>ติดต่อโรงแรม</strong></div>
    โทร: 043-123456<br>
    อีเมล: contact@khonkaenhotel.com
    <br><br>
    <a href="logout.php" class="btn btn-danger btn-sm w-100 mt-2">ออกจากระบบ</a>
  </div>
</div>

<!-- Content -->
<div class="content">
  <h1>ห้องประชุม</h1>
  <p class="subtitle">ระบบจองห้องประชุม โรงแรมขอนแก่นโฮเต็ล</p>

  <div class="container-rooms">
    <?php if (!empty($rooms)): ?>
      <?php foreach ($rooms as $room): ?>
        <div class="room-card" data-bs-toggle="modal" data-bs-target="#roomModal<?= htmlspecialchars($room['room_code']) ?>">
          <img src="uploads/<?= htmlspecialchars($room['image']) ?>" alt="<?= htmlspecialchars($room['room_name']) ?>">
          <div class="room-name"><?= htmlspecialchars($room['room_name']) ?></div>
        </div>

        <!-- Modal รายละเอียดห้อง -->
        <div class="modal fade" id="roomModal<?= htmlspecialchars($room['room_code']) ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><?= htmlspecialchars($room['room_name']) ?></h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <?php if (!empty($room['image'])): ?>
                  <img src="uploads/<?= htmlspecialchars($room['image']) ?>" class="img-fluid mb-3 rounded">
                <?php endif; ?>
                <p><strong>รายละเอียดห้อง:</strong> <?= nl2br(htmlspecialchars($room['description'])) ?></p>
                <p><strong>จำนวนที่นั่ง:</strong> <?= htmlspecialchars($room['capacity']) ?> ที่นั่ง</p>
                <p><strong>ขนาดห้อง:</strong> <?= htmlspecialchars($room['room_size']) ?></p>
                <p><strong>อุปกรณ์:</strong> <?= htmlspecialchars($room['tools']) ?></p>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-center text-muted">ไม่มีข้อมูลห้องประชุมในระบบ</p>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
