<?php
// เชื่อมต่อฐานข้อมูล
$host = "localhost";
$dbname = "hotel_system";
$username = "root"; // ปรับตามเครื่อง
$password = "";     // ปรับตามเครื่อง

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ดึงข้อมูลโรงแรม
    $stmtHotel = $pdo->query("SELECT * FROM hotel_info LIMIT 1");
    $hotel = $stmtHotel->fetch(PDO::FETCH_ASSOC);

    // ดึงห้องประชุมมาโชว์ 3 ห้อง
    $stmtRooms = $pdo->query("SELECT * FROM meeting_rooms LIMIT 3");
    $rooms = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($hotel['name']); ?> - หน้าแรก</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .hero {
      background-image: url('banner.jpg'); /* 🔁 เปลี่ยนเป็นรูปภาพโรงแรมของคุณ */
      background-size: cover;
      background-position: center;
      height: 100vh;
      position: relative;
      color: white;
    }
    .hero .overlay {
      background: rgba(0, 0, 0, 0.5);
      height: 100%;
      width: 100%;
      position: absolute;
      top: 0; left: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      text-align: center;
      padding: 0 20px;
    }
  </style>
</head>
<body>

<!-- HERO SECTION -->
<div class="hero">
  <div class="overlay">
    <h1 class="display-4"><?php echo htmlspecialchars($hotel['name']); ?></h1>
    <p class="lead mb-4"><?php echo htmlspecialchars($hotel['description']); ?></p>
    <a href="index.php" class="btn btn-lg btn-warning">จองห้องประชุมตอนนี้</a>
  </div>
</div>

<!-- ROOM HIGHLIGHT -->
<section class="container py-5">
  <h2 class="mb-4 text-center">ห้องประชุมแนะนำ</h2>
  <div class="row">
    <?php foreach ($rooms as $room): ?>
      <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body">
            <h5 class="card-title"><?php echo htmlspecialchars($room['room_name']); ?></h5>
            <p class="card-text">ความจุ: <?php echo $room['capacity']; ?> คน</p>
            <p class="card-text">อุปกรณ์: <?php echo htmlspecialchars($room['equipment']); ?></p>
            <a href="booking.php?room_id=<?php echo $room['id']; ?>" class="btn btn-primary">ดูรายละเอียด</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- CONTACT / FOOTER -->
<footer class="bg-dark text-white text-center py-4">
  <p class="mb-1"><?php echo htmlspecialchars($hotel['address']); ?> | โทร: <?php echo htmlspecialchars($hotel['phone']); ?></p>
  <p class="mb-0">© <?php echo date('Y'); ?> <?php echo htmlspecialchars($hotel['name']); ?>. All rights reserved.</p>
</footer>

</body>
</html>
