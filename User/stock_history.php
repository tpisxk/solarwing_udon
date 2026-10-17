<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'stock_history';

$filter_type         = isset($_GET['type']) ? trim($_GET['type']) : 'all';
$filter_installation = isset($_GET['installation_id']) && $_GET['installation_id'] !== '' ? $_GET['installation_id'] : 'all';
$search_keyword      = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_req_id     = isset($_GET['req_id']) ? intval($_GET['req_id']) : 0;
$form_filter_inst    = isset($_GET['form_inst_id']) && $_GET['form_inst_id'] !== '' ? $_GET['form_inst_id'] : 'all';
$form_search         = isset($_GET['form_search']) ? trim($_GET['form_search']) : '';

// 1. ดึงรายชื่อโครงการทั้งหมดสำหรับใส่ใน Dropdown กรอง
try {
    $stmt_inst = $pdo->query("SELECT id, name FROM installations ORDER BY id DESC");
    $installationsList = $stmt_inst->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $installationsList = [];
}

// 2. ดึงรายการใบเบิกทั้งหมดสำหรับทำ Dropdown และช่องกรองในหน้าฟอร์มย้อนหลัง
try {
    $req_sql = "
        SELECT req.*, i.name AS installation_name 
        FROM requisitions req 
        LEFT JOIN installations i ON req.installation_id = i.id 
        WHERE 1=1
    ";
    $req_params = [];

    if ($form_filter_inst !== 'all' && $form_filter_inst !== '') {
        $req_sql .= " AND req.installation_id = ?";
        $req_params[] = intval($form_filter_inst);
    }

    if (!empty($form_search)) {
        $req_sql .= " AND (req.project_name LIKE ? OR req.engineer_name LIKE ? OR req.id LIKE ? OR i.name LIKE ?)";
        $req_params[] = "%{$form_search}%";
        $req_params[] = "%{$form_search}%";
        $req_params[] = "%{$form_search}%";
        $req_params[] = "%{$form_search}%";
    }

    $req_sql .= " ORDER BY req.id DESC";
    $stmt_reqs = $pdo->prepare($req_sql);
    $stmt_reqs->execute($req_params);
    $allRequisitions = $stmt_reqs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $allRequisitions = [];
}

// 3. ดึงข้อมูลประวัติ stock_transactions (ปรับปรุง SQL ให้ปลอดภัยและแสดงผลแน่นอน)
try {
    $sql = "
        SELECT st.*, 
               p.name AS product_name, p.sku, p.stock_quantity AS current_stock,
               COALESCE(st.unit_cost, p.cost_price, 0) AS transaction_cost,
               i.name AS project_name,
               u.name AS user_name,
               req.id AS requisition_id, req.engineer_name, req.status AS req_status
        FROM stock_transactions st
        JOIN products p ON st.product_id = p.id
        LEFT JOIN installations i ON st.installation_id = i.id
        LEFT JOIN users u ON st.user_id = u.id
        LEFT JOIN requisitions req ON (st.reference_no = req.id OR st.reference_no = CONCAT('REQ-#', req.id) OR st.reference_no = CONCAT('RET-#', req.id) OR st.reference_no = req.project_name)
        WHERE 1=1
    ";
    $params = [];

    if ($filter_type !== 'all') {
        $sql .= " AND st.type = ?";
        $params[] = $filter_type;
    }

    if ($filter_installation !== 'all' && $filter_installation !== '') {
        $sql .= " AND st.installation_id = ?";
        $params[] = intval($filter_installation);
    }

    if (!empty($search_keyword)) {
        $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR st.reference_no LIKE ? OR req.project_name LIKE ? OR req.engineer_name LIKE ?)";
        $params[] = "%{$search_keyword}%";
        $params[] = "%{$search_keyword}%";
        $params[] = "%{$search_keyword}%";
        $params[] = "%{$search_keyword}%";
        $params[] = "%{$search_keyword}%";
    }

    $sql .= " ORDER BY st.created_at DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $transactions = [];
    $db_error = $e->getMessage();
}

// 4. ถ้ามีการเลือกดูใบเบิก (req_id) ให้ดึงข้อมูลรายละเอียดใบเบิกและรายการสินค้ามาแสดงเป็นฟอร์มแยกเดี่ยว
$active_requisition = null;
$requisition_items = [];

if ($selected_req_id > 0) {
    try {
        $stmt_req = $pdo->prepare("
            SELECT req.*, i.name AS installation_name
            FROM requisitions req
            LEFT JOIN installations i ON req.installation_id = i.id
            WHERE req.id = ?
        ");
        $stmt_req->execute([$selected_req_id]);
        $active_requisition = $stmt_req->fetch(PDO::FETCH_ASSOC);

        $stmt_items = $pdo->prepare("
            SELECT ri.*, p.name AS product_name, p.sku, p.stock_quantity AS current_stock
            FROM requisition_items ri
            JOIN products p ON ri.product_id = p.id
            WHERE ri.requisition_id = ?
        ");
        $stmt_items->execute([$selected_req_id]);
        $requisition_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $form_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติและฟอร์มใบเบิกอุปกรณ์ - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'sans-serif'] },
                    colors: { brandGreen: '#059669' }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Prompt', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            main { padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 h-screen flex overflow-hidden">

    <!-- Sidebar -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out shrink-0 no-print">
        <?php 
        if (file_exists('sidebar_admin.php')) {
            include 'sidebar_admin.php'; 
        } elseif (file_exists('sidebar.php')) {
            include 'sidebar.php';
        }
        ?>
    </div>
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-xs"></div>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header Bar -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 md:px-10 shrink-0 shadow-xs z-10 no-print">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">ประวัติการเบิกและฟอร์มใบเบิกย้อนหลัง</h2>
                </div>
            </div>
            <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
            </a>
        </header>

        <!-- Body Content -->
        <div class="flex-1 overflow-y-auto p-4 md:p-8 space-y-6">
            
            <?php if (isset($db_error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs max-w-6xl mx-auto">
                    เกิดข้อผิดพลาดฐานข้อมูล: <?= htmlspecialchars($db_error) ?>
                </div>
            <?php endif; ?>

            <?php if ($selected_req_id > 0 && $active_requisition): ?>
                <!-- ================= โหมดแสดงผลฟอร์มรายละเอียดใบเบิกแยกเดี่ยว ================= -->
                <div class="max-w-5xl mx-auto space-y-4">
                    
                    <!-- แถบเครื่องมือกรองและเลือกใบเบิกย้อนหลัง -->
                    <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-xs space-y-4 no-print">
                        <form method="GET" action="stock_history.php" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                            <input type="hidden" name="req_id" value="<?= $selected_req_id ?>">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">กรองตามโครงการ</label>
                                <select name="form_inst_id" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-2xl text-xs bg-slate-50 font-bold text-slate-700 focus:outline-none focus:border-emerald-600">
                                    <option value="all">-- ทุกโครงการ --</option>
                                    <?php foreach ($installationsList as $inst): ?>
                                        <option value="<?= $inst['id'] ?>" <?= ($form_filter_inst == $inst['id']) ? 'selected' : '' ?>><?= htmlspecialchars($inst['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">ค้นหา (ชื่อโครงการ / วิศวกร)</label>
                                <input type="text" name="form_search" value="<?= htmlspecialchars($form_search) ?>" placeholder="พิมพ์คำค้นหา..." class="w-full px-3.5 py-2.5 border border-slate-200 rounded-2xl text-xs bg-slate-50 font-medium focus:outline-none focus:border-emerald-600">
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-2xl text-xs font-bold shadow-sm transition cursor-pointer">
                                    <i class="fa-solid fa-filter me-1"></i> กรองรายการ
                                </button>
                                <a href="stock_history.php?req_id=<?= $selected_req_id ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3.5 py-2.5 rounded-2xl text-xs font-bold text-center">รีเซ็ต</a>
                            </div>
                        </form>

                        <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <div class="text-xs font-bold text-slate-700 flex items-center gap-2">
                                <i class="fa-solid fa-file-lines text-emerald-600"></i> เลือกใบเบิกที่ต้องการตรวจสอบ:
                            </div>
                            <select onchange="location.href='stock_history.php?req_id='+this.value+'&form_inst_id=<?= $form_filter_inst ?>&form_search=<?= urlencode($form_search) ?>'" class="px-4 py-2 border border-slate-200 rounded-2xl text-xs bg-slate-50 font-bold text-slate-700 focus:outline-none focus:border-emerald-600 cursor-pointer w-full sm:w-96">
                                <?php if (!empty($allRequisitions)): ?>
                                    <?php foreach ($allRequisitions as$req): ?>
                                        <option value="<?= $req['id'] ?>" <?= ($selected_req_id ==$req['id']) ? 'selected' : '' ?>>
                                            #<?= $req['id'] ?> - <?= htmlspecialchars($req['project_name']) ?> (วิศวกร: <?= htmlspecialchars($req['engineer_name'] ?: '-') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">ไม่พบรายการใบเบิกตามเงื่อนไข</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <!-- กล่องแสดงฟอร์มเอกสารใบเบิก -->
                    <div class="bg-white p-6 md:p-10 rounded-3xl shadow-sm border border-slate-200 space-y-6">
                        
                        <div class="flex justify-between items-center border-b border-slate-100 pb-4 no-print">
                            <a href="stock_history.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-bold transition flex items-center gap-2">
                                <i class="fa-solid fa-arrow-left"></i> กลับไปหน้าตารางประวัติทั้งหมด
                            </a>
                            <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full text-xs font-bold">
                                สถานะ: <?= htmlspecialchars($active_requisition['status']) ?>
                            </span>
                        </div>

                        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                                <i class="fa-solid fa-boxes-packing"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 text-base md:text-lg">บันทึกการจ่ายและรับคืนสินค้า ใบเบิก #<?= htmlspecialchars($active_requisition['id']) ?></h3>
                                <p class="text-xs text-slate-500 font-medium">โครงการ: <?= htmlspecialchars($active_requisition['project_name'] ?: ($active_requisition['installation_name'] ?? '-')) ?> (วิศวกร: <?= htmlspecialchars($active_requisition['engineer_name'] ?: '-') ?>)</p>
                            </div>
                        </div>

                        <!-- ตารางรายการอุปกรณ์ -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                                        <th class="py-3.5 px-4">ลำดับ</th>
                                        <th class="py-3.5 px-4">รายการสินค้า / SKU</th>
                                        <th class="py-3.5 px-4 text-center">คงเหลือ</th>
                                        <th class="py-3.5 px-4 text-center">จำนวนเบิก</th>
                                        <th class="py-3.5 px-4 text-center text-emerald-700">จ่ายออก</th>
                                        <th class="py-3.5 px-4 text-center text-amber-600">รับเข้า (รับคืน)</th>
                                        <th class="py-3.5 px-4">หมายเหตุ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-medium" id="formItemsTableBody">
                                    <?php if (!empty($requisition_items)): ?>
                                        <?php foreach ($requisition_items as $index =>$item): ?>
                                            <tr class="hover:bg-slate-50/50 form-item-row">
                                                <td class="py-4 px-4 text-slate-400 font-bold"><?= $index + 1 ?></td>
                                                <td class="py-4 px-4">
                                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($item['product_name']) ?></div>
                                                    <div class="text-[10px] text-slate-400">SKU: <?= htmlspecialchars($item['sku'] ?: '-') ?></div>
                                                </td>
                                                <td class="py-4 px-4 text-center font-bold text-slate-700"><?= number_format($item['current_stock'] ?? 0) ?></td>
                                                <td class="py-4 px-4 text-center font-bold text-slate-700"><?= number_format($item['quantity'] ?? 0) ?> ชิ้น</td>
                                                <td class="py-4 px-4 text-center">
                                                    <span class="inline-block px-4 py-1.5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 font-bold text-sm">
                                                        <?= number_format($item['issued_qty'] ?? 0) ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-4 text-center">
                                                    <span class="inline-block px-4 py-1.5 rounded-xl border border-amber-200 bg-amber-50 text-amber-700 font-bold text-sm">
                                                        <?= number_format($item['returned_qty'] ?? 0) ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-4 text-slate-500">
                                                    <?= !empty($item['remark']) ? htmlspecialchars($item['remark']) : '-' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-10 text-slate-400">ไม่พบรายการสินค้าในใบเบิกนี้</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination สำหรับรายการในฟอร์มใบเบิก -->
                        <div class="flex flex-col sm:flex-row items-center justify-between pt-4 border-t border-slate-100 gap-4 no-print">
                            <div class="text-xs text-slate-500 font-medium" id="formTableInfo">กำลังแสดง 0 ถึง 0 จาก 0 รายการ</div>
                            <div class="flex items-center gap-4 flex-wrap justify-end">
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span>แสดง:</span>
                                    <select id="formRowsPerPage" onchange="changeFormRowsPerPage()" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white font-bold text-slate-700 focus:outline-none focus:border-emerald-600 cursor-pointer">
                                        <option value="5" selected>5 แถว</option>
                                        <option value="10">10 แถว</option>
                                        <option value="25">25 แถว</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-1.5" id="formPaginationControls"></div>
                            </div>
                        </div>

                        <!-- ปุ่มควบคุมด้านล่างฟอร์ม -->
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-3 pt-5 border-t border-slate-100 no-print">
                            <a href="print_requisition.php?id=<?= $active_requisition['id'] ?>" target="_blank" class="bg-slate-700 hover:bg-slate-800 text-white px-5 py-3 rounded-2xl font-bold text-xs transition-all flex items-center justify-center gap-2 w-full sm:w-auto">
                                <i class="fa-solid fa-print"></i> พิมพ์ใบเบิก (PDF)
                            </a>
                            <a href="stock_history.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-6 py-3 rounded-2xl font-bold text-xs transition-all text-center w-full sm:w-auto">
                                ปิด / กลับหน้าประวัติ
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- ================= โหมดแสดงตารางประวัติการเคลื่อนไหวปกติ ================= -->
                <div class="max-w-6xl mx-auto space-y-6">
                    <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs">
                        <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-slate-600">ค้นหาอุปกรณ์ / รหัสสินค้า / อ้างอิง</label>
                                <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>" placeholder="ชื่ออุปกรณ์, เลขใบเบิก..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-slate-600">ประเภทรายการ</label>
                                <select name="type" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                                    <option value="all" <?= $filter_type == 'all' ? 'selected' : '' ?>>-- ทุกประเภท --</option>
                                    <option value="out" <?= $filter_type == 'out' ? 'selected' : '' ?>>เบิกออก (Out)</option>
                                    <option value="in" <?= $filter_type == 'in' ? 'selected' : '' ?>>รับเข้า (In)</option>
                                    <option value="return" <?= $filter_type == 'return' ? 'selected' : '' ?>>รับคืน (Return)</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-semibold text-slate-600">โครงการ / หน้างาน</label>
                                <select name="installation_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                                    <option value="all">-- ทุกโครงการ --</option>
                                    <?php foreach ($installationsList as$inst): ?>
                                        <option value="<?= $inst['id'] ?>" <?= ($filter_installation != 'all' &&$filter_installation == $inst['id']) ? 'selected' : '' ?>><?= htmlspecialchars($inst['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-2xl text-xs font-bold shadow-md cursor-pointer">
                                    <i class="fa-solid fa-filter me-1"></i> กรองข้อมูล
                                </button>
                                <a href="stock_history.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-3 rounded-2xl text-xs font-bold text-center">ล้าง</a>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                        <div class="p-6 pb-2 flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-clock-rotate-left text-emerald-600 me-2"></i> รายการเคลื่อนไหวล่าสุด</h3>
                            <span class="text-xs text-slate-400 font-medium">พบทั้งหมด <?= count($transactions) ?> รายการ</span>
                        </div>
                        <div class="overflow-x-auto px-6">
                            <table class="w-full text-left border-collapse text-xs min-w-[900px]">
                                <thead>
                                    <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider">
                                        <th class="py-3.5 px-4 font-semibold">วันที่ / เวลา</th>
                                        <th class="py-3.5 px-4 font-semibold text-center">ประเภท</th>
                                        <th class="py-3.5 px-4 font-semibold text-center">ดูฟอร์มใบเบิก</th>
                                        <th class="py-3.5 px-4 font-semibold">รายการอุปกรณ์ (SKU)</th>
                                        <th class="py-3.5 px-4 font-semibold">โครงการ / หน้างาน</th>
                                        <th class="py-3.5 px-4 font-semibold text-center">จำนวน</th>
                                        <th class="py-3.5 px-4 font-semibold text-center">คงเหลือปัจจุบัน</th>
                                        <th class="py-3.5 px-4 font-semibold text-right">มูลค่ารวม</th>
                                        <th class="py-3.5 px-4 font-semibold">ผู้ทำรายการ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-medium text-slate-700" id="historyTableBody">
                                    <?php if (!empty($transactions)): ?>
                                        <?php foreach ($transactions as $row):$type = $row['type'];$unit_cost = floatval($row['transaction_cost']);$total_val = $row['quantity'] *$unit_cost;
                                            
                                            if ($type == 'out') {$badge = '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-full text-[10px] font-bold whitespace-nowrap">เบิกออก</span>';
                                            } elseif ($type == 'in') {$badge = '<span class="bg-blue-50 text-blue-700 border border-blue-200 px-3 py-1 rounded-full text-[10px] font-bold whitespace-nowrap">รับเข้า</span>';
                                            } else {
                                                $badge = '<span class="bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1 rounded-full text-[10px] font-bold whitespace-nowrap">รับคืน</span>';
                                            }
                                            $target_req_id = intval($row['requisition_id'] ?? 0);
                                        ?>
                                            <tr class="hover:bg-slate-50/80 transition history-row">
                                                <td class="py-3.5 px-4 text-slate-500"><?= htmlspecialchars($row['created_at']) ?></td>
                                                <td class="py-3.5 px-4 text-center"><?= $badge ?></td>
                                                <td class="py-3.5 px-4 text-center">
                                                    <?php if ($target_req_id > 0): ?>
                                                        <a href="stock_history.php?req_id=<?= $target_req_id ?>" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded-xl text-[11px] font-bold border border-emerald-200 transition inline-flex items-center gap-1.5 whitespace-nowrap">
                                                            <i class="fa-solid fa-file-lines"></i> เปิดฟอร์ม #<?= $target_req_id ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-slate-400 text-[11px]">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-3.5 px-4">
                                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($row['product_name']) ?></div>
                                                    <div class="text-[10px] text-slate-400">SKU: <?= htmlspecialchars($row['sku'] ?: '-') ?></div>
                                                </td>
                                                <td class="py-3.5 px-4 font-semibold text-slate-600"><?= htmlspecialchars($row['project_name'] ?: 'ส่วนกลาง') ?></td>
                                                <td class="py-3.5 px-4 text-center font-bold text-slate-900"><?= number_format($row['quantity']) ?></td>
                                                <td class="py-3.5 px-4 text-center font-bold"><?= number_format($row['current_stock']) ?></td>
                                                <td class="py-3.5 px-4 text-right font-bold text-emerald-700">฿<?= number_format($total_val, 2) ?></td>
                                                <td class="py-3.5 px-4 text-slate-600"><?= htmlspecialchars($row['engineer_name'] ?: ($row['user_name'] ?: 'System')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr id="noDataRow"><td colspan="9" class="py-12 text-center text-slate-400">ไม่พบประวัติการเคลื่อนไหวสินค้า</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination สำหรับตารางประวัติหลัก -->
                        <div class="flex flex-col sm:flex-row items-center justify-between p-6 border-t border-slate-200/80 gap-4 bg-slate-50/50 mt-4">
                            <div class="text-xs text-slate-500 font-medium" id="tableInfo">กำลังแสดง 0 ถึง 0 จาก 0 รายการ</div>
                            <div class="flex items-center gap-4 flex-wrap justify-end">
                                <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                    <span>แสดง:</span>
                                    <select id="rowsPerPage" onchange="changeRowsPerPage()" class="px-3 py-2 rounded-xl border border-slate-200 bg-white font-bold text-slate-700 focus:outline-none focus:border-emerald-600 cursor-pointer">
                                        <option value="5" selected>5 แถว</option>
                                        <option value="10">10 แถว</option>
                                        <option value="25">25 แถว</option>
                                        <option value="50">50 แถว</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-1.5" id="paginationControls"></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebarWrapper').classList.toggle('-translate-x-full');
            document.getElementById('sidebarBackdrop').classList.toggle('hidden');
        }

        // Script สำหรับจัดการ Pagination ตารางประวัติหลัก
        let currentPage = 1;
        function changeRowsPerPage() { currentPage = 1; updateTablePagination(); }
        function changePage(page) { currentPage = page; updateTablePagination(); }

        function updateTablePagination() {
            let rows = Array.from(document.querySelectorAll('.history-row'));
            if (rows.length === 0) return;
            let rowsPerPage = parseInt(document.getElementById('rowsPerPage').value) || 5;
            let totalMatched = rows.length;
            let totalPages = Math.ceil(totalMatched / rowsPerPage) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            rows.forEach(row => row.style.display = 'none');
            let startIndex = (currentPage - 1) * rowsPerPage;
            let endIndex = startIndex + rowsPerPage;
            rows.slice(startIndex, endIndex).forEach(row => row.style.display = '');

            document.getElementById('tableInfo').innerText = totalMatched === 0 ? 'ไม่พบข้อมูล' : `กำลังแสดง ${startIndex + 1} ถึง ${Math.min(endIndex, totalMatched)} จากทั้งหมด ${totalMatched} รายการ`;

            let paginationHtml = `<button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 cursor-pointer"><i class="fa-solid fa-chevron-left"></i></button>`;
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    let activeClass = (i === currentPage) ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50';
                    paginationHtml += `<button onclick="changePage(${i})" class="px-3 py-1.5 rounded-xl border text-xs font-bold ${activeClass} cursor-pointer">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    paginationHtml += `<span class="px-2 text-slate-400 text-xs">...</span>`;
                }
            }
            paginationHtml += `<button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 cursor-pointer"><i class="fa-solid fa-chevron-right"></i></button>`;
            document.getElementById('paginationControls').innerHTML = paginationHtml;
        }

        // Script สำหรับจัดการ Pagination ตารางรายการในฟอร์มใบเบิก
        let currentFormPage = 1;
        function changeFormRowsPerPage() { currentFormPage = 1; updateFormTablePagination(); }
        function changeFormPage(page) { currentFormPage = page; updateFormTablePagination(); }

        function updateFormTablePagination() {
            let rows = Array.from(document.querySelectorAll('.form-item-row'));
            if (rows.length === 0) {
                const infoEl = document.getElementById('formTableInfo');
                if (infoEl) infoEl.innerText = 'ไม่พบรายการสินค้า';
                return;
            }
            let rowsPerPage = parseInt(document.getElementById('formRowsPerPage').value) || 5;
            let totalMatched = rows.length;
            let totalPages = Math.ceil(totalMatched / rowsPerPage) || 1;

            if (currentFormPage > totalPages) currentFormPage = totalPages;
            if (currentFormPage < 1) currentFormPage = 1;

            rows.forEach(row => row.style.display = 'none');
            let startIndex = (currentFormPage - 1) * rowsPerPage;
            let endIndex = startIndex + rowsPerPage;
            rows.slice(startIndex, endIndex).forEach(row => row.style.display = '');

            document.getElementById('formTableInfo').innerText = `กำลังแสดง ${startIndex + 1} ถึง ${Math.min(endIndex, totalMatched)} จากทั้งหมด ${totalMatched} รายการ`;

            let paginationHtml = `<button onclick="changeFormPage(${currentFormPage - 1})" ${currentFormPage === 1 ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 cursor-pointer"><i class="fa-solid fa-chevron-left"></i></button>`;
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentFormPage - 1 && i <= currentFormPage + 1)) {
                    let activeClass = (i === currentFormPage) ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50';
                    paginationHtml += `<button onclick="changeFormPage(${i})" class="px-3 py-1.5 rounded-xl border text-xs font-bold ${activeClass} cursor-pointer">${i}</button>`;
                } else if (i === currentFormPage - 2 || i === currentFormPage + 2) {
                    paginationHtml += `<span class="px-2 text-slate-400 text-xs">...</span>`;
                }
            }
            paginationHtml += `<button onclick="changeFormPage(${currentFormPage + 1})" ${currentFormPage === totalPages || totalPages === 0 ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 cursor-pointer"><i class="fa-solid fa-chevron-right"></i></button>`;
            document.getElementById('formPaginationControls').innerHTML = paginationHtml;
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateTablePagination();
            updateFormTablePagination();
        });
    </script>
</body>
</html>