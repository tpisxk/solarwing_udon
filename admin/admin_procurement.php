<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once '../db/conn.php';
require_once '../auth_check.php';

$current_page = 'admin_procurement';
$success_msg = $error_msg = '';

// จัดการการเติมสินค้าเข้าสต็อก
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $po_id = intval($_POST['po_id']);
    $action = $_POST['action_type'];

    try {
        if ($action === 'complete') {
            $stmt_po = $pdo->prepare("
                SELECT po.product_id, po.required_qty, r.installation_id 
                FROM purchase_orders po
                JOIN requisitions r ON po.requisition_id = r.id
                WHERE po.id = ?
            ");
            $stmt_po->execute([$po_id]);
            $po_item = $stmt_po->fetch(PDO::FETCH_ASSOC);

            if ($po_item) {
                $pdo->beginTransaction();
                
                // 1. เพิ่มสต็อกสินค้าในตาราง products
                $stmt_add_stock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                $stmt_add_stock->execute([$po_item['required_qty'], $po_item['product_id']]);

                // 2. บันทึกประวัติการรับเข้าลงตาราง stock_transactions
                $stmt_trans = $pdo->prepare("
                    INSERT INTO stock_transactions (product_id, installation_id, type, quantity, unit_cost, reference_no, user_id, note, created_at) 
                    VALUES (?, ?, 'in', ?, 0.00, ?, ?, ?, NOW())
                ");
                $stmt_trans->execute([
                    $po_item['product_id'],
                    $po_item['installation_id'] ?? null,
                    $po_item['required_qty'],
                    'PO-' . $po_id,
                    $_SESSION['user_id'] ?? null,
                    'เติมสต็อกอัตโนมัติจากใบสั่งซื้อด่วน (Procurement)'
                ]);

                // 3. อัปเดตสถานะจัดซื้อใน purchase_orders เป็น Completed
                $stmt_update_po = $pdo->prepare("UPDATE purchase_orders SET status = 'Completed' WHERE id = ?");
                $stmt_update_po->execute([$po_id]);

                $pdo->commit();
                $success_msg = "เติมสต็อกสินค้าเข้าคลังและบันทึกประวัติสำเร็จ!";
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// ตั้งค่าระบบ Pagination และ Search
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
if ($limit <= 0) $limit = 5;

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page <= 0) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = "%{$search}%";

try {
    // นับจำนวนทั้งหมดตามเงื่อนไขค้นหา
    $count_sql = "SELECT COUNT(*) FROM purchase_orders po
                  JOIN products p ON po.product_id = p.id
                  JOIN requisitions r ON po.requisition_id = r.id
                  WHERE p.name LIKE ? OR p.sku LIKE ? OR r.project_name LIKE ? OR r.engineer_name LIKE ?";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute([$search_param, $search_param, $search_param, $search_param]);
    $total_records = $stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    if ($total_pages == 0) $total_pages = 1;
    if ($page > $total_pages) $page = $total_pages;

    // ดึงข้อมูลแบบจำกัดจำนวน (Limit & Offset)
    $sql = "SELECT po.id AS po_id, po.required_qty, po.status, po.created_at,
                   p.name AS product_name, p.sku, GREATEST(p.stock_quantity, 0) AS current_stock,
                   r.project_name, r.engineer_name
            FROM purchase_orders po
            JOIN products p ON po.product_id = p.id
            JOIN requisitions r ON po.requisition_id = r.id
            WHERE p.name LIKE ? OR p.sku LIKE ? OR r.project_name LIKE ? OR r.engineer_name LIKE ?
            ORDER BY po.id DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $search_param, PDO::PARAM_STR);
    $stmt->bindValue(2, $search_param, PDO::PARAM_STR);
    $stmt->bindValue(3, $search_param, PDO::PARAM_STR);
    $stmt->bindValue(4, $search_param, PDO::PARAM_STR);
    $stmt->bindValue(5, $limit, PDO::PARAM_INT);
    $stmt->bindValue(6, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $purchase_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $purchase_orders = [];
    $total_records = 0;
    $total_pages = 1;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการจัดซื้อสินค้า - Admin SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS & JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

    <!-- Sidebar -->
    <div id="sidebarWrapper" class="fixed md:static inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 transition-transform bg-white border-r border-slate-200 w-64 shrink-0 flex flex-col h-full">
        <?php if (file_exists('sidebar_admin.php')) include 'sidebar_admin.php'; ?>
    </div>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 shrink-0 shadow-sm">
            <h2 class="text-base md:text-xl font-bold text-slate-900"><i class="fa-solid fa-cart-shopping text-amber-500 me-2"></i> รายการสั่งซื้อสินค้าด่วน (สินค้าหมด/ขาดสต็อก)</h2>
        </header>

        <div class="flex-1 overflow-y-auto p-4 lg:p-8">
            <div class="max-w-6xl mx-auto space-y-6">

                <!-- แถบค้นหา และตัวเลือกจำนวนแถวต่อหน้า -->
                <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
                    <form method="GET" action="" class="flex items-center gap-2 w-full md:w-auto">
                        <input type="hidden" name="limit" value="<?= $limit ?>">
                        <div class="relative w-full md:w-80">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fa-solid fa-magnifying-glass text-xs"></i>
                            </span>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อสินค้า, SKU, โครงการ..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500 transition">
                        </div>
                        <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-xl text-xs font-bold transition shadow-sm shrink-0">ค้นหา</button>
                        <?php if (!empty($search)): ?>
                            <a href="admin_procurement.php?limit=<?= $limit ?>" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-3 py-2 rounded-xl text-xs font-bold transition shrink-0">ล้าง</a>
                        <?php endif; ?>
                    </form>

                    <div class="flex items-center gap-2 text-xs text-slate-600 shrink-0">
                        <span>แสดง:</span>
                        <select onchange="window.location.href='admin_procurement.php?limit='+this.value+'&search=<?= urlencode($search) ?>'" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold focus:outline-none focus:border-emerald-500">
                            <option value="5" <?= $limit == 5 ? 'selected' : '' ?>>5 แถว</option>
                            <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10 แถว</option>
                            <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25 แถว</option>
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 แถว</option>
                        </select>
                        <span>จากทั้งหมด <?= number_format($total_records) ?> รายการ</span>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs min-w-[700px]">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase">
                                    <th class="py-3.5 px-4">สินค้าที่ต้องสั่งซื้อ</th>
                                    <th class="py-3.5 px-4">อ้างอิงโครงการ / ผู้เบิก</th>
                                    <th class="py-3.5 px-4 text-center">คงเหลือปัจจุบัน</th>
                                    <th class="py-3.5 px-4 text-center">จำนวนที่ต้องซื้อเพิ่ม</th>
                                    <th class="py-3.5 px-4 text-center">สถานะ</th>
                                    <th class="py-3.5 px-4 text-center">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (!empty($purchase_orders)): ?>
                                    <?php foreach ($purchase_orders as $po): ?>
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="py-3.5 px-4">
                                                <div class="font-bold text-slate-900"><?= htmlspecialchars($po['product_name']) ?></div>
                                                <div class="text-[10px] text-slate-400">SKU: <?= htmlspecialchars($po['sku'] ?? '-') ?></div>
                                            </td>
                                            <td class="py-3.5 px-4">
                                                <div class="font-semibold text-slate-700"><?= htmlspecialchars($po['project_name']) ?></div>
                                                <div class="text-[10px] text-slate-500">โดย: <?= htmlspecialchars($po['engineer_name']) ?></div>
                                            </td>
                                            <td class="py-3.5 px-4 text-center font-bold <?= $po['current_stock'] <= 0 ? 'text-rose-500' : 'text-amber-500' ?>">
                                                <?= $po['current_stock'] ?> ชิ้น
                                            </td>
                                            <td class="py-3.5 px-4 text-center">
                                                <span class="bg-rose-100 text-rose-700 font-extrabold px-3 py-1 rounded-full">+<?= $po['required_qty'] ?> ชิ้น</span>
                                            </td>
                                            <td class="py-3.5 px-4 text-center">
                                                <?php if ($po['status'] === 'Pending'): ?>
                                                    <span class="bg-amber-100 text-amber-700 font-bold px-2.5 py-1 rounded-full">รอสั่งซื้อ / เติมของ</span>
                                                <?php else: ?>
                                                    <span class="bg-emerald-100 text-emerald-700 font-bold px-2.5 py-1 rounded-full">เติมสต็อกสำเร็จแล้ว</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3.5 px-4 text-center">
                                                <?php if ($po['status'] === 'Pending'): ?>
                                                    <form id="form-po-<?= $po['po_id'] ?>" action="admin_procurement.php?limit=<?= $limit ?>&search=<?= urlencode($search) ?>&page=<?= $page ?>" method="POST" class="inline">
                                                        <input type="hidden" name="po_id" value="<?= $po['po_id'] ?>">
                                                        <input type="hidden" name="action_type" value="complete">
                                                        <button type="button" onclick="confirmRestock(<?= $po['po_id'] ?>)" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-xl font-bold transition-all shadow-sm">
                                                            <i class="fa-solid fa-box-archive me-1"></i> เติมเข้าสต็อก
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-slate-400 font-semibold"><i class="fa-solid fa-check-double text-emerald-500"></i> เรียบร้อย</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-8 text-slate-400">ไม่มีรายการสินค้าหมดหรือขาดสต็อกที่ต้องสั่งซื้อ</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- ส่วนแสดงปุ่มเปลี่ยนหน้า (Pagination) -->
                    <?php if ($total_pages > 1): ?>
                        <div class="flex flex-col sm:flex-row justify-between items-center p-4 border-t border-slate-200 bg-slate-50 gap-3 text-xs">
                            <div class="text-slate-500 font-medium">
                                หน้าที่ <span class="font-bold text-slate-800"><?= $page ?></span> จากทั้งหมด <span class="font-bold text-slate-800"><?= $total_pages ?></span> หน้า
                            </div>
                            <div class="flex items-center gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="admin_procurement.php?limit=<?= $limit ?>&search=<?= urlencode($search) ?>&page=1" class="px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-100 transition">หน้าแรก</a>
                                    <a href="admin_procurement.php?limit=<?= $limit ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-100 transition"><i class="fa-solid fa-chevron-left"></i></a>
                                <?php endif; ?>

                                <?php
                                $start_p = max(1, $page - 2);
                                $end_p = min($total_pages, $page + 2);
                                for ($i = $start_p; $i <= $end_p; $i++):
                                ?>
                                    <a href="admin_procurement.php?limit=<?= $limit ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" class="px-3 py-1.5 rounded-xl font-bold transition <?= $i === $page ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="admin_procurement.php?limit=<?= $limit ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-100 transition"><i class="fa-solid fa-chevron-right"></i></a>
                                    <a href="admin_procurement.php?limit=<?= $limit ?>&search=<?= urlencode($search) ?>&page=<?= $total_pages ?>" class="px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-slate-700 font-bold hover:bg-slate-100 transition">หน้าสุดท้าย</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

            </div>
        </div>
    </main>

    <!-- Script สำหรับ SweetAlert2 -->
    <script>
        function confirmRestock(poId) {
            Swal.fire({
                title: '<span class="text-slate-800 text-lg font-bold">ยืนยันการเติมสต็อก?</span>',
                html: '<span class="text-slate-500 text-xs">ระบบจะทำการอัปเดตสถานะและเพิ่มจำนวนสินค้าเข้าคลังอัตโนมัติ</span>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'ยืนยัน, เติมเข้าสต็อก',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-3xl font-sans p-6 shadow-xl',
                    confirmButton: 'rounded-2xl py-2.5 font-bold text-xs shadow-md shadow-emerald-600/20 w-36',
                    cancelButton: 'rounded-2xl py-2.5 font-bold text-xs shadow-sm w-36',
                    icon: 'border-2 border-slate-300 text-slate-400'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('form-po-' + poId).submit();
                }
            });
        }

        // แสดงแจ้งเตือน SweetAlert2 เมื่อดำเนินการสำเร็จหรือเกิดข้อผิดพลาด
        <?php if (!empty($success_msg)): ?>
            Swal.fire({
                icon: 'success',
                title: '<span class="text-slate-800 text-base font-bold">สำเร็จ!</span>',
                html: '<span class="text-slate-500 text-xs"><?= $success_msg ?></span>',
                confirmButtonColor: '#059669',
                confirmButtonText: 'ตกลง',
                customClass: {
                    popup: 'rounded-3xl font-sans p-6',
                    confirmButton: 'rounded-2xl px-6 py-2.5 font-bold text-xs shadow-md shadow-emerald-600/20 w-36'
                }
            });
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            Swal.fire({
                icon: 'error',
                title: '<span class="text-slate-800 text-base font-bold">เกิดข้อผิดพลาด</span>',
                html: '<span class="text-slate-500 text-xs"><?= $error_msg ?></span>',
                confirmButtonColor: '#059669',
                confirmButtonText: 'ตกลง',
                customClass: {
                    popup: 'rounded-3xl font-sans p-6',
                    confirmButton: 'rounded-2xl px-6 py-2.5 font-bold text-xs shadow-md w-36'
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>