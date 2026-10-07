<?php
session_start();
// ป้องกันการแคชหน้าเว็บ เพื่อให้พอกดปุ่ม Back จะต้องโหลดและเช็คสิทธิ์ใหม่ทันที
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once '../db/conn.php';
require_once '../auth_check.php';
$current_page = 'locations'; // กำหนดหน้าปัจจุบันสำหรับ Sidebar

// 1. ดึงข้อมูลโครงการทั้งหมด พร้อมคำนวณต้นทุนอุปกรณ์สุทธิจาก stock_transactions ที่ผูกกับ installation_id
try {
    $stmt = $pdo->query("
        SELECT 
            i.*,
            SUM(CASE WHEN st.type = 'out' THEN st.quantity * p.cost_price ELSE 0 END) as total_out,
            SUM(CASE WHEN st.type = 'in' OR st.type = 'return' THEN st.quantity * p.cost_price ELSE 0 END) as total_return
        FROM installations i
        LEFT JOIN stock_transactions st ON i.id = st.installation_id
        LEFT JOIN products p ON st.product_id = p.id
        GROUP BY i.id
        ORDER BY i.id DESC
    ");
    $locationsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt_fallback = $pdo->query("SELECT * FROM installations ORDER BY id DESC");
        $locationsList = $stmt_fallback->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ex) {
        $locationsList = [];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการโครงการและแผนที่หน้างาน - SolarStock Pro</title>
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
            <h2 class="text-xl font-bold text-slate-900 tracking-tight">จัดการโครงการและแผนที่หน้างานติดตั้ง</h2>
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
                        <span>บันทึกข้อมูลโครงการและพิกัดแผนที่เรียบร้อยแล้ว</span>
                        <a href="locations.php" class="text-emerald-700 font-bold hover:underline text-xs">ปิด</a>
                    </div>
                <?php elseif ($_GET['status'] == 'error'): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-800 px-6 py-4 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
                        <span>เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง</span>
                        <a href="locations.php" class="text-rose-700 font-bold hover:underline text-xs">ปิด</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- ฟอร์มเพิ่มโครงการใหม่พร้อม Map และขนาดติดตั้ง -->
            <div class="bg-white p-8 rounded-3xl border border-slate-200/80 shadow-xs space-y-6">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">เพิ่มโครงการ / หน้างานพร้อมพิกัดแผนที่</h3>
                    <p class="text-xs text-slate-500 mt-1">กรอกชื่อโครงการ รายละเอียด ขนาดการติดตั้ง และลิงก์ Google Maps เพื่อความสะดวกในการดำเนินงาน</p>
                </div>

                <form action="locations_process.php" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">ชื่อโครงการ / ลูกค้า / สถานที่หน้างาน</label>
                        <input type="text" name="name" required placeholder="เช่น โครงการติดตั้งโซล่าเซลล์ บ้านคุณสมชาย..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <!-- ช่องกรอกขนาดติดตั้ง พร้อมหน่วย kWp ต่อท้าย -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-600">ขนาดการติดตั้ง</label>
                        <div class="relative flex items-center">
                            <input type="text" name="system_size" placeholder="เช่น 5, 10, 32.5" class="w-full pl-4 pr-14 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                            <span class="absolute right-4 text-xs font-bold text-slate-400 pointer-events-none">kWp</span>
                        </div>
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600">ลิงก์ Google Maps (URL)</label>
                        <input type="url" name="map_url" placeholder="https://maps.app.goo.gl/..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <div class="space-y-1.5 md:col-span-3">
                        <label class="block text-xs font-semibold text-slate-600">รายละเอียดเพิ่มเติม (ที่อยู่ / หมายเหตุ)</label>
                        <input type="text" name="description" placeholder="เช่น บ้านเลขที่, ตำบล, อำเภอ หรือจุดสังเกตหน้างาน..." class="w-full px-4 py-3 border border-slate-200 rounded-2xl text-xs bg-slate-50 focus:outline-none focus:border-emerald-600 font-medium">
                    </div>

                    <div class="md:col-span-3 flex justify-end pt-2">
                        <button type="submit" class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-xs font-semibold shadow-md shadow-emerald-600/25 transition-all">
                            บันทึกโครงการและแผนที่
                        </button>
                    </div>
                </form>
            </div>

            <!-- ตารางแสดงรายการโครงการ พิกัดแผนที่ และต้นทุนอุปกรณ์ -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden p-6 space-y-4">
                <h3 class="text-base font-bold text-slate-900 px-2">รายการโครงการ แผนที่ และต้นทุนอุปกรณ์สุทธิ</h3>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-4 font-semibold">ชื่อโครงการ / หน้างาน</th>
                                <th class="py-3 px-4 font-semibold">ขนาดติดตั้ง</th>
                                <th class="py-3 px-4 font-semibold">รายละเอียด / ที่อยู่</th>
                                <th class="py-3 px-4 font-semibold text-center">แผนที่หน้างาน</th>
                                <th class="py-3 px-4 font-semibold text-right">มูลค่าเบิก (+) / รับคืน (-)</th>
                                <th class="py-3 px-4 font-semibold text-right">ต้นทุนอุปกรณ์สุทธิ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                            <?php if (!empty($locationsList)): ?>
                                <?php foreach ($locationsList as $loc): 
                                    $total_out = $loc['total_out'] ?? 0;
                                    $total_return = $loc['total_return'] ?? 0;
                                    $net_cost = $total_out - $total_return;
                                    $map_url = $loc['map_url'] ?? '';
                                    $sys_size = trim($loc['system_size'] ?? '');
                                ?>
                                    <tr class="hover:bg-slate-50/80 transition-all">
                                        <td class="py-3.5 px-4 font-bold text-slate-900">
                                            <?= htmlspecialchars($loc['name'] ?? $loc['project_name'] ?? '-') ?>
                                            <div class="text-[10px] text-slate-400 font-normal">ID: #<?= $loc['id'] ?></div>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <?php if (!empty($sys_size)): ?>
                                                <span class="bg-emerald-50 text-emerald-700 px-2.5 py-1 rounded-lg text-xs font-bold border border-emerald-200/80 inline-flex items-center gap-1">
                                                    <i class="fa-solid fa-solar-panel"></i> <?= htmlspecialchars($sys_size) ?><?= (stripos($sys_size, 'kw') === false) ? ' kWp' : '' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-300">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-500"><?= htmlspecialchars($loc['description'] ?? '-') ?></td>
                                        <td class="py-3.5 px-4 text-center">
                                            <?php if (!empty($map_url)): ?>
                                                <a href="<?= htmlspecialchars($map_url) ?>" target="_blank" class="inline-flex items-center gap-1.5 bg-sky-50 text-sky-700 border border-sky-200/60 px-3 py-1.5 rounded-xl font-semibold hover:bg-sky-100 transition-all">
                                                    <i class="fa-solid fa-map-location-dot"></i> เปิดแผนที่
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-300">- ไม่มีพิกัด -</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-right text-slate-600">
                                            <div>เบิก: <?= number_format($total_out, 2) ?></div>
                                            <div class="text-emerald-600">คืน: -<?= number_format($total_return, 2) ?></div>
                                        </td>
                                        <td class="py-3.5 px-4 text-right font-bold text-indigo-600 text-sm">
                                            <?= number_format($net_cost, 2) ?> บาท
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-slate-400 font-normal">ยังไม่มีข้อมูลโครงการในระบบ</td>
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