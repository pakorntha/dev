<?php
session_start();
// เรียกใช้งาน autoload ของ Composer สำหรับ mPDF
require_once("../../vendor/autoload.php");
require_once("../../system/a_func.php");
require_once("../../system/teacher_sidebar.php");

// 1. ตรวจสอบการล็อกอิน
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// 2. ดึงข้อมูลผู้ใช้งานปัจจุบัน
$stmt_user = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$userName = trim(($user['prefix'] ?? '') . ' ' . ($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
$initial = mb_substr($user['firstName'] ?? 'ค', 0, 1, 'UTF-8');

// กำหนดข้อความ Role
if ($user['role'] === 'director') {
    $userRoleStr = 'ผู้อำนวยการ';
} else {
    $userRoleStr = ($user['role'] === 'teacher') ? 'ครูผู้สอน' : 'เจ้าหน้าที่';
}

// ดึงรายชื่อครูทั้งหมด (สำหรับให้เลือกเป็นผู้ร่วมเดินทาง) ยกเว้นตัวเอง
$stmt_teachers = dd_q("SELECT id, prefix, firstName, lastName FROM user WHERE id != ? ORDER BY firstName ASC", [$_SESSION['id']]);
$db_teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

// Helper Function: แปลงวันที่เป็นภาษาไทย
function thaiDate($dateString)
{
    if (empty($dateString)) return '-';
    $months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
    $time = strtotime($dateString);
    $d = date('j', $time);
    $m = $months[date('n', $time)];
    $y = date('Y', $time) + 543;
    return "$d $m $y";
}

// ------------------------------------------------------------------
// ส่วนที่ 1: การพิมพ์หนังสือราชการ (Export to PDF ด้วย mPDF)
// ------------------------------------------------------------------
if (isset($_GET['print_id'])) {
    $print_id = $_GET['print_id'];
    $stmt_print = dd_q("SELECT * FROM official_trips WHERE id = ? LIMIT 1", [$print_id]);
    $trip = $stmt_print->fetch(PDO::FETCH_ASSOC);

    if ($trip) {
        $trip_subject = $trip['subject'];
        $trip_location = $trip['location'];
        $trip_start = thaiDate($trip['start_date']);
        $trip_end = thaiDate($trip['end_date']);
        $trip_budget = $trip['budget_source'] ? $trip['budget_source'] : 'งบส่วนตัว/ทั่วไป';

        $travelers = [$trip['teacher_name']];
        $co_travelers_ids = json_decode($trip['co_travelers'] ?? '[]', true);

        if (is_array($co_travelers_ids) && count($co_travelers_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($co_travelers_ids), '?'));
            $stmt_co = dd_q("SELECT prefix, firstName, lastName FROM user WHERE id IN ($placeholders)", $co_travelers_ids);
            $co_users = $stmt_co->fetchAll(PDO::FETCH_ASSOC);
            foreach ($co_users as $cu) {
                $travelers[] = trim($cu['prefix'] . ' ' . $cu['firstName'] . ' ' . $cu['lastName']);
            }
        }
        $travelers_text = implode(', ', $travelers);

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'default_font' => 'sarabun',
            'default_font_size' => 11,
            'margin_left' => 30,
            'margin_right' => 20,
            'margin_top' => 20,
            'margin_bottom' => 20,
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $garuda_url = 'https://fortsurasi.rta.mi.th/srshos-e-form/doc_file/krut-3-cm.png?1786226953';
        $school_name = 'โรงเรียนบ้านหนองฮี';
        
        $html = '
        <div style="text-align: center; margin-bottom: 10px;">
            <img src="' . $garuda_url . '" width="95" />
        </div>
        <div style="text-align: center; font-size: 14px; margin-bottom: 10px;">
            คำสั่งโรงเรียน '. $school_name .'
        </div>
        <div style="text-align: center; font-size: 14px; margin-bottom: 10px;">
            ที่ ........ / ' . (date('Y') + 543) . '<br>
            เรื่อง ให้บุคลากรเดินทางไปราชการ
        </div>
        <hr style="border: 0; border-top: 0.5px dotted #000; margin-bottom: 10px;">
        <div style="text-align: justify; line-height: 1.5; text-indent: 2.5cm;">
            อาศัยอำนาจตามระเบียบกระทรวงศึกษาธิการ จึงมีคำสั่งให้ ' . ($travelers_text) . '
            เดินทางไปราชการเพื่อ ' . ($trip_subject) . '
            ณ ' . ($trip_location) . ' ระหว่างวันที่ ' . $trip_start . '
            ถึงวันที่ ' . $trip_end . ' โดยให้เบิกค่าใช้จ่ายในการเดินทางไปราชการจาก ' . ($trip_budget) . '
        </div>
        <div style="text-align: left; line-height: 1.5; text-indent: 2.5cm; margin-top: 30px;">
            ทั้งนี้ ตั้งแต่บัดนี้เป็นต้นไป
        </div>
        <div style="text-align: center; font-size: 14px; margin-top: 60px;">
            สั่ง ณ วันที่ ' . thaiDate(date('Y-m-d')) . '
        </div>
        <div style="text-align: center; font-size: 14px; margin-top: 60px; margin-left: 50%;">
            (ผู้อำนวยการ โรงเรียน)<br>
            ผู้อำนวยการโรงเรียน '. $school_name .'<br>
            สพป.ร้อยเอ็ด เขต ๓
        </div>
        ';

        $mpdf->WriteHTML($html);
        $mpdf->Output($trip_subject . '.pdf', \Mpdf\Output\Destination::INLINE);
        exit();
    }
}

// ------------------------------------------------------------------
// ส่วนที่ 2: จัดการ Submit Form สร้างคำขอ
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_trip') {
    $type = $_POST['type'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $objective = trim($_POST['objective'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $budget = floatval($_POST['budget'] ?? 0);
    $budget_source = trim($_POST['budget_source'] ?? '');
    $vehicle = trim($_POST['vehicle'] ?? '');

    $co_travelers = isset($_POST['co_travelers']) ? json_encode($_POST['co_travelers']) : '[]';

    try {
        $sql = "INSERT INTO official_trips 
                (teacher_id, teacher_name, type, subject, objective, location, start_date, end_date, budget, budget_source, vehicle, co_travelers, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

        dd_q($sql, [
            $_SESSION['id'],
            $userName,
            $type,
            $subject,
            $objective,
            $location,
            $start_date,
            $end_date,
            $budget,
            $budget_source,
            $vehicle,
            $co_travelers
        ]);

        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=success");
        exit();
    } catch (Exception $e) {
        $msg = "error";
    }
}

// ------------------------------------------------------------------
// ส่วนที่ 3: จัดการ อนุมัติ / ไม่อนุมัติ คำขอ (สำหรับ Director)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decision'])) {
    $trip_id_to_update = $_POST['trip_id'] ?? '';
    $decision = $_POST['decision'] ?? '';
    $note = trim($_POST['note'] ?? '');
    
    $new_status = ($decision === 'approve') ? 'approved' : 'rejected';
    
    if(!empty($trip_id_to_update)){
        // อัปเดตสถานะ (หากไม่มีคอลัมน์ note ใน DB สามารถลบ `, note = ?` และ `$note` ออกได้)
        try {
            dd_q("UPDATE official_trips SET status = ?, note = ? WHERE id = ?", [$new_status, $note, $trip_id_to_update]);
        } catch (Exception $e) {
            // หาก Error คอลัมน์ note ให้ลองอัปเดตแบบไม่มี note
            dd_q("UPDATE official_trips SET status = ? WHERE id = ?", [$new_status, $trip_id_to_update]);
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=updated");
        exit();
    }
}

// ------------------------------------------------------------------
// ส่วนที่ 4: ดึงข้อมูลเพื่อแสดงผล
// ------------------------------------------------------------------
$msg = $_GET['msg'] ?? null;

// ผู้อำนวยการเห็นทุกรายการ, ครูเห็นเฉพาะของตัวเอง
if ($user['role'] === 'director') {
    $stmt_trips = dd_q("SELECT * FROM official_trips ORDER BY id DESC");
} else {
    $stmt_trips = dd_q("SELECT * FROM official_trips WHERE teacher_id = ? ORDER BY id DESC", [$_SESSION['id']]);
}

$my_trips = $stmt_trips->fetchAll(PDO::FETCH_ASSOC);

$stat_total = count($my_trips);
$stat_pending = 0;
$stat_approved = 0;
$stat_budget = 0;

foreach ($my_trips as $t) {
    if ($t['status'] === 'pending') $stat_pending++;
    if ($t['status'] === 'approved') {
        $stat_approved++;
        $stat_budget += $t['budget'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ไปราชการและอบรม - SiS4 SCHOOL</title>
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

    <?php sis4_teacher_sidebar_render($userName, $initial, $userRoleStr, '../../system/logout.php'); ?>

    <div class="flex-1 flex flex-col min-w-0">
        
        <!-- Header -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 z-30 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded flex items-center justify-center">
                    <i class="fa-solid fa-plane"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold text-gray-900 leading-tight">ไปราชการและอบรม</h1>
                    <p class="text-[11px] text-gray-500">ระบบงานบุคคล</p>
                </div>
            </div>
            <div class="flex items-center gap-4 ml-auto">
                <button class="relative text-gray-500 hover:text-gray-900 transition-colors">
                    <i class="fa-solid fa-bell text-lg"></i>
                    <?php if($stat_pending > 0): ?>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full border border-white"><?= $stat_pending ?></span>
                    <?php endif; ?>
                </button>
                <div class="w-px h-6 bg-gray-300"></div>
                <div class="text-sm font-medium text-gray-700 hidden sm:block">
                    <?= htmlspecialchars($userName) ?>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">

            <!-- Title & Alerts -->
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600">
                        <i class="fa-solid fa-plane-departure text-xl"></i>
                    </span>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 sm:text-2xl">ไปราชการและอบรมพัฒนาตนเอง</h1>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">สร้างคำขอ &rarr; หัวหน้ากลุ่มงานเห็นชอบ &rarr; ผู้อำนวยการอนุมัติ &rarr; เดินทาง</p>
                    </div>
                </div>
            </div>

            <?php if ($msg === 'success'): ?>
                <div class="p-4 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl text-sm font-medium flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i> สร้างคำขอไปราชการและบันทึกสู่ฐานข้อมูลเรียบร้อยแล้ว
                </div>
            <?php elseif ($msg === 'updated'): ?>
                <div class="p-4 bg-blue-50 text-blue-800 border border-blue-200 rounded-xl text-sm font-medium flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check text-blue-600"></i> อัปเดตสถานะคำขอเรียบร้อยแล้ว
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-bold text-gray-500 uppercase tracking-wider">คำขอทั้งหมด</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums"><?= $stat_total ?></p>
                        </div>
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600">
                            <i class="fa-solid fa-briefcase text-lg"></i>
                        </span>
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-bold text-gray-500 uppercase tracking-wider">รอพิจารณา</p>
                            <p class="mt-1 text-2xl font-bold text-amber-600 tabular-nums"><?= $stat_pending ?></p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-bold text-gray-500 uppercase tracking-wider">อนุมัติแล้ว</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-600 tabular-nums"><?= $stat_approved ?></p>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-bold text-gray-500 uppercase tracking-wider">งบประมาณที่อนุมัติ</p>
                            <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums"><?= number_format($stat_budget) ?> <span class="text-sm font-normal text-gray-500">บาท</span></p>
                        </div>
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-600">
                            <i class="fa-solid fa-wallet text-lg"></i>
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                
                <!-- รายการคำขอ -->
                <div class="space-y-4">
                    <?php if (count($my_trips) === 0): ?>
                        <div class="bg-white border border-gray-200 rounded-2xl p-12 text-center text-gray-500 shadow-sm">
                            <i class="fa-solid fa-folder-open text-4xl text-gray-300 mb-3 block"></i>
                            <p class="font-bold text-gray-800 text-lg">ยังไม่มีข้อมูลคำขอ</p>
                            <p class="text-sm mt-1">ประวัติการยื่นคำขอไปราชการจะแสดงที่นี่</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($my_trips as $trip):
                        $trip_days = (strtotime($trip['end_date']) - strtotime($trip['start_date'])) / (60 * 60 * 24) + 1;
                        $isApproved = $trip['status'] === 'approved';
                        $isRejected = $trip['status'] === 'rejected';
                    ?>
                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6 relative">
                            <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-100">
                                            <i class="fa-solid <?= $trip['type'] === 'ไปราชการ' ? 'fa-briefcase' : 'fa-graduation-cap' ?>"></i> <?= htmlspecialchars($trip['type']) ?>
                                        </span>
                                        <?php if ($isApproved): ?>
                                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-100">อนุมัติแล้ว</span>
                                        <?php elseif ($isRejected): ?>
                                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-100">ไม่อนุมัติ</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-100">รอผู้อำนวยการพิจารณา</span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="mt-2 text-lg font-bold text-gray-900"><?= htmlspecialchars($trip['subject']) ?></h3>
                                    
                                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-gray-600">
                                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-map-pin text-gray-400"></i> <?= htmlspecialchars($trip['location']) ?></span>
                                        <span class="flex items-center gap-1.5"><i class="fa-regular fa-calendar text-gray-400"></i> <?= thaiDate($trip['start_date']) ?> - <?= thaiDate($trip['end_date']) ?> (<?= $trip_days ?> วัน)</span>
                                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-coins text-gray-400"></i> งบประมาณ <?= number_format($trip['budget']) ?> บาท (<?= htmlspecialchars($trip['budget_source'] ?: '-') ?>)</span>
                                    </div>
                                    <p class="mt-3 text-sm text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-100 leading-relaxed"><?= nl2br(htmlspecialchars($trip['objective'])) ?></p>
                                </div>
                                
                                <div class="text-right shrink-0 flex flex-col items-end gap-2">
                                    <div class="flex items-center gap-2">
                                        <div class="text-right">
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">ผู้ขออนุญาต</p>
                                            <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($trip['teacher_name']) ?></p>
                                        </div>
                                        <div class="w-10 h-10 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center shadow-sm">
                                            <?= mb_substr($trip['teacher_name'], 0, 1, 'UTF-8') ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($isApproved): ?>
                                        <a href="?print_id=<?= $trip['id'] ?>" target="_blank" class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
                                            <i class="fa-solid fa-print"></i> พิมพ์คำสั่ง
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- ฟอร์มพิจารณาของผู้อำนวยการ -->
                            <?php if ($user['role'] === 'director' && $trip['status'] === 'pending'): ?>
                                <form action="" method="POST" class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                                    <input type="hidden" name="action" value="approve_trip">
                                    <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                                    <div class="min-w-48 flex-1">
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-bold text-gray-700 uppercase tracking-wider">ความเห็นผู้อำนวยการ</span>
                                            <input type="text" name="note" placeholder="ระบุความเห็นประกอบการพิจารณา (ถ้ามี)..." class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                        </label>
                                    </div>
                                    <button type="submit" name="decision" value="approve" class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow-sm">
                                        <i class="fa-solid fa-check"></i> อนุมัติ
                                    </button>
                                    <button type="submit" name="decision" value="reject" onclick="return confirm('ยืนยันไม่อนุมัติคำขอนี้?');" class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-bold bg-rose-600 text-white hover:bg-rose-700 transition-colors shadow-sm">
                                        <i class="fa-solid fa-xmark"></i> ไม่อนุมัติ
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- ความเห็นผู้อำนวยการ (แสดงเมื่อมีการพิจารณาแล้ว) -->
                            <?php if (!empty($trip['note'])): ?>
                                <div class="mt-4 pt-4 border-t border-gray-100">
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">ความเห็นผู้อำนวยการ</p>
                                    <p class="text-sm text-gray-800 italic">"<?= htmlspecialchars($trip['note']) ?>"</p>
                                </div>
                            <?php endif; ?>

                        </section>
                    <?php endforeach; ?>
                </div>

                <!-- สร้างคำขอใหม่ (แสดงเฉพาะตอนเป็นครู) -->
                <?php if ($user['role'] !== 'director'): ?>
                <div class="space-y-4">
                    <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6 sticky top-6">
                        <div class="mb-5 flex items-center justify-between gap-3 border-b border-gray-100 pb-4">
                            <h2 class="flex items-center gap-2 text-base font-bold text-gray-900">
                                <i class="fa-solid fa-pen-to-square text-blue-600"></i> สร้างคำขอใหม่
                            </h2>
                        </div>
                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="submit_trip">

                            <label class="block">
                                <span class="mb-1 block text-xs font-bold text-gray-700 uppercase tracking-wider">ประเภท <span class="text-rose-500">*</span></span>
                                <select name="type" required class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    <option value="ไปราชการ">ไปราชการ</option>
                                    <option value="ไปอบรม / สัมมนา">อบรม / สัมมนา</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-bold text-gray-700 uppercase tracking-wider">เรื่อง <span class="text-rose-500">*</span></span>
                                <input required placeholder="อบรมเชิงปฏิบัติการ..." name="subject" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-bold text-gray-700 uppercase tracking-wider">วัตถุประสงค์ </span>
                                <textarea name="objective" rows="2" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"></textarea>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-bold text-gray-700 uppercase tracking-wider">สถานที่ <span class="text-rose-500">*</span></span>
                                <input required name="location" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </label>

                            <div class="grid grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold text-gray-700 uppercase tracking-wider">ตั้งแต่วันที่ <span class="text-rose-500">*</span></span>
                                    <input required type="date" name="start_date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold text-gray-700 uppercase tracking-wider">ถึงวันที่ <span class="text-rose-500">*</span></span>
                                    <input required type="date" name="end_date" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                </label>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold text-gray-700 uppercase tracking-wider">งบประมาณ (บาท)</span>
                                    <input min="0" type="number" value="0" name="budget" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                </label>
                                <label class="block">
                                    <span class="mb-1 block text-xs font-bold text-gray-700 uppercase tracking-wider">แหล่งงบประมาณ</span>
                                    <input name="budget_source" placeholder="เช่น งบพัฒนาบุคลากร" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                </label>
                            </div>

                            <label class="block">
                                <span class="mb-1 block text-xs font-bold text-gray-700 uppercase tracking-wider">พาหนะ</span>
                                <input placeholder="รถยนต์ส่วนตัว / รถตู้โรงเรียน" name="vehicle" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-bold text-gray-700 uppercase tracking-wider">ผู้ร่วมเดินทาง <span class="font-normal lowercase text-gray-400">(Ctrl เพื่อเลือกหลายคน)</span></span>
                                <select multiple name="co_travelers[]" size="4" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 h-auto custom-scrollbar">
                                    <?php foreach ($db_teachers as $t): ?>
                                        <option value="<?= $t['id'] ?>"><?= trim($t['prefix'] . ' ' . $t['firstName'] . ' ' . $t['lastName']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-bold bg-blue-600 text-white hover:bg-blue-700 shadow-sm transition-colors mt-2">
                                ส่งคำขอ
                            </button>
                        </form>
                    </section>
                </div>
                <?php endif; ?>

            </div>
            <div class="h-6"></div>
        </main>
    </div>
</body>
</html>