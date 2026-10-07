<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'calendar';

// สร้าง CSRF Token หากยังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year  = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($month < 1) { $month = 1; }
if ($month > 12) { $month = 12; }

$prev_month = ($month == 1) ? 12 : $month - 1;
$prev_year  = ($month == 1) ? $year - 1 : $year;

$next_month = ($month == 12) ? 1 : $month + 1;
$next_year  = ($month == 12) ? $year + 1 : $year;

$thai_months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
$thai_year = $year + 543;

$first_day_of_month  = mktime(0, 0, 0, $month, 1, $year);
$total_days_in_month = date('t', $first_day_of_month);
$start_day_of_week   = date('w', $first_day_of_month);

$month_start_date = sprintf('%04d-%02d-01', $year, $month);
$month_end_date   = sprintf('%04d-%02d-%02d', $year, $month, $total_days_in_month);

// ดึงรายการงานที่คาบเกี่ยวในเดือนนี้ พร้อมชื่อผู้ใช้งาน (LEFT JOIN ตาราง users)
// ดึงข้อมูลรายการงานทั้งหมดในเดือน พร้อมชื่อผู้บันทึก ( u.name )
$all_month_events = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, 
               COALESCE(u.name, u.username, 'ไม่ระบุผู้ลงงาน') AS user_name,
               DATE(a.appointment_date) AS start_date,
               COALESCE(a.end_date, DATE(a.appointment_date)) AS actual_end_date
        FROM appointments a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE DATE(a.appointment_date) <= ? 
          AND COALESCE(a.end_date, DATE(a.appointment_date)) >= ? 
        ORDER BY a.appointment_date ASC, a.start_time ASC
    ");
    $stmt->execute([$month_end_date, $month_start_date]);
    $all_month_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_month_events = [];
}

// ดึงรายการผู้ใช้ทั้งหมดเพื่อนำไปใส่ใน Dropdown
$usersList = [];
try {
    $stmt_u = $pdo->query("SELECT id, COALESCE(name, fullname, username) AS display_name FROM users ORDER BY id ASC");
    $usersList = $stmt_u->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usersList = [];
}

// จัดกลุ่มงานลงในแต่ละวัน
$events_by_date = [];
for ($day = 1; $day <= $total_days_in_month; $day++) {
    $current_date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $events_by_date[$current_date_str] = [];

    foreach ($all_month_events as $ev) {
        $s_date = $ev['start_date'];
        $e_date = $ev['actual_end_date'];

        if ($current_date_str >= $s_date && $current_date_str <= $e_date) {
            $events_by_date[$current_date_str][] = $ev;
        }
    }
}

$status = $_GET['status'] ?? '';
$action = $_GET['action'] ?? '';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ปฏิทินงานและนัดหมาย - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'Inter', 'sans-serif'] },
                    colors: { brandGreen: '#059669' }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>

<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <?php include 'sidebar_admin.php'; ?>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100">

        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-10 shrink-0 z-10 shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-slate-900 tracking-tight">ปฏิทินงานและนัดหมาย (Calendar)</h2>
                <p class="text-sm text-slate-500 mt-0.5">จัดการและตรวจสอบตารางเวลางานติดตั้งหรือนัดหมายลูกค้า</p>
            </div>
            <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-house text-emerald-600"></i> หน้าหลัก
            </a>
        </header>

        <div class="flex-1 overflow-y-auto p-8 space-y-6">

            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">เดือน <?= $thai_months[$month] ?> <?= $thai_year ?></h3>
                    <p class="text-sm text-slate-500 mt-1">คลิกที่ช่องวันในปฏิทินเพื่อดูช่วงเวลาและจัดการงานติดตั้ง</p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center bg-slate-100 p-1 rounded-2xl border border-slate-200 text-sm font-semibold">
                        <a href="calendar_admin.php?month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="px-4 py-2 rounded-xl transition-all hover:bg-white text-slate-600">เดือนก่อนหน้า</a>
                        <a href="calendar_admin.php?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="px-4 py-2 rounded-xl transition-all <?= ($month == date('n') && $year == date('Y')) ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white' ?>">เดือนนี้</a>
                        <a href="calendar_admin.php?month=<?= $next_month ?>&year=<?= $next_year ?>" class="px-4 py-2 rounded-xl transition-all hover:bg-white text-slate-600">เดือนถัดไป</a>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden p-6">
                <div class="grid grid-cols-7 gap-3 mb-3 text-center">
                    <div class="text-sm font-bold text-rose-500 uppercase tracking-wider py-2">อาทิตย์</div>
                    <div class="text-sm font-bold text-slate-600 uppercase tracking-wider py-2">จันทร์</div>
                    <div class="text-sm font-bold text-slate-600 uppercase tracking-wider py-2">อังคาร</div>
                    <div class="text-sm font-bold text-slate-600 uppercase tracking-wider py-2">พุธ</div>
                    <div class="text-sm font-bold text-slate-600 uppercase tracking-wider py-2">พฤหัสบดี</div>
                    <div class="text-sm font-bold text-slate-600 uppercase tracking-wider py-2">ศุกร์</div>
                    <div class="text-sm font-bold text-slate-600 uppercase tracking-wider py-2">เสาร์</div>
                </div>

                <div class="grid grid-cols-7 gap-3">
                    <?php for ($i = 0; $i < $start_day_of_week; $i++): ?>
                        <div class="h-36 bg-slate-50/50 rounded-2xl border border-slate-100 opacity-40"></div>
                    <?php endfor;

                    for ($day = 1; $day <= $total_days_in_month; $day++):
                        $current_date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $is_today = ($current_date_str == date('Y-m-d'));
                        $day_events = $events_by_date[$current_date_str] ?? [];
                        $formatted_date_str = $day . ' ' . $thai_months[$month] . ' ' . $thai_year;
                    ?>
                        <div onclick="openDayModal('<?= $current_date_str ?>', '<?= $formatted_date_str ?>')"
                            class="h-36 bg-white rounded-2xl border <?= $is_today ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200' ?> p-3 flex flex-col justify-between hover:border-emerald-600 hover:shadow-md transition-all cursor-pointer relative overflow-hidden group">

                            <div class="flex items-center justify-between">
                                <span class="w-8 h-8 flex items-center justify-center rounded-xl text-sm font-bold <?= $is_today ? 'bg-brandGreen text-white shadow-sm' : 'text-slate-700 group-hover:bg-emerald-50 group-hover:text-emerald-700' ?>">
                                    <?= $day ?>
                                </span>
                                <?php if (!empty($day_events)): ?>
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <?php endif; ?>
                            </div>

                            <div class="flex-1 overflow-y-auto space-y-1 mt-2">
                                <?php foreach (array_slice($day_events, 0, 3) as $ev):
                                    $status_ev = $ev['status'] ?? 'pending';
                                    $badge_class = match ($status_ev) {
                                        'in_progress' => 'bg-blue-100 text-blue-800 border-blue-300',
                                        'completed'   => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                        'cancelled'   => 'bg-rose-100 text-rose-800 border-rose-300',
                                        default       => 'bg-amber-100 text-amber-800 border-amber-300',
                                    };
                                    $time_display = !empty($ev['start_time']) ? substr($ev['start_time'], 0, 5) : '';
                                    $u_name = $ev['user_name'] ?? '';
                                ?>
                                    <div class="<?= $badge_class ?> text-[11px] font-bold px-2 py-1 rounded-lg truncate border shadow-2xs flex items-center justify-between gap-1">
                                        <div class="flex items-center gap-1 truncate">
                                            <?php if ($time_display): ?><span class="opacity-75 shrink-0"><?= $time_display ?></span><?php endif; ?>
                                            <span class="truncate"><?= htmlspecialchars($ev['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <?php if (!empty($u_name) && $u_name !== 'ไม่ระบุ'): ?>
                                            <span class="text-[10px] opacity-80 shrink-0 font-normal">
                                                <i class="fa-regular fa-user"></i> <?= htmlspecialchars($u_name, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($day_events) > 3): ?>
                                    <div class="text-[10px] text-slate-400 font-bold px-1">+ อีก <?= count($day_events) - 3 ?> รายการ</div>
                                <?php endif; ?>
                            </div>

                            <div class="absolute bottom-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                <span class="w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs shadow-md">
                                    <i class="fa-solid fa-plus"></i>
                                </span>
                            </div>
                        </div>
                    <?php endfor;

                    $total_cells = $start_day_of_week + $total_days_in_month;
                    $remaining_cells = (7 - ($total_cells % 7)) % 7;
                    for ($i = 0; $i < $remaining_cells; $i++):
                    ?>
                        <div class="h-36 bg-slate-50/50 rounded-2xl border border-slate-100 opacity-40"></div>
                    <?php endfor; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal จัดการนัดหมาย -->
    <div id="dayModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-8 shadow-2xl border border-slate-200 space-y-6 transform transition-all max-h-[90vh] overflow-y-auto">

            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 id="modalTitle" class="text-lg font-bold text-slate-900">รายการนัดหมายประจำวัน</h3>
                    <p class="text-sm text-slate-500" id="modalDateSubtitle">วันที่เลือก</p>
                </div>
                <button onclick="closeModal()" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="space-y-3" id="modalEventsList"></div>

            <!-- ฟอร์มบันทึกนัดหมาย -->
            <form id="appointmentForm" action="calendar_process_admin.php" method="POST" onsubmit="showLoadingPopup()" class="space-y-4 pt-4 border-t border-slate-100">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="id" id="formEventId" value="">
                <input type="hidden" name="redirect_month" value="<?= $month ?>">
                <input type="hidden" name="redirect_year" value="<?= $year ?>">

                <div class="flex items-center justify-between">
                    <h4 id="formHeading" class="text-xs font-bold text-slate-700 uppercase tracking-wider">เพิ่มงานใหม่</h4>
                    <button type="button" id="cancelEditBtn" onclick="resetFormToCreate()" class="hidden text-xs text-rose-600 font-bold hover:underline">ยกเลิกการแก้ไข</button>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-slate-700">หัวข้อ / ชื่องาน</label>
                    <input type="text" id="inputTitle" name="title" required placeholder="เช่น ติดตั้งแผงโซล่าเซลล์..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-sm bg-slate-50/50 focus:outline-none focus:border-emerald-600 font-medium">
                </div>

                <!-- เลือกผู้ใช้งาน / ผู้รับผิดชอบ -->
                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-slate-700">ผู้รับผิดชอบ / ผู้ลงงาน</label>
                    <select id="inputUserId" name="user_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-sm bg-slate-50/50 focus:outline-none focus:border-emerald-600 font-medium">
                        <option value="">-- ใช้ผู้ใช้งานปัจจุบันที่ล็อกอิน --</option>
                        <?php foreach ($usersList as $usr): ?>
                            <option value="<?= $usr['id'] ?>"><?= htmlspecialchars($usr['display_name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700">วันเริ่มต้น</label>
                        <input type="date" id="inputStartDate" name="start_date" required onchange="validateDates()" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-sm bg-slate-50/50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700">วันสิ้นสุด</label>
                        <input type="date" id="inputEndDate" name="end_date" required onchange="validateDates()" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-sm bg-slate-50/50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700">เวลาเริ่มต้น</label>
                        <input type="time" id="inputStartTime" name="start_time" value="09:00" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-sm bg-slate-50/50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700">เวลาสิ้นสุด</label>
                        <input type="time" id="inputEndTime" name="end_time" value="12:00" required class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-sm bg-slate-50/50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-slate-700">สถานะงาน</label>
                    <select id="inputStatus" name="status" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-sm bg-slate-50/50 focus:outline-none focus:border-emerald-600 font-medium">
                        <option value="pending">รอดำเนินการ (Pending)</option>
                        <option value="in_progress">กำลังดำเนินการ (In Progress)</option>
                        <option value="completed">เสร็จสิ้น (Completed)</option>
                        <option value="cancelled">ยกเลิก (Cancelled)</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-sm font-semibold text-slate-700">รายละเอียดเพิ่มเติม</label>
                    <textarea id="inputDetails" name="details" rows="2" placeholder="สถานที่, อุปกรณ์..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-sm bg-slate-50/50 focus:outline-none focus:border-emerald-600 font-medium"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-2xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-all">ปิด</button>
                    <button type="submit" id="submitBtn" class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-2.5 rounded-2xl text-sm font-semibold shadow-md shadow-emerald-600/20 transition-all">บันทึกนัดหมาย</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading Popup -->
    <div id="loadingPopup" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-xs w-full p-6 shadow-2xl border border-slate-200 text-center space-y-4">
            <div class="w-12 h-12 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
            <div>
                <h3 class="text-base font-bold text-slate-900">กำลังบันทึกข้อมูล...</h3>
                <p class="text-xs text-slate-500 mt-1">กรุณารอสักครู่ ระบบกำลังประมวลผล</p>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div id="deleteConfirmModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 text-center space-y-4">
            <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto text-2xl shadow-inner">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900">ยืนยันการลบข้อมูล?</h3>
                <p class="text-sm text-slate-500 mt-1">คุณแน่ใจหรือไม่ว่าต้องการลบรายการนัดหมายนี้? การกระทำนี้ไม่สามารถย้อนกลับได้</p>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeDeleteModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-2.5 rounded-2xl text-sm font-semibold transition-all">ยกเลิก</button>
                <button type="button" id="confirmDeleteBtn" onclick="executeDelete()" class="flex-1 bg-rose-600 hover:bg-rose-700 text-white py-2.5 rounded-2xl text-sm font-semibold shadow-md transition-all">ลบข้อมูล</button>
            </div>
        </div>
    </div>

    <!-- Success Popup -->
    <?php if ($status === 'success'): ?>
        <div id="successPopup" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 text-center space-y-4 animate-in fade-in zoom-in duration-200">
                <div class="w-16 h-16 bg-emerald-100 text-brandGreen rounded-full flex items-center justify-center mx-auto text-2xl shadow-inner">
                    <i class="fa-solid fa-check"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">สำเร็จเรียบร้อย!</h3>
                    <p class="text-sm text-slate-500 mt-1">
                        <?= $action === 'edit' ? 'แก้ไขข้อมูลนัดหมายเรียบร้อยแล้ว' : ($action === 'delete' ? 'ลบรายการนัดหมายเรียบร้อยแล้ว' : 'เพิ่มนัดหมายใหม่เรียบร้อยแล้ว') ?>
                    </p>
                </div>
                <button onclick="closeSuccessPopup()" class="w-full bg-brandGreen hover:bg-emerald-700 text-white py-2.5 rounded-2xl text-sm font-semibold shadow-md transition-all">ตกลง</button>
            </div>
        </div>
    <?php endif; ?>

    <script>
        const allEvents = <?= json_encode($events_by_date, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let deleteTargetId = null;
        let currentSelectedDate = null;

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.toString().replace(/[&<>"']/g, m => map[m]);
        }

        function openDayModal(dateStr, formattedDate) {
            currentSelectedDate = dateStr;
            document.getElementById('modalTitle').innerText = 'รายการนัดหมายประจำวันที่ ' + formattedDate;
            document.getElementById('modalDateSubtitle').innerText = dateStr;

            renderEventList(dateStr);
            resetFormToCreate();
            
            document.getElementById('inputStartDate').value = dateStr;
            document.getElementById('inputEndDate').value = dateStr;

            document.getElementById('dayModal').classList.remove('hidden');
        }

        function validateDates() {
            let start = document.getElementById('inputStartDate').value;
            let end = document.getElementById('inputEndDate').value;

            if (start && end && end < start) {
                document.getElementById('inputEndDate').value = start;
            }
        }

        function renderEventList(dateStr) {
            let listContainer = document.getElementById('modalEventsList');
            listContainer.innerHTML = '';

            if (allEvents[dateStr] && allEvents[dateStr].length > 0) {
                allEvents[dateStr].forEach(ev => {
                    let startTime = ev.start_time ? ev.start_time.substring(0, 5) : '';
                    let endTime = ev.end_time ? ' - ' + ev.end_time.substring(0, 5) : '';
                    let statusBadge = matchStatusBadge(ev.status || 'pending');
                    let sDate = ev.start_date;
                    let eDate = ev.actual_end_date;
                    let dateRangeText = (sDate === eDate) ? sDate : `${sDate} ถึง ${eDate}`;
                    let userName = ev.user_name || 'ไม่ระบุผู้ลงงาน';

                    let itemDiv = document.createElement('div');
                    itemDiv.className = 'bg-slate-50 border border-slate-200/80 p-4 rounded-2xl space-y-2';

                    itemDiv.innerHTML = `
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700">
                                <i class="fa-regular fa-calendar-days mr-1 text-slate-400"></i> ${escapeHtml(dateRangeText)}
                                <span class="ml-2"><i class="fa-regular fa-clock mr-1 text-slate-400"></i> ${escapeHtml(startTime)}${escapeHtml(endTime)} น.</span>
                            </span>
                            ${statusBadge}
                        </div>
                        <h5 class="text-sm font-bold text-slate-900">${escapeHtml(ev.title)}</h5>
                        <p class="text-xs text-slate-500">${escapeHtml(ev.details || 'ไม่มีรายละเอียดเพิ่มเติม')}</p>
                        
                        <div class="text-xs text-slate-600 bg-white p-2 rounded-xl border border-slate-200/60 flex items-center justify-between mt-1">
                            <span class="font-medium text-slate-500"><i class="fa-solid fa-user-check text-emerald-600 mr-1.5"></i> ผู้รับผิดชอบ:</span>
                            <span class="font-bold text-slate-800">${escapeHtml(userName)}</span>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200/60">
                            <button type="button" onclick="loadEventToEditById(${ev.id})" class="px-3 py-1.5 bg-sky-50 text-sky-700 hover:bg-sky-100 rounded-xl text-xs font-semibold transition-all">
                                <i class="fa-solid fa-pen-to-square mr-1"></i> แก้ไข
                            </button>
                            <button type="button" onclick="confirmDelete(${ev.id})" class="px-3 py-1.5 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-xl text-xs font-semibold transition-all">
                                <i class="fa-solid fa-trash mr-1"></i> ลบ
                            </button>
                        </div>
                    `;
                    listContainer.appendChild(itemDiv);
                });
            } else {
                listContainer.innerHTML = '<p class="text-sm text-slate-400 text-center py-2">ยังไม่มีนัดหมายในวันนี้ สามารถเพิ่มงานใหม่ได้ด้านล่าง</p>';
            }
        }

        function matchStatusBadge(status) {
            if (status === 'in_progress') return '<span class="bg-blue-100 text-blue-800 border border-blue-300 px-2.5 py-0.5 rounded-full text-[10px] font-bold">กำลังดำเนินการ</span>';
            if (status === 'completed') return '<span class="bg-emerald-100 text-emerald-800 border border-emerald-300 px-2.5 py-0.5 rounded-full text-[10px] font-bold">เสร็จสิ้น</span>';
            if (status === 'cancelled') return '<span class="bg-rose-100 text-rose-800 border border-rose-300 px-2.5 py-0.5 rounded-full text-[10px] font-bold">ยกเลิก</span>';
            return '<span class="bg-amber-100 text-amber-800 border border-amber-300 px-2.5 py-0.5 rounded-full text-[10px] font-bold">รอดำเนินการ</span>';
        }

        function loadEventToEditById(id) {
            if (!currentSelectedDate || !allEvents[currentSelectedDate]) return;
            let ev = allEvents[currentSelectedDate].find(e => e.id == id);
            if (!ev) return;

            document.getElementById('formEventId').value = ev.id;
            document.getElementById('inputTitle').value = ev.title || '';
            document.getElementById('inputUserId').value = ev.user_id || '';
            document.getElementById('inputStartDate').value = ev.start_date || currentSelectedDate;
            document.getElementById('inputEndDate').value = ev.actual_end_date || ev.start_date || currentSelectedDate;
            document.getElementById('inputStartTime').value = ev.start_time ? ev.start_time.substring(0, 5) : '09:00';
            document.getElementById('inputEndTime').value = ev.end_time ? ev.end_time.substring(0, 5) : '12:00';
            document.getElementById('inputStatus').value = ev.status || 'pending';
            document.getElementById('inputDetails').value = ev.details || '';

            document.getElementById('formHeading').innerText = 'แก้ไขรายการนัดหมาย (ID: ' + ev.id + ')';
            document.getElementById('submitBtn').innerText = 'บันทึกการแก้ไข';
            document.getElementById('cancelEditBtn').classList.remove('hidden');

            document.getElementById('appointmentForm').scrollIntoView({ behavior: 'smooth' });
        }

        function resetFormToCreate() {
            document.getElementById('formEventId').value = '';
            document.getElementById('inputTitle').value = '';
            document.getElementById('inputUserId').value = '';
            document.getElementById('inputStartDate').value = currentSelectedDate || '';
            document.getElementById('inputEndDate').value = currentSelectedDate || '';
            document.getElementById('inputStartTime').value = '09:00';
            document.getElementById('inputEndTime').value = '12:00';
            document.getElementById('inputStatus').value = 'pending';
            document.getElementById('inputDetails').value = '';

            document.getElementById('formHeading').innerText = 'เพิ่มงานใหม่';
            document.getElementById('submitBtn').innerText = 'บันทึกนัดหมาย';
            document.getElementById('cancelEditBtn').classList.add('hidden');
        }

        function showLoadingPopup() {
            document.getElementById('loadingPopup').classList.remove('hidden');
        }

        function confirmDelete(id) {
            deleteTargetId = id;
            document.getElementById('deleteConfirmModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            deleteTargetId = null;
            document.getElementById('deleteConfirmModal').classList.add('hidden');
        }

        function executeDelete() {
            if (deleteTargetId) {
                document.getElementById('deleteConfirmModal').classList.add('hidden');
                document.getElementById('loadingPopup').classList.remove('hidden');
                window.location.href = `calendar_delete_admin.php?delete_id=${deleteTargetId}&month=<?= $month ?>&year=<?= $year ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>`;
            }
        }

        function closeModal() {
            document.getElementById('dayModal').classList.add('hidden');
        }

        function closeSuccessPopup() {
            const popup = document.getElementById('successPopup');
            if (popup) popup.remove();
            const url = new URL(window.location.href);
            url.searchParams.delete('status');
            url.searchParams.delete('action');
            window.history.replaceState({}, document.title, url);
        }
    </script>
</body>

</html>