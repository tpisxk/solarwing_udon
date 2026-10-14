<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = intval($_GET['id'] ?? 0);
$error_message = '';
$project = null;
$all_projects = [];
$payment_terms_list = [];

try {
    // 1. ดึงรายชื่อโครงการทั้งหมดสำหรับทำ Dropdown เลือกดู
    $stmt_all = $pdo->query("SELECT id, name, project_sale_no FROM project_designs ORDER BY id DESC");
    $all_projects = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

    // 2. ถ้าไม่ได้ส่ง ID มา ให้เลือกโครงการแรกสุดในรายการอัตโนมัติ
    if ($id <= 0 && !empty($all_projects)) {
        $id = $all_projects[0]['id'];
    }

    if ($id > 0) {
        // ดึงข้อมูลทั้งหมดจากตาราง project_designs โดยตรง
        $stmt = $pdo->prepare("SELECT * FROM project_designs WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$project) {
        $error_message = "ไม่พบข้อมูลโครงการในระบบฐานข้อมูล";
    } else {
        // แปลงข้อมูล JSON งวดชำระเงิน
        if (!empty($project['payment_terms_json'])) {
            $payment_terms_list = json_decode($project['payment_terms_json'], true);
        }
        if (!is_array($payment_terms_list)) {
            $payment_terms_list = [];
        }
    }
} catch (PDOException $e) {
    $error_message = "เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียด Project Design - SolarStock Pro</title>
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
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden relative">

    <!-- Sidebar สำหรับมือถือและคอมพิวเตอร์ -->
    <div id="sidebarWrapper" class="fixed md:static inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out bg-white border-r border-slate-200 w-64 shrink-0 flex flex-col shadow-xl md:shadow-none h-full overflow-y-auto">
        <div class="h-16 flex items-center justify-between px-4 border-b border-slate-100 shrink-0 md:hidden">
            <span class="font-bold text-slate-800 text-sm">SolarStock Menu</span>
            <button onclick="toggleSidebar()" class="text-slate-500 hover:text-slate-800 p-2 rounded-lg">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto">
            <?php 
            if (file_exists('sidebar.php')) {
                include 'sidebar.php';
            } elseif (file_exists('sidebar_admin.php')) {
                include 'sidebar_admin.php';
            }
            ?>
        </div>
    </div>

    <!-- Background Overlay -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-emerald-600 p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 transition focus:outline-none shrink-0">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="text-sm md:text-xl font-bold text-slate-900 tracking-tight truncate">PROJECT DESIGN DETAILS</h2>
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">แสดงรายละเอียดโครงการทั้งหมดจากตาราง project_designs</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="work_progress.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 md:px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold transition-all flex items-center gap-2 shrink-0">
                    <i class="fa-solid fa-arrow-left text-xs"></i> <span class="hidden sm:inline">กลับหน้ารายการ</span>
                </a>
            </div>
        </header>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8 w-full">
            <div class="max-w-4xl mx-auto space-y-6">

                <!-- แถบเลือกดูโครงการทั้งหมด (Dropdown Switcher) -->
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-700 w-full sm:w-auto">
                        <i class="fa-solid fa-filter text-emerald-600"></i> เลือกดูโครงการอื่น:
                    </div>
                    <select onchange="if(this.value) window.location.href='view_installation.php?id=' + this.value;" class="w-full sm:w-80 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none font-semibold transition">
                        <?php foreach ($all_projects as $ap): ?>
                            <option value="<?= $ap['id'] ?>" <?= ($ap['id'] == $id) ? 'selected' : '' ?>>
                                [ID: <?= $ap['id'] ?>] <?= htmlspecialchars($ap['name']) ?> <?= !empty($ap['project_sale_no']) ? '('.$ap['project_sale_no'].')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($error_message) && !$project): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 px-6 py-8 rounded-3xl text-center space-y-3">
                        <i class="fa-solid fa-triangle-exclamation text-3xl text-rose-500"></i>
                        <h3 class="font-bold text-base"><?= htmlspecialchars($error_message) ?></h3>
                    </div>
                <?php else: ?>

                    <!-- การ์ดแสดงข้อมูลโครงการทั้งหมด -->
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 lg:p-8 space-y-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-6 border-b border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-brandGreen flex items-center justify-center text-lg shadow-inner shrink-0">
                                    <i class="fa-solid fa-solar-panel"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-900"><?= htmlspecialchars($project['name']) ?></h3>
                                    <p class="text-xs text-slate-500">สถานะ: <span class="font-semibold text-emerald-600"><?= htmlspecialchars($project['status'] ?? 'In Progress') ?></span> | วันที่สร้าง: <span class="text-slate-600"><?= htmlspecialchars($project['created_at'] ?? '-') ?></span></p>
                                </div>
                            </div>
                            <?php if (!empty($project['map_url'])): ?>
                                <a href="<?= htmlspecialchars($project['map_url']) ?>" target="_blank" class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-4 py-2 rounded-2xl text-xs font-bold transition flex items-center gap-2">
                                    <i class="fa-solid fa-map-location-dot"></i> เปิดดูแผนที่ Google Maps
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- ข้อมูลทั่วไปและลูกค้า -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                            <div class="bg-slate-50 p-4 rounded-2xl space-y-2 border border-slate-100">
                                <p class="font-bold text-slate-700 uppercase mb-2"><i class="fa-solid fa-file-lines text-emerald-600 me-1"></i> ข้อมูลเลขที่และรหัส</p>
                                <p><span class="font-bold text-slate-500 uppercase">Project Sale No:</span> <span class="text-slate-900 font-semibold"><?= htmlspecialchars($project['project_sale_no'] ?: '-') ?></span></p>
                                <p><span class="font-bold text-slate-500 uppercase">Project No:</span> <span class="text-slate-900 font-semibold"><?= htmlspecialchars($project['project_no'] ?: '-') ?></span></p>
                                <p><span class="font-bold text-slate-500 uppercase">System Capacity:</span> <span class="text-emerald-600 font-bold"><?= htmlspecialchars($project['system_size'] ?: '-') ?></span></p>
                                <p><span class="font-bold text-slate-500 uppercase">Location (Lat/Long):</span> <span class="text-slate-900 font-mono"><?= htmlspecialchars($project['lat_long'] ?: '-') ?></span></p>
                            </div>

                            <div class="bg-slate-50 p-4 rounded-2xl space-y-2 border border-slate-100">
                                <p class="font-bold text-slate-700 uppercase mb-2"><i class="fa-solid fa-user-tie text-emerald-600 me-1"></i> ข้อมูลลูกค้าและผู้ติดต่อ</p>
                                <p><span class="font-bold text-slate-500 uppercase">Customer Name:</span> <span class="text-slate-900 font-semibold"><?= htmlspecialchars($project['customer_name'] ?: '-') ?></span></p>
                                <p><span class="font-bold text-slate-500 uppercase">Contract Person:</span> <span class="text-slate-900 font-semibold"><?= htmlspecialchars($project['contract_person'] ?: '-') ?></span></p>
                                <p><span class="font-bold text-slate-500 uppercase">Customer Phone:</span> <span class="text-slate-900 font-semibold"><?= htmlspecialchars($project['customer_phone'] ?: '-') ?></span></p>
                                <p><span class="font-bold text-slate-500 uppercase">Sales Representative:</span> <span class="text-slate-900 font-semibold"><?= htmlspecialchars($project['sales_rep'] ?: '-') ?></span></p>
                            </div>
                        </div>

                        <!-- ที่อยู่สถานที่ติดตั้งและใบกำกับภาษี -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-1">
                                <span class="font-bold text-slate-500 uppercase block">Installation Address:</span>
                                <span class="text-slate-800 font-medium"><?= htmlspecialchars($project['installation_address'] ?: '-') ?></span>
                            </div>
                            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-1">
                                <span class="font-bold text-slate-500 uppercase block">Billing Address:</span>
                                <span class="text-slate-800 font-medium"><?= htmlspecialchars($project['billing_address'] ?: '-') ?></span>
                            </div>
                        </div>

                        <!-- สเปกอุปกรณ์และรายละเอียดระบบ -->
                        <div class="space-y-3 pt-2">
                            <h4 class="text-xs font-bold text-slate-700 uppercase"><i class="fa-solid fa-sliders text-emerald-600 me-1"></i> สเปกอุปกรณ์และเงื่อนไขระบบ</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                                <div class="bg-emerald-50/50 p-3 rounded-xl border border-emerald-100">
                                    <span class="font-bold text-emerald-700 uppercase block mb-0.5">System Type</span>
                                    <span class="text-slate-800 font-semibold"><?= htmlspecialchars($project['system_type'] ?: '-') ?></span>
                                </div>
                                <div class="bg-emerald-50/50 p-3 rounded-xl border border-emerald-100">
                                    <span class="font-bold text-emerald-700 uppercase block mb-0.5">Meter # / Type</span>
                                    <span class="text-slate-800 font-semibold"><?= htmlspecialchars($project['meter_no'] ?: '-') ?> / <?= htmlspecialchars($project['meter_type'] ?: '-') ?></span>
                                </div>
                                <div class="bg-emerald-50/50 p-3 rounded-xl border border-emerald-100">
                                    <span class="font-bold text-emerald-700 uppercase block mb-0.5">MEA / PEA</span>
                                    <span class="text-slate-800 font-semibold"><?= htmlspecialchars($project['mea_pea_connection'] ?: '-') ?></span>
                                </div>
                                <div class="bg-emerald-50/50 p-3 rounded-xl border border-emerald-100">
                                    <span class="font-bold text-emerald-700 uppercase block mb-0.5">Warranty & O&M</span>
                                    <span class="text-slate-800 font-semibold"><?= htmlspecialchars($project['warranty'] ?: '-') ?> | <?= htmlspecialchars($project['om'] ?: '-') ?></span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs pt-2">
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <span class="font-bold text-slate-500 uppercase block mb-0.5">Panel Spec</span>
                                    <span class="text-slate-800 font-medium"><?= htmlspecialchars($project['panel_spec'] ?: '-') ?></span>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <span class="font-bold text-slate-500 uppercase block mb-0.5">Inverter Spec</span>
                                    <span class="text-slate-800 font-medium"><?= htmlspecialchars($project['inverter_spec'] ?: '-') ?></span>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <span class="font-bold text-slate-500 uppercase block mb-0.5">Mounting</span>
                                    <span class="text-slate-800 font-medium"><?= htmlspecialchars($project['mounting'] ?: '-') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- เงื่อนไขเพิ่มเติมและงานระบบ -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs pt-2">
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-1">
                                <p><span class="font-bold text-slate-500 uppercase">Roof / Structure Change:</span> <?= htmlspecialchars($project['roof_change'] ?: '-') ?> / <?= htmlspecialchars($project['structure_addition'] ?: '-') ?></p>
                                <p><span class="font-bold text-slate-500 uppercase">Connection Points:</span> <?= htmlspecialchars($project['connection_points'] ?: '-') ?></p>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-1">
                                <p><span class="font-bold text-slate-500 uppercase">Walk way / Installation:</span> <?= htmlspecialchars($project['walk_way'] ?: '-') ?> / <?= htmlspecialchars($project['installation_req'] ?: '-') ?></p>
                                <p><span class="font-bold text-slate-500 uppercase">Work Safety Req:</span> <?= htmlspecialchars($project['work_safety_req'] ?: '-') ?></p>
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-1">
                                <p><span class="font-bold text-slate-500 uppercase">Zero Export / String Mon:</span> <?= htmlspecialchars($project['zero_export'] ?: '-') ?> / <?= htmlspecialchars($project['string_monitoring'] ?: '-') ?></p>
                                <p><span class="font-bold text-slate-500 uppercase">Other Components:</span> <?= htmlspecialchars($project['other_components'] ?: '-') ?></p>
                            </div>
                        </div>

                        <!-- เงื่อนไขการชำระเงินและราคารวม -->
                        <div class="space-y-3 pt-4 border-t border-slate-100">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                                <h4 class="text-xs font-bold text-slate-700 uppercase"><i class="fa-solid fa-file-invoice-dollar text-brandGreen me-1"></i> เงื่อนไขการชำระเงิน (Payment Terms)</h4>
                                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 rounded-2xl text-xs font-bold">
                                    ราคารวมทั้งสิ้น: <span class="text-brandGreen text-sm"><?= number_format((float)($project['total_price'] ?? 0), 2) ?></span> บาท
                                </div>
                            </div>
                            
                            <?php if (!empty($payment_terms_list) && is_array($payment_terms_list)): ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse text-xs min-w-[500px]">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase font-bold">
                                                <th class="py-2.5 px-3 text-center">งวดที่</th>
                                                <th class="py-2.5 px-3">เปอร์เซ็นต์ (%)</th>
                                                <th class="py-2.5 px-3">เงื่อนไข / รายละเอียด</th>
                                                <th class="py-2.5 px-3 text-right">จำนวนเงิน (บาท)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                            <?php foreach ($payment_terms_list as $idx => $term): ?>
                                                <tr class="hover:bg-slate-50">
                                                    <td class="py-3 px-3 text-center font-bold text-slate-900">งวดที่ <?= $idx + 1 ?></td>
                                                    <td class="py-3 px-3 font-semibold text-brandGreen"><?= htmlspecialchars($term['pct'] ?? '-') ?>%</td>
                                                    <td class="py-3 px-3"><?= htmlspecialchars($term['desc'] ?? '-') ?></td>
                                                    <td class="py-3 px-3 text-right font-bold text-slate-900"><?= !empty($term['amt']) ? number_format((float)str_replace(',', '', $term['amt']), 2) : '-' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>
</html>