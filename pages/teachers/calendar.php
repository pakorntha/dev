<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("../../system/a_func.php");
require_once("../../system/teacher_sidebar.php"); // เรียกใช้ไฟล์ Sidebar

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// ดึงข้อมูลผู้ใช้งานที่ล็อกอิน
$stmt_user = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
if ($stmt_user->rowCount() === 0) {
    header("Location: ../login.php");
    exit();
}

$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['prefix'] ?? '') . ' ' . ($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
$initial = mb_substr($user['firstName'] ?? 'U', 0, 1, 'UTF-8');
$userRoleStr = ($user['role'] === 'teacher') ? 'ครูผู้สอน' : 'เจ้าหน้าที่';

// ==========================================
// 1. ตั้งค่าเดือนและปีสำหรับปฏิทิน (ค่าเริ่มต้นคือ สิงหาคม 2026)
// ==========================================
$month = 8;
$year = 2026;

// คำนวณจำนวนวันในเดือนและวันแรกของเดือน
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfMonth = date('w', strtotime("$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01")); // 0 (อาทิตย์) ถึง 6 (เสาร์)

$monthNames = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
$displayMonthYear = $monthNames[$month] . " " . ($year + 543);

// ==========================================
// 2. ข้อมูลจำลองกิจกรรมในปฏิทิน (Mock Data)
// ==========================================
// สีที่ใช้: teal (โครงการ), violet (ประชุม), rose (สอบ), emerald (วันหยุด), sky (อบรม), blue (กิจกรรม), amber (กำหนดส่ง), gray (งานสารบรรณ)
$events = [
    ['date' => '2026-08-03', 'title' => 'โครงการโรงเรียนปลอดขยะ', 'color' => 'bg-teal-500'],
    ['date' => '2026-08-04', 'title' => 'โครงการโรงเรียนปลอดขยะ', 'color' => 'bg-teal-500'],
    ['date' => '2026-08-05', 'title' => 'วันจัดกิจกรรม', 'color' => 'bg-gray-500'],
    ['date' => '2026-08-05', 'title' => 'ส่งหนังสือชี้แจง สตง.', 'color' => 'bg-gray-500'],
    ['date' => '2026-08-07', 'title' => 'ส่งรายชื่อผู้เข้าอบรม', 'color' => 'bg-gray-500'],
    ['date' => '2026-08-09', 'title' => 'ส่งรายชื่อนักเรียน', 'color' => 'bg-gray-500'],
    ['date' => '2026-08-10', 'title' => 'ประชุมคณะครูประจำเดือน', 'color' => 'bg-violet-500'],
    ['date' => '2026-08-10', 'title' => 'ตอบรับเข้าร่วมประชุม', 'color' => 'bg-gray-500'],
    ['date' => '2026-08-11', 'title' => 'ส่งต้นฉบับข้อสอบ', 'color' => 'bg-gray-500'],
    ['date' => '2026-08-12', 'title' => 'ส่งรายชื่อครูผู้รับผิดชอบ', 'color' => 'bg-gray-500'],
    ['date' => '2026-08-13', 'title' => 'ตรวจสุขภาพนักเรียนประจำปี', 'color' => 'bg-blue-500'],
    ['date' => '2026-08-13', 'title' => 'วันออกหน่วยบริการ', 'color' => 'bg-gray-500'],
    ['date' => '2026-08-14', 'title' => 'วันประชุม', 'color' => 'bg-gray-500'],
    ['date' => '2026-08-15', 'title' => 'วันแม่แห่งชาติ (หยุดราชการ)', 'color' => 'bg-emerald-500'],
    ['date' => '2026-08-15', 'title' => 'ยืนยันข้อมูล DMC', 'color' => 'bg-gray-500'],
    ['date' => '2026-08-16', 'title' => 'อบรมการใช้ AI เพื่อการจัดการเรียนรู้', 'color' => 'bg-sky-500'],
    ['date' => '2026-08-17', 'title' => 'อบรมการใช้ AI เพื่อการจัดการเรียนรู้', 'color' => 'bg-sky-500'],
    ['date' => '2026-08-18', 'title' => 'สัปดาห์วันวิทยาศาสตร์แห่งชาติ', 'color' => 'bg-blue-500'],
    ['date' => '2026-08-20', 'title' => 'ส่งรายงานผลการดำเนินงานให้เขตพื้นที่', 'color' => 'bg-amber-500'],
    ['date' => '2026-08-23', 'title' => 'กิจกรรมทัศนศึกษา ป.4-ป.6', 'color' => 'bg-blue-500'],
    ['date' => '2026-08-26', 'title' => 'สอบกลางภาค ภาคเรียนที่ 1', 'color' => 'bg-rose-500'],
    ['date' => '2026-08-26', 'title' => 'ส่งรายงานผลการจัดกิจกรรม', 'color' => 'bg-gray-500'],
    ['date' => '2026-08-30', 'title' => 'ประชุมผู้ปกครองนักเรียน', 'color' => 'bg-violet-500'],
    ['date' => '2026-08-31', 'title' => 'ก่อหนี้ผูกพันให้แล้วเสร็จ ภายในวันที่ 31 ส.ค.', 'color' => 'bg-gray-500'],
];

// เพิ่มกิจกรรมต่อเนื่องเพื่อให้ปฏิทินดูสมจริง
for ($i = 3; $i <= 31; $i++) {
    if (!in_array($i, [8, 9, 15, 16, 22, 23, 29, 30])) { // เว้นวันหยุดเสาร์อาทิตย์บางส่วน
        $events[] = ['date' => "2026-08-" . str_pad($i, 2, '0', STR_PAD_LEFT), 'title' => 'โครงการโรงเรียนปลอดขยะ', 'color' => 'bg-teal-500'];
    }
}

// จัดกลุ่มกิจกรรมตามวันที่
$calendarData = [];
foreach ($events as $evt) {
    $calendarData[$evt['date']][] = $evt;
}

// วันนี้สำหรับไฮไลท์ในปฏิทิน
$todayStr = date('Y-m-d');
$currentDateMock = '2026-08-08'; // สมมติวันที่ปัจจุบันให้ตรงกับดีไซน์
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ปฏิทินโรงเรียน - SiS4 SCHOOL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 h-screen overflow-hidden flex">

    <!-- ==================== SIDEBAR ==================== -->
    <?php sis4_teacher_sidebar_render($fullName, $initial, $userRoleStr, '../../system/logout.php'); ?>

    <!-- ==================== MAIN CONTENT ==================== -->
    <div class="flex-1 flex flex-col min-w-0">
        
        <!-- Header -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0 z-30">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded flex items-center justify-center">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold text-gray-900 leading-tight">ปฏิทินโรงเรียน</h1>
                    <p class="text-[11px] text-gray-500">ระบบงานทั่วไป</p>
                </div>
            </div>
            <div class="flex items-center gap-4 ml-auto">
                <button class="relative text-gray-500 hover:text-gray-900 transition-colors">
                    <i class="fa-solid fa-bell text-lg"></i>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full border border-white">2</span>
                </button>
                <div class="w-px h-6 bg-gray-300"></div>
                <div class="text-sm font-medium text-gray-700 hidden sm:block">
                    <?= htmlspecialchars($fullName) ?>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div class="mx-auto w-full max-w-7xl">

                <!-- Title Section -->
                <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="mt-1 w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-xl">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">ปฏิทินโรงเรียน</h1>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">กิจกรรม ประชุม สอบ วันหยุด อบรม โครงการ และกำหนดส่งงานจากระบบสารบรรณ รวมไว้ในที่เดียว</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5 p-1 bg-white border border-gray-200 rounded-xl shadow-sm">
                            <a class="rounded-lg px-4 py-1.5 text-sm font-medium bg-blue-600 text-white shadow-sm" href="#">รายเดือน</a>
                            <a class="rounded-lg px-4 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50" href="#">รายการ</a>
                        </div>
                    </div>
                </div>

                <!-- Calendar Layout Split -->
                <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                    
                    <!-- Left: Calendar Grid -->
                    <div>
                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden flex flex-col">
                            
                            <!-- Month Nav -->
                            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3 bg-gray-50/50">
                                <button class="rounded-lg px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-200 transition-colors font-medium">
                                    <i class="fa-solid fa-chevron-left mr-1"></i> เดือนก่อน
                                </button>
                                <h2 class="text-lg font-bold text-gray-900"><?= $displayMonthYear ?></h2>
                                <button class="rounded-lg px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-200 transition-colors font-medium">
                                    เดือนถัดไป <i class="fa-solid fa-chevron-right ml-1"></i>
                                </button>
                            </div>

                            <!-- Days Header -->
                            <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-100 text-center text-xs font-bold text-gray-600 uppercase tracking-wide">
                                <div class="py-2 text-rose-500">อา</div>
                                <div class="py-2">จ</div>
                                <div class="py-2">อ</div>
                                <div class="py-2">พ</div>
                                <div class="py-2">พฤ</div>
                                <div class="py-2">ศ</div>
                                <div class="py-2 text-blue-500">ส</div>
                            </div>

                            <!-- Calendar Grid -->
                            <div class="grid grid-cols-7 flex-1">
                                <?php
                                // Empty slots before the 1st day of the month
                                for ($i = 0; $i < $firstDayOfMonth; $i++) {
                                    echo '<div class="min-h-[100px] border-b border-r border-gray-100 bg-gray-50/50"></div>';
                                }

                                // Days of the month
                                for ($day = 1; $day <= $daysInMonth; $day++) {
                                    $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                    $dayEvents = $calendarData[$dateStr] ?? [];
                                    
                                    // Check if it's the highlighted day
                                    $isToday = ($dateStr === $currentDateMock);
                                    
                                    echo '<div class="min-h-[100px] border-b border-r border-gray-100 p-1.5 ' . ($isToday ? 'bg-blue-50/50' : '') . '">';
                                    
                                    if ($isToday) {
                                        echo '<span class="inline-grid h-6 w-6 place-items-center rounded-full text-xs font-bold bg-blue-600 text-white shadow-sm">' . $day . '</span>';
                                    } else {
                                        echo '<span class="inline-grid h-6 w-6 place-items-center rounded-full text-xs font-medium text-gray-500 hover:bg-gray-100 cursor-pointer transition-colors">' . $day . '</span>';
                                    }

                                    // Print events
                                    echo '<div class="mt-1 space-y-1">';
                                    $eventCount = count($dayEvents);
                                    $displayLimit = 3; // Show max 3 events, then "+x more"
                                    
                                    for ($e = 0; $e < min($eventCount, $displayLimit); $e++) {
                                        $evt = $dayEvents[$e];
                                        echo '<div title="' . htmlspecialchars($evt['title']) . '" class="flex items-center gap-1.5 truncate rounded px-1.5 py-0.5 text-[10px] font-medium bg-white border border-gray-100 shadow-sm cursor-pointer hover:border-gray-300 transition-colors">';
                                        echo '<span class="h-1.5 w-1.5 shrink-0 rounded-full ' . $evt['color'] . '"></span>';
                                        echo '<span class="truncate text-gray-700">' . htmlspecialchars($evt['title']) . '</span>';
                                        echo '</div>';
                                    }
                                    
                                    if ($eventCount > $displayLimit) {
                                        echo '<p class="px-1 text-[10px] font-semibold text-gray-400 hover:text-blue-500 cursor-pointer">+' . ($eventCount - $displayLimit) . ' รายการ</p>';
                                    }
                                    
                                    echo '</div></div>';
                                }
                                ?>
                            </div>

                            <!-- Legend -->
                            <div class="flex flex-wrap gap-x-4 gap-y-2 px-5 py-4 border-t border-gray-200 bg-gray-50/50 text-xs font-medium text-gray-600">
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>กิจกรรม</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-violet-500"></span>ประชุม</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>สอบ</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>วันหยุด</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span>อบรม</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-teal-500"></span>โครงการ</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>กำหนดส่งงาน</span>
                                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-gray-500"></span>งานสารบรรณ</span>
                            </div>
                        </section>
                    </div>

                    <!-- Right: List and Integrations -->
                    <div class="space-y-6">
                        
                        <!-- Upcoming List -->
                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5">
                            <div class="mb-4 flex items-center justify-between gap-3 border-b border-gray-100 pb-3">
                                <h2 class="flex items-center gap-2 text-base font-bold text-gray-900">
                                    กิจกรรมของโรงเรียน (10)
                                </h2>
                            </div>
                            <ul class="max-h-[500px] space-y-1 overflow-y-auto pr-2 custom-scrollbar">
                                <?php
                                $upcoming = [
                                    ['title' => 'โครงการโรงเรียนปลอดขยะ', 'date' => '3 ส.ค. 69', 'color' => 'bg-teal-500'],
                                    ['title' => 'ประชุมคณะครูประจำเดือน', 'date' => '10 ส.ค. 69', 'color' => 'bg-violet-500'],
                                    ['title' => 'ตรวจสุขภาพนักเรียนประจำปี', 'date' => '13 ส.ค. 69', 'color' => 'bg-blue-500'],
                                    ['title' => 'วันแม่แห่งชาติ (หยุดราชการ)', 'date' => '15 ส.ค. 69', 'color' => 'bg-emerald-500'],
                                    ['title' => 'อบรมการใช้ AI เพื่อการจัดการเรียนรู้', 'date' => '16 ส.ค. 69', 'color' => 'bg-sky-500'],
                                    ['title' => 'สัปดาห์วันวิทยาศาสตร์แห่งชาติ', 'date' => '18 ส.ค. 69', 'color' => 'bg-blue-500'],
                                    ['title' => 'ส่งรายงานผลการดำเนินงานให้เขตพื้นที่', 'date' => '20 ส.ค. 69', 'color' => 'bg-amber-500'],
                                    ['title' => 'กิจกรรมทัศนศึกษา ป.4-ป.6', 'date' => '23 ส.ค. 69', 'color' => 'bg-blue-500'],
                                    ['title' => 'สอบกลางภาค ภาคเรียนที่ 1', 'date' => '26 ส.ค. 69', 'color' => 'bg-rose-500'],
                                    ['title' => 'ประชุมผู้ปกครองนักเรียน', 'date' => '30 ส.ค. 69', 'color' => 'bg-violet-500'],
                                ];
                                foreach ($upcoming as $up):
                                ?>
                                <li class="flex items-start gap-3 rounded-lg px-3 py-2.5 hover:bg-gray-50 transition-colors cursor-pointer border border-transparent hover:border-gray-100">
                                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full <?= $up['color'] ?> shadow-sm"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold text-gray-800"><?= htmlspecialchars($up['title']) ?></span>
                                        <span class="block text-[11px] font-medium text-gray-500 mt-0.5"><i class="fa-regular fa-calendar mr-1"></i><?= htmlspecialchars($up['date']) ?></span>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>

                        <!-- Integration Notice -->
                        <section class="rounded-2xl border border-gray-200 bg-gray-50 shadow-sm p-5">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h2 class="flex items-center gap-2 text-sm font-bold text-gray-900">
                                    <i class="fa-solid fa-link text-blue-600"></i> การเชื่อมต่อปฏิทินภายนอก
                                </h2>
                            </div>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                รองรับการส่งออกเป็นไฟล์ iCalendar เพื่อนำเข้า Google Calendar และ Outlook รวมถึงการแจ้งเตือนผ่าน LINE ก่อนถึงกำหนด <br>
                                <span class="inline-block mt-2 font-semibold text-blue-600 bg-blue-50 px-2 py-1 rounded">(เชื่อมต่อในเฟสถัดไป)</span>
                            </p>
                        </section>

                    </div>
                </div>

            </div>
            
            <div class="h-6"></div>
        </main>
    </div>

</body>
</html>