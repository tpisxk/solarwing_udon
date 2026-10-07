<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';

// ตรวจสอบเฉพาะการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$current_page = 'expenses';

// ==========================================
// ดึงข้อมูลสรุปโครงการ พร้อมดึงรายละเอียดเบิก/รับเข้า และค่าใช้จ่าย
// ==========================================
$project_summaries = [];
$total_all_material_cost = 0;
$total_all_other_expenses = 0;
$total_all_expenses = 0;

try {
    // 1. ดึงข้อมูลโครงการหลัก
    $stmt_projects = $pdo->query("SELECT id, name, status FROM installations ORDER BY id DESC");
    $projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

    foreach ($projects as $proj) {
        $proj_id = $proj['id'];

        // 1.1 รายการเบิกออก (type = 'out')
        $stmt_out = $pdo->prepare("
            SELECT s.*, p.name AS product_name, p.sku, p.cost_price, u.name AS user_name
            FROM stock_transactions s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.installation_id = ? AND s.type = 'out'
            ORDER BY s.id DESC
        ");
        $stmt_out->execute([$proj_id]);
        $dispensed_items = $stmt_out->fetchAll(PDO::FETCH_ASSOC);

        $out_cost = 0;
        foreach ($dispensed_items as $item) {
            $out_cost += ($item['quantity'] * $item['cost_price']);
        }

        // 1.2 รายการรับเข้าสินค้า (type = 'in')
        $stmt_in = $pdo->prepare("
            SELECT s.*, p.name AS product_name, p.sku, p.cost_price, u.name AS user_name
            FROM stock_transactions s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.installation_id = ? AND s.type = 'in'
            ORDER BY s.id DESC
        ");
        $stmt_in->execute([$proj_id]);
        $received_in_items = $stmt_in->fetchAll(PDO::FETCH_ASSOC);

        $in_cost = 0;
        foreach ($received_in_items as $item) {
            $in_cost += ($item['quantity'] * $item['cost_price']);
        }

        // คำนวณมูลค่าอุปกรณ์สุทธิที่ใช้ไปจริง (เบิกออก - รับเข้า)
        $net_material_cost = $out_cost - $in_cost;

        // 1.3 รายการค่าใช้จ่ายอื่นๆ (Expenses)
        $stmt_expenses = $pdo->prepare("
            SELECT * FROM expenses WHERE installation_id = ? ORDER BY id DESC
        ");
        $stmt_expenses->execute([$proj_id]);
        $other_expense_items = $stmt_expenses->fetchAll(PDO::FETCH_ASSOC);

        $other_cost = 0;
        foreach ($other_expense_items as $exp) {
            $other_cost += floatval($exp['amount']);
        }

        $grand_total = $net_material_cost + $other_cost;

        // รวมยอดระดับภาพรวมระบบ
        $total_all_material_cost  += $net_material_cost;
        $total_all_other_expenses += $other_cost;
        $total_all_expenses       += $grand_total;

        $project_summaries[] = [
            'project_id'          => $proj_id,
            'project_name'        => $proj['name'],
            'project_status'      => $proj['status'],
            'out_cost'            => $out_cost,
            'in_cost'             => $in_cost,
            'net_material_cost'   => $net_material_cost,
            'other_cost'          => $other_cost,
            'grand_total'         => $grand_total,
            'dispensed_items'     => $dispensed_items,
            'received_in_items'   => $received_in_items,
            'other_expense_items' => $other_expense_items
        ];
    }

} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปรายละเอียดค่าใช้จ่ายโครงการ - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    <style>::-webkit-scrollbar { width: 6px; height: 6px; } ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }</style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar ด้านข้าง -->
    <?php 
    if (file_exists('sidebar_admin.php')) {
        include 'sidebar_admin.php'; 
    } elseif (file_exists('sidebar.php')) {
        include 'sidebar.php';
    }
    ?>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100">
        
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-10 shrink-0 z-10 shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-slate-900 tracking-tight">รายงานค่าใช้จ่ายและส่วนต่างการเบิก/รับเข้า</h2>
                <p class="text-sm text-slate-500 mt-0.5">สรุปมูลค่าเบิกออก หักลบด้วยยอดรับเข้าสินค้า แยกรายโครงการ</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-house text-emerald-600"></i> หน้าหลัก
            </a>
            </div>
        </header>

        <!-- Scroll Content Area -->
        <div class="flex-1 overflow-y-auto p-8 space-y-6">
            
            <?php if (isset($db_error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-sm">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> เกิดข้อผิดพลาดในฐานข้อมูล: <?= htmlspecialchars($db_error) ?>
                </div>
            <?php endif; ?>

            <!-- Cards สถิติภาพรวม 3 ช่อง -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/85 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">มูลค่าอุปกรณ์สุทธิ (เบิก - รับเข้า)</p>
                        <h3 class="text-2xl font-extrabold text-emerald-600">฿<?= number_format($total_all_material_cost, 2) ?></h3>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shadow-inner">
                        <i class="fa-solid fa-boxes-packing"></i>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/85 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">ค่าใช้จ่ายอื่นๆ รวม</p>
                        <h3 class="text-2xl font-extrabold text-amber-600">฿<?= number_format($total_all_other_expenses, 2) ?></h3>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shadow-inner">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/85 shadow-sm flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">ค่าใช้จ่ายรวมสุทธิทุกโครงการ</p>
                        <h3 class="text-2xl font-extrabold text-rose-600">฿<?= number_format($total_all_expenses, 2) ?></h3>
                    </div>
                    <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl shadow-inner">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                    </div>
                </div>
            </div>

            <!-- ตารางแสดงรายละเอียดแบบกดขยายได้ -->
            <div class="bg-white rounded-3xl border border-slate-200/85 shadow-sm overflow-hidden p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-diagram-project text-brandGreen me-2"></i> สรุปและแจกแจงรายละเอียดรายโครงการ</h3>
                        <p class="text-xs text-slate-500">คลิกแถบโครงการเพื่อขยายดูรายการเบิกออก รับเข้า และค่าใช้จ่ายย่อย</p>
                    </div>
                    <span class="text-xs text-slate-400 font-medium">รวมทั้งสิ้น <?= count($project_summaries) ?> โครงการ</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/70 text-xs font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-4 px-4"># / โครงการ</th>
                                <th class="py-4 px-4 text-center">สถานะ</th>
                                <th class="py-4 px-4 text-right">อุปกรณ์สุทธิ</th>
                                <th class="py-4 px-4 text-right">ค่าใช้จ่ายอื่นๆ</th>
                                <th class="py-4 px-4 text-right">รวมใช้ไปสุทธิ</th>
                                <th class="py-4 px-4 text-center">รายละเอียด</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium">
                            <?php if (!empty($project_summaries)): ?>
                                <?php foreach ($project_summaries as $p): 
                                    $status = $p['project_status'] ?? 'pending';
                                    if ($status == 'completed' || $status == 'สำเร็จ') {
                                        $badge = '<span class="bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-emerald-200">เสร็จสิ้น</span>';
                                    } elseif ($status == 'in_progress' || $status == 'กำลังดำเนินการ') {
                                        $badge = '<span class="bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-blue-200">ดำเนินการ</span>';
                                    } else {
                                        $badge = '<span class="bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full text-[10px] font-bold border border-amber-200">รอดำเนินการ</span>';
                                    }
                                ?>
                                    <!-- แถบโครงการหลัก -->
                                    <tr class="hover:bg-slate-50 transition cursor-pointer" onclick="toggleDetails('proj-<?= $p['project_id'] ?>')">
                                        <td class="py-4 px-4">
                                            <div class="font-bold text-slate-900 text-sm flex items-center gap-2">
                                                <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition-transform duration-200" id="icon-proj-<?= $p['project_id'] ?>"></i>
                                                <?= htmlspecialchars($p['project_name']) ?>
                                            </div>
                                            <div class="text-[10px] text-slate-400 ms-4">
                                                ID: #<?= $p['project_id'] ?> | เบิก: <?= count($p['dispensed_items']) ?> | รับเข้า: <?= count($p['received_in_items']) ?> | รายจ่ายอื่น: <?= count($p['other_expense_items']) ?>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4 text-center"><?= $badge ?></td>
                                        <td class="py-4 px-4 text-right">
                                            <div class="font-semibold text-emerald-600">฿<?= number_format($p['net_material_cost'], 2) ?></div>
                                            <?php if ($p['in_cost'] > 0): ?>
                                                <div class="text-[9px] text-slate-400">(เบิก ฿<?= number_format($p['out_cost']) ?> - หักรับเข้า ฿<?= number_format($p['in_cost']) ?>)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-4 text-right font-semibold text-amber-600">฿<?= number_format($p['other_cost'], 2) ?></td>
                                        <td class="py-4 px-4 text-right font-extrabold text-slate-900 text-sm">฿<?= number_format($p['grand_total'], 2) ?></td>
                                        <td class="py-4 px-4 text-center">
                                            <button class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold rounded-xl transition border border-slate-200 inline-flex items-center gap-1">
                                                <i class="fa-solid fa-list-ul text-xs"></i> ข้อมูลย่อย
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- แถวรายละเอียดเชิงลึก (3 คอลัมน์: เบิก / รับเข้า / รายจ่ายอื่น) -->
                                    <tr id="detail-proj-<?= $p['project_id'] ?>" class="hidden bg-slate-50/60">
                                        <td colspan="6" class="p-6">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-white p-5 rounded-2xl border border-slate-200 shadow-inner">
                                                
                                                <!-- 1. รายการอุปกรณ์ที่เบิกออก (Out) -->
                                                <div class="space-y-3">
                                                    <h4 class="text-xs font-bold text-slate-800 flex items-center justify-between border-b border-slate-100 pb-2">
                                                        <span><i class="fa-solid fa-arrow-up-from-bracket text-emerald-600 me-1.5"></i> เบิกออก (Out)</span>
                                                        <span class="text-[10px] text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200 font-bold">฿<?= number_format($p['out_cost'], 2) ?></span>
                                                    </h4>

                                                    <?php if (!empty($p['dispensed_items'])): ?>
                                                        <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                                            <?php foreach ($p['dispensed_items'] as $item): 
                                                                $item_total = $item['quantity'] * $item['cost_price'];
                                                            ?>
                                                                <div class="flex items-center justify-between bg-slate-50 p-2.5 rounded-xl text-[11px] border border-slate-100">
                                                                    <div>
                                                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($item['product_name']) ?></div>
                                                                        <div class="text-[10px] text-slate-400">ผู้ทำรายการ: <?= htmlspecialchars($item['user_name'] ?: '-') ?></div>
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <div class="font-bold text-slate-900"><?= number_format($item['quantity']) ?> ชิ้น</div>
                                                                        <div class="text-[10px] text-emerald-600 font-bold">= ฿<?= number_format($item_total, 2) ?></div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-[11px] text-slate-400 italic py-2">ไม่มีประวัติการเบิกออก</p>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- 2. รายการรับเข้าสินค้า (In) -->
                                                <div class="space-y-3">
                                                    <h4 class="text-xs font-bold text-slate-800 flex items-center justify-between border-b border-slate-100 pb-2">
                                                        <span><i class="fa-solid fa-arrow-down-to-bracket text-purple-600 me-1.5"></i> รับเข้า (In)</span>
                                                        <span class="text-[10px] text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full border border-purple-200 font-bold">฿<?= number_format($p['in_cost'], 2) ?></span>
                                                    </h4>

                                                    <?php if (!empty($p['received_in_items'])): ?>
                                                        <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                                            <?php foreach ($p['received_in_items'] as $item): 
                                                                $item_total = $item['quantity'] * $item['cost_price'];
                                                            ?>
                                                                <div class="flex items-center justify-between bg-purple-50/50 p-2.5 rounded-xl text-[11px] border border-purple-100">
                                                                    <div>
                                                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($item['product_name']) ?></div>
                                                                        <div class="text-[10px] text-slate-400">อ้างอิง: <?= htmlspecialchars($item['reference_no'] ?: '-') ?></div>
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <div class="font-bold text-slate-900"><?= number_format($item['quantity']) ?> ชิ้น</div>
                                                                        <div class="text-[10px] text-purple-600 font-bold">= ฿<?= number_format($item_total, 2) ?></div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-[11px] text-slate-400 italic py-2">ไม่มีประวัติการรับเข้า</p>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- 3. รายการค่าใช้จ่ายอื่นๆ (Expenses) -->
                                                <div class="space-y-3">
                                                    <h4 class="text-xs font-bold text-slate-800 flex items-center justify-between border-b border-slate-100 pb-2">
                                                        <span><i class="fa-solid fa-receipt text-amber-600 me-1.5"></i> ค่าใช้จ่ายอื่นๆ</span>
                                                        <span class="text-[10px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200 font-bold">฿<?= number_format($p['other_cost'], 2) ?></span>
                                                    </h4>

                                                    <?php if (!empty($p['other_expense_items'])): ?>
                                                        <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                                            <?php foreach ($p['other_expense_items'] as $exp): ?>
                                                                <div class="flex items-center justify-between bg-slate-50 p-2.5 rounded-xl text-[11px] border border-slate-100">
                                                                    <div>
                                                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($exp['title']) ?></div>
                                                                        <div class="text-[10px] text-slate-400">หมวดหมู่: <?= htmlspecialchars($exp['category'] ?: 'ทั่วไป') ?></div>
                                                                    </div>
                                                                    <div class="font-bold text-amber-600">
                                                                        ฿<?= number_format($exp['amount'], 2) ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-[11px] text-slate-400 italic py-2">ไม่มีรายการค่าใช้จ่ายอื่น</p>
                                                    <?php endif; ?>
                                                </div>

                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-12 text-slate-400">ไม่พบข้อมูลโครงการในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- JavaScript เปิด-ปิด Accordion Details -->
    <script>
        function toggleDetails(id) {
            const row = document.getElementById('detail-' + id);
            const icon = document.getElementById('icon-' + id);
            
            if (row.classList.contains('hidden')) {
                row.classList.remove('hidden');
                icon.classList.add('rotate-90');
            } else {
                row.classList.add('hidden');
                icon.classList.remove('rotate-90');
            }
        }
    </script>
</body>
</html>