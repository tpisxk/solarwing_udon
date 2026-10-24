<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'work_progress';
$message = '';
$message_type = '';

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// ดึงข้อมูลงานจากตาราง installations พร้อมคำนวณค่าเฉลี่ยความคืบหน้าจริงจากตาราง installation_tasks และรองรับการค้นหา
try {
    if ($search_query !== '') {
        $stmt = $pdo->prepare("
            SELECT i.*, COALESCE(AVG(t.progress), 0) as calculated_progress 
            FROM installations i 
            LEFT JOIN installation_tasks t ON i.id = t.installation_id 
            WHERE i.name LIKE ? 
            GROUP BY i.id 
            ORDER BY i.id DESC
        ");
        $stmt->execute(['%' . $search_query . '%']);
    } else {
        $stmt = $pdo->query("
            SELECT i.*, COALESCE(AVG(t.progress), 0) as calculated_progress 
            FROM installations i 
            LEFT JOIN installation_tasks t ON i.id = t.installation_id 
            GROUP BY i.id 
            ORDER BY i.id DESC
        ");
    }
    $works = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $works = [];
}

// คำนวณสถิติเบื้องต้นจากข้อมูลจริงทั้งหมดในระบบ
try {
    $stmt_all = $pdo->query("
        SELECT i.*, COALESCE(AVG(t.progress), 0) as calculated_progress 
        FROM installations i 
        LEFT JOIN installation_tasks t ON i.id = t.installation_id 
        GROUP BY i.id
    ");
    $all_works_stats = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_works_stats = [];
}

$total_works = count($all_works_stats);
$completed_works = 0;
$in_progress_works = 0;

foreach ($all_works_stats as $w) {
    $status = $w['status'] ?? '';
    if ($status === 'เสร็จสิ้น' || $status === 'Completed' || $w['calculated_progress'] >= 100) {
        $completed_works++;
    } else {
        $in_progress_works++;
    }
}

// ตั้งค่าระบบแบ่งหน้า (แสดง 5 แถวต่อหน้า)
$total_filtered_works = count($works);
$per_page = 5;
$total_pages = max(1, ceil($total_filtered_works / $per_page));
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;
$paginated_works = array_slice($works, $offset, $per_page);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ความคืบหน้าของงาน (Work Progress) - Solarwing Engineer</title>
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
    <style>::-webkit-scrollbar { width: 6px; height: 6px; } ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }</style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar สำหรับ Desktop และ Mobile Drawer -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out">
        <?php if (file_exists('sidebar_engineer.php')) include 'sidebar_engineer.php'; ?>
    </div>

    <!-- Backdrop สำหรับปิด Sidebar บนมือถือ -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-slate-900 text-xl p-2 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">ความคืบหน้าของงาน (Work Progress)</h2>
                    <p class="text-[11px] md:text-xs text-slate-500 mt-0.5">ติดตามสถานะและการดำเนินงานติดตั้งระบบโซล่าเซลล์จากฐานข้อมูล</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="add_installation_engineer.php" class="bg-brandGreen hover:bg-emerald-700 text-white px-3 md:px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold transition-all flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-plus text-xs"></i> <span class="hidden sm:inline">เพิ่มงานติดตั้งใหม่</span>
                </a>
            </div>
        </header>

        <!-- Main Content Area -->
        <div class="flex-1 overflow-y-auto p-4 md:p-8 w-full">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- Statistic Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-6">
                    <div class="bg-white p-5 md:p-6 rounded-3xl border border-slate-200/85 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg md:text-xl shadow-inner shrink-0">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <div>
                            <p class="text-[11px] md:text-xs font-bold text-slate-400 uppercase tracking-wider">กำลังดำเนินการ</p>
                            <h3 class="text-xl md:text-2xl font-extrabold text-slate-800 mt-1"><?= $in_progress_works ?> งาน</h3>
                        </div>
                    </div>
                    <div class="bg-white p-5 md:p-6 rounded-3xl border border-slate-200/85 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-emerald-50 text-brandGreen flex items-center justify-center text-lg md:text-xl shadow-inner shrink-0">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <p class="text-[11px] md:text-xs font-bold text-slate-400 uppercase tracking-wider">เสร็จสิ้นแล้ว</p>
                            <h3 class="text-xl md:text-2xl font-extrabold text-slate-800 mt-1"><?= $completed_works ?> งาน</h3>
                        </div>
                    </div>
                    <div class="bg-white p-5 md:p-6 rounded-3xl border border-slate-200/85 shadow-sm flex items-center gap-4">
                        <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg md:text-xl shadow-inner shrink-0">
                            <i class="fa-solid fa-briefcase"></i>
                        </div>
                        <div>
                            <p class="text-[11px] md:text-xs font-bold text-slate-400 uppercase tracking-wider">งานทั้งหมดในระบบ</p>
                            <h3 class="text-xl md:text-2xl font-extrabold text-slate-800 mt-1"><?= $total_works ?> งาน</h3>
                        </div>
                    </div>
                </div>

                <!-- Table Section -->
                <div class="bg-white rounded-3xl border border-slate-200/85 shadow-sm overflow-hidden flex flex-col justify-between">
                    <div class="p-4 md:p-6 border-b border-slate-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <h3 class="text-sm md:text-base font-bold text-slate-900">รายการโครงการและสถานะการติดตั้ง</h3>
                        
                        <!-- ช่องค้นหาข้อมูลโครงการ -->
                        <form method="GET" action="work_progress.php" class="w-full sm:w-auto">
                            <div class="relative flex items-center">
                                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="ค้นหาชื่อโครงการ..." class="w-full sm:w-64 px-4 py-2 rounded-xl border border-slate-200 text-xs md:text-sm focus:outline-none focus:border-brandGreen pr-16 bg-slate-50/50">
                                <div class="absolute right-2 flex items-center gap-1">
                                    <?php if ($search_query !== ''): ?>
                                        <a href="work_progress.php" class="text-slate-400 hover:text-slate-600 px-1.5 text-xs" title="ล้างการค้นหา"><i class="fa-solid fa-xmark"></i></a>
                                    <?php endif; ?>
                                    <button type="submit" class="w-7 h-7 rounded-lg bg-brandGreen text-white flex items-center justify-center hover:bg-emerald-700 transition-all text-xs" title="ค้นหา">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[700px]">
                            <thead>
                                <tr class="bg-slate-50/70 border-b border-slate-100 text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    <th class="py-4 px-6">ID / ข้อมูลโครงการ</th>
                                    <th class="py-4 px-6">ความคืบหน้า (ตามจริง)</th>
                                    <th class="py-4 px-6">สถานะ</th>
                                    <th class="py-4 px-6">วันที่บันทึก/กำหนดเสร็จ</th>
                                    <th class="py-4 px-6 text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                <?php if (empty($paginated_works)): ?>
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-400">
                                        <i class="fa-solid fa-folder-open text-3xl mb-2 text-slate-300 block"></i>
                                        ไม่พบข้อมูลงานติดตั้ง<?php if($search_query !== '') echo 'ที่ตรงกับ "' . htmlspecialchars($search_query) . '"'; ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($paginated_works as $work): 
                                        $id = $work['id'] ?? '#';
                                        $title = $work['name'] ?? $work['title'] ?? 'งานติดตั้งโซล่าเซลล์ #' . $id;
                                        $progress = round($work['calculated_progress'] ?? 0);
                                        $status = $work['status'] ?? 'รอดำเนินการ';
                                        
                                        $raw_date = $work['created_at'] ?? '';
                                        if (!empty($raw_date) && $raw_date !== '-') {
                                            $timestamp = strtotime($raw_date);
                                            $formatted_date = date('d/m/', $timestamp) . (date('Y', $timestamp) + 543);
                                        } else {
                                            $formatted_date = '-';
                                        }
                                    ?>
                                    <tr class="hover:bg-slate-50/50 transition-all">
                                        <td class="py-4 px-6">
                                            <p class="font-bold text-slate-800"><?= htmlspecialchars($title) ?></p>
                                        </td>
                                        <td class="py-4 px-6 w-48">
                                            <div class="flex items-center justify-between text-xs font-semibold mb-1">
                                                <span class="text-slate-600"><?= $progress ?>%</span>
                                            </div>
                                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                                <div class="bg-brandGreen h-2 rounded-full transition-all" style="width: <?= $progress ?>%;"></div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6">
                                            <?php if ($status === 'เสร็จสิ้น' || $status === 'Completed' || $progress >= 100): ?>
                                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">เสร็จสิ้น</span>
                                            <?php else: ?>
                                                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">กำลังดำเนินการ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6 text-slate-500 text-xs font-medium">
                                            <i class="fa-regular fa-calendar mr-1"></i> <?= htmlspecialchars($formatted_date) ?>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                
                                                <a href="installation_detail.php?id=<?= $id ?>" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-blue-600 flex items-center justify-center transition-all" title="ดูรายละเอียด">
                                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (มุมขวาล่าง พร้อมส่งค่า search ติดไปด้วย) -->
                    <?php if ($total_pages > 1): ?>
                    <div class="p-4 md:p-6 border-t border-slate-100 flex items-center justify-end gap-1.5 bg-white">
                        <?php 
                            $search_param = ($search_query !== '') ? '&search=' . urlencode($search_query) : '';
                        ?>
                        <?php if ($page > 1): ?>
                            <a href="?p=<?= $page - 1 ?><?= $search_param ?>" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">ก่อนหน้า</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?p=<?= $i ?><?= $search_param ?>" class="w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold <?= $i === $page ? 'bg-brandGreen text-white shadow-sm' : 'border border-slate-200 text-slate-600 hover:bg-slate-50' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?p=<?= $page + 1 ?><?= $search_param ?>" class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">ถัดไป</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <script>
        // ฟังก์ชันเปิด-ปิด Sidebar สำหรับมือถือ
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const backdrop = document.getElementById('sidebarBackdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }
    </script>
</body>
</html>