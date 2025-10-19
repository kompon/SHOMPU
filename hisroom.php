<?php
// my_bookings_history_mysqli.php — สำหรับสคีมา: bookings(room_code, booking[PK], user_id, booking_date, start_time, end_time), meeting_rooms(room_code, room_name, price_per_hour), payments(booking_id, verified, uploaded_at)
// ใช้การเชื่อมต่อแบบ mysqli ตามรูปแบบใน check_status.php (config.php ควรมี $hotelConn และ session เรียบร้อย)

require 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$uid = (int)$_SESSION['user_id'];

// ---------------- Params & helpers ----------------
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$fromDate = trim((string)($_GET['from'] ?? ''));
$toDate   = trim((string)($_GET['to'] ?? ''));
$view     = $_GET['view'] ?? 'all';
if (!in_array($view, ['all','past','upcoming'], true)) $view = 'all';

// ถ้า booking_date เป็น DATETIME ให้เทียบด้วย DATE()
$colDate = 'DATE(b.booking_date)';
$today   = date('Y-m-d');

$where = ['b.user_id = ?'];
$params = [$uid];
$types  = 'i';

if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
  $where[] = "$colDate >= ?";
  $params[] = $fromDate;
  $types   .= 's';
}
if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
  $where[] = "$colDate <= ?";
  $params[] = $toDate;
  $types   .= 's';
}
if ($view === 'past') {
  // รวมวันนี้อยู่ฝั่งอดีตด้วย
  $where[] = "$colDate <= ?";
  $params[] = $today;
  $types   .= 's';
} elseif ($view === 'upcoming') {
  $where[] = "$colDate > ?";
  $params[] = $today;
  $types   .= 's';
}

$whereSql = implode(' AND ', $where);

// ---------------- Count ----------------
$countSql = "SELECT COUNT(*) AS c
             FROM bookings b
             LEFT JOIN meeting_rooms mr ON b.room_code = mr.room_code
             WHERE $whereSql";

$stmt = $hotelConn->prepare($countSql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$cntRes = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total = (int)($cntRes['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// ---------------- Query list (with latest payment) ----------------
// ดึง verified ล่าสุดของแต่ละ booking ด้วยซับคิวรีย์
$sql = "SELECT
          b.booking AS id,
          b.booking_date,
          b.start_time,
          b.end_time,
          b.room_code,
          mr.room_name,
          mr.price_per_hour,
          (
            SELECT p.verified
            FROM payments p
            WHERE p.booking_id = b.booking
            ORDER BY p.uploaded_at DESC
            LIMIT 1
          ) AS pay_verified,
          (
            SELECT p.uploaded_at
            FROM payments p
            WHERE p.booking_id = b.booking
            ORDER BY p.uploaded_at DESC
            LIMIT 1
          ) AS pay_uploaded_at
        FROM bookings b
        LEFT JOIN meeting_rooms mr ON b.room_code = mr.room_code
        WHERE $whereSql
        ORDER BY b.booking_date DESC, b.start_time DESC
        LIMIT ? OFFSET ?";

$stmt = $hotelConn->prepare($sql);
$types2 = $types . 'ii';
$params2 = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function toTime5($t){ $t = (string)$t; return $t !== '' ? substr($t,0,5) : ''; }
function paymentBadge($v){
  $v = strtolower((string)$v);
  if ($v === 'approved') return ['success','อนุมัติแล้ว'];
  if ($v === 'rejected') return ['danger','ถูกปฏิเสธ'];
  if ($v === 'pending' || $v === '') return ['warning','รอตรวจ'];
  return ['secondary', $v ?: 'ไม่มีข้อมูล'];
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>ประวัติการจองของฉัน</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f7f7fb }
    .card { max-width: 1000px; margin: 24px auto; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.06) }
    .nav-pills .nav-link.active { box-shadow: 0 6px 18px rgba(13,110,253,.25) }
    <style>
  .badge.bg-success {
    background-color: #198754 !important; /* เขียวเข้ม */
  }
  .badge.bg-danger {
    background-color: #dc3545 !important; /* แดงเข้ม */
  }
  .badge.bg-warning {
    background-color: #ff9800 !important; /* ส้มเข้มกว่า default */
    color: #fff !important; /* ตัวอักษรเป็นขาวจะเห็นชัด */
  }
  .badge.bg-secondary {
    background-color: #6c757d !important; /* เทาเข้ม */
  }
</style>

  </style>
</head>
<body class="p-3 p-md-4">
  <div class="card p-4 bg-white">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h1 class="h4 m-0">ประวัติการจองของฉัน</h1>
      <div>
        <a class="btn btn-outline-secondary" href="home.php">กลับหน้าหลัก</a>
      </div>
    </div>

    <?php $qsKeep = $_GET; unset($qsKeep['view'], $qsKeep['page']);
      $makeUrl = function($v) use ($qsKeep){ return '?' . http_build_query(array_merge($qsKeep, ['view'=>$v])); };
    ?>
    <ul class="nav nav-pills gap-2 mb-3">
      <li class="nav-item"><a class="nav-link <?= $view==='all'?'active':'' ?>" href="<?= $makeUrl('all') ?>">ทั้งหมด</a></li>
      <li class="nav-item"><a class="nav-link <?= $view==='past'?'active':'' ?>" href="<?= $makeUrl('past') ?>">ก่อนหน้านี้</a></li>
     
    </ul>

    <form class="row g-2 align-items-end mb-3" method="get">
      <input type="hidden" name="view" value="<?= h($view) ?>">
      <div class="col-sm-4">
        <label class="form-label">ตั้งแต่วันที่</label>
        <input type="date" class="form-control" name="from" value="<?= h($fromDate) ?>">
      </div>
      <div class="col-sm-4">
        <label class="form-label">ถึงวันที่</label>
        <input type="date" class="form-control" name="to" value="<?= h($toDate) ?>">
      </div>
      <div class="col-sm-4">
        <button class="btn btn-primary">ค้นหา</button>
        <a href="<?= h(strtok($_SERVER['REQUEST_URI'], '?')) ?>" class="btn btn-outline-secondary">ล้างตัวกรอง</a>
      </div>
    </form>

    <?php if (empty($rows)): ?>
      <div class="alert alert-info">ไม่พบบันทึกการจอง<?= $view==='past'?' (รวมวันนี้เป็นอดีต)':'' ?><?= ($fromDate||$toDate)?' ในช่วงวันที่ที่เลือก':'' ?></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th style="width:120px">เลขที่จอง</th>
              <th>ห้อง</th>
              <th style="width:140px">วันที่</th>
              <th style="width:180px">เวลา</th>
              <th style="width:150px">สถานะชำระ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              [$bClass, $bText] = paymentBadge($r['pay_verified'] ?? '');
            ?>
              <tr>
                <td>#<?= h($r['id']) ?></td>
                <td><?= h($r['room_name'] ?: $r['room_code']) ?></td>
                <td><?= h(date('d/m/Y', strtotime($r['booking_date']))) ?></td>
                <td><?= h(toTime5($r['start_time'])) ?> - <?= h(toTime5($r['end_time'])) ?></td>
                <td>
                  <span class="badge bg-<?= h($bClass) ?>"><?= h($bText) ?></span>
                  <?php if (!empty($r['pay_uploaded_at'])): ?>
                    <div class="text-muted small">อัปเดต: <?= h($r['pay_uploaded_at']) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
        <div class="text-muted small">
          แสดง <?= $total?($offset+1):0 ?>–<?= min($offset+$perPage,$total) ?> จากทั้งหมด <?= $total ?> รายการ
        </div>
        <nav>
          <ul class="pagination m-0">
            <?php $baseQS = $_GET; unset($baseQS['page']);
              $qs = function($p) use ($baseQS){ return '?' . http_build_query(array_merge($baseQS, ['page'=>$p])); };
            ?>
            <li class="page-item <?= $page<=1?'disabled':'' ?>">
              <a class="page-link" href="<?= $page>1?$qs($page-1):'#' ?>">ก่อนหน้า</a>
            </li>
            <?php
              $window = 2; $start = max(1,$page-$window); $end = min($totalPages,$page+$window);
              if ($start>1){ echo '<li class="page-item"><a class="page-link" href="'.$qs(1).'">1</a></li>'; if ($start>2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; }
              for($p=$start;$p<=$end;$p++){ $active = $p===$page?'active':''; echo '<li class="page-item '.$active.'"><a class="page-link" href="'.$qs($p).'">'.$p.'</a></li>'; }
              if ($end<$totalPages){ if ($end<$totalPages-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; echo '<li class="page-item"><a class="page-link" href="'.$qs($totalPages).'">'.$totalPages.'</a></li>'; }
            ?>
            <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
              <a class="page-link" href="<?= $page<$totalPages?$qs($page+1):'#' ?>">ถัดไป</a>
            </li>
          </ul>
        </nav>
      </div>
    <?php endif; ?>

    <div class="mt-3">
      <a class="btn btn-outline-secondary" href="check_status.php">ตรวจสอบสถานะแบบระบุเลขการจอง</a>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
