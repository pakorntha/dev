<?php
session_start();
require_once("../../system/a_func.php");


// if (!isset($_SESSION['id'])) {
//     header("Location: login.php");
//     exit();
// }

$stmt = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user['role'] == "admin") {
    header("Location: admin_dashboard.php");
    exit();
}

// สมมติว่าในฐานข้อมูลคุณมีฟิลด์เหล่านี้ (ถ้าไม่มีให้ไปเพิ่มใน phpMyAdmin หรือแก้โค้ดให้ตรง)
$firstname = htmlspecialchars($user['firstname'] ?? 'ไม่ระบุชื่อ'); // ดึงชื่อ
$lastname = htmlspecialchars($user['lastname'] ?? 'ไม่ระบุชื่อ'); // ดึงชื่อ
$student_id = htmlspecialchars($user['student_id'] ?? '660000000'); // ดึงรหัสนักศึกษา
$faculty = htmlspecialchars($user['faculty'] ?? 'วิทยาการคอมพิวเตอร์'); // ดึงคณะ/สาขา
$gpa = htmlspecialchars($user['gpa'] ?? '3.50');
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <!-- เรียกใช้ Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- เรียกใช้ Font Awesome สำหรับไอคอน -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .card { border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .profile-img { width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand" href="#"><i class="fas fa-graduation-cap"></i> ระบบสารสนเทศนักเรียน</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <span class="nav-link text-white">สวัสดี, <?php echo $firstname; ?></span>
                </li>
                <li class="nav-item">
                    <!-- สมมติว่ามีไฟล์ logout.php สำหรับเคลียร์ session -->
                    <a class="nav-link text-danger fw-bold bg-light rounded px-3 ms-2" href="../../system/logout.php">
                        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container">
    <div class="row">
        <!-- 1. Profile Sidebar -->
        <div class="col-md-4 mb-4">
            <div class="card text-center p-4">
                <div class="mb-3">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($fullname); ?>&background=random&size=128" alt="Profile" class="profile-img">
                </div>
                <h4 class="mb-1"><?php echo $fullname; ?></h4>
                <p class="text-muted mb-2">รหัสนักศึกษา: <?php echo $student_id; ?></p>
                <span class="badge bg-info text-dark mb-3"><?php echo $faculty; ?></span>
                <hr>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary"><i class="fas fa-edit"></i> แก้ไขข้อมูลส่วนตัว</button>
                    <button class="btn btn-outline-secondary"><i class="fas fa-key"></i> เปลี่ยนรหัสผ่าน</button>
                </div>
            </div>
        </div>

        <!-- 2. Dashboard Content -->
        <div class="col-md-8">
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-success text-white p-3">
                        <h6>เกรดเฉลี่ยสะสม (GPAX)</h6>
                        <h2><?php echo $gpa; ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-dark p-3">
                        <h6>หน่วยกิตสะสม</h6>
                        <h2>45 <small class="fs-6 text-muted">/ 120</small></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-primary text-white p-3">
                        <h6>สถานะภาพ</h6>
                        <h2><i class="fas fa-check-circle"></i> ปกติ</h2>
                    </div>
                </div>
            </div>

            <!-- Enrolled Courses Table -->
            <div class="card p-4">
                <h5 class="mb-3"><i class="fas fa-book"></i> รายวิชาที่ลงทะเบียน (ภาคเรียนที่ 1/2569)</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>รหัสวิชา</th>
                                <th>ชื่อวิชา</th>
                                <th>หน่วยกิต</th>
                                <th>กลุ่มเรียน (Sec)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- ข้อมูลจำลอง (Mock Data) ถ้ามีตารางรายวิชาให้ใช้ loop ของ PHP ดึงมาแสดงตรงนี้ -->
                            <tr>
                                <td>CS101</td>
                                <td>วิทยาการคอมพิวเตอร์เบื้องต้น</td>
                                <td>3</td>
                                <td>01</td>
                            </tr>
                            <tr>
                                <td>MA102</td>
                                <td>แคลคูลัส 1</td>
                                <td>3</td>
                                <td>03</td>
                            </tr>
                            <tr>
                                <td>EN101</td>
                                <td>ภาษาอังกฤษเพื่อการสื่อสาร</td>
                                <td>3</td>
                                <td>05</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>