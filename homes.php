<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$result = $userConn->query("SELECT * FROM users WHERE id = " . intval($_SESSION['user_id']));
if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    session_destroy();
    header('Location: index.php');
    exit();
}

$rooms = [];
$sqlRooms = "SELECT * FROM meeting_rooms ORDER BY room_name ASC";
$resRooms = $hotelConn->query($sqlRooms);

if (!$resRooms) {
    die("Query error: " . $userConn->error);
}

if ($resRooms->num_rows > 0) {
    while ($row = $resRooms->fetch_assoc()) {
        $rooms[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>เลือกห้องประชุม - โรงแรมขอนแก่นโฮเต็ล</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet" />
  
  <!-- ใช้ Bootstrap 5 CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  
  <style>
    * {
      margin: 0; padding: 0; box-sizing: border-box;
      font-family: 'Sarabun', sans-serif;
    }

    /* กำหนดการแสดงผลของ Sidebar */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      height: 100%;
      width: 250px;
      background-color: #111;
      color: white;
      padding-top: 20px;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.5);
    }

    .sidebar a {
      padding: 8px 16px;
      text-decoration: none;
      font-size: 18px;
      color: white;
      display: block;
      transition: background-color 0.3s;
    }

    .sidebar a:hover {
      background-color: #575757;
    }

    .content {
      margin-left: 260px; /* ทำให้เนื้อหาหลักเลื่อนจากแถบด้านซ้าย */
      padding: 40px 20px;
    }

    h1 {
      font-size: 3rem;
      font-weight: 600;
      margin-bottom: 20px;
      text-align: center;
      color: black; /* ตั้งสีตัวอักษรของ h1 เป็นสีดำ */
      text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.4);
    }

    p.subtitle {
      color: #020202ff;
      font-weight: 400;
      text-align: center;
      font-size: 1.1rem;
      margin-bottom: 30px;
    }

    .container {
      max-width: 1200px;
      width: 100%;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 30px;
      margin-top: 20px;
    }

    .room-card {
      background: rgba(255, 255, 255, 0.85);
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between;
      height: 320px;
    }

    .room-card:hover {
      transform: translateY(-15px);
      box-shadow: 0 20px 30px rgba(0, 123, 255, 0.25);
    }

    .room-image {
      width: 100%;
      height: 180px;
      object-fit: cover;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .room-name {
      font-size: 1.6rem;
      color: #0c0c0cff;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .room-desc {
      font-size: 1rem;
      color: #7b8b9a;
      margin-bottom: 20px;
    }

    .room-info {
      font-weight: 500;
      color: #333;
      margin-bottom: 20px;
    }

    .select-button {
      background: linear-gradient(90deg, #00c6ff, #0072ff);
      border: none;
      padding: 12px 28px;
      border-radius: 30px;
      color: white;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      box-shadow: 0 6px 15px rgba(0, 190, 255, 0.8);
      transition: background 0.3s ease, transform 0.3s ease;
      margin-top: 30px;
    }

    .select-button:hover {
      background: linear-gradient(90deg, #0072ff, #00c6ff);
      transform: scale(1.05);
    }

    /* ปรับสีข้อความทั้งหมดใน Modal เป็นสีดำ */
    .modal-body,
    .modal-title,
    .modal-footer {
      color: black;
    }

  </style>
</head>
<body>
  <!-- แถบด้านซ้าย (Sidebar) -->
  <div class="sidebar">
  <h2 class="text-center">เมนู</h2>
  <a href="index.php" class="sidebar-link">หน้าหลัก</a> <!-- หน้าแรก -->
  <a href="hisroom.php" class="sidebar-link">ประวัติการจองของฉัน</a> <!-- ประวัติการจอง -->
  <a href="rooms.php" class="sidebar-link">จองห้องประชุม</a> <!-- จองห้องประชุม -->
  <a href="logout.php" class="sidebar-link">ออกจากระบบ</a> <!-- ออกจากระบบ -->
  <a href="check_status.php" class="sidebar-link">ตรวจสอบสถานะการจอง</a>
</div>


  <!-- เนื้อหาหลัก -->
  <div class="content">
    <h1>เลือกห้องประชุม</h1>
    <p class="subtitle">ระบบจองห้องประชุม โรงแรมขอนแก่นโฮเต็ล</p>

    <div class="container">
      <?php if (!empty($rooms)): ?>
        <?php foreach ($rooms as $room): ?>
          <div class="room-card" data-bs-toggle="modal" data-bs-target="#roomModal-<?= htmlspecialchars($room['room_code']) ?>">
            <?php if (!empty($room['image']) && file_exists('uploads/' . $room['image'])): ?>
              <img class="room-image" src="uploads/<?= htmlspecialchars($room['image']) ?>" alt="<?= htmlspecialchars($room['room_name']) ?>" />
            <?php else: ?>
              <div style="font-size: 60px;"></div>
            <?php endif; ?>

            <div class="room-name"><?= htmlspecialchars($room['room_name']) ?></div>
          </div>

          <!-- Modal for Room Details -->
          <div class="modal fade" id="roomModal-<?= htmlspecialchars($room['room_code']) ?>" tabindex="-1" aria-labelledby="roomModalLabel-<?= htmlspecialchars($room['room_code']) ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="roomModalLabel-<?= htmlspecialchars($room['room_code']) ?>"><?= htmlspecialchars($room['room_name']) ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <?php if (!empty($room['image']) && file_exists('uploads/' . $room['image'])): ?>
                    <img class="img-fluid mb-3" src="uploads/<?= htmlspecialchars($room['image']) ?>" alt="<?= htmlspecialchars($room['room_name']) ?>" />
                  <?php endif; ?>
                  <p><strong>รายละเอียดห้อง:</strong> <?= htmlspecialchars($room['description']) ?></p>
                  <p><strong>จำนวนที่นั่ง:</strong> <?= htmlspecialchars($room['capacity']) ?></p>
                  <p><strong>ขนาดห้อง:</strong> <?= htmlspecialchars($room['room_size']) ?></p>
                  <p><strong>อุปกรณ์:</strong> <?= htmlspecialchars($room['tools']) ?></p>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                  <button type="button" class="btn btn-primary" onclick="window.location.href='rooms.php?room_code=<?= htmlspecialchars($room['room_code']) ?>'">จองห้อง</button>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="text-align:center; width:100%; color:#ccc;">ไม่มีข้อมูลห้องประชุมในระบบ</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- เพิ่ม Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
