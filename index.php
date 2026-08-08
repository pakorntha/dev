<?php
session_start();
ini_set('display_errors', 1);       
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);             

// 1. เช็คก่อนเลยว่าไม่มี Session ใช่ไหม? ถ้าใช่ เด้งไปหน้า Login ทันที!
if (!isset($_SESSION['id'])) {
    header("Location: ../pages/login.php"); // <-- เช็ค Path ตรงนี้ให้ดีว่าไฟล์ login.php อยู่ที่ไหน
    exit();
}

// 2. ถ้ามี Session แล้ว ค่อยเรียกไฟล์เชื่อมต่อฐานข้อมูล
require_once("system/a_func.php"); 

// 3. แล้วค่อยดึงข้อมูลมาเช็ค Rank
$stmt = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);

if ($stmt->rowCount() == 1) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_role = $user['rank'];

    if ($user_role == "1") {
        header("Location: admin_dashboard.php");
        exit();
    } 
    elseif ($user_role == "0") {
        header("Location: ./pages/students/home.php");
        exit();
    } 
    else {
        header("Location: error.php?msg=invalid_role");
        exit();
    }
} else {
    session_destroy();
    header("Location: login.php?msg=account_not_found");
    exit();
}
?>