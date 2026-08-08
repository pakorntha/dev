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

// ดึงข้อมูลผู้ใช้งานที่ล็อกอิน
$stmt_user = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
if ($stmt_user->rowCount() === 0) {
    header("Location: ../login.php");
    exit();
}

$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$fullName = trim(($user['prefix'] ?? '') . ' ' . ($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
$initial = mb_substr($user['firstName'] ?? 'U', 0, 1, 'UTF-8');
$userRoleStr = ($user['role'] === 'teacher') ? 'ครูผู้สอน' : 'เจ้าหน้าที่ธุรการ';

// รับค่า ID ของเอกสาร
$docId = $_GET['id'] ?? 'doc_001';

// ==========================================
// ข้อมูลจำลอง (Mock Data) แบบไม่ระบุตัวตนบุคคลจริง
// ==========================================
$mockDB = [
    'doc_001' => [
        'id' => 'doc_001',
        'module' => 'หนังสือรับ',
        'reg_number' => 'รบ.69/000011',
        'doc_number' => 'ศธ 04009/1575',
        'title' => 'แจ้งจัดสรรงบประมาณรายจ่ายประจำปี 2569 งบลงทุน ค่าครุภัณฑ์',
        'from' => 'สำนักงานเขตพื้นที่การศึกษาประถมศึกษาขอนแก่น เขต 1',
        'to' => 'ผู้อำนวยการโรงเรียนในสังกัด',
        'date' => '8 ส.ค. 2569',
        'received_date' => '8 ส.ค. 69 16:11 น.',
        'status' => 'รอ ผอ. พิจารณา',
        'priority' => 'ด่วนมาก',
        'category' => 'งานพัสดุ',
        'registered_by' => 'นางสาวปานวาด รักดี',
        'pages' => 4,
        'summary' => 'สำนักงานเขตพื้นที่การศึกษาประถมศึกษาขอนแก่น เขต 1 แจ้งจัดสรรงบประมาณรายจ่ายประจำปี 2569 งบลงทุน ค่าครุภัณฑ์ โดยขอให้สถานศึกษาดำเนินการ 3 รายการ และมีกำหนดเวลาที่ต้องดำเนินการ 2 รายการตามเอกสารแนบ',
        'key_points' => [
            'ตรวจสอบรายการครุภัณฑ์ที่ได้รับจัดสรรให้ถูกต้อง',
            'ดำเนินการจัดซื้อจัดจ้างตามระเบียบกระทรวงการคลังว่าด้วยการจัดซื้อจัดจ้าง',
            'บันทึกข้อมูลในระบบ e-GP ให้ครบถ้วน',
            'รายงานผลการดำเนินงานภายในวันที่ 15 กันยายน 2569',
            'ก่อหนี้ผูกพันให้แล้วเสร็จ ภายในวันที่ 31 สิงหาคม 2569'
        ],
        'to_dos' => [
            'ตรวจสอบรายการครุภัณฑ์ให้ถูกต้อง',
            'ดำเนินการจัดซื้อจัดจ้างตามระเบียบ',
            'รายงานผลการดำเนินงาน'
        ],
        'deadlines' => [
            ['label' => 'รายงานผลการดำเนินงาน', 'date' => '15 ก.ย. 69'],
            ['label' => 'ก่อหนี้ผูกพันให้แล้วเสร็จ', 'date' => '31 ส.ค. 69']
        ],
        'ocr_text' => "ที่ ศธ 04009/1575\nด่วนมาก\nสำนักงานเขตพื้นที่การศึกษาประถมศึกษาขอนแก่น เขต 1\n\nเรื่อง แจ้งจัดสรรงบประมาณรายจ่ายประจำปี 2569 งบลงทุน ค่าครุภัณฑ์\nเรียน ผู้อำนวยการโรงเรียนในสังกัด\n\nด้วยสำนักงานคณะกรรมการการศึกษาขั้นพื้นฐาน ได้จัดสรรงบประมาณรายจ่ายประจำปีงบประมาณ พ.ศ. 2569 งบลงทุน ค่าครุภัณฑ์ ให้แก่สถานศึกษาในสังกัด จึงขอแจ้งให้ดำเนินการดังนี้\n\n1. ตรวจสอบรายการครุภัณฑ์ที่ได้รับจัดสรรให้ถูกต้อง\n2. ดำเนินการจัดซื้อจัดจ้างตามระเบียบกระทรวงการคลังว่าด้วยการจัดซื้อจัดจ้าง\n3. บันทึกข้อมูลในระบบ e-GP ให้ครบถ้วน\n4. รายงานผลการดำเนินงานภายในวันที่ 15 กันยายน 2569\n5. ก่อหนี้ผูกพันให้แล้วเสร็จ ภายในวันที่ 31 สิงหาคม 2569\n\nจึงเรียนมาเพื่อทราบและถือปฏิบัติโดยเคร่งครัด\n\nขอแสดงความนับถือ",
        'attachments' => [
            ['name' => 'ว-322-แนวปฏิบัติในการจัดซื้อจัดจ้าง.pdf', 'size' => '197 KB']
        ],
        'timeline' => [
            ['title' => 'อัปโหลดไฟล์เอกสาร', 'detail' => 'ว-322-แนวปฏิบัติในการจัดซื้อจัดจ้าง.pdf', 'time' => '8 ส.ค. 69 16:11 น.', 'by' => 'นางสาวปานวาด รักดี', 'active' => false],
            ['title' => 'ประมวลผลข้อความและสกัดข้อมูล', 'detail' => 'ดึงข้อมูลสาระสำคัญสำเร็จ', 'time' => '8 ส.ค. 69 16:11 น.', 'by' => 'ระบบสารบรรณ', 'active' => false],
            ['title' => 'ลงทะเบียนรับหนังสือ', 'detail' => 'เลขทะเบียน รบ.69/000011', 'time' => '8 ส.ค. 69 16:11 น.', 'by' => 'นางสาวปานวาด รักดี', 'active' => false],
            ['title' => 'เสนอผู้อำนวยการ', 'detail' => 'รอพิจารณาสั่งการ', 'time' => '8 ส.ค. 69 16:11 น.', 'by' => 'นางสาวปานวาด รักดี', 'active' => true],
        ]
    ],
    'doc_002' => [
        'id' => 'doc_002',
        'module' => 'หนังสือรับ',
        'reg_number' => 'รบ.69/000012',
        'doc_number' => 'ศธ 04009/1620',
        'title' => 'ขอเชิญเข้าร่วมการประชุมผู้บริหารสถานศึกษา ประจำเดือนสิงหาคม',
        'from' => 'สำนักงานเขตพื้นที่การศึกษาประถมศึกษาขอนแก่น เขต 1',
        'to' => 'ผู้อำนวยการโรงเรียนในสังกัด',
        'date' => '9 ส.ค. 2569',
        'received_date' => '9 ส.ค. 69 09:00 น.',
        'status' => 'รับทราบแล้ว',
        'priority' => 'ปกติ',
        'category' => 'บริหารทั่วไป',
        'registered_by' => 'นายธนพล นำชัย',
        'pages' => 2,
        'summary' => 'สำนักงานเขตพื้นที่การศึกษาประถมศึกษาขอนแก่น เขต 1 แจ้งขอเชิญผู้อำนวยการสถานศึกษาเข้าร่วมประชุมประจำเดือนสิงหาคม 2569 เพื่อรับทราบข้อราชการและมอบนโยบายการจัดการศึกษา',
        'key_points' => [
            'กำหนดการประชุมวันที่ 15 สิงหาคม 2569 เวลา 09.00 - 16.30 น.',
            'สถานที่ ณ ห้องประชุมทับทิมสยาม สำนักงานเขตพื้นที่การศึกษา',
            'ให้ผู้บริหารสถานศึกษาเข้าร่วมประชุมด้วยตนเอง'
        ],
        'to_dos' => [
            'เข้าร่วมประชุมตามวันและเวลาที่กำหนด',
            'เตรียมรายงานผลการดำเนินงานประจำเดือน'
        ],
        'deadlines' => [
            ['label' => 'วันประชุมผู้บริหาร', 'date' => '15 ส.ค. 69']
        ],
        'ocr_text' => "ที่ ศธ 04009/1620\nสำนักงานเขตพื้นที่การศึกษาประถมศึกษาขอนแก่น เขต 1\n\nเรื่อง ขอเชิญเข้าร่วมการประชุมผู้บริหารสถานศึกษา ประจำเดือนสิงหาคม\nเรียน ผู้อำนวยการโรงเรียนในสังกัด\n\nด้วยสำนักงานเขตพื้นที่การศึกษาประถมศึกษาขอนแก่น เขต 1 กำหนดจัดการประชุมผู้บริหารสถานศึกษา ประจำเดือนสิงหาคม 2569 เพื่อมอบนโยบายและติดตามผลการดำเนินงาน\n\nจึงขอเชิญท่านเข้าร่วมประชุม ในวันที่ 15 สิงหาคม 2569 เวลา 09.00 น. ณ ห้องประชุมทับทิมสยาม\n\nขอแสดงความนับถือ",
        'attachments' => [
            ['name' => 'วาระการประชุมผู้บริหาร.pdf', 'size' => '1.2 MB']
        ],
        'timeline' => [
            ['title' => 'ลงทะเบียนรับหนังสือ', 'detail' => 'เลขทะเบียน รบ.69/000012', 'time' => '9 ส.ค. 69 09:00 น.', 'by' => 'นายธนพล นำชัย', 'active' => false],
            ['title' => 'เสนอผู้อำนวยการ', 'detail' => 'ส่งเข้าแฟ้มเพื่อพิจารณา', 'time' => '9 ส.ค. 69 09:10 น.', 'by' => 'นายธนพล นำชัย', 'active' => false],
            ['title' => 'รับทราบแล้ว', 'detail' => 'ผู้อำนวยการลงนามรับทราบ', 'time' => '9 ส.ค. 69 10:30 น.', 'by' => 'ผู้อำนวยการสถานศึกษา', 'active' => true],
        ]
    ]
];

// ดึงข้อมูลเอกสาร หากไม่มี ID ให้ใช้ค่าเริ่มต้นคือ doc_001
$doc = $mockDB[$docId] ?? $mockDB['doc_001'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($doc['reg_number'] ?? '') ?> <?= htmlspecialchars($doc['title'] ?? '') ?> - SiS4 SCHOOL</title>
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
        
        <!-- HEADER -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0 z-30">
            <div class="flex-1"></div>
            <div class="relative w-96 hidden sm:block">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" placeholder="ค้นหาหนังสือ เรื่อง ผู้ส่ง เลขทะเบียน..." class="w-full pl-9 pr-4 py-1.5 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
            </div>
            <div class="flex items-center gap-4 ml-4">
                <button class="relative text-gray-500 hover:text-gray-700">
                    <i class="fa-solid fa-bell text-lg"></i>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full border-2 border-white">2</span>
                </button>
                <div class="w-px h-6 bg-gray-300"></div>
                <span class="text-sm font-medium text-gray-700 hidden sm:block"><?= htmlspecialchars($fullName) ?></span>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            <div class="mx-auto w-full max-w-7xl">
                
                <a href="incoming.php" class="mb-4 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-blue-600 transition-colors">
                    <i class="fa-solid fa-arrow-left"></i> กลับไปทะเบียนหนังสือ
                </a>

                <!-- Document Header Card -->
                <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200"><?= htmlspecialchars($doc['module']) ?></span>
                                <span class="font-mono text-sm font-bold text-gray-900 bg-gray-100 px-2 py-0.5 rounded border border-gray-200"><?= htmlspecialchars($doc['reg_number']) ?></span>
                                <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-bold uppercase tracking-wider <?= ($doc['status'] === 'รับทราบแล้ว') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>"><?= htmlspecialchars($doc['status']) ?></span>
                                <?php if ($doc['priority'] === 'ด่วนมาก' || $doc['priority'] === 'ด่วน'): ?>
                                    <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-bold uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200"><?= htmlspecialchars($doc['priority']) ?></span>
                                <?php endif; ?>
                            </div>
                            <h1 class="text-xl font-bold text-gray-900 sm:text-2xl"><?= htmlspecialchars($doc['title']) ?></h1>
                            <p class="mt-2 text-sm text-gray-500 flex items-center gap-2">
                                <span>จาก: <span class="text-gray-700 font-medium"><?= htmlspecialchars($doc['from']) ?></span></span>
                                <span class="text-gray-300">|</span>
                                <span>เลขที่: <span class="text-gray-700"><?= htmlspecialchars($doc['doc_number']) ?></span></span>
                                <span class="text-gray-300">|</span>
                                <span><i class="fa-regular fa-calendar-days"></i> <?= htmlspecialchars($doc['date']) ?></span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm transition-colors">
                                <i class="fa-solid fa-download"></i> ดาวน์โหลด
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[1.35fr_1fr]">
                    
                    <!-- Left Column -->
                    <div class="space-y-6">
                        
                        <!-- Summary Section -->
                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6">
                            <div class="mb-5 flex items-center justify-between gap-3 border-b border-gray-100 pb-3">
                                <h2 class="flex items-center gap-2 text-base font-bold text-gray-900">
                                    <i class="fa-solid fa-file-lines text-blue-600"></i> สรุปสาระสำคัญของเอกสาร
                                </h2>
                            </div>
                            
                            <p class="text-sm leading-relaxed text-gray-800">
                                <?= htmlspecialchars($doc['summary']) ?>
                            </p>
                            
                            <p class="mt-5 text-xs font-bold text-gray-600 uppercase tracking-wider">ประเด็นสำคัญ</p>
                            <ul class="mt-2 space-y-2 text-sm text-gray-700">
                                <?php foreach ($doc['key_points'] as $point): ?>
                                <li class="flex gap-2.5 items-start">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500"></span>
                                    <?= htmlspecialchars($point) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p class="text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">สิ่งที่ต้องดำเนินการ</p>
                                    <ul class="space-y-2 text-sm text-gray-700">
                                        <?php foreach ($doc['to_dos'] as $todo): ?>
                                        <li class="flex gap-2 rounded-lg bg-gray-50 px-3 py-2 border border-gray-100 shadow-sm">
                                            <i class="fa-solid fa-circle-check mt-0.5 shrink-0 text-blue-600"></i> <?= htmlspecialchars($todo) ?>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">กำหนดเวลา</p>
                                    <ul class="space-y-2">
                                        <?php foreach ($doc['deadlines'] as $deadline): ?>
                                        <li class="flex items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 text-sm border border-emerald-100 shadow-sm">
                                            <span class="min-w-0 truncate text-gray-700"><?= htmlspecialchars($deadline['label']) ?></span>
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-bold rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200"><?= htmlspecialchars($deadline['date']) ?></span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </section>

                        <!-- Original Text -->
                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <h2 class="flex items-center gap-2 text-base font-bold text-gray-900">
                                    <i class="fa-solid fa-file-invoice text-blue-600"></i> ข้อความจากเอกสารต้นฉบับ
                                </h2>
                            </div>
                            
                            <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-xl bg-gray-50 border border-gray-100 p-4 font-mono text-xs leading-relaxed text-gray-700"><?= htmlspecialchars($doc['ocr_text']) ?></pre>
                            
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <p class="mb-2 text-xs font-bold text-gray-600"><i class="fa-solid fa-paperclip me-1"></i> ไฟล์แนบ (<?= count($doc['attachments']) ?>)</p>
                                <ul class="space-y-2">
                                    <?php if (!empty($doc['attachments'])): ?>
                                        <?php foreach ($doc['attachments'] as $attachment): ?>
                                            <li class="flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 hover:bg-gray-50 transition-colors cursor-pointer">
                                                <i class="fa-solid fa-file-pdf text-rose-500 text-lg"></i>
                                                <span class="min-w-0 flex-1 truncate text-sm text-gray-700 font-medium"><?= htmlspecialchars($attachment['name']) ?></span>
                                                <span class="shrink-0 text-xs text-gray-400"><?= htmlspecialchars($attachment['size']) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="text-sm text-gray-500">ไม่มีไฟล์แนบ</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </section>

                        <!-- Comments -->
                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <h2 class="flex items-center gap-2 text-base font-bold text-gray-900">
                                    <i class="fa-regular fa-comments text-blue-600"></i> ความเห็น (0)
                                </h2>
                            </div>
                            <div class="text-sm text-gray-400 mb-4 py-2 text-center bg-gray-50 rounded-lg border border-dashed border-gray-200">ยังไม่มีความเห็น</div>
                            <form action="#" method="POST" class="flex gap-2">
                                <input type="text" placeholder="เขียนความเห็น..." class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                                    ส่ง
                                </button>
                            </form>
                        </section>

                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        
                        <!-- Metadata -->
                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6">
                            <div class="mb-5 flex items-center justify-between gap-3 border-b border-gray-100 pb-3">
                                <h2 class="flex items-center gap-2 text-base font-bold text-gray-900">
                                    <i class="fa-solid fa-circle-info text-blue-600"></i> ข้อมูลทะเบียน
                                </h2>
                            </div>
                            
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="shrink-0 text-gray-500">ประเภท</dt>
                                    <dd class="text-end text-gray-900 font-medium"><?= htmlspecialchars($doc['module']) ?></dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="shrink-0 text-gray-500">เลขทะเบียน</dt>
                                    <dd class="text-end text-gray-900 font-medium"><?= htmlspecialchars($doc['reg_number']) ?></dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="shrink-0 text-gray-500">เลขที่หนังสือ</dt>
                                    <dd class="text-end text-gray-900 font-medium"><?= htmlspecialchars($doc['doc_number']) ?></dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="shrink-0 text-gray-500">ลงวันที่</dt>
                                    <dd class="text-end text-gray-900 font-medium"><?= htmlspecialchars($doc['date']) ?></dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="shrink-0 text-gray-500">วันที่รับ</dt>
                                    <dd class="text-end text-gray-900 font-medium"><?= htmlspecialchars($doc['received_date']) ?></dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="shrink-0 text-gray-500">จาก</dt>
                                    <dd class="text-end text-gray-900 font-medium"><?= htmlspecialchars($doc['from']) ?></dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="shrink-0 text-gray-500">เรียน</dt>
                                    <dd class="text-end text-gray-900 font-medium"><?= htmlspecialchars($doc['to']) ?></dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="shrink-0 text-gray-500">จำนวนหน้า</dt>
                                    <dd class="text-end text-gray-900 font-medium"><?= htmlspecialchars($doc['pages']) ?> หน้า</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="shrink-0 text-gray-500">สิ่งที่ส่งมาด้วย</dt>
                                    <dd class="text-end text-gray-900 font-medium"><?= count($doc['attachments']) ?> รายการ</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="shrink-0 text-gray-500">ผู้ลงทะเบียน</dt>
                                    <dd class="text-end text-gray-900 font-medium"><?= htmlspecialchars($doc['registered_by']) ?></dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 pt-2 border-t border-gray-100">
                                    <dt class="text-gray-500">หมวดหมู่</dt>
                                    <dd><span class="inline-flex items-center rounded-lg px-2 py-1 text-xs font-bold bg-gray-100 text-gray-700 border border-gray-200"><?= htmlspecialchars($doc['category']) ?></span></dd>
                                </div>
                            </dl>
                            
                            <div class="mt-4 flex flex-wrap gap-2 pt-4 border-t border-gray-100">
                                <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">#<?= htmlspecialchars($doc['category']) ?></span>
                                <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">#หนังสือราชการ</span>
                                <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">#แจ้งเพื่อทราบ</span>
                            </div>
                        </section>

                        <!-- Timeline -->
                        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm p-6">
                            <div class="mb-5 flex items-center justify-between gap-3 border-b border-gray-100 pb-3">
                                <h2 class="flex items-center gap-2 text-base font-bold text-gray-900">
                                    <i class="fa-solid fa-clock-rotate-left text-blue-600"></i> ประวัติการดำเนินการ
                                </h2>
                            </div>
                            
                            <div class="relative pl-4 space-y-6">
                                <div class="absolute left-[23px] top-2 bottom-2 w-px bg-gray-200"></div>
                                <?php foreach ($doc['timeline'] as $item): ?>
                                <div class="relative">
                                    <span class="absolute -left-2.5 top-1.5 h-3 w-3 rounded-full border-2 border-white <?= !empty($item['active']) ? 'bg-blue-600 shadow-sm' : 'bg-gray-300' ?>"></span>
                                    <div class="pl-5">
                                        <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($item['title']) ?></p>
                                        <p class="text-xs <?= !empty($item['active']) ? 'text-blue-600 font-medium' : 'text-gray-600' ?> mt-0.5"><?= htmlspecialchars($item['detail']) ?></p>
                                        <p class="mt-1 flex items-center gap-1.5 text-[11px] text-gray-400">
                                            <i class="fa-regular fa-clock"></i> <?= htmlspecialchars($item['time']) ?> · <?= htmlspecialchars($item['by']) ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                    </div>
                </div>

            </div>
            
            <div class="h-6"></div>
        </main>
    </div>

</body>
</html>