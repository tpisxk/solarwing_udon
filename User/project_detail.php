<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$current_page = 'expenses_summary';
$proj_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($proj_id <= 0) {
    header("Location: expenses_summary.php");
    exit();
}

// 1. ดึงข้อมูลโครงการ
try {
    $stmt = $pdo->prepare("SELECT * FROM installations WHERE id = ?");
    $stmt->execute([$proj_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        header("Location: expenses_summary.php");
        exit();
    }

    // 2. ดึงรายการรายรับของโครงการนี้
    $stmt_inc = $pdo->prepare("SELECT * FROM incomes WHERE installation_id = ? ORDER BY id DESC");
    $stmt_inc->execute([$proj_id]);
    $incomes = $stmt_inc->fetchAll(PDO::FETCH_ASSOC);
    $total_income = 0;
    foreach ($incomes as $inc) { 
        $total_income += floatval($inc['amount']); 
    }

    // 3. ดึงรายการเบิกวัสดุอุปกรณ์ (out)
    $stmt_out = $pdo->prepare("
        SELECT s.*, p.name AS product_name, p.sku, 
               COALESCE(NULLIF(s.unit_cost, 0), NULLIF(p.cost_price, 0), 0) AS cost_price 
        FROM stock_transactions s
        JOIN products p ON s.product_id = p.id
        WHERE s.installation_id = ? AND LOWER(s.type) IN ('out', 'dispense', 'issue')
        ORDER BY s.id DESC
    ");
    $stmt_out->execute([$proj_id]);
    $dispensed_items = $stmt_out->fetchAll(PDO::FETCH_ASSOC);
    $out_cost = 0;
    foreach ($dispensed_items as $item) { 
        $out_cost += (floatval($item['quantity']) * floatval($item['cost_price'])); 
    }

    // 4. ดึงรายการรับคืนวัสดุอุปกรณ์ (in, return)
    $stmt_in = $pdo->prepare("
        SELECT s.*, p.name AS product_name, p.sku, 
               COALESCE(NULLIF(s.unit_cost, 0), NULLIF(p.cost_price, 0), 0) AS cost_price 
        FROM stock_transactions s
        JOIN products p ON s.product_id = p.id
        WHERE s.installation_id = ? AND LOWER(s.type) IN ('in', 'return', 'receive')
        ORDER BY s.id DESC
    ");
    $stmt_in->execute([$proj_id]);
    $received_in_items = $stmt_in->fetchAll(PDO::FETCH_ASSOC);
    $in_cost = 0;
    foreach ($received_in_items as $item) { 
        $in_cost += (floatval($item['quantity']) * floatval($item['cost_price'])); 
    }

    $net_material_cost = $out_cost - $in_cost;

    // 5. ดึงรายการค่าใช้จ่ายอื่นๆ (Expenses)
    $stmt_exp = $pdo->prepare("SELECT * FROM expenses WHERE installation_id = ? ORDER BY id DESC");
    $stmt_exp->execute([$proj_id]);
    $expenses = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);
    $total_expense = 0;
    foreach ($expenses as $exp) { 
        $total_expense += floatval($exp['amount']); 
    }

    $grand_total_cost = $net_material_cost + $total_expense;
    $net_profit = $total_income - $grand_total_cost;

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาด: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดโครงการ: <?= htmlspecialchars($project['name']) ?> - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Prompt', 'sans-serif'] } } } }
    </script>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <a href="expenses_summary.php" class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="text-base md:text-xl font-bold text-slate-900 tracking-tight">รายละเอียดโครงการ: <?= htmlspecialchars($project['name']) ?></h2>
                    <p class="text-xs text-slate-500">รหัสโครงการ ID: #<?= $project['id'] ?> | สถานะงาน: <?= htmlspecialchars($project['status']) ?></p>
                </div>
            </div>
            <div>
                <a href="expenses_summary.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-arrow-left"></i> กลับหน้าสรุปภาพรวม
                </a>
            </div>
        </header>

        <!-- Main Content Body -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8 space-y-6">
            
            <!-- Cards สรุปการเงินประจำโครงการ -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-1">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">รายรับรวม</p>
                    <h3 class="text-xl font-extrabold text-emerald-600">+฿<?= number_format($total_income, 2) ?></h3>
                </div>
                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-1">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">ต้นทุนวัสดุสุทธิ</p>
                    <h3 class="text-xl font-extrabold text-indigo-600">฿<?= number_format($net_material_cost, 2) ?></h3>
                </div>
                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-1">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">ค่าใช้จ่ายอื่นๆ</p>
                    <h3 class="text-xl font-extrabold text-amber-600">฿<?= number_format($total_expense, 2) ?></h3>
                </div>
                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-1">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">กำไรสุทธิโครงการ</p>
                    <h3 class="text-xl font-extrabold <?= $net_profit >= 0 ? 'text-emerald-700' : 'text-rose-700' ?>">
                        ฿<?= number_format($net_profit, 2) ?>
                    </h3>
                </div>
            </div>

            <!-- ส่วนแสดงรายละเอียดแยกเป็น 4 ส่วน (2 คอลัมน์บนมือถือ / 4 คอลัมน์บนจอใหญ่) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- ส่วนที่ 1: รายรับโครงการ -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4 flex flex-col">
                    <h3 class="font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center justify-between text-sm">
                        <span><i class="fa-solid fa-sack-dollar text-emerald-600 me-1.5"></i> รายรับ</span>
                        <span class="text-[11px] text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full font-bold">+฿<?= number_format($total_income, 2) ?></span>
                    </h3>
                    <div class="space-y-3 flex-1 max-h-[450px] overflow-y-auto pr-1">
                        <?php if (!empty($incomes)): foreach ($incomes as $inc): 
                            $raw_img = trim($inc['receipt_image'] ?? '');
                            $full_img_url = '';
                            if ($raw_img !== '') {
                                if (preg_match('/^http?:\/\//i', $raw_img) || preg_match('/^\//', $raw_img)) {
                                    $full_img_url = $raw_img;
                                } else {
                                    $clean_img = preg_replace('#^(\.\./)+#', '', $raw_img);
                                    $clean_img = preg_replace('#^(uploads/receipts/|uploads/)+#', '', $clean_img);
                                    $full_img_url = '../uploads/receipts/' . $clean_img;
                                }
                            }
                        ?>
                            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 flex items-center justify-between text-xs gap-2">
                                <div>
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($inc['title']) ?></div>
                                    <div class="text-[10px] text-slate-400">หมวด: <?= htmlspecialchars($inc['category'] ?: 'รายรับ') ?></div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <?php if ($full_img_url): ?>
                                        <img src="<?= htmlspecialchars($full_img_url) ?>" alt="สลิป" onclick="openImageModal('<?= htmlspecialchars($full_img_url) ?>')" class="w-7 h-7 object-cover rounded-lg border border-slate-200 cursor-pointer hover:opacity-80 transition shadow-xs" onerror="this.onerror=null; this.src='../Pic/456456.png';">
                                    <?php endif; ?>
                                    <div class="font-bold text-emerald-600">+฿<?= number_format($inc['amount'], 2) ?></div>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <p class="text-xs text-slate-400 italic text-center py-10">ยังไม่มีรายการรายรับ</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ส่วนที่ 2: รายการเบิกวัสดุอุปกรณ์ -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4 flex flex-col">
                    <h3 class="font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center justify-between text-sm">
                        <span><i class="fa-solid fa-box-archive text-rose-500 me-1.5"></i> เบิกวัสดุ</span>
                        <span class="text-[11px] text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full font-bold">฿<?= number_format($out_cost, 2) ?></span>
                    </h3>
                    <div class="space-y-3 flex-1 max-h-[450px] overflow-y-auto pr-1">
                        <?php if (!empty($dispensed_items)): foreach ($dispensed_items as $item): 
                            $item_total = floatval($item['quantity']) * floatval($item['cost_price']);
                        ?>
                            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <div class="text-[10px] text-slate-400">SKU: <?= htmlspecialchars($item['sku'] ?: '-') ?></div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="font-bold text-slate-900"><?= number_format($item['quantity']) ?> ชิ้น</div>
                                    <div class="text-[10px] text-rose-600 font-bold">฿<?= number_format($item_total, 2) ?></div>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <p class="text-xs text-slate-400 italic text-center py-10">ไม่มีรายการเบิกวัสดุ</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ส่วนที่ 3: รายการรับคืนวัสดุอุปกรณ์ -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4 flex flex-col">
                    <h3 class="font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center justify-between text-sm">
                        <span><i class="fa-solid fa-rotate-left text-purple-600 me-1.5"></i> รับคืนวัสดุ</span>
                        <span class="text-[11px] text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full font-bold">+฿<?= number_format($in_cost, 2) ?></span>
                    </h3>
                    <div class="space-y-3 flex-1 max-h-[450px] overflow-y-auto pr-1">
                        <?php if (!empty($received_in_items)): foreach ($received_in_items as $item): 
                            $item_total = floatval($item['quantity']) * floatval($item['cost_price']);
                        ?>
                            <div class="bg-purple-50/40 p-3 rounded-2xl border border-purple-100 flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <div class="text-[10px] text-slate-400">SKU: <?= htmlspecialchars($item['sku'] ?: '-') ?></div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="font-bold text-slate-900"><?= number_format($item['quantity']) ?> ชิ้น</div>
                                    <div class="text-[10px] text-purple-600 font-bold">+฿<?= number_format($item_total, 2) ?></div>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <p class="text-xs text-slate-400 italic text-center py-10">ไม่มีรายการรับคืนวัสดุ</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ส่วนที่ 4: ค่าใช้จ่ายอื่นๆ -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4 flex flex-col">
                    <h3 class="font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center justify-between text-sm">
                        <span><i class="fa-solid fa-receipt text-amber-600 me-1.5"></i> ค่าใช้จ่ายอื่น</span>
                        <span class="text-[11px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full font-bold">฿<?= number_format($total_expense, 2) ?></span>
                    </h3>
                    <div class="space-y-3 flex-1 max-h-[450px] overflow-y-auto pr-1">
                        <?php if (!empty($expenses)): foreach ($expenses as $exp): 
                            $raw_img = trim($exp['receipt_image'] ?? '');
                            $full_img_url = '';
                            if ($raw_img !== '') {
                                if (preg_match('/^http?:\/\//i', $raw_img) || preg_match('/^\//', $raw_img)) {
                                    $full_img_url = $raw_img;
                                } else {
                                    $clean_img = preg_replace('#^(\.\./)+#', '', $raw_img);
                                    $clean_img = preg_replace('#^(uploads/receipts/|uploads/)+#', '', $clean_img);
                                    $full_img_url = '../uploads/receipts/' . $clean_img;
                                }
                            }
                        ?>
                            <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100 flex items-center justify-between text-xs gap-2">
                                <div>
                                    <div class="font-bold text-slate-800"><?= htmlspecialchars($exp['title']) ?></div>
                                    <div class="text-[10px] text-slate-400">หมวด: <?= htmlspecialchars($exp['category'] ?: 'ทั่วไป') ?></div>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <?php if ($full_img_url): ?>
                                        <img src="<?= htmlspecialchars($full_img_url) ?>" alt="สลิป" onclick="openImageModal('<?= htmlspecialchars($full_img_url) ?>')" class="w-7 h-7 object-cover rounded-lg border border-slate-200 cursor-pointer hover:opacity-80 transition shadow-xs" onerror="this.onerror=null; this.src='../Pic/456456.png';">
                                    <?php endif; ?>
                                    <div class="font-bold text-amber-600">฿<?= number_format($exp['amount'], 2) ?></div>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <p class="text-xs text-slate-400 italic text-center py-10">ไม่มีรายการค่าใช้จ่ายอื่น</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Modal แสดงรูปสลิป / หลักฐานขนาดใหญ่ -->
    <div id="imageModal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white p-6 rounded-3xl max-w-lg w-full shadow-2xl space-y-4 text-center">
            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                <h4 class="font-bold text-slate-900 text-sm">หลักฐานการทำรายการ / สลิป</h4>
                <button onclick="closeImageModal()" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
            </div>
            <div class="flex justify-center max-h-[70vh] overflow-auto">
                <img id="modalReceiptImg" src="" alt="Receipt Slip" class="rounded-2xl max-h-[60vh] object-contain border border-slate-200">
            </div>
            <div class="flex justify-end pt-2">
                <button onclick="closeImageModal()" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-xl text-xs font-semibold">ปิด</button>
            </div>
        </div>
    </div>

    <!-- Script สำหรับเปิด/ปิด Modal รูปภาพ -->
    <script>
        function openImageModal(imgSrc) {
            document.getElementById('modalReceiptImg').src = imgSrc;
            let modal = document.getElementById('imageModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeImageModal() {
            let modal = document.getElementById('imageModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    </script>
</body>
</html>