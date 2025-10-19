<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$host = "localhost";
$dbname = "hotel_db";
$username = "root";
$password = "";

/* ---------- helper: ทำเวลาให้เป็น HH:MM:SS ---------- */
function toHms($t){
  $t = trim($t);
  if (preg_match('/^\d{2}:\d{2}:\d{2}$/',$t)) return $t;
  if (preg_match('/^\d{2}:\d{2}$/',$t))     return $t.':00';
  $ts = strtotime($t);
  return $ts ? date('H:i:s',$ts) : '';
}

/* ---------- helper: คำนวณยอดรวมจาก DB ---------- */
function calc_total(PDO $pdo, int $roomIdOrCode, string $date, string $start, string $end){
  // ดึงราคา/ชม. จาก meeting_rooms (รองรับทั้ง room_code และ id)
  $q = $pdo->prepare("SELECT room_code, price_per_hour FROM meeting_rooms WHERE room_code=:id OR id=:id LIMIT 1");
  $q->execute([':id'=>$roomIdOrCode]);
  $r = $q->fetch(PDO::FETCH_ASSOC);
  if (!$r) throw new Exception('ไม่พบข้อมูลห้องประชุม');

  $start = toHms($start);
  $end   = toHms($end);
  if (!$start || !$end) throw new Exception('รูปแบบเวลาไม่ถูกต้อง');

  $dt1 = new DateTime("1970-01-01 $start");
  $dt2 = new DateTime("1970-01-01 $end");
  if ($dt2 <= $dt1) throw new Exception('เวลาเริ่มต้องน้อยกว่าเวลาสิ้นสุด');

  $hours = ($dt2->getTimestamp() - $dt1->getTimestamp())/3600;
  $total = $hours * (float)$r['price_per_hour'];

  return [$r['room_code'], $hours, round($total,2)];
}

try{
  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4",$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

  $room_id      = (int)($_POST['room_id'] ?? 0);
  $booking_date = trim($_POST['booking_date'] ?? '');
  $start_time   = trim($_POST['start_time'] ?? '');
  $end_time     = trim($_POST['end_time'] ?? '');

  if ($room_id<=0 || !$booking_date || !$start_time || !$end_time) throw new Exception('ข้อมูลไม่ครบ');

  // กันช่วงเวลาทับกัน (hotel_db ไม่มี status)
  $dup = $pdo->prepare("
    SELECT COUNT(*) FROM bookings
    WHERE room_code IN (SELECT room_code FROM meeting_rooms WHERE room_code=:id OR id=:id)
      AND booking_date=:d
      AND NOT (:end <= start_time OR :start >= end_time)
  ");
  $dup->execute([':id'=>$room_id, ':d'=>$booking_date, ':start'=>toHms($start_time), ':end'=>toHms($end_time)]);
  if ($dup->fetchColumn()>0) throw new Exception('ช่วงเวลานี้ถูกจองแล้ว');

  // คำนวณยอดจริงจาก DB
  [$room_code, $hours, $total] = calc_total($pdo, $room_id, $booking_date, $start_time, $end_time);

  // ใส่ total_price ถ้ามีคอลัมน์นี้
  $hasTotal = (bool)$pdo->query("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=".$pdo->quote($dbname)." AND TABLE_NAME='bookings' AND COLUMN_NAME='total_price'
  ")->fetchColumn();

  if ($hasTotal){
    $ins = $pdo->prepare("INSERT INTO bookings(user_id,room_code,booking_date,start_time,end_time,total_price)
                          VALUES(:u,:rc,:d,:s,:e,:t)");
    $ok = $ins->execute([
      ':u'=>(int)$_SESSION['user_id'], ':rc'=>$room_code, ':d'=>$booking_date,
      ':s'=>toHms($start_time), ':e'=>toHms($end_time), ':t'=>$total
    ]);
  }else{
    $ins = $pdo->prepare("INSERT INTO bookings(user_id,room_code,booking_date,start_time,end_time)
                          VALUES(:u,:rc,:d,:s,:e)");
    $ok = $ins->execute([
      ':u'=>(int)$_SESSION['user_id'], ':rc'=>$room_code, ':d'=>$booking_date,
      ':s'=>toHms($start_time), ':e'=>toHms($end_time)
    ]);
  }
  if(!$ok) throw new Exception('บันทึกการจองไม่สำเร็จ');

  $bookingId = (int)$pdo->lastInsertId();
  if(!$bookingId){
    $q=$pdo->prepare("SELECT booking FROM bookings WHERE user_id=:u AND room_code=:rc AND booking_date=:d AND start_time=:s AND end_time=:e ORDER BY booking DESC LIMIT 1");
    $q->execute([':u'=>(int)$_SESSION['user_id'],':rc'=>$room_code,':d'=>$booking_date,':s'=>toHms($start_time),':e'=>toHms($end_time)]);
    $row=$q->fetch(PDO::FETCH_ASSOC); $bookingId = (int)($row['booking']??0);
  }

  // ไปหน้า QR (จะคำนวณยอดใหม่จาก DB อีกครั้งให้ตรงกัน)
  header('Location: pay_qr.php?booking_id='.$bookingId);
  exit;

}catch(Exception $e){
  http_response_code(400);
  echo '<p style="font-family:system-ui">เกิดข้อผิดพลาด: '.htmlspecialchars($e->getMessage()).'</p>';
  echo '<p><a href="javascript:history.back()">« กลับ</a></p>';
  exit;
}
