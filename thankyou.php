<?php
require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit();
}

// รับข้อมูล POST
$room_code = $_POST['room_code'] ?? '';
$date = $_POST['date'] ?? '';
$duration = $_POST['duration'] ?? '';
$price = $_POST['price'] ?? '';
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$payment_method = $_POST['payment_method'] ?? '';

// ตรวจสอบไฟล์สลิป
if (isset($_FILES['slip']) && $_FILES['slip']['error'] == 0) {
    $slip_tmp = $_FILES['slip']['tmp_name'];
    $slip_name = basename($_FILES['slip']['name']);
    $upload_dir = 'uploads/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $new_filename = uniqid() . '-' . $slip_name;
    $target_file = $upload_dir . $new_filename;

    if (!move_uploaded_file($slip_tmp, $target_file)) {
        die("อัพโหลดสลิปไม่สำเร็จ");
    }
} else {
    die("กรุณาแนบสลิปการโอนเงิน");
}

// ตรวจสอบข้อมูลครบถ้วน
if (!$room_code || !$date || !$duration || !$price || !$fullname || !$email || !$phone || !$payment_method) {
    die("ข้อมูลไม่ครบถ้วน");
}

// บันทึกข้อมูลลงฐานข้อมูล (ตัวอย่าง)
$stmt = $hotelConn->prepare("INSERT INTO payments (room_code, date, duration, price, fullname, email, phone, payment_method, slip_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
$stmt->bind_param("sssisssss", $room_code, $date, $duration, $price, $fullname, $email, $phone, $payment_method, $target_file);

if ($stmt->execute()) {
    echo "ส่งข้อมูลชำระเงินเรียบร้อย รอการอนุมัติจากแอดมิน";
} else {
    echo "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt->error;
}
?>

    <!DOCTYPE html>
    <html lang="th">
    <head>
      <meta charset="UTF-8" />
      <title>ขอบคุณสำหรับการชำระเงิน</title>
      <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet" />
      <style>
        body {
          font-family: 'Sarabun', sans-serif;
          background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
          color: #fff;
          padding: 30px;
          max-width: 600px;
          margin: auto;
          text-align: center;
        }
        .thankyou {
          background: rgba(255, 255, 255, 0.1);
          padding: 30px;
          border-radius: 20px;
          box-shadow: 0 8px 25px rgba(0,0,0,0.5);
        }
        h1 {
          font-weight: 600;
          font-size: 2rem;
          margin-bottom: 20px;
        }
      </style>
    </head>
    <body>
      <div class="thankyou">
        <h1>ขอบคุณสำหรับการชำระเงิน</h1>
        <p>ทางโรงแรมจะทำการตรวจสอบข้อมูลและอนุมัติการจองของคุณโดยเร็วที่สุด</p>
        <a href="index.php" style="color:#00cc99; text-decoration:none; font-weight:600;">กลับสู่หน้าหลัก</a>
      </div>
    </body>
    </html>
    <?php


?>
