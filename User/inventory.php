<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../db/conn.php';
require_once '../auth_check.php';
// ดึงข้อมูลสินค้าทั้งหมด เรียงตามรหัส SKU
$stmt = $pdo->query("SELECT * FROM products ORDER BY sku ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงตัวอักษร 2 ตัวหน้าของ SKU มาจัดกลุ่ม (เช่น SR1, SR2, IV1 จะตัดเอามาแค่ 2 ตัวแรก เช่น "SR" หรือ "IV")
$skuGroups = [];
foreach ($products as $row) {
    $sku = trim($row['sku']);
    if (!empty($sku) && mb_strlen($sku, 'UTF-8') >= 2) {
        // ตัดเอา 2 ตัวอักษรแรก และแปลงเป็นตัวพิมพ์ใหญ่
        $prefix = strtoupper(mb_substr($sku, 0, 2, 'UTF-8'));
        
        if (!in_array($prefix, $skuGroups)) {
            $skuGroups[] = $prefix;
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
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- เรียกใช้งาน Sidebar -->
    <?php include 'sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100">
        
        <!-- Header Bar -->
        <header class="h-20 bg-white border-b border-slate-200/80 flex items-center justify-between px-10 shrink-0 z-10 shadow-xs">
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">สต็อกคงเหลือ (Inventory Status)</h2>
            <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-house text-emerald-600"></i> หน้าหลัก
            </a>
        </header>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-10 space-y-6">
            
            <!-- แจ้งเตือนสถานะการ Import -->
            <?php if (isset($_GET['import'])): ?>
                <?php if ($_GET['import'] == 'success'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>✨ นำเข้าข้อมูลจากไฟล์ CSV สำเร็จเรียบร้อยแล้ว (อัปเดตไป <?= htmlspecialchars($_GET['count'] ?? 0) ?> รายการ)</span>
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
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center bg-white p-8 rounded-3xl border border-slate-200/80 shadow-xs gap-4">
                <div>
                    <h3 class="text-2xl font-bold text-slate-900">สต็อกสินค้าคงเหลือ (จัดกลุ่มตาม 2 ตัวอักษรหน้า SKU)</h3>
                    <p class="text-xs text-slate-500 mt-1">ตัดเอา 2 ตัวอักษรแรกมาจัดหมวดหมู่ให้อัตโนมัติ (เช่น SR, IV)</p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <input type="text" id="searchSku" placeholder="🔍 ค้นหารหัส SKU หรือชื่อสินค้า..." class="px-5 py-3 border border-slate-200 rounded-2xl text-sm w-full md:w-72 focus:outline-none focus:border-emerald-600 bg-slate-50 font-medium">
                    <button onclick="openImportModal()" class="bg-brandGreen hover:bg-emerald-700 text-white px-5 py-3 rounded-2xl text-sm font-semibold shadow-md shadow-emerald-600/25 transition-all flex items-center gap-2 shrink-0">
                        📥 Import Google Sheet
                    </button>
                </div>
            </div>

            <!-- แถบปุ่มกรองกลุ่ม 2 ตัวอักษรหน้า SKU -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
                <button onclick="filterGroup('all')" id="group-all" class="group-tab px-5 py-2.5 rounded-2xl text-xs font-semibold bg-emerald-600 text-white shadow-sm transition-all shrink-0">
                    📂 ทั้งหมด (<?= count($products) ?>)
                </button>
                <?php foreach ($skuGroups as $grp): ?>
                    <?php 
                        $grpCount = 0;
                        foreach ($products as $p) {
                            $pSku = trim($p['sku']);
                            if (mb_strlen($pSku, 'UTF-8') >= 2) {
                                $pPrefix = strtoupper(mb_substr($pSku, 0, 2, 'UTF-8'));
                                if ($pPrefix === $grp) {
                                    $grpCount++;
                                }
                            }
                        }
                    ?>
                    <button onclick="filterGroup('<?= $grp ?>')" id="group-<?= $grp ?>" class="group-tab px-5 py-2.5 rounded-2xl text-xs font-semibold bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 transition-all shrink-0">
                        🏷️ หมวด <?= $grp ?> (<?= $grpCount ?>)
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- ตารางแสดงสินค้า -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50/80 border-b border-slate-200 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                        <tr>
                            <th class="p-5">รหัส SKU</th>
                            <th class="p-5">ชื่อสินค้า</th>
                            <th class="p-5">หนวยนับ</th>
                            <th class="p-5 text-right">ราคาต้นทุน</th>
                            <th class="p-5 text-right">ราคาขาย</th>
                            <th class="p-5 text-right">คงเหลือ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="productTableBody">
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $row): ?>
                                <?php 
                                    $sku = trim($row['sku']);
                                    $rowGroup = (mb_strlen($sku, 'UTF-8') >= 2) ? strtoupper(mb_substr($sku, 0, 2, 'UTF-8')) : 'OT';
                                ?>
                            <tr class="hover:bg-slate-50/80 transition-colors product-row" data-sku-prefix="<?= $rowGroup ?>">
                                <td class="p-5 font-bold text-slate-700">
                                    <span class="bg-slate-100 border border-slate-200 px-3 py-1 rounded-xl text-emerald-700"><?= htmlspecialchars($row['sku']) ?></span>
                                </td>
                                <td class="p-5 font-bold text-slate-900"><?= htmlspecialchars($row['name']) ?></td>
                                <td class="p-5 text-slate-500"><?= htmlspecialchars($row['category']) ?></td>
                                <td class="p-5 text-right font-medium">฿<?= number_format($row['cost_price'], 2) ?></td>
                                <td class="p-5 text-right font-medium">฿<?= number_format($row['selling_price'], 2) ?></td>
                                <td class="p-5 text-right font-bold text-brandGreen"><?= number_format($row['stock_quantity']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-12 text-center text-slate-400 font-medium">ไม่พบข้อมูลสินค้าในฐานข้อมูล</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal สำหรับ Import ข้อมูล -->
    <div id="importModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center z-50">
        <div class="bg-white p-8 rounded-3xl w-full max-w-md shadow-xl border border-slate-200 space-y-6">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-900">Import ข้อมูลจาก Google Sheets</h3>
                <button onclick="closeImportModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>
            
            <form action="import_process.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-slate-600">เลือกไฟล์ CSV (ที่ดาวน์โหลดจาก Google Sheets)</label>
                    <input type="file" name="csv_file" accept=".csv" required class="w-full p-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        *ระบบจะดึง 2 ตัวอักษรแรกของรหัส SKU มาจัดกลุ่มให้อัตโนมัติ
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeImportModal()" class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">ยกเลิก</button>
                    <button type="submit" name="import_submit" class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 shadow-md shadow-emerald-600/20 transition-all">อัปโหลดและบันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script สำหรับตัวกรองกลุ่ม 2 ตัวอักษร และการค้นหา -->
    <script>
        let currentGroup = 'all';

        function filterGroup(group) {
            currentGroup = group;
            
            document.querySelectorAll('.group-tab').forEach(btn => {
                btn.classList.remove('bg-emerald-600', 'text-white', 'shadow-sm');
                btn.classList.add('bg-white', 'text-slate-600', 'border', 'border-slate-200');
            });
            
            let activeBtn = document.getElementById('group-' + group);
            if(activeBtn) {
                activeBtn.classList.remove('bg-white', 'text-slate-600', 'border', 'border-slate-200');
                activeBtn.classList.add('bg-emerald-600', 'text-white', 'shadow-sm');
            }

            applyFilters();
        }

        function applyFilters() {
            let searchFilter = document.getElementById('searchSku').value.toLowerCase();
            let rows = document.querySelectorAll('.product-row');
            
            rows.forEach(row => {
                let rowPrefix = row.getAttribute('data-sku-prefix');
                let rowText = row.innerText.toLowerCase();
                
                let matchesGroup = (currentGroup === 'all' || rowPrefix === currentGroup);
                let matchesSearch = rowText.includes(searchFilter);

                if (matchesGroup && matchesSearch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        document.getElementById('searchSku').addEventListener('keyup', applyFilters);

        function openImportModal() {
            document.getElementById('importModal').classList.remove('hidden');
            document.getElementById('importModal').classList.add('flex');
        }
        function closeImportModal() {
            document.getElementById('importModal').classList.remove('flex');
            document.getElementById('importModal').classList.add('hidden');
        }
     
    </script>
</body>
</html>