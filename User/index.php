<?php
// เรียกใช้งานไฟล์เชื่อมต่อฐานข้อมูลที่แยกไว้
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../db/conn.php';
require_once '../auth_check.php';
// ดึงข้อมูลสินค้าจากตาราง products มาแสดงผล
$stmt =$pdo->query("SELECT * FROM products ORDER BY id DESC");
$products =$stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SolarStock Pro - Modern Edition</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <!-- โหลด Tailwind CSS สำหรับจัดการดีไซน์ และ Google Fonts (Prompt & Inter) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'Inter', 'sans-serif'] },
                    colors: {
                        darkSidebar: '#000007', 
                        brandGreen: '#059669',  
                    }
                }
            }
        }
    </script>
    <style>
        .page-view { display: none; }
        .page-view.active { display: block; animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- 🌐 เรียกใช้งาน Sidebar ที่แยกไฟล์ออกมา -->
    <?php include 'sidebar.php'; ?>

    <!-- 💻 พื้นที่แสดงเนื้อหาหลัก (Main Content) -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100">
        
        <!-- ส่วนหัว (Header Bar) -->
        <header class="h-20 bg-white border-b border-slate-200/80 flex items-center justify-between px-10 shrink-0 z-10 shadow-xs">
            <h2 id="page-title" class="text-xl font-bold text-slate-900 tracking-tight">Calendar (ปฏิทินงานติดตั้ง)</h2>
            <div class="flex items-center gap-3">
                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200/60 px-4 py-1.5 rounded-full text-xs font-semibold flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> MySQL Database Active
                </span>
            </div>
        </header>

        <!-- ส่วนเนื้อหาที่เปลี่ยนไปตามเมนู (Viewport Body) -->
        <div class="flex-1 overflow-y-auto p-10">
            
            <!-- 1. หน้า Calendar -->
            <div id="view-calendar" class="page-view active space-y-6">
                <div class="bg-white p-8 rounded-3xl border border-slate-200/80 shadow-xs flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold text-slate-900">ตารางนัดหมายและปฏิบัติการหน้างาน</h3>
                        <p class="text-xs text-slate-500 mt-1">ภาพรวมตารางงานติดตั้งโซล่าเซลล์ประจำเดือน ตุลาคม 2026</p>
                    </div>
                    <button class="bg-brandGreen hover:bg-emerald-700 text-white px-6 py-3 rounded-2xl text-sm font-semibold shadow-md shadow-emerald-600/20 transition-all">+ เพิ่มนัดหมายใหม่</button>
                </div>
                <div class="bg-white p-20 rounded-3xl border border-slate-200/80 shadow-xs text-center text-slate-400 font-medium">
                    📅 [Interactive Calendar View]
                </div>
            </div>

            <!-- 2. หน้า สต็อกคงเหลือ (ดึงข้อมูลจากฐานข้อมูล MySQL) -->
            <div id="view-inventory" class="page-view space-y-6">
                <div class="flex justify-between items-center bg-white p-8 rounded-3xl border border-slate-200/80 shadow-xs">
                    <div>
                        <h3 class="text-2xl font-bold text-slate-900">สต็อกสินค้าคงเหลือ</h3>
                        <p class="text-xs text-slate-500 mt-1">ข้อมูลแบบเรียลไทม์จากฐานข้อมูล MySQL (`products` table)</p>
                    </div>
                    <input type="text" placeholder="🔍 ค้นหารหัส SKU..." class="px-5 py-3 border border-slate-200 rounded-2xl text-sm w-80 focus:outline-none focus:border-emerald-600 bg-slate-50 font-medium">
                </div>
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50/80 border-b border-slate-200 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="p-5">SKU</th>
                                <th class="p-5">ชื่อสินค้า</th>
                                <th class="p-5">หมวดหมู่</th>
                                <th class="p-5 text-right">ราคาต้นทุน</th>
                                <th class="p-5 text-right">ราคาขาย</th>
                                <th class="p-5 text-right">คงเหลือ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (count($products) > 0): ?>
                                <?php foreach ($products as$row): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="p-5 font-semibold text-slate-600"><?= htmlspecialchars($row['sku']) ?></td>
                                    <td class="p-5 font-bold text-slate-900"><?= htmlspecialchars($row['name']) ?></td>
                                    <td class="p-5 text-slate-500"><?= htmlspecialchars($row['category']) ?></td>
                                    <td class="p-5 text-right font-medium">฿<?= number_format($row['cost_price'], 2) ?></td>
                                    <td class="p-5 text-right font-medium">฿<?= number_format($row['selling_price'], 2) ?></td>
                                    <td class="p-5 text-right font-bold text-brandGreen"><?= number_format($row['stock_quantity']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="p-12 text-center text-slate-400 font-medium">ไม่พบข้อมูลสินค้าในฐานข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- หน้าอื่นๆ -->
            <div id="view-stockin" class="page-view"><div class="bg-white p-8 rounded-3xl border shadow-xs"><h3 class="text-xl font-bold">บันทึกรับเข้า (Stock In)</h3></div></div>
            <div id="view-stockout" class="page-view"><div class="bg-white p-8 rounded-3xl border shadow-xs"><h3 class="text-xl font-bold">บันทึกจ่ายออก (Stock Out)</h3></div></div>
            <div id="view-expenses" class="page-view"><div class="bg-white p-8 rounded-3xl border shadow-xs"><h3 class="text-xl font-bold">บันทึกค่าใช้จ่ายอื่นๆ</h3></div></div>
            <div id="view-addproduct" class="page-view"><div class="bg-white p-8 rounded-3xl border shadow-xs"><h3 class="text-xl font-bold">เพิ่มสินค้าใหม่</h3></div></div>
            <div id="view-locations" class="page-view"><div class="bg-white p-8 rounded-3xl border shadow-xs"><h3 class="text-xl font-bold">สถานที่ติดตั้ง</h3></div></div>
            <div id="view-orders" class="page-view"><div class="bg-white p-8 rounded-3xl border shadow-xs"><h3 class="text-xl font-bold">สั่งซื้อสินค้า</h3></div></div>
            <div id="view-pending" class="page-view"><div class="bg-white p-8 rounded-3xl border shadow-xs"><h3 class="text-xl font-bold">รอรับสินค้า</h3></div></div>
            <div id="view-employees" class="page-view"><div class="bg-white p-8 rounded-3xl border shadow-xs"><h3 class="text-xl font-bold">รายชื่อพนักงาน</h3></div></div>
            <div id="view-about" class="page-view"><div class="bg-white p-8 rounded-3xl border shadow-xs"><h3 class="text-xl font-bold">เกี่ยวกับระบบ</h3></div></div>
            <div id="view-appsheet" class="page-view"><div class="bg-white p-8 rounded-3xl border shadow-xs"><h3 class="text-xl font-bold">AppSheet Sync</h3></div></div>

        </div>
    </main>

    <!-- ⚙️️ JavaScript สำหรับควบคุมการสลับหน้าจอ (SPA Router) -->
    <script>
        const titles = {
            'calendar': 'Calendar (ปฏิทินงานติดตั้ง)',
            'inventory': 'สต็อกคงเหลือ (Inventory Status)',
            'stockin': 'รับเข้าสินค้า (Stock In)',
            'stockout': 'จ่ายออกสินค้า (Stock Out)',
            'expenses': 'ค่าใช้จ่ายอื่นๆ (Expenses)',
            'addproduct': 'เพิ่มสินค้าใหม่ (Add New Product)',
            'locations': 'สถานที่ติดตั้ง (Installation Locations)',
            'orders': 'สั่งซื้อสินค้า (Purchase Orders)',
            'pending': 'รอรับสินค้า (Pending Shipments)',
            'employees': 'รายชื่อพนักงาน (Employees)',
            'about': 'เกี่ยวกับระบบ (About)',
            'appsheet': 'AppSheet Integration'
        };

        function switchPage(pageId) {
            document.querySelectorAll('.page-view').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.menu-btn').forEach(el => {
                el.classList.remove('bg-brandGreen', 'text-white', 'shadow-md', 'shadow-emerald-900/20');
                el.classList.add('text-slate-300', 'hover:bg-slate-800/60', 'hover:text-white');
            });

            document.getElementById('view-' + pageId).classList.add('active');
            
            const btn = document.getElementById('menu-' + pageId);
            if(btn) {
                btn.classList.remove('text-slate-300', 'hover:bg-slate-800/60', 'hover:text-white');
                btn.classList.add('bg-brandGreen', 'text-white', 'shadow-md', 'shadow-emerald-900/20');
            }

            document.getElementById('page-title').innerText = titles[pageId] || 'System';
        }

        switchPage('calendar');
     
    </script>
</body>
</html>