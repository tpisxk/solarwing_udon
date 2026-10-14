<?php
require_once '../db/conn.php';
require_once '../auth_check.php';

$req_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($req_id <= 0) {
    echo '<div class="text-center py-6 text-rose-500">ไม่พบรหัสใบเบิก</div>';
    exit;
}

try {
    // ใช้ stock_quantity ตามโครงสร้างตารางจริง
    $stmt = $pdo->prepare("SELECT ri.*, p.name as product_name, p.sku as product_sku, p.stock_quantity FROM requisition_items ri LEFT JOIN products p ON ri.product_id = p.id WHERE ri.requisition_id = ?");
    $stmt->execute([$req_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $items = [];
}
?>

<?php if (empty($items)): ?>
    <div class="text-center py-6 text-slate-400">ไม่พบรายการสินค้าในใบเบิกนี้</div>
<?php else: ?>
    <div class="overflow-x-auto rounded-2xl border border-slate-200">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase font-bold">
                    <th class="py-3 px-3 text-center w-12">ลำดับ</th>
                    <th class="py-3 px-4">รายการสินค้า / SKU</th>
                    <th class="py-3 px-3 text-center">คงเหลือ</th>
                    <th class="py-3 px-3 text-center w-24">จำนวนเบิก</th>
                    <th class="py-3 px-3 text-center w-28 text-blue-700">จ่ายออก</th>
                    <th class="py-3 px-3 text-center w-28 text-emerald-700">รับเข้า</th>
                    <th class="py-3 px-4">หมายเหตุ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($items as $index => $item): 
                    $p_name = htmlspecialchars($item['product_name'] ?? 'สินค้าไม่ระบุชื่อ');
                    $p_sku = !empty($item['product_sku']) ? ' (SKU: ' . htmlspecialchars($item['product_sku']) . ')' : '';
                    $req_qty = intval($item['quantity'] ?? 0);
                    
                    // กำหนดให้ค่าเริ่มต้นของช่องจ่ายออก (issued_qty) เท่ากับจำนวนที่ขอเบิก ($req_qty) 
                    // หากเคยบันทึกค่าไว้แล้วและมากกว่า 0 ให้ใช้ค่านั้น แต่ถ้ายังไม่เคยบันทึก (เป็น 0 หรือค่าว่าง) ให้ยึดตามยอดเบิก
                    $saved_issued = intval($item['issued_qty'] ?? 0);
                    $issued_qty = ($saved_issued > 0) ? $saved_issued : $req_qty;

                    $returned_qty = isset($item['returned_qty']) ? intval($item['returned_qty']) : 0;
                ?>
                <tr>
                    <td class="py-3 px-3 text-center font-bold text-slate-400"><?= $index + 1 ?></td>
                    <td class="py-3 px-4 font-semibold text-slate-800">
                        <?= $p_name . $p_sku ?>
                        <input type="hidden" name="item_id[]" value="<?= $item['id'] ?>">
                        <input type="hidden" name="product_id[]" value="<?= $item['product_id'] ?? 0 ?>">
                    </td>
                    <td class="py-3 px-3 text-center font-bold text-slate-600">
                        <?= intval($item['stock_quantity'] ?? 0) ?>
                    </td>
                    <td class="py-3 px-3 text-center font-bold text-slate-700">
                        <?= $req_qty ?> ชิ้น
                    </td>
                    <td class="py-3 px-3 text-center">
                        <!-- ล็อกไม่ให้กรอกเกินจำนวนเบิก ($req_qty) และเริ่มต้นเท่ากับจำนวนเบิก -->
                        <input type="number" name="issued_qty[]" min="0" max="<?= $req_qty ?>" value="<?= $issued_qty ?>" 
                               oninput="if(parseInt(this.value) > <?= $req_qty ?>) this.value = <?= $req_qty ?>;"
                               class="w-24 px-2.5 py-1.5 text-center rounded-xl border border-blue-300 bg-blue-50/50 font-bold text-blue-700 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 shadow-2xs">
                    </td>
                    <td class="py-3 px-3 text-center">
                        <input type="number" name="returned_qty[]" min="0" value="<?= $returned_qty ?>" class="w-24 px-2.5 py-1.5 text-center rounded-xl border border-emerald-300 bg-emerald-50/50 font-bold text-emerald-700 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 shadow-2xs">
                    </td>
                    <td class="py-3 px-4 text-slate-500 text-xs">
                        <input type="text" name="item_remark[]" value="<?= htmlspecialchars($item['remark'] ?? '') ?>" placeholder="ระบุหมายเหตุ..." class="w-full px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50/50 font-normal focus:outline-none">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>