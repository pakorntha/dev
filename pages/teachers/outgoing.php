<?php
session_start();
require_once("../../system/a_func.php");
require_once("../../system/teacher_sidebar.php");

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['id'];
$success_msg = "";
$error_msg = "";

// ดึงข้อมูลครูที่ล็อกอิน
$stmt = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$user_id]);
if ($stmt->rowCount() === 1) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user['role'] !== 'teacher') {
        header("Location: ../../index.php");
        exit();
    }
    $fullName = $user['prefix'] . $user['firstName'] . ' ' . $user['lastName'];
    $initial = mb_substr($user['firstName'], 0, 1, 'UTF-8');
} else {
    session_destroy();
    header("Location: ../../systemlogin.php");
    exit();
}

// ฟังก์ชันแปลงวันที่เป็นภาษาไทยแบบย่อ (เช่น 7 ส.ค. 69)
function getThaiDateShort($datetime) {
    if (!$datetime) return "-";
    $time = strtotime($datetime);
    $thai_months = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
        7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];
    $d = date('j', $time);
    $m = $thai_months[(int)date('n', $time)];
    $y = date('Y', $time) + 543;
    $y_short = substr($y, 2, 2);
    $time_str = date('H:i', $time);
    return "$d $m $y_short <br><span class='text-xs text-gray-400'>เวลา $time_str น.</span>";
}

// ---------------------------------------------------------
// จัดการ POST: บันทึกข้อมูลเมื่อกดส่งฟอร์มสร้างหนังสือ
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_draft') {
    $subject = trim($_POST['subject']);
    $recipient = trim($_POST['recipient']);
    $category = trim($_POST['category']);
    $urgency = trim($_POST['urgency']);
    $content = trim($_POST['content']);

    if (empty($subject) || empty($recipient) || empty($content)) {
        $error_msg = "กรุณากรอกข้อมูลในช่องที่มีเครื่องหมาย * ให้ครบถ้วน";
    } else {
        try {
            $sql = "INSERT INTO outgoing_letters (subject, recipient, category, urgency, content, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, 'pending_director', ?)";
            $stmt = dd_q($sql, [$subject, $recipient, $category, $urgency, $content, $user_id]);

            if ($stmt) {
                // บันทึกสำเร็จ กลับไปหน้าแสดงรายการพร้อมแจ้งเตือน
                header("Location: outgoing.php?success=created");
                exit();
            }
        } catch (Exception $e) {
            $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
        }
    }
}

// เช็คหน้าที่จะแสดง (list หรือ create)
$page = isset($_GET['page']) ? $_GET['page'] : 'list';

// แจ้งเตือนความสำเร็จจาก URL
if (isset($_GET['success']) && $_GET['success'] == 'created') {
    $success_msg = "ร่างหนังสือส่งถูกบันทึกและส่งเข้าระบบรอผู้อำนวยการลงนามเรียบร้อยแล้ว";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทะเบียนหนังสือส่ง - ระบบสารบรรณ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
        .form-input { width: 100%; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; transition: border-color 0.2s; }
        .form-input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 1px #6366f1; }
        .form-label { display: block; font-size: 0.875rem; color: #4b5563; margin-bottom: 0.5rem; font-weight: 500; }
        .required { color: #ef4444; }
        .template-card { transition: all 0.2s ease-in-out; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>

<body class="text-gray-800 flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <?php sis4_teacher_sidebar_render($fullName, $initial); ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden">

        <!-- Top Header -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <!-- Search -->
            <div class="relative w-96 hidden sm:block">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" placeholder="ค้นหาหนังสือ เรื่อง ผู้ส่ง เลขทะเบียน..."
                    class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-full text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white transition-all">
            </div>

            <!-- Top Right Actions -->
            <div class="flex items-center gap-4 ml-auto">
                <button class="relative text-gray-500 hover:text-gray-700">
                    <i class="fa-solid fa-bell text-lg"></i>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full border-2 border-white">6</span>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <div class="text-sm font-medium text-gray-700 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs">
                        <?= $initial ?>
                    </div>
                    <?= htmlspecialchars($fullName) ?>
                </div>
            </div>
        </header>

        <!-- Main Content (Scrollable) -->
        <main class="flex-1 overflow-y-auto p-6">
            <div class="max-w-7xl mx-auto">

                <?php if ($success_msg): ?>
                    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex items-center gap-2">
                        <i class="fa-solid fa-check-circle"></i> <?= $success_msg ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= $error_msg ?>
                    </div>
                <?php endif; ?>

                <?php 
                // ==========================================
                // หน้า: แสดงรายการหนังสือ (List View)
                // ==========================================
                if ($page === 'list'): 
                    // ดึงสถิติ
                    $total = dd_q("SELECT COUNT(id) as c FROM outgoing_letters")->fetch(PDO::FETCH_ASSOC)['c'];
                    $pending = dd_q("SELECT COUNT(id) as c FROM outgoing_letters WHERE status = 'pending_director'")->fetch(PDO::FETCH_ASSOC)['c'];
                    $completed = dd_q("SELECT COUNT(id) as c FROM outgoing_letters WHERE status = 'completed'")->fetch(PDO::FETCH_ASSOC)['c'];
                    
                    // ดึงรายการหนังสือทั้งหมด
                    $letters = dd_q("SELECT * FROM outgoing_letters ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <!-- Title Section -->
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl shadow-sm">
                            <i class="fa-solid fa-file-export"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">ทะเบียนหนังสือส่ง</h1>
                            <p class="text-sm text-gray-500 mt-1">หนังสือที่โรงเรียนออกไปยังหน่วยงานภายนอก · รูปแบบเลขที่ ศธ 04009.11/{SEQ}</p>
                        </div>
                    </div>
                    <a href="outgoing.php?page=create" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                        <i class="fa-regular fa-file-lines"></i> ร่างหนังสือส่ง
                    </a>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                        <div class="absolute top-4 right-4 w-10 h-10 bg-indigo-50 text-indigo-500 rounded-lg flex items-center justify-center text-lg">
                            <i class="fa-solid fa-file-export"></i>
                        </div>
                        <h3 class="text-gray-500 text-sm font-medium mb-1">หนังสือส่งทั้งหมด</h3>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($total) ?></p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                        <h3 class="text-gray-500 text-sm font-medium mb-1">รอผู้อำนวยการลงนาม</h3>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($pending) ?></p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm relative overflow-hidden">
                        <h3 class="text-gray-500 text-sm font-medium mb-1">ลงนามและส่งแล้ว</h3>
                        <p class="text-3xl font-bold text-gray-800"><?= number_format($completed) ?></p>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-200 text-gray-500 text-xs uppercase">
                                    <th class="px-6 py-4 font-medium w-40">เลขทะเบียน</th>
                                    <th class="px-6 py-4 font-medium">เรื่อง</th>
                                    <th class="px-6 py-4 font-medium w-64">ผู้รับ</th>
                                    <th class="px-6 py-4 font-medium w-32">ลงวันที่</th>
                                    <th class="px-6 py-4 font-medium w-32 text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php if (count($letters) > 0): ?>
                                    <?php foreach ($letters as $l): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 align-top">
                                                <div class="font-medium text-indigo-600">ศธ 04009.11/<?= str_pad($l['id'], 3, '0', STR_PAD_LEFT) ?></div>
                                                <div class="text-xs text-gray-400 mt-0.5">ศธ 04009.11/<?= str_pad($l['id'], 3, '0', STR_PAD_LEFT) ?></div>
                                            </td>
                                            <td class="px-6 py-4 align-top">
                                                <div class="font-medium text-gray-900 mb-1"><?= htmlspecialchars($l['subject']) ?></div>
                                                <div class="text-xs text-gray-500 line-clamp-1 mb-2"><?= htmlspecialchars(mb_substr($l['content'], 0, 80, 'UTF-8')) ?>...</div>
                                                <div class="flex items-center gap-2">
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
                                            <td class="px-6 py-4 align-top text-gray-600">
                                                <?= getThaiDateShort($l['created_at']) ?>
                                            </td>
                                            <td class="px-6 py-4 align-top text-center">
                                                <?php if($l['status'] === 'pending_director'): ?>
                                                    <span class="inline-flex px-3 py-1 bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-full text-[11px] font-medium whitespace-nowrap">
                                                        รอ ผอ. พิจารณา
                                                    </span>
                                                <?php elseif($l['status'] === 'completed'): ?>
                                                    <span class="inline-flex px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-[11px] font-medium whitespace-nowrap">
                                                        เสร็จสิ้น
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex px-3 py-1 bg-gray-50 text-gray-600 border border-gray-200 rounded-full text-[11px] font-medium whitespace-nowrap">
                                                        ร่าง/แก้ไข
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                            <i class="fa-regular fa-folder-open text-3xl mb-3 text-gray-300 block"></i>
                                            ยังไม่มีประวัติการส่งหนังสือ
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>


                <?php 
                // ==========================================
                // หน้า: ร่างหนังสือส่ง (Create Form View)
                // ==========================================
                elseif ($page === 'create'): 
                ?>

                <!-- Title Section -->
                <div class="flex items-center gap-4 mb-6">
                    <a href="outgoing.php" class="w-10 h-10 bg-white border border-gray-200 text-gray-500 hover:text-indigo-600 hover:border-indigo-200 rounded-xl flex items-center justify-center transition-colors shadow-sm">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl shadow-sm">
                        <i class="fa-solid fa-file-pen"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">ร่างหนังสือส่ง</h1>
                        <p class="text-sm text-gray-500 mt-1">เลือกแม่แบบหนังสือ กรอกเนื้อหา แล้วเสนอผู้อำนวยการลงนามอิเล็กทรอนิกส์</p>
                    </div>
                </div>

                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- Main Form Content (Left) -->
                    <div class="flex-1 bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <form action="outgoing.php?page=create" method="POST" class="p-6" id="draftForm">
                            <input type="hidden" name="action" value="save_draft">

                            <div class="flex items-center gap-2 mb-6 border-b border-gray-100 pb-4">
                                <i class="fa-regular fa-file-lines text-indigo-500"></i>
                                <h2 class="font-bold text-gray-800">เนื้อหาหนังสือ</h2>
                            </div>

                            <div class="space-y-5">
                                <div>
                                    <label class="form-label">เรื่อง <span class="required">*</span></label>
                                    <input type="text" name="subject" id="input-subject" class="form-input" required value="">
                                </div>

                                <div>
                                    <label class="form-label">เรียน (หน่วยงาน/บุคคลผู้รับ) <span class="required">*</span></label>
                                    <input type="text" name="recipient" id="input-recipient" class="form-input" required value="">
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="form-label">หมวดหมู่</label>
                                        <select name="category" id="input-category" class="form-input bg-white cursor-pointer">
                                            <option value="งานบริหารทั่วไป">งานบริหารทั่วไป</option>
                                            <option value="งานกิจการนักเรียน">งานกิจการนักเรียน</option>
                                            <option value="งานวิชาการ">งานวิชาการ</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label">ชั้นความเร็ว</label>
                                        <select name="urgency" class="form-input bg-white cursor-pointer">
                                            <option value="ปกติ" selected>ปกติ</option>
                                            <option value="ด่วน">ด่วน</option>
                                            <option value="ด่วนมาก">ด่วนมาก</option>
                                            <option value="ด่วนที่สุด">ด่วนที่สุด</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="form-label">เนื้อความ <span class="required">*</span></label>
                                    <textarea name="content" id="input-content" rows="12" class="form-input resize-none leading-relaxed" required></textarea>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end gap-3 pt-5 border-t border-gray-100">
                                <a href="outgoing.php" class="px-5 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors">ยกเลิก</a>
                                <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                                    <i class="fa-regular fa-paper-plane"></i> บันทึกและเสนอผู้อำนวยการ
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Sidebar (Right) - Templates Box -->
                    <div class="w-full lg:w-80 space-y-4">
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="fa-solid fa-wand-magic-sparkles text-purple-500"></i>
                                <h2 class="font-bold text-gray-800 text-sm">แม่แบบหนังสือ</h2>
                            </div>
                            <div class="space-y-3" id="template-container">
                                <!-- Template 1: รายงาน -->
                                <div onclick="selectTemplate('report')" id="tpl-report" class="template-card p-3 rounded-xl cursor-pointer border">
                                    <h3 class="font-medium text-gray-900 text-sm mb-1">หนังสือรายงานผลการดำเนินงาน</h3>
                                    <p class="text-xs text-gray-500 mb-2 truncate">รายงานผลการดำเนินงานตามที่ได้รับมอบหมาย</p>
                                    <span class="inline-block bg-gray-100 border border-gray-200 text-gray-600 text-[10px] px-2 py-0.5 rounded-full">งานบริหารทั่วไป</span>
                                </div>

                                <!-- Template 2: ขออนุญาต -->
                                <div onclick="selectTemplate('permission')" id="tpl-permission" class="template-card p-3 rounded-xl cursor-pointer border">
                                    <h3 class="font-medium text-gray-900 text-sm mb-1">หนังสือขออนุญาต / ขออนุมัติ</h3>
                                    <p class="text-xs text-gray-500 mb-2 truncate">ขออนุญาตนำนักเรียนไปทัศนศึกษานอกสถานที่</p>
                                    <span class="inline-block bg-gray-100 border border-gray-200 text-gray-600 text-[10px] px-2 py-0.5 rounded-full">งานกิจการนักเรียน</span>
                                </div>

                                <!-- Template 3: ขอความอนุเคราะห์ -->
                                <div onclick="selectTemplate('request')" id="tpl-request" class="template-card p-3 rounded-xl cursor-pointer border">
                                    <h3 class="font-medium text-gray-900 text-sm mb-1">หนังสือขอความอนุเคราะห์</h3>
                                    <p class="text-xs text-gray-500 mb-2 truncate">ขอความอนุเคราะห์วิทยากรให้ความรู้แก่นักเรียน</p>
                                    <span class="inline-block bg-gray-100 border border-gray-200 text-gray-600 text-[10px] px-2 py-0.5 rounded-full">งานบริหารทั่วไป</span>
                                </div>
                            </div>
                        </div>

                        <!-- Info Box -->
                        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5">
                            <p class="text-sm text-gray-500 leading-relaxed">
                                เมื่อบันทึกแล้ว หนังสือจะเข้าสู่ขั้นตอนรอผู้อำนวยการลงนามอิเล็กทรอนิกส์
                                เมื่อลงนามเรียบร้อยระบบจะออกเลขที่หนังสือ จัดเก็บเข้าแฟ้ม
                                และบันทึกลงประวัติการดำเนินการโดยอัตโนมัติ
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- Script สำหรับจัดการ Template เฉพาะในหน้า create -->
    <?php if ($page === 'create'): ?>
    <script>
        const templatesData = {
            'report': {
                subject: 'รายงานผลการดำเนินงานตามที่ได้รับมอบหมาย',
                recipient: 'ผู้อำนวยการสำนักงานเขตพื้นที่การศึกษา',
                category: 'งานบริหารทั่วไป',
                content: `ตามที่สำนักงานเขตพื้นที่การศึกษา ได้แจ้งให้สถานศึกษาดำเนินการตามหนังสือที่อ้างถึงนั้น\n\nโรงเรียนบ้านหนองปลาสร้อย ได้ดำเนินการเรียบร้อยแล้ว จึงขอรายงานผลการดำเนินงานมาเพื่อโปรดทราบ รายละเอียดตามเอกสารที่แนบมาพร้อมนี้\n\n1. ผลการดำเนินงานตามวัตถุประสงค์\n2. ปัญหาอุปสรรคและข้อเสนอแนะ\n3. ภาพถ่ายประกอบการดำเนินงาน\n\nจึงเรียนมาเพื่อโปรดทราบ`
            },
            'permission': {
                subject: 'ขออนุญาตนำนักเรียนไปทัศนศึกษานอกสถานที่',
                recipient: 'ผู้อำนวยการโรงเรียน',
                category: 'งานกิจการนักเรียน',
                content: `ด้วยกลุ่มสาระการเรียนรู้ [ระบุกลุ่มสาระ] มีความประสงค์จะนำนักเรียนชั้น [ระบุชั้นเรียน] จำนวน [ระบุจำนวน] คน ไปศึกษาแหล่งเรียนรู้นอกสถานที่ ณ [ระบุสถานที่] ในวันที่ [ระบุวันที่]\n\nเพื่อเป็นการเพิ่มพูนประสบการณ์และบูรณาการการเรียนการสอน โดยมีคณะครูผู้ควบคุมดูแลจำนวน [ระบุจำนวน] ท่าน ดังรายชื่อแนบ\n\nจึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ`
            },
            'request': {
                subject: 'ขอความอนุเคราะห์วิทยากรให้ความรู้แก่นักเรียน',
                recipient: 'ผู้อำนวยการ [ระบุหน่วยงาน]',
                category: 'งานบริหารทั่วไป',
                content: `ด้วยโรงเรียนบ้านหนองปลาสร้อย ได้กำหนดจัดกิจกรรม [ระบุชื่อกิจกรรม] ให้แก่นักเรียนชั้น [ระบุชั้น] ในวันที่ [ระบุวันที่] ณ [ระบุสถานที่] \n\nในการนี้ โรงเรียนพิจารณาแล้วเห็นว่าหน่วยงานของท่านมีบุคลากรผู้มีความรู้ความสามารถและประสบการณ์ตรง จึงขอความอนุเคราะห์ท่านพิจารณามอบหมายบุคลากรมาเป็นวิทยากรบรรยายให้ความรู้แก่นักเรียนตามวันและเวลาดังกล่าว\n\nจึงเรียนมาเพื่อโปรดพิจารณาให้ความอนุเคราะห์ และขอขอบคุณมา ณ โอกาสนี้`
            }
        };

        function selectTemplate(templateId) {
            const data = templatesData[templateId];
            if (data) {
                document.getElementById('input-subject').value = data.subject;
                document.getElementById('input-recipient').value = data.recipient;
                document.getElementById('input-category').value = data.category;
                document.getElementById('input-content').value = data.content;
            }

            const allCards = document.querySelectorAll('.template-card');
            allCards.forEach(card => {
                card.classList.remove('bg-indigo-50/50', 'border-indigo-200');
                card.classList.add('bg-white', 'border-gray-100', 'hover:border-gray-300', 'hover:bg-gray-50');
                const badge = card.querySelector('span');
                badge.classList.remove('bg-white');
                badge.classList.add('bg-gray-100');
            });

            const activeCard = document.getElementById(`tpl-${templateId}`);
            activeCard.classList.remove('bg-white', 'border-gray-100', 'hover:border-gray-300', 'hover:bg-gray-50');
            activeCard.classList.add('bg-indigo-50/50', 'border-indigo-200');

            const activeBadge = activeCard.querySelector('span');
            activeBadge.classList.remove('bg-gray-100');
            activeBadge.classList.add('bg-white');
        }

        window.onload = function () {
            selectTemplate('report');
        };
    </script>
    <?php endif; ?>
</body>
</html>