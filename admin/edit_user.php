<?php
session_start();
require_once '../db/conn.php';

// ตรวจสอบสิทธิ์เฉพาะ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type_id'] != 1) {
    header("Location: ../User/login.php");
    exit();
}

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header("Location: manage_users.php");
    exit();
}
$current_page = 'edit_user';
$error_message = '';

// ---------------------------------------------------------
// 1. จัดการการลบผู้ใช้งาน (DELETE)
// ---------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    // ป้องกันไม่ให้ Admin ลบบัญชีตัวเอง
    if ($user_id == $_SESSION['user_id']) {
        $error_message = "ไม่สามารถลบบัญชีผู้ใช้ของตนเองได้";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        header("Location: manage_users.php?success=deleted");
        exit();
    }
}

// ---------------------------------------------------------
// 2. จัดการการอัปเดตข้อมูลผู้ใช้ (UPDATE)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'delete')) {
    $name = trim($_POST['name']);
    $user_type_id = intval($_POST['user_type_id']);
    
    // ตรวจสอบว่ามีการกรอกรหัสผ่านใหม่หรือไม่
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, user_type_id = ?, password = ? WHERE id = ?");
        $stmt->execute([$name, $user_type_id, $password, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, user_type_id = ? WHERE id = ?");
        $stmt->execute([$name, $user_type_id, $user_id]);
    }
    
    header("Location: manage_users.php?success=updated");
    exit();
}

// ---------------------------------------------------------
// 3. ดึงข้อมูลผู้ใช้ และ รายการ Roles
// ---------------------------------------------------------
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: manage_users.php");
    exit();
}

$roles = $pdo->query("SELECT * FROM user_type")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลผู้ใช้ - SolarWing Stock</title>
    <link rel="icon" type="image/png" href="../Pic/456456.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style> body { font-family: 'Prompt', sans-serif; } </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans h-screen flex overflow-hidden">

    <!-- ดึง Sidebar ด้านข้าง -->
    <?php 
    if (file_exists('sidebar_admin.php')) {
        include 'sidebar_admin.php'; 
    } elseif (file_exists('sidebar.php')) {
        include 'sidebar.php';
    }
    ?>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-100">
        
        <!-- Header ด้านบน -->
        <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-10 shrink-0 z-10 shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-slate-900 tracking-tight">แก้ไขข้อมูลผู้ใช้งาน</h2>
                <p class="text-sm text-slate-500 mt-0.5">ปรับเปลี่ยนรายละเอียด สิทธิ์การใช้งาน และรหัสผ่านของผู้ใช้</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="manage_users.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-2xl text-sm font-semibold transition-all flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left text-xs"></i> ย้อนกลับ
                </a>
            </div>
        </header>

        <!-- Content Area จัดวางฟอร์มให้อยู่กึ่งกลางหน้าจอ -->
        <div class="flex-1 overflow-y-auto p-8 w-full flex items-center justify-center">
            
            <div class="max-w-md w-full bg-white rounded-3xl border border-slate-200/85 shadow-sm p-8 space-y-6">
                
                <!-- หัวข้อการ์ด -->
                <div class="text-center space-y-1">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-inner">
                        <i class="fas fa-user-edit text-xl"></i>
                    </div>
                    <h1 class="text-xl font-bold text-slate-900">แก้ไขผู้ใช้งาน</h1>
                    <p class="text-xs text-slate-500">Username: <span class="text-slate-800 font-mono font-semibold bg-slate-100 px-2 py-0.5 rounded-md"><?php echo htmlspecialchars($user['username']); ?></span></p>
                </div>

                <!-- แจ้งเตือนข้อผิดพลาด -->
                <?php if (!empty($error_message)): ?>
                    <div class="bg-rose-50 text-rose-700 p-3 rounded-2xl text-xs font-medium border border-rose-200 flex items-center gap-2">
                        <i class="fas fa-exclamation-circle text-base"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- ฟอร์มแก้ไขข้อมูล -->
                <form action="" method="POST" class="space-y-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">ชื่อ-นามสกุล (Name)</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required 
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none transition">
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">สิทธิ์การใช้งาน (Role)</label>
                        <select name="user_type_id" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none transition">
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo ($user['user_type_id'] == $r['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">รหัสผ่านใหม่ <span class="text-slate-400 font-normal">(ปล่อยว่างไว้หากไม่เปลี่ยน)</span></label>
                        <input type="password" name="password" placeholder="••••••••" 
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-xs text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:bg-white focus:outline-none transition">
                    </div>

                    <!-- ปุ่มบันทึก/ยกเลิก -->
                    <div class="flex gap-3 pt-2">
                        <a href="manage_users.php" class="w-1/2 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 text-center text-xs font-semibold rounded-2xl transition border border-slate-200 flex items-center justify-center">
                            ยกเลิก
                        </a>
                        <button type="submit" class="w-1/2 py-3 bg-emerald-600 hover:bg-emerald-500 text-white text-center text-xs font-semibold rounded-2xl shadow-lg shadow-emerald-600/25 transition">
                            บันทึกข้อมูล
                        </button>
                    </div>
                </form>

                <hr class="border-slate-100 my-2">

                <!-- ปุ่มลบผู้ใช้ -->
                <form action="" method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้งานนี้? การกระทำนี้ไม่สามารถยกเลิกได้');">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" 
                            class="w-full py-3 bg-rose-50 hover:bg-rose-100 text-rose-600 text-center text-xs font-semibold rounded-2xl transition border border-rose-200 flex items-center justify-center gap-2 <?php echo ($user_id == $_SESSION['user_id']) ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                            <?php echo ($user_id == $_SESSION['user_id']) ? 'disabled title="ไม่สามารถลบตัวเองได้"' : ''; ?>>
                        <i class="fas fa-trash-alt"></i> ลบผู้ใช้งานนี้
                    </button>
                </form>

            </div>

        </div>
    </main>

</body>
</html>