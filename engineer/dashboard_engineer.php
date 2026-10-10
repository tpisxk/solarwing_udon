<?php
session_start();
require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'dashboard_engineer';

$total_items = 0;
$low_count = $out_count = $normal_count = 0;
$total_installations = $completed_installations = $in_progress_installations = 0;
$installations_list = $low_stock_products = [];
$pending_requisitions_count = 0;

try {
    // 1. สรุปสถิติสต็อกจำเป็น
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_items,
            SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) as out_count,
            SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= 5 THEN 1 ELSE 0 END) as low_count,
            SUM(CASE WHEN stock_quantity > 5 THEN 1 ELSE 0 END) as normal_count
        FROM products
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    extract($stats);

    // 2. นับจำนวนใบเบิกที่รอแอดมินตรวจสอบ (Pending)
    $stmt_req = $pdo->query("SELECT COUNT(*) FROM requisitions WHERE status = 'Pending'");
    $pending_requisitions_count = $stmt_req->fetchColumn();

    // 3. สรุปสถิติโครงการ (Installations)
    $stmt_inst_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_inst,
            SUM(CASE WHEN status = 'completed' OR status = 'สำเร็จ' THEN 1 ELSE 0 END) as completed_inst,
            SUM(CASE WHEN status = 'in_progress' OR status = 'กำลังดำเนินการ' THEN 1 ELSE 0 END) as progress_inst
        FROM installations
    ");
    $inst_stats = $stmt_inst_stats->fetch(PDO::FETCH_ASSOC);
    $total_installations = $inst_stats['total_inst'] ?? 0;
    $completed_installations = $inst_stats['completed_inst'] ?? 0;
    $in_progress_installations = $inst_stats['progress_inst'] ?? 0;

    // 4. ดึงรายการโครงการทั้งหมดพร้อมคำนวณค่าใช้จ่าย
    $stmt_install = $pdo->query("
        SELECT i.id, i.name, i.status, i.system_size, i.created_at,
            (
                COALESCE(SUM(CASE WHEN s.type = 'out' THEN s.quantity * p.cost_price ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN s.type = 'in' THEN s.quantity * p.cost_price ELSE 0 END), 0) +
                COALESCE(e.total_exp, 0)
            ) AS total_cost
        FROM installations i
        LEFT JOIN stock_transactions s ON i.id = s.installation_id
        LEFT JOIN products p ON s.product_id = p.id
        LEFT JOIN (
            SELECT installation_id, SUM(amount) AS total_exp 
            FROM expenses GROUP BY installation_id
        ) e ON i.id = e.installation_id
        GROUP BY i.id ORDER BY i.id DESC
    ");
    $installations_list = $stmt_install->fetchAll(PDO::FETCH_ASSOC);

    // 5. ดึงอุปกรณ์ที่ใกล้หมดสต็อก (<= 5 ชิ้น)
    $low_stock_products = $pdo->query("SELECT name, category, stock_quantity FROM products WHERE stock_quantity <= 5 ORDER BY stock_quantity ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineer Dashboard - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Prompt', 'sans-serif'] }, colors: { brandGreen: '#059669' } } } }
    </script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar สำหรับ Desktop และ Mobile Drawer -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out">
        <?php if (file_exists('sidebar_engineer.php')) include 'sidebar_engineer.php'; ?>
    </div>

    <!-- Backdrop สำหรับปิด Sidebar บนมือถือ -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden w-full">
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-10 shrink-0 shadow-sm z-10">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-slate-900 text-xl p-2 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </span>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">แดชบอร์ดวิศวกรและข้อมูลโครงการ</h2>
                    <p class="text-[11px] md:text-xs text-slate-500">ภาพรวมโครงการ ค่าใช้จ่าย และสถานะเบิกอุปกรณ์</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="requisition_form.php" class="bg-brandGreen hover:bg-emerald-700 text-white px-3 md:px-4 py-2.5 rounded-2xl text-xs font-bold shadow-sm transition flex items-center gap-1.5">
                    <i class="fa-solid fa-plus text-xs"></i> <span class="hidden sm:inline">สร้างใบเบิกอุปกรณ์</span>
                </a>
            </div>
        </header>

        <!-- Main Content Area -->
        <div class="flex-1 overflow-y-auto p-4 md:p-8 space-y-6">
            <?php if (isset($db_error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> เชื่อมต่อฐานข้อมูลล้มเหลว: <?= htmlspecialchars($db_error) ?>
                </div>
            <?php endif; ?>

            <!-- สรุปข้อมูลสำคัญ Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                
                <a href="user_requisitions.php" class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm hover:border-amber-400 transition group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">ใบเบิกของรอตรวจ</p>
                            <h3 class="text-xl font-extrabold text-amber-600 mt-1"><?= number_format($pending_requisitions_count) ?> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-110 transition"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    </div>
                </a>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">โครงการทั้งหมด</p>
                            <h3 class="text-xl font-extrabold text-slate-900 mt-1"><?= number_format($total_installations) ?> <span class="text-xs font-normal text-slate-500">โครงการ</span></h3>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-diagram-project"></i></div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">กำลังดำเนินการ</p>
                            <h3 class="text-xl font-extrabold text-blue-600 mt-1"><?= number_format($in_progress_installations) ?> <span class="text-xs font-normal text-slate-500">โครงการ</span></h3>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-spinner"></i></div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">โครงการเสร็จสิ้น</p>
                            <h3 class="text-xl font-extrabold text-emerald-600 mt-1"><?= number_format($completed_installations) ?> <span class="text-xs font-normal text-slate-500">โครงการ</span></h3>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-circle-check"></i></div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">อุปกรณ์ต้องเติม</p>
                            <h3 class="text-xl font-extrabold text-rose-600 mt-1"><?= number_format($low_count + $out_count) ?> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    </div>
                </div>

            </div>

            <!-- ตารางแสดงข้อมูลโครงการและอุปกรณ์ใกล้หมด -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-6">
                
                <!-- ตารางข้อมูลโครงการทั้งหมด (กินพื้นที่ 2 Column) -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-5 md:p-6 space-y-4 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm md:text-base font-bold text-slate-900"><i class="fa-solid fa-diagram-project text-brandGreen me-2"></i>รายการข้อมูลโครงการทั้งหมด</h3>
                        <span class="text-xs text-slate-400 font-medium">รวม <?= count($installations_list) ?> โครงการ</span>
                    </div>
                    <div class="overflow-x-auto max-h-96 overflow-y-auto">
                        <table class="w-full text-left border-collapse text-xs min-w-[500px]">
                            <thead class="sticky top-0 bg-white border-b border-slate-100">
                                <tr class="font-bold text-slate-400 uppercase">
                                    <th class="py-3 px-3">ชื่อโครงการ / หน้างาน</th>
                                    <th class="py-3 px-3">ขนาดระบบ</th>
                                    <th class="py-3 px-3 text-right">ค่าใช้จ่ายรวม</th>
                                    <th class="py-3 px-3 text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                <?php if ($installations_list): foreach ($installations_list as $item): 
                                    $st = $item['status'] ?? 'pending';
                                    $badge = ($st == 'completed' || $st == 'สำเร็จ') ? '<span class="bg-emerald-100 text-emerald-800 px-2.5 py-1 rounded-full text-[10px] font-bold">เสร็จสิ้น</span>' :
                                            (($st == 'in_progress' || $st == 'กำลังดำเนินการ') ? '<span class="bg-blue-100 text-blue-800 px-2.5 py-1 rounded-full text-[10px] font-bold">ดำเนินการ</span>' :
                                            '<span class="bg-amber-100 text-amber-800 px-2.5 py-1 rounded-full text-[10px] font-bold">รอดำเนินการ</span>');
                                    
                                    // จัดรูปแบบขนาดระบบให้ต่อท้ายด้วย kWp อัตโนมัติ (หากในฐานข้อมูลยังไม่ได้ใส่หน่วย)
                                    $raw_size = trim($item['system_size'] ?? '');
                                    if ($raw_size !== '' && $raw_size !== '-') {
                                        $sys_size = stripos($raw_size, 'kWp') !== false ? $raw_size : $raw_size . ' kWp';
                                    } else {
                                        $sys_size = '-';
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-3.5 px-3 font-bold text-slate-900"><?= htmlspecialchars($item['name']) ?></td>
                                        <td class="py-3.5 px-3">
                                            <?php if ($sys_size !== '-'): ?>
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 font-extrabold text-[11px]">
                                                    <i class="fa-solid fa-solar-panel text-brandGreen"></i> <?= htmlspecialchars($sys_size) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-3 text-right font-semibold text-emerald-600">฿<?= number_format($item['total_cost'], 2) ?></td>
                                        <td class="py-3.5 px-3 text-center"><?= $badge ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="4" class="text-center py-6 text-slate-400">ไม่มีข้อมูลโครงการในระบบ</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- อุปกรณ์ใกล้หมดสต็อก -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-5 md:p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm md:text-base font-bold text-slate-900"><i class="fa-solid fa-triangle-exclamation text-rose-500 me-2"></i>อุปกรณ์ใกล้หมด</h3>
                    </div>
                    <div class="overflow-x-auto max-h-96 overflow-y-auto">
                        <table class="w-full text-left border-collapse text-xs min-w-[250px]">
                            <thead class="sticky top-0 bg-white border-b border-slate-100">
                                <tr class="font-bold text-slate-400 uppercase">
                                    <th class="py-3 px-2">ชื่ออุปกรณ์</th>
                                    <th class="py-3 px-2 text-right">คงเหลือ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                <?php if ($low_stock_products): foreach ($low_stock_products as $prod): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-3 px-2 font-bold text-slate-900 truncate max-w-[130px]"><?= htmlspecialchars($prod['name']) ?></td>
                                        <td class="py-3 px-2 text-right font-extrabold text-rose-600"><?= number_format($prod['stock_quantity']) ?> ชิ้น</td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="2" class="text-center py-6 text-slate-400">ยอดเยี่ยม! ไม่มีอุปกรณ์ใกล้หมด</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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