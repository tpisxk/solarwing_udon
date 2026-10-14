<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'stockout'; // กำหนดหน้าปัจจุบันสำหรับ Sidebar

// 1. ดึงยอดรวมจำนวนสินค้าทั้งหมดในคลัง สำหรับแสดงที่ Sidebar (ใช้ stock_quantity)
try {
    $stmt_count =$pdo->query("SELECT SUM(stock_quantity) as total_stock FROM products");
    $row_count =$stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_inventory_count =$row_count['total_stock'] ?? 0;
} catch (PDOException $e) {$total_inventory_count = 0;
}

// 2. ดึงรายการสินค้าทั้งหมด พร้อมระบุจำนวนสต็อกปัจจุบัน เพื่อแสดงใน Dropdown
try {
    $stmt_products =$pdo->query("SELECT id, name, stock_quantity FROM products ORDER BY name ASC");
    $products =$stmt_products->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$products = [];
}

// 3. ดึงรายการโครงการ/หน้างาน ทั้งหมด (ตาราง installations)
try {
    $stmt_installations =$pdo->query("SELECT id, name FROM installations ORDER BY id DESC");
    $installationsList =$stmt_installations->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$installationsList = [];
}

// 4. ดึงประวัติการจ่ายออกสินค้าทั้งหมดจาก stock_transactions (กรองเฉพาะ type = 'out')
try {
    $stmt_history =$pdo->query("
        SELECT st.*, p.name AS product_name, i.name AS project_name
        FROM stock_transactions st
        LEFT JOIN products p ON st.product_id = p.id
        LEFT JOIN installations i ON st.installation_id = i.id
        WHERE st.type = 'out'
        ORDER BY st.created_at DESC
        LIMIT 100
    ");
    $stockOutHistory =$stmt_history->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$stockOutHistory = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จ่ายออกสินค้า - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tom Select CDN (Searchable Select) -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'Inter', 'sans-serif'] },
                    colors: { darkSidebar: '#000007', brandGreen: '#059669' }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

        /* Custom Tom Select Style ให้กลมกลืนกับ Tailwind Class เดิม */
        .ts-control {
            border-radius: 1rem !important; /* rounded-2xl */
            padding: 0.75rem 1rem !important; /* py-3 px-4 */
            border-color: #e2e8f0 !important; /* border-slate-200 */
            background-color: #f8fafc !important; /* bg-slate-50 */
            font-size: 0.75rem !important; /* text-xs */
            font-family: 'Prompt', sans-serif !important;
            font-weight: 500 !important;
        }
        .ts-wrapper.focus .ts-control {
            border-color: #059669 !important; /* focus:border-emerald-600 */
            box-shadow: 0 0 0 1px #059669 !important;
        }
        .ts-dropdown {
            border-radius: 1rem !important;
            overflow: hidden;
            font-size: 0.75rem !important;
            font-family: 'Prompt', sans-serif !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        }
        .ts-dropdown .option {
            padding: 0.6rem 1rem !important;
        }
        .ts-dropdown .active {
            background-color: #ecfdf5 !important;
            color: #047857 !important;
        }
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
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">จ่ายออกสินค้าจากคลัง</h2>
                    <!-- <p class="text-xs text-slate-500 hidden sm:block mt-0.5">เลือกรายการสินค้า ระบุจำนวน และโครงการเพื่อตัดสต็อกทันที</p> -->
                </div>
            </div>
            <a href="dashboard_admin.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
            </a>
        </header>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-4 md:p-10 space-y-6">
            
            <!-- แจ้งเตือนสถานะ -->
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'success'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>บันทึกจ่ายออกสินค้าเรียบร้อยแล้ว และตัดสต็อกสำเร็จ</span>
                        <a href="stockout_admin.php" class="text-emerald-700 font-bold hover:underline text-xs">ปิด</a>
                    </div>
                <?php elseif ($_GET['status'] == 'insufficient'): ?>
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>สินค้าในสต็อกไม่เพียงพอต่อจำนวนที่ต้องการจ่ายออก</span>
                        <a href="stockout_admin.php" class="text-amber-700 font-bold hover:underline text-xs">ปิด</a>
                    </div>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาเลือกโครงการและกรอกข้อมูลให้ครบถ้วน</span>
                        <a href="stockout_admin.php" class="text-rose-700 font-bold hover:underline text-xs">ปิด</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ฟอร์มบันทึกจ่ายออกสินค้า -->
            <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
                <div>
                    <h3 class="text-base md:text-lg font-bold text-slate-900">ฟอร์มจ่ายออกสินค้า (เบิกใช้งาน/ติดตั้ง)</h3>
                    <p class="text-xs text-slate-500 mt-1">เลือกรายการสินค้า ระบุจำนวนที่ต้องการเบิก และรายละเอียดโครงการหรือหน้างาน</p>
                </div>

                <form action="stockout_process_admin.php" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    
                    <!-- เลือกสินค้า (รองรับค้นหา) -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">เลือกสินค้า <span class="text-rose-500">*</span></label>
                        <select id="product_select" name="product_id" required>
                            <option value="">-- พิมพ์เพื่อค้นหา หรือเลือกสินค้า --</option>
                            <?php foreach ($products as$p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['name']) ?> — (คงเหลือ: <?= number_format($p['stock_quantity'] ?? 0) ?> ชิ้น)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- จำนวนที่จ่ายออก -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-600">จำนวนที่จ่ายออก <span class="text-rose-500">*</span></label>
                        <input type="number" name="quantity" min="1" required placeholder="ระบุจำนวน..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <!-- หน้างาน / โครงการ (บังคับเลือก - รองรับค้นหา) -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">หน้างาน / โครงการที่เบิกไปติดตั้ง <span class="text-rose-500">*</span></label>
                        <select id="installation_select" name="installation_id" required>
                            <option value="">-- พิมพ์เพื่อค้นหา หรือเลือกโครงการ/หน้างาน --</option>
                            <?php foreach ($installationsList as$inst): ?>
                                <option value="<?= $inst['id'] ?>">
                                    <?= htmlspecialchars($inst['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- หมายเหตุ -->
                    <div class="space-y-1.5 md:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600">หมายเหตุ</label>
                        <input type="text" name="note" placeholder="เช่น เบิกเพิ่มเติม, ช่าง A..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <!-- ปุ่มยืนยัน -->
                    <div class="md:col-span-3 flex justify-end pt-2">
                        <button type="submit" class="w-full sm:w-auto bg-brandGreen hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-xs font-semibold shadow-md shadow-emerald-600/25 transition-all cursor-pointer flex items-center justify-center gap-2">
                            <i class="fa-solid fa-check"></i> ยืนยันจ่ายออกสินค้า
                        </button>
                    </div>
                </form>
            </div>

            <!-- ตารางประวัติการจ่ายออกสินค้า พร้อมระบบแบ่งหน้า 5 แถว -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col justify-between">
                <div class="p-6 pb-2 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">ประวัติการจ่ายออกสินค้าล่าสุด</h3>
                    <span class="text-xs text-slate-400 font-medium" id="totalCountText">พบทั้งหมด <?= count($stockOutHistory) ?> รายการ</span>
                </div>
                
                <div class="overflow-x-auto px-6">
                    <table class="w-full text-left border-collapse text-xs min-w-[700px]">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider">
                                <th class="py-3.5 px-4 font-semibold">วันที่ / เวลา</th>
                                <th class="py-3.5 px-4 font-semibold">ชื่อสินค้า</th>
                                <th class="py-3.5 px-4 font-semibold text-center">จำนวนที่จ่ายออก</th>
                                <th class="py-3.5 px-4 font-semibold">โครงการ / หน้างาน</th>
                                <th class="py-3.5 px-4 font-semibold">หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700" id="stockoutTableBody">
                            <?php if (!empty($stockOutHistory)): ?>
                                <?php foreach ($stockOutHistory as$row): ?>
                                    <tr class="hover:bg-slate-50/80 transition-all stockout-row">
                                        <td class="py-3.5 px-4 text-slate-500"><?= htmlspecialchars($row['created_at']) ?></td>
                                        <td class="py-3.5 px-4 font-bold text-slate-900"><?= htmlspecialchars($row['product_name'] ?? 'ไม่พบสินค้า') ?></td>
                                        <td class="py-3.5 px-4 text-rose-600 font-bold text-center">-<?= number_format($row['quantity']) ?></td>
                                        <td class="py-3.5 px-4 text-blue-600 font-semibold"><?= htmlspecialchars($row['project_name'] ?? '-') ?></td>
                                        <td class="py-3.5 px-4 text-slate-500"><?= htmlspecialchars($row['note'] ?: '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="noDataRow">
                                    <td colspan="5" class="py-12 text-center text-slate-400 font-normal">ยังไม่มีประวัติการจ่ายออกสินค้า</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- แถบควบคุมการแบ่งหน้า (Pagination) และเลือกจำนวนแถว (5 แถวเริ่มต้น) ทางขวาล่าง -->
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

    <!-- เรียกใช้งาน Tom Select และระบบ Pagination -->
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
            let rows = Array.from(document.querySelectorAll('.stockout-row'));
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

            // อัปเดตข้อความข้อมูลแสดงผล
            let infoText = '';
            if (totalMatched === 0) {
                infoText = 'ไม่พบข้อมูลประวัติการจ่ายออก';
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

        document.addEventListener('DOMContentLoaded', function() {
            // เปิดใช้งาน Tom Select
            new TomSelect("#product_select", {
                create: false,
                placeholder: "-- พิมพ์เพื่อค้นหา หรือเลือกสินค้า --",
                sortField: { field: "text", direction: "asc" }
            });

            new TomSelect("#installation_select", {
                create: false,
                placeholder: "-- พิมพ์เพื่อค้นหา หรือเลือกโครงการ/หน้างาน --",
                sortField: { field: "text", direction: "asc" }
            });

            // เริ่มต้นระบบ Pagination
            updateTablePagination();
        });
    </script>
</body>
</html>