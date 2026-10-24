<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = intval($_GET['id'] ?? 0);
$error_message = '';
$success_message = '';
$project = null;
$all_projects = [];
$payment_terms_list = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_payment_terms') {
    $post_id = intval($_POST['project_id'] ?? 0);
    $terms_input = $_POST['terms'] ?? [];
    
    $new_terms_array = [];
    if (is_array($terms_input)) {
        foreach ($terms_input as $term) {
            $new_terms_array[] = [
                'pct' => trim($term['pct'] ?? ''),
                'desc' => trim($term['desc'] ?? ''),
                'amt' => trim($term['amt'] ?? ''),
                'status' => trim($term['status'] ?? 'pending')
            ];
        }
    }
    
    $json_terms = json_encode($new_terms_array, JSON_UNESCAPED_UNICODE);

    try {
        $update_stmt = $pdo->prepare("UPDATE project_designs SET payment_terms_json = ? WHERE id = ?");
        $update_stmt->execute([$json_terms, $post_id]);
        header("Location: view_installation.php?id=" . $post_id . "&status=success");
        exit();
    } catch (PDOException $e) {
        $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $success_message = "อัปเดตสถานะงวดชำระเงินเรียบร้อยแล้ว";
}

try {
    $stmt_all = $pdo->query("SELECT id, name, customer_name, project_sale_no FROM project_designs ORDER BY id DESC");
    $all_projects = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

    if ($id <= 0 && !empty($all_projects)) {
        $id = $all_projects[0]['id'];
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM project_designs WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$project) {
        $error_message = "ไม่พบข้อมูลโครงการในระบบฐานข้อมูล";
    } else {
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
    <title>Project Design / Kick Off Form - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 
                        sans: ['Prompt', 'Inter', 'sans-serif'],
                        sarabun: ['Sarabun', 'sans-serif']
                    },
                    colors: { brandGreen: '#059669' }
                }
            }
        }
    </script>
    <style>
        .font-sarabun { font-family: 'Sarabun', sans-serif !important; }
        .fit-text-container p, .fit-text-container div {
            word-break: break-word;
            overflow-wrap: break-word;
            line-height: 1.35;
        }
        @media print {
            @page { size: A4 portrait; margin: 5mm; }
            body { background: white !important; color: #0f172a !important; font-family: 'Sarabun', sans-serif !important; font-size: 8.5pt !important; }
            #sidebarWrapper, #sidebar-overlay, header, .no-print { display: none !important; }
            body * { visibility: hidden; }
            #printableProjectReport, #printableProjectReport * { visibility: visible; }
            #printableProjectReport {
                position: absolute; left: 0; top: 0; width: 100%;
                background: white !important; z-index: 99999; padding: 2px !important;
                margin: 0 !important; border: none !important; box-shadow: none !important;
            }
            .print-hidden-status { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden relative">

    <!-- Sidebar -->
    <div id="sidebarWrapper" class="fixed md:static inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out bg-white border-r border-slate-200 w-64 shrink-0 flex flex-col shadow-xl md:shadow-none h-full overflow-y-auto no-print">
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

    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden no-print"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 shrink-0 z-10 shadow-sm no-print">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-emerald-600 p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 transition focus:outline-none shrink-0">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="text-sm md:text-xl font-bold text-slate-900 tracking-tight truncate"><i class="fa-solid fa-file-contract text-emerald-600 me-2"></i>PROJECT DESIGN / KICK OFF FORM</h2>
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">แบบฟอร์มรายละเอียดโครงการและข้อมูลติดตั้งระบบโซล่าเซลล์</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-2xl text-xs md:text-sm font-bold flex items-center gap-2 shadow-sm transition cursor-pointer">
                    <i class="fa-solid fa-print"></i> <span class="hidden sm:inline">พิมพ์ / ออกรายงาน PDF</span>
                </button>
                <a href="Dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 md:px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold transition-all flex items-center gap-2 shrink-0">
                    <i class="fa-solid fa-house text-xs"></i> <span class="hidden sm:inline">กลับหน้ารายการ</span>
                </a>
            </div>
        </header>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8 w-full">
            <div class="max-w-4xl mx-auto space-y-6">

                <?php if (!empty($success_message)): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs flex items-center justify-between shadow-sm no-print">
                        <span>✨ <?= htmlspecialchars($success_message) ?></span>
                        <a href="view_installation.php?id=<?= $id ?>" class="font-bold hover:underline">✕ ปิด</a>
                    </div>
                <?php endif; ?>

                <!-- Dropdown Switcher เลือกดูโครงการอื่น -->
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-4 flex flex-col sm:flex-row items-center justify-between gap-3 no-print">
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-700 w-full sm:w-auto">
                        <i class="fa-solid fa-filter text-emerald-600"></i> เลือกดูโครงการอื่น:
                    </div>
                    <select onchange="if(this.value) window.location.href='view_installation.php?id=' + this.value;" class="w-full sm:w-96 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none font-semibold transition">
                        <?php foreach ($all_projects as $ap): ?>
                            <option value="<?= $ap['id'] ?>" <?= ($ap['id'] == $id) ? 'selected' : '' ?>>
                                 <?= htmlspecialchars($ap['customer_name']) ?>
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

                    <!-- ฟอร์มรูปแบบ Kick Off Form -->
                    <div id="printableProjectReport" class="bg-white rounded-3xl border border-slate-300 shadow-lg p-5 md:p-6 space-y-3.5 font-sarabun text-xs text-slate-800 fit-text-container">
                        
                        <!-- Header Form -->
                        <div class="border-b-2 border-slate-900 pb-2.5 flex justify-between items-start">
                            <div class="flex items-center gap-3">
                                <div class="font-extrabold text-base tracking-tighter text-emerald-700 leading-none">WING<br><span class="text-slate-900 text-xs">SOLAR</span></div>
                                <div class="border-l-2 border-slate-900 pl-3">
                                    <h1 class="text-base font-extrabold text-slate-900 uppercase tracking-tight">PROJECT DESIGN / KICK OFF FORM</h1>
                                </div>
                            </div>
                            <div class="text-right text-[11px] space-y-0.5 bg-slate-50 p-2 rounded-xl border border-slate-200 shrink-0">
                                <p><span class="font-bold text-slate-600">Project Sale No:</span> <span class="text-emerald-700 font-extrabold"><?= htmlspecialchars($project['project_sale_no'] ?: '-') ?></span></p>
                                <p><span class="font-bold text-slate-600">Project No:</span> <span class="font-bold text-slate-900"><?= htmlspecialchars($project['project_no'] ?: '-') ?></span></p>
                                <p><span class="font-bold text-slate-600">Project ID:</span> <span class="font-bold text-slate-900">#<?= $project['id'] ?></span></p>
                            </div>
                        </div>

                        <!-- 1. ส่วนข้อมูลโครงการและสถานที่ -->
                        <div class="bg-slate-50/60 p-3 rounded-2xl border border-slate-200 grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-1.5 text-xs leading-snug">
                            <div><span class="font-bold text-slate-700">Project Name:</span> <span class="font-extrabold text-emerald-800"><?= htmlspecialchars($project['name']) ?></span></div>
                            <div><span class="font-bold text-slate-700">Installation Address:</span> <?= htmlspecialchars($project['installation_address'] ?: '-') ?></div>
                            <div><span class="font-bold text-slate-700">Customer Name:</span> <span class="font-bold text-slate-900"><?= htmlspecialchars($project['customer_name'] ?: '-') ?></span></div>
                            <div><span class="font-bold text-slate-700">Billing Address:</span> <?= htmlspecialchars($project['billing_address'] ?: '-') ?></div>
                            <div><span class="font-bold text-slate-700">Contract Person:</span> <?= htmlspecialchars($project['contract_person'] ?: '-') ?></div>
                            <div><span class="font-bold text-slate-700">Location (Lat/Long):</span> <span class="font-mono bg-white px-1.5 py-0.5 rounded border border-slate-200"><?= htmlspecialchars($project['lat_long'] ?: '-') ?></span>
                                <?php if (!empty($project['map_url'])): ?>
                                    <a href="<?= htmlspecialchars($project['map_url']) ?>" target="_blank" class="text-blue-600 font-bold hover:underline no-print ms-1"><i class="fa-solid fa-map-location-dot"></i> แผนที่</a>
                                <?php endif; ?>
                            </div>
                            <div><span class="font-bold text-slate-700">Customer Phone No:</span> <?= htmlspecialchars($project['customer_phone'] ?: '-') ?></div>
                            <div><span class="font-bold text-slate-700">Sales Representative:</span> <?= htmlspecialchars($project['sales_rep'] ?: '-') ?></div>
                        </div>

                        <!-- 2. Commercial Offer & System Specs -->
                        <div class="space-y-1">
                            <div class="bg-slate-800 text-white font-bold uppercase px-3 py-1 rounded-lg text-[11px] flex items-center justify-between">
                                <span><i class="fa-solid fa-sliders text-emerald-400 me-1.5"></i> Commercial Offer & System Specs</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-2.5 border border-slate-200 rounded-xl bg-white text-xs">
                                <div><span class="font-bold text-slate-600">System Type:</span> <span class="font-semibold text-slate-800"><?= htmlspecialchars($project['system_type'] ?: 'On-Grid') ?></span></div>
                                <div><span class="font-bold text-slate-600">System Capacity:</span> <span class="text-emerald-700 font-extrabold"><?= htmlspecialchars($project['system_size'] ?: '-') ?></span></div>
                                <div><span class="font-bold text-slate-600">Meter # / Type:</span> <?= htmlspecialchars($project['meter_no'] ?: '-') ?> / <?= htmlspecialchars($project['meter_type'] ?: '-') ?></div>
                                <div><span class="font-bold text-slate-600">MEA / PEA Connection:</span> <?= htmlspecialchars($project['mea_pea_connection'] ?: '-') ?></div>
                                <div><span class="font-bold text-slate-600">Warranty:</span> <?= htmlspecialchars($project['warranty'] ?: '5 Years') ?></div>
                                <div><span class="font-bold text-slate-600">O&M:</span> <?= htmlspecialchars($project['om'] ?: '-') ?></div>
                            </div>
                        </div>

                        <!-- 3. System Requirements -->
                        <div class="space-y-1">
                            <div class="bg-slate-800 text-white font-bold uppercase px-3 py-1 rounded-lg text-[11px] flex items-center justify-between">
                                <span><i class="fa-solid fa-microchip text-emerald-400 me-1.5"></i> System Requirements</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 p-2.5 border border-slate-200 rounded-xl bg-white text-xs">
                                <div><span class="font-bold text-slate-600">Panel Spec:</span> <?= htmlspecialchars($project['panel_spec'] ?: '-') ?></div>
                                <div><span class="font-bold text-slate-600">Inverter Spec:</span> <?= htmlspecialchars($project['inverter_spec'] ?: '-') ?></div>
                                <div><span class="font-bold text-slate-600">Mounting:</span> <?= htmlspecialchars($project['mounting'] ?: '-') ?></div>
                                <div><span class="font-bold text-slate-600">Roof Change:</span> <?= htmlspecialchars($project['roof_change'] ?: 'No') ?></div>
                                <div><span class="font-bold text-slate-600">Structure Addition:</span> <?= htmlspecialchars($project['structure_addition'] ?: 'No') ?></div>
                                <div><span class="font-bold text-slate-600">Walk way:</span> <?= htmlspecialchars($project['walk_way'] ?: '-') ?></div>
                                <div class="col-span-1 sm:col-span-2"><span class="font-bold text-slate-600">Work Safety Requirement:</span> <?= htmlspecialchars($project['work_safety_req'] ?: '-') ?></div>
                                <div><span class="font-bold text-slate-600">Connection Points:</span> <?= htmlspecialchars($project['connection_points'] ?: '-') ?></div>
                                <div><span class="font-bold text-slate-600">Zero Export:</span> <?= htmlspecialchars($project['zero_export'] ?: '-') ?></div>
                                <div><span class="font-bold text-slate-600">String Monitoring:</span> <?= htmlspecialchars($project['string_monitoring'] ?: '-') ?></div>
                            </div>
                        </div>

                        <!-- 4. Payment Terms -->
                        <div class="space-y-1">
                            <div class="bg-slate-800 text-white font-bold uppercase px-3 py-1 rounded-lg text-[11px] flex items-center justify-between">
                                <span><i class="fa-solid fa-credit-card text-emerald-400 me-1.5"></i> Payment Terms</span>
                                <div class="flex items-center gap-2">
                                    <span class="bg-emerald-600 text-white text-[10px] px-2 py-0.5 rounded-md font-bold">รวม VAT: <?= number_format((float)($project['total_price'] ?? 0), 2) ?> บาท</span>
                                    <button onclick="openPaymentModal()" class="bg-amber-400 hover:bg-amber-500 text-slate-900 px-2 py-0.5 rounded text-[10px] font-bold no-print transition">
                                        <i class="fa-solid fa-pen-to-square me-1"></i> แก้ไข / ไฮไลท์
                                    </button>
                                </div>
                            </div>

                            <?php if (!empty($payment_terms_list) && is_array($payment_terms_list)): ?>
                                <div class="overflow-hidden border border-slate-300 rounded-xl">
                                    <table class="w-full text-left border-collapse text-xs table-fixed">
                                        <thead>
                                            <tr class="bg-slate-100 text-slate-800 font-bold border-b border-slate-300">
                                                <th class="py-1.5 px-2 text-center w-14 border-r border-slate-300">งวดที่</th>
                                                <th class="py-1.5 px-3 border-r border-slate-300">เงื่อนไข / รายละเอียด</th>
                                                <th class="py-1.5 px-2 text-center w-20 border-r border-slate-300">%</th>
                                                <th class="py-1.5 px-3 text-right w-36 border-r border-slate-300">จำนวนเงิน (Inc VAT)</th>
                                                <th class="py-1.5 px-2 text-center w-24 print-hidden-status">สถานะ</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200">
                                            <?php foreach ($payment_terms_list as $idx => $term): 
                                                $status = $term['status'] ?? 'pending';
                                                $is_paid = ($status === 'paid');
                                            ?>
                                                <tr class="<?= $is_paid ? 'bg-emerald-50/90 font-semibold text-emerald-950 print-reset-bg' : 'bg-white' ?>">
                                                    <td class="py-1.5 px-2 text-center font-bold border-r border-slate-200"><?= $idx + 1 ?></td>
                                                    <td class="py-1.5 px-3 border-r border-slate-200 truncate"><?= htmlspecialchars($term['desc'] ?? '-') ?></td>
                                                    <td class="py-1.5 px-2 text-center text-emerald-700 font-bold border-r border-slate-200"><?= htmlspecialchars($term['pct'] ?? '-') ?>%</td>
                                                    <td class="py-1.5 px-3 text-right font-extrabold text-slate-900 border-r border-slate-200"><?= !empty($term['amt']) ? number_format((float)str_replace(',', '', $term['amt']), 2) : '-' ?></td>
                                                    <td class="py-1.5 px-2 text-center print-hidden-status">
                                                        <?= $is_paid ? '<span class="bg-emerald-100 text-emerald-800 text-[10px] px-2 py-0.5 rounded-full font-bold">ชำระแล้ว</span>' : '<span class="bg-slate-100 text-slate-500 text-[10px] px-2 py-0.5 rounded-full font-medium">รอชำระ</span>' ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-400">ไม่มีข้อมูลเงื่อนไขการชำระเงิน</div>
                            <?php endif; ?>
                        </div>

                        <!-- 5. Signatures -->
                        <div class="grid grid-cols-2 gap-8 pt-3 text-center text-xs">
                            <div class="space-y-3">
                                <p class="font-bold text-slate-700">Sales Representative</p>
                                <div class="border-b border-slate-400 w-3/4 mx-auto pt-3"></div>
                                <p class="font-medium text-slate-800">( <?= htmlspecialchars($project['sales_rep'] ?: '......................................................') ?> )</p>
                            </div>
                            <div class="space-y-3">
                                <p class="font-bold text-slate-700">Approved by Customer</p>
                                <div class="border-b border-slate-400 w-3/4 mx-auto pt-3"></div>
                                <p class="font-medium text-slate-800">( <?= htmlspecialchars($project['customer_name'] ?: '......................................................') ?> )</p>
                            </div>
                        </div>

                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <!-- Modal สำหรับแก้ไข / ไฮไลท์งวดชำระเงิน -->
    <div id="paymentModal" class="fixed inset-0 bg-slate-900/75 backdrop-blur-xs hidden items-center justify-center z-50 p-4 overflow-y-auto no-print">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 md:p-8 shadow-2xl border border-slate-200 space-y-6 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-800 text-base"><i class="fa-solid fa-pen-to-square text-amber-500 me-2"></i> แก้ไข / ไฮไลท์สถานะงวดชำระเงิน</h3>
                <button onclick="closePaymentModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="" method="POST" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="update_payment_terms">
                <input type="hidden" name="project_id" value="<?= $id ?>">

                <div class="space-y-3">
                    <?php if (!empty($payment_terms_list)): ?>
                        <?php foreach ($payment_terms_list as $idx => $term): 
                            $status = $term['status'] ?? 'pending';
                        ?>
                            <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                                <div class="flex items-center justify-between font-bold text-slate-700">
                                    <span>งวดที่ <?= $idx + 1 ?></span>
                                    <label class="flex items-center gap-1.5 cursor-pointer text-emerald-700 font-bold bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-xl">
                                        <input type="checkbox" name="terms[<?= $idx ?>][status]" value="paid" <?= ($status === 'paid') ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                                        <span>ไฮไลท์: ชำระแล้ว (Paid)</span>
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <div>
                                        <label class="block font-semibold text-slate-500 mb-1">เปอร์เซ็นต์ (%)</label>
                                        <input type="text" name="terms[<?= $idx ?>][pct]" value="<?= htmlspecialchars($term['pct'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl bg-white font-medium">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block font-semibold text-slate-500 mb-1">จำนวนเงิน (บาท)</label>
                                        <input type="text" name="terms[<?= $idx ?>][amt]" value="<?= htmlspecialchars($term['amt'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl bg-white font-medium">
                                    </div>
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-500 mb-1">รายละเอียดเงื่อนไข</label>
                                    <input type="text" name="terms[<?= $idx ?>][desc]" value="<?= htmlspecialchars($term['desc'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-200 rounded-xl bg-white font-medium">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-slate-400 py-4">ไม่พบข้อมูลเงื่อนไขการชำระเงินในโครงการนี้</p>
                    <?php endif; ?>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closePaymentModal()" class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition">ยกเลิก</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition shadow-sm"><i class="fa-solid fa-save me-1"></i> บันทึกการเปลี่ยนแปลง</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function openPaymentModal() {
            const modal = document.getElementById('paymentModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closePaymentModal() {
            const modal = document.getElementById('paymentModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    </script>
</body>
</html>