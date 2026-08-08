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
<<<<<<< HEAD
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
=======
    <title>Login - SiS4 SCHOOL</title>
    <!-- เรียกใช้งาน Tailwind CSS ผ่าน CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- เรียกใช้งานฟอนต์และไอคอน (ตัวอย่างใช้ FontAwesome แทน Lucide) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4 font-sans text-gray-900">

    <div class="w-full max-w-md bg-white border border-gray-200 rounded-xl shadow-sm p-6 sm:p-8">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold tracking-tight">
                <span class="text-blue-600">SiS4</span>
                <span class="text-gray-900">SCHOOL</span>
            </h1>
            <p class="text-sm text-gray-500 mt-1">ระบบช่วยเหลืองานธุระการคุณครู</p>
        </div>

        <!-- Form -->
        <form class="space-y-5">
            
            <!-- Role Selection -->
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">
                    Role
                </label>
                <div class="grid grid-cols-3 gap-3">
                    <!-- Student (Active) -->
                    <button type="button" class="flex flex-col items-center gap-1.5 py-3 rounded-lg border transition-colors text-xs font-medium border-blue-600 bg-blue-50 text-blue-700 ring-1 ring-blue-600">
                        <i class="fa-solid fa-graduation-cap text-lg"></i>
                        นักเรียน
                    </button>
                    <!-- Teacher -->
                    <button type="button" class="flex flex-col items-center gap-1.5 py-3 rounded-lg border transition-colors text-xs font-medium border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:border-gray-300">
                        <i class="fa-solid fa-users text-lg"></i>
                        ครู / อาจารย์
                    </button>
                    <!-- Admin -->
                    <button type="button" class="flex flex-col items-center gap-1.5 py-3 rounded-lg border transition-colors text-xs font-medium border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:border-gray-300">
                        <i class="fa-solid fa-shield-halved text-lg"></i>
                        ผู้ดูแลระบบ
                    </button>
                </div>
            </div>

            <!-- Username Input -->
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">
                    Username
                </label>
                <div class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2.5 bg-white focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600 transition-shadow">
                    <i class="fa-solid fa-user text-gray-400"></i>
                    <input type="text" placeholder="กรอกรหัสประจำตัว" required
                        class="flex-1 bg-transparent outline-none text-sm placeholder:text-gray-400" autocomplete="username">
                </div>
            </div>

            <!-- Password Input -->
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">
                    Password
                </label>
                <div class="flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2.5 bg-white focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600 transition-shadow">
                    <i class="fa-solid fa-lock text-gray-400"></i>
                    <input type="password" placeholder="กรอกรหัสผ่าน" required
                        class="flex-1 bg-transparent outline-none text-sm placeholder:text-gray-400" autocomplete="current-password">
                    <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <!-- Extra Options -->
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 text-gray-600 cursor-pointer select-none">
                    <input type="checkbox" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                    เข้าระบบอัตโนมัติ
                </label>
                <span class="text-blue-600 hover:text-blue-700 hover:underline cursor-pointer font-medium">
                    ลืมรหัสผ่าน?
                </span>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg flex items-center justify-center gap-2 transition-colors">
                เข้าสู่ระบบ
                <i class="fa-solid fa-arrow-right"></i>
            </button>

            <!-- Footer Link -->
            <div class="text-center text-sm text-gray-500 pt-4 border-t border-gray-100">
                ผู้ปกครองลงทะเบียนใหม่? 
                <a href="/register" class="text-blue-600 hover:text-blue-700 hover:underline font-medium">
                    คลิกที่นี่
                </a>
            </div>
        </form>
    </div>
>>>>>>> 8e2a17c5af7d382cb1730bd60192dbc9c48270ab

</body>
</html>