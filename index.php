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
    $stmtHotel = $pdo->query("SELECT * FROM hotel_info ");
    $hotel = $stmtHotel->fetch(PDO::FETCH_ASSOC);

    // ดึงห้องประชุมมาโชว์ 3 ห้อง
    $stmtRooms = $pdo->query("SELECT * FROM meeting_rooms");
    $rooms = $stmtRooms->fetchAll(PDO::FETCH_ASSOC);

    // ดึงไฟล์ภาพจากโฟลเดอร์ uploads สำหรับสไลด์โชว์ background
    $uploadDir = __DIR__ . '/uploads/';
    $images = [];
    if (is_dir($uploadDir)) {
        $files = scandir($uploadDir);
        foreach ($files as $file) {
            if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
                $images[] = 'uploads/' . $file;
            }
        }
    }
    // ถ้าไม่มีภาพในโฟลเดอร์ uploads ให้ใช้ภาพ default
    if (count($images) === 0) {
        $images[] = 'banner.jpg';
    }
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
    position: relative;
    height: 100vh;
    color: white;
    overflow: hidden;
  }
  .hero img {
    position: absolute;
    width: 100%;
    height: 100%;
    object-fit: cover;
    top: 0; left: 0;
    opacity: 0;
    transition: opacity 1s ease-in-out;
    z-index: 0;
  }
  .hero img.active {
    opacity: 1;
  }
  .hero .overlay {
    position: absolute;
    z-index: 10;
    height: 100%;
    width: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    text-align: center;
    padding: 0 20px;
  }
  .hero .overlay a.btn {
    transition: transform 0.3s ease, background-color 0.3s ease;
    cursor: pointer;
  }
  .hero .overlay a.btn:hover,
  .hero .overlay a.btn:focus {
    transform: translateY(-5px);
    background-color: #ffc107;
    box-shadow: 0 8px 15px rgba(0,0,0,0.3);
  }
  </style>
</head>
<body>

<!-- HERO SECTION -->
<div class="hero">
  <!-- วนลูปแสดงภาพจาก uploads -->
  <?php foreach ($images as $index => $imgPath): ?>
    <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="Background Image <?php echo $index + 1; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>">
  <?php endforeach; ?>

  <div class="overlay">
    <h1 class="display-4"><?php echo htmlspecialchars($hotel['name']); ?></h1>
    <p class="lead mb-4"><?php echo htmlspecialchars($hotel['description']); ?></p>
    <a href="login.php" class="btn btn-lg btn-warning">จองห้องประชุมตอนนี้</a>
  </div>
</div>

<script>
  const slides = document.querySelectorAll('.hero img');
  let current = 0;

  function nextSlide() {
    slides[current].classList.remove('active');
    current = (current + 1) % slides.length;
    slides[current].classList.add('active');
  }

  setInterval(nextSlide, 4000); // เปลี่ยนภาพทุก 4 วินาที
</script>

<!-- CONTACT / FOOTER -->
<footer class="bg-dark text-white text-center py-4">
  <p class="mb-1"><?php echo htmlspecialchars($hotel['address']); ?> | โทร: <?php echo htmlspecialchars($hotel['phone']); ?></p>
  <p class="mb-0">© <?php echo date('Y'); ?> <?php echo htmlspecialchars($hotel['name']); ?>. All rights reserved.</p>
</footer>

</body>
</html>
