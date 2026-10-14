<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';

// ตรวจสอบเฉพาะการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$current_page = 'expenses_summary';

// รับค่าโครงการที่ถูกเลือกจาก Dropdown (ถ้ามี)
$selected_installation_id = isset($_GET['installation_id']) && $_GET['installation_id'] !== '' ? intval($_GET['installation_id']) : 'all';

// ดึงรายชื่อโครงการทั้งหมดสำหรับใส่ใน Dropdown ตัวกรอง
try {
    $stmt_inst_list = $pdo->query("SELECT id, name, status FROM installations ORDER BY id DESC");
    $all_installations = $stmt_inst_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_installations = [];
}

// ==========================================
// ดึงข้อมูลสรุปโครงการ ตามเงื่อนไขการกรอง
// ==========================================
$project_summaries = [];
$total_all_material_cost = 0;
$total_all_other_expenses = 0;
$total_all_expenses = 0;

try {
    $sql_proj = "SELECT id, name, status FROM installations";
    $params = [];
    if ($selected_installation_id !== 'all') {
        $sql_proj .= " WHERE id = ?";
        $params[] = $selected_installation_id;
    }
    $sql_proj .= " ORDER BY id DESC";

    $stmt_projects = $pdo->prepare($sql_proj);
    $stmt_projects->execute($params);
    $projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

    foreach ($projects as $proj) {
        $proj_id = $proj['id'];

        // 1.1 รายการเบิกออก (type = 'out')
        $stmt_out = $pdo->prepare("
            SELECT s.*, p.name AS product_name, p.sku, s.unit_cost AS cost_price, u.name AS user_name
            FROM stock_transactions s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.installation_id = ? AND s.type = 'out'
            ORDER BY s.id DESC
        ");
        $stmt_out->execute([$proj_id]);
        $dispensed_items = $stmt_out->fetchAll(PDO::FETCH_ASSOC);

        $out_cost = 0;
        foreach ($dispensed_items as $item) {
            $out_cost += ($item['quantity'] * floatval($item['cost_price']));
        }

        // 1.2 รายการรับเข้าสินค้า (type = 'in' หรือ 'return')
        $stmt_in = $pdo->prepare("
            SELECT s.*, p.name AS product_name, p.sku, s.unit_cost AS cost_price, u.name AS user_name
            FROM stock_transactions s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.installation_id = ? AND s.type IN ('in', 'return')
            ORDER BY s.id DESC
        ");
        $stmt_in->execute([$proj_id]);
        $received_in_items = $stmt_in->fetchAll(PDO::FETCH_ASSOC);

        $in_cost = 0;
        foreach ($received_in_items as $item) {
            $in_cost += ($item['quantity'] * floatval($item['cost_price']));
        }

        $net_material_cost = $out_cost - $in_cost;

        // 1.3 รายการค่าใช้จ่ายอื่นๆ (Expenses)
        $stmt_expenses = $pdo->prepare("
            SELECT * FROM expenses WHERE installation_id = ? ORDER BY id DESC
        ");
        $stmt_expenses->execute([$proj_id]);
        $other_expense_items = $stmt_expenses->fetchAll(PDO::FETCH_ASSOC);

        $other_cost = 0;
        foreach ($other_expense_items as $exp) {
            $other_cost += floatval($exp['amount']);
        }

        $grand_total = $net_material_cost + $other_cost;

        $total_all_material_cost  += $net_material_cost;
        $total_all_other_expenses += $other_cost;
        $total_all_expenses       += $grand_total;

        $project_summaries[] = [
            'project_id'          => $proj_id,
            'project_name'        => $proj['name'],
            'project_status'      => $proj['status'],
            'out_cost'            => $out_cost,
            'in_cost'             => $in_cost,
            'net_material_cost'   => $net_material_cost,
            'other_cost'          => $other_cost,
            'grand_total'         => $grand_total,
            'dispensed_items'     => $dispensed_items,
            'received_in_items'   => $received_in_items,
            'other_expense_items' => $other_expense_items
        ];
    }

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปรายละเอียดค่าใช้จ่ายโครงการ - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 
                        sans: ['Prompt', 'sans-serif'],
                        sarabun: ['Sarabun', 'sans-serif']
                    },
                    colors: { brandGreen: '#059669' }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; } 
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .font-sarabun { font-family: 'Sarabun', sans-serif !important; }
        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            body { background: white !important; color: #0f172a !important; font-family: 'Sarabun', sans-serif !important; }
            #sidebarWrapper, header, .no-print { display: none !important; }
            body * { visibility: hidden; }
            #printableExpenseReport, #printableExpenseReport * { visibility: visible; }
            #printableExpenseReport {
                position: absolute; left: 0; top: 0; width: 100%;
                background: white !important; z-index: 99999; padding: 0 !important;
                margin: 0 !important; border: none !important; box-shadow: none !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden relative">

    <!-- Sidebar: ซ่อนอัตโนมัติบนมือถือ (-translate-x-full) และแสดงปกติบนหน้าจอคอมพิวเตอร์ (lg:translate-x-0) -->
    <div id="sidebarWrapper" class="fixed lg:static inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out bg-white border-r border-slate-200 w-64 flex-shrink-0 flex flex-col shadow-xl lg:shadow-none">
        
        <div class="flex-1 overflow-y-auto">
            <?php 
            if (file_exists('sidebar_admin.php')) {
                include 'sidebar_admin.php'; 
            } elseif (file_exists('sidebar.php')) {
                include 'sidebar.php';
            }
            ?>
        </div>
    </div>

    <!-- Background Overlay เฉพาะตอนเปิด Sidebar บนมือถือ -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-20 hidden lg:hidden"></div>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <!-- ปุ่มกดเปิด Sidebar (แสดงเฉพาะบนมือถือ/แท็บเล็ต โดยซ่อนบนคอมพิวเตอร์ด้วย lg:hidden) -->
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="text-sm md:text-xl font-bold text-slate-900 tracking-tight">รายงานค่าใช้จ่ายโครงการ</h2>
                    <!-- <p class="text-xs text-slate-500 hidden sm:block">เลือกโครงการเพื่อตรวจสอบและออกรายงานเฉพาะโครงการได้ทันที</p> -->
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openPrintModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm transition">
                    <i class="fa-solid fa-file-pdf"></i> <span class="hidden sm:inline">ออกรายงาน PDF</span>
                </button>
                <a href="dashboard_admin.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
                </a>
            </div>
        </header>

        <!-- Scroll Content Area -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8 space-y-6">
            
            <?php if (isset($db_error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-sm">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> เกิดข้อผิดพลาดในฐานข้อมูล: <?= htmlspecialchars($db_error) ?>
                </div>
            <?php endif; ?>

            <!-- แถบเลือกโครงการ (Filter Bar) -->
            <div class="bg-white p-6 rounded-3xl border border-slate-200/85 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold shrink-0">
                        <i class="fa-solid fa-filter"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm">กรองข้อมูลตามโครงการ</h3>
                        <p class="text-xs text-slate-500">เลือกโครงการที่ต้องการเรียกดูและสรุปค่าใช้จ่าย</p>
                    </div>
                </div>

                <form method="GET" action="" class="flex items-center gap-3 w-full md:w-auto">
                    <select name="installation_id" onchange="this.form.submit()" class="w-full md:w-72 px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-semibold text-slate-700 cursor-pointer">
                        <option value="all">-- แสดงทุกโครงการทั้งหมด --</option>
                        <?php foreach ($all_installations as $inst): ?>
                            <option value="<?= $inst['id'] ?>" <?= ($selected_installation_id == $inst['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Cards สถิติภาพรวม 3 ช่อง -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/85 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">มูลค่าอุปกรณ์สุทธิ</p>
                        <h3 class="text-2xl font-extrabold text-emerald-600">฿<?= number_format($total_all_material_cost, 2) ?></h3>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shadow-inner">
                        <i class="fa-solid fa-boxes-packing"></i>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/85 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">ค่าใช้จ่ายอื่นๆ รวม</p>
                        <h3 class="text-2xl font-extrabold text-amber-600">฿<?= number_format($total_all_other_expenses, 2) ?></h3>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shadow-inner">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/85 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">ค่าใช้จ่ายรวมสุทธิ</p>
                        <h3 class="text-2xl font-extrabold text-rose-600">฿<?= number_format($total_all_expenses, 2) ?></h3>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl shadow-inner">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                </div>
            </div>

            <!-- ตารางแสดงรายละเอียดแบบกดขยายได้ -->
            <div class="bg-white rounded-3xl border border-slate-200/85 shadow-sm overflow-hidden p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-diagram-project text-brandGreen me-2"></i> รายละเอียดรายโครงการ</h3>
                        <p class="text-xs text-slate-500">คลิกแถบโครงการเพื่อขยายดูรายการเบิกออก รับเข้า และค่าใช้จ่ายย่อย</p>
                    </div>
                    <span class="text-xs text-slate-400 font-medium">แสดงผล <?= count($project_summaries) ?> โครงการ</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-4 px-4"># / โครงการ</th>
                                <th class="py-4 px-4 text-center">สถานะ</th>
                                <th class="py-4 px-4 text-right">อุปกรณ์สุทธิ</th>
                                <th class="py-4 px-4 text-right">ค่าใช้จ่ายอื่นๆ</th>
                                <th class="py-4 px-4 text-right">รวมใช้ไปสุทธิ</th>
                                <th class="py-4 px-4 text-center">รายละเอียด</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium">
                            <?php if (!empty($project_summaries)): ?>
                                <?php foreach ($project_summaries as $p): 
                                    $status = $p['project_status'] ?? 'pending';
                                    if ($status == 'completed' || $status == 'สำเร็จ') {
                                        $badge = '<span class="bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-emerald-200">เสร็จสิ้น</span>';
                                    } elseif ($status == 'in_progress' || $status == 'กำลังดำเนินการ') {
                                        $badge = '<span class="bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-blue-200">ดำเนินการ</span>';
                                    } else {
                                        $badge = '<span class="bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-amber-200">รอดำเนินการ</span>';
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50 transition cursor-pointer" onclick="toggleDetails('proj-<?= $p['project_id'] ?>')">
                                        <td class="py-4 px-4">
                                            <div class="font-bold text-slate-900 text-sm flex items-center gap-2">
                                                <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition-transform duration-200" id="icon-proj-<?= $p['project_id'] ?>"></i>
                                                <?= htmlspecialchars($p['project_name']) ?>
                                            </div>
                                            <div class="text-[10px] text-slate-400 ms-4">
                                                ID: #<?= $p['project_id'] ?> | เบิก: <?= count($p['dispensed_items']) ?> | รับเข้า: <?= count($p['received_in_items']) ?> | รายจ่ายอื่น: <?= count($p['other_expense_items']) ?>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4 text-center"><?= $badge ?></td>
                                        <td class="py-4 px-4 text-right">
                                            <div class="font-semibold text-emerald-600">฿<?= number_format($p['net_material_cost'], 2) ?></div>
                                        </td>
                                        <td class="py-4 px-4 text-right font-semibold text-amber-600">฿<?= number_format($p['other_cost'], 2) ?></td>
                                        <td class="py-4 px-4 text-right font-extrabold text-slate-900 text-sm">฿<?= number_format($p['grand_total'], 2) ?></td>
                                        <td class="py-4 px-4 text-center">
                                            <button class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold rounded-xl transition border border-slate-200 inline-flex items-center gap-1">
                                                <i class="fa-solid fa-list-ul text-xs"></i> ข้อมูลย่อย
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- แถวรายละเอียดเชิงลึก -->
                                    <tr id="detail-proj-<?= $p['project_id'] ?>" class="hidden bg-slate-50/60">
                                        <td colspan="6" class="p-6">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-white p-5 rounded-2xl border border-slate-200 shadow-inner">
                                                
                                                <!-- 1. เบิกออก -->
                                                <div class="space-y-3">
                                                    <h4 class="text-xs font-bold text-slate-800 flex items-center justify-between border-b border-slate-100 pb-2">
                                                        <span><i class="fa-solid fa-arrow-up-from-bracket text-emerald-600 me-1.5"></i> เบิกออก (Out)</span>
                                                        <span class="text-[10px] text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200 font-bold">฿<?= number_format($p['out_cost'], 2) ?></span>
                                                    </h4>
                                                    <?php if (!empty($p['dispensed_items'])): ?>
                                                        <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                                            <?php foreach ($p['dispensed_items'] as $item): 
                                                                $item_total = $item['quantity'] * floatval($item['cost_price']);
                                                            ?>
                                                                <div class="flex items-center justify-between bg-slate-50 p-2.5 rounded-xl text-[11px] border border-slate-100">
                                                                    <div>
                                                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($item['product_name']) ?></div>
                                                                        <div class="text-[10px] text-slate-400">ราคา/หน่วย: ฿<?= number_format($item['cost_price'], 2) ?></div>
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <div class="font-bold text-slate-900"><?= number_format($item['quantity']) ?> ชิ้น</div>
                                                                        <div class="text-[10px] text-emerald-600 font-bold">= ฿<?= number_format($item_total, 2) ?></div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-[11px] text-slate-400 italic py-2">ไม่มีประวัติการเบิกออก</p>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- 2. รับเข้า / คืนสินค้า -->
                                                <div class="space-y-3">
                                                    <h4 class="text-xs font-bold text-slate-800 flex items-center justify-between border-b border-slate-100 pb-2">
                                                        <span><i class="fa-solid fa-arrow-down-to-bracket text-purple-600 me-1.5"></i> รับเข้า / รับคืน (In/Return)</span>
                                                        <span class="text-[10px] text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full border border-purple-200 font-bold">฿<?= number_format($p['in_cost'], 2) ?></span>
                                                    </h4>
                                                    <?php if (!empty($p['received_in_items'])): ?>
                                                        <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                                            <?php foreach ($p['received_in_items'] as $item): 
                                                                $item_total = $item['quantity'] * floatval($item['cost_price']);
                                                            ?>
                                                                <div class="flex items-center justify-between bg-purple-50/50 p-2.5 rounded-xl text-[11px] border border-purple-100">
                                                                    <div>
                                                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($item['product_name']) ?></div>
                                                                        <div class="text-[10px] text-slate-400">ราคา/หน่วย: ฿<?= number_format($item['cost_price'], 2) ?></div>
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <div class="font-bold text-slate-900"><?= number_format($item['quantity']) ?> ชิ้น</div>
                                                                        <div class="text-[10px] text-purple-600 font-bold">= ฿<?= number_format($item_total, 2) ?></div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-[11px] text-slate-400 italic py-2">ไม่มีประวัติการรับเข้า</p>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- 3. ค่าใช้จ่ายอื่นๆ -->
                                                <div class="space-y-3">
                                                    <h4 class="text-xs font-bold text-slate-800 flex items-center justify-between border-b border-slate-100 pb-2">
                                                        <span><i class="fa-solid fa-receipt text-amber-600 me-1.5"></i> ค่าใช้จ่ายอื่นๆ และสลิป</span>
                                                        <span class="text-[10px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200 font-bold">฿<?= number_format($p['other_cost'], 2) ?></span>
                                                    </h4>
                                                    <?php if (!empty($p['other_expense_items'])): ?>
                                                        <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                                            <?php foreach ($p['other_expense_items'] as $exp): ?>
                                                                <div class="flex items-center justify-between bg-slate-50 p-2.5 rounded-xl text-[11px] border border-slate-100 gap-2">
                                                                    <div>
                                                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($exp['title']) ?></div>
                                                                        <div class="text-[10px] text-slate-400">หมวดหมู่: <?= htmlspecialchars($exp['category'] ?: 'ทั่วไป') ?></div>
                                                                    </div>
                                                                    <div class="flex items-center gap-2">
                                                                        <?php if (!empty($exp['receipt_image'])): ?>
                                                                            <img src="<?= htmlspecialchars($exp['receipt_image']) ?>" alt="สลิป" onclick="openImageModal('<?= htmlspecialchars($exp['receipt_image']) ?>')" class="w-9 h-9 object-cover rounded-lg border border-slate-200 cursor-pointer hover:opacity-80 transition shadow-xs" title="คลิกเพื่อดูสลิป">
                                                                        <?php endif; ?>
                                                                        <div class="text-right font-bold text-amber-600">
                                                                            ฿<?= number_format($exp['amount'], 2) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-[11px] text-slate-400 italic py-2">ไม่มีรายการค่าใช้จ่ายอื่น</p>
                                                    <?php endif; ?>
                                                </div>

                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-12 text-slate-400">ไม่พบข้อมูลโครงการในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal แสดงรูปสลิปขนาดใหญ่ -->
    <div id="imageModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-3xl max-w-lg w-full shadow-2xl space-y-4 text-center">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h4 class="font-bold text-slate-900 text-sm">หลักฐานการโอนเงิน / สลิปค่าใช้จ่าย</h4>
                <button onclick="closeImageModal()" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
            </div>
            <div class="flex justify-center max-h-[70vh] overflow-auto">
                <img id="modalReceiptImg" src="" alt="Receipt Slip" class="rounded-2xl max-h-[60vh] object-contain border border-slate-200">
            </div>
            <div class="flex justify-end pt-2">
                <button onclick="closeImageModal()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold">ปิด</button>
            </div>
        </div>
    </div>

    <!-- Modal พิมพ์รายงาน PDF (TH Sarabun) -->
    <div id="printReportModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 md:p-10 shadow-2xl border border-slate-200 space-y-6 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-200 pb-5 no-print">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white flex items-center justify-center font-bold"><i class="fa-solid fa-file-pdf text-lg"></i></div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">ตัวอย่างรายงานค่าใช้จ่ายและต้นทุนโครงการ</h3>
                        <p class="text-xs text-slate-500 font-medium">รูปแบบฟอนต์ทางการ: TH Sarabun</p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-xl text-xs md:text-sm font-bold flex items-center gap-2"><i class="fa-solid fa-print"></i> พิมพ์ / บันทึก PDF</button>
                    <button type="button" onclick="closePrintModal()" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>

            <div id="printableExpenseReport" class="p-6 bg-white text-slate-900 space-y-5 font-sarabun text-base">
                <div class="flex items-center justify-between border-b-2 border-slate-800 pb-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-emerald-600 text-white rounded-2xl flex items-center justify-center text-2xl font-bold"><i class="fa-solid fa-solar-panel"></i></div>
                        <div>
                            <h1 class="text-2xl font-bold uppercase tracking-wide text-slate-900 leading-tight">SOLARSTOCK PRO</h1>
                            <p class="text-sm font-semibold text-slate-600">รายงานสรุปค่าใช้จ่ายและต้นทุนโครงการ (PROJECT EXPENSE & COST REPORT)</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-300 rounded text-xs font-bold uppercase mb-1">OFFICIAL REPORT</span>
                        <p class="text-xs font-semibold text-slate-500">วันที่พิมพ์: <?= date('d/m/Y H:i') ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 text-sm">
                    <div><strong>มูลค่าอุปกรณ์สุทธิรวม:</strong> ฿<?= number_format($total_all_material_cost, 2) ?></div>
                    <div><strong>ค่าใช้จ่ายอื่นๆ รวม:</strong> ฿<?= number_format($total_all_other_expenses, 2) ?></div>
                    <div><strong>ค่าใช้จ่ายสุทธิทั้งสิ้น:</strong> ฿<?= number_format($total_all_expenses, 2) ?></div>
                </div>

                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-800 text-white text-left">
                            <th class="p-2.5 border border-slate-700">ชื่อโครงการ</th>
                            <th class="p-2.5 border border-slate-700 text-center">สถานะ</th>
                            <th class="p-2.5 border border-slate-700 text-right">อุปกรณ์สุทธิ (บาท)</th>
                            <th class="p-2.5 border border-slate-700 text-right">ค่าใช้จ่ายอื่น (บาท)</th>
                            <th class="p-2.5 border border-slate-700 text-right">รวมสุทธิ (บาท)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($project_summaries as $p): ?>
                            <tr>
                                <td class="p-2.5 border border-slate-200 font-bold"><?= htmlspecialchars($p['project_name']) ?></td>
                                <td class="p-2.5 border border-slate-200 text-center"><?= htmlspecialchars($p['project_status']) ?></td>
                                <td class="p-2.5 border border-slate-200 text-right"><?= number_format($p['net_material_cost'], 2) ?></td>
                                <td class="p-2.5 border border-slate-200 text-right"><?= number_format($p['other_cost'], 2) ?></td>
                                <td class="p-2.5 border border-slate-200 text-right font-bold"><?= number_format($p['grand_total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Script ควบคุมเปิด/ปิด Sidebar เฉพาะโหมดมือถือ -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const overlay = document.getElementById('sidebar-overlay');
            
            // ควบคุมการสไลด์เปิด/ปิดเฉพาะบนมือถือ
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function toggleDetails(rowId) {
            const detailRow = document.getElementById('detail-' + rowId);
            const icon = document.getElementById('icon-' + rowId);
            if (detailRow.classList.contains('hidden')) {
                detailRow.classList.remove('hidden');
                icon.style.transform = 'rotate(90deg)';
            } else {
                detailRow.classList.add('hidden');
                icon.style.transform = 'rotate(0deg)';
            }
        }

        function openImageModal(imgSrc) {
            document.getElementById('modalReceiptImg').src = imgSrc;
            document.getElementById('imageModal').classList.remove('hidden');
            document.getElementById('imageModal').classList.add('flex');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('flex');
            document.getElementById('imageModal').classList.add('hidden');
        }

        function openPrintModal() {
            document.getElementById('printReportModal').classList.remove('hidden');
            document.getElementById('printReportModal').classList.add('flex');
        }

        function closePrintModal() {
            document.getElementById('printReportModal').classList.remove('flex');
            document.getElementById('printReportModal').classList.add('hidden');
        }
    </script>
</body>
</html>