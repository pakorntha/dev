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
    $asnId = 'ASN_' . uniqid(); // เจน ID ภาษาอังกฤษเพื่อไม่ให้ซ้ำ

    dd_q("INSERT INTO assignment (id, title, description, maxScore, dueDate, courseId, updatedAt) VALUES (?, ?, ?, ?, ?, ?, NOW(3))", 
        [$asnId, $title, $description, $maxScore, $dueDate, $courseId]);

    header("Location: homework.php?course_id=" . urlencode($courseId) . "&msg=created");
    exit();
}

// 2.2 บันทึกคะแนนนักเรียน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_scores') {
    $assignmentId = $_POST['assignment_id'];
    $classroomId = $_POST['classroom_id'];
    $scores = $_POST['scores'] ?? []; // รูปแบบ [studentProfileId => score_value]

    foreach ($scores as $studentProfileId => $scoreVal) {
        if ($scoreVal === '' || $scoreVal === null) continue;
        
        $score = floatval($scoreVal);

        // เช็คว่าเคยมี submission แล้วหรือยัง
        $chk = dd_q("SELECT id FROM submission WHERE assignmentId = ? AND studentId = ?", [$assignmentId, $studentProfileId]);
        
        if ($chk->rowCount() > 0) {
            $sub = $chk->fetch(PDO::FETCH_ASSOC);
            dd_q("UPDATE submission SET score = ?, updatedAt = NOW(3) WHERE id = ?", [$score, $sub['id']]);
        } else {
            $subId = 'SUB_' . uniqid();
            dd_q("INSERT INTO submission (id, assignmentId, studentId, score, updatedAt) VALUES (?, ?, ?, ?, NOW(3))", 
                [$subId, $assignmentId, $studentProfileId, $score]);
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
    <title>ระบบสั่งงานและตรวจการบ้าน - สำหรับครู</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .btn-custom { border-radius: 8px; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="homework.php">
            <i class="fas fa-chalkboard-teacher me-2"></i>ระบบจัดการการบ้าน (ครู<?php echo htmlspecialchars($teacherName); ?>)
        </a>
        <div class="ms-auto">
            <a href="../../logout.php" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i>ออกจากระบบ
            </a>
        </div>
    </div>
</nav>

<div class="container pb-5">

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'created'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-1"></i> สั่งงานสำเร็จเรียบร้อยแล้ว!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'saved'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-1"></i> บันทึกคะแนนเรียบร้อยแล้ว!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ================================================================= -->
    <!-- VIEW 3: หน้าตรวจ/กรอกคะแนนนักเรียนแต่ละคน ในงานและห้องเรียนที่เลือก -->
    <!-- ================================================================= -->
    <?php if ($get_assignment_id && $get_classroom_id): ?>
        <?php
            // ดึงข้อมูลงาน
            $stmt_asn = dd_q("SELECT a.*, c.name AS courseName, c.code AS courseCode FROM assignment a JOIN course c ON a.courseId = c.id WHERE a.id = ?", [$get_assignment_id]);
            $asnInfo = $stmt_asn->fetch(PDO::FETCH_ASSOC);

            // ดึงข้อมูลห้องเรียน
            $stmt_cls = dd_q("SELECT * FROM classroom WHERE id = ?", [$get_classroom_id]);
            $clsInfo = $stmt_cls->fetch(PDO::FETCH_ASSOC);

            // ดึงรายชื่อนักเรียนในห้องนี้ + คะแนนที่เคยบันทึกไว้
            $stmt_students = dd_q("
                SELECT sp.id AS studentProfileId, u.username, u.prefix, u.firstName, u.lastName, sub.score, sub.submittedAt
                FROM studentprofile sp
                INNER JOIN user u ON sp.userId = u.id
                LEFT JOIN submission sub ON sub.studentId = sp.id AND sub.assignmentId = ?
                WHERE sp.classroomId = ?
                ORDER BY u.firstName ASC
            ", [$get_assignment_id, $get_classroom_id]);
            $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="homework.php">รายวิชาทั้งหมด</a></li>
                <li class="breadcrumb-item"><a href="homework.php?course_id=<?php echo urlencode($asnInfo['courseId']); ?>">วิชา <?php echo htmlspecialchars($asnInfo['courseName']); ?></a></li>
                <li class="breadcrumb-item active">ให้คะแนนห้อง <?php echo htmlspecialchars($clsInfo['name']); ?></li>
            </ol>
        </nav>

        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="fw-bold mb-1"><i class="fas fa-pen-square text-primary me-2"></i>ตรวจงาน: <?php echo htmlspecialchars($asnInfo['title']); ?></h4>
                    <span class="badge bg-secondary">ห้องเรียน: <?php echo htmlspecialchars($clsInfo['name']); ?></span>
                    <span class="badge bg-info text-dark">คะแนนเต็ม: <?php echo $asnInfo['maxScore']; ?> คะแนน</span>
                </div>
                <a href="homework.php?course_id=<?php echo urlencode($asnInfo['courseId']); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>ย้อนกลับ
                </a>
            </div>

            <form action="homework.php" method="POST">
                <input type="hidden" name="action" value="save_scores">
                <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($get_assignment_id); ?>">
                <input type="hidden" name="classroom_id" value="<?php echo htmlspecialchars($get_classroom_id); ?>">

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>รหัสนักเรียน (Username)</th>
                                <th>ชื่อ - นามสกุล</th>
                                <th style="width: 200px;">คะแนนที่ได้ (เต็ม <?php echo $asnInfo['maxScore']; ?>)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $index => $std): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><code><?php echo htmlspecialchars($std['username']); ?></code></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars(trim(($std['prefix'] ?? '') . ' ' . $std['firstName'] . ' ' . $std['lastName'])); ?></td>
                                        <td>
                                            <input type="number" step="0.5" max="<?php echo $asnInfo['maxScore']; ?>" min="0" 
                                                   name="scores[<?php echo $std['studentProfileId']; ?>]" 
                                                   value="<?php echo $std['score'] !== null ? htmlspecialchars($std['score']) : ''; ?>" 
                                                   class="form-control form-control-sm text-center" placeholder="กรอกคะแนน">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">ไม่มีนักเรียนในห้องเรียนนี้</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (count($students) > 0): ?>
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-success px-4 btn-custom"><i class="fas fa-save me-1"></i>บันทึกคะแนนทั้งหมด</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

    <!-- ================================================================= -->
    <!-- VIEW 2: หน้ารายละเอียดวิชา (สั่งงาน + รายชื่อห้องเรียน + งานย่อย) -->
    <!-- ================================================================= -->
    <?php elseif ($get_course_id): ?>
        <?php
            // ดึงข้อมูลวิชา
            $stmt_course = dd_q("SELECT * FROM course WHERE id = ? AND teacherId = ? LIMIT 1", [$get_course_id, $_SESSION['id']]);
            if ($stmt_course->rowCount() === 0) {
                echo "<script>window.location.href='homework.php';</script>";
                exit();
            }
            $course = $stmt_course->fetch(PDO::FETCH_ASSOC);

            // ดึงห้องเรียนที่เรียนวิชานี้
            $stmt_cls = dd_q("
                SELECT c.* FROM classroom c
                INNER JOIN courseclassroom cc ON c.id = cc.classroomId
                WHERE cc.courseId = ?
            ", [$get_course_id]);
            $classrooms = $stmt_cls->fetchAll(PDO::FETCH_ASSOC);

            // ดึงการบ้านทั้งหมดในวิชานี้
            $stmt_asn = dd_q("SELECT * FROM assignment WHERE courseId = ? ORDER BY createdAt DESC", [$get_course_id]);
            $assignments = $stmt_asn->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="homework.php">รายวิชาทั้งหมด</a></li>
                <li class="breadcrumb-item active">วิชา <?php echo htmlspecialchars($course['name']); ?></li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($course['name']); ?> (<?php echo htmlspecialchars($course['code']); ?>)</h3>
                <small class="text-muted">การจัดการการบ้านและการสั่งงาน</small>
            </div>
            <!-- ปุ่มเปิด Modal สั่งงาน -->
            <button class="btn btn-primary btn-custom" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                <i class="fas fa-plus-circle me-1"></i>สั่งการบ้านใหม่
            </button>
        </div>

        <div class="row">
            <!-- ซ้าย: รายการการบ้านที่สั่งแล้ว -->
            <div class="col-md-8 mb-4">
                <div class="card p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-tasks text-primary me-2"></i>รายการงานที่มอบหมาย</h5>
                    <?php if (count($assignments) > 0): ?>
                        <div class="accordion" id="accordionAssignment">
                            <?php foreach ($assignments as $idx => $asn): ?>
                                <div class="accordion-item mb-2 border rounded">
                                    <h2 class="accordion-header" id="heading<?php echo $idx; ?>">
                                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $idx; ?>">
                                            <div class="d-flex justify-content-between w-100 me-3">
                                                <span><i class="fas fa-file-alt text-secondary me-2"></i><?php echo htmlspecialchars($asn['title']); ?></span>
                                                <span class="badge bg-primary align-self-center">คะแนนเต็ม <?php echo $asn['maxScore']; ?></span>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $idx; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionAssignment">
                                        <div class="accordion-body bg-light">
                                            <p class="text-muted small mb-2"><?php echo nl2br(htmlspecialchars($asn['description'] ?? 'ไม่มีรายละเอียดเพิ่มเติม')); ?></p>
                                            <p class="small text-danger mb-3"><i class="fas fa-clock me-1"></i>กำหนดส่ง: <?php echo $asn['dueDate'] ? date('d/m/Y H:i', strtotime($asn['dueDate'])) : 'ไม่ระบุ'; ?></p>
                                            
                                            <h6 class="fw-bold text-dark mb-2">เลือกห้องเรียนเพื่อกรอกคะแนน:</h6>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($classrooms as $cls): ?>
                                                    <a href="homework.php?assignment_id=<?php echo urlencode($asn['id']); ?>&classroom_id=<?php echo urlencode($cls['id']); ?>" 
                                                       class="btn btn-outline-dark btn-sm rounded-pill">
                                                        <i class="fas fa-users me-1"></i>ตรวจห้อง <?php echo htmlspecialchars($cls['name']); ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">ยังไม่มีการสั่งการบ้านในรายวิชานี้</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ขวา: รายชื่อห้องเรียนที่เรียนวิชานี้ -->
            <div class="col-md-4 mb-4">
                <div class="card p-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-door-open text-warning me-2"></i>ห้องเรียนที่รับผิดชอบ</h5>
                    <ul class="list-group list-group-flush">
                        <?php if (count($classrooms) > 0): ?>
                            <?php foreach ($classrooms as $cls): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-users text-secondary me-2"></i>ห้อง <?php echo htmlspecialchars($cls['name']); ?></span>
                                    <span class="badge bg-secondary rounded-pill"> active </span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-muted">ยังไม่ได้จัดห้องเรียนให้วิชานี้</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Modal สั่งงานใหม่ -->
        <div class="modal fade" id="createAssignmentModal" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" action="homework.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-1"></i>มอบหมายงานใหม่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_assignment">
                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($get_course_id); ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold">หัวข้องาน / ชื่องาน</label>
                            <input type="text" name="title" class="form-control" placeholder="เช่น การบ้านครั้งที่ 1 การเขียนโปรแกรม" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">คำอธิบายรายละเอียดงาน</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="ระบุรายละเอียดงานหรือคำสั่ง..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">คะแนนเต็ม</label>
                                <input type="number" name="maxScore" class="form-control" value="10" step="0.5" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">กำหนดส่ง (Due Date)</label>
                                <input type="datetime-local" name="dueDate" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>บันทึกการสั่งงาน</button>
                    </div>
                </form>
            </div>
        </div>

    <!-- ================================================================= -->
    <!-- VIEW 1: หน้าหลักครู - แสดงรายวิชาทั้งหมดที่สอน -->
    <!-- ================================================================= -->
    <?php else: ?>
        <?php
            // ดึงวิชาทั้งหมดที่ครูคนนี้สอน
            $stmt_my_courses = dd_q("SELECT * FROM course WHERE teacherId = ?", [$_SESSION['id']]);
            $my_courses = $stmt_my_courses->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0"><i class="fas fa-book-open text-primary me-2"></i>รายวิชาที่รับผิดชอบสอน</h3>
                <p class="text-muted mb-0">เลือกวิชาเพื่อจัดการการบ้านและกรอกคะแนนให้นักเรียน</p>
            </div>
        </div>

        <div class="row">
            <?php if (count($my_courses) > 0): ?>
                <?php foreach ($my_courses as $c): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 p-3">
                            <div class="card-body d-flex flex-column">
                                <span class="badge bg-primary w-auto align-self-start mb-2"><?php echo htmlspecialchars($c['code']); ?></span>
                                <h5 class="card-title fw-bold text-dark"><?php echo htmlspecialchars($c['name']); ?></h5>
                                <p class="card-text text-muted small mt-auto pt-3">
                                    <i class="fas fa-chalkboard me-1"></i>วิชาประจำภาคเรียน
                                </p>
                                <a href="homework.php?course_id=<?php echo urlencode($c['id']); ?>" class="btn btn-outline-primary btn-custom w-100 mt-2">
                                    เข้าจัดการการบ้าน <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card p-5 text-center text-muted">
                        <i class="fas fa-folder-open fa-3x mb-3 text-secondary"></i>
                        <h5>ไม่พบรายวิชาที่คุณเป็นผู้สอน</h5>
                        <p class="small mb-0">โปรดติดต่อผู้ดูแลระบบ (Admin) เพื่อมอบหมายวิชาเรียน</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>