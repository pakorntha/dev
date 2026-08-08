<?php
session_start();
require_once("../../system/a_func.php");
require_once("../../system/teacher_sidebar.php");

// 1. ตรวจสอบสิทธิ์ผู้ใช้งาน
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// ดึงข้อมูลผู้ใช้งานที่ล็อกอินอยู่
$stmt_user = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
if ($stmt_user->rowCount() === 0) {
    header("Location: ../login.php");
    exit();
}
$user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user_info['prefix'] ?? '') . ' ' . $user_info['firstName'] . ' ' . $user_info['lastName']);
$initial = mb_substr($user_info['firstName'], 0, 1, 'UTF-8');
$userRoleStr = ($user_info['role'] === 'teacher') ? 'ครูผู้สอน' : 'ผู้ดูแลระบบ';

// 2. ดึงข้อมูลห้องเรียน/ระดับชั้น ทั้งหมด
$stmt_classrooms = dd_q("SELECT * FROM classroom ORDER BY name ASC");
$classrooms = $stmt_classrooms->fetchAll(PDO::FETCH_ASSOC);

// 3. รับค่าห้องเรียนที่เลือกจาก GET (ถ้าไม่มี ให้เลือกห้องแรกเป็นค่าเริ่มต้น)
$selected_classroom_id = $_GET['classroom_id'] ?? ($classrooms[0]['id'] ?? '');

// ดึงข้อมูลห้องเรียนที่กำลังเลือกอยู่
$current_classroom_name = "ไม่พบข้อมูลห้องเรียน";
foreach ($classrooms as $cls) {
    if ($cls['id'] === $selected_classroom_id) {
        $current_classroom_name = $cls['name'];
        break;
    }
}

// 4. ดึงรายชื่อนักเรียนในห้องเรียนที่เลือก
$students = [];
if (!empty($selected_classroom_id)) {
    $stmt_students = dd_q("
        SELECT u.username, u.prefix, u.firstName, u.lastName, sp.grade
        FROM studentprofile sp
        INNER JOIN user u ON sp.userId = u.id
        WHERE sp.classroomId = ? AND u.role = 'student'
        ORDER BY u.firstName ASC
    ", [$selected_classroom_id]);
    
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นักเรียนและห้องเรียน - SiS4 SCHOOL</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* สไตล์พิเศษสำหรับการพิมพ์ (Print) */
        @media print {
            @page { margin: 1.5cm; size: A4 portrait; }
            body { background-color: #fff; }
            /* ซ่อน Sidebar, Header และปุ่มต่างๆ */
            aside, header, .no-print { display: none !important; }
            /* ขยาย Main ให้เต็มจอ */
            main { padding: 0 !important; overflow: visible !important; width: 100% !important; }
            /* ปรับตารางให้เส้นขอบชัดเจนขึ้นตอนพิมพ์ */
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #cbd5e1 !important; padding: 10px !important; color: #000 !important; }
            th { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; }
            /* ลบเงาและกรอบโค้งออกให้ดูเป็นทางการ */
            .print-card { box-shadow: none !important; border: none !important; }
        }
    </style>
</head>
<body class="bg-[#F8FAFC] text-gray-800 h-screen overflow-hidden flex">

    <?php sis4_teacher_sidebar_render($fullName, $initial, $userRoleStr, '../../system/logout.php', 'print:hidden'); ?>

    <!-- ==================== MAIN CONTENT ==================== -->
    <div class="flex-1 flex flex-col min-w-0">
        
        <!-- Top Header (ซ่อนตอนพิมพ์) -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0 print:hidden">
            <div class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center text-sm">
                    <i class="fa-solid fa-users"></i>
                </div>
                ข้อมูลนักเรียนและห้องเรียน
            </div>
            <div class="flex items-center gap-4">
                <div class="text-sm font-medium text-gray-700 bg-gray-50 px-3 py-1.5 rounded-full border border-gray-200">
                    <i class="fa-regular fa-calendar text-blue-500 me-1"></i> ปีการศึกษา 2569
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            
            <div class="mb-6 flex justify-between items-end no-print">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">ทะเบียนรายชื่อนักเรียน</h2>
                    <p class="text-sm text-gray-500 mt-1">เลือกระดับชั้นและห้องเรียน เพื่อตรวจสอบและพิมพ์รายชื่อ</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                
                <!-- เลือกระดับชั้น (ซ่อนตอนพิมพ์) -->
                <div class="lg:col-span-1 flex flex-col gap-6 no-print">
                    <div class="bg-white border border-gray-200 rounded shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/80">
                            <h3 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                                <i class="fa-solid fa-layer-group text-blue-600"></i> เลือกห้องเรียน
                            </h3>
                        </div>
                        <div class="p-3 max-h-[60vh] overflow-y-auto custom-scrollbar">
                            <?php if (count($classrooms) > 0): ?>
                                <ul class="space-y-1.5">
                                    <?php foreach ($classrooms as $cls): ?>
                                        <?php $isActive = ($cls['id'] === $selected_classroom_id); ?>
                                        <li>
                                            <a href="?classroom_id=<?php echo urlencode($cls['id']); ?>" 
                                               class="flex justify-between items-center px-4 py-3 rounded    text-sm transition-all duration-200 <?php echo $isActive ? 'bg-blue-600 text-white shadow-md shadow-blue-200' : 'text-gray-600 hover:bg-blue-50 hover:text-blue-700 border border-transparent'; ?>">
                                                <span class="font-medium">
                                                    <i class="fa-solid fa-door-open <?php echo $isActive ? 'text-blue-200' : 'text-gray-400'; ?> me-2"></i> ห้อง <?php echo htmlspecialchars($cls['name']); ?>
                                                </span>
                                                <i class="fa-solid fa-chevron-right text-[10px] <?php echo $isActive ? 'text-white' : 'text-gray-300'; ?>"></i>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-center text-gray-500 py-6 text-sm">ยังไม่มีข้อมูลห้องเรียน</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ตารางรายชื่อนักเรียน -->
                <div class="lg:col-span-3">
                    
                    <!-- ส่วนหัวที่จะแสดงเฉพาะตอนพิมพ์ (Print Header) -->
                    <div class="hidden print:block text-center mb-8">
                        <h1 class="text-2xl font-bold text-black mb-2">ใบรายชื่อนักเรียน</h1>
                        <h2 class="text-lg text-gray-800">ชั้น/ห้อง: <?php echo htmlspecialchars($current_classroom_name); ?></h2>
                        <p class="text-sm text-gray-600 mt-1">ภาคเรียนที่ 1 ปีการศึกษา 2569</p>
                    </div>

                    <div class="bg-white border border-gray-200 shadow-sm flex flex-col h-full print-card overflow-hidden">
                        
                        <!-- Header ของ Card -->
                        <div class="px-6 py-5 border-b border-gray-100 bg-white flex flex-wrap justify-between items-center gap-4 no-print">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg shadow-sm border border-indigo-100">
                                    <i class="fa-solid fa-chalkboard-user"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 text-lg leading-tight">
                                        ห้อง <?php echo htmlspecialchars($current_classroom_name); ?>
                                    </h3>
                                    <p class="text-xs text-gray-500 mt-0.5">จำนวนทั้งหมด <?php echo count($students); ?> คน</p>
                                </div>
                            </div>
                            
                            <!-- ปุ่มพิมพ์ -->
                            <?php if (count($students) > 0): ?>
                            <button onclick="window.print()" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 hover:text-blue-600 hover:border-blue-300 px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 shadow-sm flex items-center gap-2">
                                <i class="fa-solid fa-print"></i> พิมพ์รายชื่อ
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- ตาราง -->
                        <div class="overflow-x-auto flex-1">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50/80 text-gray-500 border-b border-gray-200 text-xs uppercase tracking-wider">
                                    <tr>
                                        <th class="px-6 py-4 font-semibold w-16 text-center">เลขที่</th>
                                        <!-- <th class="px-6 py-4 font-semibold">รหัสนักเรียน</th> -->
                                        <th class="px-6 py-4 font-semibold">ชื่อ - นามสกุล</th>
                                        <th class="px-6 py-4 font-semibold text-center no-print">เกรดเฉลี่ย (GPA)</th>
                                        <!-- ช่องว่างสำหรับให้ครูขีดเขียนตอนพิมพ์ -->
                                        <th class="px-6 py-4 font-semibold text-center hidden print:table-cell w-32">หมายเหตุ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (count($students) > 0): ?>
                                        <?php foreach ($students as $index => $std): ?>
                                            <?php 
                                                // สกัดตัวอักษรแรกเพื่อทำ Avatar
                                                $stdInitial = mb_substr($std['firstName'] ?? '?', 0, 1, 'UTF-8'); 
                                                // สลับสี Avatar พื้นหลังเบาๆ
                                                $colors = ['bg-blue-100 text-blue-600', 'bg-indigo-100 text-indigo-600', 'bg-purple-100 text-purple-600', 'bg-emerald-100 text-emerald-600'];
                                                $avatarColor = $colors[$index % 4];
                                            ?>
                                            <tr class="hover:bg-blue-50/50 transition-colors group">
                                                <td class="px-6 py-3 text-center text-gray-500 font-medium"><?php echo $index + 1; ?></td>
                                                <!-- <td class="px-6 py-3">
                                                    <span class="font-mono text-xs text-gray-600 bg-gray-100/80 px-2 py-1 rounded border border-gray-200 group-hover:bg-white group-hover:border-blue-200 transition-colors print:border-none print:bg-transparent print:p-0">
                                                        
                                                    </span>
                                                </td> -->
                                                <td class="px-6 py-3">
                                                    <div class="flex items-center gap-3">
                                                        <!-- Avatar (ซ่อนตอนพิมพ์) -->
                                                        <div class="w-8 h-8 rounded <?php echo $avatarColor; ?> flex items-center justify-center text-xs font-bold shadow-sm no-print">
                                                            <?php echo htmlspecialchars($stdInitial); ?>
                                                        </div>
                                                        <span class="font-medium text-gray-900">
                                                            <?php echo htmlspecialchars(trim(($std['prefix'] ?? '') . ' ' . $std['firstName'] . ' ' . $std['lastName'])); ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-3 text-center no-print">
                                                    <span class="inline-flex items-center justify-center bg-emerald-50 text-emerald-700 font-bold px-3 py-1 rounded-full border border-emerald-100 shadow-sm">
                                                        <?php echo htmlspecialchars($std['grade'] ?? '0.00'); ?>
                                                    </span>
                                                </td>
                                                <!-- ช่องว่างสำหรับเขียนตอนพิมพ์ -->
                                                <td class="px-6 py-3 hidden print:table-cell"></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-16 no-print">
                                                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-300 text-3xl border border-gray-100">
                                                    <i class="fa-solid fa-user-astronaut"></i>
                                                </div>
                                                <p class="text-gray-900 font-medium text-lg">ไม่พบรายชื่อนักเรียน</p>
                                                <p class="text-gray-500 text-sm mt-1">ยังไม่มีการเพิ่มนักเรียนเข้าสู่ระบบในห้องเรียนนี้</p>
                                            </td>
                                            <td colspan="4" class="text-center py-10 hidden print:table-cell">ไม่มีรายชื่อนักเรียน</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="h-6"></div>
        </main>
    </div>

</body>
</html>