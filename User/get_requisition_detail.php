<?php
require_once '../db/conn.php';
require_once '../auth_check.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="text-center py-4 text-rose-500">ไม่พบรหัสใบเบิก</div>';
    exit;
}

try {
    // ดึงรายการสินค้าโดยเชื่อมโยงกับตาราง products และ requisition_items (ไม่มีเครื่องหมายขีดนำหน้าแล้ว)
    $stmt = $pdo->prepare("
        SELECT ri.*, p.name as prod_name, p.sku, p.category 
        FROM requisition_items ri 
        LEFT JOIN products p ON ri.product_id = p.id 
        WHERE ri.requisition_id = ?
    ");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($items)) {
        echo '<div class="overflow-x-auto border border-slate-200 rounded-2xl">';
        echo '<table class="w-full text-left border-collapse text-xs">';
        echo '<thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase">';
        echo '<tr><th class="py-2.5 px-3 text-center">ลำดับ</th><th class="py-2.5 px-3">รายการสินค้า / SKU</th><th class="py-2.5 px-3 text-center">จำนวนเบิก</th><th class="py-2.5 px-3">หมายเหตุ</th></tr>';
        echo '</thead>';
        echo '<tbody class="divide-y divide-slate-100">';
        
        $no = 1;
        foreach ($items as $item) {
            $name = $item['prod_name'] ?? 'สินค้าทั่วไป';
            $sku = !empty($item['sku']) ? ' (SKU: ' . $item['sku'] . ')' : '';
            echo '<tr>';
            echo '<td class="py-2.5 px-3 text-center font-bold text-slate-500">' . $no++ . '</td>';
            echo '<td class="py-2.5 px-3 font-semibold text-slate-900">' . htmlspecialchars($name . $sku) . '</td>';
            echo '<td class="py-2.5 px-3 text-center font-bold text-emerald-600">' . $item['quantity'] . ' ชิ้น</td>';
            echo '<td class="py-2.5 px-3 text-slate-600">' . htmlspecialchars($item['remark'] ?? '-') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table></div>';
    } else {
        echo '<div class="text-center py-6 text-slate-400">ไม่พบรายการสินค้าในใบเบิกนี้</div>';
    }
} catch (Exception $e) {
    echo '<div class="text-center py-4 text-rose-500">เกิดข้อผิดพลาด: ' . $e->getMessage() . '</div>';
}
?>