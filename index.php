<?php
session_start();
error_reporting(0); 
require_once("../system/a_func.php");   

if (!isset($_SESSION['id'])) {
    header("Location: pages/login.php");
    exit();
}

$stmt = dd_q("SELECT * FROM users WHERE id = ? LIMIT 1", [$_SESSION['id']]);

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