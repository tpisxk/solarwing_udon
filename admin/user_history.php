<?php
session_start();
require_once '../db/conn.php';

// ตรวจสอบสิทธิ์เฉพาะ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type_id'] != 1) {
    header("Location: ../User/login.php");
    exit();
}

$target_user_id = $_GET['id'] ?? null;
if (!$target_user_id) {
    header("Location: manage_users.php");
    exit();
}

$current_page = 'manage_users';

// 1. ดึงข้อมูลผู้ใช้งาน
$stmt_user = $pdo->prepare("SELECT u.*, ut.type_name FROM users u LEFT JOIN user_type ut ON u.user_type_id = ut.id WHERE u.id = ?");
$stmt_user->execute([$target_user_id]);
$target_user = $stmt_user->fetch();

if (!$target_user) {
    header("Location: manage_users.php");
    exit();
}

// 2. ดึงประวัติการเบิกจ่ายอุปกรณ์ของผู้ใช้รายนี้ (STOCK TRANSACTIONS)
$stmt_history = $pdo->prepare("
    SELECT s.*, p.name as product_name, p.sku, i.name as project_name
    FROM stock_transactions s
    LEFT JOIN products p ON s.product_id = p.id
    LEFT JOIN installations i ON s.installation_id = i.id
    WHERE s.user_id = ?
    ORDER BY s.id DESC
");
$stmt_history->execute([$target_user_id]);
$history_list = $stmt_history->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการเบิกจ่าย - <?= htmlspecialchars($target_user['name']) ?> - SolarWing Stock</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style> body { font-family: 'Prompt', sans-serif; } </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden relative">

    <!-- Sidebar สำหรับมือถือ (สไลด์เปิด-ปิด) และคอมพิวเตอร์ (แสดงปกติ) -->
    <div id="sidebarWrapper" class="fixed md:static inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out bg-slate-900 text-slate-300 w-64 shrink-0 flex flex-col shadow-xl md:shadow-none h-full overflow-y-auto">
        <div class="h-16 flex items-center justify-between px-4 border-b border-slate-800 shrink-0 md:hidden">
            <span class="font-bold text-white text-sm">SolarWing Menu</span>
            <button onclick="toggleSidebar()" class="text-slate-400 hover:text-white p-2 rounded-lg">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto">
            <?php 
            if (file_exists('sidebar_admin.php')) {
                include 'sidebar_admin.php'; 
            } elseif (file_exists('sidebar.php')) {
                include 'sidebar.php';
            }
            ?>
        </div>
    </div>

    <!-- Background Overlay เมื่อเปิด Sidebar บนมือถือ -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 z-40 hidden md:hidden"></div>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100 w-full">
        
        <!-- Header ด้านบน -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-6 lg:px-10 shrink-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <!-- ปุ่มแฮมเบอร์เกอร์ เปิด/ปิด Sidebar บนมือถือ -->
                <button onclick="toggleSidebar()" class="md:hidden text-slate-600 hover:text-emerald-600 p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 transition focus:outline-none shrink-0">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <div>
                    <h2 class="text-sm md:text-xl font-bold text-slate-900 tracking-tight truncate">ประวัติการเบิกจ่ายอุปกรณ์</h2>
                    <p class="text-xs text-slate-500 mt-0.5 hidden sm:block">ตรวจสอบรายการเบิก-คืนสินค้าของ <?= htmlspecialchars($target_user['name']) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="manage_users.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 md:px-4 py-2.5 rounded-2xl text-xs md:text-sm font-semibold transition-all flex items-center gap-2 shrink-0">
                    <i class="fa-solid fa-arrow-left text-xs"></i> <span class="hidden sm:inline">กลับหน้ารายชื่อผู้ใช้</span>
                </a>
            </div>
        </header>

        <!-- Content Area -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8 w-full space-y-6">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- การ์ดข้อมูลผู้ใช้งาน -->
                <div class="bg-white rounded-3xl border border-slate-200/85 shadow-sm p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-user-gear"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900"><?= htmlspecialchars($target_user['name']) ?></h3>
                            <p class="text-xs text-slate-500">Username: <span class="font-mono text-slate-700 font-semibold"><?= htmlspecialchars($target_user['username']) ?></span> | สิทธิ์: <span class="text-emerald-600 font-bold"><?= htmlspecialchars($target_user['type_name'] ?? 'User') ?></span></p>
                        </div>
                    </div>
                    <div class="text-xs font-semibold text-slate-500 bg-slate-50 px-4 py-2 rounded-2xl border border-slate-100 self-stretch sm:self-auto text-center">
                        รวมเบิกจ่ายทั้งสิ้น: <span class="text-slate-900 font-bold"><?= count($history_list) ?></span> รายการ
                    </div>
                </div>

                <!-- ตารางรายการประวัติ -->
                <div class="bg-white rounded-3xl border border-slate-200/85 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100 font-bold text-sm text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-history text-emerald-600"></i> รายการประวัติความเคลื่อนไหวสต็อก
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[700px]">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50/70 text-xs text-slate-500 font-semibold">
                                    <th class="p-4">วันที่ / เวลา</th>
                                    <th class="p-4">ประเภท</th>
                                    <th class="p-4">สินค้า (SKU)</th>
                                    <th class="p-4 text-center">จำนวน</th>
                                    <th class="p-4">โครงการ / หน้างาน</th>
                                    <th class="p-4">หมายเหตุ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                <?php if (!empty($history_list)): ?>
                                    <?php foreach ($history_list as $h): ?>
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="p-4 text-slate-500 whitespace-nowrap"><?= htmlspecialchars($h['created_at'] ?? '-') ?></td>
                                            <td class="p-4 whitespace-nowrap">
                                                <?php if (($h['type'] ?? '') === 'out'): ?>
                                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-rose-50 text-rose-600 border border-rose-200">เบิกออก (Out)</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">รับเข้า / คืน (In)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4">
                                                <div class="font-bold text-slate-800"><?= htmlspecialchars($h['product_name'] ?? 'ไม่ระบุ') ?></div>
                                                <div class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($h['sku'] ?? '-') ?></div>
                                            </td>
                                            <td class="p-4 text-center font-bold text-slate-800 whitespace-nowrap">
                                                <?= number_format($h['quantity'] ?? 0) ?>
                                            </td>
                                            <td class="p-4 text-slate-600"><?= htmlspecialchars($h['project_name'] ?? '-') ?></td>
                                            <td class="p-4 text-slate-500"><?= htmlspecialchars($h['note'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-8 text-slate-400">ยังไม่มีประวัติการเบิกจ่ายสินค้า</td>
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
        // ฟังก์ชันควบคุมเปิด/ปิด Sidebar บนมือถือ
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebarWrapper');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>
</html>