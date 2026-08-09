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
$userRoleStr = ($user['role'] === 'director') ? 'ผู้อำนวยการ' : 'ครูผู้สอน';

// ดึงรายชื่อครูทั้งหมด (สำหรับให้เลือกเป็นผู้ร่วมเดินทาง) ยกเว้นตัวเอง
$stmt_teachers = dd_q("SELECT id, prefix, firstName, lastName FROM user WHERE id != ? ORDER BY firstName ASC", [$_SESSION['id']]);
$db_teachers = $stmt_teachers->fetchAll(PDO::FETCH_ASSOC);

// Helper Function: แปลงวันที่เป็นภาษาไทย
function thaiDate($dateString)
{
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

        // --- ตั้งค่า mPDF ---
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
        $school_name = 'โรงเรียนบ้านหนองฮี'; // ปรับชื่อให้ตรงกับระบบ
        
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
            สพป.ขอนแก่น เขต ๑
        </div>
        ';

        $mpdf->WriteHTML($html);
        $mpdf->Output( $trip_subject  . $trip['id'] . '.pdf', \Mpdf\Output\Destination::INLINE);
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
    
    // บันทึกสถานะลง Database
    if(!empty($trip_id_to_update)){
        dd_q("UPDATE official_trips SET status = ?, note = ? WHERE id = ?", [$new_status, $note, $trip_id_to_update]);
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=updated");
        exit();
    }
}

// ------------------------------------------------------------------
// ส่วนที่ 4: ดึงข้อมูลเพื่อแสดงผล
// ------------------------------------------------------------------
$msg = $_GET['msg'] ?? $msg ?? null;

// ถ้าเป็น Director จะเห็นรายการทั้งหมด ถ้าเป็นครูจะเห็นเฉพาะของตัวเอง
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
    if ($t['status'] === 'pending')
        $stat_pending++;
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

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 z-30 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded flex items-center justify-center">
                    <i class="fa-solid fa-plane"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold text-gray-900 leading-tight">ไปราชการและอบรมพัฒนาตนเอง</h1>
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
            
            <?php if ($msg === 'success'): ?>
                <div class="p-4 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded text-sm font-medium flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i> สร้างคำขอไปราชการและบันทึกสู่ฐานข้อมูลเรียบร้อยแล้ว
                </div>
            <?php elseif ($msg === 'updated'): ?>
                <div class="p-4 bg-blue-50 text-blue-800 border border-blue-200 rounded text-sm font-medium flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check text-blue-600"></i> อัปเดตสถานะคำขอเรียบร้อยแล้ว
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded border border-gray-200 shadow-sm flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-1">คำขอทั้งหมด</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $stat_total ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded flex items-center justify-center text-lg"><i class="fa-solid fa-briefcase"></i></div>
                </div>
                <div class="bg-white p-5 rounded border border-gray-200 shadow-sm flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-1">รอพิจารณา</p>
                        <p class="text-3xl font-bold text-amber-600"><?= $stat_pending ?></p>
                    </div>
                    <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded flex items-center justify-center text-lg"><i class="fa-regular fa-clock"></i></div>
                </div>
                <div class="bg-white p-5 rounded border border-gray-200 shadow-sm flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-1">อนุมัติแล้ว</p>
                        <p class="text-3xl font-bold text-emerald-600"><?= $stat_approved ?></p>
                    </div>
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded flex items-center justify-center text-lg"><i class="fa-solid fa-check"></i></div>
                </div>
                <div class="bg-white p-5 rounded border border-gray-200 shadow-sm flex justify-between items-start">
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-1">งบประมาณที่อนุมัติ</p>
                        <p class="text-3xl font-bold text-gray-900"><?= number_format($stat_budget) ?> <span class="text-sm font-normal text-gray-500">บาท</span></p>
                    </div>
                    <div class="w-10 h-10 bg-teal-50 text-teal-600 rounded flex items-center justify-center text-lg"><i class="fa-solid fa-wallet"></i></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- รายการคำขอที่ดึงจาก DB -->
                <div class="lg:col-span-8 space-y-4">

                    <?php if (count($my_trips) === 0): ?>
                        <div class="bg-white border border-gray-200 rounded p-12 text-center text-gray-500 shadow-sm">
                            <i class="fa-solid fa-folder-open text-4xl text-gray-300 mb-3 block"></i>
                            ยังไม่มีประวัติการยื่นคำขอไปราชการ
                        </div>
                    <?php endif; ?>

                    <?php foreach ($my_trips as $trip):
                        $trip_days = (strtotime($trip['end_date']) - strtotime($trip['start_date'])) / (60 * 60 * 24) + 1;
                        ?>
                        <div class="bg-white border border-gray-200 rounded p-6 shadow-sm relative">
                            <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-100">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 <?= $trip['type'] === 'ไปราชการ' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-violet-50 text-violet-700 border border-violet-200' ?> rounded-lg text-[10px] font-bold uppercase tracking-wider">
                                        <i class="fa-solid <?= $trip['type'] === 'ไปราชการ' ? 'fa-briefcase' : 'fa-graduation-cap' ?> mr-1"></i>
                                        <?= htmlspecialchars($trip['type']) ?>
                                    </span>
                                    
                                    <?php if ($trip['status'] === 'approved'): ?>
                                        <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-[10px] font-bold uppercase tracking-wider">อนุมัติแล้ว</span>
                                    <?php elseif ($trip['status'] === 'rejected'): ?>
                                        <span class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg text-[10px] font-bold uppercase tracking-wider">ไม่อนุมัติ</span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg text-[10px] font-bold uppercase tracking-wider">รอพิจารณา</span>
                                    <?php endif; ?>
                                </div>

                                <!-- ปุ่มปริ้นท์ PDF ส่งไปให้ mPDF ประมวลผล (แสดงเฉพาะตอนอนุมัติแล้ว) -->
                                <?php if ($trip['status'] === 'approved'): ?>
                                <a href="?print_id=<?= $trip['id'] ?>" target="_blank"
                                    class="px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded shadow-sm text-xs font-medium transition flex items-center gap-1.5">
                                    <i class="fa-solid fa-print"></i> พิมพ์คำสั่ง
                                </a>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-gray-900 mb-2"><?= htmlspecialchars($trip['subject']) ?></h3>
                                    
                                    <div class="text-sm text-gray-600 space-y-1.5 mb-4">
                                        <p class="flex items-center gap-2"><i class="fa-solid fa-map-pin text-gray-400 w-4 text-center"></i> <?= htmlspecialchars($trip['location']) ?></p>
                                        <p class="flex items-center gap-2"><i class="fa-regular fa-calendar text-gray-400 w-4 text-center"></i> <?= thaiDate($trip['start_date']) ?> - <?= thaiDate($trip['end_date']) ?> (<?= $trip_days ?> วัน)</p>
                                        <p class="flex items-center gap-2"><i class="fa-solid fa-wallet text-gray-400 w-4 text-center"></i> งบประมาณ <?= number_format($trip['budget']) ?> บาท &middot; <?= htmlspecialchars($trip['budget_source'] ?: '-') ?> &middot; พาหนะ: <?= htmlspecialchars($trip['vehicle'] ?: '-') ?></p>
                                    </div>
                                    <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded border border-gray-100 mb-4"><?= nl2br(htmlspecialchars($trip['objective'])) ?></p>
                                </div>
                                <div class="ml-4 text-right">
                                    <div class="w-10 h-10 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center mx-auto mb-1 shadow-sm">
                                        <?= mb_substr($trip['teacher_name'], 0, 1, 'UTF-8') ?>
                                    </div>
                                    <p class="text-xs text-gray-500">ผู้ขออนุญาต</p>
                                    <p class="text-xs font-bold text-gray-800"><?= htmlspecialchars($trip['teacher_name']) ?></p>
                                </div>
                            </div>
                            
                            <!-- ส่วนของ Director: อนุมัติ / ไม่อนุมัติ -->
                            <?php if ($user['role'] === 'director' && $trip['status'] === 'pending'): ?>
                                <form action="" method="POST" class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-end gap-3 bg-blue-50/50 p-4 rounded-xl">
                                    <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                                    <div class="flex-1 min-w-[200px]">
                                        <label class="block text-xs font-bold text-gray-700 mb-1">ความเห็นผู้อำนวยการ</label>
                                        <input type="text" name="note" placeholder="ระบุความเห็นเพิ่มเติม (ถ้ามี)" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" name="decision" value="approve" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded text-sm font-bold shadow-sm transition-colors">
                                            อนุมัติ
                                        </button>
                                        <button type="submit" name="decision" value="reject" class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded text-sm font-bold shadow-sm transition-colors" onclick="return confirm('ยืนยันไม่อนุมัติคำขอนี้ใช่หรือไม่?');">
                                            ไม่อนุมัติ
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <!-- แสดงความเห็น ผอ. ถ้ามี -->
                            <?php if (!empty($trip['note'])): ?>
                                <div class="mt-4 pt-4 border-t border-gray-100">
                                    <p class="text-xs font-bold text-gray-500 mb-1 uppercase tracking-wider">บันทึกข้อความจากผู้อำนวยการ</p>
                                    <p class="text-sm text-gray-800 italic">"<?= htmlspecialchars($trip['note']) ?>"</p>
                                </div>
                            <?php endif; ?>

                            <div class="text-[10px] text-gray-400 font-medium pt-3 mt-3 border-t border-gray-100 flex items-center justify-between">
                                <span>สร้างคำขอเมื่อ <?= thaiDate($trip['created_at']) ?></span>
                                <span>ID: <?= $trip['id'] ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>

                <!-- ฟอร์มสร้างคำขอใหม่ -->
                <div class="lg:col-span-4">
                    <div class="bg-white border border-gray-200 rounded p-6 shadow-sm sticky top-6">
                        <div class="flex items-center gap-2 pb-4 border-b border-gray-100 mb-5">
                            <i class="fa-solid fa-pen-to-square text-blue-600"></i>
                            <h3 class="font-bold text-gray-900 text-base">สร้างคำขอใหม่</h3>
                        </div>

                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="submit_trip">

                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">ประเภท</label>
                                <select name="type" class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                    <option value="ไปราชการ">ไปราชการ</option>
                                    <option value="ไปอบรม / สัมมนา">ไปอบรม / สัมมนา</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">เรื่อง <span class="text-rose-500">*</span></label>
                                <input type="text" name="subject" required placeholder="อบรมเชิงปฏิบัติการ..."
                                    class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">วัตถุประสงค์</label>
                                <textarea name="objective" rows="2"
                                    class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none resize-y"></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">สถานที่ <span class="text-rose-500">*</span></label>
                                <input type="text" name="location" required
                                    class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">ตั้งแต่วันที่ <span class="text-rose-500">*</span></label>
                                    <input type="date" name="start_date" required
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">ถึงวันที่ <span class="text-rose-500">*</span></label>
                                    <input type="date" name="end_date" required
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">งบประมาณ (บาท)</label>
                                    <input type="number" name="budget" value="0" min="0"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">แหล่งงบประมาณ</label>
                                    <input type="text" name="budget_source" placeholder="เช่น งบพัฒนาบุคลากร"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">พาหนะ</label>
                                <input type="text" name="vehicle" placeholder="รถยนต์ส่วนตัว / รถตู้โรงเรียน"
                                    class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1 uppercase tracking-wider">ผู้ร่วมเดินทาง <span class="text-gray-400 font-normal lowercase">(Ctrl เพื่อเลือกหลายคน)</span></label>
                                <select name="co_travelers[]" multiple
                                    class="w-full px-3 py-2 bg-white border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none h-28 custom-scrollbar">
                                    <?php foreach ($db_teachers as $t): ?>
                                        <option value="<?= $t['id'] ?>" class="py-1">
                                            <?= trim($t['prefix'] . ' ' . $t['firstName'] . ' ' . $t['lastName']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit"
                                class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm rounded shadow-sm transition-colors mt-4">
                                ยื่นคำขอไปราชการ
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>