<?php
require_once '../db/conn.php';
require_once '../auth_check.php';

$req_id = intval($_GET['id'] ?? 0);

if ($req_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT ri.*, p.name AS product_name, p.sku, p.stock_quantity 
            FROM requisition_items ri
            LEFT JOIN products p ON ri.product_id = p.id
            WHERE ri.requisition_id = ?
        ");
        $stmt->execute([$req_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($items)) {
            echo '<div class="overflow-x-auto border border-slate-200 rounded-2xl">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase">
                                <th class="py-3 px-4">ลำดับ</th>
                                <th class="py-3 px-4">รายการสินค้า / SKU</th>
                                <th class="py-3 px-4 text-center">คงเหลือ</th>
                                <th class="py-3 px-4 text-center">จำนวนเบิก</th>
                                <th class="py-3 px-4 text-center">จ่ายออก</th>
                                <th class="py-3 px-4 text-center">รับเข้า</th>
                                <th class="py-3 px-4">หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">';

            $index = 1;
            foreach ($items as $item) {
                $item_id      = intval($item['id']);
                $product_id   = intval($item['product_id']);
                $product_name = htmlspecialchars($item['product_name'] ?? 'ไม่พบสินค้า');
                $sku          = htmlspecialchars($item['sku'] ?? '-');
                $stock_qty    = intval($item['stock_quantity'] ?? 0);
                $req_qty      = intval($item['quantity'] ?? 0);
                $issued_qty   = intval($item['issued_qty'] ?? 0);
                $returned_qty = intval($item['returned_qty'] ?? 0);
                $remark       = htmlspecialchars($item['remark'] ?? '');

                echo '<tr>';
                echo '<input type="hidden" name="item_id[]" value="' . $item_id . '">';
                echo '<input type="hidden" name="product_id[]" value="' . $product_id . '">';
                
                echo '<td class="py-3.5 px-4 text-slate-500 font-bold">' . $index++ . '</td>';
                echo '<td class="py-3.5 px-4 font-bold text-slate-900">' . $product_name . '<br><span class="text-[10px] text-slate-400 font-normal">SKU: ' . $sku . '</span></td>';
                echo '<td class="py-3.5 px-4 text-center font-semibold text-slate-600">' . number_format($stock_qty) . '</td>';
                echo '<td class="py-3.5 px-4 text-center font-bold text-blue-600">' . number_format($req_qty) . ' ชิ้น</td>';
                
                // ช่องกรอกจำนวนจ่ายออก
                echo '<td class="py-3.5 px-4 text-center">
                        <input type="number" name="issued_qty[]" min="0" value="' . $issued_qty . '" class="w-20 px-3 py-1.5 border border-slate-200 rounded-xl text-xs text-center bg-slate-50 font-bold focus:outline-none focus:border-emerald-600">
                      </td>';

                // ช่องกรอกจำนวนรับคืน
                echo '<td class="py-3.5 px-4 text-center">
                        <input type="number" name="returned_qty[]" min="0" value="' . $returned_qty . '" class="w-20 px-3 py-1.5 border border-slate-200 rounded-xl text-xs text-center bg-slate-50 font-bold focus:outline-none focus:border-emerald-600">
                      </td>';

                // ช่องหมายเหตุรายชิ้น
                echo '<td class="py-3.5 px-4">
                        <input type="text" name="item_remark[]" value="' . $remark . '" placeholder="ระบุหมายเหตุ..." class="w-full px-3 py-1.5 border border-slate-200 rounded-xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600">
                      </td>';

                echo '</tr>';
            }

            echo '    </tbody>
                    </table>
                  </div>';
        } else {
            echo '<div class="text-center py-8 text-slate-400">ไม่พบรายการสินค้าในใบเบิกนี้</div>';
        }
    } catch (Exception $e) {
        echo '<div class="text-center py-8 text-rose-500">เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage() . '</div>';
    }
} else {
    echo '<div class="text-center py-8 text-slate-400">ไม่พบรหัสใบเบิก</div>';
}