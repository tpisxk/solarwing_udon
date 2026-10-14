<?php
session_start();
// ป้องกันการแคชหน้าเว็บ เพื่อให้พอกดปุ่ม Back จะต้องโหลดและเช็คสิทธิ์ใหม่ทันที
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once '../db/conn.php';
require_once '../auth_check.php';

$success_msg = "";
$error_msg = "";

// ตรวจสอบเมื่อมีการกดปุ่มบันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $sku = trim($_POST['sku']);
    $category = trim($_POST['category']);
    $cost_price = trim($_POST['cost_price']);
    $selling_price = trim($_POST['selling_price']);
    $stock_quantity = trim($_POST['stock_quantity']);

    if (!empty($name)) {
        try {
            $sql = "INSERT INTO products (name, sku, category, cost_price, selling_price, stock_quantity, created_at) 
                    VALUES (:name, :sku, :category, :cost_price, :selling_price, :stock_quantity, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':sku' => $sku,
                ':category' => $category,
                ':cost_price' => $cost_price ?: 0.00,
                ':selling_price' => $selling_price ?: 0.00,
                ':stock_quantity' => $stock_quantity ?: 0
            ]);
            $success_msg = "เพิ่มสินค้าเข้าระบบเรียบร้อยแล้ว!";
        } catch (PDOException $e) {
            $error_msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    } else {
        $error_msg = "กรุณากรอกชื่อสินค้า";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มสินค้าใหม่ - SolarStock Pro</title>
    <!-- โหลด Favicon ของโปรเจกต์ -->
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome สำหรับไอคอน -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-slate-50 text-slate-800 font-sans antialiased">

    <!-- โครงสร้างหลัก -->
    <div class="flex h-screen overflow-hidden relative">

        <!-- Sidebar พร้อมฟังก์ชันซ่อน/แสดง -->
        <div id="sidebar" class="fixed lg:static inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out bg-white border-r border-slate-200 w-64 flex-shrink-0 flex flex-col">
            
            

            <!-- เนื้อหา Sidebar (ดึงมาจากไฟล์เดิมหรือแสดงผลเมนู) -->
            <div class="flex-1 overflow-y-auto">
                <?php include 'sidebar_admin.php'; ?>
            </div>
        </div>

        <!-- Overlay มืดๆ เมื่อเปิด Sidebar บนมือถือ -->
        <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-20 hidden lg:hidden"></div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-y-auto w-full">

            <!-- Header ด้านบน -->
            <header class="h-16 lg:h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-8 sticky top-0 z-10 shadow-xs">
                <div class="flex items-center gap-3">
                    <!-- ปุ่มสำหรับกดเปิด Sidebar บนมือถือ -->
                    <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                    
                    <h2 class="text-base lg:text-xl font-bold tracking-tight text-slate-800 truncate">เพิ่มสินค้า / อุปกรณ์โซล่าเซลล์</h2>
                </div>
                <a href="dashboard_admin.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 lg:px-4 lg:py-2.5 rounded-xl lg:rounded-2xl text-xs font-semibold flex items-center gap-2 shrink-0">
                    <i class="fa-solid fa-house text-emerald-600"></i> หน้าหลัก
                </a>
            </header>

            <!-- Form Content -->
            <main class="p-4 lg:p-8 max-w-4xl w-full mx-auto">

                <?php if (!empty($success_msg)): ?>
                    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-600 rounded-xl text-sm">
                        <?php echo $success_msg; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                    <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-600 rounded-xl text-sm">
                        <?php echo $error_msg; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white border border-slate-200 rounded-2xl p-4 lg:p-6 shadow-sm">
                    <form action="" method="POST" class="space-y-4 lg:space-y-6">
                        
                        <!-- ชื่อสินค้า และ รหัสสินค้า -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                            <div>
                                <label class="block text-xs lg:text-sm font-medium text-slate-700 mb-1 lg:mb-2">ชื่อสินค้า / อุปกรณ์ <span class="text-rose-500">*</span></label>
                                <input type="text" name="name" required class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm lg:text-base text-slate-800 focus:outline-none focus:border-emerald-600">
                            </div>
                            <div>
                                <label class="block text-xs lg:text-sm font-medium text-slate-700 mb-1 lg:mb-2">รหัสสินค้า</label>
                                <input type="text" name="sku" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm lg:text-base text-slate-800 focus:outline-none focus:border-emerald-600">
                            </div>
                        </div>

                        <!-- หมวดหมู่, ราคาทุน, ราคาขาย -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-6">
                            <div>
                                <label class="block text-xs lg:text-sm font-medium text-slate-700 mb-1 lg:mb-2">หมวดหมู่สินค้า</label>
                                <input type="text" name="category" placeholder="เช่น แผงโซล่าเซลล์, Inverter" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm lg:text-base text-slate-800 focus:outline-none focus:border-emerald-600">
                            </div>
                            <div>
                                <label class="block text-xs lg:text-sm font-medium text-slate-700 mb-1 lg:mb-2">ราคาทุน (บาท)</label>
                                <input type="number" step="0.01" name="cost_price" value="0.00" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm lg:text-base text-slate-800 focus:outline-none focus:border-emerald-600">
                            </div>
                            <div>
                                <label class="block text-xs lg:text-sm font-medium text-slate-700 mb-1 lg:mb-2">ราคาขาย (บาท)</label>
                                <input type="number" step="0.01" name="selling_price" value="0.00" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm lg:text-base text-slate-800 focus:outline-none focus:border-emerald-600">
                            </div>
                        </div>

                        <!-- จำนวนสต็อก -->
                        <div>
                            <label class="block text-xs lg:text-sm font-medium text-slate-700 mb-1 lg:mb-2">จำนวน</label>
                            <input type="number" name="stock_quantity" value="0" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm lg:text-base text-slate-800 focus:outline-none focus:border-emerald-600">
                        </div>

                        <!-- ปุ่มกด -->
                        <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-slate-100">
                            <a href="dashboard_admin.php" class="w-full sm:w-auto px-6 py-2.5 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50 text-center transition text-sm">ยกเลิก</a>
                            <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-emerald-600 text-white font-medium hover:bg-emerald-500 transition shadow-sm text-sm">บันทึกสินค้า</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- Script สำหรับควบคุมการซ่อน/แสดง Sidebar ทั้งมือถือและคอมพิวเตอร์ -->
    <script>
        // ควบคุมสำหรับมือถือ (Slide เปิด-ปิด)
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        // ควบคุมสำหรับคอมพิวเตอร์ (ซ่อนแถบเมนูให้พับเก็บซ้ายสุด)
        function toggleDesktopSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('lg:-ml-64');
            sidebar.classList.toggle('lg:opacity-0');
        }
    </script>
</body>

</html>