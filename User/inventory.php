<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../db/conn.php';
require_once '../auth_check.php';

// จัดการอัปเดตราคาต้นทุนผ่าน AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'update_cost') {
    header('Content-Type: application/json; charset=utf-8');
    $product_id = intval($_POST['product_id'] ?? 0);
    $new_cost = floatval($_POST['cost_price'] ?? 0);

    if ($product_id > 0 &&$new_cost >= 0) {
        try {
            $stmt =$pdo->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
            $stmt->execute([$new_cost,$product_id]);
            echo json_encode(['status' => 'success', 'message' => 'อัปเดตราคาต้นทุนเรียบร้อยแล้ว']);
            exit();
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึก']);
            exit();
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit();
}

// ดึงข้อมูลสินค้าทั้งหมด เรียงตามรหัส SKU
$stmt =$pdo->query("SELECT * FROM products ORDER BY sku ASC");
$products =$stmt->fetchAll(PDO::FETCH_ASSOC);

// จัดกลุ่มหมวดหมู่ตามตัวอักษรนำหน้า (ตัดตัวเลขและเครื่องหมายขีดออก เช่น SR1 -> SR)
$skuGroups = [];
foreach ($products as$row) {
    $sku = trim($row['sku']);
    if (!empty($sku)) {
        preg_match('/^[A-Za-z]+/', $sku, $matches);$prefix = !empty($matches[0]) ? strtoupper($matches[0]) : 'OT';
        
        if (!in_array($prefix,$skuGroups)) {
            $skuGroups[] =$prefix;
        }
    }
}
sort($skuGroups);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สต็อกคงเหลือ - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SheetJS สำหรับแปลงไฟล์ Excel (.xlsx, .xls) และ CSV -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    
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
        .cost-input:focus { background: #ffffff !important; border-color: #059669 !important; box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.15); }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar สำหรับมือถือและเดสก์ท็อป -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out">
        <?php if (file_exists('sidebar.php')) include 'sidebar.php'; ?>
    </div>
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header Bar -->
        <header class="h-20 bg-white border-b border-slate-200/80 flex items-center justify-between px-6 md:px-10 shrink-0 z-10 shadow-xs">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 p-2.5 rounded-xl bg-slate-100 border border-slate-200 hover:bg-slate-200 transition-all">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">สต็อกคงเหลือ (Inventory Status)</h2>
            </div>
            <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
            </a>
        </header>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-4 md:p-10 space-y-6">
            
            <!-- แจ้งเตือนสถานะการแก้ไขราคาหรือ Import -->
            <div id="alertBox" class="hidden px-6 py-4 rounded-2xl text-sm font-medium items-center justify-between shadow-xs"></div>

            <?php if (isset($_GET['import'])): ?>
                <?php if ($_GET['import'] == 'success'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>✨ นำเข้าข้อมูลสำเร็จเรียบร้อยแล้ว (อัปเดตไป <?= htmlspecialchars($_GET['count'] ?? 0) ?> รายการ)</span>
                        <a href="inventory.php" class="text-emerald-700 font-bold hover:underline text-xs">✕ ปิด</a>
                    </div>
                <?php else: ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>❌ เกิดข้อผิดพลาดในการนำเข้าไฟล์ กรุณาตรวจสอบรูปแบบไฟล์ใหม่อีกครั้ง</span>
                        <a href="inventory.php" class="text-rose-700 font-bold hover:underline text-xs">✕ ปิด</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ส่วนหัวข้อ, ช่องค้นหา และปุ่ม Import -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs gap-4">
                <div>
                    <h3 class="text-lg md:text-2xl font-bold text-slate-900">สต็อกสินค้าคงเหลือ</h3>
                    <p class="text-xs text-slate-500 mt-1">สามารถคลิกแก้ไขราคาต้นทุนในตารางและคลิกนอกช่องเพื่อบันทึกอัตโนมัติ</p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <input type="text" id="searchSku" placeholder="🔍 ค้นหารหัสสินค้า / ชื่อ..." oninput="changePage(1)" class="px-5 py-3 border border-slate-200 rounded-2xl text-sm w-full md:w-72 focus:outline-none focus:border-emerald-600 bg-slate-50 font-medium">
                    <button onclick="openImportModal()" class="bg-brandGreen hover:bg-emerald-700 text-white px-5 py-3 rounded-2xl text-sm font-semibold shadow-md shadow-emerald-600/25 transition-all flex items-center gap-2 shrink-0">
                        📥 <span class="hidden sm:inline">นำเข้าไฟล์</span> (Excel / CSV)
                    </button>
                </div>
            </div>

            <!-- แถบปุ่มกรองหมวดหมู่ SKU -->
            <div class="relative w-full overflow-hidden">
                <div class="flex items-center gap-2 overflow-x-auto pb-3 pt-1 px-1 scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent [-webkit-overflow-scrolling:touch]">
                    <button onclick="filterGroup('all')" id="group-all" class="group-tab px-4 py-2.5 rounded-2xl text-xs font-semibold bg-emerald-600 text-white shadow-sm transition-all shrink-0 whitespace-nowrap">
                        📂 ทั้งหมด (<?= count($products) ?>)
                    </button>
                    <?php foreach ($skuGroups as$grp): ?>
                        <?php 
                            $grpCount = 0;
                            foreach ($products as$p) {
                                $pSku = trim($p['sku']);
                                if (!empty($pSku)) {
                                    preg_match('/^[A-Za-z]+/', $pSku, $m);$pPrefix = !empty($m[0]) ? strtoupper($m[0]) : 'OT';
                                    if ($pPrefix === $grp) {$grpCount++; }
                                }
                            }
                        ?>
                        <button onclick="filterGroup('<?= htmlspecialchars($grp, ENT_QUOTES) ?>')" id="group-<?= htmlspecialchars($grp) ?>" class="group-tab px-4 py-2.5 rounded-2xl text-xs font-semibold bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 transition-all shrink-0 whitespace-nowrap">
                            🏷️ หมวด <?= htmlspecialchars($grp) ?> (<?=$grpCount ?>)
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ตารางแสดงสินค้า พร้อมระบบแบ่งหน้า (Pagination) -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col justify-between">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm min-w-[650px]">
                        <thead class="bg-slate-50/80 border-b border-slate-200 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="p-5">รหัสสินค้า</th>
                                <th class="p-5">ชื่อสินค้า</th>
                                <th class="p-5">หน่วยนับ</th>
                                <th class="p-5 text-right">ราคาต้นทุน (บาท)</th>
                                <th class="p-5 text-right">คงเหลือ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" id="productTableBody">
                            <?php if (count($products) > 0): ?>
                                <?php foreach ($products as$row): ?>
                                    <?php 
                                        $sku = trim($row['sku']);
                                        preg_match('/^[A-Za-z]+/', $sku, $m);$rowGroup = !empty($m[0]) ? strtoupper($m[0]) : 'OT';
                                    ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors product-row" data-sku-prefix="<?= htmlspecialchars($rowGroup, ENT_QUOTES) ?>">
                                        <td class="p-5 font-bold text-slate-700">
                                            <span class="bg-slate-100 border border-slate-200 px-3 py-1 rounded-xl text-emerald-700"><?= htmlspecialchars($row['sku']) ?></span>
                                        </td>
                                        <td class="p-5 font-bold text-slate-900"><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="p-5">
                                            <span class="px-3 py-1 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"><?= htmlspecialchars($row['category']) ?></span>
                                        </td>
                                        <!-- ช่องแก้ไขราคาต้นทุนแบบ Inline -->
                                        <td class="p-5 text-right font-medium">
                                            <div class="inline-flex items-center gap-1 justify-end">
                                                <span class="text-slate-400 text-xs">฿</span>
                                                <input type="number" step="0.01" min="0" value="<?= $row['cost_price'] ?>" 
                                                       data-id="<?= $row['id'] ?>" 
                                                       onchange="updateCostPrice(this)" 
                                                       class="cost-input w-28 px-3 py-1.5 text-right border border-slate-200 rounded-xl text-xs font-bold text-slate-800 bg-slate-50 transition-all">
                                            </div>
                                        </td>
                                        <td class="p-5 text-right font-bold text-brandGreen"><?= number_format($row['stock_quantity']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="noDataRow">
                                    <td colspan="5" class="p-12 text-center text-slate-400 font-medium">ไม่พบข้อมูลสินค้าในฐานข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- แถบควบคุมการแบ่งหน้า (Pagination) -->
                <div class="flex flex-col sm:flex-row items-center justify-between p-5 border-t border-slate-200/80 gap-4 bg-slate-50/50">
                    <div class="text-xs text-slate-500 font-medium" id="tableInfo">
                        กำลังแสดง 0 ถึง 0 จาก 0 รายการ
                    </div>
                    
                    <div class="flex items-center gap-4 flex-wrap justify-end">
                        <div class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <span>แสดง:</span>
                            <select id="rowsPerPage" onchange="changeRowsPerPage()" class="px-3 py-2 rounded-xl border border-slate-200 bg-white font-bold text-slate-700 focus:outline-none focus:border-emerald-600">
                                <option value="5">5 แถว</option>
                                <option value="10" selected>10 แถว</option>
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

    <!-- Modal สำหรับ Import ข้อมูล -->
    <div id="importModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 md:p-8 rounded-3xl w-full max-w-md shadow-xl border border-slate-200 space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-lg md:text-xl font-bold text-slate-900">นำเข้าข้อมูลสต็อก (Excel / CSV)</h3>
                <button onclick="closeImportModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>
            
            <form action="import_process.php" method="POST" enctype="multipart/form-data" id="importForm" class="space-y-4">
                <input type="hidden" name="converted_csv_data" id="convertedCsvData">

                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-slate-600">เลือกไฟล์ (.xlsx, .xls, .csv)</label>
                    <input type="file" id="uploadFile" accept=".csv, .xlsx, .xls" required onchange="handleFileSelect(event)" class="w-full p-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        *รองรับไฟล์ Excel และ CSV ระบบจะจัดการแปลงและบันทึกอัตโนมัติ
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeImportModal()" class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">ยกเลิก</button>
                    <button type="submit" name="import_submit" class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition-all">อัปโหลดและบันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script ควบคุมระบบ Sidebar, ตัวกรอง, ค้นหา, Pagination และแก้ไขราคา -->
    <script>
        function toggleSidebar() {
            document.getElementById('sidebarWrapper').classList.toggle('-translate-x-full');
            document.getElementById('sidebarBackdrop').classList.toggle('hidden');
        }

        let currentGroup = 'all';
        let currentPage = 1;

        function filterGroup(group) {
            currentGroup = group;
            
            document.querySelectorAll('.group-tab').forEach(btn => {
                btn.classList.remove('bg-emerald-600', 'text-white', 'shadow-sm');
                btn.classList.add('bg-white', 'text-slate-600', 'border', 'border-slate-200');
            });
            
            if (group === 'all') {
                let activeBtn = document.getElementById('group-all');
                if(activeBtn) {
                    activeBtn.classList.remove('bg-white', 'text-slate-600', 'border', 'border-slate-200');
                    activeBtn.classList.add('bg-emerald-600', 'text-white', 'shadow-sm');
                }
            } else {
                let activeBtn = document.getElementById('group-' + group);
                if(activeBtn) {
                    activeBtn.classList.remove('bg-white', 'text-slate-600', 'border', 'border-slate-200');
                    activeBtn.classList.add('bg-emerald-600', 'text-white', 'shadow-sm');
                }
            }

            currentPage = 1;
            updateTablePagination();
        }

        function changeRowsPerPage() {
            currentPage = 1;
            updateTablePagination();
        }

        function changePage(page) {
            currentPage = page;
            updateTablePagination();
        }

        function updateTablePagination() {
            let searchFilter = document.getElementById('searchSku').value.toLowerCase();
            let rows = Array.from(document.querySelectorAll('.product-row'));
            let rowsPerPage = parseInt(document.getElementById('rowsPerPage').value) || 10;

            let matchedRows = rows.filter(row => {
                let rowPrefix = row.getAttribute('data-sku-prefix');
                let rowText = row.innerText.toLowerCase();
                
                let matchesGroup = (currentGroup === 'all' || rowPrefix === currentGroup);
                let matchesSearch = rowText.includes(searchFilter);

                return matchesGroup && matchesSearch;
            });

            let totalMatched = matchedRows.length;
            let totalPages = Math.ceil(totalMatched / rowsPerPage) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            rows.forEach(row => row.style.display = 'none');

            let startIndex = (currentPage - 1) * rowsPerPage;
            let endIndex = startIndex + rowsPerPage;
            let currentRows = matchedRows.slice(startIndex, endIndex);

            currentRows.forEach(row => row.style.display = '');

            let infoText = '';
            if (totalMatched === 0) {
                infoText = 'ไม่พบข้อมูลสินค้า';
            } else {
                infoText = `กำลังแสดง ${startIndex + 1} ถึง ${Math.min(endIndex, totalMatched)} จากทั้งหมด ${totalMatched} รายการ`;
            }
            document.getElementById('tableInfo').innerText = infoText;

            let paginationHtml = '';
            paginationHtml += `<button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold bg-white text-slate-600 disabled:opacity-45 disabled:cursor-not-allowed hover:bg-slate-50"><i class="fa-solid fa-chevron-left"></i></button>`;

            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    let activeClass = (i === currentPage) ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50';
                    paginationHtml += `<button onclick="changePage(${i})" class="px-3 py-1.5 rounded-xl border text-xs font-bold ${activeClass}">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    paginationHtml += `<span class="px-2 text-slate-400 text-xs">...</span>`;
                }
            }

            paginationHtml += `<button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold bg-white text-slate-600 disabled:opacity-45 disabled:cursor-not-allowed hover:bg-slate-50"><i class="fa-solid fa-chevron-right"></i></button>`;

            document.getElementById('paginationControls').innerHTML = paginationHtml;
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateTablePagination();
        });

        document.getElementById('searchSku').addEventListener('keyup', () => {
            currentPage = 1;
            updateTablePagination();
        });

        // ฟังก์ชันอัปเดตราคาต้นทุนผ่าน AJAX ทันทีเมื่อแก้เสร็จ
        function updateCostPrice(inputElement) {
            let productId = inputElement.getAttribute('data-id');
            let newCost = inputElement.value;

            let formData = new FormData();
            formData.append('action', 'update_cost');
            formData.append('product_id', productId);
            formData.append('cost_price', newCost);

            fetch('inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                let alertBox = document.getElementById('alertBox');
                alertBox.classList.remove('hidden');
                alertBox.classList.add('flex');
                
                if (data.status === 'success') {
                    alertBox.className = 'flex px-6 py-4 rounded-2xl text-sm font-medium items-center justify-between shadow-xs bg-emerald-50 text-emerald-800 border border-emerald-200';
                    alertBox.innerHTML = `<span>✨ ${data.message}</span><button onclick="this.parentElement.classList.add('hidden')" class="font-bold text-xs">✕ ปิด</button>`;
                } else {
                    alertBox.className = 'flex px-6 py-4 rounded-2xl text-sm font-medium items-center justify-between shadow-xs bg-rose-50 text-rose-800 border border-rose-200';
                    alertBox.innerHTML = `<span>❌ ${data.message}</span><button onclick="this.parentElement.classList.add('hidden')" class="font-bold text-xs">✕ ปิด</button>`;
                }
                setTimeout(() => alertBox.classList.add('hidden'), 3500);
            })
            .catch(error => {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเพื่อบันทึกราคา');
            });
        }

        function openImportModal() {
            let modal = document.getElementById('importModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function closeImportModal() {
            let modal = document.getElementById('importModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;

            const fileName = file.name.toLowerCase();
            if (!fileName.endsWith('.csv')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const data = new Uint8Array(e.target.result);
                        const workbook = XLSX.read(data, { type: 'array' });
                        const firstSheetName = workbook.SheetNames[0];
                        const worksheet = workbook.Sheets[firstSheetName];
                        
                        const csvOutput = XLSX.utils.sheet_to_csv(worksheet);
                        document.getElementById('convertedCsvData').value = csvOutput;
                    } catch (err) {
                        console.error(err);
                        alert('ไม่สามารถอ่านไฟล์ Excel นี้ได้ กรุณาตรวจสอบไฟล์');
                    }
                };
                reader.readAsArrayBuffer(file);
            } else {
                document.getElementById('convertedCsvData').value = '';
            }
        }
    </script>
</body>
</html>