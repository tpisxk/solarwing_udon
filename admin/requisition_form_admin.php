<?php
/**
 * หน้าสร้างใบเบิกวัสดุอุปกรณ์ (Requisition Form) - SolarStock Pro
 */
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'requisition_form';
$success_msg = $error_msg = '';

$projects = $pdo->query("SELECT id, name FROM installations ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// ใช้ GREATEST(stock_quantity, 0) เพื่อบังคับให้ถ้าสต็อกติดลบ ให้แสดงผลเป็น 0 ทันที
$products = $pdo->query("SELECT id, name, sku, GREATEST(stock_quantity, 0) AS stock_quantity FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$templates = $pdo->query("SELECT * FROM requisition_templates ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_as_template']) || isset($_POST['import_as_template'])) {
            $t_name = trim($_POST['template_name'] ?? $_POST['template_name_import'] ?? '');
            $items = isset($_POST['import_as_template']) ? json_decode($_POST['imported_template_data'] ?? '[]', true) : ($_POST['item'] ?? []);
            if (!$t_name || empty($items)) throw new Exception("กรุณากรอกชื่อและรายการสินค้า");
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO requisition_templates (template_name) VALUES (?)");
            $stmt->execute([$t_name]);
            $t_id = $pdo->lastInsertId();
            
            $stmt_i = $pdo->prepare("INSERT INTO requisition_template_items (template_id, product_id, quantity, remark) VALUES (?, ?, ?, ?)");
            foreach ($items as $it) {
                $qty = intval($it['qty'] ?? 0);
                if ($it['product_id'] > 0 && $qty > 0) {
                    $stmt_i->execute([$t_id, $it['product_id'], $qty, $it['remark'] ?? '']);
                }
            }
            $pdo->commit();
            $success_msg = "บันทึก Template สำเร็จ!";
            $templates = $pdo->query("SELECT * FROM requisition_templates ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        } elseif (isset($_POST['submit_to_admin'])) {
            $inst_id = intval($_POST['installation_id'] ?? 0);
            $items = $_POST['item'] ?? [];
            if ($inst_id <= 0 || empty($items)) throw new Exception("กรุณาเลือกโครงการและรายการสินค้า");

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO requisitions (installation_id, project_name, req_date, engineer_name, status, created_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
            $stmt->execute([$inst_id, $_POST['project_name'], $_POST['req_date'], $_SESSION['username'] ?? 'วิศวกร']);
            $req_id = $pdo->lastInsertId();

            $stmt_i = $pdo->prepare("INSERT INTO requisition_items (requisition_id, product_id, quantity, remark) VALUES (?, ?, ?, ?)");
            $stmt_po = $pdo->prepare("INSERT INTO purchase_orders (requisition_id, product_id, required_qty, status, created_at) VALUES (?, ?, ?, 'Pending', NOW())");

            $out_of_stock_list = [];

            foreach ($items as $it) {
                $p_id = intval($it['product_id'] ?? 0);
                $qty = intval($it['qty'] ?? 0);

                if ($p_id > 0 && $qty > 0) {
                    // 1. บันทึกรายการเบิกปกติลง requisition_items
                    $stmt_i->execute([$req_id, $p_id, $qty, $it['remark'] ?? '']);

                    // 2. เช็กสต็อกปัจจุบันในตาราง products
                    $stmt_chk =$pdo->prepare("SELECT name, GREATEST(stock_quantity, 0) AS stock_quantity FROM products WHERE id = ?");
                    $stmt_chk->execute([$p_id]);
                    $prod =$stmt_chk->fetch(PDO::FETCH_ASSOC);

                    // 3. ถ้าสต็อกไม่พอหรือเป็น 0 ให้ส่งเข้าตาราง purchase_orders
                    if ($prod) {
                        $current_stock = intval($prod['stock_quantity']);
                        if ($current_stock < $qty) {$needed_qty = $qty -$current_stock;
                            $stmt_po->execute([$req_id,$p_id, $needed_qty]);$out_of_stock_list[] = $prod['name'] . " (ขาด {$needed_qty} ชิ้น)";
                        }
                    }
                }
            }
            $pdo->commit();

            // ==========================================
            // ส่ง LINE แจ้งเตือนเมื่อส่งใบเบิกใหม่
            // ==========================================
            $access_token = "pKse9FestSF5QdY9Hgnb7GtbcJqtXwucA50VBjLmWySwUbwM5JamQDMvlJflPQqTbibghoPRCGrGRnvehBxSooN0xjX6/3I4CF0nSeqbRgtKIBkE1mkxzPgmDKh+Io70jW7o548tAqX67bsJ3JQTBwdB04t89/1O/w1cDnyilFU=";
            
            $admin_line_ids = [
                "U61cff0977ce8c9de7bc8a5f949e44a43", // User ID ของทิว
                "U0b88acf06842b3899ee341a0fce58ba1", // พี่มะปราง
            ];

            $line_message = "🔔 มีใบเบิกอุปกรณ์ใหม่จากวิศวกร!\n" .
                        
                            "📌 โครงการ: " . ($_POST['project_name'] ?? '-') . "\n" .
                            "👷 ผู้ขอ: " . ($_SESSION['username'] ?? 'วิศวกร') . "\n" .
                            "⏳ สถานะ: รอแอดมินตรวจสอบ";

            if (!empty($out_of_stock_list)) {
                $line_message .= "\n\n⚠️ **มีสินค้าหมด/ขาดสต็อก :**\n- " . implode("\n- ", $out_of_stock_list);
            }

            foreach ($admin_line_ids as$to_id) {
                if (empty($to_id)) continue;

                $line_data = [
                    'to' => $to_id,
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => $line_message
                        ]
                    ]
                ];

                $ch = curl_init('https://api.line.me/v2/bot/message/push');
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($line_data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $access_token
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
            // ==========================================

            $success_msg = "ส่งใบเบิกเรียบร้อยแล้ว!" . (!empty($out_of_stock_list) ? " (ส่งรายการสินค้าที่ขาดไปยังหน้าจัดซื้อเรียบร้อย)" : "");
        } elseif (isset($_POST['export_excel'])) {
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=Requisition_" . date('Ymd_His') . ".xls");
            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
            echo '<h2>ใบเบิกของ - SOLAR WING</h2><p><b>โครงการ:</b> ' . htmlspecialchars($_POST['project_name']) . '</p><table border="1"><tr style="background:#059669;color:#fff;"><th>#</th><th>สินค้า</th><th>จำนวน</th><th>หมายเหตุ</th></tr>';
            $no = 1;
            foreach ($_POST['item'] ?? [] as$it) {
                $qty = intval($it['qty'] ?? 0);
                if ($it['product_id'] > 0 && $qty > 0) {$p_name = '-';
                    foreach ($products as$p) if ($p['id'] ==$it['product_id']) $p_name =$p['name'] . ($p['sku'] ? " ({$p['sku']})" : '');
                    echo "<tr><td align='center'>" . $no++ . "</td><td>" . htmlspecialchars($p_name) . "</td><td align='center'>" . $qty . "</td><td>" . htmlspecialchars($it['remark']) . "</td></tr>";
                }
            }
            echo '</table></body></html>';
            exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction())$pdo->rollBack();
        $error_msg =$e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเบิกของ - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Prompt', 'sans-serif'] }, colors: { brandGreen: '#059669' } } } }</script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans h-screen flex overflow-hidden">

    <div id="sidebarWrapper" class="fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:static transition-transform bg-white border-r border-slate-200">
        <?php if (file_exists('sidebar_admin.php')) include 'sidebar_admin.php'; elseif (file_exists('sidebar_admin.php')) include 'sidebar_admin.php'; ?>
    </div>
    
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 backdrop-blur-md z-40 hidden md:hidden transition-all"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50 w-full">
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 shrink-0 z-10">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 p-2 rounded-xl bg-white border border-slate-200"><i class="fa-solid fa-bars"></i></button>
                <h2 class="text-base md:text-lg font-bold text-slate-900">สร้างใบเบิกของ (Requisition Form)</h2>
            </div>
            <a href="dashboard_admin.php" class="bg-white hover:bg-slate-50 px-4 py-2.5 rounded-xl text-xs font-semibold border border-slate-200 flex items-center gap-2"><i class="fa-solid fa-house text-emerald-600"></i> หน้าหลัก</a>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="max-w-6xl mx-auto bg-white rounded-2xl border border-slate-200 p-6 md:p-8 space-y-6">
                <?php if ($success_msg): ?><div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-3.5 rounded-xl text-xs font-bold"><i class="fa-solid fa-circle-check"></i> <?= $success_msg ?></div><?php endif; ?>
                <?php if ($error_msg): ?><div class="bg-rose-50 border border-rose-200 text-rose-800 p-3.5 rounded-xl text-xs font-bold"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <div class="flex gap-2">
                        <select id="templateSelect" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs bg-white font-medium">
                            <option value="">-- เลือก Template ฟอร์มสำเร็จรูป --</option>
                            <?php foreach ($templates as$t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['template_name']) ?></option><?php endforeach; ?>
                        </select>
                        <button type="button" onclick="loadTemplate()" class="bg-slate-900 hover:bg-slate-800 text-white px-4 py-2.5 rounded-xl text-xs font-bold shrink-0"><i class="fa-solid fa-bolt text-amber-400"></i> โหลด</button>
                    </div>
                    <div class="flex gap-2">
                        <input type="file" id="templateExcelFile" accept=".xlsx, .xls, .csv" class="hidden" onchange="importExcel(event)">
                        <input type="text" id="importTemplateName" placeholder="ตั้งชื่อ Template จาก Excel..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs bg-white font-medium">
                        <button type="button" onclick="if(document.getElementById('importTemplateName').value) document.getElementById('templateExcelFile').click(); else alert('กรุณาตั้งชื่อ Template');" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-xs font-bold shrink-0"><i class="fa-solid fa-file-import"></i> นำเข้า</button>
                    </div>
                </div>

                <form action="" method="POST" id="reqForm" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50 p-6 rounded-2xl border border-slate-200">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">โครงการ / หน้างาน <span class="text-rose-500">*</span></label>
                            <select name="installation_id" onchange="document.getElementById('project_name').value = this.options[this.selectedIndex].getAttribute('data-name') || ''" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs bg-white font-semibold">
                                <option value="">-- เลือกโครงการ --</option>
                                <?php foreach ($projects as$p): ?><option value="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
                            </select>
                            <input type="hidden" name="project_name" id="project_name">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">วันที่เบิก <span class="text-rose-500">*</span></label>
                            <input type="date" name="req_date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs bg-white font-semibold">
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <label class="text-xs font-bold text-slate-700 uppercase flex items-center gap-1.5"><i class="fa-solid fa-boxes-stacked text-brandGreen"></i> รายการสินค้าจาก Stock</label>
                            <button type="button" onclick="addRow()" class="bg-brandGreen hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold"><i class="fa-solid fa-plus"></i> เพิ่มแถว</button>
                        </div>

                        <div class="overflow-x-visible border border-slate-200 rounded-2xl bg-white">
                            <table class="w-full text-left text-xs min-w-[750px]">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase">
                                    <tr>
                                        <th class="py-3 px-4 text-center w-12">#</th>
                                        <th class="py-3 px-4">พิมพ์ค้นหา หรือเลือกสินค้าจากคลัง</th>
                                        <th class="py-3 px-4 text-center w-32">คงเหลือ</th>
                                        <th class="py-3 px-4 text-center w-28">จำนวนเบิก</th>
                                        <th class="py-3 px-4">หมายเหตุ</th>
                                        <th class="py-3 px-3 text-center w-16">ลบ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100" id="tableBody">
                                    <?php for ($i = 0; $i < 4; $i++): ?>
                                    <tr class="hover:bg-slate-50/50 row-item relative">
                                        <td class="py-3 px-4 text-center font-bold text-slate-400 row-no"><?= $i + 1 ?></td>
                                        <td class="py-3 px-4 relative">
                                            <div class="relative custom-dropdown">
                                                <input type="text" autocomplete="off" oninput="filterDropdown(this, <?= $i ?>)" onfocus="openDropdown(this)" placeholder="🔍 พิมพ์ค้นหาสินค้า..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:border-brandGreen font-medium prod-input">
                                                <div class="dropdown-list hidden absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl max-h-60 overflow-y-auto divide-y"></div>
                                                <input type="hidden" name="item[<?= $i ?>][product_id]" class="prod-id-input">
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-center"><input type="text" id="stock_text_<?= $i ?>" value="-" readonly class="w-full px-2 py-2 rounded-xl border border-slate-200 bg-slate-50 text-center font-bold text-emerald-600"><input type="hidden" id="stock_val_<?= $i ?>" value="0"></td>
                                        <td class="py-3 px-4 text-center"><input type="number" name="item[<?= $i ?>][qty]" min="0" value="0" class="w-full px-2.5 py-2 rounded-xl border border-slate-200 text-center font-bold"></td>
                                        <td class="py-3 px-4"><input type="text" name="item[<?= $i ?>][remark]" placeholder="หมายเหตุ..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200"></td>
                                        <td class="py-3 px-3 text-center"><button type="button" onclick="this.closest('tr').remove(); recalculate();" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center mx-auto"><i class="fa-solid fa-trash text-xs"></i></button></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex flex-col lg:flex-row justify-between gap-4 pt-4 border-t border-slate-200">
                        <div class="flex gap-2 w-full lg:w-auto">
                            <input type="text" id="newTemplateName" placeholder="ชื่อ Template..." class="px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs w-full sm:w-64">
                            <button type="button" onclick="saveTemplate()" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2.5 rounded-xl text-xs font-bold shrink-0"><i class="fa-solid fa-bookmark"></i> บันทึก Template</button>
                        </div>
                        <div class="flex gap-2.5 justify-end w-full lg:w-auto">
                            <button type="submit" name="export_excel" value="1" formtarget="_blank" class="bg-slate-700 hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl text-xs font-bold"><i class="fa-solid fa-file-excel text-emerald-400"></i> Excel</button>
                            <button type="submit" name="submit_to_admin" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-xl text-xs font-bold"><i class="fa-solid fa-paper-plane"></i> ส่ง Admin</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        const productsData = <?= json_encode($products) ?>;
        const toggleSidebar = () => {
            document.getElementById('sidebarWrapper').classList.toggle('-translate-x-full');
            document.getElementById('sidebarBackdrop').classList.toggle('hidden');
        };

        function openDropdown(input) {
            document.querySelectorAll('.dropdown-list').forEach(l => l.classList.add('hidden'));
            renderList(input.closest('.custom-dropdown').querySelector('.dropdown-list'), productsData, input);
        }

        function filterDropdown(input, idx) {
            const val = input.value.toLowerCase().trim();
            const filtered = productsData.filter(p => (p.name + (p.sku || '')).toLowerCase().includes(val));
            renderList(input.closest('.custom-dropdown').querySelector('.dropdown-list'), filtered, input, idx);
            if (!val) { 
                input.closest('.custom-dropdown').querySelector('.prod-id-input').value = ''; 
                document.getElementById('stock_text_' + idx).value = '-'; 
                document.getElementById('stock_val_' + idx).value = '0'; 
            }
        }

        function renderList(container, items, input, idx) {
            container.innerHTML = items.length ? '' : `<div class="p-3 text-center text-slate-400 text-xs">ไม่พบสินค้า</div>`;
            items.forEach(p => {
                const stockQty = Math.max(parseInt(p.stock_quantity) || 0, 0);
                const div = document.createElement('div');
                div.className = 'px-3.5 py-2 hover:bg-emerald-50 cursor-pointer flex justify-between text-xs items-center';
                div.innerHTML = `<div><div class="font-bold text-slate-800">${p.name}</div><div class="text-[10px] text-slate-400">SKU: ${p.sku || '-'}</div></div><span class="px-2 py-0.5 rounded-full font-bold text-[10px] ${stockQty > 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}">สต็อก: ${stockQty}</span>`;
                div.onclick = () => {
                    input.value = `${p.name} ${p.sku ? '(' + p.sku + ')' : ''}`;
                    const row = input.closest('tr');
                    const rowIndex = Array.from(row.parentNode.children).indexOf(row);
                    row.querySelector('.prod-id-input').value = p.id;
                    document.getElementById('stock_text_' + rowIndex).value = stockQty + ' ชิ้น';
                    document.getElementById('stock_val_' + rowIndex).value = stockQty;
                    
                    const stockInputText = document.getElementById('stock_text_' + rowIndex);
                    if (stockQty <= 0) {
                        stockInputText.className = 'w-full px-2 py-2 rounded-xl border border-rose-200 bg-rose-50 text-center font-bold text-rose-600';
                    } else {
                        stockInputText.className = 'w-full px-2 py-2 rounded-xl border border-slate-200 bg-slate-50 text-center font-bold text-emerald-600';
                    }

                    container.classList.add('hidden');
                };
                container.appendChild(div);
            });
            container.classList.remove('hidden');
        }

        document.addEventListener('click', e => { if (!e.target.closest('.custom-dropdown')) document.querySelectorAll('.dropdown-list').forEach(l => l.classList.add('hidden')); });

        function addRow(pId = '', qty = '0', remark = '') {
            const tbody = document.getElementById('tableBody');
            const idx = tbody.rows.length;
            const p = productsData.find(x => x.id == pId) || {};
            const stockQty = Math.max(parseInt(p.stock_quantity) || 0, 0);
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50/50 row-item relative';
            tr.innerHTML = `
                <td class="py-3 px-4 text-center font-bold text-slate-400 row-no">${idx + 1}</td>
                <td class="py-3 px-4 relative">
                    <div class="relative custom-dropdown">
                        <input type="text" autocomplete="off" value="${p.name ? p.name + (p.sku ? ' (' + p.sku + ')' : '') : ''}" oninput="filterDropdown(this, ${idx})" onfocus="openDropdown(this)" placeholder="🔍 ค้นหาสินค้า..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs bg-white font-medium prod-input">
                        <div class="dropdown-list hidden absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl max-h-60 overflow-y-auto divide-y"></div>
                        <input type="hidden" name="item[${idx}][product_id]" value="${pId}" class="prod-id-input">
                    </div>
                </td>
                <td class="py-3 px-4 text-center"><input type="text" id="stock_text_${idx}" value="${p.name ? stockQty + ' ชิ้น' : '-'}" readonly class="w-full px-2 py-2 rounded-xl border border-slate-200 bg-slate-50 text-center font-bold ${stockQty > 0 ? 'text-emerald-600' : 'text-rose-600 bg-rose-50'}"><input type="hidden" id="stock_val_${idx}" value="${stockQty}"></td>
                <td class="py-3 px-4 text-center"><input type="number" name="item[${idx}][qty]" min="0" value="${qty}" placeholder="0" class="w-full px-2.5 py-2 rounded-xl border border-slate-200 text-center font-bold"></td>
                <td class="py-3 px-4"><input type="text" name="item[${idx}][remark]" value="${remark}" placeholder="หมายเหตุ..." class="w-full px-3.5 py-2 rounded-xl border border-slate-200"></td>
                <td class="py-3 px-3 text-center"><button type="button" onclick="this.closest('tr').remove(); recalculate();" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center mx-auto"><i class="fa-solid fa-trash text-xs"></i></button></td>
            `;
            tbody.appendChild(tr);
            recalculate();
        }

        function recalculate() {
            document.querySelectorAll('#tableBody .row-item').forEach((row, i) => {
                row.querySelector('.row-no').innerText = i + 1;
                row.querySelector('.prod-id-input').name = `item[${i}][product_id]`;
                row.querySelector('input[id^="stock_text_"]').id = `stock_text_${i}`;
                row.querySelector('input[id^="stock_val_"]').id = `stock_val_${i}`;
                row.querySelector('.prod-input').setAttribute('oninput', `filterDropdown(this, ${i})`);
                row.querySelector('input[type="number"]').name = `item[${i}][qty]`;
                row.querySelector('input[type="text"][placeholder*="หมายเหตุ"]').name = `item[${i}][remark]`;
            });
        }

        function saveTemplate() {
            let name = document.getElementById('newTemplateName').value.trim();
            if (!name) return alert('กรุณาตั้งชื่อ Template');
            let form = document.getElementById('reqForm');
            [['save_as_template', '1'], ['template_name', name]].forEach(d => {
                let i = document.createElement('input'); i.type = 'hidden'; i.name = d[0]; i.value = d[1]; form.appendChild(i);
            });
            form.submit();
        }

        async function loadTemplate() {
            let id = document.getElementById('templateSelect').value;
            if (!id) return alert('กรุณาเลือก Template');
            let res = await fetch('get_template_items_admin.php?template_id=' + id).then(r => r.json());
            if (res.success && res.items) {
                document.getElementById('tableBody').innerHTML = '';
                res.items.forEach(it => addRow(it.product_id, it.quantity, it.remark));
                alert('โหลด Template สำเร็จ!');
            }
        }

        function importExcel(e) {
            let file = e.target.files[0];
            if (!file) return;
            let reader = new FileReader();
            reader.onload = function(evt) {
                let workbook = XLSX.read(new Uint8Array(evt.target.result), { type: 'array' });
                let rows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]], { header: 1 });
                let items = [];
                rows.forEach(r => {
                    let name = String(r[1] || '').trim().toLowerCase();
                    let qty = parseInt(r[3] || 0);
                    let rem = String(r[5] || '').trim();
                    if (name && qty > 0) {
                        let p = productsData.find(x => x.name.toLowerCase() === name || (x.sku && name.includes(x.sku.toLowerCase())));
                        if (p) items.push({ product_id: p.id, qty: qty, remark: rem });
                    }
                });
                if (items.length) {
                    let form = document.getElementById('reqForm');
                    [['import_as_template', '1'], ['template_name_import', document.getElementById('importTemplateName').value], ['imported_template_data', JSON.stringify(items)]].forEach(d => {
                        let i = document.createElement('input'); i.type = 'hidden'; i.name = d[0]; i.value = d[1]; form.appendChild(i);
                    });
                    form.submit();
                } else {
                    alert('ไม่พบสินค้าที่ตรงกันในไฟล์ Excel');
                }
            };
            reader.readAsArrayBuffer(file);
        }
    </script>
</body>
</html>