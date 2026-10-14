<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'stock_history'; // กำหนดหน้าปัจจุบันสำหรับ Sidebar

// รับค่าตัวกรอง (ถ้ามี)
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : 'all';$filter_installation = isset($_GET['installation_id']) &&$_GET['installation_id'] !== '' ? intval($_GET['installation_id']) : 'all';$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// 1. ดึงรายชื่อโครงการสำหรับใส่ใน Dropdown ตัวกรอง
try {
    $stmt_inst =$pdo->query("SELECT id, name FROM installations ORDER BY id DESC");
    $installationsList =$stmt_inst->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$installationsList = [];
}

// 2. ดึงข้อมูลประวัติ stock_transactions พร้อมจำนวนคงเหลือปัจจุบัน (stock_quantity) และราคา unit_cost ตามจริง
try {
    $sql = "
        SELECT st.*, 
               p.name AS product_name, p.sku, p.stock_quantity AS current_stock,
               COALESCE(st.unit_cost, p.cost_price, 0) AS transaction_cost,
               i.name AS project_name,
               u.name AS user_name
        FROM stock_transactions st
        JOIN products p ON st.product_id = p.id
        LEFT JOIN installations i ON st.installation_id = i.id
        LEFT JOIN users u ON st.user_id = u.id
        WHERE 1=1
    ";
    $params = [];

    if ($filter_type !== 'all') {$sql .= " AND st.type = ?";
        $params[] =$filter_type;
    }

    if ($filter_installation !== 'all') {$sql .= " AND st.installation_id = ?";
        $params[] =$filter_installation;
    }

    if (!empty($search_keyword)) {$sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR st.reference_no LIKE ?)";
        $params[] = "\%{$search_keyword}%";
        $params[] = "\%{$search_keyword}%";
        $params[] = "\%{$search_keyword}%";
    }

    $sql .= " ORDER BY st.created_at DESC LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions =$stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {$transactions = [];
    $db_error =$e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการเบิกและเคลื่อนไหวสินค้า - SolarStock Pro</title>
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
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar สำหรับมือถือและเดสก์ท็อป -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out shrink-0">
        <?php 
        if (file_exists('sidebar_admin.php')) {
            include 'sidebar_admin.php'; 
        } elseif (file_exists('sidebar.php')) {
            include 'sidebar.php';
        }
        ?>
    </div>
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header Bar -->
        <header class="h-20 bg-white border-b border-slate-200/80 flex items-center justify-between px-6 md:px-10 shrink-0 z-10 shadow-xs">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">ประวัติการเบิกและเคลื่อนไหวสินค้า</h2>
                    <!-- <p class="text-xs text-slate-500 hidden sm:block mt-0.5">ตรวจสอบประวัติการทำรายการเบิก/รับเข้า พร้อมดูสต็อกคงเหลือปัจจุบัน</p> -->
                </div>
            </div>
            <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
            </a>
        </header>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-4 md:p-10 space-y-6">

            <?php if (isset($db_error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-sm">
                    เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: <?= htmlspecialchars($db_error) ?>
                </div>
            <?php endif; ?>

            <!-- ส่วนฟอร์มค้นหาและกรองข้อมูล -->
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs">
                <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end">
                    
                    <!-- ค้นหาชื่อสินค้า / SKU -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-600">ค้นหาอุปกรณ์ / รหัสสินค้า</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" name="search" value="<?= htmlspecialchars($search_keyword) ?>" placeholder="ชื่ออุปกรณ์, รหัสสินค้า, เลขอ้างอิง..." class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                        </div>
                    </div>

                    <!-- กรองตามประเภทรายการ -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-600">ประเภทรายการ</label>
                        <select name="type" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            <option value="all" <?= $filter_type == 'all' ? 'selected' : '' ?>>-- ทุกประเภท --</option>
                            <option value="out" <?= $filter_type == 'out' ? 'selected' : '' ?>>เบิกออก (Out)</option>
                            <option value="in" <?= $filter_type == 'in' ? 'selected' : '' ?>>รับเข้า (In)</option>
                            <option value="return" <?= $filter_type == 'return' ? 'selected' : '' ?>>รับคืน (Return)</option>
                        </select>
                    </div>

                    <!-- กรองตามโครงการ -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-600">โครงการ / หน้างาน</label>
                        <select name="installation_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            <option value="all">-- ทุกโครงการ --</option>
                            <?php foreach ($installationsList as$inst): ?>
                                <option value="<?= $inst['id'] ?>" <?= $filter_installation ==$inst['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inst['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ปุ่มกด -->
                    <div class="flex items-center gap-2">
                        <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white py-3 rounded-2xl text-xs font-bold shadow-md shadow-emerald-600/20 transition cursor-pointer">
                            <i class="fa-solid fa-filter me-1"></i> กรองข้อมูล
                        </button>
                        <a href="stock_history.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-3 rounded-2xl text-xs font-bold transition text-center">
                            ล้างค่า
                        </a>
                    </div>
                </form>
            </div>

            <!-- ตารางแสดงประวัติเคลื่อนไหวและ Pagination ทางขวาล่าง -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col justify-between">
                <div class="p-6 pb-2 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-clock-rotate-left text-brandGreen me-2"></i> รายการเคลื่อนไหวล่าสุด</h3>
                    <span class="text-xs text-slate-400 font-medium" id="totalCountText">พบทั้งหมด <?= count($transactions) ?> รายการ</span>
                </div>

                <div class="overflow-x-auto px-6">
                    <table class="w-full text-left border-collapse text-xs min-w-[750px]">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider">
                                <th class="py-3.5 px-4 font-semibold">วันที่ / เวลา</th>
                                <th class="py-3.5 px-4 font-semibold text-center">ประเภท</th>
                                <th class="py-3.5 px-4 font-semibold">รายการอุปกรณ์ (SKU)</th>
                                <th class="py-3.5 px-4 font-semibold">โครงการ / หน้างาน</th>
                                <th class="py-3.5 px-4 font-semibold text-center">จำนวนที่ทำรายการ</th>
                                <th class="py-3.5 px-4 font-semibold text-center">คงเหลือปัจจุบัน</th>
                                <th class="py-3.5 px-4 font-semibold text-right">มูลค่ารวม (บาท)</th>
                                <th class="py-3.5 px-4 font-semibold">ผู้ทำรายการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700" id="historyTableBody">
                            <?php if (!empty($transactions)): ?>
                                <?php foreach ($transactions as $row):$type = $row['type'];$unit_cost = floatval($row['transaction_cost']);$total_val = $row['quantity'] *$unit_cost;

                                    if ($type == 'out') {$badge = '<span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 rounded-full text-[10px] font-bold">เบิกออก (Out)</span>';
                                        $val_color = 'text-emerald-700';
                                    } elseif ($type == 'in') {$badge = '<span class="bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-full text-[10px] font-bold">รับเข้า (In)</span>';
                                        $val_color = 'text-blue-700';                                     } else {$badge = '<span class="bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-1 rounded-full text-[10px] font-bold">รับคืน (Return)</span>';
                                        $val_color = 'text-amber-700';
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition history-row">
                                        <td class="py-3.5 px-4 text-slate-500"><?= htmlspecialchars($row['created_at']) ?></td>
                                        <td class="py-3.5 px-4 text-center"><?= $badge ?></td>
                                        <td class="py-3.5 px-4">
                                            <div class="font-bold text-slate-900"><?= htmlspecialchars($row['product_name']) ?></div>
                                            <div class="text-[10px] text-slate-400">SKU: <?= htmlspecialchars($row['sku'] ?: '-') ?> \vert{} ราคา/หน่วย: ฿<?= number_format($unit_cost, 2) ?></div>
                                        </td>
                                        <td class="py-3.5 px-4 font-semibold text-slate-600">
                                            <?= htmlspecialchars($row['project_name'] ?: 'ส่วนกลาง / ไม่ระบุ') ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-bold text-slate-900">
                                            <?= number_format($row['quantity']) ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <span class="bg-slate-100 border border-slate-200 text-slate-800 px-2.5 py-1 rounded-xl font-bold">
                                                <?= number_format($row['current_stock']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-bold <?= $val_color ?>">
                                            ฿<?= number_format($total_val, 2) ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-600">
                                            <i class="fa-solid fa-user text-[10px] text-slate-400 me-1"></i> <?= htmlspecialchars($row['user_name'] ?: 'System') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="noDataRow">
                                    <td colspan="8" class="py-12 text-center text-slate-400">ไม่พบประวัติการเคลื่อนไหวสินค้าตามเงื่อนไขที่เลือก</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- แถบควบคุมการแบ่งหน้า (Pagination) และเลือกจำนวนแถว -->
                <div class="flex flex-col sm:flex-row items-center justify-between p-6 border-t border-slate-200/80 gap-4 bg-slate-50/50 mt-4">
                    <div class="text-xs text-slate-500 font-medium" id="tableInfo">
                        กำลังแสดง 0 ถึง 0 จาก 0 รายการ
                    </div>
                    
                    <div class="flex items-center gap-4 flex-wrap justify-end">
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <span>แสดง:</span>
                            <select id="rowsPerPage" onchange="changeRowsPerPage()" class="px-3 py-2 rounded-xl border border-slate-200 bg-white font-bold text-slate-700 focus:outline-none focus:border-emerald-600 cursor-pointer">
                                <option value="5" selected>5 แถว</option>
                                <option value="10">10 แถว</option>
                                <option value="25">25 แถว</option>
                                <option value="50">50 แถว</option>
                                <option value="100">100 แถว</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-1.5" id="paginationControls"></div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Script ควบคุม Sidebar และระบบ Pagination (แบ่งหน้า) -->
    <script>
        function toggleSidebar() {
            document.getElementById('sidebarWrapper').classList.toggle('-translate-x-full');
            document.getElementById('sidebarBackdrop').classList.toggle('hidden');
        }

        let currentPage = 1;

        function changeRowsPerPage() {
            currentPage = 1;
            updateTablePagination();
        }

        function changePage(page) {
            currentPage = page;
            updateTablePagination();
        }

        function updateTablePagination() {
            let rows = Array.from(document.querySelectorAll('.history-row'));
            let rowsPerPage = parseInt(document.getElementById('rowsPerPage').value) || 5;
            let totalMatched = rows.length;
            let totalPages = Math.ceil(totalMatched / rowsPerPage) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            // ซ่อนทุกแถวก่อน
            rows.forEach(row => row.style.display = 'none');

            // แสดงเฉพาะแถวในหน้าที่เลือก
            let startIndex = (currentPage - 1) * rowsPerPage;
            let endIndex = startIndex + rowsPerPage;
            let currentRows = rows.slice(startIndex, endIndex);

            currentRows.forEach(row => row.style.display = '');

            // อัปเดตข้อความแสดงข้อมูล (Info)
            let infoText = '';
            if (totalMatched === 0) {
                infoText = 'ไม่พบข้อมูลประวัติการทำรายการ';
            } else {
                infoText = `กำลังแสดง ${startIndex + 1} ถึง ${Math.min(endIndex, totalMatched)} จากทั้งหมด ${totalMatched} รายการ`;
            }
            document.getElementById('tableInfo').innerText = infoText;

            // สร้างปุ่ม Pagination ทางขวาล่าง
            let paginationHtml = '';
            
            // ปุ่มย้อนกลับ
            paginationHtml += `<button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 cursor-pointer"><i class="fa-solid fa-chevron-left"></i></button>`;

            // แสดงหมายเลขหน้า
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    let activeClass = (i === currentPage) ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50';
                    paginationHtml += `<button onclick="changePage(${i})" class="px-3 py-1.5 rounded-xl border text-xs font-bold ${activeClass} cursor-pointer">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    paginationHtml += `<span class="px-2 text-slate-400 text-xs">...</span>`;
                }
            }

            // ปุ่มถัดไป
            paginationHtml += `<button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 cursor-pointer"><i class="fa-solid fa-chevron-right"></i></button>`;

            document.getElementById('paginationControls').innerHTML = paginationHtml;
        }

        // เริ่มต้นทำงานเมื่อโหลดหน้าเว็บ
        document.addEventListener('DOMContentLoaded', () => {
            updateTablePagination();
        });
    </script>
</body>
</html>