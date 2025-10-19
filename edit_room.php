<?php
session_start();
require 'config.php';

// ตรวจสอบสิทธิ์ admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$room_code = $_GET['room_code'] ?? '';
if (!$room_code) {
    header("Location: admin.php");
    exit;
}

$error = '';
$success = '';

// ดึงข้อมูลห้องประชุม
$stmt = $hotelConn->prepare("SELECT * FROM meeting_rooms WHERE room_code = ?");
$stmt->bind_param("s", $room_code);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();
$stmt->close();

if (!$room) {
    die("ไม่พบห้องประชุมนี้");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_name = $_POST['room_name'];
    $description = $_POST['description'];
    $capacity = (int)$_POST['capacity'];
    $room_size = $_POST['room_size'];
    $tools = $_POST['tools'];
    $room_status = $_POST['room_status'];
    $price_per_hour = (float)$_POST['price_per_hour'];

    $image = $room['image'];  // รูปเดิม

    // อัพโหลดรูปใหม่ถ้ามี
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $newImage = basename($_FILES['image']['name']);
        $targetFile = $uploadDir . $newImage;

        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($newImage, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $error = "รองรับเฉพาะไฟล์ภาพนามสกุล jpg, jpeg, png, gif เท่านั้น";
        } else {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                // ลบไฟล์เก่า
                if (!empty($room['image']) && file_exists($uploadDir . $room['image'])) {
                    unlink($uploadDir . $room['image']);
                }
                $image = $newImage;
            } else {
                $error = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
            }
        }
    }

    if (!$error) {
        $stmt = $hotelConn->prepare("
            UPDATE meeting_rooms 
            SET room_name = ?, description = ?, capacity = ?, image = ?, room_size = ?, tools = ?, room_status = ?, price_per_hour = ?
            WHERE room_code = ?
        ");
        $stmt->bind_param("ssissssds", $room_name, $description, $capacity, $image, $room_size, $tools, $room_status, $price_per_hour, $room_code);

        if ($stmt->execute()) {
            $success = "แก้ไขข้อมูลเรียบร้อยแล้ว";
            // อัปเดตข้อมูลห้องใหม่เพื่อแสดงผลในฟอร์ม
            $stmt->close();
            $stmt = $hotelConn->prepare("SELECT * FROM meeting_rooms WHERE room_code = ?");
            $stmt->bind_param("s", $room_code);
            $stmt->execute();
            $result = $stmt->get_result();
            $room = $result->fetch_assoc();
            $stmt->close();
        } else {
            $error = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>แก้ไขห้องประชุม</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
    body {
        background-color: #f8f9fa;
        padding: 20px;
        font-family: 'Sarabun', sans-serif;
    }
    .container {
        max-width: 700px;
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    h1 {
        margin-bottom: 25px;
        color: #0d6efd;
        text-align: center;
    }
    img.room-img {
        max-width: 150px;
        border-radius: 8px;
        margin-top: 10px;
        display: block;
        margin-bottom: 15px;
    }
</style>
</head>
<body>

<div class="container">
    <h1>แก้ไขห้องประชุม (<?= htmlspecialchars($room['room_code']) ?>)</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
        <div class="mb-3">
            <label for="room_name" class="form-label">ชื่อห้องประชุม</label>
            <input type="text" class="form-control" id="room_name" name="room_name" required value="<?= htmlspecialchars($room['room_name']) ?>">
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">รายละเอียด</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($room['description']) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="capacity" class="form-label">จำนวนที่นั่ง</label>
            <input type="number" class="form-control" id="capacity" name="capacity" min="1" required value="<?= htmlspecialchars($room['capacity']) ?>">
        </div>

        <div class="mb-3">
            <label for="room_size" class="form-label">ขนาดห้อง</label>
            <input type="text" class="form-control" id="room_size" name="room_size" required value="<?= htmlspecialchars($room['room_size']) ?>">
        </div>

        <div class="mb-3">
            <label for="tools" class="form-label">อุปกรณ์</label>
            <textarea class="form-control" id="tools" name="tools" rows="2" required><?= htmlspecialchars($room['tools']) ?></textarea>
        </div>

        <div class="mb-3">
            <label for="room_status" class="form-label">สถานะห้อง</label>
            <select class="form-select" id="room_status" name="room_status" required>
                <option value="available" <?= $room['room_status'] === 'available' ? 'selected' : '' ?>>ว่าง</option>
                <option value="unavailable" <?= $room['room_status'] === 'unavailable' ? 'selected' : '' ?>>ไม่ว่าง</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="price_per_hour" class="form-label">ราคาต่อชั่วโมง (บาท)</label>
            <input type="number" step="0.01" min="0" class="form-control" id="price_per_hour" name="price_per_hour" required value="<?= htmlspecialchars($room['price_per_hour']) ?>">
        </div>

        <div class="mb-3">
            <label for="image" class="form-label">รูปภาพ (ถ้าต้องการเปลี่ยน)</label>
            <input class="form-control" type="file" id="image" name="image" accept="image/*">
            <?php if (!empty($room['image']) && file_exists('uploads/' . $room['image'])): ?>
                <img src="<?= 'uploads/' . htmlspecialchars($room['image']) ?>" alt="รูปภาพห้องประชุม" class="room-img">
            <?php else: ?>
                <p>ไม่มีรูปภาพ</p>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary w-100">บันทึกการแก้ไข</button>
    </form>

    <a href="admin.php" class="btn btn-link mt-3 d-block text-center">กลับไปหน้าแอดมิน</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
