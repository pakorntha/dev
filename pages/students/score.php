<?php
session_start();
require_once("../../system/a_func.php");

if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

$stmt_user = dd_q("SELECT * FROM user WHERE id = ? AND role = 'student' LIMIT 1", [$_SESSION['id']]);
if ($stmt_user->rowCount() === 0) {
    header("Location: ../../index.php");
    exit();
}

$student = $stmt_user->fetch(PDO::FETCH_ASSOC);
$studentName = trim(($student['prefix'] ?? '') . ' ' . $student['firstName'] . ' ' . $student['lastName']);
$initial = mb_substr($student['firstName'], 0, 1, 'UTF-8');

$stmt_profile = dd_q("SELECT id, classroomId FROM studentprofile WHERE userId = ? LIMIT 1", [$student['id']]);
$studentProfile = $stmt_profile->fetch(PDO::FETCH_ASSOC);

$overallEarned = 0.0;
$overallPossible = 0.0;
$reviewedCount = 0;
$pendingCount = 0;
$unsubmittedCount = 0;
$scoreRows = [];
$courseStats = [];

if ($studentProfile && !empty($studentProfile['classroomId'])) {
    $stmt_scores = dd_q("
        SELECT
            c.id AS courseId,
            c.code AS courseCode,
            c.name AS courseName,
            a.id AS assignmentId,
            a.title,
            a.maxScore,
            a.dueDate,
            sub.id AS submissionId,
            sub.score,
            sub.status,
            sub.feedback,
            sub.submittedAt
        FROM assignment a
        INNER JOIN course c ON c.id = a.courseId
        INNER JOIN courseclassroom cc ON cc.courseId = c.id
        LEFT JOIN submission sub ON sub.assignmentId = a.id AND sub.studentId = ?
        WHERE cc.classroomId = ?
        ORDER BY c.name ASC, a.dueDate ASC, a.createdAt ASC
    ", [$studentProfile['id'], $studentProfile['classroomId']]);

    $scoreRows = $stmt_scores->fetchAll(PDO::FETCH_ASSOC);

    foreach ($scoreRows as $row) {
        $courseId = $row['courseId'];
        if (!isset($courseStats[$courseId])) {
            $courseStats[$courseId] = [
                'courseCode' => $row['courseCode'],
                'courseName' => $row['courseName'],
                'earned' => 0.0,
                'possible' => 0.0,
                'reviewed' => 0,
                'pending' => 0,
                'unsubmitted' => 0,
            ];
        }

        if ($row['submissionId'] === null) {
            $courseStats[$courseId]['unsubmitted']++;
            $unsubmittedCount++;
            continue;
        }

        if ($row['score'] === null) {
            $courseStats[$courseId]['pending']++;
            $pendingCount++;
            continue;
        }

        $scoreValue = (float) $row['score'];
        $maxScoreValue = (float) $row['maxScore'];

        $courseStats[$courseId]['earned'] += $scoreValue;
        $courseStats[$courseId]['possible'] += $maxScoreValue;
        $courseStats[$courseId]['reviewed']++;

        $overallEarned += $scoreValue;
        $overallPossible += $maxScoreValue;
        $reviewedCount++;
    }
}

$overallPercent = $overallPossible > 0 ? min(100, round(($overallEarned / $overallPossible) * 100, 1)) : 0;

function scoreBarClass($percent) {
    if ($percent >= 80) return 'bg-emerald-500';
    if ($percent >= 60) return 'bg-blue-500';
    if ($percent >= 40) return 'bg-amber-500';
    return 'bg-red-500';
}

function scoreStatusLabel($row) {
    if ($row['submissionId'] === null) {
        return ['text' => 'ยังไม่ส่ง', 'class' => 'bg-slate-100 text-slate-600 border-slate-200'];
    }

    if ($row['score'] === null) {
        return ['text' => 'รอการตรวจ', 'class' => 'bg-orange-100 text-orange-700 border-orange-200'];
    }

    return ['text' => 'ตรวจแล้ว', 'class' => 'bg-emerald-100 text-emerald-700 border-emerald-200'];
}

function scorePercent($score, $maxScore) {
    if ($score === null || $maxScore <= 0) return null;
    return round(((float) $score / (float) $maxScore) * 100, 1);
}

$latestReviewed = array_values(array_filter($scoreRows, function ($row) {
    return $row['score'] !== null;
}));
usort($latestReviewed, function ($left, $right) {
    return strtotime($right['submittedAt'] ?? '') <=> strtotime($left['submittedAt'] ?? '');
});
$latestReviewed = array_slice($latestReviewed, 0, 8);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการเรียน - SiS4 SCHOOL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 h-screen overflow-hidden flex">
    <aside class="w-64 bg-[#111827] text-gray-300 flex flex-col h-full flex-shrink-0">
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white mr-3">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <h1 class="text-white font-bold text-sm leading-tight">SiS4 SCHOOL</h1>
                <p class="text-xs text-gray-400">ระบบจัดการสารสนเทศนักเรียน</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto no-scrollbar px-3 py-2 space-y-6">
            <div>
                <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">เมนูหลัก</p>
                <ul class="space-y-1 text-sm">
                    <li>
                        <a href="home.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded-lg transition-colors">
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
                        <a href="homework.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded-lg transition-colors">
                            <i class="fa-solid fa-book-open w-5 text-center"></i>
                            การบ้านและชิ้นงาน
                        </a>
                    </li>
                    <li>
                        <a href="score.php" class="flex items-center gap-3 px-3 py-2.5 bg-blue-600 text-white rounded-lg shadow-sm">
                            <i class="fa-solid fa-chart-line w-5 text-center"></i>
                            ผลการเรียน
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="p-4 border-t border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-sm">
                    <?= htmlspecialchars($initial) ?>
                </div>
                <div class="flex-1 overflow-hidden">
                    <p class="text-sm text-white font-medium truncate"><?= htmlspecialchars($studentName) ?></p>
                    <p class="text-xs text-gray-400 truncate">นักเรียน</p>
                </div>
                <a href="../logout.php" class="text-gray-400 hover:text-red-400 transition-colors" title="ออกจากระบบ">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <div>
                <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">คะแนนเก็บจากงานที่ตรวจแล้ว</p>
                <h1 class="text-lg font-bold text-gray-900">ผลการเรียนของฉัน</h1>
            </div>
            <div class="text-sm text-gray-600 hidden md:block">
                <?= htmlspecialchars($studentName) ?>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-slate-900 text-white rounded-2xl p-6 shadow-lg relative overflow-hidden">
                <div class="absolute -right-8 -top-8 w-36 h-36 rounded-full bg-white/10 blur-2xl"></div>
                <div class="relative z-10 grid grid-cols-1 lg:grid-cols-3 gap-6 items-center">
                    <div class="lg:col-span-2">
                        <p class="text-sm text-white/75 mb-2">คะแนนสะสมรวม</p>
                        <div class="flex items-end gap-3 flex-wrap">
                            <span class="text-5xl font-bold"><?= htmlspecialchars(number_format($overallPercent, 1)) ?></span>
                            <span class="text-lg text-white/80 mb-1">/ 100</span>
                        </div>
                        <p class="text-sm text-white/75 mt-3">คำนวณจากงานที่ตรวจแล้วทั้งหมดในห้องเรียนของคุณ</p>
                    </div>
                    <div class="bg-white/10 rounded-2xl p-4 backdrop-blur border border-white/10">
                        <div class="flex justify-between text-sm mb-2">
                            <span>ความคืบหน้า</span>
                            <span><?= htmlspecialchars(number_format($overallPercent, 1)) ?>%</span>
                        </div>
                        <div class="h-3 rounded-full bg-white/15 overflow-hidden">
                            <div class="h-full rounded-full <?= scoreBarClass($overallPercent) ?>" style="width: <?= htmlspecialchars((string) $overallPercent) ?>%;"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-xs mt-4 text-white/80">
                            <div>
                                <p class="text-white/60">ตรวจแล้ว</p>
                                <p class="text-base font-semibold text-white"><?= htmlspecialchars((string) $reviewedCount) ?> งาน</p>
                            </div>
                            <div>
                                <p class="text-white/60">รอตรวจ</p>
                                <p class="text-base font-semibold text-white"><?= htmlspecialchars((string) $pendingCount) ?> งาน</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <p class="text-sm text-gray-500">งานที่ตรวจแล้ว</p>
                    <div class="mt-2 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-3xl font-bold text-gray-900"><?= htmlspecialchars((string) $reviewedCount) ?></p>
                            <p class="text-xs text-gray-500 mt-1">งานที่นำมาคิดคะแนน</p>
                        </div>
                        <i class="fa-solid fa-circle-check text-emerald-500 text-2xl"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <p class="text-sm text-gray-500">งานรอตรวจ</p>
                    <div class="mt-2 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-3xl font-bold text-gray-900"><?= htmlspecialchars((string) $pendingCount) ?></p>
                            <p class="text-xs text-gray-500 mt-1">ส่งแล้วแต่ยังไม่มีคะแนน</p>
                        </div>
                        <i class="fa-solid fa-hourglass-half text-orange-500 text-2xl"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <p class="text-sm text-gray-500">งานยังไม่ส่ง</p>
                    <div class="mt-2 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-3xl font-bold text-gray-900"><?= htmlspecialchars((string) $unsubmittedCount) ?></p>
                            <p class="text-xs text-gray-500 mt-1">ยังไม่แนบไฟล์ส่งงาน</p>
                        </div>
                        <i class="fa-solid fa-file-circle-xmark text-slate-400 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <?php if (!empty($courseStats)): ?>
                    <?php foreach ($courseStats as $course): ?>
                        <?php
                            $coursePercent = $course['possible'] > 0 ? min(100, round(($course['earned'] / $course['possible']) * 100, 1)) : 0;
                        ?>
                        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div>
                                    <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold"><?= htmlspecialchars($course['courseCode']) ?></p>
                                    <h3 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($course['courseName']) ?></h3>
                                </div>
                                <div class="text-right">
                                    <p class="text-3xl font-bold text-gray-900"><?= htmlspecialchars(number_format($coursePercent, 1)) ?></p>
                                    <p class="text-xs text-gray-500">/ 100</p>
                                </div>
                            </div>

                            <div class="h-3 rounded-full bg-gray-100 overflow-hidden mb-3">
                                <div class="h-full rounded-full <?= scoreBarClass($coursePercent) ?>" style="width: <?= htmlspecialchars((string) $coursePercent) ?>%;"></div>
                            </div>

                            <div class="flex justify-between text-sm text-gray-600 mb-4">
                                <span>คะแนนที่ได้ <?= htmlspecialchars(number_format((float) $course['earned'], 1)) ?></span>
                                <span>จากคะแนนเต็ม <?= htmlspecialchars(number_format((float) $course['possible'], 1)) ?></span>
                            </div>

                            <div class="grid grid-cols-3 gap-3 text-center text-xs">
                                <div class="bg-gray-50 rounded-xl py-3">
                                    <p class="text-gray-500">ตรวจแล้ว</p>
                                    <p class="text-base font-bold text-gray-900 mt-1"><?= htmlspecialchars((string) $course['reviewed']) ?></p>
                                </div>
                                <div class="bg-gray-50 rounded-xl py-3">
                                    <p class="text-gray-500">รอตรวจ</p>
                                    <p class="text-base font-bold text-gray-900 mt-1"><?= htmlspecialchars((string) $course['pending']) ?></p>
                                </div>
                                <div class="bg-gray-50 rounded-xl py-3">
                                    <p class="text-gray-500">ยังไม่ส่ง</p>
                                    <p class="text-base font-bold text-gray-900 mt-1"><?= htmlspecialchars((string) $course['unsubmitted']) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 lg:col-span-2 text-center text-gray-500">
                        <i class="fa-solid fa-chart-line text-4xl text-gray-300 mb-3"></i>
                        <p>ยังไม่มีข้อมูลคะแนนในห้องเรียนของคุณ</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-bold text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-blue-600"></i> รายการคะแนนล่าสุด
                    </h2>
                    <span class="text-xs text-gray-500">แสดงงานที่ตรวจแล้วล่าสุด 8 รายการ</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-5 py-3 font-medium">วิชา</th>
                                <th class="px-5 py-3 font-medium">งาน</th>
                                <th class="px-5 py-3 font-medium text-center">คะแนน</th>
                                <th class="px-5 py-3 font-medium text-center">สถานะ</th>
                                <th class="px-5 py-3 font-medium">Feedback</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (!empty($latestReviewed)): ?>
                                <?php foreach ($latestReviewed as $row): ?>
                                    <?php $scorePercentValue = scorePercent($row['score'], $row['maxScore']); ?>
                                    <?php $status = scoreStatusLabel($row); ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-4">
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($row['courseName']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($row['courseCode']) ?></p>
                                        </td>
                                        <td class="px-5 py-4">
                                            <p class="text-gray-900"><?= htmlspecialchars($row['title']) ?></p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <?php if (!empty($row['submittedAt'])): ?>
                                                    ส่งเมื่อ <?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['submittedAt']))) ?>
                                                <?php endif; ?>
                                            </p>
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <p class="text-lg font-bold text-gray-900"><?= htmlspecialchars(number_format((float) $scorePercentValue, 1)) ?>%</p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars((string) $row['score']) ?> / <?= htmlspecialchars((string) $row['maxScore']) ?></p>
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border <?= $status['class'] ?>">
                                                <?= htmlspecialchars($status['text']) ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-4">
                                            <?php if (!empty($row['feedback'])): ?>
                                                <p class="text-sm text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($row['feedback']) ?></p>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400">ยังไม่มี feedback</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-gray-500">ยังไม่มีคะแนนที่ตรวจแล้ว</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="h-4"></div>
        </main>
    </div>
</body>
</html>