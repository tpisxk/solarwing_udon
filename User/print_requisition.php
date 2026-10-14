<?php
session_start();
require_once '../db/conn.php';
require_once '../auth_check.php';

$req_id = intval($_GET['id'] ?? 0);
if ($req_id <= 0) {
    die('ไม่พบรหัสใบเบิกที่ต้องการพิมพ์');
}

// ดึงข้อมูลหัวใบเบิก
$stmt = $pdo->prepare("SELECT * FROM requisitions WHERE id = ?");
$stmt->execute([$req_id]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req) {
    die('ไม่พบข้อมูลใบเบิกดังกล่าว');
}

// ดึงรายการอุปกรณ์ในใบเบิก
$stmt_items = $pdo->prepare("
    SELECT ri.*, p.sku as code, p.name as product_name, 'ชิ้น' as unit 
    FROM requisition_items ri 
    JOIN products p ON ri.product_id = p.id 
    WHERE ri.requisition_id = ?
");
$stmt_items->execute([$req_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบเบิกวัสดุอุปกรณ์ #<?= $req_id ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; }
        @page {
            size: auto;
            margin: 10mm;
        }
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 p-6 flex flex-col items-center min-h-screen">

    <!-- ปุ่มสั่งพิมพ์ (ซ่อนอัตโนมัติเวลาสั่งปริ้นท์เป็น PDF) -->
    <div class="no-print mb-6 flex gap-3">
        <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-xl font-bold shadow transition flex items-center gap-2 cursor-pointer">
            <i class="fa-solid fa-print"></i> พิมพ์เอกสาร / บันทึกเป็น PDF
        </button>
        <button onclick="window.close()" class="bg-slate-300 hover:bg-slate-400 text-slate-800 px-5 py-2.5 rounded-xl font-bold transition cursor-pointer">
            ปิดหน้าต่าง
        </button>
    </div>

    <!-- ฟอร์มกระดาษ A4 -->
    <div class="bg-white w-[210mm] min-h-[297mm] p-12 shadow-xl rounded-2xl flex flex-col justify-between border border-slate-200">
        <div>
            <!-- หัวกระดาษ -->
            <div class="flex justify-between items-start border-b-2 border-slate-800 pb-6 mb-6">
                <div class="flex items-center gap-4">
                    <!-- โลโก้ -->
                    <img src="../Pic/456456.png" alt="Logo" class="w-14 h-14 object-contain" onerror="this.style.display='none'">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900">SOLARWING STOCK SYSTEM</h1>
                        <p class="text-xs text-slate-500 mt-1">ใบเบิกและจ่ายวัสดุก่อสร้าง / โครงการโซลาร์เซลล์</p>
                    </div>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-bold text-emerald-700">ใบเบิกวัสดุ (REQUISITION)</h2>
                    <!-- <p class="text-sm font-semibold text-slate-700 mt-1">เลขที่ใบเบิก: #<?= $req_id ?></p> -->
                    <p class="text-xs text-slate-500">วันที่ขอเบิก: <?= htmlspecialchars($req['req_date'] ?? date('Y-m-d')) ?></p>
                </div>
            </div>

            <!-- ข้อมูลรายละเอียดโครงการ -->
            <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 text-sm mb-6">
                <div>
                    <span class="text-slate-500 text-xs block">ชื่อโครงการ / หน้างาน:</span>
                    <strong class="text-slate-900 text-base"><?= htmlspecialchars($req['project_name'] ?? '-') ?></strong>
                </div>
                <div>
                    <span class="text-slate-500 text-xs block">วิศวกรผู้ขอเบิก:</span>
                    <strong class="text-slate-900 text-base"><?= htmlspecialchars($req['engineer_name'] ?? '-') ?></strong>
                </div>
                <div>
                    <span class="text-slate-500 text-xs block">สถานะใบเบิก:</span>
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold">
                        <?= htmlspecialchars($req['status'] ?? 'Approved') ?>
                    </span>
                </div>
            </div>

            <!-- ตารางรายการอุปกรณ์ -->
            <table class="w-full text-left border-collapse text-xs mb-8">
                <thead>
                    <tr class="bg-slate-800 text-white uppercase tracking-wider">
                        <th class="py-3 px-3 rounded-l-lg">ลำดับ</th>
                        <th class="py-3 px-3">รหัส / รายชื่อวัสดุ</th>
                        <th class="py-3 px-3 text-center">ขอเบิก</th>
                        <th class="py-3 px-3 text-center">จ่ายจริง</th>
                        <th class="py-3 px-3 text-center">รับคืน</th>
                        <th class="py-3 px-3 rounded-r-lg">หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 font-medium">
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $index => $item): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="py-3 px-3 text-slate-500"><?= $index + 1 ?></td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <div class="text-slate-400 text-[11px]"><?= htmlspecialchars($item['code']) ?></div>
                                </td>
                                <td class="py-3 px-3 text-center font-bold text-slate-700"><?= number_format($item['quantity'] ?? 0) ?> <?= $item['unit'] ?></td>
                                <td class="py-3 px-3 text-center font-bold text-emerald-700"><?= number_format($item['issued_qty'] ?? 0) ?> <?= $item['unit'] ?></td>
                                <td class="py-3 px-3 text-center font-bold text-amber-600"><?= number_format($item['returned_qty'] ?? 0) ?> <?= $item['unit'] ?></td>
                                <td class="py-3 px-3 text-slate-600"><?= htmlspecialchars($item['remark'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-400">ไม่พบรายการอุปกรณ์ในใบเบิกนี้</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ส่วนลายเซ็นท้ายกระดาษ -->
        <div class="grid grid-cols-3 gap-6 pt-12 border-t border-slate-200 text-center text-xs">
            <div class="space-y-12">
                <p class="font-bold text-slate-700">ลงชื่อ......................................................<br>(ผู้ขอเบิก / วิศวกรโครงการ)</p>
                <p class="text-slate-400">วันที่ _____ / _____ / _________</p>
            </div>
            <div class="space-y-12">
                <p class="font-bold text-slate-700">ลงชื่อ......................................................<br>(ผู้จ่ายสินค้า / เจ้าหน้าที่คลัง)</p>
                <p class="text-slate-400">วันที่ _____ / _____ / _________</p>
            </div>
            <div class="space-y-12">
                <p class="font-bold text-slate-700">ลงชื่อ......................................................<br>(ผู้ตรวจสอบ / ผู้รับสินค้าคืน)</p>
                <p class="text-slate-400">วันที่ _____ / _____ / _________</p>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>