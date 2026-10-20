<?php
require_once '../db/conn.php';

$req_id = intval($_GET['id'] ?? 0);

try {
    // ดึงข้อมูลรายการเบิกจากตาราง requisition_items JOIN กับ products โดยใช้คอลัมน์ stock_quantity
    $stmt = $pdo->prepare("
        SELECT ri.*, p.name, p.stock_quantity 
        FROM requisition_items ri 
        LEFT JOIN products p ON ri.product_id = p.id 
        WHERE ri.requisition_id = ?
    ");
    $stmt->execute([$req_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($items) {
        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full text-left border-collapse text-xs">';
        echo '<thead><tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase">';
        echo '<th class="py-2.5 px-3">รายการสินค้า</th>';
        echo '<th class="py-2.5 px-3 text-center">จำนวนที่เบิก</th>';
        echo '<th class="py-2.5 px-3 text-center">คงเหลือในคลัง</th>';
        echo '</tr></thead>';
        echo '<tbody class="divide-y divide-slate-100">';

        foreach ($items as $item) {
            $requested_qty = intval($item['qty'] ?? 0);
            $stock_qty = intval($item['stock_quantity'] ?? 0);
            
            // เช็คว่าสต็อกหมดหรือเหลือน้อยกว่าจำนวนที่ขอเบิกหรือไม่
            $is_out_of_stock = ($stock_qty < $requested_qty);
            $stock_badge = $is_out_of_stock 
                ? '<span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 font-bold inline-block"><i class="fa-solid fa-triangle-exclamation me-1"></i> ' . number_format($stock_qty) . ' (ของไม่พอ)</span>'
                : '<span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-bold inline-block">' . number_format($stock_qty) . '</span>';

            echo '<tr class="hover:bg-slate-50">';
            echo '<td class="py-2.5 px-3 font-semibold text-slate-900">' . htmlspecialchars($item['product_name'] ?? 'ไม่พบชื่อสินค้า') . '</td>';
            echo '<td class="py-2.5 px-3 text-center font-bold text-slate-700">' . number_format($requested_qty) . '</td>';
            echo '<td class="py-2.5 px-3 text-center">' . $stock_badge . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    } else {
        echo '<div class="text-center py-6 text-slate-400">ไม่พบรายการสินค้าในใบเบิกนี้</div>';
    }
} catch (PDOException $e) {
    echo '<div class="text-center py-6 text-rose-500">เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage() . '</div>';
}