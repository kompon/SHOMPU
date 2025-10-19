<?php
session_start();
require 'config.php';

// ตรวจสอบสิทธิ์ admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$room_code = $_GET['room_code'] ?? '';

if (!$room_code) {
    header("Location: admin.php");
    exit;
}

// ดึงชื่อไฟล์รูปภาพเพื่อลบออกด้วย
$stmt = $hotelConn->prepare("SELECT image FROM meeting_rooms WHERE room_code = ?");
$stmt->bind_param("s", $room_code);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row) {
    // ลบไฟล์รูปภาพถ้ามี
    $uploadDir = 'uploads/';
    if (!empty($row['image']) && file_exists($uploadDir . $row['image'])) {
        unlink($uploadDir . $row['image']);
    }

    // ลบข้อมูลห้องประชุม
    $stmt = $hotelConn->prepare("DELETE FROM meeting_rooms WHERE room_code = ?");
    $stmt->bind_param("s", $room_code);
    $stmt->execute();
    $stmt->close();
}

header("Location: admin.php");
exit;
