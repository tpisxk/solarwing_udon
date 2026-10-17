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
            // ดึงข้อมูลการสั่งซื้อและเชื่อมโยงหา installation_id จากตาราง requisitions
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

                // 2. บันทึกประวัติการรับเข้าลงตาราง stock_transactions (เพื่อให้สอดคล้องกับ ER Diagram และระบบสต็อกเดิม)
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

// ดึงข้อมูลจาก purchase_orders เชื่อมกับ products และ requisitions[cite: 2]
try {
    $sql = "SELECT po.id AS po_id, po.required_qty, po.status, po.created_at,
                   p.name AS product_name, p.sku, GREATEST(p.stock_quantity, 0) AS current_stock,
                   r.project_name, r.engineer_name
            FROM purchase_orders po
            JOIN products p ON po.product_id = p.id
            JOIN requisitions r ON po.requisition_id = r.id
            ORDER BY po.id DESC";
    $purchase_orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $purchase_orders = [];
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

                <?php if ($success_msg): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-2xl text-xs font-bold"><?= $success_msg ?></div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-2xl text-xs font-bold"><?= $error_msg ?></div>
                <?php endif; ?>

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
                                                    <form action="" method="POST" class="inline">
                                                        <input type="hidden" name="po_id" value="<?= $po['po_id'] ?>">
                                                        <input type="hidden" name="action_type" value="complete">
                                                        <button type="submit" onclick="return confirm('ยืนยันว่าสินค้ามาส่งแล้ว และต้องการเติมสต็อกเข้าคลัง?')" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-1.5 rounded-xl font-bold transition-all shadow-sm">
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
                </div>

            </div>
        </div>
    </main>
</body>
</html>