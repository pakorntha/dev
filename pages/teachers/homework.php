<?php
session_start();
require_once("../../system/a_func.php");

// 1. ตรวจสอบสิทธิ์ผู้ใช้งาน (ต้องเป็นอาจารย์)
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

$stmt_user = dd_q("SELECT * FROM user WHERE id = ? AND role = 'teacher' LIMIT 1", [$_SESSION['id']]);
if ($stmt_user->rowCount() === 0) {
    header("Location: ../login.php");
    exit();
}
$teacher = $stmt_user->fetch(PDO::FETCH_ASSOC);
$teacherName = trim(($teacher['prefix'] ?? '') . ' ' . $teacher['firstName'] . ' ' . $teacher['lastName']);
$initial = mb_substr($teacher['firstName'], 0, 1, 'UTF-8');

// -------------------------------------------------------------------
// 2. จัดการ Action (POST Request)
// -------------------------------------------------------------------

// 2.1 สร้างการบ้านใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_assignment') {
    $courseId = $_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $maxScore = floatval($_POST['maxScore'] ?? 10);
    $dueDate = !empty($_POST['dueDate']) ? $_POST['dueDate'] : NULL;
    $asnId = 'ASN_' . uniqid();

    dd_q("INSERT INTO assignment (id, title, description, maxScore, dueDate, courseId, updatedAt) VALUES (?, ?, ?, ?, ?, ?, NOW(3))", 
        [$asnId, $title, $description, $maxScore, $dueDate, $courseId]);

    header("Location: homework.php?course_id=" . urlencode($courseId) . "&msg=created");
    exit();
}

// 2.2 แก้ไขงานที่มอบหมาย
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_assignment') {
    $assignmentId = $_POST['assignment_id'];
    $courseId = $_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $maxScore = floatval($_POST['maxScore'] ?? 10);
    $dueDate = !empty($_POST['dueDate']) ? $_POST['dueDate'] : NULL;

    dd_q("UPDATE assignment SET title = ?, description = ?, maxScore = ?, dueDate = ?, updatedAt = NOW(3) WHERE id = ?", 
        [$title, $description, $maxScore, $dueDate, $assignmentId]);

    header("Location: homework.php?course_id=" . urlencode($courseId) . "&msg=edited");
    exit();
}

// 2.3 ลบงานที่มอบหมาย
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_assignment') {
    $assignmentId = $_POST['assignment_id'];
    $courseId = $_POST['course_id'];

    // ลบข้อมูลการส่งงานของนักเรียนที่เกี่ยวข้องออกก่อน
    dd_q("DELETE FROM submission WHERE assignmentId = ?", [$assignmentId]);
    // ลบงานที่มอบหมาย
    dd_q("DELETE FROM assignment WHERE id = ?", [$assignmentId]);

    header("Location: homework.php?course_id=" . urlencode($courseId) . "&msg=deleted");
    exit();
}

// 2.4 บันทึกคะแนนนักเรียน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_scores') {
    $assignmentId = $_POST['assignment_id'];
    $classroomId = $_POST['classroom_id'];
    $scores = $_POST['scores'] ?? [];
    $feedbacks = $_POST['feedbacks'] ?? [];

    foreach ($scores as $studentProfileId => $scoreVal) {
        $feedback = trim($feedbacks[$studentProfileId] ?? '');
        if ($scoreVal === '' || $scoreVal === null) {
            if ($feedback === '') {
                continue;
            }
            $score = null;
        } else {
            $score = floatval($scoreVal);
        }
        $chk = dd_q("SELECT id FROM submission WHERE assignmentId = ? AND studentId = ?", [$assignmentId, $studentProfileId]);
        
        if ($chk->rowCount() > 0) {
            $sub = $chk->fetch(PDO::FETCH_ASSOC);
            dd_q("UPDATE submission SET score = ?, feedback = ?, status = 'reviewed', reviewedAt = NOW(3), updatedAt = NOW(3) WHERE id = ?", [$score, $feedback !== '' ? $feedback : null, $sub['id']]);
        } else {
            $subId = 'SUB_' . uniqid();
            dd_q("INSERT INTO submission (id, assignmentId, studentId, score, feedback, status, reviewedAt, updatedAt) VALUES (?, ?, ?, ?, ?, 'reviewed', NOW(3), NOW(3))", 
                [$subId, $assignmentId, $studentProfileId, $score, $feedback !== '' ? $feedback : null]);
        }
    }

    header("Location: homework.php?assignment_id=" . urlencode($assignmentId) . "&classroom_id=" . urlencode($classroomId) . "&msg=saved");
    exit();
}

// -------------------------------------------------------------------
// 3. จัดเตรียมข้อมูลสำหรับแสดงผล (GET Parameters)
// -------------------------------------------------------------------
$get_course_id = $_GET['course_id'] ?? null;
$get_assignment_id = $_GET['assignment_id'] ?? null;
$get_classroom_id = $_GET['classroom_id'] ?? null;

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - SiS4 SCHOOL</title>
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

    <!-- ==================== SIDEBAR ==================== -->
    <aside class="w-64 bg-gray-900 text-gray-300 flex flex-col h-full flex-shrink-0">
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <div class="w-8 h-8 bg-blue-600 rounded flex items-center justify-center text-white mr-3">
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
                    <i class="fa-solid fa-border-all w-5 text-center"></i>
                    แดชบอร์ด
                </a>
            </div>
            
            <div>
                <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานรายวิชาที่รับผิดชอบ</p>
                <ul class="space-y-1">
                    <li><a href="homework.php" class="flex items-center gap-3 px-3 py-2 bg-blue-600 text-white rounded transition-colors"><i class="fa-solid fa-file-pen w-5 text-center"></i> มอบหมายงาน / การบ้าน</a></li>
                </ul>
            </div>              
            
            <div>
                <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานสารบรรณ</p>
                <ul class="space-y-1">
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-inbox w-5 text-center"></i> หนังสือรับ</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-regular fa-note-sticky w-5 text-center"></i> บันทึกภายใน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-share-nodes w-5 text-center"></i> หนังสือเวียน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-list-check w-5 text-center"></i> งานที่มอบหมาย</a></li>
                </ul>
            </div>

            <div>
                <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานบุคคล</p>
                <ul class="space-y-1">
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-user-clock w-5 text-center"></i> ลงเวลาปฏิบัติงาน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-regular fa-calendar-minus w-5 text-center"></i> การลา</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-plane w-5 text-center"></i> ไปราชการและอบรม</a></li>
                </ul>
            </div>

            <div>
                <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานวิชาการ</p>
                <ul class="space-y-1">
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-users w-5 text-center"></i> นักเรียนและห้องเรียน</a></li>
                    <li><a href="atten.php" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-chalkboard-user w-5 text-center"></i> การมาเรียนนักเรียน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-book-open w-5 text-center"></i> แผนการสอน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-eye w-5 text-center"></i> นิเทศการสอน</a></li>
                    <li><a href="#" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i class="fa-solid fa-shield-halved w-5 text-center"></i> ประกันคุณภาพภายใน</a></li>
                </ul>
            </div>
        </nav>

        <div class="p-4 border-t border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded bg-blue-600 flex items-center justify-center text-white font-bold text-sm">
                    <?= htmlspecialchars($initial) ?>
                </div>
                <div class="flex-1 overflow-hidden">
                    <p class="text-sm text-white font-medium truncate"><?= htmlspecialchars($teacherName) ?></p>
                    <p class="text-xs text-gray-400 truncate">ครูผู้สอน</p>
                </div>
                <a href="../../logout.php" class="text-gray-400 hover:text-red-400 transition-colors" title="ออกจากระบบ">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- ==================== MAIN CONTENT ==================== -->
    <div class="flex-1 flex flex-col min-w-0">
        
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
            <div class="text-lg font-medium text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-file-pen text-blue-600"></i>
                ระบบจัดการการบ้านและคะแนน
            </div>
            <div class="flex items-center gap-4">
                <div class="text-sm font-medium text-gray-700">
                    <?= htmlspecialchars($teacherName) ?>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            
            <!-- แจ้งเตือน -->
            <?php if (isset($_GET['msg'])): ?>
                <?php if ($_GET['msg'] === 'created'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded flex items-center gap-3 mb-6">
                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                        <p class="text-sm font-medium">สั่งงานสำเร็จเรียบร้อยแล้ว!</p>
                    </div>
                <?php elseif ($_GET['msg'] === 'edited'): ?>
                    <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded flex items-center gap-3 mb-6">
                        <i class="fa-solid fa-pen text-blue-600"></i>
                        <p class="text-sm font-medium">แก้ไขงานสำเร็จเรียบร้อยแล้ว!</p>
                    </div>
                <?php elseif ($_GET['msg'] === 'deleted'): ?>
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded flex items-center gap-3 mb-6">
                        <i class="fa-solid fa-trash-can text-red-600"></i>
                        <p class="text-sm font-medium">ลบงานสำเร็จเรียบร้อยแล้ว!</p>
                    </div>
                <?php elseif ($_GET['msg'] === 'saved'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded flex items-center gap-3 mb-6">
                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                        <p class="text-sm font-medium">บันทึกคะแนนเรียบร้อยแล้ว!</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>


            <!-- ================================================================= -->
            <!-- VIEW 3: หน้าตรวจ/กรอกคะแนนนักเรียน -->
            <!-- ================================================================= -->
            <?php if ($get_assignment_id && $get_classroom_id): ?>
                <?php
                    $stmt_asn = dd_q("SELECT a.*, c.name AS courseName, c.code AS courseCode FROM assignment a JOIN course c ON a.courseId = c.id WHERE a.id = ?", [$get_assignment_id]);
                    $asnInfo = $stmt_asn->fetch(PDO::FETCH_ASSOC);

                    $stmt_cls = dd_q("SELECT * FROM classroom WHERE id = ?", [$get_classroom_id]);
                    $clsInfo = $stmt_cls->fetch(PDO::FETCH_ASSOC);

                    $stmt_students = dd_q("
                        SELECT sp.id AS studentProfileId, u.username, u.prefix, u.firstName, u.lastName, sub.score, sub.submittedAt, sub.status, sub.feedback, sub.filePath, sub.fileName
                        FROM studentprofile sp
                        INNER JOIN user u ON sp.userId = u.id
                        LEFT JOIN submission sub ON sub.studentId = sp.id AND sub.assignmentId = ?
                        WHERE sp.classroomId = ?
                        ORDER BY u.firstName ASC
                    ", [$get_assignment_id, $get_classroom_id]);
                    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <!-- Breadcrumb -->
                <nav class="text-sm font-medium text-gray-500 mb-4 flex items-center gap-2">
                    <a href="homework.php" class="hover:text-blue-600 transition-colors">รายวิชาทั้งหมด</a>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    <a href="homework.php?course_id=<?php echo urlencode($asnInfo['courseId']); ?>" class="hover:text-blue-600 transition-colors">วิชา <?php echo htmlspecialchars($asnInfo['courseName']); ?></a>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    <span class="text-gray-800">ให้คะแนนห้อง <?php echo htmlspecialchars($clsInfo['name']); ?></span>
                </nav>

                <div class="bg-white border border-gray-200 rounded shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-200 bg-gray-50/50 flex flex-wrap justify-between items-center gap-4">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base mb-1">
                                <i class="fa-solid fa-pen-to-square text-blue-600 me-2"></i>ตรวจงาน: <?php echo htmlspecialchars($asnInfo['title']); ?>
                            </h3>
                            <div class="flex items-center gap-2 text-xs">
                                <span class="bg-gray-200 text-gray-700 px-2 py-0.5 rounded">ห้องเรียน: <?php echo htmlspecialchars($clsInfo['name']); ?></span>
                                <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded">คะแนนเต็ม: <?php echo $asnInfo['maxScore']; ?></span>
                            </div>
                        </div>
                        <a href="homework.php?course_id=<?php echo urlencode($asnInfo['courseId']); ?>" class="text-sm text-gray-600 hover:text-gray-900 border border-gray-300 bg-white px-3 py-1.5 rounded transition-colors">
                            <i class="fa-solid fa-arrow-left me-1"></i> ย้อนกลับ
                        </a>
                    </div>

                    <form action="homework.php" method="POST" class="p-5">
                        <input type="hidden" name="action" value="save_scores">
                        <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($get_assignment_id); ?>">
                        <input type="hidden" name="classroom_id" value="<?php echo htmlspecialchars($get_classroom_id); ?>">

                        <div class="overflow-x-auto border border-gray-200 rounded">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50 text-gray-500 border-b border-gray-200">
                                    <tr>
                                        <th class="px-4 py-3 font-medium w-12 text-center">#</th>
                                        <th class="px-4 py-3 font-medium">รหัสนักเรียน</th>
                                        <th class="px-4 py-3 font-medium">ชื่อ-นามสกุล</th>
                                        <th class="px-4 py-3 font-medium text-center">คะแนนที่ได้ (เต็ม <?php echo $asnInfo['maxScore']; ?>)</th>
                                        <th class="px-4 py-3 font-medium">Feedback</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if (count($students) > 0): ?>
                                        <?php foreach ($students as $index => $std): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 text-center text-gray-500"><?php echo $index + 1; ?></td>
                                                <td class="px-4 py-3 text-gray-600 font-mono text-xs"><?php echo htmlspecialchars($std['username']); ?></td>
                                                <td class="px-4 py-3 text-gray-900 font-medium">
                                                    <?php echo htmlspecialchars(trim(($std['prefix'] ?? '') . ' ' . $std['firstName'] . ' ' . $std['lastName'])); ?>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <input type="number" step="0.5" max="<?php echo $asnInfo['maxScore']; ?>" min="0" 
                                                           name="scores[<?php echo $std['studentProfileId']; ?>]" 
                                                           value="<?php echo $std['score'] !== null ? htmlspecialchars($std['score']) : ''; ?>" 
                                                           class="w-24 border border-gray-300 rounded px-2 py-1 text-center text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="กรอก">
                                                </td>
                                                <td class="px-4 py-3 align-top">
                                                    <textarea name="feedbacks[<?php echo $std['studentProfileId']; ?>]" rows="2" class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="เขียน feedback..."><?php echo htmlspecialchars($std['feedback'] ?? ''); ?></textarea>
                                                    <?php if (!empty($std['filePath'])): ?>
                                                        <div class="mt-2 text-xs text-blue-600">
                                                            <i class="fa-solid fa-file-pdf me-1"></i>
                                                            <a href="../../<?php echo htmlspecialchars($std['filePath']); ?>" target="_blank" class="hover:text-blue-800">ไฟล์ส่ง: <?php echo htmlspecialchars($std['fileName'] ?? 'PDF'); ?></a>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-gray-400 py-6">ไม่มีนักเรียนในห้องเรียนนี้</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (count($students) > 0): ?>
                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded text-sm font-medium transition-colors">
                                    <i class="fa-solid fa-save me-1"></i> บันทึกคะแนนทั้งหมด
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>


            <!-- ================================================================= -->
            <!-- VIEW 2: หน้ารายละเอียดวิชา (สั่งงาน + รายการงาน + รายชื่อห้องเรียน) -->
            <!-- ================================================================= -->
            <?php elseif ($get_course_id): ?>
                <?php
                    $stmt_course = dd_q("SELECT * FROM course WHERE id = ? AND teacherId = ? LIMIT 1", [$get_course_id, $_SESSION['id']]);
                    if ($stmt_course->rowCount() === 0) {
                        echo "<script>window.location.href='homework.php';</script>";
                        exit();
                    }
                    $course = $stmt_course->fetch(PDO::FETCH_ASSOC);

                    $stmt_cls = dd_q("
                        SELECT c.* FROM classroom c
                        INNER JOIN courseclassroom cc ON c.id = cc.classroomId
                        WHERE cc.courseId = ?
                    ", [$get_course_id]);
                    $classrooms = $stmt_cls->fetchAll(PDO::FETCH_ASSOC);

                    $stmt_asn = dd_q("SELECT * FROM assignment WHERE courseId = ? ORDER BY createdAt DESC", [$get_course_id]);
                    $assignments = $stmt_asn->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <!-- Breadcrumb -->
                <nav class="text-sm font-medium text-gray-500 mb-4 flex items-center gap-2">
                    <a href="homework.php" class="hover:text-blue-600 transition-colors">รายวิชาทั้งหมด</a>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    <span class="text-gray-800">วิชา <?php echo htmlspecialchars($course['name']); ?></span>
                </nav>

                <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($course['name']); ?> <span class="text-gray-500 text-base font-medium">(<?php echo htmlspecialchars($course['code']); ?>)</span></h2>
                        <p class="text-sm text-gray-500 mt-1">การจัดการการบ้านและการสั่งงาน</p>
                    </div>
                    <button onclick="openModal('createAssignmentModal')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-plus-circle"></i> สั่งการบ้านใหม่
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- ซ้าย: รายการการบ้านที่สั่งแล้ว -->
                    <div class="lg:col-span-2 space-y-4">
                        <div class="bg-white border border-gray-200 rounded shadow-sm">
                            <div class="px-5 py-4 border-b border-gray-200 bg-gray-50/50">
                                <h3 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                                    <i class="fa-solid fa-tasks text-blue-600"></i> รายการงานที่มอบหมาย
                                </h3>
                            </div>
                            <div class="p-5 space-y-4">
                                <?php if (count($assignments) > 0): ?>
                                    <?php foreach ($assignments as $idx => $asn): ?>
                                        <div class="border border-gray-200 rounded p-4 hover:border-blue-200 transition-colors group">
                                            <div class="flex flex-wrap justify-between items-start gap-2 mb-2">
                                                <h4 class="font-bold text-gray-900 text-base">
                                                    <i class="fa-regular fa-file-alt text-gray-400 me-2"></i><?php echo htmlspecialchars($asn['title']); ?>
                                                </h4>
                                                
                                                <div class="flex items-center gap-2">
                                                    <span class="bg-blue-50 text-blue-700 border border-blue-100 px-2 py-0.5 rounded text-xs font-medium">คะแนนเต็ม <?php echo $asn['maxScore']; ?></span>
                                                    
                                                    <!-- ปุ่มแก้ไข -->
                                                    <button onclick="openEditModal('<?php echo htmlspecialchars($asn['id']); ?>', '<?php echo htmlspecialchars(addslashes($asn['title'])); ?>', '<?php echo htmlspecialchars(addslashes($asn['description'])); ?>', '<?php echo $asn['maxScore']; ?>', '<?php echo $asn['dueDate'] ? date('Y-m-d\TH:i', strtotime($asn['dueDate'])) : ''; ?>')" class="text-amber-500 bg-white hover:bg-amber-50 border border-amber-200 px-2 py-1 rounded transition-colors text-xs" title="แก้ไขงาน">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    
                                                    <!-- ปุ่มลบ -->
                                                    <form action="homework.php" method="POST" class="inline" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบงานนี้?\n\n*ข้อควรระวัง: ข้อมูลการส่งงานและคะแนนของนักเรียนในงานชิ้นนี้จะถูกลบออกทั้งหมดด้วย');">
                                                        <input type="hidden" name="action" value="delete_assignment">
                                                        <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($asn['id']); ?>">
                                                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($get_course_id); ?>">
                                                        <button type="submit" class="text-red-500 bg-white hover:bg-red-50 border border-red-200 px-2 py-1 rounded transition-colors text-xs" title="ลบงาน">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <p class="text-sm text-gray-600 mb-3"><?php echo nl2br(htmlspecialchars($asn['description'] ?? 'ไม่มีรายละเอียดเพิ่มเติม')); ?></p>
                                            
                                            <p class="text-xs text-red-500 mb-4 font-medium">
                                                <i class="fa-regular fa-clock me-1"></i>กำหนดส่ง: <?php echo $asn['dueDate'] ? date('d/m/Y H:i', strtotime($asn['dueDate'])) : 'ไม่ระบุ'; ?>
                                            </p>
                                            
                                            <div class="bg-gray-50 border border-gray-200 rounded p-3">
                                                <p class="text-xs font-bold text-gray-700 mb-2">เลือกห้องเรียนเพื่อตรวจงาน / กรอกคะแนน:</p>
                                                <div class="flex flex-wrap gap-2">
                                                    <?php foreach ($classrooms as $cls): ?>
                                                        <a href="homework.php?assignment_id=<?php echo urlencode($asn['id']); ?>&classroom_id=<?php echo urlencode($cls['id']); ?>" 
                                                           class="border border-gray-300 bg-white hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 text-gray-700 text-xs px-3 py-1.5 rounded transition-colors flex items-center gap-1.5">
                                                            <i class="fa-solid fa-users"></i> ห้อง <?php echo htmlspecialchars($cls['name']); ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-8">
                                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 text-gray-400 text-xl">
                                            <i class="fa-solid fa-folder-open"></i>
                                        </div>
                                        <p class="text-gray-500 text-sm">ยังไม่มีการสั่งการบ้านในรายวิชานี้</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ขวา: รายชื่อห้องเรียน -->
                    <div class="lg:col-span-1">
                        <div class="bg-white border border-gray-200 rounded shadow-sm">
                            <div class="px-5 py-4 border-b border-gray-200 bg-gray-50/50">
                                <h3 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                                    <i class="fa-solid fa-door-open text-orange-500"></i> ห้องเรียนที่รับผิดชอบ
                                </h3>
                            </div>
                            <ul class="divide-y divide-gray-100 p-2">
                                <?php if (count($classrooms) > 0): ?>
                                    <?php foreach ($classrooms as $cls): ?>
                                        <li class="flex justify-between items-center px-4 py-3">
                                            <span class="text-sm font-medium text-gray-700"><i class="fa-solid fa-users text-gray-400 me-2"></i>ห้อง <?php echo htmlspecialchars($cls['name']); ?></span>
                                            <span class="bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">Active</span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="px-4 py-4 text-center text-sm text-gray-500">ยังไม่ได้จัดห้องเรียนให้วิชานี้</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Modal สั่งงานใหม่ -->
                <div id="createAssignmentModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center backdrop-blur-sm transition-opacity">
                    <div class="bg-white rounded-xl w-full max-w-lg mx-4 shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
                        <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                            <h3 class="font-bold text-gray-900 text-base"><i class="fa-solid fa-plus-circle text-blue-600 me-2"></i>มอบหมายงานใหม่</h3>
                            <button type="button" onclick="closeModal('createAssignmentModal')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fa-solid fa-xmark text-lg"></i></button>
                        </div>
                        
                        <form action="homework.php" method="POST" class="flex flex-col flex-1 overflow-hidden">
                            <input type="hidden" name="action" value="create_assignment">
                            <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($get_course_id); ?>">

                            <div class="p-5 overflow-y-auto space-y-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">หัวข้องาน / ชื่องาน <span class="text-red-500">*</span></label>
                                    <input type="text" name="title" required placeholder="เช่น การบ้านครั้งที่ 1 การเขียนโปรแกรม" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">คำอธิบายรายละเอียดงาน</label>
                                    <textarea name="description" rows="3" placeholder="ระบุรายละเอียดงานหรือคำสั่ง..." class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">คะแนนเต็ม <span class="text-red-500">*</span></label>
                                        <input type="number" name="maxScore" value="10" step="0.5" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">กำหนดส่ง (Due Date)</label>
                                        <input type="datetime-local" name="dueDate" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="px-5 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3 mt-auto">
                                <button type="button" onclick="closeModal('createAssignmentModal')" class="px-4 py-2 border border-gray-300 rounded text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors">ยกเลิก</button>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 transition-colors"><i class="fa-solid fa-save me-1"></i> บันทึกการสั่งงาน</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Modal แก้ไขงาน (Tailwind Version) -->
                <div id="editAssignmentModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center backdrop-blur-sm transition-opacity">
                    <div class="bg-white rounded-xl w-full max-w-lg mx-4 shadow-xl overflow-hidden flex flex-col max-h-[90vh]">
                        <div class="px-5 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                            <h3 class="font-bold text-gray-900 text-base"><i class="fa-solid fa-pen text-amber-500 me-2"></i>แก้ไขงานที่มอบหมาย</h3>
                            <button type="button" onclick="closeModal('editAssignmentModal')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fa-solid fa-xmark text-lg"></i></button>
                        </div>
                        
                        <form action="homework.php" method="POST" class="flex flex-col flex-1 overflow-hidden">
                            <input type="hidden" name="action" value="edit_assignment">
                            <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($get_course_id); ?>">
                            <!-- ซ่อน ID งานที่จะแก้ -->
                            <input type="hidden" name="assignment_id" id="edit_assignment_id" value="">

                            <div class="p-5 overflow-y-auto space-y-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">หัวข้องาน / ชื่องาน <span class="text-red-500">*</span></label>
                                    <input type="text" name="title" id="edit_title" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">คำอธิบายรายละเอียดงาน</label>
                                    <textarea name="description" id="edit_description" rows="3" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500"></textarea>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">คะแนนเต็ม <span class="text-red-500">*</span></label>
                                        <input type="number" name="maxScore" id="edit_maxScore" step="0.5" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-1">กำหนดส่ง (Due Date)</label>
                                        <input type="datetime-local" name="dueDate" id="edit_dueDate" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="px-5 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3 mt-auto">
                                <button type="button" onclick="closeModal('editAssignmentModal')" class="px-4 py-2 border border-gray-300 rounded text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors">ยกเลิก</button>
                                <button type="submit" class="px-4 py-2 bg-amber-500 text-white rounded text-sm font-medium hover:bg-amber-600 transition-colors"><i class="fa-solid fa-save me-1"></i> บันทึกการแก้ไข</button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function openModal(id) { 
                        document.getElementById(id).classList.remove('hidden'); 
                    }
                    function closeModal(id) { 
                        document.getElementById(id).classList.add('hidden'); 
                    }
                    function openEditModal(id, title, desc, maxScore, dueDate) {
                        document.getElementById('edit_assignment_id').value = id;
                        document.getElementById('edit_title').value = title;
                        document.getElementById('edit_description').value = desc;
                        document.getElementById('edit_maxScore').value = maxScore;
                        document.getElementById('edit_dueDate').value = dueDate;
                        openModal('editAssignmentModal');
                    }
                </script>

            <!-- ================================================================= -->
            <!-- VIEW 1: หน้าหลักครู - แสดงรายวิชาทั้งหมดที่สอน -->
            <!-- ================================================================= -->
            <?php else: ?>
                <?php
                    $stmt_my_courses = dd_q("SELECT * FROM course WHERE teacherId = ?", [$_SESSION['id']]);
                    $my_courses = $stmt_my_courses->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-book-open text-blue-600"></i> รายวิชาที่รับผิดชอบสอน
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">เลือกวิชาเพื่อจัดการการบ้านและกรอกคะแนนให้นักเรียน</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (count($my_courses) > 0): ?>
                        <?php foreach ($my_courses as $c): ?>
                            <div class="bg-white border border-gray-200 rounded shadow-sm hover:shadow-md transition-shadow flex flex-col p-5">
                                <div class="mb-3">
                                    <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase"><?php echo htmlspecialchars($c['code']); ?></span>
                                </div>
                                <h3 class="font-bold text-gray-900 text-lg mb-2"><?php echo htmlspecialchars($c['name']); ?></h3>
                                
                                <p class="text-xs text-gray-500 mt-auto pt-4 mb-4 flex items-center gap-1.5">
                                    <i class="fa-solid fa-chalkboard text-gray-400"></i> วิชาประจำภาคเรียน
                                </p>
                                
                                <a href="homework.php?course_id=<?php echo urlencode($c['id']); ?>" class="block w-full text-center border border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white rounded py-2 text-sm font-medium transition-colors">
                                    เข้าจัดการการบ้าน <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="lg:col-span-3">
                            <div class="bg-white border border-gray-200 rounded shadow-sm py-12 px-4 text-center flex flex-col items-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center text-gray-400 text-3xl mb-4">
                                    <i class="fa-solid fa-folder-open"></i>
                                </div>
                                <h3 class="text-lg font-bold text-gray-800 mb-1">ไม่พบรายวิชาที่คุณเป็นผู้สอน</h3>
                                <p class="text-sm text-gray-500">โปรดติดต่อผู้ดูแลระบบ (Admin) เพื่อมอบหมายวิชาเรียน</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </main>
    </div>

</body>
</html>