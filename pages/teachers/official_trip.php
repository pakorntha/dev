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
            'mode' => 'utf-8', // บังคับให้เป็น UTF-8
            'default_font' => 'sarabun',
            'default_font_size' => 11,
            'margin_left' => 30,
            'margin_right' => 20,
            'margin_top' => 20,
            'margin_bottom' => 20,
        ]);

        // เพิ่ม 2 บรรทัดนี้! จำเป็นมากสำหรับการอ่านภาษาไทย
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        // แก้ปัญหารูปครุฑโหลดไม่ขึ้น (แนะนำให้ใช้ URL นี้ หรือดาวน์โหลดมาไว้ในเครื่อง)
        // URL นี้เป็นไฟล์ JPG ซึ่ง mPDF จะโหลดได้เสถียรกว่า SVG/PNG บางประเภท
        $garuda_url = 'https://fortsurasi.rta.mi.th/srshos-e-form/doc_file/krut-3-cm.png?1786226953';
        $school_name = 'โรงเรียนบ้านหนองฮี';
        // สร้าง HTML สำหรับ PDF
        $html = '
        <div style="text-align: center; margin-bottom: 10px;">
            <img src="' . $garuda_url . '" width="95" />
        </div>
        <div style="text-align: center; font-size: 14px; margin-bottom: 10px;">
            คำสั่งโรงเรียน '. $school_name .'
        </div>
        <div style="text-align: center; font-size: 14px; margin-bottom: 10px;">
            ที่ ........ / ' . (date('Y') + 543) . '<br>
            เรื่อง ให้ข้าราชการเดินทางไปราชการ
        </div>
        <hr style="border: 0; border-top: 0.5px dotted #000; margin-bottom: 10px;">
        <div style="text-align: justify; line-height: 1.5; text-indent: 2.5cm;">
            อาศัยอำนาจตามระเบียบกระทรวงศึกษาธิการ จึงมีคำสั่งให้ ' . ($travelers_text) . '
            ตำแหน่ง ข้าราชการครู เดินทางไปราชการเพื่อ ' . ($trip_subject) . '
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
            (นายผู้อำนวยการ โรงเรียน)<br>
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
    // โค้ดส่วนนี้เหมือนเดิม
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
// ส่วนที่ 3: ดึงข้อมูลเพื่อแสดงผล
// ------------------------------------------------------------------
$msg = $_GET['msg'] ?? $msg ?? null;

$stmt_trips = dd_q("SELECT * FROM official_trips WHERE teacher_id = ? ORDER BY id DESC", [$_SESSION['id']]);
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
    <title>ไปราชการและอบรม - AI School e-Office</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }

        /* ------------------------------------------------------
           เพิ่ม CSS ซ่อน Scrollbar ของทั้งหน้า (รวมถึง Navbar/Sidebar) 
           แต่ยังสามารถใช้เมาส์ไถ Scroll (Mouse Wheel) ได้ปกติ
           ------------------------------------------------------ */
        ::-webkit-scrollbar {
            width: 0px;
            height: 0px;
            background: transparent;
        }

        * {
            scrollbar-width: none;
            /* สำหรับ Firefox */
            -ms-overflow-style: none;
            /* สำหรับ IE และ Edge */
        }
    </style>
</head>

<body class="bg-[#f8fafc] text-slate-800 h-screen overflow-hidden flex">

    <?php sis4_teacher_sidebar_render($userName, $initial); ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8">
            <div></div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 pl-4 border-l border-slate-200">
                    <div
                        class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-semibold text-xs">
                        <?= $initial ?></div>
                    <div class="text-xs">
                        <p class="font-bold text-slate-800"><?= htmlspecialchars($userName) ?></p>
                        <p class="text-slate-400">ครู</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xl">
                    <i class="fa-solid fa-plane-departure"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-800">ไปราชการและอบรมพัฒนาตนเอง</h2>
                    <p class="text-xs text-slate-400 mt-1">สร้างคำขอ &rarr; หัวหน้ากลุ่มงานเห็นชอบ &rarr;
                        ผู้อำนวยการอนุมัติ &rarr; เดินทาง</p>
                </div>
            </div>

            <?php if ($msg === 'success'): ?>
                <div
                    class="p-3 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-sm flex items-center gap-2">
                    <i class="fa-solid fa-circle-check"></i> สร้างคำขอไปราชการและบันทึกสู่ฐานข้อมูลเรียบร้อยแล้ว
                </div>
            <?php endif; ?>

            <!-- สถิติ -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div
                    class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-xs text-slate-500 mb-1">คำขอทั้งหมด</p>
                        <p class="text-3xl font-bold text-slate-800"><?= $stat_total ?></p>
                    </div>
                    <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center"><i
                            class="fa-solid fa-suitcase"></i></div>
                </div>
                <div
                    class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-xs text-slate-500 mb-1">รอพิจารณา</p>
                        <p class="text-3xl font-bold text-slate-800"><?= $stat_pending ?></p>
                    </div>
                </div>
                <div
                    class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-xs text-slate-500 mb-1">อนุมัติแล้ว</p>
                        <p class="text-3xl font-bold text-slate-800"><?= $stat_approved ?></p>
                    </div>
                </div>
                <div
                    class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-xs text-slate-500 mb-1">งบประมาณที่อนุมัติ</p>
                        <p class="text-3xl font-bold text-slate-800"><?= number_format($stat_budget) ?> <span
                                class="text-sm font-normal">บาท</span></p>
                    </div>
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center"><i
                            class="fa-solid fa-wallet"></i></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                <!-- รายการคำขอที่ดึงจาก DB -->
                <div class="lg:col-span-8 space-y-4">

                    <?php if (count($my_trips) === 0): ?>
                        <div class="bg-white border border-slate-200 rounded-2xl p-10 text-center text-slate-500">
                            ยังไม่มีประวัติการยื่นคำขอไปราชการ
                        </div>
                    <?php endif; ?>

                    <?php foreach ($my_trips as $trip):
                        $trip_days = (strtotime($trip['end_date']) - strtotime($trip['start_date'])) / (60 * 60 * 24) + 1;
                        ?>
                        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm relative">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="px-2.5 py-1 <?= $trip['type'] === 'ไปราชการ' ? 'bg-indigo-50 text-indigo-700' : 'bg-purple-50 text-purple-700' ?> rounded text-xs font-semibold">
                                        <i
                                            class="fa-solid <?= $trip['type'] === 'ไปราชการ' ? 'fa-plane-up' : 'fa-chalkboard-user' ?> mr-1"></i>
                                        <?= htmlspecialchars($trip['type']) ?>
                                    </span>
                                    <?php if ($trip['status'] === 'approved'): ?>
                                        <span
                                            class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-xs font-semibold">อนุมัติแล้ว</span>
                                    <?php else: ?>
                                        <span
                                            class="px-2.5 py-1 bg-amber-50 text-amber-700 rounded text-xs font-semibold">รออนุมัติ</span>
                                    <?php endif; ?>
                                </div>

                                <!-- ปุ่มปริ้นท์ PDF ส่งไปให้ mPDF ประมวลผล -->
                                <a href="?print_id=<?= $trip['id'] ?>" target="_blank"
                                    class="px-3 py-1.5 bg-slate-100 hover:bg-rose-600 hover:text-white text-slate-700 rounded text-xs transition flex items-center gap-1.5">
                                    <i class="fa-solid fa-file-pdf"></i> ดาวน์โหลด PDF
                                </a>
                            </div>

                            <h3 class="text-base font-bold text-slate-800 mb-2"><?= htmlspecialchars($trip['subject']) ?>
                            </h3>

                            <div class="text-xs text-slate-600 space-y-1.5 mb-4">
                                <p><i class="fa-solid fa-location-dot w-4 text-slate-400"></i>
                                    <?= htmlspecialchars($trip['location']) ?></p>
                                <p><i class="fa-regular fa-calendar w-4 text-slate-400"></i>
                                    <?= thaiDate($trip['start_date']) ?> - <?= thaiDate($trip['end_date']) ?>
                                    (<?= $trip_days ?> วัน)</p>
                                <p><i class="fa-solid fa-coins w-4 text-slate-400"></i> งบประมาณ
                                    <?= number_format($trip['budget']) ?> บาท &middot;
                                    <?= htmlspecialchars($trip['budget_source'] ?: '-') ?> &middot; พาหนะ:
                                    <?= htmlspecialchars($trip['vehicle'] ?: '-') ?></p>
                            </div>

                            <div class="text-[11px] text-slate-400 pt-3 border-t border-slate-100">
                                • สร้างคำขอ โดย <?= htmlspecialchars($trip['teacher_name']) ?> -
                                <?= thaiDate($trip['created_at']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>

                <!-- ฟอร์มสร้างคำขอใหม่ -->
                <div class="lg:col-span-4">
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm sticky top-6">
                        <div class="flex items-center gap-2 pb-4 border-b border-slate-100 mb-4">
                            <i class="fa-solid fa-pen-to-square text-indigo-600"></i>
                            <h3 class="font-bold text-slate-800 text-sm">สร้างคำขอใหม่</h3>
                        </div>

                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="submit_trip">

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">ประเภท</label>
                                <select name="type"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                                    <option value="ไปราชการ">ไปราชการ</option>
                                    <option value="ไปอบรม / สัมมนา">ไปอบรม / สัมมนา</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">เรื่อง <span
                                        class="text-rose-500">*</span></label>
                                <input type="text" name="subject" required placeholder="อบรมเชิงปฏิบัติการ..."
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">วัตถุประสงค์</label>
                                <textarea name="objective" rows="2"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none resize-none"></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">สถานที่ <span
                                        class="text-rose-500">*</span></label>
                                <input type="text" name="location" required
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">ตั้งแต่วันที่ <span
                                            class="text-rose-500">*</span></label>
                                    <input type="date" name="start_date" required
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">ถึงวันที่ <span
                                            class="text-rose-500">*</span></label>
                                    <input type="date" name="end_date" required
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">งบประมาณ
                                        (บาท)</label>
                                    <input type="number" name="budget" value="0"
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">แหล่งงบประมาณ</label>
                                    <input type="text" name="budget_source" placeholder="เช่น งบพัฒนาบุคลากร"
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">พาหนะ</label>
                                <input type="text" name="vehicle" placeholder="รถยนต์ส่วนตัว / รถตู้โรงเรียน"
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">ผู้ร่วมเดินทาง (กด Ctrl
                                    ค้างเพื่อเลือกหลายคน)</label>
                                <select name="co_travelers[]" multiple
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-indigo-500 outline-none h-24">
                                    <?php foreach ($db_teachers as $t): ?>
                                        <option value="<?= $t['id'] ?>">
                                            <?= trim($t['prefix'] . ' ' . $t['firstName'] . ' ' . $t['lastName']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit"
                                class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs rounded-lg shadow-sm transition-colors mt-2">
                                บันทึกคำขอ
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>

</html>