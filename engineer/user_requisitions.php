<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

// Safe Column Additions สำหรับตารางที่เกี่ยวข้อง
try {
    $pdo->exec("ALTER TABLE requisition_items ADD COLUMN issued_qty INT DEFAULT 0");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE requisition_items ADD COLUMN returned_qty INT DEFAULT 0");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE requisition_items ADD COLUMN remark TEXT DEFAULT NULL");
} catch (PDOException $e) {}

try {
    $pdo->exec("ALTER TABLE stock_transactions ADD COLUMN unit_cost DECIMAL(10,2) DEFAULT 0.00");
} catch (PDOException $e) {}

$current_page = 'user_requisitions';

// =========================================================================
// 1. ประมวลผล AJAX (จ่ายสินค้า / รับคืนสินค้า / ตัด-คืนสต็อก + ล็อกราคา Snapshot)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action         = $_POST['action']; 
    $req_id         = intval($_POST['requisition_id'] ?? 0);
    $issued_qtys    = $_POST['issued_qty'] ?? [];
    $returned_qtys  = $_POST['returned_qty'] ?? [];
    $item_ids       = $_POST['item_id'] ?? [];
    $product_ids    = $_POST['product_id'] ?? [];
    $item_remarks   = $_POST['item_remark'] ?? [];

    if ($req_id > 0 && !empty($item_ids)) {
        try {
            $pdo->beginTransaction();

            $stmt_get_req = $pdo->prepare("SELECT installation_id FROM requisitions WHERE id = ?");
            $stmt_get_req->execute([$req_id]);
            $req_info = $stmt_get_req->fetch(PDO::FETCH_ASSOC);
            
            $installation_id = intval($req_info['installation_id'] ?? 0);
            $user_id = $_SESSION['user_id'] ?? ($_SESSION['id'] ?? 1);

            $stmt_get_old_item = $pdo->prepare("SELECT product_id, issued_qty, returned_qty FROM requisition_items WHERE id = ?");
            $stmt_update_item  = $pdo->prepare("UPDATE requisition_items SET issued_qty = ?, returned_qty = ?, remark = ? WHERE id = ?");
            $stmt_get_product  = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");
            $stmt_deduct_stock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $stmt_add_stock    = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            
            $stmt_trans = $pdo->prepare("INSERT INTO stock_transactions (product_id, installation_id, type, quantity, unit_cost, user_id, reference_no, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

            foreach ($item_ids as $index => $item_id) {
                $item_id      = intval($item_id);
                $p_id         = intval($product_ids[$index] ?? 0);
                $new_issued   = intval($issued_qtys[$index] ?? 0);
                $new_returned = intval($returned_qtys[$index] ?? 0);
                $remark       = trim($item_remarks[$index] ?? '');

                $stmt_get_old_item->execute([$item_id]);
                $old_item = $stmt_get_old_item->fetch(PDO::FETCH_ASSOC);
                
                if ($old_item) {
                    if ($p_id <= 0) {
                        $p_id = intval($old_item['product_id'] ?? 0);
                    }
                    $old_issued   = intval($old_item['issued_qty'] ?? 0);
                    $old_returned = intval($old_item['returned_qty'] ?? 0);
                } else {
                    $old_issued = 0;
                    $old_returned = 0;
                }

                $diff_issued   = $new_issued - $old_issued;
                $diff_returned = $new_returned - $old_returned;

                $current_cost_price = 0.00;
                if ($p_id > 0) {
                    $stmt_get_product->execute([$p_id]);
                    $prod_data = $stmt_get_product->fetch(PDO::FETCH_ASSOC);
                    $current_cost_price = floatval($prod_data['cost_price'] ?? 0.00);
                }

                $stmt_update_item->execute([$new_issued, $new_returned, $remark, $item_id]);

                if ($p_id > 0 && $diff_issued > 0) {
                    $stmt_deduct_stock->execute([$diff_issued, $p_id]);
                    $stmt_trans->execute([$p_id, $installation_id, 'out', $diff_issued, $current_cost_price, $user_id, 'REQ-#' . $req_id]);
                }

                if ($p_id > 0 && $diff_returned > 0) {
                    $stmt_add_stock->execute([$diff_returned, $p_id]);
                    $stmt_trans->execute([$p_id, $installation_id, 'return', $diff_returned, $current_cost_price, $user_id, 'RET-#' . $req_id]);
                }
            }

            if ($action === 'complete_requisition') {
                $stmt_status = $pdo->prepare("UPDATE requisitions SET status = 'Completed' WHERE id = ?");
                $stmt_status->execute([$req_id]);
                $msg = 'รับคืนสินค้า คืนสต็อกเข้าคลังเรียบร้อยแล้ว และเสร็จสิ้น Process ใบเบิกนี้!';
            } else {
                $msg = 'บันทึกการจ่ายสินค้าด้วยราคาต้นทุนปัจจุบันเรียบร้อยแล้ว';
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => $msg, 'action' => $action]);
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
            exit();
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน กรุณาตรวจสอบอีกครั้ง']);
    exit();
}

// =========================================================================
// 2. ดึงข้อมูลใบเบิกที่ได้รับการอนุมัติ (Status = Approved)
// =========================================================================
try {
    $stmt = $pdo->prepare("SELECT * FROM requisitions WHERE status = 'Approved' ORDER BY id DESC");
    $stmt->execute();
    $requisitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $requisitions = [];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการใบเบิกที่อนุมัติแล้ว - SolarStock </title>
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
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
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
    <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden backdrop-blur-xs"></div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 md:px-10 shrink-0 shadow-xs z-10">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">รายการใบเบิกที่อนุมัติแล้ว</h2>
                    <!-- <p class="text-xs text-slate-500 hidden sm:block mt-0.5">จัดการจ่ายสินค้า รับคืน และตัด-คืนสต็อกสินค้าอัตโนมัติพร้อมบันทึกประวัติราคา</p> -->
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-house text-emerald-600"></i> <span class="hidden sm:inline">หน้าหลัก</span>
                </a>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="max-w-6xl mx-auto space-y-6">
                <div id="alertBox" class="hidden px-5 py-4 rounded-2xl text-sm font-medium items-center gap-3 shadow-xs"></div>

                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs min-w-[650px]">
                            <thead>
                                <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                                    <th class="py-4 px-5">ชื่อโครงการ / หน้างาน</th>
                                    <th class="py-4 px-5">วันที่เบิก</th>
                                    <th class="py-4 px-5">วิศวกรผู้ขอ</th>
                                    <th class="py-4 px-5 text-center">สถานะ</th>
                                    <th class="py-4 px-5 text-center">จัดการ / จ่ายของ & รับคืน</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium">
                                <?php if (!empty($requisitions)): ?>
                                    <?php foreach ($requisitions as $req): ?>
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="py-4 px-5 font-bold text-slate-900"><?= htmlspecialchars($req['project_name'] ?? '-') ?></td>
                                            <td class="py-4 px-5 text-slate-600"><?= htmlspecialchars($req['req_date'] ?? '-') ?></td>
                                            <td class="py-4 px-5 text-slate-700"><?= htmlspecialchars($req['engineer_name'] ?? '-') ?></td>
                                            <td class="py-4 px-5 text-center">
                                                <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold border border-emerald-200">อนุมัติแล้ว</span>
                                            </td>
                                            <td class="py-4 px-5 text-center">
                                                <button type="button" onclick="openDetailModal(<?= $req['id'] ?>, '<?= htmlspecialchars($req['project_name']) ?>')" class="bg-brandGreen hover:bg-emerald-700 text-white px-4 py-2.5 rounded-2xl font-bold transition-all shadow-sm flex items-center gap-2 mx-auto cursor-pointer">
                                                    <i class="fa-solid fa-boxes-packing text-xs"></i> จัดการจ่าย/รับคืน
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-12 text-slate-400 font-normal">ยังไม่มีรายการใบเบิกที่รอการจัดการในขณะนี้</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal สำหรับจัดการจ่ายสินค้า รับคืน และตัด/คืนสต็อก -->
    <div id="detailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 md:p-8 space-y-5 shadow-2xl max-h-[92vh] overflow-y-auto">
            <div class="flex justify-between items-center border-b border-slate-100 pb-4">
                <div>
                    <h3 class="font-bold text-slate-900 text-base md:text-lg"><i class="fa-solid fa-boxes-packing text-emerald-600 me-2"></i> บันทึกการจ่ายและรับคืนสินค้า ใบเบิก #<span id="modalReqId"></span></h3>
                    <p class="text-xs text-slate-500 font-medium" id="modalProjectName">-</p>
                </div>
                <button type="button" onclick="closeDetailModal()" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="requisitionForm">
                <input type="hidden" name="action" id="formAction" value="save_issue">
                <input type="hidden" name="requisition_id" id="formReqId">

                <div id="modalContent" class="space-y-3 min-h-[160px]">
                    <div class="text-center py-10 text-slate-400">กำลังโหลดรายการอุปกรณ์...</div>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-center gap-2.5 pt-5 border-t border-slate-100">
                    <!-- ปุ่มพิมพ์ PDF เอกสาร -->
                    <button type="button" onclick="printRequisition()" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-3 rounded-2xl font-bold text-xs transition-all flex items-center justify-center gap-2 cursor-pointer w-full sm:w-auto">
                        <i class="fa-solid fa-print"></i> พิมพ์ใบเบิก (PDF)
                    </button>

                    <div class="flex flex-col sm:flex-row gap-2.5 w-full sm:w-auto">
                        <button type="button" onclick="closeDetailModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-3 rounded-2xl font-bold text-xs transition-all">
                            ปิดหน้าต่าง
                        </button>
                        <button type="button" onclick="submitForm('save_issue')" class="bg-amber-600 hover:bg-amber-700 text-white px-5 py-3 rounded-2xl font-bold text-xs transition-all shadow-md shadow-amber-600/20 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-floppy-disk"></i> ยืนยันจ่ายของ / ตัดสต็อก
                        </button>
                        <button type="button" onclick="submitForm('complete_requisition')" class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl font-bold text-xs transition-all shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-check-double"></i> ยืนยันรับคืน & จบ Process
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentReqId = null;

        function toggleSidebar() {
            document.getElementById('sidebarWrapper').classList.toggle('-translate-x-full');
            document.getElementById('sidebarBackdrop').classList.toggle('hidden');
        }

        function openDetailModal(reqId, projectName) {
            currentReqId = reqId;
            document.getElementById('modalReqId').innerText = reqId;
            document.getElementById('formReqId').value = reqId;
            document.getElementById('modalProjectName').innerText = 'โครงการ: ' + projectName;
            document.getElementById('detailModal').classList.remove('hidden');
            document.getElementById('detailModal').classList.add('flex');
            loadModalData(reqId);
        }

        function loadModalData(reqId) {
            document.getElementById('modalContent').innerHTML = '<div class="text-center py-10 text-slate-400"><i class="fa-solid fa-spinner fa-spin me-2"></i>กำลังโหลดรายการอุปกรณ์...</div>';
            fetch('get_requisition_detail.php?id=' + reqId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('modalContent').innerHTML = '<div class="text-center py-10 text-rose-500">เกิดข้อผิดพลาดในการดึงข้อมูลอุปกรณ์</div>';
                });
        }

        function printRequisition() {
            if (!currentReqId) return;
            window.open('print_requisition.php?id=' + currentReqId, '_blank');
        }

        function submitForm(actionType) {
            document.getElementById('formAction').value = actionType;
            const form = document.getElementById('requisitionForm');
            const formData = new FormData(form);

            fetch('user_requisitions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const alertBox = document.getElementById('alertBox');
                alertBox.classList.remove('hidden');
                
                if (data.status === 'success') {
                    alertBox.className = 'px-5 py-4 rounded-2xl text-sm font-medium flex items-center gap-3 shadow-xs bg-emerald-50 text-emerald-800 border border-emerald-200';
                    alertBox.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i><span>' + data.message + '</span>';

                    if (actionType === 'complete_requisition') {
                        setTimeout(() => {
                            closeDetailModal();
                        }, 800);
                    } else {
                        loadModalData(currentReqId);
                    }
                } else {
                    alertBox.className = 'px-5 py-4 rounded-2xl text-sm font-medium flex items-center gap-3 shadow-xs bg-rose-50 text-rose-800 border border-rose-200';
                    alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-rose-600 text-lg"></i><span>' + data.message + '</span>';
                }
                setTimeout(() => alertBox.classList.add('hidden'), 4000);
            })
            .catch(error => {
                alert('เกิดข้อผิดพลาดในการส่งข้อมูลไปยังเครื่องแม่ข่าย');
            });
        }

        function closeDetailModal() {
            document.getElementById('detailModal').classList.remove('flex');
            document.getElementById('detailModal').classList.add('hidden');
            location.reload();
        }
    </script>
</body>
</html>