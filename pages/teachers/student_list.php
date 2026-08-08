<?php
session_start();
require_once("../../system/a_func.php");

// 1. ตรวจสอบการล็อกอิน
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// 2. ดึงข้อมูลห้องเรียน/ระดับชั้น ทั้งหมดสำหรับทำ Dropdown / ตัวเลือกห้อง
$stmt_classrooms = dd_q("SELECT * FROM classroom ORDER BY name ASC");
$classrooms = $stmt_classrooms->fetchAll(PDO::FETCH_ASSOC);

// 3. รับค่าห้องเรียนที่เลือกจาก GET (ถ้าไม่มี ให้เลือกห้องแรกเป็นค่าเริ่มต้น)
$selected_classroom_id = $_GET['classroom_id'] ?? ($classrooms[0]['id'] ?? '');

// ดึงข้อมูลห้องเรียนที่กำลังเลือกอยู่
$current_classroom_name = "ไม่พบข้อมูลห้องเรียน";
foreach ($classrooms as $cls) {
    if ($cls['id'] === $selected_classroom_id) {
        $current_classroom_name = $cls['name'];
        break;
    }
}

// 4. ดึงรายชื่อนักเรียนในห้องเรียนที่เลือก
$students = [];
if (!empty($selected_classroom_id)) {
    $stmt_students = dd_q("
        SELECT u.username, u.prefix, u.firstName, u.lastName, sp.grade
        FROM studentprofile sp
        INNER JOIN user u ON sp.userId = u.id
        WHERE sp.classroomId = ? AND u.role = 'student'
        ORDER BY u.firstName ASC
    ", [$selected_classroom_id]);
    
    $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายชื่อนักเรียนตามระดับชั้น</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<div class="container py-4">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fas fa-users text-primary me-2"></i>ข้อมูลนักเรียนตามระดับชั้น</h3>
            <p class="text-muted mb-0">เลือกระดับชั้น/ห้องเรียน เพื่อดูรายชื่อนักเรียนทั้งหมด</p>
        </div>
    </div>

    <div class="row">
        <!-- ตัวเลือกห้องเรียน (Dropdown & List) -->
        <div class="col-md-4 mb-4">
            <div class="card p-3 mb-3">
                <label for="selectClassroom" class="form-label fw-bold"><i class="fas fa-filter me-1"></i> เลือกระดับชั้น / ห้องเรียน</label>
                <form action="" method="GET">
                    <select id="selectClassroom" name="classroom_id" class="form-select form-select-lg mb-3" onchange="this.form.submit()">
                        <?php if (count($classrooms) > 0): ?>
                            <?php foreach ($classrooms as $cls): ?>
                                <option value="<?php echo htmlspecialchars($cls['id']); ?>" <?php echo ($cls['id'] === $selected_classroom_id) ? 'selected' : ''; ?>>
                                    ห้อง <?php echo htmlspecialchars($cls['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">ยังไม่มีข้อมูลห้องเรียน</option>
                        <?php endif; ?>
                    </select>
                </form>
            </div>

            <!-- เมนูเลือกแบบ Quick List -->
            <div class="card p-3">
                <h6 class="fw-bold text-muted mb-2">ระดับชั้นทั้งหมด</h6>
                <div class="list-group list-group-flush">
                    <?php foreach ($classrooms as $cls): ?>
                        <a href="?classroom_id=<?php echo urlencode($cls['id']); ?>" 
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo ($cls['id'] === $selected_classroom_id) ? 'active' : ''; ?>">
                            <span><i class="fas fa-door-open me-2"></i>ห้อง <?php echo htmlspecialchars($cls['name']); ?></span>
                            <i class="fas fa-chevron-right small"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ตารางแสดงรายชื่อนักเรียน -->
        <div class="col-md-8">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-graduation-cap text-success me-2"></i>
                        รายชื่อนักเรียน - ห้อง <?php echo htmlspecialchars($current_classroom_name); ?>
                    </h5>
                    <span class="badge bg-primary fs-6">
                        จำนวน <?php echo count($students); ?> คน
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>รหัสนักเรียน (Username)</th>
                                <th>ชื่อ - นามสกุล</th>
                                <th class="text-center">เกรดเฉลี่ย (GPA)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $index => $std): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><code><?php echo htmlspecialchars($std['username']); ?></code></td>
                                        <td class="fw-bold">
                                            <?php echo htmlspecialchars(trim(($std['prefix'] ?? '') . ' ' . $std['firstName'] . ' ' . $std['lastName'])); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success">
                                                <?php echo htmlspecialchars($std['grade'] ?? '0.00'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fas fa-user-slash fa-2x mb-2 d-block"></i>
                                        ไม่พบข้อมูลนักเรียนในห้องเรียนนี้
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>