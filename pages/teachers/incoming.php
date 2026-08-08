<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once("../../system/a_func.php");
require_once("../../system/teacher_sidebar.php");

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

$stmt_user = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
if ($stmt_user->rowCount() === 0) {
    header("Location: ../login.php");
    exit();
}

$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['prefix'] ?? '') . ' ' . ($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
$initial = mb_substr($user['firstName'] ?? 'U', 0, 1, 'UTF-8');
$userRoleStr = ($user['role'] === 'teacher') ? 'ครูผู้สอน' : 'เจ้าหน้าที่ธุรการ';

$query = trim($_GET['q'] ?? '');

// ==========================================
// ข้อมูลจำลอง (Mock Data) แบบไม่ระบุตัวตนบุคคลจริง
// ==========================================
$allDocs = [
    [
        'id' => 'doc_001',
        'module' => 'หนังสือรับ',
        'reg_number' => 'รบ.69/000011',
        'doc_number' => 'ศธ 04009/1575',
        'title' => 'แจ้งจัดสรรงบประมาณรายจ่ายประจำปี 2569 งบลงทุน ค่าครุภัณฑ์',
        'from' => 'สำนักงานเขตพื้นที่การศึกษาประถมศึกษาขอนแก่น เขต 1',
        'date' => '8 ส.ค. 2569',
        'status' => 'รอ ผอ. พิจารณา',
        'priority' => 'ด่วนมาก',
        'category' => 'งานพัสดุ',
        'registered_by' => 'นางสาวปานวาด รักดี' // ชื่อจำลอง
    ],
    [
        'id' => 'doc_002',
        'module' => 'หนังสือรับ',
        'reg_number' => 'รบ.69/000012',
        'doc_number' => 'ศธ 04009/1620',
        'title' => 'ขอเชิญเข้าร่วมการประชุมผู้บริหารสถานศึกษา ประจำเดือนสิงหาคม',
        'from' => 'สำนักงานเขตพื้นที่การศึกษาประถมศึกษาขอนแก่น เขต 1',
        'date' => '9 ส.ค. 2569',
        'status' => 'รับทราบแล้ว',
        'priority' => 'ปกติ',
        'category' => 'บริหารทั่วไป',
        'registered_by' => 'นายธนพล นำชัย' // ชื่อจำลอง
    ],
    [
        'id' => 'doc_003',
        'module' => 'หนังสือรับ',
        'reg_number' => 'รบ.69/000013',
        'doc_number' => 'ศธ 0211.5/234',
        'title' => 'แนวทางการประเมินคุณภาพภายในสถานศึกษา (SAR) ปีการศึกษา 2569',
        'from' => 'กระทรวงศึกษาธิการ',
        'date' => '10 ส.ค. 2569',
        'status' => 'รอ ผอ. พิจารณา',
        'priority' => 'ด่วน',
        'category' => 'งานวิชาการ',
        'registered_by' => 'นางสาวปานวาด รักดี' // ชื่อจำลอง
    ],
    [
        'id' => 'doc_004',
        'module' => 'หนังสือรับ',
        'reg_number' => 'รบ.69/000014',
        'doc_number' => 'สธ 0300.2/115',
        'title' => 'มาตรการป้องกันและควบคุมโรคติดต่อในสถานศึกษา',
        'from' => 'กรมอนามัย กระทรวงสาธารณสุข',
        'date' => '11 ส.ค. 2569',
        'status' => 'รับทราบแล้ว',
        'priority' => 'ปกติ',
        'category' => 'กิจการนักเรียน',
        'registered_by' => 'นายธนพล นำชัย' // ชื่อจำลอง
    ]
];

// ระบบค้นหา
$filteredDocs = array_values(array_filter($allDocs, static function (array $doc) use ($query): bool {
    if ($query === '') {
        return true;
    }

    $haystack = mb_strtolower(implode(' ', [
        $doc['reg_number'] ?? '',
        $doc['doc_number'] ?? '',
        $doc['title'] ?? '',
        $doc['from'] ?? '',
        $doc['status'] ?? '',
        $doc['priority'] ?? '',
        $doc['category'] ?? '',
    ]), 'UTF-8');

    return mb_strpos($haystack, mb_strtolower($query, 'UTF-8'), 0, 'UTF-8') !== false;
}));

// สรุปสถิติ
$stats = [
    'total' => count($allDocs),
    'urgent' => 0,
    'waiting' => 0,
    'received' => 0,
];

foreach ($allDocs as $doc) {
    if (($doc['priority'] ?? '') === 'ด่วนมาก' || ($doc['priority'] ?? '') === 'ด่วน') {
        $stats['urgent']++;
    }
    if (mb_strpos($doc['status'] ?? '', 'รอ', 0, 'UTF-8') === 0) {
        $stats['waiting']++;
    }
    if (($doc['status'] ?? '') === 'รับทราบแล้ว') {
        $stats['received']++;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทะเบียนหนังสือรับ - SiS4 SCHOOL</title>
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
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0 z-30">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-inbox text-blue-600 text-lg hidden sm:block"></i>
                <div>
                    <p class="text-[10px] uppercase tracking-wider text-gray-400 font-semibold leading-none">งานสารบรรณ</p>
                    <h1 class="text-lg font-bold text-gray-900 leading-tight">ทะเบียนหนังสือรับ</h1>
                </div>
            </div>
            
            <div class="flex items-center gap-4 ml-auto">
                <button class="relative text-gray-500 hover:text-gray-700">
                    <i class="fa-solid fa-bell text-lg"></i>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full border-2 border-white">2</span>
                </button>
                <div class="w-px h-6 bg-gray-300"></div>
                <div class="text-sm font-medium text-gray-700 hidden sm:block">
                    <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">
            
            <!-- Search & Filter Section -->
            <section class="rounded-2xl bg-white border border-gray-200 p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">ทะเบียนหนังสือรับ (ส่วนกลาง)</h2>
                    <p class="text-sm text-gray-500 mt-1">รายการหนังสือราชการที่ถูกส่งเข้ามายังระบบสารบรรณ</p>
                </div>
                
                <form method="get" class="flex w-full md:w-auto items-center gap-2 bg-gray-50 rounded-xl p-2 border border-gray-200">
                    <div class="relative flex-1 md:w-72">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="ค้นหา เรื่อง, ผู้ส่ง, เลขทะเบียน..." class="w-full pl-9 pr-3 py-2 text-sm text-gray-800 bg-transparent focus:outline-none focus:bg-white focus:ring-1 focus:ring-blue-500 rounded-lg transition-colors">
                    </div>
                    <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors shadow-sm whitespace-nowrap">ค้นหา</button>
                    <?php if ($query !== ''): ?>
                        <a href="incoming.php" class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300 transition-colors whitespace-nowrap">ล้าง</a>
                    <?php endif; ?>
                </form>
            </section>

            <!-- Stats Section -->
            <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">หนังสือทั้งหมด</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['total'] ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                </div>
                
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">งานด่วน</p>
                        <p class="text-3xl font-bold text-rose-600 mt-1"><?= $stats['urgent'] ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                </div>
                
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm flex justify-between items-center">
                    <div>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">รอดำเนินการ</p>
                        <p class="text-3xl font-bold text-amber-600 mt-1"><?= $stats['waiting'] ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                </div>
            </section>

            <!-- Document List -->
            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/70 flex items-center justify-between">
                    <h2 class="font-bold text-gray-900 flex items-center gap-2">
                        <i class="fa-solid fa-list text-blue-600"></i> รายการหนังสือ
                    </h2>
                </div>

                <div class="divide-y divide-gray-100">
                    <?php foreach ($filteredDocs as $doc): ?>
                        <a href="documents.php?id=<?= urlencode($doc['id']) ?>" class="block px-5 py-4 hover:bg-blue-50/50 transition-colors group">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200"><?= htmlspecialchars($doc['module'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="font-mono text-xs font-bold text-gray-900 bg-gray-100 px-2 py-0.5 rounded border border-gray-200"><?= htmlspecialchars($doc['reg_number'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider <?= ($doc['status'] === 'รับทราบแล้ว') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>"><?= htmlspecialchars($doc['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($doc['priority'] === 'ด่วนมาก' || $doc['priority'] === 'ด่วน'): ?>
                                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200"><?= htmlspecialchars($doc['priority'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="text-base font-bold text-gray-900 truncate group-hover:text-blue-700 transition-colors"><?= htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                    <p class="mt-1 text-sm text-gray-500 truncate flex items-center gap-2">
                                        <span>จาก: <span class="text-gray-700"><?= htmlspecialchars($doc['from'], ENT_QUOTES, 'UTF-8') ?></span></span>
                                        <span class="text-gray-300">|</span>
                                        <span>เลขที่: <span class="text-gray-700"><?= htmlspecialchars($doc['doc_number'], ENT_QUOTES, 'UTF-8') ?></span></span>
                                        <span class="text-gray-300">|</span>
                                        <span><i class="fa-regular fa-calendar-days"></i> <?= htmlspecialchars($doc['date'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </p>
                                </div>
                                <div class="text-right shrink-0 flex flex-col justify-center h-full">
                                    <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider mb-0.5">ผู้ลงทะเบียน</p>
                                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($doc['registered_by'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>

                    <?php if (empty($filteredDocs)): ?>
                        <div class="px-5 py-16 text-center text-gray-500">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center text-2xl text-gray-400 mx-auto mb-3">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </div>
                            <p class="font-medium text-gray-900">ไม่พบหนังสือราชการ</p>
                            <p class="text-sm mt-1">ลองเปลี่ยนคำค้นหา หรือใช้ตัวกรองอื่น</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <div class="h-6"></div>
        </main>
    </div>
</body>
</html>