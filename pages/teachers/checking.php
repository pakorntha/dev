<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("../../system/a_func.php");
require_once("../../system/teacher_sidebar.php");

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

$stmt_user = dd_q("SELECT * FROM user WHERE id = ? AND role IN ('teacher', 'admin', 'officer') LIMIT 1", [$_SESSION['id']]);
if ($stmt_user->rowCount() === 0) {
    header("Location: ../login.php");
    exit();
}

$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['prefix'] ?? '') . ' ' . ($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
$initial = mb_substr($user['firstName'] ?? 'U', 0, 1, 'UTF-8');
$userRoleStr = ($user['role'] === 'teacher') ? 'ครูผู้สอน' : 'บุคลากร';
$workStartTime = '08:30:00';
$workEndTime = '16:30:00';

$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$message = $_GET['msg'] ?? null;

dd_q("CREATE TABLE IF NOT EXISTS teacher_worklog (
    id varchar(191) NOT NULL,
    userId int NOT NULL,
    workDate date NOT NULL,
    checkInTime time DEFAULT NULL,
    checkOutTime time DEFAULT NULL,
    checkInMethod varchar(30) DEFAULT NULL,
    status enum('present','late','leave','sick','absent','remote','unknown') NOT NULL DEFAULT 'unknown',
    note text,
    markedBy int DEFAULT NULL,
    createdAt datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updatedAt datetime(3) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY TeacherWorklog_userId_workDate_key (userId, workDate),
    KEY TeacherWorklog_workDate_key (workDate),
    KEY TeacherWorklog_markedBy_fkey (markedBy),
    KEY TeacherWorklog_userId_fkey (userId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

$checkMethodColumn = dd_q("SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_worklog' AND COLUMN_NAME = 'checkInMethod'");
if ((int) $checkMethodColumn->fetch(PDO::FETCH_ASSOC)['total'] === 0) {
    dd_q("ALTER TABLE teacher_worklog ADD COLUMN checkInMethod varchar(30) DEFAULT NULL AFTER checkOutTime");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $method = $_POST['check_method'] ?? 'web';
    $method = in_array($method, ['web', 'gps', 'qr', 'app'], true) ? $method : 'web';

    $worklogDate = $_POST['work_date'] ?? $selectedDate;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $worklogDate)) {
        $worklogDate = date('Y-m-d');
    }

    $chk_stmt = dd_q("SELECT id, checkInTime FROM teacher_worklog WHERE userId = ? AND workDate = ? LIMIT 1", [$_SESSION['id'], $worklogDate]);
    $existing = $chk_stmt->fetch(PDO::FETCH_ASSOC);

    if ($_POST['action'] === 'check_in') {
        $currentTime = date('H:i:s');
        $status = ($currentTime > $workStartTime) ? 'late' : 'present';

        if ($existing) {
            dd_q(
                "UPDATE teacher_worklog SET checkInTime = COALESCE(checkInTime, CURTIME(3)), checkInMethod = COALESCE(checkInMethod, ?), status = CASE WHEN status = 'unknown' THEN ? ELSE status END, markedBy = ?, updatedAt = NOW(3) WHERE id = ?",
                [$method, $status, $_SESSION['id'], $existing['id']]
            );
        } else {
            dd_q(
                "INSERT INTO teacher_worklog (id, userId, workDate, checkInTime, checkInMethod, status, markedBy, updatedAt)
                 VALUES (?, ?, ?, CURTIME(3), ?, ?, ?, NOW(3))",
                ['TWL_' . uniqid(), $_SESSION['id'], $worklogDate, $method, $status, $_SESSION['id']]
            );
        }

        header("Location: checking.php?date=" . urlencode($worklogDate) . "&month=" . urlencode(substr($worklogDate, 0, 7)) . "&msg=checked_in");
        exit();
    }

    if ($_POST['action'] === 'check_out' && $existing) {
        dd_q("UPDATE teacher_worklog SET checkOutTime = COALESCE(checkOutTime, CURTIME(3)), markedBy = ?, updatedAt = NOW(3) WHERE id = ?", [$_SESSION['id'], $existing['id']]);
        header("Location: checking.php?date=" . urlencode($worklogDate) . "&month=" . urlencode(substr($worklogDate, 0, 7)) . "&msg=checked_out");
        exit();
    }
}

$staffStmt = dd_q(
    "SELECT id, username, prefix, firstName, lastName, role
     FROM user
     WHERE role IN ('teacher', 'admin', 'officer')
     ORDER BY CASE role WHEN 'teacher' THEN 1 WHEN 'officer' THEN 2 ELSE 3 END, firstName ASC, lastName ASC"
);
$staff = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

$staffIds = array_map(static fn ($row) => (int) $row['id'], $staff);
$dailyByUserId = [];
$monthlyRows = [];

if (!empty($staffIds)) {
    $placeholders = implode(',', array_fill(0, count($staffIds), '?'));

    $dailyStmt = dd_q(
        "SELECT u.id AS userId, u.prefix, u.firstName, u.lastName, u.role,
                tw.id AS worklogId, tw.workDate, tw.checkInTime, tw.checkOutTime, tw.checkInMethod, tw.status, tw.note, tw.markedBy, tw.createdAt, tw.updatedAt
         FROM user u
         LEFT JOIN teacher_worklog tw ON tw.userId = u.id AND tw.workDate = ?
         WHERE u.id IN ($placeholders)
         ORDER BY CASE u.role WHEN 'teacher' THEN 1 WHEN 'officer' THEN 2 ELSE 3 END, u.firstName ASC, u.lastName ASC",
        array_merge([$selectedDate], $staffIds)
    );

    foreach ($dailyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dailyByUserId[(int) $row['userId']] = $row;
    }

    $monthlyStmt = dd_q(
        "SELECT
            u.id AS userId,
            u.prefix,
            u.firstName,
            u.lastName,
            u.role,
            COUNT(tw.id) AS totalDays,
            SUM(CASE WHEN tw.status = 'present' THEN 1 ELSE 0 END) AS presentDays,
            SUM(CASE WHEN tw.status = 'late' THEN 1 ELSE 0 END) AS lateDays,
            SUM(CASE WHEN tw.status = 'leave' THEN 1 ELSE 0 END) AS leaveDays,
            SUM(CASE WHEN tw.status = 'sick' THEN 1 ELSE 0 END) AS sickDays,
            SUM(CASE WHEN tw.status = 'absent' THEN 1 ELSE 0 END) AS absentDays,
            SUM(CASE WHEN tw.status = 'remote' THEN 1 ELSE 0 END) AS remoteDays,
            SUM(CASE WHEN tw.checkOutTime IS NULL AND tw.checkInTime IS NOT NULL THEN 1 ELSE 0 END) AS openShiftDays
         FROM user u
         LEFT JOIN teacher_worklog tw ON tw.userId = u.id AND tw.workDate BETWEEN ? AND ?
         WHERE u.id IN ($placeholders)
         GROUP BY u.id, u.prefix, u.firstName, u.lastName, u.role
         ORDER BY CASE u.role WHEN 'teacher' THEN 1 WHEN 'officer' THEN 2 ELSE 3 END, u.firstName ASC, u.lastName ASC",
        array_merge([$monthStart, $monthEnd], $staffIds)
    );

    $monthlyRows = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
}

$summaryToday = [
    'total' => count($staff),
    'present' => 0,
    'late' => 0,
    'leave' => 0,
    'sick' => 0,
    'absent' => 0,
    'remote' => 0,
    'unknown' => 0,
    'checkedOut' => 0,
    'notCheckedOut' => 0,
];

foreach ($staff as $member) {
    $userId = (int) $member['id'];
    $log = $dailyByUserId[$userId] ?? null;

    if (!$log || empty($log['checkInTime'])) {
        $summaryToday['unknown']++;
        continue;
    }

    $status = $log['status'] ?? 'unknown';
    if (!array_key_exists($status, $summaryToday)) {
        $status = 'unknown';
    }

    $summaryToday[$status]++;

    if (!empty($log['checkOutTime'])) {
        $summaryToday['checkedOut']++;
    } else {
        $summaryToday['notCheckedOut']++;
    }
}

function workStatusMeta(string $status): array
{
    return match ($status) {
        'present' => ['label' => 'มาแล้ว', 'class' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
        'late' => ['label' => 'มาสาย', 'class' => 'bg-amber-100 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500'],
        'leave' => ['label' => 'ลากิจ', 'class' => 'bg-violet-100 text-violet-700 border-violet-200', 'dot' => 'bg-violet-500'],
        'sick' => ['label' => 'ลาป่วย', 'class' => 'bg-sky-100 text-sky-700 border-sky-200', 'dot' => 'bg-sky-500'],
        'absent' => ['label' => 'ไม่มา', 'class' => 'bg-rose-100 text-rose-700 border-rose-200', 'dot' => 'bg-rose-500'],
        'remote' => ['label' => 'ปฏิบัติงานนอกสถานที่', 'class' => 'bg-indigo-100 text-indigo-700 border-indigo-200', 'dot' => 'bg-indigo-500'],
        default => ['label' => 'ยังไม่ลงเวลา', 'class' => 'bg-slate-100 text-slate-600 border-slate-200', 'dot' => 'bg-slate-400'],
    };
}

function monthLabelThai(string $month): string
{
    [$year, $monthNumber] = explode('-', $month);
    $months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    return ($months[(int) $monthNumber] ?? $monthNumber) . ' ' . ((int) $year + 543);
}

$myLog = $dailyByUserId[(int) $_SESSION['id']] ?? null;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงเวลาปฏิบัติงาน - SiS4 SCHOOL</title>
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
    <?php sis4_teacher_sidebar_render($fullName, $initial, $userRoleStr, '../../system/logout.php'); ?>

    <div class="flex-1 flex flex-col min-w-0">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <div class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-user-clock text-blue-600"></i> ระบบลงเวลาปฏิบัติงาน
            </div>
            <div class="text-sm font-medium text-gray-700">
                <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            <?php if ($message === 'checked_in'): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded flex items-center gap-3">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i>
                    <p class="text-sm font-medium">บันทึกเวลาเข้าปฏิบัติงานเรียบร้อยแล้ว</p>
                </div>
            <?php elseif ($message === 'checked_out'): ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded flex items-center gap-3">
                    <i class="fa-solid fa-circle-check text-blue-600"></i>
                    <p class="text-sm font-medium">บันทึกเวลาออกปฏิบัติงานเรียบร้อยแล้ว</p>
                </div>
            <?php endif; ?>

            <div class="flex flex-wrap items-center justify-between gap-4 mb-2">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-2xl border border-indigo-100 shadow-sm">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">ลงเวลาปฏิบัติงานของครูและบุคลากร</h1>
                        <p class="text-sm text-gray-500 mt-1"><?= date('d') ?> <?= monthLabelThai(date('Y-m')) ?> · เวลาปฏิบัติงาน <?= substr($workStartTime, 0, 5) ?> - <?= substr($workEndTime, 0, 5) ?> น.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-[1fr_2fr] gap-6 mb-6">
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 flex flex-col">
                    <div class="flex justify-between items-center mb-5">
                        <h3 class="font-bold text-gray-900 flex items-center gap-2">
                            <i class="fa-solid fa-right-to-bracket text-indigo-600"></i> ลงเวลาปฏิบัติงานวันนี้
                        </h3>
                        <span class="text-[11px] text-gray-400">เวลาปฏิบัติงาน <?= substr($workStartTime, 0, 5) ?> - <?= substr($workEndTime, 0, 5) ?> น.</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-5">
                        <div class="border border-gray-200 rounded-xl p-4">
                            <p class="text-xs text-gray-500 mb-1">เวลามา</p>
                            <p class="text-2xl font-bold text-gray-900"><?= !empty($myLog['checkInTime']) ? date('H:i', strtotime($myLog['checkInTime'])) : '--:--' ?></p>
                        </div>
                        <div class="border border-gray-200 rounded-xl p-4">
                            <p class="text-xs text-gray-500 mb-1">เวลากลับ</p>
                            <?php if (!empty($myLog['checkOutTime'])): ?>
                                <p class="text-2xl font-bold text-gray-900"><?= date('H:i', strtotime($myLog['checkOutTime'])) ?></p>
                            <?php else: ?>
                                <p class="text-2xl font-bold text-gray-900 mb-1">--:--</p>
                                <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-[10px] font-medium border border-gray-200">ยังไม่ลงเวลากลับ</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="text-xs font-semibold text-gray-500 mb-2">เลือกวิธีลงเวลา</p>
                    <form method="POST" action="" class="mt-auto">
                        <input type="hidden" name="work_date" value="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                            <?php
                                $methods = [
                                    'gps' => ['label' => 'พิกัด GPS', 'icon' => 'location-dot'],
                                    'qr' => ['label' => 'QR Code', 'icon' => 'qrcode'],
                                    'app' => ['label' => 'แอปมือถือ', 'icon' => 'mobile-screen-button'],
                                    'web' => ['label' => 'เว็บบราวเซอร์', 'icon' => 'desktop'],
                                ];
                            ?>
                            <?php foreach ($methods as $methodKey => $methodInfo): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="check_method" value="<?= htmlspecialchars($methodKey, ENT_QUOTES, 'UTF-8') ?>" class="sr-only" <?= $methodKey === 'gps' ? 'checked' : '' ?>>
                                    <div class="method-card border border-gray-200 text-gray-500 hover:bg-gray-50 rounded-lg p-2 flex flex-col items-center justify-center gap-1.5 h-full transition-colors">
                                        <i class="fa-solid fa-<?= htmlspecialchars($methodInfo['icon'], ENT_QUOTES, 'UTF-8') ?> text-lg"></i>
                                        <span class="text-[10px] font-medium text-center leading-tight"><?= htmlspecialchars($methodInfo['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <button type="submit" name="action" value="check_in" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-2.5 text-sm font-medium transition-colors shadow-sm flex items-center justify-center gap-2">
                                <i class="fa-solid fa-arrow-right-to-bracket"></i> ลงเวลามาปฏิบัติงาน
                            </button>
                            <button type="submit" name="action" value="check_out" class="bg-gray-400 hover:bg-gray-500 text-white rounded-xl py-2.5 text-sm font-medium transition-colors shadow-sm flex items-center justify-center gap-2" <?= empty($myLog['checkInTime']) ? 'disabled' : '' ?>>
                                <i class="fa-solid fa-arrow-right-from-bracket"></i> ลงเวลากลับ
                            </button>
                        </div>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col justify-center">
                        <div class="flex justify-between items-start mb-2">
                            <p class="text-sm text-gray-500">ลงเวลาแล้ววันนี้</p>
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center"><i class="fa-solid fa-user-check"></i></div>
                        </div>
                        <p class="text-3xl font-bold text-gray-900"><?= $summaryToday['present'] + $summaryToday['late'] + $summaryToday['leave'] + $summaryToday['sick'] + $summaryToday['absent'] + $summaryToday['remote'] ?></p>
                        <p class="text-xs text-gray-400 mt-2">จากบุคลากร <?= count($staff) ?> คน</p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col justify-center">
                        <div class="flex justify-between items-start mb-2">
                            <p class="text-sm text-gray-500">ยังไม่ลงเวลา</p>
                            <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-500 border border-orange-100 flex items-center justify-center"><i class="fa-solid fa-user-xmark"></i></div>
                        </div>
                        <p class="text-3xl font-bold text-gray-900"><?= $summaryToday['unknown'] ?></p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col justify-center">
                        <div class="flex justify-between items-start mb-2">
                            <p class="text-sm text-gray-500">มาสาย</p>
                            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        </div>
                        <p class="text-3xl font-bold text-gray-900"><?= $summaryToday['late'] ?></p>
                        <p class="text-xs text-gray-400 mt-2">รวมเฉพาะวันที่ลงเวลาแล้ว</p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col justify-center">
                        <div class="flex justify-between items-start mb-2">
                            <p class="text-sm text-gray-500">ลางาน</p>
                        </div>
                        <p class="text-3xl font-bold text-gray-900"><?= $summaryToday['leave'] ?></p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col justify-center">
                        <div class="flex justify-between items-start mb-2">
                            <p class="text-sm text-gray-500">ไปราชการ</p>
                        </div>
                        <p class="text-3xl font-bold text-gray-900"><?= $summaryToday['remote'] ?></p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col justify-center">
                        <div class="flex justify-between items-start mb-2">
                            <p class="text-sm text-gray-500">ยังไม่ลงเวลากลับ</p>
                            <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-500 border border-orange-100 flex items-center justify-center"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
                        </div>
                        <p class="text-3xl font-bold text-gray-900"><?= $summaryToday['notCheckedOut'] ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <i class="fa-regular fa-clock text-indigo-600"></i> สถานะรายบุคคล (<?= date('d M y', strtotime($selectedDate)) ?>)
                        </h2>
                        <p class="text-xs text-gray-500 mt-1">อ่านข้อมูลจากฐานข้อมูลจริงทั้งหมด</p>
                    </div>
                    <form method="get" class="flex items-end gap-2">
                        <label class="block">
                            <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">วันที่</span>
                            <input type="date" name="date" value="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none">
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-semibold text-gray-500 uppercase tracking-wider">เดือน</span>
                            <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none">
                        </label>
                        <button class="rounded-lg bg-blue-600 text-white px-4 py-2 text-sm font-semibold hover:bg-blue-700 transition-colors">ดูข้อมูล</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-500 text-xs border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 font-semibold">ชื่อ - สกุล</th>
                                <th class="px-6 py-4 font-semibold">สถานะ</th>
                                <th class="px-6 py-4 font-semibold text-center">เวลามา</th>
                                <th class="px-6 py-4 font-semibold text-center">เวลากลับ</th>
                                <th class="px-6 py-4 font-semibold text-center">ชั่วโมงทำงาน</th>
                                <th class="px-6 py-4 font-semibold">วิธีลงเวลา / หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($staff as $staffMember): ?>
                                <?php
                                    $userId = (int) $staffMember['id'];
                                    $log = $dailyByUserId[$userId] ?? null;
                                    $displayName = trim(($staffMember['prefix'] ?? '') . ' ' . ($staffMember['firstName'] ?? '') . ' ' . ($staffMember['lastName'] ?? ''));
                                    $initialChar = mb_substr($staffMember['firstName'] ?? '?', 0, 1, 'UTF-8');
                                    $status = $log['status'] ?? 'unknown';
                                    $statusMeta = workStatusMeta($status);
                                    $methodLabel = match (($log['checkInMethod'] ?? 'web')) {
                                        'gps' => 'พิกัด GPS',
                                        'qr' => 'QR Code',
                                        'app' => 'แอปมือถือ',
                                        default => 'เว็บบราวเซอร์',
                                    };
                                    $noteText = trim((string) ($log['note'] ?? ''));
                                    $workHours = '-';
                                    if (!empty($log['checkInTime']) && !empty($log['checkOutTime'])) {
                                        $inSeconds = strtotime($selectedDate . ' ' . $log['checkInTime']);
                                        $outSeconds = strtotime($selectedDate . ' ' . $log['checkOutTime']);
                                        if ($outSeconds > $inSeconds) {
                                            $hours = round(($outSeconds - $inSeconds) / 3600, 1);
                                            $workHours = $hours . ' ชม.';
                                        }
                                    }
                                ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full <?= $statusMeta['dot'] ?> text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                                <?= htmlspecialchars($initialChar, ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-gray-900"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($staffMember['role'] === 'teacher' ? 'ครูผู้สอน' : ($staffMember['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'บุคลากร'), ENT_QUOTES, 'UTF-8') ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="inline-flex items-center gap-2 border px-3 py-1 rounded-full text-xs font-bold <?= $statusMeta['class'] ?>">
                                            <span class="w-2 h-2 rounded-full <?= $statusMeta['dot'] ?>"></span>
                                            <?= htmlspecialchars($statusMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center text-gray-900"><?= !empty($log['checkInTime']) ? date('H:i', strtotime($log['checkInTime'])) : '-' ?></td>
                                    <td class="px-6 py-4 text-center text-gray-900"><?= !empty($log['checkOutTime']) ? date('H:i', strtotime($log['checkOutTime'])) : '-' ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($workHours !== '-'): ?>
                                            <span class="text-gray-900 font-medium"><?= htmlspecialchars($workHours, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 text-xs">
                                        <?php if (!empty($log['checkInTime'])): ?>
                                            <i class="fa-solid fa-location-dot me-1"></i> <?= htmlspecialchars($methodLabel, ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($noteText !== ''): ?>
                                                <div class="text-[11px] text-gray-400 mt-1 whitespace-normal"><?= htmlspecialchars($noteText, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <i class="fa-solid fa-chart-column text-blue-600"></i> สรุปรายเดือน <?= htmlspecialchars(monthLabelThai($selectedMonth), ENT_QUOTES, 'UTF-8') ?>
                        </h2>
                        <p class="text-xs text-gray-500 mt-1">สรุปจากฐานข้อมูลจริงตามช่วงวันที่ของเดือน</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-500 text-xs border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-4 font-semibold">ชื่อ - สกุล</th>
                                <th class="px-6 py-4 font-semibold text-center">มาแล้ว</th>
                                <th class="px-6 py-4 font-semibold text-center">มาสาย</th>
                                <th class="px-6 py-4 font-semibold text-center">ลากิจ</th>
                                <th class="px-6 py-4 font-semibold text-center">ลาป่วย</th>
                                <th class="px-6 py-4 font-semibold text-center">ไม่มา</th>
                                <th class="px-6 py-4 font-semibold text-center">ไปนอกสถานที่</th>
                                <th class="px-6 py-4 font-semibold text-center">รวมวัน</th>
                                <th class="px-6 py-4 font-semibold text-center">อัตรา</th>
                                <th class="px-6 py-4 font-semibold text-center">ยังไม่กลับ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($monthlyRows as $row): ?>
                                <?php
                                    $displayName = trim(($row['prefix'] ?? '') . ' ' . ($row['firstName'] ?? '') . ' ' . ($row['lastName'] ?? ''));
                                    $totalDays = (int) ($row['totalDays'] ?? 0);
                                    $workRate = $totalDays > 0 ? round(((int) ($row['presentDays'] ?? 0) + (int) ($row['lateDays'] ?? 0) + (int) ($row['remoteDays'] ?? 0)) / $totalDays * 100, 1) : 0;
                                ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($row['role'] === 'teacher' ? 'ครูผู้สอน' : ($row['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'บุคลากร'), ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-semibold text-emerald-700"><?= (int) ($row['presentDays'] ?? 0) ?></td>
                                    <td class="px-6 py-4 text-center font-semibold text-amber-700"><?= (int) ($row['lateDays'] ?? 0) ?></td>
                                    <td class="px-6 py-4 text-center font-semibold text-violet-700"><?= (int) ($row['leaveDays'] ?? 0) ?></td>
                                    <td class="px-6 py-4 text-center font-semibold text-sky-700"><?= (int) ($row['sickDays'] ?? 0) ?></td>
                                    <td class="px-6 py-4 text-center font-semibold text-rose-700"><?= (int) ($row['absentDays'] ?? 0) ?></td>
                                    <td class="px-6 py-4 text-center font-semibold text-indigo-700"><?= (int) ($row['remoteDays'] ?? 0) ?></td>
                                    <td class="px-6 py-4 text-center font-semibold text-gray-900"><?= $totalDays ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-blue-700 text-xs font-semibold">
                                            <?= htmlspecialchars((string) $workRate, ENT_QUOTES, 'UTF-8') ?>%
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center font-semibold text-orange-700"><?= (int) ($row['openShiftDays'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($monthlyRows)): ?>
                                <tr>
                                    <td colspan="10" class="px-6 py-10 text-center text-gray-500">ยังไม่มีข้อมูลลงเวลาของเดือนนี้</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.querySelectorAll('input[name="check_method"]').forEach((input) => {
            input.addEventListener('change', () => {
                document.querySelectorAll('.method-card').forEach((card) => {
                    card.classList.remove('border-indigo-200', 'bg-indigo-50', 'text-indigo-700', 'ring-1', 'ring-indigo-500');
                });
                const selected = input.closest('label')?.querySelector('.method-card');
                if (selected) {
                    selected.classList.add('border-indigo-200', 'bg-indigo-50', 'text-indigo-700', 'ring-1', 'ring-indigo-500');
                }
            });
        });
    </script>
</body>
</html>