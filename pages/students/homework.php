<?php
session_start();
// ถอยหลังไปหาไฟล์เชื่อมต่อฐานข้อมูลให้ถูกต้อง (ปรับ path ตามโครงสร้างจริงของคุณ)
require_once("../../system/a_func.php");

// 1. ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// ดึงข้อมูลนักเรียนจากฐานข้อมูล
$stmt_user = dd_q("SELECT * FROM user WHERE id = ? AND role = 'student' LIMIT 1", [$_SESSION['id']]);
if ($stmt_user->rowCount() === 0) {
    header("Location: ../../index.php");
    exit();
}
$student = $stmt_user->fetch(PDO::FETCH_ASSOC);
$studentName = trim(($student['prefix'] ?? '') . ' ' . $student['firstName'] . ' ' . $student['lastName']);
$initial = mb_substr($student['firstName'], 0, 1, 'UTF-8');

// 2. ดึงข้อมูลโปรไฟล์นักเรียนเพื่อหา Classroom ID
$stmt_profile = dd_q("SELECT id, classroomId FROM studentprofile WHERE userId = ? LIMIT 1", [$student['id']]);
$studentProfile = $stmt_profile->fetch(PDO::FETCH_ASSOC);

$pending_tasks = [];
$completed_tasks = [];

if ($studentProfile) {
    $studentProfileId = $studentProfile['id'];
    $classroomId = $studentProfile['classroomId'];

    // 3. ดึงข้อมูลการบ้านทั้งหมดของห้องเรียนนี้ + เช็คว่าส่งหรือยัง
    $sql = "
        SELECT a.id AS assignmentId, a.title, a.description, a.maxScore, a.dueDate, 
               c.name AS courseName, c.code AS courseCode,
               t.firstName AS teacherName, t.lastName AS teacherLastName,
               sub.id AS submissionId, sub.score, sub.updatedAt AS submittedAt
        FROM assignment a
        INNER JOIN course c ON a.courseId = c.id
        INNER JOIN courseclassroom cc ON c.id = cc.courseId
        LEFT JOIN user t ON c.teacherId = t.id
        LEFT JOIN submission sub ON a.id = sub.assignmentId AND sub.studentId = ?
        WHERE cc.classroomId = ?
        ORDER BY a.dueDate ASC
    ";
    
    $stmt_asn = dd_q($sql, [$studentProfileId, $classroomId]);
    $all_assignments = $stmt_asn->fetchAll(PDO::FETCH_ASSOC);

    // แยกงานที่ส่งแล้ว กับ ยังไม่ส่ง
    foreach ($all_assignments as $asn) {
        if ($asn['submissionId'] === null) {
            $pending_tasks[] = $asn;
        } else {
            $completed_tasks[] = $asn;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Homework - SiS4 SCHOOL</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 h-screen overflow-hidden flex">

    <!-- ==================== SIDEBAR (สำหรับนักเรียน) ==================== -->
    <aside class="w-64 bg-gray-900 text-gray-300 flex flex-col h-full flex-shrink-0">
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white mr-3">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <h1 class="text-white font-bold text-sm leading-tight">SiS4 SCHOOL</h1>
                <p class="text-xs text-gray-400">ระบบจัดการนักเรียน</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto no-scrollbar px-3 py-4 space-y-2 text-sm">
            <a href="home.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded transition-colors">
                <i class="fa-solid fa-border-all w-5 text-center"></i>
                ภาพรวม
            </a>
            <a href="schedule.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded transition-colors">
                <i class="fa-solid fa-calendar-days w-5 text-center"></i>
                ตารางเรียน
            </a>
            <!-- Active Menu -->
            <a href="homework.php" class="flex items-center gap-3 px-3 py-2.5 bg-blue-600 text-white rounded shadow-sm">
                <i class="fa-solid fa-book-open w-5 text-center"></i>
                การบ้านและชิ้นงาน
            </a>
            <a href="grade.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-800 hover:text-white rounded transition-colors">
                <i class="fa-solid fa-chart-line w-5 text-center"></i>
                ผลการเรียน
            </a>
        </nav>

        <div class="p-4 border-t border-gray-800 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-blue-600 flex items-center justify-center text-white font-bold text-sm">
                <?= htmlspecialchars($initial) ?>
            </div>
            <div class="flex-1 overflow-hidden">
                <p class="text-sm text-white font-medium truncate"><?= htmlspecialchars($studentName) ?></p>
                <p class="text-xs text-gray-400 truncate">นักเรียนชั้น ม.6</p>
            </div>
            <a href="../logout.php" class="text-gray-400 hover:text-red-400 transition-colors" title="ออกจากระบบ">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </aside>

    <!-- ==================== MAIN CONTENT ==================== -->
    <div class="flex-1 flex flex-col min-w-0">
        
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <div class="text-lg font-medium text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-book-open text-blue-600"></i>
                การบ้านและชิ้นงานของฉัน
            </div>
            <div class="flex items-center gap-4">
                <button class="relative text-gray-500 hover:text-gray-700">
                    <i class="fa-solid fa-bell text-lg"></i>
                    <?php if (count($pending_tasks) > 0): ?>
                        <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-white"></span>
                    <?php endif; ?>
                </button>
                <div class="h-6 w-px bg-gray-300"></div>
                <div class="text-sm font-medium text-gray-700">
                    <?= htmlspecialchars($studentName) ?>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- สถิติแบบเร็ว -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">งานทั้งหมดในระบบ</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($all_assignments ?? []); ?></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-50 rounded flex items-center justify-center text-blue-600">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">งานที่ต้องส่ง (ค้างส่ง)</p>
                        <p class="text-2xl font-bold text-orange-600"><?php echo count($pending_tasks); ?></p>
                    </div>
                    <div class="w-10 h-10 bg-orange-50 rounded flex items-center justify-center text-orange-600">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>
                <div class="bg-white p-4 rounded border border-gray-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 font-medium mb-1">งานที่ส่งแล้ว</p>
                        <p class="text-2xl font-bold text-emerald-600"><?php echo count($completed_tasks); ?></p>
                    </div>
                    <div class="w-10 h-10 bg-emerald-50 rounded flex items-center justify-center text-emerald-600">
                        <i class="fa-solid fa-check-double"></i>
                    </div>
                </div>
            </div>

            <!-- ส่วนงานที่ต้องทำ (Pending Tasks) -->
            <div class="bg-white border border-gray-200 rounded shadow-sm">
                <div class="px-5 py-4 border-b border-gray-200 bg-gray-50/50 flex justify-between items-center">
                    <h3 class="font-bold text-gray-900 text-base flex items-center gap-2">
                        <i class="fa-solid fa-clipboard-list text-orange-500"></i> งานที่ต้องทำ (<?php echo count($pending_tasks); ?>)
                    </h3>
                </div>
                <div class="p-5">
                    <?php if (count($pending_tasks) > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($pending_tasks as $task): ?>
                                <?php 
                                    // เช็คว่าเลยกำหนดส่งหรือยัง
                                    $is_overdue = false;
                                    if ($task['dueDate'] && strtotime($task['dueDate']) < time()) {
                                        $is_overdue = true;
                                    }
                                ?>
                                <div class="border <?php echo $is_overdue ? 'border-red-300 bg-red-50/30' : 'border-gray-200'; ?> rounded p-4 flex flex-col hover:shadow-md transition-shadow">
                                    <div class="mb-2 flex justify-between items-start gap-2">
                                        <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase"><?php echo htmlspecialchars($task['courseCode']); ?></span>
                                        <?php if ($is_overdue): ?>
                                            <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-[10px] font-bold">เลยกำหนดส่ง</span>
                                        <?php endif; ?>
                                    </div>
                                    <h4 class="font-bold text-gray-900 text-sm mb-1"><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <p class="text-xs text-gray-500 mb-3 flex-1 line-clamp-2"><?php echo htmlspecialchars($task['description']); ?></p>
                                    
                                    <div class="text-xs text-gray-600 bg-white border <?php echo $is_overdue ? 'border-red-100' : 'border-gray-100'; ?> rounded p-2 mb-3">
                                        <div class="flex justify-between mb-1">
                                            <span>คะแนนเต็ม:</span>
                                            <span class="font-bold text-blue-600"><?php echo $task['maxScore']; ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>กำหนดส่ง:</span>
                                            <span class="font-medium <?php echo $is_overdue ? 'text-red-600' : 'text-gray-800'; ?>">
                                                <?php echo $task['dueDate'] ? date('d/m/Y H:i', strtotime($task['dueDate'])) : 'ไม่มีกำหนด'; ?>
                                            </span>
                                        </div>
                                    </div>

                                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded py-2 text-sm font-medium transition-colors">
                                        <i class="fa-solid fa-file-arrow-up me-1"></i> ส่งงาน
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fa-solid fa-clipboard-check text-4xl text-emerald-400 mb-3"></i>
                            <p>ยอดเยี่ยม! คุณจัดการงานค้างส่งเสร็จหมดแล้ว</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ส่วนประวัติการส่งงาน (Completed Tasks) -->
            <div class="bg-white border border-gray-200 rounded shadow-sm">
                <div class="px-5 py-4 border-b border-gray-200 bg-gray-50/50">
                    <h3 class="font-bold text-gray-900 text-base flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-emerald-600"></i> ประวัติการส่งงาน
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-white text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-3 font-medium">วิชา</th>
                                <th class="px-5 py-3 font-medium">ชื่องาน</th>
                                <th class="px-5 py-3 font-medium text-center">เวลาที่ส่ง</th>
                                <th class="px-5 py-3 font-medium text-center">คะแนนที่ได้</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (count($completed_tasks) > 0): ?>
                                <?php foreach ($completed_tasks as $task): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-5 py-4">
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($task['courseName']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($task['courseCode']); ?></p>
                                        </td>
                                        <td class="px-5 py-4">
                                            <p class="text-gray-900"><?php echo htmlspecialchars($task['title']); ?></p>
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <p class="text-xs text-gray-600"><?php echo date('d/m/Y', strtotime($task['submittedAt'])); ?></p>
                                            <p class="text-[10px] text-gray-400"><?php echo date('H:i', strtotime($task['submittedAt'])); ?> น.</p>
                                        </td>
                                        <td class="px-5 py-4 text-center">
                                            <?php if ($task['score'] !== null): ?>
                                                <span class="inline-flex items-center justify-center bg-emerald-100 text-emerald-800 font-bold px-3 py-1 rounded text-sm border border-emerald-200">
                                                    <?php echo $task['score']; ?> / <?php echo $task['maxScore']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center justify-center bg-gray-100 text-gray-600 font-medium px-2 py-1 rounded text-xs border border-gray-200">
                                                    รอตรวจ
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-8 text-gray-500">
                                        ยังไม่มีประวัติการส่งงาน
                                    </td>
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