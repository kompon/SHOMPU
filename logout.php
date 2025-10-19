<?php
session_start();

// ทำลาย session
session_destroy();

// เปลี่ยนเส้นทางไปที่หน้า index.php
header('Location: index.php');
exit();
?>
