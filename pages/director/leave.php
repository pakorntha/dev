<?php
session_start();
// เรียกใช้ไฟล์ฟังก์ชันของระบบ
require_once("../../system/a_func.php");
require_once("../../system/director_sidebar.php");

// 1. ตรวจสอบว่ามีการล็อกอินหรือไม่
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// 2. ดึงข้อมูลผู้ใช้งาน (ตรวจสิทธิ์ Director)
$stmt = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
if ($stmt->rowCount() === 1) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user['role'] !== 'director') {
        header("Location: ../../index.php");
        exit();
    }
    $fullName = $user['prefix'] . $user['firstName'] . ' ' . $user['lastName'];
    $initial = mb_substr($user['firstName'], 0, 1, 'UTF-8');
} else {
    session_destroy();
    header("Location: ../../system/login.php");
    exit();
}

// ------------------------------------------------------------------
// ส่วนที่ 3: จัดการเมื่อมีการกดปุ่ม "อนุมัติ" หรือ "ไม่อนุมัติ"
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_id'], $_POST['action'])) {
    $leave_id = $_POST['leave_id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        dd_q("UPDATE leave_requests SET 
                directorStatus = 'อนุมัติแล้ว', 
                status = 'approved', 
                director = ?, 
                directorTime = NOW() 
              WHERE id = ?", [$fullName, $leave_id]);

        // ตั้งค่า Session เพื่อไปแสดง SweetAlert แจ้งเตือนสำเร็จ
        $_SESSION['swal_success'] = 'อนุมัติใบลาเรียบร้อยแล้ว';

    } elseif ($action === 'reject') {
        dd_q("UPDATE leave_requests SET 
                directorStatus = 'ไม่อนุมัติ', 
                status = 'rejected', 
                director = ?, 
                directorTime = NOW() 
              WHERE id = ?", [$fullName, $leave_id]);

        $_SESSION['swal_success'] = 'บันทึกการไม่อนุมัติเรียบร้อยแล้ว';
    }

    // รีเฟรชหน้าเพื่อป้องกันการส่งฟอร์มซ้ำ
    header("Location: leave.php");
    exit();
}

// ------------------------------------------------------------------
// ส่วนที่ 4: ดึงข้อมูลการลาทั้งหมด
// ------------------------------------------------------------------
$leaves = dd_q("
    SELECT * FROM leave_requests 
    ORDER BY 
        CASE WHEN directorStatus = 'รอการอนุมัติ' THEN 1 ELSE 2 END ASC,
        createdAt DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อนุมัติการลา - AI School e-Office</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .swal2-title,
        .swal2-html-container,
        .swal2-confirm,
        .swal2-cancel {
            font-family: 'Prompt', sans-serif !important;
        }
    </style>
</head>

<body class="bg-[#f8fafc] text-slate-800 h-screen overflow-hidden flex">

    <?php sis4_direcetor_sidebar_render($fullName, $initial); ?>


    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <!-- Top Header -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8 shrink-0">
            <h1 class="text-xl font-bold text-slate-800">ระบบพิจารณาอนุมัติ</h1>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-3">
                    <div
                        class="w-9 h-9 rounded-full bg-teal-500 text-white flex items-center justify-center font-semibold text-sm">
                        <?= htmlspecialchars($initial) ?>
                    </div>
                    <div class="text-xs">
                        <p class="font-bold text-slate-800"><?= htmlspecialchars($fullName) ?></p>
                        <p class="text-slate-500">ผู้อำนวยการ</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Scrollable Area -->
        <main class="flex-1 overflow-y-auto p-6 md:p-8">

            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800">รายการใบลาบุคลากร</h2>
                    <p class="text-sm text-slate-500 mt-1">ตรวจสอบและพิจารณาอนุมัติใบลาของครูและบุคลากร</p>
                </div>
                <a href="home.php"
                    class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-lg text-sm font-medium transition-colors">
                    <i class="fa-solid fa-arrow-left mr-2"></i> กลับหน้าหลัก
                </a>
            </div>

            <!-- Table Section -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs text-slate-500 bg-slate-50/50 uppercase border-b border-slate-100">
                            <tr>
                                <th class="px-5 py-4 font-semibold w-16 text-center">ลำดับ</th>
                                <th class="px-5 py-4 font-semibold">ข้อมูลผู้ลา / ประเภท</th>
                                <th class="px-5 py-4 font-semibold">รายละเอียดการลา</th>
                                <th class="px-5 py-4 font-semibold">ความเห็นหัวหน้าหมวด</th>
                                <th class="px-5 py-4 font-semibold text-center">สถานะ (ผอ.)</th>
                                <th class="px-5 py-4 font-semibold text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">

                            <?php if (empty($leaves)): ?>
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-500">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fa-regular fa-folder-open text-4xl text-slate-300 mb-3"></i>
                                            <p>ไม่มีประวัติการขออนุญาตลาในระบบ</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $i = 1;
                                foreach ($leaves as $leave): ?>
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <td class="px-5 py-4 text-center text-slate-500"><?= $i++ ?></td>

                                        <td class="px-5 py-4 align-top">
                                            <p class="font-bold text-slate-800"><?= htmlspecialchars($leave['teacherName']) ?>
                                            </p>
                                            <p
                                                class="text-xs text-indigo-600 font-semibold mt-1 bg-indigo-50 inline-block px-2 py-0.5 rounded">
                                                <?= htmlspecialchars($leave['leaveType']) ?>
                                            </p>
                                            <p class="text-xs text-slate-400 mt-2">
                                                <i class="fa-regular fa-clock"></i> ส่งเมื่อ:
                                                <?= date('d/m/Y H:i', strtotime($leave['createdAt'])) ?>
                                            </p>
                                        </td>

                                        <td class="px-5 py-4 align-top w-1/3">
                                            <p class="text-slate-800 font-medium">
                                                วันที่ <?= date('d/m/Y', strtotime($leave['startDate'])) ?>
                                                ถึง <?= date('d/m/Y', strtotime($leave['endDate'])) ?>
                                            </p>
                                            <p class="text-xs text-slate-500 mt-1">
                                                รวม <span
                                                    class="font-bold text-slate-700"><?= htmlspecialchars($leave['totalDays']) ?></span>
                                                วัน
                                            </p>
                                            <p class="text-xs text-slate-600 mt-2 line-clamp-2">
                                                <span class="font-semibold">เหตุผล:</span>
                                                <?= htmlspecialchars($leave['reason']) ?>
                                            </p>
                                            <?php if (!empty($leave['filePath'])): ?>
                                                <a href="../../<?= htmlspecialchars($leave['filePath']) ?>" target="_blank"
                                                    class="text-xs text-blue-500 hover:underline mt-2 inline-block">
                                                    <i class="fa-solid fa-paperclip"></i> ดูเอกสารแนบ
                                                </a>
                                            <?php endif; ?>
                                        </td>

                                        <td class="px-5 py-4 align-top">
                                            <?php
                                            $dh_status = $leave['deptHeadStatus'];
                                            $dh_color = ($dh_status == 'เห็นชอบแล้ว') ? 'text-emerald-600' : 'text-amber-500';
                                            ?>
                                            <p class="text-xs font-semibold <?= $dh_color ?>">
                                                <?= htmlspecialchars($dh_status) ?></p>
                                            <p class="text-[11px] text-slate-500 mt-1">
                                                <?= htmlspecialchars($leave['deptHead'] ?? 'ยังไม่ระบุ') ?></p>
                                        </td>

                                        <td class="px-5 py-4 align-top text-center">
                                            <?php
                                            if ($leave['directorStatus'] === 'อนุมัติแล้ว') {
                                                echo '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700"><i class="fa-solid fa-check mr-1.5"></i> อนุมัติแล้ว</span>';
                                            } elseif ($leave['directorStatus'] === 'ไม่อนุมัติ') {
                                                echo '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-700"><i class="fa-solid fa-xmark mr-1.5"></i> ไม่อนุมัติ</span>';
                                            } else {
                                                echo '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700"><i class="fa-regular fa-clock mr-1.5"></i> รอการอนุมัติ</span>';
                                            }
                                            ?>
                                        </td>

                                        <td class="px-5 py-4 align-top text-right">
                                            <?php if ($leave['directorStatus'] === 'รอการอนุมัติ'): ?>
                                                <div class="flex items-center justify-end gap-2">
                                                    <!-- ฟอร์มอนุมัติ -->
                                                    <form id="form-approve-<?= $leave['id'] ?>" method="POST">
                                                        <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="button"
                                                            onclick="confirmAction('approve', <?= $leave['id'] ?>, '<?= htmlspecialchars($leave['teacherName']) ?>')"
                                                            class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded text-xs font-medium shadow-sm transition-colors">
                                                            อนุมัติ
                                                        </button>
                                                    </form>
                                                    <!-- ฟอร์มไม่อนุมัติ -->
                                                    <form id="form-reject-<?= $leave['id'] ?>" method="POST">
                                                        <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="button"
                                                            onclick="confirmAction('reject', <?= $leave['id'] ?>, '<?= htmlspecialchars($leave['teacherName']) ?>')"
                                                            class="px-3 py-1.5 bg-white border border-rose-200 hover:bg-rose-50 text-rose-600 rounded text-xs font-medium shadow-sm transition-colors">
                                                            ไม่อนุมัติ
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span
                                                    class="text-xs text-slate-400 border border-slate-200 px-3 py-1.5 rounded bg-slate-50">พิจารณาแล้ว</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </tbody>
                    </table>
                </div>
            </div>

            <div class="h-8"></div>
        </main>
    </div>

    <!-- Script สำหรับเรียกใช้ SweetAlert -->
    <script>
        // เช็คว่ามี Session success ส่งมาจาก PHP หรือไม่
        <?php if (isset($_SESSION['swal_success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: '<?= $_SESSION['swal_success'] ?>',
                showConfirmButton: false,
                timer: 2000
            });
            <?php unset($_SESSION['swal_success']); ?>
        <?php endif; ?>

        // ฟังก์ชันเมื่อกดปุ่มอนุมัติ หรือ ไม่อนุมัติ
        function confirmAction(actionType, leaveId, teacherName) {
            let config = {
                title: 'ยืนยันการพิจารณา',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ยืนยัน',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true
            };

            if (actionType === 'approve') {
                config.text = `คุณต้องการ "อนุมัติ" ใบลาของ ${teacherName} ใช่หรือไม่?`;
                config.confirmButtonColor = '#10b981'; // สีเขียว Emerald
            } else {
                config.text = `คุณต้องการ "ไม่อนุมัติ" ใบลาของ ${teacherName} ใช่หรือไม่?`;
                config.confirmButtonColor = '#f43f5e'; // สีแดง Rose
            }

            Swal.fire(config).then((result) => {
                if (result.isConfirmed) {
                    // ถ้ากดยืนยัน ให้ submit ฟอร์มตาม ID ของมัน
                    document.getElementById(`form-${actionType}-${leaveId}`).submit();
                }
            });
        }
    </script>
</body>

</html>