<?php
session_start();
// ถอยหลังไปหาไฟล์เชื่อมต่อฐานข้อมูลให้ถูกต้อง (ปรับ path ตามโครงสร้างจริงของคุณ)
require_once("../../system/a_func.php");

// ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// ดึงข้อมูลนักเรียนที่ล็อกอินอยู่จากฐานข้อมูล
$stmt = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
if ($stmt->rowCount() === 1) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ตรวจสอบสิทธิ์ว่าใช่ student หรือไม่ (กันแอดมินหรือครูหลงเข้ามา)
    if ($user['role'] !== 'student') {
        header("Location: ../../index.php"); // ส่งกลับไปหน้า router หลัก
        exit();
    }

    // สร้างตัวแปรสำหรับแสดงผลชื่อ
    $fullName = $user['prefix'] . $user['firstName'] . ' ' . $user['lastName'];
    // ดึงตัวอักษรตัวแรกของชื่อมาทำเป็นรูปโปรไฟล์แบบย่อ
    $initial = mb_substr($user['firstName'], 0, 1, 'UTF-8');
    
} else {
    // ถ้าไม่พบข้อมูลให้บังคับล็อกเอาท์
    session_destroy();
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SiS4 SCHOOL</title>
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
<body class="bg-gray-50 text-gray-800 h-screen overflow-hidden flex">

    <!-- ==================== SIDEBAR (เมนูสำหรับนักเรียน) ==================== -->
    <aside class="w-64 bg-[#111827] text-gray-300 flex flex-col h-full flex-shrink-0">
        <!-- Logo -->
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white mr-3">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <h1 class="text-white font-bold text-sm leading-tight">SiS4 SCHOOL</h1>
                <p class="text-xs text-gray-400">ระบบจัดการสารสนเทศนักเรียน</p>
            </div>
        </div>

        <!-- School Info -->
        <div class="p-4">
            <div class="bg-gray-800 rounded-xl p-3 flex items-center gap-3 border border-gray-700">
                <div class="w-10 h-10 bg-gray-700 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-building-columns text-gray-400"></i>
                </div>
                <div class="text-xs">
                    <p class="text-white font-medium">โรงเรียนบ้านหนองปลาซิว</p>
                    <p class="text-gray-400 mt-0.5">ปีการศึกษา 2569</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 overflow-y-auto no-scrollbar px-3 py-2 space-y-6">
            <div>
                <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">เมนูหลัก</p>
                <ul class="space-y-1 text-sm">
                    <li>
                        <a href="home.php" class="flex items-center gap-3 px-3 py-2.5 bg-blue-600 text-white rounded-lg shadow-sm">
                            <i class="fa-solid fa-border-all w-5 text-center"></i>
                            ภาพรวม (Dashboard)
                        </a>
                    </li>
                    <li>
                        <a href="schedule.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded-lg transition-colors">
                            <i class="fa-solid fa-calendar-days w-5 text-center"></i>
                            ตารางเรียน
                        </a>
                    </li>
                    <li>
                        <a href="score.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded-lg transition-colors">
                            <i class="fa-solid fa-book-open w-5 text-center"></i>
                            การบ้านและชิ้นงาน
                        </a>
                    </li>
                    <li>
                        <a href="grade.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded-lg transition-colors">
                            <i class="fa-solid fa-chart-line w-5 text-center"></i>
                            ผลการเรียน
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- User Profile Bottom -->
        <div class="p-4 border-t border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-sm">
                    <?= htmlspecialchars($initial) ?>
                </div>
                <div class="flex-1 overflow-hidden">
                    <p class="text-sm text-white font-medium truncate"><?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']) ?></p>
                    <p class="text-xs text-gray-400 truncate">นักเรียนชั้น ม.6</p>
                </div>
                <!-- ลิงก์ไปไฟล์ออกจากระบบ -->
                <a href="../../logout.php" class="text-gray-400 hover:text-red-400 transition-colors" title="ออกจากระบบ">
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
            <div class="relative w-96">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" placeholder="ค้นหาวิชา, การบ้าน หรือครูผู้สอน..." 
                    class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all">
            </div>

            <!-- Top Right Actions -->
            <div class="flex items-center gap-4">
                <button class="relative text-gray-500 hover:text-gray-700">
                    <i class="fa-regular fa-bell text-xl"></i>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full border-2 border-white">3</span>
                </button>
                <div class="h-8 w-px bg-gray-200"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden md:block">
                        <!-- แสดงชื่อจาก Database -->
                        <p class="text-sm font-medium text-gray-700 leading-tight"><?= htmlspecialchars($fullName) ?></p>
                        <p class="text-xs text-gray-500">นักเรียน</p>
                    </div>
                    <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-sm">
                        <?= htmlspecialchars($initial) ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Scrollable Area -->
        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- Greeting -->
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center text-xl">
                    <i class="fa-solid fa-hands-clapping"></i>
                </div>
                <div>
                    <!-- ทักทายด้วยชื่อจาก Database -->
                    <h2 class="text-xl font-bold text-gray-900">สวัสดี, <?= htmlspecialchars($fullName) ?></h2>
                    <p class="text-sm text-gray-500 mt-0.5">ยินดีต้อนรับเข้าสู่ระบบจัดการเรียนการสอน ภาคเรียนที่ 1/2569</p>
                </div>
            </div>

            <!-- Stats Grid (สำหรับนักเรียน) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-sm font-medium text-gray-600">เกรดเฉลี่ยสะสม</h3>
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-graduation-cap"></i></div>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-gray-900">3.85</p>
                        <p class="text-xs text-emerald-500 mt-1 font-medium"><i class="fa-solid fa-arrow-trend-up"></i> เพิ่มขึ้นจากเทอมที่แล้ว</p>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-sm font-medium text-gray-600">การมาเรียน</h3>
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-user-check"></i></div>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-gray-900">98%</p>
                        <p class="text-xs text-gray-500 mt-1">ขาดเรียน 1 วัน / ลากิจ 0 วัน</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-sm font-medium text-gray-600">งานที่ต้องส่งสัปดาห์นี้</h3>
                        <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center"><i class="fa-solid fa-list-check"></i></div>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-gray-900">4</p>
                        <p class="text-xs text-red-500 mt-1">ค้างส่ง 1 รายการ</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-sm font-medium text-gray-600">คะแนนความประพฤติ</h3>
                        <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center"><i class="fa-solid fa-star"></i></div>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-gray-900">100</p>
                        <p class="text-xs text-gray-500 mt-1">คะแนนเต็ม 100</p>
                    </div>
                </div>
            </div>

            <!-- AI Insight Banner (ปรับบริบทสำหรับนักเรียน) -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-5 relative overflow-hidden">
                <div class="absolute -right-10 -top-10 w-32 h-32 bg-blue-200 rounded-full blur-3xl opacity-40"></div>
                
                <div class="flex gap-4 relative z-10">
                    <div class="w-10 h-10 bg-blue-600 text-white rounded-lg flex items-center justify-center shrink-0 shadow-sm">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 mb-2">คำแนะนำจากผู้ช่วย AI ของคุณ</h3>
                        <ul class="text-sm text-gray-700 space-y-1.5 list-disc list-inside marker:text-blue-400">
                            <li>สำหรับการเตรียมตัวสอบเข้าวิศวกรรมระบบควบคุมและเครื่องมือวัด ผลการเรียนวิชา <strong>ฟิสิกส์</strong> และ <strong>คณิตศาสตร์</strong> ของคุณอยู่ในเกณฑ์ดีเยี่ยม ควรทำโจทย์เรื่องกลศาสตร์เพิ่มเติม</li>
                            <li>คุณมีกำหนดส่งโปรเจกต์วิชาวิทยาศาสตร์ (โครงงานพลาสติกชีวภาพ) ในวันศุกร์นี้ <span class="text-red-500 font-medium">อย่าลืมตรวจสอบความเรียบร้อย</span></li>
                            <li>สถิติการใช้งานระบบชี้ว่าคุณมีการจัดระเบียบข้อมูล (Mind Map) ได้อย่างมีประสิทธิภาพ แนะนำให้ใช้เครื่องมือนี้ในการสรุปเนื้อหาวิชาประวัติศาสตร์สำหรับการสอบปลายภาค</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Bottom Section Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left: Recent Assignments Table -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm lg:col-span-2">
                    <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <i class="fa-solid fa-book-open-reader text-blue-600"></i> การบ้านและชิ้นงานล่าสุด
                        </h3>
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-800 font-medium">ดูงานทั้งหมด</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                                <tr>
                                    <th class="px-5 py-3 font-medium">รายวิชา</th>
                                    <th class="px-5 py-3 font-medium">รายละเอียดงาน</th>
                                    <th class="px-5 py-3 font-medium">กำหนดส่ง</th>
                                    <th class="px-5 py-3 font-medium">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-4 align-top">
                                        <p class="font-medium text-gray-900">ฟิสิกส์ 5</p>
                                        <p class="text-xs text-gray-400 mt-1">ว33201</p>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <p class="text-gray-900">แบบฝึกหัดเรื่อง กฎของนิวตันและการเคลื่อนที่</p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs text-gray-500">ครูสมชาย เรียนดี</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <p class="text-gray-900">10 ส.ค. 69</p>
                                        <p class="text-xs text-red-500 mt-1">เหลืออีก 2 วัน</p>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-orange-50 text-orange-700 border border-orange-100">
                                            รอส่งงาน
                                        </span>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-5 py-4 align-top">
                                        <p class="font-medium text-gray-900">คณิตศาสตร์เพิ่มเติม</p>
                                        <p class="text-xs text-gray-400 mt-1">ค33201</p>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <p class="text-gray-900">ใบงานความน่าจะเป็นและการแจกแจงแบบปกติ</p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs text-gray-500">ครูสมศรี รักเรียน</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <p class="text-gray-900">8 ส.ค. 69</p>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                                            ส่งแล้ว
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right: Upcoming Schedules -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
                    <div class="p-5 border-b border-gray-100">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <i class="fa-regular fa-calendar-check text-blue-600"></i> ปฏิทินการศึกษา
                        </h3>
                    </div>
                    <div class="p-5">
                        <div class="relative border-l-2 border-gray-100 ml-3 space-y-6">
                            
                            <div class="relative pl-6">
                                <span class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-red-500 border-4 border-white"></span>
                                <p class="text-sm font-bold text-gray-900">สอบกลางภาคเรียนที่ 1/2569</p>
                                <p class="text-xs text-gray-500 mt-1">สอบครบทุกรายวิชาตามตาราง</p>
                                <p class="text-xs text-red-600 mt-2 font-medium">15 - 18 สิงหาคม 2569</p>
                            </div>

                            <div class="relative pl-6">
                                <span class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-blue-400 border-4 border-white"></span>
                                <p class="text-sm font-bold text-gray-900">ส่งแฟ้มสะสมผลงาน (Portfolio)</p>
                                <p class="text-xs text-gray-500 mt-1">สำหรับงานแนะแนวศึกษาต่อระดับมหาวิทยาลัย</p>
                                <p class="text-xs text-gray-400 mt-2">20 สิงหาคม 2569</p>
                            </div>

                            <div class="relative pl-6">
                                <span class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-emerald-400 border-4 border-white"></span>
                                <p class="text-sm font-bold text-gray-900">กิจกรรมกีฬาสีภายใน</p>
                                <p class="text-xs text-gray-500 mt-1">เตรียมความพร้อมและซ้อมเชียร์</p>
                                <p class="text-xs text-gray-400 mt-2">1 กันยายน 2569</p>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
            
            <div class="h-4"></div>
        </main>
    </div>

</body>
</html>