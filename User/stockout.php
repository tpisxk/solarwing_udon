<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'stockout';

$alert_status = '';
$alert_message = '';

// ฟังก์ชันส่ง LINE Push Message
function sendLineNotification($message, $access_token, $line_ids) {
    if (empty($line_ids) || !is_array($line_ids)) return;
    $url = 'https://api.line.me/v2/bot/message/push';
    foreach ($line_ids as $to_id) {
        if (empty($to_id) || strpos($to_id, 'ใส่_') !== false) continue;
        $data = ['to' => $to_id, 'messages' => [['type' => 'text', 'text' => $message]]];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
        curl_exec($ch);
        curl_close($ch);
    }
}

// 1. จัดการประมวลผลเมื่อกด Submit ฟอร์มในหน้าเดียวกัน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_requisition') {
    $product_id      = intval($_POST['product_id'] ?? 0);
    $quantity        = intval($_POST['quantity'] ?? 0);
    $installation_id = intval($_POST['installation_id'] ?? 0);
    $note            = trim($_POST['note'] ?? '');
    
    if ($product_id <= 0 || $quantity <= 0 ||$installation_id <= 0) {
        $alert_status = 'error';$alert_message = 'กรุณาเลือกสินค้า ระบุจำนวน และเลือกโครงการ/หน้างานให้ครบถ้วน';
    } else {
        try {
            $pdo->beginTransaction();

            // ดึงชื่อโครงการ
            $stmt_inst =$pdo->prepare("SELECT name FROM installations WHERE id = ?");
            $stmt_inst->execute([$installation_id]);
            $inst =$stmt_inst->fetch(PDO::FETCH_ASSOC);
            $project_name =$inst['name'] ?? 'ไม่ระบุโครงการ';

            // ดึงชื่อสินค้า
            $stmt_prod =$pdo->prepare("SELECT name FROM products WHERE id = ?");
            $stmt_prod->execute([$product_id]);
            $prod =$stmt_prod->fetch(PDO::FETCH_ASSOC);
            $product_name =$prod['name'] ?? 'สินค้าไม่ระบุชื่อ';

            // ดึงชื่อผู้ขอ
            $engineer_name = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'วิศวกร';

            // บันทึกหัวบิล requisitions (ผูก installation_id)
            $stmt_req =$pdo->prepare("
                INSERT INTO requisitions (installation_id, project_name, engineer_name, req_date, status, created_at) 
                VALUES (?, ?, ?, CURDATE(), 'Pending', NOW())
            ");
            $stmt_req->execute([$installation_id, $project_name,$engineer_name]);
            $requisition_id =$pdo->lastInsertId();

            // บันทึกรายการสินค้า requisition_items
            $stmt_item =$pdo->prepare("
                INSERT INTO requisition_items (requisition_id, product_id, quantity) 
                VALUES (?, ?, ?)
            ");
            $stmt_item->execute([$requisition_id, $product_id,$quantity]);

            $pdo->commit();

            // ส่ง LINE แจ้งเตือนแอดมิน
            $access_token = "pKse9FestSF5QdY9Hgnb7GtbcJqtXwucA50VBjLmWySwUbwM5JamQDMvlJflPQqTbibghoPRCGrGRnvehBxSooN0xjX6/3I4CF0nSeqbRgtKIBkE1mkxzPgmDKh+Io70jW7o548tAqX67bsJ3JQTBwdB04t89/1O/w1cDnyilFU=";
            $admin_line_ids = [
                "U61cff0977ce8c9de7bc8a5f949e44a43",
                "U0b88acf06842b3899ee341a0fce58ba1",
            ];

            $line_message = "🔔 มีคำขอเบิกสินค้าใหม่ (รอตรวจสอบ)!\n\n" .
                            "📌 ใบเบิก ID: #" . $requisition_id . "\n" .
                            "📦 สินค้า: " . $product_name . "\n" .
                            "🔢 จำนวน: " . number_format($quantity) . " ชิ้น\n" .
                            "🏗️ โครงการ: " . $project_name . "\n" .
                            "👷 ผู้ขอ: " . $engineer_name . "\n" .
                            (!empty($note) ? "💬 หมายเหตุ: " . $note . "\n" : "") .
                            "\n👉 กรุณาเข้าสู่ระบบเพื่อตรวจสอบและอนุมัติ";

            sendLineNotification($line_message, $access_token,$admin_line_ids);

            // $alert_status = 'success';$alert_message = '⏳ ส่งคำขอเบิกสินค้าและยิง LINE แจ้งเตือนแอดมินเรียบร้อยแล้ว อยู่ระหว่างรอตรวจสอบและอนุมัติ';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }$alert_status = 'error';
            $alert_message = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
}

// 2. ดึงรายการสินค้าสำหรับ Dropdown
try {
    $stmt_products =$pdo->query("SELECT id, name, stock_quantity FROM products ORDER BY name ASC");
    $products =$stmt_products->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$products = []; }

// 3. ดึงรายการโครงการ/หน้างาน
try {
    $stmt_installations =$pdo->query("SELECT id, name FROM installations ORDER BY id DESC");
    $installationsList =$stmt_installations->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$installationsList = []; }

// 4. ดึงประวัติการขอเบิกสินค้า
try {
    $stmt_history =$pdo->query("
        SELECT r.*, i.name AS project_name, 
               (SELECT GROUP_CONCAT(CONCAT(p.name, ' (', ri.quantity, ' ชิ้น)') SEPARATOR ', ') 
                FROM requisition_items ri 
                LEFT JOIN products p ON ri.product_id = p.id 
                WHERE ri.requisition_id = r.id) AS product_details
        FROM requisitions r
        LEFT JOIN installations i ON r.installation_id = i.id
        ORDER BY r.id DESC
        LIMIT 100
    ");
    $stockOutHistory =$stmt_history->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {$stockOutHistory = []; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งคำขอเบิกสินค้า - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tom Select CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

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
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
        .ts-control {
            border-radius: 1rem !important;
            padding: 0.75rem 1rem !important;
            border-color: #e2e8f0 !important;
            background-color: #f8fafc !important;
            font-size: 0.75rem !important;
            font-family: 'Prompt', sans-serif !important;
            font-weight: 500 !important;
        }
        .ts-wrapper.focus .ts-control {
            border-color: #059669 !important;
            box-shadow: 0 0 0 1px #059669 !important;
        }
        .ts-dropdown {
            border-radius: 1rem !important;
            overflow: hidden;
            font-size: 0.75rem !important;
            font-family: 'Prompt', sans-serif !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        }
        .ts-dropdown .option { padding: 0.6rem 1rem !important; }
        .ts-dropdown .active { background-color: #ecfdf5 !important; color: #047857 !important; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

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

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <header class="h-20 bg-white border-b border-slate-200/80 flex items-center justify-between px-6 md:px-10 shrink-0 z-10 shadow-xs">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">ส่งคำขอเบิกสินค้า (รอแอดมินตรวจสอบ)</h2>
                </div>
            </div>
            <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
            </a>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-10 space-y-6">
            
            <?php if (!empty($alert_status)): ?>
                <?php if ($alert_status == 'success'): ?>
                    <div class="bg-amber-50 border border-amber-200 text-amber-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span><?= $alert_message ?></span>
                        <a href="stockout.php" class="text-amber-700 font-bold hover:underline text-xs">ปิด</a>
                    </div>
                <?php else: ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span><?= $alert_message ?></span>
                        <a href="stockout.php" class="text-rose-700 font-bold hover:underline text-xs">ปิด</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
                <div>
                    <h3 class="text-base md:text-lg font-bold text-slate-900">ฟอร์มขอเบิกอุปกรณ์ / สินค้า</h3>
                    <p class="text-xs text-slate-500 mt-1">เลือกรายการสินค้า ระบุจำนวน และโครงการ/หน้างาน เพื่อส่งเรื่องให้แอดมินตรวจสอบก่อนตัดสต็อก</p>
                </div>

                <!-- action ว่างไว้เพื่อให้ส่งข้อมูลเข้าหน้าตัวเอง (stockout.php) -->
                <form action="stockout.php" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="hidden" name="action" value="submit_requisition">

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

                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-600">จำนวนที่ขอเบิก <span class="text-rose-500">*</span></label>
                        <input type="number" name="quantity" min="1" required placeholder="ระบุจำนวน..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

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

                    <div class="space-y-1.5 md:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600">หมายเหตุ</label>
                        <input type="text" name="note" placeholder="เช่น เบิกเพิ่มเติม, ช่าง A..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <div class="md:col-span-3 flex justify-end pt-2">
                        <button type="submit" class="w-full sm:w-auto bg-brandGreen hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-xs font-semibold shadow-md shadow-emerald-600/25 transition-all cursor-pointer flex items-center justify-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i> ส่งคำขอเบิกสินค้าให้แอดมิน
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col justify-between">
                <div class="p-6 pb-2 flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">ประวัติและสถานะการขอเบิกสินค้า</h3>
                    <span class="text-xs text-slate-400 font-medium" id="totalCountText">พบทั้งหมด <?= count($stockOutHistory) ?> รายการ</span>
                </div>
                
                <div class="overflow-x-auto px-6">
                    <table class="w-full text-left border-collapse text-xs min-w-[700px]">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider">
                                <th class="py-3.5 px-4 font-semibold">วันที่ / เวลา</th>
                                <th class="py-3.5 px-4 font-semibold">รายการสินค้า</th>
                                <th class="py-3.5 px-4 font-semibold">โครงการ / หน้างาน</th>
                                <th class="py-3.5 px-4 font-semibold text-center">สถานะการตรวจสอบ</th>
                                <th class="py-3.5 px-4 font-semibold">ผู้ขอเบิก</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700" id="stockoutTableBody">
                            <?php if (!empty($stockOutHistory)): ?>
                                <?php foreach ($stockOutHistory as$row): ?>
                                    <tr class="hover:bg-slate-50/80 transition-all stockout-row">
                                        <td class="py-3.5 px-4 text-slate-500"><?= htmlspecialchars($row['created_at']) ?></td>
                                        <td class="py-3.5 px-4 font-bold text-slate-900"><?= htmlspecialchars($row['product_details'] ?? 'ไม่พบสินค้า') ?></td>
                                        <td class="py-3.5 px-4 text-blue-600 font-semibold"><?= htmlspecialchars($row['project_name'] ?? 'ส่วนกลาง') ?></td>
                                        <td class="py-3.5 px-4 text-center">
                                            <?php 
                                                $st =$row['status'] ?? 'Pending';
                                                if ($st === 'Approved') { 
                                                    echo '<span class="px-2.5 py-1 rounded-full bg-blue-100 text-blue-700 font-bold">อนุมัติแล้ว (รอรับของ)</span>'; 
                                                } elseif ($st === 'Completed') {
                                                    echo '<span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold">รับของและตัดสต็อกแล้ว</span>';
                                                } elseif ($st === 'Rejected') {
                                                    echo '<span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 font-bold">ไม่อนุมัติ</span>';
                                                } else {
                                                    echo '<span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 font-bold">รอแอดมินตรวจสอบ</span>';
                                                }
                                            ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-600"><?= htmlspecialchars($row['engineer_name'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="noDataRow">
                                    <td colspan="5" class="py-12 text-center text-slate-400 font-normal">ยังไม่มีประวัติการขอเบิกสินค้า</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

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
                                <option value="100">100 แถว</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-1.5" id="paginationControls"></div>
                    </div>
                </div>
            </div>

        </div>
    </main>

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

            rows.forEach(row => row.style.display = 'none');

            let startIndex = (currentPage - 1) * rowsPerPage;
            let endIndex = startIndex + rowsPerPage;
            let currentRows = rows.slice(startIndex, endIndex);

            currentRows.forEach(row => row.style.display = '');

            let infoText = '';
            if (totalMatched === 0) {
                infoText = 'ไม่พบข้อมูลประวัติการขอเบิก';
            } else {
                infoText = `กำลังแสดง ${startIndex + 1} ถึง ${Math.min(endIndex, totalMatched)} จากทั้งหมด ${totalMatched} รายการ`;
            }
            document.getElementById('tableInfo').innerText = infoText;

            let paginationHtml = '';
            paginationHtml += `<button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-bold bg-white text-slate-600 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50 cursor-pointer"><i class="fa-solid fa-chevron-left"></i></button>`;

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

        document.addEventListener('DOMContentLoaded', function() {
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

            updateTablePagination();
        });
    </script>
</body>
</html>