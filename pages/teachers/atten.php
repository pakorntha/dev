<?php
session_start();
require_once("../../system/a_func.php");

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

$stmt_user = dd_q("SELECT * FROM user WHERE id = ? AND role IN ('teacher','admin') LIMIT 1", [$_SESSION['id']]);
if ($stmt_user->rowCount() === 0) {
    header("Location: ../login.php");
    exit();
}

$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['prefix'] ?? '') . ' ' . $user['firstName'] . ' ' . $user['lastName']);
$initial = mb_substr($user['firstName'], 0, 1, 'UTF-8');

$selectedClassroomId = $_GET['classroom_id'] ?? '';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$message = $_GET['msg'] ?? null;

// ==========================================
// ส่วนบันทึกการเช็คชื่อ
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_attendance') {
    $selectedClassroomId = $_POST['classroom_id'] ?? '';
    $selectedDate = $_POST['attendance_date'] ?? date('Y-m-d');
    $statuses = $_POST['status'] ?? [];
    $notes = $_POST['note'] ?? [];

    foreach ($statuses as $studentProfileId => $status) {
        $allowedStatuses = ['present', 'late', 'sick', 'leave', 'absent', 'unknown'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'unknown';
        }

        $note = trim($notes[$studentProfileId] ?? '');
        $stmt_existing = dd_q("SELECT id FROM attendance WHERE studentProfileId = ? AND attendanceDate = ? LIMIT 1", [$studentProfileId, $selectedDate]);

        if ($stmt_existing->rowCount() > 0) {
            $existing = $stmt_existing->fetch(PDO::FETCH_ASSOC);
            dd_q("UPDATE attendance SET status = ?, note = ?, classroomId = ?, markedBy = ?, updatedAt = NOW(3) WHERE id = ?", [$status, $note !== '' ? $note : null, $selectedClassroomId, $_SESSION['id'], $existing['id']]);
        } else {
            $attendanceId = 'ATT_' . uniqid();
            dd_q("INSERT INTO attendance (id, studentProfileId, classroomId, attendanceDate, status, note, markedBy, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(3))", [$attendanceId, $studentProfileId, $selectedClassroomId, $selectedDate, $status, $note !== '' ? $note : null, $_SESSION['id']]);
        }
    }

    header("Location: atten.php?classroom_id=" . urlencode($selectedClassroomId) . "&date=" . urlencode($selectedDate) . "&msg=saved");
    exit();
}

$classrooms = dd_q("SELECT * FROM classroom ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
if ($selectedClassroomId === '' && count($classrooms) > 0) {
    $selectedClassroomId = $classrooms[0]['id'];
}

$classroomOverview = [];
$stmt_overview = dd_q("
    SELECT
        c.id AS classroomId,
        c.name AS classroomName,
        COUNT(sp.id) AS totalStudents,
        COUNT(att.id) AS checkedCount,
        SUM(CASE WHEN att.id IS NULL THEN 1 ELSE 0 END) AS uncheckedCount,
        SUM(CASE WHEN att.status = 'present' THEN 1 ELSE 0 END) AS presentCount,
        SUM(CASE WHEN att.status = 'late' THEN 1 ELSE 0 END) AS lateCount,
        SUM(CASE WHEN att.status = 'absent' THEN 1 ELSE 0 END) AS absentCount
    FROM classroom c
    LEFT JOIN studentprofile sp ON sp.classroomId = c.id
    LEFT JOIN attendance att ON att.classroomId = c.id AND att.attendanceDate = ?
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
", [$selectedDate]);
$classroomOverview = $stmt_overview->fetchAll(PDO::FETCH_ASSOC);

$selectedClassroomName = 'ไม่พบข้อมูลห้องเรียน';
foreach ($classrooms as $classroom) {
    if ($classroom['id'] === $selectedClassroomId) {
        $selectedClassroomName = $classroom['name'];
        break;
    }
}

$students = [];
$summary = [
    'present' => 0, 'late' => 0, 'sick' => 0, 'leave' => 0, 'absent' => 0, 'unknown' => 0, 'total' => 0,
];

$analyzedRiskStudents = [];
$classAverageRate = 0;
$recentDays = 30;

if (!empty($selectedClassroomId)) {
    // ดึงรายชื่อนักเรียนเพื่อเช็คชื่อ
    $stmt_students = dd_q("
        SELECT sp.id AS studentProfileId, u.username, u.prefix, u.firstName, u.lastName, sp.grade,
               att.status AS todayStatus, att.note AS todayNote
        FROM studentprofile sp
        INNER JOIN user u ON sp.userId = u.id
        LEFT JOIN attendance att ON att.studentProfileId = sp.id AND att.attendanceDate = ?
        WHERE sp.classroomId = ? AND u.role = 'student'
        ORDER BY u.firstName ASC
    ", [$selectedDate, $selectedClassroomId]);
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

    // สรุปยอดวันนี้
    $summaryToday = dd_q("
        SELECT status, COUNT(*) AS total
        FROM attendance
        WHERE classroomId = ? AND attendanceDate = ?
        GROUP BY status
    ", [$selectedClassroomId, $selectedDate])->fetchAll(PDO::FETCH_ASSOC);

    foreach ($summaryToday as $row) {
        $summary[$row['status']] = (int) $row['total'];
    }
    $summary['total'] = count($students);

    // ==========================================
    // วิเคราะห์กลุ่มเสี่ยง (30 วันย้อนหลัง)
    // ==========================================
    $stmt_risk = dd_q("
        SELECT
            sp.id AS studentProfileId,
            u.username, u.prefix, u.firstName, u.lastName,
            COUNT(a.id) AS totalDays,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS presentDays,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS lateDays,
            SUM(CASE WHEN a.status = 'sick' THEN 1 ELSE 0 END) AS sickDays,
            SUM(CASE WHEN a.status = 'leave' THEN 1 ELSE 0 END) AS leaveDays,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absentDays
        FROM studentprofile sp
        INNER JOIN user u ON sp.userId = u.id
        LEFT JOIN attendance a ON a.studentProfileId = sp.id AND a.attendanceDate >= DATE_SUB(?, INTERVAL ? DAY)
        WHERE sp.classroomId = ? AND u.role = 'student'
        GROUP BY sp.id, u.username, u.prefix, u.firstName, u.lastName
    ", [$selectedDate, $recentDays, $selectedClassroomId]);
    $riskStudentsData = $stmt_risk->fetchAll(PDO::FETCH_ASSOC);

    foreach ($riskStudentsData as $student) {
        $total = (int)$student['totalDays'];
        if ($total == 0) continue;

        $present = (int)$student['presentDays'];
        $late = (int)$student['lateDays'];
        $absent = (int)$student['absentDays'];
        $sick = (int)$student['sickDays'];
        $leave = (int)$student['leaveDays'];

        // คำนวณอัตราเข้าเรียน (มาเรียน + มาสาย ถือว่าเข้าเรียน)
        $attendanceRate = round((($present + $late) / $total) * 100);

        $warnings = [];
        $score = 0;

        // เกณฑ์วิเคราะห์
        if ($absent >= 3) {
            $warnings[] = "ขาดเรียนสะสม {$absent} วัน";
            $score += 40;
        }
        if ($late >= 5) {
            $warnings[] = "มาสายเกิน 5 ครั้ง ({$late} ครั้ง)";
            $score += 30;
        }
        if (($sick + $leave) >= 5) {
            $warnings[] = "ลาบ่อยผิดปกติ (" . ($sick + $leave) . " ครั้ง)";
            $score += 20;
        }
        if ($attendanceRate < 80) {
            $warnings[] = "อัตราการมาเรียน {$attendanceRate}% ต่ำกว่าเกณฑ์";
            $score += (80 - $attendanceRate);
        }

        // ถ้าเข้าเกณฑ์ จะถูกนำมาจัดระดับความเสี่ยง
        if (!empty($warnings)) {
            if ($score >= 60 || $attendanceRate <= 50) {
                $level = 'เร่งด่วน';
                $theme = 'rose';
                $barColor = 'bg-rose-500';
            } elseif ($score >= 30 || $attendanceRate < 80) {
                $level = 'เฝ้าระวัง';
                $theme = 'amber';
                $barColor = 'bg-orange-500';
            } else {
                $level = 'ติดตาม';
                $theme = 'slate';
                $barColor = 'bg-slate-500';
            }

            $analyzedRiskStudents[] = [
                'name' => trim(($student['prefix'] ?? '') . ' ' . $student['firstName'] . ' ' . $student['lastName']),
                'rate' => $attendanceRate,
                'warnings' => $warnings,
                'level' => $level,
                'theme' => $theme,
                'barColor' => $barColor,
                'score' => $score // สำหรับเรียงลำดับ
            ];
        }
    }

    // เรียงลำดับคนที่มีความเสี่ยงสูงขึ้นก่อน
    usort($analyzedRiskStudents, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    // อัตราเฉลี่ยทั้งห้อง
    $classRateStmt = dd_q("
        SELECT COUNT(a.id) AS totalDays, SUM(CASE WHEN a.status IN ('present', 'late') THEN 1 ELSE 0 END) AS attendedDays
        FROM attendance a
        WHERE a.classroomId = ? AND a.attendanceDate >= DATE_SUB(?, INTERVAL ? DAY)
    ", [$selectedClassroomId, $selectedDate, $recentDays]);
    $classRate = $classRateStmt->fetch(PDO::FETCH_ASSOC);
    if ((int)$classRate['totalDays'] > 0) {
        $classAverageRate = round(((int)$classRate['attendedDays'] / (int)$classRate['totalDays']) * 100, 1);
    }
}

function attendanceColor($status) {
    return match ($status) {
        'present' => ['bg' => 'bg-emerald-600 text-white', 'label' => 'มาเรียน'],
        'late' => ['bg' => 'bg-amber-500 text-white', 'label' => 'มาสาย'],
        'sick' => ['bg' => 'bg-sky-500 text-white', 'label' => 'ลาป่วย'],
        'leave' => ['bg' => 'bg-violet-500 text-white', 'label' => 'ลากิจ'],
        'absent' => ['bg' => 'bg-rose-500 text-white', 'label' => 'ขาดเรียน'],
        default => ['bg' => 'bg-slate-500 text-white', 'label' => 'ไม่ทราบสาเหตุ'],
    };
}

function classroomCheckLabel($checkedCount, $totalStudents) {
    if ((int) $checkedCount > 0 && (int) $checkedCount >= (int) $totalStudents) return ['text' => 'เช็คชื่อแล้ว', 'class' => 'bg-emerald-100 text-emerald-700 ring-emerald-200'];
    if ((int) $checkedCount > 0) return ['text' => 'เช็คบางส่วน', 'class' => 'bg-amber-100 text-amber-700 ring-amber-200'];
    return ['text' => 'ยังไม่เช็คชื่อ', 'class' => 'bg-slate-100 text-slate-600 ring-slate-200'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การมาเรียนนักเรียน - SiS4 SCHOOL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 text-gray-800 h-screen overflow-hidden flex">
    <aside class="w-64 bg-[#111827] text-gray-300 flex flex-col h-full flex-shrink-0">
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white mr-3">
                <i class="fa-solid fa-school"></i>
            </div>
            <div>
                <h1 class="text-white font-bold text-sm leading-tight">SiS4 SCHOOL</h1>
                <p class="text-xs text-gray-400">ระบบสารบรรณอัจฉริยะ</p>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto no-scrollbar px-3 py-4 space-y-6 text-sm">
            <div>
                <a href="home.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded transition-colors">
                    <i class="fa-solid fa-border-all w-5 text-center"></i> แดชบอร์ด
                </a>
            </div>
            <div>
                <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานรายวิชาที่รับผิดชอบ</p>
                <ul class="space-y-1">
                    <li><a href="homework.php" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-file-pen w-5 text-center"></i> มอบหมายงาน / การบ้าน</a></li>
                </ul>
            </div>
            <div>
                <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานวิชาการ</p>
                <ul class="space-y-1">
                    <li><a href="student_list.php" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-users w-5 text-center"></i> นักเรียนและห้องเรียน</a></li>
                    <li><a href="atten.php" class="flex items-center gap-3 px-3 py-2 bg-blue-600 text-white rounded transition-colors shadow-sm"><i class="fa-solid fa-chalkboard-user w-5 text-center"></i> การมาเรียนนักเรียน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-book-open w-5 text-center"></i> แผนการสอน</a></li>
                </ul>
            </div>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded bg-blue-600 flex items-center justify-center text-white font-bold text-sm"><?= htmlspecialchars($initial) ?></div>
                <div class="flex-1 overflow-hidden">
                    <p class="text-sm text-white font-medium truncate"><?= htmlspecialchars($fullName) ?></p>
                    <p class="text-xs text-gray-400 truncate">ครูผู้สอน</p>
                </div>
                <a href="../../system/logout.php" class="text-gray-400 hover:text-red-400 transition-colors" title="ออกจากระบบ"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
            </div>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <div>
                <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">บันทึกการมาเรียน</p>
                <h1 class="text-lg font-bold text-gray-900">การมาเรียนนักเรียน</h1>
            </div>
            <div class="flex items-center gap-3 text-sm text-gray-600">
                <span class="hidden md:inline-block">คุณ <?= htmlspecialchars($fullName) ?></span>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            <?php if ($message === 'saved'): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded flex items-center gap-3">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i>
                    <p class="text-sm font-medium">บันทึกการมาเรียนเรียบร้อยแล้ว</p>
                </div>
            <?php endif; ?>

            <!-- ภาพรวมของห้อง -->
            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2"><i class="fa-solid fa-school text-blue-600"></i> ภาพรวมการเช็คชื่อห้องเรียน</h2>
                        <p class="text-xs text-gray-500 mt-1">สถานะเช็คชื่อประจำวันของแต่ละห้องในวันที่ <?= htmlspecialchars(date('d/m/Y', strtotime($selectedDate))) ?></p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                    <?php foreach ($classroomOverview as $overview): ?>
                        <?php
                            $checkLabel = classroomCheckLabel($overview['checkedCount'], $overview['totalStudents']);
                            $progress = (int) $overview['totalStudents'] > 0 ? round(((int) $overview['checkedCount'] / (int) $overview['totalStudents']) * 100, 1) : 0;
                        ?>
                        <a href="atten.php?classroom_id=<?= urlencode($overview['classroomId']) ?>&date=<?= urlencode($selectedDate) ?>" class="rounded-xl border border-gray-200 p-4 bg-gray-50/70 hover:bg-white hover:border-blue-300 transition-all group">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">ห้องเรียน</p>
                                    <h3 class="text-lg font-bold text-gray-900 mt-1 group-hover:text-blue-600 transition-colors">ห้อง <?= htmlspecialchars($overview['classroomName']) ?></h3>
                                </div>
                                <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-xs font-medium ring-1 ring-inset whitespace-nowrap <?= $checkLabel['class'] ?>"><?= $checkLabel['text'] ?></span>
                            </div>
                            <div class="mt-3 h-2 rounded-full bg-gray-200 overflow-hidden">
                                <div class="h-full rounded-full bg-blue-600" style="width: <?= htmlspecialchars((string) $progress) ?>%;"></div>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                                <span><?= htmlspecialchars((string) $overview['checkedCount']) ?>/<?= htmlspecialchars((string) $overview['totalStudents']) ?> คน</span>
                                <span>ไม่เช็ค <?= htmlspecialchars((string) $overview['uncheckedCount']) ?> คน</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- สถิติห้องที่เลือก -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm flex flex-col justify-center">
                    <p class="text-sm text-gray-500">ชั้นเรียนที่เลือก</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900"><?= htmlspecialchars($selectedClassroomName) ?></p>
                    <p class="text-xs text-gray-400 mt-1">วันที่ <?= htmlspecialchars(date('d/m/Y', strtotime($selectedDate))) ?></p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm flex flex-col justify-center">
                    <p class="text-sm text-gray-500">มาเรียน</p>
                    <div class="mt-1 flex items-end justify-between">
                        <p class="text-3xl font-bold text-emerald-600"><?= htmlspecialchars((string) $summary['present']) ?></p>
                        <span class="text-xs text-gray-500 mb-1">จาก <?= htmlspecialchars((string) $summary['total']) ?> คน</span>
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm flex flex-col justify-center">
                    <p class="text-sm text-gray-500">มาสาย</p>
                    <p class="mt-1 text-3xl font-bold text-amber-600"><?= htmlspecialchars((string) $summary['late']) ?></p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm flex flex-col justify-center">
                    <p class="text-sm text-gray-500">ขาดเรียน</p>
                    <p class="mt-1 text-3xl font-bold text-rose-600"><?= htmlspecialchars((string) $summary['absent']) ?></p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm flex flex-col justify-center">
                    <p class="text-sm text-gray-500">อัตรามาเรียน (30 วัน)</p>
                    <p class="mt-1 text-3xl font-bold text-gray-900"><?= htmlspecialchars((string) $classAverageRate) ?>%</p>
                </div>
            </div>

            <!-- วิเคราะห์นักเรียนกลุ่มเสี่ยง (รูปแบบใหม่) -->
            <?php if (!empty($analyzedRiskStudents)): ?>
            <div class="mt-6 mb-6">
                <div class="flex flex-wrap items-center justify-between mb-3 gap-3">
                    <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <i class="fa-regular fa-bell text-orange-500"></i> นักเรียนกลุ่มเสี่ยงที่ควรติดตาม (<?= count($analyzedRiskStudents) ?> คน)
                    </h2>
                    <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700 border border-violet-100">วิเคราะห์ข้อมูลอัตโนมัติ</span>
                </div>
                <p class="text-sm text-gray-600 mb-4 leading-relaxed">
                    ระบบวิเคราะห์จากข้อมูลย้อนหลัง 30 วัน — เกณฑ์: ขาดเรียนสะสม 3 วัน · มาสายเกิน 5 ครั้ง · ลาบ่อยผิดปกติ · อัตราการมาเรียนต่ำกว่า 80%<br>
                    <span class="text-gray-400 text-xs">ระบบจะแจ้งเตือนครูประจำชั้น ฝ่ายปกครอง และผู้อำนวยการโดยอัตโนมัติ</span>
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <?php foreach ($analyzedRiskStudents as $risk): ?>
                        <div class="rounded-xl border border-<?= $risk['theme'] ?>-200 bg-<?= $risk['theme'] ?>-50/40 p-5 flex flex-col">
                            <div class="flex justify-between items-start mb-3 gap-2">
                                <div>
                                    <h3 class="font-bold text-gray-900 text-base"><?= $risk['name'] ?></h3>
                                    <p class="text-xs text-gray-500 mt-1">ห้อง <?= htmlspecialchars($selectedClassroomName) ?></p>
                                </div>
                                <span class="border border-<?= $risk['theme'] ?>-200 text-<?= $risk['theme'] ?>-700 bg-<?= $risk['theme'] ?>-100 px-2 py-0.5 rounded text-xs font-bold whitespace-nowrap">
                                    <?= $risk['level'] ?>
                                </span>
                            </div>

                            <div class="space-y-1.5 mb-4 flex-1">
                                <?php foreach ($risk['warnings'] as $warning): ?>
                                    <p class="text-xs text-<?= $risk['theme'] ?>-700 flex items-start gap-1.5">
                                        <i class="fa-solid fa-triangle-exclamation mt-0.5"></i> <?= $warning ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-auto">
                                <div class="h-1.5 w-full bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full <?= $risk['barColor'] ?>" style="width: <?= $risk['rate'] ?>%;"></div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">อัตราการมาเรียน <?= $risk['rate'] ?>%</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ตารางเช็คชื่อ -->
            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3 bg-gray-50/50">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900 flex items-center gap-2"><i class="fa-solid fa-list-check text-blue-600"></i> รายชื่อเช็คการเข้าเรียน</h2>
                        <p class="text-xs text-gray-500 mt-1">เลือกสถานะแล้วกดบันทึกที่ด้านล่าง</p>
                    </div>
                    <form method="GET" class="flex flex-wrap items-center gap-2">
                        <select name="classroom_id" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:ring-1 focus:ring-blue-500 focus:outline-none">
                            <?php foreach ($classrooms as $classroom): ?>
                                <option value="<?= htmlspecialchars($classroom['id']) ?>" <?= $classroom['id'] === $selectedClassroomId ? 'selected' : '' ?>>ห้อง <?= htmlspecialchars($classroom['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:ring-1 focus:ring-blue-500 focus:outline-none">
                        <button type="submit" class="rounded-lg bg-gray-800 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-900 transition-colors">แสดง</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <form method="POST" class="min-w-[920px]">
                        <input type="hidden" name="action" value="save_attendance">
                        <input type="hidden" name="classroom_id" value="<?= htmlspecialchars($selectedClassroomId) ?>">
                        <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($selectedDate) ?>">

                        <table class="w-full text-left text-sm">
                            <thead class="bg-white text-gray-500 text-xs uppercase border-b border-gray-200">
                                <tr>
                                    <th class="px-5 py-3 font-medium w-12 text-center">เลขที่</th>
                                    <th class="px-5 py-3 font-medium">รหัส</th>
                                    <th class="px-5 py-3 font-medium">ชื่อ-นามสกุล</th>
                                    <th class="px-5 py-3 font-medium">สถานะการเข้าเรียน</th>
                                    <th class="px-5 py-3 font-medium">หมายเหตุ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (!empty($students)): ?>
                                    <?php foreach ($students as $index => $student): ?>
                                        <?php $currentStatus = $student['todayStatus'] ?? 'unknown'; ?>
                                        <?php $defaultStatus = $currentStatus === 'unknown' ? 'present' : $currentStatus; ?>
                                        <tr class="hover:bg-gray-50/50 transition-colors">
                                            <td class="px-5 py-4 text-center text-gray-500"><?= $index + 1 ?></td>
                                            <td class="px-5 py-4 font-mono text-xs text-gray-600 bg-gray-50/50 rounded"><?= htmlspecialchars($student['username']) ?></td>
                                            <td class="px-5 py-4">
                                                <p class="font-medium text-gray-900"><?= htmlspecialchars(trim(($student['prefix'] ?? '') . ' ' . $student['firstName'] . ' ' . $student['lastName'])) ?></p>
                                            </td>
                                            <td class="px-5 py-4">
                                                <div class="grid grid-cols-3 gap-2 xl:grid-cols-6">
                                                    <?php foreach (['present', 'late', 'sick', 'leave', 'absent', 'unknown'] as $status): ?>
                                                        <?php $state = attendanceColor($status); ?>
                                                        <label class="cursor-pointer">
                                                            <input type="radio" name="status[<?= htmlspecialchars((string) $student['studentProfileId']) ?>]" value="<?= $status ?>" class="attendance-status-input sr-only" <?= $defaultStatus === $status ? 'checked' : '' ?>>
                                                            <span data-status-pill="<?= htmlspecialchars((string) $student['studentProfileId']) ?>" data-status-value="<?= $status ?>" class="attendance-status-pill block rounded-md px-2 py-1.5 text-center text-xs font-medium transition-colors border border-transparent <?= $defaultStatus === $status ? $state['bg'] . ' shadow-sm' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
                                                                <?= $state['label'] ?>
                                                            </span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4">
                                                <input type="text" name="note[<?= htmlspecialchars((string) $student['studentProfileId']) ?>]" value="<?= htmlspecialchars($student['todayNote'] ?? '') ?>" placeholder="ระบุสาเหตุ (ถ้ามี)" class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-5 py-12 text-center text-gray-500">
                                            <i class="fa-solid fa-users-slash text-3xl mb-3 text-gray-300"></i><br>
                                            ไม่มีข้อมูลนักเรียนในห้องที่เลือก
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <?php if (!empty($students)): ?>
                        <div class="flex items-center justify-end gap-3 border-t border-gray-100 p-5 bg-gray-50/50">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition-colors shadow-sm">
                                <i class="fa-solid fa-floppy-disk"></i> บันทึกการเช็คชื่อทั้งหมด
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </section>
            
            <div class="h-6"></div>
        </main>
    </div>

    <script>
        document.addEventListener('change', function (event) {
            if (!event.target.classList.contains('attendance-status-input')) return;

            const input = event.target;
            const wrapper = input.closest('td');
            if (!wrapper) return;

            const pills = wrapper.querySelectorAll('.attendance-status-pill');
            pills.forEach(function (pill) {
                const isActive = pill.getAttribute('data-status-value') === input.value;
                pill.classList.toggle('bg-emerald-600', input.value === 'present' && isActive);
                pill.classList.toggle('bg-amber-500', input.value === 'late' && isActive);
                pill.classList.toggle('bg-sky-500', input.value === 'sick' && isActive);
                pill.classList.toggle('bg-violet-500', input.value === 'leave' && isActive);
                pill.classList.toggle('bg-rose-500', input.value === 'absent' && isActive);
                pill.classList.toggle('bg-slate-500', input.value === 'unknown' && isActive);
                pill.classList.toggle('shadow-sm', isActive);

                const inactive = !isActive;
                pill.classList.toggle('bg-gray-100', inactive);
                pill.classList.toggle('text-gray-500', inactive);
                pill.classList.toggle('hover:bg-gray-200', inactive);
                pill.classList.toggle('text-white', isActive);
            });
        });
    </script>
</body>
</html>