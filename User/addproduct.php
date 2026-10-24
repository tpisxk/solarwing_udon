<?php
session_start();
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
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $cost_price = trim($_POST['cost_price'] ?? '');
    $selling_price = trim($_POST['selling_price'] ?? '');
    $stock_quantity = trim($_POST['stock_quantity'] ?? '');

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
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Prompt', 'Inter', 'sans-serif'] },
                    colors: { darkSidebar: '#000000', brandGreen: '#059669' }
                }
            }
        }
    </script>
</head>

<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- Sidebar สำหรับมือถือและคอมพิวเตอร์ -->
    <div id="sidebar" class="fixed lg:static inset-y-0 left-0 z-30 transform -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out shrink-0 flex flex-col">
        <?php include 'sidebar.php'; ?>
    </div>

    <!-- Overlay สำหรับมือถือ -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-950/50 z-20 hidden lg:hidden"></div>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-1 flex-col h-screen overflow-hidden bg-slate-100 w-full">

        <!-- Header ด้านบน -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 shrink-0 shadow-sm z-10">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-emerald-600 p-2 rounded-xl focus:outline-none">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h2 class="text-base lg:text-xl font-bold tracking-tight text-slate-800 truncate">เพิ่มสินค้า / อุปกรณ์โซล่าเซลล์</h2>
            </div>
            <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-xs font-semibold flex items-center gap-2 shrink-0 transition">
                <i class="fa-solid fa-house text-emerald-600"></i> หน้าหลัก
            </a>
        </header>

        <!-- Form Content -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8">
            <main class="max-w-4xl w-full mx-auto space-y-6">

                <?php if (!empty($success_msg)): ?>
                    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-600 rounded-2xl text-xs font-bold shadow-sm">
                        <?php echo $success_msg; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                    <div class="p-4 bg-rose-50 border border-rose-200 text-rose-600 rounded-2xl text-xs font-bold shadow-sm">
                        <?php echo $error_msg; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white border border-slate-200 rounded-3xl p-6 lg:p-8 shadow-sm">
                    <form action="" method="POST" class="space-y-6">
                        
                        <!-- ชื่อสินค้า และ รหัสสินค้า -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-2">ชื่อสินค้า / อุปกรณ์ <span class="text-rose-500">*</span></label>
                                <input type="text" name="name" required class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 font-medium transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-2">รหัสสินค้า</label>
                                <input type="text" name="sku" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 font-medium transition">
                            </div>
                        </div>

                        <!-- หน่วยนับ, ราคาทุน, ราคาขาย -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-2">หน่วยนับ</label>
                                <input type="text" name="unit" placeholder="เช่น ชิ้น, แผง" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 font-medium transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-2">ราคาทุน (บาท)</label>
                                <input type="number" step="0.01" name="cost_price" value="0.00" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 font-medium transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-2">ราคาขาย (บาท)</label>
                                <input type="number" step="0.01" name="selling_price" value="0.00" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 font-medium transition">
                            </div>
                        </div>

                        <!-- จำนวนสต็อก -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-2">จำนวน</label>
                            <input type="number" name="stock_quantity" value="0" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs text-slate-800 focus:outline-none focus:border-emerald-600 font-medium transition">
                        </div>

                        <!-- ปุ่มกด -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                            <a href="dashboard_admin.php" class="px-6 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">ยกเลิก</a>
                            <button type="submit" class="px-6 py-3 rounded-2xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition shadow-sm text-xs">บันทึกสินค้า</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </main>

    <!-- Script สำหรับควบคุมการซ่อน/แสดง Sidebar บนมือถือ -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>

</html>