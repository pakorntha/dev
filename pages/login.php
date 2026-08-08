<?php
session_start();
// ถอยหลัง 1 ชั้นเพื่อดึงไฟล์ system/a_func.php
require_once("../system/a_func.php");

// 1. ถ้าล็อกอินค้างไว้อยู่แล้ว ให้ส่งกลับไปหน้า index.php เพื่อให้ระบบแยก Role อัตโนมัติ
if (isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

$error_msg = "";

// 2. เมื่อมีการกดปุ่มเข้าสู่ระบบ (ส่งข้อมูลแบบ POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error_msg = "กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน";
    } else {
        // ดึงข้อมูลผู้ใช้งานจากฐานข้อมูล
        $stmt = dd_q("SELECT * FROM users WHERE username = ? LIMIT 1", [$username]);

        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // ตรวจสอบรหัสผ่าน (รองรับทั้งแบบเข้ารหัส password_hash และแบบตัวหนังสือธรรมดา)
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                
                // สร้าง Session จดจำผู้ใช้
                $_SESSION['id'] = $user['id'];

                // ล็อกอินสำเร็จ -> ส่งกลับไปที่ index.php ให้ Router ทำงานต่อ
                header("Location: ../index.php");
                exit();

            } else {
                $error_msg = "รหัสผ่านไม่ถูกต้อง";
            }
        } else {
            $error_msg = "ไม่พบชื่อผู้ใช้นี้ในระบบ";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card-login {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            background: #ffffff;
        }
        .login-header {
            text-align: center;
            padding: 30px 20px 10px;
        }
        .login-header i {
            font-size: 3rem;
            color: #0d6efd;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px;
        }
        .btn-login {
            border-radius: 10px;
            padding: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="card card-login p-4">
        <div class="login-header">
            <i class="fas fa-user-circle mb-2"></i>
            <h4 class="fw-bold">เข้าสู่ระบบ</h4>
            <p class="text-muted small">ระบบจัดการข้อมูลนักเรียน</p>
        </div>

        <form action="" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label text-muted small fw-bold">ชื่อผู้ใช้ (Username)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" required autocomplete="off">
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label text-muted small fw-bold">รหัสผ่าน (Password)</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-login">
                <i class="fas fa-sign-in-alt me-1"></i> เข้าสู่ระบบ
            </button>
        </form>
    </div>
</div>

<!-- แจ้งเตือนข้อผิดพลาดเมื่อล็อกอินไม่สำเร็จด้วย SweetAlert2 -->
<?php if (!empty($error_msg)): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'เข้าสู่ระบบไม่สำเร็จ',
        text: '<?php echo $error_msg; ?>',
        confirmButtonColor: '#0d6efd'
    });
</script>
<?php endif; ?>

</body>
</html>