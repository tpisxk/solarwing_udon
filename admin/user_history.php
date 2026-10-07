<?php
session_start();

require_once '../db/conn.php';

// ป้องกันลูปและตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type_id'] != 1) {
    header("Location: ../login.php");
    exit();
}

$target_user_id = $_GET['id'] ?? null;
if (!$target_user_id) {
    header("Location: manage_users.php");
    exit();
}

// ดึงข้อมูลผู้ใช้เป้าหมาย
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$target_user = $stmt->fetch();

if (!$target_user) {
    header("Location: manage_users.php");
    exit();
}

// ==========================================
// 1. ดึงรายชื่อโครงการทั้งหมดที่มีในระบบ เพื่อทำตัวกรอง (Dropdown)
// ==========================================
$projects_filter = [];
try {
    // ดึงโครงการทั้งหมดจากตาราง installations ให้แสดงใน Dropdown เสมอ
    $stmt_proj = $pdo->query("SELECT id, name AS project_name FROM installations ORDER BY name ASC");
    $projects_filter = $stmt_proj->fetchAll();
} catch (Exception $e) {
    $projects_filter = [];
}

// ==========================================
// 2. รับค่าตัวกรองโครงการที่เลือก
// ==========================================
$selected_project_id = $_GET['project_id'] ?? '';

// ==========================================
// 3. ดึงประวัติการเบิกจ่าย
// ==========================================
$logs = [];
try {
    $sql = "
        SELECT s.*, 
               p.name AS product_name, 
               p.sku, 
               i.name AS project_name 
        FROM stock_transactions s
        LEFT JOIN products p ON s.product_id = p.id 
        LEFT JOIN installations i ON s.installation_id = i.id
        WHERE s.user_id = ? AND s.type = 'out'
    ";
    
    $params = [$target_user_id];

    if (!empty($selected_project_id)) {
        $sql .= " AND s.installation_id = ?";
        $params[] = $selected_project_id;
    }

    $sql .= " ORDER BY s.id DESC";

    $stmt_logs = $pdo->prepare($sql);
    $stmt_logs->execute($params);
    $logs = $stmt_logs->fetchAll();
} catch (Exception $e) {
    $logs = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <title>ประวัติการเบิกจ่ายของ <?php echo htmlspecialchars($target_user['name']); ?> - SolarWing Stock</title>
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
                <h2 class="text-xl font-bold text-slate-900 tracking-tight">ประวัติการเบิกจ่ายอุปกรณ์</h2>
                <p class="text-sm text-slate-500 mt-0.5">ผู้ใช้งาน: <span class="font-bold text-slate-700"><?php echo htmlspecialchars($target_user['name']); ?></span> (Username: <?php echo htmlspecialchars($target_user['username']); ?>)</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="manage_users.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-sm font-semibold transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left text-xs"></i> กลับหน้าจัดการผู้ใช้
                </a>
            </div>
        </header>

        <!-- ส่วนตารางแสดงรายละเอียด -->
        <div class="flex-1 overflow-y-auto p-8">
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm max-w-7xl mx-auto">
                <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-box-open text-blue-600"></i> รายการอุปกรณ์ที่เบิกออกทั้งหมด
                    </h3>
                    
                    <!-- ฟอร์มตัวกรองโครงการ -->
                    <form method="GET" class="flex items-center gap-3">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($target_user_id); ?>">
                        <label for="project_id" class="text-xs text-slate-500 font-medium">กรองตามโครงการ:</label>
                        <select name="project_id" id="project_id" onchange="this.form.submit()" class="text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="">-- โครงการทั้งหมด --</option>
                            <?php foreach ($projects_filter as $proj): ?>
                                <option value="<?php echo $proj['id']; ?>" <?php echo ($selected_project_id == $proj['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($proj['project_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-xs text-slate-400 font-medium ml-3">พบ <?php echo count($logs); ?> รายการ</span>
                    </form>
                </div>
                
                <?php if (!empty($logs)): ?>
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/70 text-xs text-slate-500 font-semibold">
                                <th class="p-4">รหัสรายการ</th>
                                <th class="p-4">ชื่ออุปกรณ์ / SKU</th>
                                <th class="p-4 text-center">จำนวนที่เบิก</th>
                                <th class="p-4">โครงการ / หน้างาน</th>
                                <th class="p-4">เลขที่เอกสาร / หมายเหตุ</th>
                                <th class="p-4 text-center">วันที่ทำรายการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-4 font-mono text-slate-400">#<?php echo $log['id']; ?></td>
                                <td class="p-4">
                                    <!-- ดึงจาก product_name -->
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($log['product_name'] ?? 'ไม่พบชื่อสินค้า'); ?></div>
                                    <?php if (!empty($log['sku'])): ?>
                                        <div class="text-[10px] text-slate-400 font-mono">SKU: <?php echo htmlspecialchars($log['sku']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-semibold bg-emerald-50 text-emerald-600 border border-emerald-200">
                                        <?php echo htmlspecialchars($log['quantity']); ?> ชิ้น
                                    </span>
                                </td>
                                <!-- คอลัมน์โครงการ: ดึงจาก project_name -->
                                <td class="p-4 text-blue-600 font-medium">
                                    <?php echo htmlspecialchars($log['project_name'] ?? 'ไม่ได้ระบุโครงการ'); ?>
                                </td>
                                <td class="p-4 text-slate-600">
                                    <?php 
                                        $ref = $log['reference_no'] ?? '';
                                        $note = $log['note'] ?? '';
                                        echo htmlspecialchars(trim("$ref $note") !== '' ? "$ref $note" : '-');
                                    ?>
                                </td>
                                <td class="p-4 text-center text-slate-500 font-medium">
                                    <?php echo htmlspecialchars($log['created_at']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-16 text-slate-400 text-xs space-y-3">
                        <i class="fas fa-box-open text-4xl text-slate-300"></i>
                        <p class="text-sm font-medium">ไม่พบประวัติการเบิกจ่าย</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

</body>
</html>