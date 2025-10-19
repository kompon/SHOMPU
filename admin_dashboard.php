<?php
// admin_dashboard.php
session_start();
require_once __DIR__ . '/config.php';

/* -------------------------
   1) สิทธิ์เข้าถึง
--------------------------*/
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
  // header('Location: admin.php'); exit;
}

/* -------------------------
   2) ตั้งค่าชื่อผู้พิมพ์รายงาน (ค่าเริ่มต้น)
--------------------------*/
$reportPrinter = 'ผู้ดูแลระบบ';
if (!empty($_SESSION['username'])) { $reportPrinter = $_SESSION['username']; }

/* -------------------------
   3) KPI Cards
--------------------------*/
function q($sql){ global $conn; return mysqli_query($conn, $sql); }
function money_th($num){ return number_format((float)$num, 2); }

$bookingCount = 0; if($res=q("SELECT COUNT(*) total FROM bookings")){ $bookingCount=(int)mysqli_fetch_assoc($res)['total']; }
$roomCount    = 0; if($res=q("SELECT COUNT(*) total FROM meeting_rooms")){ $roomCount=(int)mysqli_fetch_assoc($res)['total']; }
$pendingPayments=0;if($res=q("SELECT COUNT(*) total FROM payments WHERE verified='pending'")){ $pendingPayments=(int)mysqli_fetch_assoc($res)['total']; }

$totalRevenue = 0.0;
$sqlRevenue = "
  SELECT COALESCE(SUM((TIMESTAMPDIFF(MINUTE,b.start_time,b.end_time)/60)*r.price_per_hour),0) total
  FROM bookings b JOIN meeting_rooms r ON r.room_code=b.room_code
";
if($res=q($sqlRevenue)){ $totalRevenue=(float)mysqli_fetch_assoc($res)['total']; }

/* -------------------------
   4) กราฟรายเดือน
--------------------------*/
$chartLabels=[]; $chartDataBookings=[]; $chartDataRevenue=[];
$mb=[]; $mr=[];
$sqlMonthlyBookings="
  SELECT DATE_FORMAT(booking_date,'%Y-%m') ym, COUNT(*) cnt
  FROM bookings GROUP BY ym ORDER BY ym ASC";
if($res=q($sqlMonthlyBookings)){ while($row=mysqli_fetch_assoc($res)){ $mb[$row['ym']]=(int)$row['cnt']; } }
$sqlMonthlyRevenue="
  SELECT DATE_FORMAT(b.booking_date,'%Y-%m') ym,
         COALESCE(SUM((TIMESTAMPDIFF(MINUTE,b.start_time,b.end_time)/60)*r.price_per_hour),0) rev
  FROM bookings b JOIN meeting_rooms r ON r.room_code=b.room_code
  GROUP BY ym ORDER BY ym ASC";
if($res=q($sqlMonthlyRevenue)){ while($row=mysqli_fetch_assoc($res)){ $mr[$row['ym']]=(float)$row['rev']; } }
$allMonths=array_unique(array_merge(array_keys($mb),array_keys($mr))); sort($allMonths);
foreach($allMonths as $ym){ $chartLabels[]=$ym; $chartDataBookings[]=$mb[$ym]??0; $chartDataRevenue[]=isset($mr[$ym])?round((float)$mr[$ym],2):0; }

/* -------------------------
   5) รายการจองล่าสุด
--------------------------*/
$latestBookings=[];
$sqlLatest="
  SELECT b.booking, b.booking_date, b.start_time, b.end_time, b.room_code,
         r.room_name, r.price_per_hour
  FROM bookings b
  LEFT JOIN meeting_rooms r ON r.room_code=b.room_code
  ORDER BY b.booking DESC LIMIT 10";
if($res=q($sqlLatest)){ while($row=mysqli_fetch_assoc($res)){ $latestBookings[]=$row; } }

/* -------------------------
   6) สถิติห้องที่จองเยอะที่สุด "เดือนนี้" (อิง start_time จริง)
--------------------------*/
$firstDay = date('Y-m-01');
$lastDay  = date('Y-m-t');

$topRooms = [];
$sqlTopRooms = "
  SELECT 
    b.room_code,
    COALESCE(r.room_name, CONCAT('รหัสห้อง ', b.room_code)) AS room_name,
    COUNT(*) AS cnt,
    ROUND(SUM(TIMESTAMPDIFF(MINUTE, b.start_time, b.end_time)) / 60, 2) AS hours
  FROM bookings b
  LEFT JOIN meeting_rooms r ON r.room_code = b.room_code
  WHERE 
    MONTH(b.start_time) = MONTH(CURDATE())
    AND YEAR(b.start_time) = YEAR(CURDATE())
    AND b.start_time IS NOT NULL 
    AND b.end_time IS NOT NULL
  GROUP BY b.room_code, room_name
  ORDER BY cnt DESC, hours DESC
  LIMIT 5
";
if ($res = q($sqlTopRooms)) {
  while ($row = mysqli_fetch_assoc($res)) {
    $topRooms[] = $row;
  }
}
$topRoomCard = $topRooms[0] ?? null;

/* -------------------------
   7) ปฏิทินการใช้ห้อง (FullCalendar events)
--------------------------*/
$calendarEvents = [];
$sqlCal = "
  SELECT b.booking, b.booking_date, b.start_time, b.end_time, b.room_code,
         COALESCE(r.room_name, CONCAT('ห้อง ',b.room_code)) room_name
  FROM bookings b
  LEFT JOIN meeting_rooms r ON r.room_code=b.room_code
  WHERE b.booking_date BETWEEN '$firstDay' AND '$lastDay'
  ORDER BY b.booking_date ASC, b.start_time ASC
";
if($res=q($sqlCal)){
  while($row=mysqli_fetch_assoc($res)){
    // สร้าง ISO datetime สำหรับ FullCalendar
    $start = $row['booking_date'].'T'.substr($row['start_time'],0,8);
    $end   = $row['booking_date'].'T'.substr($row['end_time'],0,8);
    $calendarEvents[] = [
      'id'    => (string)$row['booking'],
      'title' => $row['room_name']." (".$row['room_code'].")",
      'start' => $start,
      'end'   => $end
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Fonts & CSS -->
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <!-- FullCalendar -->
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales/th.global.min.js"></script>

  <style>
   :root {
  --sheet-border: #dcdcdc;
  --sheet-head: #f9fafb;
  --ink: #222;
  --accent1: #ff7a00;
  --accent2: #ffb100;
}

body {
  background: #f7f9fc;
  font-family: "Sarabun", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  color: var(--ink);
  font-size: 15px;
}

/* การ์ดหลัก */
.card-kpi {
  border: 1px solid var(--sheet-border);
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,.05);
  background: #fff;
  transition: 0.3s;
}
.card-kpi:hover {
  box-shadow: 0 6px 18px rgba(0,0,0,.1);
}

/* หัวการ์ดมีแถบสี */
.card-header-bar {
  height: 6px;
  background: linear-gradient(90deg, var(--accent1), var(--accent2));
  border-top-left-radius: 12px;
  border-top-right-radius: 12px;
  margin: -16px -16px 10px -16px;
}

/* KPI ตัวเลข */
.kpi-number {
  font-size: 28px;
  font-weight: 700;
}
.kpi-label {
  color: #666;
}

/* ตาราง */
.table-rounded {
  overflow: hidden;
  border-radius: 12px;
  border: 1px solid var(--sheet-border);
}
.table thead {
  background: var(--sheet-head);
  font-weight: 600;
}

/* แถบด้านบน */
.action-bar {
  background: #fff;
  border: 1px solid var(--sheet-border);
  border-radius: 12px;
  padding: 10px 12px;
  box-shadow: 0 3px 12px rgba(0,0,0,.05);
}

/* กล่องรายงาน */
.report-box {
  border: 1px solid var(--sheet-border);
  border-radius: 12px;
  background: #fff;
  padding: 20px;
  margin-bottom: 14px;
  box-shadow: 0 3px 10px rgba(0,0,0,.05);
}
.report-title {
  text-align: center;
  font-weight: 800;
  font-size: 22px;
  color: var(--accent1);
}
.report-sub {
  text-align: center;
  color: #555;
  margin-bottom: 8px;
}
.report-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 18px;
}
.report-kv .k { color: #666; }
.report-kv .v { font-weight: 600; }

/* ปฏิทิน */
#roomCalendar {
  background: #fff;
  border: 1px solid var(--sheet-border);
  border-radius: 12px;
  padding: 10px;
}
.fc .fc-toolbar-title {
  font-weight: 800;
  color: var(--accent1);
}

/* เวลาพิมพ์ */
@media print {
  body { background: #fff; font-size: 14px; }
  nav.navbar, .no-print, .btn, a.btn { display: none !important; }
  .container { max-width: 100% !important; }
  .card, .card-kpi, .report-box, .table-rounded, .action-bar {
    box-shadow: none !important;
    border-color: #999 !important;
  }
  .card-header-bar {
    background: #000 !important;
    -webkit-print-color-adjust: exact;
  }
  .report-title {
    color: #000 !important;
  }
  @page {
    size: A4 portrait;
    margin: 14mm;
  }
}

  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container-fluid">
      <span class="navbar-brand brand">Khonkaen Hotel</span>
      <div class="ms-auto d-flex gap-2">
      
        <a href="add_room.php" class="btn btn-primary btn-sm">+ เพิ่มห้อง</a>
        <a href="logout.php" class="btn btn-danger btn-sm">ออกจากระบบ</a>
      </div>
    </div>
  </nav>

  <div class="container py-4">
    <!-- ACTION BAR -->
    <div class="action-bar d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3 no-print">
      <div>
        <strong>แดชบอร์ดผู้ดูแลระบบ</strong><br>
        <small class="text-muted">สรุปสถิติระบบจองห้องประชุม</small>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <div class="input-group" style="max-width:340px;">
          <span class="input-group-text">ผู้พิมพ์</span>
          <input id="printerNameInput" type="text" class="form-control" placeholder="กรอกชื่อผู้พิมพ์"
            value="<?= htmlspecialchars($reportPrinter) ?>">
          <button class="btn btn-outline-primary" type="button" id="savePrinterBtn">บันทึก</button>
        </div>
        <button type="button" class="btn btn-outline-secondary" onclick="history.back()">ย้อนกลับ</button>
        <button type="button" class="btn btn-success" onclick="printReport()">พิมพ์รายงาน</button>
      </div>
    </div>

    <!-- REPORT HEADER -->
    <div class="report-box">
      <div class="report-title">รายงานสถิติการจองห้องประชุม</div>
      <div class="report-sub"><strong>Khonkaen Hotel</strong></div>
      <div class="report-grid report-kv">
        <div class="k">พิมพ์เมื่อ</div><div class="v" id="printAt">-</div>
        <div class="k">ผู้พิมพ์</div><div class="v"><span id="printerName"><?= htmlspecialchars($reportPrinter) ?></span></div>
      </div>
    </div>

    <!-- KPI ROW + TOP ROOM CARD -->
    <div class="row g-3">
      <div class="col-12 col-md-3">
        <div class="card card-kpi p-3">
          <div class="kpi-label">การจองทั้งหมด</div>
          <div class="kpi-number"><?= number_format($bookingCount) ?></div>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="card card-kpi p-3">
          <div class="kpi-label">จำนวนห้องประชุม</div>
          <div class="kpi-number"><?= number_format($roomCount) ?></div>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="card card-kpi p-3">
          <div class="kpi-label">สลิปรออนุมัติ</div>
          <div class="kpi-number"><?= number_format($pendingPayments) ?></div>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="card card-kpi p-3">
          <div class="kpi-label">รายได้รวม</div>
          <div class="kpi-number">฿ <?= money_th($totalRevenue) ?></div>
        </div>
      </div>

      <!-- ห้องที่จองเยอะที่สุดในเดือนนี้ -->
      <div class="col-12">
        <div class="card card-kpi p-3">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">ห้องที่จองเยอะที่สุดในเดือนนี้</h5>
            <span class="text-muted">ช่วง: <?= htmlspecialchars($firstDay) ?> ถึง <?= htmlspecialchars($lastDay) ?></span>
          </div>
          <?php if($topRoomCard): ?>
            <div class="mt-3 d-flex flex-wrap align-items-center gap-3">
              <div><span class="badge bg-success badge-pill">Top</span></div>
              <div><strong><?= htmlspecialchars($topRoomCard['room_name']) ?></strong> <span class="text-muted">(<?= htmlspecialchars($topRoomCard['room_code']) ?>)</span></div>
              <div class="ms-auto">
                <span class="me-3">จำนวนการจอง: <strong><?= number_format($topRoomCard['cnt']) ?></strong> ครั้ง</span>
                <span>ชั่วโมงรวม: <strong><?= number_format($topRoomCard['hours'],2) ?></strong> ชม.</span>
              </div>
            </div>
            <!-- Top 5 -->
            <?php if(count($topRooms)>1): ?>
            <div class="table-responsive table-rounded mt-3">
              <table class="table mb-0 align-middle">
                <thead>
                  <tr>
                    <th>อันดับ</th>
                    <th>ห้อง</th>
                    <th class="text-center">จำนวนการจอง</th>
                    <th class="text-center">ชั่วโมงรวม</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($topRooms as $i=>$r): ?>
                  <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($r['room_name']) ?> <small class="text-muted">(<?= htmlspecialchars($r['room_code']) ?>)</small></td>
                    <td class="text-center"><?= number_format($r['cnt']) ?></td>
                    <td class="text-center"><?= number_format($r['hours'],2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="text-muted mt-3">ยังไม่มีข้อมูลการจองในเดือนนี้</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- CHARTS -->
    <div class="row g-3 mt-1">
      <div class="col-12">
        <div class="card card-kpi p-3">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">สรุปการจองรายเดือน</h5>
            <small class="text-muted">จากตาราง bookings</small>
          </div>
          <canvas id="bookingsChart" height="95"></canvas>
        </div>
      </div>

      <div class="col-12">
        <div class="card card-kpi p-3">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">รายได้รายเดือน</h5>
            <small class="text-muted">ชั่วโมงจอง × ราคา/ชั่วโมง</small>
          </div>
          <canvas id="revenueChart" height="95"></canvas>
        </div>
      </div>
    </div>

    

    <!-- LATEST BOOKINGS -->
    <div class="row g-3 mt-1">
      <div class="col-12">
        <div class="card card-kpi p-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">รายการจองล่าสุด</h5>
            <a href="admin_review.php" class="btn btn-sm btn-outline-primary no-print">ดูทั้งหมด</a>
          </div>
          <div class="table-responsive table-rounded">
            <table class="table mb-0 align-middle">
              <thead>
                <tr>
                  <th>#Booking</th>
                  <th>ห้อง</th>
                  <th>วันจอง</th>
                  <th>เวลา</th>
                  <th>ชั่วโมง</th>
                  <th>ราคา/ชม.</th>
                  <th>ราคารวม</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($latestBookings)): ?>
                  <tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีรายการ</td></tr>
                <?php else: foreach ($latestBookings as $row):
                  $mins=0;
                  if(!empty($row['start_time']) && !empty($row['end_time'])){
                    $mins=(strtotime($row['end_time'])-strtotime($row['start_time']))/60;
                    if($mins<0) $mins=0;
                  }
                  $hours=round($mins/60,2);
                  $pricePerHour=(float)($row['price_per_hour']??0);
                  $amt=round($hours*$pricePerHour,2);
                ?>
                <tr>
                  <td><?= htmlspecialchars($row['booking']) ?></td>
                  <td><?= htmlspecialchars($row['room_name'] ?? ('รหัสห้อง '.$row['room_code'])) ?></td>
                  <td><?= htmlspecialchars($row['booking_date']) ?></td>
                  <td><?= htmlspecialchars(substr($row['start_time'],0,5)) ?>–<?= htmlspecialchars(substr($row['end_time'],0,5)) ?></td>
                  <td><?= number_format($hours,2) ?></td>
                  <td>฿ <?= money_th($pricePerHour) ?></td>
                  <td><strong>฿ <?= money_th($amt) ?></strong></td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <div class="mt-3 no-print">
           
          </div>
        </div>
      </div>
    </div>

  </div><!-- /.container -->

<script>
  // ====== Data from PHP ======
  const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
  const dataBookings = <?= json_encode($chartDataBookings, JSON_UNESCAPED_UNICODE) ?>;
  const dataRevenue  = <?= json_encode($chartDataRevenue, JSON_UNESCAPED_UNICODE) ?>;
  const calEvents    = <?= json_encode($calendarEvents, JSON_UNESCAPED_UNICODE) ?>;

  // ====== Charts ======
  new Chart(document.getElementById('bookingsChart'), {
    type:'line',
    data:{ labels, datasets:[{ label:'จำนวนการจอง', data:dataBookings, tension:.35 }] },
    options:{ responsive:true, plugins:{ legend:{ display:true } }, scales:{ y:{ beginAtZero:true } } }
  });
  new Chart(document.getElementById('revenueChart'), {
    type:'bar',
    data:{ labels, datasets:[{ label:'รายได้ (บาท)', data:dataRevenue }] },
    options:{ responsive:true, plugins:{ legend:{ display:true } }, scales:{ y:{ beginAtZero:true } } }
  });

  // ====== ปฏิทินการใช้ห้อง (FullCalendar) ======


  // ====== วันที่ปัจจุบัน (ไทย/พ.ศ.) ======
  function setPrintTimestamp(){
    const el=document.getElementById('printAt'); if(!el) return;
    const now=new Date();
    const yearTH=now.getFullYear()+543;
    const monthsTH=["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    const dd=now.getDate();
    const hhmm=now.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'});
    el.textContent=`${dd} ${monthsTH[now.getMonth()]} ${yearTH} เวลา ${hhmm} น.`;
  }

  // ====== ตั้ง/จำชื่อผู้พิมพ์ ======
  const printerNameSpan=document.getElementById('printerName');
  const printerNameInput=document.getElementById('printerNameInput');
  const savePrinterBtn=document.getElementById('savePrinterBtn');
  function loadPrinterName(){
    const saved=localStorage.getItem('printerName');
    if(saved && saved.trim()!==''){
      if(printerNameSpan) printerNameSpan.textContent=saved;
      if(printerNameInput) printerNameInput.value=saved;
    }
  }
  function savePrinterName(){
    const name=(printerNameInput?.value||'').trim();
    if(name!==''){
      localStorage.setItem('printerName',name);
      if(printerNameSpan) printerNameSpan.textContent=name;
    }
  }
  if(savePrinterBtn) savePrinterBtn.addEventListener('click', savePrinterName);

  // ====== Print support (canvas -> img) ======
  function canvasToImageForPrint(){
    document.querySelectorAll('canvas').forEach(cv=>{
      if(cv.dataset.printImgCreated==='1') return;
      try{
        const img=document.createElement('img');
        img.src=cv.toDataURL('image/png');
        img.style.maxWidth='100%'; img.style.height='auto'; img.style.display='none';
        img.classList.add('print-canvas-img');
        cv.insertAdjacentElement('afterend',img);
        cv.dataset.printImgCreated='1';
      }catch(e){}
    });
  }
  function beforePrint(){
    setPrintTimestamp();
    savePrinterName();
    canvasToImageForPrint();
    document.querySelectorAll('canvas').forEach(cv=>cv.classList.add('d-print-none'));
    document.querySelectorAll('img.print-canvas-img').forEach(img=>img.style.display='block');
  }
  function afterPrint(){
    document.querySelectorAll('canvas').forEach(cv=>cv.classList.remove('d-print-none'));
    document.querySelectorAll('img.print-canvas-img').forEach(img=>img.style.display='none');
  }
  if(window.matchMedia){
    const mql=window.matchMedia('print');
    if(mql.addEventListener) mql.addEventListener('change', e=>e.matches?beforePrint():afterPrint());
    else if(mql.addListener) mql.addListener(e=>e.matches?beforePrint():afterPrint());
  }
  window.addEventListener('beforeprint', beforePrint);
  window.addEventListener('afterprint', afterPrint);

  // ปุ่มพิมพ์
  function printReport(){ setPrintTimestamp(); savePrinterName(); window.print(); }
  window.printReport=printReport;

  // Init
  document.addEventListener('DOMContentLoaded', ()=>{ loadPrinterName(); setPrintTimestamp(); });
</script>
</body>
</html>
