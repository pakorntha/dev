<?php
session_start();
require_once("../../system/a_func.php");
require_once("../../system/teacher_sidebar.php");

// 1. ตรวจสอบการล็อกอิน
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// 2. ดึงข้อมูลครูผู้ใช้งาน
$stmt_user = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$userName = trim(($user['prefix'] ?? '') . ' ' . ($user['firstName'] ?? 'วีระพงษ์') . ' ' . ($user['lastName'] ?? 'ชัยชนะ'));
$initial = mb_substr($user['firstName'] ?? 'ว', 0, 1, 'UTF-8');

$msg = $_GET['msg'] ?? null;

// 3. จัดการการยื่นใบลา (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_leave') {
    $leaveType = trim($_POST['leaveType'] ?? 'ลากิจ');
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!empty($startDate) && !empty($endDate) && !empty($reason)) {
        // คำนวณจำนวนวัน
        $d1 = new DateTime($startDate);
        $d2 = new DateTime($endDate);
        $totalDays = $d2->diff($d1)->days + 1;

        // จัดการอัปโหลดไฟล์
        $filePath = null;
        if (isset($_FILES['attach_file']) && $_FILES['attach_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp   = $_FILES['attach_file']['tmp_name'];
            $fileName  = $_FILES['attach_file']['name'];
            $fileSize  = $_FILES['attach_file']['size'];
            $ext       = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed   = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

            // ตรวจสอบชนิดไฟล์
            if (!in_array($ext, $allowed)) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?msg=invalid_file_type");
                exit();
            }

            // ตรวจสอบขนาดไฟล์ (ไม่เกิน 5MB)
            if ($fileSize > 5 * 1024 * 1024) {
                header("Location: " . $_SERVER['PHP_SELF'] . "?msg=file_too_large");
                exit();
            }

            $uploadDir = __DIR__ . '/../../uploads/leaves/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = 'LEAVE_' . $_SESSION['id'] . '_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                $filePath = 'uploads/leaves/' . $newFileName;
            }
        }

        try {
            $sql = "INSERT INTO leave_requests (teacherId, teacherName, leaveType, startDate, endDate, totalDays, reason, phone, filePath, status, deptHead, director) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'นางสุตารัตน์ ทองแท้', 'นายสมชาย มั่นคง')";
            dd_q($sql, [$_SESSION['id'], $userName, $leaveType, $startDate, $endDate, $totalDays, $reason, $phone, $filePath]);

            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=success");
            exit();
        } catch (Throwable $e) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=error");
            exit();
        }
    }
}

// 4. ดึงข้อมูลจาก Database (หากไม่มีข้อมูล จะใช้ Mock Data แสดงผล)
$my_leaves = [];
try {
    $stmt = dd_q("SELECT * FROM leave_requests WHERE teacherId = ? ORDER BY id DESC", [$_SESSION['id']]);
    $my_leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $my_leaves = [];
}

// ข้อมูลตัวอย่าง (Mock Data) หากยังไม่มีการบันทึกใน DB
if (empty($my_leaves)) {
    $my_leaves = [
        [
            'id' => 1,
            'teacherName' => $userName,
            'leaveType' => 'ลากิจ',
            'startDate' => '2026-08-06',
            'endDate' => '2026-08-06',
            'totalDays' => 1,
            'reason' => 'ธุระส่วนตัวเร่งด่วนของครอบครัว',
            'filePath' => null,
            'status' => 'approved',
            'createdAt' => '2026-08-03 09:00:00',
            'deptHead' => 'นางสุตารัตน์ ทองแท้',
            'deptHeadStatus' => 'เห็นชอบ',
            'deptHeadTime' => '3 ส.ค. 69 11:00 น.',
            'director' => 'นายสมชาย มั่นคง',
            'directorStatus' => 'อนุมัติ',
            'directorTime' => '4 ส.ค. 69 14:00 น.'
        ]
    ];
}

// สถิติต่างๆ
$my_total_count = count($my_leaves);
$pending_count = count(array_filter($my_leaves, fn($i) => ($i['status'] ?? '') === 'pending')) + 3;
$school_leave_today = 0;
$used_days_this_year = array_sum(array_column(array_filter($my_leaves, fn($i) => ($i['status'] ?? '') === 'approved'), 'totalDays'));

// วันลาคงเหลือ (ปี 2569) - ตามระเบียบข้าราชการครู
$leave_allowance = [
    'ลากิจส่วนตัว' => ['used' => 1, 'max' => 45],
    'ลาป่วย' => ['used' => 0, 'max' => 60],
    'ลาพักผ่อน' => ['used' => 0, 'max' => 10],
    'ลาคลอดบุตร' => ['used' => 0, 'max' => 90]
];

// ปฏิทินวันลาวันนี้ (Mock Data)
$today_leaves = [
    ['name' => 'นายสมศักดิ์ รักดี', 'type' => 'ลาป่วย', 'dept' => 'กลุ่มสาระฯ วิทยาศาสตร์', 'avatarBg' => 'bg-emerald-600', 'initial' => 'ส'],
    ['name' => 'นางสาวกนกวรรณ ใจงาม', 'type' => 'ลากิจ', 'dept' => 'กลุ่มสาระฯ ภาษาไทย', 'avatarBg' => 'bg-amber-600', 'initial' => 'ก']
];
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบการลา - AI School e-Office</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>

<body class="bg-[#f8fafc] text-slate-800 h-screen overflow-hidden flex">

    <?php sis4_teacher_sidebar_render($userName, $initial); ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <!-- Header Top Bar -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8 flex-shrink-0">
            <div></div>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <input type="text" placeholder="ค้นหาหนังสือ เรื่อง ผู้ส่ง เลขทะเบียน..."
                        class="pl-9 pr-4 py-1.5 bg-slate-100 border-0 rounded-full text-xs w-72 focus:ring-2 focus:ring-indigo-500 outline-none">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                </div>
                <div class="relative p-2 bg-slate-100 rounded-full text-slate-600 hover:bg-slate-200 cursor-pointer">
                    <i class="fa-regular fa-bell text-sm"></i>
                    <span
                        class="absolute top-0 right-0 w-4 h-4 bg-rose-500 text-white rounded-full text-[10px] flex items-center justify-center font-bold">2</span>
                </div>
                <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
                    <div
                        class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-semibold text-xs">
                        <?= $initial ?>
                    </div>
                    <div class="text-xs">
                        <p class="font-bold text-slate-800"><?= htmlspecialchars($userName) ?></p>
                        <p class="text-slate-400">ครู</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- Title & Subtitle -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-lg">
                    <i class="fa-regular fa-calendar-minus"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-800">ระบบการลา</h2>
                    <p class="text-xs text-slate-400">ยื่นใบลา ติดตามการอนุมัติ
                        และตรวจสอบวันลาคงเหลือตามระเบียบข้าราชการครู</p>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($msg === 'success'): ?>
                <div
                    class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i> ยื่นใบลาและอัปโหลดเอกสารเรียบร้อยแล้ว รอเสนอผู้บังคับบัญชาอนุมัติตามลำดับ
                </div>
            <?php elseif ($msg === 'invalid_file_type'): ?>
                <div
                    class="p-4 bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> รองรับเฉพาะไฟล์ PDF, JPG, PNG, DOC, DOCX เท่านั้น
                </div>
            <?php elseif ($msg === 'file_too_large'): ?>
                <div
                    class="p-4 bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> ขนาดไฟล์แนบต้องไม่เกิน 5MB
                </div>
            <?php elseif ($msg === 'error'): ?>
                <div
                    class="p-4 bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> เกิดข้อผิดพลาดในการยื่นใบลา กรุณาลองใหม่อีกครั้ง
                </div>
            <?php endif; ?>

            <!-- Metric Cards (4 Cards Top Row) -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                <div
                    class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">ใบลาของฉัน</p>
                        <p class="text-3xl font-bold text-slate-800"><?= $my_total_count ?></p>
                    </div>
                    <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
                        <i class="fa-regular fa-file-lines text-lg"></i>
                    </div>
                </div>

                <div
                    class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">รอการอนุมัติ</p>
                        <p class="text-3xl font-bold text-slate-800"><?= $pending_count ?></p>
                    </div>
                </div>

                <div
                    class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">ลาวันนี้ทั้งโรงเรียน</p>
                        <p class="text-3xl font-bold text-slate-800"><?= $school_leave_today ?></p>
                    </div>
                </div>

                <div
                    class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">วันลาที่ใช้ในปีนี้</p>
                        <p class="text-3xl font-bold text-slate-800"><?= $used_days_this_year ?></p>
                        <p class="text-[10px] text-slate-400 mt-0.5">เฉพาะใบลาที่อนุมัติแล้ว</p>
                    </div>
                </div>

            </div>

            <!-- Main Two Column Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- Left Column (ประวัติการลา) -->
                <div class="lg:col-span-7 space-y-6">

                    <!-- Section: ประวัติการลาของฉัน -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                            <i class="fa-regular fa-clipboard text-indigo-600"></i> ประวัติการลาของฉัน
                        </h3>

                        <?php foreach ($my_leaves as $item): ?>
                            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-slate-800 text-sm">
                                                <?= htmlspecialchars($item['teacherName']) ?></h4>
                                            <span
                                                class="px-2.5 py-0.5 bg-slate-100 text-slate-600 rounded-full text-xs font-medium">
                                                <?= htmlspecialchars($item['leaveType']) ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-400 mt-1">
                                            <?= date('j สิงหาคม Y', strtotime($item['startDate'])) ?> -
                                            <?= date('j สิงหาคม Y', strtotime($item['endDate'])) ?> &bull;
                                            <?= $item['totalDays'] ?> วัน
                                        </p>
                                    </div>
                                    <div>
                                        <?php if (($item['status'] ?? '') === 'approved'): ?>
                                            <span
                                                class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-semibold">
                                                อนุมัติแล้ว
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-xs font-semibold">
                                                รอการอนุมัติ
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <p class="text-xs text-slate-700 font-medium"><?= htmlspecialchars($item['reason']) ?></p>

                                <!-- ปุ่มดูไฟล์แนบ (ถ้ามี) -->
                                <?php if (!empty($item['filePath'])): ?>
                                    <div class="pt-1">
                                        <a href="../../<?= htmlspecialchars($item['filePath']) ?>" target="_blank"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-medium transition-colors">
                                            <i class="fa-solid fa-paperclip"></i> ดูเอกสารแนบ
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Flow Timeline Audit -->
                                <div class="pt-3 border-t border-slate-100 space-y-1.5 text-[11px] text-slate-500">
                                    <p>&bull; ยื่นใบลา โดย <?= htmlspecialchars($item['teacherName']) ?> &bull;
                                        <?= date('d/m/Y H:i', strtotime($item['createdAt'] ?? 'now')) ?></p>
                                    <?php if (!empty($item['deptHead'])): ?>
                                        <p>&bull; หัวหน้ากลุ่มงาน<?= htmlspecialchars($item['deptHeadStatus'] ?? 'เห็นชอบ') ?>
                                            โดย <?= htmlspecialchars($item['deptHead']) ?> &bull;
                                            <?= htmlspecialchars($item['deptHeadTime'] ?? '-') ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['director'])): ?>
                                        <p>&bull; ผู้อำนวยการ<?= htmlspecialchars($item['directorStatus'] ?? 'อนุมัติ') ?> โดย
                                            <?= htmlspecialchars($item['director']) ?> &bull;
                                            <?= htmlspecialchars($item['directorTime'] ?? '-') ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>

                <!-- Right Column (ยื่นใบลา -> วันลาคงเหลือ -> ปฏิทินวันลาวันนี้) -->
                <div class="lg:col-span-5 space-y-6">

                    <!-- 1. ฟอร์มยื่นใบลา -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
                            <i class="fa-solid fa-paper-plane text-indigo-600 text-base"></i>
                            <h3 class="font-bold text-slate-800 text-base">ยื่นใบลา</h3>
                        </div>

                        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="action" value="submit_leave">

                            <!-- ประเภทการลา -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">ประเภทการลา <span
                                        class="text-rose-500">*</span></label>
                                <select name="leaveType" required
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
                                    <option value="ลากิจ">ลากิจ</option>
                                    <option value="ลาป่วย">ลาป่วย</option>
                                    <option value="ลาพักผ่อน">ลาพักผ่อน</option>
                                    <option value="ลาคลอดบุตร">ลาคลอดบุตร</option>
                                    <option value="ลาเข้ารับการตรวจเลือกหรือเตรียมพล">ลาเข้ารับการตรวจเลือกหรือเตรียมพล
                                    </option>
                                </select>
                            </div>

                            <!-- ตั้งแต่วันที่ & ถึงวันที่ -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">ตั้งแต่วันที่ <span
                                            class="text-rose-500">*</span></label>
                                    <input type="date" name="startDate" required value="2026-08-09"
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">ถึงวันที่ <span
                                            class="text-rose-500">*</span></label>
                                    <input type="date" name="endDate" required value="2026-08-09"
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
                                </div>
                            </div>

                            <!-- เหตุผลการลา -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">เหตุผลการลา <span
                                        class="text-rose-500">*</span></label>
                                <textarea name="reason" rows="3" required placeholder="ระบุเหตุผลการลาโดยละเอียด..."
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all resize-none"></textarea>
                            </div>

                            <!-- เบอร์ติดต่อระหว่างลา -->
                            <div>
                                <label
                                    class="block text-xs font-semibold text-slate-700 mb-1">เบอร์ติดต่อระหว่างลา</label>
                                <input type="text" name="phone" placeholder="08x-xxx-xxxx"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
                            </div>

                            <!-- ไฟล์แนบ -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">ไฟล์แนบ (ใบรับรองแพทย์)</label>
                                <input type="file" name="attach_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                    class="w-full text-xs text-slate-600 border border-slate-200 rounded-lg bg-slate-50 p-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700 file:font-semibold hover:file:bg-indigo-100 transition-all">
                                <p class="text-[10px] text-slate-400 mt-1">รองรับไฟล์ PDF, JPG, PNG, DOC, DOCX (ไม่เกิน 5MB)</p>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit"
                                class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
                                ยื่นใบลา
                            </button>

                            <p class="text-[11px] text-slate-400 text-center leading-relaxed">
                                ใบลาจะส่งไปยังหัวหน้ากลุ่มงาน แล้วเสนอผู้อำนวยการตามลำดับ
                            </p>
                        </form>
                    </div>

                    <!-- 2. วันลาคงเหลือของฉัน (ปี 2569) -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                <i class="fa-regular fa-chart-bar text-indigo-600"></i> วันลาคงเหลือของฉัน (ปี 2569)
                            </h3>
                        </div>

                        <div class="grid grid-cols-1 gap-3">
                            <?php foreach ($leave_allowance as $type_name => $data): ?>
                                <?php
                                $remaining = $data['max'] - $data['used'];
                                $percent = ($data['used'] / $data['max']) * 100;
                                ?>
                                <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl space-y-2">
                                    <div class="flex items-baseline justify-between">
                                        <p class="text-xs font-semibold text-slate-700"><?= $type_name ?></p>
                                        <span class="text-[10px] text-slate-400">คงเหลือ <?= $remaining ?> / <?= $data['max'] ?> วัน</span>
                                    </div>
                                    <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-indigo-600 h-1.5 rounded-full" style="width: <?= $percent ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 3. ปฏิทินวันลาวันนี้ -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                <i class="fa-regular fa-calendar-check text-indigo-600"></i> ปฏิทินวันลาวันนี้
                                (<?= date('9 สิงหาคม 2569') ?>)
                            </h3>
                        </div>

                        <?php if (count($today_leaves) > 0): ?>
                            <div class="space-y-3">
                                <?php foreach ($today_leaves as $person): ?>
                                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-8 h-8 rounded-full <?= $person['avatarBg'] ?> text-white font-bold flex items-center justify-center text-xs">
                                                <?= $person['initial'] ?>
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-slate-800"><?= $person['name'] ?></p>
                                                <p class="text-[10px] text-slate-400"><?= $person['dept'] ?></p>
                                            </div>
                                        </div>
                                        <span
                                            class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-xs font-medium">
                                            <?= $person['type'] ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-xs text-slate-400 text-center py-4">วันนี้ไม่มีครูหรือบุคลากรลา</p>
                        <?php endif; ?>
                    </div>

                </div>

            </div>

        </main>
    </div>

</body>

</html>