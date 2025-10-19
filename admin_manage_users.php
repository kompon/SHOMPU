<?php
session_start();

// ตรวจสอบสิทธิ์ (ถ้ามีระบบ role)
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
  // header("Location: login.php");
  // exit;
}

// เชื่อมต่อฐาน user_db
$host = "localhost";
$dbname = "user_db";
$username = "root";
$password = "";

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY id ASC");
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>จัดการผู้ใช้งาน | แผงควบคุมแอดมิน</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
  background: #f7f9fc;
  font-family: "Sarabun", sans-serif;
}
.navbar {
  background: #000000;   /* สีดำ */
  color: white;
  font-weight: 600;
}
.navbar .navbar-brand, .navbar a, .navbar span {
  color: white !important;
}
.card {
  border: none;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.table thead {
  background: #000;
  color: white;
}
.btn-green {
  background: #28a745;
  color: white;
  border: none;
}
.btn-green:hover {
  background: #218838;
  color: white;
}
.btn-back {
  background: #6c757d;
  color: #fff;
}
.btn-back:hover {
  background: #5a6268;
}
.btn-logout {
  background: #dc3545; /* สีแดง */
  color: #fff;
  border: none;
}
.btn-logout:hover {
  background: #bb2d3b;
  color: #fff;
}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark px-3">
  <span class="navbar-brand fw-bold">ระบบจองห้องประชุม</span>
  <div class="ms-auto">
    <span class="me-3"><i class="bi bi-person-circle"></i> แอดมิน</span>
    <a href="logout.php" class="btn btn-logout btn-sm"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
  </div>
</nav>

<div class="container mt-4">
  <div class="card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="d-flex align-items-center gap-2">
        <a href="admin.php" class="btn btn-back btn-sm"></i> ย้อนกลับ</a>
        <h4 class="fw-bold mb-0">จัดการผู้ใช้งาน</h4>
      </div>
      <a href="add_user.php" class="btn btn-green btn-sm"><i class="bi bi-person-plus"></i> เพิ่มผู้ใช้</a>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle">
        <thead>
          <tr class="text-center">
            <th>ID</th>
            <th>ชื่อผู้ใช้</th>
            <th>อีเมล</th>
            <th>สิทธิ์</th>
            <th>วันที่สร้าง</th>
            <th>การจัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">ไม่มีข้อมูลผู้ใช้</td></tr>
          <?php else: foreach ($users as $u): ?>
          <tr>
            <td class="text-center"><?= htmlspecialchars($u['id']) ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td class="text-center">
              <?php if ($u['role'] === 'admin'): ?>
                <span class="badge bg-danger">Admin</span>
              <?php else: ?>
                <span class="badge bg-secondary">User</span>
              <?php endif; ?>
            </td>
            <td class="text-center"><?= htmlspecialchars($u['created_at']) ?></td>
            <td class="text-center">
              <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i></a>
              <a href="delete_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('ลบผู้ใช้นี้หรือไม่?')"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>
