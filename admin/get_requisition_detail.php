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
                                <th class="py-3 px-4 text-center">คงเหลือในคลัง</th>
                                <th class="py-3 px-4 text-center">จำนวนที่เบิก</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">';

            $index = 1;
            foreach ($items as $item) {
                $product_name = htmlspecialchars($item['product_name'] ?? 'ไม่พบชื่อสินค้า');
                $sku          = htmlspecialchars($item['sku'] ?? '-');
                $stock_qty    = intval($item['stock_quantity'] ?? 0);
                
                // รองรับทั้งคอลัมน์ qty และ quantity ในตาราง requisition_items
                $req_qty      = intval($item['qty'] ?? ($item['quantity'] ?? 0));

                echo '<tr>';
                echo '<td class="py-3.5 px-4 text-slate-500 font-bold">' . $index++ . '</td>';
                echo '<td class="py-3.5 px-4 font-bold text-slate-900">' . $product_name . '<br><span class="text-[10px] text-slate-400 font-normal">SKU: ' . $sku . '</span></td>';
                
                // แสดงสต็อกคงเหลือ พร้อมเตือนหากสินค้าไม่พอ
                echo '<td class="py-3.5 px-4 text-center">';
                if ($stock_qty < $req_qty) {
                    echo '<span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 font-bold">' . number_format($stock_qty) . ' (ของไม่พอ)</span>';
                } else {
                    echo '<span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold">' . number_format($stock_qty) . '</span>';
                }
                echo '</td>';

                // จำนวนที่เบิก
                echo '<td class="py-3.5 px-4 text-center font-bold text-blue-600 text-sm">' . number_format($req_qty) . ' ชิ้น</td>';
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