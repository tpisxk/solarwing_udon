<?php
session_start();
require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'dashboard_engineer';

$total_items = 0;
$low_count = $out_count = $normal_count = 0;
$total_installations = $completed_installations = $in_progress_installations = $pending_installations = 0;
$total_project_cost = 0;
$installations_list = $low_stock_products = [];

try {
    // 1. สรุปสถิติสต็อกภาพรวม
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

    // 2. ดึงรายการโครงการ ความคืบหน้า และคำนวณงบประมาณ/ค่าใช้จ่ายจริง
    $stmt_install = $pdo->query("
        SELECT 
            i.id, i.name, i.system_size,
            COALESCE(t.avg_progress, 0) AS avg_progress,
            CASE 
                COALESCE(t.avg_progress, 0)
                WHEN 0 THEN 'pending'
                WHEN 100 THEN 'completed'
                ELSE 'in_progress'
            END AS calculated_status,
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
        LEFT JOIN (
            SELECT installation_id, AVG(progress) AS avg_progress
            FROM installation_tasks GROUP BY installation_id
        ) t ON i.id = t.installation_id
        GROUP BY i.id ORDER BY i.id DESC
    ");
    $installations_list = $stmt_install->fetchAll(PDO::FETCH_ASSOC);

    // คำนวณสรุปสถิติต่างๆ
    $total_installations = count($installations_list);
    foreach ($installations_list as $item) {
        $total_project_cost += $item['total_cost'];
        if ($item['calculated_status'] == 'completed') {
            $completed_installations++;
        } elseif ($item['calculated_status'] == 'in_progress') {
            $in_progress_installations++;
        } else {
            $pending_installations++;
        }
    }

    // 3. ดึงสินค้าใกล้หมดสต็อก (<= 5 ชิ้น) พร้อมป้องกันค่าติดลบ
    $stmt_low = $pdo->query("
        SELECT name, category, GREATEST(stock_quantity, 0) AS stock_quantity 
        FROM products 
        WHERE stock_quantity <= 5 
        ORDER BY stock_quantity ASC 
        LIMIT 10
    ");
    $low_stock_products = $stmt_low->fetchAll(PDO::FETCH_ASSOC);

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
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden relative">

    <!-- Sidebar -->
    <div id="sidebar" class="fixed lg:static inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out bg-white border-r border-slate-200 w-64 flex-shrink-0 flex flex-col">
       <div class="flex-1 overflow-y-auto">
            <?php if (file_exists('sidebar_engineer.php')) include 'sidebar_engineer.php'; ?>
        </div>
    </div>

    <!-- Background Overlay สำหรับมือถือ -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-20 hidden lg:hidden"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden w-full">
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 shrink-0 shadow-sm z-10">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="text-base lg:text-xl font-bold text-slate-900 truncate">แดชบอร์ดวิศวกรและติดตามโครงการ</h2>
                    <p class="text-xs text-slate-500">ตรวจสอบสถานะความคืบหน้า งบประมาณ และอุปกรณ์หน้างาน</p>
                </div>
            </div>
            <a href="requisition_form.php" class="bg-brandGreen hover:bg-emerald-700 text-white px-4 py-2.5 rounded-2xl text-xs font-bold shadow-sm transition flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-plus"></i> สร้างใบเบิกอุปกรณ์
            </a>
        </header>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8 space-y-6">
            <?php if (isset($db_error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> เชื่อมต่อฐานข้อมูลล้มเหลว: <?= htmlspecialchars($db_error) ?>
                </div>
            <?php endif; ?>

            <!-- สรุปสถิติสำคัญ 5 การ์ดข้อมูล -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">โครงการทั้งหมด</p>
                        <h3 class="text-xl font-extrabold text-slate-900 mt-1"><?= number_format($total_installations) ?> <span class="text-xs font-normal text-slate-500">โครงการ</span></h3>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-diagram-project"></i></div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">กำลังดำเนินการ</p>
                        <h3 class="text-xl font-extrabold text-blue-600 mt-1"><?= number_format($in_progress_installations) ?> <span class="text-xs font-normal text-slate-500">โครงการ</span></h3>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-spinner"></i></div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">เสร็จสิ้นแล้ว</p>
                        <h3 class="text-xl font-extrabold text-emerald-600 mt-1"><?= number_format($completed_installations) ?> <span class="text-xs font-normal text-slate-500">โครงการ</span></h3>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-circle-check"></i></div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">ค่าใช้จ่ายรวมทุกโครงการ</p>
                        <h3 class="text-lg font-extrabold text-blue-600 mt-1">฿<?= number_format($total_project_cost, 2) ?></h3>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-wallet"></i></div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">อุปกรณ์ใกล้หมด</p>
                        <h3 class="text-xl font-extrabold text-rose-600 mt-1"><?= number_format($low_count + $out_count) ?> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center"><i class="fa-solid fa-triangle-exclamation"></i></div>
                </div>
            </div>

            <!-- ตารางข้อมูลหลักสำหรับวิศวกร -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- โครงการและความคืบหน้า -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-clipboard-list text-brandGreen me-2"></i>โครงการและความคืบหน้า</h3>
                    <div class="max-h-80 overflow-y-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="sticky top-0 bg-white border-b border-slate-100 font-bold text-slate-400 uppercase">
                                <tr>
                                    <th class="py-2.5 px-3">ชื่อโครงการ</th>
                                    <th class="py-2.5 px-3 text-center">ความคืบหน้า</th>
                                    <th class="py-2.5 px-3 text-center">สถานะอัตโนมัติ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                <?php if ($installations_list): foreach ($installations_list as $item): 
                                    $st = $item['calculated_status'];
                                    $progress = round($item['avg_progress']);
                                    
                                    if ($st == 'completed') {
                                        $badge = '<span class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full text-[10px] font-bold whitespace-nowrap">เสร็จสิ้น (100%)</span>';
                                    } elseif ($st == 'in_progress') {
                                        $badge = '<span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-[10px] font-bold whitespace-nowrap">ดำเนินการ (' . $progress . '%)</span>';
                                    } else {
                                        $badge = '<span class="bg-amber-100 text-amber-800 px-3 py-1 rounded-full text-[10px] font-bold whitespace-nowrap">รอดำเนินการ (0%)</span>';
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50 transition-all">
                                        <td class="py-3 px-3 font-bold text-slate-900"><?= htmlspecialchars($item['name']) ?></td>
                                        <td class="py-3 px-3 text-center font-semibold text-slate-600 w-36">
                                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                                <div class="bg-emerald-500 h-full rounded-full" style="width: <?= $progress ?>%;"></div>
                                            </div>
                                            <span class="text-[10px] text-slate-400 mt-0.5 inline-block"><?= $progress ?>%</span>
                                        </td>
                                        <td class="py-3 px-3 text-center"><?= $badge ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="3" class="text-center py-6 text-slate-400">ไม่มีข้อมูลโครงการ</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- รายละเอียดงบประมาณและขนาดระบบ -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-wallet text-blue-600 me-2"></i> รายละเอียดงบประมาณและขนาดระบบ</h3>
                    <div class="max-h-80 overflow-y-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="sticky top-0 bg-white border-b border-slate-100 font-bold text-slate-400 uppercase">
                                <tr>
                                    <th class="py-2.5 px-3">ชื่อโครงการ</th>
                                    <th class="py-2.5 px-3 text-center">ขนาดระบบ</th>
                                    <th class="py-2.5 px-3 text-right">ค่าใช้จ่ายรวม (บาท)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                <?php if ($installations_list): foreach ($installations_list as $item): 
                                    $raw_size = trim($item['system_size'] ?? '');
                                    if ($raw_size !== '' && $raw_size !== '-') {
                                        $sys_size = stripos($raw_size, 'kWp') !== false ? $raw_size : $raw_size . ' kWp';
                                    } else {
                                        $sys_size = '-';
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-3 px-3 font-bold text-slate-900"><?= htmlspecialchars($item['name']) ?></td>
                                        <td class="py-3 px-3 text-center">
                                            <?php if ($sys_size !== '-'): ?>
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 font-extrabold text-[11px] shadow-sm">
                                                    <i class="fa-solid fa-solar-panel text-brandGreen"></i> <?= htmlspecialchars($sys_size) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 text-right font-extrabold text-blue-600">฿<?= number_format($item['total_cost'], 2) ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="3" class="text-center py-6 text-slate-400">ไม่มีข้อมูลค่าใช้จ่ายโครงการ</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ตารางอุปกรณ์ใกล้หมดสต็อก -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-triangle-exclamation text-rose-500 me-2"></i> อุปกรณ์ใกล้หมดสต็อก (≤ 5 ชิ้น)</h3>
                <div class="max-h-72 overflow-y-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 bg-white border-b border-slate-100 font-bold text-slate-400 uppercase">
                            <tr>
                                <th class="py-2.5 px-3">ชื่ออุปกรณ์</th>
                                <th class="py-2.5 px-3">หมวดหมู่</th>
                                <th class="py-2.5 px-3 text-right">คงเหลือ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if ($low_stock_products): foreach ($low_stock_products as $prod): 
                                $qty = max(0, intval($prod['stock_quantity']));
                            ?>
                                <tr class="hover:bg-slate-50 bg-rose-50/10">
                                    <td class="py-3 px-3 font-bold text-slate-900"><?= htmlspecialchars($prod['name']) ?></td>
                                    <td class="py-3 px-3 text-slate-500"><?= htmlspecialchars($prod['category'] ?? '-') ?></td>
                                    <td class="py-3 px-3 text-right font-extrabold text-rose-600">
                                        <?= number_format($qty) ?> ชิ้น
                                        <?php if ($qty <= 0): ?>
                                            <span class="text-[9px] block text-rose-500 font-semibold">(หมดสต็อก)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="3" class="text-center py-6 text-slate-400">ยอดเยี่ยม! ไม่มีอุปกรณ์ใกล้หมดสต็อก</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>
</html>