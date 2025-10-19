<?php
require 'config.php';
session_start();

// ตรวจสอบว่าเป็นแอดมิน
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// รับ action อนุมัติหรือปฏิเสธ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = $_POST['payment_id'] ?? 0;
    $action = $_POST['action'] ?? '';

    if ($payment_id && in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $hotelConn->prepare("UPDATE payment_verification SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $payment_id);
        $stmt->execute();
    }
}

// ดึงข้อมูลรายการชำระเงินทั้งหมด
$result = $hotelConn->query("SELECT * FROM payment_verification ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>ตรวจสอบการชำระเงิน - แอดมิน</title>
  <style>
    body {
      font-family: 'Sarabun', sans-serif;
      padding: 20px;
      background: #f0f0f0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 40px;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 12px;
      text-align: center;
    }
    th {
      background: #00cc99;
      color: #fff;
    }
    .status-pending { color: orange; font-weight: bold; }
    .status-approved { color: green; font-weight: bold; }
    .status-rejected { color: red; font-weight: bold; }
    form {
      margin: 0;
    }
    button {
      margin: 5px;
      padding: 5px 10px;
      cursor: pointer;
      border: none;
      border-radius: 5px;
    }
    .btn-approve { background-color: #28a745; color: white; }
    .btn-reject { background-color: #dc3545; color: white; }
  </style>
</head>
<body>
  <h1>รายการชำระเงินรอการตรวจสอบ</h1>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>ห้องประชุม</th>
        <th>วันที่จอง</th>
        <th>ระยะเวลา</th>
        <th>ราคา</th>
        <th>ชื่อ-นามสกุล</th>
        <th>อีเมล</th>
        <th>โทรศัพท์</th>
        <th>วิธีชำระเงิน</th>
        <th>สลิป</th>
        <th>สถานะ</th>
        <th>จัดการ</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $result->fetch_assoc()) : ?>
      <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['room_code']) ?></td>
        <td><?= htmlspecialchars($row['date_booking']) ?></td>
        <td><?= htmlspecialchars($row['duration']) ?></td>
        <td><?= number_format($row['price'], 2) ?></td>
        <td><?= htmlspecialchars($row['fullname']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= htmlspecialchars($row['phone']) ?></td>
        <td><?= htmlspecialchars($row['payment_method']) ?></td>
        <td>
          <?php if ($row['slip_path'] && file_exists($row['slip_path'])) : ?>
            <a href="<?= htmlspecialchars($row['slip_path']) ?>" target="_blank">ดูสลิป</a>
          <?php else: ?>
            ไม่มีสลิป
          <?php endif; ?>
        </td>
        <td class="status-<?= $row['status'] ?>">
          <?= ucfirst($row['status']) ?>
        </td>
        <td>
          <?php if ($row['status'] === 'pending'): ?>
          <form method="POST" style="display:inline-block;">
            <input type="hidden" name="payment_id" value="<?= $row['id'] ?>">
            <input type="hidden" name="action" value="approve">
            <button class="btn-approve" type="submit">อนุมัติ</button>
          </form>
          <form method="POST" style="display:inline-block;">
            <input type="hidden" name="payment_id" value="<?= $row['id'] ?>">
            <input type="hidden" name="action" value="reject">
            <button class="btn-reject" type="submit">ปฏิเสธ</button>
          </form>
          <?php else: ?>
            -
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</body>
</html>
