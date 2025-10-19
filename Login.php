<?php
require 'config.php';
session_start();

$error = "";

$hardcoded_admin_email = "admin@gmail.com";
$hardcoded_admin_password = "admin1234";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"] ?? '');

    // ✅ แอดมินแบบฮาร์ดโค้ด
    if ($email === $hardcoded_admin_email && $password === $hardcoded_admin_password) {
        $_SESSION["user_id"] = 0;
        $_SESSION["username"] = "Admin";
        $_SESSION["email"] = $email;
        $_SESSION["user_role"] = "admin";
        header("Location: admin.php");
        exit;
    }

    // ✅ ตรวจผู้ใช้จากฐานข้อมูล
    $stmt = $userConn->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $username, $hashed_password, $role);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            $_SESSION["user_id"] = $id;
            $_SESSION["username"] = $username;
            $_SESSION["email"] = $email;
            $_SESSION["user_role"] = $role;

            if ($role === "admin") {
                header("Location: admin.php");
            } else {
                header("Location: home.php");
            }
            exit;
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบอีเมลนี้ในระบบ";
    }

    $stmt->close();
    $userConn->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เข้าสู่ระบบ - โรงแรมขอนแก่นโฮเต็ล</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --ink:#111; --muted:#666; --line:#ddd; --bg:#f5f5f5; --card:#fff;
      --green:#28a745; --green-dark:#218838; --danger:#c82333;
    }
    *{box-sizing:border-box; font-family:'Sarabun',sans-serif;}
    html,body{height:100%; margin:0; background:#f6f6f6;}

    body {
      display: flex;
      justify-content: center;
      align-items: center;
      background: linear-gradient(180deg, #ffffff, #f0f0f0);
    }

    .login-card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 20px;
      padding: 40px 35px;
      width: 90%;
      max-width: 400px;
      box-shadow: 0 8px 28px rgba(0,0,0,0.08);
      text-align: center;
    }

    h1 {
      font-size: 28px;
      font-weight: 700;
      color: var(--ink);
      margin-bottom: 4px;
    }

    p.sub {
      font-size: 15px;
      color: var(--muted);
      margin-bottom: 28px;
    }

    .alert {
      background: #fce8e8;
      border: 1px solid #f5c6cb;
      color: var(--danger);
      padding: 10px;
      border-radius: 10px;
      margin-bottom: 18px;
      font-size: 14px;
    }

    .form-group {
      margin-bottom: 18px;
      text-align: left;
    }

    label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: #333;
      margin-bottom: 5px;
    }

    .form-control {
      width: 100%;
      padding: 12px 14px;
      border-radius: 10px;
      border: 1px solid #ccc;
      background: #fafafa;
      font-size: 15px;
      transition: all 0.2s;
    }

    .form-control:focus {
      outline: none;
      border-color: #000;
      background: #fff;
    }

    .btn-login {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 10px;
      background-color: var(--green);
      color: white;
      font-weight: 600;
      font-size: 16px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .btn-login:hover {
      background-color: var(--green-dark);
    }

    .foot {
      text-align: center;
      margin-top: 18px;
      font-size: 14px;
      color: #444;
    }

    .foot a {
      color: #000;
      font-weight: 700;
      text-decoration: none;
    }

    .foot a:hover {
      text-decoration: underline;
    }

    @media (max-width: 480px) {
      .login-card {
        padding: 30px 22px;
      }
    }
  </style>
</head>
<body>

  <div class="login-card">
    <h1>เข้าสู่ระบบ</h1>
    <p class="sub">โรงแรมขอนแก่นโฮเต็ล (Khonkaen Hotel)</p>

    <?php if (!empty($error)) : ?>
      <div class="alert">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="form-group">
        <label for="email">อีเมล</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com" required>
      </div>

      <div class="form-group">
        <label for="password">รหัสผ่าน</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="กรอกรหัสผ่าน" required>
      </div>

      <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
    </form>

    <div class="foot">
      ยังไม่มีบัญชี? <a href="regis.php">สมัครสมาชิก</a>
    </div>
  </div>

</body>
</html>
