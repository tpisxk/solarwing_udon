<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'requisition_form';
$success_msg = '';$error_msg = '';

// ดึงข้อมูลโครงการทั้งหมด
$projects = [];
try {
    $stmt_p =$pdo->query("SELECT id, name FROM installations ORDER BY id DESC");
    $projects =$stmt_p->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$projects = []; }

// ดึงรายการสินค้าและ id จาก products
$products = [];
try {
    $stmt_prod =$pdo->query("SELECT id, name, sku, category, stock_quantity FROM products ORDER BY name ASC");
    $products =$stmt_prod->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$products = []; }

// 1. จัดการเมื่อวิศวกรกดส่งใบเบิกให้ Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_to_admin'])) {
    $installation_id = intval($_POST['installation_id'] ?? 0);
    $project_name = trim($_POST['project_name'] ?? '');
    $req_date = trim($_POST['req_date'] ?? date('Y-m-d'));
    $items =$_POST['item'] ?? [];
    $engineer_name =$_SESSION['username'] ?? 'วิศวกร';

    if ($installation_id > 0 && !empty($items)) {
        try {
            $pdo->beginTransaction();

            $stmt_req =$pdo->prepare("INSERT INTO requisitions (installation_id, project_name, req_date, engineer_name, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
            $stmt_req->execute([$installation_id,$project_name, $req_date,$engineer_name]);
            $req_id =$pdo->lastInsertId();

            $stmt_item =$pdo->prepare("INSERT INTO requisition_items (requisition_id, product_id, quantity, remark) VALUES (?, ?, ?, ?)");

            foreach ($items as$it) {
                $product_id = intval($it['product_id'] ?? 0);
                $qty = intval($it['qty'] ?? 0);
                $remark = trim($it['remark'] ?? '');

                if ($product_id > 0 && $qty > 0) {$stmt_item->execute([$req_id,$product_id, $qty,$remark]);
                }
            }

            $pdo->commit();$success_msg = "ส่งใบเบิกไปยังผู้ดูแลระบบ (Admin) เรียบร้อยแล้ว (Stock ในคลังยังคงเดิม)";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {$pdo->rollBack(); }
            $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    } else {
        $error_msg = "กรุณาเลือกโครงการและระบุรายการสินค้าอย่างน้อย 1 รายการ";
    }
}

// 2. จัดการเมื่อวิศวกรกดปุ่ม Export เป็น Excel (รับค่าแบบ POST เพื่อความชัวร์ว่าข้อมูลสินค้ามาครบ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_excel'])) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Requisition_Form_" . date('Ymd_His') . ".xls");
    
    $req_project = trim($_POST['project_name'] ?? 'ไม่ระบุชื่อโครงการ');
    $req_date = trim($_POST['req_date'] ?? date('Y-m-d'));
    $items =$_POST['item'] ?? [];

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
    echo '<body>';
    echo '<h2 style="text-align:center;">ใบเบิกของ - SOLAR WING</h2>';
    echo '<p><b>แผนกเบิกของ:</b> Solar Wing &nbsp;&nbsp;&nbsp;&nbsp; <b>วันที่เบิกของ:</b> ' . htmlspecialchars($req_date) . '</p>';
    echo '<p><b>ชื่อโครงการ:</b> ' . htmlspecialchars($req_project) . '</p>';
    echo '<table border="1">';
    echo '<tr style="background-color:#059669; color:#ffffff; font-weight:bold;">';
    echo '<th>ลำดับที่</th><th>รายการสินค้า</th><th>จำนวนที่เบิก</th><th>หมายเหตุ</th>';
    echo '</tr>';

    $no = 1;
    foreach ($items as$it) {
        $prod_id = intval($it['product_id'] ?? 0);
        $qty = trim($it['qty'] ?? '');
        if ($prod_id > 0 && $qty !== '') {$p_name = '-';
            foreach ($products as$p) {
                if ($p['id'] ==$prod_id) {
                    $p_name =$p['name'] . (!empty($p['sku']) ? ' (SKU: ' .$p['sku'] . ')' : '');
                    break;
                }
            }

            echo '<tr>';
            echo '<td align="center">' . $no++ . '</td>';
            echo '<td>' . htmlspecialchars($p_name) . '</td>';
            echo '<td align="center">' . htmlspecialchars($qty) . '</td>';
            echo '<td>' . htmlspecialchars($it['remark'] ?? '') . '</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    echo '</body></html>';
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเบิกของ (Requisition Form) - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Prompt', 'sans-serif'] }, colors: { brandGreen: '#059669' } } } }
    </script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar สำหรับ Desktop และ Mobile Drawer -->
    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out">
        <?php 
        if (file_exists('sidebar_engineer.php')) { include 'sidebar_engineer.php'; }
        elseif (file_exists('sidebar_admin.php')) { include 'sidebar_admin.php'; }
        ?>
    </div>

    <!-- Backdrop สำหรับปิด Sidebar บนมือถือ -->
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-slate-900 text-xl p-2 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">สร้างใบเบิกของ (Requisition Form)</h2>
                    <p class="text-[11px] md:text-xs text-slate-500 mt-0.5">เลือกรายการสินค้าเพื่อส่งให้แอดมินตรวจสอบ หรือ Export เป็น Excel</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 md:px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
                </a>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-8 w-full">
            <div class="max-w-5xl mx-auto bg-white rounded-3xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6">
                
                <?php if (!empty($success_msg)): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-semibold"><?= $success_msg ?></div>
                <?php endif; ?>
                <?php if (!empty($error_msg)): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-xs font-semibold"><?= $error_msg ?></div>
                <?php endif; ?>

                <div class="border-b border-slate-200 pb-6 flex justify-between items-center">
                    <div>
                        <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-wider">SOLAR WING</h1>
                        <p class="text-xs md:text-sm font-bold text-slate-600 mt-1">ใบเบิกของ (Requisition Form)</p>
                    </div>
                    <div class="text-right text-xs space-y-1">
                        <div><b>แผนก:</b> Solar Wing</div>
                        <div><b>วันที่:</b> <?= date('Y-m-d') ?></div>
                    </div>
                </div>

                <!-- ฟอร์มหลักสำหรับรองรับทั้งส่ง Admin และ Export Excel -->
                <form action="requisition_form.php" method="POST" id="reqForm" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50 p-6 rounded-2xl border border-slate-200/80">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase">เลือกชื่อโครงการ / หน้างาน</label>
                            <select name="installation_id" id="installationSelect" onchange="updateProjectName(this)" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-brandGreen">
                                <option value="">-- เลือกโครงการ --</option>
                                <?php foreach ($projects as$p): ?>
                                    <option value="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="project_name" id="project_name">
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase">วันที่เบิก</label>
                            <input type="date" name="req_date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-brandGreen">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">รายการสินค้าจาก Stock</label>
                            <button type="button" onclick="addRow()" class="bg-brandGreen hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all shadow-sm">
                                <i class="fa-solid fa-plus text-xs"></i> เพิ่มแถวรายการ
                            </button>
                        </div>

                        <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                            <table class="w-full text-left border-collapse text-xs min-w-[700px]" id="reqTable">
                                <thead>
                                    <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 font-bold uppercase">
                                        <th class="py-3 px-3 text-center w-12">ลำดับ</th>
                                        <th class="py-3 px-4">เลือกรายการจาก Stock</th>
                                        <th class="py-3 px-3 text-center w-32">คงเหลือในคลัง</th>
                                        <th class="py-3 px-3 text-center w-24">จำนวนเบิก</th>
                                        <th class="py-3 px-4">หมายเหตุ</th>
                                        <th class="py-3 px-2 text-center w-16">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100" id="tableBody">
                                    <?php for ($i = 0; $i < 4; $i++): ?>
                                    <tr class="hover:bg-slate-50/50 row-item">
                                        <td class="py-2.5 px-3 text-center font-bold text-slate-500 row-no"><?= $i + 1 ?></td>
                                        <td class="py-2.5 px-3">
                                            <select name="item[<?= $i ?>][product_id]" onchange="updateProductInfo(this, <?= $i ?>)" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-brandGreen prod-select">
                                                <option value="">-- เลือกสินค้าจาก Stock --</option>
                                                <?php foreach ($products as$prod): ?>
                                                    <option value="<?= $prod['id'] ?>" data-stock="<?= intval($prod['stock_quantity']) ?>">
                                                        <?= htmlspecialchars($prod['name']) ?><?= !empty($prod['sku']) ? ' (SKU: ' .$prod['sku'] . ')' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <input type="text" id="stock_<?= $i ?>" value="-" readonly class="w-full px-2 py-2 rounded-xl border border-slate-100 bg-slate-50 text-center text-xs font-bold text-emerald-600">
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <input type="number" name="item[<?= $i ?>][qty]" min="0" placeholder="0" class="w-full px-2 py-2 rounded-xl border border-slate-200 text-center text-xs focus:outline-none focus:border-brandGreen font-bold">
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <input type="text" name="item[<?= $i ?>][remark]" placeholder="หมายเหตุ..." class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen">
                                        </td>
                                        <td class="py-2.5 px-2 text-center">
                                            <button type="button" onclick="removeRow(this)" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center mx-auto transition-all" title="ลบแถว">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ปุ่มควบคุม (ส่ง Admin และ Export Excel) -->
                    <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="submit" name="export_excel" value="1" formtarget="_blank" class="bg-slate-700 hover:bg-slate-800 text-white px-5 py-3 rounded-2xl text-xs font-bold shadow-md transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-file-excel text-sm text-emerald-400"></i> Export เป็น Excel
                        </button>
                        <button type="submit" name="submit_to_admin" onclick="return confirm('ยืนยันส่งใบเบิกนี้ให้ Admin ตรวจสอบ?')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-xs font-bold shadow-md shadow-emerald-600/25 transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-paper-plane text-sm"></i> ส่งใบเบิกให้ Admin ตรวจสอบ
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <script>
        // ฟังก์ชันเปิด-ปิด Sidebar สำหรับมือถือ
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const backdrop = document.getElementById('sidebarBackdrop');
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }

        const productsData = <?= json_encode($products) ?>;
        
        function updateProjectName(selectElem) {
            const selected = selectElem.options[selectElem.selectedIndex];
            document.getElementById('project_name').value = selected.getAttribute('data-name') || '';
        }

        function updateProductInfo(selectElem, index) {
            const selectedOption = selectElem.options[selectElem.selectedIndex];
            const stockQty = selectedOption.getAttribute('data-stock') ?? '-';
            const stockInput = document.getElementById('stock_' + index);
            if (stockInput) stockInput.value = stockQty !== '-' ? stockQty + ' ชิ้น' : '-';
        }

        function addRow() {
            const tbody = document.getElementById('tableBody');
            const rowCount = tbody.rows.length;
            let optionsHtml = '<option value="">-- เลือกสินค้าจาก Stock --</option>';
            productsData.forEach(p => {
                let label = p.name + (p.sku ? ` (SKU: ${p.sku})` : '');
                optionsHtml += `<option value="${p.id}" data-stock="${p.stock_quantity ?? 0}">${label}</option>`;
            });
            const newRow = document.createElement('tr');
            newRow.className = 'hover:bg-slate-50/50 row-item';
            newRow.innerHTML = `
                <td class="py-2.5 px-3 text-center font-bold text-slate-500 row-no">${rowCount + 1}</td>
                <td class="py-2.5 px-3">
                    <select name="item[${rowCount}][product_id]" onchange="updateProductInfo(this, ${rowCount})" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-brandGreen prod-select">${optionsHtml}</select>
                </td>
                <td class="py-2.5 px-3 text-center"><input type="text" id="stock_${rowCount}" value="-" readonly class="w-full px-2 py-2 rounded-xl border border-slate-100 bg-slate-50 text-center text-xs font-bold text-emerald-600"></td>
                <td class="py-2.5 px-3 text-center"><input type="number" name="item[${rowCount}][qty]" min="0" placeholder="0" class="w-full px-2 py-2 rounded-xl border border-slate-200 text-center text-xs focus:outline-none focus:border-brandGreen font-bold"></td>
                <td class="py-2.5 px-3"><input type="text" name="item[${rowCount}][remark]" placeholder="หมายเหตุ..." class="w-full px-2 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen"></td>
                <td class="py-2.5 px-2 text-center"><button type="button" onclick="removeRow(this)" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center mx-auto transition-all" title="ลบแถว"><i class="fa-solid fa-trash text-xs"></i></button></td>
            `;
            tbody.appendChild(newRow);
            recalculateRows();
        }

        function removeRow(btn) {
            btn.closest('tr').remove();
            recalculateRows();
        }

        function recalculateRows() {
            document.querySelectorAll('#tableBody .row-item').forEach((row, index) => {
                row.querySelector('.row-no').innerText = index + 1;
                row.querySelector('select').name = `item[${index}][product_id]`;
                row.querySelector('input[type="text"]').id = `stock_${index}`;
                row.querySelectorAll('input[type="number"], input[type="text"][name]').forEach(inp => {
                    if(inp.name.includes('qty')) inp.name = `item[${index}][qty]`;
                    if(inp.name.includes('remark')) inp.name = `item[${index}][remark]`;
                });
                row.querySelector('select').setAttribute('onchange', `updateProductInfo(this, ${index})`);
            });
        }
    </script>
</body>
</html>