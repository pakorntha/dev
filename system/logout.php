<?php
session_start();

// 1. เคลียร์ตัวแปร Session ทั้งหมดที่มีในระบบ
$_SESSION = array();

// 2. ลบ Cookie ของ Session ในเบราว์เซอร์ (เพื่อความปลอดภัยสูงสุด)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. ทำลาย Session ทั้งหมด
session_destroy();

// 4. เด้งกลับไปที่หน้า Login
header("Location: ../pages/login.php");
exit();
?>