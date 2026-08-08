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

// ดึงข้อมูลเอกสาร หากไม่มี ID ให้ใช้ค่าเริ่มต้น
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
        
        /* Custom timeline styles */
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 24px;
            bottom: -24px;
            width: 2px;
            background-color: #e2e8f0;
        }
        .timeline-item:last-child::before {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 h-screen overflow-hidden flex">

    <?php sis4_teacher_sidebar_render($fullName, $initial, $userRoleStr, '../../system/logout.php'); ?>

    <div class="flex-1 flex flex-col min-w-0">
        
        <!-- HEADER -->
        <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0 z-30 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded flex items-center justify-center">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold text-gray-900 leading-tight">รายละเอียดหนังสือ</h1>
                    <p class="text-[11px] text-gray-500">ระบบงานสารบรรณ</p>
                </div>
            </div>
            <div class="flex items-center gap-4 ml-auto">
                <button class="relative text-gray-500 hover:text-gray-900 transition-colors">
                    <i class="fa-solid fa-bell text-lg"></i>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full border border-white">2</span>
                </button>
                <div class="w-px h-6 bg-gray-300"></div>
                <div class="text-sm font-medium text-gray-700 hidden sm:block">
                    <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-6">
            
            <!-- Toolbar -->
            <div class="flex flex-wrap justify-between items-center gap-4 mb-2">
                <a href="incoming.php" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-blue-600 transition-colors font-medium bg-white px-4 py-2 border border-gray-200 rounded shadow-sm">
                    <i class="fa-solid fa-arrow-left"></i> กลับหน้ารวม
                </a>
                <div class="flex gap-2">
                    <button class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded text-sm font-medium transition-colors shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-print"></i> พิมพ์
                    </button>
                    <button class="bg-blue-600 border border-blue-600 text-white hover:bg-blue-700 px-4 py-2 rounded text-sm font-medium transition-colors shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-download"></i> ดาวน์โหลดเอกสาร
                    </button>
                </div>
            </div>

            <!-- Header Info Box (New Anti-Copyright Layout) -->
            <div class="bg-white border border-gray-300 rounded-xl shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-200 bg-gray-50">
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="bg-gray-200 text-gray-800 text-xs font-bold px-2 py-1 rounded uppercase tracking-wider"><?= htmlspecialchars($doc['module']) ?></span>
                        <span class="text-blue-700 font-bold text-sm bg-blue-50 border border-blue-200 px-2 py-1 rounded"><?= htmlspecialchars($doc['reg_number']) ?></span>
                        
                        <?php if ($doc['status'] === 'รับทราบแล้ว'): ?>
                            <span class="bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold px-2 py-1 rounded"><i class="fa-solid fa-check me-1"></i> <?= htmlspecialchars($doc['status']) ?></span>
                        <?php else: ?>
                            <span class="bg-amber-100 text-amber-800 border border-amber-200 text-xs font-bold px-2 py-1 rounded"><i class="fa-regular fa-clock me-1"></i> <?= htmlspecialchars($doc['status']) ?></span>
                        <?php endif; ?>
                        
                        <?php if ($doc['priority'] === 'ด่วนมาก' || $doc['priority'] === 'ด่วน'): ?>
                            <span class="bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold px-2 py-1 rounded"><i class="fa-solid fa-bolt me-1"></i> <?= htmlspecialchars($doc['priority']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 leading-tight"><?= htmlspecialchars($doc['title']) ?></h1>
                </div>

                <!-- Metadata Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-0 divide-y md:divide-y-0 md:divide-x divide-gray-200">
                    <div class="p-5">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">ข้อมูลผู้ส่ง</p>
                        <p class="text-sm font-medium text-gray-900 mb-1"><?= htmlspecialchars($doc['from']) ?></p>
                        <p class="text-xs text-gray-500">เลขที่: <?= htmlspecialchars($doc['doc_number']) ?></p>
                    </div>
                    <div class="p-5">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">ผู้รับ / ผู้เรียน</p>
                        <p class="text-sm font-medium text-gray-900 mb-1"><?= htmlspecialchars($doc['to']) ?></p>
                        <p class="text-xs text-gray-500">ลงวันที่: <?= htmlspecialchars($doc['date']) ?></p>
                    </div>
                    <div class="p-5">
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">ข้อมูลระบบ</p>
                        <p class="text-sm font-medium text-gray-900 mb-1">หมวดหมู่: <?= htmlspecialchars($doc['category']) ?></p>
                        <p class="text-xs text-gray-500">ลงทะเบียนโดย: <?= htmlspecialchars($doc['registered_by']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Main Content Split -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Left Column (Content & Files) - Takes up 2/3 -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Summary Box -->
                    <div class="bg-white border border-gray-300 rounded-xl shadow-sm">
                        <div class="px-5 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                <i class="fa-solid fa-list-check text-blue-600"></i> สรุปสาระสำคัญ
                            </h3>
                        </div>
                        <div class="p-5">
                            <p class="text-sm text-gray-700 leading-relaxed mb-4 p-4 bg-blue-50/50 border border-blue-100 rounded text-justify">
                                <?= htmlspecialchars($doc['summary']) ?>
                            </p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 border-b border-gray-200 pb-1">ประเด็นสำคัญ</p>
                                    <ul class="space-y-2">
                                        <?php foreach ($doc['key_points'] as $point): ?>
                                            <li class="text-sm text-gray-700 flex items-start gap-2">
                                                <i class="fa-solid fa-check text-emerald-500 mt-1"></i>
                                                <span><?= htmlspecialchars($point) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 border-b border-gray-200 pb-1">กำหนดการที่เกี่ยวข้อง</p>
                                    <ul class="space-y-2">
                                        <?php foreach ($doc['deadlines'] as $deadline): ?>
                                            <li class="flex items-center justify-between text-sm p-2 bg-gray-50 border border-gray-200 rounded">
                                                <span class="text-gray-700 truncate mr-2"><?= htmlspecialchars($deadline['label']) ?></span>
                                                <span class="bg-white border border-gray-300 font-bold text-gray-800 px-2 py-0.5 rounded text-xs whitespace-nowrap"><i class="fa-regular fa-calendar-days mr-1"></i><?= htmlspecialchars($deadline['date']) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if(empty($doc['deadlines'])): ?>
                                            <li class="text-sm text-gray-400 italic">ไม่มีกำหนดการ</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- OCR Box -->
                    <div class="bg-white border border-gray-300 rounded-xl shadow-sm">
                        <div class="px-5 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                <i class="fa-solid fa-align-left text-gray-500"></i> ข้อความจากเอกสารต้นฉบับ
                            </h3>
                        </div>
                        <div class="p-5">
                            <textarea readonly class="w-full h-64 bg-gray-50 border border-gray-200 rounded p-4 text-sm font-mono text-gray-700 focus:outline-none resize-y"><?= htmlspecialchars($doc['ocr_text']) ?></textarea>
                        </div>
                    </div>

                    <!-- Attachments -->
                    <div class="bg-white border border-gray-300 rounded-xl shadow-sm">
                        <div class="px-5 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                <i class="fa-solid fa-paperclip text-gray-500"></i> ไฟล์แนบ (<?= count($doc['attachments']) ?>)
                            </h3>
                        </div>
                        <div class="p-5">
                            <?php if (!empty($doc['attachments'])): ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <?php foreach ($doc['attachments'] as $attachment): ?>
                                        <a href="#" class="flex items-center gap-3 p-3 border border-gray-200 rounded hover:border-blue-400 hover:bg-blue-50 transition-colors group">
                                            <div class="w-10 h-10 bg-rose-100 text-rose-600 rounded flex items-center justify-center text-lg">
                                                <i class="fa-solid fa-file-pdf"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-bold text-gray-800 truncate group-hover:text-blue-700"><?= htmlspecialchars($attachment['name']) ?></p>
                                                <p class="text-[11px] text-gray-500"><?= htmlspecialchars($attachment['size']) ?></p>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 text-center py-4">ไม่มีไฟล์แนบ</p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Right Column (Timeline & Comments) - Takes up 1/3 -->
                <div class="lg:col-span-1 space-y-6">
                    
                    <!-- Timeline Box -->
                    <div class="bg-white border border-gray-300 rounded-xl shadow-sm">
                        <div class="px-5 py-3 border-b border-gray-200 bg-gray-50">
                            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                <i class="fa-solid fa-clock-rotate-left text-gray-500"></i> ประวัติการดำเนินการ
                            </h3>
                        </div>
                        <div class="p-5">
                            <div class="relative">
                                <?php foreach ($doc['timeline'] as $item): ?>
                                    <div class="timeline-item relative pb-6 pl-6">
                                        <!-- Node -->
                                        <div class="absolute left-0 top-1.5 w-3 h-3 rounded-full border-2 <?= !empty($item['active']) ? 'bg-blue-600 border-blue-600 shadow-sm' : 'bg-white border-gray-300' ?>"></div>
                                        
                                        <!-- Content -->
                                        <div>
                                            <p class="text-sm font-bold <?= !empty($item['active']) ? 'text-blue-700' : 'text-gray-800' ?>">
                                                <?= htmlspecialchars($item['title']) ?>
                                            </p>
                                            <p class="text-xs text-gray-600 mt-1"><?= htmlspecialchars($item['detail']) ?></p>
                                            <div class="flex items-center gap-2 mt-1.5 text-[11px] text-gray-400 font-medium">
                                                <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars($item['time']) ?></span>
                                                <span>•</span>
                                                <span><?= htmlspecialchars($item['by']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Comments Box -->
                    <div class="bg-white border border-gray-300 rounded-xl shadow-sm">
                        <div class="px-5 py-3 border-b border-gray-200 bg-gray-50">
                            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                <i class="fa-regular fa-comments text-gray-500"></i> ความเห็นและบันทึกข้อความ
                            </h3>
                        </div>
                        <div class="p-5 flex flex-col h-[300px]">
                            <div class="flex-1 overflow-y-auto mb-4 border border-dashed border-gray-200 rounded flex items-center justify-center bg-gray-50">
                                <p class="text-sm text-gray-400 italic">ยังไม่มีข้อความบันทึก</p>
                            </div>
                            
                            <form action="#" method="POST" class="mt-auto">
                                <div class="flex gap-2">
                                    <input type="text" placeholder="พิมพ์ข้อความ..." class="flex-1 border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required>
                                    <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded text-sm font-bold hover:bg-gray-900 transition-colors">
                                        ส่ง
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
            
            <div class="h-6"></div>
        </main>
    </div>

</body>
</html>