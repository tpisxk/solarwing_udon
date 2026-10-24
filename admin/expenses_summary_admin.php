<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$current_page = 'expenses_summary';

// รับค่าโครงการที่ถูกเลือกจาก Dropdown ตัวกรอง (รองรับ 'all' ได้อย่างถูกต้อง)
$selected_installation_id = isset($_GET['installation_id']) && $_GET['installation_id'] !== '' ? $_GET['installation_id'] : 'all';

// ดึงรายชื่อโครงการทั้งหมดสำหรับใส่ใน Dropdown ตัวกรอง
try {
    $stmt_inst_list = $pdo->query("SELECT id, name, status FROM installations ORDER BY id DESC");
    $all_installations = $stmt_inst_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_installations = [];
}

// ==========================================
// ดึงข้อมูลสรุปโครงการ รายรับ ค่าใช้จ่าย และรับคืน
// ==========================================
$project_summaries = [];
$total_all_dispensed_cost = 0;
$total_all_returned_cost = 0;
$total_all_material_cost = 0;
$total_all_other_expenses = 0;
$total_all_expenses = 0;
$total_all_incomes = 0;
$total_all_net_profit = 0;

try {
    $sql_proj = "
        SELECT i.id, i.name, i.status, 
               COALESCE((SELECT AVG(t.progress) FROM installation_tasks t WHERE t.installation_id = i.id), 0) AS avg_progress 
        FROM installations i
    ";
    $params = [];
    
    // แก้ไขเงื่อนไขการกรองให้รองรับ 'all' ได้อย่างถูกต้อง
    if ($selected_installation_id !== 'all' && intval($selected_installation_id) > 0) {
        $sql_proj .= " WHERE i.id = ?";
        $params[] = intval($selected_installation_id);
    }
    $sql_proj .= " ORDER BY i.id DESC";

    $stmt_projects = $pdo->prepare($sql_proj);
    $stmt_projects->execute($params);
    $projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

    foreach ($projects as $proj) {
        $proj_id = $proj['id'];

        // 1.1 รายการเบิกออก
        $stmt_out = $pdo->prepare("
            SELECT s.*, p.name AS product_name, p.sku, 
                   COALESCE(NULLIF(s.unit_cost, 0), NULLIF(p.cost_price, 0), 0) AS cost_price 
            FROM stock_transactions s
            JOIN products p ON s.product_id = p.id
            WHERE s.installation_id = ? AND LOWER(s.type) IN ('out', 'dispense', 'issue')
        ");
        $stmt_out->execute([$proj_id]);
        $dispensed_items = $stmt_out->fetchAll(PDO::FETCH_ASSOC);

        $out_cost = 0;
        foreach ($dispensed_items as $item) {
            $out_cost += (floatval($item['quantity']) * floatval($item['cost_price']));
        }

        // 1.2 รายการรับเข้า/รับคืนสินค้า
        $stmt_in = $pdo->prepare("
            SELECT s.*, p.name AS product_name, p.sku, 
                   COALESCE(NULLIF(s.unit_cost, 0), NULLIF(p.cost_price, 0), 0) AS cost_price 
            FROM stock_transactions s
            JOIN products p ON s.product_id = p.id
            WHERE s.installation_id = ? AND LOWER(s.type) IN ('in', 'return', 'receive')
        ");
        $stmt_in->execute([$proj_id]);
        $received_in_items = $stmt_in->fetchAll(PDO::FETCH_ASSOC);

        $in_cost = 0;
        foreach ($received_in_items as $item) {
            $in_cost += (floatval($item['quantity']) * floatval($item['cost_price']));
        }

        $net_material_cost = $out_cost - $in_cost; // เบิกหักรับคืน

        // 1.3 รายการค่าใช้จ่ายอื่นๆ (Expenses)
        $stmt_expenses = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE installation_id = ?");
        $stmt_expenses->execute([$proj_id]);
        $other_cost = floatval($stmt_expenses->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // 1.4 รายการรายรับ (Incomes) ของโครงการนี้
        $stmt_incomes = $pdo->prepare("SELECT SUM(amount) as total FROM incomes WHERE installation_id = ?");
        $stmt_incomes->execute([$proj_id]);
        $project_income = floatval($stmt_incomes->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $grand_total = $net_material_cost + $other_cost;
        $project_net_profit = $project_income - $grand_total; // กำไรสุทธิประจำโครงการ

        $total_all_dispensed_cost += $out_cost;
        $total_all_returned_cost  += $in_cost;
        $total_all_material_cost  += $net_material_cost;
        $total_all_other_expenses += $other_cost;
        $total_all_expenses       += $grand_total;
        $total_all_incomes        += $project_income;

        $project_summaries[] = [
            'project_id'         => $proj_id,
            'project_name'       => $proj['name'],
            'project_status'     => $proj['status'],
            'progress'           => floatval($proj['avg_progress']),
            'out_cost'           => $out_cost,
            'in_cost'            => $in_cost,
            'net_material_cost'  => $net_material_cost,
            'other_cost'         => $other_cost,
            'project_income'     => $project_income,
            'grand_total'        => $grand_total,
            'project_net_profit' => $project_net_profit
        ];
    }

    // คำนวณกำไรสุทธิภาพรวมทั้งหมด
    $total_all_net_profit = $total_all_incomes - $total_all_expenses;

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานการเงินและผลประกอบการโครงการ - SolarStock Pro</title>
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

    <!-- Sidebar -->
    <div id="sidebarWrapper" class="fixed lg:static inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out bg-white border-r border-slate-200 w-64 flex-shrink-0 flex flex-col shadow-xl lg:shadow-none">
        <div class="flex-1 overflow-y-auto">
            <?php 
            if (file_exists('sidebar_admin.php')) {
                include 'sidebar_admin.php'; 
            } elseif (file_exists('sidebar_admin.php')) {
                include 'sidebar_admin.php';
            }
            ?>
        </div>
    </div>

    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-20 hidden lg:hidden"></div>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="text-sm md:text-xl font-bold text-slate-900 tracking-tight">รายงานการเงินและผลประกอบการโครงการ</h2>
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
                        <p class="text-xs text-slate-500">เลือกโครงการที่ต้องการเรียกดูและสรุปผลการดำเนินงาน</p>
                    </div>
                </div>

                <form method="GET" action="" class="flex items-center gap-3 w-full md:w-auto">
                    <select name="installation_id" onchange="this.form.submit()" class="w-full md:w-72 px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-semibold text-slate-700 cursor-pointer">
                        <option value="all" <?= ($selected_installation_id === 'all') ? 'selected' : '' ?>>-- แสดงทุกโครงการทั้งหมด --</option>
                        <?php foreach ($all_installations as $inst): ?>
                            <option value="<?= $inst['id'] ?>" <?= ($selected_installation_id == $inst['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Cards สถิติภาพรวมทางการเงิน -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white p-5 rounded-3xl border border-slate-200/85 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">รายรับรวม (+)</p>
                        <h3 class="text-xl font-extrabold text-emerald-600">฿<?= number_format($total_all_incomes, 2) ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shadow-inner">
                        <i class="fa-solid fa-sack-dollar"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/85 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">เบิกวัสดุ (-)</p>
                        <h3 class="text-xl font-extrabold text-rose-500">฿<?= number_format($total_all_dispensed_cost, 2) ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-500 flex items-center justify-center text-lg shadow-inner">
                        <i class="fa-solid fa-box-archive"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/85 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">รับคืน (+)</p>
                        <h3 class="text-xl font-extrabold text-purple-600">฿<?= number_format($total_all_returned_cost, 2) ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg shadow-inner">
                        <i class="fa-solid fa-rotate-left"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/85 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">ต้นทุนวัสดุสุทธิ</p>
                        <h3 class="text-xl font-extrabold text-indigo-600">฿<?= number_format($total_all_material_cost, 2) ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg shadow-inner">
                        <i class="fa-solid fa-boxes-packing"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-3xl border border-slate-200/85 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">กำไรสุทธิ</p>
                        <h3 class="text-xl font-extrabold <?= $total_all_net_profit >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
                            ฿<?= number_format($total_all_net_profit, 2) ?>
                        </h3>
                    </div>
                    <div class="w-12 h-12 rounded-2xl <?= $total_all_net_profit >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?> flex items-center justify-center text-lg shadow-inner">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
            </div>

            <!-- ตารางแสดงรายละเอียดสรุปกำไร-ขาดทุน รายโครงการ -->
            <div class="bg-white rounded-3xl border border-slate-200/85 shadow-sm overflow-hidden p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-calculator text-brandGreen me-2"></i> สรุปผลกำไร-ขาดทุน รายโครงการ</h3>
                        <p class="text-xs text-slate-500">คลิกที่แถวโครงการเพื่อเปิดดูรายละเอียดเชิงลึกในหน้าใหม่</p>
                    </div>
                    <span class="text-xs text-slate-400 font-medium">แสดงผล <?= count($project_summaries) ?> โครงการ</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[950px]">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-4 px-4">โครงการ</th>
                                <th class="py-4 px-4 text-center">สถานะงาน</th>
                                <th class="py-4 px-4 text-right">รายรับรวม (+)</th>
                                <th class="py-4 px-4 text-right">เบิกวัสดุ (-)</th>
                                <th class="py-4 px-4 text-right text-purple-600">รับคืน (+)</th>
                                <th class="py-4 px-4 text-right">ต้นทุนวัสดุสุทธิ</th>
                                <th class="py-4 px-4 text-right">ค่าใช้จ่ายอื่น (-)</th>
                                <th class="py-4 px-4 text-right">กำไร (ขาดทุน) สุทธิ</th>
                                <th class="py-4 px-4 text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium">
                            <?php if (!empty($project_summaries)): ?>
                                <?php foreach ($project_summaries as $p): 
                                    $progress = $p['progress'];
                                    if ($progress >= 100) {
                                        $badge = '<span class="bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-emerald-200">เสร็จสิ้น</span>';
                                    } elseif ($progress > 0) {
                                        $badge = '<span class="bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-blue-200">' . number_format($progress, 0) . '%</span>';
                                    } else {
                                        $badge = '<span class="bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-amber-200">รอดำเนินการ</span>';
                                    }
                                    
                                    $is_profit = $p['project_net_profit'] >= 0;
                                    $net_profit_color = $is_profit ? 'text-emerald-700 bg-emerald-50' : 'text-rose-700 bg-rose-50';
                                ?>
                                    <tr class="hover:bg-slate-50 transition cursor-pointer" onclick="window.location.href='project_detail_admin.php?id=<?= $p['project_id'] ?>'">
                                        <td class="py-4 px-4">
                                            <div class="font-bold text-slate-900 text-sm flex items-center gap-2">
                                                <?= htmlspecialchars($p['project_name']) ?>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4 text-center"><?= $badge ?></td>
                                        <td class="py-4 px-4 text-right font-semibold text-emerald-600">+฿<?= number_format($p['project_income'], 2) ?></td>
                                        <td class="py-4 px-4 text-right font-semibold text-rose-500">฿<?= number_format($p['out_cost'], 2) ?></td>
                                        <td class="py-4 px-4 text-right font-bold text-purple-600">+฿<?= number_format($p['in_cost'], 2) ?></td>
                                        <td class="py-4 px-4 text-right font-semibold text-indigo-600">฿<?= number_format($p['net_material_cost'], 2) ?></td>
                                        <td class="py-4 px-4 text-right font-semibold text-amber-600">฿<?= number_format($p['other_cost'], 2) ?></td>
                                        <td class="py-4 px-4 text-right">
                                            <span class="inline-block px-3 py-1.5 rounded-xl font-extrabold text-xs <?= $net_profit_color ?>">
                                                <?= $is_profit ? '+' : '' ?>฿<?= number_format($p['project_net_profit'], 2) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-center">
                                            <a href="project_detail_admin.php?id=<?= $p['project_id'] ?>" onclick="event.stopPropagation();" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[11px] font-bold rounded-xl transition inline-flex items-center gap-1">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i> เปิดดูหน้าใหม่
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-12 text-slate-400">ไม่พบข้อมูลโครงการในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal พิมพ์รายงาน PDF (TH Sarabun) -->
    <div id="printReportModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 md:p-10 shadow-2xl border border-slate-200 space-y-6 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-200 pb-5 no-print">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white flex items-center justify-center font-bold"><i class="fa-solid fa-file-pdf text-lg"></i></div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">ตัวอย่างรายงานสรุปผลประกอบการโครงการ</h3>
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
                            <p class="text-sm font-semibold text-slate-600">รายงานสรุปรายรับ ค่าใช้จ่าย และกำไรสุทธิโครงการ</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-0.5 bg-emerald-50 text-emerald-800 border border-emerald-300 rounded text-xs font-bold uppercase mb-1">OFFICIAL REPORT</span>
                        <p class="text-xs font-semibold text-slate-600">วันที่พิมพ์: <?= date('d/m/Y') ?></p>
                    </div>
                </div>

                <div class="space-y-3">
                    <h4 class="font-bold text-slate-800 uppercase text-sm border-b border-slate-300 pb-1">สรุปภาพรวมทางการเงิน</h4>
                    <div class="grid grid-cols-5 gap-3 text-xs font-semibold">
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] text-slate-500 block">รายรับรวม</span>
                            <span class="text-emerald-700 font-bold">+฿<?= number_format($total_all_incomes, 2) ?></span>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] text-slate-500 block">เบิกออก</span>
                            <span class="text-rose-600 font-bold">฿<?= number_format($total_all_dispensed_cost, 2) ?></span>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] text-slate-500 block">รับคืน</span>
                            <span class="text-purple-700 font-bold">+฿<?= number_format($total_all_returned_cost, 2) ?></span>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] text-slate-500 block">ต้นทุนวัสดุสุทธิ</span>
                            <span class="text-indigo-700 font-bold">฿<?= number_format($total_all_material_cost, 2) ?></span>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] text-slate-500 block">กำไรสุทธิรวม</span>
                            <span class="<?= $total_all_net_profit >= 0 ? 'text-emerald-800' : 'text-rose-800' ?> font-extrabold">฿<?= number_format($total_all_net_profit, 2) ?></span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 pt-4">
                    <h4 class="font-bold text-slate-800 uppercase text-sm border-b border-slate-300 pb-1">รายละเอียดรายโครงการ</h4>
                    <table class="w-full text-left border-collapse border border-slate-300 text-xs">
                        <thead>
                            <tr class="bg-slate-800 text-white font-bold">
                                <th class="py-2 px-3 border border-slate-700">ชื่อโครงการ</th>
                                <th class="py-2 px-3 border border-slate-700 text-right">รายรับรวม</th>
                                <th class="py-2 px-3 border border-slate-700 text-right">เบิกออก</th>
                                <th class="py-2 px-3 border border-slate-700 text-right">รับคืน</th>
                                <th class="py-2 px-3 border border-slate-700 text-right">ต้นทุนสุทธิ</th>
                                <th class="py-2 px-3 border border-slate-700 text-right">กำไรสุทธิ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if (!empty($project_summaries)): foreach ($project_summaries as $p): ?>
                            <tr>
                                <td class="py-2 px-3 border border-slate-300 font-semibold"><?= htmlspecialchars($p['project_name']) ?></td>
                                <td class="py-2 px-3 border border-slate-300 text-right text-emerald-700 font-semibold">+฿<?= number_format($p['project_income'], 2) ?></td>
                                <td class="py-2 px-3 border border-slate-300 text-right text-rose-600">฿<?= number_format($p['out_cost'], 2) ?></td>
                                <td class="py-2 px-3 border border-slate-300 text-right text-purple-700 font-semibold">+฿<?= number_format($p['in_cost'], 2) ?></td>
                                <td class="py-2 px-3 border border-slate-300 text-right text-indigo-700 font-semibold">฿<?= number_format($p['net_material_cost'], 2) ?></td>
                                <td class="py-2 px-3 border border-slate-300 text-right font-bold <?= $p['project_net_profit'] >= 0 ? 'text-emerald-800' : 'text-rose-800' ?>">฿<?= number_format($p['project_net_profit'], 2) ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="6" class="py-4 text-center text-slate-500 border border-slate-300">ไม่มีข้อมูลโครงการ</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-2 gap-8 pt-10 page-break-inside-avoid text-xs">
                    <div class="text-center p-4 border border-slate-300 rounded-xl bg-slate-50/40 space-y-12">
                        <p class="font-bold text-slate-800">ผู้จัดทำรายงาน</p>
                        <div class="space-y-1">
                            <p class="font-semibold text-slate-800">ลงชื่อ............................................................</p>
                            <p class="text-slate-600">วันที่.........../.........../............</p>
                        </div>
                    </div>
                    <div class="text-center p-4 border border-slate-300 rounded-xl bg-slate-50/40 space-y-12">
                        <p class="font-bold text-slate-800">ผู้ตรวจสอบ / ผู้อนุมัติ</p>
                        <div class="space-y-1">
                            <p class="font-semibold text-slate-800">ลงชื่อ............................................................</p>
                            <p class="text-slate-600">วันที่.........../.........../............</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script สำหรับควบคุม Sidebar และ Modal พิมพ์ -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function openPrintModal() {
            let modal = document.getElementById('printReportModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closePrintModal() {
            let modal = document.getElementById('printReportModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    </script>
</body>
</html>