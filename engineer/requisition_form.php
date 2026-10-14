<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'requisition_form';
$success_msg = '';
$error_msg = '';

// ดึงข้อมูลโครงการทั้งหมด
$projects = [];
try {
    $stmt_p = $pdo->query("SELECT id, name FROM installations ORDER BY id DESC");
    $projects = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $projects = [];
}

// ดึงรายการสินค้าและ id จาก products
$products = [];
try {
    $stmt_prod = $pdo->query("SELECT id, name, sku, category, stock_quantity FROM products ORDER BY name ASC");
    $products = $stmt_prod->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $products = [];
}

// ดึงรายการ Template ทั้งหมด
$templates = [];
try {
    $stmt_t = $pdo->query("SELECT * FROM requisition_templates ORDER BY id DESC");
    $templates = $stmt_t->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $templates = [];
}

// ==========================================
// 1. จัดการบันทึก Template จากฟอร์มหน้าเว็บ (ตัด kwp ออก)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_as_template'])) {
    $template_name = trim($_POST['template_name'] ?? '');
    $items = $_POST['item'] ?? [];

    if (!empty($template_name) && !empty($items)) {
        try {
            $pdo->beginTransaction();
            $stmt_t_ins = $pdo->prepare("INSERT INTO requisition_templates (template_name) VALUES (?)");
            $stmt_t_ins->execute([$template_name]);
            $template_id = $pdo->lastInsertId();

            // ไม่บันทึก kwp แล้ว (ใช้ตารางเดิมโดยปล่อย kwp เป็นค่าว่าง)
            $stmt_ti_ins = $pdo->prepare("INSERT INTO requisition_template_items (template_id, product_id, quantity, remark) VALUES (?, ?, ?, ?)");

            foreach ($items as $it) {
                $product_id = intval($it['product_id'] ?? 0);
                $qty = intval($it['qty'] ?? 0);
                $remark = trim($it['remark'] ?? '');

                if ($product_id > 0 && $qty > 0) {
                    $stmt_ti_ins->execute([$template_id, $product_id, $qty, $remark]);
                }
            }

            $pdo->commit();
            $success_msg = "บันทึกฟอร์มนี้เป็น Template สำเร็จแล้ว!";
            $stmt_t = $pdo->query("SELECT * FROM requisition_templates ORDER BY id DESC");
            $templates = $stmt_t->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error_msg = "บันทึก Template ไม่สำเร็จ: " . $e->getMessage();
        }
    } else {
        $error_msg = "กรุณากรอกชื่อ Template และระบุรายการสินค้าอย่างน้อย 1 รายการ";
    }
}

// ==========================================
// 2. จัดการ Import Excel เพื่อสร้าง Template ใหม่
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_as_template'])) {
    $template_name = trim($_POST['template_name_import'] ?? '');
    $imported_json = $_POST['imported_template_data'] ?? '';

    if (!empty($template_name) && !empty($imported_json)) {
        $items = json_decode($imported_json, true);
        if (!empty($items)) {
            try {
                $pdo->beginTransaction();
                $stmt_t_ins = $pdo->prepare("INSERT INTO requisition_templates (template_name) VALUES (?)");
                $stmt_t_ins->execute([$template_name]);
                $template_id = $pdo->lastInsertId();

                $stmt_ti_ins = $pdo->prepare("INSERT INTO requisition_template_items (template_id, product_id, quantity, remark) VALUES (?, ?, ?, ?)");

                foreach ($items as $it) {
                    $product_id = intval($it['product_id'] ?? 0);
                    $qty = intval($it['qty'] ?? 0);
                    $remark = trim($it['remark'] ?? '');

                    if ($product_id > 0 && $qty > 0) {
                        $stmt_ti_ins->execute([$template_id, $product_id, $qty, $remark]);
                    }
                }

                $pdo->commit();
                $success_msg = "Import ไฟล์ Excel และบันทึกเป็น Template สำเร็จแล้ว!";
                $stmt_t = $pdo->query("SELECT * FROM requisition_templates ORDER BY id DESC");
                $templates = $stmt_t->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error_msg = "ไม่สามารถบันทึก Template ได้: " . $e->getMessage();
            }
        } else {
            $error_msg = "ไม่พบข้อมูลรายการสินค้าในไฟล์ Excel";
        }
    } else {
        $error_msg = "กรุณากรอกชื่อ Template และเลือกไฟล์ Excel";
    }
}

// ==========================================
// 3. จัดการเมื่อกดส่งใบเบิกให้ Admin
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_to_admin'])) {
    $installation_id = intval($_POST['installation_id'] ?? 0);
    $project_name = trim($_POST['project_name'] ?? '');
    $req_date = trim($_POST['req_date'] ?? date('Y-m-d'));
    $items = $_POST['item'] ?? [];
    $engineer_name = $_SESSION['username'] ?? 'วิศวกร';

    if ($installation_id > 0 && !empty($items)) {
        try {
            $pdo->beginTransaction();

            $stmt_req = $pdo->prepare("INSERT INTO requisitions (installation_id, project_name, req_date, engineer_name, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
            $stmt_req->execute([$installation_id, $project_name, $req_date, $engineer_name]);
            $req_id = $pdo->lastInsertId();

            $stmt_item = $pdo->prepare("INSERT INTO requisition_items (requisition_id, product_id, quantity, remark) VALUES (?, ?, ?, ?)");

            foreach ($items as $it) {
                $product_id = intval($it['product_id'] ?? 0);
                $qty = intval($it['qty'] ?? 0);
                $remark = trim($it['remark'] ?? '');

                if ($product_id > 0 && $qty > 0) {
                    $stmt_item->execute([$req_id, $product_id, $qty, $remark]);
                }
            }

            $pdo->commit();
            $success_msg = "ส่งใบเบิกไปยังผู้ดูแลระบบ (Admin) เรียบร้อยแล้ว";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    } else {
        $error_msg = "กรุณาเลือกโครงการและระบุรายการสินค้าอย่างน้อย 1 รายการ";
    }
}

// ==========================================
// 4. จัดการเมื่อกด Export เป็น Excel
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_excel'])) {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Requisition_Form_" . date('Ymd_His') . ".xls");

    $req_project = trim($_POST['project_name'] ?? 'ไม่ระบุชื่อโครงการ');
    $req_date = trim($_POST['req_date'] ?? date('Y-m-d'));
    $items = $_POST['item'] ?? [];

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
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
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
</head>

<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform duration-300 ease-in-out">
        <?php
        if (file_exists('sidebar_engineer.php')) { include 'sidebar_engineer.php'; }
        elseif (file_exists('sidebar_admin.php')) { include 'sidebar_admin.php'; }
        ?>
    </div>

    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-sm"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 md:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-slate-900 text-xl p-2 rounded-xl bg-slate-50 border border-slate-200 focus:outline-none">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">สร้างใบเบิกของ (Requisition Form)</h2>
                    <p class="text-[11px] md:text-xs text-slate-500 mt-0.5">เลือกใช้ Template สำเร็จรูป หรือบันทึกฟอร์มใหม่เพื่อใช้งาน</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 md:px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
                </a>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-8 w-full">
            <div class="max-w-6xl mx-auto bg-white rounded-3xl border border-slate-200 shadow-sm p-6 md:p-8 space-y-6">

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

                <!-- โซนจัดการ Template -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
                    <div class="flex items-center gap-2">
                        <select id="templateSelect" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-brandGreen">
                            <option value="">-- เลือก Template ฟอร์มสำเร็จรูป --</option>
                            <?php foreach ($templates as$t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['template_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="loadTemplate()" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-bolt text-amber-400"></i> โหลดฟอร์มนี้
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="file" id="templateExcelFile" accept=".xlsx, .xls, .csv" class="hidden" onchange="importAndSaveTemplate(event)">
                        <input type="text" id="importTemplateName" placeholder="ตั้งชื่อ Template จากไฟล์ Excel..." class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-brandGreen">
                        <button type="button" onclick="triggerImportTemplate()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-file-import"></i> Import เป็นฟอร์ม
                        </button>
                    </div>
                </div>

                <!-- ฟอร์มหลักสำหรับเบิกของ -->
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
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                            <label class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">รายการสินค้าจาก Stock</label>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="addRow()" class="bg-brandGreen hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all shadow-sm">
                                    <i class="fa-solid fa-plus text-xs"></i> เพิ่มแถวรายการ
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                            <table class="w-full text-left border-collapse text-xs min-w-[700px]" id="reqTable">
                                <thead>
                                    <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 font-bold uppercase">
                                        <th class="py-3 px-3 text-center w-12">ลำดับ</th>
                                        <th class="py-3 px-4">เลือกรายการจาก Stock</th>
                                        <th class="py-3 px-3 text-center w-32">คงเหลือในคลัง</th>
                                        <th class="py-3 px-3 text-center w-28">จำนวนเบิก</th>
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

                    <!-- ปุ่มล่างฟอร์ม -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100">
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <input type="text" id="newTemplateName" placeholder="ชื่อ Template จากฟอร์มนี้..." class="px-3 py-2.5 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-brandGreen w-full sm:w-60">
                            <button type="button" onclick="saveTemplate()" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-1.5 shadow-sm">
                                <i class="fa-solid fa-bookmark"></i> บันทึกตารางนี้เป็น Template
                            </button>
                        </div>

                        <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                            <button type="submit" name="export_excel" value="1" formtarget="_blank" class="bg-slate-700 hover:bg-slate-800 text-white px-5 py-3 rounded-2xl text-xs font-bold shadow-md transition-all flex items-center justify-center gap-2">
                                <i class="fa-solid fa-file-excel text-sm text-emerald-400"></i> Export เป็น Excel
                            </button>
                            <button type="submit" name="submit_to_admin" onclick="return confirm('ยืนยันส่งใบเบิกนี้ให้ Admin ตรวจสอบ?')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-xs font-bold shadow-md shadow-emerald-600/25 transition-all flex items-center justify-center gap-2">
                                <i class="fa-solid fa-paper-plane text-sm"></i> ส่งใบเบิกให้ Admin
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <script>
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

        function addRow(selectedProductId = '', qtyVal = '', remarkVal = '') {
            const tbody = document.getElementById('tableBody');
            const rowCount = tbody.rows.length;
            let optionsHtml = '<option value="">-- เลือกสินค้าจาก Stock --</option>';
            
            productsData.forEach(p => {
                let label = p.name + (p.sku ? ` (SKU: ${p.sku})` : '');
                let selected = (p.id == selectedProductId) ? 'selected' : '';
                let stock = p.stock_quantity ?? 0;
                optionsHtml += `<option value="${p.id}" data-stock="${stock}" ${selected}>${label}</option>`;
            });

            let initialStock = '-';
            if(selectedProductId) {
                let found = productsData.find(p => p.id == selectedProductId);
                if(found) initialStock = (found.stock_quantity ?? 0) + ' ชิ้น';
            }

            const newRow = document.createElement('tr');
            newRow.className = 'hover:bg-slate-50/50 row-item';
            newRow.innerHTML = `
                <td class="py-2.5 px-3 text-center font-bold text-slate-500 row-no">${rowCount + 1}</td>
                <td class="py-2.5 px-3">
                    <select name="item[${rowCount}][product_id]" onchange="updateProductInfo(this, ${rowCount})" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-brandGreen prod-select">${optionsHtml}</select>
                </td>
                <td class="py-2.5 px-3 text-center"><input type="text" id="stock_${rowCount}" value="${initialStock}" readonly class="w-full px-2 py-2 rounded-xl border border-slate-100 bg-slate-50 text-center text-xs font-bold text-emerald-600"></td>
                <td class="py-2.5 px-3 text-center"><input type="number" name="item[${rowCount}][qty]" min="0" value="${qtyVal}" placeholder="0" class="w-full px-2 py-2 rounded-xl border border-slate-200 text-center text-xs focus:outline-none focus:border-brandGreen font-bold"></td>
                <td class="py-2.5 px-3"><input type="text" name="item[${rowCount}][remark]" value="${remarkVal}" placeholder="หมายเหตุ..." class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none focus:border-brandGreen"></td>
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
                row.querySelector('input[id^="stock_"]').id = `stock_${index}`;
                row.querySelectorAll('input[type="number"]').forEach(inp => inp.name = `item[${index}][qty]`);
                row.querySelectorAll('input[type="text"][name*="remark"]').forEach(inp => inp.name = `item[${index}][remark]`);
                row.querySelector('select').setAttribute('onchange', `updateProductInfo(this, ${index})`);
            });
        }

        function saveTemplate() {
            let tName = document.getElementById('newTemplateName').value.trim();
            if (!tName) {
                alert('กรุณาตั้งชื่อ Template ก่อนบันทึก');
                return;
            }
            let form = document.getElementById('reqForm');
            let hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'save_as_template';
            hiddenInput.value = '1';
            
            let hiddenNameInput = document.createElement('input');
            hiddenNameInput.type = 'hidden';
            hiddenNameInput.name = 'template_name';
            hiddenNameInput.value = tName;

            form.appendChild(hiddenInput);
            form.appendChild(hiddenNameInput);
            form.submit();
        }

        async function loadTemplate() {
            let tId = document.getElementById('templateSelect').value;
            if (!tId) {
                alert('กรุณาเลือก Template ที่ต้องการใช้งาน');
                return;
            }

            try {
                let response = await fetch('get_template_items.php?template_id=' + tId);
                let result = await response.json();
                
                if (result.success && result.items.length > 0) {
                    const tbody = document.getElementById('tableBody');
                    tbody.innerHTML = '';

                    result.items.forEach(item => {
                        addRow(item.product_id, item.quantity, item.remark);
                    });
                    alert('โหลด Template สำเร็จเรียบร้อยแล้ว!');
                } else {
                    alert('ไม่พบรายการสินค้าใน Template นี้');
                }
            } catch (err) {
                console.error(err);
                alert('เกิดข้อผิดพลาดในการโหลด Template');
            }
        }

        function triggerImportTemplate() {
            let tName = document.getElementById('importTemplateName').value.trim();
            if (!tName) {
                alert('กรุณาตั้งชื่อ Template ก่อนเลือกไฟล์ Excel');
                return;
            }
            document.getElementById('templateExcelFile').click();
        }

        function importAndSaveTemplate(event) {
            const file = event.target.files[0];
            if (!file) return;

            let tName = document.getElementById('importTemplateName').value.trim();
            const reader = new FileReader();

            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const worksheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonRows = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                    let headerRowIndex = -1;
                    let colMap = { sku: 0, item: 1, qty: 3, remark: 6 };

                    for (let i = 0; i < jsonRows.length; i++) {
                        let row = jsonRows[i];
                        if (!row) continue;
                        let rowStr = row.join('|').toLowerCase();
                        if (rowStr.includes('รหัส') || rowStr.includes('ชื่อสินค้า') || rowStr.includes('จำนวน')) {
                            headerRowIndex = i;
                            row.forEach((cell, idx) => {
                                let val = String(cell).trim();
                                if (val.includes('รหัส')) colMap.sku = idx;
                                if (val.includes('ชื่อ')) colMap.item = idx;
                                if (val.includes('จำนวน')) colMap.qty = idx;
                                if (val.includes('หมายเหตุ')) colMap.remark = idx;
                            });
                            break;
                        }
                    }

                    let startIndex = headerRowIndex !== -1 ? headerRowIndex + 1 : 0;
                    const cleanText = (str) => String(str || '').trim().replace(/\s+/g, ' ');
                    let parsedItems = [];

                    for (let i = startIndex; i < jsonRows.length; i++) {
                        let row = jsonRows[i];
                        if (!row || row.length === 0) continue;

                        let rawSku = cleanText(row[colMap.sku]);
                        let rawItemName = cleanText(row[colMap.item]);
                        let qty = row[colMap.qty] !== undefined ? cleanText(row[colMap.qty]) : '';
                        let rawRemark = colMap.remark !== undefined ? cleanText(row[colMap.remark]) : '';

                        if (!rawSku && !rawItemName) continue;

                        let foundProd = productsData.find(p => {
                            let pName = cleanText(p.name).toLowerCase();
                            let pSku = cleanText(p.sku).toLowerCase();
                            let searchSku = rawSku.toLowerCase();
                            let searchName = rawItemName.toLowerCase();
                            return (pSku && pSku === searchSku) || (pName && pName === searchName) || pName.includes(searchName);
                        });

                        if (foundProd) {
                            parsedItems.push({
                                product_id: foundProd.id,
                                qty: qty || 1,
                                remark: rawRemark
                            });
                        }
                    }

                    if (parsedItems.length === 0) {
                        alert('ไม่พบรายการสินค้าที่ตรงกับระบบในไฟล์ Excel นี้');
                        return;
                    }

                    let form = document.getElementById('reqForm');
                    
                    let inputAction = document.createElement('input');
                    inputAction.type = 'hidden';
                    inputAction.name = 'import_as_template';
                    inputAction.value = '1';

                    let inputName = document.createElement('input');
                    inputName.type = 'hidden';
                    inputName.name = 'template_name_import';
                    inputName.value = tName;

                    let inputData = document.createElement('input');
                    inputData.type = 'hidden';
                    inputData.name = 'imported_template_data';
                    inputData.value = JSON.stringify(parsedItems);

                    form.appendChild(inputAction);
                    form.appendChild(inputName);
                    form.appendChild(inputData);
                    form.submit();

                } catch (err) {
                    console.error(err);
                    alert('เกิดข้อผิดพลาดในการอ่านไฟล์ Excel');
                }
                event.target.value = '';
            };
            reader.readAsArrayBuffer(file);
        }
    </script>
</body>

</html>