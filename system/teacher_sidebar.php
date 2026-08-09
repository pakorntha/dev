<?php
if (!function_exists('sis4_teacher_sidebar_link_class')) {
    function sis4_teacher_sidebar_link_class(bool $active): string
    {
        return $active
            ? 'flex items-center gap-3 px-3 py-2.5 bg-blue-600 text-white rounded transition-colors shadow-sm'
            : 'flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors';
    }
}

if (!function_exists('sis4_teacher_sidebar_render')) {
    function sis4_teacher_sidebar_render(string $displayName, string $initial, string $roleText = 'ครูผู้สอน', string $logoutPath = '../../system/logout.php', string $extraAsideClass = ''): void
    {
        $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
        $dashboardActive = $currentPage === 'home.php';
        $homeworkActive = $currentPage === 'homework.php';
        $checkingActive = $currentPage === 'checking.php';
        $documentsActive = in_array($currentPage, ['incoming.php', 'documents.php'], true);
        $studentListActive = $currentPage === 'student_list.php';
        $attendanceActive = $currentPage === 'atten.php';
        $lessionPlanActive = $currentPage === 'lession_plan.php';
        $supervisionActive = $currentPage === 'supervision.php';
        $leaveActive = $currentPage === 'leave.php';
        $calendarActive = $currentPage === 'calendar.php';
        $officialTripActive = $currentPage === 'official_trip.php';
        $asideClass = trim('w-64 bg-gray-900 text-gray-300 flex flex-col h-full flex-shrink-0 ' . $extraAsideClass);
        ?>
        <aside class="<?= htmlspecialchars($asideClass, ENT_QUOTES, 'UTF-8') ?>">
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
                    <a href="home.php" class="<?= sis4_teacher_sidebar_link_class($dashboardActive) ?>">
                        <i class="fa-solid fa-border-all w-5 text-center"></i>
                        แดชบอร์ด
                    </a>
                </div>

                <div>
                    <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานรายวิชาที่รับผิดชอบ
                    </p>
                    <ul class="space-y-1">
                        <li>
                            <a href="homework.php" class="<?= sis4_teacher_sidebar_link_class($homeworkActive) ?>">
                                <i class="fa-solid fa-inbox w-5 text-center"></i>
                                มอบหมายงาน / การบ้าน
                            </a>
                        </li>
                    </ul>
                </div>

                <div>
                    <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานสารบรรณ</p>
                    <ul class="space-y-1">
                        <li>
                            <a href="incoming.php" class="<?= sis4_teacher_sidebar_link_class($documentsActive) ?>">
                                <i class="fa-solid fa-inbox w-5 text-center"></i>
                                หนังสือรับ
                            </a>
                        </li>
                        <li><a href="#"
                                class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i
                                    class="fa-regular fa-note-sticky w-5 text-center"></i> บันทึกภายใน</a></li>
                        <li><a href="#"
                                class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i
                                    class="fa-solid fa-share-nodes w-5 text-center"></i> หนังสือเวียน</a></li>
                        <li><a href="#"
                                class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i
                                    class="fa-solid fa-list-check w-5 text-center"></i> งานที่มอบหมาย</a></li>
                    </ul>
                </div>

                <div>
                    <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานบุคคล</p>
                    <ul class="space-y-1">
                        <li>
                            <a href="checking.php" class="<?= sis4_teacher_sidebar_link_class($checkingActive) ?>">
                                <i class="fa-solid fa-user-clock w-5 text-center"></i>
                                ลงเวลาปฏิบัติงาน
                            </a>
                        </li>
                        <li><a href="leave.php" class="<?= sis4_teacher_sidebar_link_class($leaveActive) ?>"><i
                                    class="fa-regular fa-calendar-minus w-5 text-center"></i> การลา</a></li>
                        <li><a href="official_trip.php" class="<?= sis4_teacher_sidebar_link_class($officialTripActive) ?>"><i
                                    class="fa-solid fa-plane w-5 text-center"></i> ไปราชการและอบรม</a></li>
                    </ul>
                </div>

                <div>
                    <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานวิชาการ</p>
                    <ul class="space-y-1">
                        <li>
                            <a href="student_list.php" class="<?= sis4_teacher_sidebar_link_class($studentListActive) ?>">
                                <i class="fa-solid fa-users w-5 text-center"></i>
                                นักเรียนและห้องเรียน
                            </a>
                        </li>
                        <li>
                            <a href="atten.php" class="<?= sis4_teacher_sidebar_link_class($attendanceActive) ?>">
                                <i class="fa-solid fa-chalkboard-user w-5 text-center"></i>
                                การมาเรียนนักเรียน
                            </a>
                        </li>
                        <li><a href="lession_plan.php" class=" <?= sis4_teacher_sidebar_link_class($lessionPlanActive) ?>"><i
                                    class="fa-solid fa-book-open w-5 text-center"></i> แผนการสอน</a></li>
                        <li><a href="supervision.php" class=" <?= sis4_teacher_sidebar_link_class($supervisionActive) ?>"><i
                                    class="fa-solid fa-eye w-5 text-center"></i> นิเทศการสอน</a></li>
                        <li><a href="#"
                                class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i
                                    class="fa-solid fa-shield-halved w-5 text-center"></i> ประกันคุณภาพภายใน</a>
                        </li>
                        <li><a href="wellbeing.php"
                                class="flex items-center gap-3 px-3 py-2 hover:bg-gray-800 hover:text-white rounded transition-colors"><i
                                    class="fa-solid fa-shield-heart w-5 text-center"></i> ดูแลสุขภาวะนักเรียน</a>
                        </li>
                    </ul>
                </div>

                <!-- เพิ่มหมวดหมู่งานทั่วไปและปฏิทินโรงเรียน -->
                <div>
                    <p class="px-3 text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">งานทั่วไป</p>
                    <ul class="space-y-1">
                        <li>
                            <a href="calendar.php" class="<?= sis4_teacher_sidebar_link_class($calendarActive) ?>">
                                <i class="fa-solid fa-calendar-days w-5 text-center"></i>
                                ปฏิทินโรงเรียน
                            </a>
                        </li>
                    </ul>
                </div>

            </nav>

            <div class="p-4 border-t border-gray-800">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded bg-blue-600 flex items-center justify-center text-white font-bold text-sm">
                        <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <p class="text-sm text-white font-medium truncate">
                            <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($roleText, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <a href="<?= htmlspecialchars($logoutPath, ENT_QUOTES, 'UTF-8') ?>"
                        class="text-gray-400 hover:text-red-400 transition-colors" title="ออกจากระบบ">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        </aside>
        <?php
    }
}
?>