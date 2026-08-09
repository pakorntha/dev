<?php
session_start();
// เรียกใช้ไฟล์ฟังก์ชันเชื่อมต่อฐานข้อมูล
require_once("../../system/a_func.php");
require_once("../../system/teacher_sidebar.php");

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// 1. ดึงข้อมูลครูที่ล็อกอิน
$stmt = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
if ($stmt->rowCount() === 1) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user['role'] !== 'teacher') {
        header("Location: ../../index.php"); 
        exit();
    }
    $fullName = $user['prefix'] . $user['firstName'] . ' ' . $user['lastName'];
    $initial = mb_substr($user['firstName'], 0, 1, 'UTF-8');
    
    // กำหนดห้องที่ครูเป็นที่ปรึกษา (อ้างอิงจากตาราง classroom ในฐานข้อมูลของคุณ)
    // ในอนาคตคุณสามารถทำตาราง advisor เพื่อดึงค่านี้แบบไดนามิกได้
    $teacher_room = "ม.6/1"; 
} else {
    session_destroy();
    header("Location: ../../systemlogin.php");
    exit();
}

// 2. ดึงรายชื่อนักเรียนในห้องที่ปรึกษา พร้อมสถานะการทำแบบประเมินและรหัสผ่าน
// ทำการ JOIN ตาราง user -> studentprofile -> classroom
$students_stmt = dd_q("
    SELECT 
        u.id, 
        u.prefix, 
        u.firstName, 
        u.lastName,
        c.name as room_name,
        COALESCE(sa.status, 'ยังไม่ตอบ') as assess_status,
        COALESCE(sa.access_code, 'ยังไม่ได้สร้างรหัส') as access_code,
        COALESCE(sa.care_level, '-') as care_level
    FROM user u
    INNER JOIN studentprofile sp ON u.id = sp.userId
    INNER JOIN classroom c ON sp.classroomId = c.id
    LEFT JOIN student_assessments sa ON u.id = sa.student_id AND sa.assessment_name = 'ประเมินความเครียดก่อนสอบปลายภาค 2569'
    WHERE u.role = 'student' AND c.name = ?
    ORDER BY u.firstName ASC
", [$teacher_room]);

$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// คำนวณสถิติ
$total_students = count($students);
$completed = 0;
foreach ($students as $s) {
    if ($s['assess_status'] === 'ตอบแล้ว') $completed++;
}
$pending = $total_students - $completed;
$percent_completed = $total_students > 0 ? round(($completed / $total_students) * 100) : 0;

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบดูแลสุขภาวะนักเรียน - SiS4 SCHOOL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .tab-active { color: #4f46e5; border-bottom: 2px solid #4f46e5; font-weight: 600; }
        .tab-inactive { color: #6b7280; border-bottom: 2px solid transparent; }
        .tab-inactive:hover { color: #374151; border-color: #d1d5db; }
        nav {
            -ms-overflow-style: none; /* IE and Edge */
            scrollbar-width: none; /* Firefox */
        }
        nav::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-gray-800 h-screen overflow-hidden flex">

    <!-- Sidebar -->
    <?php sis4_teacher_sidebar_render($fullName, $initial); ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0">
        
        <!-- Header -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <div class="relative w-96 hidden sm:block">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" placeholder="ค้นหา..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500">
            </div>
            <div class="flex items-center gap-4 ml-auto">
                <div class="text-sm font-medium text-gray-700 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-orange-500 text-white flex items-center justify-center"><?= $initial ?></div>
                    <?= htmlspecialchars($fullName) ?> <br><span class="text-xs text-gray-400 font-normal">ครู</span>
                </div>
            </div>
        </header>

        <!-- Main Scrollable -->
        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- Tabs Navigation -->
            <div class="border-b border-gray-200 flex gap-6 px-2">
                <a href="#" class="pb-3 px-1 tab-inactive text-sm">ภาพรวม</a>
                <a href="#" class="pb-3 px-1 tab-active text-sm">ห้องที่ปรึกษา</a>
                <a href="#" class="pb-3 px-1 tab-inactive text-sm">เคสที่ดูแล</a>
            </div>

            <!-- Page Title & Actions -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-lg">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">ห้องที่ปรึกษา · <?= htmlspecialchars($teacher_room) ?></h1>
                        <p class="text-sm text-gray-500 mt-0.5">แสดงเฉพาะนักเรียนในห้องที่คุณรับผิดชอบ และไม่แสดงคำตอบรายข้อของนักเรียน</p>
                    </div>
                </div>
                <button class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-print"></i> พิมพ์รหัสเข้าทำแบบประเมิน
                </button>
            </div>

            <!-- Info Alerts -->
            <div class="space-y-3">
                <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 flex gap-3 items-start text-sm text-blue-800">
                    <i class="fa-solid fa-circle-info mt-0.5 text-blue-500"></i>
                    <p>แบบประเมินนี้เป็นเครื่องมือคัดกรองเบื้องต้นเพื่อช่วยให้ครูดูแลนักเรียนได้ทันเวลา ไม่ใช่การวินิจฉัยโรค ผลที่ได้ไม่ใช่ข้อสรุปว่านักเรียนมีความผิดปกติ การประเมินที่แน่ชัดต้องทำโดยบุคลากรทางการแพทย์หรือผู้เชี่ยวชาญด้านสุขภาพจิต</p>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-3 flex gap-3 items-start text-sm text-gray-600">
                    <i class="fa-solid fa-shield-halved mt-0.5 text-gray-400"></i>
                    <p><strong>ขอบเขตข้อมูลที่คุณเข้าถึงได้:</strong> ครูที่ปรึกษา 1 ห้อง — ทุกครั้งที่เปิดดูข้อมูลรายบุคคล ระบบจะบันทึกไว้ในประวัติการเข้าถึง</p>
                </div>
            </div>

            <!-- Assessment Tabs (Pills) -->
            <div class="flex gap-2">
                <button class="px-4 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-full">ประเมินความเครียดก่อนสอบปลายภาค 2569</button>
                <button class="px-4 py-1.5 bg-white border border-gray-200 text-gray-600 text-sm font-medium rounded-full hover:bg-gray-50">คัดกรองภาวะซึมเศร้า ภาคเรียนที่ 1/2569</button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium mb-1">นักเรียนในห้อง</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $total_students ?></p>
                    </div>
                    <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center"><i class="fa-solid fa-user-group"></i></div>
                </div>
                
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium mb-1">ตอบแบบประเมินแล้ว</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $completed ?></p>
                        <p class="text-xs text-gray-400 mt-1"><?= $percent_completed ?>%</p>
                    </div>
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center"><i class="fa-regular fa-circle-check"></i></div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium mb-1">ยังไม่ตอบ</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $pending ?></p>
                        <p class="text-xs text-gray-400 mt-1">ปิดรับ 17 ส.ค. 69</p>
                    </div>
                    <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center"><i class="fa-regular fa-circle"></i></div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium mb-1">ควรได้รับการติดตาม</p>
                        <p class="text-2xl font-bold text-gray-900">0</p>
                        <p class="text-xs text-gray-400 mt-1">เคสที่ยังไม่ปิด 0 เคส</p>
                    </div>
                    <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center"><i class="fa-solid fa-clipboard-list"></i></div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <p class="text-sm text-gray-600">ประเมินความเครียดก่อนสอบปลายภาค 2569 · แบบประเมินความเครียด 5 ข้อ (ST-5)</p>
                    <span class="bg-emerald-50 text-emerald-600 text-xs px-2 py-1 rounded border border-emerald-200 font-medium">กำลังเปิดให้ทำ</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2.5 mb-2 mt-4">
                    <div class="bg-emerald-500 h-2.5 rounded-full" style="width: <?= $percent_completed ?>%"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-500">
                    <div class="flex gap-4">
                        <span class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-emerald-500"></div> ปกติ</span>
                        <span class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-sky-400"></div> ติดตาม</span>
                        <span class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-amber-400"></div> ประเมินเพิ่ม</span>
                        <span class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-purple-400"></div> ส่งต่อ</span>
                        <span class="flex items-center gap-1"><div class="w-2 h-2 rounded-full bg-red-500"></div> เร่งด่วน</span>
                    </div>
                    <span>ปกติ <?= $completed ?></span>
                </div>
            </div>

            <!-- Student List Table -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-gray-900 text-base">รายชื่อนักเรียนและสถานะ</h3>
                    <p class="text-xs text-gray-500">แสดงระดับการดูแลเท่านั้น ไม่แสดงคำตอบรายข้อของนักเรียน</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-5 py-3 font-medium">เลขที่</th>
                                <th class="px-5 py-3 font-medium">ชื่อ - สกุล</th>
                                <th class="px-5 py-3 font-medium">รหัสเข้าสอบ</th>
                                <th class="px-5 py-3 font-medium">สถานะการตอบ</th>
                                <th class="px-5 py-3 font-medium text-center">ระดับการดูแล</th>
                                <th class="px-5 py-3 font-medium text-center">การติดตาม</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-gray-500">ไม่พบรายชื่อนักเรียนในห้องที่ปรึกษาของคุณ</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $index => $s): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-4 text-gray-500"><?= htmlspecialchars($s['student_number'] ?? ($index + 1)) ?></td>
                                    <td class="px-5 py-4 font-medium text-gray-800">
                                        <?= htmlspecialchars($s['prefix'] . $s['firstName'] . ' ' . $s['lastName']) ?>
                                    </td>
                                    <td class="px-5 py-4">
                                        <code class="bg-indigo-50 text-indigo-700 px-2 py-1 rounded border border-indigo-100 font-mono text-xs">
                                            <?= htmlspecialchars($s['access_code']) ?>
                                        </code>
                                    </td>
                                    <td class="px-5 py-4">
                                        <?php if ($s['assess_status'] === 'ตอบแล้ว'): ?>
                                            <span class="flex items-center gap-2 text-emerald-600"><i class="fa-solid fa-circle-check"></i> ตอบแล้ว</span>
                                        <?php else: ?>
                                            <span class="flex items-center gap-2 text-gray-400"><i class="fa-regular fa-circle"></i> ยังไม่ตอบ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-center text-gray-400"><?= htmlspecialchars($s['care_level']) ?></td>
                                    <td class="px-5 py-4 text-center text-gray-400">—</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="h-8"></div>
        </main>
    </div>

</body>
</html>