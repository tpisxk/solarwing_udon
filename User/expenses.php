<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'expenses'; // กำหนดหน้าปัจจุบันสำหรับ Sidebar

// 1. ดึงข้อมูลค่าใช้จ่ายทั่วไป
try {
    $stmt_exp = $pdo->query("
        SELECT e.*, i.name as project_name 
        FROM expenses e 
        LEFT JOIN installations i ON e.installation_id = i.id 
        ORDER BY e.created_at DESC LIMIT 50
    ");
    $expensesList = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_total_exp = $pdo->query("SELECT SUM(amount) as total FROM expenses");
    $totalGeneralExpense = $stmt_total_exp->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    $expensesList = [];
    $totalGeneralExpense = 0;
}

// 2. ดึงรายชื่อโครงการสำหรับใส่ใน Dropdown
try {
    $stmt_inst_list = $pdo->query("SELECT id, name FROM installations ORDER BY id DESC");
    $installationsDropdown = $stmt_inst_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $installationsDropdown = [];
}

// 3. คำนวณต้นทุนอุปกรณ์แต่ละโครงการจาก stock_transactions (เบิกออก - รับคืน) x ราคาต้นทุน
try {
    $stmt_proj = $pdo->query("
        SELECT 
            i.id,
            i.name as project_name,
            SUM(CASE WHEN st.type = 'out' THEN st.quantity * p.cost_price ELSE 0 END) as total_out,
            SUM(CASE WHEN st.type = 'in' OR st.type = 'return' THEN st.quantity * p.cost_price ELSE 0 END) as total_return
        FROM installations i
        LEFT JOIN stock_transactions st ON i.id = st.installation_id
        LEFT JOIN products p ON st.product_id = p.id
        GROUP BY i.id, i.name
        ORDER BY i.id DESC
    ");
    $projectsCost = $stmt_proj->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $projectsCost = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการค่าใช้จ่ายและต้นทุนโครงการ - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'Inter', 'sans-serif'] },
                    colors: { darkSidebar: '#000007', brandGreen: '#059669' }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100">
        
        <!-- Header Bar -->
        <header class="h-20 bg-white border-b border-slate-200/80 flex items-center justify-between px-10 shrink-0 z-10 shadow-xs">
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">จัดการค่าใช้จ่ายและต้นทุนโครงการ</h2>
            <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-house text-emerald-600"></i> หน้าหลัก
            </a>
        </header>

        <!-- Main Body -->
        <div class="flex-1 overflow-y-auto p-10 space-y-6">
            
            <!-- แจ้งเตือนสถานะ -->
            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] == 'success'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>บันทึกรายการค่าใช้จ่ายเรียบร้อยแล้ว</span>
                        <a href="expenses.php" class="text-emerald-700 font-bold hover:underline text-xs">ปิด</a>
                    </div>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง</span>
                        <a href="expenses.php" class="text-rose-700 font-bold hover:underline text-xs">ปิด</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ส่วนที่ 1: สรุปต้นทุนวัสดุอุปกรณ์แต่ละโครงการ (เบิก - รับคืน) -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-8 space-y-6 shadow-xs">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">สรุปต้นทุนอุปกรณ์แต่ละโครงการ</h3>
                    <p class="text-xs text-slate-500 mt-1">คำนวณอัตโนมัติจากมูลค่าอุปกรณ์ที่เบิกออก หักลบด้วยอุปกรณ์ที่รับคืนคลัง คูณด้วยราคาต้นทุนจริง</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4 font-semibold">ชื่อโครงการ / หน้างาน</th>
                                <th class="py-3 px-4 font-semibold text-right">มูลค่าเบิกออก (+)</th>
                                <th class="py-3 px-4 font-semibold text-right">มูลค่ารับคืน (-)</th>
                                <th class="py-3 px-4 font-semibold text-right">ต้นทุนอุปกรณ์สุทธิ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if (!empty($projectsCost)): ?>
                                <?php foreach ($projectsCost as $proj): 
                                    $net_cost = $proj['total_out'] - $proj['total_return'];
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition-all">
                                        <td class="py-3.5 px-4 font-bold text-slate-900"><?= htmlspecialchars($proj['project_name']) ?></td>
                                        <td class="py-3.5 px-4 text-right text-slate-600"><?= number_format($proj['total_out'], 2) ?> บาท</td>
                                        <td class="py-3.5 px-4 text-right text-emerald-600">-<?= number_format($proj['total_return'], 2) ?> บาท</td>
                                        <td class="py-3.5 px-4 text-right font-bold text-indigo-600 text-sm"><?= number_format($net_cost, 2) ?> บาท</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-slate-400">ยังไม่มีข้อมูลโครงการหรือการเบิกจ่ายอุปกรณ์</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ส่วนที่ 2: ฟอร์มบันทึกค่าใช้จ่ายทั่วไป (พร้อม Dropdown โครงการ) -->
            <div class="bg-white p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">เพิ่มรายการค่าใช้จ่ายทั่วไป</h3>
                    <p class="text-xs text-slate-500 mt-1">บันทึกค่าใช้จ่ายอื่นๆ เช่น ค่าน้ำมัน, ค่าเดินทาง, ค่าจ้างเหมา หรือค่าใช้จ่ายเบ็ดเตล็ด</p>
                </div>

                <form action="expenses_process.php" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <!-- โครงการ (Dropdown) -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">โครงการ / หน้างาน (ระบุหรือไม่ก็ได้)</label>
                        <select name="installation_id" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            <option value="">-- ส่วนกลาง / ไม่ระบุโครงการ --</option>
                            <?php foreach ($installationsDropdown as $inst): ?>
                                <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- รายละเอียดค่าใช้จ่าย -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">รายการ / รายละเอียดค่าใช้จ่าย</label>
                        <input type="text" name="title" required placeholder="เช่น ค่าขนส่งแผงโซล่าเซลล์..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <!-- จำนวนเงิน -->
                    <div class="space-y-1.5 md:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600">จำนวนเงิน (บาท)</label>
                        <input type="number" step="0.01" name="amount" min="0.01" required placeholder="0.00" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <!-- หมวดหมู่ -->
                    <div class="space-y-1.5 md:col-span-1">
                        <label class="block text-xs font-semibold text-slate-600">หมวดหมู่</label>
                        <select name="category" class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            <option value="ทั่วไป">ทั่วไป / เบ็ดเตล็ด</option>
                            <option value="เดินทาง/ขนส่ง">เดินทาง / ขนส่ง</option>
                            <option value="อุปกรณ์/เครื่องมือ">อุปกรณ์ / เครื่องมือ</option>
                            <option value="ค่าแรง/เงินเดือน">ค่าแรง / ค่าจ้าง</option>
                        </select>
                    </div>

                    <div class="md:col-span-6 flex justify-end pt-2">
                        <button type="submit" class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-xs font-semibold shadow-md shadow-emerald-600/25 transition-all cursor-pointer">
                            บันทึกค่าใช้จ่าย
                        </button>
                    </div>
                </form>
            </div>

            <!-- ส่วนที่ 3: ตารางประวัติค่าใช้จ่ายทั่วไป -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden p-6 space-y-4">
                <h3 class="text-base font-bold text-slate-900 px-2">ประวัติค่าใช้จ่ายทั่วไปล่าสุด</h3>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4 font-semibold">วันที่ / เวลา</th>
                                <th class="py-3 px-4 font-semibold">โครงการ</th>
                                <th class="py-3 px-4 font-semibold">รายการ</th>
                                <th class="py-3 px-4 font-semibold">หมวดหมู่</th>
                                <th class="py-3 px-4 font-semibold text-right">จำนวนเงิน (บาท)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if (!empty($expensesList)): ?>
                                <?php foreach ($expensesList as $row): ?>
                                    <tr class="hover:bg-slate-50/80 transition-all">
                                        <td class="py-3.5 px-4 text-slate-500"><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                                        <td class="py-3.5 px-4 text-slate-600 font-semibold">
                                            <?= htmlspecialchars($row['project_name'] ?? 'ส่วนกลาง / ไม่ระบุ') ?>
                                        </td>
                                        <td class="py-3.5 px-4 font-bold text-slate-900"><?= htmlspecialchars($row['title'] ?? $row['description'] ?? '-') ?></td>
                                        <td class="py-3.5 px-4">
                                            <span class="bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full text-[11px] font-semibold">
                                                <?= htmlspecialchars($row['category'] ?? 'ทั่วไป') ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-bold text-rose-600"><?= number_format($row['amount'] ?? 0, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-slate-400 font-normal">ยังไม่มีรายการค่าใช้จ่ายทั่วไปในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

</body>
</html>