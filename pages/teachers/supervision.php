<?php
session_start();
require_once("../../system/a_func.php");
require_once("../../system/teacher_sidebar.php");

// 1. ตรวจสอบการล็อกอิน
if (!isset($_SESSION['id'])) {
    header("Location: ../login.php");
    exit();
}

// 2. ดึงข้อมูลครูผู้ใช้งานปัจจุบัน
$stmt_user = dd_q("SELECT * FROM user WHERE id = ? LIMIT 1", [$_SESSION['id']]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$userName = trim(($user['prefix'] ?? '') . ' ' . ($user['firstName'] ?? 'วีระพงษ์') . ' ' . ($user['lastName'] ?? 'ชัยชนะ'));
$initial = mb_substr($user['firstName'] ?? 'ว', 0, 1, 'UTF-8');

// 3. ดึงข้อมูลจาก Database (หากตารางยังไม่มี หรือไม่มีข้อมูล ระบบจะใช้ Mock Data อัตโนมัติ)
$supervisions = [];
$stats = [
    'total' => 5,
    'recorded' => 4,
    'avg_score' => 4.23,
    'pending_ack' => 1
];

try {
    $stmt = dd_q("SELECT * FROM supervisions ORDER BY id DESC");
    $db_supervisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($db_supervisions)) {
        $supervisions = $db_supervisions;
        $stats['total'] = count($supervisions);
        $stats['recorded'] = count(array_filter($supervisions, fn($i) => $i['status'] !== 'scheduled'));
        $stats['pending_ack'] = count(array_filter($supervisions, fn($i) => $i['status'] === 'pending_ack'));
        
        $total_scores = array_column(array_filter($supervisions, fn($i) => $i['overallScore'] > 0), 'overallScore');
        $stats['avg_score'] = count($total_scores) > 0 ? number_format(array_sum($total_scores) / count($total_scores), 2) : 0;
    }
} catch (Throwable $e) {
    // ใช้ Mock Data กรณีฐานข้อมูลยังไม่พร้อม
}

// ข้อมูล Mock ตามภาพ
if (empty($supervisions)) {
    $supervisions = [
        [
            'id' => 1,
            'teacherName' => 'นางสาวอรวรรณ ดวงแก้ว',
            'subject' => 'ภาษาอังกฤษ',
            'classroom' => 'ห้อง ป.4/1',
            'supervisionDate' => '12 สิงหาคม 2569',
            'supervisorName' => 'นางสุตารัตน์ ทองแท้',
            'status' => 'scheduled',
            'initial' => 'อ',
            'avatarBg' => 'bg-indigo-600'
        ],
        [
            'id' => 2,
            'teacherName' => 'นายวีระพงษ์ ชัยชนะ',
            'subject' => 'ภาษาไทย',
            'classroom' => 'ห้อง ป.3/1',
            'supervisionDate' => '5 สิงหาคม 2569',
            'supervisorName' => 'นายสมชาย มั่นคง',
            'status' => 'acknowledged',
            'initial' => 'ว',
            'avatarBg' => 'bg-blue-600',
            'overallScore' => 4.67,
            'scores' => [
                'teaching' => 5.0,
                'media' => 4.5,
                'eval' => 4.5,
                'climate' => 5.0,
                'activity' => 4.5,
                'engagement' => 4.5
            ],
            'strengths' => 'จัดกิจกรรมได้ตามลำดับขั้นตอน ใช้คำถามกระตุ้นความคิดได้ดี นักเรียนมีส่วนร่วมสูง',
            'recommendations' => 'ควรเพิ่มการใช้สื่อเทคโนโลยีและออกแบบการวัดผลระหว่างเรียนให้หลากหลายขึ้น',
            'acknowledgedAt' => '7 ส.ค. 69 10:00 น.'
        ]
    ];
}

// คะแนนเฉลี่ยรายหัวข้อ (Mock Data)
$topic_averages = [
    'การจัดการเรียนการสอน' => 4.38,
    'สื่อและเทคโนโลยีการสอน' => 4.00,
    'การวัดและประเมินผล' => 4.13,
    'บรรยากาศในชั้นเรียน' => 4.63,
    'กิจกรรมการเรียนรู้' => 4.13,
    'การมีส่วนร่วมของนักเรียน' => 4.13
];

// ผลการนิเทศรายครู (Mock Data)
$teacher_rankings = [
    ['name' => 'นายวีระพงษ์ ชัยชนะ', 'count' => 1, 'score' => '4.67', 'bg' => 'bg-blue-600', 'initial' => 'ว'],
    ['name' => 'นายธนกฤต วงศ์ไทย', 'count' => 1, 'score' => '4.50', 'bg' => 'bg-amber-700', 'initial' => 'ธ'],
    ['name' => 'นางสาวชนิดาภา พูลสุข', 'count' => 1, 'score' => '4.08', 'bg' => 'bg-rose-700', 'initial' => 'ช'],
    ['name' => 'นายภาณุพงศ์ สุขเกษม', 'count' => 1, 'score' => '3.67', 'bg' => 'bg-emerald-700', 'initial' => 'ภ']
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบนิเทศการสอน - AI School e-Office</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Prompt -->
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 h-screen overflow-hidden flex">

    <?php sis4_teacher_sidebar_render($userName, $initial); ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        
        <!-- Top Navbar Header -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8 flex-shrink-0">
            <div></div>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <input type="text" placeholder="ค้นหาหนังสือ เรื่อง ผู้ส่ง เลขทะเบียน..." class="pl-9 pr-4 py-1.5 bg-slate-100 border-0 rounded-full text-xs w-72 focus:ring-2 focus:ring-indigo-500 outline-none">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                </div>
                <div class="relative p-2 bg-slate-100 rounded-full text-slate-600 hover:bg-slate-200 cursor-pointer">
                    <i class="fa-regular fa-bell text-sm"></i>
                    <span class="absolute top-0 right-0 w-4 h-4 bg-rose-500 text-white rounded-full text-[10px] flex items-center justify-center font-bold">2</span>
                </div>
                <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
                    <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center font-semibold text-xs">
                        <?= $initial ?>
                    </div>
                    <div class="text-xs">
                        <p class="font-bold text-slate-800"><?= htmlspecialchars($userName) ?></p>
                        <p class="text-slate-400">ครู</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Body -->
        <main class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- Title & Breadcrumbs -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-lg">
                    <i class="fa-regular fa-eye"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-800">ระบบนิเทศการสอน</h2>
                    <p class="text-xs text-slate-400 flex items-center gap-1.5 mt-0.5">
                        <span>นัดหมาย</span> &rarr; 
                        <span>เข้าสังเกตการสอน</span> &rarr; 
                        <span>บันทึกผล</span> &rarr; 
                        <span>ครูรับทราบ</span> &rarr; 
                        <span>ติดตามผลการพัฒนา</span>
                    </p>
                </div>
            </div>

            <!-- Top 4 Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                
                <!-- Card 1 -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">การนิเทศทั้งหมด</p>
                        <p class="text-3xl font-bold text-slate-800"><?= $stats['total'] ?></p>
                    </div>
                    <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
                        <i class="fa-regular fa-id-card text-lg"></i>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">บันทึกผลแล้ว</p>
                        <p class="text-3xl font-bold text-slate-800"><?= $stats['recorded'] ?></p>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">คะแนนเฉลี่ยทั้งโรงเรียน</p>
                        <div class="flex items-baseline gap-1">
                            <span class="text-3xl font-bold text-slate-800"><?= number_format($stats['avg_score'], 2) ?></span>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-0.5">จากคะแนนเต็ม 5</p>
                    </div>
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                        <i class="fa-regular fa-star text-lg"></i>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500 mb-1">รอครูรับทราบ</p>
                        <p class="text-3xl font-bold text-slate-800"><?= $stats['pending_ack'] ?></p>
                    </div>
                </div>

            </div>

            <!-- Two Columns Main Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Left Column (Supervision Timeline List) -->
                <div class="lg:col-span-8 space-y-4">
                    
                    <?php foreach ($supervisions as $item): ?>
                        
                        <?php if ($item['status'] === 'scheduled'): ?>
                            <!-- Scheduled Supervision Card -->
                            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full <?= $item['avatarBg'] ?? 'bg-indigo-600' ?> text-white font-bold flex items-center justify-center text-sm">
                                        <?= $item['initial'] ?? 'อ' ?>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($item['teacherName']) ?></h4>
                                        <p class="text-xs text-slate-400 mt-0.5">
                                            <?= htmlspecialchars($item['subject']) ?> &bull; <?= htmlspecialchars($item['classroom']) ?> &bull; <?= htmlspecialchars($item['supervisionDate']) ?>
                                        </p>
                                        <p class="text-xs text-slate-400 mt-0.5">
                                            ผู้นิเทศ: <?= htmlspecialchars($item['supervisorName']) ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-medium">
                                    นัดหมายแล้ว
                                </span>
                            </div>

                        <?php else: ?>
                            <!-- Completed / Acknowledged Supervision Detailed Card -->
                            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-5">
                                
                                <!-- Card Header -->
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full <?= $item['avatarBg'] ?? 'bg-blue-600' ?> text-white font-bold flex items-center justify-center text-sm">
                                            <?= $item['initial'] ?? 'ว' ?>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-slate-800 text-base"><?= htmlspecialchars($item['teacherName']) ?></h4>
                                            <p class="text-xs text-slate-400 mt-0.5">
                                                <?= htmlspecialchars($item['subject']) ?> &bull; <?= htmlspecialchars($item['classroom']) ?> &bull; <?= htmlspecialchars($item['supervisionDate']) ?>
                                            </p>
                                            <p class="text-xs text-slate-400 mt-0.5">
                                                ผู้นิเทศ: <?= htmlspecialchars($item['supervisorName']) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="text-right space-y-1">
                                        <span class="px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold inline-block">
                                            ครูรับทราบแล้ว
                                        </span>
                                        <div class="flex items-center justify-end gap-1 text-amber-400 text-xs">
                                            <span class="text-base font-bold text-slate-800 mr-1"><?= number_format($item['overallScore'], 2) ?></span>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- 6 Criterion Scores Progress Bars Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-xs">
                                    
                                    <!-- 1. การจัดการเรียนการสอน -->
                                    <div class="space-y-1">
                                        <div class="flex justify-between font-medium text-slate-700">
                                            <span>การจัดการเรียนการสอน</span>
                                            <span class="font-bold text-slate-800"><?= number_format($item['scores']['teaching'] ?? 5.0, 1) ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= (($item['scores']['teaching'] ?? 5.0)/5)*100 ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- 2. สื่อและเทคโนโลยีการสอน -->
                                    <div class="space-y-1">
                                        <div class="flex justify-between font-medium text-slate-700">
                                            <span>สื่อและเทคโนโลยีการสอน</span>
                                            <span class="font-bold text-slate-800"><?= number_format($item['scores']['media'] ?? 4.5, 1) ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= (($item['scores']['media'] ?? 4.5)/5)*100 ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- 3. การวัดและประเมินผล -->
                                    <div class="space-y-1">
                                        <div class="flex justify-between font-medium text-slate-700">
                                            <span>การวัดและประเมินผล</span>
                                            <span class="font-bold text-slate-800"><?= number_format($item['scores']['eval'] ?? 4.5, 1) ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= (($item['scores']['eval'] ?? 4.5)/5)*100 ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- 4. บรรยากาศในชั้นเรียน -->
                                    <div class="space-y-1">
                                        <div class="flex justify-between font-medium text-slate-700">
                                            <span>บรรยากาศในชั้นเรียน</span>
                                            <span class="font-bold text-slate-800"><?= number_format($item['scores']['climate'] ?? 5.0, 1) ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= (($item['scores']['climate'] ?? 5.0)/5)*100 ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- 5. กิจกรรมการเรียนรู้ -->
                                    <div class="space-y-1">
                                        <div class="flex justify-between font-medium text-slate-700">
                                            <span>กิจกรรมการเรียนรู้</span>
                                            <span class="font-bold text-slate-800"><?= number_format($item['scores']['activity'] ?? 4.5, 1) ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= (($item['scores']['activity'] ?? 4.5)/5)*100 ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- 6. การมีส่วนร่วมของนักเรียน -->
                                    <div class="space-y-1">
                                        <div class="flex justify-between font-medium text-slate-700">
                                            <span>การมีส่วนร่วมของนักเรียน</span>
                                            <span class="font-bold text-slate-800"><?= number_format($item['scores']['engagement'] ?? 4.5, 1) ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                            <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= (($item['scores']['engagement'] ?? 4.5)/5)*100 ?>%"></div>
                                        </div>
                                    </div>

                                </div>

                                <!-- Strengths Box (จุดเด่น) -->
                                <div class="p-4 bg-[#f0fdf4] border border-emerald-100 rounded-xl text-xs space-y-1">
                                    <p class="font-bold text-emerald-800">จุดเด่น</p>
                                    <p class="text-emerald-900 leading-relaxed"><?= htmlspecialchars($item['strengths']) ?></p>
                                </div>

                                <!-- Recommendations Box (ข้อเสนอแนะเพื่อการพัฒนา) -->
                                <div class="p-4 bg-[#fefce8] border border-amber-100 rounded-xl text-xs space-y-1">
                                    <p class="font-bold text-amber-800">ข้อเสนอแนะเพื่อการพัฒนา</p>
                                    <p class="text-amber-900 leading-relaxed"><?= htmlspecialchars($item['recommendations']) ?></p>
                                </div>

                                <!-- Footer timestamp -->
                                <div class="text-[11px] text-slate-400">
                                    ครูรับทราบเมื่อ <?= htmlspecialchars($item['acknowledgedAt']) ?>
                                </div>

                            </div>
                        <?php endif; ?>

                    <?php endforeach; ?>

                </div>

                <!-- Right Column (Analytics & Teacher List) -->
                <div class="lg:col-span-4 space-y-6">
                    
                    <!-- Card 1: คะแนนเฉลี่ยรายหัวข้อ -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-chart-line text-indigo-600"></i>
                            <h3 class="font-bold text-slate-800 text-sm">คะแนนเฉลี่ยรายหัวข้อ</h3>
                        </div>

                        <div class="space-y-3 text-xs">
                            <?php foreach ($topic_averages as $title => $score): ?>
                                <div class="space-y-1">
                                    <div class="flex justify-between text-slate-700">
                                        <span><?= $title ?></span>
                                        <span class="font-bold text-slate-800"><?= number_format($score, 2) ?></span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                        <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= ($score/5)*100 ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Card 2: ผลการนิเทศรายครู -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
                        <h3 class="font-bold text-slate-800 text-sm">ผลการนิเทศรายครู</h3>

                        <div class="space-y-3">
                            <?php foreach ($teacher_rankings as $t): ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full <?= $t['bg'] ?> text-white font-bold flex items-center justify-center text-xs">
                                            <?= $t['initial'] ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800 text-xs"><?= $t['name'] ?></p>
                                            <p class="text-[10px] text-slate-400">นิเทศแล้ว <?= $t['count'] ?> ครั้ง</p>
                                        </div>
                                    </div>
                                    <span class="font-bold text-slate-800 text-sm"><?= $t['score'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>

            </div>

        </main>
    </div>

</body>
</html>