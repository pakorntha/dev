<?php
session_start();
require_once("../../system/a_func.php");
// สมมติว่ามี Sidebar สำหรับผู้บริหาร
require_once("../../system/director_sidebar.php"); 

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['id'];
$success_msg = "";
$error_msg = "";

// ดึงข้อมูลผู้บริหารที่ล็อกอิน
$stmt = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$user_id]);
if ($stmt->rowCount() === 1) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    // ตรวจสอบ Role (ปรับให้ตรงกับ role ของผู้บริหารในระบบคุณ เช่น 'director' หรือ 'admin')
    if ($user['role'] !== 'director' && $user['role'] !== 'admin') {
        header("Location: ../../index.php");
        exit();
    }
    $fullname = $user['prefix'] . $user['firstName'] . ' ' . $user['lastName'];
    $initial = mb_substr($user['firstName'], 0, 1, 'UTF-8');
    $role = $user['role'];
} else {
    session_destroy();
    header("Location: ../../systemlogin.php");
    exit();
}

// ฟังก์ชันแปลงวันที่เป็นภาษาไทย
function getThaiDate($datetime, $full = false) {
    if (!$datetime) return "-";
    $time = strtotime($datetime);
    $thai_months_short = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    $thai_months_full = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    $d = date('j', $time);
    $m = $full ? $thai_months_full[(int)date('n', $time)] : $thai_months_short[(int)date('n', $time)];
    $y = date('Y', $time) + 543;
    $y_short = substr($y, 2, 2);
    
    if ($full) {
        return "$d $m $y";
    }
    return "$d $m $y_short";
}

// ---------------------------------------------------------
// จัดการ POST: เมื่อผู้บริหารกดรับรอง/ลงนามหนังสือ
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    $letter_id = intval($_POST['letter_id']);
    
    try {
        // อัปเดตสถานะเป็น completed
        $stmt = dd_q("UPDATE outgoing_letters SET status = 'completed' WHERE id = ?", [$letter_id]);
        if ($stmt) {
            header("Location: outgoing.php?id=$letter_id&success=approved");
            exit();
        }
    } catch (Exception $e) {
        $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// รับค่า ID ของหนังสือ (ถ้ามี) เพื่อแสดงหน้ารายละเอียด
$view_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทะเบียนหนังสือส่ง (สำหรับผู้บริหาร) - ระบบสารบรรณ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 CSS & JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- html2pdf.js สำหรับดาวน์โหลดไฟล์ PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>

<body class="text-gray-800 flex h-screen overflow-hidden">

    <?php sis4_direcetor_sidebar_render($fullname, $initial, $role, '../../system/logout.php'); ?>
    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden w-full">
        <!-- Top Header -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <h1 class="text-lg font-bold text-gray-800">ระบบบริหารจัดการสารบรรณอิเล็กทรอนิกส์</h1>
            <div class="flex items-center gap-4 ml-auto">
                <div class="text-sm font-medium text-gray-700 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xs">
                        <?= $initial ?>
                    </div>
                    <?= htmlspecialchars($fullname) ?> (ผู้บริหาร)
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto">

                <?php if ($error_msg): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= $error_msg ?>
                    </div>
                <?php endif; ?>

                <?php 
                // ==========================================
                // หน้า: แสดงรายละเอียดหนังสือ (Detail View)
                // ==========================================
                if ($view_id > 0): 
                    $letter = dd_q("SELECT l.*, u.firstName, u.lastName, u.prefix 
                                    FROM outgoing_letters l 
                                    LEFT JOIN user u ON l.created_by = u.id 
                                    WHERE l.id = ?", [$view_id])->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$letter):
                        echo "<div class='text-center py-10'>ไม่พบข้อมูลหนังสือ</div>";
                    else:
                        $reg_no = "ศธ 04009.11/" . str_pad($letter['id'], 3, '0', STR_PAD_LEFT);
                        $created_name = $letter['prefix'] . $letter['firstName'] . ' ' . $letter['lastName'];
                ?>
                <!-- Header Actions -->
                <a href="outgoing.php" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 mb-6 transition-colors">
                    <i class="fa-solid fa-arrow-left"></i> กลับไปทะเบียนหนังสือ
                </a>

                <!-- Document Header Section -->
                <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6 shadow-sm">
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="px-2.5 py-1 bg-indigo-50 text-indigo-600 rounded-md text-xs font-medium border border-indigo-100">หนังสือส่ง</span>
                                <span class="text-indigo-600 font-semibold text-sm"><?= $reg_no ?></span>
                                
                                <?php if($letter['status'] === 'completed'): ?>
                                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-600 rounded-md text-xs font-medium border border-emerald-100">เสร็จสิ้น</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 bg-yellow-50 text-yellow-600 rounded-md text-xs font-medium border border-yellow-100">รอพิจารณา</span>
                                <?php endif; ?>

                                <?php if($letter['urgency'] != 'ปกติ'): ?>
                                    <span class="px-2.5 py-1 bg-orange-50 text-orange-600 rounded-md text-xs font-medium border border-orange-100"><?= htmlspecialchars($letter['urgency']) ?></span>
                                <?php endif; ?>
                            </div>
                            <h1 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($letter['subject']) ?></h1>
                            <p class="text-gray-500 text-sm">
                                ถึง <?= htmlspecialchars($letter['recipient']) ?> · เลขที่ <?= $reg_no ?> · ลงวันที่ <?= getThaiDate($letter['created_at'], true) ?>
                            </p>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex items-center gap-3">
                            <?php if($letter['status'] === 'pending_director'): ?>
                                <form id="approveForm" action="outgoing.php" method="POST">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="letter_id" value="<?= $letter['id'] ?>">
                                    <button type="button" onclick="confirmApprove()" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                                        <i class="fa-solid fa-signature"></i> พิจารณารับรอง / ลงนาม
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                                    <i class="fa-solid fa-folder-open"></i> จัดเก็บเข้าแฟ้ม
                                </button>
                                <button onclick="downloadPDF()" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                                    <i class="fa-solid fa-download"></i> ดาวน์โหลด PDF
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Left Column (AI & Content) -->
                    <div class="lg:col-span-7 xl:col-span-8 space-y-6">
                        
                        <!-- AI Summary Box -->
                        <div class="bg-white rounded-2xl border border-purple-100 p-6 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-1 h-full bg-purple-500"></div>
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2 text-purple-700 font-bold">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                    สรุปสาระสำคัญโดย AI
                                </div>
                                <div class="flex gap-2">
                                    <span class="px-2.5 py-1 bg-purple-50 text-purple-700 rounded-full text-xs font-medium border border-purple-100">ความมั่นใจ 94%</span>
                                    <span class="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium border border-gray-200"><i class="fa-regular fa-clock mr-1"></i>อ่าน 2 นาที</span>
                                </div>
                            </div>
                            
                            <p class="text-gray-700 text-sm mb-4 leading-relaxed">
                                สรุปเนื้อหา: <?= htmlspecialchars(mb_substr($letter['content'], 0, 150, 'UTF-8')) ?>...
                            </p>
                            
                            <div class="mb-4">
                                <h4 class="text-xs font-bold text-gray-500 mb-2">ประเด็นสำคัญ</h4>
                                <ul class="space-y-1.5">
                                    <li class="flex items-start gap-2 text-sm text-gray-700">
                                        <div class="w-1.5 h-1.5 rounded-full bg-purple-500 mt-1.5"></div>
                                        ขออนุมัติและแจ้งรายละเอียดการดำเนินงาน
                                    </li>
                                    <li class="flex items-start gap-2 text-sm text-gray-700">
                                        <div class="w-1.5 h-1.5 rounded-full bg-purple-500 mt-1.5"></div>
                                        หมวดหมู่: <?= htmlspecialchars($letter['category']) ?>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Content Box (จุดที่ต้องการ Download เป็น PDF) -->
                        <div id="printable-area" class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                            <div class="flex items-center justify-between mb-6 data-ignore-print">
                                <div class="flex items-center gap-2 text-gray-800 font-bold">
                                    <i class="fa-solid fa-expand text-blue-500"></i>
                                    เนื้อหาหนังสือ
                                </div>
                                <span class="px-2.5 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium border border-gray-200">เลขที่ <?= $reg_no ?></span>
                            </div>
                            
                            <!-- Letter Content Display -->
                            <div class="bg-gray-50 p-6 rounded-xl border border-gray-100 text-gray-800 text-sm leading-loose whitespace-pre-wrap font-serif min-h-[300px]">
<?= htmlspecialchars($letter['content']) ?>
                            </div>
                            
                            <!-- พื้นที่ลายเซ็นแสดงเฉพาะใน PDF -->
                            <?php if($letter['status'] === 'completed'): ?>
                            <div class="mt-12 flex justify-end text-sm text-center">
                                <div>
                                    <p>ลงชื่อ ..............................................................</p>
                                    <p class="mt-2">( <?= htmlspecialchars($fullname) ?> )</p>
                                    <p class="mt-1">ผู้อำนวยการโรงเรียนบ้านหนองปลาสร้อย</p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Right Column (Meta & History) -->
                    <div class="lg:col-span-5 xl:col-span-4 space-y-6">
                        
                        <!-- Registration Info -->
                        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                            <div class="flex items-center gap-2 text-gray-800 font-bold mb-5">
                                <i class="fa-regular fa-file-lines text-blue-500"></i>
                                ข้อมูลทะเบียน
                            </div>
                            
                            <div class="space-y-3.5">
                                <div class="flex justify-between items-start border-b border-gray-50 pb-3">
                                    <span class="text-gray-500 text-sm">ประเภท</span>
                                    <span class="text-gray-900 text-sm font-medium">หนังสือส่ง</span>
                                </div>
                                <div class="flex justify-between items-start border-b border-gray-50 pb-3">
                                    <span class="text-gray-500 text-sm">เลขทะเบียน</span>
                                    <span class="text-gray-900 text-sm font-medium"><?= $reg_no ?></span>
                                </div>
                                <div class="flex justify-between items-start border-b border-gray-50 pb-3">
                                    <span class="text-gray-500 text-sm">เลขที่หนังสือ</span>
                                    <span class="text-gray-900 text-sm font-medium"><?= $reg_no ?></span>
                                </div>
                                <div class="flex justify-between items-start border-b border-gray-50 pb-3">
                                    <span class="text-gray-500 text-sm">ลงวันที่</span>
                                    <span class="text-gray-900 text-sm font-medium"><?= getThaiDate($letter['created_at'], true) ?></span>
                                </div>
                                <div class="flex justify-between items-start border-b border-gray-50 pb-3">
                                    <span class="text-gray-500 text-sm">จาก</span>
                                    <span class="text-gray-900 text-sm font-medium text-right">โรงเรียนบ้านหนองปลาสร้อย</span>
                                </div>
                                <div class="flex justify-between items-start border-b border-gray-50 pb-3">
                                    <span class="text-gray-500 text-sm">เรียน</span>
                                    <span class="text-gray-900 text-sm font-medium text-right"><?= htmlspecialchars($letter['recipient']) ?></span>
                                </div>
                                <div class="flex justify-between items-start border-b border-gray-50 pb-3">
                                    <span class="text-gray-500 text-sm">ผู้สร้างร่าง</span>
                                    <span class="text-gray-900 text-sm font-medium text-right"><?= htmlspecialchars($created_name) ?></span>
                                </div>
                                <div class="flex justify-between items-center pt-1">
                                    <span class="text-gray-500 text-sm">หมวดหมู่</span>
                                    <span class="px-2.5 py-1 bg-blue-50 text-blue-600 rounded-full text-xs font-medium border border-blue-100">
                                        <?= htmlspecialchars($letter['category']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- History -->
                        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
                            <div class="flex items-center gap-2 text-gray-800 font-bold mb-5">
                                <i class="fa-solid fa-clock-rotate-left text-blue-500"></i>
                                ประวัติการดำเนินการ
                            </div>
                            
                            <div class="relative border-l border-gray-200 ml-3 space-y-6">
                                <?php if($letter['status'] === 'completed'): ?>
                                <div class="relative pl-6">
                                    <div class="absolute -left-1.5 top-1.5 w-3 h-3 rounded-full bg-emerald-500 ring-4 ring-white"></div>
                                    <p class="text-sm font-medium text-gray-900">ผู้อำนวยการลงนามเรียบร้อย</p>
                                    <p class="text-xs text-gray-500 mt-1">เสร็จสิ้นกระบวนการ</p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="relative pl-6">
                                    <?php if($letter['status'] === 'pending_director'): ?>
                                        <div class="absolute -left-1.5 top-1.5 w-3 h-3 rounded-full bg-yellow-500 ring-4 ring-white"></div>
                                    <?php else: ?>
                                        <div class="absolute -left-1.5 top-1.5 w-3 h-3 rounded-full bg-gray-300 ring-4 ring-white"></div>
                                    <?php endif; ?>
                                    <p class="text-sm font-medium text-gray-900">เสนอผู้อำนวยการพิจารณา</p>
                                    <p class="text-xs text-gray-500 mt-1">รอการตรวจสอบและลงนาม</p>
                                </div>
                                
                                <div class="relative pl-6">
                                    <div class="absolute -left-1.5 top-1.5 w-3 h-3 rounded-full bg-gray-300 ring-4 ring-white"></div>
                                    <p class="text-sm font-medium text-gray-900">สร้างร่างหนังสือส่ง</p>
                                    <p class="text-xs text-gray-500 mt-1">โดย <?= htmlspecialchars($created_name) ?></p>
                                    <p class="text-xs text-gray-400"><?= getThaiDate($letter['created_at']) ?> เวลา <?= date('H:i', strtotime($letter['created_at'])) ?> น.</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <?php
                    endif;
                // ==========================================
                // หน้า: แสดงรายการหนังสือทั้งหมด (List View)
                // ==========================================
                else: 
                    // ดึงรายการหนังสือส่งทั้งหมด
                    $letters = dd_q("SELECT l.*, u.firstName, u.lastName 
                                     FROM outgoing_letters l 
                                     LEFT JOIN user u ON l.created_by = u.id 
                                     ORDER BY l.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                    
                    // คำนวณสถานะสำหรับแสดงผลการ์ดสรุป
                    $pending_count = 0;
                    $completed_count = 0;
                    foreach ($letters as $l) {
                        if ($l['status'] == 'pending_director') $pending_count++;
                        if ($l['status'] == 'completed') $completed_count++;
                    }
                ?>
                
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">รายการหนังสือส่งรอการพิจารณา</h1>
                        <p class="text-sm text-gray-500 mt-1">ตรวจสอบและลงนามหนังสือที่ส่งออกจากหน่วยงาน</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center gap-3 text-yellow-600 mb-2">
                            <i class="fa-solid fa-file-signature text-xl"></i>
                            <h3 class="font-bold">รอพิจารณา / ลงนาม</h3>
                        </div>
                        <p class="text-3xl font-bold text-gray-900"><?= number_format($pending_count) ?></p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center gap-3 text-emerald-600 mb-2">
                            <i class="fa-solid fa-check-double text-xl"></i>
                            <h3 class="font-bold">ลงนามแล้ว</h3>
                        </div>
                        <p class="text-3xl font-bold text-gray-900"><?= number_format($completed_count) ?></p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center gap-3 text-blue-600 mb-2">
                            <i class="fa-solid fa-layer-group text-xl"></i>
                            <h3 class="font-bold">หนังสือส่งทั้งหมด</h3>
                        </div>
                        <p class="text-3xl font-bold text-gray-900"><?= number_format(count($letters)) ?></p>
                    </div>
                </div>

                <!-- Table List -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-200 text-gray-500 text-xs uppercase">
                                    <th class="px-6 py-4 font-medium">เลขทะเบียน / วันที่</th>
                                    <th class="px-6 py-4 font-medium">เรื่อง</th>
                                    <th class="px-6 py-4 font-medium w-48">ผู้รับ</th>
                                    <th class="px-6 py-4 font-medium w-32 text-center">สถานะ</th>
                                    <th class="px-6 py-4 font-medium w-24 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php if (count($letters) > 0): ?>
                                    <?php foreach ($letters as $l): 
                                        $reg = "ศธ 04009.11/" . str_pad($l['id'], 3, '0', STR_PAD_LEFT);
                                    ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 align-top">
                                                <div class="font-medium text-indigo-600"><?= $reg ?></div>
                                                <div class="text-xs text-gray-500 mt-1"><?= getThaiDate($l['created_at']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 align-top">
                                                <div class="font-medium text-gray-900 mb-1"><?= htmlspecialchars($l['subject']) ?></div>
                                                <div class="flex items-center gap-2 mt-2">
                                                    <?php if($l['urgency'] != 'ปกติ'): ?>
                                                        <span class="px-2 py-0.5 bg-orange-50 text-orange-600 border border-orange-200 rounded-full text-[10px] font-medium">
                                                            <?= htmlspecialchars($l['urgency']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-600 border border-blue-200 rounded-full text-[10px] font-medium">
                                                        <?= htmlspecialchars($l['category']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 align-top text-gray-600">
                                                <?= htmlspecialchars($l['recipient']) ?>
                                            </td>
                                            <td class="px-6 py-4 align-top text-center">
                                                <?php if($l['status'] === 'pending_director'): ?>
                                                    <span class="inline-flex px-3 py-1 bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-full text-[11px] font-medium whitespace-nowrap">
                                                        รอลงนาม
                                                    </span>
                                                <?php elseif($l['status'] === 'completed'): ?>
                                                    <span class="inline-flex px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-[11px] font-medium whitespace-nowrap">
                                                        เสร็จสิ้น
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex px-3 py-1 bg-gray-50 text-gray-600 border border-gray-200 rounded-full text-[11px] font-medium whitespace-nowrap">
                                                        ร่าง
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 align-top text-center">
                                                <a href="outgoing.php?id=<?= $l['id'] ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors tooltip" title="ตรวจสอบรายละเอียด">
                                                    <i class="fa-regular fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                            ไม่พบข้อมูลหนังสือส่ง
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- Script สำหรับจัดการ SweetAlert และแปลง PDF -->
    <script>
        // ฟังก์ชันเมื่อกดปุ่ม "รับรองและลงนาม"
        function confirmApprove() {
            Swal.fire({
                title: 'ยืนยันการลงนาม?',
                text: "คุณต้องการพิจารณารับรองและลงนามหนังสือฉบับนี้ใช่หรือไม่",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2563eb', // bg-blue-600
                cancelButtonColor: '#d33',
                confirmButtonText: '<i class="fa-solid fa-check"></i> ใช่, ลงนามเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    // หากผู้ใช้กดยืนยัน ให้ submit ฟอร์ม
                    document.getElementById('approveForm').submit();
                }
            });
        }

        // ฟังก์ชันดาวน์โหลด PDF
        function downloadPDF() {
            // เลือก element ที่ต้องการปริ้นท์
            const element = document.getElementById('printable-area');
            
            // ตั้งค่าสำหรับ PDF
            const opt = {
                margin:       15,
                filename:     'หนังสือส่ง_<?= isset($reg_no) ? str_replace("/", "-", $reg_no) : "เอกสาร" ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // สร้างและดาวน์โหลด PDF
            html2pdf().set(opt).from(element).save();
        }

        // ตรวจสอบสถานะการทำงาน (รับพารามิเตอร์ success จาก URL)
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === 'approved') {
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: 'รับรองและลงนามหนังสือเรียบร้อยแล้ว',
                    icon: 'success',
                    confirmButtonColor: '#10b981', // bg-emerald-500
                    confirmButtonText: 'ตกลง'
                });
                
                // ลบ Query String เพื่อไม่ให้โชว์ alert ซ้ำเวลารีเฟรช
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + "?id=<?= $view_id ?>";
                window.history.replaceState({path:newUrl}, '', newUrl);
            }
        });
    </script>
</body>
</html>