<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dev";


try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

/**
 * ฟังก์ชัน dd_q() สำหรับ Query ฐานข้อมูลแบบปลอดภัย (Prepared Statement)
 * @param string $sql คำสั่ง SQL เช่น "SELECT * FROM users WHERE id = ?"
 * @param array $params ตัวแปรที่ต้องการใส่ใน ? เช่น [$_SESSION['id']]
 */
function dd_q($sql, $params = []) {
    global $conn; // ดึงตัวแปร $conn จากด้านบนมาใช้งานในฟังก์ชัน
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
}
?>