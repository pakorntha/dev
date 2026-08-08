<?php
session_start();
// ถอยหลังไปหาไฟล์เชื่อมต่อฐานข้อมูลให้ถูกต้อง (ปรับ path ตามโครงสร้างจริงของคุณ)
require_once("../../system/a_func.php");

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// ดึงข้อมูลครูที่ล็อกอินอยู่จากฐานข้อมูล (อ้างอิงจากตาราง users)
$stmt = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
if ($stmt->rowCount() === 1) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ตรวจสอบสิทธิ์ว่าใช่ teacher หรือไม่ (ถ้าเป็นนักเรียนให้เด้งกลับ)
    // หมายเหตุ: หากไอดีที่เทสอยู่ (นายเจแปน) เป็น student ในฐานข้อมูล คุณอาจจะต้องแก้ role ในฐานข้อมูลให้เป็น teacher ก่อนนะครับ ไม่งั้นมันจะเด้งออก
    if ($user['role'] !== 'teacher') {
        header("Location: ../../index.php"); 
        exit();
    }

    // สร้างตัวแปรสำหรับแสดงผลชื่อ
    $fullName = $user['prefix'] . $user['firstName'] . ' ' . $user['lastName'];
    $initial = mb_substr($user['firstName'], 0, 1, 'UTF-8');
    
} else {
    // ถ้าไม่พบข้อมูลให้บังคับล็อกเอาท์
    session_destroy();
    header("Location: ../../systemlogin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - SiS4 SCHOOL</title>
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

    <!-- ==================== SIDEBAR (สำหรับครู) ==================== -->
    <aside class="w-64 bg-gray-900 text-gray-300 flex flex-col h-full flex-shrink-0">
        <!-- Logo -->
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white mr-3">
                <i class="fa-solid fa-school"></i>
            </div>
            <div>
                <h1 class="text-white font-bold text-sm leading-tight">SiS4 SCHOOL</h1>
                <p class="text-xs text-gray-400">ระบบสารบรรณอัจฉริยะ</p>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 overflow-y-auto no-scrollbar px-3 py-4 space-y-6 text-sm">
            
            <!-- เมนูหลัก -->
            <div>
                <a href="home.php" class="flex items-center gap-3 px-3 py-2.5 bg-blue-600 text-white rounded">
                    <i class="fa-solid fa-border-all w-5 text-center"></i>
                    แดชบอร์ด
                </a>
            </div>

            <!-- งานสารบรรณ -->
            <div>
                <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานสารบรรณ</p>
                <ul class="space-y-1">
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-inbox w-5 text-center"></i> หนังสือรับ</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-regular fa-note-sticky w-5 text-center"></i> บันทึกภายใน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-share-nodes w-5 text-center"></i> หนังสือเวียน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-list-check w-5 text-center"></i> งานที่มอบหมาย</a></li>
                </ul>
            </div>

            <!-- งานบุคคล -->
            <div>
                <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานบุคคล</p>
                <ul class="space-y-1">
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-user-clock w-5 text-center"></i> ลงเวลาปฏิบัติงาน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-regular fa-calendar-minus w-5 text-center"></i> การลา</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-plane w-5 text-center"></i> ไปราชการและอบรม</a></li>
                </ul>
            </div>

            <!-- งานวิชาการ -->
            <div>
                <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานวิชาการ</p>
                <ul class="space-y-1">
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-users w-5 text-center"></i> นักเรียนและห้องเรียน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-chalkboard-user w-5 text-center"></i> การมาเรียนนักเรียน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-book-open w-5 text-center"></i> แผนการสอน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-eye w-5 text-center"></i> นิเทศการสอน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-shield-halved w-5 text-center"></i> ประกันคุณภาพภายใน</a></li>
                </ul>
            </div>
        </nav>

        <!-- User Profile Bottom -->
        <div class="p-4 border-t border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded bg-blue-600 flex items-center justify-center text-white font-bold text-sm">
                    <?= htmlspecialchars($initial) ?>
                </div>
                <div class="flex-1 overflow-hidden">
                    <p class="text-sm text-white font-medium truncate"><?= htmlspecialchars($fullName) ?></p>
                    <p class="text-xs text-gray-400 truncate">ครูผู้สอน</p>
                </div>
                <a href="../../system/logout.php" class="text-gray-400 hover:text-red-400 transition-colors" title="ออกจากระบบ">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- ==================== MAIN CONTENT ==================== -->
    <div class="flex-1 flex flex-col min-w-0">
        
        <!-- Top Header -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <!-- Search -->
            <div class="relative w-96 hidden sm:block">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" placeholder="ค้นหาหนังสือ เรื่อง ผู้ส่ง เลขทะเบียน..." 
                    class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white transition-all">
            </div>

            <!-- Top Right Actions -->
            <div class="flex items-center gap-4 ml-auto">
                <button class="relative text-gray-500 hover:text-gray-700">
                    <i class="fa-solid fa-bell text-lg"></i>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full border-2 border-white">2</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <div class="text-sm font-medium text-gray-700 flex items-center gap-2">
                    <?= htmlspecialchars($fullName) ?>
                </div>
            </div>
        </header>

        <!-- Main Scrollable Area -->
        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- Greeting -->
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded flex items-center justify-center text-lg">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">สวัสดีตอนบ่าย <?= htmlspecialchars($fullName) ?></h1>
                    <p class="text-sm text-gray-500 mt-0.5">ครู · โรงเรียนบ้านหนองปลาสร้อย · ปีการศึกษา 2569</p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded border border-gray-200 shadow-sm flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">งานที่ได้รับมอบหมาย</p>
                        <p class="text-2xl font-bold text-gray-900">2</p>
                        <p class="text-[11px] text-gray-400 mt-1">ทั้งหมด 3 รายการ</p>
                    </div>
                    <div class="w-10 h-10 bg-blue-50 rounded flex items-center justify-center text-blue-600">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                </div>
                
                <div class="bg-white p-4 rounded border border-gray-200 shadow-sm flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">งานเกินกำหนด</p>
                        <p class="text-2xl font-bold text-gray-900">0</p>
                        <p class="text-[11px] text-gray-400 mt-1">ต้องเร่งดำเนินการ</p>
                    </div>
                    <div class="w-10 h-10 bg-emerald-50 rounded flex items-center justify-center text-emerald-600">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded border border-gray-200 shadow-sm flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">หนังสือที่ยังไม่รับทราบ</p>
                        <p class="text-2xl font-bold text-gray-900">1</p>
                        <p class="text-[11px] text-gray-400 mt-1">กดรับทราบเพื่อยืนยัน</p>
                    </div>
                    <div class="w-10 h-10 bg-amber-50 rounded flex items-center justify-center text-amber-600">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                </div>

                <div class="bg-white p-4 rounded border border-gray-200 shadow-sm flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">งานที่เสร็จสิ้นแล้ว</p>
                        <p class="text-2xl font-bold text-gray-900">1</p>
                        <p class="text-[11px] text-gray-400 mt-1">ผ่านการตรวจสอบแล้ว</p>
                    </div>
                    <div class="w-10 h-10 bg-emerald-50 rounded flex items-center justify-center text-emerald-600">
                        <i class="fa-solid fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <!-- ANNOUNCEMENT (แทนที่ส่วนข้อมูล AI) -->
            <div class="bg-blue-50 border border-blue-200 rounded p-4 flex items-start gap-3">
                <i class="fa-solid fa-circle-info text-blue-600 mt-0.5"></i>
                <div>
                    <h3 class="text-sm font-bold text-blue-900">ประกาศ: การส่งแผนการสอนและรายงาน SAR ประจำปี</h3>
                    <p class="text-sm text-blue-800 mt-1">
                        ขอความร่วมมือคณะครูทุกท่าน อัปเดตแผนการสอนรายสัปดาห์ในระบบภายในวันศุกร์นี้ และเตรียมความพร้อมข้อมูลสำหรับการประเมินประกันคุณภาพภายใน (SAR)
                    </p>
                </div>
            </div>

            <!-- Bottom Section Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left: งานที่มอบหมายให้ฉัน -->
                <div class="bg-white border border-gray-200 rounded shadow-sm lg:col-span-2 flex flex-col">
                    <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50/50">
                        <h3 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                            <i class="fa-solid fa-clipboard-check text-blue-600"></i> งานที่มอบหมายให้ฉัน
                        </h3>
                        <a href="#" class="text-xs text-blue-600 hover:underline">ดูทั้งหมด</a>
                    </div>
                    
                    <div class="p-5 space-y-4 flex-1">
                        <!-- รายการที่ 1 -->
                        <div class="border border-gray-200 rounded p-4 hover:border-blue-300 transition-colors">
                            <div class="flex justify-between items-start gap-2">
                                <p class="text-sm font-bold text-gray-900">ขอความร่วมมือจัดกิจกรรมสัปดาห์วันวิทยาศาสตร์แห่งชาติ ประจำปี 2569</p>
                                <span class="bg-blue-50 text-blue-700 border border-blue-100 px-2 py-0.5 rounded text-[10px] whitespace-nowrap">รับทราบแล้ว</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1.5">ผู้สั่งการ: นายสมชาย มั่นคง · กำหนดส่ง 26 สิงหาคม 2569</p>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="h-1.5 flex-1 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-600 rounded-full" style="width: 15%;"></div>
                                </div>
                                <span class="text-[10px] text-gray-500 bg-gray-50 px-2 py-0.5 rounded border border-gray-200">เหลืออีก 19 วัน</span>
                            </div>
                        </div>

                        <!-- รายการที่ 2 -->
                        <div class="border border-gray-200 rounded p-4 hover:border-blue-300 transition-colors">
                            <div class="flex justify-between items-start gap-2">
                                <p class="text-sm font-bold text-gray-900">แจ้งกำหนดการตรวจสุขภาพนักเรียนและฉีดวัคซีนประจำปีการศึกษา 2569</p>
                                <span class="bg-orange-50 text-orange-700 border border-orange-100 px-2 py-0.5 rounded text-[10px] whitespace-nowrap">กำลังดำเนินการ</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1.5">ผู้สั่งการ: นายสมชาย มั่นคง · กำหนดส่ง 13 สิงหาคม 2569</p>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="h-1.5 flex-1 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-600 rounded-full" style="width: 60%;"></div>
                                </div>
                                <span class="text-[10px] text-gray-500 bg-gray-50 px-2 py-0.5 rounded border border-gray-200">เหลืออีก 6 วัน</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: กำหนดการ & ทางลัด -->
                <div class="space-y-6">
                    
                    <!-- กำหนดการที่ใกล้ถึง -->
                    <div class="bg-white border border-gray-200 rounded shadow-sm">
                        <div class="px-5 py-4 border-b border-gray-200 bg-gray-50/50">
                            <h3 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                                <i class="fa-regular fa-clock text-orange-500"></i> กำหนดการที่ใกล้ถึง
                            </h3>
                        </div>
                        <div class="p-5">
                            <ul class="space-y-4">
                                <li class="flex gap-3">
                                    <span class="mt-1.5 w-2 h-2 shrink-0 rounded-full bg-orange-500"></span>
                                    <div>
                                        <p class="text-sm font-bold text-gray-800">ส่งรายชื่อผู้เข้าอบรม</p>
                                        <p class="text-xs text-gray-500 mt-0.5 truncate">การอบรมเชิงปฏิบัติการการใช้เทคโนโลยี...</p>
                                        <p class="text-[10px] text-orange-600 font-medium mt-1">7 สิงหาคม 2569 · ครบกำหนดวันนี้</p>
                                    </div>
                                </li>
                                <li class="flex gap-3">
                                    <span class="mt-1.5 w-2 h-2 shrink-0 rounded-full bg-orange-500"></span>
                                    <div>
                                        <p class="text-sm font-bold text-gray-800">ส่งรายชื่อนักเรียน</p>
                                        <p class="text-xs text-gray-500 mt-0.5 truncate">แจ้งกำหนดการตรวจสุขภาพนักเรียน...</p>
                                        <p class="text-[10px] text-gray-400 mt-1">9 สิงหาคม 2569 · เหลืออีก 2 วัน</p>
                                    </div>
                                </li>
                                <li class="flex gap-3">
                                    <span class="mt-1.5 w-2 h-2 shrink-0 rounded-full bg-orange-500"></span>
                                    <div>
                                        <p class="text-sm font-bold text-gray-800">ตอบรับเข้าร่วมประชุม</p>
                                        <p class="text-xs text-gray-500 mt-0.5 truncate">แจ้งการประชุมผู้บริหารสถานศึกษา...</p>
                                        <p class="text-[10px] text-gray-400 mt-1">10 สิงหาคม 2569 · เหลืออีก 3 วัน</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- ทางลัด -->
                    <div class="bg-white border border-gray-200 rounded shadow-sm">
                        <div class="px-5 py-4 border-b border-gray-200 bg-gray-50/50">
                            <h3 class="font-bold text-gray-900 text-sm">ทางลัด</h3>
                        </div>
                        <div class="p-4 grid grid-cols-2 gap-2">
                            <a href="#" class="flex flex-col items-center justify-center gap-2 border border-gray-200 rounded p-3 text-gray-600 hover:bg-blue-50 hover:border-blue-200 transition-colors">
                                <i class="fa-solid fa-file-arrow-up text-blue-600"></i>
                                <span class="text-xs">ลงทะเบียนรับ</span>
                            </a>
                            <a href="#" class="flex flex-col items-center justify-center gap-2 border border-gray-200 rounded p-3 text-gray-600 hover:bg-blue-50 hover:border-blue-200 transition-colors">
                                <i class="fa-solid fa-file-pen text-blue-600"></i>
                                <span class="text-xs">ร่างหนังสือส่ง</span>
                            </a>
                            <a href="#" class="flex flex-col items-center justify-center gap-2 border border-gray-200 rounded p-3 text-gray-600 hover:bg-blue-50 hover:border-blue-200 transition-colors">
                                <i class="fa-solid fa-magnifying-glass text-blue-600"></i>
                                <span class="text-xs">ค้นหาเอกสาร</span>
                            </a>
                            <a href="#" class="flex flex-col items-center justify-center gap-2 border border-gray-200 rounded p-3 text-gray-600 hover:bg-blue-50 hover:border-blue-200 transition-colors">
                                <i class="fa-solid fa-chart-pie text-blue-600"></i>
                                <span class="text-xs">รายงานสถิติ</span>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
            
            <div class="h-4"></div>
        </main>
    </div>

</body>
</html>