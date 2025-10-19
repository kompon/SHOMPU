<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>เพิ่มห้องประชุม</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    
    :root{
      --bg-grad-a:#f8f9fc;
      --bg-grad-b:#eef1f6;
      --ink:#0f172a;
      --muted:#475569;
      --brand-a:#06b6d4;
      --brand-b:#3b82f6;
      --brand-hover-shadow: rgba(59,130,246,.45);
      --accent:#38bdf8;
      --card:#ffffff;
      --stroke:#cbd5e1;
      --field:#f8fafc;
    }

    
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      font-family:'Sarabun',system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial,'Noto Sans',sans-serif;
      background:linear-gradient(135deg,var(--bg-grad-a),var(--bg-grad-b));
      color:var(--ink);
      margin:0;
    }

    
    .content{
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:32px 20px;
    }

    .card{
      width:100%;
      max-width:820px;
      background:var(--card);
      border-radius:22px;
      box-shadow:0 12px 36px rgba(0,0,0,.08);
      padding:28px;
      animation:fadeIn .5s ease;
    }

    @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

    .title{
      text-align:center;
      margin:0 0 8px;
      font-size:1.85rem;
      font-weight:700;
      color:#1e293b;
    }
    .subtitle{
      text-align:center;
      margin:0 0 20px;
      color:var(--muted);
      font-weight:600;
      font-size:14px;
    }

    /* ===== Form ===== */
    form{ margin:0; }
    .grid{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .grid .full{ grid-column:1 / -1; }

    label{
      display:block; margin:12px 0 6px; color:var(--muted);
      font-size:14px; font-weight:700;
    }
    input[type="text"], input[type="number"], input[type="file"], textarea{
      width:100%; background:var(--field); border:1px solid var(--stroke);
      border-radius:12px; padding:11px 12px; font-size:14px;
      transition:border-color .15s, box-shadow .15s, background .15s;
    }
    textarea{ resize:vertical; min-height:88px; }
    input:focus, textarea:focus, input[type="file"]:focus{
      outline:none; border-color:var(--accent);
      background:#fff; box-shadow:0 0 0 3px rgba(56,189,248,.2);
    }


    button{
      margin-top:22px; width:100%; padding:12px 18px;
      border:none; border-radius:12px; cursor:pointer;
      font-weight:700; font-size:15px; letter-spacing:.2px;
      color:#fff; background:linear-gradient(90deg,var(--brand-a),var(--brand-b));
      transition:transform .15s, box-shadow .15s, filter .15s;
    }
    button:hover{ transform:translateY(-2px); box-shadow:0 10px 24px var(--brand-hover-shadow); }
    button:active{ transform:translateY(0); filter:saturate(1.1); }

  
    .error{
      max-width:820px; margin:0 auto 16px; color:#ef4444;
      font-weight:700; text-align:center;
    }

    
    .back{
      display:block; text-align:center; margin:16px auto 0; color:#3b82f6;
      text-decoration:none; font-weight:600;
    }
    .back:hover{ text-decoration:underline; }

    
    @media (max-width: 900px){
      .grid{ grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

  <main class="content">
    <div class="card">
      <h1 class="title">เพิ่มห้องประชุม</h1>
      <p class="subtitle">กรอกข้อมูลให้ครบถ้วนเพื่อสร้างห้องประชุมใหม่</p>

      <!-- แสดง error (ถ้ามี) -->
      <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <div class="grid">
          <div>
            <label>รหัสห้อง</label>
            <input type="text" name="room_code" required>
          </div>

          <div>
            <label>ชื่อห้อง</label>
            <input type="text" name="room_name" required>
          </div>

          <div class="full">
            <label>รายละเอียด</label>
            <textarea name="description" rows="3"></textarea>
          </div>

          <div>
            <label>จำนวนที่นั่ง</label>
            <input type="number" name="capacity" required min="1">
          </div>

          <div>
            <label>ขนาดห้อง</label>
            <input type="text" name="room_size" required>
          </div>

          <div class="full">
            <label>อุปกรณ์</label>
            <textarea name="tools" rows="2" required></textarea>
          </div>

          <div>
            <label>ราคาต่อชั่วโมง (บาท)</label>
            <input type="number" step="0.01" name="price_per_hour" required>
          </div>

          <div>
            <label>รูปภาพ</label>
            <input type="file" name="image" accept="image/*">
          </div>
        </div>

        <button type="submit">บันทึกข้อมูล</button>
      </form>

      <a class="back" href="admin.php">← กลับไปหน้าแอดมิน</a>
    </div>
  </main>

</body>
</html>
