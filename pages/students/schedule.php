<?php
session_start();
require_once("../../system/a_func.php");

// 1. ตรวจสอบการล็อกอิน
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// 2. ดึงข้อมูลผู้ใช้งานที่ล็อกอิน
$stmt_user = dd_q("SELECT * FROM user WHERE id = ? AND role = 'student' LIMIT 1", [$_SESSION['id']]);
if ($stmt_user->rowCount() === 0) {
    header("Location: ../../index.php");
    exit();
}
$student = $stmt_user->fetch(PDO::FETCH_ASSOC);
$studentName = trim(($student['prefix'] ?? '') . ' ' . $student['firstName'] . ' ' . $student['lastName']);
$initial = mb_substr($student['firstName'], 0, 1, 'UTF-8');

// =========================================================================
// 3. ข้อมูลจำลอง (MOCK DATA) - ออกแบบตามโครงสร้างที่จะใช้เก็บใน Database จริง
// =========================================================================
/*
  [แนวคิดการทำตารางใน Database ในอนาคต]:
  CREATE TABLE `schedule` (
    `id` int AUTO_INCREMENT PRIMARY KEY,
    `courseId` varchar(191) NOT NULL,
    `classroomId` varchar(191) NOT NULL,
    `dayNum` int NOT NULL, -- 1=จันทร์, 2=อังคาร, 3=พุธ, 4=พฤหัสบดี, 5=ศุกร์
    `startTime` time NOT NULL,
    `endTime` time NOT NULL,
    `room` varchar(100) NOT NULL
  );
*/

// โครงสร้าง Mock Data ตัวอย่าง
$mock_schedules = [
    // --- วันจันทร์ (dayNum = 1) ---
    [
        'id' => 101,
        'dayNum' => 1,
        'dayName' => 'วันจันทร์',
        'startTime' => '08:30',
        'endTime' => '10:10',
        'courseCode' => 'CS101',
        'courseName' => 'วิทยาการคอมพิวเตอร์เบื้องต้น',
        'room' => 'ห้องปฏิบัติการคอมพิวเตอร์ 301',
        'teacherName' => 'ดร.สมชาย ใจดี',
        'badgeColor' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'accentColor' => 'border-l-yellow-500'
    ],
    [
        'id' => 102,
        'dayNum' => 1,
        'dayName' => 'วันจันทร์',
        'startTime' => '10:20',
        'endTime' => '12:00',
        'courseCode' => 'MA102',
        'courseName' => 'แคลคูลัส 1',
        'room' => 'ห้อง 402 อาคารเรียนรวม',
        'teacherName' => 'อ.วิภาวรรณ สุขเสริฐ',
        'badgeColor' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'accentColor' => 'border-l-yellow-500'
    ],

    // --- วันอังคาร (dayNum = 2) ---
    [
        'id' => 103,
        'dayNum' => 2,
        'dayName' => 'วันอังคาร',
        'startTime' => '08:30',
        'endTime' => '11:00',
        'courseCode' => 'SC103',
        'courseName' => 'ฟิสิกส์ทั่วไปและปฏิบัติการ',
        'room' => 'ห้องปฏิบัติการฟิสิกส์ 2',
        'teacherName' => 'ผศ.ดร.ประเสริฐ วิชาการ',
        'badgeColor' => 'bg-pink-100 text-pink-800 border-pink-300',
        'accentColor' => 'border-l-pink-500'
    ],
    [
        'id' => 104,
        'dayNum' => 2,
        'dayName' => 'วันอังคาร',
        'startTime' => '13:00',
        'endTime' => '15:30',
        'courseCode' => 'EN101',
        'courseName' => 'ภาษาอังกฤษเพื่อการสื่อสาร',
        'room' => 'ห้อง 501',
        'teacherName' => 'Teacher Michael Scott',
        'badgeColor' => 'bg-pink-100 text-pink-800 border-pink-300',
        'accentColor' => 'border-l-pink-500'
    ],

    // --- วันพุธ (dayNum = 3) ---
    [
        'id' => 105,
        'dayNum' => 3,
        'dayName' => 'วันพุธ',
        'startTime' => '09:20',
        'endTime' => '12:00',
        'courseCode' => 'CS102',
        'courseName' => 'การเขียนโปรแกรมเชิงโครงสร้าง',
        'room' => 'LAB Com 102',
        'teacherName' => 'ดร.สมชาย ใจดี',
        'badgeColor' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'accentColor' => 'border-l-emerald-500'
    ],

    // --- วันพฤหัสบดี (dayNum = 4) ---
    [
        'id' => 106,
        'dayNum' => 4,
        'dayName' => 'วันพฤหัสบดี',
        'startTime' => '08:30',
        'endTime' => '10:10',
        'courseCode' => 'TH101',
        'courseName' => 'ภาษาไทยเพื่อการเสนอผลงาน',
        'room' => 'ห้อง 204',
        'teacherName' => 'อ.ปรียาพร นามงาม',
        'badgeColor' => 'bg-orange-100 text-orange-800 border-orange-300',
        'accentColor' => 'border-l-orange-500'
    ],
    [
        'id' => 107,
        'dayNum' => 4,
        'dayName' => 'วันพฤหัสบดี',
        'startTime' => '10:20',
        'endTime' => '12:00',
        'courseCode' => 'MA102',
        'courseName' => 'แคลคูลัส 1 (สัมมนา)',
        'room' => 'ห้อง 302',
        'teacherName' => 'อ.วิภาวรรณ สุขเสริฐ',
        'badgeColor' => 'bg-orange-100 text-orange-800 border-orange-300',
        'accentColor' => 'border-l-orange-500'
    ],

    // --- วันศุกร์ (dayNum = 5) ---
    [
        'id' => 108,
        'dayNum' => 5,
        'dayName' => 'วันศุกร์',
        'startTime' => '13:00',
        'endTime' => '16:00',
        'courseCode' => 'PE101',
        'courseName' => 'พลศึกษาและสุขศึกษา',
        'room' => 'อาคารพลศึกษา / สนามกีฬา',
        'teacherName' => 'อ.เกรียงไกร ชัยชนะ',
        'badgeColor' => 'bg-blue-100 text-blue-800 border-blue-300',
        'accentColor' => 'border-l-blue-500'
    ],
];

/* 
// =========================================================================
// [เตรียมไว้ใช้งานในอนาคต] - ตัวอย่างคำสั่ง SQL เมื่อนำ Database จริงมาต่อ
// =========================================================================
$stmt_sp = dd_q("SELECT classroomId FROM studentprofile WHERE userId = ? LIMIT 1", [$student['id']]);
$sp = $stmt_sp->fetch(PDO::FETCH_ASSOC);

if ($sp && !empty($sp['classroomId'])) {
    $sql = "
        SELECT 
            s.id, s.dayNum, s.startTime, s.endTime, s.room,
            c.code AS courseCode, c.name AS courseName,
            CONCAT(IFNULL(u.prefix, ''), ' ', u.firstName, ' ', u.lastName) AS teacherName
        FROM schedule s
        INNER JOIN course c ON s.courseId = c.id
        LEFT JOIN user u ON c.teacherId = u.id
        WHERE s.classroomId = ?
        ORDER BY s.dayNum ASC, s.startTime ASC
    ";
    $stmt_sched = dd_q($sql, [$sp['classroomId']]);
    $real_schedules = $stmt_sched->fetchAll(PDO::FETCH_ASSOC);

    // Map ข้อมูลใส่ $mock_schedules หากมีข้อมูลจริง
    if (count($real_schedules) > 0) {
        // ... นำค่ามาใช้งานแทน Mock Data
    }
}
*/

// จัดกลุ่มข้อมูลตามวัน (1-5)
$days_map = [
    1 => ['name' => 'วันจันทร์', 'color' => 'border-yellow-400', 'bg' => 'bg-yellow-50', 'badge' => 'bg-yellow-500'],
    2 => ['name' => 'วันอังคาร', 'color' => 'border-pink-400', 'bg' => 'bg-pink-50', 'badge' => 'bg-pink-500'],
    3 => ['name' => 'วันพุธ', 'color' => 'border-emerald-400', 'bg' => 'bg-emerald-50', 'badge' => 'bg-emerald-500'],
    4 => ['name' => 'วันพฤหัสบดี', 'color' => 'border-orange-400', 'bg' => 'bg-orange-50', 'badge' => 'bg-orange-500'],
    5 => ['name' => 'วันศุกร์', 'color' => 'border-blue-400', 'bg' => 'bg-blue-50', 'badge' => 'bg-blue-500'],
];

// จัดการ Filter ตัวเลือกวันในหน้า UI
$selected_day = isset($_GET['day']) ? (int)$_GET['day'] : 0; // 0 = แสดงทุกวัน

$filtered_schedules = [];
foreach ($mock_schedules as $item) {
    if ($selected_day === 0 || $item['dayNum'] === $selected_day) {
        $filtered_schedules[$item['dayNum']][] = $item;
    }
}

// สถิติเบื้องต้น
$total_courses = count(array_unique(array_column($mock_schedules, 'courseCode')));
$today_num = (int)date('N'); // 1 = Mon, 5 = Fri
$today_classes_count = count(array_filter($mock_schedules, fn($i) => $i['dayNum'] === $today_num));
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตารางเรียนของฉัน - SiS4 SCHOOL</title>
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
    </style>
</head>
<body class="bg-gray-100 text-gray-800 h-screen overflow-hidden flex">

    <!-- ==================== SIDEBAR ==================== -->
    <aside class="w-64 bg-gray-900 text-gray-300 flex flex-col h-full flex-shrink-0">
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white mr-3">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <h1 class="text-white font-bold text-sm leading-tight">SiS4 SCHOOL</h1>
                <p class="text-xs text-gray-400">ระบบจัดการนักเรียน</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto no-scrollbar px-3 py-4 space-y-2 text-sm">
            <a href="home.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded transition-colors">
                <i class="fa-solid fa-border-all w-5 text-center"></i>
                ภาพรวม
            </a>
            <!-- Active Menu -->
            <a href="schedule.php" class="flex items-center gap-3 px-3 py-2.5 bg-blue-600 text-white rounded shadow-sm">
                <i class="fa-solid fa-calendar-days w-5 text-center"></i>
                ตารางเรียน
            </a>
            <a href="homework.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded transition-colors">
                <i class="fa-solid fa-book-open w-5 text-center"></i>
                การบ้านและชิ้นงาน
            </a>
            <a href="grade.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded transition-colors">
                <i class="fa-solid fa-chart-line w-5 text-center"></i>
                ผลการเรียน
            </a>
        </nav>

        <div class="p-4 border-t border-gray-800 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-blue-600 flex items-center justify-center text-white font-bold text-sm">
                <?= htmlspecialchars($initial) ?>
            </div>
            <div class="flex-1 overflow-hidden">
                <p class="text-sm text-white font-medium truncate"><?= htmlspecialchars($studentName) ?></p>
                <p class="text-xs text-gray-400 truncate">นักเรียนชั้น ม.6</p>
            </div>
            <a href="../../system/logout.php" class="text-gray-400 hover:text-red-400 transition-colors" title="ออกจากระบบ">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </aside>

    <!-- ==================== MAIN CONTENT ==================== -->
    <div class="flex-1 flex flex-col min-w-0">
        
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <div class="text-lg font-medium text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-calendar-week text-blue-600"></i>
                ตารางเรียนประจำภาคเรียน
            </div>
            <div class="flex items-center gap-4">
                <span class="bg-blue-50 text-blue-700 text-xs px-3 py-1.5 rounded-full font-semibold border border-blue-200">
                    <i class="fa-solid fa-clock me-1"></i>ภาคเรียนที่ 1/2569
                </span>
                <div class="h-6 w-px bg-gray-300"></div>
                <div class="text-sm font-medium text-gray-700">
                    <?= htmlspecialchars($studentName) ?>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">รายวิชาทั้งหมด</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $total_courses ?> <span class="text-xs font-normal text-gray-500">วิชา</span></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-50 rounded flex items-center justify-center text-blue-600">
                        <i class="fa-solid fa-book"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">วิชาที่เรียนวันนี้</p>
                        <p class="text-2xl font-bold text-emerald-600"><?= $today_classes_count ?> <span class="text-xs font-normal text-gray-500">คาบ</span></p>
                    </div>
                    <div class="w-10 h-10 bg-emerald-50 rounded flex items-center justify-center text-emerald-600">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">ชั่วโมงเรียนรวมต่อสัปดาห์</p>
                        <p class="text-2xl font-bold text-purple-600">18 <span class="text-xs font-normal text-gray-500">ชั่วโมง</span></p>
                    </div>
                    <div class="w-10 h-10 bg-purple-50 rounded flex items-center justify-center text-purple-600">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                </div>
            </div>

            <!-- Day Filter Tabs -->
            <div class="bg-white border border-gray-200 rounded p-3 shadow-sm flex flex-wrap gap-2 items-center justify-between">
                <div class="flex flex-wrap gap-1.5">
                    <a href="schedule.php?day=0" class="px-4 py-2 text-xs font-medium rounded transition-colors <?= $selected_day === 0 ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                        ทั้งหมด
                    </a>
                    <?php foreach ($days_map as $dNum => $dMeta): ?>
                        <a href="schedule.php?day=<?= $dNum ?>" class="px-3 py-2 text-xs font-medium rounded transition-colors flex items-center gap-1.5 <?= $selected_day === $dNum ? 'bg-blue-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                            <span class="w-2 h-2 rounded-full <?= $dMeta['badge'] ?>"></span>
                            <?= $dMeta['name'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <span class="text-xs text-gray-400 font-medium">
                    <i class="fa-solid fa-info-circle me-1"></i>คลิกเพื่อกรองตารางตามวัน
                </span>
            </div>

            <!-- Schedule Timelines -->
            <div class="space-y-6">
                <?php if (count($filtered_schedules) > 0): ?>
                    <?php foreach ($days_map as $dNum => $dMeta): ?>
                        <?php if (isset($filtered_schedules[$dNum])): ?>
                            
                            <div class="bg-white border border-gray-200 rounded shadow-sm overflow-hidden">
                                <!-- Day Header -->
                                <div class="px-5 py-3 border-b border-gray-200 <?= $dMeta['bg'] ?> flex justify-between items-center">
                                    <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full <?= $dMeta['badge'] ?>"></span>
                                        <?= $dMeta['name'] ?>
                                    </h3>
                                    <span class="text-xs text-gray-500 font-medium">
                                        <?= count($filtered_schedules[$dNum]) ?> วิชาเรียน
                                    </span>
                                </div>

                                <!-- Class Cards Grid -->
                                <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php foreach ($filtered_schedules[$dNum] as $item): ?>
                                        <div class="border border-gray-200 border-l-4 <?= $item['accentColor'] ?> rounded p-4 bg-white hover:shadow-md transition-shadow flex flex-col justify-between">
                                            <div>
                                                <!-- Time & Code -->
                                                <div class="flex justify-between items-center mb-2">
                                                    <span class="text-xs font-bold text-gray-700 bg-gray-100 px-2 py-0.5 rounded">
                                                        <i class="fa-regular fa-clock me-1 text-gray-500"></i><?= $item['startTime'] ?> - <?= $item['endTime'] ?> น.
                                                    </span>
                                                    <span class="border px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $item['badgeColor'] ?>">
                                                        <?= htmlspecialchars($item['courseCode']) ?>
                                                    </span>
                                                </div>

                                                <!-- Subject Name -->
                                                <h4 class="font-bold text-gray-900 text-sm mb-2 leading-snug">
                                                    <?= htmlspecialchars($item['courseName']) ?>
                                                </h4>
                                            </div>

                                            <!-- Room & Teacher -->
                                            <div class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-600 space-y-1">
                                                <p class="flex items-center gap-2 text-blue-700 font-medium">
                                                    <i class="fa-solid fa-location-dot w-4 text-center"></i>
                                                    <?= htmlspecialchars($item['room']) ?>
                                                </p>
                                                <p class="flex items-center gap-2 text-gray-500">
                                                    <i class="fa-solid fa-chalkboard-user w-4 text-center"></i>
                                                    <?= htmlspecialchars($item['teacherName']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white border border-gray-200 rounded p-12 text-center text-gray-500 shadow-sm">
                        <i class="fa-solid fa-calendar-xmark text-4xl text-gray-300 mb-3"></i>
                        <p class="text-sm font-medium">ไม่พบตารางเรียนในวันที่เลือก</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="h-4"></div>
        </main>
    </div>

</body>
</html>