<?php
session_start();
ini_set('display_errors', 1);       
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);            

// 1. เช็คว่ายังไม่ได้ล็อกอิน ให้เด้งไปหน้า Login
if (!isset($_SESSION['id'])) {
    header("Location: pages/login.php");
    exit();
}

// 2. เรียกไฟล์เชื่อมต่อฐานข้อมูล
require_once("system/a_func.php"); 

// 3. ดึงข้อมูล User
$stmt = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);

if ($stmt->rowCount() == 1) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_role = $user['role']; // ค่าที่ได้จะเป็น 'student', 'teacher', หรือ 'admin'

    // 4. ตรวจสอบ Role ตามค่า ENUM ในฐานข้อมูล
    if ($user_role === 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    } 
    elseif ($user_role === 'student') {
        header("Location: pages/students/home.php");
        exit();
    } 
    elseif ($user_role === 'teacher') {
        // เพิ่มหน้ารองรับสำหรับอาจารย์ (ถ้ายังไม่มีสามารถเปลี่ยน path ได้)
        header("Location: pages/teachers/home.php");
        exit();
    } 
    else {
        header("Location: error.php?msg=invalid_role");
        exit();
    }
} else {
    session_destroy();
    header("Location: pages/login.php?msg=account_not_found");
    exit();
}
?>