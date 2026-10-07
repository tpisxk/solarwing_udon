<?php
session_start();
require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'dashboard_admin';

$total_items = $total_qty = $total_value = $total_project_cost = 0;
$low_stock_count = $out_of_stock_count = $normal_stock_count = 0;
$chart_products = $installations_list = $low_stock_products = [];

try {
    // 1. สรุปสถิติสต็อกภาพรวม และนับสถานะสินค้าใน Query เดียว
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_items,
            COALESCE(SUM(stock_quantity), 0) as total_qty,
            COALESCE(SUM(stock_quantity * cost_price), 0) as total_value,
            SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) as out_count,
            SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= 5 THEN 1 ELSE 0 END) as low_count,
            SUM(CASE WHEN stock_quantity > 5 THEN 1 ELSE 0 END) as normal_count
        FROM products
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    extract($stats);

    // 2. ดึงสินค้า 6 อันดับแรกสำหรับทำกราฟ
    $chart_products = $pdo->query("SELECT name, stock_quantity FROM products ORDER BY stock_quantity DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

    // 3. ดึงรายการโครงการ คำนวณค่าอุปกรณ์เบิกใช้ออกสุทธิ + ค่าใช้จ่ายอื่นๆ (expenses) ใน Query เดียว
    $stmt_install = $pdo->query("
        SELECT 
            i.id, i.name, i.status,
            (
                COALESCE(SUM(CASE WHEN s.type = 'out' THEN s.quantity * p.cost_price ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN s.type = 'in' THEN s.quantity * p.cost_price ELSE 0 END), 0) +
                COALESCE(e.total_exp, 0)
            ) AS total_cost
        FROM installations i
        LEFT JOIN stock_transactions s ON i.id = s.installation_id
        LEFT JOIN products p ON s.product_id = p.id
        LEFT JOIN (
            SELECT installation_id, SUM(amount) AS total_exp 
            FROM expenses GROUP BY installation_id
        ) e ON i.id = e.installation_id
        GROUP BY i.id ORDER BY i.id DESC
    ");
    $installations_list = $stmt_install->fetchAll(PDO::FETCH_ASSOC);
    $total_project_cost = array_sum(array_column($installations_list, 'total_cost'));

    // 4. ดึงสินค้าใกล้หมดสต็อก (<= 5 ชิ้น)
    $low_stock_products = $pdo->query("SELECT sku, name, category, stock_quantity FROM products WHERE stock_quantity <= 5 ORDER BY stock_quantity ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

$chart_labels = array_column($chart_products, 'name');
$chart_data = array_map('intval', array_column($chart_products, 'stock_quantity'));
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SolarStock Pro</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Prompt', 'sans-serif']
                    },
                    colors: {
                        brandGreen: '#059669'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <?php if (file_exists('sidebar_admin.php')) include 'sidebar_admin.php'; ?>

    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <!-- Header -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-10 shrink-0 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </span>
                <div>
                    <h2 class="text-xl font-bold text-slate-900">ภาพรวมคลังสินค้าและค่าใช้จ่ายโครงการ</h2>
                    <p class="text-xs text-slate-500">ระบบจัดการและวิเคราะห์ข้อมูลเรียลไทม์</p>
                </div>
            </div>
            <a href="dashboard_admin.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-house text-emerald-600"></i> หน้าหลัก
            </a>
        </header>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto p-8 space-y-6">
            <?php if (isset($db_error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl text-xs">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> เชื่อมต่อฐานข้อมูลล้มเหลว: <?= htmlspecialchars($db_error) ?>
                </div>
            <?php endif; ?>

            <!-- สถิติภาพรวม 4 Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm hover:-translate-y-1 transition group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase">รายการสินค้าทั้งหมด</p>
                            <h3 class="text-2xl font-extrabold text-slate-900 mt-1"><span class="counter" data-target="<?= $total_items ?>">0</span> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-brandGreen flex items-center justify-center text-lg"><i class="fa-solid fa-boxes-stacked"></i></div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm hover:-translate-y-1 transition group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase">มูลค่าสต็อกรวม</p>
                            <h3 class="text-2xl font-extrabold text-slate-900 mt-1">฿<span class="counter-decimal" data-target="<?= $total_value ?>">0.00</span></h3>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg"><i class="fa-solid fa-wallet"></i></div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm hover:-translate-y-1 transition group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase">ค่าใช้จ่ายโครงการรวม</p>
                            <h3 class="text-2xl font-extrabold text-blue-600 mt-1">฿<span class="counter-decimal" data-target="<?= $total_project_cost ?>">0.00</span></h3>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm hover:-translate-y-1 transition group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase">สินค้าใกล้หมด / หมด</p>
                            <h3 class="text-2xl font-extrabold text-rose-600 mt-1"><span class="counter" data-target="<?= $low_count + $out_count ?>">0</span> <span class="text-xs font-normal text-slate-500">รายการ</span></h3>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    </div>
                </div>
            </div>

            <!-- กราฟวิเคราะห์ -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm lg:col-span-2">
                    <h3 class="text-base font-bold text-slate-900 mb-4">สถิติสินค้าคงเหลือสูงสุด (Top Stock Items)</h3>
                    <div class="h-64 w-full"><canvas id="barChart"></canvas></div>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex flex-col justify-between">
                    <h3 class="text-base font-bold text-slate-900">สัดส่วนสถานะสต็อก</h3>
                    <div class="h-48 w-full my-2"><canvas id="doughnutChart"></canvas></div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs font-semibold pt-2 border-t border-slate-100">
                        <div class="text-emerald-600">ปกติ: <?= $normal_count ?></div>
                        <div class="text-amber-500">ใกล้หมด: <?= $low_count ?></div>
                        <div class="text-rose-600">หมด: <?= $out_count ?></div>
                    </div>
                </div>
            </div>

            <!-- ตารางข้อมูล -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- ตารางโครงการ -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-clipboard-list text-brandGreen me-2"></i>โครงการที่กำลังดำเนินงาน</h3>
                    <div class="max-h-72 overflow-y-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="sticky top-0 bg-white border-b border-slate-100 font-bold text-slate-400 uppercase">
                                <tr>
                                    <th class="py-2.5 px-3">โครงการ</th>
                                    <th class="py-2.5 px-3 text-right">ค่าใช้จ่ายจริง</th>
                                    <th class="py-2.5 px-3 text-center">สถานะ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                <?php if ($installations_list): foreach ($installations_list as $item):
                                        $st = $item['status'] ?? 'pending';
                                        $badge = ($st == 'completed' || $st == 'สำเร็จ') ? '<span class="bg-emerald-100 text-emerald-800 px-2.5 py-0.5 rounded-full text-[10px] font-bold">เสร็จสิ้น</span>' : (($st == 'in_progress' || $st == 'กำลังดำเนินการ') ? '<span class="bg-blue-100 text-blue-800 px-2.5 py-0.5 rounded-full text-[10px] font-bold">ดำเนินการ</span>' :
                                                '<span class="bg-amber-100 text-amber-800 px-2.5 py-0.5 rounded-full text-[10px] font-bold">รอดำเนินการ</span>');
                                ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="py-3 px-3 font-bold text-slate-900"><?= htmlspecialchars($item['name']) ?></td>
                                            <td class="py-3 px-3 text-right font-semibold text-emerald-600">฿<?= number_format($item['total_cost'], 2) ?></td>
                                            <td class="py-3 px-3 text-center"><?= $badge ?></td>
                                        </tr>
                                    <?php endforeach;
                                else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-6 text-slate-400">ไม่มีข้อมูลโครงการ</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ตารางสินค้าใกล้หมด -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                    <h3 class="text-base font-bold text-slate-900"><i class="fa-solid fa-triangle-exclamation text-rose-500 me-2"></i> สินค้าใกล้หมด / ต้องเติมสต็อก</h3>
                    <div class="max-h-72 overflow-y-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="sticky top-0 bg-white border-b border-slate-100 font-bold text-slate-400 uppercase">
                                <tr>
                                    <th class="py-2.5 px-3">ชื่อสินค้า</th>
                                    <th class="py-2.5 px-3">หมวดหมู่</th>
                                    <th class="py-2.5 px-3 text-right">คงเหลือ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                <?php if ($low_stock_products): foreach ($low_stock_products as $prod): ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="py-3 px-3 font-bold text-slate-900"><?= htmlspecialchars($prod['name']) ?></td>
                                            <td class="py-3 px-3 text-slate-500"><?= htmlspecialchars($prod['category'] ?? '-') ?></td>
                                            <td class="py-3 px-3 text-right font-extrabold text-rose-600"><?= number_format($prod['stock_quantity']) ?> ชิ้น</td>
                                        </tr>
                                    <?php endforeach;
                                else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-6 text-slate-400">ไม่มีสินค้าใกล้หมด</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Number Counter Animation
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.counter').forEach(c => {
                let target = +c.dataset.target,
                    count = 0,
                    step = Math.ceil(target / 20);
                if (!target) return c.innerText = 0;
                let timer = setInterval(() => {
                    count += step;
                    if (count >= target) {
                        c.innerText = target.toLocaleString();
                        clearInterval(timer);
                    } else {
                        c.innerText = count.toLocaleString();
                    }
                }, 30);
            });
            document.querySelectorAll('.counter-decimal').forEach(c => {
                let target = +c.dataset.target,
                    count = 0,
                    step = target / 20;
                if (!target) return c.innerText = '0.00';
                let timer = setInterval(() => {
                    count += step;
                    if (count >= target) {
                        c.innerText = target.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        clearInterval(timer);
                    } else {
                        c.innerText = count.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                }, 30);
            });
        });

        // Charts Configuration
        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    data: <?= json_encode($chart_data) ?>,
                    backgroundColor: '#059669',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        new Chart(document.getElementById('doughnutChart'), {
            type: 'doughnut',
            data: {
                labels: ['ปกติ', 'ใกล้หมด', 'หมด'],
                datasets: [{
                    data: [<?= $normal_count ?>, <?= $low_count ?>, <?= $out_count ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#f43f5e'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                cutout: '70%'
            }
        });
    </script>
</body>

</html>