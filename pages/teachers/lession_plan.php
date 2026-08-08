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
$userName = trim(($user['prefix'] ?? '') . ' ' . ($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
$initial = mb_substr($user['firstName'] ?? 'U', 0, 1, 'UTF-8');
$userRole = $user['role'] ?? 'teacher';

$msg = $_GET['msg'] ?? null;
$msg_type = $_GET['type'] ?? 'success';

// 3. จัดการ Action ต่างๆ (ลบ, ส่งตรวจทันที, บันทึก/อัปเดต)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $plan_id = !empty($_POST['plan_id']) ? (int)$_POST['plan_id'] : null;

    // --- Action: ลบแผนการสอน ---
    if ($action === 'delete_plan' && $plan_id) {
        $stmt_check = dd_q("SELECT filePath FROM lesson_plans WHERE id = ? AND teacherId = ?", [$plan_id, $_SESSION['id']]);
        $old_plan = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($old_plan) {
            if (!empty($old_plan['filePath'])) {
                $file_to_delete = __DIR__ . '/../../' . $old_plan['filePath'];
                if (file_exists($file_to_delete)) {
                    @unlink($file_to_delete);
                }
            }
            dd_q("DELETE FROM lesson_plans WHERE id = ? AND teacherId = ?", [$plan_id, $_SESSION['id']]);
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=deleted&type=success");
        exit();
    }

    // --- Action: เปลี่ยนสถานะฉบับร่างเป็นส่งตรวจทันที ---
    if ($action === 'submit_existing_draft' && $plan_id) {
        dd_q("UPDATE lesson_plans SET status = 'pending' WHERE id = ? AND teacherId = ?", [$plan_id, $_SESSION['id']]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=submitted&type=success");
        exit();
    }

    // --- Action: บันทึกใหม่ หรือ อัปเดตข้อมูล ---
    if ($action === 'save_draft' || $action === 'submit_plan') {
        $unit_name = trim($_POST['unit_name'] ?? '');
        $subject_group = trim($_POST['subject_group'] ?? '');
        $grade_level = trim($_POST['grade_level'] ?? '');
        $semester = trim($_POST['semester'] ?? 'ภาคเรียนที่ 1/2569');
        $hours = (int)($_POST['hours'] ?? 0);
        $standards = trim($_POST['standards'] ?? '');
        $indicators = trim($_POST['indicators'] ?? '');
        $objectives = trim($_POST['objectives'] ?? '');
        $activities = trim($_POST['activities'] ?? '');
        $evaluation = trim($_POST['evaluation'] ?? '');
        $resources = trim($_POST['resources'] ?? '');
        
        $status = ($action === 'submit_plan') ? 'pending' : 'draft';

        if ($action === 'submit_plan' && (empty($unit_name) || empty($subject_group) || empty($grade_level))) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=required_fields&type=error");
            exit();
        }

        // จัดการไฟล์ PDF
        $filePath = null;
        if (isset($_FILES['plan_file']) && $_FILES['plan_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['plan_file']['tmp_name'];
            $fileName = $_FILES['plan_file']['name'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($ext === 'pdf') {
                $uploadDir = __DIR__ . '/../../uploads/lesson_plans/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'PLAN_' . $_SESSION['id'] . '_' . time() . '_' . uniqid() . '.pdf';
                $targetPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $filePath = 'uploads/lesson_plans/' . $newFileName;
                }
            } else {
                header("Location: " . $_SERVER['PHP_SELF'] . "?msg=invalid_pdf&type=error");
                exit();
            }
        }

        try {
            if ($plan_id) {
                // UPDATE ข้อมูลเดิม
                if ($filePath) {
                    // หากมีการอัปโหลดไฟล์ใหม่ ให้ลบไฟล์เก่าทิ้ง
                    $stmt_old = dd_q("SELECT filePath FROM lesson_plans WHERE id = ? AND teacherId = ?", [$plan_id, $_SESSION['id']]);
                    $old_data = $stmt_old->fetch();
                    if ($old_data && !empty($old_data['filePath']) && file_exists(__DIR__ . '/../../' . $old_data['filePath'])) {
                        @unlink(__DIR__ . '/../../' . $old_data['filePath']);
                    }

                    $sql = "UPDATE lesson_plans SET 
                            unitName=?, subjectGroup=?, gradeLevel=?, semester=?, hours=?, 
                            standards=?, indicators=?, objectives=?, activities=?, evaluation=?, 
                            resources=?, filePath=?, status=? 
                            WHERE id=? AND teacherId=?";
                    $params = [
                        $unit_name, $subject_group, $grade_level, $semester, $hours,
                        $standards, $indicators, $objectives, $activities, $evaluation,
                        $resources, $filePath, $status, $plan_id, $_SESSION['id']
                    ];
                } else {
                    $sql = "UPDATE lesson_plans SET 
                            unitName=?, subjectGroup=?, gradeLevel=?, semester=?, hours=?, 
                            standards=?, indicators=?, objectives=?, activities=?, evaluation=?, 
                            resources=?, status=? 
                            WHERE id=? AND teacherId=?";
                    $params = [
                        $unit_name, $subject_group, $grade_level, $semester, $hours,
                        $standards, $indicators, $objectives, $activities, $evaluation,
                        $resources, $status, $plan_id, $_SESSION['id']
                    ];
                }
                dd_q($sql, $params);
                $redirect_msg = ($status === 'pending') ? 'submitted' : 'updated';
            } else {
                // INSERT ข้อมูลใหม่
                $sql = "INSERT INTO lesson_plans (
                            teacherId, unitName, subjectGroup, gradeLevel, semester, hours, 
                            standards, indicators, objectives, activities, evaluation, resources, 
                            filePath, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                dd_q($sql, [
                    $_SESSION['id'], $unit_name, $subject_group, $grade_level, $semester, $hours,
                    $standards, $indicators, $objectives, $activities, $evaluation, $resources,
                    $filePath, $status
                ]);
                $redirect_msg = ($status === 'pending') ? 'submitted' : 'draft_saved';
            }

            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . $redirect_msg . "&type=success");
            exit();
        } catch (Throwable $e) {
            die("เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage());
        }
    }
}

// 4. ตรวจสอบการดึงข้อมูลมาแก้ไข (GET edit_id)
$edit_plan = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt_edit = dd_q("SELECT * FROM lesson_plans WHERE id = ? AND teacherId = ? LIMIT 1", [$edit_id, $_SESSION['id']]);
    $edit_plan = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

// 5. ดึงรายการแผนการสอนทั้งหมด
$lesson_plans = [];
try {
    $stmt_plans = dd_q("SELECT * FROM lesson_plans WHERE teacherId = ? ORDER BY id DESC", [$_SESSION['id']]);
    $lesson_plans = $stmt_plans->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $lesson_plans = [];
}

// คำนวณสถิติ
$total_count = count($lesson_plans);
$pending_count = count(array_filter($lesson_plans, fn($item) => ($item['status'] ?? '') === 'pending'));
$approved_count = count(array_filter($lesson_plans, fn($item) => ($item['status'] ?? '') === 'approved'));
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบส่งแผนการสอน - AI School e-Office</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 h-screen overflow-hidden flex">

    <?php sis4_teacher_sidebar_render($userName, $initial); ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        
        <!-- Header -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-800">ระบบส่งแผนการสอน</h2>
                    <p class="text-xs text-slate-500">ครูส่งแผน &rarr; AI ตรวจความครบถ้วน &rarr; หัวหน้ากลุ่มสาระตรวจ &rarr; ผู้อำนวยการอนุมัติและลงนาม</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <input type="text" placeholder="ค้นหาหนังสือ เรื่อง ผู้ส่ง..." class="pl-9 pr-4 py-1.5 bg-slate-100 border-0 rounded-full text-xs w-64 focus:ring-2 focus:ring-indigo-500 outline-none">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- Alerts -->
            <?php if ($msg === 'submitted'): ?>
                <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i> ส่งแผนการสอนเข้าระบบเรียบร้อยแล้ว
                </div>
            <?php elseif ($msg === 'draft_saved'): ?>
                <div class="p-4 bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk text-blue-600"></i> บันทึกฉบับร่างเรียบร้อยแล้ว
                </div>
            <?php elseif ($msg === 'updated'): ?>
                <div class="p-4 bg-indigo-50 border border-indigo-200 text-indigo-800 text-sm rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-indigo-600"></i> อัปเดตข้อมูลแผนการสอนเรียบร้อยแล้ว
                </div>
            <?php elseif ($msg === 'deleted'): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-trash text-rose-600"></i> ลบแผนการสอนเรียบร้อยแล้ว
                </div>
            <?php elseif ($msg === 'required_fields'): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> กรุณากรอกข้อมูลสำคัญ (ชื่อหน่วย, กลุ่มสาระ, ระดับชั้น) ให้ครบถ้วน
                </div>
            <?php elseif ($msg === 'invalid_pdf'): ?>
                <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 text-sm rounded-xl flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> กรุณาอัปโหลดไฟล์ในรูปแบบ PDF เท่านั้น
                </div>
            <?php endif; ?>

            <!-- Status Cards Bar -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">แผนการสอนทั้งหมด</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $total_count ?></p>
                    </div>
                    <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
                        <i class="fa-solid fa-book-bookmark text-lg"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">รอการตรวจ</p>
                        <p class="text-2xl font-bold text-amber-600"><?= $pending_count ?></p>
                    </div>
                    <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                        <i class="fa-regular fa-clock text-lg"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">อนุมัติแล้ว</p>
                        <p class="text-2xl font-bold text-emerald-600"><?= $approved_count ?></p>
                    </div>
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                        <i class="fa-solid fa-circle-check text-lg"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">คะแนน AI เฉลี่ย</p>
                        <p class="text-2xl font-bold text-indigo-600">5/100</p>
                        <p class="text-[10px] text-slate-400">ความครบถ้วนตามองค์ประกอบ</p>
                    </div>
                    <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center">
                        <i class="fa-solid fa-wand-magic-sparkles text-lg"></i>
                    </div>
                </div>
            </div>

            <!-- Two Column Layout: List (Left) & Form (Right) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Left Side: List of Submitted Plans -->
                <div class="lg:col-span-7 space-y-4">
                    <h3 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-indigo-600"></i> รายการแผนการสอนที่บันทึกแล้ว
                    </h3>

                    <?php if (count($lesson_plans) > 0): ?>
                        <?php foreach ($lesson_plans as $plan): ?>
                            <?php 
                                $statusText = 'ฉบับร่าง';
                                $statusClass = 'bg-slate-100 text-slate-700 border-slate-200';
                                if (($plan['status'] ?? '') === 'pending') {
                                    $statusText = 'รอการตรวจ';
                                    $statusClass = 'bg-amber-50 text-amber-700 border-amber-200';
                                } elseif (($plan['status'] ?? '') === 'approved') {
                                    $statusText = 'อนุมัติแล้ว';
                                    $statusClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                }
                            ?>
                            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4 relative">
                                
                                <!-- Header & Actions -->
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="px-3 py-1 border rounded-full text-xs font-medium <?= $statusClass ?>">
                                            <?= $statusText ?>
                                        </span>
                                        <span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full text-xs">
                                            <?= htmlspecialchars($plan['subjectGroup']) ?>
                                        </span>
                                        <span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full text-xs">
                                            <?= htmlspecialchars($plan['gradeLevel']) ?>
                                        </span>
                                        <?php if (!empty($plan['filePath'])): ?>
                                            <a href="../../<?= htmlspecialchars($plan['filePath']) ?>" target="_blank" class="px-2.5 py-1 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-full text-xs flex items-center gap-1 transition-colors">
                                                <i class="fa-solid fa-file-pdf"></i> ดูไฟล์ PDF
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Action Buttons (สำหรับฉบับร่าง หรือรายการที่ยังไม่อนุมัติ) -->
                                    <div class="flex items-center gap-1.5">
                                        <?php if ($plan['status'] === 'draft'): ?>
                                            <form action="" method="POST" class="inline">
                                                <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                                <button type="submit" name="action" value="submit_existing_draft" onclick="return confirm('ยืนยันการส่งตรวจแผนการสอนนี้?')" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-xs font-medium transition-colors flex items-center gap-1">
                                                    <i class="fa-solid fa-paper-plane"></i> ส่งตรวจ
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($plan['status'] !== 'approved'): ?>
                                            <a href="?edit_id=<?= $plan['id'] ?>" class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 rounded-lg text-xs font-medium transition-colors flex items-center gap-1">
                                                <i class="fa-solid fa-pen-to-square"></i> แก้ไข
                                            </a>

                                            <form action="" method="POST" class="inline">
                                                <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                                <button type="submit" name="action" value="delete_plan" onclick="return confirm('คุณต้องการลบแผนการสอนนี้ใช่หรือไม่?')" class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-medium transition-colors flex items-center gap-1">
                                                    <i class="fa-solid fa-trash"></i> ลบ
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Title -->
                                <div>
                                    <h4 class="text-base font-bold text-slate-800 mb-1"><?= htmlspecialchars($plan['unitName']) ?></h4>
                                    <p class="text-xs text-slate-400">
                                        <?= htmlspecialchars($userName) ?> &bull; 
                                        <?= htmlspecialchars($plan['semester']) ?> &bull; 
                                        <?= htmlspecialchars($plan['hours']) ?> ชั่วโมง &bull; 
                                        บันทึกเมื่อ <?= date('d/m/Y H:i', strtotime($plan['createdAt'] ?? 'now')) ?>
                                    </p>
                                </div>

                                <!-- Standard & Objectives Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs pt-2 border-t border-slate-100">
                                    <div>
                                        <p class="font-semibold text-slate-700 mb-1">ตัวชี้วัด / มาตรฐาน</p>
                                        <div class="text-slate-600 whitespace-pre-line leading-relaxed">
                                            <?= htmlspecialchars($plan['standards'] ?? '') ?>
                                            <?= "\n" . htmlspecialchars($plan['indicators'] ?? '') ?>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-slate-700 mb-1">จุดประสงค์การเรียนรู้</p>
                                        <div class="text-slate-600 whitespace-pre-line leading-relaxed">
                                            <?= htmlspecialchars($plan['objectives'] ?? '') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bg-white border border-slate-200 rounded-2xl p-12 text-center text-slate-400 shadow-sm">
                            <i class="fa-solid fa-folder-open text-4xl mb-3 text-slate-300"></i>
                            <p class="text-sm font-medium">ยังไม่มีรายการแผนการสอน</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Side: Form (ส่ง/แก้ไขแผนการสอน) -->
                <div class="lg:col-span-5 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-5 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <i class="<?= $edit_plan ? 'fa-solid fa-pen-to-square text-amber-600' : 'fa-solid fa-paper-plane text-indigo-600' ?> text-lg"></i>
                            <h3 class="font-bold text-slate-800 text-base">
                                <?= $edit_plan ? 'แก้ไขแผนการสอน' : 'ส่งแผนการสอน' ?>
                            </h3>
                        </div>
                        <?php if ($edit_plan): ?>
                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="text-xs text-rose-500 hover:underline">
                                <i class="fa-solid fa-xmark"></i> ยกเลิกการแก้ไข
                            </a>
                        <?php endif; ?>
                    </div>

                    <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                        
                        <!-- Hidden Plan ID (สำหรับโหมดแก้ไข) -->
                        <input type="hidden" name="plan_id" value="<?= htmlspecialchars($edit_plan['id'] ?? '') ?>">

                        <!-- 1. ชื่อหน่วยการเรียนรู้ -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">ชื่อหน่วยการเรียนรู้ <span class="text-rose-500">*</span></label>
                            <input type="text" name="unit_name" required value="<?= htmlspecialchars($edit_plan['unitName'] ?? '') ?>" placeholder="เช่น หน่วยที่ 1 สิ่งมีชีวิตรอบตัว" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
                        </div>

                        <!-- 2 & 3. กลุ่มสาระ & ระดับชั้น -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">กลุ่มสาระ <span class="text-rose-500">*</span></label>
                                <select name="subject_group" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
                                    <?php 
                                        $groups = ["ภาษาไทย", "คณิตศาสตร์", "วิทยาศาสตร์และเทคโนโลยี", "สังคมศึกษา ศาสนา และวัฒนธรรม", "สุขศึกษาและพลศึกษา", "ศิลปะ", "การงานอาชีพ", "ภาษาต่างประเทศ"];
                                        $selectedGroup = $edit_plan['subjectGroup'] ?? '';
                                        foreach ($groups as $g) {
                                            $sel = ($selectedGroup === $g) ? 'selected' : '';
                                            echo "<option value=\"{$g}\" {$sel}>{$g}</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">ระดับชั้น <span class="text-rose-500">*</span></label>
                                <input type="text" name="grade_level" required value="<?= htmlspecialchars($edit_plan['gradeLevel'] ?? '') ?>" placeholder="เช่น ป.4 หรือ ม.1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
                            </div>
                        </div>

                        <!-- 4 & 5. ภาคเรียน & จำนวนชั่วโมง -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">ภาคเรียน</label>
                                <input type="text" name="semester" value="<?= htmlspecialchars($edit_plan['semester'] ?? 'ภาคเรียนที่ 1/2569') ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">จำนวนชั่วโมง</label>
                                <input type="number" name="hours" min="1" value="<?= htmlspecialchars($edit_plan['hours'] ?? '8') ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all">
                            </div>
                        </div>

                        <!-- 6. มาตรฐานการเรียนรู้ -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">มาตรฐานการเรียนรู้</label>
                            <textarea name="standards" rows="2" placeholder="มาตรฐาน ว 1.1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all resize-none"><?= htmlspecialchars($edit_plan['standards'] ?? '') ?></textarea>
                        </div>

                        <!-- 7. ตัวชี้วัด -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">ตัวชี้วัด</label>
                            <textarea name="indicators" rows="2" placeholder="ว 1.1 ป.4/1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all resize-none"><?= htmlspecialchars($edit_plan['indicators'] ?? '') ?></textarea>
                        </div>

                        <!-- 8. จุดประสงค์การเรียนรู้ -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">จุดประสงค์การเรียนรู้</label>
                            <textarea name="objectives" rows="3" placeholder="1. นักเรียนสามารถอธิบาย..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all resize-none"><?= htmlspecialchars($edit_plan['objectives'] ?? '') ?></textarea>
                        </div>

                        <!-- 9. กิจกรรมการเรียนรู้ -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">กิจกรรมการเรียนรู้</label>
                            <textarea name="activities" rows="3" placeholder="ระบุกิจกรรมการเรียนรู้..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all resize-none"><?= htmlspecialchars($edit_plan['activities'] ?? '') ?></textarea>
                        </div>

                        <!-- 10. การวัดและประเมินผล -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">การวัดและประเมินผล</label>
                            <textarea name="evaluation" rows="2" placeholder="เช่น การตรวจใบงาน..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all resize-none"><?= htmlspecialchars($edit_plan['evaluation'] ?? '') ?></textarea>
                        </div>

                        <!-- 11. สื่อและแหล่งเรียนรู้ -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">สื่อและแหล่งเรียนรู้</label>
                            <textarea name="resources" rows="2" placeholder="เช่น สื่อสไลด์..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none transition-all resize-none"><?= htmlspecialchars($edit_plan['resources'] ?? '') ?></textarea>
                        </div>

                        <!-- 12. ไฟล์แผนการสอน (PDF) -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">ไฟล์แผนการสอน (PDF)</label>
                            <?php if (!empty($edit_plan['filePath'])): ?>
                                <p class="text-[11px] text-indigo-600 mb-1">
                                    <i class="fa-solid fa-file-pdf"></i> มีไฟล์เดิมแล้ว (อัปโหลดใหม่เพื่อเปลี่ยน)
                                </p>
                            <?php endif; ?>
                            <input type="file" name="plan_file" accept="application/pdf,.pdf" class="w-full text-xs text-slate-600 border border-slate-200 rounded-lg bg-slate-50 p-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700 file:font-semibold hover:file:bg-indigo-100 transition-all">
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-3 grid grid-cols-2 gap-3">
                            <button type="submit" name="action" value="save_draft" class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium text-xs rounded-xl transition-colors flex items-center justify-center gap-2">
                                <i class="fa-regular fa-bookmark"></i> บันทึกฉบับร่าง
                            </button>
                            <button type="submit" name="action" value="submit_plan" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
                                <i class="fa-solid fa-paper-plane"></i> <?= $edit_plan ? 'อัปเดตและส่งตรวจ' : 'ส่งตรวจ' ?>
                            </button>
                        </div>

                    </form>
                </div>

            </div>

        </main>
    </div>

</body>
</html>