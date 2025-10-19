<?php
// admin_all.php — แสดงทุกรายการแบบไม่ต้องค้นหา + อนุมัติ/ไม่อนุมัติ/รีเซ็ต + ปุ่มย้อนกลับ + โมดัลยืนยัน + โมดัลดูสลิป
require 'config.php';
session_start();

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$t); }

/* ---------- ตรวจสคีมาเบื้องต้น ---------- */
$hasPayments = $hotelConn->query("SHOW TABLES LIKE 'payments'")->num_rows > 0;
if(!$hasPayments){
    http_response_code(500);
    echo "<h3 style='font-family:sans-serif'>ไม่พบตาราง <code>payments</code> ในฐานข้อมูล</h3>";
    exit;
}
$pc = [];
$res = $hotelConn->query("SHOW COLUMNS FROM payments");
while($r=$res->fetch_assoc()){ $pc[$r['Field']]=true; }
$hasNote        = isset($pc['note']);
$hasVerifiedBy  = isset($pc['verified_by']);
$hasVerifiedAt  = isset($pc['verified_at']);

$hasBookingPayStatus = ($hotelConn->query("SHOW COLUMNS FROM bookings LIKE 'payment_status'")->num_rows > 0);

/* ---------- ดำเนินการ: อนุมัติ / ไม่อนุมัติ / รีเซ็ต ---------- */
$msg = null;
if($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['act']??'', ['approve','reject','reset'], true)){
    if(!csrf_ok($_POST['csrf']??'')){
        $msg = 'ไม่ผ่านการตรวจสอบความปลอดภัย (CSRF)';
    }else{
        $pid = (int)($_POST['payment_id']??0);
        $act = $_POST['act'];

        if($act === 'reset'){
            // รีเซ็ตเป็น pending และล้าง note/verified_at/verified_by (ถ้ามีคอลัมน์)
            $sets = "verified='pending'";
            if($hasNote){       $sets .= ", note=NULL"; }
            if($hasVerifiedBy){ $sets .= ", verified_by=NULL"; }
            if($hasVerifiedAt){ $sets .= ", verified_at=NULL"; }

            $ok1 = $hotelConn->query("UPDATE payments SET $sets WHERE id={$pid} LIMIT 1");

            // sync bookings.payment_status -> 'pending' (อิงสคีมาเดิม WHERE booking=?)
            $ok2 = true;
            if($ok1 && $hasBookingPayStatus){
                $st=$hotelConn->prepare("SELECT booking_id FROM payments WHERE id=? LIMIT 1");
                $st->bind_param('i',$pid); $st->execute();
                $bk = $st->get_result()->fetch_assoc(); $st->close();
                if($bk && isset($bk['booking_id'])){
                    $pending='pending';
                    $st=$hotelConn->prepare("UPDATE bookings SET payment_status=? WHERE booking=? LIMIT 1");
                    $st->bind_param('si', $pending, $bk['booking_id']);
                    $ok2 = $st->execute();
                    $st->close();
                }
            }
            $msg = ($ok1 && $ok2) ? 'รีเซ็ตข้อมูลเรียบร้อย' : 'รีเซ็ตไม่สำเร็จ';

        }else{
            // approve / reject
            $note = trim((string)($_POST['note']??''));
            $newStatus = $act==='approve' ? 'approved' : 'rejected';

            // อัปเดต payments
            $sets  = "verified=?";
            $types = 's';
            $vals  = [$newStatus];

            if($hasNote){        $sets.=", note=?";        $types.='s'; $vals[]=$note; }
            if($hasVerifiedBy){  $sets.=", verified_by=?"; $types.='i'; $vals[]=0; /* ไม่มีระบบผู้ใช้จริง ให้ 0 */ }
            if($hasVerifiedAt){  $sets.=", verified_at=NOW()"; }

            $sql = "UPDATE payments SET $sets WHERE id=? LIMIT 1";
            $types.='i'; $vals[]=$pid;

            $st = $hotelConn->prepare($sql);
            $st->bind_param($types, ...$vals);
            $ok1 = $st->execute();
            $st->close();

            // sync bookings.payment_status ถ้ามีคอลัมน์ (อิงสคีมาเดิม WHERE booking=?)
            $ok2 = true;
            if($ok1 && $hasBookingPayStatus){
                $st=$hotelConn->prepare("SELECT booking_id FROM payments WHERE id=? LIMIT 1");
                $st->bind_param('i',$pid); $st->execute();
                $bk = $st->get_result()->fetch_assoc(); $st->close();
                if($bk && isset($bk['booking_id'])){
                    $st=$hotelConn->prepare("UPDATE bookings SET payment_status=? WHERE booking=? LIMIT 1");
                    $st->bind_param('si', $newStatus, $bk['booking_id']);
                    $ok2 = $st->execute();
                    $st->close();
                }
            }
            $msg = ($ok1 && $ok2) ? 'อัปเดตสถานะเรียบร้อย' : 'ไม่สามารถอัปเดตได้';
        }
    }
}

/* ---------- ดึง "ทุกรายการ" (เรียง pending ก่อน แล้วตามเวลาล่าสุด) ---------- */
/* หมายเหตุ: ถ้าข้อมูลเยอะมาก สามารถเพิ่ม LIMIT ได้เองภายหลัง เช่น LIMIT 500 */
$sql="SELECT p.id AS payment_id, p.booking_id, p.verified, p.uploaded_at, p.slip_path,
             b.user_id, b.booking_date, b.start_time, b.end_time, b.room_code,
             mr.room_name, mr.price_per_hour
      FROM payments p
      JOIN bookings b ON p.booking_id=b.booking
      LEFT JOIN meeting_rooms mr ON b.room_code=mr.room_code
      ORDER BY FIELD(p.verified,'pending','rejected','approved'), p.uploaded_at DESC";
$rs = $hotelConn->query($sql);

$rows=[]; $uidsOnPage=[];
while($it=$rs->fetch_assoc()){
    $rows[] = $it;
    if(isset($it['user_id'])) $uidsOnPage[(int)$it['user_id']] = true;
}

/* ---------- เติมอีเมลผู้จองจาก DB users ---------- */
$emailsByUid=[];
if($uidsOnPage){
    $uids = array_keys($uidsOnPage);
    $in = implode(',', array_fill(0,count($uids), '?'));
    $tp = str_repeat('i', count($uids));
    $st = $userConn->prepare("SELECT id, email FROM users WHERE id IN ($in)");
    $st->bind_param($tp, ...$uids);
    $st->execute();
    $ru = $st->get_result();
    while($u=$ru->fetch_assoc()){
        $emailsByUid[(int)$u['id']] = (string)$u['email'];
    }
    $st->close();
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>อนุมัติสลิปการจอง (ทั้งหมด)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f7f7fb}
    .card{max-width:1200px;margin:32px auto;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.06)}
    .thumb{max-height:120px;border:1px solid #eee;border-radius:8px;cursor:pointer}
    .nowrap{white-space:nowrap}
    .w-actions{width:380px}
  </style>
</head>
<body>
<div class="card p-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h3 class="mb-0">อนุมัติสลิปการจอง (ทั้งหมด)</h3>
    <!-- ปุ่มย้อนกลับ -->
    <button type="button" class="btn btn-secondary btn-sm" onclick="history.back()">ย้อนกลับ</button>
  </div>

  <?php if(!empty($msg)): ?>
    <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <?php if(empty($rows)): ?>
    <div class="text-muted">ยังไม่มีรายการ</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
        <tr>
          <th>ข้อมูลผู้จอง</th>
          <th>ห้องที่จอง</th>
          <th>ข้อมูลในการจอง</th>
          <th>สลิปที่แนบ</th>
          <th>สถานะ</th>
          <th class="w-actions">ดำเนินการ</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $it):
          $email = $emailsByUid[(int)($it['user_id']??0)] ?? '-';
          $stime = substr((string)($it['start_time']??''),0,5);
          $etime = substr((string)($it['end_time']??''),0,5);
          $badge='secondary';
          if(($it['verified']??'')==='pending') $badge='warning';
          elseif(($it['verified']??'')==='approved') $badge='success';
          elseif(($it['verified']??'')==='rejected') $badge='danger';
        ?>
          <tr>
            <!-- ผู้จอง -->
            <td>
              <div><?= htmlspecialchars($email) ?></div>
              <small class="text-muted">Booking #<?= (int)$it['booking_id'] ?></small>
            </td>

            <!-- ห้องที่จอง -->
            <td>
              <div><?= htmlspecialchars((string)($it['room_name']??'-')) ?></div>
              <small class="text-muted">รหัสห้อง: <?= htmlspecialchars((string)($it['room_code']??'-')) ?></small>
            </td>

            <!-- ข้อมูลการจอง -->
            <td>
              <div class="nowrap"><strong>วันที่:</strong> <?= htmlspecialchars((string)($it['booking_date']??'')) ?></div>
              <div class="nowrap"><strong>เวลา:</strong> <?= htmlspecialchars($stime) ?>–<?= htmlspecialchars($etime) ?></div>
              <?php if(isset($it['price_per_hour'])): ?>
                <div class="text-muted small">ราคา/ชม.: <?= number_format((float)$it['price_per_hour'],2) ?> บาท</div>
              <?php endif; ?>
            </td>

            <!-- สลิปที่แนบ -->
            <td>
              <?php if(!empty($it['slip_path'])): ?>
                <img src="<?= htmlspecialchars((string)$it['slip_path']) ?>"
                     class="thumb"
                     alt="slip"
                     onclick="showSlip('<?= htmlspecialchars((string)$it['slip_path']) ?>')">
                <div><small class="text-muted"><?= htmlspecialchars((string)$it['uploaded_at']??'') ?></small></div>
              <?php else: ?>
                <span class="text-muted">ไม่มีไฟล์</span>
              <?php endif; ?>
            </td>

            <!-- สถานะ -->
            <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars((string)$it['verified']) ?></span></td>

            <!-- ปุ่มอนุมัติ/ไม่อนุมัติ/รีเซ็ต + ล้างช่องหมายเหตุ -->
            <td>
              <form method="post" class="d-flex flex-wrap gap-2 action-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <input type="hidden" name="payment_id" value="<?= (int)$it['payment_id'] ?>">

                <div class="input-group input-group-sm" style="min-width:220px;max-width:280px">
                  <input type="text" name="note" class="form-control form-control-sm" placeholder="หมายเหตุ (ถ้ามี)">
                  <button type="button" class="btn btn-light" onclick="clearRow(this)">ล้าง</button>
                </div>

                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-sm btn-success" onclick="openConfirm(this.form,'approve')">อนุมัติ</button>
                  <button type="button" class="btn btn-sm btn-outline-danger" onclick="openConfirm(this.form,'reject')">ไม่อนุมัติ</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="confirmReset(this.form)" title="ตั้งค่าสถานะกลับเป็น pending และล้างหมายเหตุ">รีเซ็ต</button>
                </div>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Modal: ยืนยันอนุมัติ/ไม่อนุมัติ -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmTitle">ยืนยันการทำรายการ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body">
        <div id="confirmText" class="mb-2"></div>
        <div class="small text-muted" id="confirmNote"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="button" class="btn btn-primary" id="confirmDoBtn">ยืนยัน</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: แสดงสลิป -->
<div class="modal fade" id="slipModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">สลิปการชำระเงิน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body text-center">
  <img id="slipModalImg" src="" class="rounded"
       alt="slip"
       style="max-width:80%; max-height:80vh; object-fit:contain;">
</div>

      <div class="modal-footer">
        <a id="slipDownload" href="#" class="btn btn-outline-primary" download>ดาวน์โหลด</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ออก</button>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap Bundle (รวม Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let _confirmForm = null;
let _confirmAct  = null;

// เปิดโมดัลดูสลิป
function showSlip(path){
  document.getElementById('slipModalImg').src = path;
  document.getElementById('slipDownload').href = path;
  const modal = new bootstrap.Modal(document.getElementById('slipModal'));
  modal.show();
}

// เปิดโมดัลยืนยันสำหรับอนุมัติ/ไม่อนุมัติ
function openConfirm(form, act){
  _confirmForm = form;
  _confirmAct  = act;

  const booking = form.closest('tr')?.querySelector('small.text-muted')?.textContent?.trim() || '';
  const note    = (form.querySelector('input[name="note"]')?.value || '').trim();

  const title = act === 'approve' ? 'ยืนยันอนุมัติรายการ' : 'ยืนยันไม่อนุมัติรายการ';
  const body  = booking ? booking : 'โปรดยืนยันการทำรายการ';

  document.getElementById('confirmTitle').textContent = title;
  document.getElementById('confirmText').textContent  = body;
  document.getElementById('confirmNote').textContent  = note ? 'หมายเหตุ: ' + note : '';

  const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
  modal.show();

  // ผูกปุ่มยืนยัน (rebind ทุกครั้งให้แน่ใจว่าทำงานกับฟอร์มปัจจุบัน)
  const btn = document.getElementById('confirmDoBtn');
  btn.onclick = function(){
    if(!_confirmForm || !_confirmAct) return;
    // ใส่ hidden input act เพื่อ submit
    const hid = document.createElement('input');
    hid.type  = 'hidden';
    hid.name  = 'act';
    hid.value = _confirmAct;
    _confirmForm.appendChild(hid);
    _confirmForm.submit();
  };
}

// รีเซ็ตด้วย confirm ธรรมดา
function confirmReset(form){
  const booking = form.closest('tr')?.querySelector('small.text-muted')?.textContent?.trim() || '';
  const ok = confirm('ยืนยันรีเซ็ตสถานะเป็น pending และล้างหมายเหตุ?\n' + (booking || ''));
  if(!ok) return;
  const hid = document.createElement('input');
  hid.type  = 'hidden';
  hid.name  = 'act';
  hid.value = 'reset';
  form.appendChild(hid);
  form.submit();
}

// ล้างเฉพาะช่องหมายเหตุของแถว
function clearRow(btn){
  const form = btn.closest('form');
  if(!form) return;
  const note = form.querySelector('input[name="note"]');
  if(note){ note.value = ''; note.focus(); }
}
</script>
</body>
</html>
