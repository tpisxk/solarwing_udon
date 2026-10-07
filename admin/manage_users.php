<?php
session_start();
require_once '../db/conn.php';

// ป้องกันลูป: ตรวจสอบว่าถ้าไม่มี session หรือไม่ได้เป็น admin ให้ไปหน้า login นอกโฟลเดอร์ admin
if (!isset($_SESSION['user_id']) || (isset($_SESSION['user_type_id']) && $_SESSION['user_type_id'] != 1)) {
    // เช็คไม่ให้ redirect ซ้ำถ้าอยู่ที่หน้า login อยู่แล้ว
    if (basename($_SERVER['PHP_SELF']) != 'login.php') {
        header("Location: ../login.php"); // ปรับ path ไปยังหน้า login จริงของคุณ เช่น login.php หรือ ../User/login.php
        exit();
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด JOIN กับตาราง user_type
$stmt = $pdo->query("
    SELECT u.*, ut.type_name 
    FROM users u 
    LEFT JOIN user_type ut ON u.user_type_id = ut.id
    ORDER BY u.id ASC
");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <title>จัดการผู้ใช้งานระบบ - SolarWing Stock</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { font-family: 'Prompt', sans-serif; } </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex">

    <!-- Sidebar ด้านข้าง -->
    <aside class="w-64 bg-slate-900 text-slate-300 hidden md:block shrink-0 min-h-screen">
        <?php include 'sidebar_admin.php'; ?>
    </aside>

    <!-- เนื้อหาหลัก -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-100">
        
        <!-- ส่วนหัวด้านบน -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-10 shrink-0 z-10 shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-slate-900 tracking-tight">จัดการข้อมูลผู้ใช้งานระบบ</h2>
                <p class="text-sm text-slate-500 mt-0.5">ระบบจัดการและตรวจสอบสิทธิ์การใช้งาน รวมถึงประวัติการเบิกจ่ายอุปกรณ์โซล่าเซลล์</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="calendar.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-sm font-semibold transition-all flex items-center gap-2">
                    <i class="fa-solid fa-calendar-days text-xs"></i> ไปยังปฏิทินงาน
                </a>
            </div>
        </header>

        <!-- ส่วนเนื้อหาตาราง -->
        <div class="flex-1 overflow-y-auto p-8">
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm max-w-7xl mx-auto">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-users text-emerald-600"></i> รายชื่อผู้ใช้งานทั้งหมดในระบบ
                    </h3>
                    <span class="text-xs text-slate-400 font-medium">ทั้งหมด <?php echo count($users); ?> บัญชี</span>
                </div>
                
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/70 text-xs text-slate-500 font-semibold">
                            <th class="p-4">ID</th>
                            <th class="p-4">ชื่อ-นามสกุล</th>
                            <th class="p-4">ชื่อผู้ใช้ (Username)</th>
                            <th class="p-4">สิทธิ์การใช้งาน</th>
                            <th class="p-4 text-center">ประวัติการเบิกจ่าย</th>
                            <th class="p-4 text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-4 font-mono text-slate-400">#<?php echo $u['id']; ?></td>
                            <td class="p-4 font-bold text-slate-800"><?php echo htmlspecialchars($u['name']); ?></td>
                            <td class="p-4 text-slate-600"><?php echo htmlspecialchars($u['username']); ?></td>
                            <td class="p-4">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-emerald-50 text-emerald-600 border border-emerald-200">
                                    <?php echo htmlspecialchars($u['type_name'] ?? 'User'); ?>
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                <a href="user_history.php?id=<?php echo $u['id']; ?>" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-xl font-semibold transition border border-blue-200 inline-flex items-center gap-1">
                                    <i class="fas fa-box-open"></i> ดูรายการเบิกจ่าย
                                </a>
                            </td>
                            <td class="p-4 text-center">
                                <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-xl font-semibold transition border border-amber-200 inline-flex items-center gap-1">
                                    <i class="fas fa-edit"></i> แก้ไข
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>