<?php
require 'config.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = trim($_POST["username"]);
  $email = trim($_POST["email"]);
  $password = $_POST["password"];
  $confirm_password = $_POST["confirm_password"];

  if ($password !== $confirm_password) {
      $error = "รหัสผ่านไม่ตรงกัน";
  } else {
      $check_stmt = $userConn->prepare("SELECT id FROM users WHERE email = ?");
      $check_stmt->bind_param("s", $email);
      $check_stmt->execute();
      $check_stmt->store_result();

      if ($check_stmt->num_rows > 0) {
          $error = "อีเมลนี้ถูกใช้งานแล้ว";
      } else {
          $hashed_password = password_hash($password, PASSWORD_DEFAULT);
          $role = "user";
          $stmt = $userConn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
          $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
          if ($stmt->execute()) {
              header("Location: index.php");
              exit();
          } else {
              $error = "เกิดข้อผิดพลาด: " . $userConn->error;
          }
          $stmt->close();
      }
      $check_stmt->close();
  }
  $userConn->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<title>สมัครสมาชิก - โรงแรมขอนแก่นโฮเต็ล</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet" />
<style>
  * {
    box-sizing: border-box;
    font-family: 'Sarabun', sans-serif;
  }
  body {
    background: linear-gradient(180deg, #f5f5f5, #e9e9e9);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
  }
  .register-box {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 18px;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.08);
    padding: 40px 36px;
    width: 100%;
    max-width: 420px;
  }
  h2 {
    text-align: center;
    font-weight: 700;
    font-size: 26px;
    color: #111;
    margin-bottom: 6px;
  }
  p.sub {
    text-align: center;
    font-size: 14px;
    color: #666;
    margin-bottom: 26px;
  }
  .form-group {
    margin-bottom: 18px;
  }
  label {
    font-weight: 600;
    font-size: 14px;
    color: #333;
    display: block;
    margin-bottom: 6px;
  }
  .form-control {
    width: 100%;
    padding: 11px 14px;
    border: 1px solid #ccc;
    border-radius: 10px;
    font-size: 15px;
    color: #111;
    background: #fafafa;
    transition: border-color 0.2s ease;
  }
  .form-control:focus {
    outline: none;
    border-color: #111;
    background: #fff;
  }
  .btn-submit {
    width: 100%;
    padding: 12px;
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.3s ease;
  }
  .btn-submit:hover {
    background-color: #218838;
  }
  .login-link {
    text-align: center;
    margin-top: 18px;
    font-size: 14px;
    color: #444;
  }
  .login-link a {
    color: #111;
    font-weight: 700;
    text-decoration: none;
  }
  .login-link a:hover {
    text-decoration: underline;
  }
  .error {
    text-align: center;
    background: #fce8e8;
    color: #c82333;
    border: 1px solid #f5c6cb;
    padding: 10px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 16px;
  }
  @media (max-width: 480px) {
    .register-box {
      padding: 30px 24px;
    }
  }
</style>
</head>
<body>

<div class="register-box">
  <h2>สมัครสมาชิก</h2>
  <p class="sub">โรงแรมขอนแก่นโฮเต็ล (Khonkaen Hotel)</p>

  <?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="">
    <div class="form-group">
      <label for="username">ชื่อผู้ใช้</label>
      <input type="text" class="form-control" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" required>
    </div>

    <div class="form-group">
      <label for="email">อีเมล</label>
      <input type="email" class="form-control" id="email" name="email" placeholder="กรอกอีเมล" required>
    </div>

    <div class="form-group">
      <label for="password">รหัสผ่าน</label>
      <input type="password" class="form-control" id="password" name="password" placeholder="อย่างน้อย 8 ตัวอักษร" required minlength="8">
    </div>

    <div class="form-group">
      <label for="confirm_password">ยืนยันรหัสผ่าน</label>
      <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="พิมพ์รหัสผ่านอีกครั้ง" required>
    </div>

    <button type="submit" class="btn-submit">สมัครสมาชิก</button>
  </form>

  <div class="login-link">
    มีบัญชีแล้ว? <a href="index.php">เข้าสู่ระบบ</a>
  </div>
</div>

</body>
</html>
