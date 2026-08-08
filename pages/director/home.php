<?php
session_start();
require_once("../../system/a_func.php");
require_once("../../system/director_sidebar.php");

// 1. ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// 2. ดึงข้อมูลผู้ใช้งาน
$stmt = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
if ($stmt->rowCount() === 1) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user['role'] !== 'director') {
        header("Location: ../../index.php"); 
        exit();
    }
    $fullName = $user['prefix'] . $user['firstName'] . ' ' . $user['lastName'];
    $initial = mb_substr($user['firstName'], 0, 1, 'UTF-8');
    $schoolName = "โรงเรียนตัวอย่าง"; 
    $academicYear = "2569";
} else {
    session_destroy();
    header("Location: ../../systemlogin.php");
    exit();
}

// ------------------------------------------------------------------
// ส่วนที่ 2: ดึงข้อมูลสถิติแยกตามตารางที่มีในระบบ
// ------------------------------------------------------------------
$pendingLeaves = dd_q("SELECT COUNT(*) FROM leave_requests WHERE directorStatus = 'รอการอนุมัติ'")->fetchColumn();
$pendingTrips = dd_q("SELECT COUNT(*) FROM official_trips WHERE director_status = 'pending'")->fetchColumn();
$pendingPlans = dd_q("SELECT COUNT(*) FROM lesson_plans WHERE status = 'pending'")->fetchColumn();
$statTotalPending = $pendingLeaves + $pendingTrips + $pendingPlans;

// ------------------------------------------------------------------
// ส่วนที่ 3: ดึงข้อมูลงานรอพิจารณา (รวมจาก 3 ตาราง)
// ------------------------------------------------------------------
// 3.1 ใบลา
$leaves = dd_q("
    SELECT id, 'ใบลา' as category, leaveType as subject, teacherName as sender, 
    DATE_FORMAT(createdAt, '%d %b %y') as doc_date_th, createdAt as sort_date, directorStatus as status 
    FROM leave_requests 
    WHERE directorStatus = 'รอการอนุมัติ'
")->fetchAll(PDO::FETCH_ASSOC);

// 3.2 ไปราชการ
$trips = dd_q("
    SELECT id, 'ไปราชการ' as category, subject, teacher_name as sender, 
    DATE_FORMAT(created_at, '%d %b %y') as doc_date_th, created_at as sort_date, director_status as status 
    FROM official_trips 
    WHERE director_status = 'pending'
")->fetchAll(PDO::FETCH_ASSOC);

// 3.3 แผนการสอน (Join กับ user เพื่อดึงชื่อครู)
$plans = dd_q("
    SELECT p.id, 'แผนการสอน' as category, p.unitName as subject, 
    CONCAT(u.prefix, u.firstName, ' ', u.lastName) as sender, 
    DATE_FORMAT(p.createdAt, '%d %b %y') as doc_date_th, p.createdAt as sort_date, p.status 
    FROM lesson_plans p 
    LEFT JOIN user u ON p.teacherId = u.id 
    WHERE p.status = 'pending'
")->fetchAll(PDO::FETCH_ASSOC);

// นำข้อมูลทั้ง 3 ตารางมารวมกัน และเรียงลำดับตามวันที่สร้างล่าสุด
$documents = array_merge($leaves, $trips, $plans);
usort($documents, function($a, $b) {
    return strtotime($b['sort_date']) - strtotime($a['sort_date']);
});
$documents = array_slice($documents, 0, 5); // เอาแค่ 5 รายการล่าสุด

// ------------------------------------------------------------------
// ส่วนที่ 4: ดึงข้อมูลกำหนดการ (วันที่ไปราชการ หรือ วันที่ลา) ที่กำลังจะมาถึง
// ------------------------------------------------------------------
$up_trips = dd_q("
    SELECT subject as title, CONCAT('ไปราชการ: ', teacher_name) as description, 
    DATE_FORMAT(start_date, '%d %b %Y') as date_text, 
    DATEDIFF(start_date, CURDATE()) as days_left, 'indigo' as color_theme, start_date as sort_date
    FROM official_trips 
    WHERE start_date >= CURDATE() AND status != 'canceled'
")->fetchAll(PDO::FETCH_ASSOC);

$up_leaves = dd_q("
    SELECT leaveType as title, CONCAT('ผู้ลา: ', teacherName) as description, 
    DATE_FORMAT(startDate, '%d %b %Y') as date_text, 
    DATEDIFF(startDate, CURDATE()) as days_left, 'rose' as color_theme, startDate as sort_date
    FROM leave_requests 
    WHERE startDate >= CURDATE() AND status != 'rejected'
")->fetchAll(PDO::FETCH_ASSOC);

$schedules = array_merge($up_trips, $up_leaves);
usort($schedules, function($a, $b) {
    return strtotime($a['sort_date']) - strtotime($b['sort_date']);
});
$schedules = array_slice($schedules, 0, 4);

function getGreeting() {
    $hour = date('H');
    if ($hour < 12) return "สวัสดีตอนเช้า";
    if ($hour < 17) return "สวัสดีตอนบ่าย";
    return "สวัสดีตอนเย็น";
}
$greeting = getGreeting();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดผู้อำนวยการ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Prompt', sans-serif; } </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 h-screen overflow-hidden flex">

     <?php sis4_direcetor_sidebar_render($fullName, $initial); ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8 shrink-0">
            <div class="w-1/3">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" placeholder="ค้นหา..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm outline-none">
                </div>
            </div>
            <div class="flex items-center gap-6">
                <button class="relative text-slate-500 hover:text-indigo-600">
                    <i class="fa-regular fa-bell text-xl"></i>
                    <span class="absolute -top-1.5 -right-1.5 bg-rose-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center"><?= htmlspecialchars($statTotalPending) ?></span>
                </button>
                <div class="flex items-center gap-3 pl-6 border-l border-slate-200">
                    <div class="w-9 h-9 rounded-full bg-teal-500 text-white flex items-center justify-center font-semibold text-sm">
                        <?= htmlspecialchars($initial) ?>
                    </div>
                    <div class="text-xs">
                        <p class="font-bold text-slate-800"><?= htmlspecialchars($fullName) ?></p>
                        <p class="text-slate-500">ผู้อำนวยการ</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 md:p-8 space-y-6">
            
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl">
                        <i class="fa-solid fa-border-all"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800"><?= $greeting ?> <?= htmlspecialchars($fullName) ?></h2>
                        <p class="text-sm text-slate-500 mt-1">ผู้อำนวยการ &middot; <?= htmlspecialchars($schoolName) ?></p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid ปรับตามตารางจริง -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
                    <p class="text-sm text-slate-500 font-medium mb-1">งานรออนุมัติรวม</p>
                    <p class="text-3xl font-bold text-indigo-600 mb-1"><?= htmlspecialchars($statTotalPending) ?></p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
                    <p class="text-sm text-slate-500 font-medium mb-1">ใบลาที่รออนุมัติ</p>
                    <p class="text-3xl font-bold text-rose-500 mb-1"><?= htmlspecialchars($pendingLeaves) ?></p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
                    <p class="text-sm text-slate-500 font-medium mb-1">ไปราชการที่รออนุมัติ</p>
                    <p class="text-3xl font-bold text-amber-500 mb-1"><?= htmlspecialchars($pendingTrips) ?></p>
                </div>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden">
                    <p class="text-sm text-slate-500 font-medium mb-1">แผนการสอนรออนุมัติ</p>
                    <p class="text-3xl font-bold text-emerald-500 mb-1"><?= htmlspecialchars($pendingPlans) ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <!-- Pending List -->
                <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h3 class="font-bold text-slate-800 text-base">รายการที่รอพิจารณา (ล่าสุด)</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs text-slate-500 bg-slate-50/50">
                                <tr>
                                    <th class="px-5 py-3 font-medium">หมวดหมู่</th>
                                    <th class="px-5 py-3 font-medium">เรื่อง</th>
                                    <th class="px-5 py-3 font-medium">ผู้ส่ง</th>
                                    <th class="px-5 py-3 font-medium">วันที่</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if(empty($documents)): ?>
                                <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">ไม่มีรายการรอพิจารณา</td></tr>
                                <?php else: ?>
                                    <?php foreach($documents as $doc): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-5 py-4">
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-700">
                                                <?= htmlspecialchars($doc['category']) ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 font-medium text-slate-800"><?= htmlspecialchars($doc['subject']) ?></td>
                                        <td class="px-5 py-4 text-slate-600"><?= htmlspecialchars($doc['sender']) ?></td>
                                        <td class="px-5 py-4 text-slate-500 text-xs"><?= htmlspecialchars($doc['doc_date_th']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Timeline / Schedules -->
                <div class="lg:col-span-1 bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
                    <div class="flex items-center gap-2 mb-5">
                        <i class="fa-regular fa-calendar-check text-amber-500"></i>
                        <h3 class="font-bold text-slate-800 text-base">กำหนดการใกล้ถึง</h3>
                    </div>
                    
                    <div class="space-y-4 pl-4 border-l-2 border-slate-100">
                        <?php if(empty($schedules)): ?>
                            <p class="text-sm text-slate-500 py-4">ไม่มีกำหนดการในระยะนี้</p>
                        <?php else: ?>
                            <?php foreach($schedules as $task): ?>
                            <?php $days_text = ($task['days_left'] == 0) ? "วันนี้" : "อีก " . $task['days_left'] . " วัน"; ?>
                            <div class="relative">
                                <div class="absolute left-0 w-2.5 h-2.5 bg-<?= htmlspecialchars($task['color_theme']) ?>-500 rounded-full -ml-[21px] mt-1.5 border-2 border-white"></div>
                                <h4 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($task['title']) ?></h4>
                                <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($task['description']) ?></p>
                                <p class="text-[11px] text-slate-400 mt-1">
                                    <?= htmlspecialchars($task['date_text']) ?> &middot; 
                                    <span class="text-<?= htmlspecialchars($task['color_theme']) ?>-600 font-medium"><?= $days_text ?></span>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>