<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$host="localhost"; $dbname="hotel_db"; $username="root"; $password="";
// ★ เปลี่ยนเป็นพร้อมเพย์ของจริง
$PROMPTPAY_ID='0963729471';

function toHms($t){ $t=trim($t);
  if(preg_match('/^\d{2}:\d{2}:\d{2}$/',$t))return $t;
  if(preg_match('/^\d{2}:\d{2}$/',$t))return $t.':00';
  $ts=strtotime($t); return $ts?date('H:i:s',$ts):'';
}
function calc_total(PDO $pdo, array $bk){
  $st = toHms($bk['start_time']); $en = toHms($bk['end_time']);
  $dt1=new DateTime("1970-01-01 $st"); $dt2=new DateTime("1970-01-01 $en");
  $hours=($dt2->getTimestamp()-$dt1->getTimestamp())/3600;
  $price=(float)$bk['price_per_hour'];
  return [ $hours, round($hours*$price,2) ];
}
function tlv($id,$value){ $len=str_pad(strlen($value),2,'0',STR_PAD_LEFT); return $id.$len.$value; }
function fmtPP($pp){ $pp=preg_replace('/\D/','',$pp); return (strpos($pp,'0')===0)?'0066'.substr($pp,1):$pp; }
function crc16($s){ $p=0x1021;$crc=0xFFFF;for($i=0;$i<strlen($s);$i++){ $crc^=(ord($s[$i])<<8);for($j=0;$j<8;$j++){ $crc=($crc&0x8000)?(($crc<<1)^$p):($crc<<1);$crc&=0xFFFF;}} return strtoupper(str_pad(dechex($crc),4,'0',STR_PAD_LEFT)); }

try{
  $pdo=new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4",$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

  $bid=(int)($_GET['booking_id']??0); if($bid<=0) throw new Exception('ไม่พบเลขที่การจอง');

  // ดึงข้อมูลการจอง + ห้อง
  $sql="SELECT b.booking,b.user_id,b.room_code,b.booking_date,b.start_time,b.end_time,
               mr.room_name,mr.price_per_hour
        FROM bookings b JOIN meeting_rooms mr ON b.room_code=mr.room_code
        WHERE b.booking=:bid AND b.user_id=:uid LIMIT 1";
  $st=$pdo->prepare($sql);
  $st->execute([':bid'=>$bid, ':uid'=>(int)$_SESSION['user_id']]);
  $bk=$st->fetch(PDO::FETCH_ASSOC);
  if(!$bk) throw new Exception('ไม่พบข้อมูลการจองของคุณ');

  // คำนวณยอดจาก DB (ตรงกับที่หน้า payment แสดง)
  [$hours, $total] = calc_total($pdo, $bk);

  // ทำ PromptPay Payload (amount = $total)
  $mai = tlv('00','A000000677010111') . tlv('01', fmtPP($PROMPTPAY_ID));
  $payload = tlv('00','01') . tlv('01','12') . tlv('29',$mai)
           . tlv('53','764') . tlv('54', number_format($total,2,'.',''))
           . tlv('58','TH') . tlv('59','KhonKaenHotel') . tlv('60','KhonKaen');
  $payloadForCRC = $payload.'6304';
  $crc = crc16($payloadForCRC);
  $pp = $payloadForCRC.$crc;

}catch(Exception $e){
  http_response_code(400);
  echo '<p style="font-family:system-ui">เกิดข้อผิดพลาด: '.htmlspecialchars($e->getMessage()).'</p>';
  echo '<p><a href="index.php">« กลับหน้าหลัก</a></p>'; exit;
}
?>
<!doctype html><html lang="th"><head>
<meta charset="utf-8"><title>QR ชำระเงิน - การจอง #<?=htmlspecialchars($bk['booking'])?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>.card{max-width:720px;margin:24px auto}.qr{width:220px;height:220px;margin:auto}.muted{color:#666}</style>
</head><body class="bg-light">
<div class="card card-body">
  <h3 class="mb-3">ชำระเงินการจองห้องประชุม</h3>
  <p><strong>เลขที่การจอง:</strong> <?=$bk['booking']?></p>
  <p><strong>ห้อง:</strong> <?=htmlspecialchars($bk['room_name'])?></p>
  <p><strong>วันที่:</strong> <?=htmlspecialchars($bk['booking_date'])?> 
     <strong>เวลา:</strong> <?=htmlspecialchars(substr($bk['start_time'],0,5))?>–<?=htmlspecialchars(substr($bk['end_time'],0,5))?>
     (<?=number_format($hours,2)?> ชม.)
  </p>
  <p><strong>ราคา/ชั่วโมง:</strong> <?=number_format((float)$bk['price_per_hour'],2)?> บาท</p>
  <hr>
  <h4>รวมเป็นเงิน: <?=number_format($total,2)?> บาท</h4>

  <div class="my-3 text-center">
    <div id="qrcode" class="qr"></div>
    <div class="muted mt-2">สแกนด้วยแอปธนาคารเพื่อชำระเงิน (PromptPay)</div>
  </div>

  <div class="mt-3">
    <a class="btn btn-outline-secondary" href="hisroom_upload.php">แนบสลิปหลังชำระเงิน</a>
    <a class="btn btn-outline-primary ms-2" href="check_status.php">ตรวจสอบสถานะ</a>
    <a class="btn btn-outline-dark ms-2" href="index.php">กลับหน้าหลัก</a>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
        onerror="document.getElementById('qrcode').innerHTML=
        '<img alt=QR class=qr src=&quot;https://api.qrserver.com/v1/create-qr-code/?size=220x220&amp;data=<?=urlencode($pp)?>&quot;>'; ">
</script>
<script>
if (window.QRCode) new QRCode(document.getElementById("qrcode"), {
  text: "<?=htmlspecialchars($pp, ENT_QUOTES)?>", width:220, height:220, correctLevel:QRCode.CorrectLevel.M
});
</script>
</body></html>
