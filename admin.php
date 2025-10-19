<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$sql = "SELECT * FROM meeting_rooms";
$result = $hotelConn->query($sql);

?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<title>ระบบแอดมิน จัดการห้องประชุม</title>
<!-- ใช้ Google Font สำหรับฟอนต์ใหม่ -->
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet" />
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  /* เพิ่มแถบด้านซ้าย (Sidebar) */
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    width: 250px;
    background-color: #111;
    color: white;
    padding-top: 30px;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.5);
  }

  .sidebar a {
    padding: 10px 20px;
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
    margin-left: 260px; /* เนื้อหาหลักเลื่อนจากแถบด้านซ้าย */
    padding: 40px 20px;
  }

  /* การตั้งค่าเนื้อหาหลัก */
  body {
    font-family: 'Sarabun', sans-serif;
    background: linear-gradient(135deg, #fdfdfdff);
    color: black;
    padding: 20px;
  }

  h1 {
    font-size: 2.8rem;
    font-weight: 600;
    margin-bottom: 20px;
    text-align: center;
    color: #333;
  }

  p.subtitle {
    color: #333;
    font-weight: 400;
    text-align: center;
    font-size: 1.1rem;
    margin-bottom: 30px;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background-color: rgba(255,255,255,0.1);
    border-radius: 10px;
    overflow: hidden;
  }

  th, td {
    padding: 12px 15px;
    text-align: left;
  }

  th {
    background-color: #0f0f0fff;
    color: white;
  }

  tr:nth-child(even) {
    background-color: rgba(255,255,255,0.05);
  }

  a.button {
    padding: 6px 12px;
    border-radius: 20px;
    color: #000;
    text-decoration: none;
    font-weight: 600;
    margin-right: 5px;
  }

  .content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;  /* จัดข้อความไว้ทางซ้าย */
    justify-content: center;  /* ให้ปุ่มอยู่ที่ขอบขวา */
}

.button-group {
  display: flex;
  gap: 40px; /* เพิ่มช่องว่างระหว่างปุ่ม */
  margin-top: 20px;
  justify-content: flex-end;
}


a.add-btn,
a.edit-btn,
a.delete-btn {
  display: inline-block;
  padding: 10px 20px;
  border-radius: 20px;
  color: white;
  text-decoration: none;
  cursor: pointer;
  white-space: nowrap; /* กันข้อความหด */
}

a.add-btn {
  background-color: #28a745;
}

a.edit-btn {
  background-color: #ffa600ff;
  color: black;
}

a.delete-btn {
  background-color: #ff4d4d;
}

  a.button:hover {
    opacity: 0.8;
  }

  img.room-img {
    width: 80px;
    border-radius: 8px;
  }
</style>
</head>
<body>

<div class="sidebar">
  <h2 class="text-center" style="color:white; padding-left:20px;">เมนู</h2>

  
  <a href="admin_review.php">อนุมัติการจอง</a>
  <a href="admin_manage_users.php">จัดการผู้ใช้งาน</a>
  <a href="admin_dashboard.php">รายงานสถิติการจอง</a>

  <a href="logout.php">ออกจากระบบ</a>
</div>


  <!-- เนื้อหาหลัก -->
  <div class="content">
    <h1>ระบบแอดมินจัดการห้องประชุม</h1>
    <p>สวัสดี <?= htmlspecialchars($_SESSION['username']) ?> </p>

    <a href="add_room.php" class="button add-btn">เพิ่มห้องประชุม</a>

    


    <table>
      <thead>
        <tr>
          <th>รหัสห้อง</th>
          <th>ชื่อห้อง</th>
          <th>รายละเอียด</th>
          <th>จำนวนที่นั่ง</th>
          <th>รูปภาพ</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while($room = $result->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($room['room_code']) ?></td>
              <td><?= htmlspecialchars($room['room_name']) ?></td>
              <td><?= htmlspecialchars($room['description']) ?></td>
              <td><?= htmlspecialchars($room['capacity']) ?></td>
              <td>
                <?php if (!empty($room['image']) && file_exists('uploads/' . $room['image'])): ?>
                  <img src="uploads/<?= htmlspecialchars($room['image']) ?>" alt="รูปห้อง" class="room-img">
                <?php else: ?>
                  ไม่มีรูปภาพ
                <?php endif; ?>
              </td>
              <td>
                <a href="edit_room.php?room_code=<?= urlencode($room['room_code']) ?>" class="button edit-btn">แก้ไข</a>
                <a href="delete_room.php?room_code=<?= urlencode($room['room_code']) ?>" class="button delete-btn" onclick="return confirm('คุณแน่ใจว่าต้องการลบห้องนี้หรือไม่?');">ลบ</a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" style="text-align:center;">ไม่มีข้อมูลห้องประชุม</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</body>
</html>
